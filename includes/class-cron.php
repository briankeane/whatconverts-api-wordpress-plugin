<?php
if (!defined('ABSPATH')) exit;

class WCM_Cron {
    private const EVENT = 'wcm_prewarm_cache';

    public static function register(): void {
        add_action(self::EVENT, [self::class, 'prewarm_caches']);
    }

    public static function activate(): void {
        if (!wp_next_scheduled(self::EVENT)) {
            wp_schedule_event(time(), 'hourly', self::EVENT);
        }
    }

    public static function deactivate(): void {
        $timestamp = wp_next_scheduled(self::EVENT);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::EVENT);
        }
    }

    public static function prewarm_caches(): void {
        $metrics = apply_filters('wcm_prewarm_metrics_instance', new WCM_Metrics());
        $targets = self::discover_targets();

        foreach ($targets as [$account_id, $months]) {
            $result = $metrics->get_metrics_for_account($account_id, false, $months);
            if (is_wp_error($result)) {
                self::log("Prewarm failed for account=" . ($account_id ?: 'all') . " months=$months: " . $result->get_error_message());
            }
        }
    }

    private static function discover_targets(): array {
        global $wpdb;

        // Start with sane defaults so we at least prewarm the common "all accounts" ranges.
        $targets = self::configured_targets();

        if (!$wpdb) {
            return $targets;
        }

        $transients = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_wcm_metrics_%'
             AND option_name NOT LIKE '_transient_timeout_%'"
        );

        foreach ($transients as $option_name) {
            if (preg_match('/^_transient_wcm_metrics_([^_]+)_([a-z0-9]+)$/i', $option_name, $m)) {
                $account = $m[1] === 'all' ? null : $m[1];
                $months = $m[2];
                $targets[] = [$account, $months];
            }
        }

        // Deduplicate
        $unique = [];
        $seen = [];
        foreach ($targets as [$account, $months]) {
            $key = ($account ?: 'all') . '|' . $months;
            if (!isset($seen[$key])) {
                $unique[] = [$account, $months];
                $seen[$key] = true;
            }
        }

        return $unique;
    }

    private static function configured_targets(): array {
        $defaults = [
            [null, '12'],
        ];

        $configured = apply_filters('wcm_prewarm_targets', $defaults);

        if (!is_array($configured)) {
            return $defaults;
        }

        $clean = [];
        foreach ($configured as $target) {
            if (!is_array($target) || count($target) !== 2) {
                continue;
            }
            [$account, $months] = $target;
            $account = (is_string($account) && preg_match('/^\d+$/', $account)) ? $account : null;
            $months = in_array($months, ['1', '3', '6', '12', 'all'], true) ? $months : '12';
            $clean[] = [$account, $months];
        }

        return $clean ?: $defaults;
    }

    private static function log(string $msg): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCM_Cron] ' . $msg);
        }
    }
}
