<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    public function test_admin_can_list_audit_logs(): void
    {
        // Generate some audit logs by opening a cash register
        $this->openCashRegister();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/audit-logs');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_mozo_cannot_access_audit_logs(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/audit-logs')
            ->assertStatus(403);
    }

    public function test_caja_cannot_access_audit_logs(): void
    {
        $this->withHeaders($this->cajaHeaders())
            ->getJson('/api/audit-logs')
            ->assertStatus(403);
    }

    public function test_cocina_cannot_access_audit_logs(): void
    {
        $this->withHeaders($this->cocinaHeaders())
            ->getJson('/api/audit-logs')
            ->assertStatus(403);
    }

    public function test_audit_log_filters_by_entity_type(): void
    {
        $this->openCashRegister();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/audit-logs?entity_type=cash_register');

        $response->assertOk();
        foreach ($response->json('data') as $log) {
            $this->assertEquals('cash_register', $log['entity_type']);
        }
    }
}
