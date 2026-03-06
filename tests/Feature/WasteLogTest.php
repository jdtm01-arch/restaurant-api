<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class WasteLogTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    public function test_admin_can_create_waste_log(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/waste-logs', [
                'product_id'     => $product->id,
                'quantity'       => 2.5,
                'unit'           => 'kg',
                'reason'         => 'expired',
                'estimated_cost' => 25.00,
                'waste_date'     => now()->toDateString(),
                'description'    => 'Test waste',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('expired', $response->json('data.reason'));
    }

    public function test_cocina_can_create_waste_log(): void
    {
        $product = $this->getProductA();

        $this->withHeaders($this->cocinaHeaders())
            ->postJson('/api/waste-logs', [
                'product_id'     => $product->id,
                'quantity'       => 1,
                'unit'           => 'unidad',
                'reason'         => 'damaged',
                'estimated_cost' => 10.00,
                'waste_date'     => now()->toDateString(),
            ])
            ->assertStatus(201);
    }

    public function test_cocina_can_list_waste_logs(): void
    {
        $this->withHeaders($this->cocinaHeaders())
            ->getJson('/api/waste-logs')
            ->assertOk();
    }

    public function test_cocina_cannot_delete_waste_log(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->cocinaHeaders())
            ->postJson('/api/waste-logs', [
                'product_id'     => $product->id,
                'quantity'       => 1,
                'unit'           => 'unidad',
                'reason'         => 'damaged',
                'estimated_cost' => 5.00,
                'waste_date'     => now()->toDateString(),
            ]);

        $wasteLogId = $response->json('data.id');

        $this->withHeaders($this->cocinaHeaders())
            ->deleteJson("/api/waste-logs/{$wasteLogId}")
            ->assertStatus(403);
    }

    public function test_admin_can_delete_waste_log(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/waste-logs', [
                'product_id'     => $product->id,
                'quantity'       => 1,
                'unit'           => 'kg',
                'reason'         => 'expired',
                'estimated_cost' => 10.00,
                'waste_date'     => now()->toDateString(),
            ]);

        $wasteLogId = $response->json('data.id');

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/waste-logs/{$wasteLogId}")
            ->assertOk();
    }

    public function test_mozo_cannot_create_waste_log(): void
    {
        $product = $this->getProductA();

        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/waste-logs', [
                'product_id'     => $product->id,
                'quantity'       => 1,
                'unit'           => 'kg',
                'reason'         => 'expired',
                'estimated_cost' => 5,
                'waste_date'     => now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_waste_log_validation(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/waste-logs', [])
            ->assertStatus(422);
    }

    public function test_can_update_waste_log(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/waste-logs', [
                'product_id'     => $product->id,
                'quantity'       => 1,
                'unit'           => 'kg',
                'reason'         => 'expired',
                'estimated_cost' => 10.00,
                'waste_date'     => now()->toDateString(),
            ]);

        $wasteLogId = $response->json('data.id');

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/waste-logs/{$wasteLogId}", [
                'quantity'       => 3,
                'estimated_cost' => 30.00,
            ]);

        $response->assertOk();
        $this->assertEquals(3, $response->json('data.quantity'));
    }
}
