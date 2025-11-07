// Ensure main.js is loaded first to provide window.API_BASE_URL, window.select, etc.

window.Auth = {
    /**
     * Handles user registration.
     * @param {object} formData - The registration form data.
     * @returns {Promise<boolean>} True if registration is successful, false otherwise.
     */
    register: async function(formData) {
        window.clearErrors('registerForm'); // Clear previous errors for the register form

        // Basic client-side validation for required fields
        let isValid = true;
        const errors = {};

        if (!formData.username || formData.username.trim() === '') {
            errors.username = 'Username is required.';
            isValid = false;
        }
        // Corrected: Use 'email' as per register.html
        if (!formData.email || formData.email.trim() === '') {
            errors.email = 'Email is required.';
            isValid = false;
        }
        // Corrected: Use 'password' as per register.html
        if (!formData.password || formData.password.length < 8) {
            errors.password = 'Password must be at least 8 characters long.';
            isValid = false;
        }
        if (formData.password !== formData.confirm_password) {
            errors.confirm_password = 'Passwords do not match.';
            isValid = false;
        }

        if (!isValid) {
            window.displayValidationErrors(errors, 'registerForm');
            window.showToast('Please correct the errors in the form.', 'warning');
            return false;
        }

        const requestOptions = await window.preparePostRequest(formData);
        if (!requestOptions) {
            console.error('Failed to prepare POST request for registration (CSRF token issue).');
            return false;
        }

        try {
            const response = await fetch(`${window.API_BASE_URL}/register`, requestOptions);
            const data = await window.handleApiResponse(response); // Use global handler

            if (data.status === 'success') {
                window.showToast(data.message, 'success');
                const registerForm = window.select('#registerForm'); // Use window.select
                if (registerForm) registerForm.reset();
                setTimeout(() => { window.location.href = './login.html'; }, 1500);
                return true;
            } else {
                if (data.errors) {
                    window.displayValidationErrors(data.errors, 'registerForm');
                } else {
                    window.displayMessage(data.message || 'Registration failed. Please try again.', 'danger', 'message-container');
                }
                window.showToast(data.message || 'Registration failed.', 'danger');
                return false;
            }
        } catch (error) {
            console.error('Error during registration:', error);
            window.showToast('An unexpected error occurred during registration. Please try again later.', 'danger');
            return false;
        }
    },

    /**
     * Handles user login.
     * @param {object} formData - The login form data.
     * @returns {Promise<boolean>} True if login is successful, false otherwise.
     */
    login: async function(formData) {
        window.clearErrors('loginForm');

        const requestOptions = await window.preparePostRequest(formData);
        if (!requestOptions) {
            console.error('Failed to prepare POST request for login (CSRF token issue).');
            return false;
        }

        try {
            const response = await fetch(`${window.API_BASE_URL}/login`, requestOptions);
            const data = await window.handleApiResponse(response); // Use global handler

            if (data.status === 'success') {
                window.showToast(data.message, 'success');
                const urlParams = new URLSearchParams(window.location.search);
                const returnTo = urlParams.get('return_to') || './user_dashboard.html';
                setTimeout(() => { window.location.href = returnTo; }, 1500);
                return true;
            } else {
                window.showToast(data.message || 'Login failed. Please check your credentials.', 'danger');
                if (data.errors) {
                    window.displayValidationErrors(data.errors, 'loginForm');
                } else {
                    window.displayMessage(data.message || 'Login failed. Please check your credentials.', 'danger', 'message-container');
                }
                return false;
            }
        } catch (error) {
            console.error('Error during login:', error);
            window.showToast('An unexpected error occurred during login. Please try again later.', 'danger');
            return false;
        }
    },

    /**
     * Handles user logout.
     * @returns {Promise<boolean>} True if logout is successful, false otherwise.
     */
    logout: async function() {
        console.log('Auth.logout() called.');
        window.clearErrors();

        const requestOptions = await window.preparePostRequest({}, 'POST'); // Logout usually doesn't need body, but CSRF is good
        if (!requestOptions) {
            console.error('Failed to prepare POST request for logout (CSRF token issue).');
            window.showToast('Security token missing for logout. Please refresh.', 'danger');
            return false;
        }
        console.log('Logout request options prepared:', requestOptions);

        try {
            const response = await fetch(`${window.API_BASE_URL}/logout`, requestOptions);
            console.log('Logout fetch response received:', response);

            const data = await window.handleApiResponse(response); // Use global handler
            console.log('Logout API response data:', data);

            if (data.status === 'success') {
                window.showToast(data.message, 'success');
                console.log('Logout successful, client-side redirecting to login page...');
                const authLinksContainer = window.select('#auth-links-container'); // Use window.select
                if (authLinksContainer) {
                    authLinksContainer.innerHTML = `<li><a href="./login.html" id="auth-link" class="btn-primary">Sign In</a></li>`;
                }
                if (typeof window.updateCartIcon === 'function') {
                    window.updateCartIcon(0);
                }
                setTimeout(() => {
                    window.location.href = './login.html';
                }, 500);
                return true;
            } else {
                window.showToast(data.message || 'Logout failed. Please try again.', 'danger');
                console.error('Logout failed:', data.message);
                return false;
            }
        } catch (error) {
            console.error('Error during logout fetch or processing:', error);
            window.showToast('An unexpected error occurred during logout. Please try again later.', 'danger');
            return false;
        }
    },

    /**
     * Handles forgot password request.
     * @param {object} formData - The forgot password form data (email).
     * @returns {Promise<boolean>} True if request is sent, false otherwise.
     */
    forgotPassword: async function(formData) {
        window.clearErrors('forgotPasswordForm');

        const requestOptions = await window.preparePostRequest(formData);
        if (!requestOptions) {
            console.error('Failed to prepare POST request for forgot password (CSRF token issue).');
            return false;
        }

        try {
            const response = await fetch(`${window.API_BASE_URL}/forgot-password`, requestOptions);
            const data = await window.handleApiResponse(response); // Use global handler

            window.showToast(data.message, data.status === 'success' ? 'success' : 'danger');
            const forgotPasswordForm = window.select('#forgotPasswordForm'); // Use window.select
            if (forgotPasswordForm) forgotPasswordForm.reset();
            return data.status === 'success';
        } catch (error) {
            console.error('Error during forgot password:', error);
            window.showToast('An unexpected error occurred. Please try again later.', 'danger');
            return false;
        }
    },

    /**
     * Handles reset password request.
     * @param {object} formData - The reset password form data (token, new_password, confirm_new_password).
     * @returns {Promise<boolean>} True if password reset is successful, false otherwise.
     */
    resetPassword: async function(formData) {
        window.clearErrors('resetPasswordForm');

        let isValid = true;
        const errors = {};

        if (!formData.new_password || formData.new_password.length < 8) {
            errors.new_password = 'New password must be at least 8 characters long.';
            isValid = false;
        }
        if (formData.new_password !== formData.confirm_new_password) {
            errors.confirm_new_password = 'New passwords do not match.';
            isValid = false;
        }

        if (!isValid) {
            window.displayValidationErrors(errors, 'resetPasswordForm');
            window.showToast('Please correct the errors in the form.', 'warning');
            return false;
        }

        const requestOptions = await window.preparePostRequest(formData);
        if (!requestOptions) {
            console.error('Failed to prepare POST request for password reset (CSRF token issue).');
            return false;
        }

        try {
            const response = await fetch(`${window.API_BASE_URL}/reset-password`, requestOptions);
            const data = await window.handleApiResponse(response); // Use global handler

            if (data.status === 'success') {
                window.showToast(data.message, 'success');
                const resetPasswordForm = window.select('#resetPasswordForm'); // Use window.select
                if (resetPasswordForm) resetPasswordForm.reset();
                setTimeout(() => { window.location.href = './login.html'; }, 1500);
                return true;
            } else {
                if (data.errors) {
                    window.displayValidationErrors(data.errors, 'resetPasswordForm');
                } else {
                    window.displayMessage(data.message || 'Password reset failed. Please try again.', 'danger', 'message-container');
                }
                window.showToast(data.message || 'Password reset failed.', 'danger');
                return false;
            }
        } catch (error) {
            console.error('Error during password reset:', error);
            window.showToast('An unexpected error occurred. Please try again later.', 'danger');
            return false;
        }
    },

    // NEW: Function to check authentication status
    checkAuthStatus: async function() {
        try {
            const response = await fetch(`${window.API_BASE_URL}/auth/status`);
            const result = await window.handleApiResponse(response);
            if (result.status === 'success') {
                // If logged in, update the header UI
                if (result.logged_in && result.user) {
                    this.updateAuthLinks(result.user.username);
                } else {
                    this.updateAuthLinks(null); // Not logged in
                }
                return { logged_in: result.logged_in, user: result.user };
            } else {
                this.updateAuthLinks(null); // Not logged in or error
                return { logged_in: false, user: null };
            }
        } catch (error) {
            console.error('Error checking authentication status:', error);
            this.updateAuthLinks(null); // Assume not logged in on error
            return { logged_in: false, user: null };
        }
    },

    // NEW: Function to get authenticated user data (if needed directly)
    getAuthenticatedUser: async function() {
        const status = await this.checkAuthStatus();
        return status.logged_in ? status.user : null;
    },

    /**
     * Updates the header authentication links based on login status.
     * This function is also called by main.js.
     * @param {string|null} username - The username if logged in, null otherwise.
     */
    updateAuthLinks: function(username) {
        const authLinksContainer = window.select('#auth-links-container');
        if (!authLinksContainer) {
            console.warn('Auth links container not found.');
            return;
        }

        if (username) {
            authLinksContainer.innerHTML = `
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle btn-primary" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-2"></i>${username}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="./user_dashboard.html"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
                        <li><a class="dropdown-item" href="./user_profile.html"><i class="fas fa-user me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="./user_orders.html"><i class="fas fa-shopping-bag me-2"></i> My Orders</a></li>
                        <li><a class="dropdown-item" href="./user_addresses.html"><i class="fas fa-map-marker-alt me-2"></i> My Addresses</a></li>
                        <li><a class="dropdown-item" href="./wishlist.html"><i class="fas fa-heart me-2"></i> Wishlist</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form id="logoutFormNav" class="logout-form">
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            `;
            // Attach logout listener to the new form in the dropdown
            const logoutFormNav = window.select('#logoutFormNav');
            if (logoutFormNav) {
                logoutFormNav.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    await window.Auth.logout();
                });
            }
        } else {
            authLinksContainer.innerHTML = `<li><a href="./login.html" id="auth-link" class="btn-primary">Sign In</a></li>`;
        }
    }
};

