/**
 * public/assets/js/admin/admin_coupons.js
 *
 * This file handles the coupon management functionality for the admin panel.
 * It interacts with the AdminCouponController backend API to manage coupons.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get the base URL path from the global variable injected by PHP
    const baseUrlPath = window.AppBaseUrlPath || '';
    const couponsTableBody = document.getElementById('couponsTableBody');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const couponsTableCard = document.getElementById('couponsTableCard');
    const messageDiv = document.getElementById('message');
    
    // Modal elements
    const couponModal = document.getElementById('couponModal');
    const couponModalInstance = bootstrap.Modal.getOrCreateInstance(couponModal);
    const couponForm = document.getElementById('couponForm');
    const couponModalLabel = document.getElementById('couponModalLabel');
    
    // Form fields
    const couponIdInput = document.getElementById('couponId');
    const couponCodeInput = document.getElementById('couponCode');
    const couponTypeInput = document.getElementById('couponType');
    const couponValueInput = document.getElementById('couponValue');
    const couponMaxDiscountInput = document.getElementById('couponMaxDiscount');
    const couponMinAmountInput = document.getElementById('couponMinAmount');
    const couponUsageLimitInput = document.getElementById('couponUsageLimit');
    const couponPerUserLimitInput = document.getElementById('couponPerUserLimit');
    const couponStatusInput = document.getElementById('couponStatus');
    const couponStartDateInput = document.getElementById('couponStartDate');
    const couponEndDateInput = document.getElementById('couponEndDate');
    const couponValueHelp = document.getElementById('couponValueHelp');
    
    // Set today as minimum date for date pickers
    const today = new Date().toISOString().split('T')[0];
    couponStartDateInput.setAttribute('min', today);
    couponEndDateInput.setAttribute('min', today);
    
    // Set current date as default for start date
    couponStartDateInput.value = today;
    
    // Set tomorrow as default for end date
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    couponEndDateInput.value = tomorrow.toISOString().split('T')[0];
    
    // Event listeners
    document.getElementById('addNewCouponBtn').addEventListener('click', function() {
        resetForm();
        couponModalLabel.textContent = 'Add New Coupon';
    });
    
    couponTypeInput.addEventListener('change', function() {
        updateCouponValueHelp();
    });
    
    couponForm.addEventListener('submit', function(e) {
        e.preventDefault();
        saveCoupon();
    });
    
    // Initialize the page
    initCouponManagement();
    
    /**
     * Initialize coupon management functionality
     */
    function initCouponManagement() {
        loadCoupons();
    }
    
    /**
     * Load all coupons from the API
     */
    async function loadCoupons() {
        try {
            // Show loading spinner
            loadingSpinner.style.display = 'block';
            couponsTableCard.style.display = 'none';
            
            // Fetch coupons from API
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/coupons`, {
                method: 'GET'
            });
            
            if (!response.ok) {
                throw new Error(`Failed to load coupons: ${response.status}`);
            }
            
            const coupons = await response.json();
            renderCouponsTable(coupons);
            
            // Hide loading spinner and show table
            loadingSpinner.style.display = 'none';
            couponsTableCard.style.display = 'block';
        } catch (error) {
            console.error('Error loading coupons:', error);
            loadingSpinner.style.display = 'none';
            Admin.showAlert('Failed to load coupons. Please try again.', 'danger');
        }
    }
    
    /**
     * Render coupons in the table
     * @param {Array} coupons - Array of coupon objects
     */
    function renderCouponsTable(coupons) {
        // Clear existing table rows
        couponsTableBody.innerHTML = '';
        
        if (coupons.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="9" class="text-center">No coupons found</td>
            `;
            couponsTableBody.appendChild(row);
            return;
        }
        
        // Sort coupons by creation date (newest first)
        coupons.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        
        // Add each coupon as a row in the table
        coupons.forEach(coupon => {
            const row = document.createElement('tr');
            
            // Format discount display
            let discountDisplay = '';
            if (coupon.type === 'percentage') {
                discountDisplay = `${coupon.value}%`;
            } else {
                discountDisplay = `$${parseFloat(coupon.value).toFixed(2)}`;
            }
            
            // Format usage display
            let usageDisplay = 'Unlimited';
            if (coupon.usage_limit !== null) {
                usageDisplay = `${coupon.used_count}/${coupon.usage_limit}`;
            }
            
            // Format status
            let statusBadge = '';
            const currentDate = new Date();
            const startDate = new Date(coupon.start_date);
            const endDate = new Date(coupon.end_date);
            
            if (!coupon.is_active) {
                statusBadge = '<span class="badge bg-secondary">Inactive</span>';
            } else if (currentDate < startDate) {
                statusBadge = '<span class="badge bg-info">Upcoming</span>';
            } else if (currentDate > endDate) {
                statusBadge = '<span class="badge bg-danger">Expired</span>';
            } else {
                statusBadge = '<span class="badge bg-success">Active</span>';
            }
            
            // Format dates
            const formattedStartDate = formatDate(coupon.start_date);
            const formattedEndDate = formatDate(coupon.end_date);
            
            row.innerHTML = `
                <td>${coupon.id}</td>
                <td><span class="badge bg-primary">${Admin.escapeHtml(coupon.code)}</span></td>
                <td>${discountDisplay}</td>
                <td>${coupon.type === 'percentage' ? 'Percentage' : 'Fixed Amount'}</td>
                <td>$${parseFloat(coupon.minimum_amount).toFixed(2)}</td>
                <td>${usageDisplay}</td>
                <td>${statusBadge}</td>
                <td>${formattedEndDate}</td>
                <td>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-primary edit-coupon" data-id="${coupon.id}">
                            <i class="fa-solid fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-coupon" data-id="${coupon.id}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            couponsTableBody.appendChild(row);
        });
        
        // Add event listeners to edit and delete buttons
        document.querySelectorAll('.edit-coupon').forEach(button => {
            button.addEventListener('click', function() {
                const couponId = this.getAttribute('data-id');
                editCoupon(couponId);
            });
        });
        
        document.querySelectorAll('.delete-coupon').forEach(button => {
            button.addEventListener('click', function() {
                const couponId = this.getAttribute('data-id');
                confirmDeleteCoupon(couponId);
            });
        });
    }
    
    /**
     * Format date to a readable string
     * @param {string} dateStr - Date string in YYYY-MM-DD format
     * @returns {string} Formatted date string
     */
    function formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    /**
     * Edit a coupon
     * @param {string} couponId - ID of the coupon to edit
     */
    async function editCoupon(couponId) {
        try {
            // Fetch coupon data from API
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/coupons/${couponId}`, {
                method: 'GET'
            });
            
            if (!response.ok) {
                throw new Error(`Failed to load coupon: ${response.status}`);
            }
            
            const coupon = await response.json();
            
            // Populate form with coupon data
            couponIdInput.value = coupon.id;
            couponCodeInput.value = coupon.code;
            couponTypeInput.value = coupon.type;
            couponValueInput.value = coupon.value;
            couponMaxDiscountInput.value = coupon.maximum_discount || '';
            couponMinAmountInput.value = coupon.minimum_amount;
            couponUsageLimitInput.value = coupon.usage_limit || '';
            couponPerUserLimitInput.value = coupon.per_user_limit;
            couponStatusInput.value = coupon.is_active ? '1' : '0';
            couponStartDateInput.value = coupon.start_date;
            couponEndDateInput.value = coupon.end_date;
            
            // Update UI based on coupon type
            updateCouponValueHelp();
            
            // Set min date for end date based on start date
            couponEndDateInput.setAttribute('min', coupon.start_date);
            
            // Update modal title
            couponModalLabel.textContent = `Edit Coupon: ${coupon.code}`;
            
            // Show modal
            couponModalInstance.show();
        } catch (error) {
            console.error('Error loading coupon:', error);
            Admin.showAlert('Failed to load coupon data. Please try again.', 'danger');
        }
    }
    
    /**
     * Update the coupon value help text based on selected type
     */
    function updateCouponValueHelp() {
        if (couponTypeInput.value === 'percentage') {
            couponValueHelp.textContent = 'Enter percentage value (1-100)';
            couponValueInput.setAttribute('min', '1');
            couponValueInput.setAttribute('max', '100');
        } else {
            couponValueHelp.textContent = 'Enter fixed amount (minimum 0.01)';
            couponValueInput.removeAttribute('max');
            couponValueInput.setAttribute('min', '0.01');
        }
    }
    
    /**
     * Save coupon (create or update)
     */
    async function saveCoupon() {
        // Clear previous errors
        Admin.clearFormErrors(couponForm);
        
        // Prepare data for submission
        const couponData = {
            code: couponCodeInput.value.trim(),
            type: couponTypeInput.value,
            value: parseFloat(couponValueInput.value),
            maximum_discount: couponMaxDiscountInput.value ? parseFloat(couponMaxDiscountInput.value) : null,
            minimum_amount: parseFloat(couponMinAmountInput.value),
            usage_limit: couponUsageLimitInput.value ? parseInt(couponUsageLimitInput.value) : null,
            per_user_limit: parseInt(couponPerUserLimitInput.value),
            is_active: couponStatusInput.value === '1',
            start_date: couponStartDateInput.value,
            end_date: couponEndDateInput.value
        };
        
        // Add ID if editing existing coupon
        if (couponIdInput.value) {
            couponData.id = parseInt(couponIdInput.value);
        }
        
        // Validate data
        const validationErrors = validateCouponData(couponData);
        if (Object.keys(validationErrors).length > 0) {
            // Show validation errors
            for (const [field, message] of Object.entries(validationErrors)) {
                Admin.showInputError(field, message);
            }
            return;
        }
        
        try {
            let response;
            let url;
            let method;
            
            if (couponIdInput.value) {
                // Update existing coupon
                url = `${baseUrlPath}/api/admin/coupons/${couponIdInput.value}`;
                method = 'PUT';
            } else {
                // Create new coupon
                url = `${baseUrlPath}/api/admin/coupons`;
                method = 'POST';
            }
            
            response = await Admin.fetchWithCsrf(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(couponData)
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                if (errorData.errors && typeof errorData.errors === 'object') {
                    // Handle validation errors
                    for (const [field, message] of Object.entries(errorData.errors)) {
                        Admin.showInputError(field, message);
                    }
                } else {
                    // Handle other errors
                    throw new Error(errorData.message || `Failed to ${couponIdInput.value ? 'update' : 'create'} coupon`);
                }
                return;
            }
            
            const result = await response.json();
            Admin.showAlert(result.message, 'success');
            
            // Reset form and hide modal
            couponModalInstance.hide();
            resetForm();
            
            // Reload coupons
            loadCoupons();
        } catch (error) {
            console.error('Error saving coupon:', error);
            Admin.showAlert(error.message || `Failed to ${couponIdInput.value ? 'update' : 'create'} coupon. Please try again.`, 'danger');
        }
    }
    
    /**
     * Validate coupon data
     * @param {Object} data - Coupon data to validate
     * @returns {Object} Validation errors
     */
    function validateCouponData(couponData) {
        const errors = {};
        
        // Validate code
        if (!couponData.code) {
            errors.code = 'Coupon code is required';
        } else if (couponData.code.length > 50) {
            errors.code = 'Coupon code cannot exceed 50 characters';
        }
        
        // Validate type
        if (!couponData.type || !['percentage', 'fixed_amount'].includes(couponData.type)) {
            errors.type = 'Invalid discount type';
        }
        
        // Validate value
        if (isNaN(couponData.value) || couponData.value <= 0) {
            errors.value = 'Discount value must be greater than 0';
        } else if (couponData.type === 'percentage' && couponData.value > 100) {
            errors.value = 'Percentage value cannot exceed 100';
        }
        
        // Validate dates
        if (!couponData.start_date) {
            errors.start_date = 'Start date is required';
        }
        
        if (!couponData.end_date) {
            errors.end_date = 'Expiry date is required';
        } else if (couponData.end_date < couponData.start_date) {
            errors.end_date = 'Expiry date must be after start date';
        }
        
        // Validate minimum amount
        if (isNaN(couponData.minimum_amount) || couponData.minimum_amount < 0) {
            errors.minimum_amount = 'Minimum amount cannot be negative';
        }
        
        // Validate maximum discount
        if (couponData.maximum_discount !== null && (isNaN(couponData.maximum_discount) || couponData.maximum_discount < 0)) {
            errors.maximum_discount = 'Maximum discount cannot be negative';
        }
        
        // Validate usage limit
        if (couponData.usage_limit !== null && (isNaN(couponData.usage_limit) || couponData.usage_limit < 1)) {
            errors.usage_limit = 'Usage limit must be at least 1';
        }
        
        // Validate per user limit
        if (isNaN(couponData.per_user_limit) || couponData.per_user_limit < 1) {
            errors.per_user_limit = 'Per user limit must be at least 1';
        }
        
        return errors;
    }
    
    /**
     * Confirm and delete a coupon
     * @param {string} couponId - ID of the coupon to delete
     */
    function confirmDeleteCoupon(couponId) {
        if (confirm('Are you sure you want to delete this coupon? This action cannot be undone.')) {
            deleteCoupon(couponId);
        }
    }
    
    /**
     * Delete a coupon
     * @param {string} couponId - ID of the coupon to delete
     */
    async function deleteCoupon(couponId) {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/coupons/${couponId}`, {
                method: 'DELETE'
            });
            
            if (!response.ok) {
                throw new Error(`Failed to delete coupon: ${response.status}`);
            }
            
            const result = await response.json();
            Admin.showAlert(result.message, 'success');
            
            // Reload coupons
            loadCoupons();
        } catch (error) {
            console.error('Error deleting coupon:', error);
            Admin.showAlert(error.message || 'Failed to delete coupon. Please try again.', 'danger');
        }
    }
    
    /**
     * Reset the coupon form to its initial state
     */
    function resetForm() {
        couponForm.reset();
        
        // Clear hidden ID field
        couponIdInput.value = '';
        
        // Set defaults
        const today = new Date().toISOString().split('T')[0];
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        couponStartDateInput.value = today;
        couponEndDateInput.value = tomorrow.toISOString().split('T')[0];
        couponMinAmountInput.value = '0';
        couponPerUserLimitInput.value = '1';
        couponStatusInput.value = '1';
        
        // Update UI based on default type
        updateCouponValueHelp();
        
        // Clear any validation errors
        Admin.clearFormErrors(couponForm);
    }
    
    // Initialize date picker constraints
    couponStartDateInput.addEventListener('change', function() {
        // Set min date for end date based on selected start date
        couponEndDateInput.setAttribute('min', this.value);
        
        // If end date is before new start date, reset it
        if (couponEndDateInput.value && couponEndDateInput.value < this.value) {
            const newEndDate = new Date(this.value);
            newEndDate.setDate(newEndDate.getDate() + 1);
            couponEndDateInput.value = newEndDate.toISOString().split('T')[0];
        }
    });
});