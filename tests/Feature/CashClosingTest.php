<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class CashClosingTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    public function test_admin_can_perform_cash_closing(): void
    {
        $crId = $this->openCashRegister();

        // Close the register first
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", [
                'closing_amount_real' => 200,
            ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', [
                'date' => now()->toDateString(),
            ]);

        $response->assertStatus(201);
    }

    public function test_cannot_close_day_with_open_register(): void
    {
        $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', [
                'date' => now()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_cannot_close_same_day_twice(): void
    {
        $crId = $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", [
                'closing_amount_real' => 200,
            ]);

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', [
                'date' => now()->toDateString(),
            ])
            ->assertStatus(201);

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/cash-closings', [
                'date' => now()->toDateString(),
            ])
            ->assertStatus(409);
    }

    public function test_can_preview_closing(): void
    {
        $crId = $this->openCashRegister();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$crId}/close", [
                'closing_amount_real' => 200,
            ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/cash-closings/preview?date=' . now()->toDateString());

        $response->assertOk();
        $this->assertArrayHasKey('total_sales', $response->json('data'));
    }

    public function test_can_list_closings(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/cash-closings')
            ->assertOk();
    }

    public function test_mozo_cannot_perform_closing(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/cash-closings', [
                'date' => now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_caja_cannot_perform_closing(): void
    {
        $this->withHeaders($this->cajaHeaders())
            ->postJson('/api/cash-closings', [
                'date' => now()->toDateString(),
            ])
            ->assertStatus(403);
    }
}
