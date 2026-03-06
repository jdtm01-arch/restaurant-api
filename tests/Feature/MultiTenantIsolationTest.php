<?php

namespace Tests\Feature;

use App\Models\FinancialAccount;
use App\Models\FinancialMovement;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * E2E Test: Aislamiento multi-tenant.
 *
 * Verifica que los datos de un restaurante nunca se filtren al otro:
 * 1. Crear 2 restaurantes con datos independientes
 * 2. Usuario de R1 NO ve datos de R2
 * 3. Usuario de R1 NO puede operar sobre recursos de R2
 * 4. Reportes de R1 NO incluyen datos de R2
 */
class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $r1;
    private Restaurant $r2;
    private User $userR1;
    private User $userR2;
    private string $tokenR1;
    private string $tokenR2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Two restaurants
        $this->r1 = Restaurant::create(['name' => 'Restaurante 1', 'ruc' => '20100000001']);
        $this->r2 = Restaurant::create(['name' => 'Restaurante 2', 'ruc' => '20100000002']);

        $roleAdmin = Role::where('slug', 'admin_restaurante')->firstOrFail();

        // Users — each belongs ONLY to their restaurant
        $this->userR1 = User::factory()->create(['name' => 'Admin R1']);
        $this->userR2 = User::factory()->create(['name' => 'Admin R2']);

        DB::table('restaurant_user')->insert([
            [
                'restaurant_id' => $this->r1->id,
                'user_id'       => $this->userR1->id,
                'role_id'       => $roleAdmin->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'restaurant_id' => $this->r2->id,
                'user_id'       => $this->userR2->id,
                'role_id'       => $roleAdmin->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        $this->tokenR1 = $this->userR1->createToken('test')->plainTextToken;
        $this->tokenR2 = $this->userR2->createToken('test')->plainTextToken;

        // Seed data for each restaurant independently
        foreach ([$this->r1, $this->r2] as $r) {
            $cat = ProductCategory::create([
                'restaurant_id' => $r->id,
                'name'          => "Categoría {$r->name}",
            ]);

            DB::table('products')->insert([
                'restaurant_id'  => $r->id,
                'category_id'    => $cat->id,
                'name'           => "Producto de {$r->name}",
                'price_with_tax' => 25.00,
                'is_active'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            Table::create([
                'restaurant_id' => $r->id,
                'number'        => 1,
                'name'          => "Mesa {$r->name}",
            ]);

            // Financial initialization
            $cashAccount = FinancialAccount::create([
                'restaurant_id' => $r->id,
                'name'          => 'Caja Principal',
                'type'          => 'cash',
                'currency'      => 'PEN',
                'is_active'     => true,
            ]);

            $user = $r->id === $this->r1->id ? $this->userR1 : $this->userR2;
            FinancialMovement::create([
                'financial_account_id' => $cashAccount->id,
                'restaurant_id'        => $r->id,
                'user_id'              => $user->id,
                'type'                 => FinancialMovement::TYPE_INITIAL_BALANCE,
                'amount'               => 10000.00,
                'reference_type'       => FinancialMovement::REF_INITIAL_BALANCE,
                'reference_id'         => $cashAccount->id,
                'description'          => 'Saldo inicial test',
                'movement_date'        => now()->toDateString(),
                'created_by'           => $user->id,
            ]);

            $r->update(['financial_initialized_at' => now()]);
        }
    }

    /**
     * Step 1: Cada restaurante solo ve sus propios productos.
     */
    public function test_products_are_isolated(): void
    {
        $response = $this->withHeaders($this->headersFor($this->tokenR1, $this->r1->id))
            ->getJson('/api/products');
        $response->assertOk();

        $products = $response->json('data');
        foreach ($products as $product) {
            $this->assertEquals($this->r1->id, $product['restaurant_id']);
        }
    }

    /**
     * Step 2: Cada restaurante solo ve sus propias mesas.
     */
    public function test_tables_are_isolated(): void
    {
        $response = $this->withHeaders($this->headersFor($this->tokenR1, $this->r1->id))
            ->getJson('/api/tables');
        $response->assertOk();

        $tables = $response->json('data');
        foreach ($tables as $table) {
            $this->assertEquals($this->r1->id, $table['restaurant_id']);
        }
    }

    /**
     * Step 3: Usuario R1 no puede acceder con header de R2.
     */
    public function test_user_cannot_access_other_restaurant(): void
    {
        $response = $this->withHeaders($this->headersFor($this->tokenR1, $this->r2->id))
            ->getJson('/api/products');
        $response->assertStatus(403);
    }

    /**
     * Step 4: Operaciones de negocio son aisladas — crear orden en R1 no aparece en R2.
     */
    public function test_orders_are_isolated(): void
    {
        $cash = PaymentMethod::where('name', 'Efectivo')->firstOrFail();

        // Open cash register for R1
        $this->withHeaders($this->headersFor($this->tokenR1, $this->r1->id))
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(201);

        $productR1 = DB::table('products')
            ->where('restaurant_id', $this->r1->id)
            ->first();

        $tableR1 = Table::where('restaurant_id', $this->r1->id)->first();

        // Create order in R1
        $response = $this->withHeaders($this->headersFor($this->tokenR1, $this->r1->id))
            ->postJson('/api/orders', [
                'channel'  => 'dine_in',
                'table_id' => $tableR1->id,
                'items'    => [
                    ['product_id' => $productR1->id, 'quantity' => 1],
                ],
            ]);
        $response->assertStatus(201);

        // R2 should see 0 orders
        // First open caja for R2
        $this->withHeaders($this->headersFor($this->tokenR2, $this->r2->id))
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(201);

        $response = $this->withHeaders($this->headersFor($this->tokenR2, $this->r2->id))
            ->getJson('/api/orders');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Step 5: Usuario R1 no puede operar sobre el recurso de R2 directamente.
     */
    public function test_user_cannot_modify_other_restaurant_resource(): void
    {
        $tableR2 = Table::where('restaurant_id', $this->r2->id)->first();

        // R1 tries to update R2's table
        $response = $this->withHeaders($this->headersFor($this->tokenR1, $this->r1->id))
            ->putJson("/api/tables/{$tableR2->id}", [
                'name'     => 'Hacked',
                'capacity' => 99,
            ]);

        // Should fail — either 403 or 404 (model binding with scope)
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    /**
     * Step 6: Reportes son aislados.
     */
    public function test_reports_are_isolated(): void
    {
        // Sales in R1
        $this->createCompleteSaleInRestaurant($this->r1, $this->tokenR1, $this->userR1);

        // Check daily summary for R1 includes the sale
        $response = $this->withHeaders($this->headersFor($this->tokenR1, $this->r1->id))
            ->getJson('/api/reports/daily-summary?date=' . now()->toDateString());
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('data.total_sales'));

        // Check daily summary for R2 shows 0
        $response = $this->withHeaders($this->headersFor($this->tokenR2, $this->r2->id))
            ->getJson('/api/reports/daily-summary?date=' . now()->toDateString());
        $response->assertOk();
        $this->assertEquals(0, $response->json('data.total_sales'));
    }

    /**
     * Step 7: Audit logs are isolated.
     */
    public function test_audit_logs_are_isolated(): void
    {
        // Perform an operation in R1
        $this->withHeaders($this->headersFor($this->tokenR1, $this->r1->id))
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(201);

        // R2 should see 0 audit logs
        $response = $this->withHeaders($this->headersFor($this->tokenR2, $this->r2->id))
            ->getJson('/api/audit-logs');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Step 8: Waste logs are isolated.
     */
    public function test_waste_logs_are_isolated(): void
    {
        $productR1 = DB::table('products')
            ->where('restaurant_id', $this->r1->id)
            ->first();

        // Create waste log in R1
        $this->withHeaders($this->headersFor($this->tokenR1, $this->r1->id))
            ->postJson('/api/waste-logs', [
                'product_id'     => $productR1->id,
                'quantity'       => 1,
                'unit'           => 'kg',
                'reason'         => 'expired',
                'estimated_cost' => 10.00,
                'waste_date'     => now()->toDateString(),
            ])
            ->assertStatus(201);

        // R2 should see 0 waste logs
        $response = $this->withHeaders($this->headersFor($this->tokenR2, $this->r2->id))
            ->getJson('/api/waste-logs');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function headersFor(string $token, int $restaurantId): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'    => "Bearer {$token}",
            'X-Restaurant-Id'  => $restaurantId,
        ];
    }

    private function createCompleteSaleInRestaurant(Restaurant $r, string $token, User $user): void
    {
        $cash = PaymentMethod::where('name', 'Efectivo')->firstOrFail();
        $cashAccount = FinancialAccount::withoutGlobalScopes()
            ->where('restaurant_id', $r->id)
            ->where('type', 'cash')
            ->first();
        $headers = $this->headersFor($token, $r->id);

        // Open caja
        $this->withHeaders($headers)
            ->postJson('/api/cash-registers', ['opening_amount' => 100]);

        $product = DB::table('products')->where('restaurant_id', $r->id)->first();

        // Create + close + pay
        $response = $this->withHeaders($headers)
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);
        $orderId = $response->json('data.id');
        $total = $response->json('data.total');

        $this->withHeaders($headers)
            ->postJson("/api/orders/{$orderId}/close");

        $this->withHeaders($headers)
            ->postJson("/api/orders/{$orderId}/pay", [
                'payments' => [
                    ['payment_method_id' => $cash->id, 'financial_account_id' => $cashAccount->id, 'amount' => $total],
                ],
            ]);
    }
}
