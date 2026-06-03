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
 *
 * @param array $items each ['actual' => float, 'volumetric' => float, 'qty' => int]
 * @return float Per item: max(actual, volumetric) * qty, summed across items.
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
