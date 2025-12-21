<?php
if (!defined('ABSPATH')) exit;

class WCM_API {
    private const BASE_URL = 'https://app.whatconverts.com/api/v1';

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

        $defaults = [
            'start_date' => gmdate('Y-m-d', strtotime('-12 months')),
            'end_date' => gmdate('Y-m-d'),
            'per_page' => 250,
            'page' => 1,
        ];
        if ($this->profile_id) {
            $defaults['profile_id'] = $this->profile_id;
        }

        $params = wp_parse_args($params, $defaults);
        $all_leads = [];
        $page = 1;
        $total_pages = 1;

        do {
            $params['page'] = $page;
            $response = $this->request('/leads?' . http_build_query($params));

            if (is_wp_error($response)) {
                return $response;
            }

            if (isset($response['leads'])) {
                $all_leads = array_merge($all_leads, $response['leads']);
            }

            $total_pages = $response['total_pages'] ?? 1;
            $page++;
        } while ($page <= $total_pages && $page <= 100);

        return $all_leads;
    }

    public function test_connection(): bool|WP_Error {
        if (!$this->is_configured()) {
            return new WP_Error('wcm_not_configured', 'API credentials not configured');
        }

        $response = $this->request('/leads?per_page=1');
        return !is_wp_error($response);
    }

    private function request(string $endpoint): array|WP_Error {
        $response = wp_remote_get(self::BASE_URL . $endpoint, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->token . ':' . $this->secret),
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
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
}
