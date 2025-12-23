<?php
if (!defined('ABSPATH')) exit;

class WCM_Metrics {
    private WCM_API $api;

    public function __construct(?WCM_API $api = null) {
        $this->api = $api ?? new WCM_API();
    }

    public function get_metrics(bool $force_refresh = false, string $months = '12'): array|WP_Error {
        return $this->get_metrics_for_account(null, $force_refresh, $months);
    }

    public function get_metrics_for_account(?string $account_id, bool $force_refresh = false, string $months = '12'): array|WP_Error {
        $months = $this->sanitize_months($months);
        $account_id = $this->sanitize_account_id($account_id);
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
        set_transient($cache_key, $metrics, wcm_get_cache_ttl());

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
            'sales_value' => $total_sales_value,
            'quote_value' => $total_quote_value,
            'total_leads' => count($leads),
            'last_updated' => gmdate('Y-m-d H:i:s'),
        ];
    }

    public function clear_cache(?string $account_id = null): void {
        $months_options = ['1', '3', '6', '12', 'all'];
        $account_key = $account_id ?: 'all';

        foreach ($months_options as $months) {
            $this->delete_transient_everywhere('wcm_metrics_' . $account_key . '_' . $months);
        }
    }

    private function sanitize_months(string $months): string {
        $allowed = ['1', '3', '6', '12', 'all'];
        return in_array($months, $allowed, true) ? $months : '12';
    }

    private function sanitize_account_id(?string $account_id): ?string {
        if (empty($account_id)) {
            return null;
        }

        return preg_match('/^\d+$/', $account_id) === 1 ? $account_id : null;
    }

    public function clear_all_caches(): void {
        global $wpdb;

        // Get all our transient keys from database
        $transients = $wpdb->get_col(
            "SELECT REPLACE(option_name, '_transient_', '') FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_wcm_metrics_%'
             AND option_name NOT LIKE '_transient_timeout_%'"
        );

        // Delete each from everywhere
        foreach ($transients as $transient) {
            $this->delete_transient_everywhere($transient);
        }

        // Also clear common patterns directly (in case object cache has keys not in DB)
        $months_options = ['1', '3', '6', '12', 'all'];
        $account_ids = ['all', '102204', '106898', '106894', '99459'];

        foreach ($account_ids as $account_id) {
            foreach ($months_options as $months) {
                $this->delete_transient_everywhere('wcm_metrics_' . $account_id . '_' . $months);
            }
        }

        // Nuclear option: also delete directly from database
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_wcm_metrics_%'
             OR option_name LIKE '_transient_timeout_wcm_metrics_%'"
        );
    }

    private function delete_transient_everywhere(string $transient): void {
        // 1. Try WordPress transient API (handles object cache if configured correctly)
        delete_transient($transient);

        // 2. Explicitly delete from object cache (Redis/Memcached)
        wp_cache_delete($transient, 'transient');
        wp_cache_delete($transient, 'options');

        // 3. Explicitly delete from database (in case object cache didn't clear it)
        delete_option('_transient_' . $transient);
        delete_option('_transient_timeout_' . $transient);
    }
}
