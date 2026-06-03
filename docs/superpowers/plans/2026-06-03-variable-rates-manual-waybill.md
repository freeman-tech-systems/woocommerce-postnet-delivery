# Variable Rates & Manual Waybill Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a global Fixed/Variable rate mode (variable = weight/volumetric tiered pricing), make waybill creation an always-manual order-page action, and remove per-product fees + CSV.

**Architecture:** Keep the single-file procedural plugin. Extract the pure rate math into a dependency-free `includes/rate-calculations.php` (unit-tested with a standalone PHP runner). Thin WordPress wrappers read product/option data and delegate to the pure functions. The existing `woocommerce_package_rates` filter branches on `rate_mode`. Waybill auto-creation hooks are removed; the existing on-order button becomes the sole creation path.

**Tech Stack:** PHP 8.x, WordPress, WooCommerce, jQuery (admin), standalone PHP test runner (no PHPUnit).

---

## File Structure

- `includes/rate-calculations.php` — **NEW.** Pure functions: `wc_postnet_delivery_calc_volumetric_weight`, `wc_postnet_delivery_calc_chargeable_weight`, `wc_postnet_delivery_calc_tiered_rate`. No WP dependencies, no side effects.
- `tests/test-rate-calculations.php` — **NEW.** Standalone runner (`php tests/test-rate-calculations.php`) with a tiny assert harness.
- `wc-postnet-delivery.php` — **MODIFY.** Require the includes file; add WP wrappers; rewrite rates filter; settings schema/UI/sanitizer; remove per-product + CSV; make waybill manual-only.
- `js/wc-postnet-delivery-options.js` — **MODIFY.** Rate-mode show/hide; remove CSV import handler.
- `css/wc-postnet-delivery.css` — **MODIFY.** Variable-rate table styling.
- `README.txt` — **MODIFY.** Version bump + changelog + feature text.

Service keys (existing, from `wc_postnet_delivery_service_types()`): `postnet_to_postnet`, `main_centre_express`, `main_centre_economy`, `regional_centre_express`, `regional_centre_economy`.

---

## Task 1: Pure rate-calculation helpers (TDD)

**Files:**
- Create: `includes/rate-calculations.php`
- Test: `tests/test-rate-calculations.php`

- [ ] **Step 1: Write the failing test**

Create `tests/test-rate-calculations.php`:

```php
<?php
require __DIR__ . '/../includes/rate-calculations.php';

$failures = 0;
function check($label, $actual, $expected) {
  global $failures;
  $ok = abs((float)$actual - (float)$expected) < 0.0001;
  if (!$ok) { $failures++; echo "FAIL: $label — expected $expected, got $actual\n"; }
  else { echo "PASS: $label\n"; }
}

// volumetric weight: (L*W*H)/divisor
check('vol 10x10x10/5000', wc_postnet_delivery_calc_volumetric_weight(10,10,10,5000), 0.2);
check('vol zero dim -> 0', wc_postnet_delivery_calc_volumetric_weight(0,10,10,5000), 0.0);
check('vol zero divisor -> 0', wc_postnet_delivery_calc_volumetric_weight(10,10,10,0), 0.0);

// chargeable weight: per-item max(actual,vol) then *qty, summed
$items = [
  ['actual' => 1.0, 'volumetric' => 2.0, 'qty' => 3], // 2*3 = 6
  ['actual' => 5.0, 'volumetric' => 1.0, 'qty' => 1], // 5*1 = 5
];
check('chargeable per-item-max-sum', wc_postnet_delivery_calc_chargeable_weight($items), 11.0);
check('chargeable empty -> 0', wc_postnet_delivery_calc_chargeable_weight([]), 0.0);

// tiered rate: base + ceil(max(0, weight-included)) * per_kg
check('tier under threshold', wc_postnet_delivery_calc_tiered_rate(3, 50, 5, 10), 50.0);
check('tier at threshold', wc_postnet_delivery_calc_tiered_rate(5, 50, 5, 10), 50.0);
check('tier 0.2 over rounds up', wc_postnet_delivery_calc_tiered_rate(5.2, 50, 5, 10), 60.0);
check('tier 2kg over', wc_postnet_delivery_calc_tiered_rate(7, 50, 5, 10), 70.0);

echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test-rate-calculations.php`
Expected: fatal error / failure — `includes/rate-calculations.php` does not exist yet.

- [ ] **Step 3: Write minimal implementation**

Create `includes/rate-calculations.php`:

