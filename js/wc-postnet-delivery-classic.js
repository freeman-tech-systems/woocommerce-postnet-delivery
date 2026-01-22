(function($) {
  'use strict';
  
  let currentAddress = null;
  let addressChangeTimeout = null;
  
  // Function to get current delivery address
  function getCurrentAddress() {
    const address = {
      address_1: $('#shipping_address_1').val() || '',
      address_2: $('#shipping_address_2').val() || '',
      city: $('#shipping_city').val() || '',
      state: $('#shipping_state').val() || '',
      postcode: $('#shipping_postcode').val() || '',
      country: $('#shipping_country').val() || ''
    };
    return JSON.stringify(address);
  }
  
  // Function to auto-select first store (closest)
  function autoSelectClosestStore() {
    const storeSelect = $('select[name="destination_store"]');
    if (storeSelect.length && storeSelect.find('option').length > 1) {
      // Skip the first option if it's the "Select a Store..." placeholder
      const firstStoreOption = storeSelect.find('option').not(':first');
      if (firstStoreOption.length > 0) {
        const firstStoreValue = firstStoreOption.first().val();
        if (firstStoreValue) {
          storeSelect.val(firstStoreValue).trigger('change');
          console.log('[PostNet] Auto-selected closest store');
        }
      }
    }
  }
  
  // Function to re-fetch stores when address changes
  function refetchStoresForNewAddress() {
    const storeSelect = $('select[name="destination_store"]');
    if (!storeSelect.length) {
      return; // Store selector not present
    }
    
    // Show loading state
    storeSelect.prop('disabled', true);
    const originalHtml = storeSelect.html();
    storeSelect.html('<option value="">Loading stores...</option>');
    
    // Clear previous selection
    localStorage.removeItem('postnet_selected_store');
    document.cookie = 'postnet_selected_store=; path=/; max-age=0';
    
    // Fetch new stores
    $.ajax({
      url: wc_postnet_delivery_params.ajax_url,
      type: 'POST',
      data: {
        action: 'wc_postnet_delivery_stores',
        security: wc_postnet_delivery_params.nonce
      },
      success: function(response) {
        if (response.success && response.data && response.data.length > 0) {
          // Clear and rebuild dropdown
          storeSelect.html('<option value="">Select a Store...</option>');
          
          response.data.forEach(function(store) {
            if (store && store.code && store.name) {
              const optionValue = JSON.stringify([store.code, store.name]);
              storeSelect.append($('<option></option>')
                .attr('value', optionValue)
                .text(store.name));
            }
          });
          
          // Auto-select first store (closest)
          autoSelectClosestStore();
        } else {
          storeSelect.html(originalHtml);
          console.error('[PostNet] No stores found for new address');
        }
      },
      error: function() {
        storeSelect.html(originalHtml);
        console.error('[PostNet] Error fetching stores');
      },
      complete: function() {
        storeSelect.prop('disabled', false);
      }
    });
  }
  
  // Initialize on document ready
  $(document).ready(function() {
    // Auto-select closest store on page load if available
    setTimeout(function() {
      autoSelectClosestStore();
    }, 500);
    
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
    
    // Initialize current address
    currentAddress = getCurrentAddress();
    
    // Watch for address field changes
    $('#shipping_address_1, #shipping_address_2, #shipping_city, #shipping_state, #shipping_postcode, #shipping_country').on('input change', function() {
      const newAddress = getCurrentAddress();
      
      // Only trigger if address actually changed and has meaningful data
      if (newAddress !== currentAddress && 
          newAddress !== '{"address_1":"","address_2":"","city":"","state":"","postcode":"","country":""}') {
        
        // Clear timeout if one exists
        if (addressChangeTimeout) {
          clearTimeout(addressChangeTimeout);
        }
        
        // Debounce the store re-fetch
        addressChangeTimeout = setTimeout(function() {
          currentAddress = newAddress;
          console.log('[PostNet] Address changed, re-fetching stores');
          refetchStoresForNewAddress();
        }, 1000); // 1 second debounce
      }
    });
    
    // Also listen for WooCommerce address update events
    $(document.body).on('updated_checkout', function() {
      const newAddress = getCurrentAddress();
      if (newAddress !== currentAddress && 
          newAddress !== '{"address_1":"","address_2":"","city":"","state":"","postcode":"","country":""}') {
        currentAddress = newAddress;
        console.log('[PostNet] Checkout updated, re-fetching stores');
        refetchStoresForNewAddress();
      }
    });
  });
  
  // Helper function to get cookie value by name
  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    if (match) return decodeURIComponent(match[2]);
    return null;
  }
  
})(jQuery);
