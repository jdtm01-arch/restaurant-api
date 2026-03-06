<?php

namespace Tests\Feature\Traits;

use App\Models\FinancialAccount;
use App\Models\FinancialMovement;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Trait compartido para setUp de tests.
 * Prepara restaurante, usuarios con roles, productos, mesa, y tokens.
 */
trait SetUpRestaurant
{
    protected Restaurant $restaurant;
    protected int $restaurantId;
    protected User $adminUser;
    protected User $adminGeneralUser;
    protected User $cajaUser;
    protected User $mozoUser;
    protected User $cocinaUser;
    protected string $adminToken;
    protected string $adminGeneralToken;
    protected string $cajaToken;
    protected string $mozoToken;
    protected string $cocinaToken;
    protected Table $table;
    protected ProductCategory $category;
    protected PaymentMethod $cashPaymentMethod;
    protected FinancialAccount $cashFinancialAccount;

    protected function setUpRestaurant(): void
    {
        $this->seed();

        $this->restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'ruc'  => '20999111222',
        ]);
        $this->restaurantId = $this->restaurant->id;

        /** @var array<string, Role> $roles */
        $roles = [
            'admin_general'     => Role::where('slug', 'admin_general')->firstOrFail(),
            'admin_restaurante' => Role::where('slug', 'admin_restaurante')->firstOrFail(),
            'caja'              => Role::where('slug', 'caja')->firstOrFail(),
            'mozo'              => Role::where('slug', 'mozo')->firstOrFail(),
            'cocina'            => Role::where('slug', 'cocina')->firstOrFail(),
        ];

        // Create users
        $this->adminUser        = User::factory()->create(['name' => 'Admin']);
        $this->adminGeneralUser = User::factory()->create(['name' => 'Admin General']);
        $this->cajaUser         = User::factory()->create(['name' => 'Caja']);
        $this->mozoUser         = User::factory()->create(['name' => 'Mozo']);
        $this->cocinaUser       = User::factory()->create(['name' => 'Cocina']);

        $usersAndRoles = [
            ['user' => $this->adminUser, 'role' => $roles['admin_restaurante']],
            ['user' => $this->adminGeneralUser, 'role' => $roles['admin_general']],
            ['user' => $this->cajaUser, 'role' => $roles['caja']],
            ['user' => $this->mozoUser, 'role' => $roles['mozo']],
            ['user' => $this->cocinaUser, 'role' => $roles['cocina']],
        ];

        foreach ($usersAndRoles as $ur) {
            DB::table('restaurant_user')->insert([
                'restaurant_id' => $this->restaurantId,
                'user_id'       => $ur['user']->id,
                'role_id'       => $ur['role']->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Tokens
        $this->adminToken        = $this->adminUser->createToken('test')->plainTextToken;
        $this->adminGeneralToken = $this->adminGeneralUser->createToken('test')->plainTextToken;
        $this->cajaToken         = $this->cajaUser->createToken('test')->plainTextToken;
        $this->mozoToken         = $this->mozoUser->createToken('test')->plainTextToken;
        $this->cocinaToken       = $this->cocinaUser->createToken('test')->plainTextToken;

        // Payment method
        $this->cashPaymentMethod = PaymentMethod::where('name', 'Efectivo')->firstOrFail();

        // Category + Products
        $this->category = ProductCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Test Category',
        ]);

        DB::table('products')->insert([
            [
                'restaurant_id'  => $this->restaurantId,
                'category_id'    => $this->category->id,
                'name'           => 'Product A',
                'price_with_tax' => 25.00,
                'is_active'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'restaurant_id'  => $this->restaurantId,
                'category_id'    => $this->category->id,
                'name'           => 'Product B',
                'price_with_tax' => 15.00,
                'is_active'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);

        // Table
        $this->table = Table::create([
            'restaurant_id' => $this->restaurantId,
            'number'        => 1,
            'name'          => 'Mesa Test',
        ]);

        // Financial: create cash account and mark restaurant as initialized
        $this->cashFinancialAccount = FinancialAccount::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Caja Principal',
            'type'          => 'cash',
            'currency'      => 'PEN',
            'is_active'     => true,
        ]);

        // Initial balance so cash register can be opened with typical amounts
        FinancialMovement::create([
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashFinancialAccount->id,
            'type'                 => FinancialMovement::TYPE_INITIAL_BALANCE,
            'reference_type'       => FinancialMovement::REF_INITIAL_BALANCE,
            'reference_id'         => $this->cashFinancialAccount->id,
            'amount'               => 10000.00,
            'description'          => 'Saldo inicial — Caja Principal',
            'movement_date'        => now()->toDateString(),
            'created_by'           => $this->adminUser->id,
        ]);

        $this->restaurant->update(['financial_initialized_at' => now()]);
    }

    protected function adminHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'    => "Bearer {$this->adminToken}",
            'X-Restaurant-Id'  => $this->restaurantId,
        ];
    }

    protected function adminGeneralHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'    => "Bearer {$this->adminGeneralToken}",
            'X-Restaurant-Id'  => $this->restaurantId,
        ];
    }

    protected function cajaHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'    => "Bearer {$this->cajaToken}",
            'X-Restaurant-Id'  => $this->restaurantId,
        ];
    }

    protected function mozoHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'    => "Bearer {$this->mozoToken}",
            'X-Restaurant-Id'  => $this->restaurantId,
        ];
    }

    protected function cocinaHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'    => "Bearer {$this->cocinaToken}",
            'X-Restaurant-Id'  => $this->restaurantId,
        ];
    }

    protected function getProductA(): object
    {
        return DB::table('products')
            ->where('restaurant_id', $this->restaurantId)
            ->where('name', 'Product A')
            ->first();
    }

    protected function getProductB(): object
    {
        return DB::table('products')
            ->where('restaurant_id', $this->restaurantId)
            ->where('name', 'Product B')
            ->first();
    }

    protected function openCashRegister(float $amount = 200.00): int
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => $amount]);

        return $response->json('data.id');
    }

    protected function createAndCloseOrder(string $channel = 'takeaway', ?int $tableId = null): array
    {
        $productA = $this->getProductA();
        $data = [
            'channel' => $channel,
            'items'   => [['product_id' => $productA->id, 'quantity' => 2]],
        ];

        if ($tableId) {
            $data['table_id'] = $tableId;
        }

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', $data);

        $orderId = $response->json('data.id');
        $total = $response->json('data.total');

        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/close");

        return ['id' => $orderId, 'total' => $total];
    }

    protected function payOrder(int $orderId, float $total): int
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$orderId}/pay", [
                'payments' => [
                    [
                        'payment_method_id'    => $this->cashPaymentMethod->id,
                        'financial_account_id' => $this->cashFinancialAccount->id,
                        'amount'               => $total,
                    ],
                ],
            ]);

        return $response->json('data.id');
    }
}
