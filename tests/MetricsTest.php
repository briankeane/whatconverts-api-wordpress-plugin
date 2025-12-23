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

    public function test_counts_qualified_leads_by_quotable_field(): void {
        $metrics = new WCM_Metrics($this->createMock(WCM_API::class));

        $result = $metrics->calculate_metrics([
            ['quotable' => 'Yes'],
            ['quotable' => 'yes'],
            ['quotable' => 'YES'],
            ['quotable' => 'No'],
            ['quotable' => ''],
        ]);

        $this->assertEquals(3, $result['qualified_leads']);
        $this->assertEquals(5, $result['total_leads']);
    }

    public function test_counts_closed_leads_by_sales_value(): void {
        $metrics = new WCM_Metrics($this->createMock(WCM_API::class));

        $result = $metrics->calculate_metrics([
            ['sales_value' => 1000],
            ['sales_value' => 500],
            ['sales_value' => 2000],
            ['sales_value' => 0],
            ['quotable' => 'Yes'],
        ]);

        $this->assertEquals(3, $result['closed_leads']);
        $this->assertEquals(3500.0, $result['annual_sales_value']);
    }

    public function test_sums_quote_value_separately(): void {
        $metrics = new WCM_Metrics($this->createMock(WCM_API::class));

        $result = $metrics->calculate_metrics([
            ['quote_value' => 500],
            ['quote_value' => 1000],
            ['sales_value' => 200, 'quote_value' => 300],
        ]);

        $this->assertEquals(1800.0, $result['annual_quote_value']);
        $this->assertEquals(200.0, $result['annual_sales_value']);
    }

    public function test_tracks_sales_and_quote_values_independently(): void {
        $metrics = new WCM_Metrics($this->createMock(WCM_API::class));

        $result = $metrics->calculate_metrics([
            ['sales_value' => 1000, 'quote_value' => 999],
        ]);

        $this->assertEquals(1, $result['closed_leads']);
        $this->assertEquals(1000.0, $result['annual_sales_value']);
        $this->assertEquals(999.0, $result['annual_quote_value']);
    }

    public function test_returns_cached_data_without_api_call(): void {
        Functions\when('get_transient')->justReturn([
            'qualified_leads' => 100,
            'closed_leads' => 50,
            'annual_sales_value' => 10000,
            'annual_quote_value' => 15000,
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
            ['quotable' => 'Yes', 'quote_value' => 750],
            ['sales_value' => 500],
        ]);

        $result = (new WCM_Metrics($api))->get_metrics();

        $this->assertEquals(1, $result['qualified_leads']);
        $this->assertEquals(1, $result['closed_leads']);
        $this->assertEquals(500.0, $result['annual_sales_value']);
        $this->assertEquals(750.0, $result['annual_quote_value']);
    }

    public function test_force_refresh_bypasses_cache(): void {
        Functions\when('get_transient')->justReturn(['qualified_leads' => 999]);
        Functions\when('set_transient')->justReturn(true);

        $api = $this->createMock(WCM_API::class);
        $api->expects($this->once())->method('fetch_leads')->willReturn([]);

        $result = (new WCM_Metrics($api))->get_metrics(force_refresh: true);
        $this->assertEquals(0, $result['qualified_leads']);
    }

    public function test_default_months_is_all_time(): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $api = $this->createMock(WCM_API::class);
        $api->expects($this->once())
            ->method('fetch_leads')
            ->with($this->callback(function($params) {
                return $params['months'] === 'all';
            }))
            ->willReturn([]);

        (new WCM_Metrics($api))->get_metrics();
    }

    public function test_months_parameter_passed_to_api(): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $api = $this->createMock(WCM_API::class);
        $api->expects($this->once())
            ->method('fetch_leads')
            ->with($this->callback(function($params) {
                return $params['months'] === '3';
            }))
            ->willReturn([]);

        (new WCM_Metrics($api))->get_metrics(false, '3');
    }
}
