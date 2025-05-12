<?php
if (!defined('ABSPATH')) {
    exit;
}

// Load configuration file
$lapinopay_config = require dirname(__FILE__) . '/config.php';

// Add these constants to your config file
define('LAPINOPAY_ALLOWED_PAYMENT_METHODS', ['VISA_MC', 'REVOLUT_PAY', 'GOOGLE_PAY', 'APPLE_PAY']);
define('LAPINOPAY_ALLOWED_CURRENCIES', ['EUR', 'USD']);

// Add this at the very beginning of your file, before any output
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = esc_url_raw(wp_unslash($_SERVER['HTTP_ORIGIN']));
    header("Access-Control-Allow-Origin: " . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        $allowed_methods = 'GET, POST, OPTIONS';
        header("Access-Control-Allow-Methods: " . $allowed_methods);
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        $headers = sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']));
        header("Access-Control-Allow-Headers: " . $headers);
    }
    exit(0);
}

add_action('plugins_loaded', 'init_lapinopay_gateway');

function init_lapinopay_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class Lapino_Instant_Payment_Gateway extends WC_Payment_Gateway {
        protected $icon_url;
        protected $api_base_url;
        protected $minimum_amount;
        protected $checkout_url;
        protected $payment_provider;
        protected static $config;
        protected $api_token;
        
        public $title;
        public $description;
        public $enabled;

        public function __construct() {
            global $lapinopay_config;
            self::$config = $lapinopay_config;
            
            $this->id = 'lapinopay-instant-payment-gateway-guardarian';
            // $this->icon = plugins_url('assets/images/credit-card-icon.png', dirname(__FILE__));
            $this->method_title = esc_html__('Lapinopay Payment Gateway', 'lapinopay');
            $this->method_description = esc_html__('Instant Approval High Risk Merchant Gateway with instant payouts to your USDC wallet.', 'lapinopay');
            $this->has_fields = true;

            $this->init_form_fields();
            $this->init_settings();

            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->api_token = $this->get_option('api_token');

            // Validate currency and API token
            $validation_result = $this->validate_currency_and_token($this->api_token);
            if (!$validation_result['success']) {
                $this->enabled = 'no';
                add_action('woocommerce_admin_notices', function() use ($validation_result) {
                    echo '<div class="notice notice-error"><p>' . esc_html($validation_result['message']) . '</p></div>';
                });
            } else {
                $this->api_base_url = self::$config['api']['base_url'];
                $this->minimum_amount = $this->get_option('minimum_amount', 20);
                $this->checkout_url = self::$config['api']['checkout_url'];
                $this->payment_provider = self::$config['payment']['provider'];
            }

            // Remove the woocommerce_receipt hook and use the correct ones
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_checkout_process', array($this, 'validate_fields'));
            
            // Add this to prevent cart emptying during checkout
            remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
            remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
            
            // Debug logging
            add_action('init', function() {
                $this->log_message('Lapinopay Gateway Initialized');
            });
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => esc_html__('Enable/Disable', 'lapinopay'),
                    'type'    => 'checkbox',
                    'label'   => esc_html__('Enable Lapinopay payment gateway', 'lapinopay'),
                    'default' => 'no',
                ),
                'api_token' => array(
                    'title'       => esc_html__('API Token', 'lapinopay'),
                    'type'        => 'text',
                    'description' => esc_html__('Enter your API token for authentication', 'lapinopay'),
                    'desc_tip'    => true,
                ),
            );
        }

        private function validate_currency_and_token($api_token) {
            // Check WooCommerce currency
            $currency = get_woocommerce_currency();
            if (!in_array($currency, LAPINOPAY_ALLOWED_CURRENCIES)) {
                return array(
                    'success' => false, 
                    'message' => __('Currency requirement: Only EUR and USD are supported.', 'lapinopay')
                );
            }

            // Make sure the base URL includes the protocol
            $base_url = self::$config['api']['base_url'];
            if (!preg_match("~^(?:f|ht)tps?://~i", $base_url)) {
                $base_url = "http://" . $base_url;
            }

            $validation_url = add_query_arg(
                array('token' => $api_token),
                trailingslashit($base_url) . self::$config['api']['token_validation_endpoint']
            );

            // Validate token with API using GET method
            $context = stream_context_create([
                "http" => [
                    "method" => "GET",
                    "ignore_errors" => true
                ]
            ]);
            $response = file_get_contents($validation_url, false, $context);
            echo '<script>logValidation(' . esc_js($response) . ');</script>';
            $http_status = explode(" ", $http_response_header[0])[1];
            if ($http_status === '200') {
                return array('success' => true);
                // return array('success' => false, 'message' => __('Token validation failed: Invalid response from server.', 'instant-approval-payment-gateway'));
            }

            $data = json_decode($response, true);
            if (!$data || !isset($data['success'])) {
                return array('success' => false, 'message' => __('Token validation failed: Invalid response from server.', 'lapinopay'));
            }

        }

        public function process_admin_options() {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')) {
                WC_Admin_Settings::add_error(__('Nonce verification failed. Please try again.', 'lapinopay'));
                return false;
            }

            $api_token = isset($_POST[$this->plugin_id . $this->id . '_api_token']) 
                ? sanitize_text_field(wp_unslash($_POST[$this->plugin_id . $this->id . '_api_token'])) 
                : '';

            if (empty($api_token)) {
                WC_Admin_Settings::add_error(__('API Token is required.', 'lapinopay'));
                return false;
            }

            // Use the new validation function
            $validation_result = $this->validate_currency_and_token($api_token);
            if (!$validation_result['success']) {
                WC_Admin_Settings::add_error($validation_result['message']);
                return false;
            }

            return parent::process_admin_options();
        }

        // Add this helper function to encrypt data
        private function encrypt_data($data) {
            $key = $this->api_token; // Using API token as encryption key
            $method = 'aes-256-cbc';
            $iv = substr(hash('sha256', $key), 0, 16);
            $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
            return urlencode(base64_encode($encrypted));
        }

        public function process_payment($order_id) {
            // Verify nonce
            if (!isset($_POST['lapinopay_payment_nonce']) || 
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lapinopay_payment_nonce'])), 'lapinopay_payment')) {
                wc_add_notice(__('Security check failed. Please refresh the page and try again.', 'lapinopay'), 'error');
                return array(
                    'result' => 'failure',
                    'redirect' => wc_get_cart_url()
                );
            }

            $order = wc_get_order($order_id);
            
            // Sanitize payment category
            $payment_category = isset($_POST['lapinopay_payment_category']) ? 
                sanitize_text_field(wp_unslash($_POST['lapinopay_payment_category'])) : 'VISA_MC';
            
            // Save payment category as order meta
            $order->update_meta_data('_lapinopay_payment_category', $payment_category);
            $order->save();

            // Set payment method
            $order->set_payment_method($this->id);
            $order->set_payment_method_title($this->method_title);

            // Generate nonce
            $nonce = wp_create_nonce('payment_callback_' . $order_id);
            
            // Store nonce and update status
            $order->update_meta_data('lapinopay_payment_nonce', $nonce);
            $order->update_status('pending', __('Awaiting payment confirmation', 'lapinopay'));
            $order->save();

            // Validate currency and API token
            $api_token = $this->api_token; // Assuming api_token is set during initialization
            $validation_result = $this->validate_currency_and_token($api_token);
            if (!$validation_result['success']) {
                // Redirect to cart with error message
                wc_add_notice($validation_result['message'], 'error');
                return array(
                    'result'   => 'failure',
                    'redirect' => wc_get_cart_url(),
                );
            }

            // Get site URL
            $site_url = get_site_url();

            // Create callback URLs
            $callback_base = rest_url('lapinopay/v1/payment-callback/');
            $status_callback_url = add_query_arg(array(
                'order_id' => $order_id,
                'nonce' => $nonce,
                'txid' => 'test'
            ), $callback_base);

            // Get billing information exactly as React expects it
            $billing_data = array(
                'billing_first_name' => urlencode($order->get_billing_first_name()),
                'billing_last_name'  => urlencode($order->get_billing_last_name()),
                'billing_email'      => urlencode($order->get_billing_email()),
                'billing_phone'      => urlencode($order->get_billing_phone()),
                'billing_address'    => urlencode($order->get_billing_address_1()),
                'billing_city'       => urlencode($order->get_billing_city()),
                'billing_state'      => urlencode($order->get_billing_state()),
                'billing_postcode'   => urlencode($order->get_billing_postcode()),
                'billing_country'    => urlencode($order->get_billing_country()),
            );

            // Get products information with indexed format
            $products = array();
            $items = $order->get_items();
            $index = 0;
            
            foreach ($items as $item) {
                $product = $item->get_product();
                if ($product) {
                    $products["product_{$index}_id"] = strval($product->get_id());
                    $products["product_{$index}_name"] = urlencode($item->get_name());
                    $products["product_{$index}_quantity"] = strval($item->get_quantity());
                    $products["product_{$index}_price"] = strval($item->get_total());
                    $products["product_{$index}_description"] = urlencode($product->get_description());
                    $index++;
                }
            }

            // Prepare URL parameters exactly matching React component expectations
            $amount = strval($order->get_total());
            $hashed_amount = $this->hash_amount($amount, $order_id);

            $url_params = array(
                'token'               => $this->api_token,
                'cancel_callback_url' => urlencode($site_url),
                'status_callback_url' => urlencode($status_callback_url),
                'success_callback_url' => urlencode($site_url . '/order-received/' . $order_id),
                'amount'              => $amount,
                'hashed_amount'       => $hashed_amount,
                'currency'            => get_woocommerce_currency(),
                'order_id'            => $order_id,
                'nonce'              => $nonce,
                'site_type'          => 'wc',
                'site_url'           => urlencode($site_url),
                'payment_category'    => $payment_category,
                
                
                'subtotal'           => strval($order->get_subtotal()),
                'tax'                => strval($order->get_total_tax()),
                'shipping_total'     => strval($order->get_shipping_total()),
                'discount_total'     => strval($order->get_total_discount()),
                'grand_total'        => strval($order->get_total()),
            );

            // Merge billing data
            $url_params = array_merge($url_params, $billing_data);

            // Add products
            $url_params = array_merge($url_params, $products);

            // Clean up any empty values but preserve zeros
            $url_params = array_filter($url_params, function($value) {
                return $value !== '' && $value !== null;
            });

            // Debug log for URL parameters
            $this->log_message('URL Parameters: ' . wp_json_encode($url_params));
            $redirect_url = add_query_arg($url_params, $this->checkout_url);
            $this->log_message('Final Redirect URL: ' . $redirect_url);
            $go_to_checkout_url = $site_url . '/?lapinopay_checkout#' . urlencode($redirect_url);

            return array(
                'result'   => 'success',
                'redirect' => $go_to_checkout_url
            );
        }

        private function verify_woocommerce_pages() {
            $thankyou_page_id = get_option('woocommerce_thankyou_page_id');
            $checkout_page_id = get_option('woocommerce_checkout_page_id');

            if (!$thankyou_page_id || !$checkout_page_id) {
                // Create pages if they don't exist
                lapinopay_check_thankyou_page();
                return true; // Return true since pages will be created
            }

            return true;
        }

        public function lapinopay_instant_payment_gateway_get_icon_url() {
            return !empty($this->icon_url) ? esc_url($this->icon_url) : '';
        }

        public function payment_fields() {
            try {
                // Add nonce field
                wp_nonce_field('lapinopay_payment', 'lapinopay_payment_nonce');

                // Output any errors first
                if (isset($this->errors) && !empty($this->errors)) {
                    echo '<div class="woocommerce-error">' . esc_html(implode(', ', $this->errors)) . '</div>';
                }

                // Add description if set
                if ($this->description) {
                    echo '<div class="payment-method-description">' . wp_kses_post($this->description) . '</div>';
                }

                // Get the correct path to the template file
                $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/payment-fields.php';
                $this->log_message('Template path: ' . $template_path);

                if (file_exists($template_path)) {
                    // Include the custom template
                    include_once($template_path);
                } else {
                    $this->log_message('Template file not found at: ' . $template_path);
                    // Fallback to basic select if template is not found
                    ?>
                    <div class="lapinopay-additional-fields" style="padding: 10px 0;">
                        <p class="form-row form-row-wide">
                            <label for="lapinopay_payment_category"><?php esc_html_e('Payment Method', 'lapinopay'); ?> <span class="required">*</span></label>
                            <select name="lapinopay_payment_category" id="lapinopay_payment_category" class="select" style="width: 100%; max-width: 400px;" required>
                                <option value=""><?php esc_html_e('Select a payment method', 'lapinopay'); ?></option>
                                <option value="VISA_MC"><?php esc_html_e('Credit Card', 'lapinopay'); ?></option>
                                <option value="REVOLUT_PAY"><?php esc_html_e('Revolut Pay', 'lapinopay'); ?></option>
                                <option value="GOOGLE_PAY"><?php esc_html_e('Google Pay', 'lapinopay'); ?></option>
                                <option value="APPLE_PAY"><?php esc_html_e('Apple Pay', 'lapinopay'); ?></option>
                            </select>
                        </p>
                    </div>
                    <?php
                }

            } catch (Exception $e) {
                $this->log_message('Payment Fields Error: ' . $e->getMessage(), 'error');
                echo '<div class="woocommerce-error">' . esc_html__('An error occurred while loading payment fields.', 'lapinopay') . '</div>';
            }
        }

        // Add validation for payment category
        public function validate_fields() {
            // Verify nonce
            if (!isset($_POST['lapinopay_payment_nonce']) || 
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lapinopay_payment_nonce'])), 'lapinopay_payment')) {
                wc_add_notice(__('Security check failed.', 'lapinopay'), 'error');
                return false;
            }

            if (empty($_POST['lapinopay_payment_category'])) {
                wc_add_notice(__('Please select a payment method.', 'lapinopay'), 'error');
                return false;
            }
            
            $payment_category = sanitize_text_field(wp_unslash($_POST['lapinopay_payment_category']));
            $allowed_categories = array('VISA_MC', 'REVOLUT_PAY', 'GOOGLE_PAY', 'APPLE_PAY');
            
            if (!in_array($payment_category, $allowed_categories)) {
                wc_add_notice(__('Invalid payment method selected.', 'lapinopay'), 'error');
                return false;
            }
            
            return true;
        }

        // Add display in admin area (optional)
        public function display_admin_order_meta($order) {
            $payment_category = $order->get_meta('_lapinopay_payment_category');
            
            if ($payment_category) {
                $payment_methods = array(
                    'VISA_MC' => __('Credit Card', 'lapinopay'),
                    'REVOLUT_PAY' => __('Revolut Pay', 'lapinopay'),
                    'GOOGLE_PAY' => __('Google Pay', 'lapinopay'),
                    'APPLE_PAY' => __('Apple Pay', 'lapinopay')
                );
                
                $display_value = isset($payment_methods[$payment_category]) ? 
                    $payment_methods[$payment_category] : $payment_category;
                    
                echo '<p><strong>' . esc_html__('Payment Method:', 'lapinopay') . '</strong> ' 
                    . esc_html($display_value) . '</p>';
            }
        }

        // Add this method to the Lapino_Instant_Payment_Gateway class
        private function hash_amount($amount, $order_id) {
            // Combine amount with order_id and token to make it unique per transaction
            $data = $amount . '|' . $order_id . '|' . $this->api_token;
            // Use HMAC-SHA256 for secure hashing
            return hash_hmac('sha256', $data, $this->api_token);
        }

        private function log_message($message, $type = 'info') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (is_array($message) || is_object($message)) {
                    $message = wp_json_encode($message);
                }
                wc_get_logger()->log($type, $message, array('source' => 'lapinopay'));
            }
        }
    }

    add_filter('woocommerce_payment_gateways', function($gateways) {
        $gateways[] = 'Lapino_Instant_Payment_Gateway';
        return $gateways;
    });
}

