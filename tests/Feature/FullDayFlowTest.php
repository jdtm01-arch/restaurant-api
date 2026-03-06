<?php

namespace Tests\Feature;

use App\Models\FinancialAccount;
use App\Models\FinancialMovement;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * E2E Test: Flujo completo de un día de trabajo.
 *
 * Simula las 19 operaciones que un restaurante haría en un día:
 * 1. Login → 2. Abrir caja → 3. Crear orden mesa → 4. Agregar ítems
 * → 5. Comanda cocina → 6. Agregar ítem extra → 7. Cerrar orden
 * → 8. Pagar orden → 9. Ver recibo → 10. Crear orden takeaway
 * → 11. Cerrar y pagar takeaway → 12. Registrar gasto → 13. Merma
 * → 14. Reporte X → 15. Descuento en nueva orden → 16. Cancelar orden
 * → 17. Cerrar caja → 18. Cierre de día → 19. Reporte resumen
 */
class FullDayFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $mozo;
    private Restaurant $restaurant;
    private int $restaurantId;
    private string $adminToken;
    private string $mozoToken;
    private PaymentMethod $cash;
    private FinancialAccount $cashAccount;
    private Table $table;
    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Restaurant
        $this->restaurant = Restaurant::create([
            'name' => 'La Conquista Test',
            'ruc'  => '20123456789',
        ]);
        $this->restaurantId = $this->restaurant->id;

        // Roles
        $roleAdmin = Role::where('slug', 'admin_restaurante')->firstOrFail();
        $roleMozo  = Role::where('slug', 'mozo')->firstOrFail();

        // Users
        $this->admin = User::factory()->create(['name' => 'Admin']);
        $this->mozo  = User::factory()->create(['name' => 'Mozo']);

        DB::table('restaurant_user')->insert([
            [
                'restaurant_id' => $this->restaurantId,
                'user_id'       => $this->admin->id,
                'role_id'       => $roleAdmin->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'restaurant_id' => $this->restaurantId,
                'user_id'       => $this->mozo->id,
                'role_id'       => $roleMozo->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        // Payment method
        $this->cash = PaymentMethod::where('name', 'Efectivo')->firstOrFail();

        // Category + Products
        $this->category = ProductCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Platos Principales',
        ]);

        DB::table('products')->insert([
            [
                'restaurant_id'  => $this->restaurantId,
                'category_id'    => $this->category->id,
                'name'           => 'Lomo Saltado',
                'price_with_tax' => 35.00,
                'is_active'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'restaurant_id'  => $this->restaurantId,
                'category_id'    => $this->category->id,
                'name'           => 'Ceviche',
                'price_with_tax' => 30.00,
                'is_active'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'restaurant_id'  => $this->restaurantId,
                'category_id'    => $this->category->id,
                'name'           => 'Chicha Morada',
                'price_with_tax' => 8.00,
                'is_active'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);

        // Table
        $this->table = Table::create([
            'restaurant_id' => $this->restaurantId,
            'number'        => 1,
            'name'          => 'Mesa 1',
        ]);

        // Generate auth tokens
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->mozoToken  = $this->mozo->createToken('test')->plainTextToken;

        // Financial initialization
        $this->cashAccount = FinancialAccount::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Caja Principal',
            'type'          => 'cash',
            'currency'      => 'PEN',
            'is_active'     => true,
        ]);

        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => FinancialMovement::TYPE_INITIAL_BALANCE,
            'reference_type'       => FinancialMovement::REF_INITIAL_BALANCE,
            'reference_id'         => $this->cashAccount->id,
            'amount'               => 10000.00,
            'description'          => 'Saldo inicial',
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->admin->id,
        ]);

        $this->restaurant->update(['financial_initialized_at' => now()]);
    }

    public function test_full_day_flow(): void
    {
        $products = DB::table('products')
            ->where('restaurant_id', $this->restaurantId)
            ->get();

        $lomoId    = $products->firstWhere('name', 'Lomo Saltado')->id;
        $cevicheId = $products->firstWhere('name', 'Ceviche')->id;
        $chichaId  = $products->firstWhere('name', 'Chicha Morada')->id;

        // ─── Step 1: Login ─────────────────────────────────────────
        $response = $this->postJson('/api/login', [
            'email'    => $this->admin->email,
            'password' => 'password',
        ]);
        $response->assertOk();

        // ─── Step 2: Abrir caja ────────────────────────────────────
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-registers', [
                'opening_amount' => 200.00,
            ]);
        $response->assertStatus(201);
        $cashRegisterId = $response->json('data.id');

        // ─── Step 3: Crear orden dine_in ───────────────────────────
        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel'  => 'dine_in',
                'table_id' => $this->table->id,
                'items'    => [
                    ['product_id' => $lomoId, 'quantity' => 2],
                    ['product_id' => $cevicheId, 'quantity' => 1],
                ],
            ]);
        $response->assertStatus(201);
        $orderId = $response->json('data.id');

        // Verify total: 35*2 + 30*1 = 100
        $this->assertEquals(100.00, $response->json('data.total'));

        // ─── Step 4: ver ítems de la orden ─────────────────────────
        $response = $this->withHeaders($this->mozoHeaders())
            ->getJson("/api/orders/{$orderId}");
        $response->assertOk();
        $this->assertCount(2, $response->json('data.items'));

        // ─── Step 5: Comanda cocina ────────────────────────────────
        $response = $this->withHeaders($this->mozoHeaders())
            ->getJson("/api/orders/{$orderId}/kitchen-ticket");
        $response->assertOk();
        $this->assertArrayHasKey('text', $response->json('data'));

        // ─── Step 6: Agregar ítem extra ────────────────────────────
        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/items", [
                'product_id' => $chichaId,
                'quantity'   => 2,
            ]);
        $response->assertStatus(201);

        // Verify new total: 100 + 8*2 = 116
        $this->assertEquals(116.00, $response->json('data.order.total'));

        // ─── Step 7: Cerrar orden ──────────────────────────────────
        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/close");
        $response->assertOk();
        $this->assertEquals('closed', $response->json('data.status'));

        // ─── Step 8: Pagar orden (efectivo) ────────────────────────
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$orderId}/pay", [
                'payments' => [
                    ['payment_method_id' => $this->cash->id, 'financial_account_id' => $this->cashAccount->id, 'amount' => 116.00],
                ],
            ]);
        $response->assertStatus(201);
        $saleId = $response->json('data.id');
        $this->assertNotNull($response->json('data.receipt_number'));

        // ─── Step 9: Ver recibo ────────────────────────────────────
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/sales/{$saleId}/receipt");
        $response->assertOk();
        $this->assertArrayHasKey('text', $response->json('data'));

        // ─── Step 10: Crear orden takeaway ─────────────────────────
        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $cevicheId, 'quantity' => 3],
                ],
            ]);
        $response->assertStatus(201);
        $takeawayOrderId = $response->json('data.id');

        // Verify total: 30*3 = 90
        $this->assertEquals(90.00, $response->json('data.total'));

        // ─── Step 11: Cerrar y pagar takeaway ──────────────────────
        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$takeawayOrderId}/close")
            ->assertOk();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$takeawayOrderId}/pay", [
                'payments' => [
                    ['payment_method_id' => $this->cash->id, 'financial_account_id' => $this->cashAccount->id, 'amount' => 90.00],
                ],
            ]);
        $response->assertStatus(201);

        // ─── Step 12: Registrar gasto ──────────────────────────────
        $expenseCategory = \App\Models\ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Insumos',
        ]);
        $pendingStatus = \App\Models\ExpenseStatus::where('slug', 'pending')->firstOrFail();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expenses', [
                'expense_category_id' => $expenseCategory->id,
                'expense_status_id'   => $pendingStatus->id,
                'amount'              => 50.00,
                'description'         => 'Verduras del día',
                'expense_date'        => now()->toDateString(),
            ]);
        $response->assertStatus(201);

        // ─── Step 13: Registrar merma ──────────────────────────────
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/waste-logs', [
                'product_id'     => $lomoId,
                'quantity'       => 0.5,
                'unit'           => 'kg',
                'reason'         => 'expired',
                'estimated_cost' => 15.00,
                'waste_date'     => now()->toDateString(),
                'description'    => 'Lomo en mal estado',
            ]);
        $response->assertStatus(201);

        // ─── Step 14: Reporte X ────────────────────────────────────
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/cash-registers/{$cashRegisterId}/x-report");
        $response->assertOk();
        $this->assertArrayHasKey('total_sales', $response->json('data'));

        // ─── Step 15: Crear orden con descuento ────────────────────
        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $lomoId, 'quantity' => 1],
                ],
            ]);
        $response->assertStatus(201);
        $discountOrderId = $response->json('data.id');

        // Apply 10% discount — must close first, then discount, then pay
        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$discountOrderId}/close")
            ->assertOk();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$discountOrderId}/discount", [
                'discount_percentage' => 10,
            ]);
        $response->assertOk();
        // Discount: 35 * 10% = 3.50  → total = 31.50
        $this->assertEquals(31.50, $response->json('data.total'));

        // Pay
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$discountOrderId}/pay", [
                'payments' => [
                    ['payment_method_id' => $this->cash->id, 'financial_account_id' => $this->cashAccount->id, 'amount' => 31.50],
                ],
            ])
            ->assertStatus(201);

        // ─── Step 16: Cancelar una orden ───────────────────────────
        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $chichaId, 'quantity' => 1],
                ],
            ]);
        $response->assertStatus(201);
        $cancelOrderId = $response->json('data.id');

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$cancelOrderId}/cancel", [
                'cancellation_reason' => 'Cliente se fue',
            ]);
        $response->assertOk();
        $this->assertEquals('cancelled', $response->json('data.status'));

        // ─── Step 17: Cerrar caja ──────────────────────────────────
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$cashRegisterId}/close", [
                'closing_amount_real' => 425.00,
            ]);
        $response->assertOk();
        $this->assertEquals('closed', $response->json('data.status'));

        // ─── Step 18: Cierre del día ───────────────────────────────
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', [
                'date' => now()->toDateString(),
            ]);
        $response->assertStatus(201);

        // ─── Step 19: Reporte resumen del día ──────────────────────
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/reports/daily-summary?date=' . now()->toDateString());
        $response->assertOk();

        // Verify totals: 116 + 90 + 31.50 = 237.50
        $this->assertEquals(237.50, $response->json('data.total_sales'));
        $this->assertEquals(3, $response->json('data.total_orders'));
        $this->assertEquals(1, $response->json('data.cancelled_orders'));

        // ─── Verify audit trail ────────────────────────────────────
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/audit-logs');
        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function adminHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'  => "Bearer {$this->adminToken}",
            'X-Restaurant-Id' => $this->restaurantId,
        ];
    }

    private function mozoHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'  => "Bearer {$this->mozoToken}",
            'X-Restaurant-Id' => $this->restaurantId,
        ];
    }
}
