/**
 * public/assets/js/admin/admin_settings.js
 *
 * This file handles the functionality for the admin settings page.
 * It interacts with the AdminSettingsController backend to manage site settings.
 */

document.addEventListener('DOMContentLoaded', function() {
    const loadingSpinner = document.getElementById('loadingSpinner');
    const settingsContainer = document.getElementById('settingsContainer');
    const settingsForm = document.getElementById('settingsForm');
    const saveSettingsBtn = document.getElementById('saveSettingsBtn');
    const settingsTabs = document.getElementById('settingsTabs');
    const settingsTabContent = document.getElementById('settingsTabContent');
    const messageDiv = document.getElementById('message');

    // Get base URL path from the global variable injected by PHP
    const baseUrlPath = window.AppBaseUrlPath || '';

    // Store original settings data to detect changes
    let originalSettings = {};
    let settingsGroups = {};

    /**
     * Fetches settings from the API and populates the form.
     */
    async function loadSettings() {
        // Show loading spinner and hide settings container initially
        if (loadingSpinner) loadingSpinner.style.display = 'block';
        if (settingsContainer) settingsContainer.style.display = 'none';

        try {
            const response = await fetch(`${baseUrlPath}/api/admin/settings`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText}`);
            }

            const data = await response.json();

            if (data.success) {
                settingsGroups = data.data;
                
                // Flatten settings for comparison
                for (const groupName in settingsGroups) {
                    settingsGroups[groupName].forEach(setting => {
                        originalSettings[setting.key_name] = setting.value;
                    });
                }

                renderSettingsForm(settingsGroups);
                
                // Hide loading spinner and show settings container
                if (loadingSpinner) loadingSpinner.style.display = 'none';
                if (settingsContainer) settingsContainer.style.display = 'block';
            } else {
                Admin.showAlert(data.message || 'Failed to load settings.', 'danger', 'message');
                if (loadingSpinner) loadingSpinner.style.display = 'none';
            }
        } catch (error) {
            console.error('Network or unexpected error fetching settings:', error);
            Admin.showAlert('Network error. Could not load settings.', 'danger', 'message');
            if (loadingSpinner) loadingSpinner.style.display = 'none';
        }
    }

    /**
     * Renders the settings form with tabs and input fields.
     * @param {Object} settingsGroups The settings grouped by group name.
     */
    function renderSettingsForm(settingsGroups) {
        // Clear existing tabs and content
        settingsTabs.innerHTML = '';
        settingsTabContent.innerHTML = '';

        let isFirstTab = true;

        for (const groupName in settingsGroups) {
            const settings = settingsGroups[groupName];
            const groupId = groupName.replace(/\s+/g, '-').toLowerCase();
            
            // Create tab
            const tabItem = document.createElement('li');
            tabItem.className = 'nav-item';
            tabItem.role = 'presentation';
            
            const tabButton = document.createElement('button');
            tabButton.className = `nav-link ${isFirstTab ? 'active' : ''}`;
            tabButton.id = `${groupId}-tab`;
            tabButton.setAttribute('data-bs-toggle', 'tab');
            tabButton.setAttribute('data-bs-target', `#${groupId}`);
            tabButton.type = 'button';
            tabButton.role = 'tab';
            tabButton.setAttribute('aria-controls', groupId);
            tabButton.setAttribute('aria-selected', isFirstTab ? 'true' : 'false');
            tabButton.textContent = groupName.charAt(0).toUpperCase() + groupName.slice(1);
            
            tabItem.appendChild(tabButton);
            settingsTabs.appendChild(tabItem);
            
            // Create tab content
            const tabPane = document.createElement('div');
            tabPane.className = `tab-pane fade ${isFirstTab ? 'show active' : ''}`;
            tabPane.id = groupId;
            tabPane.role = 'tabpanel';
            tabPane.setAttribute('aria-labelledby', `${groupId}-tab`);
            
            const row = document.createElement('div');
            row.className = 'row g-3';
            
            settings.forEach(setting => {
                const col = document.createElement('div');
                col.className = 'col-md-6';
                
                const formGroup = document.createElement('div');
                formGroup.className = 'mb-3';
                
                const label = document.createElement('label');
                label.className = 'form-label';
                label.setAttribute('for', `setting-${setting.key_name}`);
                label.textContent = setting.description || setting.key_name;
                
                const input = createInputForSetting(setting);
                
                formGroup.appendChild(label);
                formGroup.appendChild(input);
                col.appendChild(formGroup);
                row.appendChild(col);
            });
            
            tabPane.appendChild(row);
            settingsTabContent.appendChild(tabPane);
            
            isFirstTab = false;
        }
    }

    /**
     * Creates an appropriate input element based on setting type.
     * @param {Object} setting The setting object.
     * @return {HTMLElement} The input element.
     */
    function createInputForSetting(setting) {
        let input;
        
        switch (setting.type) {
            case 'boolean':
                input = document.createElement('div');
                input.className = 'form-check form-switch';
                
                const checkbox = document.createElement('input');
                checkbox.className = 'form-check-input';
                checkbox.type = 'checkbox';
                checkbox.id = `setting-${setting.key_name}`;
                checkbox.name = `settings[${setting.key_name}]`;
                checkbox.checked = setting.value === '1' || setting.value === true;
                
                const checkboxLabel = document.createElement('label');
                checkboxLabel.className = 'form-check-label';
                checkboxLabel.setAttribute('for', `setting-${setting.key_name}`);
                checkboxLabel.textContent = checkbox.checked ? 'Enabled' : 'Disabled';
                
                input.appendChild(checkbox);
                input.appendChild(checkboxLabel);
                
                // Update label text when checkbox changes
                checkbox.addEventListener('change', function() {
                    checkboxLabel.textContent = this.checked ? 'Enabled' : 'Disabled';
                });
                
                break;
                
            case 'number':
                input = document.createElement('input');
                input.className = 'form-control';
                input.type = 'number';
                input.id = `setting-${setting.key_name}`;
                input.name = `settings[${setting.key_name}]`;
                input.value = setting.value || '';
                input.step = 'any';
                break;
                
            case 'textarea':
                input = document.createElement('textarea');
                input.className = 'form-control';
                input.id = `setting-${setting.key_name}`;
                input.name = `settings[${setting.key_name}]`;
                input.value = setting.value || '';
                input.rows = '4';
                break;
                
            case 'select':
                input = document.createElement('select');
                input.className = 'form-select';
                input.id = `setting-${setting.key_name}`;
                input.name = `settings[${setting.key_name}]`;
                
                // Add default option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select an option';
                input.appendChild(defaultOption);
                
                // This would need to be extended with actual options
                // For now, we'll just create a text input
                input = document.createElement('input');
                input.className = 'form-control';
                input.type = 'text';
                input.id = `setting-${setting.key_name}`;
                input.name = `settings[${setting.key_name}]`;
                input.value = setting.value || '';
                break;
                
            case 'file':
                input = document.createElement('div');
                input.className = 'mb-3';
                
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.className = 'form-control';
                fileInput.id = `setting-${setting.key_name}`;
                fileInput.name = setting.key_name;
                
                const currentFile = document.createElement('div');
                currentFile.className = 'mt-2';
                
                if (setting.value) {
                    currentFile.innerHTML = `
                        <small class="text-muted">Current file: ${setting.value}</small>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="remove-${setting.key_name}">
                            <i class="fa-solid fa-trash"></i> Remove
                        </button>
                    `;
                    
                    // Add remove file functionality
                    const removeBtn = currentFile.querySelector(`#remove-${setting.key_name}`);
                    if (removeBtn) {
                        removeBtn.addEventListener('click', function() {
                            // Add a hidden input to indicate file removal
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = `remove_${setting.key_name}`;
                            hiddenInput.value = '1';
                            input.appendChild(hiddenInput);
                            
                            // Remove current file display
                            currentFile.remove();
                        });
                    }
                }
                
                input.appendChild(fileInput);
                input.appendChild(currentFile);
                break;
                
            default: // text
                input = document.createElement('input');
                input.className = 'form-control';
                input.type = 'text';
                input.id = `setting-${setting.key_name}`;
                input.name = `settings[${setting.key_name}]`;
                input.value = setting.value || '';
                break;
        }
        
        return input;
    }

    /**
     * Saves settings to the server.
     */
    async function saveSettings() {
        // Disable save button to prevent multiple submissions
        saveSettingsBtn.disabled = true;
        saveSettingsBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

        try {
            // Create FormData object
            const formData = new FormData(settingsForm);
            
            // Add CSRF token
            const csrfToken = await Admin.getCsrfToken();
            if (csrfToken) {
                formData.set('csrf_token', csrfToken);
            }

            // Use fetchWithCsrf for request
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/settings`, {
                method: 'PUT',
                body: formData
            });

            // Check for HTTP errors before trying to parse JSON
            if (!response.ok) {
                // Try to parse JSON to get a more specific error from the backend
                try {
                    const errorData = await response.json();
                    let errorMessage = errorData.error || `An error occurred with status code: ${response.status}`;
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
                    Admin.showAlert(`Server error: ${response.status} ${response.statusText}`, 'danger');
                }
                return;
            }

            // If response is OK, process the successful save
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                
                // Update original settings with the new values
                for (const key in data.data) {
                    originalSettings[key] = data.data[key];
                }
            } else {
                let errorMessage = data.error || 'An unknown error occurred while saving settings.';
                if (data.errors) {
                    errorMessage += '<ul class="mt-2 mb-0">';
                    for (const field in data.errors) {
                        errorMessage += `<li>${field}: ${data.errors[field]}</li>`;
                    }
                    errorMessage += '</ul>';
                }
                Admin.showAlert(errorMessage, 'danger');
            }
        } catch (error) {
            console.error('Network or unexpected error saving settings:', error);
            Admin.showAlert('Network error. Please try again.', 'danger');
        } finally {
            // Re-enable save button
            saveSettingsBtn.disabled = false;
            saveSettingsBtn.innerHTML = '<i class="fa-solid fa-save me-2"></i>Save All Settings';
        }
    }

    /**
     * Warns user if there are unsaved changes when leaving the page.
     */
    function checkForUnsavedChanges(event) {
        // Get current form values
        const formData = new FormData(settingsForm);
        const currentSettings = {};
        
        for (const [key, value] of formData.entries()) {
            if (key.startsWith('settings[')) {
                const settingKey = key.substring(9, key.length - 1); // Remove 'settings[' and ']'
                currentSettings[settingKey] = value;
            }
        }
        
        // Check if any settings have changed
        for (const key in originalSettings) {
            if (originalSettings[key] != currentSettings[key]) {
                // Found a change, show warning
                event.preventDefault();
                event.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return event.returnValue;
            }
        }
    }

    // Event listeners
    if (saveSettingsBtn) {
        saveSettingsBtn.addEventListener('click', saveSettings);
    }

    // Warn before leaving if there are unsaved changes
    window.addEventListener('beforeunload', checkForUnsavedChanges);

    // Load settings when the page loads
    loadSettings();
});