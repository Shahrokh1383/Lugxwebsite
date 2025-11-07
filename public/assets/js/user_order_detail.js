// Ensure main.js and auth.js are loaded before this script.

window.UserOrderDetail = {
    /**
     * Initializes the order details page.
     */
    init: async function() {
        console.log('Initializing user order detail page...');
        window.clearErrors('message-container-order-detail');

        const authStatus = await window.Auth.checkAuthStatus();
        if (!authStatus.logged_in) {
            window.showToast('Please log in to view order details.', 'info');
            setTimeout(() => {
                window.location.href = './login.html?return_to=' + encodeURIComponent(window.location.pathname);
            }, 1500);
            return;
        }

        const orderId = this.getOrderIdFromUrl();
        if (orderId) {
            await this.loadOrderDetail(orderId);
        } else {
            window.displayMessage('No order ID found in URL. Please go back to your orders list.', 'danger', 'message-container-order-detail');
            window.showToast('Invalid access to order details.', 'danger');
        }

        this.setupEventListeners();
    },

    /**
     * Extracts the order ID from the URL query parameters.
     * @returns {number|null} The order ID or null if not found.
     */
    getOrderIdFromUrl: function() {
        const params = new URLSearchParams(window.location.search);
        const orderId = params.get('order_id');
        return orderId ? parseInt(orderId) : null;
    },

    /**
     * Loads the full details of a specific order from the API.
     * @param {number} orderId - The ID of the order to load.
     */
    loadOrderDetail: async function(orderId) {
        const orderDetailsContent = window.select('#order-details-content');
        const orderNumberDisplay = window.select('#order-number-display');
        const messageContainer = window.select('#message-container-order-detail');

        if (!orderDetailsContent || !orderNumberDisplay || !messageContainer) {
            console.warn('Required DOM elements for order detail not found.');
            return;
        }

        orderDetailsContent.innerHTML = '<p class="text-center text-medium-gray py-5">Loading order details...</p>';
        orderNumberDisplay.textContent = ''; // Clear previous order number
        window.clearErrors(messageContainer.id);

        try {
            const response = await fetch(`${window.API_BASE_URL}/orders/${orderId}`);
            const result = await window.handleApiResponse(response);

            if (result.status === 'success' && result.data) {
                const order = result.data;
                orderNumberDisplay.textContent = `(#${order.order_number})`;
                this.renderOrderDetail(order, orderDetailsContent);
                window.showToast('Order details loaded successfully!', 'success');
            } else {
                window.displayMessage(result.message || 'Failed to load order details.', 'danger', messageContainer.id);
                orderDetailsContent.innerHTML = '<p class="text-center text-danger py-5">Error loading order details.</p>';
                window.showToast('Error loading order details.', 'danger');
            }
        } catch (error) {
            console.error('Error loading order details:', error);
            window.displayMessage('A network error occurred while loading order details. Please try again.', 'danger', messageContainer.id);
            orderDetailsContent.innerHTML = '<p class="text-center text-danger py-5">Network error loading order details.</p>';
            window.showToast('Network error loading order details.', 'danger');
        }
    },

    /**
     * Renders the full order details into the specified container.
     * @param {object} order - The order object with all details.
     * @param {HTMLElement} container - The DOM element to render details into.
     */
    renderOrderDetail: function(order, container) {
        const orderDate = new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        const orderTotal = parseFloat(order.total_amount).toFixed(2);
        const orderSubtotal = parseFloat(order.subtotal).toFixed(2);
        const orderDiscount = parseFloat(order.discount_amount).toFixed(2);
        const orderStatus = order.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()); 
        const paymentStatus = order.payment_status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());

        let html = `
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="fw-bold text-dark-gray">Order Summary</h5>
                    <p class="text-medium-gray mb-1"><strong>Order Date:</strong> ${orderDate}</p>
                    <p class="text-medium-gray mb-1"><strong>Status:</strong> <span class="badge bg-secondary">${orderStatus}</span></p>
                    <p class="text-medium-gray mb-1"><strong>Payment Status:</strong> <span class="badge ${this.getPaymentStatusBadgeClass(order.payment_status)}">${paymentStatus}</span></p>
                    <p class="text-medium-gray mb-1"><strong>Payment Method:</strong> ${order.payment_method.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase())}</p>
                    ${order.coupon_code ? `<p class="text-medium-gray mb-1"><strong>Coupon Applied:</strong> ${order.coupon_code}</p>` : ''}
                    ${order.notes ? `<p class="text-medium-gray mb-1"><strong>Notes:</strong> ${order.notes}</p>` : ''}
                </div>
                <div class="col-md-6 text-md-end">
                    <h5 class="fw-bold text-dark-gray">Order Totals</h5>
                    <p class="text-medium-gray mb-1"><strong>Subtotal:</strong> $${orderSubtotal}</p>
                    <p class="text-medium-gray mb-1"><strong>Discount:</strong> $${orderDiscount}</p>
                    <p class="text-medium-gray mb-1"><strong>Shipping:</strong> $${parseFloat(order.shipping_amount).toFixed(2)}</p>
                    <p class="text-medium-gray mb-1"><strong>Tax:</strong> $${parseFloat(order.tax_amount).toFixed(2)}</p>
                    <h4 class="fw-bold text-dark-gray mt-2">Total: $${orderTotal}</h4>
                </div>
            </div>

            <hr class="my-4">

            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="fw-bold text-dark-gray">Billing Address</h5>
                    <p class="text-medium-gray mb-1">${order.billing_address.first_name} ${order.billing_address.last_name}</p>
                    <p class="text-medium-gray mb-1">${order.billing_address.address}${order.billing_address.address2 ? `, ${order.billing_address.address2}` : ''}</p>
                    <p class="text-medium-gray mb-1">${order.billing_address.city}, ${order.billing_address.state} ${order.billing_address.postal_code}</p>
                    <p class="text-medium-gray mb-1">${order.billing_address.country}</p>
                    <p class="text-medium-gray mb-1">Phone: ${order.billing_address.phone || 'N/A'}</p>
                </div>
                <div class="col-md-6">
                    <h5 class="fw-bold text-dark-gray">Shipping Address</h5>
                    <p class="text-medium-gray mb-1">${order.shipping_address.first_name} ${order.shipping_address.last_name}</p>
                    <p class="text-medium-gray mb-1">${order.shipping_address.address}${order.shipping_address.address2 ? `, ${order.shipping_address.address2}` : ''}</p>
                    <p class="text-medium-gray mb-1">${order.shipping_address.city}, ${order.shipping_address.state} ${order.shipping_address.postal_code}</p>
                    <p class="text-medium-gray mb-1">${order.shipping_address.country}</p>
                    <p class="text-medium-gray mb-1">Phone: ${order.shipping_address.phone || 'N/A'}</p>
                </div>
            </div>

            <hr class="my-4">

            <h5 class="fw-bold text-dark-gray mb-3">Order Items</h5>
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Product</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Price</th>
                            <th scope="col">Total</th>
                            <th scope="col">Download/Key</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        order.items.forEach(item => {
            const itemPrice = parseFloat(item.item_price_at_purchase).toFixed(2);
            const itemTotal = parseFloat(item.item_total_at_purchase).toFixed(2);
            const productImageUrl = item.product_featured_image ? `../assets/uploads/${item.product_featured_image}` : 'https://placehold.co/50x50/cccccc/000000?text=No+Image';

            let downloadKeyContent = '';
            if (item.assigned_keys && item.assigned_keys.length > 0) {
                // Display all assigned keys
                downloadKeyContent = '<ul class="list-unstyled mb-0">';
                item.assigned_keys.forEach(key => {
                    downloadKeyContent += `
                        <li class="d-flex align-items-center mb-1">
                            <code>${key.license_key}</code>
                            <button class="btn btn-sm btn-outline-secondary ms-2 copy-key-btn" data-key="${key.license_key}" title="Copy to clipboard">
                                <i class="fas fa-copy"></i>
                            </button>
                        </li>
                    `;
                });
                downloadKeyContent += '</ul>';
            } else if (item.download_link) {
                // Display direct download link if available
                downloadKeyContent = `<a href="${item.download_link}" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-download me-1"></i> Download File</a>`;
            } else {
                downloadKeyContent = 'N/A'; 
            }

            html += `
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="${productImageUrl}" alt="${item.product_title}" class="rounded me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                    <a href="./shop_detail.html?slug=${item.product_slug}" class="text-primary-blue fw-bold">${item.product_title}</a>
                                </div>
                            </td>
                            <td>${item.quantity}</td>
                            <td>$${itemPrice}</td>
                            <td>$${itemTotal}</td>
                            <td>${downloadKeyContent}</td>
                        </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>

            <hr class="my-4">

            <h5 class="fw-bold text-dark-gray mb-3">Order Status History</h5>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Status</th>
                            <th scope="col">Comment</th>
                            <th scope="col">Changed By</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        if (order.history && order.history.length > 0) {
            order.history.forEach(entry => {
                const historyDate = new Date(entry.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                const historyStatus = entry.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
                const changedBy = entry.username || (entry.first_name && entry.last_name ? entry.first_name + ' ' + entry.last_name : 'System'); 

                html += `
                        <tr>
                            <td>${historyDate}</td>
                            <td><span class="badge bg-info text-dark">${historyStatus}</span></td>
                            <td>${entry.comment || 'No comment'}</td>
                            <td>${changedBy}</td>
                        </tr>
                `;
            });
        } else {
            html += `
                        <tr>
                            <td colspan="4" class="text-center text-medium-gray">No status history available.</td>
                        </tr>
            `;
        }

        html += `
                    </tbody>
                </table>
            </div>
        `;

        container.innerHTML = html;
    },

    /**
     * Returns the appropriate Bootstrap badge class for payment status.
     * Replicated from order.js for consistency.
     * @param {string} status - The payment status string.
     * @returns {string} Bootstrap badge class.
     */
    getPaymentStatusBadgeClass: function(status) {
        switch (status) {
            case 'pending':
                return 'bg-warning text-dark';
            case 'paid':
                return 'bg-success';
            case 'failed':
                return 'bg-danger';
            case 'refunded':
                return 'bg-info text-dark';
            default:
                return 'bg-secondary';
        }
    },

    /**
     * Sets up event listeners for dynamically added elements (e.g., copy key buttons).
     */
    setupEventListeners: function() {
        // Event delegation for copy buttons
        const orderDetailsContent = window.select('#order-details-content');
        if (orderDetailsContent) {
            orderDetailsContent.addEventListener('click', (event) => {
                const target = event.target.closest('.copy-key-btn');
                if (target) {
                    const keyToCopy = target.dataset.key;
                    if (keyToCopy) {
                        this.copyToClipboard(keyToCopy);
                    }
                }
            });
        }
    },

    /**
     * Copies text to the clipboard.
     * Replicated from order.js. Can be moved to main.js if it's a generic utility.
     * @param {string} text - The text to copy.
     */
    copyToClipboard: function(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            window.showToast('Key copied to clipboard!', 'success');
        } catch (err) {
            console.error('Failed to copy text: ', err);
            window.showToast('Failed to copy key to clipboard.', 'danger');
        }
        document.body.removeChild(textarea);
    }
};

// Initialize UserOrderDetail when DOM is ready and on user_order_detail.html
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('user_order_detail.html')) {
        if (typeof window.select === 'function' && typeof window.selectAll === 'function' &&
            typeof window.showToast === 'function' && typeof window.displayMessage === 'function' &&
            typeof window.clearErrors === 'function' && typeof window.handleApiResponse === 'function' &&
            typeof window.Auth !== 'undefined') {
            window.UserOrderDetail.init();
        } else {
            console.error('Required global functions or objects (main.js, auth.js) are not available. Ensure scripts are loaded in correct order.');
        }
    }
});
