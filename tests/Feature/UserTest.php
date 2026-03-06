<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    public function test_admin_can_list_users(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/users');

        $response->assertOk();
        // Should include admin, caja, mozo, cocina
        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    public function test_admin_can_create_user(): void
    {
        $role = \App\Models\Role::where('slug', 'mozo')->first();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/users', [
                'name'     => 'Nuevo Mozo',
                'email'    => 'nuevomozo@test.com',
                'password' => 'password123',
                'role_id'  => $role->id,
            ]);

        $response->assertStatus(201);
        $this->assertEquals('Nuevo Mozo', $response->json('data.name'));
        $this->assertEquals('mozo', $response->json('data.role.slug'));
    }

    public function test_admin_can_show_user(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/users/{$this->mozoUser->id}");

        $response->assertOk();
        $this->assertEquals($this->mozoUser->name, $response->json('data.name'));
    }

    public function test_admin_can_update_user(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/users/{$this->mozoUser->id}", [
                'name' => 'Updated Mozo',
            ]);

        $response->assertOk();
        $this->assertEquals('Updated Mozo', $response->json('data.name'));
    }

    public function test_admin_can_delete_user(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/users/{$this->mozoUser->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('restaurant_user', [
            'restaurant_id' => $this->restaurantId,
            'user_id'       => $this->mozoUser->id,
        ]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/users/{$this->adminUser->id}")
            ->assertStatus(422);
    }

    public function test_admin_can_reset_password(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/users/{$this->mozoUser->id}/reset-password", [
                'password' => 'newpassword123',
            ]);

        $response->assertOk();

        // Verify login with new password
        $this->postJson('/api/login', [
            'email'    => $this->mozoUser->email,
            'password' => 'newpassword123',
        ])->assertOk();
    }

    public function test_mozo_cannot_manage_users(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/users')
            ->assertStatus(403);
    }

    public function test_caja_cannot_manage_users(): void
    {
        $this->withHeaders($this->cajaHeaders())
            ->getJson('/api/users')
            ->assertStatus(403);
    }

    public function test_create_user_email_must_be_unique(): void
    {
        $role = \App\Models\Role::where('slug', 'mozo')->first();

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/users', [
                'name'     => 'Duplicate',
                'email'    => $this->mozoUser->email,
                'password' => 'password123',
                'role_id'  => $role->id,
            ])
            ->assertStatus(422);
    }

    /* ───────────────────────────────────────────────────────────
     * admin_restaurante cannot manage admin_general users
     * ─────────────────────────────────────────────────────────── */

    public function test_admin_restaurante_cannot_create_user_with_admin_general_role(): void
    {
        $adminGeneralRole = \App\Models\Role::where('slug', 'admin_general')->first();

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/users', [
                'name'     => 'Nuevo Admin',
                'email'    => 'nuevoadmin@test.com',
                'password' => 'password123',
                'role_id'  => $adminGeneralRole->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.message', 'No tienes permiso para asignar el rol de administrador general.');
    }

    public function test_admin_restaurante_cannot_update_admin_general_user(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson("/api/users/{$this->adminGeneralUser->id}", [
                'name' => 'Hackeado',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.message', 'No tienes permiso para modificar un administrador general.');
    }

    public function test_admin_restaurante_cannot_assign_admin_general_role(): void
    {
        $adminGeneralRole = \App\Models\Role::where('slug', 'admin_general')->first();

        $this->withHeaders($this->adminHeaders())
            ->putJson("/api/users/{$this->mozoUser->id}", [
                'role_id' => $adminGeneralRole->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.message', 'No tienes permiso para asignar el rol de administrador general.');
    }

    public function test_admin_restaurante_cannot_delete_admin_general_user(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/users/{$this->adminGeneralUser->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.message', 'No tienes permiso para modificar un administrador general.');
    }

    public function test_admin_restaurante_cannot_reset_password_of_admin_general_user(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/users/{$this->adminGeneralUser->id}/reset-password", [
                'password' => 'newpassword123',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.message', 'No tienes permiso para modificar un administrador general.');
    }

    public function test_admin_general_can_manage_admin_restaurante_user(): void
    {
        // admin_general should be able to update an admin_restaurante user
        $this->withHeaders($this->adminGeneralHeaders())
            ->putJson("/api/users/{$this->adminUser->id}", [
                'name' => 'Updated by General',
            ])
            ->assertOk();

        // admin_general should be able to reset password of admin_restaurante
        $this->withHeaders($this->adminGeneralHeaders())
            ->postJson("/api/users/{$this->adminUser->id}/reset-password", [
                'password' => 'newpassword123',
            ])
            ->assertOk();
    }

    public function test_admin_general_can_create_admin_general_user(): void
    {
        $adminGeneralRole = \App\Models\Role::where('slug', 'admin_general')->first();

        $this->withHeaders($this->adminGeneralHeaders())
            ->postJson('/api/users', [
                'name'     => 'Otro Admin General',
                'email'    => 'otroadmin@test.com',
                'password' => 'password123',
                'role_id'  => $adminGeneralRole->id,
            ])
            ->assertStatus(201);
    }
}
