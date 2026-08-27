<?php

/**
 * The file that defines the helper class
 *
 * A class definition that includes attributes and functions used across the
 * plugin.
 *
 * @link       https://pesepay.com
 * @since      1.0.0
 *
 * @package    Pesepay
 * @subpackage Pesepay/includes
 */

/**
 * The core helper functions class.
 *
 * This is used to define helper functions used across the plugin..
 *
 * @since      1.0.0
 * @package    Pesepay
 * @subpackage Pesepay/includes/classes
 * @author     Pesepay <digital@pesepay.com>
 */

class PesePay_Helper
{
    /**
     * Fallback currencies used when cached/live list is unavailable.
     *
     * @since 1.2.10
     * @return array
     */
    private static function get_fallback_currencies()
    {
        return array(
            array(
                "code" => "USD",
                "name" => "United States Dollar"
            ),
            array(
                "code" => "ZWL",
                "name" => "Zimbabwe Dollar"
            ),
            array(
                "code" => "ZiG",
                "name" => "Zimbabwe Gold"
            )
        );
    }

    /**
     * Get the live API base URL.
     *
     * Allows building URLs on top of the base by appending a given path.
     *
     * @version 1.0.0
     * @since 1.0.0
     * @param string $path
     * @return string
     */
    public static function get_remote_base_url($path = "")
    {
        return "https://api.pesepay.com/api/payments-engine/" . ltrim($path, "\\/");
    }

    /**
     * Get the sandbox API base URL.
     *
     * @since 1.3.0
     * @param string $path
     * @return string
     */
    public static function get_sandbox_base_url($path = "")
    {
        return "https://api.test.sandbox.pesepay.com/payments-engine/" . ltrim($path, "\\/");
    }

    /**
     * Return credentials and endpoints for the active environment.
     *
     * When $mode is null the global test_mode gateway setting determines the
     * environment. Pass 'live' or 'test' explicitly to override (used when
     * re-checking a payment using the environment saved at initiation time).
     *
     * Returns false when:
     *   - the gateway instance is not available, or
     *   - test mode is requested but either test key is missing.
     *
     * Never mixes live and test credentials.
     *
     * @since 1.3.0
     * @param string|null          $mode             'live', 'test', or null (auto-detect).
     * @param WC_Pesepay_Gateway|object|null $gateway Optional gateway instance (used in tests).
     * @return array|false
     */
    public static function get_environment_config($mode = null, $gateway = null)
    {
        if ($gateway === null) {
            $gateway = self::get_gateway_instance();
        }

        if (!is_object($gateway)) {
            return false;
        }

        if ($mode === null) {
            $mode = ($gateway->get_option('test_mode') === 'yes') ? 'test' : 'live';
        }

        if ($mode === 'test') {
            $integration_key = (string) $gateway->get_option('test_integration_key', '');
            $encryption_key  = (string) $gateway->get_option('test_encryption_key', '');

            if ($integration_key === '' || $encryption_key === '') {
                return false;
            }

            return array(
                'mode'            => 'test',
                'integration_key' => $integration_key,
                'encryption_key'  => $encryption_key,
                'initiate_url'    => self::get_sandbox_base_url('v1/payments/initiate'),
                'check_url'       => self::get_sandbox_base_url('v1/payments/check-payment'),
            );
        }

        // Live mode — always use live credentials and endpoints.
        return array(
            'mode'            => 'live',
            'integration_key' => (string) $gateway->get_option('integration_key', ''),
            'encryption_key'  => (string) $gateway->get_option('encryption_key', ''),
            'initiate_url'    => self::get_remote_base_url('v1/payments/initiate'),
            'check_url'       => self::get_remote_base_url('v1/payments/check-payment'),
        );
    }

    /**
     * Get currencies supported by pesepay
     *
     * Will request directly from pesepay and cache for 1/4 Day
     *
     * @version 1.0.0
     * @since 1.0.0
     * @return array|string
     */
    public static function get_supported_currencies()
    {

        $currencies = get_transient(PESEPAY_SLUG . "-currencies");

        if (is_array($currencies) && !empty($currencies)) {
            return $currencies;
        }

        /**
         * Do not make inline remote requests during normal page loads.
         * This keeps storefront/admin responsive even when Pesepay is down.
         */
        return self::get_fallback_currencies();
    }

    /**
     * Get the list of supported currency codes
     *
     * @since 1.0.0
     * @version 1.0.0
     * @return array|string
     */
    public static function get_supported_currency_codes()
    {
        return wp_list_pluck(self::get_supported_currencies(), "code");
    }

    /**
     * Get our initialized payment gateway class
     *
     * @since 1.0.0
     * @version 1.0.0
     * @return WC_Pesepay_Gateway|false
     */
    public static function get_gateway_instance()
    {

        if (function_exists("WC") && isset(WC()->payment_gateways->payment_gateways()[self::get_gateway_id()])) {
            return  WC()->payment_gateways->payment_gateways()[self::get_gateway_id()];
        }
        return false;
    }

    /**
     * Get the unique id of this payment gateway
     *
     * @version 1.0.0
     * @since 1.0.0
     * @return string
     */
    public static function get_gateway_id()
    {
        return apply_filters(PESEPAY_SLUG . "-gateway-id", "wc_gateway_pesepay");
    }

    /**
     * Log debug message
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param string $message
     * @return void
     */
    public static function log($message)
    {

        $gateway = self::get_gateway_instance();

        if (is_object($gateway) && $gateway->get_option("debug") == "yes") {
            $log = new WC_Logger();
            $log->log('debug', $message);
        }
    }

