<?php

namespace Tests\Feature;

use App\Models\FinancialAccount;
use App\Models\FinancialMovement;
use App\Models\AccountTransfer;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class FinancialModuleTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected FinancialAccount $cashAccount;
    protected FinancialAccount $digitalAccount;
    protected FinancialAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();

        // Crear cuentas financieras
        $this->cashAccount = FinancialAccount::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Caja Física',
            'type'          => 'cash',
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

        $this->bankAccount = FinancialAccount::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Cuenta BCP',
            'type'          => 'bank',
            'currency'      => 'PEN',
            'is_active'     => true,
        ]);
    }

    /* ================================================================
     * FINANCIAL ACCOUNTS CRUD
     * ================================================================ */

    public function test_admin_can_list_financial_accounts(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/financial-accounts');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'type', 'currency', 'is_active']]]);

        // 3 from setUp() + 1 from setUpRestaurant() trait (Caja Principal)
        $this->assertCount(4, $response->json('data'));
    }

    public function test_admin_can_create_financial_account(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/financial-accounts', [
                'name' => 'Plin',
                'type' => 'digital',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Plin', 'type' => 'digital']);

        $this->assertDatabaseHas('financial_accounts', [
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Plin',
            'type'          => 'digital',
        ]);
    }

    public function test_create_account_validation_rejects_invalid_type(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/financial-accounts', [
                'name' => 'Cuenta X',
                'type' => 'crypto', // invalid
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_update_financial_account(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/financial-accounts/{$this->cashAccount->id}", [
                'name' => 'Caja Efectivo Actualizada',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Caja Efectivo Actualizada']);
    }

    public function test_admin_can_delete_account_without_movements(): void
    {
        $emptyAccount = FinancialAccount::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Temporal',
            'type'          => 'cash',
        ]);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/financial-accounts/{$emptyAccount->id}")
            ->assertOk();

        $this->assertSoftDeleted('financial_accounts', ['id' => $emptyAccount->id]);
    }

    public function test_cannot_delete_account_with_movements(): void
    {
        // Crear un movimiento
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 1,
            'amount'               => 100,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/financial-accounts/{$this->cashAccount->id}")
            ->assertStatus(422);
    }

    public function test_admin_can_view_account_with_balance(): void
    {
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 1,
            'amount'               => 500,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/financial-accounts/{$this->cashAccount->id}");

        $response->assertOk()
            ->assertJsonFragment(['balance' => 500.0]);
    }

    /* ================================================================
     * BALANCES
     * ================================================================ */

    public function test_admin_can_get_consolidated_balances(): void
    {
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 1,
            'amount'               => 1000,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->digitalAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 2,
            'amount'               => 300,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/financial-accounts/balances');

        $response->assertOk()
            // 200 (Caja Principal initial_balance) + 1000 (Caja Física income) + 300 (Yape income)
            ->assertJsonFragment(['total' => 1500.0])
            ->assertJsonPath('data.by_type.cash', fn ($v) => (float) $v === 1200.0)
            ->assertJsonPath('data.by_type.digital', fn ($v) => (float) $v === 300.0);
    }

    /* ================================================================
     * SALE → FINANCIAL MOVEMENT (income)
     * ================================================================ */

    public function test_sale_payment_generates_financial_movement(): void
    {
        $this->openCashRegister();
        $order = $this->createAndCloseOrder();

        $response = $this->withHeaders($this->cajaHeaders())
            ->postJson("/api/orders/{$order['id']}/pay", [
                'payments' => [
                    [
                        'payment_method_id'    => $this->cashPaymentMethod->id,
                        'financial_account_id' => $this->cashAccount->id,
                        'amount'               => $order['total'],
                    ],
                ],
            ]);

        $response->assertStatus(201);

        // Verificar que se creó el movimiento financiero
        $this->assertDatabaseHas('financial_movements', [
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'sale_payment',
            'amount'               => $order['total'],
        ]);
    }

    public function test_sale_without_account_fails_validation(): void
    {
        $this->openCashRegister();
        $order = $this->createAndCloseOrder();

        $response = $this->withHeaders($this->cajaHeaders())
            ->postJson("/api/orders/{$order['id']}/pay", [
                'payments' => [
                    [
                        'payment_method_id' => $this->cashPaymentMethod->id,
                        'amount'            => $order['total'],
                    ],
                ],
            ]);

        // financial_account_id is now required
        $response->assertStatus(422);
    }

    /* ================================================================
     * EXPENSE PAYMENT → FINANCIAL MOVEMENT (expense)
     * ================================================================ */

    public function test_expense_payment_generates_financial_movement(): void
    {
        $this->openCashRegister();

        // Crear gasto
        $expenseRes = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->createExpenseCategory(),
                'expense_status_id'   => $this->getPendingStatusId(),
                'amount'              => 50.00,
                'description'         => 'Compra de insumos',
                'expense_date'        => now()->toDateString(),
            ]);

        $expenseRes->assertStatus(201);
        $expenseId = $expenseRes->json('data.id');

        // Registrar pago con cuenta financiera
        $payRes = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => $this->cashPaymentMethod->id,
                'financial_account_id' => $this->cashAccount->id,
                'amount'               => 50.00,
                'paid_at'              => now()->toDateString(),
            ]);

        $payRes->assertStatus(201);

        $this->assertDatabaseHas('financial_movements', [
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => 'expense',
            'reference_type'       => 'expense_payment',
            'amount'               => 50.0,
        ]);
    }

    /* ================================================================
     * TRANSFERS
     * ================================================================ */

    public function test_admin_can_create_transfer(): void
    {
        // Seed balance in origin
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 1,
            'amount'               => 500,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->cashAccount->id,
                'to_account_id'   => $this->bankAccount->id,
                'amount'          => 200,
                'description'     => 'Depósito a banco',
            ]);

        $response->assertStatus(201);

        // Debe haber 2 movimientos: transfer_out + transfer_in
        $this->assertDatabaseHas('financial_movements', [
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => 'transfer_out',
            'amount'               => 200,
        ]);

        $this->assertDatabaseHas('financial_movements', [
            'financial_account_id' => $this->bankAccount->id,
            'type'                 => 'transfer_in',
            'amount'               => 200,
        ]);

        // Verificar saldos
        $this->assertEquals(300.0, \App\Services\FinancialAccountService::getAccountBalance(
            $this->cashAccount->id, $this->restaurantId
        ));
        $this->assertEquals(200.0, \App\Services\FinancialAccountService::getAccountBalance(
            $this->bankAccount->id, $this->restaurantId
        ));
    }

    public function test_transfer_to_same_account_fails(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->cashAccount->id,
                'to_account_id'   => $this->cashAccount->id,
                'amount'          => 100,
            ])
            ->assertStatus(422);
    }

    public function test_transfer_exceeding_balance_fails(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->cashAccount->id,
                'to_account_id'   => $this->bankAccount->id,
                'amount'          => 99999,
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_list_transfers(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/account-transfers')
            ->assertOk();
    }

    /* ================================================================
     * FINANCIAL MOVEMENTS LIST
     * ================================================================ */

    public function test_admin_can_list_financial_movements(): void
    {
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 1,
            'amount'               => 100,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/financial-movements');

        $response->assertOk()
            // 1 from this test + 1 initial_balance from setUpRestaurant trait
            ->assertJsonPath('total', 2);
    }

    public function test_movements_filtered_by_account(): void
    {
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 1,
            'amount'               => 100,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->digitalAccount->id,
            'type'                 => 'income',
            'reference_type'       => 'manual_adjustment',
            'reference_id'         => 2,
            'amount'               => 50,
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/financial-movements?financial_account_id={$this->cashAccount->id}");

        $response->assertOk()
            ->assertJsonPath('total', 1);
    }

    /* ================================================================
     * MULTI-TENANT ISOLATION
     * ================================================================ */

    public function test_restaurant_b_cannot_see_restaurant_a_accounts(): void
    {
        // Crear otro restaurante
        $restaurantB = Restaurant::create([
            'name' => 'Restaurant B',
            'ruc'  => '20888222333',
        ]);

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

        // Restaurant B no debe ver las cuentas de Restaurant A
        $response = $this->withHeaders($headersB)
            ->getJson('/api/financial-accounts');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    /* ================================================================
     * BALANCE CALCULATION CORRECTNESS
     * ================================================================ */

    public function test_balance_calculates_correctly_with_mixed_movements(): void
    {
        $accountId = $this->cashAccount->id;

        // income: +1000
        FinancialMovement::create([
            'restaurant_id' => $this->restaurantId, 'financial_account_id' => $accountId,
            'type' => 'income', 'reference_type' => 'manual_adjustment', 'reference_id' => 1,
            'amount' => 1000, 'movement_date' => now()->toDateString(), 'created_by' => $this->adminUser->id,
        ]);

        // expense: -200
        FinancialMovement::create([
            'restaurant_id' => $this->restaurantId, 'financial_account_id' => $accountId,
            'type' => 'expense', 'reference_type' => 'manual_adjustment', 'reference_id' => 2,
            'amount' => 200, 'movement_date' => now()->toDateString(), 'created_by' => $this->adminUser->id,
        ]);

        // transfer_out: -150
        FinancialMovement::create([
            'restaurant_id' => $this->restaurantId, 'financial_account_id' => $accountId,
            'type' => 'transfer_out', 'reference_type' => 'transfer', 'reference_id' => 1,
            'amount' => 150, 'movement_date' => now()->toDateString(), 'created_by' => $this->adminUser->id,
        ]);

        // transfer_in: +75
        FinancialMovement::create([
            'restaurant_id' => $this->restaurantId, 'financial_account_id' => $accountId,
            'type' => 'transfer_in', 'reference_type' => 'transfer', 'reference_id' => 2,
            'amount' => 75, 'movement_date' => now()->toDateString(), 'created_by' => $this->adminUser->id,
        ]);

        // Expected: 1000 - 200 - 150 + 75 = 725
        $balance = \App\Services\FinancialAccountService::getAccountBalance($accountId, $this->restaurantId);
        $this->assertEquals(725.0, $balance);
    }

    /* ================================================================
     * HELPERS
     * ================================================================ */

    protected function createExpenseCategory(): int
    {
        $cat = \App\Models\ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Insumos Test',
        ]);
        return $cat->id;
    }

    protected function getPendingStatusId(): int
    {
        return \App\Models\ExpenseStatus::where('slug', 'pending')->firstOrFail()->id;
    }
}
