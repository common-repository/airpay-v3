<?php
/**
 * WordPress kit
 * Plugin Name: Airpay for WooCommerce
 * Plugin URI: http://airpay.co.in/
 * Description: Used to integrate the airpay payment services with your website.
 * Version: 5.8.2
 * Author: Airpay
 * Requires at least: 4.7
 * Tested up to: 6.4.3
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * @package wordpress_kit
 */

/**
 * Included class-airpaychecksum.php for checksum calculation.
 */
require 'class-airpaychecksum.php';

add_action('plugins_loaded', 'airpay_woocommerce_init', 0);

/**
 * Initialize the AirPay integration.
 * This function sets up any necessary hooks or
 * configurations for the AirPay payment gateway.
 */
function airpay_woocommerce_init() {

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Localisation
     */
    load_plugin_textdomain('airpay', false, dirname(plugin_basename(__FILE__)) . '/languages');
    // Nonce verification.

    $msg = isset($_GET['msg']) ? sanitize_text_field(wp_unslash($_GET['msg'])) : '';
    if ('' !== $msg && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'msg_nonce_action')) {
        add_action('the_content', 'showMessage');
    }

    /**
     * Gateway class
     */
    class Airpay extends WC_Payment_Gateway {
        /**
         * Variable initialisation
         *
         * @var $msg is value from GET parameter.
         */
        protected $msg = array();
        /**
         * Variable initialisation
         *
         * @var $merchant_identifier is value from settings
         */
        public $merchant_identifier;
        /**
         * Variable initialisation
         *
         * @var $secret_key is value from settings
         */
        public $secret_key;
        /**
         * Variable initialisation
         *
         * @var $iso_currency is value from settings
         */
        public $iso_currency;
        /**
         * Variable initialisation
         *
         * @var $currency is value from settings
         */
        public $currency;
        /**
         * Variable initialisation
         *
         * @var $payment_mode is value from settings
         */
        public $payment_mode;
        /**
         * Variable initialisation
         *
         * @var $username is value from settings
         */
        public $username;
        /**
         * Variable initialisation
         *
         * @var $password is value from settings
         */
        public $password;
        /**
         * Variable initialisation
         *
         * @var $redirect_page_id is value from settings
         */
        public $redirect_page_id;
        /**
         * Variable initialisation
         *
         * @var $mode is value from settings
         */
        public $mode;
        /**
         * Variable initialisation
         *
         * @var $log is value from settings
         */
        public $log;
        /**
         * Variable initialisation
         *
         * @var $liveurl is value from settings
         */
        public $liveurl;
        /**
         * Constructor for the class WC_Airpay
         * Which initilises all ariables.
         */
        public function __construct() {

            // construct form //
            // Go wild in here.
            $this->id = 'airpay';
            $this->method_title = 'Airpay';
            $this->icon = WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/images/logo.png';
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            $this->title = ($this->settings['title']) ? $this->settings['title'] : '';
            $this->description = $this->settings['description'];
            $this->merchant_identifier = isset($this->settings['merchant_identifier']) ? $this->settings['merchant_identifier'] : '';
            $this->secret_key = $this->settings['secret_key'];
            $this->currency = $this->settings['currency'];
            $this->iso_currency = $this->settings['iso_currency'];
            $this->payment_mode = $this->settings['payment_mode'];
            $this->username = $this->settings['username'];
            $this->password = $this->settings['password'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];
            $this->mode = isset($this->settings['mode']) ? $this->settings['mode'] : '';
            $this->log = $this->settings['log'];
            $this->liveurl = 'https://payments.airpay.co.in/pay/index.php';
            $this->msg['message'] = '';
            $this->msg['class'] = '';

            add_action('init', array(&$this, 'airpay_check_response'));
            // update for woocommerce >2.0.

            add_action('woocommerce_api_' . strtolower(get_class($this)), array(&$this, 'airpay_check_response'));
            add_action('valid-Airpay-request', array(&$this, 'successful_request')); // this save.
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }
            add_action('woocommerce_receipt_airpay', array(&$this, 'receipt_page'));
        }
        /**
         * Initialies all form fields.
         * All form fields assigning.
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => esc_html__('Enable/Disable', 'airpay'),
                    'type' => 'checkbox',
                    'label' => esc_html__('Enable Airpay Payment Module.', 'airpay'),
                    'default' => esc_html__('no', 'airpay'),
                ),
                'title' => array(
                    'title' => esc_html__('Title:', 'airpay'),
                    'type' => 'text',
                    'description' => esc_html__('This controls the title which the user sees during checkout.', 'airpay'),
                    'default' => esc_html__('Airpay', 'airpay'),
                ),
                'description' => array(
                    'title' => esc_html__('Description:', 'airpay'),
                    'type' => 'textarea',
                    'description' => esc_html__('This controls the description which the user sees during checkout.', 'airpay'),
                    'default' => 'The best payment gateway provider in India for e-payment through credit card, debit card & netbanking.',
                ),

                'merchant_identifier' => array(
                    'title' => esc_html__('Merchant Id', 'airpay'),
                    'type' => 'text',
                    'description' => esc_html__('This id(Merchant Id) given to Merchant by Airpay.', 'airpay'),
                ),
                'username' => array(
                    'title' => esc_html__('User Name', 'airpay'),
                    'type' => 'text',
                    'description' => esc_html__('Given to Merchant by Airpay', 'airpay'),
                ),
                'password' => array(
                    'title' => esc_html__('Password', 'airpay'),
                    'type' => 'password',
                    'description' => esc_html__('Given to Merchant by Airpay', 'airpay'),
                ),
                'secret_key' => array(
                    'title' => esc_html__('Secret Key', 'airpay'),
                    'type' => 'text',
                    'description' => esc_html__('Given to Merchant by Airpay', 'airpay'),
                ),
                'currency' => array(
                    'title' => esc_html__('Currency code', 'airpay'),
                    'type' => 'text',
                    'description' => esc_html__('Currency code', 'airpay'),
                ),
                'iso_currency' => array(
                    'title' => esc_html__('ISO Currency', 'airpay'),
                    'type' => 'text',
                    'description' => esc_html__('ISO Currency', 'airpay'),
                ),
                'payment_mode' => array(
                    'title' => esc_html__('Payment Mode', 'airpay'),
                    'type' => 'text',
                    'description' => esc_html__(
                        'chmod variable contains Payment Modes available for user. for e.g. If you want to show only Credit Card/Debit Card, then value of the chmod variable will be "pg". If you want Netbanking and Prepaid card then value of the chmod variable will be "nb_ppc".
                    If you want to show all payment options activated for you at airpay, then leave this variable blank.

                    ',
                        'airpay'
                    ),
                ),
                'redirect_page_id' => array(
                    'title' => esc_html__('Return Page', 'airpay'),
                    'type' => 'select',
                    'options' => $this->get_pages('Select Page'),
                    'description' => esc_html__('URL of success page', 'airpay'),
                ),
                'log' => array(
                    'title' => esc_html__('Do you want to log', 'airpay'),
                    'type' => 'text',
                    'options' => 'text',
                    'description' => esc_html__('(yes/no)', 'airpay'),
                ),
            );
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options() {
            echo '<h3>' . esc_html__('Airpay Payment Gateway', 'airpay') . '</h3>';
            echo '<p>' . esc_html__('India online payment solutions for all your transactions by Airpay', 'airpay') . '</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }
        /**
         *  There are no payment fields for Airpay, but we want to show the description if set.
         **/
        public function payment_fields() {
            if ($this->description) {
                echo wp_kses_post(wpautop(wptexturize($this->description)));
            }
        }
        /**
         * Receipt Page
         **/
        /**
         * Parameter description
         *
         * @param order $order order details.
         */
        public function receipt_page($order) {

            echo '<p>' . esc_html__('Thank you for your order, please click the button below to pay with Airpay.', 'airpay') . '</p>';
            echo $this->airpay_generate_form($order);
        }

        /**
         * Process the payment and return the result
         **/
        /**
         * Parameter description
         *
         * @param order_id $order_id order id details.
         */
        public function process_payment($order_id) {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => add_query_arg(
                    'order',
                    $order->id,
                    add_query_arg('key', $order->order_key, $order->get_checkout_payment_url(true))
                ),
            );
        }

        /**
         * Check for valid Airpay server callback // response processing //
         **/
        public function airpay_check_response() {

            global $woocommerce;
            // check_admin_referer('TRANSACTIONID', 'TRANSACTIONID_nonce');
            // check_admin_referer('TRANSACTIONSTATUS', 'TRANSACTIONSTATUS_nonce');
            if (isset($_REQUEST['TRANSACTIONID']) && isset($_REQUEST['TRANSACTIONSTATUS'])) {

                $order_sent = isset($_REQUEST['TRANSACTIONID']) ? sanitize_text_field(wp_unslash($_REQUEST['TRANSACTIONID'])) : null;
                $transaction_id = isset($_POST['TRANSACTIONID']) ? sanitize_text_field(wp_unslash($_POST['TRANSACTIONID'])) : null;
                $order = isset($_POST['TRANSACTIONID']) ? sanitize_text_field(wp_unslash($_POST['TRANSACTIONID'])) : null;
                $response_description = isset($_REQUEST['MESSAGE']) ? sanitize_text_field(wp_unslash($_REQUEST['MESSAGE'])) : null;
                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    $order = new WC_Order(sanitize_text_field(wp_unslash($_REQUEST['TRANSACTIONID'])));
                } else {
                    $order = new woocommerce_order(sanitize_text_field(wp_unslash($_REQUEST['TRANSACTIONID'])));
                }
                $redirect_url = ('' === $this->redirect_page_id || 0 === $this->redirect_page_id) ? get_site_url() . '/' : get_permalink($this->redirect_page_id);
                $ap_transaction_id = isset($_POST['APTRANSACTIONID']) ? sanitize_text_field(wp_unslash($_POST['APTRANSACTIONID'])) : null;
                $amount = isset($_POST['AMOUNT']) ? sanitize_text_field(wp_unslash($_POST['AMOUNT'])) : null;
                $transaction_status = isset($_POST['TRANSACTIONSTATUS']) ? sanitize_text_field(wp_unslash($_POST['TRANSACTIONSTATUS'])) : null;
                $response_description = isset($_POST['MESSAGE']) ? sanitize_text_field(wp_unslash($_POST['MESSAGE'])) : null;
                $ap_secure_hash = isset($_POST['ap_SecureHash']) ? sanitize_text_field(wp_unslash($_POST['ap_SecureHash'])) : null;
                $customer_vpa = isset($_POST['CUSTOMERVPA']) ? sanitize_text_field(wp_unslash($_POST['CUSTOMERVPA'])) : null;
                $chmod = isset($_POST['CHMOD']) ? sanitize_text_field(wp_unslash($_POST['CHMOD'])) : null;
                $error_msg = '';
                if (empty($order) || empty($ap_transaction_id) || empty($amount) || empty($transaction_status) || empty($ap_secure_hash)) {
                    // Reponse has been compromised. So treat this transaction as failed.
                    if (empty($order)) {
                        $error_msg = 'TRANSACTIONID ';
                    }
                    if (empty($ap_transaction_id)) {
                        $error_msg .= ' APTRANSACTIONID';
                    }
                    if (empty($amount)) {
                        $error_msg .= ' AMOUNT';
                    }
                    if (empty($transaction_status)) {
                        $error_msg .= ' TRANSACTIONSTATUS';
                    }
                    if (empty($ap_secure_hash)) {
                        $error_msg .= ' ap_SecureHash';
                    }
                    $error_msg .= '<tr><td>Variable(s) ' . $error_msg . ' is/are empty.</td></tr>';
                }
                if ($error_msg != '') {
                    $this->msg['class'] = 'error';
                    $this->msg['message'] = 'Thank you for shopping with us. However, the transaction has been Failed For Reason  : ' . $error_msg;
                }
                if (200 == $_REQUEST['TRANSACTIONSTATUS']) {
                    // success.

                    $mercid = $this->merchant_identifier;
                    $username = $this->username;
                    $order_amount = $order->order_total;
                    $order_amount = sprintf('%01.2f', $order_amount);
                    if (isset($_REQUEST['AMOUNT'])) {
                        if (($_REQUEST['AMOUNT'] === $order_amount)) {
                            //$all = AirpayChecksum::get_all_params();

                            $newcheck = sprintf('%u', crc32($transaction_id . ':' . $ap_transaction_id . ':' . $amount . ':' . $transaction_status . ':' . $response_description . ':' . $mercid . ':' . $username));
                            if (isset($_REQUEST['CHMOD'])) {
                                if ('upi' === sanitize_text_field(wp_unslash($_REQUEST['CHMOD']))) {
                                    $cvar = isset($_REQUEST['CUSTOMERVPA']) ? sanitize_text_field(wp_unslash($_REQUEST['CUSTOMERVPA'])) : null;
                                    $newcheck = sprintf('%u', crc32($transaction_id . ':' . $ap_transaction_id . ':' . $amount . ':' . $transaction_status . ':' . $response_description . ':' . $mercid . ':' . $username . ':' . $cvar));
                                }
                            }
                            if ($newcheck === $ap_secure_hash) {
                                if ('completed' !== $order->status) {
                                    $this->msg['message'] = esc_html__('Thank you for shopping with us. Your account has been charged and your transaction is successful.', 'airpay');
                                    $this->msg['class'] = 'success';
                                    if ('processing' !== $order->status) {
                                        $order->payment_complete();
                                        $order->add_order_note(esc_html__('#' . $ap_transaction_id . ' payment successful', 'airpay'));
                                        $order->add_order_note(esc_html($this->msg['message']));
                                        $woocommerce->cart->empty_cart();
                                    }
                                }
                            } else {
                                $this->msg['class'] = 'error';
                                $this->msg['message'] = esc_html__('Severe Error Occur.', 'airpay');
                                $order->update_status('failed');
                                $order->add_order_note('Failed');
                                $order->add_order_note(esc_html($this->msg['message']));
                            }
                        }
                    } else {
                        // Order mismatch occur //.
                        $this->msg['class'] = 'error';
                        $this->msg['message'] = esc_html__('Order Mismatch Occur', 'airpay');
                        $order->update_status('failed');
                        $order->add_order_note('Failed');
                        $order->add_order_note(esc_html($this->msg['message']));

                    }
                } else {
                    $order->update_status('failed');
                    $order->add_order_note('Failed');
                    $order->add_order_note($response_description);
                    $order->add_order_note(esc_html($this->msg['message']));

                }
                wp_safe_redirect($this->get_return_url($order));
                exit;
            } else {
                // wp_safe_redirect(get_site_url() . '/checkout/');
                // exit;
            }
        }

        /**
         * Generate Airpay button link
         **/
        /**
         * Parameter description
         *
         * @param order_id $order_id order id details.
         */
        public function airpay_generate_form($order_id) {
            global $woocommerce;
            $txn_date = gmdate('Y-m-d');
            $milliseconds = (int) (1000 * (strtotime(gmdate('Y-m-d'))));
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                $order = new WC_Order($order_id);
            } else {
                $order = new woocommerce_order($order_id);
            }
            $redirect_url = ('' === $this->redirect_page_id || 0 === $this->redirect_page_id) ? get_site_url() . '/' : get_permalink($this->redirect_page_id);
            // pretty url check //.
            $a = strstr($redirect_url, '?');
            if ($a) {
                $redirect_url .= '&wc-api=WC_Airpay';
            } else {
                $redirect_url .= '?wc-api=WC_Airpay';
            }
            $amt = $order->order_total;
            $amt = sprintf('%01.2f', $amt);
            $txntype = '1';
            $apayoption = '1';
            $currency = 'INR';
            $purpose = '1';
            $product_description = 'airpay';
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
            }
            $post_variables = array(
                'merchant_identifier' => $this->merchant_identifier,
                'orderId' => $order_id,
                'returnUrl' => $redirect_url,
                'buyerEmail' => $order->billing_email,
                'buyerFirstName' => $order->billing_first_name,
                'buyerLastName' => $order->billing_last_name,
                'buyerAddress' => $order->billing_address_1,
                'buyerCity' => $order->billing_city,
                'buyerState' => $order->billing_state,
                'buyerCountry' => $order->billing_country,
                'buyerPincode' => $order->billing_postcode,
                'buyerPhone' => $order->billing_phone,
                'txnType' => $txntype,
                'apPayOption' => $apayoption,
                'mode' => $this->mode,
                'currency' => $this->currency,
                'amount' => $amt, // Amount should be in paisa.
                'merchantIpAddress' => $ip,
                'purpose' => $purpose,
                'productDescription' => $product_description,
                'txnDate' => $txn_date,

            );

            $all = '';
            foreach ($post_variables as $name => $value) {
                if ('checksum' !== $name) {
                    $all .= "'";
                    if ('returnUrl' === $name) {
                        $all .= AirpayChecksum::sanitized_url($value);
                    } else {

                        $all .= AirpayChecksum::sanitized_param($value);
                    }
                    $all .= "'";
                }
            }
            $alldata = AirpayChecksum::sanitized_param($order->billing_email) . AirpayChecksum::sanitized_param($order->billing_first_name) . AirpayChecksum::sanitized_param($order->billing_last_name) . AirpayChecksum::sanitized_param($order->billing_address_1) . AirpayChecksum::sanitized_param($order->billing_city) . AirpayChecksum::sanitized_param($order->billing_state) . AirpayChecksum::sanitized_param($order->billing_country) . AirpayChecksum::sanitized_param($amt) . '' . $order_id;
            $privatekey = AirpayChecksum::encrypt($this->username . ':|:' . $this->password, $this->secret_key);
            $key_sha_256 = AirpayChecksum::encrypt_sha_256($this->username . '~:~' . $this->password);
            $checksum = AirpayChecksum::calculate_checksum_sha_256($alldata . gmdate('Y-m-d'), $key_sha_256);

            $airpay_args = array(
                'mercid' => $this->merchant_identifier,
                'orderid' => $order_id,
                'buyerEmail' => $order->billing_email,
                'buyerFirstName' => $order->billing_first_name,
                'buyerLastName' => $order->billing_last_name,
                'buyerAddress' => $order->billing_address_1,
                'buyerCity' => $order->billing_city,
                'buyerState' => $order->billing_state,
                'buyerCountry' => $order->billing_country,
                'buyerPincode' => $order->billing_postcode,
                'buyerPhone' => $order->billing_phone,
                'txnType' => $txntype,
                'mode' => $this->mode,
                'currency' => $this->currency,
                'amount' => $amt,
                'chmod' => $this->payment_mode,
                'purpose' => $purpose,
                'productDescription' => $product_description,
                'txnDate' => $txn_date,
                'checksum' => $checksum,
            );
            foreach ($airpay_args as $name => $value) {
                if ('checksum' !== $name) {
                    if ('returnUrl' === $name) {
                        $value = AirpayChecksum::sanitized_url($value);

                    } else {
                        $value = AirpayChecksum::sanitized_param($value);

                    }
                }
            }

            $airpay_args_array = array();
            foreach ($airpay_args as $key => $value) {
                if ('checksum' !== $key) {
                    if ('returnUrl' === $key) {
                        $airpay_args_array[] = "<input type='hidden' name='$key' value='" . AirpayChecksum::sanitized_url($value) . "'/>";
                    } else {
                        $airpay_args_array[] = "<input type='hidden' name='$key' value='" . AirpayChecksum::sanitized_param($value) . "'/>";
                    }
                } else {
                    $airpay_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
                }
            }
            return '<form action="' . $this->liveurl . '" method="post" id="Airpay_payment_form">
                ' . implode('', $airpay_args_array) . '
                    <input type="hidden" name="privatekey" value="' . $privatekey . '">
                    <input type="hidden" name="apyVer" value="3">
                    <input type="hidden" name="isocurrency" value="' . $this->iso_currency . '">
                <input type="submit" class="button-alt" id="submit_Airpay_payment_form" value="' . __('Pay via Airpay') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart') . '</a>
                </form><script type="text/javascript">
    jQuery(function(){
        jQuery("body").block({
            message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />' . __('Thank you for your order. We are now redirecting you to Airpay to make payment.') . '",
            overlayCSS: { background: "#fff", opacity: 0.6 },
            css: { padding: 20, textAlign: "center", color: "#555", border: "3px solid #aaa", backgroundColor: "#fff", cursor: "wait", lineHeight: "32px" }
        });
        jQuery("#submit_Airpay_payment_form").click();
    });
</script>';
        }

        /**
         * End Airpay Essential Functions
         *
         * @param title  $title title of page.
         * @param indent $indent indent of page.
         **/
        public function get_pages($title = false, $indent = true) {
            // get all pages.
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) {
                $page_list[] = $title;
            }

            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array.
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }
    }

    /**
     * Add the Gateway to WooCommerce
     *
     * @param methods $methods method name.
     **/
    function airpay_woocommerce_add_gateway($methods) {
        $methods[] = 'Airpay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'airpay_woocommerce_add_gateway');
}
