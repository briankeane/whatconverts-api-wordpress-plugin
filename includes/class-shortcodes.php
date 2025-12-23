<?php
if (!defined('ABSPATH')) exit;

class WCM_Shortcodes {
    public static function register(): void {
        add_shortcode('wc_qualified_leads', [self::class, 'qualified_leads']);
        add_shortcode('wc_closed_leads', [self::class, 'closed_leads']);
        add_shortcode('wc_sales_value', [self::class, 'sales_value']);
        add_shortcode('wc_quote_value', [self::class, 'quote_value']);
        add_shortcode('wc_total_leads', [self::class, 'total_leads']);
        add_shortcode('wc_last_updated', [self::class, 'last_updated']);
        add_shortcode('wc_debug', [self::class, 'debug']);
    }

    private static function get_metric(string $key, array $atts, bool $show_debug = false) {
        $atts = shortcode_atts(['account_id' => '', 'months' => '12', 'debug' => 'false', 'refresh' => 'false'], $atts);
        $account_id = self::sanitize_account_id($atts['account_id']);
        $months = self::sanitize_months($atts['months']);
        $debug = $atts['debug'] === 'true' && current_user_can('manage_options');
        $force_refresh = $atts['refresh'] === 'true' && current_user_can('manage_options');

        $cache_key = 'wcm_metrics_' . ($account_id ?: 'all') . '_' . $months;
        $cache_status = 'MISS';
        $requests_made = 0;

        $start_time = microtime(true);
        $cached = (!$force_refresh) ? get_transient($cache_key) : false;

        if ($cached !== false) {
            $cache_status = 'HIT';
            $data = $cached;
        } else {
            if (!$force_refresh && self::should_skip_live_fetch()) {
                $cache_status = 'SKIP';
                $data = new WP_Error('wcm_admin_cache_only', 'Live fetch skipped in admin/editor to avoid blocking the request.');
            } else {
                $requests_before = WCM_API::get_request_count();
                $data = (new WCM_Metrics())->get_metrics_for_account($account_id, $force_refresh, $months);
                $requests_made = WCM_API::get_request_count() - $requests_before;
            }
        }

        $elapsed_ms = round((microtime(true) - $start_time) * 1000);

        if ($debug || $show_debug) {
            // Debug output uses already-fetched metrics, no extra API calls
            $debug_data = [
                'shortcode' => 'wc_' . $key,
                'metric' => $key,
                'value' => is_wp_error($data) ? null : ($data[$key] ?? null),
                'cache' => $cache_status,
                'api_requests' => $requests_made,
                'elapsed_ms' => $elapsed_ms,
                'cache_key' => $cache_key,
                'account_id' => $account_id ?: 'all',
                'months' => $months,
                'all_metrics' => is_wp_error($data) ? ['error' => $data->get_error_message()] : $data,
            ];
            $console_script = '<script>console.log("[WCM Debug]", ' . wp_json_encode($debug_data) . ');</script>';

            return [
                'value' => is_wp_error($data) ? null : ($data[$key] ?? null),
                'debug' => $console_script
            ];
        }

        return is_wp_error($data) ? null : ($data[$key] ?? null);
    }

    private static function sanitize_months(string $months): string {
        $allowed = ['1', '3', '6', '12', 'all'];
        return in_array($months, $allowed, true) ? $months : '12';
    }

    private static function sanitize_account_id(?string $account_id): ?string {
        if (empty($account_id)) {
            return null;
        }

        return preg_match('/^\d+$/', $account_id) === 1 ? $account_id : null;
    }

    private static function should_skip_live_fetch(): bool {
        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return false;
        }

        if (function_exists('is_admin') && is_admin()) {
            return true;
        }

