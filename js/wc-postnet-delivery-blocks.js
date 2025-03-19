/**
 * PostNet Delivery Store Selection for WooCommerce Blocks Checkout
 * Enhanced version with Map and List views
 */
(function() {
    // Configuration
    const DEBUG = false;
    
    // State variables
    let observer = null;
    let map = null;
    let markers = [];
    let selectedMarker = null;
    let storeDetails = {}; // Cache for store details
    
    // Debug logging helper
    function log(message, data) {
        if (DEBUG) {
            console.log(`[PostNet] ${message}`, data !== undefined ? data : '');
        }
    }
    
    // Helper function to get selected store from localStorage
    function getSelectedStore() {
        return localStorage.getItem('postnet_selected_store') || '';
    }
    
    // Helper function to set selected store in localStorage
    function setSelectedStore(store) {
        selectStore(store.code, store.name);
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
                    const selectedStore = getSelectedStore();
                    log('Adding store data to cart', selectedStore);
                    return {
                        ...data,
                        postnet_selected_store: selectedStore
                    };
                },
                
                validateShippingData: function(shippingData) {
                    if (isPostNetShippingSelected()) {
                        const selectedStore = getSelectedStore();
                        if (!selectedStore) {
                            log('Validation failed: No store selected');
                            return {
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
            
            // Handle tab switching
            if (e.target && e.target.classList.contains('postnet-tab-header')) {
                const tabName = e.target.dataset.tab;
                switchTab(tabName);
            }
            
            // Handle store selection from list
            if (e.target && (e.target.classList.contains('postnet-store-item') || e.target.closest('.postnet-store-item'))) {
                const storeItem = e.target.classList.contains('postnet-store-item') ? 
                    e.target : e.target.closest('.postnet-store-item');
                
                const storeCode = storeItem.dataset.storeCode;
                const storeName = storeItem.dataset.storeName;
                
                // Select store
                selectStore(storeCode, storeName, storeItem);
            }
            
            // Handle pagination clicks
            if (e.target && e.target.classList.contains('postnet-page-number')) {
                const pageNumber = e.target.dataset.page;
                
                // Update active page
                document.querySelectorAll('.postnet-page-number').forEach(el => {
                    el.classList.remove('active');
                });
                e.target.classList.add('active');
                
                // Show active page content
                document.querySelectorAll('.postnet-list-page').forEach(el => {
                    el.classList.remove('active');
                });
                document.querySelector(`.postnet-list-page[data-page="${pageNumber}"]`).classList.add('active');
            }
        });
        
        // Listen for form submissions
        document.body.addEventListener('submit', function(e) {
            if (e.target && e.target.classList.contains('wc-block-components-form')) {
                log('Form submission detected');
                
                // If PostNet is selected but no store chosen, prevent submission
                if (isPostNetShippingSelected() && !getSelectedStore()) {
                    log('Preventing submission: No store selected');
                    e.preventDefault();
                    alert('Please select a PostNet destination store.');
                    return false;
                }
            }
        });
    }
    
    // Switch between tabs
    function switchTab(tabName) {
        log('Switching to tab:', tabName);
        
        // Update tab headers
        const tabHeaders = document.querySelectorAll('.postnet-tab-header');
        tabHeaders.forEach(header => {
            if (header.dataset.tab === tabName) {
                header.classList.add('active');
            } else {
                header.classList.remove('active');
            }
        });
        
        // Update tab content
        const tabContents = document.querySelectorAll('.postnet-tab-content');
        tabContents.forEach(content => {
            if (content.dataset.tab === tabName) {
                content.classList.add('active');
                
                // If switching to map tab and we have a map, trigger resize event
                if (tabName === 'map' && map) {
                    window.setTimeout(() => {
                        google.maps.event.trigger(map, 'resize');
                        
                        // If we have markers, fit bounds
                        if (markers.length > 0) {
                            const bounds = new google.maps.LatLngBounds();
                            markers.forEach(marker => bounds.extend(marker.getPosition()));
                            map.fitBounds(bounds);
                            
                            // If we have a selected store, center on it
                            if (selectedMarker) {
                                window.setTimeout(() => {
                                    map.setCenter(selectedMarker.getPosition());
                                    map.setZoom(15);
                                }, 100);
                            }
                        }
                    }, 50);
                }
            } else {
                content.classList.remove('active');
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

        // Check if Google Maps is available
        const hasGoogleMaps = window.wc_postnet_delivery_params && 
                             window.wc_postnet_delivery_params.has_google_maps &&
                             window.google && window.google.maps;
        
        log('Has Google Maps:', hasGoogleMaps);
        
        // Create the container
        const container = document.createElement('div');
        container.id = 'postnet-store-selector-container';
        container.className = 'wc-block-components-checkout-step wc-block-components-shipping-rates-control__package postnet-store-selector';
        
        // Create title
        const title = document.createElement('div');
        title.className = 'wc-block-components-title';
        title.innerHTML = '<span class="wc-block-components-title__text">Select PostNet Store</span>';
        title.style.marginBottom = '16px';
        container.appendChild(title);
        
        if (hasGoogleMaps) {
            // Create tab headers for advanced view
            const tabHeaders = document.createElement('div');
            tabHeaders.className = 'postnet-tab-headers';
            tabHeaders.innerHTML = `
                <div class="postnet-tab-header active" data-tab="map">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    Map
                </div>
                <div class="postnet-tab-header" data-tab="list">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>
                    </svg>
                    List
                </div>
            `;
            container.appendChild(tabHeaders);
            
            // Create map tab content
            const mapContent = document.createElement('div');
            mapContent.className = 'postnet-tab-content active';
            mapContent.dataset.tab = 'map';
            mapContent.innerHTML = `
                <div id="postnet-map-container"></div>
                <div id="postnet-selected-store-details"></div>
            `;
            container.appendChild(mapContent);
            
            // Create list tab content
            const listContent = document.createElement('div');
            listContent.className = 'postnet-tab-content';
            listContent.dataset.tab = 'list';
            container.appendChild(listContent);
        } else {
            // Simple dropdown view when no Google Maps API key
            log('No Google Maps API key, using simple dropdown');
            const dropdownContent = document.createElement('div');
            dropdownContent.className = 'postnet-tab-content active';
            container.appendChild(dropdownContent);
        }
        
        // Add hidden input field (always needed)
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'destination_store';
        hiddenInput.id = 'destination_store';
        hiddenInput.value = '';
        container.appendChild(hiddenInput);
        
        // Insert the container into the DOM
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
                allStores = data.data;
                if (!getSelectedStore()) { setSelectedStore(data.data[0]); }
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
        
        const hasGoogleMaps = window.wc_postnet_delivery_params && 
                            window.wc_postnet_delivery_params.has_google_maps &&
                            window.google && window.google.maps;
        
        // For simple dropdown view (no Google Maps)
        if (!hasGoogleMaps) {
            renderDropdownView(container.querySelector('.postnet-tab-content'), stores);
        } else {
            // Render list view for tab interface
            const listTab = container.querySelector('.postnet-tab-content[data-tab="list"]');
            if (listTab) {
                renderListView(listTab, stores);
            }
            
            // Render map view
            const mapContainer = document.getElementById('postnet-map-container');
            if (mapContainer) {
                setTimeout(() => initializeMap(mapContainer, stores), 100);
            }
        }
        
        // Instead of restoring the store selection here,
        // we'll do it after markers are fully loaded to ensure proper indexing
    }
    
    // Render dropdown view (for when Google Maps API is not available)
    function renderDropdownView(container, stores) {
        container.innerHTML = '';
        
        // Create form field
        const fieldContainer = document.createElement('div');
        fieldContainer.className = 'wc-block-components-text-input';
        fieldContainer.style.marginBottom = '16px';
        container.appendChild(fieldContainer);
        
        // Create label
        const label = document.createElement('label');
        label.htmlFor = 'postnet-store-select';
        label.className = 'wc-block-components-form-label';
        //label.textContent = 'Select a PostNet Store';
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
        const savedStore = getSelectedStore();
        if (savedStore) {
            select.value = savedStore;
            log('Restored previously selected store', savedStore);
        }
        
        // Add change event handler
        select.addEventListener('change', function() {
            const value = this.value;
            log('Store selected from dropdown', value);
            
            if (!value) {
                return;
            }
            
            try {
                const storeData = JSON.parse(value);
                if (Array.isArray(storeData) && storeData.length >= 2) {
                    selectStoreFromDropdown(storeData[0], storeData[1], value);
                }
            } catch (e) {
                log('Error handling store selection', e);
            }
        });
        
        // Add validation message area
        const validationMsg = document.createElement('div');
        validationMsg.id = 'postnet-store-validation';
        validationMsg.style.color = '#cc1818';
        validationMsg.style.marginTop = '8px';
        validationMsg.style.fontSize = '14px';
        validationMsg.style.display = 'none';
        fieldContainer.appendChild(validationMsg);
    }
    
    // Initialize Map - Make styling match the design
    function initializeMap(mapContainer, stores) {
        // Custom map style
        const mapStyles = [
            {
                featureType: "poi",
                elementType: "labels",
                stylers: [{ visibility: "off" }]
            },
            {
                featureType: "transit",
                elementType: "labels",
                stylers: [{ visibility: "off" }]
            }
        ];
        
        // Create map with custom styling
        map = new google.maps.Map(mapContainer, {
            center: { lat: -29.0, lng: 24.0 },  // Center of South Africa
            zoom: 5,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            styles: mapStyles,
            zoomControlOptions: {
                position: google.maps.ControlPosition.RIGHT_TOP
            }
        });
        
        markers = [];
        let bounds = new google.maps.LatLngBounds();
        let hasValidCoordinates = false;
        
        // Create info window for markers
        const infoWindow = new google.maps.InfoWindow();
        
        let loadedMarkers = 0;
        const totalStores = stores.length;
        
        // Process each store and add markers
        stores.forEach(store => {
            // Get store details
            getStoreDetails(store.code).then(storeDetails => {
                if (storeDetails.lat && storeDetails.lng) {
                    // Define position
                    const position = { lat: storeDetails.lat, lng: storeDetails.lng };
                    
                    // Create marker with classic API instead of AdvancedMarkerElement
                    const marker = new google.maps.Marker({
                        position: position,
                        map: map,
                        title: storeDetails.name,
                        // Store custom info directly on the marker
                        code: storeDetails.code,
                        name: storeDetails.name,
                        icon: {
                            url: window.wc_postnet_delivery_params.map_marker_url,
                            size: new google.maps.Size(40, 40),
                            scaledSize: new google.maps.Size(40, 40),
                            origin: new google.maps.Point(0, 0),
                            anchor: new google.maps.Point(20, 40)
                        }
                    });
                    
                    // Create info window content
                    const infoContent = `
                        <div class="postnet-map-info">
                            <strong>${storeDetails.name}</strong><br>
                            ${storeDetails.address}<br>
                            <button class="postnet-map-select-btn" 
                                    onclick="selectMapStore('${storeDetails.code}', '${storeDetails.name.replace(/'/g, "\\'")}')">
                                Select Store
                            </button>
                        </div>
                    `;
                    
                    // Add click event for marker
                    marker.addListener('click', () => {
                        infoWindow.setContent(infoContent);
                        infoWindow.open(map, marker);
                    });
                    
                    markers.push(marker);
                    bounds.extend(position);
                    hasValidCoordinates = true;
                    
                    loadedMarkers++;
                    
                    // Fit bounds after all markers are added
                    if (hasValidCoordinates && loadedMarkers === totalStores) {
                        map.fitBounds(bounds);
                        
                        // Now that all markers are loaded, we can restore the selected store
                        restoreSelectedStore();
                    }
                } else {
                    loadedMarkers++;
                    // Still check if this was the last store to process
                    if (loadedMarkers === totalStores) {
                        // If we have any valid markers
                        if (markers.length > 0 && hasValidCoordinates) {
                            map.fitBounds(bounds);
                        }
                        restoreSelectedStore();
                    }
                }
            }).catch(error => {
                log('Error getting store details', error);
                loadedMarkers++;
                // Still check if this was the last store
                if (loadedMarkers === totalStores) {
                    if (markers.length > 0 && hasValidCoordinates) {
                        map.fitBounds(bounds);
                    }
                    restoreSelectedStore();
                }
            });
        });
        
        // Add function to global scope to handle marker selection from info window
        window.selectMapStore = function(code, name) {
            selectStore(code, name);
            infoWindow.close();
        };
    }
    
    // Function to restore selected store from localStorage
    function restoreSelectedStore() {
        const savedStore = getSelectedStore();
        if (savedStore) {
            try {
                const storeData = JSON.parse(savedStore);
                if (Array.isArray(storeData) && storeData.length >= 2) {
                    log('Restoring selected store after markers loaded:', storeData[1]);
                    selectStore(storeData[0], storeData[1]);
                }
            } catch (e) {
                log('Error restoring selected store', e);
            }
        }
    }
    
    // Render the list view
    function renderListView(container, stores) {
        container.innerHTML = '';
        
        // Create stores list container
        const storesListDiv = document.createElement('div');
        storesListDiv.className = 'postnet-stores-list';
        container.appendChild(storesListDiv);
        
        let counter = 0;
        let page = 1;
        
        let pageDiv = document.createElement('div');
        pageDiv.className = 'postnet-list-page active';
        pageDiv.dataset.page = page;
        storesListDiv.appendChild(pageDiv);
        
        // Add stores to the list
        stores.forEach(store => {
            if (counter % 5 === 0 && counter > 0) {
                // Create new page
                page++;
                pageDiv = document.createElement('div');
                pageDiv.className = 'postnet-list-page';
                pageDiv.dataset.page = page;
                storesListDiv.appendChild(pageDiv);
            }
            
            // Create store item
            const storeItem = document.createElement('div');
            storeItem.className = 'postnet-store-item';
            storeItem.dataset.storeCode = store.code;
            storeItem.dataset.storeName = store.name;
            
            // Store name with radio button
            const storeNameDiv = document.createElement('div');
            storeNameDiv.className = 'postnet-store-radio';
            
            // Create radio input
            const radioInput = document.createElement('input');
            radioInput.type = 'radio';
            radioInput.name = 'postnet-store';
            radioInput.value = store.code;
            radioInput.id = `store-radio-${store.code}`;
            
            // Create label for the radio
            const radioLabel = document.createElement('label');
            radioLabel.className = 'postnet-store-name';
            radioLabel.htmlFor = `store-radio-${store.code}`;
            radioLabel.textContent = store.name;
            
            storeNameDiv.appendChild(radioInput);
            storeNameDiv.appendChild(radioLabel);
            storeItem.appendChild(storeNameDiv);
            
            // Loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'postnet-store-loading';
            loadingDiv.textContent = 'Loading store details...';
            storeItem.appendChild(loadingDiv);
            
            pageDiv.appendChild(storeItem);
            
            // Load store details
            getStoreDetails(store.code).then(storeDetails => {
                // Remove loading indicator
                loadingDiv.remove();
                
                // Add address
                const addressDiv = document.createElement('div');
                addressDiv.className = 'postnet-store-address-details';
                addressDiv.innerHTML = `
                    ${storeDetails.address}, ${storeDetails.city}<br>
                    ${storeDetails.province}, ${storeDetails.postal_code}
                `;
                storeItem.appendChild(addressDiv);
                
                // Add click event to radio button
                radioInput.addEventListener('change', function() {
                    if (this.checked) {
                        selectStore(store.code, store.name, storeItem);
                    }
                });
                
            }).catch(error => {
                loadingDiv.textContent = 'Error loading store details.';
                log('Error loading store details', error);
            });
            
            // Add click handler to entire store item
            storeItem.addEventListener('click', function(e) {
                if (e.target !== radioInput) { // Only if the radio itself wasn't clicked
                    radioInput.checked = true;
                    selectStore(store.code, store.name, storeItem);
                }
            });
            
            counter++;
        });
        
        // Add pagination if needed
        if (page > 1) {
            const paginationDiv = document.createElement('div');
            paginationDiv.className = 'postnet-pagination';
            
            for (let i = 1; i <= page; i++) {
                const pageBtn = document.createElement('span');
                pageBtn.className = 'postnet-page-number' + (i === 1 ? ' active' : '');
                pageBtn.dataset.page = i;
                pageBtn.textContent = i;
                paginationDiv.appendChild(pageBtn);
            }
            
            storesListDiv.appendChild(paginationDiv);
        }
        
        // Add validation message area
        const validationMsg = document.createElement('div');
        validationMsg.id = 'postnet-store-validation';
        validationMsg.style.display = 'none';
        container.appendChild(validationMsg);
    }
    
    // Select a store
    function selectStore(storeCode, storeName, listItem, mapMarker) {
        log('Selecting store:', storeCode, storeName);
        
        // Store value
        const storeValue = JSON.stringify([storeCode, storeName]);
        
        // Set form field value
        const destinationStoreInput = document.getElementById('destination_store');
        if (destinationStoreInput) {
            destinationStoreInput.value = storeValue;
        }
        
        // Save to local storage and cookie
        localStorage.setItem('postnet_selected_store', storeValue);
        document.cookie = 'postnet_selected_store=' + encodeURIComponent(storeValue) + '; path=/; max-age=86400';
        
        // Get full store details
        getStoreDetails(storeCode).then(storeDetails => {
            selectedStore = storeDetails;
            
            // Update selected store display
            updateSelectedStoreDetails(storeDetails);
            
            // Update radio button selections
            document.querySelectorAll('input[name="postnet-store"]').forEach(radio => {
                radio.checked = radio.value === storeCode;
            });
            
            // Update list item styling
            document.querySelectorAll('.postnet-store-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            if (listItem) {
                listItem.classList.add('selected');
            } else {
                const matchingItems = document.querySelectorAll(`.postnet-store-item[data-store-code="${storeCode}"]`);
                matchingItems.forEach(item => item.classList.add('selected'));
            }
            
            // Ensure hidden input for blocks checkout
            ensureHiddenInput(storeValue);
            
            // Update validation state
            updateValidationState();
        }).catch(error => {
            log('Error getting store details', error);
        });
    }
    
    // Update selected store details display
    function updateSelectedStoreDetails(storeDetails) {
        const detailsContainer = document.getElementById('postnet-selected-store-details');
        if (!detailsContainer) return;
        
        if (!storeDetails) {
            detailsContainer.classList.remove('active');
            return;
        }
        
        // Find current store index in markers array
        const currentIndex = markers.findIndex(marker => marker.code === storeDetails.code);
        
        // Create navigation header with arrows and counter - handle case where currentIndex is -1
        const actualIndex = currentIndex >= 0 ? currentIndex : 0;
        const totalMarkers = markers.length || 1;  // Prevent showing "0 of 0"
        
        let html = `
            <div class="postnet-store-details-container">
                <div class="postnet-store-nav">
                    <button type="button" class="postnet-nav-btn postnet-prev-store" ${actualIndex <= 0 ? 'disabled' : ''} aria-label="Previous store">
                        <svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"></path></svg>
                    </button>
                    <div class="postnet-location-counter">${actualIndex + 1} of ${totalMarkers}</div>
                    <button type="button" class="postnet-nav-btn postnet-next-store" ${actualIndex >= markers.length-1 ? 'disabled' : ''} aria-label="Next store">
                        <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"></path></svg>
                    </button>
                </div>
                
                <div class="postnet-store-content">
                    <div class="postnet-store-header">${storeDetails.name}</div>
                    
                    <div class="postnet-store-section">
                        <div class="postnet-section-title">Address</div>
                        <div class="postnet-store-address">
                            ${storeDetails.address}<br>
                            ${storeDetails.city}, ${storeDetails.province}<br>
                            ${storeDetails.postal_code}
                        </div>
                    </div>
                    
                    ${storeDetails.telephone || storeDetails.email ? `
                    <div class="postnet-store-section">
                        <div class="postnet-section-title">Contact</div>
                        <div class="postnet-store-contact">
                            ${storeDetails.telephone ? `<strong>Tel:</strong> ${storeDetails.telephone}<br>` : ''}
                            ${storeDetails.email ? `<strong>Email:</strong> ${storeDetails.email}` : ''}
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
        
        detailsContainer.innerHTML = html;
        detailsContainer.classList.add('active');
        
        // Add event listeners for navigation buttons - only if we have a valid index
        if (currentIndex >= 0) {
            const prevButton = detailsContainer.querySelector('.postnet-prev-store');
            const nextButton = detailsContainer.querySelector('.postnet-next-store');
            
            if (prevButton) {
                prevButton.addEventListener('click', () => {
                    if (currentIndex > 0) {
                        // Automatically select the previous store
                        const prevMarker = markers[currentIndex - 1];
                        if (prevMarker && prevMarker.code) {
                            selectStore(prevMarker.code, prevMarker.name, null, prevMarker);
                        }
                    }
                });
            }
            
            if (nextButton) {
                nextButton.addEventListener('click', () => {
                    if (currentIndex < markers.length - 1) {
                        // Automatically select the next store
                        const nextMarker = markers[currentIndex + 1];
                        if (nextMarker && nextMarker.code) {
                            selectStore(nextMarker.code, nextMarker.name, null, nextMarker);
                        }
                    }
                });
            }
        }
    }
    
    // Get store details via AJAX
    function getStoreDetails(storeCode) {
        // Check cache first
        if (storeDetails[storeCode]) {
            return Promise.resolve(storeDetails[storeCode]);
        }
        
        return new Promise((resolve, reject) => {
            fetch(wc_postnet_delivery_params.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'wc_postnet_delivery_store_details',
                    security: wc_postnet_delivery_params.nonce,
                    store_code: storeCode
                })
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success && data.data) {
                    // Cache the result
                    storeDetails[storeCode] = data.data;
                    resolve(data.data);
                } else {
                    throw new Error(data.data ? data.data.message : 'Failed to get store details');
                }
            })
            .catch(function(error) {
                log('Error fetching store details', error);
                reject(error);
            });
        });
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
    function updateValidationState() {
        const validationMsg = document.getElementById('postnet-store-validation');
        const selectedStore = getSelectedStore();
        
        if (validationMsg) {
            if (!selectedStore) {
                validationMsg.textContent = 'Please select a destination store';
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
