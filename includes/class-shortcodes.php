<?php
if (!defined('ABSPATH')) exit;

class WCM_Shortcodes {
    public static function register(): void {
        add_shortcode('wc_qualified_leads', [self::class, 'qualified_leads']);
        add_shortcode('wc_closed_leads', [self::class, 'closed_leads']);
        add_shortcode('wc_annual_sales_value', [self::class, 'annual_sales_value']);
        add_shortcode('wc_annual_quote_value', [self::class, 'annual_quote_value']);
        add_shortcode('wc_total_leads', [self::class, 'total_leads']);
        add_shortcode('wc_last_updated', [self::class, 'last_updated']);
        add_shortcode('wc_debug', [self::class, 'debug']);
    }

    private static function get_metric(string $key, array $atts) {
        $atts = shortcode_atts(['account_id' => '', 'months' => 'all'], $atts);
        $account_id = $atts['account_id'] ?: null;
        $months = $atts['months'];

        $data = (new WCM_Metrics())->get_metrics_for_account($account_id, false, $months);
        return is_wp_error($data) ? null : ($data[$key] ?? null);
    }

    public static function qualified_leads($atts): string {
        $val = self::get_metric('qualified_leads', $atts ?: []);
        return esc_html($val !== null ? number_format($val) : '—');
    }

    public static function closed_leads($atts): string {
        $val = self::get_metric('closed_leads', $atts ?: []);
        return esc_html($val !== null ? number_format($val) : '—');
    }

    public static function annual_sales_value($atts): string {
        $val = self::get_metric('annual_sales_value', $atts ?: []);
        if ($val === null) return esc_html('—');
        return esc_html('$' . number_format($val));
    }

    public static function annual_quote_value($atts): string {
        $val = self::get_metric('annual_quote_value', $atts ?: []);
        if ($val === null) return esc_html('—');
        return esc_html('$' . number_format($val));
    }

    public static function total_leads($atts): string {
        $val = self::get_metric('total_leads', $atts ?: []);
        return esc_html($val !== null ? number_format($val) : '—');
    }

    public static function last_updated($atts): string {
        $a = shortcode_atts(['account_id' => '', 'months' => 'all', 'format' => 'M j, Y g:i a'], $atts ?: []);
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

        $atts = shortcode_atts(['account_id' => '', 'months' => 'all'], $atts ?: []);
        $account_id = $atts['account_id'] ?: null;
        $months = $atts['months'];

        // Force clear and recalculate
        $metrics = new WCM_Metrics();
        $metrics->clear_all_caches();
        $result = $metrics->get_metrics_for_account($account_id, true, $months);

        if (is_wp_error($result)) {
            return '<pre>Error: ' . esc_html($result->get_error_message()) . '</pre>';
        }

        $output = "=== CALCULATED METRICS (months: $months) ===\n";
        $output .= "Qualified Leads: " . $result['qualified_leads'] . "\n";
        $output .= "Closed Leads: " . $result['closed_leads'] . "\n";
        $output .= "Annual Sales Value: $" . number_format($result['annual_sales_value']) . "\n";
        $output .= "Annual Quote Value: $" . number_format($result['annual_quote_value']) . "\n";
        $output .= "Total Leads: " . $result['total_leads'] . "\n\n";

        // Also show sample leads
        $api = new WCM_API();
        $params = ['months' => $months, 'per_page' => 5];
        if ($account_id) {
            $params['account_id'] = $account_id;
        }
        $leads = $api->fetch_leads($params);

        if (!is_wp_error($leads)) {
            $output .= "=== SAMPLE LEADS ===\n";
            foreach (array_slice($leads, 0, 5) as $i => $lead) {
                $output .= "Lead $i:\n";
                $output .= "  quotable: " . ($lead['quotable'] ?? 'NOT SET') . "\n";
                $output .= "  sales_value: " . ($lead['sales_value'] ?? 'NOT SET') . "\n";
                $output .= "  quote_value: " . ($lead['quote_value'] ?? 'NOT SET') . "\n";
                $output .= "  lead_status: " . ($lead['lead_status'] ?? 'NOT SET') . "\n\n";
            }
        }

        return '<pre>' . esc_html($output) . '</pre>';
    }
}
