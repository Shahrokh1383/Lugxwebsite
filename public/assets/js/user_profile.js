document.addEventListener('DOMContentLoaded', () => {
    const profileForm = select('#profile-form');
    const changePasswordForm = select('#change-password-form');
    const messageContainer = select('#message-container');

    // Select profile form fields
    const firstNameField = select('#first_name');
    const lastNameField = select('#last_name');
    const emailField = select('#email');
    const phoneField = select('#phone'); // Corrected variable name and selector
    const dateOfBirthField = select('#date_of_birth');
    const genderField = select('#gender');

    // Select change password form fields
    const currentPasswordField = select('#current_password');
    const newPasswordField = select('#new_password');
    const confirmNewPasswordField = select('#confirm_new_password');

    // Function to load user profile data
    async function loadProfileData() {
        clearErrors(); // Clear any previous errors
        messageContainer.innerHTML = ''; // Clear previous messages

        // Disable fields and show loading
        setFormFieldsDisabled(profileForm, true);
        displayMessage('Loading profile data...', 'info');

        try {
            const response = await fetch('http://localhost:8080/Lugxwebsite/public/api/user/profile');
            const result = await response.json();

            if (response.ok && result.user) { // Ensure result.user exists
                const user = result.user;
                firstNameField.value = user.first_name || '';
                lastNameField.value = user.last_name || '';
                emailField.value = user.email || '';
                phoneField.value = user.phone || ''; // Corrected property access to user.phone
                dateOfBirthField.value = user.date_of_birth || '';
                genderField.value = user.gender || '';
                displayMessage('Profile data loaded.', 'success'); 
            } else {
                displayMessage(result.message || 'Failed to load profile data.', 'danger');
            }
        } catch (error) {
            console.error('Error loading profile data:', error);
            displayMessage('An unexpected error occurred while loading profile data.', 'danger');
        } finally {
            setFormFieldsDisabled(profileForm, false); // Re-enable fields
        }
    }

    // Helper to disable/enable form fields
    function setFormFieldsDisabled(form, disabled) {
        const fields = form.querySelectorAll('input, select, button');
        fields.forEach(field => {
            if (field.type !== 'submit') { // Don't disable submit button
                field.disabled = disabled;
            }
        });
    }

    // Handle Profile Form Submission
    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();
        displayMessage('Saving profile...', 'info');
        setFormFieldsDisabled(profileForm, true);

        const formData = {
            first_name: firstNameField.value,
            last_name: lastNameField.value,
            phone: phoneField.value, // Corrected property name
            date_of_birth: dateOfBirthField.value,
            gender: genderField.value
        };

        try {
            const requestOptions = await preparePostRequest(formData, 'PUT');
            if (!requestOptions) {
                setFormFieldsDisabled(profileForm, false);
                return;
            }

            const response = await fetch('http://localhost:8080/Lugxwebsite/public/api/user/profile', requestOptions);
            const result = await response.json();

            if (response.ok) {
                displayMessage(result.message || 'Profile updated successfully!', 'success');
                // Optionally reload profile data to ensure consistency
                // loadProfileData();
            } else {
                if (result.errors) {
                    displayValidationErrors(result.errors);
                } else {
                    displayMessage(result.message || 'Failed to update profile.', 'danger');
                }
            }
        } catch (error) {
            console.error('Error updating profile:', error);
            displayMessage('An unexpected error occurred. Please try again.', 'danger');
        } finally {
            setFormFieldsDisabled(profileForm, false);
        }
    });

    // Handle Change Password Form Submission
    changePasswordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();
        displayMessage('Changing password...', 'info');
        setFormFieldsDisabled(changePasswordForm, true);

        const currentPassword = currentPasswordField.value;
        const newPassword = newPasswordField.value;
        const confirmNewPassword = confirmNewPasswordField.value;

        // Client-side validation for password fields
        let hasError = false;
        const errors = {}; // Collect errors in an object

        if (newPassword.length < 8) {
            errors.new_password = 'New password must be at least 8 characters long.';
            hasError = true;
        }
        if (newPassword !== confirmNewPassword) {
            errors.confirm_new_password = 'New password and confirmation do not match.';
            hasError = true;
        }
        
        if (hasError) {
            displayValidationErrors(errors); // Use displayValidationErrors
            setFormFieldsDisabled(changePasswordForm, false);
            displayMessage('Please correct the errors in the password form.', 'danger');
            return;
        }

        const formData = {
            current_password: currentPassword,
            new_password: newPassword,
            new_password_confirmation: confirmNewPassword // Backend usually expects confirmation field
        };

        try {
            const requestOptions = await preparePostRequest(formData, 'PUT');
            if (!requestOptions) {
                setFormFieldsDisabled(changePasswordForm, false);
                return;
            }

            const response = await fetch('http://localhost:8080/Lugxwebsite/public/api/user/change-password', requestOptions);
            const result = await response.json();

            if (response.ok) {
                displayMessage(result.message || 'Password changed successfully!', 'success');
                changePasswordForm.reset(); // Clear password fields
            } else {
                if (result.errors) {
                    displayValidationErrors(result.errors);
                } else {
                    displayMessage(result.message || 'Failed to change password.', 'danger');
                }
            }
        } catch (error) {
            console.error('Error changing password:', error);
            displayMessage('An unexpected error occurred. Please try again.', 'danger');
        } finally {
            setFormFieldsDisabled(changePasswordForm, false);
        }
    });

    // Initial load of profile data when the page is ready
    loadProfileData();
});
