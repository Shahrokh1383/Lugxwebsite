/**
 * newsletter.js
 * Handles the logic for the newsletter subscription form.
 */
document.addEventListener('DOMContentLoaded', async () => {
    // Select the newsletter form
    const newsletterForm = window.select('.newsletter-form form');
    if (!newsletterForm) {
        console.error('Newsletter form not found. Please check the selector.');
        return;
    }

    const emailInput = window.select('input[type="email"]', newsletterForm);
    const subscribeButton = window.select('button[type="submit"]', newsletterForm);

    let csrfToken = null;

    // Initially disable the button while we fetch the token
    if (subscribeButton) {
        subscribeButton.disabled = true;
        subscribeButton.textContent = 'Loading...';
    }


    // --- Fetch CSRF token from the API endpoint ---
    try {
        const response = await fetch(`${window.API_BASE_URL}/csrf-token`);

        if (!response.ok) {
            throw new Error('Failed to fetch CSRF token from API.');
        }
        const data = await response.json();
        if (data.status === 'success' && data.csrf_token) {
            csrfToken = data.csrf_token;
            console.log('Successfully fetched CSRF token from API.');
            if (subscribeButton) {
                subscribeButton.disabled = false; // Enable the button
                subscribeButton.textContent = 'Subscribe';
            }
        } else {
            throw new Error(data.message || 'Failed to get CSRF token.');
        }
    } catch (error) {
        console.error('Error fetching CSRF token:', error);
        if (subscribeButton) {
            subscribeButton.textContent = 'Error';
        }
        // Use showToast for global error notification
        if (typeof showToast === 'function') {
            showToast('Failed to initialize newsletter form. Please try reloading the page.', 'danger');
        }
        return; // Stop execution if token fetch fails
    }

    // --- Handle form submission ---
    newsletterForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = emailInput.value.trim();
        if (!email) {
            if (typeof showToast === 'function') {
                showToast('Please enter a valid email address.', 'warning');
            }
            return;
        }

        // Disable button to prevent multiple submissions
        if (subscribeButton) {
            subscribeButton.disabled = true;
            subscribeButton.textContent = 'Subscribing...';
        }

        try {
            const response = await fetch(`${window.API_BASE_URL}/newsletter/subscribe`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken, // Use the fetched CSRF token
                },
                body: JSON.stringify({ email: email }),
            });

            const result = await response.json();

            if (response.ok) {
                // Success message using toast
                if (typeof showToast === 'function') {
                    showToast(result.message, 'success');
                }
                emailInput.value = ''; // Clear the input field
            } else {
                // Error message from server using toast
                if (typeof showToast === 'function') {
                    showToast(result.message || 'An error occurred. Please try again.', 'danger');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            // Network error using toast
            if (typeof showToast === 'function') {
                showToast('Network error. Please check your connection.', 'danger');
            }
        } finally {
            // Re-enable the button
            if (subscribeButton) {
                subscribeButton.disabled = false;
                subscribeButton.textContent = 'Subscribe';
            }
        }
    });
});