        return false;
    }

    public static function qualified_leads($atts): string {
        $result = self::get_metric('qualified_leads', $atts ?: []);
        if (is_array($result)) {
            $val = $result['value'];
            $output = $val !== null ? number_format($val) : '—';
            return esc_html($output) . $result['debug'];
        }
        return esc_html($result !== null ? number_format($result) : '—');
    }

    public static function closed_leads($atts): string {
        $result = self::get_metric('closed_leads', $atts ?: []);
        if (is_array($result)) {
            $val = $result['value'];
            $output = $val !== null ? number_format($val) : '—';
            return esc_html($output) . $result['debug'];
        }
        return esc_html($result !== null ? number_format($result) : '—');
    }

    public static function sales_value($atts): string {
        $result = self::get_metric('sales_value', $atts ?: []);
        if (is_array($result)) {
            $val = $result['value'];
            $output = $val !== null ? '$' . number_format($val) : '—';
            return esc_html($output) . $result['debug'];
        }
        if ($result === null) return esc_html('—');
        return esc_html('$' . number_format($result));
    }

    public static function quote_value($atts): string {
        $result = self::get_metric('quote_value', $atts ?: []);
        if (is_array($result)) {
            $val = $result['value'];
            $output = $val !== null ? '$' . number_format($val) : '—';
            return esc_html($output) . $result['debug'];
        }
        if ($result === null) return esc_html('—');
        return esc_html('$' . number_format($result));
    }

    public static function total_leads($atts): string {
        $result = self::get_metric('total_leads', $atts ?: []);
        if (is_array($result)) {
            $val = $result['value'];
            $output = $val !== null ? number_format($val) : '—';
            return esc_html($output) . $result['debug'];
        }
        return esc_html($result !== null ? number_format($result) : '—');
    }

    public static function last_updated($atts): string {
        $a = shortcode_atts(['account_id' => '', 'months' => '12', 'format' => 'M j, Y g:i a'], $atts ?: []);
        $account_id = $a['account_id'] ?: null;
        $months = $a['months'];

        $data = (new WCM_Metrics())->get_metrics_for_account($account_id, false, $months);
        $val = is_wp_error($data) ? null : ($data['last_updated'] ?? null);

        if (!$val) return esc_html('Never');
        return esc_html(date($a['format'], strtotime($val)));
    }

    public static function debug($atts): string {
        if (!current_user_can('manage_options')) {
            return '';
        }

        $atts = shortcode_atts(['account_id' => '', 'months' => '12'], $atts ?: []);
        $account_id = $atts['account_id'] ?: null;
        $months = $atts['months'];

        $requests_before = WCM_API::get_request_count();

        // Get metrics (uses cache if available, no force refresh)
        $metrics = new WCM_Metrics();
        $result = $metrics->get_metrics_for_account($account_id, false, $months);

        $requests_after = WCM_API::get_request_count();

        if (is_wp_error($result)) {
            return '<pre>Error: ' . esc_html($result->get_error_message()) . '</pre>';
        }

        $output = "=== DEBUG INFO ===\n";
        $output .= "API Requests: " . ($requests_after - $requests_before) . "\n";
        $output .= "Cache Key: wcm_metrics_" . ($account_id ?: 'all') . "_" . $months . "\n\n";

        $output .= "=== CALCULATED METRICS (months: $months) ===\n";
        $output .= "Qualified Leads: " . $result['qualified_leads'] . " (quotable = 'Yes')\n";
        $output .= "Closed Leads: " . $result['closed_leads'] . " (sales_value > 0)\n";
        $output .= "Sales Value: $" . number_format($result['sales_value']) . " (sum of sales_value)\n";
        $output .= "Quote Value: $" . number_format($result['quote_value']) . " (sum of quote_value)\n";
        $output .= "Total Leads: " . $result['total_leads'] . "\n";
        $output .= "Last Updated: " . $result['last_updated'] . "\n\n";

        $output .= "=== HOW TO VERIFY ===\n";
        $output .= "Use debug=\"true\" on any shortcode to see raw leads in browser console.\n";
        $output .= "Example: [wc_sales_value account_id=\"...\" debug=\"true\"]\n";

        return '<pre>' . esc_html($output) . '</pre>';
    }
}
