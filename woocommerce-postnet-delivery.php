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

const POSTNET_SHIPPING_FREE = 'PostNet Free Shipping';
const POSTNET_SHIPPING_STORE = 'PostNet to PostNet';
const POSTNET_SHIPPING_EXPRESS = 'PostNet Express';
const POSTNET_SHIPPING_ECONOMY = 'PostNet Economy';

add_action('admin_enqueue_scripts', 'woocommerce_postnet_delivery_enqueue_scripts');
add_action('admin_init', 'woocommerce_postnet_delivery_admin');
add_action('admin_init', 'woocommerce_postnet_delivery_settings_init');
add_action('admin_menu', 'woocommerce_postnet_delivery_settings_page');
add_action('admin_notices', 'woocommerce_postnet_delivery_show_shipping_configured_notice');
add_action('before_woocommerce_init', 'woocommerce_postnet_delivery_compatibility');
add_action('woocommerce_admin_order_data_after_shipping_address', 'woocommerce_postnet_delivery_checkout_field_display_admin_order_meta', 10, 1);
add_action('woocommerce_after_shipping_rate', 'woocommerce_postnet_delivery_checkout_field', 10, 1);
add_action('woocommerce_checkout_process', 'woocommerce_postnet_delivery_validations');
add_action('woocommerce_checkout_update_order_meta', 'woocommerce_postnet_delivery_checkout_field_update_order_meta');
add_action('woocommerce_order_details_after_order_table', 'woocommerce_postnet_delivery_order_received_page');
add_action('woocommerce_process_product_meta', 'woocommerce_postnet_delivery_save_product_fields');
add_action('woocommerce_product_options_shipping', 'woocommerce_postnet_delivery_product_fields');
add_action('wp_ajax_nopriv_woocommerce_postnet_delivery_stores', 'woocommerce_postnet_delivery_stores');
add_action('wp_ajax_woocommerce_postnet_delivery_stores', 'woocommerce_postnet_delivery_stores');

add_filter('woocommerce_package_rates', 'woocommerce_postnet_delivery_custom_shipping_methods_logic', 10, 2);

function woocommerce_postnet_delivery_compatibility() {
  if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
}

function woocommerce_postnet_delivery_settings_init() {
  register_setting('woocommerce_postnet_delivery', 'woocommerce_postnet_delivery_options');

  add_settings_section(
    'woocommerce_postnet_delivery_section',
    __('PostNet Delivery Settings', 'woocommerce-postnet-delivery'),
    'woocommerce_postnet_delivery_section_callback',
    'woocommerce_postnet_delivery'
  );

  // Repeat add_settings_field() as needed for your options here
}

function woocommerce_postnet_delivery_settings_page() {
  add_submenu_page(
    'woocommerce', // Parent slug
    'PostNet Delivery', // Page title
    'PostNet Delivery', // Menu title
    'manage_options', // Capability
    'woocommerce_postnet_delivery', // Menu slug
    'woocommerce_postnet_delivery_options_page' // Function to display the settings page
  );
}

function woocommerce_postnet_delivery_section_callback() {
  echo '<p>' . __('Set up delivery options for PostNet.', 'woocommerce-postnet-delivery') . '</p>';
}

function woocommerce_postnet_delivery_service_types() {
  return [
    'postnet_to_postnet' => 'PostNet to PostNet',
    'regional_centre_express' => 'Regional Centre - Express',
    'regional_centre_economy' => 'Regional Centre - Economy',
    'main_centre_express' => 'Main Centre - Express',
    'main_centre_economy' => 'Main Centre - Economy'
  ];
}

