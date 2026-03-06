<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Traits\SetUpRestaurant;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase, SetUpRestaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRestaurant();
    }

    private function seedSaleData(): void
    {
        $this->openCashRegister();
        $order = $this->createAndCloseOrder();
        $this->payOrder($order['id'], $order['total']);
    }

    /*
    |--------------------------------------------------------------------------
    | Access control
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_access_all_reports(): void
    {
        $today = now()->toDateString();

        $endpoints = [
            "/api/reports/sales-by-category?date_from={$today}&date_to={$today}",
            "/api/reports/sales-by-hour?date_from={$today}&date_to={$today}",
            "/api/reports/cancellations-discounts?date_from={$today}&date_to={$today}",
            "/api/reports/sales-by-waiter?date_from={$today}&date_to={$today}",
            "/api/reports/food-cost?date_from={$today}&date_to={$today}",
            "/api/reports/waste?date_from={$today}&date_to={$today}",
            "/api/reports/accounts-payable",
            "/api/reports/daily-cash-flow?date_from={$today}&date_to={$today}",
            "/api/reports/top-products?date_from={$today}&date_to={$today}",
            "/api/reports/daily-summary?date={$today}",
        ];

        foreach ($endpoints as $endpoint) {
            $this->withHeaders($this->adminHeaders())
                ->getJson($endpoint)
                ->assertOk();
        }
    }

    public function test_mozo_cannot_access_reports(): void
    {
        $today = now()->toDateString();

        $this->withHeaders($this->mozoHeaders())
            ->getJson("/api/reports/daily-summary?date={$today}")
            ->assertStatus(403);
    }

    public function test_caja_cannot_access_reports(): void
    {
        $today = now()->toDateString();

        $this->withHeaders($this->cajaHeaders())
            ->getJson("/api/reports/daily-summary?date={$today}")
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Report data
    |--------------------------------------------------------------------------
    */

    public function test_daily_summary_contains_expected_fields(): void
    {
        $this->seedSaleData();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/reports/daily-summary?date=' . now()->toDateString());

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('total_sales', $data);
        $this->assertArrayHasKey('total_orders', $data);
        $this->assertArrayHasKey('average_ticket', $data);
        $this->assertArrayHasKey('total_expenses', $data);
        $this->assertArrayHasKey('net_income', $data);
        $this->assertArrayHasKey('delivery_pct', $data);
        $this->assertArrayHasKey('cancelled_orders', $data);
    }

    public function test_sales_by_category_returns_data(): void
    {
        $this->seedSaleData();
        $today = now()->toDateString();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/reports/sales-by-category?date_from={$today}&date_to={$today}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('totals', $data);
        $this->assertArrayHasKey('by_category', $data);
        $this->assertGreaterThan(0, $data['totals']['total_sales']);
    }

    public function test_top_products_returns_top_and_least(): void
    {
        $this->seedSaleData();
        $today = now()->toDateString();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/reports/top-products?date_from={$today}&date_to={$today}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('top_sellers', $data);
        $this->assertArrayHasKey('least_sellers', $data);
    }

    public function test_accounts_payable_works_without_dates(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/reports/accounts-payable');

        $response->assertOk();
        $this->assertArrayHasKey('summary', $response->json('data'));
    }

    public function test_date_validation_on_reports(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/reports/sales-by-hour')
            ->assertStatus(422);
    }

    public function test_sales_by_hour_returns_24_hours(): void
    {
        $today = now()->toDateString();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/reports/sales-by-hour?date_from={$today}&date_to={$today}");

        $response->assertOk();
        $this->assertCount(24, $response->json('data.by_hour'));
    }

    public function test_waste_report_returns_data(): void
    {
        // Add waste log
        $product = $this->getProductA();
        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/waste-logs', [
                'product_id'     => $product->id,
                'quantity'       => 1,
                'unit'           => 'kg',
                'reason'         => 'expired',
                'estimated_cost' => 20,
                'waste_date'     => now()->toDateString(),
            ]);

        $today = now()->toDateString();

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/reports/waste?date_from={$today}&date_to={$today}");

        $response->assertOk();
        $this->assertEquals(20, $response->json('data.total_estimated_cost'));
    }
}
