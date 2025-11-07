// Ensure main.js, auth.js, and user_orders.js are loaded before this script.

document.addEventListener('DOMContentLoaded', () => {
    const userFullNameElement = window.select('#dashboard-user-fullname');
    const userEmailElement = window.select('#dashboard-user-email');
    const lastLoginElement = window.select('#dashboard-last-login-value');
    const totalOrdersElement = window.select('#dashboard-total-orders'); // New element for total orders
    const messageContainer = window.select('#message-container');

    async function loadDashboardSummary() {
        // Set initial loading states
        if (userFullNameElement) userFullNameElement.textContent = 'Loading...';
        if (userEmailElement) userEmailElement.textContent = 'Loading...';
        if (lastLoginElement) lastLoginElement.textContent = 'Loading...';
        if (totalOrdersElement) totalOrdersElement.textContent = 'Loading...'; // Set loading for total orders

        window.clearErrors(messageContainer.id); // Clear any previous errors
        messageContainer.innerHTML = ''; // Clear previous messages

        try {
            // Fetch user profile data
            const profileResponse = await fetch(`${window.API_BASE_URL}/user/profile`);
            const profileResult = await window.handleApiResponse(profileResponse); // Use global handleApiResponse

            if (profileResult.status === 'success' && profileResult.user) {
                const user = profileResult.user;
                
                // Display Full Name
                if (userFullNameElement) {
                    const fullName = (user.first_name || '') + ' ' + (user.last_name || '');
                    userFullNameElement.textContent = fullName.trim() || user.email;
                }
                
                // Display Email
                if (userEmailElement) {
                    userEmailElement.textContent = user.email || 'N/A';
                }

                // Display Last Login
                if (lastLoginElement) {
                    lastLoginElement.textContent = user.last_login ? new Date(user.last_login).toLocaleString() : 'N/A';
                }

                // Fetch and display Total Orders count
                if (totalOrdersElement && window.UserOrders && typeof window.UserOrders.getTotalOrdersCount === 'function') {
                    const totalOrders = await window.UserOrders.getTotalOrdersCount();
                    totalOrdersElement.textContent = totalOrders.toString();
                } else {
                    console.warn('UserOrders.getTotalOrdersCount function not available. Cannot display total orders.');
                    if (totalOrdersElement) totalOrdersElement.textContent = 'N/A';
                }

                window.displayMessage('Dashboard data loaded.', 'success', messageContainer.id); 
            } else {
                window.displayMessage(profileResult.message || 'Failed to load dashboard summary.', 'danger', messageContainer.id);
                if (userFullNameElement) userFullNameElement.textContent = 'N/A';
                if (userEmailElement) userEmailElement.textContent = 'N/A';
                if (lastLoginElement) lastLoginElement.textContent = 'N/A';
                if (totalOrdersElement) totalOrdersElement.textContent = 'N/A';
            }
        } catch (error) {
            console.error('Error loading dashboard summary:', error);
            window.displayMessage('An unexpected error occurred while loading dashboard summary.', 'danger', messageContainer.id);
            if (userFullNameElement) userFullNameElement.textContent = 'Error';
            if (userEmailElement) userEmailElement.textContent = 'Error';
            if (lastLoginElement) lastLoginElement.textContent = 'Error';
            if (totalOrdersElement) totalOrdersElement.textContent = 'Error';
        }
    }

    // Call the function to load summary data when the page loads
    // Ensure all required main.js functions and UserOrders object are available
    if (typeof window.select === 'function' && typeof window.displayMessage === 'function' && 
        typeof window.clearErrors === 'function' && typeof window.handleApiResponse === 'function' &&
        typeof window.Auth !== 'undefined' && typeof window.UserOrders !== 'undefined') {
        loadDashboardSummary();
    } else {
        console.error('Required global functions or objects (main.js, auth.js, user_orders.js) are not available. Ensure scripts are loaded in correct order.');
    }
});