function woocommerce_postnet_delivery_options_page() {
  // Check user capabilities
  if (!current_user_can('manage_options')) {
    return;
  }

  // Retrieve the plugin settings from the options table
  $options = get_option('woocommerce_postnet_delivery_options');
  if (!$options){
    $options = [
      'service_type' => [],
      'collection_type' => 'always_collect'
    ];
  }
  
  $stores = json_decode(file_get_contents('https://www.postnet.co.za/cart_store-json_list/'));
  $selected_store = isset($options['postnet_store']) ? esc_attr($options['postnet_store']) : '';
  ?>
  <div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
      <?php
      // Output security fields for the registered setting "woocommerce_postnet_delivery"
      settings_fields('woocommerce_postnet_delivery');
      // Output setting sections and their fields
      do_settings_sections('woocommerce_postnet_delivery');

      // Add your settings fields here
      ?>
      <table class="form-table">
        <tr>
          <th scope="row"><label for="service_type">Service Type</label></th>
          <td>
            <fieldset>
              <?php
              $service_types = woocommerce_postnet_delivery_service_types();
              foreach ($service_types as $service_key=>$service_name){
                ?>
                <label>
                  <input type="checkbox" name="woocommerce_postnet_delivery_options[service_type][]" value="<?=$service_key?>" <?php checked(in_array($service_key, (array)($options['service_type']))); ?> />
                  <?=$service_name?>
                </label><br />
                <?php
              }
              ?>
            </fieldset>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="postnet_to_postnet_fee">PostNet to PostNet Delivery Fee</label></th>
          <td>
            <input type="number" name="woocommerce_postnet_delivery_options[postnet_to_postnet_fee]" value="<?php echo isset($options['postnet_to_postnet_fee']) ? esc_attr($options['postnet_to_postnet_fee']) : ''; ?>" />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="order_amount_threshold">Order Amount Threshold</label></th>
          <td>
            <input type="number" name="woocommerce_postnet_delivery_options[order_amount_threshold]" value="<?php echo isset($options['order_amount_threshold']) ? esc_attr($options['order_amount_threshold']) : ''; ?>" />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="collection_type">Collection Type</label></th>
          <td>
            <select name="woocommerce_postnet_delivery_options[collection_type]">
              <option value="always_collect" <?php selected($options['collection_type'], 'always_collect'); ?>>Always Collect</option>
              <option value="always_deliver" <?php selected($options['collection_type'], 'always_deliver'); ?>>Always Deliver</option>
              <option value="service_based" <?php selected($options['collection_type'], 'service_based'); ?>>Service Based</option>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="postnet_store">PostNet Store</label></th>
          <td>
            <select name="woocommerce_postnet_delivery_options[postnet_store]" id="postnet_store">
              <option value=''>Select...</option>
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
          <th scope="row"><label for="postnet_store_email">PostNet Store Email</label></th>
          <td>
            <input type="text" name="woocommerce_postnet_delivery_options[postnet_store_email]" id="postnet_store_email" style="width:300px;" value="<?php echo isset($options['postnet_store_email']) ? esc_attr($options['postnet_store_email']) : ''; ?>" readonly />
          </td>
        </tr>
        <!-- Add other settings as necessary -->
      </table>
      
      <?php
      // Output save settings button
      submit_button('Save Settings');
      ?>
      <a href="<?=esc_url(add_query_arg('action', 'configure_shipping_options'))?>" class="button">Configure PostNet Shipping</a>
      <a href="<?=esc_url(add_query_arg('action', 'export_products'))?>" class="button">Export Products CSV</a>
      <label for="postnet_delivery_csv" class="button">Import Products CSV</label>
    </form>
    <form method="post" enctype="multipart/form-data" class="hidden">
      <input type='file' name='postnet_delivery_csv' accept='.csv' id="postnet_delivery_csv">
      <input type='hidden' name='action' value='import_products'>
    </form>
  </div>
  <?php
}

function woocommerce_postnet_delivery_enqueue_scripts($hook) {
  // Check if we are on the settings page of our plugin
  if ($hook == 'woocommerce_page_woocommerce_postnet_delivery') {
    // Enqueue our script
    wp_enqueue_script('woocommerce-postnet-delivery-options-js', plugin_dir_url(__FILE__) . 'js/woocommerce-postnet-delivery-options.js', array('jquery'), '1.0.0', true);
  }
}

function woocommerce_postnet_delivery_admin() {
  // Check if the export action has been triggered
  if (isset($_GET['action']) && $_GET['action'] === 'export_products') {
    woocommerce_postnet_delivery_export_products_csv();
  }
  
  // Check if the import action has been triggered
  if (isset($_POST['action']) && $_POST['action'] === 'import_products' && !empty($_FILES['postnet_delivery_csv'])) {
    woocommerce_postnet_delivery_import_products_csv();
  }
  
  // Configure shipping options
  if (isset($_GET['action']) && $_GET['action'] === 'configure_shipping_options') {
    woocommerce_postnet_delivery_configure_shipping_options();
  }
}

function woocommerce_postnet_delivery_csv_headers() {
  $headers = ['Product ID', 'Product Name'];
  $service_types = woocommerce_postnet_delivery_service_types();
  foreach ($service_types as $service_key=>$service_name){
    if ($service_key == 'postnet_to_postnet') continue;
    
    $headers[] = $service_name;
  }
  
  return $headers;
}

