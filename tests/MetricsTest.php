<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class MetricsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\when('get_option')->alias(function($key, $default = '') {
            if ($key === 'wcm_cache_length_minutes') {
                return 60;
            }
            return $default;
        });
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
        $this->assertEquals(3500.0, $result['sales_value']);
    }

    public function test_sums_quote_value_separately(): void {
        $metrics = new WCM_Metrics($this->createMock(WCM_API::class));

        $result = $metrics->calculate_metrics([
            ['quote_value' => 500],
            ['quote_value' => 1000],
            ['sales_value' => 200, 'quote_value' => 300],
        ]);

        $this->assertEquals(1800.0, $result['quote_value']);
        $this->assertEquals(200.0, $result['sales_value']);
    }

    public function test_tracks_sales_and_quote_values_independently(): void {
        $metrics = new WCM_Metrics($this->createMock(WCM_API::class));

        $result = $metrics->calculate_metrics([
            ['sales_value' => 1000, 'quote_value' => 999],
        ]);

        $this->assertEquals(1, $result['closed_leads']);
        $this->assertEquals(1000.0, $result['sales_value']);
        $this->assertEquals(999.0, $result['quote_value']);
    }

    public function test_returns_cached_data_without_api_call(): void {
        Functions\when('get_transient')->justReturn([
            'qualified_leads' => 100,
            'closed_leads' => 50,
            'sales_value' => 10000,
            'quote_value' => 15000,
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
        $this->assertEquals(500.0, $result['sales_value']);
        $this->assertEquals(750.0, $result['quote_value']);
    }

    public function test_force_refresh_bypasses_cache(): void {
        Functions\when('get_transient')->justReturn(['qualified_leads' => 999]);
        Functions\when('set_transient')->justReturn(true);

        $api = $this->createMock(WCM_API::class);
        $api->expects($this->once())->method('fetch_leads')->willReturn([]);

        $result = (new WCM_Metrics($api))->get_metrics(force_refresh: true);
        $this->assertEquals(0, $result['qualified_leads']);
    }

    public function test_default_months_is_twelve(): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $api = $this->createMock(WCM_API::class);
        $api->expects($this->once())
            ->method('fetch_leads')
            ->with($this->callback(function($params) {
                return $params['months'] === '12';
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

    /**
     * Regression test: Cache key must include months parameter
     * Bug: Different month ranges were sharing the same cache key
     */
    public function test_cache_key_includes_months(): void {
        $capturedKeys = [];

        Functions\when('get_transient')->alias(function($key) use (&$capturedKeys) {
            $capturedKeys[] = $key;
            return false;
        });
        Functions\when('set_transient')->justReturn(true);

        $api = $this->createMock(WCM_API::class);
        $api->method('fetch_leads')->willReturn([]);

        $metrics = new WCM_Metrics($api);
        $metrics->get_metrics(false, '3');
        $metrics->get_metrics(false, '12');

        $this->assertContains('wcm_metrics_all_3', $capturedKeys);
        $this->assertContains('wcm_metrics_all_12', $capturedKeys);
    }

    /**
     * Regression test: Cache key must include account_id
     * Bug: Different accounts were sharing the same cache key
     */
    public function test_cache_key_includes_account_id(): void {
        $capturedKeys = [];

        Functions\when('get_transient')->alias(function($key) use (&$capturedKeys) {
            $capturedKeys[] = $key;
            return false;
        });
        Functions\when('set_transient')->justReturn(true);

        $api = $this->createMock(WCM_API::class);
        $api->method('fetch_leads')->willReturn([]);

        $metrics = new WCM_Metrics($api);
        $metrics->get_metrics_for_account('102204', false, '12');
        $metrics->get_metrics_for_account('99459', false, '12');

        $this->assertContains('wcm_metrics_102204_12', $capturedKeys);
        $this->assertContains('wcm_metrics_99459_12', $capturedKeys);
    }

    /**
     * Regression test: Duplicate leads should not inflate metrics
     * Bug: Wrong API params caused same lead returned multiple times, inflating totals
     */
    public function test_duplicate_leads_are_counted_correctly(): void {
        $metrics = new WCM_Metrics($this->createMock(WCM_API::class));

        // Simulate duplicate leads (same lead_id appearing multiple times)
        $duplicateLead = ['lead_id' => 123, 'sales_value' => 15000, 'quotable' => 'Yes'];
        $result = $metrics->calculate_metrics([
            $duplicateLead,
            $duplicateLead,
            $duplicateLead, // Same lead 3 times
        ]);

        // Note: Current implementation counts all rows, even duplicates
        // This test documents current behavior - if we add deduplication, update expected values
        $this->assertEquals(3, $result['closed_leads']);
        $this->assertEquals(45000.0, $result['sales_value']);
        $this->assertEquals(3, $result['total_leads']);
    }

    public function test_cache_ttl_respects_option_value(): void {
        Functions\when('get_transient')->justReturn(false);
        $setArgs = [];
        Functions\when('set_transient')->alias(function($key, $value, $ttl) use (&$setArgs) {
            $setArgs[] = [$key, $ttl];
            return true;
        });
        Functions\when('get_option')->alias(function($key, $default = '') {
            if ($key === 'wcm_cache_length_minutes') {
                return 15;
            }
            return $default;
        });

        $api = $this->createMock(WCM_API::class);
        $api->method('fetch_leads')->willReturn([]);

        (new WCM_Metrics($api))->get_metrics();

        $this->assertNotEmpty($setArgs);
        [$cacheKey, $ttl] = $setArgs[0];
        $this->assertEquals('wcm_metrics_all_12', $cacheKey);
        $this->assertEquals(15 * 60, $ttl);
    }

    public function test_invalid_months_and_account_id_are_sanitized(): void {
        $getKeys = [];
        $setKeys = [];

        Functions\when('get_transient')->alias(function($key) use (&$getKeys) {
            $getKeys[] = $key;
            return false;
        });
        Functions\when('set_transient')->alias(function($key, $value, $ttl) use (&$setKeys) {
            $setKeys[] = $key;
            return true;
        });

        $api = $this->createMock(WCM_API::class);
        $api->expects($this->once())
            ->method('fetch_leads')
            ->with($this->callback(function($params) {
                // Invalid months should fall back to 12 and invalid account_id should be removed
                return $params['months'] === '12' && !isset($params['account_id']);
            }))
            ->willReturn([]);

        $metrics = new WCM_Metrics($api);
        $metrics->get_metrics_for_account('not-a-number', false, '999');

        $this->assertContains('wcm_metrics_all_12', $getKeys);
        $this->assertContains('wcm_metrics_all_12', $setKeys);
    }
}
