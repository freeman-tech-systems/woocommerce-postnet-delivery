<?php
/**
 * Plugin Name: PostNet Delivery For WooCommerce
 * Plugin URI: https://github.com/freeman-tech-systems/wc-postnet-delivery
 * Description: Adds PostNet delivery options to WooCommerce checkout.
 * Version: 1.0.0
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

add_filter('woocommerce_package_rates', 'wc_postnet_delivery_custom_shipping_methods_logic', 10, 2);

function wc_postnet_delivery_compatibility() {
  if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
}

function wc_postnet_delivery_settings_init() {
  register_setting('wc_postnet_delivery', 'wc_postnet_delivery_options');

  add_settings_section(
    'wc_postnet_delivery_section',
    __('PostNet Delivery Settings', 'wc-postnet-delivery'),
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
  echo '<p>' . esc_html__('Set up delivery options for PostNet.', 'wc-postnet-delivery') . ' <a href="' . esc_url('https://www.postnet.co.za/woocommerce-app-info') . '" target="_blank">Setup and Usage Instructions</a></p>';
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
          <th scope="row"><label for="service_type"><?php echo __('Service Type', 'wc-postnet-delivery'); ?></label></th>
          <td>
            <fieldset>
              <?php
              $service_types = wc_postnet_delivery_service_types();
              foreach ($service_types as $service_key=>$service_name){
                ?>
                <label>
                  <input type="checkbox" name="wc_postnet_delivery_options[service_type][]" value="<?php echo esc_html($service_key); ?>" <?php checked(in_array($service_key, (array)($options['service_type']))); ?> />
                  <?php echo esc_html($service_name); ?>
                </label><br />
                <?php
              }
              ?>
            </fieldset>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="postnet_to_postnet_fee"><?php echo __('PostNet to PostNet Delivery Fee', 'wc-postnet-delivery'); ?></label></th>
          <td>
            <input type="number" name="wc_postnet_delivery_options[postnet_to_postnet_fee]" value="<?php echo isset($options['postnet_to_postnet_fee']) ? esc_attr($options['postnet_to_postnet_fee']) : ''; ?>" required />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="order_amount_threshold"><?php echo __('Order Amount Threshold', 'wc-postnet-delivery'); ?></label></th>
          <td>
            <input type="number" name="wc_postnet_delivery_options[order_amount_threshold]" value="<?php echo isset($options['order_amount_threshold']) ? esc_attr($options['order_amount_threshold']) : ''; ?>" required />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="collection_type"><?php echo __('Collection Type', 'wc-postnet-delivery'); ?></label></th>
          <td>
            <select name="wc_postnet_delivery_options[collection_type]">
              <option value="always_collect" <?php selected($options['collection_type'], 'always_collect'); ?>><?php echo __('Always Collect', 'wc-postnet-delivery'); ?></option>
              <option value="always_deliver" <?php selected($options['collection_type'], 'always_deliver'); ?>><?php echo __('Always Deliver', 'wc-postnet-delivery'); ?></option>
              <option value="service_based" <?php selected($options['collection_type'], 'service_based'); ?>><?php echo __('Service Based', 'wc-postnet-delivery'); ?></option>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="postnet_store">PostNet Store</label></th>
          <td>
            <select name="wc_postnet_delivery_options[postnet_store]" id="postnet_store" required>
              <option value=''><?php echo __('Select...', 'wc-postnet-delivery'); ?></option>
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
          <th scope="row"><label for="postnet_api_key"><?php echo __('API Key', 'wc-postnet-delivery'); ?></label></th>
          <td>
            <input type="text" name="wc_postnet_delivery_options[postnet_api_key]" id="postnet_api_key" style="width:300px;" value="<?php echo isset($options['postnet_api_key']) ? esc_attr($options['postnet_api_key']) : ''; ?>" required />
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="postnet_api_passcode"><?php echo __('Passphrase', 'wc-postnet-delivery'); ?></label></th>
          <td>
            <input type="text" name="wc_postnet_delivery_options[postnet_api_passcode]" id="postnet_api_passcode" style="width:300px;" value="<?php echo isset($options['postnet_api_passcode']) ? esc_attr($options['postnet_api_passcode']) : ''; ?>" required />
          </td>
        </tr>
      </table>
      
      <?php
      // Output save settings button
      submit_button('Save Settings');
      $configure_nonce = wp_create_nonce('configure_shipping_options_nonce');
      $export_nonce = wp_create_nonce('export_products_nonce');
      ?>
      <a href="<?php echo esc_url(add_query_arg(['action' => 'configure_shipping_options', '_wpnonce' => $configure_nonce])); ?>" class="button"><?php echo __('Configure PostNet Shipping', 'wc-postnet-delivery'); ?></a>
      <a href="<?php echo esc_url(add_query_arg(['action' => 'export_products', '_wpnonce' => $export_nonce])); ?>" class="button"><?php echo __('Export Products CSV', 'wc-postnet-delivery'); ?></a>
      <label for="postnet_delivery_csv" class="button"><?php echo __('Import Products CSV', 'wc-postnet-delivery'); ?></label>
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
    wp_enqueue_script('wc-postnet-delivery-options-js', plugin_dir_url(__FILE__) . 'js/wc-postnet-delivery-options.js', array('jquery'), '1.0.0', true);
  }
}

function wc_postnet_delivery_admin() {
  // Check if the export action has been triggered
  if (isset($_GET['action']) && $_GET['action'] === 'export_products') {
    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'export_products_nonce')) {
      wc_postnet_delivery_export_products_csv();
    } else {
      wp_die('Security check failed.');
    }
  }
  
  // Check if the import action has been triggered
  if (isset($_POST['action']) && $_POST['action'] === 'import_products' && !empty($_FILES['postnet_delivery_csv'])) {
    if (isset($_POST['postnet_delivery_nonce']) && wp_verify_nonce($_POST['postnet_delivery_nonce'], 'postnet_delivery_action')) {
      wc_postnet_delivery_import_products_csv();
    } else {
      // Invalid nonce, return an error or exit
      wp_die('Security check failed.');
    }
  }
  
  // Configure shipping options
  if (isset($_GET['action']) && $_GET['action'] === 'configure_shipping_options') {
    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'configure_shipping_options_nonce')) {
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
  if (!(isset($_POST['postnet_delivery_nonce']) && wp_verify_nonce($_POST['postnet_delivery_nonce'], 'postnet_delivery_action'))) {
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
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Shipping options have been configured.', 'wc-postnet-delivery') . '</p></div>';
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
        'label' => sprintf(__('%s Delivery Fee', 'wc-postnet-delivery'), $service_types[$service]),
        'desc_tip' => 'true',
        // Translators: %s is the service type name.
        'description' => sprintf(__('Enter the delivery fee for %s service.', 'wc-postnet-delivery'), $service_types[$service]),
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
    $stores = wc_postnet_delivery_fetch_stores();
    
    // Check if decoding was successful
    if ( null === $stores ) {
      wp_send_json_error( 'Error decoding stores data' );
      return;
    }

    wp_send_json_success( $stores );
}

function wc_postnet_delivery_checkout_field($rate) {
  $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

  // The chosen methods are stored in an array, one for each package. Most stores will only have one package.
  $chosen_method = ! empty( $chosen_methods ) ? $chosen_methods[0] : '';

  if ($rate->label != POSTNET_SHIPPING_STORE || $rate->id !== $chosen_method) return;
  $stores = wc_postnet_delivery_fetch_stores();
  
  $options = array(
    '' => 'Select a Store...'
  );
  
  foreach ($stores as $store){
    $options[wp_json_encode([$store->code, $store->name])] = $store->name;
  }
  
  woocommerce_form_field( 'destination_store', array(
    'type'          => 'select',
    'required'      => true,
    'class'         => array('my-field-class form-row-wide'),
    'options'       => $options, // Pass the options array
    ));
  
  echo '</div>';
}

function wc_postnet_delivery_checkout_field_update_order_meta($order_id) {
  if ( ! empty( $_POST['destination_store'] ) ) {
    update_post_meta( $order_id, 'Destination Store', sanitize_text_field( $_POST['destination_store'] ) );
  }
}

function wc_postnet_delivery_checkout_field_display_admin_order_meta($order) {
  $store = json_decode(get_post_meta( $order->get_id(), 'Destination Store', true ));
  echo '<p><strong>'.esc_html__('Destination Store').':</strong> ' . esc_html($store[1]) . '</p>';
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
  $destination_store = get_post_meta( $order->get_id(), 'Destination Store', true );
  if ( ! empty( $destination_store ) ) {
    $store = json_decode($destination_store);
    echo '<p><strong>Destination Store:</strong> ' . esc_html( $store[1] ) . '</p>';
  }
  
  $waybill_number = get_post_meta( $order->get_id(), 'Waybill Number', true );
  if ( ! empty( $waybill_number ) ) {
    echo '<p><strong>Waybill Number:</strong> ' . esc_html( $waybill_number ) . '</p>';
  }
  
  $tracking_url = get_post_meta( $order->get_id(), 'Tracking URL', true );
  if ( ! empty( $tracking_url ) ) {
    echo '<p><strong>Tracking URL:</strong> <a href="'.esc_url($tracking_url) .'" target="_blank">' . esc_html( $tracking_url ) . '</a></p>';
  }
  
  $label_print = get_post_meta( $order->get_id(), 'Label Print', true );
  if ( ! empty( $label_print ) ) {
    echo '<p><strong>Label Print:</strong> <a href="'.esc_url($label_print) .'" target="_blank">' . esc_html( $label_print ) . '</a></p>';
  }
}

function wc_postnet_delivery_collection_notification($order_id){
  if (!$order_id) return;
  
  // Get an instance of the WC_Order object
  $order = wc_get_order($order_id);
  
  // Get delivery options
  $options = get_option('wc_postnet_delivery_options');
  $postal_code = $order->get_shipping_postcode();
  $main_check = $postal_code ? json_decode(wc_postnet_fetch_url('https://pnsa.restapis.co.za/public/is-main?postcode='.$postal_code)) : null;
  $is_main = $main_check ? $main_check->main : false;
  $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
  $chosen_method = ! empty( $chosen_methods ) ? $chosen_methods[0] : '';
  
  if (empty($chosen_method)) return;
  
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