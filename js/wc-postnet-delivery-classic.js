(function($) {
  'use strict';
  
  // Initialize on document ready
  $(document).ready(function() {
    // Handle store selection from dropdown
    $(document).on('change', 'select[name="destination_store"]', function() {
      const selectedValue = $(this).val();
      
      if (selectedValue) {
        try {
          // Try to parse the store data
          const storeData = JSON.parse(selectedValue);
          if (Array.isArray(storeData) && storeData.length >= 2) {
            const storeCode = storeData[0];
            const storeName = storeData[1];
            
            // Save to cookies for order processing
            document.cookie = 'postnet_selected_store=' + encodeURIComponent(selectedValue) + '; path=/; max-age=86400';
          }
        } catch (e) {
          console.error('[PostNet] Error parsing store data', e);
        }
      }
    });
    
    // Initialize if there's a saved selection from a previous session
    const postnetCookie = getCookie('postnet_selected_store');
    if (postnetCookie) {
      $('select[name="destination_store"]').val(postnetCookie);
    }
  });
  
  // Helper function to get cookie value by name
  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    if (match) return decodeURIComponent(match[2]);
    return null;
  }
  
})(jQuery);
