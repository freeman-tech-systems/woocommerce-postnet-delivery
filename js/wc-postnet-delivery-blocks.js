/**
 * PostNet Delivery Store Selection for WooCommerce Blocks Checkout
 * Enhanced version with more robust detection and insertion methods
 */
(function() {
    // Configuration
    const DEBUG = true;
    const CHECK_INTERVAL = 1000; // ms
    const MAX_ATTEMPTS = 30;
    
    // State variables
    let checkAttempts = 0;
    let checkInterval = null;
    let observer = null;
    
    // Debug logging helper
    function log(message, data) {
        if (DEBUG) {
            console.log(`[PostNet] ${message}`, data !== undefined ? data : '');
        }
    }
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        log('DOM loaded, initializing...');
        initPostNetDelivery();
    });
    
    // If DOM is already loaded, initialize immediately
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        log('DOM already loaded, initializing immediately');
        setTimeout(initPostNetDelivery, 100);
    }
    
    // Main initialization function
    function initPostNetDelivery() {
        // Register with WooCommerce Blocks API if available
        registerWithBlocksAPI();
        
        // First check
        checkShippingMethod();
        
        // Set up continuous checking
        if (checkInterval) clearInterval(checkInterval);
        checkInterval = setInterval(function() {
            checkAttempts++;
            if (checkAttempts > MAX_ATTEMPTS) {
                clearInterval(checkInterval);
                log('Max check attempts reached, stopping automatic checks');
                return;
            }
            checkShippingMethod();
        }, CHECK_INTERVAL);
        
        // Set up event listeners for shipping method changes
        setupEventListeners();
        
        // Set up mutation observer to watch for DOM changes
        setupMutationObserver();
    }
    
    // Register with WooCommerce Blocks API
    function registerWithBlocksAPI() {
        if (window.wc && window.wc.blocksCheckout) {
            log('WooCommerce Blocks API detected');
            
            const { registerCheckoutFilters } = window.wc.blocksCheckout;
            
            registerCheckoutFilters('postnet-delivery-options', {
                additionalCartData: function(data) {
                    const selectedStore = localStorage.getItem('postnet_selected_store') || '';
                    log('Adding store data to cart', selectedStore);
                    return {
                        ...data,
                        postnet_selected_store: selectedStore
                    };
                },
                
                validateShippingData: function(shippingData) {
                    if (isPostNetShippingSelected()) {
                        const selectedStore = localStorage.getItem('postnet_selected_store');
                        if (!selectedStore) {
                            log('Validation failed: No store selected');
                            return {
                               // errorMessage: 'Please select a PostNet destination store.',
                                valid: false,
                            };
                        }
                    }
                    log('Validation passed');
                    return { valid: true };
                }
            });
        } else {
            log('WooCommerce Blocks API not detected');
        }
    }
    
    // Set up event listeners
    function setupEventListeners() {
        // Listen for clicks on shipping method radio buttons
        document.body.addEventListener('click', function(e) {
            if (e.target && (
                e.target.name === 'shipping_method' || 
                e.target.classList.contains('wc-block-components-radio-control__input')
            )) {
                log('Shipping method clicked, checking in 500ms');
                setTimeout(checkShippingMethod, 500);
            }
        });
        
        // Listen for form submissions
        document.body.addEventListener('submit', function(e) {
            if (e.target && e.target.classList.contains('wc-block-components-form')) {
                log('Form submission detected');
                
                // If PostNet is selected but no store chosen, prevent submission
                if (isPostNetShippingSelected() && !localStorage.getItem('postnet_selected_store')) {
                    log('Preventing submission: No store selected');
                    e.preventDefault();
                    alert('Please select a PostNet destination store.');
                    return false;
                }
            }
        });
    }
    
    // Set up mutation observer
    function setupMutationObserver() {
        if (observer) {
            observer.disconnect();
        }
        
        observer = new MutationObserver(function(mutations) {
            // Look for changes to the shipping method section
            const hasRelevantChanges = mutations.some(mutation => {
                return mutation.target.classList && (
                    mutation.target.classList.contains('wc-block-components-shipping-rates-control') ||
                    mutation.target.closest('.wc-block-components-shipping-rates-control')
                );
            });
            
            if (hasRelevantChanges) {
                log('Relevant DOM changes detected, checking shipping method');
                checkShippingMethod();
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'checked']
        });
    }
    
    // Check if PostNet shipping is selected
    function isPostNetShippingSelected() {
        // Method 1: Check for radio buttons with labels
        const radioLabels = document.querySelectorAll('.wc-block-components-radio-control__label, .wc-block-shipping-rates-control__item-label');
        for (const label of radioLabels) {
            if (label.textContent.includes('PostNet to PostNet')) {
                const radioOption = label.closest('.wc-block-components-radio-control__option, .wc-block-shipping-rates-control__item');
                if (radioOption) {
                    const radioInput = radioOption.querySelector('input[type="radio"]');
                    if (radioInput && radioInput.checked) {
                        log('PostNet shipping detected (method 1)');
                        return true;
                    }
                }
            }
        }
        
        // Method 2: Check text content of selected shipping method
        const selectedMethod = document.querySelector('.wc-block-components-radio-control__input:checked, input[name="shipping_method"]:checked');
        if (selectedMethod) {
            const parentEl = selectedMethod.closest('.wc-block-components-radio-control__option, .wc-block-shipping-rates-control__item');
            if (parentEl && parentEl.textContent.includes('PostNet to PostNet')) {
                log('PostNet shipping detected (method 2)');
                return true;
            }
        }
        
        // Method 3: Look for any visible text indicating PostNet is selected
        const shippingSection = document.querySelector(
            '.wc-block-components-shipping-rates-control, ' +
            '.wc-block-checkout__shipping-option, ' +
            '.wc-block-shipping-totals'
        );
        
        if (shippingSection) {
            const inputs = shippingSection.querySelectorAll('input[type="radio"]:checked');
            for (const input of inputs) {
                const label = input.closest('label, div');
                if (label && label.textContent.includes('PostNet to PostNet')) {
                    log('PostNet shipping detected (method 3)');
                    return true;
                }
            }
        }
        
        log('PostNet shipping not detected');
        return false;
    }
    
    // Main function to check shipping method and update UI
    function checkShippingMethod() {
        // Make sure we're on the checkout page
        if (!isCheckoutPage()) {
            return;
        }
        
        log('Checking shipping method...');
        
        const isSelected = isPostNetShippingSelected();
        const existingSelector = document.getElementById('postnet-store-selector-container');
        
        // If PostNet is selected but no selector exists, add it
        if (isSelected && !existingSelector) {
            log('PostNet selected, adding store selector');
            injectStoreSelector();
        }
        // If PostNet is not selected but selector exists, remove it
        else if (!isSelected && existingSelector) {
            log('PostNet not selected, removing store selector');
            existingSelector.remove();
        }
        // If already in correct state, do nothing
        else {
            log('Selector state already correct', isSelected ? 'selected with selector' : 'not selected, no selector');
        }
    }
    
    // Check if this is the checkout page
    function isCheckoutPage() {
        // Method 1: Check URL
        if (window.location.href.includes('/checkout')) {
            return true;
        }
        
        // Method 2: Check for checkout blocks
        if (document.querySelector('.wc-block-checkout, .wp-block-woocommerce-checkout')) {
            return true;
        }
        
        return false;
    }
    
    // Find location to insert store selector
    function findInsertionPoint() {
        // Try multiple selectors in order of preference
        const selectors = [
            // WooCommerce blocks shipping controls
            '.wc-block-components-shipping-rates-control',
            // Shipping method fieldset
            'fieldset.wc-block-components-checkout-step:has(.wc-block-components-shipping-rates-control)',
            // Shipping address section
            '.wc-block-checkout__shipping-fields',
            // Any shipping options
            '.wc-block-checkout__shipping-option',
            // Shipping totals
            '.wc-block-shipping-totals',
            // Payment section (insert before it)
            '.wc-block-checkout__payment-method',
            // Any checkout form
            'form.wc-block-components-form'
        ];
        
        for (const selector of selectors) {
            try {
                const element = document.querySelector(selector);
                if (element) {
                    log('Found insertion point:', selector);
                    return element;
                }
            } catch (e) {
                // Some browsers might not support :has selector, just continue
            }
        }
        
        log('Could not find insertion point');
        return null;
    }
    
    // Inject the store selector into the page
    function injectStoreSelector() {
        const insertionPoint = findInsertionPoint();
        if (!insertionPoint) {
            log('No insertion point found, cannot add store selector');
            return;
        }
        
        // Create the container
        const container = document.createElement('div');
        container.id = 'postnet-store-selector-container';
        container.className = 'wc-block-components-checkout-step wc-block-components-shipping-rates-control__package postnet-store-selector';
        container.style.marginTop = '24px';
        container.style.marginBottom = '24px';
        container.style.padding = '16px';
        container.style.border = '1px solid #e0e0e0';
        container.style.borderRadius = '4px';
        
        // Create title
        const title = document.createElement('div');
        title.className = 'wc-block-components-title';
        title.innerHTML = '<span class="wc-block-components-title__text">Select PostNet Store</span>';
        title.style.marginBottom = '8px';
        container.appendChild(title);
        
        // Create loading indicator
        const loading = document.createElement('div');
        loading.textContent = 'Loading PostNet stores...';
        loading.style.padding = '8px 0';
        container.appendChild(loading);
        
        // Decide where to insert the container
        if (insertionPoint.classList.contains('wc-block-components-shipping-rates-control')) {
            // Insert after the shipping rates control
            insertionPoint.parentNode.insertBefore(container, insertionPoint.nextSibling);
        } else {
            // Try to find a good spot inside the container
            const shippingMethods = insertionPoint.querySelector('.wc-block-components-shipping-rates-control');
            if (shippingMethods) {
                shippingMethods.parentNode.insertBefore(container, shippingMethods.nextSibling);
            } else {
                // Just append to the end of the container
                insertionPoint.appendChild(container);
            }
        }
        
        // Fetch the stores
        fetchPostNetStores(container);
    }
    
    // Fetch PostNet stores
    function fetchPostNetStores(container) {
        log('Fetching PostNet stores...');
        
        if (!wc_postnet_delivery_params || !wc_postnet_delivery_params.ajax_url) {
            showError(container, 'Configuration error. Please refresh the page and try again.');
            log('Missing AJAX parameters', wc_postnet_delivery_params);
            return;
        }
        
        fetch(wc_postnet_delivery_params.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'wc_postnet_delivery_stores',
                security: wc_postnet_delivery_params.nonce
            })
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            log('Stores response received', data);
            if (data.success && data.data && data.data.length > 0) {
                renderStoreSelector(container, data.data);
            } else {
                throw new Error('Invalid store data received');
            }
        })
        .catch(function(error) {
            log('Error fetching stores', error);
            showError(container, 'Error loading stores: ' + error.message);
        });
    }
    
    // Render the store selector
    function renderStoreSelector(container, stores) {
        log('Rendering store selector with ' + stores.length + ' stores');
        
        // Clear container
        container.innerHTML = '';
        
        // Re-add title
        const title = document.createElement('div');
        title.className = 'wc-block-components-title';
        title.innerHTML = '<span class="wc-block-components-title__text">Select PostNet Store</span>';
        title.style.marginBottom = '16px';
        container.appendChild(title);
        
        // Create form field
        const fieldContainer = document.createElement('div');
        fieldContainer.className = 'wc-block-components-text-input';
        fieldContainer.style.marginBottom = '16px';
        container.appendChild(fieldContainer);
        
        // Create label
        const label = document.createElement('label');
        label.htmlFor = 'postnet-store-select';
        label.className = 'wc-block-components-form-label';
       // label.textContent = 'Destination Store';
        label.style.display = 'block';
        label.style.marginBottom = '8px';
        label.style.fontWeight = '600';
        fieldContainer.appendChild(label);
        
        // Create select wrapper
        const selectWrapper = document.createElement('div');
        selectWrapper.className = 'wc-block-components-select';
        fieldContainer.appendChild(selectWrapper);
        
        // Create select element
        const select = document.createElement('select');
        select.id = 'postnet-store-select';
        select.className = 'wc-block-components-select__input';
        select.style.width = '100%';
        select.style.padding = '10px';
        select.style.borderRadius = '4px';
        select.style.border = '1px solid #8d96a0';
        select.style.backgroundColor = '#fff';
        select.style.color = '#2c3338';
        select.style.fontSize = '16px';
        selectWrapper.appendChild(select);
        
        // Add default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select a PostNet store...';
        select.appendChild(defaultOption);
        
        // Add options for each store
        stores.forEach(function(store) {
            if (!store) return;
            
            const option = document.createElement('option');
            
            // Handle different data formats
            let storeCode = null;
            let storeName = null;
            
            if (typeof store === 'object') {
                storeCode = store.code || store.store_code;
                storeName = store.name || store.store_name;
            }
            
            if (storeCode && storeName) {
                option.value = JSON.stringify([storeCode, storeName]);
                option.textContent = storeName;
                select.appendChild(option);
            }
        });
        
        // Set previously selected store if exists
        const savedStore = localStorage.getItem('postnet_selected_store');
        if (savedStore) {
            select.value = savedStore;
            log('Restored previously selected store', savedStore);
        }
        
        // Add change event handler
        select.addEventListener('change', function() {
            const value = this.value;
            log('Store selected', value);
            
            // Save selection
            localStorage.setItem('postnet_selected_store', value);
            document.cookie = 'postnet_selected_store=' + encodeURIComponent(value) + '; path=/; max-age=86400';
            
            // Add hidden input to form
            ensureHiddenInput(value);
            
            // Update validation state
            updateValidationState(container);
        });
        
        // Add validation message area
        const validationMsg = document.createElement('div');
        validationMsg.id = 'postnet-store-validation';
        validationMsg.style.color = '#cc1818';
        validationMsg.style.marginTop = '8px';
        validationMsg.style.fontSize = '14px';
        fieldContainer.appendChild(validationMsg);
        
        // Initial validation
        updateValidationState(container);
    }
    
    // Ensure hidden input exists with the correct value
    function ensureHiddenInput(value) {
        let hiddenInput = document.getElementById('postnet_selected_store_input');
        
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.id = 'postnet_selected_store_input';
            hiddenInput.name = 'postnet_selected_store';
            
            // Find form to add the input to
            const form = document.querySelector('form.wc-block-components-form, .woocommerce-checkout');
            if (form) {
                form.appendChild(hiddenInput);
                log('Added hidden input to form');
            } else {
                log('Could not find form to add hidden input');
                // Add to body as fallback
                document.body.appendChild(hiddenInput);
            }
        }
        
        hiddenInput.value = value;
    }
    
    // Update the validation state
    function updateValidationState(container) {
        const validationMsg = document.getElementById('postnet-store-validation');
        const selectedStore = localStorage.getItem('postnet_selected_store');
        
        if (validationMsg) {
            if (!selectedStore) {
                //validationMsg.textContent = 'Please select a destination store';
                validationMsg.style.display = 'block';
                
                // Attempt to find and disable the place order button
                const placeOrderButtons = document.querySelectorAll('button[type="submit"], .wc-block-components-checkout-place-order-button');
                placeOrderButtons.forEach(button => {
                    if (button.textContent.toLowerCase().includes('place order')) {
                        button.setAttribute('disabled', 'disabled');
                        log('Disabled place order button');
                    }
                });
            } else {
                validationMsg.textContent = '';
                validationMsg.style.display = 'none';
                
                // Re-enable place order button
                const placeOrderButtons = document.querySelectorAll('button[type="submit"][disabled], .wc-block-components-checkout-place-order-button[disabled]');
                placeOrderButtons.forEach(button => {
                    if (button.textContent.toLowerCase().includes('place order')) {
                        button.removeAttribute('disabled');
                        log('Enabled place order button');
                    }
                });
            }
        }
    }
    
    // Show error message
    function showError(container, message) {
        log('Showing error', message);
        
        // Clear container
        container.innerHTML = '';
        
        // Re-add title
        const title = document.createElement('div');
        title.className = 'wc-block-components-title';
        title.innerHTML = '<span class="wc-block-components-title__text">Select PostNet Store</span>';
        container.appendChild(title);
        
        // Add error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'wc-block-components-validation-error';
        errorDiv.style.color = '#cc1818';
        errorDiv.style.marginTop = '16px';
        errorDiv.style.padding = '8px';
        errorDiv.style.border = '1px solid #cc1818';
        errorDiv.style.borderRadius = '4px';
        errorDiv.textContent = message;
        container.appendChild(errorDiv);
        
        // Add retry button
        const retryButton = document.createElement('button');
        retryButton.type = 'button';
        retryButton.className = 'wc-block-components-button wc-block-components-button--secondary';
        retryButton.textContent = 'Retry';
        retryButton.style.marginTop = '16px';
        retryButton.addEventListener('click', function() {
            log('Retry button clicked');
            container.innerHTML = '<div style="padding: 16px;">Retrying...</div>';
            setTimeout(() => {
                fetchPostNetStores(container);
            }, 500);
        });
        container.appendChild(retryButton);
    }
})();
