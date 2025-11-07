/**
 * public/assets/js/admin/admin_orders.js
 *
 * This file handles the orders management functionality for the admin panel.
 * It interacts with the AdminOrderController backend.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get the base URL path from the global variable
    const baseUrlPath = window.AppBaseUrlPath || '';
    
    // DOM Elements
    const loadingSpinner = document.getElementById('loadingSpinner');
    const ordersTableCard = document.getElementById('ordersTableCard');
    const ordersTableBody = document.getElementById('ordersTableBody');
    const ordersPagination = document.getElementById('ordersPagination');
    const messageDiv = document.getElementById('message');
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const statusFilter = document.getElementById('statusFilter');
    
    // Modals
    const orderDetailModal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    const statusChangeModal = new bootstrap.Modal(document.getElementById('statusChangeModal'));
    const paymentStatusChangeModal = new bootstrap.Modal(document.getElementById('paymentStatusChangeModal'));
    const assignKeyModal = new bootstrap.Modal(document.getElementById('assignKeyModal'));
    
    // Forms
    const statusChangeForm = document.getElementById('statusChangeForm');
    const paymentStatusChangeForm = document.getElementById('paymentStatusChangeForm');
    const assignKeyForm = document.getElementById('assignKeyForm');
    
    // Buttons
    const saveStatusChangeBtn = document.getElementById('saveStatusChange');
    const savePaymentStatusChangeBtn = document.getElementById('savePaymentStatusChange');
    const assignKeyBtn = document.getElementById('assignKeyButton');
    
    // State
    let currentPage = 1;
    let itemsPerPage = 10;
    let totalItems = 0;
    let currentFilters = {
        search: '',
        status: ''
    };
    
    // Initialize
    function init() {
        loadOrders();
        setupEventListeners();
    }
    
    // Setup event listeners
    function setupEventListeners() {
        // Search functionality
        searchButton.addEventListener('click', function() {
            currentFilters.search = searchInput.value.trim();
            currentPage = 1;
            loadOrders();
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                currentFilters.search = searchInput.value.trim();
                currentPage = 1;
                loadOrders();
            }
        });
        
        // Status filter
        statusFilter.addEventListener('change', function() {
            currentFilters.status = statusFilter.value;
            currentPage = 1;
            loadOrders();
        });
        
        // Save status change
        saveStatusChangeBtn.addEventListener('click', saveStatusChange);
        
        // Save payment status change
        savePaymentStatusChangeBtn.addEventListener('click', savePaymentStatusChange);
        
        // Assign key
        assignKeyBtn.addEventListener('click', assignProductKey);
    }
    
    // Load orders from API
    async function loadOrders() {
        showLoading(true);
        hideMessage();
        
        try {
            // Build query parameters
            const params = new URLSearchParams({
                page: currentPage,
                limit: itemsPerPage
            });
            
            if (currentFilters.search) {
                params.append('search', currentFilters.search);
            }
            
            if (currentFilters.status) {
                params.append('status', currentFilters.status);
            }
            
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/orders?${params.toString()}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success !== false) {
                totalItems = data.length;
                renderOrdersTable(data);
                renderPagination();
                ordersTableCard.style.display = 'block';
            } else {
                showMessage(data.message || 'Failed to load orders.', 'danger');
            }
        } catch (error) {
            console.error('Error loading orders:', error);
            showMessage('Network error. Please try again.', 'danger');
        } finally {
            showLoading(false);
        }
    }
    
    // Render orders table
    function renderOrdersTable(orders) {
        ordersTableBody.innerHTML = '';
        
        if (!orders || orders.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = `<td colspan="7" class="text-center">No orders found.</td>`;
            ordersTableBody.appendChild(emptyRow);
            return;
        }
        
        orders.forEach(order => {
            const row = document.createElement('tr');
            
            // Format status badge
            let statusBadge = '';
            switch(order.status) {
                case 'pending':
                    statusBadge = '<span class="badge bg-warning">Pending</span>';
                    break;
                case 'processing':
                    statusBadge = '<span class="badge bg-info">Processing</span>';
                    break;
                case 'shipped':
                    statusBadge = '<span class="badge bg-primary">Shipped</span>';
                    break;
                case 'delivered':
                    statusBadge = '<span class="badge bg-success">Delivered</span>';
                    break;
                case 'cancelled':
                    statusBadge = '<span class="badge bg-danger">Cancelled</span>';
                    break;
                case 'refunded':
                    statusBadge = '<span class="badge bg-secondary">Refunded</span>';
                    break;
                default:
                    statusBadge = `<span class="badge bg-secondary">${order.status}</span>`;
            }
            
            // Format payment status badge
            let paymentStatusBadge = '';
            switch(order.payment_status) {
                case 'pending':
                    paymentStatusBadge = '<span class="badge bg-warning">Pending</span>';
                    break;
                case 'paid':
                    paymentStatusBadge = '<span class="badge bg-success">Paid</span>';
                    break;
                case 'failed':
                    paymentStatusBadge = '<span class="badge bg-danger">Failed</span>';
                    break;
                case 'refunded':
                    paymentStatusBadge = '<span class="badge bg-secondary">Refunded</span>';
                    break;
                default:
                    paymentStatusBadge = `<span class="badge bg-secondary">${order.payment_status}</span>`;
            }
            
            // Format date
            const orderDate = new Date(order.created_at).toLocaleDateString();
            
            row.innerHTML = `
                <td>#${order.order_number || order.id}</td>
                <td>${Admin.escapeHtml(order.first_name || '')} ${Admin.escapeHtml(order.last_name || '')}</td>
                <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
                <td>${statusBadge}</td>
                <td>${paymentStatusBadge}</td>
                <td>${orderDate}</td>
                <td>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-info view-order" data-id="${order.id}" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary change-status" data-id="${order.id}" title="Change Status">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success change-payment-status" data-id="${order.id}" title="Change Payment Status">
                            <i class="fas fa-money-bill-wave"></i>
                        </button>
                    </div>
                </td>
            `;
            
            ordersTableBody.appendChild(row);
        });
        
        // Add event listeners to action buttons
        document.querySelectorAll('.view-order').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = parseInt(this.getAttribute('data-id'));
                viewOrderDetails(orderId);
            });
        });
        
        document.querySelectorAll('.change-status').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = parseInt(this.getAttribute('data-id'));
                openStatusChangeModal(orderId);
            });
        });
        
        document.querySelectorAll('.change-payment-status').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = parseInt(this.getAttribute('data-id'));
                openPaymentStatusChangeModal(orderId);
            });
        });
    }
    
    // Render pagination
    function renderPagination() {
        ordersPagination.innerHTML = '';
        
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        
        if (totalPages <= 1) {
            return;
        }
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>`;
        ordersPagination.appendChild(prevLi);
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
                ordersPagination.appendChild(li);
            } else if (i === currentPage - 2 || i === currentPage + 2) {
                const li = document.createElement('li');
                li.className = 'page-item disabled';
                li.innerHTML = `<a class="page-link" href="#">...</a>`;
                ordersPagination.appendChild(li);
            }
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>`;
        ordersPagination.appendChild(nextLi);
        
        // Add event listeners to pagination links
        document.querySelectorAll('#ordersPagination .page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.getAttribute('data-page'));
                if (page && page !== currentPage) {
                    currentPage = page;
                    loadOrders();
                }
            });
        });
    }
    
    // View order details
    async function viewOrderDetails(orderId) {
        showLoading(true);
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/orders/${orderId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const order = await response.json();
            
            if (order) {
                renderOrderDetails(order, orderId); // Pass orderId as second parameter
                orderDetailModal.show();
            } else {
                showMessage('Order not found.', 'danger');
            }
        } catch (error) {
            console.error('Error loading order details:', error);
            showMessage('Network error. Please try again.', 'danger');
        } finally {
            showLoading(false);
        }
    }
    
    // Render order details in modal
    function renderOrderDetails(order, orderId) { // Add orderId parameter
        const modalBody = document.getElementById('orderDetailModalBody');
        
        // Format status badge
        let statusBadge = '';
        switch(order.status) {
            case 'pending':
                statusBadge = '<span class="badge bg-warning">Pending</span>';
                break;
            case 'processing':
                statusBadge = '<span class="badge bg-info">Processing</span>';
                break;
            case 'shipped':
                statusBadge = '<span class="badge bg-primary">Shipped</span>';
                break;
            case 'delivered':
                statusBadge = '<span class="badge bg-success">Delivered</span>';
                break;
            case 'cancelled':
                statusBadge = '<span class="badge bg-danger">Cancelled</span>';
                break;
            case 'refunded':
                statusBadge = '<span class="badge bg-secondary">Refunded</span>';
                break;
            default:
                statusBadge = `<span class="badge bg-secondary">${order.status}</span>`;
        }
        
        // Format payment status badge
        let paymentStatusBadge = '';
        switch(order.payment_status) {
            case 'pending':
                paymentStatusBadge = '<span class="badge bg-warning">Pending</span>';
                break;
            case 'paid':
                paymentStatusBadge = '<span class="badge bg-success">Paid</span>';
                break;
            case 'failed':
                paymentStatusBadge = '<span class="badge bg-danger">Failed</span>';
                break;
            case 'refunded':
                paymentStatusBadge = '<span class="badge bg-secondary">Refunded</span>';
                break;
            default:
                paymentStatusBadge = `<span class="badge bg-secondary">${order.payment_status}</span>`;
        }
        
        // Format date
        const orderDate = new Date(order.created_at).toLocaleString();
        
        // Build order items HTML
        let orderItemsHtml = '';
        if (order.order_items && order.order_items.length > 0) {
            orderItemsHtml = `
                <h6 class="mt-4">Order Items</h6>
                <div class="table-responsive">
                    <table class="table table-dark table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            order.order_items.forEach(item => {
                orderItemsHtml += `
                    <tr>
                        <td>${Admin.escapeHtml(item.product_title)}</td>
                        <td>${item.quantity}</td>
                        <td>$${parseFloat(item.item_price_at_purchase).toFixed(2)}</td>
                        <td>$${parseFloat(item.item_total_at_purchase).toFixed(2)}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary assign-key-btn" data-item-id="${item.order_item_id}" data-order-id="${orderId}" title="Assign Key">
                                <i class="fas fa-key"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            orderItemsHtml += `
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        // Build addresses HTML
        let addressesHtml = '';
        if (order.billing_address || order.shipping_address) {
            addressesHtml = '<h6 class="mt-4">Addresses</h6><div class="row">';
            
            if (order.billing_address) {
                const billing = typeof order.billing_address === 'string' 
                    ? JSON.parse(order.billing_address) 
                    : order.billing_address;
                
                addressesHtml += `
                    <div class="col-md-6">
                        <h6>Billing Address</h6>
                        <address>
                            ${Admin.escapeHtml(billing.first_name || '')} ${Admin.escapeHtml(billing.last_name || '')}<br>
                            ${Admin.escapeHtml(billing.address || '')}<br>
                            ${Admin.escapeHtml(billing.city || '')}, ${Admin.escapeHtml(billing.state || '')} ${Admin.escapeHtml(billing.zip || '')}<br>
                            ${Admin.escapeHtml(billing.country || '')}<br>
                            Phone: ${Admin.escapeHtml(billing.phone || '')}
                        </address>
                    </div>
                `;
            }
            
            if (order.shipping_address) {
                const shipping = typeof order.shipping_address === 'string' 
                    ? JSON.parse(order.shipping_address) 
                    : order.shipping_address;
                
                addressesHtml += `
                    <div class="col-md-6">
                        <h6>Shipping Address</h6>
                        <address>
                            ${Admin.escapeHtml(shipping.first_name || '')} ${Admin.escapeHtml(shipping.last_name || '')}<br>
                            ${Admin.escapeHtml(shipping.address || '')}<br>
                            ${Admin.escapeHtml(shipping.city || '')}, ${Admin.escapeHtml(shipping.state || '')} ${Admin.escapeHtml(shipping.zip || '')}<br>
                            ${Admin.escapeHtml(shipping.country || '')}<br>
                            Phone: ${Admin.escapeHtml(shipping.phone || '')}
                        </address>
                    </div>
                `;
            }
            
            addressesHtml += '</div>';
        }
        
        modalBody.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h5>Order #${order.order_number || order.id}</h5>
                    <p><strong>Date:</strong> ${orderDate}</p>
                    <p><strong>Status:</strong> ${statusBadge}</p>
                    <p><strong>Payment Status:</strong> ${paymentStatusBadge}</p>
                    <p><strong>Payment Method:</strong> ${Admin.escapeHtml(order.payment_method)}</p>
                    <p><strong>Transaction ID:</strong> ${Admin.escapeHtml(order.payment_transaction_id || 'N/A')}</p>
                </div>
                <div class="col-md-6">
                    <h5>Customer Information</h5>
                    <p><strong>Name:</strong> ${Admin.escapeHtml(order.first_name || '')} ${Admin.escapeHtml(order.last_name || '')}</p>
                    <p><strong>Email:</strong> ${Admin.escapeHtml(order.email)}</p>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-12">
                    <h5>Order Summary</h5>
                    <table class="table table-dark">
                        <tr>
                            <td>Subtotal:</td>
                            <td class="text-end">$${parseFloat(order.subtotal).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-end">-$${parseFloat(order.discount_amount || 0).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td>Tax:</td>
                            <td class="text-end">$${parseFloat(order.tax_amount || 0).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td>Shipping:</td>
                            <td class="text-end">$${parseFloat(order.shipping_amount || 0).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <th>Total:</th>
                            <th class="text-end">$${parseFloat(order.total_amount).toFixed(2)}</th>
                        </tr>
                    </table>
                </div>
            </div>
            
            ${orderItemsHtml}
            ${addressesHtml}
            
            <div class="mt-4">
                <h6>Order Notes</h6>
                <p>${Admin.escapeHtml(order.notes || 'No notes available.')}</p>
            </div>
            
            <div class="mt-4">
                <h6>Status History</h6>
                <div id="statusHistoryContainer">
                    <div class="text-center">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Load status history
        loadStatusHistory(orderId);
        
        // Add event listener to assign key buttons
        document.querySelectorAll('.assign-key-btn').forEach(button => {
            button.addEventListener('click', function() {
                const orderItemId = parseInt(this.getAttribute('data-item-id'));
                const orderId = parseInt(this.getAttribute('data-order-id'));
                openAssignKeyModal(orderItemId, orderId);
            });
        });
    }
    
    // Load status history
    async function loadStatusHistory(orderId) {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/orders/${orderId}/status-history`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const history = await response.json();
            
            const container = document.getElementById('statusHistoryContainer');
            
            if (history && history.length > 0) {
                let historyHtml = '<ul class="list-group">';
                
                history.forEach(entry => {
                    const entryDate = new Date(entry.created_at).toLocaleString();
                    const username = entry.username ? `by ${Admin.escapeHtml(entry.username)}` : 'by System';
                    
                    historyHtml += `
                        <li class="list-group-item bg-dark text-light">
                            <div class="d-flex justify-content-between">
                                <span><strong>${Admin.escapeHtml(entry.status)}</strong> ${username}</span>
                                <small>${entryDate}</small>
                            </div>
                            ${entry.comment ? `<div class="mt-1">${Admin.escapeHtml(entry.comment)}</div>` : ''}
                        </li>
                    `;
                });
                
                historyHtml += '</ul>';
                container.innerHTML = historyHtml;
            } else {
                container.innerHTML = '<p class="text-muted">No status history available.</p>';
            }
        } catch (error) {
            console.error('Error loading status history:', error);
            document.getElementById('statusHistoryContainer').innerHTML = '<p class="text-danger">Failed to load status history.</p>';
        }
    }
    
    // Open status change modal
    function openStatusChangeModal(orderId) {
        document.getElementById('statusOrderId').value = orderId;
        document.getElementById('newStatus').value = '';
        document.getElementById('statusComment').value = '';
        statusChangeModal.show();
    }
    
    // Open payment status change modal
    function openPaymentStatusChangeModal(orderId) {
        document.getElementById('paymentStatusOrderId').value = orderId;
        document.getElementById('newPaymentStatus').value = '';
        paymentStatusChangeModal.show();
    }
    
    // Open assign key modal
    function openAssignKeyModal(orderItemId, orderId) { // Add orderId parameter
        document.getElementById('assignKeyOrderItemId').value = orderItemId;
        document.getElementById('assignKeyOrderId').value = orderId; // Store orderId in hidden field
        document.getElementById('productKey').value = '';
        assignKeyModal.show();
    }
    
    // Save status change
    async function saveStatusChange() {
        const orderId = document.getElementById('statusOrderId').value;
        const newStatus = document.getElementById('newStatus').value;
        const comment = document.getElementById('statusComment').value;
        
        if (!newStatus) {
            Admin.showAlert('Please select a status.', 'warning');
            return;
        }
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/orders/${orderId}/status`, {
                method: 'PUT',
                body: {
                    status: newStatus,
                    comment: comment
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.message) {
                Admin.showAlert(data.message, 'success');
                statusChangeModal.hide();
                loadOrders(); // Refresh orders list
                
                // If order detail modal is open, refresh it
                if (!document.getElementById('orderDetailModal').classList.contains('hidden')) {
                    viewOrderDetails(orderId);
                }
            } else {
                Admin.showAlert('Failed to update order status.', 'danger');
            }
        } catch (error) {
            console.error('Error updating order status:', error);
            Admin.showAlert('Network error. Please try again.', 'danger');
        }
    }
    
    // Save payment status change
    async function savePaymentStatusChange() {
        const orderId = document.getElementById('paymentStatusOrderId').value;
        const newPaymentStatus = document.getElementById('newPaymentStatus').value;
        
        if (!newPaymentStatus) {
            Admin.showAlert('Please select a payment status.', 'warning');
            return;
        }
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/orders/${orderId}/payment-status`, {
                method: 'PUT',
                body: {
                    payment_status: newPaymentStatus
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.message) {
                Admin.showAlert(data.message, 'success');
                paymentStatusChangeModal.hide();
                loadOrders(); // Refresh orders list
                
                // If order detail modal is open, refresh it
                if (!document.getElementById('orderDetailModal').classList.contains('hidden')) {
                    viewOrderDetails(orderId);
                }
            } else {
                Admin.showAlert('Failed to update payment status.', 'danger');
            }
        } catch (error) {
            console.error('Error updating payment status:', error);
            Admin.showAlert('Network error. Please try again.', 'danger');
        }
    }
    
    // Assign product key
    async function assignProductKey() {
        const orderItemId = document.getElementById('assignKeyOrderItemId').value;
        const orderId = document.getElementById('assignKeyOrderId').value; // Get orderId from hidden field
        const productKey = document.getElementById('productKey').value.trim();
        
        if (!productKey) {
            Admin.showAlert('Please enter a product key.', 'warning');
            return;
        }
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/orders/item/${orderItemId}/key`, {
                method: 'POST',
                body: {
                    key: productKey
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.message) {
                Admin.showAlert(data.message, 'success');
                assignKeyModal.hide();
                
                // If order detail modal is open, refresh it
                if (orderId && !document.getElementById('orderDetailModal').classList.contains('hidden')) {
                    viewOrderDetails(orderId);
                }
            } else {
                Admin.showAlert('Failed to assign product key.', 'danger');
            }
        } catch (error) {
            console.error('Error assigning product key:', error);
            Admin.showAlert('Network error. Please try again.', 'danger');
        }
    }
    
    // Show/hide loading spinner
    function showLoading(show) {
        if (show) {
            loadingSpinner.style.display = 'block';
            ordersTableCard.style.display = 'none';
        } else {
            loadingSpinner.style.display = 'none';
        }
    }
    
    // Show message
    function showMessage(message, type) {
        messageDiv.innerHTML = message;
        messageDiv.className = `alert alert-${type}`;
        messageDiv.classList.remove('d-none');
    }
    
    // Hide message
    function hideMessage() {
        messageDiv.classList.add('d-none');
        messageDiv.innerHTML = '';
    }
    
    // Initialize the page
    init();
});