<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class CronTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\when('get_option')->justReturn('');
        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_transient')->justReturn(false);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_prewarm_uses_configured_targets(): void {
        $callCount = 0;

        $api = $this->createMock(WCM_API::class);
        $api->method('fetch_leads')->willReturn([]);

        $metrics = $this->getMockBuilder(WCM_Metrics::class)
            ->setConstructorArgs([$api])
            ->onlyMethods(['get_metrics_for_account'])
            ->getMock();

        $metrics->expects($this->exactly(2))
            ->method('get_metrics_for_account')
            ->willReturnCallback(function($account, $force, $months) {
                static $call = 0;
                $call++;
                if ($call === 1) {
                    TestCase::assertSame('102204', $account);
                    TestCase::assertFalse($force);
                    TestCase::assertSame('3', $months);
                } elseif ($call === 2) {
                    TestCase::assertNull($account);
                    TestCase::assertFalse($force);
                    TestCase::assertSame('12', $months);
                }
                return [];
            });

        Functions\when('apply_filters')->alias(function($tag, $value) use ($metrics) {
            if ($tag === 'wcm_prewarm_targets') {
                return [
                    ['102204', '3'],
                    [null, '12'],
                ];
            }
            if ($tag === 'wcm_prewarm_metrics_instance') {
                return $metrics;
            }
            return $value;
        });

        // Override metrics instance used by cron via closure binding
        $ref = new ReflectionClass(WCM_Cron::class);
        $method = $ref->getMethod('prewarm_caches');
        $method->setAccessible(true);

        // Swap in our $metrics by temporarily overriding constructor usage
        Functions\when('wp_next_scheduled')->justReturn(false);
        Functions\when('wp_schedule_event')->justReturn(true);

        $method->invoke(null);
    }
}
