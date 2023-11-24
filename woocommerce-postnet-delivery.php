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

add_action( 'before_woocommerce_init', function() {
  if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
  }
} );

// Hook into the admin menu
add_action('admin_menu', 'woocommerce_postnet_delivery_settings_page');

// Register settings
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

add_action('admin_init', 'woocommerce_postnet_delivery_settings_init');

// Settings page callback
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

// Section callback
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

// Settings page display callback
function woocommerce_postnet_delivery_options_page() {
  // Check user capabilities
  if (!current_user_can('manage_options')) {
    return;
  }

  // Retrieve the plugin settings from the options table
  $options = get_option('woocommerce_postnet_delivery_options');
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
                  <input type="checkbox" name="woocommerce_postnet_delivery_options[service_type][]" value="<?=$service_key?>" <?php checked(in_array($service_key, (array)$options['service_type'])); ?> />
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

// Hook into the admin scripts action
add_action('admin_enqueue_scripts', 'woocommerce_postnet_delivery_enqueue_scripts');

function woocommerce_postnet_delivery_enqueue_scripts($hook) {
  // Check if we are on the settings page of our plugin
  if ($hook == 'woocommerce_page_woocommerce_postnet_delivery') {
    // Enqueue our script
    wp_enqueue_script('woocommerce-postnet-delivery-options-js', plugin_dir_url(__FILE__) . 'js/woocommerce-postnet-delivery-options.js', array('jquery'), '1.0.0', true);
  }
}

add_action('admin_init', 'woocommerce_postnet_delivery_admin');

function woocommerce_postnet_delivery_admin() {
  if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
  }

  // Check if the export action has been triggered
  if (isset($_GET['action']) && $_GET['action'] === 'export_products') {
    woocommerce_postnet_delivery_export_products_csv();
  }
  
  // Check if the import action has been triggered
  if (isset($_POST['action']) && $_POST['action'] === 'import_products' && !empty($_FILES['postnet_delivery_csv'])) {
    woocommerce_postnet_delivery_import_products_csv();
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

// Hook to add custom fields to product options shipping
add_action('woocommerce_product_options_shipping', 'woocommerce_postnet_delivery_product_fields');

function woocommerce_postnet_delivery_product_fields() {
  global $post;

  echo '<div class="options_group">';
  echo '<hr />';
  echo '<p><strong>PostNet Delivery Fees</strong></p>';

  // Get the enabled service types from the settings
  $options = get_option('woocommerce_postnet_delivery_options');
  $enabled_services = isset($options['service_type']) ? $options['service_type'] : array();
  $service_types = woocommerce_postnet_delivery_service_types();

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

// Hook to save custom fields data
add_action('woocommerce_process_product_meta', 'woocommerce_postnet_delivery_save_product_fields');

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