```php
<?php
/**
 * Pure rate-calculation helpers for PostNet delivery.
 * No WordPress/WooCommerce dependencies — safe to unit test in isolation.
 */
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
  // Allow direct CLI inclusion for tests; block web access outside WP.
  exit;
}

/**
 * Volumetric weight in kg from centimetre dimensions.
 * Returns 0.0 if any dimension <= 0 or divisor <= 0.
 */
function wc_postnet_delivery_calc_volumetric_weight($length_cm, $width_cm, $height_cm, $divisor) {
  $length_cm = (float) $length_cm;
  $width_cm  = (float) $width_cm;
  $height_cm = (float) $height_cm;
  $divisor   = (float) $divisor;
  if ($length_cm <= 0 || $width_cm <= 0 || $height_cm <= 0 || $divisor <= 0) {
    return 0.0;
  }
  return ($length_cm * $width_cm * $height_cm) / $divisor;
}

/**
 * Total chargeable weight (kg) for a shipment.
 * @param array $items each ['actual' => float, 'volumetric' => float, 'qty' => int]
 * Per item: max(actual, volumetric) * qty, summed across items.
 */
function wc_postnet_delivery_calc_chargeable_weight(array $items) {
  $total = 0.0;
  foreach ($items as $item) {
    $actual     = isset($item['actual']) ? (float) $item['actual'] : 0.0;
    $volumetric = isset($item['volumetric']) ? (float) $item['volumetric'] : 0.0;
    $qty        = isset($item['qty']) ? (int) $item['qty'] : 0;
    $total += max($actual, $volumetric) * $qty;
  }
  return $total;
}

/**
 * Tiered rate. Base covers up to included_kg; each kg above (rounded UP) costs per_kg.
 */
function wc_postnet_delivery_calc_tiered_rate($weight_kg, $base, $included_kg, $per_kg) {
  $weight_kg   = (float) $weight_kg;
  $base        = (float) $base;
  $included_kg = (float) $included_kg;
  $per_kg      = (float) $per_kg;
  $over = $weight_kg - $included_kg;
  if ($over <= 0) {
    return $base;
  }
  $extra_units = (int) ceil($over);
  return $base + ($extra_units * $per_kg);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test-rate-calculations.php`
Expected: all PASS, exit 0.

- [ ] **Step 5: Commit**

```bash
git add includes/rate-calculations.php tests/test-rate-calculations.php
git commit -m "Add pure rate-calculation helpers with standalone tests"
```

---

## Task 2: Wire calculations into the rates filter

**Files:**
- Modify: `wc-postnet-delivery.php` (top require; new wrappers; rewrite `wc_postnet_delivery_custom_shipping_methods_logic`; delete `wc_postnet_delivery_service_fee`)

- [ ] **Step 1: Require the pure helpers** near the top of the plugin (after the `const` block):

```php
require_once plugin_dir_path(__FILE__) . 'includes/rate-calculations.php';
```

- [ ] **Step 2: Add WordPress wrappers** (place near `wc_postnet_delivery_service_fee`):

```php
function wc_postnet_delivery_volumetric_weight($product, $divisor) {
  $length = wc_get_dimension((float) $product->get_length(), 'cm');
  $width  = wc_get_dimension((float) $product->get_width(), 'cm');
  $height = wc_get_dimension((float) $product->get_height(), 'cm');
  return wc_postnet_delivery_calc_volumetric_weight($length, $width, $height, $divisor);
}

function wc_postnet_delivery_chargeable_weight($package, $divisor) {
  $items = array();
  foreach ($package['contents'] as $values) {
    $product = isset($values['data']) ? $values['data'] : null;
    if (!$product) continue;
    $items[] = array(
      'actual'     => wc_get_weight((float) $product->get_weight(), 'kg'),
      'volumetric' => wc_postnet_delivery_volumetric_weight($product, $divisor),
      'qty'        => isset($values['quantity']) ? (int) $values['quantity'] : 0,
    );
  }
  return wc_postnet_delivery_calc_chargeable_weight($items);
}

function wc_postnet_delivery_service_rate($package, $service, $options) {
  $divisor = (isset($options['volumetric_divisor']) && (float) $options['volumetric_divisor'] > 0)
    ? (float) $options['volumetric_divisor'] : 5000;
  $weight = wc_postnet_delivery_chargeable_weight($package, $divisor);
  $cfg = isset($options['variable_rates'][$service]) ? $options['variable_rates'][$service] : array();
  $base     = isset($cfg['base']) ? (float) $cfg['base'] : 0;
  $included = isset($cfg['included_kg']) ? (float) $cfg['included_kg'] : 0;
  $per_kg   = isset($cfg['per_kg']) ? (float) $cfg['per_kg'] : 0;
  return wc_postnet_delivery_calc_tiered_rate($weight, $base, $included, $per_kg);
}
```

