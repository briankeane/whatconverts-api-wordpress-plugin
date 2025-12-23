<?php
if (!defined('ABSPATH')) exit;

class WCM_API {
    private const BASE_URL = 'https://app.whatconverts.com/api/v1';
    private const MAX_REQUESTS_PER_LOAD = 25;

    private static int $request_count = 0;

    public static function get_request_count(): int {
        return self::$request_count;
    }

    private string $token;
    private string $secret;
    private ?string $profile_id;

    public function __construct(string $token = '', string $secret = '', ?string $profile_id = null) {
        $from_options = empty($token) && empty($secret);
        $this->token = $token ?: ($from_options ? get_option('wcm_api_token', '') : '');
        $this->secret = $secret ?: ($from_options ? get_option('wcm_api_secret', '') : '');
        $this->profile_id = $profile_id ?? ($from_options ? (get_option('wcm_profile_id', '') ?: null) : null);
    }

    public function is_configured(): bool {
        return !empty($this->token) && !empty($this->secret);
    }

    public function fetch_leads(array $params = []): array|WP_Error {
        if (!$this->is_configured()) {
            return new WP_Error('wcm_not_configured', 'API credentials not configured');
        }

        $months = $params['months'] ?? '12';
        unset($params['months']);

        if ($months === 'all') {
            return $this->fetch_all_leads($params);
        }

        return $this->fetch_leads_for_period($params, $months);
    }

    private function fetch_leads_for_period(array $params, string $months, ?string $end_date = null): array|WP_Error {
        $end = $end_date ?? gmdate('Y-m-d');
        $start = gmdate('Y-m-d', strtotime("-{$months} months", strtotime($end)));

        $defaults = [
            'leads_per_page' => 2500,  // API max is 2500
            'page_number' => 1,
            'start_date' => $start,
            'end_date' => $end,
        ];

        if ($this->profile_id) {
            $defaults['profile_id'] = $this->profile_id;
        }

        $params = wp_parse_args($params, $defaults);
        $all_leads = [];
        $page_number = 1;
        $total_pages = 1;

        do {
            $params['page_number'] = $page_number;
            $response = $this->request('/leads?' . http_build_query($params));

            if (is_wp_error($response)) {
                return $response;
            }

            if (isset($response['leads'])) {
                self::log("Page $page_number: received " . count($response['leads']) . " leads");
                $all_leads = array_merge($all_leads, $response['leads']);
            }

            $total_pages = $response['total_pages'] ?? 1;
            self::log("Total pages: $total_pages, current page: $page_number");
            $page_number++;
        } while ($page_number <= $total_pages && $page_number <= 100);

        self::log("Total leads fetched: " . count($all_leads));

        return $all_leads;
    }

    private function fetch_all_leads(array $params): array|WP_Error {
        $all_leads = [];
        $end_date = gmdate('Y-m-d');
        $max_chunks = 10; // 10 years max

        for ($chunk = 0; $chunk < $max_chunks; $chunk++) {
            $leads = $this->fetch_leads_for_period($params, '12', $end_date);

            if (is_wp_error($leads)) {
                return $leads;
            }

            if (empty($leads)) {
                break; // No more leads in this period
            }

            $all_leads = array_merge($all_leads, $leads);

            // Move end_date back 12 months for next chunk
            $end_date = gmdate('Y-m-d', strtotime('-12 months', strtotime($end_date)));

            // Small delay between chunks to avoid rate limiting
            if ($chunk < $max_chunks - 1) {
                usleep(200000); // 200ms
            }
        }

        return $all_leads;
    }

    public function test_connection(): bool|WP_Error {
        if (!$this->is_configured()) {
            return new WP_Error('wcm_not_configured', 'API credentials not configured');
        }

        $response = $this->request('/leads?leads_per_page=1');
        return !is_wp_error($response);
    }

    private function request(string $endpoint, int $retry = 0): array|WP_Error {
        // Failsafe: prevent infinite loops
        self::$request_count++;
        if (self::$request_count > self::MAX_REQUESTS_PER_LOAD) {
            self::log("FAILSAFE: Max requests (" . self::MAX_REQUESTS_PER_LOAD . ") exceeded, aborting");
            return new WP_Error('wcm_max_requests', 'Maximum API requests exceeded for this page load');
        }

        self::log("Request #" . self::$request_count . ": " . self::BASE_URL . $endpoint . ($retry > 0 ? " (retry $retry)" : ""));

        $response = wp_remote_get(self::BASE_URL . $endpoint, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->token . ':' . $this->secret),
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            self::log("Error: " . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        self::log("Response: HTTP $code");

        if ($code === 429 && $retry < 3) {
            self::log("Rate limited, backing off...");
            sleep(pow(2, $retry + 1));
            return $this->request($endpoint, $retry + 1);
        }

        if ($code !== 200) {
            return new WP_Error('wcm_api_error', "API returned status $code");
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('wcm_json_error', 'Invalid JSON response');
        }

        return $data;
    }

    private static function log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCM_API] ' . $message);
        }
    }
}
