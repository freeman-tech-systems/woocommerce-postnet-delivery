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
    
    // Multi Site Mode toggle functionality
    $('input[name="wc_postnet_delivery_options[multi_site_mode]"]').on('change', function() {
      if ($(this).is(':checked')) {
        $('#collection-addresses-section').show();
        // Add required back to collection address fields
        $('#collection-addresses-section').find('input[type="text"], select').each(function() {
          // Don't add required to the optional "Additional Address Info" field
          if ($(this).attr('name') && $(this).attr('name').indexOf('[sender_other_address]') === -1) {
            $(this).attr('required', 'required');
          }
        });
      } else {
        $('#collection-addresses-section').hide();
        // Remove required from all collection address fields so the form can be saved
        $('#collection-addresses-section').find('input[type="text"], select').removeAttr('required');
      }
    });
    
    // Add new collection address
    $('#add-collection-address').on('click', function() {
      const container = $('#collection-addresses-container');
      const addresses = container.find('.collection-address');
      const newIndex = addresses.length;
      
      const newAddress = `
        <div class="collection-address" data-index="${newIndex}">
          <h3>Collection Address #${newIndex + 1}</h3>
          <table class="form-table">
            <tr>
              <th scope="row"><label for="sender_street_address_${newIndex}">Street Address</label></th>
              <td>
                <input type="text" name="wc_postnet_delivery_options[collection_addresses][${newIndex}][sender_street_address]" id="sender_street_address_${newIndex}" style="width:400px;" value="" required />
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="sender_other_address_${newIndex}">Additional Address Info</label></th>
              <td>
                <input type="text" name="wc_postnet_delivery_options[collection_addresses][${newIndex}][sender_other_address]" id="sender_other_address_${newIndex}" style="width:400px;" value="" />
                <p class="description">Optional: Additional address information like unit number, building name, etc.</p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="sender_country_code_${newIndex}">Country Code</label></th>
              <td>
                <select name="wc_postnet_delivery_options[collection_addresses][${newIndex}][sender_country_code]" id="sender_country_code_${newIndex}" required>
                  <option value="ZA">South Africa</option>
                  <option value="BW">Botswana</option>
                  <option value="LS">Lesotho</option>
                  <option value="NA">Namibia</option>
                  <option value="SZ">Eswatini</option>
                </select>
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="sender_suburb_${newIndex}">Suburb/City</label></th>
              <td>
                <input type="text" name="wc_postnet_delivery_options[collection_addresses][${newIndex}][sender_suburb]" id="sender_suburb_${newIndex}" style="width:300px;" value="" required />
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="sender_postal_code_${newIndex}">Postal Code</label></th>
              <td>
                <input type="text" name="wc_postnet_delivery_options[collection_addresses][${newIndex}][sender_postal_code]" id="sender_postal_code_${newIndex}" style="width:150px;" value="" required />
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="sender_name_${newIndex}">Company/Business Name</label></th>
              <td>
                <input type="text" name="wc_postnet_delivery_options[collection_addresses][${newIndex}][sender_name]" id="sender_name_${newIndex}" style="width:400px;" value="" required />
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="sender_contact_number_${newIndex}">Contact Number</label></th>
              <td>
                <input type="text" name="wc_postnet_delivery_options[collection_addresses][${newIndex}][sender_contact_number]" id="sender_contact_number_${newIndex}" style="width:200px;" value="" required />
              </td>
            </tr>
            <tr>
              <th scope="row"><label for="sender_contact_person_${newIndex}">Contact Person</label></th>
              <td>
                <input type="text" name="wc_postnet_delivery_options[collection_addresses][${newIndex}][sender_contact_person]" id="sender_contact_person_${newIndex}" style="width:300px;" value="" required />
              </td>
            </tr>
          </table>
          <button type="button" class="button remove-address" data-index="${newIndex}">Remove Address</button>
        </div>
      `;
      
      container.append(newAddress);
    });
    
    // Remove collection address
    $(document).on('click', '.remove-address', function() {
      const index = $(this).data('index');
      const addressElement = $(this).closest('.collection-address');
      
      if (confirm('Are you sure you want to remove this collection address?')) {
        addressElement.remove();
        
        // Reindex remaining addresses
        $('#collection-addresses-container .collection-address').each(function(newIndex) {
          const oldIndex = $(this).data('index');
          $(this).attr('data-index', newIndex);
          $(this).find('h3').text(`Collection Address #${newIndex + 1}`);
          
          // Update all input names and IDs
          $(this).find('input, select').each(function() {
            const oldName = $(this).attr('name');
            const oldId = $(this).attr('id');
            
            if (oldName) {
              $(this).attr('name', oldName.replace(`[${oldIndex}]`, `[${newIndex}]`));
            }
            if (oldId) {
              $(this).attr('id', oldId.replace(`_${oldIndex}`, `_${newIndex}`));
            }
          });
          
          $(this).find('.remove-address').attr('data-index', newIndex);
        });
      }
    });
    
    // Validate order status change to completed
    $(document).on('change', 'select[name="order_status"]', function() {
      const selectedStatus = $(this).val();
      
      if (selectedStatus === 'wc-completed') {
        // Check if we're in Multi Site Mode
        const multiSiteMode = $('input[name="wc_postnet_delivery_options[multi_site_mode]"]').is(':checked');
        
        if (multiSiteMode) {
          // Check if collection address is selected
          const collectionAddressIndex = $('#collection_address_index').val();
          
          if (collectionAddressIndex === '' || collectionAddressIndex === null) {
            // Show warning and prevent status change
            alert('Warning: You must select a collection address before marking this order as completed in Multi Site Mode.');
            $(this).val(''); // Reset to current status
            return false;
          }
        }
      }
    });
    
    // Also validate when the order status dropdown is in the order actions area
    $(document).on('change', '#order_status', function() {
      const selectedStatus = $(this).val();
      
      if (selectedStatus === 'completed') {
        // Check if we're in Multi Site Mode by looking for the collection address field
        if ($('#collection_address_index').length > 0) {
          // Check if collection address is selected
          const collectionAddressIndex = $('#collection_address_index').val();
          
          if (collectionAddressIndex === '' || collectionAddressIndex === null) {
            // Show warning and prevent status change
            alert('Warning: You must select a collection address before marking this order as completed in Multi Site Mode.');
            $(this).val(''); // Reset to current status
            return false;
          }
        }
      }
    });
    
    // Function to refresh visual indicators based on current selection
    function refreshCollectionAddressIndicators() {
      const collectionAddressSelect = $('#collection_address_index');
      if (collectionAddressSelect.length > 0) {
        const selectedAddress = collectionAddressSelect.val();
        const addressContainer = collectionAddressSelect.closest('.address');
        
        // Remove existing status classes
        addressContainer.removeClass('collection-address-required collection-address-success');
        
        if (selectedAddress !== '' && selectedAddress !== null) {
          // Address selected - show success state
          addressContainer.addClass('collection-address-success');
          
          // Update description text
          const description = addressContainer.find('.description');
          if (description.length) {
            description.html('✅ Collection address selected. Order can now be completed.');
          }
          
          // Remove ALL warning messages (including the PHP-generated one about completed orders)
          addressContainer.find('.collection-address-warning').remove();
          
          // Remove any existing success messages to avoid duplicates
          addressContainer.find('.collection-address-success').not(':first').remove();
          
          // Add success message if it doesn't exist
          if (addressContainer.find('.collection-address-success').length === 0) {
            const successMsg = $('<div class="collection-address-success">Collection address selected. Order can be completed and waybill will be created.</div>');
            collectionAddressSelect.before(successMsg);
          }
        } else {
          // No address selected - show required state
          addressContainer.addClass('collection-address-required');
          
          // Update description text
          const description = addressContainer.find('.description');
          if (description.length) {
            description.html('⚠️ REQUIRED: Select the collection address for this order. Waybill creation requires this selection in Multi Site Mode.');
          }
          
          // Remove success message if it exists
          addressContainer.find('.collection-address-success').remove();
          
          // Add warning message if it doesn't exist
          if (addressContainer.find('.collection-address-warning').length === 0) {
            const warningMsg = $('<div class="collection-address-warning">Warning: No collection address selected. Order cannot be completed until an address is chosen.</div>');
            collectionAddressSelect.before(warningMsg);
          }
        }
      }
    }
    
    // Refresh indicators when page loads (with a small delay to ensure DOM is ready)
    setTimeout(function() {
      refreshCollectionAddressIndicators();
    }, 100);
    
    // Also refresh when the page becomes visible (handles form submission and page refresh)
    $(document).on('visibilitychange', function() {
      if (!document.hidden) {
        setTimeout(function() {
          refreshCollectionAddressIndicators();
        }, 200);
      }
    });
    
    // Handle WooCommerce admin form submissions and updates
    $(document).on('submit', 'form', function() {
      // Set a flag to refresh indicators after form submission
      sessionStorage.setItem('refreshPostNetIndicators', 'true');
    });
    
    // Check for the refresh flag when page loads
    if (sessionStorage.getItem('refreshPostNetIndicators')) {
      sessionStorage.removeItem('refreshPostNetIndicators');
      setTimeout(function() {
        refreshCollectionAddressIndicators();
      }, 500);
    }
    
    // Check if we need to refresh indicators based on URL parameters or page state
    if (window.location.search.includes('message=') || window.location.search.includes('updated=')) {
      setTimeout(function() {
        refreshCollectionAddressIndicators();
      }, 300);
    }
    
    // Watch for DOM changes that might indicate the form was updated
    if (typeof MutationObserver !== 'undefined') {
      const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
            // Check if any of the added nodes contain our collection address field
            mutation.addedNodes.forEach(function(node) {
              if (node.nodeType === 1 && $(node).find('#collection_address_index').length > 0) {
                setTimeout(function() {
                  refreshCollectionAddressIndicators();
                }, 100);
              }
            });
          }
        });
      });
      
      // Start observing the document body for changes
      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    }
    
    // Update visual indicators when collection address selection changes
    $(document).on('change', '#collection_address_index', function() {
      const selectedAddress = $(this).val();
      const addressContainer = $(this).closest('.address');
      
      // Remove existing status classes
      addressContainer.removeClass('collection-address-required collection-address-success');
      
      if (selectedAddress && selectedAddress !== '') {
        // Address selected - show success state
        addressContainer.addClass('collection-address-success');
        
        // Update description text
        const description = addressContainer.find('.description');
        if (description.length) {
          description.html('✅ Collection address selected. Order can now be completed.');
        }
        
        // Remove ALL warning messages (including the PHP-generated one about completed orders)
        addressContainer.find('.collection-address-warning').remove();
        
        // Remove any existing success messages to avoid duplicates
        addressContainer.find('.collection-address-success').not(':first').remove();
        
        // Add success message if it doesn't exist
        if (addressContainer.find('.collection-address-success').length === 0) {
          const successMsg = $('<div class="collection-address-success">Collection address selected. Order can be completed and waybill will be created.</div>');
          $(this).before(successMsg);
        }
      } else {
        // No address selected - show required state
        addressContainer.addClass('collection-address-required');
        
        // Update description text
        const description = addressContainer.find('.description');
        if (description.length) {
          description.html('⚠️ REQUIRED: Select the collection address for this order. Waybill creation requires this selection in Multi Site Mode.');
        }
        
        // Remove success message if it exists
        addressContainer.find('.collection-address-success').remove();
        
        // Add warning message if it doesn't exist
        if (addressContainer.find('.collection-address-warning').length === 0) {
          const warningMsg = $('<div class="collection-address-warning">Warning: No collection address selected. Order cannot be completed until an address is chosen.</div>');
          $(this).before(warningMsg);
        }
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