- [ ] **Step 3: Rewrite the express/economy/store cost branches** in `wc_postnet_delivery_custom_shipping_methods_logic`. Add `$rate_mode = isset($options['rate_mode']) ? $options['rate_mode'] : 'fixed';` near the top of the function. Replace the express block:

```php
if ($rate_id === $postnet_rate_express) {
  if (!$free_shipping && $is_main && in_array('main_centre_express', $enabled_services)) {
    $rate->cost = ($rate_mode === 'variable')
      ? wc_postnet_delivery_service_rate($package, 'main_centre_express', $options)
      : (isset($options['main_centre_express_fee']) ? floatval($options['main_centre_express_fee']) : 0);
  } else if (!$free_shipping && !$is_main && in_array('regional_centre_express', $enabled_services)) {
    $rate->cost = ($rate_mode === 'variable')
      ? wc_postnet_delivery_service_rate($package, 'regional_centre_express', $options)
      : (isset($options['regional_centre_express_fee']) ? floatval($options['regional_centre_express_fee']) : 0);
  } else {
    unset($rates[$rate_id]);
  }
  continue;
}
```

Replace the economy block identically but with `main_centre_economy` / `regional_centre_economy` / `*_economy_fee`.

Replace the store block:

```php
if ($rate_id === $postnet_rate_store) {
  if (!$free_shipping && in_array('postnet_to_postnet', $enabled_services)) {
    $rate->cost = ($rate_mode === 'variable')
      ? wc_postnet_delivery_service_rate($package, 'postnet_to_postnet', $options)
      : floatval($postnet_to_postnet_fee);
  } else {
    unset($rates[$rate_id]);
  }
  continue;
}
```

- [ ] **Step 4: Delete `wc_postnet_delivery_service_fee()`** (no longer referenced).

- [ ] **Step 5: Lint**

Run: `php -l wc-postnet-delivery.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add wc-postnet-delivery.php
git commit -m "Branch shipping rates on Fixed/Variable mode using weight/volumetric calc"
```

---

## Task 3: Options schema, defaults, and sanitizer

**Files:**
- Modify: `wc-postnet-delivery.php` (`register_setting` defaults; `wc_postnet_delivery_sanitize_options`)

- [ ] **Step 1: Update the `default` array** in `wc_postnet_delivery_settings_init`: add `'rate_mode' => 'fixed'`, `'volumetric_divisor' => 5000`, `'variable_rates' => array()`; remove `'waybill_option' => 'single'`.

- [ ] **Step 2: Update `wc_postnet_delivery_sanitize_options`.** Remove the `waybill_option` block. Add:

```php
$sanitized['rate_mode'] = (isset($input['rate_mode']) && $input['rate_mode'] === 'variable') ? 'variable' : 'fixed';

$sanitized['volumetric_divisor'] = (isset($input['volumetric_divisor']) && floatval($input['volumetric_divisor']) > 0)
  ? floatval($input['volumetric_divisor']) : 5000;

$sanitized['variable_rates'] = array();
$valid_services = array_keys(wc_postnet_delivery_service_types());
$defaults_included = array('postnet_to_postnet' => 5);
if (isset($input['variable_rates']) && is_array($input['variable_rates'])) {
  foreach ($valid_services as $service) {
    $row = isset($input['variable_rates'][$service]) ? $input['variable_rates'][$service] : array();
    $default_included = isset($defaults_included[$service]) ? $defaults_included[$service] : 2;
    $sanitized['variable_rates'][$service] = array(
      'base'        => isset($row['base']) ? floatval($row['base']) : 0,
      'included_kg' => isset($row['included_kg']) && $row['included_kg'] !== '' ? floatval($row['included_kg']) : $default_included,
      'per_kg'      => isset($row['per_kg']) ? floatval($row['per_kg']) : 0,
    );
  }
}
```

- [ ] **Step 3: Lint & commit**

Run: `php -l wc-postnet-delivery.php` (expect no errors)

```bash
git add wc-postnet-delivery.php
git commit -m "Add rate_mode, volumetric_divisor, variable_rates to options + sanitizer"
```

---

## Task 4: Settings page UI

**Files:**
- Modify: `wc-postnet-delivery.php` (`wc_postnet_delivery_options_page`)

