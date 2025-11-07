// public/assets/js/admin/admin_order_detail.js
// This script handles the AJAX operations for viewing and updating order details.

document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Elements ---
    const loadingSpinner = document.getElementById('loadingSpinner');
    const orderDetailsContent = document.getElementById('orderDetailsContent');
    const orderIdDisplay = document.getElementById('orderIdDisplay');
    const orderDateDisplay = document.getElementById('orderDateDisplay');
    const orderTotalAmount = document.getElementById('orderTotalAmount');
    const orderStatusDisplay = document.getElementById('orderStatusDisplay');
    const customerName = document.getElementById('customerName');
    const customerEmail = document.getElementById('customerEmail');
    const shippingAddress = document.getElementById('shippingAddress');
    const orderItemsTableBody = document.getElementById('orderItemsTableBody');
    const productKeysList = document.getElementById('productKeysList');
    const updateStatusForm = document.getElementById('updateStatusForm');
    const orderStatusSelect = document.getElementById('orderStatusSelect');

    // Assume these functions are available globally from admin_main.js
    // API_BASE_URL, showMessage, showLoading, hideLoading are assumed
    
    // --- Get Order ID from URL ---
    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('id');

    if (!orderId) {
        showMessage('Invalid order ID.', 'danger');
        hideLoading();
        return;
    }

    // --- Fetch Order Details ---
    const fetchOrderDetails = async () => {
        showLoading();
        try {
            const response = await fetch(`${API_BASE_URL}/api/admin/orders/${orderId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            if (response.ok && result.success) {
                renderOrderDetails(result.data);
                orderDetailsContent.style.display = 'block';
            } else {
                showMessage(result.message || 'Failed to fetch order details.', 'danger');
                orderDetailsContent.style.display = 'none';
            }
        } catch (error) {
            console.error('Error fetching order details:', error);
            showMessage('Connection error while fetching order details.', 'danger');
            orderDetailsContent.style.display = 'none';
        } finally {
            hideLoading();
        }
    };

    // --- Render Order Details ---
    const renderOrderDetails = (order) => {
        // Basic Order Info
        orderIdDisplay.textContent = order.id;
        orderTotalAmount.textContent = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(order.total_amount);
        orderDateDisplay.textContent = new Date(order.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        orderStatusDisplay.textContent = order.status;
        orderStatusSelect.value = order.status; // Set the dropdown to the current status

        // Customer Info
        customerName.textContent = order.user_name || 'N/A';
        customerEmail.textContent = order.user_email || 'N/A';
        shippingAddress.innerHTML = `
            ${order.shipping_address.street || ''}<br>
            ${order.shipping_address.city || ''}, ${order.shipping_address.state || ''} ${order.shipping_address.zip || ''}<br>
            ${order.shipping_address.country || ''}
        `;

        // Order Items Table
        orderItemsTableBody.innerHTML = '';
        if (order.items && order.items.length > 0) {
            order.items.forEach(item => {
                const row = document.createElement('tr');
                const formattedPrice = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(item.price);
                const formattedTotal = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(item.price * item.quantity);
                row.innerHTML = `
                    <td>${item.product_name}</td>
                    <td>${item.quantity}</td>
                    <td>${formattedPrice}</td>
                    <td>${formattedTotal}</td>
                `;
                orderItemsTableBody.appendChild(row);
            });
        } else {
            orderItemsTableBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No items found for this order.</td></tr>`;
        }

        // Product Keys
        productKeysList.innerHTML = '';
        if (order.keys && order.keys.length > 0) {
            order.keys.forEach(key => {
                const listItem = document.createElement('li');
                listItem.className = 'list-group-item bg-dark text-light';
                listItem.textContent = key;
                productKeysList.appendChild(listItem);
            });
        } else {
            productKeysList.innerHTML = `<li class="list-group-item bg-dark text-muted">No product keys available.</li>`;
        }
    };

    // --- Form Submission Handler (Update Status) ---
    updateStatusForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const newStatus = orderStatusSelect.value;
        const formData = new FormData();
        formData.append('status', newStatus);
        
        showLoading();

        try {
            const response = await fetch(`${API_BASE_URL}/api/admin/orders/${orderId}/status`, {
                method: 'POST', // Assuming PUT is not supported, we can use a POST with a method override if needed.
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
            });
            const result = await response.json();

            if (response.ok && result.success) {
                showMessage(result.message || 'Order status updated successfully.', 'success');
                // Re-fetch details to show the updated status
                fetchOrderDetails();
            } else {
                showMessage(result.message || 'Error updating order status.', 'danger');
            }
        } catch (error) {
            console.error('Status update error:', error);
            showMessage('Connection error while updating status.', 'danger');
        } finally {
            hideLoading();
        }
    });

    // --- Initial Call ---
    fetchOrderDetails();
});
