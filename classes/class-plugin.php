<?php
/**
 * Core class for WooCommerce PostNet Delivery.
 *
 * @package Woocommerce_Postnet_Delivery
 */

namespace Woocommerce_Postnet_Delivery;

/**
 * Plugin Class.
 */
class Plugin implements Hooks {

	/**
	 * Holds the version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Holds the plugins components.
	 *
	 * @var Settings[]|WooCommerce[]|Component[]
	 */
	protected $components;

	/**
	 * Hold the record of the plugins current version for upgrade.
	 *
	 * @var string
	 */
	const VERSION_KEY = '_woocommerce_postnet_delivery_version';

	/**
	 * Initiate the woocommerce_postnet_delivery object.
	 */
	public function __construct() {

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin        = get_file_data( WCPD_CORE, array( 'Version' ), 'plugin' );
		$this->version = array_shift( $plugin );

		// Setup components.
		$this->setup_components();
		// Start hooks.
		$this->setup_hooks();
	}

	/**
	 * Setup the plugins components.
	 */
	protected function setup_components() {

		$this->components['woocommerce'] = new WooCommerce( $this );
		$this->components['settings']    = new Settings( $this );
	}

	/**
	 * Setup and register WordPress hooks.
	 */
	public function setup_hooks() {

		// Call components with setup hooks.
		foreach ( $this->components as $component ) {
			if ( $component instanceof Hooks ) {
				$component->setup_hooks();
			}
		}

		add_action( 'init', array( $this, 'woocommerce_postnet_delivery_init' ), PHP_INT_MAX ); // Always the last thing to init.
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Get the plugin version
	 */
	public function version() {

		return $this->version;
	}

	/**
	 * Check woocommerce_postnet_delivery version to allow 3rd party implementations to update or upgrade.
	 */
	protected function check_version() {

		$previous_version = get_option( self::VERSION_KEY, 0.0 );
		$new_version      = $this->version();
		if ( version_compare( $previous_version, $new_version, '<' ) ) {
			// Allow for updating.
			do_action( "_woocommerce_postnet_delivery_version_upgrade", $previous_version, $new_version );
			// Update version.
			update_option( self::VERSION_KEY, $new_version, true );
		}
	}

	/**
	 * Initialise woocommerce_postnet_delivery.
	 */
	public function woocommerce_postnet_delivery_init() {

		// Check version.
		$this->check_version();

		// @todo Plugin init code.
		/**
		 * Init the settings system
		 *
		 * @param Plugin ${slug} The core object.
		 */
		do_action( 'woocommerce_postnet_delivery_init', $this );
	}

	/**
	 * Hook into admin_init.
	 */
	public function admin_init() {
	}

	/**
	 * Hook into the admin_menu.
	 */
	public function admin_menu() {
	}

	/**
	 * Get a component.
	 *
	 * @param string $slug The component slug to get.
	 *
	 * @return Component|null
	 */
	public function get_component( string $slug ) {

		return $this->components[ $slug ] ?? null;
	}

	/**
	 * Get the instance of the class.
	 *
	 * @return self
	 */
	public static function get_instance() {

		static $instance;
		if ( is_null( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}
}