function woocommerce_postnet_delivery_export_products_csv() {
  // Define the CSV headers
  $headers = woocommerce_postnet_delivery_csv_headers();

  // Set the headers to force download of the file
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="woocommerce-products.csv"');

  // Create a file pointer connected to the output stream
  $output = fopen('php://output', 'w');

  // Output the column headings
  fputcsv($output, $headers);

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
    $service_types = woocommerce_postnet_delivery_service_types();
    foreach ($service_types as $service_key=>$_service_name){
      if ($service_key == 'postnet_to_postnet') continue;
      
      $row[] = get_post_meta($product_id, '_'.$service_key.'_fee', true);
    }

    // Output the row to the CSV
    fputcsv($output, $row);
  }

  // Close the output stream
  fclose($output);

  // Terminate the current script; prevents WordPress template loading
  exit();
}

function woocommerce_postnet_delivery_import_products_csv() {
  // Check for file upload
  if (isset($_FILES['postnet_delivery_csv']) && isset($_FILES['postnet_delivery_csv']['tmp_name'])) {
    // Open the file for reading
    $file = fopen($_FILES['postnet_delivery_csv']['tmp_name'], 'r');

    if ($file) {
      // Read the header line
      $header = fgetcsv($file);
      
      // Define the expected headers
      $expected_headers = woocommerce_postnet_delivery_csv_headers();

      // Check if headers match
      if ($header !== $expected_headers) {
        // Close the file before returning or echoing an error
        fclose($file);

        // Add an admin notice on header mismatch
        add_action('admin_notices', function() use ($header) {
          echo '<div class="notice notice-error is-dismissible"><p>The uploaded file headers do not match the expected headers. Please check the file and try again.</p></div>';
        });
        return;
      }

      while (($data = fgetcsv($file)) !== FALSE) {
        $product_id = intval($data[0]);
        
        $col = 2;
        $service_types = woocommerce_postnet_delivery_service_types();
        foreach ($service_types as $service_key=>$_service_name){
          if ($service_key == 'postnet_to_postnet') continue;
          
          $fee = wc_clean($data[$col]);
          update_post_meta($product_id, '_'.$service_key.'_fee', $fee);
          $col++;
        }
      }

      fclose($file);

      // Add an admin notice on successful import
      add_action('admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible"><p>Product delivery fees have been successfully updated.</p></div>';
      });
    }
  }
}

function woocommerce_postnet_delivery_configure_shipping_options() {
  $zone = woocommerce_postnet_delivery_get_zone();
  
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
      woocommerce_postnet_delivery_create_shipping_option($zone, $method_type, $method_title);
    }
  }
  
  wp_safe_redirect(add_query_arg('shipping_configured', '1', menu_page_url('woocommerce_postnet_delivery', false)));
}

function woocommerce_postnet_delivery_get_zone() {
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

function woocommerce_postnet_delivery_create_shipping_option($zone, $method_type, $method_title) {
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
    // echo '<div class="notice notice-success is-dismissible"><p>' . __('Shipping options have been configured.', 'text-domain') . '</p></div>';
  }
}

