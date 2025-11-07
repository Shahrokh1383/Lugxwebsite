// Ensure main.js and auth.js are loaded before this script.

window.UserOrders = {
    /**
     * Initializes the user orders page.
     */
    init: async function() {
        console.log('Initializing user orders page...');
        window.clearErrors('message-container-orders');

        // Check if user is logged in, redirect to login if not
        const authStatus = await window.Auth.checkAuthStatus();
        if (!authStatus.logged_in) {
            window.showToast('Please log in to view your orders.', 'info');
            setTimeout(() => {
                window.location.href = './login.html?return_to=' + encodeURIComponent(window.location.pathname);
            }, 1500);
            return;
        }

        await this.loadUserOrders();
        this.setupEventListeners();
    },

    /**
     * Loads the list of orders for the authenticated user.
     */
    loadUserOrders: async function() {
        const ordersListContainer = window.select('#orders-list');
        const noOrdersMessage = window.select('#no-orders-message');
        const messageContainer = window.select('#message-container-orders');

        if (!ordersListContainer || !noOrdersMessage || !messageContainer) {
            console.warn('Required DOM elements for user orders not found.');
            return;
        }

        ordersListContainer.innerHTML = '<p class="text-center text-medium-gray py-5">Loading your orders...</p>';
        noOrdersMessage.classList.add('d-none');
        window.clearErrors(messageContainer.id);

        try {
            const response = await fetch(`${window.API_BASE_URL}/orders`);
            const result = await window.handleApiResponse(response);

            if (result.status === 'success' && result.data) {
                const orders = result.data;
                ordersListContainer.innerHTML = ''; // Clear loading message

                if (orders.length === 0) {
                    noOrdersMessage.classList.remove('d-none');
                    ordersListContainer.innerHTML = ''; // Ensure list is empty
                } else {
                    noOrdersMessage.classList.add('d-none');
                    this.renderOrders(orders, ordersListContainer);
                }
            } else {
                window.displayMessage(result.message || 'Failed to load orders.', 'danger', messageContainer.id);
                ordersListContainer.innerHTML = ''; // Clear on error
            }
        } catch (error) {
            console.error('Error loading user orders:', error);
            window.displayMessage('A network error occurred while loading orders. Please try again.', 'danger', messageContainer.id);
            ordersListContainer.innerHTML = ''; // Clear on error
        }
    },

    /**
     * Renders the list of orders into the specified container.
     * @param {Array} orders - An array of order objects.
     * @param {HTMLElement} container - The DOM element to render orders into.
     */
    renderOrders: function(orders, container) {
        let tableHtml = `
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Order #</th>
                        <th scope="col">Date</th>
                        <th scope="col">Total</th>
                        <th scope="col">Status</th>
                        <th scope="col">Payment Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        orders.forEach(order => {
            const orderDate = new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            const orderTotal = parseFloat(order.total_amount).toFixed(2);
            const orderStatus = order.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()); // e.g., "pending" -> "Pending"
            const paymentStatus = order.payment_status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()); // e.g., "pending" -> "Pending"

            tableHtml += `
                <tr>
                    <td><a href="./user_order_detail.html?order_id=${order.id}" class="text-primary-blue fw-bold">${order.order_number}</a></td>
                    <td>${orderDate}</td>
                    <td>$${orderTotal}</td>
                    <td><span class="badge bg-secondary">${orderStatus}</span></td>
                    <td><span class="badge ${this.getPaymentStatusBadgeClass(order.payment_status)}">${paymentStatus}</span></td>
                    <td>
                        <a href="./user_order_detail.html?order_id=${order.id}" class="btn btn-sm btn-outline-primary me-2" title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        ${(order.payment_status === 'paid' || order.payment_status === 'delivered') ?
                            // Changed to view keys on detail page, not directly download from list
                            // The download keys button here is removed as per the new approach, keys will be on detail page
                            // If you still want a quick download/view button here, it needs to fetch order details first.
                            // For now, we'll rely on the detail page.
                            // Removed the direct download keys button from here for simplicity and consistency with detail page
                            // If you want to keep it, it needs to be updated to handle multiple keys per item.
                            // For now, the detail page will handle all key/download displays.
                            '' 
                        : ''
                        }
                    </td>
                </tr>
            `;
        });

        tableHtml += `
                </tbody>
            </table>
        `;
        container.innerHTML = tableHtml;
    },

    /**
     * Returns the appropriate Bootstrap badge class for payment status.
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
     * Sets up event listeners for dynamically added elements (e.g., download keys buttons).
     * NOTE: The direct "Download Keys" button is removed from the list view.
     * Keys will be displayed on the order detail page.
     */
    setupEventListeners: function() {
        // No direct download keys button event listener needed here anymore.
        // All key/download logic will be on user_order_detail.js
    },

    /**
     * Fetches and displays product keys for a specific order.
     * This method is now primarily for the order detail page, but kept here for reference.
     * It will be modified/moved to user_order_detail.js
     * @param {number} orderId - The ID of the order.
     */
    getDownloadKeys: async function(orderId) {
        // This method's logic will be moved to user_order_detail.js
        // For now, it's just a placeholder.
        console.warn('getDownloadKeys method is deprecated in order.js. Use user_order_detail.js for key display.');
        window.showToast('Please view order details page for product keys.', 'info');
    },

    /**
     * Copies text to the clipboard.
     * This utility function can remain here or be moved to main.js if it's generic.
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
    },

    /**
     * Fetches the total number of orders for the authenticated user.
     * This is intended for the dashboard summary.
     * @returns {Promise<number>} The total number of orders, or 0 on error.
     */
    getTotalOrdersCount: async function() {
        try {
            const response = await fetch(`${window.API_BASE_URL}/orders`);
            const result = await window.handleApiResponse(response);

            if (result.status === 'success' && result.data) {
                return result.data.length; // Return the count of orders
            } else {
                console.error('Failed to get total orders count:', result.message);
                return 0;
            }
        } catch (error) {
            console.error('Network error getting total orders count:', error);
            return 0;
        }
    }
};

// Initialize user orders when DOM is ready and on user_orders.html
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('user_orders.html')) {
        if (typeof window.select === 'function' && typeof window.selectAll === 'function' &&
            typeof window.showToast === 'function' && typeof window.displayMessage === 'function' &&
            typeof window.clearErrors === 'function' && typeof window.handleApiResponse === 'function' &&
            typeof window.Auth !== 'undefined') {
            window.UserOrders.init();
        } else {
            console.error('Required global functions or objects (main.js, auth.js) are not available. Ensure scripts are loaded in correct order.');
        }
    }
});