// Then modify your REST API registration
add_action('rest_api_init', function() {
    register_rest_route('lapinopay/v1', '/payment-callback/', array(
        'methods' => ['GET', 'POST', 'OPTIONS'],
        'callback' => 'handle_payment_callback',
        'permission_callback' => function() {
            return true; // Adjust this based on your security needs
        }
    ));
});

// Modify your callback handler
function handle_payment_callback($request) {
    $order_id = absint($request->get_param('order_id'));
    $nonce = sanitize_text_field($request->get_param('nonce'));
    $txid = sanitize_text_field($request->get_param('txid'));
    $status = sanitize_text_field($request->get_param('status'));

    wc_get_logger()->debug(
        sprintf('Payment callback received - Order ID: %d, Status: %s', $order_id, $status),
        array('source' => 'lapinopay')
    );

    $order = wc_get_order($order_id);
    if (!$order) {
        return new WP_Error('invalid_order', __('Invalid order ID.', 'lapinopay'), array('status' => 404));
    }

    $stored_nonce = $order->get_meta('lapinopay_payment_nonce', true);
    if (empty($nonce) || $stored_nonce !== $nonce) {
        return new WP_Error('invalid_nonce', __('Invalid nonce.', 'lapinopay'), array('status' => 403));
    }

    try {
        switch ($status) {
            case 'COMPLETED':
                // Only complete the payment if it hasn't been completed already
                if ($order->get_status() !== 'completed') {
                    $order->payment_complete($txid);
                    $order->add_order_note(sprintf(
                        // translators: %s: Transaction ID
                        __('Payment completed successfully. Transaction ID: %s', 'lapinopay'),
                        $txid
                    ));
                    
                    // Only empty cart after successful payment completion
                    if (function_exists('WC') && isset(WC()->cart)) {
                        WC()->cart->empty_cart();
                    }
                }
                break;

            case 'FAILED':
                $order->update_status('failed', sprintf(
                    // translators: %s: Transaction ID
                    __('Payment failed. Transaction ID: %s', 'lapinopay'),
                    $txid
                ));
                break;

            case 'CANCELED':
                $order->update_status('cancelled', sprintf(
                    // translators: %s: Transaction ID
                    __('Payment cancelled by user. Transaction ID: %s', 'lapinopay'),
                    $txid
                ));
                break;

            default:
                return new WP_Error(
                    'invalid_status',
                    __('Invalid payment status received.', 'lapinopay'),
                    array('status' => 400)
                );
        }

        return new WP_REST_Response(array(
            'status' => 'success',
            'message' => 'Order status updated successfully.',
            'order_status' => $order->get_status()
        ), 200);

    } catch (Exception $e) {
        wc_get_logger()->error(
            sprintf('Payment callback error: %s', $e->getMessage()),
            array('source' => 'lapinopay')
        );
        return new WP_Error(
            'callback_error',
            $e->getMessage(),
            array('status' => 500)
        );
    }
}

add_action('woocommerce_payment_complete', function($order_id) {
    $order = wc_get_order($order_id);
    wc_get_logger()->info(
        sprintf('Payment complete for order %d - Payment method: %s', $order_id, $order->get_payment_method()),
        array('source' => 'lapinopay')
    );
}, 10, 1);