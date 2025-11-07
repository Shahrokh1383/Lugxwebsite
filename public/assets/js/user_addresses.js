document.addEventListener('DOMContentLoaded', () => {
    // Cache DOM elements
    const addressesListContainer = select('#addresses-list');
    const addressModal = new bootstrap.Modal(select('#addressModal'));
    const addressForm = select('#address-form');
    const addressModalLabel = select('#addressModalLabel');
    const addAddressBtn = select('#add-address-btn');
    const messageContainer = select('#message-container'); // Cache message container

    // Cache form input elements for easier access
    const addressIdInput = select('#address_id');
    const titleInput = select('#title');
    const firstNameInput = select('#first_name'); // ADDED
    const lastNameInput = select('#last_name');   // ADDED
    const addressLine1Input = select('#address_line_1');
    const addressLine2Input = select('#address_line_2'); // ADDED
    const cityInput = select('#city');
    const stateInput = select('#state');
    const zipCodeInput = select('#zip_code');
    const countryInput = select('#country');
    const phoneInput = select('#phone');         // ADDED
    const isDefaultCheckbox = select('#is_default');

    // Initial setup
    if (messageContainer) {
        clearErrors('address-form'); // Clear any initial form errors
        messageContainer.innerHTML = ''; // Clear main message container
    } else {
        console.error('ERROR: messageContainer element not found in user_addresses.js. Cannot clear messages.');
    }

    // Function to load and display addresses
    async function loadAddresses() {
        addressesListContainer.innerHTML = '<div class="col"><p class="text-center text-medium-gray">Loading addresses...</p></div>';
        try {
            const response = await fetch('http://localhost:8080/Lugxwebsite/public/api/user/addresses');
            const result = await response.json();

            if (response.ok) {
                if (result.addresses && result.addresses.length > 0) {
                    renderAddresses(result.addresses);
                } else {
                    addressesListContainer.innerHTML = '<div class="col"><div class="card border-0 rounded-3 shadow-sm p-4 text-center text-medium-gray"><p class="mb-0">No addresses found. Click "Add New Address" to add one.</p></div></div>';
                }
            } else {
                displayMessage(result.message || 'Failed to load addresses.', 'danger', 'message-container'); // Pass messageContainer ID
                addressesListContainer.innerHTML = '<div class="col"><div class="card border-0 rounded-3 shadow-sm p-4 text-center text-danger"><p class="mb-0">Error loading addresses.</p></div></div>';
            }
        } catch (error) {
            console.error('Error loading addresses:', error);
            displayMessage('An unexpected error occurred while loading addresses.', 'danger', 'message-container'); // Pass messageContainer ID
            addressesListContainer.innerHTML = '<div class="col"><div class="card border-0 rounded-3 shadow-sm p-4 text-center text-danger"><p class="mb-0">Error loading addresses.</p></div></div>';
        }
    }

    // Function to render addresses into the DOM
    function renderAddresses(addresses) {
        addressesListContainer.innerHTML = ''; // Clear previous content
        addresses.forEach(address => {
            const isDefaultClass = address.is_default ? 'default-address' : '';
            const defaultBadge = address.is_default ? '<span class="badge bg-success ms-2">Default</span>' : ''; // Updated badge style

            const addressCard = document.createElement('div');
            addressCard.className = `col`; // Removed address-card and isDefaultClass from here, will apply to inner card
            addressCard.innerHTML = `
                <div class="card border-0 rounded-3 shadow-sm p-4 ${isDefaultClass}">
                    <div class="d-flex justify-content-end mb-2">
                        <button type="button" class="btn btn-info btn-sm me-2 edit-address-btn" data-id="${address.id}">Edit</button>
                        <button type="button" class="btn btn-danger btn-sm delete-address-btn" data-id="${address.id}">Delete</button>
                        ${!address.is_default ? `<button type="button" class="btn btn-primary btn-sm ms-2 set-default-btn" data-id="${address.id}">Set as Default</button>` : ''}
                    </div>
                    <h5 class="card-title fw-bold text-dark-gray mb-2">${address.title || 'N/A'}</h5>
                    <!-- ADDED: Display First Name and Last Name -->
                    <p class="card-text text-medium-gray mb-1"><strong>Name:</strong> ${address.first_name || 'N/A'} ${address.last_name || 'N/A'}</p>
                    <!-- ADDED: Display Address Line 2 -->
                    <p class="card-text text-medium-gray mb-1"><strong>Address:</strong> ${address.address || 'N/A'}${address.address2 ? `, ${address.address2}` : ''}</p>
                    <p class="card-text text-medium-gray mb-1"><strong>City, State, Zip:</strong> ${address.city || 'N/A'}, ${address.state || 'N/A'}, ${address.postal_code || 'N/A'}</p>
                    <p class="card-text text-medium-gray mb-1"><strong>Country:</strong> ${address.country || 'N/A'}</p>
                    <!-- ADDED: Display Phone -->
                    <p class="card-text text-medium-gray"><strong>Phone:</strong> ${address.phone || 'N/A'}</p>
                    ${defaultBadge}
                </div>
            `;
            addressesListContainer.appendChild(addressCard);
        });

        // Attach event listeners to new buttons (using event delegation for efficiency if many addresses)
        // Or keep current approach as it's fine for a limited number of addresses
        selectAll('.edit-address-btn').forEach(button => {
            button.addEventListener('click', (e) => openEditAddressModal(e.target.dataset.id));
        });
        selectAll('.delete-address-btn').forEach(button => {
            button.addEventListener('click', (e) => deleteAddress(e.target.dataset.id));
        });
        selectAll('.set-default-btn').forEach(button => {
            button.addEventListener('click', (e) => setDefaultAddress(e.target.dataset.id));
        });
    }

    // Reset form and set modal title for adding new address
    addAddressBtn.addEventListener('click', () => {
        addressForm.reset();
        addressIdInput.value = ''; // Clear hidden ID field
        addressModalLabel.textContent = 'Add New Address';
        clearErrors('address-form'); // Clear any previous errors for the form
        // Clear all input values manually since form.reset() might not clear all types
        titleInput.value = '';
        firstNameInput.value = '';
        lastNameInput.value = '';
        addressLine1Input.value = '';
        addressLine2Input.value = '';
        cityInput.value = '';
        stateInput.value = '';
        zipCodeInput.value = '';
        countryInput.value = '';
        phoneInput.value = '';
        isDefaultCheckbox.checked = false;
    });

    // Handle Address Form Submission (Add/Edit)
    addressForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors('address-form'); // Clear previous errors for the form
        displayMessage('Saving address...', 'info', 'message-container'); // Pass messageContainer ID

        const addressId = addressIdInput.value;
        const method = addressId ? 'PUT' : 'POST';
        const url = addressId ? `${window.API_BASE_URL}/user/addresses/${addressId}` : `${window.API_BASE_URL}/user/addresses`;

        const formData = {
            title: titleInput.value.trim(),
            first_name: firstNameInput.value.trim(),     // ADDED
            last_name: lastNameInput.value.trim(),       // ADDED
            address: addressLine1Input.value.trim(),
            address2: addressLine2Input.value.trim() || null, // ADDED
            city: cityInput.value.trim(),
            state: stateInput.value.trim(),
            postal_code: zipCodeInput.value.trim(),
            country: countryInput.value.trim(),
            phone: phoneInput.value.trim() || null,     // ADDED
            is_default: isDefaultCheckbox.checked ? 1 : 0
        };

        // Client-side validation for required fields
        let isValid = true;
        const errors = {};

        if (!formData.title) errors.title = 'Address title is required.';
        if (!formData.first_name) errors.first_name = 'First name is required.';
        if (!formData.last_name) errors.last_name = 'Last name is required.';
        if (!formData.address) errors.address_line_1 = 'Address line 1 is required.';
        if (!formData.city) errors.city = 'City is required.';
        if (!formData.state) errors.state = 'State/Province is required.';
        if (!formData.postal_code) errors.zip_code = 'Zip/Postal Code is required.';
        if (!formData.country) errors.country = 'Country is required.';

        if (Object.keys(errors).length > 0) {
            displayValidationErrors(errors, 'address-form'); // Pass form ID for targeted error display
            isValid = false;
        }

        if (!isValid) {
            displayMessage('Please correct the form errors.', 'danger', 'message-container'); // Pass messageContainer ID
            return;
        }

        try {
            const requestOptions = await preparePostRequest(formData, method);
            if (!requestOptions) {
                displayMessage('Failed to prepare request (CSRF token missing).', 'danger', 'message-container');
                return;
            }

            const response = await fetch(url, requestOptions);
            const result = await response.json();

            if (response.ok) {
                displayMessage(result.message || `Address ${addressId ? 'updated' : 'added'} successfully!`, 'success', 'message-container');
                addressModal.hide(); // Close modal on success
                loadAddresses(); // Reload addresses
            } else {
                if (result.errors) {
                    displayValidationErrors(result.errors, 'address-form'); // Pass form ID
                } else {
                    displayMessage(result.message || `Failed to ${addressId ? 'update' : 'add'} address.`, 'danger', 'message-container');
                }
            }
        } catch (error) {
            console.error(`Error ${addressId ? 'updating' : 'adding'} address:`, error);
            displayMessage('An unexpected error occurred. Please try again.', 'danger', 'message-container');
        }
    });

    // Function to open modal for editing an address
    async function openEditAddressModal(id) {
        clearErrors('address-form'); // Clear errors for the form
        addressModalLabel.textContent = 'Edit Address';
        displayMessage('Loading address details...', 'info', 'message-container'); // Pass messageContainer ID
        try {
            const response = await fetch(`${window.API_BASE_URL}/user/addresses/${id}`); // Use window.API_BASE_URL
            const result = await response.json();

            if (response.ok && result.address) {
                const address = result.address;
                addressIdInput.value = address.id;
                titleInput.value = address.title || '';
                firstNameInput.value = address.first_name || ''; // ADDED
                lastNameInput.value = address.last_name || '';   // ADDED
                addressLine1Input.value = address.address || '';
                addressLine2Input.value = address.address2 || ''; // ADDED
                cityInput.value = address.city || '';
                stateInput.value = address.state || '';
                zipCodeInput.value = address.postal_code || '';
                countryInput.value = address.country || '';
                phoneInput.value = address.phone || '';         // ADDED
                isDefaultCheckbox.checked = address.is_default === 1;

                addressModal.show();
                displayMessage('Address details loaded.', 'success', 'message-container'); // Pass messageContainer ID
            } else {
                displayMessage(result.message || 'Failed to load address for editing.', 'danger', 'message-container');
            }
        } catch (error) {
            console.error('Error loading address for editing:', error);
            displayMessage('An unexpected error occurred while loading address for editing.', 'danger', 'message-container');
        }
    }

    // Function to delete an address
    async function deleteAddress(id) {
        // Using window.showConfirmation for consistency
        window.showConfirmation('Are you sure you want to delete this address?', async () => {
            clearErrors('address-form'); // Clear errors for the form
            displayMessage('Deleting address...', 'info', 'message-container');
            try {
                const requestOptions = await preparePostRequest({}, 'DELETE');
                if (!requestOptions) {
                    displayMessage('Failed to prepare DELETE request (CSRF token missing).', 'danger', 'message-container');
                    return;
                }

                const response = await fetch(`${window.API_BASE_URL}/user/addresses/${id}`, requestOptions); // Use window.API_BASE_URL
                const result = await response.json();

                if (response.ok) {
                    displayMessage(result.message || 'Address deleted successfully!', 'success', 'message-container');
                    loadAddresses(); // Reload addresses
                } else {
                    displayMessage(result.message || 'Failed to delete address.', 'danger', 'message-container');
                }
            } catch (error) {
                console.error('Error deleting address:', error);
                displayMessage('An unexpected error occurred while deleting address.', 'danger', 'message-container');
            }
        });
    }

    // Function to set an address as default
    async function setDefaultAddress(id) {
        // Using window.showConfirmation for consistency
        window.showConfirmation('Are you sure you want to set this address as default?', async () => {
            clearErrors('address-form'); // Clear errors for the form
            displayMessage('Setting default address...', 'info', 'message-container');
            try {
                const formData = { is_default: 1 };
                const requestOptions = await preparePostRequest(formData, 'PUT');
                if (!requestOptions) {
                    displayMessage('Failed to prepare request (CSRF token missing).', 'danger', 'message-container');
                    return;
                }

                const response = await fetch(`${window.API_BASE_URL}/user/addresses/${id}/set-default`, requestOptions); // Use window.API_BASE_URL
                const result = await response.json();

                if (response.ok) {
                    displayMessage(result.message || 'Address set as default successfully!', 'success', 'message-container');
                    loadAddresses(); // Reload addresses to update default badge
                } else {
                    displayMessage(result.message || 'Failed to set address as default.', 'danger', 'message-container');
                }
            } catch (error) {
                console.error('Error setting default address:', error);
                displayMessage('An unexpected error occurred while setting default address.', 'danger', 'message-container');
            }
        });
    }

    // Initial load of addresses when the page is ready
    loadAddresses();

    // Check authentication status on page load (important for user-specific pages)
    window.Auth.checkAuthStatus().then(authStatus => {
        if (!authStatus.logged_in) {
            window.showToast('Please log in to manage your addresses.', 'info');
            setTimeout(() => {
                window.location.href = './login.html?return_to=' + encodeURIComponent(window.location.pathname);
            }, 1500);
        }
    }).catch(error => {
        console.error('Error checking auth status:', error);
        window.showToast('An error occurred during authentication check.', 'danger');
    });
});