// DOMContentLoaded event listener to attach form submissions
document.addEventListener('DOMContentLoaded', () => {
    console.log('auth.js loaded and DOMContentLoaded event fired.');

    // Handle Register Form Submission
    const registerForm = window.select('#registerForm'); // Use window.select
    if (registerForm) {
        console.log('Register form found. Attaching submit listener.');
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = {
                username: window.select('#username')?.value || '',
                email: window.select('#email')?.value || '',
                password: window.select('#password')?.value || '',
                confirm_password: window.select('#confirm_password')?.value || '',
                first_name: window.select('#first_name')?.value || '',
                last_name: window.select('#last_name')?.value || '',
                phone: window.select('#phone')?.value || '',
                date_of_birth: window.select('#date_of_birth')?.value || '',
                gender: window.select('#gender')?.value || '',
            };
            await window.Auth.register(formData);
        });
    }

    // Handle Login Form Submission
    const loginForm = window.select('#loginForm'); // Use window.select
    if (loginForm) {
        console.log('Login form found. Attaching submit listener.');
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = {
                email: window.select('#email')?.value || '',
                password: window.select('#password')?.value || '',
                remember_me: window.select('#remember_me')?.checked || false,
            };
            await window.Auth.login(formData);
        });
    }

    // Handle Logout Form Submission (specifically for the form on user_dashboard.html)
    // This targets the logout form in the sidebar
    const logoutForm = window.select('#logoutForm'); // Use window.select
    if (logoutForm) {
        const logoutButton = window.select('button[type="submit"]', logoutForm); // Use window.select
        if (logoutButton) {
            console.log('Logout button found inside logoutForm. Attaching click listener.');
            logoutButton.addEventListener('click', async (e) => {
                e.preventDefault();
                console.log('Logout button clicked. Calling Auth.logout().');
                await window.Auth.logout();
            });
        } else {
            console.warn('Logout button (type="submit") not found inside logoutForm.');
        }
    } else {
        console.log('Logout form with ID "logoutForm" not found on this page.'); // Changed to log, as it's not an error on non-dashboard pages
    }

    // Handle Forgot Password Form Submission
    const forgotPasswordForm = window.select('#forgotPasswordForm'); // Use window.select
    if (forgotPasswordForm) {
        console.log('Forgot password form found. Attaching submit listener.');
        forgotPasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = {
                email: window.select('#email')?.value || '',
            };
            await window.Auth.forgotPassword(formData);
        });
    }

    // Handle Reset Password Form Submission
    const resetPasswordForm = window.select('#resetPasswordForm'); // Use window.select
    if (resetPasswordForm) {
        console.log('Reset password form found. Attaching submit listener.');
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        if (token) {
            const resetTokenInput = window.select('#reset_token'); // Use window.select
            if (resetTokenInput) {
                resetTokenInput.value = token;
                console.log('Reset token found in URL and populated.');
            } else {
                console.warn('Input with ID "reset_token" not found for populating token.');
            }
        } else {
            window.showToast('Password reset token is missing from the URL. Please use the link from your email.', 'danger');
            console.error('Reset token missing from URL.');
        }

        resetPasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = {
                token: window.select('#reset_token')?.value || '',
                new_password: window.select('#new_password')?.value || '',
                confirm_new_password: window.select('#confirm_new_password')?.value || '',
            };
            await window.Auth.resetPassword(formData);
        });
    }

    // Handle logic for email verification success/error messages on login.html
    // This block is fine to keep as it's specific to login.html messages.
    if (window.location.pathname.includes('login.html')) {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('verified') && urlParams.get('verified') === 'true') {
            window.showToast('Your email has been successfully verified! You can now log in.', 'success');
        } else if (urlParams.has('verified') && urlParams.get('verified') === 'already') {
            window.showToast('Your email was already verified. Please log in.', 'info');
        } else if (urlParams.has('error') && urlParams.get('error') === 'verification_failed') {
            window.showToast('Email verification failed. The token might be invalid or expired.', 'danger');
        }
    }

    // Initial check for auth status on DOMContentLoaded for pages that need it
    // This ensures the header updates correctly on page load
    // This is the function that main.js also calls
    window.Auth.checkAuthStatus(); // Call it once on DOMContentLoaded
});
