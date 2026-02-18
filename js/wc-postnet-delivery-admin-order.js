jQuery(document).ready(function($) {
  // Handle retry waybill creation button click
  $(document).on('click', '.postnet-retry-waybill', function(e) {
    e.preventDefault();
    
    var $button = $(this);
    var $spinner = $button.siblings('.postnet-retry-spinner');
    var $message = $button.siblings('.postnet-retry-message');
    var orderId = $button.data('order-id');
    
    // Disable button and show spinner
    $button.prop('disabled', true);
    $spinner.css('visibility', 'visible');
    $message.hide().removeClass('notice-success notice-error');
    
    // Make AJAX request
    $.ajax({
      url: wc_postnet_delivery_order_params.ajax_url,
      type: 'POST',
      data: {
        action: 'wc_postnet_retry_waybill',
        nonce: wc_postnet_delivery_order_params.nonce,
        order_id: orderId
      },
      success: function(response) {
        $spinner.css('visibility', 'hidden');
        
        if (response.success) {
          // Show success message
          $message
            .addClass('notice notice-success is-dismissible')
            .html('<p>' + response.data.message + '</p>')
            .show();
          
          // Reload page after 2 seconds to show updated waybill info
          setTimeout(function() {
            location.reload();
          }, 2000);
        } else {
          // Show error message
          $message
            .addClass('notice notice-error is-dismissible')
            .html('<p><strong>' + wc_postnet_delivery_order_params.error_label + '</strong> ' + response.data.message + '</p>')
            .show();
          
          // Re-enable button
          $button.prop('disabled', false);
        }
      },
      error: function(xhr, status, error) {
        $spinner.css('visibility', 'hidden');
        $message
          .addClass('notice notice-error is-dismissible')
          .html('<p><strong>' + wc_postnet_delivery_order_params.error_label + '</strong> ' + wc_postnet_delivery_order_params.ajax_error + '</p>')
          .show();
        
        // Re-enable button
        $button.prop('disabled', false);
      }
    });
  });
});
