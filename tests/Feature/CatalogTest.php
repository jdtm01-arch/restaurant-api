<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function authHeaders(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_can_list_roles(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/catalogs/roles');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_can_list_payment_methods(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/catalogs/payment-methods');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_can_list_expense_statuses(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/catalogs/expense-statuses');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_unauthenticated_cannot_access_catalogs(): void
    {
        $this->getJson('/api/catalogs/roles')
            ->assertStatus(401);
    }
}
