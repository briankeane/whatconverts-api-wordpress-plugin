<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class APITest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_test_connection_uses_leads_per_page_param(): void {
        // Provide credentials so constructor skips get_option
        $api = new WCM_API('token', 'secret');

        Functions\when('get_option')->alias(fn($name, $default = '') => $default);

        Functions\when('wp_remote_retrieve_response_code')->alias(fn($response) => $response['response']['code'] ?? 0);
        Functions\when('wp_remote_retrieve_body')->alias(fn($response) => $response['body'] ?? '');

        Functions\expect('wp_remote_get')->once()->andReturnUsing(function($url, $args) {
            TestCase::assertStringContainsString('leads_per_page=1', $url);

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['leads' => [], 'total_pages' => 1]),
            ];
        });

        $result = $api->test_connection();

        $this->assertTrue($result);
    }
}
