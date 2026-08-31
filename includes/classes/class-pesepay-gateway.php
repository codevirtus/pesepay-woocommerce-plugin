<?php

defined('ABSPATH') || exit;

/**
 * The file that defines the core payment class
 *
 * A class definition that includes attributes and functions used across the
 * payments side of the site and the admin area.
 *
 * @link       https://pesepay.com
 * @since      1.0.0
 *
 * @package    Pesepay
 * @subpackage Pesepay/includes
 */

/**
 * The core payment class.
 *
 * This is used to define payment hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this payment gateway as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Pesepay
 * @subpackage Pesepay/includes/classes
 * @author     Pesepay <digital@pesepay.com>
 */
class WC_Pesepay_Gateway extends WC_Payment_Gateway
{

    /**
     * Initialise the payment gateway
     *
     * We only get one chance to do it correctly ;)
     *
     * @since 1.0.0
     * @version 1.0.0
     * @return void
     */
    public function __construct()
    {
        $this->id = 'wc_gateway_pesepay';
        $this->icon = plugins_url('public/img/logo.svg', PESEPAY_PLUGIN_FILE);
        $this->has_fields = true;
        $this->method_title = __('Pesepay', PESEPAY_SLUG);
        $this->method_description = __('Pay with Pesepay', PESEPAY_SLUG);

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Prepare the admin fields for setting up the plugin
     *
     * @since 1.0.0
     * @version 1.0.0
     * @return void
     */
    public function init_form_fields()
    {

        /**
         * Currencies supported by this gateway
         */
        $currencies = PesePay_Helper::get_supported_currencies();
        $currencies = array_combine(array_column($currencies, "code"), array_column($currencies, "name"));

        /**
         * Quick link to pesepay
         */
        $anchor = '<a href="https://pesepay.com">Pesepay</a>';

        /**
         * Status to set after an order has been completed
         */
        $statuses = wc_get_is_paid_statuses();
        $statuses = array_combine($statuses, array_map("wc_get_order_status_name", $statuses));

        $statuses = array_merge(array("" => __("Select Status", PESEPAY_SLUG)), $statuses);

        /**
         * The form fields
         */
        $this->form_fields = apply_filters(PESEPAY_SLUG . '_form_fields', array(

            'enabled' => array(
                'title' => __('Enable/Disable', PESEPAY_SLUG),
                'type' => 'checkbox',
                'label' => __('Enable Pesepay Payment', PESEPAY_SLUG),
                'default' => 'no'
            ),
            'encryption_key' => array(
                'title' => __('Encryption Key', PESEPAY_SLUG),
                "custom_attributes" => array("minlength" => PesePay_Helper::encryption_key_length(), "maxlength" => PesePay_Helper::encryption_key_length()),
                'type' => 'password',
                'description' => sprintf(__('Encryption key, obtained from %s', PESEPAY_SLUG), $anchor),
            ),
            'integration_key' => array(
                'title' => __('Integration Key', PESEPAY_SLUG),
                'type' => 'password',
                'description' => sprintf(__('Integration key, obtained from %s', PESEPAY_SLUG), $anchor),
            ),
            'title' => array(
                'title' => __('Title', PESEPAY_SLUG),
                'type' => 'text',
                'description' => __('This controls the title for the payment method the customer sees during checkout.', PESEPAY_SLUG),
                'default' => __('Pesepay Payment', PESEPAY_SLUG),
                'desc_tip' => true,
            ),

            'description' => array(
                'title' => __('Description', PESEPAY_SLUG),
                'type' => 'text',
                'description' => __('Payment method description that the customer will see on your checkout.', PESEPAY_SLUG),
                'default' => __('Pay with Pesepay.', PESEPAY_SLUG),
                'desc_tip' => true,
            ),

            'status' => array(
                'title' => __('Order Status', PESEPAY_SLUG),
                'type' => 'select',
                'description' => __('Order status after a customer has completed payment.', PESEPAY_SLUG),
                'default' => current($statuses),
                'options' => $statuses,
                'desc_tip' => true,
                "class" => "wc-enhanced-select",
            ),

            'currencies' => array(
                'title' => __('Currencies', PESEPAY_SLUG),
                'type' => 'multiselect',
                'description' => __('Currencies this payment gateway should handle.', PESEPAY_SLUG),
                'default' => current(PesePay_Helper::get_supported_currency_codes()),
                'desc_tip' => true,
                "class" => "wc-enhanced-select",
                'options' => $currencies,
                "select_buttons" => true
            ),

            'instructions' => array(
                'title' => __('Instructions', PESEPAY_SLUG),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.(Optional)', PESEPAY_SLUG),
                'default' => '',
                'desc_tip' => true,
            ),
            'debug' => array(
                'title' => __('Debug log', PESEPAY_SLUG),
                'type' => 'checkbox',
                'label' => __('Enable logging', PESEPAY_SLUG),
                'default' => 'no',
            ),

            // ── Test / sandbox mode ──────────────────────────────────────────
            'test_mode' => array(
                'title' => __('Enable Test Mode', PESEPAY_SLUG),
                'type' => 'checkbox',
                'label' => __('Enable test mode (simulated payments — does not process real money)', PESEPAY_SLUG),
                'default' => 'no',
                'description' => __('When enabled, payments are sent to the Pesepay sandbox environment. No real money is charged. Use your Pesepay sandbox credentials below.', PESEPAY_SLUG),
            ),
            'test_integration_key' => array(
                'title' => __('Test Integration Key', PESEPAY_SLUG),
                'type' => 'password',
                'description' => __('Integration key for the Pesepay sandbox environment.', PESEPAY_SLUG),
                'default' => '',
            ),
            'test_encryption_key' => array(
                'title' => __('Test Encryption Key', PESEPAY_SLUG),
                'type' => 'password',
                'description' => __('Encryption key for the Pesepay sandbox environment.', PESEPAY_SLUG),
                'default' => '',
                'custom_attributes' => array(
                    'minlength' => PesePay_Helper::encryption_key_length(),
                    'maxlength' => PesePay_Helper::encryption_key_length(),
                ),
            ),
        ));
    }

    /**
     * Show the pesepay badge
     *
     * @since 1.0.0
     * @version 1.0.0
     * @return void
     */
    public function payment_fields()
    {
        parent::payment_fields();

        include plugin_dir_path(dirname(__DIR__)) . "public/partials/pesepay-public-display.php";
    }

    /**
     * Initiate payment on pesepay and redirect
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id): array
    {

        $order = wc_get_order($order_id);

        // Validate test credentials early when test mode is active.
        if ($this->get_option('test_mode') === 'yes') {
            $test_integration_key = (string) $this->get_option('test_integration_key', '');
            $test_encryption_key = (string) $this->get_option('test_encryption_key', '');

            if ($test_integration_key === '' || $test_encryption_key === '') {
                $message = __('Pesepay test mode is enabled but test credentials are not configured. Please add your Test Integration Key and Test Encryption Key in the gateway settings.', PESEPAY_SLUG);
                wc_add_notice($message, 'error');
                PesePay_Helper::log('[test] Missing test credentials for order #' . $order_id);
                return parent::process_payment($order_id);
            }
        }

        $total = $this->get_order_total();
        $currency = get_woocommerce_currency();
        $ref = sprintf(__("Order #: %s", PESEPAY_SLUG), $order->get_id());

        $url = add_query_arg(
            array(
                'wc-api' => PESEPAY_SLUG,
                "order" => $order->get_id()
            ),
            site_url()
        );

        $links = array(
            "returnUrl" => $url,
            "resultUrl" => $url
        );

        $response = PesePay_Helper::remote_init_transaction($total, $currency, $ref, $links);

        if ($response) {

            if ($response["success"]) {

                // Record the environment used so status checks always query the correct endpoint.
                $environment = ($this->get_option('test_mode') === 'yes') ? 'test' : 'live';
                $order->update_meta_data(PesePay_Helper::meta_key_prefix("-environment"), $environment);

                # Save the reference number and/or poll url (used to check the status of a transaction)
                $order->update_meta_data(PesePay_Helper::meta_key_prefix("-reference-number"), $response["data"]["referenceNumber"]);
                $order->save_meta_data();

                # Schedule payment check after 2 minutes
                $timestamp = wp_next_scheduled('check_pesepay_payment_status', array($order->get_id()));
                if (!$timestamp) {
                    if (wp_schedule_single_event(time() + 120, 'check_pesepay_payment_status', array($order->get_id()))) {
                        PesePay_Helper::log('Event scheduled to check payment status for order ' . $order->get_id());
                    } else {
                        PesePay_Helper::log('Failed to schedule event for order ' . $order->get_id());
                    }
                } else {
                    PesePay_Helper::log('Event already scheduled for order ' . $order->get_id() . ' at ' . date('Y-m-d H:i:s', $timestamp));
                }

                WC()->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $response["data"]["redirectUrl"]
                );
            } else {
                # Get error message
                wc_add_notice($response["data"]["transactionStatusDescription"], "error");

                PesePay_Helper::log($response["data"]["transactionStatusDescription"] . "Order #: " . $order->get_id());
            }
        } else {
            # Get generic error message
            $message = __('Failed to initiate the transaction on pesepay, make sure your credentials are correct', PESEPAY_SLUG);

            wc_add_notice($message, "error");

            PesePay_Helper::log($message . "Order #: " . $order->get_id());
        }

        return parent::process_payment($order_id);
    }

    /**
     * Check if the gateway needs setup
     *
     * In test mode, checks for test credentials; in live mode, checks for live credentials.
     *
     * @since 1.0.0
     * @version 1.0.0
     * @return bool
     */
    public function needs_setup()
    {
        if ($this->get_option('test_mode') === 'yes') {
            $keys = array('test_encryption_key', 'test_integration_key');
        } else {
            $keys = array('encryption_key', 'integration_key');
        }

        $enabled = true;
        foreach ($keys as $key) {
            $enabled &= strlen($this->get_option($key, "")) > 0;
        }

        return !$enabled;
    }

    /**
     * Check if the gateway is available for use.
     *
     * @version 1.0.0
     * @since 1.0.0
     * @return bool
     */
    public function is_available()
    {

        if (parent::is_available() && !$this->needs_setup()) {

            $currencies = $this->get_option("currencies", array());

            return in_array(get_woocommerce_currency(), wp_parse_list($currencies));
        }

        return false;
    }


} // end \WC_Pesepay

/**
 * Function to check Pesepay payment status for a given order.
 *
 * Uses the environment recorded at payment initiation so that sandbox
 * transactions are never checked against the live endpoint, even if the
 * global test_mode setting has changed since the payment was made.
 *
 * @version 1.3.0
 * @param int $order_id The WooCommerce order ID.
 */
function check_pesepay_payment_status_callback($order_id)
{
    $order = wc_get_order($order_id);

    if ($order) {
        $reference = $order->get_meta(PesePay_Helper::meta_key_prefix("-reference-number"));

        // Use the environment saved when the payment was initiated.
        $mode = $order->get_meta(PesePay_Helper::meta_key_prefix("-environment")) ?: 'live';

        $response = PesePay_Helper::remote_check_transaction($reference, $mode);

        if ($response && $response["success"]) {
            $status = strtoupper($response["data"]["transactionStatus"]);

            switch ($status) {
                case "CANCELLED":
                    $message = __("Payment status: CANCELLED on Pesepay ref " . $reference);
                    $order->add_order_note($message);
                    break;

                case "SUCCESS":
                    $message = __("Payment status: SUCCESS on Pesepay ref " . $reference);
                    $order->add_order_note($message);
                    if (!in_array($order->get_status(), array('completed', 'processing'))) {
                        $order->payment_complete();
                        wc_reduce_stock_levels($order->get_id());
                        PesePay_Helper::log("Pesepay " . $response["data"]["transactionStatusDescription"] . " Ref: " . $reference);
                    }
                    break;

                case "FAILED":
                default:
                    $message = __("Pesepay: " . $response["data"]["transactionStatusDescription"] . " Ref " . $reference);
                    $order->add_order_note($message);
                    PesePay_Helper::log("Pesepay " . $response["data"]["transactionStatusDescription"] . " Ref: " . $reference);
                    break;
            }
        } else {
            $message = __("Error retrieving transaction status from Pesepay");
            $order->add_order_note($message);
        }

        $order->save();
    }
}

add_action('check_pesepay_payment_status', 'check_pesepay_payment_status_callback', 10, 1);
