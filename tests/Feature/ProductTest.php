<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class ProductTest extends TestCase
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

    public function test_admin_can_list_products(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/products');

        $response->assertOk();
        // SetUpRestaurant creates Product A and Product B
        $this->assertGreaterThanOrEqual(2, $response->json('total'));
    }

    public function test_mozo_can_list_products(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/products')
            ->assertOk();
    }

    public function test_can_filter_products_by_category(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/products?category_id={$this->category->id}");

        $response->assertOk();
        foreach ($response->json('data') as $product) {
            $this->assertEquals($this->category->id, $product['category_id']);
        }
    }

    public function test_can_filter_products_by_active_status(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/products?active=1');

        $response->assertOk();
        foreach ($response->json('data') as $product) {
            $this->assertTrue((bool) $product['is_active']);
        }
    }

    public function test_can_search_products_by_name(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/products?search=Product A');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_product(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/products', [
                'name'           => 'Nuevo Producto',
                'category_id'    => $this->category->id,
                'price_with_tax' => 45.50,
                'description'    => 'Descripción de prueba',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('Nuevo Producto', $response->json('data.name'));
        $this->assertEquals(45.50, $response->json('data.price_with_tax'));
    }

    public function test_mozo_cannot_create_product(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/products', [
                'name'           => 'Hack Product',
                'category_id'    => $this->category->id,
                'price_with_tax' => 10,
            ])
            ->assertStatus(403);
    }

    public function test_product_name_must_be_unique_per_restaurant(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/products', [
                'name'           => 'Product A', // already exists from setUp
                'category_id'    => $this->category->id,
                'price_with_tax' => 10,
            ])
            ->assertStatus(422);
    }

    public function test_product_requires_name_and_price(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/products', [])
            ->assertStatus(422);
    }

    public function test_product_requires_valid_category(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/products', [
                'name'           => 'Sin Categoría',
                'category_id'    => 99999,
                'price_with_tax' => 10,
            ])
            ->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_product(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/products/{$product->id}");

        $response->assertOk();
        $this->assertEquals('Product A', $response->json('data.name'));
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_product(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/products/{$product->id}", [
                'name'           => 'Product A Editado',
                'category_id'    => $this->category->id,
                'price_with_tax' => 30.00,
            ]);

        $response->assertOk();
        $this->assertEquals('Product A Editado', $response->json('data.name'));
    }

    public function test_mozo_cannot_update_product(): void
    {
        $product = $this->getProductA();

        $this->withHeaders($this->mozoHeaders())
            ->putJson("/api/products/{$product->id}", [
                'name'           => 'Hack',
                'category_id'    => $this->category->id,
                'price_with_tax' => 1,
            ])
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete / Restore
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_product(): void
    {
        $product = $this->getProductA();

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/products/{$product->id}")
            ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_admin_can_restore_product(): void
    {
        $product = $this->getProductA();

        // Delete
        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/products/{$product->id}")
            ->assertOk();

        // Restore
        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/products/{$product->id}/restore");

        $response->assertOk();
    }

    public function test_mozo_cannot_delete_product(): void
    {
        $product = $this->getProductA();

        $this->withHeaders($this->mozoHeaders())
            ->deleteJson("/api/products/{$product->id}")
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle Active
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_toggle_product_active(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/products/{$product->id}/toggle-active");

        $response->assertOk();
        // Was active (true), now should be false
        $this->assertFalse($response->json('data.is_active'));
    }
}
