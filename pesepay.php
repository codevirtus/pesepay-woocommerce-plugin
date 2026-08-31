<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://pesepay.com
 * @since             1.0.0
 * @package           Pesepay
 *
 * @wordpress-plugin
 * Plugin Name:       Pesepay
 * Plugin URI:        https://pesepay.com
 * Description:       Robust & Secure online payments solution for Africa.
 * Version:           1.3.1
 * Author:            Pesepay
 * Author URI:        https://pesepay.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pesepay
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('PESEPAY_VERSION', '1.3.1');

/**
 * Plugin unique short name.
 * Used in translations as well as in prefixes
 */
define('PESEPAY_SLUG', 'pesepay');

/**
 * Plugin unique name.
 * Used by wordpress to identify plugin
 */
define('PESEPAY_NAME', plugin_basename(__FILE__));

if ( ! defined( 'PESEPAY_PLUGIN_FILE' ) ) {
    define( 'PESEPAY_PLUGIN_FILE', __FILE__ );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-pesepay-activator.php
 */
function activate_pesepay()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-pesepay-activator.php';
	Pesepay_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-pesepay-deactivator.php
 */
function deactivate_pesepay()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-pesepay-deactivator.php';
	Pesepay_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_pesepay');
register_deactivation_hook(__FILE__, 'deactivate_pesepay');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-pesepay.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_pesepay()
{

	$plugin = new Pesepay();
	$plugin->run();
}
run_pesepay();