    /**
     * Pesepay remote interaction
     */

    /**
     * Length of encryption key
     *
     * @since 1.0.0
     * @version 1.0.0
     * @var array|integer
     */
    private static $ENCRYPTION_KEY_LENGTH = array(16, 32);

    /**
     * The algorithm to use for the encryption
     *
     * @since 1.0.0
     * @version 1.0.0
     * @var string
     */
    private static $ENCRYPTION_ALGORITHM = "aes-256-cbc";

    /**
     * Initiate a transaction on pesepay
     *
     * @version 1.0.0
     * @since 1.0.0
     * @param float $amount
     * @param string $currency
     * @param string $reason
     * @param array $links
     * @return array|bool
     */
    public static function remote_init_transaction($amount, $currency, $reason, $links)
    {
        $config = self::get_environment_config();

        if (!$config) {
            return false;
        }

        $data = array(
            "amountDetails" => array(
                "amount" => $amount,
                "currencyCode" => $currency
            ),
            "reasonForPayment" => $reason,
            "resultUrl" => $links["resultUrl"],
            "returnUrl" => $links["returnUrl"]
        );

        self::log('[' . $config['mode'] . '] Initiating transaction');

        return self::remote_request($config['initiate_url'], $data, "POST", $config);
    }

    /**
     * Check the status of a transaction
     *
     * Pass $mode = 'live' or 'test' to use the environment recorded at payment
     * initiation time (stored in order meta) so that sandbox transactions are
     * never checked against the live endpoint, even if the global setting
     * has since been changed.
     *
     * @since 1.0.0
     * @version 1.3.0
     * @param string $reference
     * @param string|null $mode 'live', 'test', or null (use current global setting).
     * @return array|bool
     */
    public static function remote_check_transaction($reference, $mode = null)
    {
        $config = self::get_environment_config($mode);

        if (!$config) {
            return false;
        }

        $data = array(
            "referenceNumber" => $reference
        );

        self::log('[' . $config['mode'] . '] Checking transaction status');

        return self::remote_request($config['check_url'], $data, "GET", $config);
    }

    /**
     * Perform remote request to pesepay
     *
     * @since 1.0.0
     * @version 1.3.0
     * @param string $url     Full endpoint URL.
     * @param array|string $payload
     * @param string $method  HTTP method.
     * @param array|null $config Environment config from get_environment_config().
     * @return array|bool
     */
    private static function remote_request($url, $payload = "", $method = "POST", $config = null)
    {
        if ($config === null) {
            $config = self::get_environment_config();
        }

        if (!$config) {
            return false;
        }

        $headers = array(
            'Authorization' => $config['integration_key']
        );

        /**
         * Post takes different params from get
         */
        $response = false;
        switch (strtoupper($method)) {
            case "POST":
                if (is_array($payload)) {
                    $payload = json_encode($payload);
                }

                $data = self::content_encrypt($config['encryption_key'], $payload);
                $payload = array("payload" => $data);

                $response = wp_safe_remote_post($url, array(
                    "body" => json_encode($payload),
                    "headers" => array_merge($headers, array(
                        'Content-Type' => 'application/json'
                    ))
                ));
                break;
            case "GET":
                $url = add_query_arg($payload, $url);

                $response = wp_safe_remote_get($url, array(
                    "headers" => $headers
                ));
                break;
        }

        if ($response && !is_wp_error($response)  && wp_remote_retrieve_response_code($response) == 200) {

            $payload = wp_remote_retrieve_body($response);

            if ($payload) {

                $payload = json_decode($payload, true);

                if (isset($payload["payload"])) {

                    $data = self::content_decrypt($config['encryption_key'], $payload["payload"]);
                    $success = true;
                } else {
                    $data =  $payload["message"];
                    $success = false;
                }
                return array(
                    "success" => $success,
                    "data" => json_decode($data, true)
                );
            }
        }

        return false;
    }

    /**
     * Decrypt content with given key
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param string $key
     * @param string $content
     * @return string
     */
    private static function content_decrypt($key, $content = "")
    {

        $iv = self::encryption_key_get_iv($key);

        return openssl_decrypt($content, self::$ENCRYPTION_ALGORITHM, $key, 0, $iv);
    }

    /**
     * Encrypt content with given key
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param string $key
     * @param string $content
     * @return string
     */
    private static function content_encrypt($key, $content = "")
    {

        $iv = self::encryption_key_get_iv($key);

        return openssl_encrypt($content, self::$ENCRYPTION_ALGORITHM, $key, 0, $iv);
    }

    /**
     * Get initialisation vector for key
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param string $key
     * @return string
     */
    private static function encryption_key_get_iv($key)
    {
        /**
         * Use symphony php 8.0 to access first element of array
         */
        return substr($key, 0, self::$ENCRYPTION_KEY_LENGTH[array_key_first(self::$ENCRYPTION_KEY_LENGTH)]);
    }

    /**
     * Get the expected length of an encryption key
     *
     * @since 1.0.0
     * @version 1.0.0
     * @return int
     */
    public static function encryption_key_length()
    {
        /**
         * Use symphony php 8.0 to access last element of array
         */
        return self::$ENCRYPTION_KEY_LENGTH[array_key_last(self::$ENCRYPTION_KEY_LENGTH)];
    }

    /**
     * Get the order meta prefix
     *
     * Prepends this plugins slug
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param string $key
     * @return string
     */
    public static function meta_key_prefix($key = "")
    {
        return "_" . PESEPAY_SLUG . $key;
    }
}
