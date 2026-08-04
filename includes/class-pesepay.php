<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://pesepay.com
 * @since      1.0.0
 *
 * @package    Pesepay
 * @subpackage Pesepay/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Pesepay
 * @subpackage Pesepay/includes
 * @author     Pesepay <digital@pesepay.com>
 */
class Pesepay
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Pesepay_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		$this->version = PESEPAY_VERSION;
		$this->plugin_name = PESEPAY_SLUG;

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Pesepay_Loader. Orchestrates the hooks of the plugin.
	 * - Pesepay_i18n. Defines internationalization functionality.
	 * - Pesepay_Admin. Defines all hooks for the admin area.
	 * - Pesepay_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
	{

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pesepay-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pesepay-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-pesepay-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-pesepay-public.php';

		/**
		 * Plugin functions file
		 */
		require_once plugin_dir_path(__DIR__) . "includes/class-pesepay-functions.php";

		/**
		 * Load Composer packages
		 */
		require_once plugin_dir_path(__DIR__) . 'vendor/autoload.php';

		$this->loader = new Pesepay_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Pesepay_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale()
	{

		$plugin_i18n = new Pesepay_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{

		$plugin_admin = new Pesepay_Admin($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('admin_notices', $plugin_admin, 'admin_notices');

		$this->loader->add_action('woocommerce_init', $plugin_admin, 'woocommerce_init');
		$this->loader->add_action('woocommerce_payment_gateways', $plugin_admin, 'woocommerce_payment_gateways');

		$this->loader->add_filter('plugin_action_links_' . PESEPAY_NAME, $plugin_admin, 'plugin_links');

		$this->loader->add_filter('woocommerce_currencies', $plugin_admin, 'woocommerce_currencies', 20);
		$this->loader->add_filter('woocommerce_currency_symbol', $plugin_admin, 'woocommerce_currency_symbol', 20, 2);
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{

		$plugin_public = new Pesepay_Public($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('woocommerce_api_' . $this->get_plugin_name(), $plugin_public, 'woocommerce_api_wc_gateway');$this->loader->add_action('before_woocommerce_init', $this, 'pesepay_cart_checkout');
        $this->loader->add_action('woocommerce_blocks_loaded', $this, 'register_pesepay_payment_type_for_block');
		$this->loader->add_filter('woocommerce_payment_complete_order_status', $plugin_public, 'woocommerce_payment_complete_order_status', 9, 2);
		$this->loader->add_filter('woocommerce_no_available_payment_methods_message', $plugin_public, 'woocommerce_no_available_payment_methods_message', 10, 1);
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Pesepay_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}

	/**
	 * Woocommerce Block compatibility.
	 *
	 * @since 1.2.5
	 * @author Marshall Chikari
	 */
	public function pesepay_cart_checkout() {

		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				defined( 'PESEPAY_PLUGIN_FILE' ) ? PESEPAY_PLUGIN_FILE : __FILE__,
				true
			);
		}
	}

	/**
	 * Block checkout compatibility.
	 *
	 * @since 1.2.5
	 * @author Marshall Chikari
	 */
	public function register_pesepay_payment_type_for_block() {

		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {

			require_once __DIR__ . '/classes/wc-pesepay-block.php';

			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Pesepay_Block() );
				}
			);
		}
	}
}