- [ ] **Step 1: Add the Rate Mode row** before the existing fee rows:

```php
<tr>
  <th scope="row"><label for="rate_mode"><?php echo esc_html__('Rate Mode', 'delivery-options-postnet-woocommerce'); ?></label></th>
  <td>
    <select name="wc_postnet_delivery_options[rate_mode]" id="rate_mode">
      <option value="fixed" <?php selected(isset($options['rate_mode']) ? $options['rate_mode'] : 'fixed', 'fixed'); ?>><?php echo esc_html__('Fixed Rate', 'delivery-options-postnet-woocommerce'); ?></option>
      <option value="variable" <?php selected(isset($options['rate_mode']) ? $options['rate_mode'] : 'fixed', 'variable'); ?>><?php echo esc_html__('Variable Rate (weight / volumetric)', 'delivery-options-postnet-woocommerce'); ?></option>
    </select>
    <p class="description"><?php echo esc_html__('Fixed: one flat rate per service. Variable: tiered rate from chargeable weight (max of actual vs volumetric, summed).', 'delivery-options-postnet-woocommerce'); ?></p>
  </td>
</tr>
```

- [ ] **Step 2: Wrap the existing 5 fee rows** (`postnet_to_postnet_fee`, `regional_centre_express_fee`, `regional_centre_economy_fee`, `main_centre_express_fee`, `main_centre_economy_fee`) so they sit inside a Fixed-mode container. Add a CSS class `postnet-fixed-rates` to each of those `<tr>` rows (e.g. `<tr class="postnet-fixed-rates">`), keeping their existing fields and descriptions.

- [ ] **Step 3: Remove the "Waybill Option" row** entirely (the `<tr>` with `waybill_option` select).

- [ ] **Step 4: Add the Variable Rates block** after the `</table>` that closes the main settings table and before the Collection Addresses section:

```php
<div id="variable-rates-section" style="<?php echo (isset($options['rate_mode']) && $options['rate_mode'] === 'variable') ? 'display:block;' : 'display:none;'; ?>">
  <h2><?php echo esc_html__('Variable Rates', 'delivery-options-postnet-woocommerce'); ?></h2>
  <table class="form-table">
    <tr>
      <th scope="row"><label for="volumetric_divisor"><?php echo esc_html__('Volumetric Divisor', 'delivery-options-postnet-woocommerce'); ?></label></th>
      <td>
        <input type="number" step="any" min="1" name="wc_postnet_delivery_options[volumetric_divisor]" id="volumetric_divisor" value="<?php echo esc_attr(isset($options['volumetric_divisor']) ? $options['volumetric_divisor'] : 5000); ?>" />
        <p class="description"><?php echo esc_html__('Volumetric weight (kg) = (L x W x H in cm) / divisor. Default 5000.', 'delivery-options-postnet-woocommerce'); ?></p>
      </td>
    </tr>
  </table>
  <table class="widefat striped postnet-variable-rates-table">
    <thead>
      <tr>
        <th><?php echo esc_html__('Service', 'delivery-options-postnet-woocommerce'); ?></th>
        <th><?php echo esc_html__('Base Rate (R)', 'delivery-options-postnet-woocommerce'); ?></th>
        <th><?php echo esc_html__('Included up to (kg)', 'delivery-options-postnet-woocommerce'); ?></th>
        <th><?php echo esc_html__('Per kg thereafter (R)', 'delivery-options-postnet-woocommerce'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php
      $service_types = wc_postnet_delivery_service_types();
      $default_included = array('postnet_to_postnet' => 5);
      foreach ($service_types as $service_key => $service_name) {
        $row = isset($options['variable_rates'][$service_key]) ? $options['variable_rates'][$service_key] : array();
        $base = isset($row['base']) ? $row['base'] : '';
        $included = isset($row['included_kg']) && $row['included_kg'] !== '' ? $row['included_kg'] : (isset($default_included[$service_key]) ? $default_included[$service_key] : 2);
        $per_kg = isset($row['per_kg']) ? $row['per_kg'] : '';
        ?>
        <tr>
          <td><?php echo esc_html($service_name); ?></td>
          <td><input type="number" step="any" min="0" name="wc_postnet_delivery_options[variable_rates][<?php echo esc_attr($service_key); ?>][base]" value="<?php echo esc_attr($base); ?>" /></td>
          <td><input type="number" step="any" min="0" name="wc_postnet_delivery_options[variable_rates][<?php echo esc_attr($service_key); ?>][included_kg]" value="<?php echo esc_attr($included); ?>" /></td>
          <td><input type="number" step="any" min="0" name="wc_postnet_delivery_options[variable_rates][<?php echo esc_attr($service_key); ?>][per_kg]" value="<?php echo esc_attr($per_kg); ?>" /></td>
        </tr>
        <?php
      }
      ?>
    </tbody>
  </table>
</div>
```

