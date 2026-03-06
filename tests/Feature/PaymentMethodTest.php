<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
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

    public function test_admin_can_list_payment_methods(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/payment-methods');

        $response->assertOk();
        // Seeded payment methods should be present
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_mozo_can_list_payment_methods(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/payment-methods')
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_can_view_payment_method(): void
    {
        $method = PaymentMethod::first();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/payment-methods/{$method->id}");

        $response->assertOk();
        $this->assertEquals($method->name, $response->json('data.name'));
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_payment_method(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/payment-methods', [
                'name' => 'Criptomoneda',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('Criptomoneda', $response->json('data.name'));
        $this->assertDatabaseHas('payment_methods', ['name' => 'Criptomoneda']);
    }

    public function test_mozo_cannot_create_payment_method(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/payment-methods', [
                'name' => 'Hack Method',
            ])
            ->assertStatus(403);
    }

    public function test_name_is_required(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/payment-methods', [])
            ->assertStatus(422);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/payment-methods', [
                'name' => 'Efectivo', // Already seeded
            ])
            ->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_payment_method(): void
    {
        $method = PaymentMethod::create(['name' => 'Original']);

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/payment-methods/{$method->id}", [
                'name' => 'Editado',
            ]);

        $response->assertOk();
        $this->assertEquals('Editado', $response->json('data.name'));
    }

    public function test_admin_can_toggle_active_status(): void
    {
        $method = PaymentMethod::create(['name' => 'Toggle Method']);

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("/api/payment-methods/{$method->id}", [
                'name'   => 'Toggle Method',
                'active' => false,
            ]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('data.active'));
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_payment_method(): void
    {
        $method = PaymentMethod::create(['name' => 'Para Eliminar']);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/payment-methods/{$method->id}")
            ->assertOk();

        $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    }

    public function test_mozo_cannot_delete_payment_method(): void
    {
        $method = PaymentMethod::create(['name' => 'Protegido']);

        $this->withHeaders($this->mozoHeaders())
            ->deleteJson("/api/payment-methods/{$method->id}")
            ->assertStatus(403);
    }

    public function test_cannot_delete_payment_method_with_payments(): void
    {
        // The seeded 'Efectivo' method is used by sale payments in tests that create orders+payments.
        // We'll create a scenario with an expense payment.
        $method = PaymentMethod::where('name', 'Efectivo')->firstOrFail();

        // Create an expense payment using this method
        \App\Models\ExpensePayment::create([
            'expense_id'        => \App\Models\Expense::create([
                'restaurant_id'       => $this->restaurantId,
                'expense_category_id' => \App\Models\ExpenseCategory::create([
                    'restaurant_id' => $this->restaurantId,
                    'name'          => 'Test Cat',
                ])->id,
                'expense_status_id'   => \App\Models\ExpenseStatus::where('slug', 'pending')->first()->id,
                'user_id'             => $this->adminUser->id,
                'amount'              => 100,
                'description'         => 'Test',
                'expense_date'        => now(),
            ])->id,
            'payment_method_id' => $method->id,
            'amount'            => 50,
            'paid_at'           => now(),
        ]);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/payment-methods/{$method->id}")
            ->assertStatus(422);
    }
}
