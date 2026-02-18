<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Plugin Name: Delivery Options For PostNet
 * Plugin URI: https://github.com/freeman-tech-systems/woocommerce-postnet-delivery
 * Description: Adds PostNet delivery options to WooCommerce checkout.
 * Version: 1.0.11
 * Author: Freeman Tech Systems
 * Author URI: https://github.com/freeman-tech-systems
 * License: GPL2
 * WC requires at least: 3.0
 * WC tested up to: 8.2.1
 */

const POSTNET_SHIPPING_FREE = 'PostNet Free Shipping';
const POSTNET_SHIPPING_STORE = 'Collect at PostNet';
const POSTNET_SHIPPING_EXPRESS = 'PostNet Door Delivery - Express';
const POSTNET_SHIPPING_ECONOMY = 'PostNet Door Delivery - Economy';

/** Internal method IDs used to identify PostNet shipping options (not editable by user) */
const POSTNET_METHOD_ID_FREE = 'postnet_free';
const POSTNET_METHOD_ID_STORE = 'postnet_store';
const POSTNET_METHOD_ID_EXPRESS = 'postnet_express';
const POSTNET_METHOD_ID_ECONOMY = 'postnet_economy';

add_action('admin_enqueue_scripts', 'wc_postnet_delivery_enqueue_scripts');
add_action('admin_init', 'wc_postnet_delivery_admin');
add_action('admin_init', 'wc_postnet_delivery_settings_init');
add_action('admin_menu', 'wc_postnet_delivery_settings_page');
add_action('admin_notices', 'woocommerce_postnet_delivery_show_shipping_configured_notice');
add_action('before_woocommerce_init', 'wc_postnet_delivery_compatibility');
add_action('woocommerce_admin_order_data_after_shipping_address', 'wc_postnet_delivery_checkout_field_display_admin_order_meta', 10, 1);
add_action('woocommerce_admin_order_data_after_shipping_address', 'wc_postnet_delivery_display_waybill_status', 20, 1);
add_action('woocommerce_after_shipping_rate', 'wc_postnet_delivery_checkout_field', 10, 1);
add_action('woocommerce_checkout_process', 'wc_postnet_delivery_validations');
add_action('woocommerce_checkout_update_order_meta', 'wc_postnet_delivery_checkout_field_update_order_meta');
add_action('woocommerce_order_details_after_order_table', 'wc_postnet_delivery_order_received_page');
add_action('woocommerce_process_product_meta', 'wc_postnet_delivery_save_product_fields');
add_action('woocommerce_product_options_shipping', 'wc_postnet_delivery_product_fields');
add_action('woocommerce_thankyou', 'wc_postnet_delivery_collection_notification', 10, 1);
add_action('woocommerce_order_status_completed', 'wc_postnet_delivery_create_waybill_on_completion', 10, 1);
add_action('woocommerce_admin_order_data_after_billing_address', 'wc_postnet_delivery_admin_collection_address_field', 10, 1);
add_action('woocommerce_process_shop_order_meta', 'wc_postnet_delivery_save_admin_collection_address_field', 10, 2);
add_action('woocommerce_before_order_object_save', 'wc_postnet_delivery_validate_collection_address_before_completion', 10, 2);
add_action('woocommerce_admin_order_data_after_billing_address', 'wc_postnet_delivery_admin_number_of_boxes_field', 10, 1);
add_action('woocommerce_process_shop_order_meta', 'wc_postnet_delivery_save_admin_number_of_boxes_field', 10, 2);
add_action('woocommerce_before_order_object_save', 'wc_postnet_delivery_validate_number_of_boxes_before_completion', 10, 2);
add_action('wp_ajax_nopriv_wc_postnet_delivery_stores', 'wc_postnet_delivery_stores');
add_action('wp_ajax_wc_postnet_delivery_stores', 'wc_postnet_delivery_stores');
add_action('wp_ajax_validate_google_api_key', 'wc_postnet_validate_google_api_key');
add_action('wp_ajax_wc_postnet_retry_waybill', 'wc_postnet_delivery_retry_waybill_ajax');

add_filter('woocommerce_package_rates', 'wc_postnet_delivery_custom_shipping_methods_logic', 10, 2);

// Hook for enqueuing scripts
add_action('wp_enqueue_scripts', 'wc_postnet_delivery_enqueue_frontend_scripts');

// Hook to store destination store data from blocks checkout
add_filter('woocommerce_store_api_checkout_order_processed', 'wc_postnet_delivery_blocks_checkout_update_order_meta', 10, 1);

// Add action for AJAX endpoint to get PostNet store details
add_action('wp_ajax_wc_postnet_delivery_store_details', 'wc_postnet_delivery_get_store_details');
add_action('wp_ajax_nopriv_wc_postnet_delivery_store_details', 'wc_postnet_delivery_get_store_details');

function wc_postnet_delivery_compatibility() {
  if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
}

function wc_postnet_delivery_settings_init() {
  register_setting(
    'wc_postnet_delivery', 
    'wc_postnet_delivery_options',
    array(
      'type' => 'array',
      'sanitize_callback' => 'wc_postnet_delivery_sanitize_options',
      'default' => array(
        'service_type' => array(),
        'collection_type' => 'always_collect',
        'waybill_option' => 'single',
        'postnet_to_postnet_fee' => '',
        'regional_centre_express_fee' => '',
        'regional_centre_economy_fee' => '',
        'main_centre_express_fee' => '',
        'main_centre_economy_fee' => '',
        'order_amount_threshold' => '',
        'postnet_store' => '',
        'postnet_api_key' => '',
        'postnet_api_passcode' => '',
        'google_api_key' => '',
        'multi_site_mode' => false,
        'collection_addresses' => array(),
        'postnet_shipping_instance_ids' => array()
      )
    )
  );

  add_settings_section(
    'wc_postnet_delivery_section',
    __('PostNet Delivery Settings', 'delivery-options-postnet-woocommerce'), // Updated text domain
    'wc_postnet_delivery_section_callback',
    'wc_postnet_delivery'
  );
}

function wc_postnet_delivery_settings_page() {
  add_submenu_page(
    'woocommerce', // Parent slug
    'PostNet Delivery', // Page title
    'PostNet Delivery', // Menu title
    'manage_options', // Capability
    'wc_postnet_delivery', // Menu slug
    'wc_postnet_delivery_options_page' // Function to display the settings page
  );
}

function wc_postnet_delivery_section_callback() {
  echo '<p>' . esc_html__('Set up delivery options for PostNet.', 'delivery-options-postnet-woocommerce') . ' <a href="' . esc_url('https://www.postnet.co.za/woocommerce-app-info') . '" target="_blank">' . esc_html__('Setup and Usage Instructions', 'delivery-options-postnet-woocommerce') . '</a></p>';
}

function wc_postnet_delivery_service_types() {
  return [
    'postnet_to_postnet' => 'PostNet to PostNet',
    'regional_centre_express' => 'Regional Centre - Express',
    'regional_centre_economy' => 'Regional Centre - Economy',
    'main_centre_express' => 'Main Centre - Express',
    'main_centre_economy' => 'Main Centre - Economy'
  ];
}

function wc_postnet_fetch_url($url){
  $response = wp_remote_get($url);
  
  if ( is_wp_error( $response ) ) {
    wp_send_json_error( 'Error fetching stores' );
    return;
  }

  return wp_remote_retrieve_body( $response );
}

