<?php
if (!defined('ABSPATH')) exit;

class WCM_Shortcodes {
    public static function register(): void {
        add_shortcode('wc_qualified_leads', [self::class, 'qualified_leads']);
        add_shortcode('wc_closed_leads', [self::class, 'closed_leads']);
        add_shortcode('wc_annual_value', [self::class, 'annual_value']);
        add_shortcode('wc_total_leads', [self::class, 'total_leads']);
        add_shortcode('wc_last_updated', [self::class, 'last_updated']);
    }

    private static function get_metric(string $key, array $atts) {
        $atts = shortcode_atts(['account_id' => ''], $atts);
        $account_id = $atts['account_id'] ?: null;

        $data = (new WCM_Metrics())->get_metrics_for_account($account_id);
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

    public static function annual_value($atts): string {
        $val = self::get_metric('annual_value', $atts ?: []);
        if ($val === null) return esc_html('—');
        return esc_html('$' . number_format($val));
    }

    public static function total_leads($atts): string {
        $val = self::get_metric('total_leads', $atts ?: []);
        return esc_html($val !== null ? number_format($val) : '—');
    }

    public static function last_updated($atts): string {
        $a = shortcode_atts(['account_id' => '', 'format' => 'M j, Y g:i a'], $atts ?: []);
        $account_id = $a['account_id'] ?: null;

        $data = (new WCM_Metrics())->get_metrics_for_account($account_id);
        $val = is_wp_error($data) ? null : ($data['last_updated'] ?? null);

        if (!$val) return esc_html('Never');
        return esc_html(date($a['format'], strtotime($val)));
    }
}
