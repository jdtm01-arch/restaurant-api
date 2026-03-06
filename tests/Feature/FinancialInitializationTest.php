<?php

namespace Tests\Feature;

use App\Models\FinancialAccount;
use App\Models\FinancialMovement;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialInitializationTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected int $restaurantId;
    protected User $adminGeneralUser;
    protected User $adminUser;
    protected string $adminGeneralToken;
    protected string $adminToken;
    protected FinancialAccount $cashAccount;
    protected FinancialAccount $digitalAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->restaurant = Restaurant::create([
            'name' => 'Init Test Restaurant',
            'ruc'  => '20999333444',
        ]);
        $this->restaurantId = $this->restaurant->id;

        $roleAdminGeneral = Role::where('slug', 'admin_general')->firstOrFail();
        $roleAdmin = Role::where('slug', 'admin_restaurante')->firstOrFail();

        $this->adminGeneralUser = User::factory()->create(['name' => 'Admin General']);
        $this->adminUser = User::factory()->create(['name' => 'Admin']);

        DB::table('restaurant_user')->insert([
            [
                'restaurant_id' => $this->restaurantId,
                'user_id'       => $this->adminGeneralUser->id,
                'role_id'       => $roleAdminGeneral->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'restaurant_id' => $this->restaurantId,
                'user_id'       => $this->adminUser->id,
                'role_id'       => $roleAdmin->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        $this->adminGeneralToken = $this->adminGeneralUser->createToken('test')->plainTextToken;
        $this->adminToken = $this->adminUser->createToken('test')->plainTextToken;

        // Create accounts (NOT initialized yet)
        $this->cashAccount = FinancialAccount::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Caja Principal',
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
    }

    /* ================================================================
     * STATUS ENDPOINT
     * ================================================================ */

    public function test_can_get_initialization_status_not_initialized(): void
    {
        $response = $this->withHeaders($this->adminGeneralHeaders())
            ->getJson('/api/financial/status');

        $response->assertOk()
            ->assertJsonPath('data.initialized', false)
            ->assertJsonPath('data.has_accounts', true);

        $this->assertNull($response->json('data.initialized_at'));
        $this->assertCount(2, $response->json('data.accounts'));
    }

    public function test_can_get_initialization_status_initialized(): void
    {
        $this->restaurant->update(['financial_initialized_at' => now()]);

        $response = $this->withHeaders($this->adminGeneralHeaders())
            ->getJson('/api/financial/status');

        $response->assertOk()
            ->assertJsonPath('data.initialized', true);

        $this->assertNotNull($response->json('data.initialized_at'));
    }

    /* ================================================================
     * INITIALIZATION
     * ================================================================ */

    public function test_admin_general_can_initialize_accounts(): void
    {
        $response = $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/financial/initialize', [
                'accounts' => [
                    ['id' => $this->cashAccount->id, 'initial_balance' => 500.00],
                    ['id' => $this->digitalAccount->id, 'initial_balance' => 200.00],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertEquals(700, $response->json('data.total_initialized'));

        // Verify restaurant is now initialized
        $this->restaurant->refresh();
        $this->assertTrue($this->restaurant->isFinancialInitialized());

        // Verify movements were created
        $this->assertDatabaseHas('financial_movements', [
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->cashAccount->id,
            'type'                 => 'initial_balance',
            'reference_type'       => 'initial_balance',
            'amount'               => 500.0,
        ]);

        $this->assertDatabaseHas('financial_movements', [
            'restaurant_id'        => $this->restaurantId,
            'financial_account_id' => $this->digitalAccount->id,
            'type'                 => 'initial_balance',
            'amount'               => 200.0,
        ]);
    }

    public function test_zero_balance_accounts_do_not_create_movements(): void
    {
        $response = $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/financial/initialize', [
                'accounts' => [
                    ['id' => $this->cashAccount->id, 'initial_balance' => 500.00],
                    ['id' => $this->digitalAccount->id, 'initial_balance' => 0],
                ],
            ]);

        $response->assertStatus(201);

        // Only 1 movement (cash), digital had 0
        $this->assertEquals(1, FinancialMovement::where('restaurant_id', $this->restaurantId)
            ->where('type', 'initial_balance')
            ->count());
    }

    public function test_non_admin_general_cannot_initialize(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/financial/initialize', [
                'accounts' => [
                    ['id' => $this->cashAccount->id, 'initial_balance' => 500.00],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_initialize_twice(): void
    {
        // First initialization
        $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/financial/initialize', [
                'accounts' => [
                    ['id' => $this->cashAccount->id, 'initial_balance' => 500.00],
                ],
            ])
            ->assertStatus(201);

        // Second attempt
        $response = $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/financial/initialize', [
                'accounts' => [
                    ['id' => $this->cashAccount->id, 'initial_balance' => 1000.00],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_initialization_validation_rejects_invalid_data(): void
    {
        $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/financial/initialize', [
                'accounts' => [],
            ])
            ->assertStatus(422);

        $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/financial/initialize', [
                'accounts' => [
                    ['id' => 99999, 'initial_balance' => 500.00],
                ],
            ])
            ->assertStatus(422);
    }

    /* ================================================================
     * MIDDLEWARE: OPERATIONS BLOCKED WITHOUT INITIALIZATION
     * ================================================================ */

    public function test_cash_register_blocked_without_initialization(): void
    {
        $response = $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 100]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Las cuentas financieras del restaurante no han sido inicializadas. Contacte al administrador.']);
    }

    public function test_account_transfers_blocked_without_initialization(): void
    {
        $response = $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/account-transfers', [
                'from_account_id' => $this->cashAccount->id,
                'to_account_id'   => $this->digitalAccount->id,
                'amount'          => 100,
            ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Las cuentas financieras del restaurante no han sido inicializadas. Contacte al administrador.']);
    }

    public function test_operations_work_after_initialization(): void
    {
        // Initialize
        $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/financial/initialize', [
                'accounts' => [
                    ['id' => $this->cashAccount->id, 'initial_balance' => 1000.00],
                    ['id' => $this->digitalAccount->id, 'initial_balance' => 0],
                ],
            ])
            ->assertStatus(201);

        // Now cash register should work
        $response = $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 1000.00]);

        $response->assertStatus(201);
    }

    /* ================================================================
     * BALANCE INCLUDES INITIAL BALANCE
     * ================================================================ */

    public function test_balance_includes_initial_balance(): void
    {
        // Initialize
        $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/financial/initialize', [
                'accounts' => [
                    ['id' => $this->cashAccount->id, 'initial_balance' => 500.00],
                    ['id' => $this->digitalAccount->id, 'initial_balance' => 200.00],
                ],
            ])
            ->assertStatus(201);

        // Check balance
        $balance = \App\Services\FinancialAccountService::getAccountBalance(
            $this->cashAccount->id,
            $this->restaurantId
        );
        $this->assertEquals(500.0, $balance);

        // Check consolidated
        $all = \App\Services\FinancialAccountService::getAllBalances($this->restaurantId);
        $this->assertEquals(700.0, $all['total']);
    }

    /* ================================================================
     * MULTI-TENANT ISOLATION
     * ================================================================ */

    public function test_initialization_is_per_restaurant(): void
    {
        // Initialize restaurant 1
        $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/financial/initialize', [
                'accounts' => [
                    ['id' => $this->cashAccount->id, 'initial_balance' => 500.00],
                ],
            ])
            ->assertStatus(201);

        // Create restaurant 2
        $r2 = Restaurant::create(['name' => 'Restaurant 2', 'ruc' => '20999555666']);

        // R2 should NOT be initialized
        $this->assertFalse($r2->isFinancialInitialized());

        // R2 status endpoint
        $user2 = User::factory()->create();
        $role = Role::where('slug', 'admin_general')->firstOrFail();
        DB::table('restaurant_user')->insert([
            'restaurant_id' => $r2->id,
            'user_id'       => $user2->id,
            'role_id'       => $role->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        $token2 = $user2->createToken('test')->plainTextToken;

        $this->app['auth']->forgetGuards();

        $response = $this->withHeaders([
            'Authorization'   => "Bearer {$token2}",
            'X-Restaurant-Id' => $r2->id,
        ])->getJson('/api/financial/status');

        $response->assertOk()
            ->assertJsonPath('data.initialized', false);
    }

    /* ================================================================
     * HELPERS
     * ================================================================ */

    protected function adminGeneralHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'   => "Bearer {$this->adminGeneralToken}",
            'X-Restaurant-Id' => $this->restaurantId,
        ];
    }

    protected function adminHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return [
            'Authorization'   => "Bearer {$this->adminToken}",
            'X-Restaurant-Id' => $this->restaurantId,
        ];
    }
}
