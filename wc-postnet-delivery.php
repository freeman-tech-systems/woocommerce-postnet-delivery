<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Plugin Name: Delivery Options For PostNet
 * Plugin URI: https://github.com/freeman-tech-systems/woocommerce-postnet-delivery
 * Description: Adds PostNet delivery options to WooCommerce checkout.
 * Version: 1.0.6
 * Author: Freeman Tech Systems
 * Author URI: https://github.com/freeman-tech-systems
 * License: GPL2
 * WC requires at least: 3.0
 * WC tested up to: 8.2.1
 */

const POSTNET_SHIPPING_FREE = 'PostNet Free Shipping';
const POSTNET_SHIPPING_STORE = 'PostNet to PostNet';
const POSTNET_SHIPPING_EXPRESS = 'PostNet Express';
const POSTNET_SHIPPING_ECONOMY = 'PostNet Economy';

add_action('admin_enqueue_scripts', 'wc_postnet_delivery_enqueue_scripts');
add_action('admin_init', 'wc_postnet_delivery_admin');
add_action('admin_init', 'wc_postnet_delivery_settings_init');
add_action('admin_menu', 'wc_postnet_delivery_settings_page');
add_action('admin_notices', 'woocommerce_postnet_delivery_show_shipping_configured_notice');
add_action('before_woocommerce_init', 'wc_postnet_delivery_compatibility');
add_action('woocommerce_admin_order_data_after_shipping_address', 'wc_postnet_delivery_checkout_field_display_admin_order_meta', 10, 1);
add_action('woocommerce_after_shipping_rate', 'wc_postnet_delivery_checkout_field', 10, 1);
add_action('woocommerce_checkout_process', 'wc_postnet_delivery_validations');
add_action('woocommerce_checkout_update_order_meta', 'wc_postnet_delivery_checkout_field_update_order_meta');
add_action('woocommerce_order_details_after_order_table', 'wc_postnet_delivery_order_received_page');
add_action('woocommerce_process_product_meta', 'wc_postnet_delivery_save_product_fields');
add_action('woocommerce_product_options_shipping', 'wc_postnet_delivery_product_fields');
add_action('woocommerce_thankyou', 'wc_postnet_delivery_collection_notification', 10, 1);
add_action('wp_ajax_nopriv_wc_postnet_delivery_stores', 'wc_postnet_delivery_stores');
add_action('wp_ajax_wc_postnet_delivery_stores', 'wc_postnet_delivery_stores');
add_action('wp_ajax_validate_google_api_key', 'wc_postnet_validate_google_api_key');

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
        'postnet_to_postnet_fee' => '',
        'order_amount_threshold' => '',
        'postnet_store' => '',
        'postnet_api_key' => '',
        'postnet_api_passcode' => '',
        'google_api_key' => ''
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
      'collection_type' => 'always_collect'
    ];
  }
  
  $stores = json_decode(wc_postnet_fetch_url('https://www.postnet.co.za/cart_store-json_list/'));
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
      </table>
      
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
    wp_enqueue_script('wc-postnet-delivery-options-js', plugin_dir_url(__FILE__) . 'js/wc-postnet-delivery-options.js', array('jquery'), '1.0.6', true);
    
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

