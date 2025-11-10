// Ensure main.js, auth.js, and cart.js are loaded before this script.

window.Checkout = {
    // Internal state for user addresses and cart data
    userAddresses: [],
    currentCartData: null, // Stores the full cart data including totals

    // DOM Elements (cached for performance)
    elements: {
        checkoutForm: null,
        cartItemsContainer: null,
        checkoutSubtotal: null,
        checkoutDiscount: null,
        checkoutTax: null,
        checkoutShipping: null,
        checkoutTotal: null,
        couponCodeInput: null,
        applyCouponBtn: null,
        couponMessageElement: null,
        useSavedShippingAddressCheckbox: null,
        savedShippingAddressesContainer: null,
        newShippingAddressForm: null,
        sameAsShippingCheckbox: null,
        billingAddressContainer: null,
        useSavedBillingAddressCheckbox: null,
        savedBillingAddressesContainer: null,
        newBillingAddressForm: null,
        placeOrderBtn: null,
        messageContainer: null, // Moved to top for immediate caching
    },

    /**
     * Initializes the checkout page by loading necessary data and setting up event listeners.
     */
    init: async function() {
        console.log('Initializing checkout page...');
        this.cacheElements(); // Cache DOM elements first
        
        // Check if messageContainer is successfully cached before trying to access it
        if (this.elements.messageContainer) {
            window.clearErrors('checkoutForm'); // Clear any initial form errors
            this.elements.messageContainer.innerHTML = ''; // Clear main message container
        } else {
            console.error('ERROR: messageContainer element not found. Cannot clear messages.');
            // Potentially show a toast or alert here if message container is critical
        }

        // Check if user is logged in, redirect to login if not
        const authStatus = await window.Auth.checkAuthStatus();
        if (!authStatus.logged_in) {
            window.showToast('Please log in to proceed to checkout.', 'info');
            setTimeout(() => {
                window.location.href = './login.html?return_to=' + encodeURIComponent(window.location.pathname);
            }, 1500);
            return;
        }

        // Load cart summary
        await this.loadCheckoutSummary();

        // Load user addresses
        await this.loadUserAddresses();

        // Set up event listeners
        this.setupEventListeners();

        // Initial toggle of address forms based on default states
        this.toggleAddressForms('shipping');
        this.toggleBillingAddressForm();
    },

    /**
     * Caches frequently used DOM elements.
     */
    cacheElements: function() {
        // Cache messageContainer first as it's used early in init
        this.elements.messageContainer = window.select('#message-container');

        this.elements.checkoutForm = window.select('#checkoutForm');
        this.elements.cartItemsContainer = window.select('#checkout-cart-items');
        this.elements.checkoutSubtotal = window.select('#checkout-subtotal');
        this.elements.checkoutDiscount = window.select('#checkout-discount');
        this.elements.checkoutTax = window.select('#checkout-tax');
        this.elements.checkoutShipping = window.select('#checkout-shipping');
        this.elements.checkoutTotal = window.select('#checkout-total');
        this.elements.couponCodeInput = window.select('#coupon-code-input');
        this.elements.applyCouponBtn = window.select('#apply-coupon-btn');
        this.elements.couponMessageElement = window.select('#coupon-message');
        // this.elements.removeCouponBtn is now managed by cart.js, so we don't select it here.

        this.elements.useSavedShippingAddressCheckbox = window.select('#use-saved-shipping-address');
        this.elements.savedShippingAddressesContainer = window.select('#saved-shipping-addresses-container');
        this.elements.newShippingAddressForm = window.select('#new-shipping-address-form');
        this.elements.sameAsShippingCheckbox = window.select('#same-as-shipping');
        this.elements.billingAddressContainer = window.select('#billing-address-container');
        this.elements.useSavedBillingAddressCheckbox = window.select('#use-saved-billing-address');
        this.elements.savedBillingAddressesContainer = window.select('#saved-billing-addresses-container');
        this.elements.newBillingAddressForm = window.select('#new-billing-address-form');
        this.elements.placeOrderBtn = window.select('#place-order-btn');
    },

    /**
     * Sets up all necessary event listeners for the checkout page.
     */
    setupEventListeners: function() {
        if (this.elements.checkoutForm) {
            this.elements.checkoutForm.addEventListener('submit', this.handleSubmitOrder.bind(this));
        }

        if (this.elements.useSavedShippingAddressCheckbox) {
            this.elements.useSavedShippingAddressCheckbox.addEventListener('change', this.toggleAddressForms.bind(this, 'shipping'));
        }

        if (this.elements.sameAsShippingCheckbox) {
            this.elements.sameAsShippingCheckbox.addEventListener('change', this.toggleBillingAddressForm.bind(this));
        }

        if (this.elements.useSavedBillingAddressCheckbox) {
            this.elements.useSavedBillingAddressCheckbox.addEventListener('change', this.toggleAddressForms.bind(this, 'billing'));
        }

        if (this.elements.applyCouponBtn) {
            this.elements.applyCouponBtn.addEventListener('click', this.handleCouponApplication.bind(this));
        }

        // IMPORTANT: The removeCouponBtn listener is now managed by cart.js.
        // We need to ensure that if cart.js creates this button, its event listener is attached by cart.js.
        // If the remove coupon button exists on the page (e.g., if cart.js has already rendered it),
        // we can try to attach a listener to it here, but ideally cart.js handles its own button's events.
        // For now, we'll assume cart.js manages its own dynamically created remove button's events.
        // If a static remove-coupon-btn exists in checkout.html, we can add a listener here:
        const staticRemoveCouponBtn = window.select('#remove-coupon-btn');
        if (staticRemoveCouponBtn) {
            staticRemoveCouponBtn.addEventListener('click', this.handleCouponRemoval.bind(this));
        }
    },

    /**
     * Loads the current cart contents and displays them in the order summary.
     */
    loadCheckoutSummary: async function() {
        if (!this.elements.cartItemsContainer) return;

        this.elements.cartItemsContainer.innerHTML = '<tr><td colspan="4" class="text-center text-medium-gray py-4">Loading cart summary...</td></tr>';
        this.elements.placeOrderBtn.disabled = true; // Disable until cart is loaded

        try {
            const response = await fetch(`${window.API_BASE_URL}/cart`);
            const result = await window.handleApiResponse(response);

            if (result.status === 'success' && result.data) {
                this.currentCartData = result.data; // Store full cart data
                const cartItems = result.data.items || [];
                const totals = result.data.totals; // Access totals object within data

                this.elements.cartItemsContainer.innerHTML = ''; // Clear loading message

                if (cartItems.length === 0) {
                    this.elements.cartItemsContainer.innerHTML = '<tr><td colspan="4" class="text-center text-medium-gray py-4">Your cart is empty. Please add products before checking out.</td></tr>';
                    window.showToast('Your cart is empty. Please add products to proceed.', 'info');
                    return; // Exit if cart is empty
                }

                cartItems.forEach(item => {
                    const BASE_ASSET_URL = 'http://localhost:8080/Lugxwebsite/public';
                    
                    const productImageUrl = item.featured_image 
                        ? `${BASE_ASSET_URL}/${item.featured_image}` 
                        : `${BASE_ASSET_URL}/assets/img/placeholder.jpg`;

                    const row = `
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="${productImageUrl}" onerror="this.onerror=null;this.src='${BASE_ASSET_URL}/assets/img/placeholder.jpg';" alt="${item.title}" class="rounded-3 me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                    <div>
                                        <h6 class="mb-0 text-dark-gray">${item.title}</h6>
                                        <small class="text-medium-gray">Category: ${item.category_name || 'N/A'}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="text-dark-gray fw-bold">$${parseFloat(item.cart_item_price).toFixed(2)}</td>
                            <td class="text-dark-gray">${item.quantity}</td>
                            <td class="text-dark-gray fw-bold">$${parseFloat(item.subtotal).toFixed(2)}</td>
                        </tr>
                    `;
                    this.elements.cartItemsContainer.insertAdjacentHTML('beforeend', row);
                });

                // Update totals
                this.elements.checkoutSubtotal.textContent = `$${parseFloat(totals.subtotal).toFixed(2)}`;
                this.elements.checkoutDiscount.textContent = `-$${parseFloat(totals.discount).toFixed(2)}`;
                this.elements.checkoutTax.textContent = `$${parseFloat(totals.tax_amount || 0).toFixed(2)}`;
                this.elements.checkoutShipping.textContent = `$${parseFloat(totals.shipping_amount || 0).toFixed(2)}`;
                this.elements.checkoutTotal.textContent = `$${parseFloat(totals.total).toFixed(2)}`;

                // Update coupon section UI (now handled by cart.js)
                // We still call cart.js's updateCouponSectionUI to ensure input/apply button state is correct
                if (window.Cart && typeof window.Cart.updateCouponSectionUI === 'function') {
                    window.Cart.updateCouponSectionUI(totals);
                }


                this.elements.placeOrderBtn.disabled = false; // Enable place order button after successful load

            } else {
                this.elements.cartItemsContainer.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-4">Error loading cart: ${result.message || 'Unknown error'}</td></tr>`;
                window.showToast(`Error loading cart: ${result.message || 'Unknown error'}`, 'danger');
            }
        } catch (error) {
            console.error('Error loading checkout summary:', error);
            this.elements.cartItemsContainer.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Network error loading cart. Please try again.</td></tr>';
            window.showToast('Network error loading cart. Please try again.', 'danger');
        }
    },

    /**
     * Updates the UI of the coupon section based on current cart totals.
     * This function now only calls cart.js's updateCouponSectionUI.
     * @param {object} totals - The totals object from the cart API response.
     */
    updateCouponSectionUI: function(totals) {
        // This function now simply delegates to Cart.js's updateCouponSectionUI
        // as per the new agreed-upon responsibility.
        if (window.Cart && typeof window.Cart.updateCouponSectionUI === 'function') {
            window.Cart.updateCouponSectionUI(totals);
        } else {
            console.warn('Cart.updateCouponSectionUI is not available. Coupon UI may not update correctly.');
        }
    },

    /**
     * Handles coupon code application.
     * This function will now call Cart.applyCouponToCart and rely on Cart.js to update the UI.
     */
    handleCouponApplication: async function() {
        const couponInput = this.elements.couponCodeInput;
        const couponCode = couponInput ? couponInput.value.trim() : '';
        const applyBtn = this.elements.applyCouponBtn;
        const messageElement = this.elements.couponMessageElement; // Reference to message element

        if (!couponCode) {
            if (messageElement) {
                messageElement.textContent = 'Please enter a coupon code.';
                messageElement.classList.remove('text-success');
                messageElement.classList.add('text-danger');
            }
            return;
        }

        applyBtn.disabled = true; // Disable button during API call
        applyBtn.textContent = 'Applying...';
        if (messageElement) {
            messageElement.textContent = 'Applying coupon...';
            messageElement.className = 'text-center small mt-2 text-info';
        }

        try {
            if (window.Cart && typeof window.Cart.applyCouponToCart === 'function') {
                const result = await window.Cart.applyCouponToCart(couponCode); // Cart.applyCouponToCart handles its own UI updates and toasts

                // After Cart.applyCouponToCart, reload checkout summary to ensure totals are updated
                await this.loadCheckoutSummary();

            } else {
                window.showToast('Cart coupon functionality not loaded.', 'error');
                console.error('Cart.applyCouponToCart function not available.');
            }
        } catch (error) {
            console.error('Error applying coupon:', error);
            window.showToast('An unexpected error occurred while applying coupon.', 'danger');
        } finally {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply Coupon';
        }
    },

    /**
     * Handles coupon code removal.
     * This function will now call Cart.removeCouponFromCart and rely on Cart.js to update the UI.
     */
    handleCouponRemoval: async function() {
        // This function is now only responsible for triggering the removal in Cart.js
        // The button itself and its state are managed by Cart.js's updateCouponSectionUI
        const applyBtn = this.elements.applyCouponBtn; // Assuming applyBtn is still visible and can be used for reference
        const messageElement = this.elements.couponMessageElement;

        // Temporarily disable apply button and show message while removing
        if (applyBtn) {
            applyBtn.disabled = true;
            applyBtn.textContent = 'Removing...';
        }
        if (messageElement) {
            messageElement.textContent = 'Removing coupon...';
            messageElement.className = 'text-center small mt-2 text-info';
        }

        try {
            if (window.Cart && typeof window.Cart.removeCouponFromCart === 'function') {
                const result = await window.Cart.removeCouponFromCart(); // Cart.removeCouponFromCart handles its own UI updates and toasts

                // After Cart.removeCouponFromCart, reload checkout summary to ensure totals are updated
                await this.loadCheckoutSummary();

            } else {
                window.showToast('Cart coupon functionality not loaded.', 'error');
                console.error('Cart.removeCouponFromCart function not available.');
            }
        } catch (error) {
            console.error('Error removing coupon:', error);
            window.showToast('An unexpected error occurred while removing coupon.', 'danger');
        } finally {
            // Re-enable apply button and reset text regardless of success/failure
            if (applyBtn) {
                applyBtn.disabled = false;
                applyBtn.textContent = 'Apply Coupon';
            }
        }
    },

    /**
     * Loads user's saved addresses and populates the address selection sections.
     */
    loadUserAddresses: async function() {
        const shippingContainer = this.elements.savedShippingAddressesContainer;
        const billingContainer = this.elements.savedBillingAddressesContainer;

        if (!shippingContainer || !billingContainer) return;

        shippingContainer.innerHTML = '<p class="text-medium-gray">Loading saved addresses...</p>';
        billingContainer.innerHTML = '<p class="text-medium-gray">Loading saved addresses...</p>';

        try {
            const response = await fetch(`${window.API_BASE_URL}/user/addresses`);
            const result = await window.handleApiResponse(response);

            if (result.status === 'success' && result.addresses) {
                this.userAddresses = result.addresses; // Store addresses

                this.renderAddresses(shippingContainer, 'shipping', this.userAddresses);
                this.renderAddresses(billingContainer, 'billing', this.userAddresses);

                // Initial address form toggling based on loaded addresses
                this.handleInitialAddressFormState();

            } else {
                shippingContainer.innerHTML = '<p class="text-danger">Error loading addresses.</p>';
                billingContainer.innerHTML = '<p class="text-danger">Error loading addresses.</p>';
                window.showToast('Error loading saved addresses.', 'danger');
            }
        } catch (error) {
            console.error('Error loading user addresses:', error);
            shippingContainer.innerHTML = '<p class="text-danger">Network error loading addresses.</p>';
            billingContainer.innerHTML = '<p class="text-danger">Network error loading addresses.</p>';
            window.showToast('Network error loading saved addresses. Please try again.', 'danger');
        }
    },

    /**
     * Handles the initial state of address forms after addresses are loaded.
     */
    handleInitialAddressFormState: function() {
        console.log('DEBUG: handleInitialAddressFormState called.'); // Debug log
        // Find default address
        const defaultAddress = this.userAddresses.find(addr => addr.is_default);

        // Shipping Address Logic
        if (this.userAddresses.length > 0) {
            console.log('DEBUG: User has saved addresses. Setting useSavedShippingAddressCheckbox to true.'); // Debug log
            // If there are saved addresses, default to using them
            this.elements.useSavedShippingAddressCheckbox.checked = true;
            if (defaultAddress) {
                console.log('DEBUG: Default address found. Populating shipping form with default address.'); // Debug log
                // Select the radio button for the default address
                const defaultShippingRadio = window.select(`#shipping_address_${defaultAddress.id}`);
                if (defaultShippingRadio) {
                    defaultShippingRadio.checked = true;
                }
                this.populateAddressForm('new-shipping-address-form', defaultAddress);
            } else {
                console.log('DEBUG: No default address found. Selecting first saved address for shipping.'); // Debug log
                // If no default, select the first saved address
                const firstAddressRadio = window.select(`input[name="selected_shipping_address"]`);
                if (firstAddressRadio) {
                    firstAddressRadio.checked = true;
                    this.populateAddressForm('new-shipping-address-form', this.userAddresses[0]);
                }
            }
            this.elements.savedShippingAddressesContainer.classList.remove('d-none');
            this.elements.newShippingAddressForm.classList.add('d-none');
        } else {
            console.log('DEBUG: No saved addresses. Forcing new shipping address form.'); // Debug log
            // No saved addresses, force new address form
            this.elements.useSavedShippingAddressCheckbox.checked = false;
            this.elements.savedShippingAddressesContainer.classList.add('d-none');
            this.elements.newShippingAddressForm.classList.remove('d-none');
        }
        this.setAddressFormRequired(this.elements.newShippingAddressForm.id, !this.elements.useSavedShippingAddressCheckbox.checked);

        // Billing Address Logic (depends on "Same as shipping" checkbox)
        if (this.elements.sameAsShippingCheckbox.checked) {
            console.log('DEBUG: "Same as shipping" checkbox is checked. Hiding billing address container.'); // Debug log
            this.elements.billingAddressContainer.classList.add('d-none');
            this.setAddressFormRequired(this.elements.newBillingAddressForm.id, false);
            // Also ensure saved billing address radios are not required if hidden
            this.elements.savedBillingAddressesContainer.querySelectorAll('input').forEach(input => input.removeAttribute('required'));
        } else {
            console.log('DEBUG: "Same as shipping" checkbox is NOT checked. Showing billing address container.'); // Debug log
            this.elements.billingAddressContainer.classList.remove('d-none');
            // If not same as shipping, apply similar logic for billing addresses
            if (this.userAddresses.length > 0) {
                console.log('DEBUG: User has saved addresses for billing. Setting useSavedBillingAddressCheckbox to true.'); // Debug log
                this.elements.useSavedBillingAddressCheckbox.checked = true;
                if (defaultAddress) {
                    const defaultBillingRadio = window.select(`#billing_address_${defaultAddress.id}`);
                    if (defaultBillingRadio) {
                        defaultBillingRadio.checked = true;
                    }
                    this.populateAddressForm('new-billing-address-form', defaultAddress);
                } else {
                    const firstAddressRadio = window.select(`input[name="selected_billing_address"]`);
                    if (firstAddressRadio) {
                        firstAddressRadio.checked = true;
                        this.populateAddressForm('new-billing-address-form', this.userAddresses[0]);
                    }
                }
                this.elements.savedBillingAddressesContainer.classList.remove('d-none');
                this.elements.newBillingAddressForm.classList.add('d-none');
            } else {
                console.log('DEBUG: No saved addresses for billing. Forcing new billing address form.'); // Debug log
                this.elements.useSavedBillingAddressCheckbox.checked = false;
                this.elements.savedBillingAddressesContainer.classList.add('d-none');
                this.elements.newBillingAddressForm.classList.remove('d-none');
            }
            this.setAddressFormRequired(this.elements.newBillingAddressForm.id, !this.elements.useSavedBillingAddressCheckbox.checked);
        }
    },

    /**
     * Renders saved addresses as radio buttons in the specified container.
     * @param {HTMLElement} container - The DOM element to render addresses into.
     * @param {string} type - 'shipping' or 'billing'.
     * @param {Array} addresses - Array of user address objects.
     */
    renderAddresses: function(container, type, addresses) {
        console.log(`DEBUG: renderAddresses called for type: ${type} with ${addresses.length} addresses.`); // Debug log
        container.innerHTML = ''; // Clear existing content
        if (addresses.length === 0) {
            container.innerHTML = `<p class="text-medium-gray">No saved addresses. Please enter a new ${type} address.</p>`;
            // Ensure "Use a saved address" checkbox is unchecked and hidden if no addresses
            const useSavedCheckbox = window.select(`#use-saved-${type}-address`);
            if (useSavedCheckbox) {
                useSavedCheckbox.checked = false;
                // Optionally hide the checkbox if no saved addresses are available
                useSavedCheckbox.closest('.form-check').classList.add('d-none');
            }
            return;
        }

        // Ensure "Use a saved address" checkbox is visible if addresses exist
        const useSavedCheckbox = window.select(`#use-saved-${type}-address`);
        if (useSavedCheckbox) {
            useSavedCheckbox.closest('.form-check').classList.remove('d-none');
        }

        addresses.forEach(address => {
            const addressHtml = `
                <div class="form-check border rounded-3 p-3 mb-2 shadow-sm">
                    <input class="form-check-input" type="radio" name="selected_${type}_address" id="${type}_address_${address.id}" value="${address.id}" ${address.is_default ? 'checked' : ''}>
                    <label class="form-check-label w-100" for="${type}_address_${address.id}">
                        <strong class="text-dark-gray">${address.title}</strong><br>
                        <span class="text-medium-gray">${address.address}, ${address.city}, ${address.state}, ${address.postal_code}, ${address.country}</span>
                        ${address.phone ? `<br><span class="text-medium-gray">Phone: ${address.phone}</span>` : ''}
                        ${address.is_default ? '<span class="badge bg-primary ms-2">Default</span>' : ''}
                    </label>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', addressHtml);
        });

        // Add event listeners for newly rendered radio buttons
        container.querySelectorAll(`input[name="selected_${type}_address"]`).forEach(radio => {
            radio.addEventListener('change', (event) => {
                const addressId = parseInt(event.target.value);
                const selectedAddress = this.userAddresses.find(addr => addr.id === addressId);
                console.log(`DEBUG: Radio button changed for ${type} address. Selected ID: ${addressId}, Address Data:`, selectedAddress); // Debug log
                if (selectedAddress) {
                    this.populateAddressForm(`new-${type}-address-form`, selectedAddress);
                }
            });
        });
    },

    /**
     * Toggles visibility of saved addresses vs. new address form.
     * @param {string} type - 'shipping' or 'billing'.
     */
    toggleAddressForms: function(type) {
        console.log(`DEBUG: toggleAddressForms called for type: ${type}.`); // Debug log
        const useSavedCheckbox = this.elements[`useSaved${type.charAt(0).toUpperCase() + type.slice(1)}AddressCheckbox`];
        const savedContainer = this.elements[`saved${type.charAt(0).toUpperCase() + type.slice(1)}AddressesContainer`];
        const newForm = this.elements[`new${type.charAt(0).toUpperCase() + type.slice(1)}AddressForm`];

        if (!useSavedCheckbox || !savedContainer || !newForm) {
            console.warn(`Elements for ${type} address forms not found.`);
            return;
        }

        if (useSavedCheckbox.checked && this.userAddresses.length > 0) {
            console.log(`DEBUG: ${type} - Using saved addresses.`); // Debug log
            savedContainer.classList.remove('d-none');
            newForm.classList.add('d-none');
            // Populate new form with selected saved address if one is checked
            const selectedAddressRadio = window.select(`input[name="selected_${type}_address"]:checked`);
            const selectedAddressId = selectedAddressRadio?.value;
            console.log(`DEBUG: ${type} - Initially selected radio ID:`, selectedAddressId); // Debug log

            if (selectedAddressId) {
                const selectedAddress = this.userAddresses.find(addr => addr.id === parseInt(selectedAddressId));
                if (selectedAddress) {
                    console.log(`DEBUG: ${type} - Populating form with selected saved address:`, selectedAddress); // Debug log
                    this.populateAddressForm(newForm.id, selectedAddress);
                }
            } else {
                // If no radio is checked but saved addresses exist, select the first one
                const firstAddressRadio = window.select(`input[name="selected_${type}_address"]`);
                if (firstAddressRadio) {
                    firstAddressRadio.checked = true;
                    console.log(`DEBUG: ${type} - No radio checked, selecting first saved address:`, this.userAddresses[0]); // Debug log
                    this.populateAddressForm(newForm.id, this.userAddresses[0]);
                }
            }
        } else {
            console.log(`DEBUG: ${type} - Using new address form.`); // Debug log
            savedContainer.classList.add('d-none');
            newForm.classList.remove('d-none');
            this.clearAddressForm(newForm.id); // Clear new form when switching to it
        }

        // Ensure required attributes are set/unset for validation
        this.setAddressFormRequired(newForm.id, !useSavedCheckbox.checked);
    },

    /**
     * Toggles the billing address form based on the "same as shipping" checkbox.
     */
    toggleBillingAddressForm: function() {
        console.log('DEBUG: toggleBillingAddressForm called.'); // Debug log
        const sameAsShippingCheckbox = this.elements.sameAsShippingCheckbox;
        const billingAddressContainer = this.elements.billingAddressContainer;
        const useSavedBillingCheckbox = this.elements.useSavedBillingAddressCheckbox;
        const newBillingForm = this.elements.newBillingAddressForm;

        if (!sameAsShippingCheckbox || !billingAddressContainer || !useSavedBillingCheckbox || !newBillingForm) {
            console.warn('One or more billing address elements not found.');
            return;
        }

        if (sameAsShippingCheckbox.checked) {
            console.log('DEBUG: Billing - Same as shipping checked. Hiding billing form.'); // Debug log
            billingAddressContainer.classList.add('d-none');
            // Disable all billing address inputs and make them not required
            this.setAddressFormRequired(newBillingForm.id, false);
            // Also ensure saved billing address radios are not required if hidden
            this.elements.savedBillingAddressesContainer.querySelectorAll('input').forEach(input => input.removeAttribute('required'));
        } else {
            console.log('DEBUG: Billing - Same as shipping UNCHECKED. Showing billing form.'); // Debug log
            billingAddressContainer.classList.remove('d-none');
            // Re-enable/set required based on billing's own "use saved" checkbox
            // This will also handle populating the form if a saved address is selected
            this.toggleAddressForms('billing');
        }
    },

    /**
     * Populates an address form with data from a given address object.
     * @param {string} formId - The ID of the form container (e.g., 'new-shipping-address-form').
     * @param {object} addressData - The address object to populate from.
     */
    populateAddressForm: function(formId, addressData) {
        console.log(`DEBUG: populateAddressForm called for ${formId} with data:`, addressData); // Debug log
        const prefix = formId.includes('shipping') ? 'shipping_' : 'billing_';
        
        // Ensure elements exist before trying to set value
        const firstName = window.select(`#${prefix}first_name`);
        if (firstName) firstName.value = addressData.first_name || '';
        const lastName = window.select(`#${prefix}last_name`);
        if (lastName) lastName.value = addressData.last_name || '';
        const addressLine1 = window.select(`#${prefix}address_line1`);
        if (addressLine1) addressLine1.value = addressData.address || ''; // 'address' in DB maps to 'address_line1' in form
        const addressLine2 = window.select(`#${prefix}address_line2`);
        if (addressLine2) addressLine2.value = addressData.address2 || ''; // Assuming address2 for line2
        const city = window.select(`#${prefix}city`);
        if (city) city.value = addressData.city || '';
        const state = window.select(`#${prefix}state`);
        if (state) state.value = addressData.state || '';
        const zipCode = window.select(`#${prefix}zip_code`);
        if (zipCode) zipCode.value = addressData.postal_code || ''; // 'postal_code' in DB maps to 'zip_code' in form
        const country = window.select(`#${prefix}country`);
        if (country) country.value = addressData.country || '';
        const phone = window.select(`#${prefix}phone`);
        if (phone) phone.value = addressData.phone || '';

        console.log(`DEBUG: After populating ${formId}, shipping_first_name value:`, window.select(`#shipping_first_name`)?.value); // Specific debug for a problematic field
    },

    /**
     * Clears all input fields in a given address form.
     * @param {string} formId - The ID of the form container.
     */
    clearAddressForm: function(formId) {
        console.log(`DEBUG: clearAddressForm called for ${formId}.`); // Debug log
        const formElement = window.select(`#${formId}`);
        if (formElement) {
            formElement.querySelectorAll('input[type="text"], input[type="email"], textarea').forEach(input => {
                input.value = '';
                input.classList.remove('is-invalid'); // Clear validation styles
                const errorElement = window.select(`#${input.id}-error`);
                if (errorElement) errorElement.textContent = '';
            });
        }
    },

    /**
     * Sets or unsets the 'required' attribute for input fields within an address form.
     * @param {string} formId - The ID of the form container (e.g., 'new-shipping-address-form').
     * @param {boolean} isRequired - True to set required, false to unset.
     */
    setAddressFormRequired: function(formId, isRequired) {
        console.log(`DEBUG: setAddressFormRequired called for ${formId}, isRequired: ${isRequired}.`); // Debug log
        const formElement = window.select(`#${formId}`);
        if (formElement) {
            formElement.querySelectorAll('input[name], select[name], textarea[name]').forEach(input => {
                // Only set required if the input is visible and not part of a hidden section
                const isHidden = input.closest('.d-none'); // Check if parent is hidden
                if (isRequired && !isHidden) {
                    input.setAttribute('required', 'required');
                } else {
                    input.removeAttribute('required');
                }
            });
        }
    },

    /**
     * Collects all necessary address data based on user selection.
     * @param {string} type - 'shipping' or 'billing'.
     * @returns {object|null} The collected address data, or null if validation fails.
     */
    collectAddressData: function(type) {
        console.log(`DEBUG: collectAddressData called for type: ${type}.`); // Debug log
        const useSavedCheckbox = this.elements[`useSaved${type.charAt(0).toUpperCase() + type.slice(1)}AddressCheckbox`];
        const sameAsShippingCheckbox = this.elements.sameAsShippingCheckbox;
        let addressData = {};
        const errors = {};
        let isValid = true;

        // If billing and same as shipping, return shipping address data
        if (type === 'billing' && sameAsShippingCheckbox && sameAsShippingCheckbox.checked) {
            console.log('DEBUG: Billing address is same as shipping. Collecting shipping data recursively.'); // Debug log
            // Recursively get shipping address, but prevent infinite loop if shipping also depends on billing (unlikely here)
            const shippingData = this.collectAddressData('shipping');
            if (shippingData === null) {
                // If shipping data is invalid, then billing data is also invalid
                console.log('DEBUG: Shipping data invalid, returning null for billing.'); // Debug log
                return null;
            }
            return shippingData;
        }

        if (useSavedCheckbox && useSavedCheckbox.checked) {
            console.log(`DEBUG: ${type} - Using saved address logic.`); // Debug log
            const selectedAddressRadio = window.select(`input[name="selected_${type}_address"]:checked`);
            if (!selectedAddressRadio) {
                errors[`${type}_address_selection`] = `Please select a saved ${type} address.`;
                isValid = false;
                console.log(`DEBUG: ${type} - No saved address radio selected.`); // Debug log
            } else {
                const selectedAddressId = parseInt(selectedAddressRadio.value);
                const foundAddress = this.userAddresses.find(addr => addr.id === selectedAddressId);
                if (!foundAddress) {
                    errors[`${type}_address_selection`] = `Selected ${type} address not found.`;
                    isValid = false;
                    console.log(`DEBUG: ${type} - Selected saved address not found in userAddresses.`); // Debug log
                } else {
                    // Map UserAddress model fields to the expected format for backend
                    addressData = {
                        first_name: foundAddress.first_name || '',
                        last_name: foundAddress.last_name || '',
                        address: foundAddress.address || '', // 'address' in DB maps to 'address' for backend
                        address2: foundAddress.address2 || '', // Assuming address2 for line2
                        city: foundAddress.city || '',
                        state: foundAddress.state || '',
                        postal_code: foundAddress.postal_code || '', // 'postal_code' in DB maps to 'postal_code' for backend
                        country: foundAddress.country || '',
                        phone: foundAddress.phone || ''
                    };
                    console.log(`DEBUG: ${type} - Collected from saved address:`, addressData); // Debug log
                }
            }
        } else {
            console.log(`DEBUG: ${type} - Using new address form logic.`); // Debug log
            // Collect from new address form
            const prefix = type + '_';
            addressData = {
                first_name: window.select(`#${prefix}first_name`)?.value || '',
                last_name: window.select(`#${prefix}last_name`)?.value || '',
                address: window.select(`#${prefix}address_line1`)?.value || '', // 'address_line1' from form maps to 'address' for backend
                address2: window.select(`#${prefix}address_line2`)?.value || '',
                city: window.select(`#${prefix}city`)?.value || '',
                state: window.select(`#${prefix}state`)?.value || '',
                postal_code: window.select(`#${prefix}zip_code`)?.value || '', // 'zip_code' from form maps to 'postal_code' for backend
                country: window.select(`#${prefix}country`)?.value || '',
                phone: window.select(`#${prefix}phone`)?.value || ''
            };

            console.log(`DEBUG: ${type} - Collected from new form (raw):`, addressData); // Debug log

            // Client-side validation for new address fields (only if form is visible and not using saved)
            const newFormElement = this.elements[`new${type.charAt(0).toUpperCase() + type.slice(1)}AddressForm`];
            if (!newFormElement.classList.contains('d-none')) { // Only validate if the form is visible
                const requiredFields = [
                    'first_name', 'last_name', 'address', 'city', 'state', 'postal_code', 'country'
                ];

                requiredFields.forEach(field => {
                    // Special mapping for form fields vs backend expected keys
                    let formFieldId = `${prefix}${field}`;
                    if (field === 'address') formFieldId = `${prefix}address_line1`;
                    if (field === 'postal_code') formFieldId = `${prefix}zip_code`;

                    const inputElement = window.select(`#${formFieldId}`);
                    if (inputElement && !inputElement.value.trim()) {
                        errors[`${formFieldId}`] = `${field.replace(/_/g, ' ')} is required.`;
                        isValid = false;
                        console.log(`DEBUG: ${type} - Validation failed for ${formFieldId}: empty.`); // Debug log
                    }
                });
            }
        }

        if (!isValid) {
            window.displayValidationErrors(errors, 'checkoutForm');
            console.log(`DEBUG: ${type} - Validation errors found:`, errors); // Debug log
            return null;
        }
        console.log(`DEBUG: ${type} - Successfully collected address data:`, addressData); // Debug log
        return addressData;
    },

    /**
     * Handles the submission of the checkout form to create an order.
     * @param {Event} event - The form submission event.
     */
    handleSubmitOrder: async function(event) {
        event.preventDefault();
        window.clearErrors('checkoutForm'); // Clear previous errors

        this.elements.placeOrderBtn.disabled = true;
        this.elements.placeOrderBtn.textContent = 'Placing Order...';

        const shippingAddress = this.collectAddressData('shipping');
        if (!shippingAddress) {
            this.elements.placeOrderBtn.disabled = false;
            this.elements.placeOrderBtn.textContent = 'Place Order';
            window.showToast('Please correct shipping address errors.', 'danger');
            return;
        }

        const billingAddress = this.collectAddressData('billing');
        if (!billingAddress) {
            this.elements.placeOrderBtn.disabled = false;
            this.elements.placeOrderBtn.textContent = 'Place Order';
            window.showToast('Please correct billing address errors.', 'danger');
            return;
        }

        const paymentMethod = window.select('input[name="payment_method"]:checked')?.value;
        if (!paymentMethod) {
            window.displayValidationErrors({'payment_method': 'Please select a payment method.'}, 'checkoutForm');
            this.elements.placeOrderBtn.disabled = false;
            this.elements.placeOrderBtn.textContent = 'Place Order';
            window.showToast('Please select a payment method.', 'danger');
            return;
        }

        const orderData = {
            payment_method: paymentMethod,
            coupon_code: this.elements.couponCodeInput?.value.trim() || null, // Get coupon code from the input field
            billing_address: billingAddress,
            shipping_address: shippingAddress,
            notes: window.select('#order_notes')?.value.trim() || null
        };
        
        // If a coupon was applied and is in currentCartData, ensure we send that one
        if (this.currentCartData && this.currentCartData.totals && this.currentCartData.totals.applied_coupon) {
            orderData.coupon_code = this.currentCartData.totals.applied_coupon.code;
        }
        
        // Ensure currentCartData is available and has items
        if (!this.currentCartData || !this.currentCartData.items || this.currentCartData.items.length === 0) {
            window.showToast('Your cart is empty. Cannot place order.', 'danger');
            this.elements.placeOrderBtn.disabled = false;
            this.elements.placeOrderBtn.textContent = 'Place Order';
            return;
        }

        try {
            const requestOptions = await window.preparePostRequest(orderData);
            if (!requestOptions) {
                throw new Error('Failed to prepare POST request (CSRF token missing).');
            }

            console.log('DEBUG: Sending order data to API:', orderData); // Debug log before sending
            const response = await fetch(`${window.API_BASE_URL}/orders`, requestOptions);
            const result = await window.handleApiResponse(response);

            if (result.status === 'success') {
                window.showToast(result.message || 'Order placed successfully!', 'success');
                // Redirect to a confirmation page or user orders page
                setTimeout(() => {
                    window.location.href = './user_orders.html';
                }, 1500);
            } else {
                window.showToast(result.message || 'Failed to place order. Please try again.', 'danger');
                if (result.errors) {
                    window.displayValidationErrors(result.errors, 'checkoutForm');
                } else {
                    window.displayMessage(result.message || 'Failed to place order.', 'danger', 'message-container');
                }
            }
        } catch (error) {
            console.error('Error placing order:', error);
            window.showToast('An unexpected error occurred. Please try again later.', 'danger');
        } finally {
            this.elements.placeOrderBtn.disabled = false;
            this.elements.placeOrderBtn.textContent = 'Place Order';
        }
    }
};

// Initialize checkout when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Ensure all required main.js functions are available
    if (typeof window.select === 'function' && typeof window.selectAll === 'function' &&
        typeof window.showToast === 'function' && typeof window.displayMessage === 'function' &&
        typeof window.clearErrors === 'function' && typeof window.displayValidationErrors === 'function' &&
        typeof window.handleApiResponse === 'function' && typeof window.preparePostRequest === 'function' &&
        typeof window.Auth !== 'undefined' && typeof window.Cart !== 'undefined') {
        window.Checkout.init();
    } else {
        console.error('Required global functions or objects (main.js, auth.js, cart.js) are not available. Ensure scripts are loaded in correct order.');
    }
});