<?php
/**
 * Customer PostNet waybill email (plain text).
 *
 * Override this template by copying it to yourtheme/woocommerce/emails/plain/postnet-waybill.php
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $additional_content
 * @var string   $waybill_number
 * @var string   $tracking_url
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

echo "= " . esc_html(wp_strip_all_tags($email_heading)) . " =\n\n";

printf(esc_html__('Hi %s,', 'delivery-options-postnet-woocommerce'), esc_html($order->get_billing_first_name()));
echo "\n\n";

printf(esc_html__('Good news! A PostNet waybill has been created for your order #%s. You can use the details below to track your shipment.', 'delivery-options-postnet-woocommerce'), esc_html($order->get_order_number()));
echo "\n\n";

echo esc_html__('Waybill Number:', 'delivery-options-postnet-woocommerce') . ' ' . esc_html($waybill_number) . "\n";

if ( ! empty($tracking_url) ) {
  echo esc_html__('Track your shipment:', 'delivery-options-postnet-woocommerce') . ' ' . esc_url($tracking_url) . "\n";
}

echo "\n----------------------------------------\n\n";

do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n----------------------------------------\n\n";

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

if ( ! empty($additional_content) ) {
  echo "\n" . esc_html(wp_strip_all_tags(wptexturize($additional_content))) . "\n";
}

echo "\n" . wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
