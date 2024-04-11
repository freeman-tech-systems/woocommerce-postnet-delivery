<?php
/**
 * Plugin Name: WooCommerce PostNet Delivery
 * Plugin URI: https://github.com/freeman-tech-systems/woocommerce-postnet-delivery
 * Description: Adds PostNet delivery options to WooCommerce checkout.
 * Version: 1.0
 * Author: Freeman Tech Systems
 * Author URI: https://github.com/freeman-tech-systems
 * License: GPL2
 * WC requires at least: 3.0
 * WC tested up to: 8.2.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Constants.
define( 'WCPD_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCPD_URL', plugin_dir_url( __FILE__ ) );
const WCPD_CORE = __FILE__;

if ( ! version_compare( PHP_VERSION, '7.4', '>=' ) ) {
	if ( is_admin() ) {
		add_action( 'admin_notices', 'woocommerce_postnet_delivery_php_ver' );
	}
} else {
	// Includes Woocommerce_Postnet_Delivery and starts instance.
	include_once WCPD_PATH . 'bootstrap.php';
}

/**
 * PHP Compatibility error message.
 *
 * @return void
 */
function woocommerce_postnet_delivery_php_ver() {

	$message = __( 'WooCommerce PostNet Delivery requires PHP version 7.4 or later. We strongly recommend PHP 7.4 or later for security and performance reasons.', 'woocommerce-postnet-delivery' );
	echo sprintf( '<div id="woocommerce_postnet_delivery_error" class="error notice notice-error"><p>%s</p></div>', esc_html( $message ) );
}