- [ ] **Step 5: Remove the CSV buttons** — delete the "Export Products CSV" `<a>`, the "Import Products CSV" `<label>`, and the second `<form>` with the file input + `$export_nonce`. Keep "Save Settings" and "Configure PostNet Shipping".

- [ ] **Step 6: Lint & commit**

Run: `php -l wc-postnet-delivery.php`

```bash
git add wc-postnet-delivery.php
git commit -m "Settings UI: Rate Mode toggle, variable-rate table, remove waybill option + CSV buttons"
```

---

## Task 5: Settings JS (toggle + remove CSV handler)

**Files:**
- Modify: `js/wc-postnet-delivery-options.js`

- [ ] **Step 1: Remove the CSV import handler** (the `$('#postnet_delivery_csv').on('change', ...)` block) since the field no longer exists.

- [ ] **Step 2: Add the rate-mode toggle** inside the `$(function(){ ... })`:

```js
function postnetToggleRateMode() {
  var mode = $('#rate_mode').val();
  if (mode === 'variable') {
    $('.postnet-fixed-rates').hide();
    $('#variable-rates-section').show();
  } else {
    $('.postnet-fixed-rates').show();
    $('#variable-rates-section').hide();
  }
}
$('#rate_mode').on('change', postnetToggleRateMode);
postnetToggleRateMode();
```

- [ ] **Step 3: Commit**

```bash
git add js/wc-postnet-delivery-options.js
git commit -m "Settings JS: toggle Fixed/Variable blocks, drop CSV import handler"
```

---

## Task 6: Remove per-product fees + CSV backend

**Files:**
- Modify: `wc-postnet-delivery.php`

- [ ] **Step 1: Remove hooks** (the `add_action` lines): `woocommerce_process_product_meta` -> `wc_postnet_delivery_save_product_fields`, and `woocommerce_product_options_shipping` -> `wc_postnet_delivery_product_fields`.

- [ ] **Step 2: Remove the export/import handling** in `wc_postnet_delivery_admin` (the two `if (isset($_GET['action'])...)` / `if (isset($_POST['action'])...)` blocks for `export_products` and `import_products`). Keep the `configure_shipping_options` block.

- [ ] **Step 3: Delete functions:** `wc_postnet_delivery_product_fields`, `wc_postnet_delivery_save_product_fields`, `wc_postnet_delivery_csv_headers`, `wc_postnet_delivery_export_products_csv`, `wc_postnet_delivery_import_products_csv`.

- [ ] **Step 4: Lint & commit**

Run: `php -l wc-postnet-delivery.php`

```bash
git add wc-postnet-delivery.php
git commit -m "Remove per-product delivery fees and CSV import/export"
```

---

## Task 7: Make waybill creation manual-only

**Files:**
- Modify: `wc-postnet-delivery.php`

- [ ] **Step 1: Remove auto-create hooks** (the `add_action` lines): `woocommerce_thankyou` -> `wc_postnet_delivery_collection_notification`, and `woocommerce_order_status_completed` -> `wc_postnet_delivery_create_waybill_on_completion`.

- [ ] **Step 2: Remove the completion-guard hooks** (the `add_action` lines): `woocommerce_before_order_object_save` -> `wc_postnet_delivery_validate_collection_address_before_completion`, and `woocommerce_before_order_object_save` -> `wc_postnet_delivery_validate_number_of_boxes_before_completion`.

- [ ] **Step 3: Delete the now-unused functions:** `wc_postnet_delivery_collection_notification`, `wc_postnet_delivery_create_waybill_on_completion`, `wc_postnet_delivery_validate_collection_address_before_completion`, `wc_postnet_delivery_validate_number_of_boxes_before_completion`.

- [ ] **Step 4: Always show the Number of Boxes field.** In `wc_postnet_delivery_admin_number_of_boxes_field`, remove the early-return that checks `waybill_option !== 'user_specified'`. Change the default so an unset value shows `1`: replace `$number_of_boxes = get_post_meta(...)` with:

```php
$number_of_boxes = get_post_meta($order->get_id(), '_number_of_boxes', true);
if ($number_of_boxes === '' || $number_of_boxes === null) {
  $number_of_boxes = 1;
}
```

