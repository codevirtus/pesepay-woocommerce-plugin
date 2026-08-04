<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
/**
 * Class for handling Pesepay Payment block checkout support.
 *
 * @since 1.2.5
 * @author Marshall Chikari
 */
final class WC_Pesepay_Block extends AbstractPaymentMethodType {

	/**
	 * The Pesepay Payment gateway instance.
	 *
	 * @var WC_Pesepay_Gateway
	 */
	private $gateway;

	/**
	 * The name of the payment method.
	 *
	 * @var string
	 */
	protected $name = 'wc_gateway_pesepay';

	/**
	 * Initializes the Pesepay Payment block.
	 *
	 * @since 1.2.5
     * @author Marshall Chikari
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', [] );
		$this->gateway  = new WC_Pesepay_Gateway();
	}

	/**
	 * Checks if the payment method is active.
	 *
	 * @since 1.2.5
     * @author Marshall Chikari
	 *
	 * @return bool True if the payment method is active, false otherwise.
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Retrieves the script handles for the payment method.
	 *
	 * @since 1.2.5
     * @author Marshall Chikari
	 *
	 * @return array The script handles for the payment method.
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'wc-pesepay-payment-blocks-integration',
			plugins_url( 'includes/classes/block/checkout.js', PESEPAY_PLUGIN_FILE),
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			null,
			true
		);

		$pesepay_settings = get_option( 'woocommerce_' . $this->name . '_settings', [] );

		wp_localize_script(
			'wc-pesepay-payment-blocks-integration',
			'PesepayBlockData',
			array(
				'settings' => $pesepay_settings,
				'badge' => plugin_dir_url( PESEPAY_PLUGIN_FILE ) . 'public/img/badge.png',
				'icon'    => plugin_dir_url( PESEPAY_PLUGIN_FILE ) . 'public/img/logo.svg',
			)
		);

		return array( 'wc-pesepay-payment-blocks-integration' );
	}

	/**
	 * Retrieves the payment method data.
	 *
	 * @since 1.2.5
     * @author Marshall Chikari
	 *
	 * @return array The payment method data, including title and description.
	 */
	public function get_payment_method_data() {
		$icon_url = plugins_url('public/img/logo.svg', PESEPAY_PLUGIN_FILE);

		return [
			'title'       => $this->get_setting('title'),
			'description' => $this->get_setting('description'),
		    'pluginUrl' => plugin_dir_url( PESEPAY_PLUGIN_FILE ),
			'currency'    => get_woocommerce_currency(),
			'supports'    => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
		];
	}

}