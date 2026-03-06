<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
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

    public function test_admin_can_list_categories(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/product-categories');

        $response->assertOk();
        // SetUpRestaurant creates 'Test Category'
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_mozo_can_list_categories(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/product-categories')
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_category(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/product-categories', [
                'name' => 'Bebidas',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('Bebidas', $response->json('data.name'));
    }

    public function test_mozo_cannot_create_category(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/product-categories', [
                'name' => 'Bebidas',
            ])
            ->assertStatus(403);
    }

    public function test_category_name_is_required(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/product-categories', [])
            ->assertStatus(422);
    }

    public function test_category_name_must_be_unique_per_restaurant(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/product-categories', [
                'name' => 'Test Category', // already exists from setUp
            ])
            ->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_category(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/product-categories/{$this->category->id}", [
                'name' => 'Categoría Actualizada',
            ]);

        $response->assertOk();
        $this->assertEquals('Categoría Actualizada', $response->json('data.name'));
    }

    public function test_mozo_cannot_update_category(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->putJson("/api/product-categories/{$this->category->id}", [
                'name' => 'Hack',
            ])
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_category(): void
    {
        $emptyCategory = ProductCategory::create([
            'restaurant_id' => $this->restaurantId,
            'name' => 'Para Borrar',
        ]);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/product-categories/{$emptyCategory->id}")
            ->assertOk();
    }

    public function test_cannot_delete_product_category_with_associated_products(): void
    {
        // $this->category already has Product A and Product B associated
        $response = $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/product-categories/{$this->category->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('error.message', 'No se puede eliminar la categoría porque tiene productos asociados.');
    }

    public function test_mozo_cannot_delete_category(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->deleteJson("/api/product-categories/{$this->category->id}")
            ->assertStatus(403);
    }
}
