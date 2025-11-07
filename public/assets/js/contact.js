/**
 * contact.js
 * Handles the logic for the contact form submission.
 * It uses the API endpoint for sending a message and the global toast notification system.
 */
document.addEventListener('DOMContentLoaded', () => {
    const contactForm = window.select('#contact-form');
    const submitButton = window.select('#form-submit');
    
    // Check if the form and button exist
    if (!contactForm || !submitButton) {
        console.error('Contact form or submit button not found. Please check the HTML selectors.');
        return;
    }
    
    // Handle form submission
    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault(); // Prevent the default form submission
        
        // Clear previous errors
        window.clearErrors('contact-form');
        
        // Get form data
        const formData = new FormData(contactForm);
        let data = Object.fromEntries(formData.entries());
        
        // Remove empty optional fields (subject, phone)
        if (data.subject && data.subject.trim() === '') {
            delete data.subject;
        }
        if (data.phone && data.phone.trim() === '') {
            delete data.phone;
        }
        
        // Disable button to prevent multiple submissions
        submitButton.disabled = true;
        submitButton.textContent = 'Sending...';
        
        try {
            // Prepare request with CSRF token
            const requestOptions = await window.preparePostRequest(data);
            if (!requestOptions) {
                throw new Error('Failed to prepare request. CSRF token might be missing.');
            }
            
            // Send form data to the backend API endpoint
            const response = await fetch(`${window.API_BASE_URL}/contact/message`, requestOptions);
            const result = await window.handleApiResponse(response);
            
            if (result.status === 'success') {
                // Display success message using a toast notification
                window.showToast(result.message || 'Your message has been sent successfully!', 'success');
                contactForm.reset(); // Clear the form fields on success
                // Reset priority to default value
                const prioritySelect = window.select('#priority');
                if (prioritySelect) {
                    prioritySelect.value = 'medium';
                }
            } else {
                // Handle validation errors
                if (result.errors) {
                    window.displayValidationErrors(result.errors, 'contact-form');
                } else {
                    // Display general error message
                    window.showToast(result.message || 'An error occurred. Please try again.', 'danger');
                }
            }
        } catch (error) {
            console.error('Contact form submission error:', error);
            window.showToast('Network error. Please check your connection.', 'danger');
        } finally {
            // Re-enable the button
            submitButton.disabled = false;
            submitButton.textContent = 'Send Message Now';
        }
    });
});