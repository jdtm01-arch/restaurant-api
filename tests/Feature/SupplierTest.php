<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    public function test_admin_can_list_suppliers(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/suppliers');

        $response->assertOk();
    }

    public function test_mozo_can_list_suppliers(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/suppliers')
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_supplier(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/suppliers', [
                'name'    => 'Proveedor Test',
                'ruc'     => '20501234567',
                'phone'   => '999888777',
                'address' => 'Av. Prueba 123',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('Proveedor Test', $response->json('data.name'));
    }

    public function test_mozo_cannot_create_supplier(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/suppliers', [
                'name' => 'Hack Supplier',
                'ruc'  => '20999888777',
            ])
            ->assertStatus(403);
    }

    public function test_supplier_name_is_required(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/suppliers', [])
            ->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Proveedor Vista',
            'ruc'  => '20111222333',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/suppliers/{$supplier->id}");

        $response->assertOk();
        $this->assertEquals('Proveedor Vista', $response->json('data.name'));
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Proveedor Original',
            'ruc'  => '20444555666',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/suppliers/{$supplier->id}", [
                'name' => 'Proveedor Editado',
                'ruc'  => '20444555666',
            ]);

        $response->assertOk();
        $this->assertEquals('Proveedor Editado', $response->json('data.name'));
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Para Eliminar',
            'ruc'  => '20777888999',
        ]);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/suppliers/{$supplier->id}")
            ->assertOk();
    }

    public function test_mozo_cannot_delete_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Protegido',
            'ruc'  => '20000111222',
        ]);

        $this->withHeaders($this->mozoHeaders())
            ->deleteJson("/api/suppliers/{$supplier->id}")
            ->assertStatus(403);
    }
}
