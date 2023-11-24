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