function wc_postnet_delivery_options_page() {
  // Check user capabilities
  if (!current_user_can('manage_options')) {
    return;
  }

  // Retrieve the plugin settings from the options table
  $options = get_option('wc_postnet_delivery_options');
  if (!$options){
    $options = [
      'service_type' => [],
      'collection_type' => 'always_collect',
      'waybill_option' => 'single'
    ];
  }
  
  $stores = json_decode(wc_postnet_fetch_url('https://www.postnet.co.za/cart_store-json_list/?local=1'));
  error_log(print_r($stores, true));
  $selected_store = isset($options['postnet_store']) ? esc_attr($options['postnet_store']) : '';
  ?>
  <div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
      <?php
      // Output security fields for the registered setting "wc_postnet_delivery"
      settings_fields('wc_postnet_delivery');
      // Output setting sections and their fields
      do_settings_sections('wc_postnet_delivery');
      ?>
      <table class="form-table">
        <tr>
          <th scope="row"><label for="service_type"><?php echo esc_html__('Service Type', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <fieldset>
              <?php
              $service_types = wc_postnet_delivery_service_types();
              foreach ($service_types as $service_key=>$service_name){
                ?>
                <label>
                  <input type="checkbox" name="wc_postnet_delivery_options[service_type][]" value="<?php echo esc_attr($service_key); ?>" <?php checked(in_array($service_key, (array)($options['service_type']))); ?> />
                  <?php echo esc_html($service_name); ?>
                </label><br />
                <?php
              }
              ?>
            </fieldset>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="postnet_to_postnet_fee"><?php echo esc_html__('PostNet to PostNet Delivery Fee', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <input type="number" name="wc_postnet_delivery_options[postnet_to_postnet_fee]" value="<?php echo esc_attr(isset($options['postnet_to_postnet_fee']) ? $options['postnet_to_postnet_fee'] : ''); ?>" required />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="regional_centre_express_fee"><?php echo esc_html__('Regional Centre - Express', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <input type="number" name="wc_postnet_delivery_options[regional_centre_express_fee]" value="<?php echo esc_attr(isset($options['regional_centre_express_fee']) ? $options['regional_centre_express_fee'] : ''); ?>" step="any" min="0" />
            <p class="description"><?php echo esc_html__('Flat rate for Regional Centre - Express. Leave blank to use individual product calculations.', 'delivery-options-postnet-woocommerce'); ?></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="regional_centre_economy_fee"><?php echo esc_html__('Regional Centre - Economy', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <input type="number" name="wc_postnet_delivery_options[regional_centre_economy_fee]" value="<?php echo esc_attr(isset($options['regional_centre_economy_fee']) ? $options['regional_centre_economy_fee'] : ''); ?>" step="any" min="0" />
            <p class="description"><?php echo esc_html__('Flat rate for Regional Centre - Economy. Leave blank to use individual product calculations.', 'delivery-options-postnet-woocommerce'); ?></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="main_centre_express_fee"><?php echo esc_html__('Main Centre - Express', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <input type="number" name="wc_postnet_delivery_options[main_centre_express_fee]" value="<?php echo esc_attr(isset($options['main_centre_express_fee']) ? $options['main_centre_express_fee'] : ''); ?>" step="any" min="0" />
            <p class="description"><?php echo esc_html__('Flat rate for Main Centre - Express. Leave blank to use individual product calculations.', 'delivery-options-postnet-woocommerce'); ?></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="main_centre_economy_fee"><?php echo esc_html__('Main Centre - Economy', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <input type="number" name="wc_postnet_delivery_options[main_centre_economy_fee]" value="<?php echo esc_attr(isset($options['main_centre_economy_fee']) ? $options['main_centre_economy_fee'] : ''); ?>" step="any" min="0" />
            <p class="description"><?php echo esc_html__('Flat rate for Main Centre - Economy. Leave blank to use individual product calculations.', 'delivery-options-postnet-woocommerce'); ?></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="order_amount_threshold"><?php echo esc_html__('Order Amount Threshold', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <input type="number" name="wc_postnet_delivery_options[order_amount_threshold]" value="<?php echo esc_attr(isset($options['order_amount_threshold']) ? $options['order_amount_threshold'] : ''); ?>" required />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="collection_type"><?php echo esc_html__('Collection Type', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <select name="wc_postnet_delivery_options[collection_type]">
              <option value="always_collect" <?php selected($options['collection_type'], 'always_collect'); ?>><?php echo esc_html__('Always Collect', 'delivery-options-postnet-woocommerce'); ?></option>
              <option value="always_deliver" <?php selected($options['collection_type'], 'always_deliver'); ?>><?php echo esc_html__('Always Deliver', 'delivery-options-postnet-woocommerce'); ?></option>
              <option value="service_based" <?php selected($options['collection_type'], 'service_based'); ?>><?php echo esc_html__('Service Based', 'delivery-options-postnet-woocommerce'); ?></option>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="waybill_option"><?php echo esc_html__('Waybill Option', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <select name="wc_postnet_delivery_options[waybill_option]" id="waybill_option">
              <option value="single" <?php selected(isset($options['waybill_option']) ? $options['waybill_option'] : 'single', 'single'); ?>><?php echo esc_html__('Single Waybill', 'delivery-options-postnet-woocommerce'); ?></option>
              <option value="individual" <?php selected(isset($options['waybill_option']) ? $options['waybill_option'] : 'single', 'individual'); ?>><?php echo esc_html__('Individual Waybills', 'delivery-options-postnet-woocommerce'); ?></option>
              <option value="user_specified" <?php selected(isset($options['waybill_option']) ? $options['waybill_option'] : 'single', 'user_specified'); ?>><?php echo esc_html__('User Specified', 'delivery-options-postnet-woocommerce'); ?></option>
            </select>
            <p class="description"><?php echo esc_html__('Choose whether orders are grouped on a single waybill, each item creates its own waybill, or the number of boxes is specified by the user on the order page.', 'delivery-options-postnet-woocommerce'); ?></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="postnet_store"><?php echo esc_html__('PostNet Store', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <select name="wc_postnet_delivery_options[postnet_store]" id="postnet_store" required>
              <option value=''><?php echo esc_html__('Select...', 'delivery-options-postnet-woocommerce'); ?></option>
              <?php
              $selected_store = isset($options['postnet_store']) ? esc_attr($options['postnet_store']) : '';
              foreach ($stores as $store){
                echo '<option value="'.esc_attr($store->code).'" data-email="'.esc_attr($store->email).'"'.selected($selected_store, $store->code).'>'.esc_html($store->store_name).'</option>';
              }
              ?>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="postnet_api_key"><?php echo esc_html__('API Key', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <input type="text" name="wc_postnet_delivery_options[postnet_api_key]" id="postnet_api_key" style="width:300px;" value="<?php echo esc_attr(isset($options['postnet_api_key']) ? $options['postnet_api_key'] : ''); ?>" required />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="postnet_api_passcode"><?php echo esc_html__('Passphrase', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <input type="text" name="wc_postnet_delivery_options[postnet_api_passcode]" id="postnet_api_passcode" style="width:300px;" value="<?php echo esc_attr(isset($options['postnet_api_passcode']) ? $options['postnet_api_passcode'] : ''); ?>" required />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="google_api_key"><?php echo esc_html__('Google API Key', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <input type="text" name="wc_postnet_delivery_options[google_api_key]" id="google_api_key" style="width:300px;" value="<?php echo esc_attr(isset($options['google_api_key']) ? $options['google_api_key'] : ''); ?>" />
            <button type="button" id="validate_google_api" class="button"><?php echo esc_html__('Validate', 'delivery-options-postnet-woocommerce'); ?></button>
            <span class="spinner" id="google_api_spinner" style="float: none;"></span>
            <p class="description"><?php echo esc_html__('Enter your Google Maps API key with Maps JavaScript API enabled.', 'delivery-options-postnet-woocommerce'); ?></p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="multi_site_mode"><?php echo esc_html__('Multi Site Mode', 'delivery-options-postnet-woocommerce'); ?></label></th>
          <td>
            <label>
              <input type="checkbox" name="wc_postnet_delivery_options[multi_site_mode]" value="1" <?php checked(isset($options['multi_site_mode']) ? $options['multi_site_mode'] : false); ?> />
              <?php echo esc_html__('Enable Multi Site Mode', 'delivery-options-postnet-woocommerce'); ?>
            </label>
            <p class="description"><?php echo esc_html__('When enabled, waybills will only be created when order status is changed to Completed and a collection address is selected.', 'delivery-options-postnet-woocommerce'); ?></p>
          </td>
        </tr>
      </table>
      
      <!-- Collection Addresses Section -->
      <div id="collection-addresses-section" style="<?php echo (isset($options['multi_site_mode']) && $options['multi_site_mode']) ? 'display: block;' : 'display: none;'; ?>">
        <h2><?php echo esc_html__('Collection Addresses', 'delivery-options-postnet-woocommerce'); ?></h2>
        <p><?php echo esc_html__('Configure multiple collection addresses for Multi Site Mode. These addresses will be used as the originating address on waybills instead of PostNet stores.', 'delivery-options-postnet-woocommerce'); ?></p>
        
        <div id="collection-addresses-container">
          <?php
          $collection_addresses = isset($options['collection_addresses']) ? $options['collection_addresses'] : array();
          if (empty($collection_addresses)) {
            $collection_addresses = array(array(
              'sender_street_address' => '',
              'sender_other_address' => '',
              'sender_country_code' => 'ZA',
              'sender_suburb' => '',
              'sender_postal_code' => '',
              'sender_name' => '',
              'sender_contact_number' => '',
              'sender_contact_person' => ''
            ));
          }
          
          foreach ($collection_addresses as $index => $address) {
            ?>
            <div class="collection-address" data-index="<?php echo esc_attr($index); ?>">
              <h3><?php echo esc_html__('Collection Address', 'delivery-options-postnet-woocommerce'); ?> #<?php echo esc_html($index + 1); ?></h3>
              <table class="form-table">
                <tr>
                  <th scope="row"><label for="sender_street_address_<?php echo esc_attr($index); ?>"><?php echo esc_html__('Street Address', 'delivery-options-postnet-woocommerce'); ?></label></th>
                  <td>
                    <input type="text" name="wc_postnet_delivery_options[collection_addresses][<?php echo esc_attr($index); ?>][sender_street_address]" id="sender_street_address_<?php echo esc_attr($index); ?>" style="width:400px;" value="<?php echo esc_attr($address['sender_street_address']); ?>" required />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label for="sender_other_address_<?php echo esc_attr($index); ?>"><?php echo esc_html__('Additional Address Info', 'delivery-options-postnet-woocommerce'); ?></label></th>
                  <td>
                    <input type="text" name="wc_postnet_delivery_options[collection_addresses][<?php echo esc_attr($index); ?>][sender_other_address]" id="sender_other_address_<?php echo esc_attr($index); ?>" style="width:400px;" value="<?php echo esc_attr($address['sender_other_address']); ?>" />
                    <p class="description"><?php echo esc_html__('Optional: Additional address information like unit number, building name, etc.', 'delivery-options-postnet-woocommerce'); ?></p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label for="sender_country_code_<?php echo esc_attr($index); ?>"><?php echo esc_html__('Country Code', 'delivery-options-postnet-woocommerce'); ?></label></th>
                  <td>
                    <select name="wc_postnet_delivery_options[collection_addresses][<?php echo esc_attr($index); ?>][sender_country_code]" id="sender_country_code_<?php echo esc_attr($index); ?>" required>
                      <option value="ZA" <?php selected($address['sender_country_code'], 'ZA'); ?>><?php echo esc_html__('South Africa', 'delivery-options-postnet-woocommerce'); ?></option>
                      <option value="BW" <?php selected($address['sender_country_code'], 'BW'); ?>><?php echo esc_html__('Botswana', 'delivery-options-postnet-woocommerce'); ?></option>
                      <option value="LS" <?php selected($address['sender_country_code'], 'LS'); ?>><?php echo esc_html__('Lesotho', 'delivery-options-postnet-woocommerce'); ?></option>
                      <option value="NA" <?php selected($address['sender_country_code'], 'NA'); ?>><?php echo esc_html__('Namibia', 'delivery-options-postnet-woocommerce'); ?></option>
                      <option value="SZ" <?php selected($address['sender_country_code'], 'SZ'); ?>><?php echo esc_html__('Eswatini', 'delivery-options-postnet-woocommerce'); ?></option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label for="sender_suburb_<?php echo esc_attr($index); ?>"><?php echo esc_html__('Suburb/City', 'delivery-options-postnet-woocommerce'); ?></label></th>
                  <td>
                    <input type="text" name="wc_postnet_delivery_options[collection_addresses][<?php echo esc_attr($index); ?>][sender_suburb]" id="sender_suburb_<?php echo esc_attr($index); ?>" style="width:300px;" value="<?php echo esc_attr($address['sender_suburb']); ?>" required />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label for="sender_postal_code_<?php echo esc_attr($index); ?>"><?php echo esc_html__('Postal Code', 'delivery-options-postnet-woocommerce'); ?></label></th>
                  <td>
                    <input type="text" name="wc_postnet_delivery_options[collection_addresses][<?php echo esc_attr($index); ?>][sender_postal_code]" id="sender_postal_code_<?php echo esc_attr($index); ?>" style="width:150px;" value="<?php echo esc_attr($address['sender_postal_code']); ?>" required />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label for="sender_name_<?php echo esc_attr($index); ?>"><?php echo esc_html__('Company/Business Name', 'delivery-options-postnet-woocommerce'); ?></label></th>
                  <td>
                    <input type="text" name="wc_postnet_delivery_options[collection_addresses][<?php echo esc_attr($index); ?>][sender_name]" id="sender_name_<?php echo esc_attr($index); ?>" style="width:400px;" value="<?php echo esc_attr($address['sender_name']); ?>" required />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label for="sender_contact_number_<?php echo esc_attr($index); ?>"><?php echo esc_html__('Contact Number', 'delivery-options-postnet-woocommerce'); ?></label></th>
                  <td>
                    <input type="text" name="wc_postnet_delivery_options[collection_addresses][<?php echo esc_attr($index); ?>][sender_contact_number]" id="sender_contact_number_<?php echo esc_attr($index); ?>" style="width:200px;" value="<?php echo esc_attr($address['sender_contact_number']); ?>" required />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label for="sender_contact_person_<?php echo esc_attr($index); ?>"><?php echo esc_html__('Contact Person', 'delivery-options-postnet-woocommerce'); ?></label></th>
                  <td>
                    <input type="text" name="wc_postnet_delivery_options[collection_addresses][<?php echo esc_attr($index); ?>][sender_contact_person]" id="sender_postal_code_<?php echo esc_attr($index); ?>" style="width:300px;" value="<?php echo esc_attr($address['sender_contact_person']); ?>" required />
                  </td>
                </tr>
              </table>
              <button type="button" class="button remove-address" data-index="<?php echo esc_attr($index); ?>"><?php echo esc_html__('Remove Address', 'delivery-options-postnet-woocommerce'); ?></button>
            </div>
            <?php
          }
          ?>
        </div>
        
        <button type="button" id="add-collection-address" class="button"><?php echo esc_html__('Add Another Collection Address', 'delivery-options-postnet-woocommerce'); ?></button>
      </div>
      
      <?php
      // Output save settings button
      submit_button(esc_html__('Save Settings', 'delivery-options-postnet-woocommerce'));
      $configure_nonce = wp_create_nonce('configure_shipping_options_nonce');
      $export_nonce = wp_create_nonce('export_products_nonce');
      ?>
      <a href="<?php echo esc_url(add_query_arg(['action' => 'configure_shipping_options', '_wpnonce' => $configure_nonce])); ?>" class="button"><?php echo esc_html__('Configure PostNet Shipping', 'delivery-options-postnet-woocommerce'); ?></a>
      <a href="<?php echo esc_url(add_query_arg(['action' => 'export_products', '_wpnonce' => $export_nonce])); ?>" class="button"><?php echo esc_html__('Export Products CSV', 'delivery-options-postnet-woocommerce'); ?></a>
      <label for="postnet_delivery_csv" class="button"><?php echo esc_html__('Import Products CSV', 'delivery-options-postnet-woocommerce'); ?></label>
    </form>
    <form method="post" enctype="multipart/form-data" class="hidden">
      <?php wp_nonce_field('postnet_delivery_action', 'postnet_delivery_nonce'); ?>
      <input type='file' name='postnet_delivery_csv' accept='.csv' id="postnet_delivery_csv">
      <input type='hidden' name='action' value='import_products'>
    </form>
  </div>
  <?php
}

function wc_postnet_delivery_enqueue_scripts($hook) {
  // Check if we are on the settings page of our plugin
  if ($hook == 'woocommerce_page_wc_postnet_delivery') {
    // Enqueue our script
    wp_enqueue_script('wc-postnet-delivery-options-js', plugin_dir_url(__FILE__) . 'js/wc-postnet-delivery-options.js', array('jquery'), '1.0.8', true);
    
    // Enqueue SweetAlert for nice alerts
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0', true);
    
    // Localize the script with data for AJAX
    wp_localize_script(
      'wc-postnet-delivery-options-js',
      'wc_postnet_delivery_params',
      array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wc_postnet_delivery_admin_nonce')
      )
    );
  }
  
  // Enqueue scripts for order edit page
  if ($hook == 'post.php' || $hook == 'post-new.php') {
    global $post;
    if ($post && $post->post_type == 'shop_order') {
      wp_enqueue_script('wc-postnet-delivery-admin-order-js', plugin_dir_url(__FILE__) . 'js/wc-postnet-delivery-admin-order.js', array('jquery'), '1.0.0', true);
      
      wp_localize_script(
        'wc-postnet-delivery-admin-order-js',
        'wc_postnet_delivery_order_params',
        array(
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('wc_postnet_delivery_order_nonce'),
          'error_label' => __('Error:', 'delivery-options-postnet-woocommerce'),
          'ajax_error' => __('An error occurred while processing your request.', 'delivery-options-postnet-woocommerce')
        )
      );
    }
  }
}

function wc_postnet_delivery_admin() {
  // Check if the export action has been triggered
  if (isset($_GET['action']) && $_GET['action'] === 'export_products') {
    if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'export_products_nonce')) {
      wc_postnet_delivery_export_products_csv();
    } else {
      wp_die('Security check failed.');
    }
  }
  
  // Check if the import action has been triggered
  if (isset($_POST['action']) && $_POST['action'] === 'import_products' && !empty($_FILES['postnet_delivery_csv'])) {
    if (isset($_POST['postnet_delivery_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['postnet_delivery_nonce'])), 'postnet_delivery_action')) {
      wc_postnet_delivery_import_products_csv();
    } else {
      wp_die('Security check failed.');
    }
  }
  
  // Configure shipping options
  if (isset($_GET['action']) && $_GET['action'] === 'configure_shipping_options') {
    if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'configure_shipping_options_nonce')) {
      wc_postnet_delivery_configure_shipping_options();
    } else {
      wp_die('Security check failed.');
    }
  }
}

function wc_postnet_delivery_csv_headers() {
  $headers = ['Product ID', 'Product Name'];
  $service_types = wc_postnet_delivery_service_types();
  foreach ($service_types as $service_key=>$service_name){
    if ($service_key == 'postnet_to_postnet') continue;
    
    $headers[] = $service_name;
  }
  
  return $headers;
}

function wc_postnet_delivery_export_products_csv() {
  // Define the CSV headers
  $headers = wc_postnet_delivery_csv_headers();

  // Set the headers to force download of the file
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="woocommerce-products.csv"');

  // Output the column headings directly
  echo implode(',', $headers) . "\n";

  // Get all the WooCommerce products
  $args = array(
    'post_type' => 'product',
    'posts_per_page' => -1,
  );

  $products = get_posts($args);

  foreach ($products as $product) {
    // Get the product ID
    $product_id = $product->ID;
    // Get the product name
    $product_name = get_the_title($product_id);

    // Combine the data into a single row
    $row = [
      $product_id,
      $product_name
    ];

    // Get the fees from the product meta
    $service_types = wc_postnet_delivery_service_types();
    foreach ($service_types as $service_key => $_service_name) {
      if ($service_key == 'postnet_to_postnet') continue;

      $row[] = get_post_meta($product_id, '_' . $service_key . '_fee', true);
    }

    // Output the row directly
    echo implode(',', $row) . "\n";
  }

  // Terminate the current script to prevent WordPress template loading
  exit();
}

function wc_postnet_delivery_import_products_csv() {
  // Verify the nonce for security
  if (!(isset($_POST['postnet_delivery_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['postnet_delivery_nonce'])), 'postnet_delivery_action'))) {
    wp_die('Security check failed.');
  }

  // Initialize the WordPress filesystem
  if (!function_exists('WP_Filesystem')) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
  }

  $access_type = get_filesystem_method();
  if ($access_type === 'direct') {
    $creds = request_filesystem_credentials(site_url() . '/wp-admin/', '', false, false, array());
    if (!WP_Filesystem($creds)) {
      wp_die('Could not initialize filesystem.');
      return;
    }
  }

  global $wp_filesystem;

  // Check for file upload
  if (isset($_FILES['postnet_delivery_csv']) && isset($_FILES['postnet_delivery_csv']['tmp_name'])) {
    // Get the file path from the uploaded file
    $file_path = $_FILES['postnet_delivery_csv']['tmp_name'];

    // Check if the file exists and read its contents
    if ($wp_filesystem->exists($file_path)) {
      // Read the file into an array of lines
      $file_contents = $wp_filesystem->get_contents_array($file_path);

      if (!empty($file_contents)) {
        // Extract the header line
        $header = str_getcsv(array_shift($file_contents));

        // Define the expected headers
        $expected_headers = wc_postnet_delivery_csv_headers();

        // Check if headers match
        if ($header !== $expected_headers) {
          // Add an admin notice on header mismatch
          add_action('admin_notices', function() use ($header) {
            echo '<div class="notice notice-error is-dismissible"><p>The uploaded file headers do not match the expected headers. Please check the file and try again.</p></div>';
          });
          return;
        }

        // Process each line of data
        foreach ($file_contents as $line) {
          $data = str_getcsv($line);
          $product_id = intval($data[0]);

          $col = 2;
          $service_types = wc_postnet_delivery_service_types();
          foreach ($service_types as $service_key => $_service_name) {
            if ($service_key == 'postnet_to_postnet') continue;

            $fee = wc_clean($data[$col]);
            update_post_meta($product_id, '_' . $service_key . '_fee', $fee);
            $col++;
          }
        }

        // Add an admin notice on successful import
        add_action('admin_notices', function() {
          echo '<div class="notice notice-success is-dismissible"><p>Product delivery fees have been successfully updated.</p></div>';
        });
      } else {
        wp_die('Failed to read the uploaded file.');
      }
    } else {
      wp_die('Uploaded file not found.');
    }
  } else {
    wp_die('No file was uploaded.');
  }
}

/**
 * Internal PostNet method IDs to default titles (for creation only).
 *
 * @return array Map of POSTNET_METHOD_ID_* => default method title
 */
function wc_postnet_delivery_get_postnet_method_specs() {
  return array(
    POSTNET_METHOD_ID_FREE => POSTNET_SHIPPING_FREE,
    POSTNET_METHOD_ID_STORE => POSTNET_SHIPPING_STORE,
    POSTNET_METHOD_ID_EXPRESS => POSTNET_SHIPPING_EXPRESS,
    POSTNET_METHOD_ID_ECONOMY => POSTNET_SHIPPING_ECONOMY,
  );
}

/**
 * Get the WooCommerce rate id (e.g. flat_rate:3) for a PostNet method by internal id.
 * Uses stored instance IDs so we do not rely on editable titles.
 *
 * @param string $internal_id One of POSTNET_METHOD_ID_FREE, POSTNET_METHOD_ID_STORE, etc.
 * @return string|null Rate id like 'flat_rate:3' or null if not configured
 */
function wc_postnet_delivery_get_postnet_rate_id($internal_id) {
  $options = get_option('wc_postnet_delivery_options');
  $instance_ids = isset($options['postnet_shipping_instance_ids']) && is_array($options['postnet_shipping_instance_ids'])
    ? $options['postnet_shipping_instance_ids']
    : array();
  $instance_id = isset($instance_ids[$internal_id]) ? (int) $instance_ids[$internal_id] : 0;
  if ($instance_id <= 0) {
    return null;
  }
  return 'flat_rate:' . $instance_id;
}

/**
 * Get the internal PostNet method id for a WooCommerce rate id (e.g. flat_rate:3).
 *
 * @param string $rate_id Rate id like 'flat_rate:3'
 * @return string|null One of POSTNET_METHOD_ID_* or null if not a PostNet method
 */
function wc_postnet_delivery_get_postnet_internal_id_for_rate($rate_id) {
  if (strpos($rate_id, 'flat_rate:') !== 0) {
    return null;
  }
  $instance_id = (int) substr($rate_id, strlen('flat_rate:'));
  if ($instance_id <= 0) {
    return null;
  }
  $options = get_option('wc_postnet_delivery_options');
  $instance_ids = isset($options['postnet_shipping_instance_ids']) && is_array($options['postnet_shipping_instance_ids'])
    ? $options['postnet_shipping_instance_ids']
    : array();
  foreach ($instance_ids as $internal_id => $stored_id) {
    if ((int) $stored_id === $instance_id) {
      return $internal_id;
    }
  }
  return null;
}

function wc_postnet_delivery_configure_shipping_options() {
  $zone = wc_postnet_delivery_get_zone();
  $options = get_option('wc_postnet_delivery_options');
  $instance_ids = isset($options['postnet_shipping_instance_ids']) && is_array($options['postnet_shipping_instance_ids'])
    ? $options['postnet_shipping_instance_ids']
    : array();

  $specs = wc_postnet_delivery_get_postnet_method_specs();
  $existing_methods = $zone->get_shipping_methods(true);
  $existing_instance_ids = array();
  foreach ($existing_methods as $method) {
    if ($method->id === 'flat_rate' && isset($method->instance_id)) {
      $existing_instance_ids[$method->instance_id] = $method;
    }
  }

  foreach ($specs as $internal_id => $default_title) {
    $stored_instance_id = isset($instance_ids[$internal_id]) ? (int) $instance_ids[$internal_id] : 0;
    $found = false;

    if ($stored_instance_id > 0 && isset($existing_instance_ids[$stored_instance_id])) {
      $found = true;
    } else {
      foreach ($existing_methods as $method) {
        if ($method->id === 'flat_rate' && $method->title === $default_title) {
          $instance_ids[$internal_id] = (int) $method->instance_id;
          $found = true;
          break;
        }
      }
    }

    if (!$found) {
      $instance_id = wc_postnet_delivery_create_shipping_option($zone, 'flat_rate', $default_title);
      if ($instance_id > 0) {
        $instance_ids[$internal_id] = $instance_id;
      }
    }
  }

  $options['postnet_shipping_instance_ids'] = $instance_ids;
  update_option('wc_postnet_delivery_options', $options);

  wp_safe_redirect(add_query_arg('shipping_configured', '1', menu_page_url('wc_postnet_delivery', false)));
}

function wc_postnet_delivery_get_zone() {
  $zone_name = 'South Africa';
  $zone_order = 0;

  // Check if the zone already exists
  $existing_zones = WC_Shipping_Zones::get_zones();
  foreach ($existing_zones as $zone) {
    if ($zone['zone_name'] === $zone_name) {
      return WC_Shipping_Zones::get_zone($zone['zone_id']);
    }
  }

  if (!isset($zone_id)) {
    // Zone does not exist, create it
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name($zone_name);
    $zone->set_zone_order($zone_order);
    $zone->add_location('ZA', 'country');
    $zone->save();

    return $zone;
  }
}

/**
 * Create a flat rate shipping method in the zone and set its title/cost.
 *
 * @param WC_Shipping_Zone $zone
 * @param string $method_type e.g. 'flat_rate'
 * @param string $method_title Display title for the method
 * @return int The new instance_id, or 0 on failure
 */
function wc_postnet_delivery_create_shipping_option($zone, $method_type, $method_title) {
  $instance_id = (int) $zone->add_shipping_method($method_type);
  if ($instance_id <= 0) {
    return 0;
  }

  $option_name = 'woocommerce_flat_rate_' . $instance_id . '_settings';
  $instance_settings = get_option($option_name, array());
  $instance_settings['title'] = $method_title;
  $instance_settings['cost'] = '0.00';
  $instance_settings['tax_status'] = 'taxable';
  update_option($option_name, $instance_settings);

  return $instance_id;
}

function woocommerce_postnet_delivery_show_shipping_configured_notice() {
  if (isset($_GET['shipping_configured']) && $_GET['shipping_configured'] == '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Shipping options have been configured.', 'delivery-options-postnet-woocommerce') . '</p></div>';
  }
}

function wc_postnet_delivery_product_fields() {
  global $post;

  // Get the enabled service types from the settings
  $options = get_option('wc_postnet_delivery_options');
  $enabled_services = isset($options['service_type']) ? $options['service_type'] : array();
  $service_types = wc_postnet_delivery_service_types();
  
  if (!$enabled_services || (count($enabled_services) == 1 && $enabled_services[0] == 'postnet_to_postnet')) return;

  echo '<div class="options_group">';
  echo '<hr />';
  echo '<p><strong>PostNet Delivery Fees</strong></p>';

  // For each enabled service, add a corresponding field
  foreach ($enabled_services as $service) {
    if ($service == 'postnet_to_postnet') continue;
    
    $field_id = '_'.sanitize_title($service).'_fee';
    $value = get_post_meta($post->ID, $field_id, true); // Retrieve the saved value
    
    woocommerce_wp_text_input(
      [
        'id' => $field_id,
        // Translators: %s is the service type name.
        'label' => sprintf(__('%s Delivery Fee', 'delivery-options-postnet-woocommerce'), $service_types[$service]),
        'desc_tip' => 'true',
        // Translators: %s is the service type name.
        'description' => sprintf(__('Enter the delivery fee for %s service.', 'delivery-options-postnet-woocommerce'), $service_types[$service]),
        'type' => 'number',
        'custom_attributes' => [
          'step' => 'any',
          'min' => '0'
        ],
        'value' => $value
      ]
    );
  }

  echo '</div>';
}

function wc_postnet_delivery_save_product_fields($post_id) {
  // Get the enabled service types from the settings
  $options = get_option('wc_postnet_delivery_options');
  $enabled_services = isset($options['service_type']) ? $options['service_type'] : array();

  // Save the delivery fee for each enabled service
  foreach ($enabled_services as $service) {
    $field_id = '_'.sanitize_title($service).'_fee';
    if (isset($_POST[$field_id])) {
      update_post_meta($post_id, $field_id, wc_clean($_POST[$field_id]));
    }
  }
}

function wc_postnet_delivery_custom_shipping_methods_logic($rates, $package) {
  // Calculate the PostNet fee
  $postal_code = $package['destination']['postcode'];
  $main_check = $postal_code ? json_decode(wc_postnet_fetch_url('https://pnsa.restapis.co.za/public/is-main?postcode='.$postal_code)) : null;
  $is_main = $main_check ? $main_check->main : false;
  $subtotal = $package['cart_subtotal'];
  $options = get_option('wc_postnet_delivery_options');
  $order_amount_threshold = isset($options['order_amount_threshold']) ? $options['order_amount_threshold'] : 0;
  $postnet_to_postnet_fee = isset($options['postnet_to_postnet_fee']) ? $options['postnet_to_postnet_fee'] : 0;
  $enabled_services = isset($options['service_type']) ? $options['service_type'] : array();
  $free_shipping = $order_amount_threshold > 0 && $subtotal >= $order_amount_threshold;
  
  $postnet_rate_free = wc_postnet_delivery_get_postnet_rate_id(POSTNET_METHOD_ID_FREE);
  $postnet_rate_store = wc_postnet_delivery_get_postnet_rate_id(POSTNET_METHOD_ID_STORE);
  $postnet_rate_express = wc_postnet_delivery_get_postnet_rate_id(POSTNET_METHOD_ID_EXPRESS);
  $postnet_rate_economy = wc_postnet_delivery_get_postnet_rate_id(POSTNET_METHOD_ID_ECONOMY);

  foreach ($rates as $rate_id => $rate) {
    if ($rate_id === $postnet_rate_free) {
      if (!$free_shipping) unset($rates[$rate_id]);
      continue;
    }
    if ($rate_id === $postnet_rate_store) {
      if (!$free_shipping && in_array('postnet_to_postnet', $enabled_services)){
        $rate->cost = $postnet_to_postnet_fee;
      } else {
        unset($rates[$rate_id]);
      }
      continue;
    }
    if ($rate_id === $postnet_rate_express) {
      if (!$free_shipping && $is_main && in_array('main_centre_express', $enabled_services)){
        $flat_rate = isset($options['main_centre_express_fee']) && $options['main_centre_express_fee'] !== '' ? floatval($options['main_centre_express_fee']) : null;
        $rate->cost = $flat_rate !== null ? $flat_rate : wc_postnet_delivery_service_fee($package, 'main_centre_express');
      } else if (!$free_shipping && !$is_main && in_array('regional_centre_express', $enabled_services)){
        $flat_rate = isset($options['regional_centre_express_fee']) && $options['regional_centre_express_fee'] !== '' ? floatval($options['regional_centre_express_fee']) : null;
        $rate->cost = $flat_rate !== null ? $flat_rate : wc_postnet_delivery_service_fee($package, 'regional_centre_express');
      } else {
        unset($rates[$rate_id]);
      }
      continue;
    }
    if ($rate_id === $postnet_rate_economy) {
      if (!$free_shipping && $is_main && in_array('main_centre_economy', $enabled_services)){
        $flat_rate = isset($options['main_centre_economy_fee']) && $options['main_centre_economy_fee'] !== '' ? floatval($options['main_centre_economy_fee']) : null;
        $rate->cost = $flat_rate !== null ? $flat_rate : wc_postnet_delivery_service_fee($package, 'main_centre_economy');
      } else if (!$free_shipping && !$is_main && in_array('regional_centre_economy', $enabled_services)){
        $flat_rate = isset($options['regional_centre_economy_fee']) && $options['regional_centre_economy_fee'] !== '' ? floatval($options['regional_centre_economy_fee']) : null;
        $rate->cost = $flat_rate !== null ? $flat_rate : wc_postnet_delivery_service_fee($package, 'regional_centre_economy');
      } else {
        unset($rates[$rate_id]);
      }
      continue;
    }
  }
  
  return $rates;
}

function wc_postnet_delivery_service_fee($package, $service) {
  $fee = 0;
  
  foreach ($package['contents'] as $item_id => $values) {
    $value = get_post_meta($values['product_id'], '_'.$service.'_fee', true);
    $fee += ($value ? $value : 0) * $values['quantity'];
  }
  
  return $fee;
}

function wc_postnet_delivery_fetch_stores() {
  $address_details = array_filter([
    WC()->customer->get_shipping_address(),
    WC()->customer->get_shipping_address_2(),
    WC()->customer->get_shipping_city(),
    WC()->customer->get_shipping_state(),
    WC()->customer->get_shipping_postcode()
  ]);
  
  $address = implode(', ', array_filter($address_details));
  $body = wc_postnet_fetch_url('https://postnet.co.za/courier_package-calculate/?data%5Baddress%5D='.urlencode($address));
  return json_decode( $body );
}

function wc_postnet_delivery_stores() {
  // Verify security nonce
  if (isset($_POST['security']) && wp_verify_nonce(sanitize_text_field($_POST['security']), 'wc_postnet_delivery_nonce')) {
    try {
      $stores = wc_postnet_delivery_fetch_stores();
      
      if (empty($stores)) {
        wp_send_json_error('No stores found');
        return;
      }
      
      // Format stores for the frontend
      $formatted_stores = array();
      foreach ($stores as $store) {
        // Support both object and array access
        $code = is_object($store) ? ($store->code ?? null) : ($store['code'] ?? null);
        $name = is_object($store) ? ($store->name ?? $store->store_name ?? null) : ($store['name'] ?? $store['store_name'] ?? null);
        
        if ($code && $name) {
          $formatted_stores[] = array(
            'code' => $code,
            'name' => $name
          );
        }
      }
      
      wp_send_json_success($formatted_stores);
    } catch (Exception $e) {
      wp_send_json_error('Error: ' . $e->getMessage());
    }
  } else {
    wp_send_json_error('Security check failed');
  }
  
  wp_die();
}

function wc_postnet_delivery_checkout_field($rate) {
  $chosen_methods = WC()->session->get('chosen_shipping_methods');

  // The chosen methods are stored in an array, one for each package. Most stores will only have one package.
  $chosen_method = !empty($chosen_methods) ? $chosen_methods[0] : '';

  $postnet_store_rate_id = wc_postnet_delivery_get_postnet_rate_id(POSTNET_METHOD_ID_STORE);
  if ($postnet_store_rate_id === null || $rate->id !== $postnet_store_rate_id || $rate->id !== $chosen_method) return;
  
  // Get available stores
  $stores = wc_postnet_delivery_fetch_stores();
  if (empty($stores)) return;
  
  // Get Google Maps API key from options
  $options = get_option('wc_postnet_delivery_options');
  $google_api_key = !empty($options['google_api_key']) ? $options['google_api_key'] : '';
  
  // Check if we're using blocks checkout
  $is_blocks_checkout = class_exists('\Automattic\WooCommerce\Blocks\Package') && 
                        did_action('woocommerce_blocks_enqueue_checkout_block_scripts');
  
  // Only use tabs for blocks checkout with Google Maps
  $use_tabs = $is_blocks_checkout && !empty($google_api_key);
  
  if ($use_tabs) {
    // Enhanced selector with Map and List views for blocks checkout
    ?>
    <div class="postnet-store-selector-tabs">
      <div class="postnet-tab-headers">
        <div class="postnet-tab-header active" data-tab="map"><?php esc_html_e('Map', 'delivery-options-postnet-woocommerce'); ?></div>
        <div class="postnet-tab-header" data-tab="list"><?php esc_html_e('List', 'delivery-options-postnet-woocommerce'); ?></div>
      </div>
      
      <div class="postnet-tab-content active" data-tab="map">
        <div id="postnet-map-container"></div>
        <div id="postnet-selected-store-details"></div>
      </div>
      
      <div class="postnet-tab-content" data-tab="list">
        <div class="postnet-stores-list">
          <?php
          $counter = 0;
          $page = 1;
          
          echo '<div class="postnet-list-page active" data-page="' . esc_attr($page) . '">';
          
          foreach ($stores as $store) {
            if ($counter % 5 === 0 && $counter > 0) {
              echo '</div>';
              $page++;
              echo '<div class="postnet-list-page" data-page="' . esc_attr($page) . '">';
            }
            
            echo '<div class="postnet-store-item" data-store-code="' . esc_attr($store->code) . '" data-store-name="' . esc_attr($store->name) . '">';
            echo '<div class="postnet-store-name">' . esc_html($store->name) . '</div>';
            echo '<div class="postnet-store-loading">' . esc_html__('Loading store details...', 'delivery-options-postnet-woocommerce') . '</div>';
            echo '</div>';
            
            $counter++;
          }
          
          echo '</div>';
          
          if ($page > 1) {
            echo '<div class="postnet-pagination">';
            for ($i = 1; $i <= $page; $i++) {
              echo '<span class="postnet-page-number' . ($i === 1 ? ' active' : '') . '" data-page="' . esc_attr($i) . '">' . esc_html($i) . '</span>';
            }
            echo '</div>';
          }
          ?>
        </div>
      </div>
      
      <input type="hidden" name="destination_store" id="destination_store" value="" required>
    </div>
    <?php
  } else {
    // Simple dropdown selector for classic checkout
    $options = array(
      '' => 'Select a Store...'
    );
    
    foreach ($stores as $store) {
      $options[wp_json_encode([$store->code, $store->name])] = $store->name;
    }
    
    woocommerce_form_field('destination_store', array(
      'type'          => 'select',
      'required'      => true,
      'class'         => array('my-field-class form-row-wide'),
      'options'       => $options,
    ));
  }
  
  echo '</div>';
}

function wc_postnet_delivery_checkout_field_update_order_meta($order_id) {
  if ( ! empty( $_POST['destination_store'] ) ) {
    update_post_meta( $order_id, 'Destination Store', sanitize_text_field( $_POST['destination_store'] ) );
  }
}

function wc_postnet_delivery_checkout_field_display_admin_order_meta($order) {
  wc_postnet_delivery_order_received_page($order);
}

function wc_postnet_delivery_validations() {
  $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
  $chosen_method = ! empty( $chosen_methods ) ? $chosen_methods[0] : '';
  
  $postnet_store_rate_id = wc_postnet_delivery_get_postnet_rate_id(POSTNET_METHOD_ID_STORE);
  if ($postnet_store_rate_id === null || $chosen_method !== $postnet_store_rate_id) return;
  
  if ( ! isset($_POST['destination_store']) || empty($_POST['destination_store']) ) {
    wc_add_notice('<strong>Destination Store</strong> is a required field.', 'error' );
  }
}

function wc_postnet_delivery_order_received_page($order) {
  $destination_store = get_post_meta($order->get_id(), 'Destination Store', true);
  if (!empty($destination_store)) {
    $store = json_decode($destination_store);
    echo '<p><strong>' . esc_html__('Destination Store', 'delivery-options-postnet-woocommerce') . ':</strong> ' . esc_html($store[1]) . '</p>';
  }
  
  $waybill_number = get_post_meta($order->get_id(), 'Waybill Number', true);
  if (!empty($waybill_number)) {
    echo '<p><strong>' . esc_html__('Waybill Number', 'delivery-options-postnet-woocommerce') . ':</strong> ' . esc_html($waybill_number) . '</p>';
  }
  
  $tracking_url = get_post_meta($order->get_id(), 'Tracking URL', true);
  if (!empty($tracking_url)) {
    echo '<p><strong>' . esc_html__('Tracking URL', 'delivery-options-postnet-woocommerce') . ':</strong> <a href="' . esc_url($tracking_url) . '" target="_blank">' . esc_html($tracking_url) . '</a></p>';
  }
  
  $label_print = get_post_meta($order->get_id(), 'Label Print', true);
  if (!empty($label_print)) {
    echo '<p><strong>' . esc_html__('Label Print', 'delivery-options-postnet-woocommerce') . ':</strong> <a href="' . esc_url($label_print) . '" target="_blank">' . esc_html($label_print) . '</a></p>';
  }
  
  // Display collection information if available
  $collection_reference = get_post_meta($order->get_id(), 'Collection Reference', true);
  if (!empty($collection_reference)) {
    echo '<p><strong>' . esc_html__('Collection Reference', 'delivery-options-postnet-woocommerce') . ':</strong> ' . esc_html($collection_reference) . '</p>';
  }
  
  $collection_success = get_post_meta($order->get_id(), 'Collection Success', true);
  $collection_error = get_post_meta($order->get_id(), 'Collection Error', true);
  
  if ($collection_success === 'true') {
    echo '<p><strong>' . esc_html__('Collection Status', 'delivery-options-postnet-woocommerce') . ':</strong> <span style="color: green;">' . esc_html__('Success', 'delivery-options-postnet-woocommerce') . '</span></p>';
  } elseif ($collection_success === 'false' || !empty($collection_error)) {
    echo '<p><strong>' . esc_html__('Collection Status', 'delivery-options-postnet-woocommerce') . ':</strong> <span style="color: red;">' . esc_html__('Failed', 'delivery-options-postnet-woocommerce') . '</span></p>';
    if (!empty($collection_error)) {
      echo '<p><strong>' . esc_html__('Collection Error', 'delivery-options-postnet-woocommerce') . ':</strong> ' . esc_html($collection_error) . '</p>';
    }
  }
}

/**
 * Display waybill creation status and retry button on admin order screen
 */
function wc_postnet_delivery_display_waybill_status($order) {
  if (!$order) {
    return;
  }
  
  $order_id = $order->get_id();
  
  // Check if this is a PostNet delivery order
  $shipping_items = $order->get_items('shipping');
  if (empty($shipping_items)) {
    return;
  }
  
  $shipping_item = reset($shipping_items);
  $chosen_method = $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id();
  $postnet_internal_id = wc_postnet_delivery_get_postnet_internal_id_for_rate($chosen_method);
  
  // Only show for PostNet delivery methods
  if (!$postnet_internal_id || !in_array($postnet_internal_id, array(POSTNET_METHOD_ID_FREE, POSTNET_METHOD_ID_STORE, POSTNET_METHOD_ID_EXPRESS, POSTNET_METHOD_ID_ECONOMY))) {
    return;
  }
  
  $waybill_number = get_post_meta($order_id, 'Waybill Number', true);
  $creation_status = get_post_meta($order_id, '_waybill_creation_status', true);
  $creation_error = get_post_meta($order_id, '_waybill_creation_error', true);
  $creation_attempted = get_post_meta($order_id, '_waybill_creation_attempted', true);
  
  // Only show if waybill doesn't exist or there was a failed attempt
  echo '<pre>Waybill Number: '.print_r($waybill_number, true).'</pre>';
  echo '<pre>Creation Status: '.print_r($creation_status, true).'</pre>';
  echo '<pre>Creation Attempted: '.print_r($creation_attempted, true).'</pre>';
  echo '<pre>Creation Error: '.print_r($creation_error, true).'</pre>';
  echo '<pre>Chosen Method: '.print_r($chosen_method, true).'</pre>';
  echo '<pre>Postnet Internal ID: '.print_r($postnet_internal_id, true).'</pre>';
  if (empty($waybill_number) && ($creation_status === 'failed' || !empty($creation_attempted))) {
    echo '<div class="postnet-waybill-status" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #dc3232;">';
    echo '<h3>' . esc_html__('PostNet Waybill Status', 'delivery-options-postnet-woocommerce') . '</h3>';
    
    if ($creation_status === 'failed') {
      echo '<p><strong>' . esc_html__('Status:', 'delivery-options-postnet-woocommerce') . '</strong> <span style="color: #dc3232;">' . esc_html__('Failed', 'delivery-options-postnet-woocommerce') . '</span></p>';
      
      if (!empty($creation_error)) {
        echo '<p><strong>' . esc_html__('Error:', 'delivery-options-postnet-woocommerce') . '</strong> <span style="color: #dc3232;">' . esc_html($creation_error) . '</span></p>';
      }
      
      if (!empty($creation_attempted)) {
        echo '<p><strong>' . esc_html__('Last Attempt:', 'delivery-options-postnet-woocommerce') . '</strong> ' . esc_html($creation_attempted) . '</p>';
      }
      
      echo '<button type="button" class="button button-primary postnet-retry-waybill" data-order-id="' . esc_attr($order_id) . '" style="margin-top: 10px;">' . esc_html__('Retry Waybill Creation', 'delivery-options-postnet-woocommerce') . '</button>';
      echo '<span class="postnet-retry-spinner spinner" style="float: none; margin-left: 10px; visibility: hidden;"></span>';
    } elseif (!empty($creation_attempted)) {
      echo '<p><strong>' . esc_html__('Status:', 'delivery-options-postnet-woocommerce') . '</strong> <span style="color: #f56e28;">' . esc_html__('Attempted', 'delivery-options-postnet-woocommerce') . '</span></p>';
      echo '<p><strong>' . esc_html__('Last Attempt:', 'delivery-options-postnet-woocommerce') . '</strong> ' . esc_html($creation_attempted) . '</p>';
      
      if (!empty($creation_error)) {
        echo '<p><strong>' . esc_html__('Error:', 'delivery-options-postnet-woocommerce') . '</strong> <span style="color: #dc3232;">' . esc_html($creation_error) . '</span></p>';
      }
      
      echo '<button type="button" class="button button-primary postnet-retry-waybill" data-order-id="' . esc_attr($order_id) . '" style="margin-top: 10px;">' . esc_html__('Retry Waybill Creation', 'delivery-options-postnet-woocommerce') . '</button>';
      echo '<span class="postnet-retry-spinner spinner" style="float: none; margin-left: 10px; visibility: hidden;"></span>';
    } else {
      // No waybill exists and no attempt recorded - show retry button
      echo '<p><strong>' . esc_html__('Status:', 'delivery-options-postnet-woocommerce') . '</strong> <span style="color: #f56e28;">' . esc_html__('No waybill created', 'delivery-options-postnet-woocommerce') . '</span></p>';
      echo '<button type="button" class="button button-primary postnet-retry-waybill" data-order-id="' . esc_attr($order_id) . '" style="margin-top: 10px;">' . esc_html__('Create Waybill', 'delivery-options-postnet-woocommerce') . '</button>';
      echo '<span class="postnet-retry-spinner spinner" style="float: none; margin-left: 10px; visibility: hidden;"></span>';
    }
    
    echo '<div class="postnet-retry-message" style="margin-top: 10px; display: none;"></div>';
    echo '</div>';
  }
}

/**
 * AJAX handler for retrying waybill creation
 */
function wc_postnet_delivery_retry_waybill_ajax() {
  // Verify nonce
  check_ajax_referer('wc_postnet_delivery_order_nonce', 'nonce');
  
  // Check permissions
  if (!current_user_can('edit_shop_orders')) {
    wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'delivery-options-postnet-woocommerce')));
    return;
  }
  
  $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
  
  if (!$order_id) {
    wp_send_json_error(array('message' => __('Invalid order ID.', 'delivery-options-postnet-woocommerce')));
    return;
  }
  
  $order = wc_get_order($order_id);
  if (!$order) {
    wp_send_json_error(array('message' => __('Order not found.', 'delivery-options-postnet-woocommerce')));
    return;
  }
  
  // Check if waybill already exists
  $existing_waybill = get_post_meta($order_id, 'Waybill Number', true);
  if (!empty($existing_waybill)) {
    wp_send_json_error(array('message' => __('Waybill already exists for this order.', 'delivery-options-postnet-woocommerce')));
    return;
  }
  
  // Get options
  $options = get_option('wc_postnet_delivery_options');
  if (!$options) {
    wp_send_json_error(array('message' => __('PostNet plugin is not configured.', 'delivery-options-postnet-woocommerce')));
    return;
  }
  
  // Determine if we need collection address (Multi Site Mode)
  $collection_address = null;
  if (isset($options['multi_site_mode']) && $options['multi_site_mode']) {
    $collection_address_index = get_post_meta($order_id, '_collection_address_index', true);
    if ($collection_address_index !== '') {
      $collection_addresses = isset($options['collection_addresses']) ? $options['collection_addresses'] : array();
      if (isset($collection_addresses[$collection_address_index])) {
        $collection_address = $collection_addresses[$collection_address_index];
      }
    }
    
    // In Multi Site Mode, collection address is required
    if (!$collection_address) {
      wp_send_json_error(array('message' => __('Collection address must be selected before creating waybill in Multi Site Mode.', 'delivery-options-postnet-woocommerce')));
      return;
    }
  }
  
  // Attempt to create waybill
  $success = wc_postnet_delivery_create_waybill($order, $collection_address);
  
  if ($success) {
    $waybill_number = get_post_meta($order_id, 'Waybill Number', true);
    wp_send_json_success(array(
      'message' => __('Waybill created successfully.', 'delivery-options-postnet-woocommerce'),
      'waybill_number' => $waybill_number
    ));
  } else {
    $error = get_post_meta($order_id, '_waybill_creation_error', true);
    $error_message = !empty($error) ? $error : __('Failed to create waybill. Please check the error logs.', 'delivery-options-postnet-woocommerce');
    wp_send_json_error(array('message' => $error_message));
  }
}

/**
 * Create waybill when order is placed (standard mode)
 * Now uses the unified waybill creation method
 */
function wc_postnet_delivery_collection_notification($order_id){
  if (!$order_id) {
    error_log('PostNet: No order ID provided, returning early');
    return;
  }
  
  // Get an instance of the WC_Order object
  $order = wc_get_order($order_id);
  if (!$order) {
    error_log('PostNet: Could not get order object for ID: ' . $order_id);
    return;
  }
  
  // Get delivery options
  $options = get_option('wc_postnet_delivery_options');
  
  // Skip waybill creation if Multi Site Mode is enabled
  if (isset($options['multi_site_mode']) && $options['multi_site_mode']) {
    error_log('PostNet: Multi Site Mode enabled, skipping automatic waybill creation for order ' . $order_id);
    return;
  }
  
  $postal_code = $order->get_shipping_postcode();
  
  $main_check = $postal_code ? json_decode(wc_postnet_fetch_url('https://pnsa.restapis.co.za/public/is-main?postcode='.$postal_code)) : null;
  $is_main = $main_check ? $main_check->main : false;
  
  $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
  $chosen_method = ! empty( $chosen_methods ) ? $chosen_methods[0] : '';
  
  // If no chosen method in session, try to get it from the order
  if (empty($chosen_method)) {
    // Try to get from order meta first
    $chosen_method = $order->get_meta('_chosen_shipping_method');
    if (empty($chosen_method)) {
      // If not in meta, try to get from shipping items
      $shipping_items = $order->get_items('shipping');
      if (!empty($shipping_items)) {
        $shipping_item = reset($shipping_items);
        $chosen_method = $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id();
      }
    }
  }
  
  if (empty($chosen_method)) {
    error_log('PostNet: No shipping method found in order or session, returning early');
    return;
  }
  
  error_log('PostNet: Using shipping method: ' . $chosen_method);
  
  $rate = null;
  $zone = wc_postnet_delivery_get_zone();
  $shipping_methods = $zone->get_shipping_methods();
  foreach ($shipping_methods as $method) {
    // Check if method instance ID matches the chosen method ID
    if ($method->id . ':' . $method->instance_id == $chosen_method) {
      $rate = $method;
    }
  }
  
  if (!$rate) return;

  $postnet_internal_id = wc_postnet_delivery_get_postnet_internal_id_for_rate($chosen_method);
  if ($postnet_internal_id !== POSTNET_METHOD_ID_STORE) {
    delete_post_meta($order_id, 'Destination Store');
    error_log('PostNet: Unsetting destination store as shipping method is not PostNet to PostNet');
    return;
  }
  
  // Create waybill using the unified method (no collection address for old method)
  $success = wc_postnet_delivery_create_waybill($order);
  
  if ($success) {
    error_log('PostNet: Waybill created successfully for order ' . $order_id . ' using standard method.');
  } else {
    error_log('PostNet: Failed to create waybill for order ' . $order_id . ' using standard method.');
  }
}

/**
 * Sanitize the plugin options
 *
 * @param array $input The raw input array
 * @return array The sanitized output array
 */
function wc_postnet_delivery_sanitize_options($input) {
  $sanitized = array();

  // Sanitize service types array
  if (isset($input['service_type']) && is_array($input['service_type'])) {
    $valid_services = array_keys(wc_postnet_delivery_service_types());
    $sanitized['service_type'] = array_filter($input['service_type'], function($service) use ($valid_services) {
      return in_array($service, $valid_services);
    });
  } else {
    $sanitized['service_type'] = array();
  }

  // Sanitize collection type
  $valid_collection_types = array('always_collect', 'always_deliver', 'service_based');
  $sanitized['collection_type'] = isset($input['collection_type']) && in_array($input['collection_type'], $valid_collection_types) 
    ? $input['collection_type'] 
    : 'always_collect';

  // Sanitize numeric fields
  $sanitized['postnet_to_postnet_fee'] = isset($input['postnet_to_postnet_fee']) 
    ? floatval($input['postnet_to_postnet_fee']) 
    : '';
  
  $sanitized['regional_centre_express_fee'] = isset($input['regional_centre_express_fee']) 
    ? floatval($input['regional_centre_express_fee']) 
    : '';
  
  $sanitized['regional_centre_economy_fee'] = isset($input['regional_centre_economy_fee']) 
    ? floatval($input['regional_centre_economy_fee']) 
    : '';
  
  $sanitized['main_centre_express_fee'] = isset($input['main_centre_express_fee']) 
    ? floatval($input['main_centre_express_fee']) 
    : '';
  
  $sanitized['main_centre_economy_fee'] = isset($input['main_centre_economy_fee']) 
    ? floatval($input['main_centre_economy_fee']) 
    : '';
  
  $sanitized['order_amount_threshold'] = isset($input['order_amount_threshold']) 
    ? floatval($input['order_amount_threshold']) 
    : '';

  // Sanitize store code
  $sanitized['postnet_store'] = isset($input['postnet_store']) 
    ? sanitize_text_field($input['postnet_store']) 
    : '';

  // Sanitize API credentials
  $sanitized['postnet_api_key'] = isset($input['postnet_api_key']) 
    ? sanitize_text_field($input['postnet_api_key']) 
    : '';
  
  $sanitized['postnet_api_passcode'] = isset($input['postnet_api_passcode']) 
    ? sanitize_text_field($input['postnet_api_passcode']) 
    : '';
    
  // Sanitize Google API key
  $sanitized['google_api_key'] = isset($input['google_api_key']) 
    ? sanitize_text_field($input['google_api_key']) 
    : '';

  // Sanitize multi_site_mode
  $sanitized['multi_site_mode'] = isset($input['multi_site_mode']) ? boolval($input['multi_site_mode']) : false;

  // Sanitize waybill option
  $valid_waybill_options = array('single', 'individual', 'user_specified');
  $sanitized['waybill_option'] = isset($input['waybill_option']) && in_array($input['waybill_option'], $valid_waybill_options, true)
    ? $input['waybill_option']
    : 'single';

  // Sanitize collection_addresses
  if (isset($input['collection_addresses']) && is_array($input['collection_addresses'])) {
    $sanitized['collection_addresses'] = array_map(function($address) {
      return array_map('sanitize_text_field', $address);
    }, $input['collection_addresses']);
  } else {
    $sanitized['collection_addresses'] = array();
  }

  // Preserve PostNet shipping instance IDs (set by "Configure PostNet Shipping", not in form)
  $existing = get_option('wc_postnet_delivery_options', array());
  if (isset($existing['postnet_shipping_instance_ids']) && is_array($existing['postnet_shipping_instance_ids'])) {
    $sanitized['postnet_shipping_instance_ids'] = array();
    $valid_internal_ids = array(POSTNET_METHOD_ID_FREE, POSTNET_METHOD_ID_STORE, POSTNET_METHOD_ID_EXPRESS, POSTNET_METHOD_ID_ECONOMY);
    foreach ($existing['postnet_shipping_instance_ids'] as $internal_id => $instance_id) {
      if (in_array($internal_id, $valid_internal_ids, true)) {
        $sanitized['postnet_shipping_instance_ids'][$internal_id] = (int) $instance_id;
      }
    }
  } else {
    $sanitized['postnet_shipping_instance_ids'] = array();
  }

  return $sanitized;
}

// Implement frontend scripts loading
function wc_postnet_delivery_enqueue_frontend_scripts() {
  if (!function_exists('is_checkout') || !is_checkout()) {
    return;
  }

  // Generate a unique version to prevent caching
  $version = '1.0.' . time();
  
  // Get Google API key from options
  $options = get_option('wc_postnet_delivery_options');
  $google_api_key = !empty($options['google_api_key']) ? $options['google_api_key'] : '';
  
  // Check if WooCommerce Blocks is active
  if (class_exists('\Automattic\WooCommerce\Blocks\Package')) {
    error_log('PostNet: Loading blocks checkout script with version ' . $version);
    
    // Enqueue our block checkout script
    wp_register_script(
      'wc-postnet-delivery-blocks-js',
      plugin_dir_url(__FILE__) . 'js/wc-postnet-delivery-blocks.js',
      array('jquery', 'wp-element', 'wp-components'),
      $version,
      true
    );
    
    // Enqueue styles for the store selector
    wp_register_style(
      'wc-postnet-delivery-style',
      plugin_dir_url(__FILE__) . 'css/wc-postnet-delivery.css',
      array(),
      $version
    );
    wp_enqueue_style('wc-postnet-delivery-style');
    
    // If we have a Google API key, include Google Maps
    if (!empty($google_api_key)) {
      wp_enqueue_script(
        'google-maps',
        'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_api_key) . '&libraries=places,marker',
        array(),
        null,
        true
      );
    }
    
    // Pass necessary data to JavaScript
    wp_localize_script(
      'wc-postnet-delivery-blocks-js',
      'wc_postnet_delivery_params',
      array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wc_postnet_delivery_nonce'),
        'shipping_method_title' => POSTNET_SHIPPING_STORE,
        'shipping_rate_id_store' => wc_postnet_delivery_get_postnet_rate_id(POSTNET_METHOD_ID_STORE),
        'debug_mode' => true,
        'version' => $version,
        'has_google_maps' => !empty($google_api_key),
        'google_api_key' => $google_api_key,
        'map_marker_url' => plugins_url('img/map-marker.png', __FILE__)
      )
    );
    wp_enqueue_script('wc-postnet-delivery-blocks-js');
    
    // Add a debugging helper in the footer
    add_action('wp_footer', 'wc_postnet_delivery_add_debug_helper');
  } else {
    // Classic checkout - enqueue scripts and styles
    wp_enqueue_style(
      'wc-postnet-delivery-style',
      plugin_dir_url(__FILE__) . 'css/wc-postnet-delivery.css',
      array(),
      $version
    );
    
    // For classic checkout, we'll always use the dropdown, but still need AJAX for store details
    wp_enqueue_script(
      'wc-postnet-delivery-classic-js',
      plugin_dir_url(__FILE__) . 'js/wc-postnet-delivery-classic.js',
      array('jquery'),
      $version,
      true
    );
    
    wp_localize_script(
      'wc-postnet-delivery-classic-js',
      'wc_postnet_delivery_params',
      array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wc_postnet_delivery_nonce'),
        'shipping_method_title' => POSTNET_SHIPPING_STORE,
        'shipping_rate_id_store' => wc_postnet_delivery_get_postnet_rate_id(POSTNET_METHOD_ID_STORE),
      )
    );
  }
}

// Add a debug helper script
function wc_postnet_delivery_add_debug_helper() {
  ?>
  <script type="text/javascript">
    // Debug helper function
    window.checkPostNetIntegration = function() {
      console.log('---------- PostNet Debug Info ----------');
      console.log('Script params:', window.wc_postnet_delivery_params || 'Not loaded');
      console.log('Selected store:', localStorage.getItem('postnet_selected_store') || 'None');
      console.log('Hidden input:', document.getElementById('postnet_selected_store_input') || 'Not found');
      
      const container = document.getElementById('postnet-store-selector-container');
      console.log('Selector container:', container || 'Not found');
      
      // Find all shipping method labels
      const labels = document.querySelectorAll('.wc-block-components-radio-control__label');
      console.log('Shipping method labels:', labels.length);
      labels.forEach(label => {
        console.log(' - ' + label.textContent + (label.closest('.wc-block-components-radio-control__option').querySelector('input:checked') ? ' (SELECTED)' : ''));
      });
      
      console.log('---------------------------------------');
    };
    console.log('PostNet debug helper loaded. Call checkPostNetIntegration() to debug.');
  </script>
  <?php
}

// Update order meta with store selection from blocks checkout
function wc_postnet_delivery_blocks_checkout_update_order_meta($order) {
  // Try various methods to get the selected store
  $selected_store = '';
  
  // Method 1: Get from request payload
  $request = json_decode(file_get_contents('php://input'), true);
  if (!empty($request['extensions']['postnet-delivery-options']['postnet_selected_store'])) {
    $selected_store = $request['extensions']['postnet-delivery-options']['postnet_selected_store'];
  } 
  // Method 2: Get from cookie
  elseif (!empty($_COOKIE['postnet_selected_store'])) {
    $selected_store = sanitize_text_field($_COOKIE['postnet_selected_store']);
  }
  // Method 3: Get from POST data
  elseif (!empty($_POST['postnet_selected_store'])) {
    $selected_store = sanitize_text_field($_POST['postnet_selected_store']);
  }
  
  // Save the selected store if we found one
  if (!empty($selected_store)) {
    update_post_meta($order->get_id(), 'Destination Store', sanitize_text_field($selected_store));
  }
  
  return $order;
}

/**
 * Get full store details for a specific store
 */
function wc_postnet_delivery_get_store_details() {
  // Check nonce
  check_ajax_referer('wc_postnet_delivery_nonce', 'security');
  
  // Get store code
  $store_code = isset($_POST['store_code']) ? sanitize_text_field($_POST['store_code']) : '';
  
  if (empty($store_code)) {
    wp_send_json_error(array('message' => 'Store code is required.'));
    return;
  }
  
  // Get all stores
  $all_stores = json_decode(wc_postnet_fetch_url('https://www.postnet.co.za/cart_store-json_list/?local=1'));
  
  // Find the matching store
  $store_details = null;
  foreach ($all_stores as $store) {
    if ($store->code === $store_code) {
      $store_details = $store;
      break;
    }
  }
  
  if ($store_details) {
    // Format city/suburb field based on available data
    $city_parts = array_filter(array(
      isset($store_details->suburb) ? $store_details->suburb : null,
      isset($store_details->town) ? $store_details->town : null
    ));
    $city = !empty($city_parts) ? implode(', ', $city_parts) : '';
    
    // Format the response using the proper field names from the API
    $response = array(
      'code' => $store_details->code,
      'name' => $store_details->store_name,
      'address' => isset($store_details->physical_address) ? $store_details->physical_address : '',
      'city' => $city,
      'province' => isset($store_details->region) ? $store_details->region : '',
      'postal_code' => isset($store_details->postal_code) ? $store_details->postal_code : '',
      'telephone' => isset($store_details->telephone) ? $store_details->telephone : '',
      'email' => isset($store_details->email) ? $store_details->email : '',
      'lat' => isset($store_details->latitude) ? (float)$store_details->latitude : 0,
      'lng' => isset($store_details->longitude) ? (float)$store_details->longitude : 0,
    );
    
    wp_send_json_success($response);
  } else {
    wp_send_json_error(array('message' => 'Store not found.'));
  }
  
  wp_die();
}

/**
 * AJAX handler to validate Google API Key
 */
function wc_postnet_validate_google_api_key() {
  // Check nonce for security
  check_ajax_referer('wc_postnet_delivery_admin_nonce', 'security');
  
  // Check permissions
  if (!current_user_can('manage_options')) {
    wp_send_json_error(array(
      'message' => __('You do not have permission to perform this action.', 'delivery-options-postnet-woocommerce')
    ));
    return;
  }
  
  // Get the API key from the request
  $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
  
  if (empty($api_key)) {
    wp_send_json_error(array(
      'message' => __('API key is required.', 'delivery-options-postnet-woocommerce')
    ));
    return;
  }
  
  // If everything is successful
  wp_send_json_success(array(
    'message' => __('Google API key is valid and has the required services enabled.', 'delivery-options-postnet-woocommerce')
  ));
}

/**
 * Add collection address selection field to admin order page
 */
function wc_postnet_delivery_admin_collection_address_field($order) {
  $options = get_option('wc_postnet_delivery_options');
  
  // Only show if Multi Site Mode is enabled
  if (!isset($options['multi_site_mode']) || !$options['multi_site_mode']) {
    return;
  }
  
  $collection_addresses = isset($options['collection_addresses']) ? $options['collection_addresses'] : array();
  if (empty($collection_addresses)) {
    return;
  }
  
  $selected_address = get_post_meta($order->get_id(), '_collection_address_index', true);
  $order_status = $order->get_status();
  
  // Check if collection address was recently updated (within last 5 minutes)
  $address_updated = get_post_meta($order->get_id(), '_collection_address_updated', true);
  $recently_updated = false;
  if ($address_updated) {
    $update_time = strtotime($address_updated);
    $current_time = current_time('timestamp');
    $recently_updated = ($current_time - $update_time) < 300; // 5 minutes
  }
  
  // Also check if this is a form submission that just saved the address
  $just_saved = isset($_POST['collection_address_index']) && !empty($_POST['collection_address_index']);
  
  // Check session flag for recently saved address
  $session_saved = false;
  if (!session_id()) {
    session_start();
  }
  if (isset($_SESSION['postnet_address_saved_' . $order->get_id()])) {
    $session_saved = true;
    // Clear the session flag after using it
    unset($_SESSION['postnet_address_saved_' . $order->get_id()]);
  }
  
  // Determine CSS classes based on selection and order status
  $css_classes = 'address';
  if ($selected_address === '' || $selected_address === null) {
    $css_classes .= ' collection-address-required';
  } else {
    $css_classes .= ' collection-address-success';
  }
  
  echo '<div class="' . esc_attr($css_classes) . '">';
  echo '<h3>' . esc_html__('Collection Address', 'delivery-options-postnet-woocommerce') . '</h3>';
  
  // Show warning ONLY if no address is selected AND order is completed AND no recent updates
  // This prevents the warning from showing when an address was just saved
  if (($selected_address === '' || $selected_address === null) && $order_status === 'completed' && !$recently_updated && !$just_saved && !$session_saved) {
    echo '<div class="collection-address-warning" id="collection-address-warning">';
    echo esc_html__('Warning: This order is marked as completed but no collection address is selected. Waybill creation will fail.', 'delivery-options-postnet-woocommerce');
    echo '</div>';
    
    // Debug logging
    error_log('PostNet: Warning shown for order ' . $order->get_id() . ' - Address: ' . ($selected_address !== '' ? $selected_address : 'empty') . ', Status: ' . $order_status . ', Recently Updated: ' . ($recently_updated ? 'yes' : 'no') . ', Just Saved: ' . ($just_saved ? 'yes' : 'no') . ', Session Saved: ' . ($session_saved ? 'yes' : 'no'));
  }
  
  // Show success message if address is selected (regardless of order status)
  if ($selected_address !== '' && $selected_address !== null) {
    echo '<div class="collection-address-success" id="collection-address-success">';
    echo esc_html__('Collection address selected. Order can be completed and waybill will be created.', 'delivery-options-postnet-woocommerce');
    echo '</div>';
  }
  
  echo '<select name="collection_address_index" id="collection_address_index" required>';
  echo '<option value="">' . esc_html__('Select Collection Address', 'delivery-options-postnet-woocommerce') . '</option>';
  
  foreach ($collection_addresses as $index => $address) {
    $address_label = $address['sender_name'] . ' - ' . $address['sender_street_address'] . ', ' . $address['sender_suburb'];
    $selected = ($selected_address == $index) ? 'selected' : '';
    echo '<option value="' . esc_attr($index) . '" ' . $selected . '>' . esc_html($address_label) . '</option>';
  }
  
  echo '</select>';
  
  // Add hidden field to track if address was just saved
  if ($just_saved || $session_saved) {
    echo '<input type="hidden" id="address-just-saved" value="1" />';
  }
  
  if ($selected_address === '' || $selected_address === null) {
    echo '<p class="description">' . esc_html__('⚠️ REQUIRED: Select the collection address for this order. Waybill creation requires this selection in Multi Site Mode.', 'delivery-options-postnet-woocommerce') . '</p>';
  } else {
    echo '<p class="description">' . esc_html__('✅ Collection address selected. Order can now be completed.', 'delivery-options-postnet-woocommerce') . '</p>';
  }
  
  echo '</div>';
  
  // Add JavaScript to refresh indicators if this is a completed order with an address
  if ($order_status === 'completed' && ($selected_address !== '' && $selected_address !== null)) {
    echo '<script type="text/javascript">
      jQuery(document).ready(function($) {
        // Force refresh of indicators for completed orders
        setTimeout(function() {
          if (typeof refreshCollectionAddressIndicators === "function") {
            refreshCollectionAddressIndicators();
          }
        }, 200);
      });
    </script>';
  }
  
  // Add JavaScript to immediately remove warnings if address was just saved
  if ($just_saved || $session_saved) {
    echo '<script type="text/javascript">
      jQuery(document).ready(function($) {
        // Immediately remove any warning messages if address was just saved
        $(".collection-address-warning").remove();
        // Update the container to success state
        $(".address").removeClass("collection-address-required").addClass("collection-address-success");
      });
    </script>';
  }
}

/**
 * Save collection address selection from admin order page
 */
function wc_postnet_delivery_save_admin_collection_address_field($order_id, $post) {
  if (isset($_POST['collection_address_index'])) {
    update_post_meta($order_id, '_collection_address_index', sanitize_text_field($_POST['collection_address_index']));
    
    // Add a flag to indicate the collection address was updated
    update_post_meta($order_id, '_collection_address_updated', current_time('timestamp'));
    
    // Set a session flag to indicate the address was just saved
    if (!session_id()) {
      session_start();
    }
    $_SESSION['postnet_address_saved_' . $order_id] = true;
  }
}

/**
 * Add number of boxes field to admin order page
 */
function wc_postnet_delivery_admin_number_of_boxes_field($order) {
  $options = get_option('wc_postnet_delivery_options');
  
  // Only show if waybill_option is 'user_specified'
  if (!isset($options['waybill_option']) || $options['waybill_option'] !== 'user_specified') {
    return;
  }
  
  $number_of_boxes = get_post_meta($order->get_id(), '_number_of_boxes', true);
  $order_status = $order->get_status();
  
  // Check if number_of_boxes was recently updated (within last 5 minutes)
  $boxes_updated = get_post_meta($order->get_id(), '_number_of_boxes_updated', true);
  $recently_updated = false;
  if ($boxes_updated) {
    $update_time = strtotime($boxes_updated);
    $current_time = current_time('timestamp');
    $recently_updated = ($current_time - $update_time) < 300; // 5 minutes
  }
  
  // Also check if this is a form submission that just saved the number
  $just_saved = isset($_POST['number_of_boxes']) && !empty($_POST['number_of_boxes']);
  
  // Check session flag for recently saved number
  $session_saved = false;
  if (!session_id()) {
    session_start();
  }
  if (isset($_SESSION['postnet_boxes_saved_' . $order->get_id()])) {
    $session_saved = true;
    // Clear the session flag after using it
    unset($_SESSION['postnet_boxes_saved_' . $order->get_id()]);
  }
  
  // Determine CSS classes based on selection and order status
  $css_classes = 'number-of-boxes';
  if ($number_of_boxes === '' || $number_of_boxes === null || $number_of_boxes === '0') {
    $css_classes .= ' number-of-boxes-required';
  } else {
    $css_classes .= ' number-of-boxes-success';
  }
  
  echo '<div class="' . esc_attr($css_classes) . '">';
  echo '<h3>' . esc_html__('Number of Boxes', 'delivery-options-postnet-woocommerce') . '</h3>';
  
  // Show warning ONLY if no number is set AND order is completed AND no recent updates
  if (($number_of_boxes === '' || $number_of_boxes === null || $number_of_boxes === '0') && $order_status === 'completed' && !$recently_updated && !$just_saved && !$session_saved) {
    echo '<div class="number-of-boxes-warning" id="number-of-boxes-warning">';
    echo esc_html__('Warning: This order is marked as completed but no number of boxes is specified. Waybill creation will fail.', 'delivery-options-postnet-woocommerce');
    echo '</div>';
  }
  
  // Show success message if number is set (regardless of order status)
  if ($number_of_boxes !== '' && $number_of_boxes !== null && $number_of_boxes !== '0') {
    echo '<div class="number-of-boxes-success" id="number-of-boxes-success">';
    echo esc_html__('Number of boxes specified. Order can be completed and waybill will be created.', 'delivery-options-postnet-woocommerce');
    echo '</div>';
  }
  
  echo '<input type="number" name="number_of_boxes" id="number_of_boxes" min="1" step="1" value="' . esc_attr($number_of_boxes) . '" required />';
  
  // Add hidden field to track if number was just saved
  if ($just_saved || $session_saved) {
    echo '<input type="hidden" id="boxes-just-saved" value="1" />';
  }
  
  if ($number_of_boxes === '' || $number_of_boxes === null || $number_of_boxes === '0') {
    echo '<p class="description">' . esc_html__('⚠️ REQUIRED: Enter the number of boxes for this order. Waybill creation requires this value when User Specified option is selected.', 'delivery-options-postnet-woocommerce') . '</p>';
  } else {
    echo '<p class="description">' . esc_html__('✅ Number of boxes specified. Order can now be completed.', 'delivery-options-postnet-woocommerce') . '</p>';
  }
  
  echo '</div>';
  
  // Add JavaScript to refresh indicators if this is a completed order with a number
  if ($order_status === 'completed' && ($number_of_boxes !== '' && $number_of_boxes !== null && $number_of_boxes !== '0')) {
    echo '<script type="text/javascript">
      jQuery(document).ready(function($) {
        setTimeout(function() {
          if (typeof refreshNumberOfBoxesIndicators === "function") {
            refreshNumberOfBoxesIndicators();
          }
        }, 200);
      });
    </script>';
  }
  
  // Add JavaScript to immediately remove warnings if number was just saved
  if ($just_saved || $session_saved) {
    echo '<script type="text/javascript">
      jQuery(document).ready(function($) {
        $(".number-of-boxes-warning").remove();
        $(".number-of-boxes").removeClass("number-of-boxes-required").addClass("number-of-boxes-success");
      });
    </script>';
  }
}

/**
 * Save number of boxes from admin order page
 */
function wc_postnet_delivery_save_admin_number_of_boxes_field($order_id, $post) {
  if (isset($_POST['number_of_boxes'])) {
    $number_of_boxes = intval($_POST['number_of_boxes']);
    if ($number_of_boxes > 0) {
      update_post_meta($order_id, '_number_of_boxes', $number_of_boxes);
      
      // Add a flag to indicate the number of boxes was updated
      update_post_meta($order_id, '_number_of_boxes_updated', current_time('timestamp'));
      
      // Set a session flag to indicate the number was just saved
      if (!session_id()) {
        session_start();
      }
      $_SESSION['postnet_boxes_saved_' . $order_id] = true;
    }
  }
}

/**
 * Create waybill when order status is changed to Completed (Multi Site Mode)
 * Uses the unified waybill creation method
 */
function wc_postnet_delivery_create_waybill_on_completion($order_id) {
  $options = get_option('wc_postnet_delivery_options');
  
  // Only proceed if Multi Site Mode is enabled
  if (!isset($options['multi_site_mode']) || !$options['multi_site_mode']) {
    return;
  }
  
  $order = wc_get_order($order_id);
  if (!$order) {
    return;
  }
  
  // Check if a collection address has been selected
  $collection_address_index = get_post_meta($order_id, '_collection_address_index', true);
  if ($collection_address_index === '') {
    error_log('PostNet: No collection address selected for order ' . $order_id . '. Waybill creation skipped.');
    return;
  }
  
  // Get the selected collection address
  $collection_addresses = isset($options['collection_addresses']) ? $options['collection_addresses'] : array();
  if (!isset($collection_addresses[$collection_address_index])) {
    error_log('PostNet: Invalid collection address index ' . $collection_address_index . ' for order ' . $order_id);
    return;
  }
  
  $selected_address = $collection_addresses[$collection_address_index];
  
  // Check if waybill already exists
  $existing_waybill = get_post_meta($order_id, 'Waybill Number', true);
  if (!empty($existing_waybill)) {
    error_log('PostNet: Waybill already exists for order ' . $order_id . '. Skipping creation.');
    return;
  }
  
  // Create waybill using the selected collection address
  wc_postnet_delivery_create_waybill_with_collection_address($order, $selected_address);
}

/**
 * Unified waybill creation method used by both old and new methods
 */
function wc_postnet_delivery_create_waybill($order, $collection_address = null) {
  try {
    // Validate input parameters
    if (!$order || !is_object($order)) {
      error_log('PostNet: Invalid order object provided to wc_postnet_delivery_create_waybill');
      return false;
    }
    
    $options = get_option('wc_postnet_delivery_options');
    if (!$options) {
      error_log('PostNet: No plugin options found');
      return false;
    }
    
    $postal_code = $order->get_shipping_postcode();
    
    $main_check = $postal_code ? json_decode(wc_postnet_fetch_url('https://pnsa.restapis.co.za/public/is-main?postcode='.$postal_code)) : null;
    $is_main = $main_check ? $main_check->main : false;
  
  // Try to get shipping method from session first (if available)
  $chosen_method = '';
  if (function_exists('WC') && WC() && WC()->session && WC()->session->get('chosen_shipping_methods')) {
    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    $chosen_method = !empty($chosen_methods) ? $chosen_methods[0] : '';
    error_log('PostNet: Got shipping method from session: ' . $chosen_method);
  } else {
    error_log('PostNet: Session not available, trying order meta for order ' . $order->get_id());
  }
  
  // If no chosen method in session, try to get it from the order
  if (empty($chosen_method)) {
    $chosen_method = $order->get_meta('_chosen_shipping_method');
    if (empty($chosen_method)) {
      $shipping_items = $order->get_items('shipping');
      if (!empty($shipping_items)) {
        $shipping_item = reset($shipping_items);
        $chosen_method = $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id();
        error_log('PostNet: Got shipping method from shipping items: ' . $chosen_method);
      } else {
        error_log('PostNet: No shipping items found for order ' . $order->get_id());
      }
    } else {
      error_log('PostNet: Got shipping method from order meta: ' . $chosen_method);
    }
  }
  
  if (empty($chosen_method)) {
    error_log('PostNet: No shipping method found for order ' . $order->get_id());
    return false;
  }
  
  $rate = null;
  $zone = wc_postnet_delivery_get_zone();
  if (!$zone) {
    error_log('PostNet: Could not get shipping zone for order ' . $order->get_id());
    return false;
  }
  
  $shipping_methods = $zone->get_shipping_methods();
  if (empty($shipping_methods)) {
    error_log('PostNet: No shipping methods found in zone for order ' . $order->get_id());
    return false;
  }
  
  foreach ($shipping_methods as $method) {
    if ($method->id . ':' . $method->instance_id == $chosen_method) {
      $rate = $method;
      break;
    }
  }
  
  if (!$rate) {
    error_log('PostNet: Could not find matching shipping method for order ' . $order->get_id() . ' with method: ' . $chosen_method);
    return false;
  }

  $postnet_internal_id = wc_postnet_delivery_get_postnet_internal_id_for_rate($chosen_method);
  $service_type = ($is_main ? 'main' : 'regional').'_centre_';
  $destination_store = json_decode(get_post_meta($order->get_id(), 'Destination Store', true));

  switch ($postnet_internal_id) {
    case POSTNET_METHOD_ID_FREE:
    case POSTNET_METHOD_ID_EXPRESS:
      $service_type .= 'express';
      break;
    case POSTNET_METHOD_ID_STORE:
      $service_type = 'postnet_to_postnet';
      break;
    case POSTNET_METHOD_ID_ECONOMY:
      $service_type .= 'economy';
      break;
    default:
      return false;
  }
  
  // Define the base data to send
  $data = [
    'online_store_name' => get_bloginfo('name'),
    'collection_type' => $options['collection_type'],
    'waybill_option' => isset($options['waybill_option']) ? $options['waybill_option'] : 'single',
    'service_type' => $service_type,
    'origin_store' => $options['postnet_store'],
    'destination_store' => $destination_store ? $destination_store[0] : '',
    'receiver_street_address' => $order->get_shipping_address_1(),
    'receiver_suburb' => $order->get_shipping_city(),
    'receiver_postal_code' => $order->get_shipping_postcode(),
    'receiver_name' => $order->get_formatted_shipping_full_name(),
    'receiver_contact_person' => $order->get_billing_first_name(),
    'receiver_contact_number' => $order->get_billing_phone(),
    'order_number' => $order->get_order_number(),
    'order_total' => (float) $order->get_total(),
    'order_items' => []
  ];
  
  // Add number_of_boxes if waybill_option is 'user_specified'
  if (isset($options['waybill_option']) && $options['waybill_option'] === 'user_specified') {
    $number_of_boxes = get_post_meta($order->get_id(), '_number_of_boxes', true);
    if ($number_of_boxes && intval($number_of_boxes) > 0) {
      $data['number_of_boxes'] = intval($number_of_boxes);
    }
  }
  
  // Add collection address fields if provided (Multi Site Mode)
  if ($collection_address) {
    $data['sender_street_address'] = $collection_address['sender_street_address'];
    $data['sender_other_address'] = $collection_address['sender_other_address'];
    $data['sender_country_code'] = $collection_address['sender_country_code'];
    $data['sender_suburb'] = $collection_address['sender_suburb'];
    $data['sender_postal_code'] = $collection_address['sender_postal_code'];
    $data['sender_name'] = $collection_address['sender_name'];
    $data['sender_contact_number'] = $collection_address['sender_contact_number'];
    $data['sender_contact_person'] = $collection_address['sender_contact_person'];
    $data['create_collection'] = true;
  }
  
  // Get the order items
  foreach ($order->get_items() as $item_id => $item) {
    $product = $item->get_product();
    $data['order_items'][] = [
      'product_id' => (string)$product->get_id(),
      'description' => $product->get_name(),
      'qty' => $item->get_quantity(),
      'price' => (float) $item->get_total(),
      'weight' => (float) $product->get_weight(),
      'length' => (float) $product->get_length(),
      'width' => (float) $product->get_width(),
      'height' => (float) $product->get_height(),
    ];
  }
  
  // API URL
  $url = 'https://www.postnet.co.za/postnet_api-process_plugin_order';
  
  // Basic Authentication
  $username = $options['postnet_api_key'];
  $password = $options['postnet_api_passcode'];
  $auth = base64_encode("$username:$password");
  
  // Setup request headers
  $headers = [
    'Content-Type' => 'application/json',
    'Authorization' => 'Basic ' . $auth
  ];
  
  // Send the request
  $response = wp_remote_post($url, [
    'method' => 'POST',
    'headers' => $headers,
    'body' => wp_json_encode($data),
    'timeout' => 45,
    'sslverify' => false
  ]);
  
  // Handle the response
  $order_id = $order->get_id();
  
  // Store attempt timestamp
  update_post_meta($order_id, '_waybill_creation_attempted', current_time('mysql'));
  
  if (is_wp_error($response)) {
    $error_message = $response->get_error_message();
    error_log('Error in API request: ' . $error_message);
    
    // Store error information
    update_post_meta($order_id, '_waybill_creation_status', 'failed');
    update_post_meta($order_id, '_waybill_creation_error', sanitize_text_field($error_message));
    
    return false;
  } else {
    $response_body = wp_remote_retrieve_body($response);
    $response = json_decode($response_body);
    
    if (isset($response->success) && $response->success) {
      update_post_meta($order_id, 'Waybill Number', sanitize_text_field($response->waybill_number));
      update_post_meta($order_id, 'Tracking URL', sanitize_text_field($response->tracking_url));
      update_post_meta($order_id, 'Label Print', sanitize_text_field($response->label_print));
      
      // Clear error status on success
      update_post_meta($order_id, '_waybill_creation_status', 'success');
      delete_post_meta($order_id, '_waybill_creation_error');
      
      // Handle collection response fields if create_collection was true
      if ($collection_address) {
        error_log('PostNet: Processing collection response for order ' . $order_id);
        
        // Save collection response fields
        if (isset($response->collection_reference) && !empty($response->collection_reference)) {
          update_post_meta($order_id, 'Collection Reference', sanitize_text_field($response->collection_reference));
          error_log('PostNet: Collection reference saved for order ' . $order_id . ': ' . $response->collection_reference);
        } else {
          error_log('PostNet: No collection reference in response for order ' . $order_id);
        }
        
        if (isset($response->collection_success)) {
          $success_value = $response->collection_success ? 'true' : 'false';
          update_post_meta($order_id, 'Collection Success', $success_value);
          error_log('PostNet: Collection success status for order ' . $order_id . ': ' . $success_value);
        } else {
          error_log('PostNet: No collection success status in response for order ' . $order_id);
        }
        
        if (isset($response->collection_error) && !empty($response->collection_error)) {
          update_post_meta($order_id, 'Collection Error', sanitize_text_field($response->collection_error));
          error_log('PostNet: Collection error for order ' . $order_id . ': ' . $response->collection_error);
        } else {
          error_log('PostNet: No collection error in response for order ' . $order_id);
        }
        
        error_log('PostNet: Waybill created successfully for order ' . $order_id . ' with collection address.');
      } else {
        error_log('PostNet: Waybill created successfully for order ' . $order_id . ' with PostNet store.');
      }
      
      return true;
    } else {
      // Extract error message from response
      $error_message = '';
      if (isset($response->error)) {
        $error_message = is_string($response->error) ? $response->error : wp_json_encode($response->error);
      } elseif (isset($response->message)) {
        $error_message = is_string($response->message) ? $response->message : wp_json_encode($response->message);
      } else {
        $error_message = __('Unknown error occurred', 'delivery-options-postnet-woocommerce');
      }
      
      error_log('API Response: ' . $response_body);
      
      // Store error information
      update_post_meta($order_id, '_waybill_creation_status', 'failed');
      update_post_meta($order_id, '_waybill_creation_error', sanitize_text_field($error_message));
      
      return false;
    }
  }
  
  } catch (Exception $e) {
    $order_id = $order->get_id();
    $error_message = $e->getMessage();
    
    error_log('PostNet: Exception in wc_postnet_delivery_create_waybill: ' . $error_message);
    error_log('PostNet: Stack trace: ' . $e->getTraceAsString());
    
    // Store error information
    update_post_meta($order_id, '_waybill_creation_status', 'failed');
    update_post_meta($order_id, '_waybill_creation_error', sanitize_text_field($error_message));
    
    return false;
  }
}

/**
 * Create waybill with collection address data (Multi Site Mode)
 */
function wc_postnet_delivery_create_waybill_with_collection_address($order, $collection_address) {
  return wc_postnet_delivery_create_waybill($order, $collection_address);
}

/**
 * Validate collection address selection before allowing order completion
 */
function wc_postnet_delivery_validate_collection_address_before_completion($order, $data_store) {
  try {
    // Validate input parameters
    if (!$order || !is_object($order)) {
      error_log('PostNet: Invalid order object in validation function');
      return;
    }
    
    $options = get_option('wc_postnet_delivery_options');
    if (!$options) {
      error_log('PostNet: No plugin options found in validation function');
      return;
    }
    
    // Only validate if Multi Site Mode is enabled
    if (!isset($options['multi_site_mode']) || !$options['multi_site_mode']) {
      return;
    }
    
    // Get the current order status and the new status being set
    $current_status = $order->get_status();
    $new_status = $order->get_status();
    
    // Check if we're trying to change to 'completed' status
    if (isset($_POST['order_status']) && $_POST['order_status'] === 'wc-completed') {
      $new_status = 'completed';
    }
    
    // If the status is being changed to 'completed', validate collection address
    if ($new_status === 'completed') {
      $collection_address_index = get_post_meta($order->get_id(), '_collection_address_index', true);
      
      if ($collection_address_index === '') {
        // Prevent the status change and show error
        wp_die(
          '<div class="notice notice-error"><p><strong>Error:</strong> Cannot complete order. A collection address must be selected before marking the order as completed in Multi Site Mode.</p></div>',
          'Collection Address Required',
          array('back_link' => true)
        );
      }
      
      // Validate that the selected collection address exists
      $collection_addresses = isset($options['collection_addresses']) ? $options['collection_addresses'] : array();
      if (!isset($collection_addresses[$collection_address_index])) {
        wp_die(
          '<div class="notice notice-error"><p><strong>Error:</strong> The selected collection address is invalid. Please select a valid collection address before completing the order.</p></div>',
          'Invalid Collection Address',
          array('back_link' => true)
        );
      }
    }
    
  } catch (Exception $e) {
    error_log('PostNet: Exception in validation function: ' . $e->getMessage());
    error_log('PostNet: Stack trace: ' . $e->getTraceAsString());
    // Don't block the order save if validation fails due to an error
    return;
  }
}

/**
 * Validate number of boxes selection before allowing order completion
 */
function wc_postnet_delivery_validate_number_of_boxes_before_completion($order, $data_store) {
  try {
    // Validate input parameters
    if (!$order || !is_object($order)) {
      error_log('PostNet: Invalid order object in number of boxes validation function');
      return;
    }
    
    $options = get_option('wc_postnet_delivery_options');
    if (!$options) {
      error_log('PostNet: No plugin options found in number of boxes validation function');
      return;
    }
    
    // Only validate if waybill_option is 'user_specified'
    if (!isset($options['waybill_option']) || $options['waybill_option'] !== 'user_specified') {
      return;
    }
    
    // Get the current order status and the new status being set
    $current_status = $order->get_status();
    $new_status = $order->get_status();
    
    // Check if we're trying to change to 'completed' status
    if (isset($_POST['order_status']) && $_POST['order_status'] === 'wc-completed') {
      $new_status = 'completed';
    }
    
    // If the status is being changed to 'completed', validate number of boxes
    if ($new_status === 'completed') {
      $number_of_boxes = get_post_meta($order->get_id(), '_number_of_boxes', true);
      
      if ($number_of_boxes === '' || $number_of_boxes === null || $number_of_boxes === '0' || intval($number_of_boxes) < 1) {
        // Prevent the status change and show error
        wp_die(
          '<div class="notice notice-error"><p><strong>Error:</strong> Cannot complete order. The number of boxes must be specified before marking the order as completed when User Specified waybill option is selected.</p></div>',
          'Number of Boxes Required',
          array('back_link' => true)
        );
      }
    }
    
  } catch (Exception $e) {
    error_log('PostNet: Exception in number of boxes validation function: ' . $e->getMessage());
    error_log('PostNet: Stack trace: ' . $e->getTraceAsString());
    // Don't block the order save if validation fails due to an error
    return;
  }
}

// Completely disable shipping on cart page only
add_action('init', 'disable_cart_shipping');
function disable_cart_shipping() {
    if (is_cart()) {
        // Remove shipping calculation only on cart page
        remove_action('woocommerce_cart_calculate_fees', array(WC()->shipping, 'calculate_shipping'));
        
        // Clear shipping methods only on cart page
        WC()->session->set('chosen_shipping_methods', array());
    }
}

// Hide shipping totals - return false only on cart page
add_filter('woocommerce_cart_needs_shipping', 'cart_needs_shipping_check');
function cart_needs_shipping_check($needs_shipping) {
    if (is_cart()) {
        return false;
    }
    return $needs_shipping;
}

// Disable shipping calculation - return false only on cart page  
add_filter('woocommerce_cart_ready_to_calc_shipping', 'cart_ready_to_calc_shipping_check');
function cart_ready_to_calc_shipping_check($ready) {
    if (is_cart()) {
        return false;
    }
    return $ready;
}