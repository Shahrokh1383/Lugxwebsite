/**
 * public/assets/js/admin/admin_users.js
 *
 * This file handles the client-side logic for user management in the admin panel.
 * It includes fetching, displaying, adding, editing, and deleting user data via AJAX.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get references to DOM elements
    const userLoadingSpinner = document.getElementById('userLoadingSpinner');
    const userListSection = document.getElementById('userListSection');
    const usersTableBody = document.getElementById('usersTableBody');
    const paginationControls = document.getElementById('paginationControls');
    const userSearchInput = document.getElementById('userSearchInput');
    const searchUsersBtn = document.getElementById('searchUsersBtn');
    const addNewUserBtn = document.getElementById('addNewUserBtn');
    const userModal = new bootstrap.Modal(document.getElementById('userModal')); // Bootstrap Modal instance
    const userModalLabel = document.getElementById('userModalLabel');
    const userForm = document.getElementById('userForm');
    const saveUserBtn = document.getElementById('saveUserBtn');
    const modalMessageDiv = document.getElementById('modalMessage');
    const userFormCsrfToken = document.getElementById('userFormCsrfToken');
    const userIdInput = document.getElementById('userId');
    const roleIdSelect = document.getElementById('roleId');
    let currentPage = 1;
    let currentSearchTerm = '';
    // Get the base URL path from the global variable injected by PHP (e.g., /Lugxwebsite)
    const baseUrlPath = window.AppBaseUrlPath || ''; 
    /**
     * Clears all form fields and validation feedback in the user modal.
     */
    function clearUserForm() {
        userForm.reset(); // Resets all form fields
        userIdInput.value = ''; // Clear hidden user ID
        // Clear invalid-feedback and is-invalid classes
        userForm.querySelectorAll('.form-control, .form-select').forEach(input => {
            input.classList.remove('is-invalid');
        });
        userForm.querySelectorAll('.invalid-feedback').forEach(feedback => {
            feedback.textContent = '';
        });
        modalMessageDiv.classList.add('d-none');
        modalMessageDiv.innerHTML = '';
        document.getElementById('password').setAttribute('placeholder', 'Leave blank to keep current password');
    }
    /**
     * Populates the roles dropdown in the user modal.
     */
    async function loadRoles() {
        try {
            // API call to roles endpoint, manually adding '/public'
            const response = await fetch(`${baseUrlPath}/public/api/admin/roles`, { // مسیر بروزرسانی شده
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (response.ok && data.success) {
                roleIdSelect.innerHTML = ''; // Clear existing options
                data.data.forEach(role => {
                    const option = document.createElement('option');
                    option.value = role.id;
                    option.textContent = role.name;
                    roleIdSelect.appendChild(option);
                });
            } else {
                Admin.showAlert(data.message || 'Failed to load user roles.', 'danger', 'userManagementMessage');
            }
        } catch (error) {
            console.error('Network or unexpected error loading roles:', error);
            Admin.showAlert('Network error. Could not load user roles.', 'danger', 'userManagementMessage');
        }
    }
    /**
     * Fetches user data from the API and populates the table.
     * @param {number} page The page number to fetch.
     * @param {string} search The search term.
     */
    async function loadUsers(page = 1, search = '') {
        currentPage = page;
        currentSearchTerm = search;
        if (userLoadingSpinner) userLoadingSpinner.style.display = 'block';
        if (userListSection) userListSection.style.display = 'none';
        usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading users...</td></tr>';
        paginationControls.innerHTML = '';
        try {
            // API call to users endpoint, manually adding '/public'
            const response = await fetch(`${baseUrlPath}/public/api/admin/users?page=${page}&limit=10&search=${encodeURIComponent(search)}`, { // مسیر بروزرسانی شده
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (response.ok && data.success) {
                usersTableBody.innerHTML = ''; // Clear existing rows
                if (data.data.users && data.data.users.length > 0) {
                    data.data.users.forEach(user => {
                        const row = `
                            <tr>
                                <td>${user.id}</td>
                                <td>${Admin.escapeHtml(user.first_name)} ${Admin.escapeHtml(user.last_name)}</td>
                                <td>${Admin.escapeHtml(user.email)}</td>
                                <td><span class="badge bg-${user.role_name === 'admin' ? 'danger' : 'success'}">${Admin.escapeHtml(user.role_name)}</span></td>
                                <td><span class="badge bg-${user.is_active ? 'success' : 'secondary'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
                                <td>${new Date(user.created_at).toLocaleDateString()}</td>
                                <td>
                                    <button class="btn btn-sm btn-info edit-user-btn" data-id="${user.id}" title="Edit User">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-user-btn" data-id="${user.id}" title="Delete User">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        usersTableBody.insertAdjacentHTML('beforeend', row);
                    });
                    attachUserActionListeners(); // Attach listeners to new buttons
                } else {
                    usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No users found.</td></tr>';
                }
                generatePagination(data.data.currentPage, data.data.totalPages);
            } else {
                Admin.showAlert(data.message || 'Failed to load users.', 'danger', 'userManagementMessage');
                usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading users.</td></tr>';
            }
        } catch (error) {
            console.error('Network or unexpected error fetching users:', error);
            Admin.showAlert('Network error. Could not load users.', 'danger', 'userManagementMessage');
            usersTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Network error.</td></tr>';
        } finally {
            if (userLoadingSpinner) userLoadingSpinner.style.display = 'none';
            if (userListSection) userListSection.style.display = 'block';
        }
    }
    /**
     * Generates pagination links.
     * @param {number} currentPage The current active page.
     * @param {number} totalPages The total number of pages.
     */
    function generatePagination(currentPage, totalPages) {
        paginationControls.innerHTML = '';
        const ul = document.createElement('ul');
        ul.classList.add('pagination', 'justify-content-center');
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.classList.add('page-item', currentPage === 1 ? 'disabled' : '');
        prevLi.innerHTML = `<a class="page-link bg-dark text-light border-secondary" href="#" data-page="${currentPage - 1}">Previous</a>`;
        ul.appendChild(prevLi);
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            const li = document.createElement('li');
            li.classList.add('page-item', i === currentPage ? 'active' : '');
            li.innerHTML = `<a class="page-link bg-dark text-light border-secondary" href="#" data-page="${i}">${i}</a>`;
            ul.appendChild(li);
        }
        // Next button
        const nextLi = document.createElement('li');
        nextLi.classList.add('page-item', currentPage === totalPages ? 'disabled' : '');
        nextLi.innerHTML = `<a class="page-link bg-dark text-light border-secondary" href="#" data-page="${currentPage + 1}">Next</a>`;
        ul.appendChild(nextLi);
        paginationControls.appendChild(ul);
        // Attach event listeners to pagination links
        ul.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.dataset.page);
                if (!isNaN(page) && page >= 1 && page <= totalPages) {
                    loadUsers(page, currentSearchTerm);
                }
            });
        });
    }
    /**
     * Attaches event listeners to dynamically created edit and delete buttons.
     * This should be called after the user table is re-rendered.
     */
    function attachUserActionListeners() {
        document.querySelectorAll('.edit-user-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = parseInt(this.dataset.id);
                openUserModal(userId);
            });
        });
        document.querySelectorAll('.delete-user-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = parseInt(this.dataset.id);
                deleteUser(userId);
            });
        });
    }
    /**
     * Opens the add/edit user modal.
     * @param {number|null} userId The ID of the user to edit, or null for adding a new user.
     */
    async function openUserModal(userId = null) {
        clearUserForm();
        modalMessageDiv.classList.add('d-none'); // Hide modal messages initially
        if (userId) {
            userModalLabel.textContent = 'Edit User';
            userIdInput.value = userId;
            document.getElementById('password').setAttribute('placeholder', 'Leave blank to keep current password');
            try {
                // API call to get user by ID endpoint, manually adding '/public'
                const response = await fetch(`${baseUrlPath}/public/api/admin/users/${userId}`, { // مسیر بروزرسانی شده
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (response.ok && data.success) {
                    const user = data.data;
                    document.getElementById('firstName').value = user.first_name;
                    document.getElementById('lastName').value = user.last_name;
                    document.getElementById('email').value = user.email;
                    document.getElementById('isActive').checked = user.is_active == 1; // Convert to boolean
                    roleIdSelect.value = user.role_id; // Set selected role
                } else {
                    Admin.showAlert(data.message || 'Failed to load user data for editing.', 'danger', 'userManagementMessage');
                    userModal.hide(); // Hide modal if data loading fails
                    return;
                }
            } catch (error) {
                console.error('Network or unexpected error fetching user for edit:', error);
                Admin.showAlert('Network error. Could not load user data.', 'danger', 'userManagementMessage');
                userModal.hide();
                return;
            }
        } else {
            userModalLabel.textContent = 'Add New User';
            document.getElementById('password').setAttribute('placeholder', 'Enter password');
        }
        // Always populate CSRF token when opening the modal for forms
        await Admin.populateCsrfToken('userForm', 'userFormCsrfToken');
        userModal.show();
    }
    /**
     * Handles the submission of the user add/edit form.
     */
    userForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        modalMessageDiv.classList.add('d-none');
        modalMessageDiv.innerHTML = '';
        saveUserBtn.disabled = true;
        saveUserBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        const formData = new FormData(userForm);
        const userData = Object.fromEntries(formData.entries());
        
        // Convert 'is_active' checkbox value to boolean (or 0/1 for PHP)
        userData.is_active = userData.is_active === 'on' ? 1 : 0;
        
        // Remove password if empty for update operations
        if (userIdInput.value && userData.password === '') {
            delete userData.password;
        }
        const userId = userIdInput.value;
        const method = userId ? 'PUT' : 'POST';
        // Construct URL with '/public' manually added
        const url = userId ? `${baseUrlPath}/public/api/admin/users/${userId}` : `${baseUrlPath}/public/api/admin/users`; // مسیر بروزرسانی شده
        // Ensure CSRF token is present
        let csrfToken = userFormCsrfToken.value;
        if (!csrfToken) {
            csrfToken = await Admin.fetchCsrfToken(); // Admin.fetchCsrfToken already uses baseUrlPath correctly
            if (!csrfToken) {
                Admin.showAlert('Security token missing. Please refresh and try again.', 'danger', 'modalMessage');
                saveUserBtn.disabled = false;
                saveUserBtn.innerHTML = 'Save User';
                return;
            }
            userFormCsrfToken.value = csrfToken;
        }
        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(userData)
            });
            const data = await response.json();
            if (response.ok && data.success) {
                Admin.showAlert(data.message, 'success', 'userManagementMessage');
                userModal.hide(); // Close modal
                loadUsers(currentPage, currentSearchTerm); // Reload users to show changes
            } else {
                let errorMessage = data.message || 'An unknown error occurred.';
                if (data.errors) {
                    errorMessage += '<ul class="mt-2 mb-0">';
                    for (const field in data.errors) {
                        // Display validation errors below the respective input fields
                        const errorElement = document.getElementById(`${field}Error`);
                        const inputElement = document.getElementById(field);
                        if (errorElement && inputElement) {
                            errorElement.textContent = data.errors[field];
                            inputElement.classList.add('is-invalid');
                        } else {
                            errorMessage += `<li>${field}: ${data.errors[field]}</li>`;
                        }
                    }
                    errorMessage += '</ul>';
                }
                Admin.showAlert(errorMessage, 'danger', 'modalMessage');
                // Re-populate CSRF token on failure
                await Admin.populateCsrfToken('userForm', 'userFormCsrfToken');
            }
        } catch (error) {
            console.error('Network or unexpected error during user save:', error);
            Admin.showAlert('Network error. Please try again.', 'danger', 'modalMessage');
        } finally {
            saveUserBtn.disabled = false;
            saveUserBtn.innerHTML = 'Save User';
        }
    });
    /**
     * Handles user deletion.
     * @param {number} userId The ID of the user to delete.
     */
    async function deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            return;
        }
        
        try {
            // Use Admin.fetchWithCsrf to handle CSRF token automatically
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/public/api/admin/users/${userId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            const data = await response.json();
            if (response.ok && data.success) {
                Admin.showAlert(data.message, 'success', 'userManagementMessage');
                loadUsers(currentPage, currentSearchTerm); // Reload users after deletion
            } else {
                Admin.showAlert(data.message || 'Failed to delete user.', 'danger', 'userManagementMessage');
            }
        } catch (error) {
            console.error('Network or unexpected error during user deletion:', error);
            Admin.showAlert('Network error. Could not delete user.', 'danger', 'userManagementMessage');
        }
    }
    // --- Event Listeners ---
    // Initial load of users and roles when the page loads
    loadUsers();
    loadRoles();
    // Search button click
    searchUsersBtn.addEventListener('click', function() {
        loadUsers(1, userSearchInput.value); // Reset to page 1 for new search
    });
    // Add New User button click
    addNewUserBtn.addEventListener('click', function() {
        openUserModal(); // Open modal for adding a new user
    });
    // Optional: Trigger search on Enter key in search input
    userSearchInput.addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            searchUsersBtn.click();
        }
    });
    // Helper to escape HTML for display (to prevent XSS)
    // Admin.escapeHtml is expected to be defined in admin_main.js
    if (typeof Admin.escapeHtml === 'undefined') {
        console.warn("Admin.escapeHtml is not defined. Ensure admin_main.js is loaded before admin_users.js.");
        Admin.escapeHtml = function(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        };
    }
});