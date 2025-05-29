<?php
/**
 * Plugin Name: LapinoPay - Instant USDC Payment Gateway
 * Plugin URI: https://lapinopay.com/docs/payment-gateway
 * Description: Instant Approval High Risk Merchant Gateway with instant payouts to your USDC wallet.
 * Version: 1.1.8
 * Requires Plugins: woocommerce
 * Requires at least: 5.8
 * Tested up to: 6.8
 * WC requires at least: 5.8
 * WC tested up to: 9.6.1
 * Requires PHP: 7.2
 * Author: LapinoPay Team
 * Author URI: https://lapinopay.com/team
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: lapinopay
 * Domain Path: /languages
 */

    // Exit if accessed directly.
    if (!defined('ABSPATH')) {
        exit;
    }

    add_action('before_woocommerce_init', function() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    });
	
	add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

/**
 * Enqueue block assets for the gateway.
 */
function lapinopay_enqueue_block_assets() {
    // Fetch all enabled WooCommerce payment gateways
    $lapinopay_available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
    $lapinopay_gateways_data = array();

    foreach ($lapinopay_available_gateways as $gateway_id => $gateway) {
		if (strpos($gateway_id, 'lapinopay-instant-payment-gateway') === 0) {
        $icon_url = method_exists($gateway, 'lapinopay_instant_payment_gateway_get_icon_url') ? $gateway->lapinopay_instant_payment_gateway_get_icon_url() : '';
        $lapinopay_gateways_data[] = array(
            'id' => sanitize_key($gateway_id),
            'label' => sanitize_text_field($gateway->get_title()),
            'description' => wp_kses_post($gateway->get_description()),
            'icon_url' => sanitize_url($icon_url),
        );
		}
    }

    wp_enqueue_script(
        'lapinopay-block-support',
        plugin_dir_url(__FILE__) . 'assets/js/lapinopay-block-checkout-support.js',
        array('wc-blocks-registry', 'wp-element', 'wp-i18n', 'wp-components', 'wp-blocks', 'wp-editor'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/lapinopay-block-checkout-support.js'),
        true
    );

    // Localize script with gateway data
    wp_localize_script(
        'lapinopay-block-support',
        'lapinopayData',
        $lapinopay_gateways_data
    );
}
add_action('enqueue_block_assets', 'lapinopay_enqueue_block_assets');

/**
 * Enqueue styles for the gateway on checkout page.
 */
function lapinopay_enqueue_styles() {
    if (is_checkout()) {
        wp_enqueue_style(
            'lapinopay-styles',
            plugin_dir_url(__FILE__) . 'assets/css/lapinopay-payment-gateway-styles.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'assets/css/lapinopay-payment-gateway-styles.css')
        );
    }
}
add_action('wp_enqueue_scripts', 'lapinopay_enqueue_styles');

    // Include only the Guardarian payment gateway class
    include_once(plugin_dir_path(__FILE__) . 'includes/class-lapinopay-instant-payment-gateway.php');

    // Conditional function that check if Checkout page use Checkout Blocks
    function lapinopay_is_checkout_block() {
        return WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' );
    }

    function lapinopay_add_notice($lapinopay_message, $lapinopay_notice_type = 'error') {
        // Check if the Checkout page is using Checkout Blocks
        if (lapinopay_is_checkout_block()) {
            // For blocks, throw a WooCommerce exception
            if ($lapinopay_notice_type === 'error') {
                throw new \WC_Data_Exception('checkout_error', esc_html($lapinopay_message)); 
            }
            // Handle other notice types if needed
        } else {
            // Default WooCommerce behavior
            wc_add_notice(esc_html($lapinopay_message), $lapinopay_notice_type); 
        }
    }	
	
    // Modify the activation hook function
    function lapinopay_check_thankyou_page() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Create or update the thank you page
        $thankyou_page = array(
            'post_title'    => 'Order Received',
            'post_content'  => '[woocommerce_thankyou]',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'order-received'
        );
        
        // Check if page exists
        $existing_thankyou = get_option('woocommerce_thankyou_page_id');
        if ($existing_thankyou) {
            $thankyou_page['ID'] = $existing_thankyou;
            wp_update_post($thankyou_page);
        } else {
            $thankyou_id = wp_insert_post($thankyou_page);
            update_option('woocommerce_thankyou_page_id', $thankyou_id);
        }

        // Create or update the checkout page
        $checkout_page = array(
            'post_title'    => 'Checkout',
            'post_content'  => '[woocommerce_checkout]',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'checkout'
        );
        
        // Check if page exists
        $existing_checkout = get_option('woocommerce_checkout_page_id');
        if ($existing_checkout) {
            $checkout_page['ID'] = $existing_checkout;
            wp_update_post($checkout_page);
        } else {
            $checkout_id = wp_insert_post($checkout_page);
            update_option('woocommerce_checkout_page_id', $checkout_id);
        }

        // Flush rewrite rules to ensure proper URL structure
        flush_rewrite_rules();
    }

    // Add activation and deactivation hooks
    register_activation_hook(__FILE__, 'lapinopay_check_thankyou_page');
    register_deactivation_hook(__FILE__, 'lapinopay_cleanup_pages');

    // Optional: Cleanup function for deactivation
    function lapinopay_cleanup_pages() {
        // You can choose to remove the pages on deactivation if needed
        // For now, we'll leave them in place
    }

    // Add this near the top of your plugin file
    add_action('plugins_loaded', function() {
        // Ensure REST API is loaded
        if (!class_exists('WP_REST_Server')) {
            return;
        }

        // Force REST API to load
        add_filter('rest_enabled', '__return_true');
        add_filter('rest_jsonp_enabled', '__return_true');
    });

    // Handle the checkout page
    add_action('template_redirect', function() {
        if (get_query_var('lapinopay_checkout')) {
            $file = plugin_dir_path(__FILE__) . 'checkout.html';
            
            if (file_exists($file)) {
                // Initialize WP_Filesystem
                global $wp_filesystem;
                if (!function_exists('WP_Filesystem')) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                }
                
                if (!WP_Filesystem()) {
                    wp_die('Failed to initialize filesystem.');
                }

                // Set proper headers
                header('Content-Type: text/html; charset=UTF-8');
                header('X-Content-Type-Options: nosniff');
                
                // Use WP_Filesystem to read and output the file
                echo wp_kses_post($wp_filesystem->get_contents($file));
                exit;
            } else {
                wp_die('Checkout page not found.');
            }
        }
    });

    // Add rewrite rules for checkout page
    add_action('init', function() {
        add_rewrite_rule(
            '^lapinopay_checkout/?$',
            'index.php?lapinopay_checkout=1',
            'top'
        );
    });

    // Add query vars
    add_filter('query_vars', function($vars) {
        $vars[] = 'lapinopay_checkout';
        return $vars;
    });

    // Flush rewrite rules on plugin activation
    register_activation_hook(__FILE__, function() {
        add_rewrite_rule(
            '^lapinopay_checkout/?$',
            'index.php?lapinopay_checkout=1',
            'top'
        );
        flush_rewrite_rules();
    });

    // Flush rewrite rules on plugin deactivation
    register_deactivation_hook(__FILE__, function() {
        flush_rewrite_rules();
    });

    // Include the payment gateway class
    add_action('plugins_loaded', function() {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        include_once(plugin_dir_path(__FILE__) . 'includes/class-lapinopay-instant-payment-gateway.php');
    });

    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', function($gateways) {
        $gateways[] = 'WC_LapinoPay_Instant_Payment_Gateway';
        return $gateways;
    });
?>