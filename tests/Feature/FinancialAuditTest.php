<?php

namespace Tests\Feature;

use App\Models\AccountTransfer;
use App\Models\CashClosing;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseStatus;
use App\Models\FinancialAccount;
use App\Models\FinancialMovement;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

/**
 * Auditoría completa del sistema financiero.
 *
 * Cubre todas las reglas de negocio:
 * - Ventas/órdenes requieren caja abierta
 * - Bloqueo por cierre contable (CashClosing)
 * - Bloqueo por caja cerrada (CashRegister)
 * - Gastos y pagos de gastos
 * - Transferencias entre cuentas
 * - Apertura de caja con monto mínimo
 * - Aislamiento multi-tenant
 * - Autorización/políticas
 */
class FinancialAuditTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected FinancialAccount $bankAccount;
    protected FinancialAccount $digitalAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();

        $this->bankAccount = FinancialAccount::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Cuenta BCP',
            'type'          => 'bank',
            'currency'      => 'PEN',
            'is_active'     => true,
        ]);

        $this->digitalAccount = FinancialAccount::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Yape',
            'type'          => 'digital',
            'currency'      => 'PEN',
            'is_active'     => true,
        ]);
    }

    /* ================================================================
     * 1. VENTAS — Requieren caja abierta y sin cierre contable
     * ================================================================ */

    public function test_sale_requires_open_cash_register(): void
    {
        // Crear una orden cerrada directamente en DB (sin necesidad de caja abierta)
        $product = $this->getProductA();
        $order = Order::create([
            'restaurant_id' => $this->restaurantId,
            'user_id'       => $this->adminUser->id,
            'channel'       => 'takeaway',
            'status'        => Order::STATUS_CLOSED,
            'opened_at'     => now(),
            'closed_at'     => now(),
            'subtotal'      => 50.00,
            'total'         => 50.00,
        ]);

        // Sin caja abierta → pago debe fallar
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$order->id}/pay", [
                'payments' => [
                    [
                        'payment_method_id'    => $this->cashPaymentMethod->id,
                        'financial_account_id' => $this->cashFinancialAccount->id,
                        'amount'               => 50.00,
                    ],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_sale_blocked_by_accounting_close(): void
    {
        $crId = $this->openCashRegister();
        $order = $this->createAndCloseOrder();

        // Crear cierre contable directamente en BD (la caja sigue abierta)
        CashClosing::create([
            'restaurant_id' => $this->restaurantId,
            'date'          => now()->toDateString(),
            'closed_by'     => $this->adminUser->id,
            'closed_at'     => now(),
            'total_sales'   => 0,
            'total_expenses' => 0,
            'net_total'     => 0,
        ]);

        // Intentar pagar → debe fallar por cierre contable (422)
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$order['id']}/pay", [
                'payments' => [
                    [
                        'payment_method_id'    => $this->cashPaymentMethod->id,
                        'financial_account_id' => $this->cashFinancialAccount->id,
                        'amount'               => $order['total'],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['No se puede registrar una venta: ya existe cierre contable para la fecha de hoy.']);
    }

    public function test_cannot_pay_already_paid_order(): void
    {
        $this->openCashRegister();
        $order = $this->createAndCloseOrder();
        $this->payOrder($order['id'], $order['total']);

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$order['id']}/pay", [
                'payments' => [
                    [
                        'payment_method_id'    => $this->cashPaymentMethod->id,
                        'financial_account_id' => $this->cashFinancialAccount->id,
                        'amount'               => $order['total'],
                    ],
                ],
            ])
            ->assertStatus(409);
    }

    public function test_sale_generates_financial_movement(): void
    {
        $this->openCashRegister();
        $order = $this->createAndCloseOrder();
        $this->payOrder($order['id'], $order['total']);

        $this->assertDatabaseHas('financial_movements', [
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashFinancialAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'sale_payment',
        ]);
    }

    /* ================================================================
     * 2. ÓRDENES — Requieren caja abierta
     * ================================================================ */

    public function test_order_creation_requires_open_register(): void
    {
        // Sin caja abierta
        $product = $this->getProductA();

        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_cancel_order_requires_open_register(): void
    {
        // Crear una orden abierta directamente en DB
        $order = Order::create([
            'restaurant_id' => $this->restaurantId,
            'user_id'       => $this->adminUser->id,
            'channel'       => 'takeaway',
            'status'        => Order::STATUS_OPEN,
            'opened_at'     => now(),
        ]);

        // Sin caja abierta → cancelar debe fallar
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$order->id}/cancel", [
                'cancellation_reason' => 'Test cancel sin caja',
            ])
            ->assertStatus(422);
    }

    public function test_add_item_to_order_requires_open_register(): void
    {
        $product = $this->getProductA();
        $productB = $this->getProductB();

        // Crear una orden abierta directamente en DB
        $order = Order::create([
            'restaurant_id' => $this->restaurantId,
            'user_id'       => $this->adminUser->id,
            'channel'       => 'takeaway',
            'status'        => Order::STATUS_OPEN,
            'opened_at'     => now(),
        ]);

        // Sin caja abierta → agregar item debe fallar (admin es dueño de la orden)
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$order->id}/items", [
                'product_id' => $productB->id,
                'quantity'   => 1,
            ])
            ->assertStatus(422);
    }

    /* ================================================================
     * 3. GASTOS — Bloqueo por cierre contable y caja cerrada
     * ================================================================ */

    public function test_expense_creation_blocked_on_closed_register_date(): void
    {
        $crId = $this->openCashRegister();

        // Cerrar caja
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", ['closing_amount_real' => 200]);

        // Intentar crear gasto con fecha de hoy (caja cerrada)
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->createExpenseCategory(),
                'expense_status_id'   => $this->getPendingStatusId(),
                'amount'              => 50.00,
                'description'         => 'Test blocked',
                'expense_date'        => now()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_expense_creation_blocked_by_accounting_close(): void
    {
        $crId = $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", ['closing_amount_real' => 200]);

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', ['date' => now()->toDateString()]);

        // Intentar crear gasto en fecha con cierre contable
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->createExpenseCategory(),
                'expense_status_id'   => $this->getPendingStatusId(),
                'amount'              => 50.00,
                'description'         => 'Test blocked by closing',
                'expense_date'        => now()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_paid_expense_cannot_be_deleted(): void
    {
        $this->openCashRegister();

        $expense = $this->createExpenseWithPayment();

        // Marcar como pagado
        $paidStatus = ExpenseStatus::where('slug', 'paid')->firstOrFail();
        $this->withHeaders($this->adminHeaders())
            ->putJson("/api/expenses/{$expense['id']}", [
                'expense_category_id' => $expense['category_id'],
                'expense_status_id'   => $paidStatus->id,
                'amount'              => $expense['amount'],
                'description'         => 'Test paid',
                'expense_date'        => now()->toDateString(),
            ]);

        // Intentar eliminar
        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/expenses/{$expense['id']}")
            ->assertStatus(422);
    }

    /* ================================================================
     * 4. PAGOS DE GASTOS — Validaciones
     * ================================================================ */

    public function test_expense_payment_blocked_on_closed_register(): void
    {
        $crId = $this->openCashRegister();

        $expenseRes = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->createExpenseCategory(),
                'expense_status_id'   => $this->getPendingStatusId(),
                'amount'              => 100.00,
                'description'         => 'Test',
                'expense_date'        => now()->toDateString(),
            ]);
        $expenseId = $expenseRes->json('data.id');

        // Cerrar caja (need to close orders first, but we have no orders)
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", ['closing_amount_real' => 200]);

        // Intentar registrar pago
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => $this->cashPaymentMethod->id,
                'financial_account_id' => $this->cashFinancialAccount->id,
                'amount'               => 100.00,
                'paid_at'              => now()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_expense_payment_date_cannot_be_before_expense_date(): void
    {
        $this->openCashRegister();

        $futureDate = now()->addDay()->toDateString();
        $expenseRes = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->createExpenseCategory(),
                'expense_status_id'   => $this->getPendingStatusId(),
                'amount'              => 50.00,
                'description'         => 'Test',
                'expense_date'        => now()->toDateString(),
            ]);
        $expenseId = $expenseRes->json('data.id');

        // Intentar pagar con fecha anterior
        $yesterday = now()->subDay()->toDateString();
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => $this->cashPaymentMethod->id,
                'financial_account_id' => $this->cashFinancialAccount->id,
                'amount'               => 50.00,
                'paid_at'              => $yesterday,
            ])
            ->assertStatus(422);
    }

    public function test_expense_payment_cannot_exceed_expense_amount(): void
    {
        $this->openCashRegister();

        $expenseRes = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->createExpenseCategory(),
                'expense_status_id'   => $this->getPendingStatusId(),
                'amount'              => 50.00,
                'description'         => 'Test',
                'expense_date'        => now()->toDateString(),
            ]);
        $expenseId = $expenseRes->json('data.id');

        // Intentar pagar más del total
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => $this->cashPaymentMethod->id,
                'financial_account_id' => $this->cashFinancialAccount->id,
                'amount'               => 100.00,
                'paid_at'              => now()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_expense_payment_generates_financial_movement(): void
    {
        $this->openCashRegister();

        $expenseRes = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->createExpenseCategory(),
                'expense_status_id'   => $this->getPendingStatusId(),
                'amount'              => 50.00,
                'description'         => 'Test movement',
                'expense_date'        => now()->toDateString(),
            ]);
        $expenseId = $expenseRes->json('data.id');

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => $this->cashPaymentMethod->id,
                'financial_account_id' => $this->cashFinancialAccount->id,
                'amount'               => 50.00,
                'paid_at'              => now()->toDateString(),
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('financial_movements', [
            'restaurant_id'  => $this->restaurantId,
            'type'           => 'expense',
            'reference_type' => 'expense_payment',
            'amount'         => 50.00,
        ]);
    }

    /* ================================================================
     * 5. TRANSFERENCIAS — Bloqueo por cierre contable y caja cerrada
     * ================================================================ */

    public function test_transfer_blocked_by_accounting_close(): void
    {
        // Dar saldo a la cuenta bancaria
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->bankAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 999,
            'amount'               => 500,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $crId = $this->openCashRegister();

        // Cerrar caja y hacer cierre contable
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", ['closing_amount_real' => 200]);

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', ['date' => now()->toDateString()]);

        // Intentar transferencia (banco → digital, sin involucrar efectivo pero cierre contable bloquea todo)
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->bankAccount->id,
                'to_account_id'   => $this->digitalAccount->id,
                'amount'          => 100,
                'description'     => 'Blocked transfer',
            ])
            ->assertStatus(422);
    }

    public function test_transfer_blocked_when_cash_register_closed_and_cash_account(): void
    {
        // Dar saldo
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashFinancialAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 999,
            'amount'               => 500,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $crId = $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", ['closing_amount_real' => 200]);

        // Transferencia que involucra cuenta cash con caja cerrada
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->cashFinancialAccount->id,
                'to_account_id'   => $this->bankAccount->id,
                'amount'          => 100,
                'description'     => 'Blocked cash transfer',
            ])
            ->assertStatus(422);
    }

    public function test_transfer_insufficient_balance_fails(): void
    {
        $this->openCashRegister();

        // Intentar transferir más de lo disponible
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->bankAccount->id,
                'to_account_id'   => $this->digitalAccount->id,
                'amount'          => 99999,
            ])
            ->assertStatus(422);
    }

    public function test_transfer_creates_movement_pair(): void
    {
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->bankAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 999,
            'amount'               => 1000,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->bankAccount->id,
                'to_account_id'   => $this->digitalAccount->id,
                'amount'          => 200,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('financial_movements', [
            'financial_account_id' => $this->bankAccount->id,
            'type'                 => 'transfer_out',
            'amount'               => 200,
        ]);

        $this->assertDatabaseHas('financial_movements', [
            'financial_account_id' => $this->digitalAccount->id,
            'type'                 => 'transfer_in',
            'amount'               => 200,
        ]);
    }

    public function test_transfer_edit_blocked_after_5_days(): void
    {
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->bankAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 999,
            'amount'               => 1000,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $this->openCashRegister();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->bankAccount->id,
                'to_account_id'   => $this->digitalAccount->id,
                'amount'          => 100,
            ]);
        $transferId = $response->json('data.id');

        // Manipular fecha de creación a 6 días atrás
        AccountTransfer::where('id', $transferId)
            ->update(['created_at' => now()->subDays(6)]);

        $this->withHeaders($this->adminHeaders())
            ->putJson("/api/account-transfers/{$transferId}", [
                'from_account_id' => $this->bankAccount->id,
                'to_account_id'   => $this->digitalAccount->id,
                'amount'          => 150,
            ])
            ->assertStatus(422);
    }

    public function test_only_admin_general_can_delete_transfer(): void
    {
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->bankAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 999,
            'amount'               => 1000,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $this->openCashRegister();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->bankAccount->id,
                'to_account_id'   => $this->digitalAccount->id,
                'amount'          => 100,
            ]);
        $transferId = $response->json('data.id');

        // admin_restaurante no puede eliminar
        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/account-transfers/{$transferId}")
            ->assertStatus(403);

        // admin_general sí puede
        $this->withHeaders($this->adminGeneralHeaders())
            ->deleteJson("/api/account-transfers/{$transferId}")
            ->assertOk();
    }

    /* ================================================================
     * 6. APERTURA DE CAJA
     * ================================================================ */

    public function test_cannot_open_register_twice_same_day(): void
    {
        $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(409);
    }

    public function test_opening_amount_must_match_previous_closing(): void
    {
        $crId = $this->openCashRegister(200);

        // Cerrar con monto real de 300
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", ['closing_amount_real' => 300]);

        // Simular siguiente día
        CashRegister::where('id', $crId)->update(['date' => now()->subDay()]);

        // Intentar abrir con monto menor al cierre anterior
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(422);

        // Abrir con monto igual o mayor (debería funcionar si hay saldo)
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 300])
            ->assertStatus(201);
    }

    public function test_cannot_open_register_on_accounting_closed_date(): void
    {
        // Crear cierre contable directamente en BD (sin necesidad de caja previa)
        CashClosing::create([
            'restaurant_id' => $this->restaurantId,
            'date'          => now()->toDateString(),
            'closed_by'     => $this->adminUser->id,
            'closed_at'     => now(),
            'total_sales'   => 0,
            'total_expenses' => 0,
            'net_total'     => 0,
        ]);

        // Intentar abrir caja el mismo día que tiene cierre contable
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 200])
            ->assertStatus(422);
    }

    public function test_close_register_blocks_when_pending_orders_exist(): void
    {
        $crId = $this->openCashRegister();
        $product = $this->getProductA();

        // Crear orden (queda abierta)
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);

        // Intentar cerrar caja
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", ['closing_amount_real' => 200])
            ->assertStatus(422);
    }

    /* ================================================================
     * 7. CIERRE CONTABLE — Bloquea operaciones futuras
     * ================================================================ */

    public function test_accounting_close_requires_closed_register(): void
    {
        $this->openCashRegister();

        // Intentar cierre contable con caja abierta
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', ['date' => now()->toDateString()])
            ->assertStatus(422);
    }

    public function test_accounting_close_cannot_be_duplicated(): void
    {
        $crId = $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", ['closing_amount_real' => 200]);

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', ['date' => now()->toDateString()])
            ->assertStatus(201);

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', ['date' => now()->toDateString()])
            ->assertStatus(409);
    }

    /* ================================================================
     * 8. CUENTAS FINANCIERAS — No eliminar con movimientos
     * ================================================================ */

    public function test_cannot_delete_account_with_movements(): void
    {
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->bankAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 1,
            'amount'               => 100,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/financial-accounts/{$this->bankAccount->id}")
            ->assertStatus(422);
    }

    public function test_can_delete_account_without_movements(): void
    {
        $emptyAccount = FinancialAccount::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Temporal',
            'type'          => 'bank',
        ]);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/financial-accounts/{$emptyAccount->id}")
            ->assertOk();
    }

    /* ================================================================
     * 9. AUTORIZACIÓN — Políticas de acceso
     * ================================================================ */

    public function test_mozo_cannot_access_financial_accounts(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/financial-accounts')
            ->assertStatus(403);
    }

    public function test_mozo_cannot_create_financial_account(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/financial-accounts', [
                'name' => 'Prueba',
                'type' => 'bank',
            ])
            ->assertStatus(403);
    }

    public function test_mozo_cannot_list_transfers(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/account-transfers')
            ->assertStatus(403);
    }

    public function test_mozo_cannot_list_financial_movements(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/financial-movements')
            ->assertStatus(403);
    }

    public function test_caja_can_list_financial_accounts(): void
    {
        $this->withHeaders($this->cajaHeaders())
            ->getJson('/api/financial-accounts')
            ->assertOk();
    }

    public function test_caja_cannot_create_financial_account(): void
    {
        $this->withHeaders($this->cajaHeaders())
            ->postJson('/api/financial-accounts', [
                'name' => 'Prueba',
                'type' => 'bank',
            ])
            ->assertStatus(403);
    }

    public function test_caja_can_list_transfers(): void
    {
        $this->withHeaders($this->cajaHeaders())
            ->getJson('/api/account-transfers')
            ->assertOk();
    }

    public function test_admin_can_create_transfer(): void
    {
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->bankAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 999,
            'amount'               => 1000,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->bankAccount->id,
                'to_account_id'   => $this->digitalAccount->id,
                'amount'          => 100,
            ])
            ->assertStatus(201);
    }

    public function test_mozo_cannot_perform_cash_closing(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/cash-closings', ['date' => now()->toDateString()])
            ->assertStatus(403);
    }

    /* ================================================================
     * 10. MULTI-TENANT — Aislamiento entre restaurantes
     * ================================================================ */

    public function test_restaurant_b_cannot_see_restaurant_a_financial_data(): void
    {
        $restaurantB = Restaurant::create(['name' => 'Restaurant B', 'ruc' => '20888222333']);
        $userB = User::factory()->create(['name' => 'Admin B']);
        $role = Role::where('slug', 'admin_restaurante')->firstOrFail();

        DB::table('restaurant_user')->insert([
            'restaurant_id' => $restaurantB->id,
            'user_id'       => $userB->id,
            'role_id'       => $role->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $tokenB = $userB->createToken('test')->plainTextToken;
        $headersB = [
            'Authorization'   => "Bearer {$tokenB}",
            'X-Restaurant-Id' => (string) $restaurantB->id,
        ];

        // No ver cuentas de Restaurant A
        $response = $this->withHeaders($headersB)->getJson('/api/financial-accounts');
        $response->assertOk();
        $this->assertEmpty($response->json('data'));

        // No ver transferencias de Restaurant A
        $response = $this->withHeaders($headersB)->getJson('/api/account-transfers');
        $response->assertOk();
        $this->assertEquals(0, $response->json('total'));

        // No ver movimientos de Restaurant A
        $response = $this->withHeaders($headersB)->getJson('/api/financial-movements');
        $response->assertOk();
        $this->assertEquals(0, $response->json('total'));
    }

    public function test_restaurant_b_cannot_use_restaurant_a_accounts_for_transfer(): void
    {
        $restaurantB = Restaurant::create(['name' => 'Restaurant B', 'ruc' => '20888222333']);
        $restaurantB->update(['financial_initialized_at' => now()]);

        $userB = User::factory()->create(['name' => 'Admin B']);
        $role = Role::where('slug', 'admin_restaurante')->firstOrFail();

        DB::table('restaurant_user')->insert([
            'restaurant_id' => $restaurantB->id,
            'user_id'       => $userB->id,
            'role_id'       => $role->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Crear cuentas para B
        $accountB1 = FinancialAccount::create([
            'restaurant_id' => $restaurantB->id,
            'name' => 'CajaB', 'type' => 'cash', 'is_active' => true,
        ]);
        $accountB2 = FinancialAccount::create([
            'restaurant_id' => $restaurantB->id,
            'name' => 'BancoB', 'type' => 'bank', 'is_active' => true,
        ]);

        $tokenB = $userB->createToken('test')->plainTextToken;
        $headersB = [
            'Authorization'   => "Bearer {$tokenB}",
            'X-Restaurant-Id' => (string) $restaurantB->id,
        ];

        // Intentar usar cuentas del restaurante A
        $this->withHeaders($headersB)
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->cashFinancialAccount->id, // cuenta de A
                'to_account_id'   => $accountB2->id,
                'amount'          => 100,
            ])
            ->assertStatus(404); // findOrFail scoped to restaurant B should fail
    }

    /* ================================================================
     * 11. FLUJO COMPLETO — Full day workflow
     * ================================================================ */

    public function test_full_day_financial_flow(): void
    {
        // 1. Abrir caja
        $crId = $this->openCashRegister(200);

        // 2. Crear y pagar orden
        $order = $this->createAndCloseOrder();
        $this->payOrder($order['id'], $order['total']);

        // 3. Crear gasto y pagar
        $catId = $this->createExpenseCategory();
        $expenseRes = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $catId,
                'expense_status_id'   => $this->getPendingStatusId(),
                'amount'              => 30.00,
                'description'         => 'Insumos',
                'expense_date'        => now()->toDateString(),
            ]);
        $expenseId = $expenseRes->json('data.id');

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => $this->cashPaymentMethod->id,
                'financial_account_id' => $this->cashFinancialAccount->id,
                'amount'               => 30.00,
                'paid_at'              => now()->toDateString(),
            ]);

        // 4. Cerrar caja
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", ['closing_amount_real' => 220])
            ->assertOk();

        // 5. Hacer cierre contable
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', ['date' => now()->toDateString()])
            ->assertStatus(201);

        // 6. Verificar que no se puede abrir caja del mismo día (409 = ya existe registro)
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 200])
            ->assertStatus(409);

        // 7. Verificar que el cierre contable tiene los totales correctos
        $closings = CashClosing::where('restaurant_id', $this->restaurantId)->first();
        $this->assertEquals($order['total'], $closings->total_sales);
    }

    /* ================================================================
     * HELPERS
     * ================================================================ */

    protected function createExpenseCategory(): int
    {
        $cat = ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Insumos Test ' . uniqid(),
        ]);
        return $cat->id;
    }

    protected function getPendingStatusId(): int
    {
        return ExpenseStatus::where('slug', 'pending')->firstOrFail()->id;
    }

    protected function createExpenseWithPayment(): array
    {
        $catId = $this->createExpenseCategory();
        $pendingId = $this->getPendingStatusId();

        $expenseRes = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $catId,
                'expense_status_id'   => $pendingId,
                'amount'              => 100.00,
                'description'         => 'Test with payment',
                'expense_date'        => now()->toDateString(),
            ]);

        $expenseId = $expenseRes->json('data.id');

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => $this->cashPaymentMethod->id,
                'financial_account_id' => $this->cashFinancialAccount->id,
                'amount'               => 100.00,
                'paid_at'              => now()->toDateString(),
            ]);

        return ['id' => $expenseId, 'amount' => 100.00, 'category_id' => $catId];
    }
}
