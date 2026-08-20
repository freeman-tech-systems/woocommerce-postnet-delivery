<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Customer email sent when a PostNet waybill is created for an order.
 *
 * Enabled via the "Waybill Email" checkbox on the PostNet Delivery settings
 * page (off by default). Subject, heading and additional content are
 * customisable under WooCommerce > Settings > Emails.
 */
class WC_Postnet_Delivery_Waybill_Email extends WC_Email {
  public $waybill_number = '';
  public $tracking_url = '';

  public function __construct() {
    $this->id             = 'wc_postnet_delivery_waybill';
    $this->customer_email = true;
    $this->title          = __('PostNet Waybill Created', 'delivery-options-postnet-woocommerce');
    $this->description    = __('Sent to the customer when a PostNet waybill is created for their order, containing the waybill number and tracking link. This email is switched on or off from the PostNet Delivery settings page (WooCommerce > PostNet Delivery).', 'delivery-options-postnet-woocommerce');
    $this->template_html  = 'emails/postnet-waybill.php';
    $this->template_plain = 'emails/plain/postnet-waybill.php';
    $this->template_base  = trailingslashit(dirname(__DIR__)) . 'templates/';
    $this->placeholders   = array(
      '{order_number}'   => '',
      '{waybill_number}' => '',
    );

    add_action('wc_postnet_delivery_waybill_created', array($this, 'trigger'), 10, 1);

    parent::__construct();
  }

  public function get_default_subject() {
    return __('Your {site_title} order #{order_number} has been shipped', 'delivery-options-postnet-woocommerce');
  }

  public function get_default_heading() {
    return __('Your order has been shipped', 'delivery-options-postnet-woocommerce');
  }

  /**
   * The plugin settings checkbox is the single on/off switch for this email;
   * there is no separate enable toggle on the WooCommerce email settings screen.
   */
  public function is_enabled() {
    $options = get_option('wc_postnet_delivery_options', array());
    $enabled = !empty($options['waybill_email_enabled']);
    return apply_filters('woocommerce_email_enabled_' . $this->id, $enabled, $this->object, $this);
  }

  /**
   * Send the email for an order.
   *
   * @param int $order_id Order ID.
   */
  public function trigger($order_id) {
    $this->setup_locale();

    $order = wc_get_order($order_id);
    if ($order) {
      $this->object                          = $order;
      $this->recipient                       = $order->get_billing_email();
      $this->waybill_number                  = get_post_meta($order_id, 'Waybill Number', true);
      $this->tracking_url                    = get_post_meta($order_id, 'Tracking URL', true);
      $this->placeholders['{order_number}']   = $order->get_order_number();
      $this->placeholders['{waybill_number}'] = $this->waybill_number;
    }

    if ($this->object && $this->is_enabled() && $this->get_recipient() && !empty($this->waybill_number)) {
      $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }

    $this->restore_locale();
  }

  public function get_content_html() {
    return wc_get_template_html($this->template_html, array(
      'order'              => $this->object,
      'email_heading'      => $this->get_heading(),
      'additional_content' => method_exists($this, 'get_additional_content') ? $this->get_additional_content() : '',
      'waybill_number'     => $this->waybill_number,
      'tracking_url'       => $this->tracking_url,
      'sent_to_admin'      => false,
      'plain_text'         => false,
      'email'              => $this,
    ), '', $this->template_base);
  }

  public function get_content_plain() {
    return wc_get_template_html($this->template_plain, array(
      'order'              => $this->object,
      'email_heading'      => $this->get_heading(),
      'additional_content' => method_exists($this, 'get_additional_content') ? $this->get_additional_content() : '',
      'waybill_number'     => $this->waybill_number,
      'tracking_url'       => $this->tracking_url,
      'sent_to_admin'      => false,
      'plain_text'         => true,
      'email'              => $this,
    ), '', $this->template_base);
  }

  public function init_form_fields() {
    $placeholder_text = sprintf(__('Available placeholders: %s', 'delivery-options-postnet-woocommerce'), '<code>{site_title}, {order_number}, {waybill_number}</code>');

    $this->form_fields = array(
      'subject' => array(
        'title'       => __('Subject', 'delivery-options-postnet-woocommerce'),
        'type'        => 'text',
        'desc_tip'    => true,
        'description' => $placeholder_text,
        'placeholder' => $this->get_default_subject(),
        'default'     => '',
      ),
      'heading' => array(
        'title'       => __('Email heading', 'delivery-options-postnet-woocommerce'),
        'type'        => 'text',
        'desc_tip'    => true,
        'description' => $placeholder_text,
        'placeholder' => $this->get_default_heading(),
        'default'     => '',
      ),
      'additional_content' => array(
        'title'       => __('Additional content', 'delivery-options-postnet-woocommerce'),
        'description' => __('Text to appear below the main email content.', 'delivery-options-postnet-woocommerce') . ' ' . $placeholder_text,
        'css'         => 'width:400px; height: 75px;',
        'placeholder' => __('N/A', 'delivery-options-postnet-woocommerce'),
        'type'        => 'textarea',
        'default'     => '',
        'desc_tip'    => true,
      ),
      'email_type' => array(
        'title'       => __('Email type', 'delivery-options-postnet-woocommerce'),
        'type'        => 'select',
        'description' => __('Choose which format of email to send.', 'delivery-options-postnet-woocommerce'),
        'default'     => 'html',
        'class'       => 'email_type wc-enhanced-select',
        'options'     => $this->get_email_type_options(),
        'desc_tip'    => true,
      ),
    );
  }
}
