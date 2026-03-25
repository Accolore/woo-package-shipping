<?php
/**
 * Plugin Name:       Woo Package Shipping
 * Plugin URI:        https://example.com/woo-package-shipping
 * Description:       WooCommerce shipping method with per-package cost and duties, restricted to a single configurable country.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Lorenzo Accorinti
 * Author URI:        https://github.com/Accolore
 * License:           GPL-2.0-or-later
 * Text Domain:       woo-package-shipping
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WOO_PKG_SHIPPING_VERSION', '1.0.0' );
define( 'WOO_PKG_SHIPPING_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOO_PKG_SHIPPING_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce HPOS (High-Performance Order Storage).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Bootstrap the plugin after all plugins have loaded so WooCommerce classes are available.
 */
add_action( 'plugins_loaded', 'woo_pkg_shipping_init' );

function woo_pkg_shipping_init(): void {
	// Bail early if WooCommerce is not active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woo_pkg_shipping_missing_wc_notice' );
		return;
	}

	require_once WOO_PKG_SHIPPING_DIR . 'includes/class-package-shipping-calculator.php';
	require_once WOO_PKG_SHIPPING_DIR . 'includes/class-wc-shipping-package-duties.php';

	// Register the shipping method with WooCommerce.
	add_filter( 'woocommerce_shipping_methods', 'woo_pkg_shipping_register_method' );
}

/**
 * Add our shipping method class to the WooCommerce list of available methods.
 *
 * @param array $methods Existing shipping method classes.
 * @return array
 */
function woo_pkg_shipping_register_method( array $methods ): array {
	$methods['woo_package_duties'] = 'WC_Shipping_Package_Duties';
	return $methods;
}

/**
 * Admin notice shown when WooCommerce is not active.
 */
function woo_pkg_shipping_missing_wc_notice(): void {
	echo '<div class="notice notice-error"><p>'
		. esc_html__(
			'Woo Package Shipping requires WooCommerce to be installed and active.',
			'woo-package-shipping'
		)
		. '</p></div>';
}
