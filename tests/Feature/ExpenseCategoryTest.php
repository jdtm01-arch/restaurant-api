<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    /*
    |--------------------------------------------------------------------------
    | List
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_expense_categories(): void
    {
        ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Insumos',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/expense-categories');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_mozo_can_list_expense_categories(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/expense-categories')
            ->assertOk();
    }

    public function test_categories_are_scoped_to_restaurant(): void
    {
        ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Propia',
        ]);

        // Create category for another restaurant
        $otherRestaurant = \App\Models\Restaurant::create([
            'name' => 'Other Restaurant',
            'ruc'  => '20888999000',
        ]);
        ExpenseCategory::create([
            'restaurant_id' => $otherRestaurant->id,
            'name'          => 'Ajena',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/expense-categories');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Propia', $response->json('data.0.name'));
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_expense_category(): void
    {
        $cat = ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Servicios',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/expense-categories/{$cat->id}");

        $response->assertOk();
        $this->assertEquals('Servicios', $response->json('data.name'));
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_expense_category(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expense-categories', [
                'name' => 'Limpieza',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('Limpieza', $response->json('data.name'));
        $this->assertDatabaseHas('expense_categories', [
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Limpieza',
        ]);
    }

    public function test_mozo_cannot_create_expense_category(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/expense-categories', [
                'name' => 'Hack',
            ])
            ->assertStatus(403);
    }

    public function test_name_is_required(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expense-categories', [])
            ->assertStatus(422);
    }

    public function test_duplicate_name_in_same_restaurant_is_rejected(): void
    {
        ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Insumos',
        ]);

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/expense-categories', [
                'name' => 'Insumos',
            ])
            ->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_expense_category(): void
    {
        $cat = ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Original',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/expense-categories/{$cat->id}", [
                'name' => 'Editada',
            ]);

        $response->assertOk();
        $this->assertEquals('Editada', $response->json('data.name'));
    }

    public function test_admin_can_toggle_active_status(): void
    {
        $cat = ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Toggle',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/expense-categories/{$cat->id}", [
                'name'   => 'Toggle',
                'active' => false,
            ]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('data.active'));
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_expense_category(): void
    {
        $cat = ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Para Eliminar',
        ]);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/expense-categories/{$cat->id}")
            ->assertOk();

        $this->assertSoftDeleted('expense_categories', ['id' => $cat->id]);
    }

    public function test_mozo_cannot_delete_expense_category(): void
    {
        $cat = ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Protegida',
        ]);

        $this->withHeaders($this->mozoHeaders())
            ->deleteJson("/api/expense-categories/{$cat->id}")
            ->assertStatus(403);
    }

    public function test_cannot_delete_category_with_expenses(): void
    {
        $cat = ExpenseCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name'          => 'Con Gastos',
        ]);

        $status = ExpenseStatus::where('slug', 'pending')->firstOrFail();

        Expense::create([
            'restaurant_id'       => $this->restaurantId,
            'expense_category_id' => $cat->id,
            'expense_status_id'   => $status->id,
            'user_id'             => $this->adminUser->id,
            'amount'              => 100,
            'description'         => 'Test expense',
            'expense_date'        => now(),
        ]);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/expense-categories/{$cat->id}")
            ->assertStatus(422);
    }
}
