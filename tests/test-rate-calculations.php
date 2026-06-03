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
