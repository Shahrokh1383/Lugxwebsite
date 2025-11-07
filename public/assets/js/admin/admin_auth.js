/**
 * public/assets/js/admin/admin_auth.js
 *
 * This file handles the AJAX login and logout functionality for the admin panel.
 * It interacts with the admin login form and the AdminAuthController backend.
 */
document.addEventListener('DOMContentLoaded', function() {
    const adminLoginForm = document.getElementById('adminLoginForm');
    const messageDiv = document.getElementById('message'); // The div to display messages
    // Get the base URL path from the global variable injected by PHP (e.g., /Lugxwebsite/public)
    const baseUrlPath = window.AppBaseUrlPath || ''; 
    
    /**
     * Handles the submission of the admin login form.
     */
    if (adminLoginForm) {
        adminLoginForm.addEventListener('submit', async function(event) {
            event.preventDefault(); // Prevent default form submission
            // Clear previous messages
            messageDiv.classList.add('d-none');
            messageDiv.innerHTML = '';
            const submitButton = adminLoginForm.querySelector('button[type="submit"]');
            submitButton.disabled = true; // Disable button to prevent multiple submissions
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Logging in...'; // Show loading spinner
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('rememberMe').checked;
            const csrfTokenInput = document.getElementById('csrf_token');
            let csrfToken = csrfTokenInput ? csrfTokenInput.value : null;
            
            // Ensure CSRF token is present. If not, try to fetch it again.
            if (!csrfToken) {
                const tokenResponse = await Admin.fetchCsrfToken();
                if (!tokenResponse.success) {
                    Admin.showAlert('Security token missing. Please refresh the page.', 'danger');
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Login';
                    return;
                }
                csrfToken = tokenResponse.csrf_token;
                csrfTokenInput.value = csrfToken; // Update the input field
            }
            
            try {
                // API call to login endpoint using fetchWithCsrf
                const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/auth/login`, {
                    method: 'POST',
                    body: JSON.stringify({
                        email: email,
                        password: password,
                        remember_me: rememberMe
                    })
                });
                
                // Check for HTTP errors before trying to parse JSON
                if (!response.ok) {
                    // Try to parse JSON to get a more specific error from the backend
                    try {
                        const errorData = await response.json();
                        let errorMessage = errorData.message || `An error occurred with status code: ${response.status}`;
                        if (errorData.errors) {
                             errorMessage += '<ul class="mt-2 mb-0">';
                             for (const field in errorData.errors) {
                                 errorMessage += `<li>${field}: ${errorData.errors[field]}</li>`;
                             }
                             errorMessage += '</ul>';
                        }
                        Admin.showAlert(errorMessage, 'danger');
                    } catch (jsonError) {
                        // If parsing JSON fails, it might be a server error returning plain HTML
                        console.error('Failed to parse JSON error response:', jsonError);
                        Admin.showAlert(`Server error: ${response.status} ${response.statusText}.`, 'danger');
                    }
                    // Re-fetch CSRF token on failure to prevent replay attacks
                    await Admin.populateCsrfToken('adminLoginForm', 'csrf_token');
                    return;
                }
                
                // If response is OK, process the successful login
                const data = await response.json();
                if (data.success) {
                    Admin.showAlert(data.message, 'success');
                    // Redirect to the admin dashboard on successful login
                    setTimeout(() => {
                        window.location.href = `${baseUrlPath}/frontend/admin/admin_dashboard.html`; 
                    }, 1500);
                } else {
                    let errorMessage = data.message || 'An unknown error occurred during login.';
                    if (data.errors) {
                        errorMessage += '<ul class="mt-2 mb-0">';
                        for (const field in data.errors) {
                            errorMessage += `<li>${field}: ${data.errors[field]}</li>`;
                        }
                        errorMessage += '</ul>';
                    }
                    Admin.showAlert(errorMessage, 'danger');
                    // Re-fetch CSRF token on failure to prevent replay attacks
                    await Admin.populateCsrfToken('adminLoginForm', 'csrf_token');
                }
            } catch (error) {
                console.error('Network or unexpected error during login:', error);
                Admin.showAlert('Network error. Please try again.', 'danger');
            } finally {
                submitButton.disabled = false; // Re-enable button
                submitButton.innerHTML = 'Login'; // Reset button text
            }
        });
    }
    
    /**
     * Function to handle admin logout.
     * This can be called from a logout button/link on the dashboard page.
     */
    window.adminLogout = async function() {
        if (!confirm('Are you sure you want to log out?')) {
            return; // User cancelled logout
        }
        
        try {
            // Use fetchWithCsrf for logout
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/auth/logout`, {
                method: 'POST'
            });
            
            // Check for HTTP errors before trying to parse JSON
            if (!response.ok) {
                 const errorText = await response.text();
                 console.error('Logout failed with status:', response.status, 'Response:', errorText);
                 Admin.showAlert(`Logout failed: ${response.statusText}`, 'danger');
                 return;
            }
            
            const data = await response.json();
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                // Redirect to the admin login page after successful logout
                setTimeout(() => {
                    window.location.href = `${baseUrlPath}/frontend/admin/admin_login.html`; 
                }, 1000);
            } else {
                Admin.showAlert(data.message || 'Failed to log out.', 'danger');
            }
        } catch (error) {
            console.error('Network or unexpected error during logout:', error);
            Admin.showAlert('Network error during logout. Please try again.', 'danger');
        }
    };
});