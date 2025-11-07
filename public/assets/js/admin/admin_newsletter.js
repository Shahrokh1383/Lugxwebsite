// public/assets/js/admin/admin_newsletter.js
// This script handles the AJAX operations for managing newsletter subscribers.

document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Elements ---
    const subscribersTableBody = document.getElementById('subscribersTableBody');
    const subscribersTableCard = document.getElementById('subscribersTableCard');
    const exportSubscribersBtn = document.getElementById('exportSubscribersBtn');
    
    // Assume these functions are available globally from admin_main.js
    // API_BASE_URL, showMessage, showLoading, hideLoading are assumed
    
    // --- Initial Data Fetch ---
    const fetchSubscribers = async () => {
        showLoading();
        try {
            const response = await fetch(`${API_BASE_URL}/api/admin/newsletter`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            if (response.ok && result.success) {
                renderSubscribersTable(result.data);
                subscribersTableCard.style.display = 'block';
            } else {
                showMessage(result.message || 'Failed to fetch subscribers.', 'danger');
                subscribersTableCard.style.display = 'none';
            }
        } catch (error) {
            console.error('Error fetching subscribers:', error);
            showMessage('Connection error while fetching subscribers.', 'danger');
        } finally {
            hideLoading();
        }
    };

    // --- Render Table ---
    const renderSubscribersTable = (subscribers) => {
        subscribersTableBody.innerHTML = '';
        if (subscribers.length === 0) {
            subscribersTableBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No subscribers found.</td></tr>`;
            return;
        }

        subscribers.forEach(subscriber => {
            const row = document.createElement('tr');
            const subscribedDate = new Date(subscriber.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            
            row.innerHTML = `
                <td>${subscriber.id}</td>
                <td>${subscriber.email}</td>
                <td>${subscribedDate}</td>
                <td>
                    <button class="btn btn-danger btn-sm delete-btn" data-id="${subscriber.id}">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </td>
            `;
            subscribersTableBody.appendChild(row);
        });
    };

    // --- Event Listener for Delete Action ---
    subscribersTableBody.addEventListener('click', async (e) => {
        const button = e.target.closest('.delete-btn');
        if (!button) return;

        const subscriberId = button.dataset.id;
        if (confirm('Are you sure you want to delete this subscriber?')) {
            showLoading();
            try {
                const response = await fetch(`${API_BASE_URL}/api/admin/newsletter/${subscriberId}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: '_method=DELETE'
                });
                const result = await response.json();
                
                if (response.ok && result.success) {
                    showMessage(result.message || 'Subscriber deleted successfully.', 'success');
                    fetchSubscribers(); // Refresh the table
                } else {
                    showMessage(result.message || 'Error deleting subscriber.', 'danger');
                }
            } catch (error) {
                console.error('Deletion error:', error);
                showMessage('Connection error while deleting subscriber.', 'danger');
            } finally {
                hideLoading();
            }
        }
    });

    // --- Event Listener for Export Button ---
    exportSubscribersBtn.addEventListener('click', async () => {
        showLoading();
        try {
            const response = await fetch(`${API_BASE_URL}/api/admin/newsletter/export`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'newsletter_subscribers.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                showMessage('Subscribers list exported successfully!', 'success');
            } else {
                const result = await response.json();
                showMessage(result.message || 'Failed to export subscribers.', 'danger');
            }
        } catch (error) {
            console.error('Export error:', error);
            showMessage('Connection error during export.', 'danger');
        } finally {
            hideLoading();
        }
    });

    // --- Initial Call ---
    fetchSubscribers();
});
