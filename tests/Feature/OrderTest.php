<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
        $this->openCashRegister();
    }

    /*
    |--------------------------------------------------------------------------
    | Creation
    |--------------------------------------------------------------------------
    */

    public function test_mozo_can_create_dine_in_order(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel'  => 'dine_in',
                'table_id' => $this->table->id,
                'items'    => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertEquals('open', $response->json('data.status'));
        $this->assertEquals('dine_in', $response->json('data.channel'));
        $this->assertEquals(50.00, $response->json('data.total')); // 25*2
    }

    public function test_mozo_can_create_takeaway_order(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertEquals('takeaway', $response->json('data.channel'));
        $this->assertNull($response->json('data.table_id'));
    }

    public function test_dine_in_requires_table(): void
    {
        $product = $this->getProductA();

        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'dine_in',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_cannot_create_order_on_occupied_table(): void
    {
        $product = $this->getProductA();

        // First order
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel'  => 'dine_in',
                'table_id' => $this->table->id,
                'items'    => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(201);

        // Second order same table
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel'  => 'dine_in',
                'table_id' => $this->table->id,
                'items'    => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(409);
    }

    public function test_requires_items(): void
    {
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [],
            ])
            ->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Item management
    |--------------------------------------------------------------------------
    */

    public function test_can_add_item_to_order(): void
    {
        $productA = $this->getProductA();
        $productB = $this->getProductB();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $productA->id, 'quantity' => 1],
                ],
            ]);
        $orderId = $response->json('data.id');

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/items", [
                'product_id' => $productB->id,
                'quantity'   => 2,
            ]);

        $response->assertStatus(201);
        $this->assertEquals(55.00, $response->json('data.order.total')); // 25 + 15*2
    }

    public function test_adding_same_product_merges(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);
        $orderId = $response->json('data.id');

        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/items", [
                'product_id' => $product->id,
                'quantity'   => 2,
            ]);

        // Should have 1 item with quantity 3
        $response = $this->withHeaders($this->mozoHeaders())
            ->getJson("/api/orders/{$orderId}");

        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals(3, $items[0]['quantity']);
    }

    public function test_can_update_item_quantity(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);
        $orderId = $response->json('data.id');

        $order = $this->withHeaders($this->mozoHeaders())
            ->getJson("/api/orders/{$orderId}");
        $itemId = $order->json('data.items.0.id');

        $response = $this->withHeaders($this->mozoHeaders())
            ->patchJson("/api/orders/{$orderId}/items/{$itemId}/quantity", [
                'quantity' => 5,
            ]);

        $response->assertOk();
        $this->assertEquals(125.00, $response->json('data.order.total')); // 25*5
    }

    public function test_can_remove_item(): void
    {
        $productA = $this->getProductA();
        $productB = $this->getProductB();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $productA->id, 'quantity' => 1],
                    ['product_id' => $productB->id, 'quantity' => 1],
                ],
            ]);
        $orderId = $response->json('data.id');

        $order = $this->withHeaders($this->mozoHeaders())
            ->getJson("/api/orders/{$orderId}");
        $itemId = $order->json('data.items.0.id');

        $this->withHeaders($this->mozoHeaders())
            ->deleteJson("/api/orders/{$orderId}/items/{$itemId}")
            ->assertOk();

        $response = $this->withHeaders($this->mozoHeaders())
            ->getJson("/api/orders/{$orderId}");
        $this->assertCount(1, $response->json('data.items'));
    }

    /*
    |--------------------------------------------------------------------------
    | Discount
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_apply_discount(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 4], // 100
                ],
            ]);
        $orderId = $response->json('data.id');

        // Close the order first (discount is only allowed on closed orders)
        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/close")
            ->assertOk();

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$orderId}/discount", [
                'discount_percentage' => 20,
            ]);

        $response->assertOk();
        $this->assertEquals(80.00, $response->json('data.total'));
        $this->assertEquals(20.00, $response->json('data.discount_amount'));
    }

    public function test_mozo_cannot_apply_discount(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);
        $orderId = $response->json('data.id');

        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/discount", [
                'discount_percentage' => 10,
            ])
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Close / Cancel
    |--------------------------------------------------------------------------
    */

    public function test_can_close_order(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);
        $orderId = $response->json('data.id');

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/close");

        $response->assertOk();
        $this->assertEquals('closed', $response->json('data.status'));
    }

    public function test_admin_can_cancel_order(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);
        $orderId = $response->json('data.id');

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$orderId}/cancel", [
                'cancellation_reason' => 'Motivo de prueba',
            ]);

        $response->assertOk();
        $this->assertEquals('cancelled', $response->json('data.status'));
    }

    public function test_mozo_cannot_cancel_order(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);
        $orderId = $response->json('data.id');

        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/cancel", [
                'cancellation_reason' => 'Motivo de prueba',
            ])
            ->assertStatus(403);
    }

    public function test_cannot_close_already_closed_order(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);
        $orderId = $response->json('data.id');

        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/close")
            ->assertOk();

        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/close")
            ->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Kitchen ticket
    |--------------------------------------------------------------------------
    */

    public function test_can_get_kitchen_ticket(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);
        $orderId = $response->json('data.id');

        $response = $this->withHeaders($this->mozoHeaders())
            ->getJson("/api/orders/{$orderId}/kitchen-ticket");

        $response->assertOk();
        $this->assertArrayHasKey('text', $response->json('data'));
    }

    public function test_cocina_cannot_create_order(): void
    {
        $product = $this->getProductA();

        $this->withHeaders($this->cocinaHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_cocina_can_view_orders(): void
    {
        $this->withHeaders($this->cocinaHeaders())
            ->getJson('/api/orders')
            ->assertOk();
    }

    public function test_list_orders_with_status_filter(): void
    {
        $product = $this->getProductA();

        // Create 2 orders
        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(201);

        $response2 = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $secondId = $response2->json('data.id');

        // Close second order
        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$secondId}/close");

        // Filter by open
        $response = $this->withHeaders($this->mozoHeaders())
            ->getJson('/api/orders?status=open');
        $response->assertOk();

        foreach ($response->json('data') as $order) {
            $this->assertEquals('open', $order['status']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Cash Register Closed — Block Operations
    |--------------------------------------------------------------------------
    */

    public function test_cannot_create_order_without_cash_register(): void
    {
        // Close the cash register opened in setUp
        $register = \App\Models\CashRegister::where('restaurant_id', $this->restaurantId)
            ->where('status', 'open')->first();

        // Cancel all open orders first
        \App\Models\Order::where('restaurant_id', $this->restaurantId)
            ->where('status', 'open')
            ->update(['status' => 'cancelled']);

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$register->id}/close", [
                'closing_amount_real' => 200,
            ])
            ->assertOk();

        $product = $this->getProductA();

        $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'NO_CASH_REGISTER_OPEN']);
    }

    public function test_cannot_add_item_when_cash_closed(): void
    {
        $product = $this->getProductA();
        $productB = $this->getProductB();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $response->json('data.id');

        // Close cash register (cancel remaining orders first)
        \App\Models\Order::where('restaurant_id', $this->restaurantId)
            ->where('status', 'open')
            ->where('id', '!=', $orderId)
            ->update(['status' => 'cancelled']);

        // Force-close by cancelling this order's status temporarily, close register, then reopen it
        $order = \App\Models\Order::find($orderId);
        $order->status = 'cancelled';
        $order->save();

        $register = \App\Models\CashRegister::where('restaurant_id', $this->restaurantId)
            ->where('status', 'open')->first();
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$register->id}/close", ['closing_amount_real' => 200])
            ->assertOk();

        // Restore order to open status (simulates order left in DB but cash closed)
        $order->status = 'open';
        $order->save();

        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/items", [
                'product_id' => $productB->id,
                'quantity' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'NO_CASH_REGISTER_OPEN']);
    }

    public function test_cannot_update_item_quantity_when_cash_closed(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $response->json('data.id');

        $orderData = $this->withHeaders($this->mozoHeaders())
            ->getJson("/api/orders/{$orderId}");
        $itemId = $orderData->json('data.items.0.id');

        // Close cash register
        $order = \App\Models\Order::find($orderId);
        $order->status = 'cancelled';
        $order->save();

        $register = \App\Models\CashRegister::where('restaurant_id', $this->restaurantId)
            ->where('status', 'open')->first();
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$register->id}/close", ['closing_amount_real' => 200])
            ->assertOk();

        $order->status = 'open';
        $order->save();

        $this->withHeaders($this->mozoHeaders())
            ->patchJson("/api/orders/{$orderId}/items/{$itemId}/quantity", [
                'quantity' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'NO_CASH_REGISTER_OPEN']);
    }

    public function test_cannot_remove_item_when_cash_closed(): void
    {
        $product = $this->getProductA();
        $productB = $this->getProductB();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [
                    ['product_id' => $product->id, 'quantity' => 1],
                    ['product_id' => $productB->id, 'quantity' => 1],
                ],
            ]);
        $orderId = $response->json('data.id');

        $orderData = $this->withHeaders($this->mozoHeaders())
            ->getJson("/api/orders/{$orderId}");
        $itemId = $orderData->json('data.items.0.id');

        // Close cash register
        $order = \App\Models\Order::find($orderId);
        $order->status = 'cancelled';
        $order->save();

        $register = \App\Models\CashRegister::where('restaurant_id', $this->restaurantId)
            ->where('status', 'open')->first();
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$register->id}/close", ['closing_amount_real' => 200])
            ->assertOk();

        $order->status = 'open';
        $order->save();

        $this->withHeaders($this->mozoHeaders())
            ->deleteJson("/api/orders/{$orderId}/items/{$itemId}")
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'NO_CASH_REGISTER_OPEN']);
    }

    public function test_cannot_apply_discount_when_cash_closed(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 4]],
            ]);
        $orderId = $response->json('data.id');

        // Close the order (status = closed / por cobrar)
        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/close")
            ->assertOk();

        // Close cash register
        $order = \App\Models\Order::find($orderId);
        $order->status = 'cancelled';
        $order->save();

        $register = \App\Models\CashRegister::where('restaurant_id', $this->restaurantId)
            ->where('status', 'open')->first();
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$register->id}/close", ['closing_amount_real' => 200])
            ->assertOk();

        $order->status = 'closed';
        $order->save();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$orderId}/discount", [
                'discount_percentage' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'NO_CASH_REGISTER_OPEN']);
    }

    public function test_cannot_reopen_order_when_cash_closed(): void
    {
        $product = $this->getProductA();

        $response = $this->withHeaders($this->mozoHeaders())
            ->postJson('/api/orders', [
                'channel' => 'takeaway',
                'items'   => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $response->json('data.id');

        // Close the order
        $this->withHeaders($this->mozoHeaders())
            ->postJson("/api/orders/{$orderId}/close")
            ->assertOk();

        // Close cash register
        $order = \App\Models\Order::find($orderId);
        $order->status = 'cancelled';
        $order->save();

        $register = \App\Models\CashRegister::where('restaurant_id', $this->restaurantId)
            ->where('status', 'open')->first();
        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/cash-registers/{$register->id}/close", ['closing_amount_real' => 200])
            ->assertOk();

        $order->status = 'closed';
        $order->save();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/orders/{$orderId}/reopen")
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'NO_CASH_REGISTER_OPEN']);
    }
}
