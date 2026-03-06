<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('token', $response->json());
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'wrongpassword',
        ])->assertStatus(401);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $this->postJson('/api/login', [
            'email'    => 'nonexistent@example.com',
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/logout')
            ->assertOk();
    }

    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/me');

        $response->assertOk();
        $this->assertEquals($user->email, $response->json('data.email'));
    }

    public function test_unauthenticated_cannot_access_me(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_login_validation_requires_fields(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422);
    }
}
