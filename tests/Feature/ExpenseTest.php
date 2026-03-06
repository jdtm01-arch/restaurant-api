<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\ExpenseStatus;
use App\Models\CashRegister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected ExpenseCategory $expenseCategory;
    protected ExpenseStatus $pendingStatus;
    protected ExpenseStatus $paidStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();

        // Abrir caja por defecto para todos los tests de gastos
        $this->openCashRegister();

        $this->expenseCategory = ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name' => 'Insumos',
        ]);

        $this->pendingStatus = ExpenseStatus::where('slug', 'pending')->firstOrFail();
        $this->paidStatus    = ExpenseStatus::where('slug', 'paid')->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | List
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_expenses(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/expenses');

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_expense(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 150.00,
                'description'         => 'Compra de verduras',
                'expense_date'        => now()->toDateString(),
            ]);

        $response->assertStatus(201);
    }

    public function test_mozo_cannot_create_expense(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 50,
                'description'         => 'Intento no autorizado',
                'expense_date'        => now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_caja_cannot_create_expense(): void
    {
        $this->withHeaders($this->cajaHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 50,
                'description'         => 'Intento no autorizado',
                'expense_date'        => now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_expense_validation_fails_without_required_fields(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [])
            ->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_expense(): void
    {
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 100,
                'description'         => 'Gasto original',
                'expense_date'        => now()->toDateString(),
            ]);

        $expenseId = $createResponse->json('id');

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/expenses/{$expenseId}", [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 120,
                'description'         => 'Gasto actualizado',
                'expense_date'        => now()->toDateString(),
            ]);

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_expense(): void
    {
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 30,
                'description'         => 'Para eliminar',
                'expense_date'        => now()->toDateString(),
            ]);

        $expenseId = $createResponse->json('id');

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/expenses/{$expenseId}")
            ->assertOk();
    }

    public function test_mozo_cannot_delete_expense(): void
    {
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 30,
                'description'         => 'Intento de borrado',
                'expense_date'        => now()->toDateString(),
            ]);

        $expenseId = $createResponse->json('id');

        $this->withHeaders($this->mozoHeaders())
            ->deleteJson("/api/expenses/{$expenseId}")
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Show (detail con pagos)
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_show_expense_with_payments(): void
    {
        // Crear gasto
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 80.00,
                'description'         => 'Gasto con pagos',
                'expense_date'        => now()->toDateString(),
            ]);

        $expenseId = $createResponse->json('id');

        // Registrar pago (caja ya abierta desde setUp)
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => 1,
                'financial_account_id' => $this->cashFinancialAccount->id,
                'amount'               => 40.00,
                'paid_at'              => now()->toDateString(),
            ]);

        // Ver detalle
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/expenses/{$expenseId}");

        $response->assertOk();
        $response->assertJsonPath('id', $expenseId);
        $response->assertJsonStructure(['id', 'description', 'payments', 'attachments']);
        $this->assertCount(1, $response->json('payments'));
    }

    /*
    |--------------------------------------------------------------------------
    | Validación de fecha de pago
    |--------------------------------------------------------------------------
    */

    public function test_payment_date_cannot_be_before_expense_date(): void
    {
        // Crear gasto con fecha de HOY
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 50.00,
                'description'         => 'Gasto para validación de fecha',
                'expense_date'        => now()->toDateString(),
            ]);

        $expenseId = $createResponse->json('id');

        // Intentar registrar pago con fecha ANTERIOR a la del gasto
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => 1,
                'financial_account_id' => $this->cashFinancialAccount->id,
                'amount'               => 50.00,
                'paid_at'              => now()->subDay()->toDateString(), // ayer
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('paid_at', $response->json('errors'));
    }

    /*
    |--------------------------------------------------------------------------
    | Validación de caja cerrada al registrar pago
    |--------------------------------------------------------------------------
    */

    public function test_cannot_register_payment_when_cash_register_is_closed(): void
    {
        $expenseDate = now()->toDateString();

        // Crear gasto
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 60.00,
                'description'         => 'Gasto caja cerrada',
                'expense_date'        => $expenseDate,
            ]);

        $expenseId = $createResponse->json('id');

        // Cerrar la caja existente (abierta en setUp)
        CashRegister::where('restaurant_id', $this->restaurantId)
            ->whereDate('date', $expenseDate)
            ->update([
                'status'    => CashRegister::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $this->adminUser->id,
            ]);

        // Intentar registrar pago con la misma fecha (caja cerrada)
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => 1,
                'financial_account_id' => $this->cashFinancialAccount->id,
                'amount'               => 60.00,
                'paid_at'              => $expenseDate,
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('paid_at', $response->json('errors'));
    }

    /*
    |--------------------------------------------------------------------------
    | Validación: suma de pagos debe coincidir para marcar como pagado
    |--------------------------------------------------------------------------
    */

    public function test_cannot_mark_expense_as_paid_when_payments_do_not_match_amount(): void
    {
        // Crear gasto por S/ 100
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 100.00,
                'description'         => 'Gasto suma incompleta',
                'expense_date'        => now()->toDateString(),
            ]);

        $expenseId = $createResponse->json('id');

        // Registrar solo S/ 60 (no el total); caja ya abierta desde setUp
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/expenses/{$expenseId}/payments", [
                'payment_method_id'    => 1,
                'financial_account_id' => $this->cashFinancialAccount->id,
                'amount'               => 60.00,
                'paid_at'              => now()->toDateString(),
            ]);

        // Intentar marcar como pagado (faltan S/ 40)
        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/expenses/{$expenseId}", [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->paidStatus->id,
                'amount'              => 100.00,
                'description'         => 'Gasto suma incompleta',
                'expense_date'        => now()->toDateString(),
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('amount', $response->json('errors'));
    }

    /*
    |--------------------------------------------------------------------------
    | Validación: no crear gasto si la caja de la fecha está cerrada
    |--------------------------------------------------------------------------
    */

    public function test_cannot_create_expense_when_cash_register_is_closed(): void
    {
        $expenseDate = now()->toDateString();

        // Cerrar la caja existente (abierta en setUp)
        CashRegister::where('restaurant_id', $this->restaurantId)
            ->whereDate('date', $expenseDate)
            ->update([
                'status'    => CashRegister::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $this->adminUser->id,
            ]);

        // Intentar crear gasto con esa fecha (caja cerrada)
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $this->expenseCategory->id,
                'expense_status_id'   => $this->pendingStatus->id,
                'amount'              => 50.00,
                'description'         => 'Gasto con caja cerrada',
                'expense_date'        => $expenseDate,
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('expense_date', $response->json('errors'));
    }
}
