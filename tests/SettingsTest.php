<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class SettingsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\when('get_option')->alias(function($key, $default = '') {
            if (in_array($key, ['wcm_api_token', 'wcm_api_secret'], true)) {
                return 'x';
            }
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

    public function test_preview_uses_cache_only_when_missing(): void {
        Functions\when('get_transient')->justReturn(false);

        $method = new ReflectionMethod(WCM_Settings::class, 'render_metrics_preview');
        $method->setAccessible(true);

        ob_start();
        $method->invoke(null);
        $output = ob_get_clean();

        $this->assertStringContainsString('No cached data yet', $output);
    }

    public function test_preview_shows_cached_metrics(): void {
        Functions\when('get_transient')->justReturn([
            'qualified_leads' => 5,
            'closed_leads' => 2,
            'sales_value' => 1234,
            'quote_value' => 5678,
            'total_leads' => 10,
        ]);

        $method = new ReflectionMethod(WCM_Settings::class, 'render_metrics_preview');
        $method->setAccessible(true);

        ob_start();
        $method->invoke(null);
        $output = ob_get_clean();

        $this->assertStringContainsString('Qualified', $output);
        $this->assertStringContainsString('5', $output);
        $this->assertStringContainsString('1,234', $output);
        $this->assertStringContainsString('5,678', $output);
    }
}
