// public/assets/js/admin/admin_categories.js
// This script handles the AJAX operations for managing categories.
document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Elements ---
    const categoriesTableBody = document.getElementById('categoriesTableBody');
    const categoriesTableCard = document.getElementById('categoriesTableCard');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const messageDiv = document.getElementById('message');
    const addNewCategoryBtn = document.getElementById('addNewCategoryBtn');
    const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    const categoryModalLabel = document.getElementById('categoryModalLabel');
    const categoryForm = document.getElementById('categoryForm');
    const categoryIdInput = document.getElementById('categoryId');
    const categoryNameInput = document.getElementById('categoryName');
    const categorySlugInput = document.getElementById('categorySlug');
    const parentCategorySelect = document.getElementById('parentCategory');
    const currentImageContainer = document.getElementById('currentImageContainer');
    const currentImage = document.getElementById('currentImage');
    const removeImageButton = document.getElementById('removeImage');
    
    // Base URL from global variable
    const baseUrlPath = window.AppBaseUrlPath || '';
    const API_BASE_URL = baseUrlPath;
    
    // --- Utility Functions ---
    function showLoading() {
        loadingSpinner.classList.remove('d-none');
    }
    
    function hideLoading() {
        loadingSpinner.classList.add('d-none');
    }
    
    function showMessage(message, type) {
        Admin.showAlert(message, type);
    }
    
    function clearFormErrors() {
        Admin.clearFormErrors(categoryForm);
    }
    
    // --- Initial Data Fetch ---
    const fetchCategories = async () => {
        showLoading();
        try {
            const response = await Admin.fetchWithCsrf(`${API_BASE_URL}/api/admin/categories`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `Failed to fetch categories (Status: ${response.status})`);
            }
            
            const result = await response.json();
            if (result.success) {
                renderCategoriesTable(result.data);
                categoriesTableCard.style.display = 'block';
            } else {
                throw new Error(result.message || 'Failed to fetch categories.');
            }
        } catch (error) {
            console.error('Error fetching categories:', error);
            showMessage(error.message || 'Connection error while fetching categories.', 'danger');
            categoriesTableCard.style.display = 'none';
        } finally {
            hideLoading();
        }
    };
    
    // --- Fetch Parent Categories ---
    const fetchParentCategories = async () => {
        try {
            const response = await Admin.fetchWithCsrf(`${API_BASE_URL}/api/admin/categories/top-level`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`Failed to fetch parent categories (Status: ${response.status})`);
            }
            
            const result = await response.json();
            if (result.success) {
                populateParentCategories(result.data);
            }
        } catch (error) {
            console.error('Error fetching parent categories:', error);
        }
    };
    
    // --- Populate Parent Categories Dropdown ---
    const populateParentCategories = (categories) => {
        // Clear existing options except the first one
        while (parentCategorySelect.options.length > 1) {
            parentCategorySelect.remove(1);
        }
        
        // Add categories
        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            parentCategorySelect.appendChild(option);
        });
    };
    
    // --- Render Table ---
    const renderCategoriesTable = (categories) => {
        categoriesTableBody.innerHTML = '';
        if (categories.length === 0) {
            categoriesTableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No categories found.</td></tr>`;
            return;
        }
        
        categories.forEach(category => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${category.id}</td>
                <td>
                    ${category.image ? 
                        `<img src="${baseUrlPath}/${category.image}" class="img-thumbnail me-2" style="width: 30px; height: 30px; object-fit: cover;">` : 
                        '<i class="fa-solid fa-image text-muted me-2"></i>'
                    }
                    ${Admin.escapeHtml(category.name)}
                </td>
                <td>${Admin.escapeHtml(category.slug)}</td>
                <td>${category.parent_id ? Admin.escapeHtml(category.parent_name || 'Parent Category') : 'None'}</td>
                <td>
                    <span class="badge ${category.is_active ? 'bg-success' : 'bg-danger'}">
                        ${category.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-warning btn-sm me-2 edit-btn" data-id="${category.id}">
                        <i class="fa-solid fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-danger btn-sm delete-btn" data-id="${category.id}">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </td>
            `;
            categoriesTableBody.appendChild(row);
        });
    };
    
    // --- Form Submission Handler (Add/Edit) ---
    categoryForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearFormErrors();
        
        const categoryId = categoryIdInput.value;
        const isEditMode = !!categoryId;
        
        showLoading();
        
        try {
            // Prepare form data
            const formData = new FormData(categoryForm);
            
            // Convert checkbox values to boolean
            const isActiveCheckbox = document.getElementById('isActive');
            formData.set('is_active', isActiveCheckbox.checked ? '1' : '0');
            
            // If editing and image is being removed
            if (isEditMode && currentImageContainer.classList.contains('d-none') === false && 
                !categoryForm.querySelector('input[name="image"]')?.files?.length) {
                formData.append('remove_image', '1');
            }
            
            // API call
            const url = isEditMode ? 
                `${API_BASE_URL}/api/admin/categories/${categoryId}` : 
                `${API_BASE_URL}/api/admin/categories`;
                
            // Use POST method for both create and update
            // This is a workaround for issues with PUT and multipart/form-data
            const method = isEditMode ? 'POST' : 'POST';
            
            // If updating, add a hidden field to indicate this is an update
            if (isEditMode) {
                formData.append('_method', 'PUT');
            }
            
            const response = await Admin.fetchWithCsrf(url, {
                method: method,
                body: formData
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                if (errorData.errors) {
                    // Handle validation errors
                    Object.entries(errorData.errors).forEach(([field, message]) => {
                        Admin.showInputError(field, message);
                    });
                    throw new Error('Please fix the validation errors.');
                }
                throw new Error(errorData.message || `Request failed with status ${response.status}`);
            }
            
            const result = await response.json();
            if (result.success) {
                showMessage(result.message || (isEditMode ? 'Category updated successfully.' : 'Category added successfully.'), 'success');
                categoryModal.hide();
                fetchCategories();
            } else {
                throw new Error(result.message || 'Error saving category.');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showMessage(error.message || 'Connection error while saving category.', 'danger');
        } finally {
            hideLoading();
        }
    });
    
    // --- Remove Image Handler ---
    removeImageButton.addEventListener('click', () => {
        currentImageContainer.classList.add('d-none');
        currentImage.src = '';
    });
    
    // --- Event Listeners for Buttons ---
    addNewCategoryBtn.addEventListener('click', async () => {
        categoryModalLabel.textContent = 'Add New Category';
        categoryForm.reset();
        categoryIdInput.value = '';
        currentImageContainer.classList.add('d-none');
        
        // Clear validation errors
        clearFormErrors();
        
        // Fetch parent categories
        await fetchParentCategories();
    });
    
    categoriesTableBody.addEventListener('click', async (e) => {
        // Handle Edit button click
        if (e.target.closest('.edit-btn')) {
            const button = e.target.closest('.edit-btn');
            const categoryId = button.dataset.id;
            showLoading();
            
            try {
                // Fetch category data
                const response = await Admin.fetchWithCsrf(`${API_BASE_URL}/api/admin/categories/${categoryId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`Failed to fetch category (Status: ${response.status})`);
                }
                
                const result = await response.json();
                if (result.success) {
                    const category = result.data;
                    
                    // Set form values
                    categoryIdInput.value = category.id;
                    categoryNameInput.value = category.name;
                    categorySlugInput.value = category.slug;
                    parentCategorySelect.value = category.parent_id || '';
                    document.getElementById('sortOrder').value = category.sort_order || 0;
                    document.getElementById('isActive').checked = category.is_active;
                    document.getElementById('categoryDescription').value = category.description || '';
                    document.getElementById('metaTitle').value = category.meta_title || '';
                    document.getElementById('metaDescription').value = category.meta_description || '';
                    
                    // Handle image
                    if (category.image) {
                        currentImage.src = `${baseUrlPath}/${category.image}`;
                        currentImageContainer.classList.remove('d-none');
                    } else {
                        currentImageContainer.classList.add('d-none');
                    }
                    
                    categoryModalLabel.textContent = 'Edit Category';
                    
                    // Fetch parent categories
                    await fetchParentCategories();
                    
                    categoryModal.show();
                } else {
                    throw new Error(result.message || 'Failed to load category data.');
                }
            } catch (error) {
                console.error('Error fetching category for edit:', error);
                showMessage(error.message || 'Connection error.', 'danger');
            } finally {
                hideLoading();
            }
        }
        
        // Handle Delete button click
        if (e.target.closest('.delete-btn')) {
            const button = e.target.closest('.delete-btn');
            const categoryId = button.dataset.id;
            const categoryName = button.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
            
            if (confirm(`Are you sure you want to delete the category "${categoryName}"?`)) {
                showLoading();
                try {
                    const response = await Admin.fetchWithCsrf(`${API_BASE_URL}/api/admin/categories/${categoryId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });
                    
                    if (!response.ok) {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.error || `Failed to delete category (Status: ${response.status})`);
                    }
                    
                    const result = await response.json();
                    if (result.success) {
                        showMessage(result.message || 'Category deleted successfully.', 'success');
                        fetchCategories();
                    } else {
                        throw new Error(result.message || 'Error deleting category.');
                    }
                } catch (error) {
                    console.error('Deletion error:', error);
                    showMessage(error.message || 'Connection error while deleting category.', 'danger');
                } finally {
                    hideLoading();
                }
            }
        }
    });
    
    // --- Initial Call ---
    fetchCategories();
});