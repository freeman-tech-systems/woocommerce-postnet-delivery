(($) => {
  $(function(){
    $('#postnet_store').on('change', function(){
      $('#postnet_store_email').val($(this).find('option:selected').data('email'));
    });
    
    $('#postnet_delivery_csv').on('change', function(){
      if (confirm('Proceed with import?')){
        $(this).closest('form').submit();
      }
    });
  });
})(jQuery);

jQuery(document).ready(function($) {
    // Handle validate Google API key button click
    $('#validate_google_api').on('click', function(e) {
        e.preventDefault();
        
        const apiKey = $('#google_api_key').val();
        
        if (!apiKey) {
            Swal.fire({
                title: 'Error',
                text: 'Please enter a Google API key to validate.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        // Show spinner
        $('#google_api_spinner').addClass('is-active');
        
        // Send AJAX request to validate the API key
        $.ajax({
            url: wc_postnet_delivery_params.ajax_url,
            type: 'POST',
            data: {
                action: 'validate_google_api_key',
                security: wc_postnet_delivery_params.nonce,
                api_key: apiKey
            },
            success: function(response) {
                // Hide spinner
                $('#google_api_spinner').removeClass('is-active');
                
                if (response.success) {
                    Swal.fire({
                        title: 'Success',
                        text: response.data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.data.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                // Hide spinner
                $('#google_api_spinner').removeClass('is-active');
                
                Swal.fire({
                    title: 'Error',
                    text: 'An unexpected error occurred. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });
});
