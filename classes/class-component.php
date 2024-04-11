<?php
/**
 * Component abstract class.
 *
 * @package Woocommerce_Postnet_Delivery
 */

namespace Woocommerce_Postnet_Delivery;

/**
 * WooCommerce Class.
 */
abstract class Component implements Hooks {

	/**
	 * Holds the main plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin The main plugin instance.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin = $plugin;
	}

}
