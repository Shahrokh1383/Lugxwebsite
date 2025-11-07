// public/assets/js/admin/admin_pages.js
// This script handles the AJAX operations for managing static pages.

document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Elements ---
    const pagesTableBody = document.getElementById('pagesTableBody');
    const pagesTableCard = document.getElementById('pagesTableCard');
    const addNewPageBtn = document.getElementById('addNewPageBtn');
    const pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
    const pageModalLabel = document.getElementById('pageModalLabel');
    const pageForm = document.getElementById('pageForm');
    const pageIdInput = document.getElementById('pageId');
    const pageTitleInput = document.getElementById('pageTitle');
    const pageSlugInput = document.getElementById('pageSlug');
    const pageStatusSelect = document.getElementById('pageStatus');
    
    // Assume these functions are available globally from admin_main.js
    // API_BASE_URL, showMessage, showLoading, hideLoading are assumed
    
    // --- Initial Data Fetch ---
    const fetchPages = async () => {
        showLoading();
        try {
            const response = await fetch(`${API_BASE_URL}/api/admin/pages`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            if (response.ok && result.success) {
                renderPagesTable(result.data);
                pagesTableCard.style.display = 'block';
            } else {
                showMessage(result.message || 'Failed to fetch static pages.', 'danger');
                pagesTableCard.style.display = 'none';
            }
        } catch (error) {
            console.error('Error fetching pages:', error);
            showMessage('Connection error while fetching pages.', 'danger');
        } finally {
            hideLoading();
        }
    };

    // --- Render Table ---
    const renderPagesTable = (pages) => {
        pagesTableBody.innerHTML = '';
        if (pages.length === 0) {
            pagesTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No static pages found.</td></tr>`;
            return;
        }

        pages.forEach(page => {
            const row = document.createElement('tr');
            const statusBadge = page.status === 'published' ? `<span class="badge bg-success">Published</span>` : `<span class="badge bg-warning">Draft</span>`;
            
            row.innerHTML = `
                <td>${page.id}</td>
                <td>${page.title}</td>
                <td>${page.slug}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-warning btn-sm me-2 edit-btn" data-id="${page.id}">
                        <i class="fa-solid fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-danger btn-sm delete-btn" data-id="${page.id}">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </td>
            `;
            pagesTableBody.appendChild(row);
        });
    };

    // --- Form Submission Handler (Add/Edit) ---
    pageForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const pageId = pageIdInput.value;
        const isEditMode = !!pageId;

        const formData = new FormData();
        formData.append('title', pageTitleInput.value);
        formData.append('slug', pageSlugInput.value);
        formData.append('content', tinymce.get('pageContent').getContent());
        formData.append('status', pageStatusSelect.value);

        let url = isEditMode ? `${API_BASE_URL}/api/admin/pages/${pageId}` : `${API_BASE_URL}/api/admin/pages`;
        let method = 'POST';
        
        if (isEditMode) {
            formData.append('_method', 'PUT'); // For editing, we use PUT with a method override
        }

        showLoading();
        pageModal.hide();

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
                showMessage(result.message || (isEditMode ? 'Page updated successfully.' : 'Page added successfully.'), 'success');
                fetchPages(); // Refresh the table
            } else {
                showMessage(result.message || 'Error saving page.', 'danger');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showMessage('Connection error while saving page.', 'danger');
        } finally {
            hideLoading();
        }
    });

    // --- Event Listeners for Buttons ---
    addNewPageBtn.addEventListener('click', () => {
        pageModalLabel.textContent = 'Add New Page';
        pageForm.reset();
        pageIdInput.value = '';
        tinymce.get('pageContent').setContent('');
    });

    pagesTableBody.addEventListener('click', async (e) => {
        // Handle Edit button click
        if (e.target.closest('.edit-btn')) {
            const button = e.target.closest('.edit-btn');
            const pageId = button.dataset.id;
            
            showLoading();
            try {
                const response = await fetch(`${API_BASE_URL}/api/admin/pages/${pageId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    const page = result.data;
                    pageIdInput.value = page.id;
                    pageTitleInput.value = page.title;
                    pageSlugInput.value = page.slug;
                    pageStatusSelect.value = page.status;
                    tinymce.get('pageContent').setContent(page.content);
                    
                    pageModalLabel.textContent = 'Edit Page';
                    pageModal.show();
                } else {
                    showMessage(result.message || 'Failed to load page data.', 'danger');
                }
            } catch (error) {
                console.error('Error fetching page for edit:', error);
                showMessage('Connection error.', 'danger');
            } finally {
                hideLoading();
            }
        }

        // Handle Delete button click
        if (e.target.closest('.delete-btn')) {
            const button = e.target.closest('.delete-btn');
            const pageId = button.dataset.id;
            if (confirm('Are you sure you want to delete this page?')) {
                showLoading();
                try {
                    const response = await fetch(`${API_BASE_URL}/api/admin/pages/${pageId}`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: '_method=DELETE'
                    });
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        showMessage(result.message || 'Page deleted successfully.', 'success');
                        fetchPages(); // Refresh the table
                    } else {
                        showMessage(result.message || 'Error deleting page.', 'danger');
                    }
                } catch (error) {
                    console.error('Deletion error:', error);
                    showMessage('Connection error while deleting page.', 'danger');
                } finally {
                    hideLoading();
                }
            }
        }
    });

    // --- Initial Call ---
    fetchPages();
});