Remove the completion "Warning" block and the `*-required` styling branches (no longer required to complete); keep the simple numeric input (`min="1"`, default shown) and a short description: "Number of boxes for this shipment (used on the waybill only)."

- [ ] **Step 5: Default boxes to 1 on save.** In `wc_postnet_delivery_save_admin_number_of_boxes_field`, if `number_of_boxes` is unset or `< 1`, store `1` instead of skipping.

- [ ] **Step 6: Always send box count + user_specified in the payload.** In `wc_postnet_delivery_create_waybill`, set `'waybill_option' => 'user_specified'` in `$data`, and replace the conditional `number_of_boxes` block with an always-on version:

```php
$number_of_boxes = get_post_meta($order->get_id(), '_number_of_boxes', true);
$data['number_of_boxes'] = ($number_of_boxes && intval($number_of_boxes) > 0) ? intval($number_of_boxes) : 1;
```

- [ ] **Step 7: Lint & commit**

Run: `php -l wc-postnet-delivery.php`

```bash
git add wc-postnet-delivery.php
git commit -m "Waybill creation is now manual-only; always show boxes field (default 1)"
```

---

## Task 8: CSS for the variable-rate table

**Files:**
- Modify: `css/wc-postnet-delivery.css`

- [ ] **Step 1: Append styles:**

```css
.postnet-variable-rates-table { max-width: 760px; margin-top: 10px; }
.postnet-variable-rates-table th, .postnet-variable-rates-table td { padding: 8px 10px; vertical-align: middle; }
.postnet-variable-rates-table input[type="number"] { width: 120px; }
#variable-rates-section h2 { margin-top: 24px; }
```

- [ ] **Step 2: Commit**

```bash
git add css/wc-postnet-delivery.css
git commit -m "Style the variable-rate settings table"
```

---

## Task 9: Version bump, README, changelog

**Files:**
- Modify: `wc-postnet-delivery.php` (plugin header `Version:`), `README.txt` (`Stable tag:` + changelog)

- [ ] **Step 1:** Bump `Version: 1.0.13` -> `Version: 1.0.14` in the plugin header, and `Stable tag: 1.0.13` -> `1.0.14` in `README.txt`.

- [ ] **Step 2:** Add a changelog entry under `== Changelog ==` describing: global Fixed/Variable rate mode; variable weight/volumetric tiered rates; manual-only waybill creation with always-visible box count; removal of per-product fees + CSV import/export. Note the upgrade caveat: installs relying on per-product fees must move to Fixed or Variable.

- [ ] **Step 3: Commit**

```bash
git add wc-postnet-delivery.php README.txt
git commit -m "Bump version to 1.0.14 and update changelog"
```

---

## Task 10: Final verification

- [ ] **Step 1:** Run `php tests/test-rate-calculations.php` — expect ALL PASS.
- [ ] **Step 2:** Run `php -l wc-postnet-delivery.php` and `php -l includes/rate-calculations.php` — expect no syntax errors.
- [ ] **Step 3:** Grep for orphaned references — `grep -n "wc_postnet_delivery_service_fee\|wc_postnet_delivery_product_fields\|export_products\|import_products\|waybill_option\|create_waybill_on_completion\|collection_notification" wc-postnet-delivery.php` — confirm only intended references remain (e.g. `waybill_option` only as the constant `'user_specified'` payload value).
- [ ] **Step 4 (manual, requires running store):** In `docker/`, `docker compose up -d`; configure plugin; verify: Fixed vs Variable rate display at checkout; main vs regional split; multi-product chargeable weight; free-shipping threshold; manual waybill button (standard + multi-site) creates waybill + saves tracking meta; box-count field defaults to 1.

---

## Self-Review Notes

- **Spec coverage:** rate engine (T1,T2), settings schema/UI/JS (T3,T4,T5), remove per-product+CSV (T4 buttons, T6 backend), manual waybill + boxes (T7), CSS (T8), version/docs (T9), testing (T1, T10). Multi-site collection booking is unchanged code (kept), exercised via the existing button/AJAX — covered by T7 removals not touching `wc_postnet_delivery_create_waybill`'s collection-address path. Email out of scope per spec.
- **Type consistency:** `variable_rates[$service]` shape `{base, included_kg, per_kg}` is identical across sanitizer (T3), UI (T4), and `wc_postnet_delivery_service_rate` (T2). `volumetric_divisor` default 5000 consistent across T2/T3/T4.
