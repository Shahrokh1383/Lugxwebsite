// Ensure main.js is loaded before this script.
// DOM Elements (cached for performance and consistency)
window.Cart = {
    elements: {
        cartItemsContainer: null,
        cartSubtotalSpan: null,
        cartDiscountSpan: null,
        cartTotalSpan: null,
        cartItemCountSpan: null, // The span in the header that shows item count
        couponCodeInput: null,
        applyCouponBtn: null,
        removeCouponBtn: null, // New button for removing coupon (will be null if not on page)
        checkoutBtn: null,
        updateCartBtn: null, // This button might not be explicitly used if updates are instant
        couponMessageElement: null, // Element to display coupon messages (will be null if not on page)
    },
    /**
     * Initializes cart-related DOM elements and event listeners.
     */
    init: function() {
        console.log('Initializing cart...');
        this.cacheElements(); // Cache DOM elements
        // Add event listeners for coupon and checkout, ONLY if elements exist
        if (this.elements.applyCouponBtn) {
            this.elements.applyCouponBtn.addEventListener('click', this.handleApplyCouponClick.bind(this));
        }
        if (this.elements.checkoutBtn) {
            this.elements.checkoutBtn.addEventListener('click', this.handleCheckout.bind(this));
        }
        // The remove coupon button will be dynamically added/removed, so its listener is set in updateCouponSectionUI
        // No need to add it here, as it might not exist initially.
        // Load initial cart summary (this will also render items if on cart.html)
        this.loadCartSummary();
    },
    /**
     * Caches frequently used DOM elements.
     * It's crucial here to get references, which might be null if elements don't exist.
     */
    cacheElements: function() {
        this.elements.cartItemsContainer = window.select('#cart-table-body');
        this.elements.cartSubtotalSpan = window.select('#cart-subtotal');
        this.elements.cartDiscountSpan = window.select('#cart-discount');
        this.elements.cartTotalSpan = window.select('#cart-total');
        this.elements.cartItemCountSpan = window.select('#cart-item-count'); // The span in the header
        
        // These elements might not exist on all pages (e.g., shop.html)
        // Check for both cart.html and checkout.html coupon input IDs
        this.elements.couponCodeInput = window.select('#coupon-input') || window.select('#coupon-code-input');
        this.elements.applyCouponBtn = window.select('#apply-coupon-btn');
        this.elements.removeCouponBtn = window.select('#remove-coupon-btn'); // Cache if it exists statically
        this.elements.checkoutBtn = window.select('#checkout-btn');
        this.elements.updateCartBtn = window.select('#update-cart-btn');
        this.elements.couponMessageElement = window.select('#coupon-message');
    },
    /**
     * Renders the cart items in the table with the desired styling.
     * @param {Array} items - Array of cart item objects.
     */
    renderCartItems: function(items) {
        if (!this.elements.cartItemsContainer) return; // Exit if container not found (e.g., on shop.html)
        this.elements.cartItemsContainer.innerHTML = ''; // Clear existing items
        if (items.length === 0) {
            this.elements.cartItemsContainer.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-medium-gray">Your cart is empty. <a href="./shop.html">Start shopping!</a></td>
                </tr>
            `;
            return;
        }
        items.forEach(item => {
            const displayPrice = parseFloat(item.cart_item_price || item.current_product_price || 0).toFixed(2);
            const itemSubtotal = (parseFloat(item.quantity) * parseFloat(item.cart_item_price || item.current_product_price || 0)).toFixed(2);
            
            // --- اصلاح شده: استفاده از window.BASE_URL برای مسیردهی صحیح تصویر ---
            const productImageUrl = item.featured_image 
                ? `${window.BASE_URL}/${item.featured_image}` 
                : `${window.BASE_URL}/assets/img/placeholder.jpg`;

            const row = `
                <tr>
                    <td data-label="Product">
                        <div class="d-flex align-items-center">
                            <img src="${productImageUrl}" onerror="this.onerror=null;this.src='${window.BASE_URL}/assets/img/placeholder.jpg';" alt="${item.title}" class="rounded-3 me-3" style="width: 80px; height: 80px; object-fit: cover;">
                            <div>
                                <h5 class="mb-0 text-dark-gray">${item.title}</h5>
                                <small class="text-medium-gray">Category: ${item.category_name || 'N/A'}</small>
                            </div>
                        </div>
                    </td>
                    <td data-label="Price" class="text-dark-gray fw-bold">$${displayPrice}</td>
                    <td data-label="Quantity">
                        <div class="input-group input-group-sm quantity-input-group">
                            <button class="btn btn-outline-secondary quantity-minus" type="button" data-product-id="${item.product_id}">-</button>
                            <input type="text" class="form-control text-center quantity-input" value="${item.quantity}" data-product-id="${item.product_id}" readonly>
                            <button class="btn btn-outline-secondary quantity-plus" type="button" data-product-id="${item.product_id}">+</button>
                        </div>
                    </td>
                    <td data-label="Total" class="text-dark-gray fw-bold">$${itemSubtotal}</td>
                    <td data-label="Actions">
                        <button class="btn btn-sm btn-outline-danger remove-item-btn" data-product-id="${item.product_id}">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            `;
            this.elements.cartItemsContainer.insertAdjacentHTML('beforeend', row);
        });
        // Attach event listeners for quantity buttons and remove buttons
        // Using event delegation or re-attaching after rendering
        window.selectAll('.quantity-minus').forEach(button => {
            button.addEventListener('click', this.handleCartQuantityChange.bind(this));
        });
        window.selectAll('.quantity-plus').forEach(button => {
            button.addEventListener('click', this.handleCartQuantityChange.bind(this));
        });
        window.selectAll('.remove-item-btn').forEach(button => {
            button.addEventListener('click', this.handleRemoveFromCart.bind(this));
        });
    },
    /**
     * Updates the cart totals displayed on the page.
     * @param {Object} totals - Object containing subtotal, discount, total, item_count.
     */
    updateCartTotals: function(totals) {
        if (this.elements.cartSubtotalSpan) this.elements.cartSubtotalSpan.textContent = `$${parseFloat(totals.subtotal).toFixed(2)}`;
        if (this.elements.cartDiscountSpan) this.elements.cartDiscountSpan.textContent = `-$${parseFloat(totals.discount).toFixed(2)}`;
        if (this.elements.cartTotalSpan) this.elements.cartTotalSpan.textContent = `$${parseFloat(totals.total).toFixed(2)}`;
        // Update the global cart icon count via main.js utility
        window.updateCartIcon(totals.item_count);
    },
    /**
     * Updates the UI of the coupon section based on current cart totals.
     * This function will now check if the coupon-related elements exist before manipulating them.
     * @param {object} totals - The totals object from the cart API response.
     */
    updateCouponSectionUI: function(totals) {
        // Check if coupon-related elements exist on the current page
        const couponInput = this.elements.couponCodeInput;
        const applyBtn = this.elements.applyCouponBtn;
        const messageElement = this.elements.couponMessageElement;
        
        // If any of the core coupon elements are missing, do not proceed with UI updates for coupon section
        if (!couponInput || !applyBtn || !messageElement) {
            return;
        }
        
        // Remove existing remove coupon button if any
        if (this.elements.removeCouponBtn) {
            this.elements.removeCouponBtn.remove();
            this.elements.removeCouponBtn = null; // Clear cached reference
        }
        
        if (totals.applied_coupon && totals.discount > 0) {
            // Coupon is applied
            messageElement.textContent = `Coupon "${totals.applied_coupon.code}" applied! You saved $${parseFloat(totals.discount).toFixed(2)}.`;
            messageElement.classList.remove('text-danger');
            messageElement.classList.add('text-success');
            couponInput.value = totals.applied_coupon.code;
            couponInput.disabled = true;
            applyBtn.disabled = true;
            
            // Add "Remove Coupon" button
            const removeBtn = document.createElement('button');
            removeBtn.className = window.location.pathname.includes('checkout.html') ? 
                'btn btn-outline-danger ms-2' : 'btn btn-outline-danger ms-2';
            removeBtn.type = 'button';
            removeBtn.textContent = 'Remove Coupon';
            removeBtn.id = 'remove-coupon-btn';
            
            // Check if we're in checkout.html to add the button in the right place
            if (window.location.pathname.includes('checkout.html')) {
                // In checkout.html, add the button after the input group
                const inputGroup = couponInput.closest('.input-group');
                if (inputGroup) {
                    inputGroup.after(removeBtn);
                }
            } else {
                // In cart.html, add the button next to apply button
                applyBtn.parentNode.appendChild(removeBtn);
            }
            
            this.elements.removeCouponBtn = removeBtn; // Cache the new button
            removeBtn.addEventListener('click', this.removeCoupon.bind(this)); // Attach listener
        } else {
            // No coupon applied or coupon removed
            messageElement.textContent = totals.coupon_message || ''; // Display specific message if any
            messageElement.classList.remove('text-success');
            if (totals.coupon_message && totals.discount <= 0) {
                 messageElement.classList.add('text-danger'); // Show as error if message exists but no discount
            } else {
                 messageElement.classList.remove('text-danger');
            }
            couponInput.value = '';
            couponInput.disabled = false;
            applyBtn.disabled = false;
        }
    },
    /**
     * Fetches cart contents and updates the UI.
     * This function will be called by main.js and cart.js itself.
     */
    loadCartSummary: async function() {
        try {
            const response = await fetch(`${window.API_BASE_URL}/cart`);
            const result = await window.handleApiResponse(response);
            // Check authentication status to decide whether to show toast on non-cart pages
            const authStatus = await window.Auth.checkAuthStatus(); // Get auth status
            if (result.status === 'success' && result.data) {
                // Only render items if cartItemsContainer exists (i.e., on cart.html)
                if (this.elements.cartItemsContainer) {
                    this.renderCartItems(result.data.items || []);
                }
                this.updateCartTotals(result.data.totals);
                // Call updateCouponSectionUI only if coupon elements exist on the page
                if (this.elements.couponCodeInput && this.elements.applyCouponBtn && this.elements.couponMessageElement) {
                    this.updateCouponSectionUI(result.data.totals); // Update coupon UI based on loaded data
                }
            } else {
                // Only show toast if on cart.html OR if logged in (error is unexpected)
                if (window.location.pathname.includes('cart.html') || authStatus.logged_in) {
                    window.showToast(result.message || 'Failed to load cart summary.', 'danger');
                }
                
                if (this.elements.cartItemsContainer) {
                    this.elements.cartItemsContainer.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">Error loading cart: ${result.message || 'Unknown error.'}</td></tr>`;
                }
                this.updateCartTotals({ subtotal: 0, discount: 0, total: 0, item_count: 0 });
                // Only attempt to update coupon UI if elements exist
                if (this.elements.couponCodeInput && this.elements.applyCouponBtn && this.elements.couponMessageElement) {
                    this.updateCouponSectionUI({ applied_coupon: null, discount: 0, coupon_message: result.message || 'Failed to load cart.' }); // Clear coupon UI
                }
            }
        } catch (error) {
            console.error('Error in loadCartSummary:', error);
            // Only show toast if on cart.html OR if logged in (network error is unexpected)
            const authStatus = await window.Auth.checkAuthStatus(); // Re-check auth status in catch
            if (window.location.pathname.includes('cart.html') || authStatus.logged_in) {
                window.showToast('Could not load cart summary. Please try again.', 'danger');
            }
            
            if (this.elements.cartItemsContainer) {
                this.elements.cartItemsContainer.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">A network or server error occurred. Please try again.</td></tr>`;
            }
            this.updateCartTotals({ subtotal: 0, discount: 0, total: 0, item_count: 0 });
            // Only attempt to update coupon UI if elements exist
            if (this.elements.couponCodeInput && this.elements.applyCouponBtn && this.elements.couponMessageElement) {
                this.updateCouponSectionUI({ applied_coupon: null, discount: 0, coupon_message: 'Network error loading cart.' }); // Clear coupon UI
            }
        }
    },
    /**
     * Adds an item to the cart.
     * @param {number} productId The ID of the product to add.
     * @param {number} quantity The quantity to add.
     * @returns {Promise<Object>} The full API response.
     */
    addItemToCart: async function(productId, quantity = 1) {
        const formData = { product_id: productId, quantity: quantity };
        const requestOptions = await window.preparePostRequest(formData, 'POST');
        if (!requestOptions) return { status: 'error', message: 'Failed to prepare request.' };
        try {
            const response = await fetch(`${window.API_BASE_URL}/cart/add`, requestOptions);
            const result = await window.handleApiResponse(response);
            if (result.status === 'success') {
                window.showToast(result.message, 'success');
                this.loadCartSummary(); // Refresh cart UI (updates icon and full cart if on cart.html)
            } else {
                window.showToast(result.message || 'Failed to add item to cart.', 'danger');
            }
            return result;
        } catch (error) {
            console.error('Error adding to cart:', error);
            window.showToast('An error occurred while adding to cart. Please try again.', 'danger');
            return { status: 'error', message: 'Network error adding to cart.' };
        }
    },
    /**
     * Updates the quantity of an item in the cart.
     * @param {number} productId The ID of the product whose quantity needs to be updated.
     * @param {number} newQuantity The new quantity.
     * @returns {Promise<Object>} The full API response.
     */
    updateCartItemQuantity: async function(productId, newQuantity) {
        if (newQuantity < 1) {
            return this.removeCartItem(productId); // Use 'this' for internal method call
        }
        const formData = { product_id: productId, new_quantity: newQuantity };
        const requestOptions = await window.preparePostRequest(formData, 'PUT');
        if (!requestOptions) return { status: 'error', message: 'Failed to prepare request.' };
        try {
            const response = await fetch(`${window.API_BASE_URL}/cart/update`, requestOptions);
            const result = await window.handleApiResponse(response);
            if (result.status === 'success') {
                window.showToast(result.message, 'success');
                this.loadCartSummary(); // Refresh cart UI
            } else {
                window.showToast(result.message || 'Failed to update cart item.', 'danger');
            }
            return result;
        } catch (error) {
            console.error('Error updating cart item:', error);
            window.showToast('An error occurred while updating cart. Please try again.', 'danger');
            return { status: 'error', message: 'Network error updating cart.' };
        }
    },
    /**
     * Removes an item from the cart.
     * @param {number} productId The ID of the product to remove.
     * @returns {Promise<Object>} The full API response.
     */
    removeCartItem: async function(productId) {
        // IMPORTANT: Use a custom modal instead of confirm() for better UX.
        // For now, keeping confirm() as per existing code.
        if (!confirm('Are you sure you want to remove this item from your cart?')) {
            return { status: 'info', message: 'Item removal cancelled.' }; // User cancelled
        }
        const formData = { product_id: productId };
        const requestOptions = await window.preparePostRequest(formData, 'DELETE');
        if (!requestOptions) return { status: 'error', message: 'Failed to prepare request.' };
        try {
            const response = await fetch(`${window.API_BASE_URL}/cart/remove`, requestOptions);
            const result = await window.handleApiResponse(response);
            if (result.status === 'success') {
                window.showToast(result.message, 'success');
                this.loadCartSummary(); // Refresh cart UI
            } else {
                window.showToast(result.message || 'Failed to remove item from cart.', 'danger');
            }
            return result;
        } catch (error) {
            console.error('Error removing cart item:', error);
            window.showToast('An error occurred while removing item from cart. Please try again.', 'danger');
            return { status: 'error', message: 'Network error removing item.' };
        }
    },
    /**
     * Applies a coupon code to the cart.
     * Renamed to applyCouponToCart for clarity and consistency with checkout.js.
     * @param {string} couponCode The coupon code to apply.
     * @returns {Promise<Object>} The full API response.
     */
    applyCouponToCart: async function(couponCode) { // Renamed from applyCoupon
        const messageElement = this.elements.couponMessageElement;
        // Only proceed if coupon elements exist
        if (!messageElement || !this.elements.applyCouponBtn || !this.elements.couponCodeInput) {
            console.warn('Coupon UI elements not found. Cannot apply coupon from this page.');
            return { status: 'error', message: 'Coupon functionality not available on this page.' };
        }
        messageElement.textContent = 'Applying coupon...';
        messageElement.className = 'text-center small mt-2 text-info'; // Use text-info for pending
        this.elements.applyCouponBtn.disabled = true; // Disable apply button during API call
        const formData = { coupon_code: couponCode };
        const requestOptions = await window.preparePostRequest(formData, 'POST');
        if (!requestOptions) {
            this.elements.applyCouponBtn.disabled = false;
            return { status: 'error', message: 'Failed to prepare request.' };
        }
        try {
            const response = await fetch(`${window.API_BASE_URL}/cart/apply-coupon`, requestOptions);
            const result = await window.handleApiResponse(response);
            if (result.status === 'success') {
                messageElement.className = 'text-center small mt-2 text-success';
                messageElement.textContent = result.message;
                window.showToast(result.message, 'success');
                this.loadCartSummary(); // Refresh cart UI
            } else {
                messageElement.className = 'text-center small mt-2 text-danger';
                messageElement.textContent = result.message;
                window.showToast(result.message || 'Failed to apply coupon.', 'danger');
            }
            return result; // Return the full result object
        } catch (error) {
            console.error('Error applying coupon:', error);
            messageElement.className = 'text-center small mt-2 text-danger';
            messageElement.textContent = 'A network or server error occurred. Please try again.';
            window.showToast('A network or server error occurred. Please try again.', 'danger');
            return { status: 'error', message: 'Network error applying coupon.' };
        } finally {
            this.elements.applyCouponBtn.disabled = false; // Re-enable apply button
        }
    },
    /**
     * Removes the applied coupon from the cart.
     * @returns {Promise<Object>} The full API response.
     */
    removeCoupon: async function() {
        const messageElement = this.elements.couponMessageElement;
        // Only proceed if coupon elements exist
        if (!messageElement || !this.elements.removeCouponBtn) { // Check removeCouponBtn as well
            console.warn('Coupon UI elements not found. Cannot remove coupon from this page.');
            return { status: 'error', message: 'Coupon functionality not available on this page.' };
        }
        messageElement.textContent = 'Removing coupon...';
        messageElement.className = 'text-center small mt-2 text-info';
        this.elements.removeCouponBtn.disabled = true; // Disable remove button during API call
        // Backend endpoint for removing coupon (assuming /api/cart/remove-coupon)
        const requestOptions = await window.preparePostRequest({}, 'DELETE'); // No specific data needed for removal
        if (!requestOptions) {
            this.elements.removeCouponBtn.disabled = false;
            return { status: 'error', message: 'Failed to prepare request.' };
        }
        try {
            const response = await fetch(`${window.API_BASE_URL}/cart/remove-coupon`, requestOptions);
            const result = await window.handleApiResponse(response);
            if (result.status === 'success') {
                messageElement.className = 'text-center small mt-2 text-success'; // Should be text-success or default
                messageElement.textContent = result.message;
                window.showToast(result.message, 'success');
                this.loadCartSummary(); // Refresh cart UI
            } else {
                messageElement.className = 'text-center small mt-2 text-danger';
                messageElement.textContent = result.message;
                window.showToast(result.message || 'Failed to remove coupon.', 'danger');
            }
            return result; // Return the full result object
        } catch (error) {
            console.error('Error removing coupon:', error);
            messageElement.className = 'text-center small mt-2 text-danger';
            messageElement.textContent = 'A network or server error occurred. Please try again.';
            window.showToast('A network or server error occurred. Please try again.', 'danger');
            return { status: 'error', message: 'Network error removing coupon.' };
        } finally {
            this.elements.removeCouponBtn.disabled = false; // Re-enable remove button
        }
    },
    /**
     * Event handler for quantity change buttons in the cart.
     * @param {Event} event
     */
    handleCartQuantityChange: function(event) {
        const button = event.currentTarget;
        const productId = button.dataset.productId;
        const quantityInput = window.select(`.quantity-input[data-product-id="${productId}"]`);
        let currentQuantity = parseInt(quantityInput.value);
        if (isNaN(currentQuantity)) {
            currentQuantity = 1;
        }
        if (button.classList.contains('quantity-plus')) {
            currentQuantity++;
        } else if (button.classList.contains('quantity-minus')) {
            currentQuantity--;
        }
        this.updateCartItemQuantity(productId, currentQuantity); // Use 'this' for internal method call
    },
    /**
     * Event handler for remove from cart buttons.
     * @param {Event} event
     */
    handleRemoveFromCart: function(event) {
        const button = event.currentTarget;
        const productId = button.dataset.productId;
        this.removeCartItem(productId); // Use 'this' for internal method call
    },
    /**
     * Event handler for apply coupon button.
     * @param {Event} event
     */
    handleApplyCouponClick: function(event) {
        event.preventDefault();
        // Check if coupon input exists before accessing its value
        if (this.elements.couponCodeInput) {
            const couponCode = this.elements.couponCodeInput.value.trim();
            if (couponCode) {
                this.applyCouponToCart(couponCode); // Use 'this' for internal method call
            } else {
                window.showToast('Please enter a coupon code.', 'warning');
            }
        } else {
            console.warn('Coupon input element not found. Cannot apply coupon.');
        }
    },
    /**
     * Event handler for checkout button.
     */
    handleCheckout: function() {
        window.location.href = './checkout.html';
    }
};
document.addEventListener('DOMContentLoaded', async () => { // Made async to await auth status
    // Ensure main.js has defined its global utility functions
    if (typeof window.select === 'function' && typeof window.selectAll === 'function' && typeof window.showToast === 'function' && 
        typeof window.handleApiResponse === 'function' && typeof window.Auth !== 'undefined' && typeof window.Auth.checkAuthStatus === 'function') {
        
        // --- Access control for cart.html ---
        const currentPage = window.location.pathname;
        if (currentPage.includes('cart.html')) {
            const authStatus = await window.Auth.checkAuthStatus();
            if (!authStatus.logged_in) {
                window.showToast('Please log in to view your cart.', 'info');
                setTimeout(() => {
                    window.location.href = './login.html?return_to=' + encodeURIComponent(currentPage);
                }, 1500);
                return; // Stop further execution of cart.js init
            }
        }
        
        // --- Access control for checkout.html ---
        if (currentPage.includes('checkout.html')) {
            const authStatus = await window.Auth.checkAuthStatus();
            if (!authStatus.logged_in) {
                window.showToast('Please log in to proceed with checkout.', 'info');
                setTimeout(() => {
                    window.location.href = './login.html?return_to=' + encodeURIComponent(currentPage);
                }, 1500);
                return; // Stop further execution of cart.js init
            }
        }
        
        window.Cart.init();
    } else {
        console.error('Required main.js, auth.js functions not available. Ensure scripts are loaded in correct order.');
    }
});