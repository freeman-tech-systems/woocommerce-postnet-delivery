<?php





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