function woocommerce_postnet_delivery_product_fields() {
  global $post;

  // Get the enabled service types from the settings
  $options = get_option('woocommerce_postnet_delivery_options');
  $enabled_services = isset($options['service_type']) ? $options['service_type'] : array();
  $service_types = woocommerce_postnet_delivery_service_types();
  
  if (!$enabled_services || (count($enabled_services == 1 && $enabled_services[0] == 'postnet_to_postnet'))) return;

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
        'label' => sprintf(__('%s Delivery Fee', 'woocommerce'), $service_types[$service]),
        'desc_tip' => 'true',
        'description' => sprintf(__('Enter the delivery fee for %s service.', 'woocommerce'), $service_types[$service]),
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

function woocommerce_postnet_delivery_save_product_fields($post_id) {
  // Get the enabled service types from the settings
  $options = get_option('woocommerce_postnet_delivery_options');
  $enabled_services = isset($options['service_type']) ? $options['service_type'] : array();

  // Save the delivery fee for each enabled service
  foreach ($enabled_services as $service) {
    $field_id = '_'.sanitize_title($service).'_fee';
    if (isset($_POST[$field_id])) {
      update_post_meta($post_id, $field_id, wc_clean($_POST[$field_id]));
    }
  }
}

function woocommerce_postnet_delivery_custom_shipping_methods_logic($rates, $package) {
  // Calculate the PostNet fee
  $postal_code = $package['destination']['postcode'];
  $main_check = json_decode(file_get_contents('https://pnsa.restapis.co.za/public/is-main?postcode='.$postal_code));
  $is_main = $main_check->main;
  $subtotal = $package['cart_subtotal'];
  $options = get_option('woocommerce_postnet_delivery_options');
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
          $rate->cost = woocommerce_postnet_delivery_service_fee($package, 'main_centre_express');
        } else if (!$free_shipping && !$is_main && in_array('regional_centre_express', $enabled_services)){
          $rate->cost = woocommerce_postnet_delivery_service_fee($package, 'regional_centre_express');
        } else {
          unset($rates[$rate_id]);
        }
        break;
      case POSTNET_SHIPPING_ECONOMY:
        if (!$free_shipping && $is_main && in_array('main_centre_economy', $enabled_services)){
          $rate->cost = woocommerce_postnet_delivery_service_fee($package, 'main_centre_economy');
        } else if (!$free_shipping && !$is_main && in_array('regional_centre_economy', $enabled_services)){
          $rate->cost = woocommerce_postnet_delivery_service_fee($package, 'regional_centre_economy');
        } else {
          unset($rates[$rate_id]);
        }
        break;
    }
  }
  
  return $rates;
}

function woocommerce_postnet_delivery_service_fee($package, $service) {
  $fee = 0;
  
  foreach ($package['contents'] as $item_id => $values) {
    $value = get_post_meta($values['product_id'], '_'.$service.'_fee', true);
    $fee += $value * $values['quantity'];
  }
  
  return $fee;
}

function woocommerce_postnet_delivery_fetch_stores() {
  $response = wp_remote_get('https://www.postnet.co.za/cart_store-json_list/');

  if ( is_wp_error( $response ) ) {
    wp_send_json_error( 'Error fetching stores' );
    return;
  }

  $body = wp_remote_retrieve_body( $response );
  return json_decode( $body );
}

function woocommerce_postnet_delivery_stores() {
    $stores = woocommerce_postnet_delivery_fetch_stores();
    
    // Check if decoding was successful
    if ( null === $stores ) {
      wp_send_json_error( 'Error decoding stores data' );
      return;
    }

    wp_send_json_success( $stores );
}

function woocommerce_postnet_delivery_checkout_field($rate) {
  $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

  // The chosen methods are stored in an array, one for each package. Most stores will only have one package.
  $chosen_method = ! empty( $chosen_methods ) ? $chosen_methods[0] : '';

  if ($rate->label != POSTNET_SHIPPING_STORE || $rate->id !== $chosen_method) return;
  
  $stores = woocommerce_postnet_delivery_fetch_stores();
  
  $options = array(
    '' => 'Select a Store...'
  );
  
  foreach ($stores as $store){
    $options[$store->store_name] = $store->store_name;
  }
  
  woocommerce_form_field( 'destination_store', array(
    'type'          => 'select',
    'required'      => true,
    'class'         => array('my-field-class form-row-wide'),
    'options'       => $options, // Pass the options array
    ));
  
  echo '</div>';
}

function woocommerce_postnet_delivery_checkout_field_update_order_meta($order_id) {
  if ( ! empty( $_POST['destination_store'] ) ) {
    update_post_meta( $order_id, 'Destination Store', sanitize_text_field( $_POST['destination_store'] ) );
  }
}

function woocommerce_postnet_delivery_checkout_field_display_admin_order_meta($order) {
  echo '<p><strong>'.__('Destination Store').':</strong> ' . get_post_meta( $order->get_id(), 'Destination Store', true ) . '</p>';
}

function woocommerce_postnet_delivery_validations() {
  $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
  $chosen_method = ! empty( $chosen_methods ) ? $chosen_methods[0] : '';
  
  $zone = woocommerce_postnet_delivery_get_zone();
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

function woocommerce_postnet_delivery_order_received_page($order) {
  // Get the custom field value
  $field_value = get_post_meta( $order->get_id(), 'Destination Store', true );

  // Check if there's a value for the custom field
  if ( ! empty( $field_value ) ) {
    // Display the custom field and its value
    echo '<p><strong>Destination Store:</strong> ' . esc_html( $field_value ) . '</p>';
  }
}