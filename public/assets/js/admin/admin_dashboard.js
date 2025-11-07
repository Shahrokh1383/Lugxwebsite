/**
 * public/assets/js/admin/admin_dashboard.js
 *
 * This file handles fetching and displaying data for the admin dashboard.
 * It interacts with the AdminDashboardController backend to get statistics.
 */

document.addEventListener('DOMContentLoaded', function() {
    const loadingSpinner = document.getElementById('loadingSpinner');
    const statsCards = document.getElementById('statsCards');
    const totalUsersElement = document.getElementById('totalUsers');
    const totalProductsElement = document.getElementById('totalProducts');
    const totalOrdersElement = document.getElementById('totalOrders');
    const unreadMessagesCountElement = document.getElementById('unreadMessagesCount');
    const recentOrdersTableBody = document.getElementById('recentOrdersTableBody');
    const recentUsersTableBody = document.getElementById('recentUsersTableBody');

    // Get the base URL path from the global variable injected by PHP (e.g., /Lugxwebsite/public)
    const baseUrlPath = window.AppBaseUrlPath || '';

    /**
     * Fetches dashboard statistics from the API and updates the HTML.
     */
    async function loadDashboardStats() {
        // Show loading spinner and hide stats cards initially
        if (loadingSpinner) loadingSpinner.style.display = 'block';
        if (statsCards) statsCards.style.display = 'none';

        try {
            const response = await fetch(`${baseUrlPath}/api/admin/dashboard/stats`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText}`);
            }

            const data = await response.json();

            if (data.success) {
                const stats = data.data;
                console.log('Dashboard stats fetched:', stats);

                // Populate stat cards
                if (totalUsersElement) totalUsersElement.textContent = stats.totalUsers;
                if (totalProductsElement) totalProductsElement.textContent = stats.totalProducts;
                if (totalOrdersElement) totalOrdersElement.textContent = stats.totalOrders;
                if (unreadMessagesCountElement) unreadMessagesCountElement.textContent = stats.unreadMessagesCount;

                // Populate Recent Orders table
                if (recentOrdersTableBody) {
                    recentOrdersTableBody.innerHTML = '';
                    if (stats.recentOrders && stats.recentOrders.length > 0) {
                        stats.recentOrders.forEach(order => {
                            const row = `
                                <tr>
                                    <td>${Admin.escapeHtml(order.order_number || order.id)}</td>
                                    <td>${order.user_id ? `User ${order.user_id}` : 'Guest'}</td>
                                    <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
                                    <td><span class="badge bg-primary">${Admin.escapeHtml(order.status)}</span></td>
                                    <td>${new Date(order.created_at).toLocaleDateString()}</td>
                                </tr>
                            `;
                            recentOrdersTableBody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        recentOrdersTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No recent orders found.</td></tr>';
                    }
                }

                // Populate Recent Users table
                if (recentUsersTableBody) {
                    recentUsersTableBody.innerHTML = '';
                    if (stats.recentUsers && stats.recentUsers.length > 0) {
                        stats.recentUsers.forEach(user => {
                            const row = `
                                <tr>
                                    <td>${user.id}</td>
                                    <td>${Admin.escapeHtml(user.first_name)} ${Admin.escapeHtml(user.last_name)}</td>
                                    <td>${Admin.escapeHtml(user.email)}</td>
                                    <td>${new Date(user.created_at).toLocaleDateString()}</td>
                                </tr>
                            `;
                            recentUsersTableBody.insertAdjacentHTML('beforeend', row);
                        });
                    } else {
                        recentUsersTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No recent users found.</td></tr>';
                    }
                }

                // Hide loading spinner and show stats cards
                if (loadingSpinner) loadingSpinner.style.display = 'none';
                if (statsCards) statsCards.style.display = 'flex';
            } else {
                // Display error message using Admin.showAlert from admin_main.js
                Admin.showAlert(data.message || 'Failed to load dashboard statistics.', 'danger', 'message');
                if (loadingSpinner) loadingSpinner.style.display = 'none';
            }
        } catch (error) {
            console.error('Network or unexpected error fetching dashboard stats:', error);
            Admin.showAlert('Network error. Could not load dashboard statistics.', 'danger', 'message');
            if (loadingSpinner) loadingSpinner.style.display = 'none';
        }
    }

    // Call the function to load dashboard stats when the page loads
    loadDashboardStats();
});