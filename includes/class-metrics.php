<?php
if (!defined('ABSPATH')) exit;

class WCM_Metrics {
    private WCM_API $api;

    public function __construct(?WCM_API $api = null) {
        $this->api = $api ?? new WCM_API();
    }

    public function get_metrics(bool $force_refresh = false): array|WP_Error {
        return $this->get_metrics_for_account(null, $force_refresh);
    }

    public function get_metrics_for_account(?string $account_id, bool $force_refresh = false): array|WP_Error {
        $cache_key = 'wcm_metrics_' . ($account_id ?: 'all');

        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $params = $account_id ? ['account_id' => $account_id] : [];
        $leads = $this->api->fetch_leads($params);

        if (is_wp_error($leads)) {
            return $leads;
        }

        $metrics = $this->calculate_metrics($leads);
        set_transient($cache_key, $metrics, WCM_CACHE_TTL);

        return $metrics;
    }

    public function calculate_metrics(array $leads): array {
        $qualified = 0;
        $closed = 0;
        $value = 0.0;

        foreach ($leads as $lead) {
            $status = strtolower($lead['lead_status'] ?? '');

            if (in_array($status, ['qualified', 'quotable'])) {
                $qualified++;
            }

            if (in_array($status, ['closed', 'converted'])) {
                $closed++;
                $value += floatval($lead['sales_value'] ?? $lead['quote_value'] ?? 0);
            }
        }

        return [
            'qualified_leads' => $qualified,
            'closed_leads' => $closed,
            'annual_value' => $value,
            'total_leads' => count($leads),
            'last_updated' => gmdate('Y-m-d H:i:s'),
        ];
    }

    public function clear_cache(?string $account_id = null): void {
        if ($account_id) {
            delete_transient('wcm_metrics_' . $account_id);
        } else {
            delete_transient('wcm_metrics_all');
        }
    }
}
