<?php
if (!defined('ABSPATH')) exit;

class WCM_Settings {
    private const PAGE = 'whatconverts-metrics';

    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_post_wcm_test_connection', [self::class, 'handle_test_connection']);
        add_action('admin_post_wcm_clear_cache', [self::class, 'handle_clear_cache']);
    }

    public static function add_menu(): void {
        add_options_page('WhatConverts Metrics', 'WhatConverts', 'manage_options', self::PAGE, [self::class, 'render_page']);
    }

    public static function register_settings(): void {
        foreach (['wcm_api_token', 'wcm_api_secret', 'wcm_profile_id'] as $opt) {
            register_setting('wcm_settings', $opt, ['sanitize_callback' => 'sanitize_text_field']);
        }

        add_settings_section('wcm_api_section', 'API Credentials', function() {
            echo '<p>Enter your WhatConverts API credentials from Tracking → Integrations → API Keys.</p>';
        }, self::PAGE);

        add_settings_field('wcm_api_token', 'API Token', function() {
            printf('<input type="text" name="wcm_api_token" value="%s" class="regular-text" />', esc_attr(get_option('wcm_api_token', '')));
        }, self::PAGE, 'wcm_api_section');

        add_settings_field('wcm_api_secret', 'API Secret', function() {
            printf('<input type="password" name="wcm_api_secret" value="%s" class="regular-text" />', esc_attr(get_option('wcm_api_secret', '')));
        }, self::PAGE, 'wcm_api_section');

        add_settings_field('wcm_profile_id', 'Profile ID (optional)', function() {
            printf('<input type="text" name="wcm_profile_id" value="%s" class="regular-text" /><p class="description">Leave empty for all profiles.</p>', esc_attr(get_option('wcm_profile_id', '')));
        }, self::PAGE, 'wcm_api_section');
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) return;

        $msg = $_GET['wcm_message'] ?? '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if ($msg === 'connection_success'): ?>
                <div class="notice notice-success"><p>Connection successful!</p></div>
            <?php elseif ($msg === 'connection_failed'): ?>
                <div class="notice notice-error"><p>Connection failed: <?php echo esc_html($_GET['wcm_error'] ?? 'Unknown'); ?></p></div>
            <?php elseif ($msg === 'cache_cleared'): ?>
                <div class="notice notice-success"><p>Cache cleared.</p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('wcm_settings'); do_settings_sections(self::PAGE); submit_button(); ?>
            </form>

            <hr>
            <h2>Actions</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                <?php wp_nonce_field('wcm_test_connection', 'wcm_nonce'); ?>
                <input type="hidden" name="action" value="wcm_test_connection">
                <button type="submit" class="button">Test Connection</button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;margin-left:10px">
                <?php wp_nonce_field('wcm_clear_cache', 'wcm_nonce'); ?>
                <input type="hidden" name="action" value="wcm_clear_cache">
                <button type="submit" class="button">Clear Cache</button>
            </form>

            <hr>
            <h2>Shortcodes</h2>
            <table class="widefat" style="max-width:500px">
                <tr><td><code>[wc_qualified_leads]</code></td><td>Qualified leads count</td></tr>
                <tr><td><code>[wc_closed_leads]</code></td><td>Closed leads count</td></tr>
                <tr><td><code>[wc_annual_value]</code></td><td>Total value (currency)</td></tr>
                <tr><td><code>[wc_total_leads]</code></td><td>All leads count</td></tr>
                <tr><td><code>[wc_last_updated]</code></td><td>Last refresh time</td></tr>
            </table>

            <?php self::render_metrics_preview(); ?>
        </div>
        <?php
    }

    private static function render_metrics_preview(): void {
        $api = new WCM_API();
        if (!$api->is_configured()) {
            echo '<p><em>Configure credentials to see metrics.</em></p>';
            return;
        }

        $metrics = (new WCM_Metrics($api))->get_metrics();
        if (is_wp_error($metrics)) {
            printf('<p class="error">Error: %s</p>', esc_html($metrics->get_error_message()));
            return;
        }
        ?>
        <h3>Current Data</h3>
        <table class="widefat" style="max-width:300px">
            <tr><td>Qualified</td><td><?php echo number_format($metrics['qualified_leads']); ?></td></tr>
            <tr><td>Closed</td><td><?php echo number_format($metrics['closed_leads']); ?></td></tr>
            <tr><td>Value</td><td>$<?php echo number_format($metrics['annual_value']); ?></td></tr>
            <tr><td>Total</td><td><?php echo number_format($metrics['total_leads']); ?></td></tr>
        </table>
        <?php
    }

    public static function handle_test_connection(): void {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('wcm_test_connection', 'wcm_nonce');

        $result = (new WCM_API())->test_connection();
        $url = admin_url('options-general.php?page=' . self::PAGE);

        if ($result === true) {
            wp_redirect(add_query_arg('wcm_message', 'connection_success', $url));
        } else {
            $error = is_wp_error($result) ? $result->get_error_message() : 'Failed';
            wp_redirect(add_query_arg(['wcm_message' => 'connection_failed', 'wcm_error' => $error], $url));
        }
        exit;
    }

    public static function handle_clear_cache(): void {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('wcm_clear_cache', 'wcm_nonce');

        delete_transient('wcm_metrics_cache');
        wp_redirect(add_query_arg('wcm_message', 'cache_cleared', admin_url('options-general.php?page=' . self::PAGE)));
        exit;
    }
}
