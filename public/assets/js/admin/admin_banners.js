// public/assets/js/admin/admin_banners.js
// This script handles the AJAX operations for managing banners.

document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Elements ---
    const bannersTableBody = document.getElementById('bannersTableBody');
    const bannersTableCard = document.getElementById('bannersTableCard');
    const addNewBannerBtn = document.getElementById('addNewBannerBtn');
    const bannerModal = new bootstrap.Modal(document.getElementById('bannerModal'));
    const bannerModalLabel = document.getElementById('bannerModalLabel');
    const bannerForm = document.getElementById('bannerForm');
    const bannerIdInput = document.getElementById('bannerId');
    const bannerTitleInput = document.getElementById('bannerTitle');
    const bannerImageInput = document.getElementById('bannerImage');
    const bannerLinkInput = document.getElementById('bannerLink');
    const bannerStatusSelect = document.getElementById('bannerStatus');

    // Assume these functions are available globally from admin_main.js
    // API_BASE_URL, showMessage, showLoading, hideLoading are assumed
    
    // --- Initial Data Fetch ---
    const fetchBanners = async () => {
        showLoading();
        try {
            const response = await fetch(`${API_BASE_URL}/api/admin/banners`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            if (response.ok && result.success) {
                renderBannersTable(result.data);
                bannersTableCard.style.display = 'block';
            } else {
                showMessage(result.message || 'Failed to fetch banners.', 'danger');
                bannersTableCard.style.display = 'none';
            }
        } catch (error) {
            console.error('Error fetching banners:', error);
            showMessage('Connection error while fetching banners.', 'danger');
        } finally {
            hideLoading();
        }
    };

    // --- Render Table ---
    const renderBannersTable = (banners) => {
        bannersTableBody.innerHTML = '';
        if (banners.length === 0) {
            bannersTableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No banners found.</td></tr>`;
            return;
        }

        banners.forEach(banner => {
            const row = document.createElement('tr');
            const statusBadge = banner.status === 'active' ? `<span class="badge bg-success">Active</span>` : `<span class="badge bg-danger">Inactive</span>`;
            
            row.innerHTML = `
                <td>${banner.id}</td>
                <td>${banner.title}</td>
                <td><img src="${AppBaseUrlPath}/assets/images/banners/${banner.image}" alt="${banner.title}" class="img-thumbnail" style="width: 100px;"></td>
                <td><a href="${banner.link}" target="_blank">${banner.link}</a></td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-warning btn-sm me-2 edit-btn" data-id="${banner.id}">
                        <i class="fa-solid fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-danger btn-sm delete-btn" data-id="${banner.id}">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </td>
            `;
            bannersTableBody.appendChild(row);
        });
    };

    // --- Form Submission Handler (Add/Edit) ---
    bannerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const bannerId = bannerIdInput.value;
        const isEditMode = !!bannerId;

        const formData = new FormData(bannerForm);
        
        let url = isEditMode ? `${API_BASE_URL}/api/admin/banners/${bannerId}` : `${API_BASE_URL}/api/admin/banners`;
        let method = 'POST';
        
        if (isEditMode) {
            formData.append('_method', 'POST'); // We use POST with a method override for file uploads
        }

        showLoading();
        bannerModal.hide();

        try {
            const response = await fetch(url, {
                method: method,
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
            });
            const result = await response.json();

            if (response.ok && result.success) {
                showMessage(result.message || (isEditMode ? 'Banner updated successfully.' : 'Banner added successfully.'), 'success');
                fetchBanners(); // Refresh the table
            } else {
                showMessage(result.message || 'Error saving banner.', 'danger');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showMessage('Connection error while saving banner.', 'danger');
        } finally {
            hideLoading();
        }
    });

    // --- Event Listeners for Buttons ---
    addNewBannerBtn.addEventListener('click', () => {
        bannerModalLabel.textContent = 'Add New Banner';
        bannerForm.reset();
        bannerIdInput.value = '';
    });

    bannersTableBody.addEventListener('click', async (e) => {
        // Handle Edit button click
        if (e.target.closest('.edit-btn')) {
            const button = e.target.closest('.edit-btn');
            const bannerId = button.dataset.id;
            
            showLoading();
            try {
                const response = await fetch(`${API_BASE_URL}/api/admin/banners/${bannerId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    const banner = result.data;
                    bannerIdInput.value = banner.id;
                    bannerTitleInput.value = banner.title;
                    bannerLinkInput.value = banner.link;
                    bannerStatusSelect.value = banner.status;
                    
                    // We don't set the image input value for security reasons
                    // and allow the user to choose a new file if needed.
                    
                    bannerModalLabel.textContent = 'Edit Banner';
                    bannerModal.show();
                } else {
                    showMessage(result.message || 'Failed to load banner data.', 'danger');
                }
            } catch (error) {
                console.error('Error fetching banner for edit:', error);
                showMessage('Connection error.', 'danger');
            } finally {
                hideLoading();
            }
        }

        // Handle Delete button click
        if (e.target.closest('.delete-btn')) {
            const button = e.target.closest('.delete-btn');
            const bannerId = button.dataset.id;
            if (confirm('Are you sure you want to delete this banner?')) {
                showLoading();
                try {
                    const response = await fetch(`${API_BASE_URL}/api/admin/banners/${bannerId}`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: '_method=DELETE'
                    });
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        showMessage(result.message || 'Banner deleted successfully.', 'success');
                        fetchBanners(); // Refresh the table
                    } else {
                        showMessage(result.message || 'Error deleting banner.', 'danger');
                    }
                } catch (error) {
                    console.error('Deletion error:', error);
                    showMessage('Connection error while deleting banner.', 'danger');
                } finally {
                    hideLoading();
                }
            }
        }
    });

    // --- Initial Call ---
    fetchBanners();
});
