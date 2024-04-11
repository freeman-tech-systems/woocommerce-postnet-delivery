<?php
/**
 * Interface for setting up hooks.
 *
 * @package woocommerce_postnet_delivery
 */

namespace Woocommerce_Postnet_Delivery;

/**
 * Hooks.
 */
interface Hooks {

	/**
	 * Add hooks to WordPress.
	 */
	public function setup_hooks();
}
