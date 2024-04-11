<?php
/**
 * WooCommerce PostNet Delivery Bootstrap.
 *
 * @package   Woocommerce_Postnet_Delivery
 */

namespace Woocommerce_Postnet_Delivery;

require_once WCPD_PATH . 'autoload.php';

add_action( 'plugins_loaded', 'Woocommerce_Postnet_Delivery\\Plugin::get_instance' );
