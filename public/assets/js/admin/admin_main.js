/**
 * public/assets/js/admin/admin_main.js
 *
 * This file contains general utility functions for the admin panel.
 * It includes functions for displaying notifications and fetching CSRF tokens.
 */
const Admin = {
    // Store the current CSRF token globally
    _csrfToken: null,
    
    /**
     * Displays a notification alert.
     * @param {string} message The message to display.
     * @param {string} type The type of alert (e.g., 'success', 'danger', 'warning').
     * @param {string} targetId The ID of the element to display the alert in.
     * @param {number} duration The duration in milliseconds before the alert hides. Set to 0 to keep it visible.
     */
    showAlert: function(message, type, targetId = 'message', duration = 5000) {
        const alertElement = document.getElementById(targetId);
        if (alertElement) {
            alertElement.innerHTML = message;
            alertElement.className = `alert alert-${type}`;
            alertElement.classList.remove('d-none');
            if (duration > 0) {
                setTimeout(() => {
                    this.hideAlert(targetId); // Pass the ID instead of the element
                }, duration);
            }
        } else {
            console.error(`Alert target element with ID '${targetId}' not found.`);
        }
    },
    
    /**
     * Hides a notification alert.
     * @param {string|HTMLElement} alertIdentifier The ID or the alert element to hide.
     */
    hideAlert: function(alertIdentifier) {
        let alertElement;
        if (typeof alertIdentifier === 'string') {
            alertElement = document.getElementById(alertIdentifier);
        } else if (alertIdentifier instanceof HTMLElement) {
            alertElement = alertIdentifier;
        }
        if (alertElement) {
            alertElement.classList.add('d-none');
            alertElement.innerHTML = '';
        }
    },
    
    /**
     * Clears all error messages and invalid states from a form.
     * @param {HTMLElement} formElement The form element.
     */
    clearFormErrors: function(formElement) {
        if (!formElement) {
            console.warn(`Form element not found.`);
            return;
        }
        // Remove invalid-feedback messages
        formElement.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
        // Remove is-invalid class from all form elements
        formElement.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    },
    
    /**
     * Shows a validation error message for a specific input field.
     * @param {string} inputName The name attribute of the input field.
     * @param {string} message The error message to display.
     * @param {string} tabId Optional. The ID of the tab containing the input field.
     */
    showInputError: function(inputName, message, tabId = null) {
        const inputElement = document.querySelector(`[name="${inputName}"]`);
        if (inputElement) {
            inputElement.classList.add('is-invalid');
            // Find or create the error message element
            let errorElement = inputElement.nextElementSibling;
            if (errorElement && errorElement.classList.contains('invalid-feedback')) {
                errorElement.textContent = message;
            } else {
                errorElement = document.createElement('div');
                errorElement.classList.add('invalid-feedback');
                errorElement.textContent = message;
                inputElement.parentNode.insertBefore(errorElement, inputElement.nextSibling);
            }
            
            // If tabId is provided, switch to that tab
            if (tabId) {
                const tabButton = document.querySelector(`[data-bs-target="#${tabId}"]`);
                if (tabButton && !tabButton.classList.contains('active')) {
                    tabButton.click();
                    // Wait a bit for the tab to become active before focusing
                    setTimeout(() => {
                        inputElement.focus();
                    }, 100);
                } else {
                    inputElement.focus();
                }
            } else {
                // Try to find the parent tab pane
                const tabPane = inputElement.closest('.tab-pane');
                if (tabPane) {
                    const tabId = tabPane.id;
                    const tabButton = document.querySelector(`[data-bs-target="#${tabId}"]`);
                    if (tabButton && !tabButton.classList.contains('active')) {
                        tabButton.click();
                        // Wait a bit for the tab to become active before focusing
                        setTimeout(() => {
                            inputElement.focus();
                        }, 100);
                    } else {
                        inputElement.focus();
                    }
                } else {
                    inputElement.focus();
                }
            }
        }
    },
    
    /**
     * Fetches the CSRF token from the server and stores it globally.
     * @returns {Promise<Object>} An object containing success status and the token.
     */
    fetchCsrfToken: async function() {
        const baseUrlPath = window.AppBaseUrlPath || '';
        const apiUrl = `${baseUrlPath}/api/csrf-token`;
        console.log("Fetching CSRF token from:", apiUrl);
        try {
            const response = await fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            const data = await response.json();
            
            if (data.csrf_token) {
                console.log("CSRF Token fetched successfully.");
                // Store the token globally
                this._csrfToken = data.csrf_token;
                return { success: true, csrf_token: data.csrf_token };
            } else {
                console.error("API response missing 'csrf_token':", data);
                this.showAlert('Failed to load security token. Please refresh the page.', 'danger');
                return { success: false, csrf_token: null };
            }
        } catch (error) {
            console.error("Network error fetching CSRF token:", error);
            this.showAlert('Network error. Could not load security token.', 'danger');
            return { success: false, csrf_token: null };
        }
    },
    
    /**
     * Gets the current CSRF token from memory or fetches a new one if needed.
     * @returns {Promise<string>} The CSRF token.
     */
    getCsrfToken: async function() {
        if (!this._csrfToken) {
            const response = await this.fetchCsrfToken();
            if (response.success) {
                return response.csrf_token;
            }
            return null;
        }
        return this._csrfToken;
    },
    
    /**
     * Populates a hidden input with the CSRF token.
     * @param {string} formId The ID of the form containing the CSRF input.
     * @param {string} inputId The ID of the CSRF hidden input field.
     */
    populateCsrfToken: async function(formId, inputId = 'csrf_token') {
        const csrfInput = document.getElementById(inputId);
        const form = document.getElementById(formId);
        const submitButton = form ? form.querySelector('button[type="submit"]') : null;
        if (csrfInput) {
            const response = await this.fetchCsrfToken();
            if (response.success) {
                csrfInput.value = response.csrf_token;
                if (submitButton) {
                    submitButton.disabled = false;
                    console.log(`Form '${formId}' submit button enabled.`);
                }
            } else {
                if (submitButton) {
                    submitButton.disabled = true;
                    console.warn(`Form '${formId}' submit button disabled due to missing CSRF token.`);
                }
            }
        } else {
            console.warn(`CSRF token input with ID '${inputId}' not found in form '${formId}'.`);
        }
    },
    
    /**
     * Sets up fetch with CSRF token for API calls.
     * @param {string} url The URL to fetch.
     * @param {Object} options The fetch options.
     * @returns {Promise<Response>} The fetch response.
     */
    fetchWithCsrf: async function(url, options = {}) {
        // Ensure we have a CSRF token
        const csrfToken = await this.getCsrfToken();
        if (!csrfToken) {
            throw new Error('CSRF token not available');
        }
        
        // Set up headers
        const headers = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            ...options.headers
        };
        
        // For FormData requests, don't set Content-Type header
        if (options.body instanceof FormData) {
            // Let the browser set the Content-Type header with the correct boundary
            delete headers['Content-Type'];
        } else if (options.body && typeof options.body === 'object') {
            // For JSON requests, set Content-Type
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        
        // Return fetch with updated options
        return fetch(url, {
            ...options,
            headers
        });
    },
    
    /**
     * Toggles the visibility of the admin sidebar.
     */
    toggleSidebar: function() {
        const sidebar = document.querySelector('.admin-sidebar');
        if (sidebar) {
            sidebar.classList.toggle('collapsed');
        }
    },
    
    /**
     * Escapes HTML characters in a string to prevent XSS attacks.
     * @param {string} str The string to escape.
     * @returns {string} The escaped string.
     */
    escapeHtml: function(str) {
        if (str === null || str === undefined) {
            return '';
        }
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    },
    
    /**
     * Switches to a specific tab in a tabbed interface.
     * @param {string} tabId The ID of the tab to switch to.
     * @param {string} tabContainerId Optional. The ID of the tab container.
     */
    switchToTab: function(tabId, tabContainerId = null) {
        // Find the tab button
        const tabButton = document.querySelector(`[data-bs-target="#${tabId}"]`);
        if (!tabButton) {
            console.error(`Tab button with target '#${tabId}' not found.`);
            return false;
        }
        
        // If the tab is already active, do nothing
        if (tabButton.classList.contains('active')) {
            return true;
        }
        
        // Click the tab button to activate it
        tabButton.click();
        
        // Wait a bit for the tab to become active
        return new Promise(resolve => {
            setTimeout(() => {
                resolve(true);
            }, 100);
        });
    }
};
window.Admin = Admin;
document.addEventListener('DOMContentLoaded', () => {
    // Ensure this is the correct form ID for the product page
    if (document.getElementById('adminLoginForm')) {
        Admin.populateCsrfToken('adminLoginForm', 'adminFormCsrfToken');
    }
});