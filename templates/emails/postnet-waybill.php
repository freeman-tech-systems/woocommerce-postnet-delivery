<?php
/**
 * Customer PostNet waybill email (HTML).
 *
 * Override this template by copying it to yourtheme/woocommerce/emails/postnet-waybill.php
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

do_action('woocommerce_email_header', $email_heading, $email); ?>

<p><?php printf(esc_html__('Hi %s,', 'delivery-options-postnet-woocommerce'), esc_html($order->get_billing_first_name())); ?></p>

<p><?php printf(esc_html__('Good news! A PostNet waybill has been created for your order #%s. You can use the details below to track your shipment.', 'delivery-options-postnet-woocommerce'), esc_html($order->get_order_number())); ?></p>

<p><strong><?php esc_html_e('Waybill Number:', 'delivery-options-postnet-woocommerce'); ?></strong> <?php echo esc_html($waybill_number); ?></p>

<?php if ( ! empty($tracking_url) ) : ?>
<p><a href="<?php echo esc_url($tracking_url); ?>" target="_blank"><?php esc_html_e('Track your shipment', 'delivery-options-postnet-woocommerce'); ?></a></p>
<?php endif; ?>

<?php
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

if ( ! empty($additional_content) ) {
  echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email);
