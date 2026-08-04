<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://pesepay.com
 * @since      1.0.0
 *
 * @package    Pesepay
 * @subpackage Pesepay/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Pesepay
 * @subpackage Pesepay/admin
 * @author     Pesepay <digital@pesepay.com>
 */
class Pesepay_Admin
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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Check if we should show notice that open ssl is required
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return void
	 */
	public function admin_notices()
	{
		if (!function_exists("openssl_encrypt")) {
			include plugin_dir_path(__FILE__) . "partials/pesepay-admin-display.php";
		}

		if (!function_exists('WC') || !current_user_can('manage_woocommerce')) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || strpos((string) $screen->id, 'woocommerce') === false) {
			return;
		}

		$gateway = PesePay_Helper::get_gateway_instance();
		if (!is_object($gateway) || $gateway->get_option('enabled') !== 'yes') {
			return;
		}

		$issues = array();
		$integration_key = (string) $gateway->get_option('integration_key', '');
		$encryption_key = (string) $gateway->get_option('encryption_key', '');

		if ($integration_key === '') {
			$issues[] = __('Integration Key is missing.', PESEPAY_SLUG);
		}

		if ($encryption_key === '') {
			$issues[] = __('Encryption Key is missing.', PESEPAY_SLUG);
		}

		$currencies = wp_parse_list($gateway->get_option('currencies', array()));
		if (!in_array(get_woocommerce_currency(), $currencies, true)) {
			$issues[] = sprintf(
				__('Store currency (%1$s) is not included in Pesepay currencies (%2$s).', PESEPAY_SLUG),
				get_woocommerce_currency(),
				empty($currencies) ? __('none selected', PESEPAY_SLUG) : implode(', ', $currencies)
			);
		}

		if (empty($issues)) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Pesepay setup issue:', PESEPAY_SLUG) . '</strong> ' . esc_html(implode(' ', $issues)) . '</p></div>';
	}

	/**
	 * Load custom payment gateway class
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return void
	 */
	public function woocommerce_init()
	{
		include plugin_dir_path(__DIR__) . "includes/classes/class-pesepay-gateway.php";
	}

	/**
	 * Load our custom payment gateway class
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @param array $gateways
	 * @return array
	 */
	function woocommerce_payment_gateways($methods)
	{

		$methods[] = 'WC_Pesepay_Gateway';
		return $methods;
	}

	/**
	 * Add quick link to plugin settings page
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @param array $links
	 * @return array
	 */
	public function plugin_links($links)
	{
		$links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . PesePay_Helper::get_gateway_id()) . '">' . __("Settings", $this->plugin_name) . '</a>';
		return $links;
	}

	/**
	 * Add ZWL currency if not already added
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @param array|string $currencies
	 * @return array|string
	 */
	public function woocommerce_currencies($currencies)
	{

		if (!isset($currencies["ZWL"])) {
			$_currencies = PesePay_Helper::get_supported_currencies();

			foreach ($_currencies as $_currency) {
				if (!isset($currencies[$_currency["code"]])) {
					$currencies[$_currency["code"]] = $_currency["name"];
				}
			}
		}

		return $currencies;
	}

	/**
	 * Set currency symbol if not already setup
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @param string $symbol
	 * @param string $currency
	 * @return string
	 */
	public function woocommerce_currency_symbol($symbol, $currency)
	{

		if (strlen($symbol) == 0) {
			switch ($currency) {
				case 'ZWL':
					$symbol = 'ZWL';
					break;
			}
		}

		return $symbol;
	}
}
