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

        add_filter('woocommerce_order_actions', array($this, 'add_admin_order_actions'), 10, 1);
        add_action('woocommerce_order_action_pesepay_check_status', array($this, 'process_admin_order_action'), 10, 1);
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

            'poll_attempts' => array(
                'title' => __('Status Poll Attempts', PESEPAY_SLUG),
                'type' => 'number',
                'description' => __('How many times the plugin will re-check the payment status with Pesepay before giving up. A status that is not confirmed (e.g. FAILED while the money was deducted) gets a final confirmation check before the order is failed. Set to 1 to skip extra polling.', PESEPAY_SLUG),
                'default' => 6,
                'custom_attributes' => array('min' => 1, 'max' => 30, 'step' => 1),
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
                $order->update_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"), 0);
                $order->update_meta_data(PesePay_Helper::meta_key_prefix("-failed-verify"), 0);
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

    /**
     * Add a manual "re-check" action to WooCommerce order admin pages.
     *
     * @since 1.4.0
     * @param array $actions
     * @return array
     */
    public function add_admin_order_actions($actions)
    {
        $actions['pesepay_check_status'] = __('Re-check Pesepay status', PESEPAY_SLUG);

        return $actions;
    }

    /**
     * Run the manual status re-check from the order admin page.
     *
     * Resets the poll counters so a manual re-check behaves like a fresh check
     * and re-runs the same logic as the scheduled poll.
     *
     * @since 1.4.0
     * @param WC_Order|int $order
     * @return void
     */
    public function process_admin_order_action($order)
    {
        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order);
        }

        if (!$order) {
            return;
        }

        $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"));
        $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-failed-verify"));
        $order->save_meta_data();

        check_pesepay_payment_status_callback($order->get_id());
    }


} // end \WC_Pesepay

/**
 * Get the delay (in seconds) before the next status check.
 *
 * Retries grow from a short first interval to a longer one, capping out so a
 * transaction is not hammered forever.
 *
 * @since 1.4.0
 * @param int $attempt Zero-based retry index already performed.
 * @return int
 */
function pesepay_next_poll_delay($attempt)
{
    $delays = array(120, 300, 900, 1800, 3600, 3600);

    $index = max(0, min((int) $attempt, count($delays) - 1));

    return $delays[$index];
}

/**
 * Queue the next status check for an order.
 *
 * Uses a one-shot WP-Cron event; wp_schedule_single_event() de-duplicates by
 * hook + args so duplicate calls never create parallel events.
 *
 * @since 1.4.0
 * @param int $order_id
 * @param int $delay
 * @return void
 */
function pesepay_queue_status_check($order_id, $delay = 120)
{
    if (!wp_schedule_single_event(time() + (int) $delay, 'check_pesepay_payment_status', array($order_id))) {
        PesePay_Helper::log('A status check is already scheduled for order ' . $order_id);
    }
}

/**
 * Remove any pending status checks for an order.
 *
 * @since 1.4.0
 * @param int $order_id
 * @return void
 */
function pesepay_clear_status_checks($order_id)
{
    wp_clear_scheduled_hook('check_pesepay_payment_status', array($order_id));
}

/**
 * Function to check Pesepay payment status for a given order.
 *
 * Uses the environment recorded at payment initiation so that sandbox
 * transactions are never checked against the live endpoint, even if the
 * global test_mode setting has changed since the payment was made.
 *
 * The check keeps re-polling until a terminal status is reached:
 *   - SUCCESS / SUCCEEDED  → order is completed & stock reduced.
 *   - CANCELLED            → terminal, order left as-is.
 *   - FAILED               → given one final confirmation poll before the
 *                            order is failed, to guard against the case where
 *                            money was deducted but Pesepay reported FAILED.
 *   - any other status     → still processing, keep polling up to the
 *                            configured attempt limit, then leave the order
 *                            on hold (never fail an unconfirmed order).
 *
 * Every response is written to the plugin debug log (when enabled) and every
 * result is recorded as an order note with the status and reference.
 *
 * @version 1.4.0
 * @param int $order_id The WooCommerce order ID.
 */
