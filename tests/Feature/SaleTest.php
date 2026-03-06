<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
        $this->openCashRegister();
    }

    public function test_can_pay_closed_order(): void
    {
        $order = $this->createAndCloseOrder();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$order['id']}/pay", [
                'payments' => [
                    ['payment_method_id' => $this->cashPaymentMethod->id, 'financial_account_id' => $this->cashFinancialAccount->id, 'amount' => $order['total']],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.receipt_number'));
        $this->assertEquals($order['total'], $response->json('data.total'));
    }

    public function test_cannot_pay_open_order(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $response->json('data.id');

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$orderId}/pay", [
                'payments' => [
                    ['payment_method_id' => $this->cashPaymentMethod->id, 'financial_account_id' => $this->cashFinancialAccount->id, 'amount' => 25],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_cannot_pay_with_wrong_amount(): void
    {
        $order = $this->createAndCloseOrder();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$order['id']}/pay", [
                'payments' => [
                    ['payment_method_id' => $this->cashPaymentMethod->id, 'financial_account_id' => $this->cashFinancialAccount->id, 'amount' => 1.00],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_cannot_pay_already_paid_order(): void
    {
        $order = $this->createAndCloseOrder();
        $this->payOrder($order['id'], $order['total']);

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$order['id']}/pay", [
                'payments' => [
                    ['payment_method_id' => $this->cashPaymentMethod->id, 'financial_account_id' => $this->cashFinancialAccount->id, 'amount' => $order['total']],
                ],
            ])
            ->assertStatus(409);
    }

    public function test_split_payment_with_multiple_methods(): void
    {
        $card = \App\Models\PaymentMethod::where('name', 'Tarjeta')->firstOrFail();
        $order = $this->createAndCloseOrder();

        $half = $order['total'] / 2;

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$order['id']}/pay", [
                'payments' => [
                    ['payment_method_id' => $this->cashPaymentMethod->id, 'financial_account_id' => $this->cashFinancialAccount->id, 'amount' => $half],
                    ['payment_method_id' => $card->id, 'financial_account_id' => $this->cashFinancialAccount->id, 'amount' => $half],
                ],
            ]);

        $response->assertStatus(201);
    }

    public function test_can_list_sales(): void
    {
        $order = $this->createAndCloseOrder();
        $this->payOrder($order['id'], $order['total']);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/sales');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_can_view_receipt(): void
    {
        $order = $this->createAndCloseOrder();
        $saleId = $this->payOrder($order['id'], $order['total']);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/sales/{$saleId}/receipt");

        $response->assertOk();
        $this->assertArrayHasKey('text', $response->json('data'));
    }

    public function test_cocina_cannot_view_sales(): void
    {
        $this->withHeaders($this->cocinaHeaders())
            ->getJson('/api/sales')
            ->assertStatus(403);
    }
}
