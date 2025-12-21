<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class MetricsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_counts_qualified_leads_case_insensitive(): void {
        $metrics = new WCM_Metrics($this->createMock(WCM_API::class));

        $result = $metrics->calculate_metrics([
            ['lead_status' => 'Qualified'],
            ['lead_status' => 'qualified'],
            ['lead_status' => 'Quotable'],
            ['lead_status' => 'New'],
        ]);

        $this->assertEquals(3, $result['qualified_leads']);
        $this->assertEquals(4, $result['total_leads']);
    }

    public function test_counts_closed_leads_and_sums_value(): void {
        $metrics = new WCM_Metrics($this->createMock(WCM_API::class));

        $result = $metrics->calculate_metrics([
            ['lead_status' => 'Closed', 'sales_value' => 1000],
            ['lead_status' => 'closed', 'sales_value' => 500],
            ['lead_status' => 'Converted', 'sales_value' => 2000],
            ['lead_status' => 'Qualified', 'sales_value' => 9999],
        ]);

        $this->assertEquals(3, $result['closed_leads']);
        $this->assertEquals(3500.0, $result['annual_value']);
    }

    public function test_uses_quote_value_when_sales_value_missing(): void {
        $metrics = new WCM_Metrics($this->createMock(WCM_API::class));

        $result = $metrics->calculate_metrics([
            ['lead_status' => 'Closed', 'quote_value' => 500],
            ['lead_status' => 'Closed', 'sales_value' => 1000, 'quote_value' => 999],
        ]);

        $this->assertEquals(1500.0, $result['annual_value']);
    }

    public function test_returns_cached_data_without_api_call(): void {
        Functions\when('get_transient')->justReturn([
            'qualified_leads' => 100,
            'closed_leads' => 50,
            'annual_value' => 10000,
            'total_leads' => 200,
            'last_updated' => '2024-01-01 12:00:00',
        ]);

        $api = $this->createMock(WCM_API::class);
        $api->expects($this->never())->method('fetch_leads');

        $result = (new WCM_Metrics($api))->get_metrics();
        $this->assertEquals(100, $result['qualified_leads']);
    }

    public function test_fetches_from_api_when_cache_empty(): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $api = $this->createMock(WCM_API::class);
        $api->method('fetch_leads')->willReturn([
            ['lead_status' => 'Qualified'],
            ['lead_status' => 'Closed', 'sales_value' => 500],
        ]);

        $result = (new WCM_Metrics($api))->get_metrics();

        $this->assertEquals(1, $result['qualified_leads']);
        $this->assertEquals(1, $result['closed_leads']);
        $this->assertEquals(500.0, $result['annual_value']);
    }

    public function test_force_refresh_bypasses_cache(): void {
        Functions\when('get_transient')->justReturn(['qualified_leads' => 999]);
        Functions\when('set_transient')->justReturn(true);

        $api = $this->createMock(WCM_API::class);
        $api->expects($this->once())->method('fetch_leads')->willReturn([]);

        $result = (new WCM_Metrics($api))->get_metrics(force_refresh: true);
        $this->assertEquals(0, $result['qualified_leads']);
    }
}
