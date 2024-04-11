<?php
/**
 * WooCommerce class for handling WooCommerce features.
 *
 * @package Woocommerce_Postnet_Delivery
 */

namespace Woocommerce_Postnet_Delivery;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * WooCommerce Class.
 */
class WooCommerce extends Component {

	/**
	 * Add hooks.
	 */
	public function setup_hooks() {

		add_action( 'before_woocommerce_init', [ $this, 'declare_compatibility' ] );
	}

	/**
	 * Declare compatibility.
	 */
	public function declare_compatibility() {

		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', WCPD_CORE );
		}
	}

}