function wc_postnet_delivery_configure_shipping_options() {
  $zone = wc_postnet_delivery_get_zone();
  
  // Method IDs for the custom methods you're interested in
  $required_methods = array(
    POSTNET_SHIPPING_FREE=>'flat_rate',
    POSTNET_SHIPPING_STORE=>'flat_rate',
    POSTNET_SHIPPING_EXPRESS=>'flat_rate',
    POSTNET_SHIPPING_ECONOMY=>'flat_rate',
  );

  // Get existing methods for the zone
  $existing_methods = $zone->get_shipping_methods(true);

  // Check if your required methods exist
  foreach ($required_methods as $method_title=>$method_type) {
    $found = false;
    foreach ($existing_methods as $method) {
      if ($method->title === $method_title) {
        $found = true;
        break;
      }
    }

    // If not found, add the method
    if (!$found) {
      wc_postnet_delivery_create_shipping_option($zone, $method_type, $method_title);
    }
  }
  
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

function wc_postnet_delivery_create_shipping_option($zone, $method_type, $method_title) {
  // Instance ID for the new method -- set to 0 to auto-assign
  $instance_id = $zone->add_shipping_method($method_type);
  
  $option_name = 'woocommerce_flat_rate_' . $instance_id . '_settings'; // Construct the option name

  // Retrieve the existing settings
  $instance_settings = get_option($option_name, array());

  // Update specific settings
  $instance_settings['title'] = $method_title; // Setting the title
  $instance_settings['cost'] = '0.00'; // Setting the flat rate cost
  $instance_settings['tax_status'] = 'taxable'; // Setting the tax status

  // Save the updated settings back
  update_option($option_name, $instance_settings);
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
  
  foreach ($rates as $rate_id => $rate) {
    switch ($rate->label){
      case POSTNET_SHIPPING_FREE:
        if (!$free_shipping) unset($rates[$rate_id]);
        break;
      case POSTNET_SHIPPING_STORE:
        if (!$free_shipping && in_array('postnet_to_postnet', $enabled_services)){
          $rate->cost = $postnet_to_postnet_fee;
        } else {
          unset($rates[$rate_id]);
        }
        break;
      case POSTNET_SHIPPING_EXPRESS:
        if (!$free_shipping && $is_main && in_array('main_centre_express', $enabled_services)){
          $rate->cost = wc_postnet_delivery_service_fee($package, 'main_centre_express');
        } else if (!$free_shipping && !$is_main && in_array('regional_centre_express', $enabled_services)){
          $rate->cost = wc_postnet_delivery_service_fee($package, 'regional_centre_express');
        } else {
          unset($rates[$rate_id]);
        }
        break;
      case POSTNET_SHIPPING_ECONOMY:
        if (!$free_shipping && $is_main && in_array('main_centre_economy', $enabled_services)){
          $rate->cost = wc_postnet_delivery_service_fee($package, 'main_centre_economy');
        } else if (!$free_shipping && !$is_main && in_array('regional_centre_economy', $enabled_services)){
          $rate->cost = wc_postnet_delivery_service_fee($package, 'regional_centre_economy');
        } else {
          unset($rates[$rate_id]);
        }
        break;
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

  if ($rate->label != POSTNET_SHIPPING_STORE || $rate->id !== $chosen_method) return;
  
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
  
  $zone = wc_postnet_delivery_get_zone();
  $shipping_methods = $zone->get_shipping_methods();
  $instance_id = 0;
  foreach ($shipping_methods as $method){
    if ($method->title == POSTNET_SHIPPING_STORE){
      $instance_id = $method->instance_id;
      break;
    }
  }
  
  if ($chosen_method != 'flat_rate:'.$instance_id) return;
  
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
}

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
    error_log('PostNet: Got shipping method from order: ' . $chosen_method);
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
  
  // If the shipping method is not POSTNET_SHIPPING_STORE, unset the destination store
  if ($rate->title !== POSTNET_SHIPPING_STORE) {
    delete_post_meta($order_id, 'Destination Store');
    error_log('PostNet: Unsetting destination store as shipping method is not PostNet to PostNet');
    return;
  }
  
  $service_type = ($is_main ? 'main' : 'regional').'_centre_';
  $destination_store = json_decode(get_post_meta( $order_id, 'Destination Store', true ));
  
  switch ($rate->title){
    case POSTNET_SHIPPING_FREE:
    case POSTNET_SHIPPING_EXPRESS:
      $service_type .= 'express';
      break;
    case POSTNET_SHIPPING_STORE:
      $service_type = 'postnet_to_postnet';
      break;
    case POSTNET_SHIPPING_ECONOMY:
      $service_type .= 'economy';
      break;
    default:
      return;
  }
  
  // Define the data to send
  $data = [
    'online_store_name' => get_bloginfo('name'),
    'collection_type' => $options['collection_type'],
    'service_type' => $service_type,
    'origin_store' => $options['postnet_store'],
    'destination_store' => $destination_store ? $destination_store[0] : ''
    ,
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
  if (is_wp_error($response)) {
    error_log('Error in API request: ' . $response->get_error_message());
  } else {
    $response_body = wp_remote_retrieve_body($response);
    $response = json_decode($response_body);
    
    if (isset($response->success) && $response->success){
      update_post_meta( $order_id, 'Waybill Number', sanitize_text_field( $response->waybill_number ) );
      update_post_meta( $order_id, 'Tracking URL', sanitize_text_field( $response->tracking_url ) );
      update_post_meta( $order_id, 'Label Print', sanitize_text_field( $response->label_print ) );
    } else {
      error_log('API Response: ' . $response_body);
    }
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
  $all_stores = json_decode(wc_postnet_fetch_url('https://www.postnet.co.za/cart_store-json_list/'));
  
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