function check_pesepay_payment_status_callback($order_id)
{
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    // Order is already marked as paid — nothing left to verify.
    if (in_array($order->get_status(), wc_get_is_paid_statuses(), true)) {
        pesepay_clear_status_checks($order->get_id());
        return;
    }

    $reference = $order->get_meta(PesePay_Helper::meta_key_prefix("-reference-number"));

    // Use the environment saved when the payment was initiated.
    $mode = $order->get_meta(PesePay_Helper::meta_key_prefix("-environment")) ?: 'live';

    $attempts = (int) $order->get_meta(PesePay_Helper::meta_key_prefix("-check-attempts"));

    $gateway = PesePay_Helper::get_gateway_instance();
    $max_attempts = (is_object($gateway)) ? max(1, (int) $gateway->get_option('poll_attempts', 6)) : 6;

    $response = PesePay_Helper::remote_check_transaction($reference, $mode);

    // Log the raw response for debugging.
    PesePay_Helper::log(
        vsprintf('Status check for order #%1$s (ref %2$s): %3$s', array(
            $order->get_id(),
            $reference,
            wp_json_encode($response)
        ))
    );

    // Pesepay could not be reached or returned an error — retry instead of failing.
    if (!$response || !$response["success"]) {
        $message = __('Error retrieving transaction status from Pesepay', PESEPAY_SLUG);
        $order->add_order_note($message . ' | Ref: ' . $reference);
        PesePay_Helper::log($message . ' Order #: ' . $order->get_id());

        if ($attempts >= $max_attempts) {
            $order->add_order_note(sprintf(
                __('Pesepay status check failed %1$d times — no further checks scheduled for ref %2$s', PESEPAY_SLUG),
                $max_attempts,
                $reference
            ));
            pesepay_clear_status_checks($order->get_id());
            $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"));
        } else {
            $order->update_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"), $attempts + 1);
            $order->save_meta_data();
            pesepay_queue_status_check($order->get_id(), pesepay_next_poll_delay($attempts));
        }

        $order->save();
        return;
    }

    $status = strtoupper($response["data"]["transactionStatus"]);
    $description = isset($response["data"]["transactionStatusDescription"]) ? $response["data"]["transactionStatusDescription"] : '';

    switch ($status) {
        case "SUCCESS":
        case "SUCCEEDED":
            $order->update_meta_data(PesePay_Helper::meta_key_prefix("-transaction-status"), "SUCCESS");
            $order->add_order_note(sprintf(__('Pesepay status: SUCCESS | Ref: %1$s | %2$s', PESEPAY_SLUG), $reference, $description));
            PesePay_Helper::log('Pesepay SUCCESS Order #: ' . $order->get_id() . ' Ref: ' . $reference);

            if (!in_array($order->get_status(), wc_get_is_paid_statuses(), true)) {
                $order->payment_complete();
                wc_reduce_stock_levels($order->get_id());
            }

            pesepay_clear_status_checks($order->get_id());
            $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"));
            $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-failed-verify"));
            break;

        case "CANCELLED":
            $order->update_meta_data(PesePay_Helper::meta_key_prefix("-transaction-status"), "CANCELLED");
            $order->add_order_note(sprintf(__('Pesepay status: CANCELLED | Ref: %1$s | %2$s', PESEPAY_SLUG), $reference, $description));
            PesePay_Helper::log('Pesepay CANCELLED Order #: ' . $order->get_id() . ' Ref: ' . $reference);

            pesepay_clear_status_checks($order->get_id());
            $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"));
            $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-failed-verify"));
            break;

        case "FAILED":
            $order->add_order_note(sprintf(__('Pesepay status: FAILED | Ref: %1$s | %2$s — confirming before finalising.', PESEPAY_SLUG), $reference, $description));
            PesePay_Helper::log('Pesepay FAILED Order #: ' . $order->get_id() . ' Ref: ' . $reference);

            // Last-chance verification: Pesepay can report FAILED while money was
            // actually deducted. Give it a single confirmation poll before marking
            // the order as failed. If that poll reports SUCCESS, the order is paid.
            $failed_checks = (int) $order->get_meta(PesePay_Helper::meta_key_prefix("-failed-verify"));

            if ($failed_checks < 1) {
                $order->update_meta_data(PesePay_Helper::meta_key_prefix("-failed-verify"), 1);
                $order->save_meta_data();
                pesepay_queue_status_check($order->get_id(), 180);
            } else {
                $order->update_meta_data(PesePay_Helper::meta_key_prefix("-transaction-status"), "FAILED");
                pesepay_clear_status_checks($order->get_id());
                $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"));
                $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-failed-verify"));

                // Only downgrade the order if it has not already been marked paid.
                if (!in_array($order->get_status(), wc_get_is_paid_statuses(), true)) {
                    $order->set_status("failed", sprintf(__('Pesepay: %1$s | Ref: %2$s', PESEPAY_SLUG), $description, $reference));
                }
            }
            break;

        default:
            // Non-terminal — keep polling until the transaction settles.
            if ($attempts >= $max_attempts) {
                $order->add_order_note(sprintf(
                    __('Pesepay transaction for ref %1$s is still not confirmed after %2$d checks — order kept on hold.', PESEPAY_SLUG),
                    $reference,
                    $max_attempts
                ));
                PesePay_Helper::log('Pesepay pending after ' . $max_attempts . ' attempts, Order #: ' . $order->get_id() . ' Ref: ' . $reference);

                pesepay_clear_status_checks($order->get_id());
                $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"));
            } else {
                $order->update_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"), $attempts + 1);
                $order->add_order_note(sprintf(
                    __('Pesepay status: %1$s | Ref: %2$s — check %3$d of %4$d.', PESEPAY_SLUG),
                    $status,
                    $reference,
                    $attempts + 1,
                    $max_attempts
                ));

                $order->save_meta_data();
                pesepay_queue_status_check($order->get_id(), pesepay_next_poll_delay($attempts));
                PesePay_Helper::log('Pesepay status ' . $status . ' Order #: ' . $order->get_id() . ' Ref: ' . $reference);
            }
            break;
    }

    $order->save();
}

add_action('check_pesepay_payment_status', 'check_pesepay_payment_status_callback', 10, 1);
