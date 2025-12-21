<?php
/**
 * Plugin Name: WhatConverts API Metrics
 * Description: Display lead metrics from WhatConverts API
 * Version: 1.0.1
 * Author: Alloy GP
 */

if (!defined('ABSPATH')) exit;

define('WCM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCM_CACHE_TTL', HOUR_IN_SECONDS);

require_once WCM_PLUGIN_DIR . 'includes/class-api.php';
require_once WCM_PLUGIN_DIR . 'includes/class-metrics.php';
require_once WCM_PLUGIN_DIR . 'includes/class-shortcodes.php';

if (is_admin()) {
    require_once WCM_PLUGIN_DIR . 'admin/class-settings.php';
}

add_action('init', function() {
    WCM_Shortcodes::register();
    if (is_admin()) {
        WCM_Settings::init();
    }
});

register_activation_hook(__FILE__, function() {
    add_option('wcm_api_token', '');
    add_option('wcm_api_secret', '');
    add_option('wcm_profile_id', '');
});

register_deactivation_hook(__FILE__, function() {
    delete_transient('wcm_metrics_cache');
});
