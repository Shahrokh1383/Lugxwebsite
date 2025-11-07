// Ensure main.js is loaded first to provide window.API_BASE_URL, window.select, etc.

// Define the global wishlist object with lowercase 'w' for consistency
window.wishlist = {
    /**
     * Fetches the current wishlist contents from the API and renders them on the page.
     */
    loadWishlist: async function() {
        const wishlistItemsContainer = window.select('#wishlist-items-container'); // Use window.select
        const wishlistEmptyMessage = window.select('#wishlist-empty-message');     // Use window.select

        if (!wishlistItemsContainer || !wishlistEmptyMessage) {
            console.warn('Wishlist page elements not found. Skipping full wishlist load.');
            return;
        }

        wishlistItemsContainer.innerHTML = '<div class="col-12 text-center py-5 text-medium-gray">Loading wishlist...</div>';

        try {
            // Use window.API_BASE_URL for consistency
            const response = await fetch(`${window.API_BASE_URL}/wishlist`);
            const result = await window.handleApiResponse(response); // Use global handleApiResponse

            if (result.status === 'success') {
                const items = result.data || [];

                wishlistItemsContainer.innerHTML = ''; // Clear existing items

                if (items.length === 0) {
                    wishlistEmptyMessage.classList.remove('d-none');
                } else {
                    wishlistEmptyMessage.classList.add('d-none');
                    items.forEach(item => {
                        // Ensure prices are numbers for toFixed()
                        const price = parseFloat(item.price);
                        const salePrice = parseFloat(item.sale_price);
                        const displayPrice = salePrice > 0 ? salePrice : price;

                        const productCard = `
                            <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
                                <div class="card h-100 border-0 rounded-3 shadow-sm product-card">
                                    <div class="position-relative">
                                        <a href="./product_detail.html?slug=${item.slug}">
                                            <img src="${window.BASE_URL}/assets/img/products/${item.featured_image || 'placeholder.jpg'}" class="card-img-top rounded-top-3" alt="${item.title}" style="height: 180px; object-fit: cover;">
                                        </a>
                                        <span class="badge bg-danger position-absolute top-0 start-0 m-2 price-badge">$${displayPrice.toFixed(2)}</span>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title text-dark-gray fw-bold mb-2">
                                            <a href="./product_detail.html?slug=${item.slug}" class="text-dark-gray text-decoration-none">${item.title}</a>
                                        </h5>
                                        <p class="card-text text-medium-gray small mb-3">Category: ${item.category_name || 'N/A'}</p>
                                        <div class="d-flex justify-content-between align-items-center mt-auto">
                                            <button class="btn custom-btn-secondary btn-sm add-to-cart-btn" data-product-id="${item.product_id}">
                                                <i class="fas fa-shopping-cart me-1"></i> Add to Cart
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger remove-from-wishlist-btn" data-product-id="${item.product_id}">
                                                <i class="fas fa-trash-alt"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        wishlistItemsContainer.insertAdjacentHTML('beforeend', productCard);
                    });

                    // Attach event listeners using event delegation or re-attaching after rendering
                    window.selectAll('.add-to-cart-btn').forEach(button => {
                        button.addEventListener('click', (e) => {
                            const productId = e.currentTarget.dataset.productId;
                            // Call the global Cart object's function
                            if (window.Cart && typeof window.Cart.addItemToCart === 'function') {
                                window.Cart.addItemToCart(productId, 1);
                            } else {
                                window.showToast('Cart functionality not loaded.', 'error');
                                console.error('Cart.addItemToCart function not available.');
                            }
                        });
                    });

                    window.selectAll('.remove-from-wishlist-btn').forEach(button => {
                        button.addEventListener('click', (e) => {
                            const productId = e.currentTarget.dataset.productId;
                            window.wishlist.removeFromWishlist(productId); // Call local wishlist function
                        });
                    });
                }
            } else {
                console.error('Failed to load wishlist:', result.message);
                wishlistItemsContainer.innerHTML = `<div class="col-12 alert alert-danger text-center mt-4" role="alert">Error loading wishlist: ${result.message}</div>`;
                wishlistEmptyMessage.classList.add('d-none');
                window.showToast(result.message || 'Error loading wishlist.', 'danger');
            }
        } catch (error) {
            console.error('Network or parsing error loading wishlist:', error);
            wishlistItemsContainer.innerHTML = `<div class="col-12 alert alert-danger text-center mt-4" role="alert">A network error occurred. Please try again.</div>`;
            wishlistEmptyMessage.classList.add('d-none');
            window.showToast('A network error occurred while loading wishlist. Please try again.', 'danger');
        }
    },

    /**
     * Adds a product to the user's wishlist.
     * @param {number} productId The ID of the product to add.
     * @returns {Promise<boolean>} True if successful, false otherwise.
     */
    addToWishlist: async function(productId) {
        const formData = { product_id: productId };
        const requestOptions = await window.preparePostRequest(formData, 'POST'); // Use global preparePostRequest

        if (!requestOptions) return false;

        try {
            // Use window.API_BASE_URL for consistency
            const response = await fetch(`${window.API_BASE_URL}/wishlist/add`, requestOptions);
            const result = await window.handleApiResponse(response); // Use global handleApiResponse

            if (result.status === 'success') {
                console.log(result.message);
                window.showToast(result.message, 'success');
                // If on wishlist.html, reload the list to show the new item
                if (window.location.pathname.includes('wishlist.html')) {
                    window.wishlist.loadWishlist();
                }
                return true;
            } else {
                console.error('Failed to add item to wishlist:', result.message);
                window.showToast(result.message || 'Failed to add item to wishlist.', 'danger');
                return false;
            }
        } catch (error) {
            console.error('Network or parsing error adding item to wishlist:', error);
            window.showToast('A network error occurred while adding item to wishlist. Please try again.', 'danger');
            return false;
        }
    },

    /**
     * Removes a product from the user's wishlist.
     * @param {number} productId The ID of the product to remove.
     * @returns {Promise<boolean>} True if successful, false otherwise.
     */
    removeFromWishlist: async function(productId) {
        // IMPORTANT: Use a custom modal instead of confirm() for better UX and consistency.
        // For now, keeping confirm() as per existing code.
        if (!confirm('Are you sure you want to remove this item from your wishlist?')) {
            return false; // User cancelled
        }

        const formData = { product_id: productId };
        const requestOptions = await window.preparePostRequest(formData, 'DELETE'); // Use global preparePostRequest

        if (!requestOptions) return false;

        try {
            // Use window.API_BASE_URL for consistency
            const response = await fetch(`${window.API_BASE_URL}/wishlist/remove`, requestOptions);
            const result = await window.handleApiResponse(response); // Use global handleApiResponse

            if (result.status === 'success') {
                console.log(result.message);
                window.showToast(result.message, 'success');
                window.wishlist.loadWishlist(); // Reload wishlist to reflect changes
                return true;
            } else {
                console.error('Failed to remove item from wishlist:', result.message);
                window.showToast(result.message || 'Failed to remove item from wishlist.', 'danger');
                return false;
            }
        } catch (error) {
            console.error('Network or parsing error removing item from wishlist:', error);
            window.showToast('A network error occurred while removing item from wishlist. Please try again.', 'danger');
            return false;
        }
    },

    /**
     * Adds a product from the wishlist directly to the shopping cart.
     * Requires the global Cart object to be available (from cart.js).
     * @param {number} productId The ID of the product to add to cart.
     */
    addWishlistItemToCart: async function(productId) {
        if (typeof window.Cart === 'undefined' || typeof window.Cart.addItemToCart !== 'function') {
            console.error('Cart functionality (cart.js) is not loaded or available.');
            window.showToast('Shopping cart functionality is not available. Please ensure cart.js is loaded.', 'danger');
            return false;
        }
        // Call the addItemToCart function from the global Cart object
        const success = await window.Cart.addItemToCart(productId, 1); // Add 1 quantity
        // showToast will be handled by Cart.addItemToCart
        // Optionally remove from wishlist after adding to cart (UX decision)
        // if (success) {
        //     await window.wishlist.removeFromWishlist(productId);
        // }
        return success;
    }
};

// Event listeners for wishlist.html page
document.addEventListener('DOMContentLoaded', function() {
    // Only load wishlist if on the wishlist.html page
    if (window.location.pathname.includes('wishlist.html')) {
        // Ensure main.js utilities are available before calling wishlist functions
        if (typeof window.select === 'function' && typeof window.selectAll === 'function' && typeof window.showToast === 'function' && typeof window.handleApiResponse === 'function' && typeof window.preparePostRequest === 'function') {
            window.wishlist.loadWishlist();
        } else {
            console.error('Required main.js functions not available. Ensure main.js is loaded before wishlist.js.');
        }
    }
    // No need to load wishlist summary for other pages, as wishlist count is not in header
});
