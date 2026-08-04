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
     * Get the base api url
     *
     * Allows building urls on top of the base by appending given path
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
     * Get currencies supported by pesepay
     *
     * Will request directly from pesepay and cache for 1/4 Day
     *
     * @version 1.0.0
     * @since 1.0.0
     * @return array|string
     */
    public static    function get_supported_currencies()
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

        $url = "v1/payments/initiate";

        $data = array(
            "amountDetails" => array(
                "amount" => $amount,
                "currencyCode" => $currency
            ),
            "reasonForPayment" => $reason,
            "resultUrl" => $links["resultUrl"],
            "returnUrl" => $links["returnUrl"]
        );

        return self::remote_request($url, $data);
    }

    /**
     * Check the status of a transaction
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param string $reference
     * @return array|bool
     */
    public static function remote_check_transaction($reference)
    {

        $url =  "v1/payments/check-payment";

        $data = array(
            "referenceNumber" => $reference
        );

        return self::remote_request($url, $data, "GET");
    }

    /**
     * Perform remote request to pesepay
     *
     * @since 1.0.0
     * @version 1.0.0
     * @param string $path
     * @param string $payload
     * @return array|bool
     */
    private static function remote_request($path, $payload = "", $method = "POST")
    {

        $gateway = self::get_gateway_instance();

        if (is_object($gateway)) {

            $headers = array(
                'Authorization' => $gateway->get_option('integration_key')
            );

            if (!str_starts_with($path, "http")) {
                $url = self::get_remote_base_url($path);
            }

            /**
             * Post takes different params from get
             */
            $response = false;
            switch (strtoupper($method)) {
                case "POST":
                    if (is_array($payload)) {
                        $payload = json_encode($payload);
                    }

                    $data = self::content_encrypt($gateway->get_option('encryption_key'), $payload);
                    $payload = array("payload" => $data);

                    $response =    wp_safe_remote_post($url, array(
                        "body" => json_encode($payload),
                        "headers" => array_merge($headers, array(
                            'Content-Type' => 'application/json'
                        ))
                    ));
                    break;
                case "GET":
                    $url = add_query_arg($payload, $url);

                    $response =    wp_safe_remote_get($url, array(
                        "headers" => $headers
                    ));
                    break;
            }

            if ($response && !is_wp_error($response)  && wp_remote_retrieve_response_code($response) == 200) {

                $payload = wp_remote_retrieve_body($response);

                if ($payload) {

                    $payload = json_decode($payload, true);

                    if (isset($payload["payload"])) {

                        $data = self::content_decrypt($gateway->get_option('encryption_key'), $payload["payload"]);
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
