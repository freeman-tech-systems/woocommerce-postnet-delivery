<?php
/**
 * Settings class for Woocommerce_Postnet_Delivery.
 *
 * @package Woocommerce_Postnet_Delivery
 */

namespace Woocommerce_Postnet_Delivery;

/**
 * Settings Class.
 */
class Settings extends Component {

	/**
	 * Holds the menu slug.
	 */
	const MENU_SLUG = 'woocommerce_postnet_delivery';

	/**
	 * Holds the options slug.
	 */
	const OPTIONS_SLUG = 'woocommerce_postnet_delivery_options';

	/**
	 * Holds the transient slug.
	 */
	const TRANS_SLUG = 'woocommerce_postnet_delivery_stores';

	/**
	 * Holds the Stores URL.
	 */
	const STORES_URL = 'https://www.postnet.co.za/cart_store-json_list/';

	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {

		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * @param $hook
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {

		// Check if we are on the settings page of our plugin
		if ( $hook === 'woocommerce_page_' . self::MENU_SLUG ) {
			$asset = include WCPD_PATH . 'build/main.asset.php';
			wp_enqueue_script( 'woocommerce-postnet-delivery-options-js', WCPD_URL . 'build/main.js', $asset['dependencies'], $asset['version'], true );
		}
	}

	/**
	 * Register the settings page.
	 */
	public function add_settings_page() {

		add_submenu_page(
			'woocommerce', // Parent slug
			__( 'PostNet Delivery', 'woocommerce_postnet_delivery' ), // Page title
			__( 'PostNet Delivery', 'woocommerce_postnet_delivery' ), // Menu title
			'manage_options', // Capability
			self::MENU_SLUG, // Menu slug
			[ $this, 'render_options_page' ]
		);
	}

	/**
	 * Render the options page.
	 */
	public function render_options_page() {

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = $this->get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				// Output security fields for the registered setting "woocommerce_postnet_delivery"
				settings_fields( 'woocommerce_postnet_delivery' );
				// Output setting sections and their fields
				do_settings_sections( 'woocommerce_postnet_delivery' );

				// Add your settings fields here
				?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="service_type">Service Type</label></th>
						<td>
							<fieldset>
								<?php
								$service_types = $this->get_delivery_service_types();
								foreach ( $service_types as $service_key => $service_name ) {
									?>
									<label>
										<input type="checkbox" name="woocommerce_postnet_delivery_options[service_type][]" value="<?= $service_key ?>" <?php checked( in_array( $service_key,
										                                                                                                                                        (array) $options['service_type'] ) ); ?> />
										<?= $service_name ?>
									</label><br/>
									<?php
								}
								?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="postnet_to_postnet_fee">PostNet to PostNet Delivery Fee</label></th>
						<td>
							<input type="number" name="woocommerce_postnet_delivery_options[postnet_to_postnet_fee]" value="<?php echo isset( $options['postnet_to_postnet_fee'] ) ? esc_attr( $options['postnet_to_postnet_fee'] ) : ''; ?>"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="order_amount_threshold">Order Amount Threshold</label></th>
						<td>
							<input type="number" name="woocommerce_postnet_delivery_options[order_amount_threshold]" value="<?php echo isset( $options['order_amount_threshold'] ) ? esc_attr( $options['order_amount_threshold'] ) : ''; ?>"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="collection_type">Collection Type</label></th>
						<td>
							<select name="woocommerce_postnet_delivery_options[collection_type]">
								<option value="always_collect" <?php selected( $options['collection_type'], 'always_collect' ); ?>>Always Collect</option>
								<option value="always_deliver" <?php selected( $options['collection_type'], 'always_deliver' ); ?>>Always Deliver</option>
								<option value="service_based" <?php selected( $options['collection_type'], 'service_based' ); ?>>Service Based</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="postnet_store">PostNet Store</label></th>
						<td>
							<select name="woocommerce_postnet_delivery_options[postnet_store]" id="postnet_store">
								<option value=''>Select...</option>
								<?php
								$selected_store = isset( $options['postnet_store'] ) ? esc_attr( $options['postnet_store'] ) : '';
								foreach ( $options['stores'] as $store ) {
									echo '<option value="' . esc_attr( $store->code ) . '" data-email="' . esc_attr( $store->email ) . '"' . selected( $selected_store,
									                                                                                                                   $store->code ) . '>' . esc_html( $store->store_name ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="postnet_store_email">PostNet Store Email</label></th>
						<td>
							<input type="text" name="woocommerce_postnet_delivery_options[postnet_store_email]" id="postnet_store_email" style="width:300px;" value="<?php echo isset( $options['postnet_store_email'] ) ? esc_attr( $options['postnet_store_email'] ) : ''; ?>" readonly/>
						</td>
					</tr>
					<!-- Add other settings as necessary -->
				</table>

				<?php
				// Output save settings button
				submit_button( 'Save Settings' );
				?>
				<a href="<?= esc_url( add_query_arg( 'action', 'export_products' ) ) ?>" class="button">Export Products CSV</a>
				<label for="postnet_delivery_csv" class="button">Import Products CSV</label>
			</form>
			<form method="post" enctype="multipart/form-data" class="hidden">
				<input type='file' name='postnet_delivery_csv' accept='.csv' id="postnet_delivery_csv">
				<input type='hidden' name='action' value='import_products'>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the settings.
	 *
	 * @return []
	 */
	public function get_settings() {

		static $options;

		$default = [
			'service_type'           => '',
			'postnet_to_postnet_fee' => '',
			'order_amount_threshold' => '',
			'collection_type'        => '',
			'postnet_store'          => '',
			'stores'                 => $this->get_stores(),
		];

		if ( ! $options ) {
			// Retrieve the plugin settings from the options table
			$options = get_option( self::OPTIONS_SLUG, [] );

			$selected_store = isset( $options['postnet_store'] ) ? esc_attr( $options['postnet_store'] ) : '';
		}

		return wp_parse_args( $options, $default );
	}

	/**
	 * Get a list of the stores.
	 *
	 * @return array
	 */
	public function get_stores() {

		$stores = get_transient( self::TRANS_SLUG );
		if ( empty( $stores ) ) {
			$stores = json_decode( file_get_contents( self::STORES_URL ) );
			if ( ! empty( $stores ) ) {
				set_transient( self::TRANS_SLUG, $stores, DAY_IN_SECONDS );
			}
		}

		return (array) $stores;
	}

	/**
	 * Get the delivery service types.
	 *
	 * @return string[]
	 */
	function get_delivery_service_types() {

		return [
			'postnet_to_postnet'      => __( 'PostNet to PostNet', 'woocommerce_postnet_delivery' ),
			'regional_centre_express' => __( 'Regional Centre - Express', 'woocommerce_postnet_delivery' ),
			'regional_centre_economy' => __( 'Regional Centre - Economy', 'woocommerce_postnet_delivery' ),
			'main_centre_express'     => __( 'Main Centre - Express', 'woocommerce_postnet_delivery' ),
			'main_centre_economy'     => __( 'Main Centre - Economy', 'woocommerce_postnet_delivery' ),
		];
	}
}
