<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class ApiTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_not_configured_without_credentials(): void {
        Functions\when('get_option')->justReturn('');
        $this->assertFalse((new WCM_API())->is_configured());
    }

    public function test_configured_with_credentials(): void {
        $this->assertTrue((new WCM_API('token', 'secret'))->is_configured());
    }

    public function test_fetch_returns_error_when_not_configured(): void {
        Functions\when('get_option')->justReturn('');

        $result = (new WCM_API())->fetch_leads();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('wcm_not_configured', $result->get_error_code());
    }

    public function test_fetch_parses_leads_from_response(): void {
        Functions\when('wp_parse_args')->alias(fn($a, $d) => array_merge($d, $a));
        Functions\when('is_wp_error')->alias(fn($x) => $x instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'leads' => [['lead_id' => 1, 'lead_status' => 'Qualified']],
            'total_pages' => 1,
        ]));
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);

        $result = (new WCM_API('token', 'secret'))->fetch_leads();

        $this->assertCount(1, $result);
        $this->assertEquals('Qualified', $result[0]['lead_status']);
    }

    public function test_fetch_returns_error_on_401(): void {
        Functions\when('wp_parse_args')->alias(fn($a, $d) => array_merge($d, $a));
        Functions\when('is_wp_error')->alias(fn($x) => $x instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 401]]);

        $result = (new WCM_API('token', 'secret'))->fetch_leads();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('wcm_api_error', $result->get_error_code());
    }

    public function test_fetch_propagates_http_errors(): void {
        Functions\when('wp_parse_args')->alias(fn($a, $d) => array_merge($d, $a));

        $error = new WP_Error('http_error', 'Connection failed');
        Functions\when('wp_remote_get')->justReturn($error);
        Functions\when('is_wp_error')->alias(fn($x) => $x instanceof WP_Error);

        $result = (new WCM_API('token', 'secret'))->fetch_leads();
        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_connection_succeeds_on_200(): void {
        Functions\when('is_wp_error')->alias(fn($x) => $x instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('{}');
        Functions\when('wp_remote_get')->justReturn([]);

        $this->assertTrue((new WCM_API('token', 'secret'))->test_connection());
    }

    public function test_connection_fails_on_401(): void {
        Functions\when('is_wp_error')->alias(fn($x) => $x instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);
        Functions\when('wp_remote_get')->justReturn([]);

        $this->assertFalse((new WCM_API('token', 'secret'))->test_connection());
    }
}
