<?php
if (!defined('ABSPATH')) exit;

class WCM_Metrics {
    private WCM_API $api;

    public function __construct(?WCM_API $api = null) {
        $this->api = $api ?? new WCM_API();
    }

    public function get_metrics(bool $force_refresh = false, string $months = 'all'): array|WP_Error {
        return $this->get_metrics_for_account(null, $force_refresh, $months);
    }

    public function get_metrics_for_account(?string $account_id, bool $force_refresh = false, string $months = 'all'): array|WP_Error {
        $cache_key = 'wcm_metrics_' . ($account_id ?: 'all') . '_' . $months;

        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $params = ['months' => $months];
        if ($account_id) {
            $params['account_id'] = $account_id;
        }
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
        $total_sales_value = 0.0;
        $total_quote_value = 0.0;

        foreach ($leads as $lead) {
            if (strtolower($lead['quotable'] ?? '') === 'yes') {
                $qualified++;
            }

            $sales = floatval($lead['sales_value'] ?? 0);
            if ($sales > 0) {
                $closed++;
                $total_sales_value += $sales;
            }

            $quote = floatval($lead['quote_value'] ?? 0);
            if ($quote > 0) {
                $total_quote_value += $quote;
            }
        }

        return [
            'qualified_leads' => $qualified,
            'closed_leads' => $closed,
            'annual_sales_value' => $total_sales_value,
            'annual_quote_value' => $total_quote_value,
            'total_leads' => count($leads),
            'last_updated' => gmdate('Y-m-d H:i:s'),
        ];
    }

    public function clear_cache(?string $account_id = null): void {
        if ($account_id) {
            delete_transient('wcm_metrics_' . $account_id);
        }
        delete_transient('wcm_metrics_all');
    }

    public function clear_all_caches(): void {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wcm\_metrics\_%' ESCAPE '\\' OR option_name LIKE '\_transient\_timeout\_wcm\_metrics\_%' ESCAPE '\\'");
    }
}
