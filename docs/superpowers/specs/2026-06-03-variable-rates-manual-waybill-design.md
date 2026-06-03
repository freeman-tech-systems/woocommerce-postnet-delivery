# Design: Weight/Volumetric Rates & Manual Waybill Flow

**Date:** 2026-06-03
**Plugin:** Delivery Options For PostNet (`wc-postnet-delivery.php`)
**Branch:** `feature/variable-rates-manual-waybill`

## 1. Goals

1. Replace the current pricing model with a **single global Rate Mode toggle: Fixed or Variable**.
2. **Variable mode** = tiered rate per service, driven by chargeable weight (max of actual vs volumetric, per item, summed).
3. Make **waybill creation always a manual button** on the order page (no creation at checkout or on completion).
4. **Number of boxes** always available at creation; never affects the rate.
5. Multi-site unchanged except that the same button also runs collection booking.
6. Remove per-product fees + CSV import/export.

## 2. Locked decisions

| Decision | Choice |
|---|---|
| Fixed vs Variable control | One global toggle (`rate_mode`) for all 5 services |
| Per-product fees + CSV | Removed entirely |
| Waybill trigger | Always a manual button on the order page; drop Single/Individual/User-Specified setting |
| Customer email | Out of scope — handled by existing WooCommerce setup; plugin just saves tracking meta |
| Chargeable weight | Per-item `max(actual, volumetric)`, then summed across the cart |
| Per-kg charging above threshold | Round up to the next whole kg |
| Included-weight thresholds | Editable per service (defaults: 5kg Collect, 2kg door services) |
| Volumetric divisor | Configurable setting, default 5000 |
| Architecture | Approach B — keep single-file procedural plugin; isolate rate math into focused functions |

## 3. Rate engine (Approach B — factored functions)

Three new functions, called from the existing `woocommerce_package_rates` filter:

```
volumetric_weight($product)      // (L x W x H in cm) / divisor -> kg ; 0 if any dim missing
chargeable_weight($package)      // sum over items of max(actual_kg, volumetric_kg) * qty
tiered_rate($weight, $service)   // base + ceil(max(0, weight - included_kg)) * per_kg
```

- Units normalized with `wc_get_weight()` / `wc_get_dimension()` (reads the store's WooCommerce unit settings -> kg / cm).
- Per-item max, then sum; round up to the next kg above the included weight; divisor default 5000 (configurable).

The rates filter (`wc_postnet_delivery_custom_shipping_methods_logic`) becomes:

- Resolve service from the rate id + the `is-main` postcode lookup (unchanged): Collect -> `postnet_to_postnet`; Express/Economy -> `main_*` or `regional_*`.
- `rate_mode === 'fixed'` -> cost = the existing per-service flat-fee field.
- `rate_mode === 'variable'` -> cost = `tiered_rate(chargeable_weight($package), $service)`.
- Free-shipping threshold (`order_amount_threshold`) still overrides to the Free rate.
- Customer-facing options (Collect / Express / Economy) are unchanged.

**Removed:** `wc_postnet_delivery_service_fee()` (the per-product fee sum).

## 4. Settings screen

- New **Rate Mode** select (Fixed / Variable), default **Fixed** (preserves current behavior on upgrade).
- **Fixed block** (shown when Fixed): the 5 existing flat-fee fields.
- **Variable block** (shown when Variable):
  - **Volumetric divisor** field (default 5000).
  - A table, one row per service x columns: *Base rate (R)* / *Included up to (kg)* / *Per-kg thereafter (R)*. "Included" pre-filled 5kg (Collect) / 2kg (the four door services).
- Show/hide handled in `wc-postnet-delivery-options.js` (same pattern as the Multi-Site toggle).
- **Removed from UI:** "Waybill Option" select; "Export/Import Products CSV" buttons.
- Kept: Configure PostNet Shipping, Order Amount Threshold, store/API/Google keys, Multi-Site + collection addresses.

## 5. Waybill creation flow

- **Remove** `woocommerce_thankyou` auto-create and **remove** `woocommerce_order_status_completed` auto-create (both hooks dropped).
- **Keep & generalize** the existing on-order **Create Waybill** button + its AJAX handler (already handles the multi-site collection-address requirement).
- **Number of Boxes** field: always shown for PostNet orders, defaults to `1`, saved always.
- **Multi-site:** button still requires a selected collection address and sends `create_collection` (unchanged). **Drop the "block order completion until address selected" `wp_die` guard**, since completion is no longer the trigger — the button enforces it instead.
- **API payload** (`create_waybill`): always send `waybill_option = 'user_specified'` and `number_of_boxes` (default 1); everything else unchanged. Tracking meta (Waybill Number / Tracking URL / Label Print) saved exactly as today.

## 6. Removals

- Product meta UI: `wc_postnet_delivery_product_fields` / `_save_product_fields` (+ hooks).
- CSV: `_export_products_csv`, `_import_products_csv`, `_csv_headers`, the `admin_init` export/import handlers, and the buttons.
- The number-of-boxes completion-time validation hook (`wc_postnet_delivery_validate_number_of_boxes_before_completion`).
- The collection-address completion-time `wp_die` guard (`wc_postnet_delivery_validate_collection_address_before_completion`).
- Existing `_{service}_fee` product meta is left in the DB (orphaned, harmless).

## 7. Options schema changes (`wc_postnet_delivery_options` + sanitizer)

- **Add:** `rate_mode` (`fixed`|`variable`, default `fixed`), `volumetric_divisor` (float >0, default 5000), `variable_rates[$service] = {base, included_kg, per_kg}` for the 5 services.
- **Drop:** `waybill_option`.
- Sanitizer validates service keys, floats, and the rate-mode/divisor.

## 8. Out of scope

Customer email (WooCommerce handles it), checkout/store-selector/maps UI, and any move to `WC_Shipping_Method` classes.

## 9. Files touched

- `wc-postnet-delivery.php` (core)
- `js/wc-postnet-delivery-options.js` (toggle + remove CSV handler)
- `css/wc-postnet-delivery.css` (variable-rate table)
- `README.txt` + changelog + version bump

## 10. Testing

- Logic checks for `volumetric_weight`, `chargeable_weight` (per-item-max-then-sum), `tiered_rate` (ceil rounding at the threshold boundary).
- Manual scenarios: fixed vs variable; main vs regional; multi-product; missing-dimension product; free-shipping threshold; waybill button in standard + multi-site; boxes field; tracking meta saved.

## 11. Edge cases / risks

- A product with no weight *and* no dimensions contributes 0 kg (merchant must set product weights/dimensions).
- Blank variable-rate fields are treated as 0 (no hard block).
- Default `rate_mode = 'fixed'` preserves flat-rate behavior on upgrade. Installs that relied on per-product fees lose that pricing source and must switch to Fixed or Variable (note in changelog).
