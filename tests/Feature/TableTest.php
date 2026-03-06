<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class TableTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    public function test_admin_can_list_tables(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/tables')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create_table(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/tables', [
                'number'   => 2,
                'name'     => 'Mesa 2',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('Mesa 2', $response->json('data.name'));
    }

    public function test_admin_can_update_table(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/tables/{$this->table->id}", [
                'number'   => $this->table->number,
                'name'     => 'Mesa VIP',
            ]);

        $response->assertOk();
        $this->assertEquals('Mesa VIP', $response->json('data.name'));
    }

    public function test_admin_can_soft_delete_and_restore_table(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/tables/{$this->table->id}")
            ->assertOk();

        $this->assertSoftDeleted('tables', ['id' => $this->table->id]);

        $this->withHeaders($this->adminHeaders())
            ->putJson("/api/tables/{$this->table->id}/restore")
            ->assertOk();

        $this->assertDatabaseHas('tables', [
            'id'         => $this->table->id,
            'deleted_at' => null,
        ]);
    }

    public function test_mozo_cannot_create_table(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/tables', ['number' => 99, 'name' => 'Hack'])
            ->assertStatus(403);
    }

    public function test_create_table_validation_fails(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/tables', [])
            ->assertStatus(422);
    }
}
