<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('ABSPATH', '/tmp/wordpress/');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('WCM_PLUGIN_DIR', dirname(__DIR__) . '/');
define('WCM_CACHE_TTL', HOUR_IN_SECONDS);

if (!class_exists('WP_Error')) {
    class WP_Error {
        protected string $code;
        protected string $message;
        protected array $data;

        public function __construct(string $code = '', string $message = '', $data = []) {
            $this->code = $code;
            $this->message = $message;
            $this->data = is_array($data) ? $data : [$data];
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

if (!function_exists('wcm_get_cache_ttl')) {
    function wcm_get_cache_ttl(): int {
        if (function_exists('get_option')) {
            $minutes = absint(get_option('wcm_cache_length_minutes', 60));
            if ($minutes < 5) {
                $minutes = 60;
            }
            return max(300, $minutes * MINUTE_IN_SECONDS);
        }
        return WCM_CACHE_TTL;
    }
}

require_once WCM_PLUGIN_DIR . 'includes/class-api.php';
require_once WCM_PLUGIN_DIR . 'includes/class-metrics.php';
require_once WCM_PLUGIN_DIR . 'includes/class-cron.php';
require_once WCM_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once WCM_PLUGIN_DIR . 'admin/class-settings.php';
