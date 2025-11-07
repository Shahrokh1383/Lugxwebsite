// Ensure these are truly global and available immediately
window.BASE_URL = 'http://localhost:8080/Lugxwebsite/public';
// NEW: Define API_BASE_URL globally here
window.API_BASE_URL = `${window.BASE_URL}/api`;

// Utility functions for DOM selection
window.select = (selector, parent = document) => parent.querySelector(selector);
window.selectAll = (selector, parent = document) => parent.querySelectorAll(selector);

// New: Toast/Notification System (replacing traditional displayMessage for better UX)
/**
 * Initializes the toast container if it doesn't exist.
 * @returns {HTMLElement} The toast container element.
 */
function getOrCreateToastContainer() {
    let container = window.select('#toast-container'); // Use window.select here
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.bottom = '20px';
        container.style.right = '20px';
        container.style.zIndex = '1050'; // Above modals
        document.body.appendChild(container);
    }
    return container;
}

/**
 * Displays a Bootstrap Toast notification.
 * @param {string} message The message to display.
 * @param {string} type 'success', 'danger', 'info', 'warning'.
 * @param {number} delay Auto-hide delay in milliseconds. Defaults to 5000.
 */
window.showToast = function(message, type, delay = 5000) { // Make it a window property directly
    const toastContainer = getOrCreateToastContainer();
    const bgClass = {
        'success': 'text-bg-success',
        'danger': 'text-bg-danger',
        'info': 'text-bg-info',
        'warning': 'text-bg-warning'
    }[type] || 'text-bg-primary'; // Default to primary if type is unknown

    const toastId = `toast-${Date.now()}`; // Unique ID for each toast

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = window.select(`#${toastId}`); // Use window.select here
    // Check if bootstrap is loaded before initializing Toast
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toastElement, { delay: delay });
        bsToast.show();

        // Remove toast from DOM after it's hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    } else {
        console.error('Bootstrap Toast not available. Ensure Bootstrap JS is loaded.');
        // Fallback to simple console log if Bootstrap is not loaded
        console.log(`Toast fallback: ${message}`);
    }
};

// Keep displayMessage for specific use cases (e.g., form-level messages) but prioritize showToast for general notifications
/**
 * Displays a message to the user in a designated message container (Bootstrap Alert).
 * @param {string} message The message text.
 * @param {string} type 'success', 'danger', 'info', 'warning'.
 * @param {string} containerId The ID of the message container. Defaults to 'message-container'.
 */
window.displayMessage = function(message, type, containerId = 'message-container') { // Make it a window property directly
    const messageContainer = window.select(`#${containerId}`); // Use window.select here
    if (messageContainer) {
        messageContainer.innerHTML = '';
        messageContainer.style.display = 'block';

        const alertDiv = document.createElement('div');
        const alertType = type === 'error' ? 'danger' : type;
        alertDiv.className = `alert alert-${alertType} alert-dismissible fade show`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `${message}
                                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;

        messageContainer.appendChild(alertDiv);
        messageContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        console.warn('Message container not found. Cannot display message:', message, 'Type:', type);
    }
};

/**
 * Clears all validation error messages displayed below form inputs and the main message container.
 * @param {string} [formId] Optional: The ID of the form to clear errors from.
 */
window.clearErrors = function(formId) { // Make it a window property directly
    let parentElement = document;
    if (formId) {
        const form = window.select(`#${formId}`); // Use window.select here
        if (form) {
            parentElement = form;
        } else {
            console.warn(`Form with ID '${formId}' not found for clearing errors.`);
        }
    }

    window.selectAll('.invalid-feedback', parentElement).forEach(el => { // Use window.selectAll here
        el.textContent = '';
        el.style.display = 'none';
    });
    window.selectAll('.is-invalid', parentElement).forEach(el => { // Use window.selectAll here
        el.classList.remove('is-invalid');
    });

    const messageContainer = window.select('#message-container'); // Use window.select here
    if (messageContainer) {
        messageContainer.innerHTML = '';
        messageContainer.style.display = 'none';
    }
};

