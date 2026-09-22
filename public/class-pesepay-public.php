<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://pesepay.com
 * @since      1.0.0
 *
 * @package    Pesepay
 * @subpackage Pesepay/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Pesepay
 * @subpackage Pesepay/public
 * @author     Pesepay <digital@pesepay.com>
 */
class Pesepay_Public
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Handle response from pesepay
     *
     * @since 1.0.0
     * @version 1.4.0
     * @return array
     */
    public function woocommerce_api_wc_gateway()
    {

        $order = filter_input(INPUT_GET, "order", FILTER_VALIDATE_INT);

        if ($order) {

            $order = wc_get_order($order);

            // Order is already marked as paid — send the customer straight through.
            if (in_array($order->get_status(), wc_get_is_paid_statuses(), true)) {
                $gateway = PesePay_Helper::get_gateway_instance();

                if (is_object($gateway)) {
                    wp_redirect($gateway->get_return_url($order));
                } else {
                    wp_redirect($order->get_checkout_order_received_url());
                }
                return;
            }

            $reference = $order->get_meta(PesePay_Helper::meta_key_prefix("-reference-number"));

            // Use the environment recorded at payment initiation.
            $mode = $order->get_meta(PesePay_Helper::meta_key_prefix("-environment")) ?: 'live';

            $response = PesePay_Helper::remote_check_transaction($reference, $mode);

            // Log the raw response for debugging.
            PesePay_Helper::log(
                vsprintf('Status check (return) for order #%1$s (ref %2$s): %3$s', array(
                    $order->get_id(),
                    $reference,
                    wp_json_encode($response)
                ))
            );

            if ($response && $response["success"]) {

                # Save the reference number and/or poll url (used to check the status of a transaction)
                $order->update_meta_data(PesePay_Helper::meta_key_prefix("-reference-number"), $response["data"]["referenceNumber"]);
                $order->save_meta_data();

                $status = strtoupper($response["data"]["transactionStatus"]);
                $description = isset($response["data"]["transactionStatusDescription"]) ? $response["data"]["transactionStatusDescription"] : '';

                $order->add_order_note(sprintf(__('Pesepay status: %1$s | Ref: %2$s | %3$s', PESEPAY_SLUG), $status, $reference, $description));

                switch ($status) {
                    case "SUCCESS":
                    case "SUCCEEDED":
                        //payment confirmed
                        $order->payment_complete();

                        // Reduce stock levels
                        wc_reduce_stock_levels($order->get_id());

                        $order->update_meta_data(PesePay_Helper::meta_key_prefix("-transaction-status"), "SUCCESS");
                        $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"));
                        $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-failed-verify"));
                        $order->save_meta_data();

                        pesepay_clear_status_checks($order->get_id());

                        PesePay_Helper::log(__("Payment Completed", $this->plugin_name) . " Order #: " . $order->get_id());

                        $gateway = PesePay_Helper::get_gateway_instance();

                        if (is_object($gateway)) {
                            wp_redirect($gateway->get_return_url($order));
                        } else {
                            wp_redirect($order->get_checkout_order_received_url());
                        }
                        return;
                        break;
                    case "CANCELLED":
                        $message = __("Transaction cancelled on Pesepay", $this->plugin_name);

                        wc_add_notice($message, "error");

                        PesePay_Helper::log($message . " Order #: " . $order->get_id());

                        $order->update_meta_data(PesePay_Helper::meta_key_prefix("-transaction-status"), "CANCELLED");
                        $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-check-attempts"));
                        $order->delete_meta_data(PesePay_Helper::meta_key_prefix("-failed-verify"));
                        $order->save_meta_data();

                        pesepay_clear_status_checks($order->get_id());
                        break;
                    case "FAILED":
                    default:
                        // Pesepay reports FAILED (or a status we don't recognise).
                        // Money may already have been deducted, so do NOT fail the
                        // order immediately — queue a confirmation poll and let
                        // check_pesepay_payment_status_callback() finalise it.
                        $message = __("Payment status could not be confirmed, it will be verified automatically", $this->plugin_name);

                        wc_add_notice($message, "error");

                        PesePay_Helper::log($message . " Order #: " . $order->get_id() . " Ref: " . $reference);

                        pesepay_queue_status_check($order->get_id(), 180);
                        break;
                }
            } else {
                # Get error message — schedule a retry rather than failing the order
                $message = __("Error retrieving transaction status, it will be checked automatically", $this->plugin_name);

                wc_add_notice($message, "error");

                PesePay_Helper::log($response ? wp_json_encode($response) : $message . " Order #: " . $order->get_id());

                pesepay_queue_status_check($order->get_id(), 60);
            }

            $order->save();

            wp_redirect($order->get_checkout_payment_url());
            return;
        }

        wp_redirect(site_url());
    }

    /**
     * Set order status as set in payment gateway options
     *
     * @param string $status
     * @param string $order
     * @return string
     */
    public function woocommerce_payment_complete_order_status($status, $order)
    {

        $order = wc_get_order($order);

        if ($order) {

            if ($order->get_payment_method() == PesePay_Helper::get_gateway_id()) {

                $gateway = PesePay_Helper::get_gateway_instance();

                if (is_object($gateway)) {
                    $_status = $gateway->get_option("status");

                    #Only change if specifically set to be changed
                    if ($_status) {
                        $status = $_status;
                    }
                }
            }
        }

        return $status;
    }

    /**
     * Append a user-friendly Pesepay reason when no payment methods are available.
     *
     * @since 1.2.10
     * @param string $message
     * @return string
     */
    public function woocommerce_no_available_payment_methods_message($message)
    {
        $reason = $this->get_unavailable_reason();

        if (!$reason) {
            return $message;
        }

        $pesepay_message = __('Pesepay is currently unavailable for this order. Please use another payment method or contact us for help.', $this->plugin_name);

        if (!is_string($message) || trim($message) === '') {
            return $pesepay_message;
        }

        return $message . ' ' . $pesepay_message;
    }

    /**
     * Determine why Pesepay cannot be used on checkout.
     *
     * @since 1.2.10
     * @return string|false
     */
    private function get_unavailable_reason()
    {
        $gateway = PesePay_Helper::get_gateway_instance();

        if (!is_object($gateway)) {
            return false;
        }

        if ($gateway->get_option('enabled') !== 'yes') {
            return false;
        }

        if ($gateway->get_option('test_mode') === 'yes') {
            $integration_key = (string) $gateway->get_option('test_integration_key', '');
            $encryption_key = (string) $gateway->get_option('test_encryption_key', '');
        } else {
            $integration_key = (string) $gateway->get_option('integration_key', '');
            $encryption_key = (string) $gateway->get_option('encryption_key', '');
        }

        if ($integration_key === '') {
            return 'missing_integration_key';
        }

        if ($encryption_key === '') {
            return 'missing_encryption_key';
        }

        $currencies = wp_parse_list($gateway->get_option('currencies', array()));
        if (!in_array(get_woocommerce_currency(), $currencies, true)) {
            return 'currency_mismatch';
        }

        return false;
    }
}
