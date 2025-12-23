<?php
/**
 * Plugin Name: WhatConverts API Metrics
 * Description: Display lead metrics from WhatConverts API
 * Version: 1.0.1
 * Author: Alloy GP
 */

if (!defined('ABSPATH')) exit;

define('WCM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCM_CACHE_TTL', HOUR_IN_SECONDS); // Legacy default; actual TTL is configurable via wcm_get_cache_ttl()

if (!function_exists('wcm_get_cache_ttl')) {
    function wcm_get_cache_ttl(): int {
        $minutes = absint(get_option('wcm_cache_length_minutes', 60));
        if ($minutes < 5) {
            $minutes = 60;
        }
        return max(300, $minutes * MINUTE_IN_SECONDS); // enforce minimum of 5 minutes
    }
}

require_once WCM_PLUGIN_DIR . 'includes/class-api.php';
require_once WCM_PLUGIN_DIR . 'includes/class-metrics.php';
require_once WCM_PLUGIN_DIR . 'includes/class-cron.php';
require_once WCM_PLUGIN_DIR . 'includes/class-shortcodes.php';

if (is_admin()) {
    require_once WCM_PLUGIN_DIR . 'admin/class-settings.php';
}

add_action('init', function() {
    WCM_Shortcodes::register();
    WCM_Cron::register();
    if (is_admin()) {
        WCM_Settings::init();
    }
});

register_activation_hook(__FILE__, function() {
    add_option('wcm_api_token', '');
    add_option('wcm_api_secret', '');
    add_option('wcm_profile_id', '');
    add_option('wcm_cache_length_minutes', 60);
    WCM_Cron::activate();
});

register_deactivation_hook(__FILE__, function() {
    (new WCM_Metrics())->clear_all_caches();
    WCM_Cron::deactivate();
});
