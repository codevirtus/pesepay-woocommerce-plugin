=== Pesepay ===
Contributors: Pesepay
Donate link: https://pesepay.com
Tags: Pesepay, payment, woocommerce, zimbabwe, gateway
Requires at least: 4.0.0
Tested up to: 7.0
Requires PHP: 7.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Pesepay plugin allow you to make safe and secure way to accept payments online

== Description ==

The Pesepay extension helps merchants to collect payments via the Pesepay payment gateway without any line of code ,our developers have done everything for you. You only need integration and encryption keys and you are good to start receiving  payments. Follow the link below to Sign up on [Pesepay](https://dashboard.pesepay.com/#/auth/register) and get the keys.

== Installation ==

= Automatic installation =

Automatic installation is the easiest option -- WordPress will handle the file transfer, and you won’t need to leave your web browser. To do an automatic install of Pesepay, log in to your WordPress dashboard, navigate to the Plugins menu, and click “Add New.”
 
In the search field type “Pesepay”, then click “Search Plugins.” Once you’ve found it,  you can view details about it such as the point release, rating, and description. Most importantly of course, you can install it by! Clicking “Install Now,” and WordPress will take it from there.

= Manual installation =

1. Upload `/Pesepay/` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= What is Pesepay? =

Pesepay is a payments processing gateway. We handle different transactions for a variety of reasons. From donations campaigns, to online merchants, to events, Pesepay is a great platform to start accepting payments online.

= How do I sign up? =

If you are signing up for your own business, you simply need to click on the Sign Up button on https://pesepay.com. Enter the required details to start the sign-up process. Please note, applications are found after logging in to your account with your company profile, not on the home page of the Pesepay website

== Screenshots ==
1. Checkout Page.
2. Plugin Setup Page.

== Changelog ==

= 1.3.0 =
* Added sandbox / test mode with dedicated test credentials and Pesepay sandbox endpoints.
* Improved checkout messaging when Pesepay is unavailable.
* Added admin diagnostics for Pesepay setup issues.
* Prevented inline currency API calls during page loads to avoid slowdowns when Pesepay is unreachable.

= 1.2.8 =
* Checks for payment status in the background if user does not click continue on pesepay page
* Add support for woocommerce checkout blocks.

= 1.0.0 =
* Initial Release.

== Upgrade Notice ==

= 1.3.0 =
* Adds sandbox/test mode support. Existing live installations are unaffected — test mode is disabled by default.
* Improves storefront/admin resilience by avoiding inline currency API calls during normal page loads.

= 1.2.9 =
* Add support for latest woocommerce checkout blocks.

= 1.0.0 =
* Initial Release.
