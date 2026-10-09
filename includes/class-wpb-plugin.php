<?php
defined('ABSPATH') || exit;
final class WPB_Plugin {
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'product_fields']);
        add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_product']);
        add_shortcode('wpb_booking', [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
    }
    public static function menu(): void {
        add_menu_page('Equipment Booking', 'Equipment Booking', 'manage_woocommerce', 'wpb-booking', [__CLASS__, 'dashboard'], 'dashicons-calendar-alt', 56);
    }
    public static function dashboard(): void {
        if (!current_user_can('manage_woocommerce')) return;
        $products = wc_get_products(['limit' => 100, 'status' => 'publish', 'meta_key' => '_wpb_enabled', 'meta_value' => 'yes']);
        echo '<div class="wrap"><h1>Equipment Booking <small>v' . esc_html(WPB_VERSION) . '</small></h1>';
        echo '<div class="notice notice-info inline"><p><strong>Development preview:</strong> Booking selection works as a visual demonstration only. Checkout and reservation locking are not yet implemented. Do not accept real reservations.</p></div>';
        echo '<h2>Bookable products</h2><p>Enable booking on an existing WooCommerce product under Product data → General. Add the shortcode <code>[wpb_booking id="PRODUCT_ID"]</code> to any page, or use it in a product description.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Product</th><th>Hours</th><th>Increment</th><th>Price per hour</th><th>Shortcode</th></tr></thead><tbody>';
        foreach ($products as $p) {
            $id = $p->get_id();
            $days = $p->get_meta('_wpb_days');
            echo '<tr><td><a href="' . esc_url(get_edit_post_link($id)) . '">' . esc_html($p->get_name()) . '</a></td><td>' . esc_html($p->get_meta('_wpb_open') ?: '10:00') . '–' . esc_html($p->get_meta('_wpb_close') ?: '18:00') . '</td><td>' . esc_html($p->get_meta('_wpb_increment') ?: '60') . ' min</td><td>' . wp_kses_post(wc_price((float)$p->get_meta('_wpb_rate'))) . '</td><td><code>[wpb_booking id="' . absint($id) . '"]</code></td></tr>';
        }
        if (!$products) echo '<tr><td colspan="5">No bookable products yet.</td></tr>';
        echo '</tbody></table><h2>Calendar configuration</h2><p>Week starts on: <strong>' . esc_html(get_option('start_of_week') == 1 ? 'Monday' : 'Sunday') . '</strong> (Settings → General). Times use the WordPress site timezone: <strong>' . esc_html(wp_timezone_string()) . '</strong>.</p></div>';
    }
    public static function product_fields(): void {
        echo '<div class="options_group">';
        woocommerce_wp_checkbox(['id' => '_wpb_enabled', 'label' => 'Enable equipment booking', 'description' => 'Show a customer-facing scheduling preview for this product.']);
        woocommerce_wp_text_input(['id' => '_wpb_rate', 'label' => 'Hourly rate', 'type' => 'number', 'custom_attributes' => ['min' => '0', 'step' => '0.01']]);
        woocommerce_wp_text_input(['id' => '_wpb_open', 'label' => 'Opens (HH:MM)', 'placeholder' => '10:00']);
        woocommerce_wp_text_input(['id' => '_wpb_close', 'label' => 'Closes (HH:MM)', 'placeholder' => '18:00']);
        woocommerce_wp_select(['id' => '_wpb_increment', 'label' => 'Booking increment', 'options' => ['30' => '30 minutes', '60' => '60 minutes', '120' => '120 minutes']]);
        woocommerce_wp_text_input(['id' => '_wpb_days', 'label' => 'Open weekdays', 'description' => 'Comma-separated ISO weekdays: 1=Mon, 2=Tue, … 7=Sun. Example: 1,2,3,4,5', 'desc_tip' => true, 'placeholder' => '1,2,3,4,5']);
        echo '</div>';
    }
    public static function save_product($product): void {
        $fields = ['_wpb_rate', '_wpb_open', '_wpb_close', '_wpb_increment', '_wpb_days'];
        $product->update_meta_data('_wpb_enabled', isset($_POST['_wpb_enabled']) ? 'yes' : 'no');
        foreach ($fields as $key) {
            $value = isset($_POST[$key]) && is_scalar($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            if ($key === '_wpb_rate') $value = (string) max(0, (float) $value);
            if ($key === '_wpb_increment' && !in_array($value, ['30', '60', '120'], true)) $value = '60';
            if (in_array($key, ['_wpb_open', '_wpb_close'], true) && $value !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $value)) $value = '';
            if ($key === '_wpb_days') {
                $days = array_filter(array_map('intval', explode(',', $value)), static fn($d) => $d >= 1 && $d <= 7);
                $value = implode(',', array_unique($days));
            }
            $product->update_meta_data($key, $value);
        }
    }
    public static function assets(): void {
        wp_register_style('wpb-booking', WPB_URL . 'assets/booking.css', [], WPB_VERSION);
        wp_register_script('wpb-booking', WPB_URL . 'assets/booking.js', [], WPB_VERSION, true);
    }
    public static function shortcode($atts): string {
        $atts = shortcode_atts(['id' => 0], $atts, 'wpb_booking');
        $product = wc_get_product(absint($atts['id']));
        if (!$product || $product->get_meta('_wpb_enabled') !== 'yes' || !$product->is_visible()) return '';
        $open = $product->get_meta('_wpb_open') ?: '10:00';
        $close = $product->get_meta('_wpb_close') ?: '18:00';
        $step = (int) ($product->get_meta('_wpb_increment') ?: 60);
        $days = $product->get_meta('_wpb_days') ?: '1,2,3,4,5';
        $rate = (float) $product->get_meta('_wpb_rate');
        wp_enqueue_style('wpb-booking');
        wp_enqueue_script('wpb-booking');
        $config = ['open' => $open, 'close' => $close, 'step' => $step, 'days' => array_map('intval', explode(',', $days)), 'rate' => $rate, 'currency' => get_woocommerce_currency(), 'locale' => str_replace('_', '-', get_locale()), 'today' => wp_date('Y-m-d'), 'maxDate' => wp_date('Y-m-d', strtotime('+90 days', current_time('timestamp')))];
        ob_start(); ?>
        <section class="wpb-booking" data-config="<?php echo esc_attr(wp_json_encode($config)); ?>">
          <h3><?php echo esc_html($product->get_name()); ?> — Schedule preview</h3>
          <p class="wpb-note">Development preview only: availability is illustrative and reservations cannot yet be purchased.</p>
          <label>Date <input class="wpb-date" type="date" min="<?php echo esc_attr($config['today']); ?>" max="<?php echo esc_attr($config['maxDate']); ?>" value="<?php echo esc_attr($config['today']); ?>"></label>
          <label>Start time <select class="wpb-start"></select></label>
          <label>Duration <select class="wpb-duration"></select></label>
          <p class="wpb-summary" aria-live="polite"></p>
          <button type="button" disabled>Checkout (coming soon)</button>
        </section>
        <?php return (string) ob_get_clean();
    }
}
