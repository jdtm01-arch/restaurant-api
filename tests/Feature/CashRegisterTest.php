<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    public function test_admin_can_open_cash_register(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 200]);

        $response->assertStatus(201);
        $this->assertEquals('open', $response->json('data.status'));
        $this->assertEquals(200, $response->json('data.opening_amount'));
    }

    public function test_cannot_open_second_cash_register_same_day(): void
    {
        $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(409);
    }

    public function test_can_get_current_open_register(): void
    {
        $this->openCashRegister();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/cash-registers/current');

        $response->assertOk();
        $this->assertEquals('open', $response->json('data.status'));
    }

    public function test_admin_can_close_cash_register(): void
    {
        $crId = $this->openCashRegister();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", [
                'closing_amount_real' => 250,
            ]);

        $response->assertOk();
        $this->assertEquals('closed', $response->json('data.status'));
    }

    public function test_cannot_close_already_closed_register(): void
    {
        $crId = $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", [
                'closing_amount_real' => 200,
            ])
            ->assertOk();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", [
                'closing_amount_real' => 200,
            ])
            ->assertStatus(422);
    }

    public function test_can_generate_x_report(): void
    {
        $crId = $this->openCashRegister();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/cash-registers/{$crId}/x-report");

        $response->assertOk();
        $this->assertArrayHasKey('total_sales', $response->json('data'));
    }

    public function test_mozo_cannot_open_cash_register(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(403);
    }

    public function test_caja_can_open_cash_register(): void
    {
        $this->withHeaders($this->cajaHeaders())
            ->postJson('/api/cash-registers', ['opening_amount' => 100])
            ->assertStatus(201);
    }

    public function test_open_requires_amount(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-registers', [])
            ->assertStatus(422);
    }

    public function test_cannot_close_with_open_orders(): void
    {
        $crId = $this->openCashRegister();
        $product = $this->getProductA();

        // Create an open order
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(201);

        // Try to close cash register — should fail
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", [
                'closing_amount_real' => 200,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'OPEN_ORDERS_EXIST']);
    }

    public function test_cannot_close_with_pending_orders(): void
    {
        $crId = $this->openCashRegister();
        $product = $this->getProductA();

        // Create an order and close it (status = closed / por cobrar)
        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $response->json('data.id');

        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/close")
            ->assertOk();

        // Try to close cash register — should fail because of pending (closed) orders
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", [
                'closing_amount_real' => 200,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'OPEN_ORDERS_EXIST']);
    }
}
