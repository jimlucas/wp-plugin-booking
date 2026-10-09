<?php
/**
 * Plugin Name: WP Plugin Booking
 * Description: Development preview of equipment and service scheduling for WooCommerce.
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 * Requires PHP: 8.0
 * Text Domain: wp-plugin-booking
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
define('WPB_VERSION', '0.1.0');
define('WPB_URL', plugin_dir_url(__FILE__));
require_once __DIR__ . '/includes/class-wpb-plugin.php';
add_action('plugins_loaded', static function () {
    if (class_exists('WooCommerce')) {
        WPB_Plugin::init();
    } else {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-warning"><p>' . esc_html__('WP Plugin Booking requires WooCommerce to be active.', 'wp-plugin-booking') . '</p></div>';
        });
    }
});