/**
 * Displays validation errors received from the backend.
 * @param {object} errors An object where keys are field names and values are error messages.
 * @param {string} [formId] Optional: The ID of the form to apply errors to.
 */
window.displayValidationErrors = function(errors, formId) { // Make it a window property directly
    window.clearErrors(formId);

    let generalErrorsHtml = '';
    for (const field in errors) {
        if (errors.hasOwnProperty(field)) {
            const inputElement = window.select(`#${field}`); // Use window.select here
            if (inputElement) {
                inputElement.classList.add('is-invalid');
                const errorElement = window.select(`#${field}-error`); // Corrected error element ID convention
                if (errorElement) {
                    errorElement.textContent = errors[field];
                    errorElement.style.display = 'block';
                } else {
                    generalErrorsHtml += `<p>${errors[field]}</p>`;
                }
            } else {
                generalErrorsHtml += `<p>${errors[field]}</p>`;
            }
        }
    }

    if (generalErrorsHtml) {
        window.displayMessage(generalErrorsHtml, 'danger', 'message-container');
    }

    const firstInvalid = window.select('.is-invalid'); // Use window.select here
    if (firstInvalid) {
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};

/**
 * Helper function to handle API responses, checking for success, errors, and JSON parsing issues.
 * @param {Response} response The fetch API Response object.
 * @returns {Promise<Object>} The parsed JSON result.
 * @throws {Error} If the response is not OK, not JSON, or parsing fails.
 */
window.handleApiResponse = async function(response) {
    if (!response.ok) {
        const errorText = await response.text();
        console.error('API Response not OK:', response.status, response.statusText, 'Body:', errorText);
        // Check if it's a redirect to login page (status 302)
        // This is a common pattern for unauthenticated API requests
        if (response.status === 302 && response.headers.get('Location') && response.headers.get('Location').includes('login.html')) {
            // Only redirect if not already on a login/register page to prevent loops
            if (!window.location.pathname.includes('login.html') && !window.location.pathname.includes('register.html')) {
                window.location.href = response.headers.get('Location');
            }
            throw new Error("Redirected to login page. User not authenticated.");
        }
        throw new Error(`HTTP error! Status: ${response.status} - ${response.statusText}. Server Response: ${errorText.substring(0, 200)}...`);
    }

    const contentType = response.headers.get('Content-Type');
    if (!contentType || !contentType.includes('application/json')) {
        const errorText = await response.text();
        console.error('Expected JSON but received:', contentType, 'Body:', errorText);
        throw new Error(`Expected JSON response, but received '${contentType}'. Server Response: ${errorText.substring(0, 200)}...`);
    }

    try {
        return await response.json();
    } catch (e) {
        const errorText = await response.text();
        console.error('JSON parsing failed:', e, 'Raw response:', errorText);
        throw new Error(`Failed to parse JSON response. Raw response: ${errorText.substring(0, 200)}... Error: ${e.message}`);
    }
};


/**
 * Fetches the CSRF token from the backend.
 * @returns {Promise<string|null>} A promise that resolves with the CSRF token string or null on failure.
 */
window.getCsrfToken = async function() { // Make it a window property directly
    try {
        const response = await fetch(`${window.API_BASE_URL}/csrf-token`); // Use window.API_BASE_URL
        const data = await window.handleApiResponse(response); // Use the new handler
        return data.csrf_token || null;
    } catch (error) {
        console.error('Error fetching CSRF token:', error);
        return null;
    }
};

let csrfToken = null; // This will store the fetched CSRF token

/**
 * Prepares the request options for POST/PUT/DELETE requests, including CSRF token in a header.
 * @param {object} formData The data to be sent.
 * @param {string} method The HTTP method ('POST', 'PUT', 'DELETE'). Defaults to 'POST'.
 * @returns {Promise<object|null>} A promise that resolves with the prepared request options, or null.
 */
window.preparePostRequest = async function(formData, method = 'POST') { // Make it a window property directly
    if (!csrfToken) {
        csrfToken = await window.getCsrfToken(); // Use window.getCsrfToken
        if (!csrfToken) {
            window.showToast('Security token missing. Please refresh the page and try again.', 'danger');
            return null;
        }
    }

    return {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(formData)
    };
};

// Cart Icon Management (kept here as a general utility)
/**
 * Updates the number displayed on the shopping cart icon in the header.
 * @param {number} count The number of items in the cart.
 */
window.updateCartIcon = function(count) {
    const cartItemCountSpan = window.select('#cart-item-count'); // Use window.select here
    if (cartItemCountSpan) {
        cartItemCountSpan.textContent = count;
        if (count > 0) {
            cartItemCountSpan.classList.remove('d-none'); // Ensure it's visible
        } else {
            cartItemCountSpan.classList.add('d-none'); // Hide if cart is empty
        }
    }
};

// REMOVED: The checkAuthStatus function from main.js is now removed.
// It is now handled exclusively by window.Auth.checkAuthStatus in auth.js.

document.addEventListener('DOMContentLoaded', async function() {
    console.log('main.js loaded and DOMContentLoaded event fired.');

    // Fetch CSRF token on load (this is crucial for all subsequent API calls)
    csrfToken = await window.getCsrfToken(); // Initialize global csrfToken

    // Check auth status on load to update header links (login/dashboard)
    // Now calling the centralized function from auth.js
    if (typeof window.Auth !== 'undefined' && typeof window.Auth.checkAuthStatus === 'function') {
        window.Auth.checkAuthStatus();
    } else {
        console.error('window.Auth or window.Auth.checkAuthStatus is not available. Ensure auth.js is loaded correctly.');
        // Fallback to displaying generic sign-in link if auth.js is not ready
        const authLinksContainer = window.select('#auth-links-container');
        if (authLinksContainer) {
            authLinksContainer.innerHTML = `<li><a href="./login.html" id="auth-link" class="btn-primary">Sign In</a></li>`;
        }
    }

    // --- Initialize cart icon count on page load ---
    // Make sure Cart object exists and has loadCartSummary method, otherwise default to 0
    // This will be called in cart.js as well, but this ensures a fallback and initial state
    if (typeof window.Cart !== 'undefined' && typeof window.Cart.loadCartSummary === 'function') {
        window.Cart.loadCartSummary(); // Calls updateCartIcon internally
    } else {
        window.updateCartIcon(0); // Default to 0 if Cart.js is not yet fully loaded/defined
    }


    // --- Preloader ---
    const preloader = window.select('#preloader'); // Use window.select here
    if (preloader) {
        window.addEventListener('load', function() {
            setTimeout(function() {
                preloader.classList.add('hidden');
            }, 500);
        });
    }

    // --- Sticky Navbar ---
    // Select the navbar specifically in the main section or fallback to general
    const navbar = window.select('.main-header .navbar') || window.select('.shop-header .navbar') || window.select('.navbar'); // Use window.select here

    if (navbar) {
        const handleStickyNavbar = () => {
            if (window.scrollY > 0) {
                navbar.classList.add('sticky');
            } else {
                navbar.classList.remove('sticky');
            }
        };

        window.addEventListener('scroll', handleStickyNavbar);
        handleStickyNavbar();
    }

    // --- Mobile Menu Toggle ---
    const menuToggle = window.select('.menu-toggle'); // Use window.select here
    const navLinks = window.select('.nav-links'); // Use window.select here

    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            const icon = menuToggle.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            }
        });

        window.selectAll('a', navLinks).forEach(link => { // Use window.selectAll here
            link.addEventListener('click', () => {
                if (window.innerWidth <= 991 && navLinks.classList.contains('active')) {
                    navLinks.classList.remove('active');
                    const icon = menuToggle.querySelector('i');
                    if (icon) {
                        icon.classList.add('fa-bars');
                        icon.classList.remove('fa-times');
                    }
                }
            });
        });
    }
});
