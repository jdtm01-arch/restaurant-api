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
 * E2E Test: Permisos por rol.
 *
 * Verifica que cada rol solo pueda ejecutar las operaciones permitidas
 * y que sea denegado en las que no le corresponden.
 *
 * Roles:
 * - admin_restaurante: acceso completo
 * - caja: caja, órdenes, ventas
 * - mozo: órdenes, cocina
 * - cocina: solo ver órdenes y mermas
 */
class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private int $restaurantId;
    private array $users = [];
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->restaurant = Restaurant::create([
            'name' => 'Permisos Test',
            'ruc'  => '20999888777',
        ]);
        $this->restaurantId = $this->restaurant->id;

        // Create one user per role
        $roles = ['admin_restaurante', 'caja', 'mozo', 'cocina'];
        foreach ($roles as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $user = User::factory()->create(['name' => "User {$roleSlug}"]);

            DB::table('restaurant_user')->insert([
                'restaurant_id' => $this->restaurantId,
                'user_id'       => $user->id,
                'role_id'       => $role->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $this->users[$roleSlug] = $user;
            $this->tokens[$roleSlug] = $user->createToken('test')->plainTextToken;
        }

        // Seed common data
        $cat = ProductCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Test Cat',
        ]);

        DB::table('products')->insert([
            'restaurant_id'  => $this->restaurantId,
            'category_id'    => $cat->id,
            'name'           => 'Test Product',
            'price_with_tax' => 20.00,
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        Table::create([
            'restaurant_id' => $this->restaurantId,
            'number'        => 1,
            'name'          => 'Mesa Test',
        ]);

        // Financial initialization
        $cashAccount = FinancialAccount::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Caja Principal',
            'type'          => 'cash',
            'currency'      => 'PEN',
            'is_active'     => true,
        ]);

        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $cashAccount->id,
            'type'                 => FinancialMovement::TYPE_INITIAL_BALANCE,
            'reference_type'       => FinancialMovement::REF_INITIAL_BALANCE,
            'reference_id'         => $cashAccount->id,
            'amount'               => 100.00,
            'description'          => 'Saldo inicial',
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->users['admin_restaurante']->id,
        ]);

        $this->restaurant->update(['financial_initialized_at' => now()]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN_RESTAURANTE — Full access
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_open_cash_register(): void
    {
        $this->withHeaders($this->headersFor('admin_restaurante'))
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(201);
    }

    public function test_admin_can_create_and_cancel_order(): void
    {
        $this->openCashRegister('admin_restaurante');
        $product = $this->getProduct();

        $response = $this->withHeaders($this->headersFor('admin_restaurante'))
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $response->assertStatus(201);
        $orderId = $response->json('data.id');

        // Admin can cancel
        $this->withHeaders($this->headersFor('admin_restaurante'))
            ->postJson("/api/orders/{$orderId}/cancel", [
                'cancellation_reason' => 'Motivo de prueba',
            ])
            ->assertOk();
    }

    public function test_admin_can_apply_discount(): void
    {
        $this->openCashRegister('admin_restaurante');
        $product = $this->getProduct();

        $response = $this->withHeaders($this->headersFor('admin_restaurante'))
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $response->json('data.id');

        // Close the order first (discount is only allowed on closed orders)
        $this->withHeaders($this->headersFor('admin_restaurante'))
            ->postJson("/api/orders/{$orderId}/close")
            ->assertOk();

        $this->withHeaders($this->headersFor('admin_restaurante'))
            ->postJson("/api/orders/{$orderId}/discount", [
                'discount_percentage' => 15,
            ])
            ->assertOk();
    }

    public function test_admin_can_access_reports(): void
    {
        $today = now()->toDateString();

        $this->withHeaders($this->headersFor('admin_restaurante'))
            ->getJson("/api/reports/daily-summary?date={$today}")
            ->assertOk();
    }

    public function test_admin_can_access_audit_logs(): void
    {
        $this->withHeaders($this->headersFor('admin_restaurante'))
            ->getJson('/api/audit-logs')
            ->assertOk();
    }

    public function test_admin_can_perform_cash_closing(): void
    {
        $crResponse = $this->openCashRegister('admin_restaurante');
        $crId = $crResponse->json('data.id');

        // Close the register first
        $this->withHeaders($this->headersFor('admin_restaurante'))
            ->postJson("/api/cash-registers/{$crId}/close", [
                'closing_amount_real' => 100,
            ])
            ->assertOk();

        $this->withHeaders($this->headersFor('admin_restaurante'))
            ->postJson('/api/cash-closings', [
                'date' => now()->toDateString(),
            ])
            ->assertStatus(201);
    }

    /*
    |--------------------------------------------------------------------------
    | CAJA — Cash register, orders management, sales
    |--------------------------------------------------------------------------
    */

    public function test_caja_can_open_cash_register(): void
    {
        $this->withHeaders($this->headersFor('caja'))
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(201);
    }

    public function test_caja_can_create_order(): void
    {
        $this->openCashRegister('caja');
        $product = $this->getProduct();

        $this->withHeaders($this->headersFor('caja'))
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(201);
    }

    public function test_caja_cannot_apply_discount(): void
    {
        $this->openCashRegister('caja');
        $product = $this->getProduct();

        $response = $this->withHeaders($this->headersFor('caja'))
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $response->json('data.id');

        $this->withHeaders($this->headersFor('caja'))
            ->postJson("/api/orders/{$orderId}/discount", [
                'discount_percentage' => 10,
            ])
            ->assertStatus(403);
    }

    public function test_caja_cannot_access_reports(): void
    {
        $this->withHeaders($this->headersFor('caja'))
            ->getJson('/api/reports/daily-summary?date=' . now()->toDateString())
            ->assertStatus(403);
    }

    public function test_caja_cannot_access_audit_logs(): void
    {
        $this->withHeaders($this->headersFor('caja'))
            ->getJson('/api/audit-logs')
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | MOZO — Orders, kitchen tickets
    |--------------------------------------------------------------------------
    */

    public function test_mozo_can_create_order(): void
    {
        $this->openCashRegister('admin_restaurante');
        $product = $this->getProduct();

        $this->withHeaders($this->headersFor('mozo'))
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(201);
    }

    public function test_mozo_can_view_kitchen_ticket(): void
    {
        $this->openCashRegister('admin_restaurante');
        $product = $this->getProduct();

        $response = $this->withHeaders($this->headersFor('mozo'))
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $response->json('data.id');

        $this->withHeaders($this->headersFor('mozo'))
            ->getJson("/api/orders/{$orderId}/kitchen-ticket")
            ->assertOk();
    }

    public function test_mozo_cannot_cancel_order(): void
    {
        $this->openCashRegister('admin_restaurante');
        $product = $this->getProduct();

        $response = $this->withHeaders($this->headersFor('mozo'))
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $response->json('data.id');

        $this->withHeaders($this->headersFor('mozo'))
            ->postJson("/api/orders/{$orderId}/cancel", [
                'cancellation_reason' => 'Motivo de prueba',
            ])
            ->assertStatus(403);
    }

    public function test_mozo_cannot_open_cash_register(): void
    {
        $this->withHeaders($this->headersFor('mozo'))
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(403);
    }

    public function test_mozo_cannot_access_reports(): void
    {
        $this->withHeaders($this->headersFor('mozo'))
            ->getJson('/api/reports/daily-summary?date=' . now()->toDateString())
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | COCINA — View orders only
    |--------------------------------------------------------------------------
    */

    public function test_cocina_can_view_orders(): void
    {
        $this->openCashRegister('admin_restaurante');

        $this->withHeaders($this->headersFor('cocina'))
            ->getJson('/api/orders')
            ->assertOk();
    }

    public function test_cocina_cannot_create_order(): void
    {
        $this->openCashRegister('admin_restaurante');
        $product = $this->getProduct();

        $this->withHeaders($this->headersFor('cocina'))
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(403);
    }

    public function test_cocina_cannot_open_cash_register(): void
    {
        $this->withHeaders($this->headersFor('cocina'))
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(403);
    }

    public function test_cocina_cannot_access_sales(): void
    {
        $this->withHeaders($this->headersFor('cocina'))
            ->getJson('/api/sales')
            ->assertStatus(403);
    }

    public function test_cocina_can_create_waste_log(): void
    {
        $product = $this->getProduct();

        $this->withHeaders($this->headersFor('cocina'))
            ->postJson('/api/waste-logs', [
                'product_id'     => $product->id,
                'quantity'       => 1,
                'unit'           => 'kg',
                'reason'         => 'damaged',
                'estimated_cost' => 5.00,
                'waste_date'     => now()->toDateString(),
            ])
            ->assertStatus(201);
    }

    public function test_cocina_cannot_delete_waste_log(): void
    {
        $product = $this->getProduct();

        $response = $this->withHeaders($this->headersFor('cocina'))
            ->postJson('/api/waste-logs', [
                'product_id'     => $product->id,
                'quantity'       => 1,
                'unit'           => 'kg',
                'reason'         => 'damaged',
                'estimated_cost' => 5.00,
                'waste_date'     => now()->toDateString(),
            ]);
        $wasteLogId = $response->json('data.id');

        $this->withHeaders($this->headersFor('cocina'))
            ->deleteJson("/api/waste-logs/{$wasteLogId}")
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function headersFor(string $roleSlug): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'    => "Bearer {$this->tokens[$roleSlug]}",
            'X-Restaurant-Id'  => $this->restaurantId,
        ];
    }

    private function openCashRegister(string $roleSlug)
    {
        return $this->withHeaders($this->headersFor($roleSlug))
            ->postJson('/api/cash-registers', ['opening_amount' => 100]);
    }

    private function getProduct(): object
    {
        return DB::table('products')
            ->where('restaurant_id', $this->restaurantId)
            ->first();
    }
}
