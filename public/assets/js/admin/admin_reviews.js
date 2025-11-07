/**
 * public/assets/js/admin/admin_reviews.js
 *
 * This file handles the functionality for the admin reviews management page.
 * It includes functions for loading, approving, rejecting, deleting, and replying to reviews.
 */
document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const loadingSpinner = document.getElementById('loadingSpinner');
    const reviewsTableCard = document.getElementById('reviewsTableCard');
    const reviewsTableBody = document.getElementById('reviewsTableBody');
    const messageDiv = document.getElementById('message');
    const refreshBtn = document.getElementById('refreshReviewsBtn');
    const replyModal = new bootstrap.Modal(document.getElementById('replyModal'));
    const viewReviewModal = new bootstrap.Modal(document.getElementById('viewReviewModal'));
    const replyForm = document.getElementById('replyForm');
    const reviewIdInput = document.getElementById('reviewId');
    const replyTextInput = document.getElementById('replyText');
    const submitReplyBtn = document.getElementById('submitReplyBtn');
    
    // Filter form elements
    const filterForm = document.getElementById('reviewsFilterForm');
    const statusFilter = document.getElementById('statusFilter');
    const ratingFilter = document.getElementById('ratingFilter');
    const searchFilter = document.getElementById('searchFilter');
    
    // Pagination element
    const paginationElement = document.getElementById('reviewsPagination');
    
    // Statistics elements
    const totalReviewsCount = document.getElementById('totalReviewsCount');
    const approvedReviewsCount = document.getElementById('approvedReviewsCount');
    const pendingReviewsCount = document.getElementById('pendingReviewsCount');
    const avgRating = document.getElementById('avgRating');
    
    // Current state
    let currentPage = 1;
    let totalPages = 1;
    let currentFilters = {
        status: '',
        rating: '',
        search: ''
    };
    let currentSort = {
        by: 'created_at',
        order: 'DESC'
    };
    
    // Get the base URL path from the global variable
    const baseUrlPath = window.AppBaseUrlPath || '';
    
    /**
     * Load review statistics
     */
    async function loadReviewStatistics() {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/reviews/statistics`, {
                method: 'GET'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const stats = data.data;
                totalReviewsCount.textContent = stats.total_reviews;
                approvedReviewsCount.textContent = stats.approved_reviews;
                pendingReviewsCount.textContent = stats.pending_reviews;
                avgRating.textContent = stats.average_rating;
            }
        } catch (error) {
            console.error('Error loading review statistics:', error);
        }
    }
    
    /**
     * Load all reviews from the server
     */
    async function loadReviews(page = 1, filters = {}, sort = {}) {
        // Show loading spinner
        loadingSpinner.style.display = 'block';
        reviewsTableCard.style.display = 'none';
        
        // Update current state
        currentPage = page;
        currentFilters = { ...currentFilters, ...filters };
        currentSort = { ...currentSort, ...sort };
        
        // Build query parameters
        const queryParams = new URLSearchParams();
        queryParams.append('page', page);
        queryParams.append('per_page', 10);
        queryParams.append('sort_by', currentSort.by);
        queryParams.append('order', currentSort.order);
        
        if (currentFilters.status) {
            queryParams.append('status', currentFilters.status);
        }
        
        if (currentFilters.rating) {
            queryParams.append('rating', currentFilters.rating);
        }
        
        if (currentFilters.search) {
            queryParams.append('search', currentFilters.search);
        }
        
        try {
            // Fetch reviews
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/reviews?${queryParams.toString()}`, {
                method: 'GET'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                renderReviewsTable(data.data);
                renderPagination(data.pagination);
                reviewsTableCard.style.display = 'block';
            } else {
                Admin.showAlert(data.message || 'Failed to load reviews.', 'danger');
            }
        } catch (error) {
            console.error('Error loading reviews:', error);
            Admin.showAlert('Network error. Could not load reviews.', 'danger');
        } finally {
            // Hide loading spinner
            loadingSpinner.style.display = 'none';
        }
    }
    
    /**
     * Render the reviews table
     */
    function renderReviewsTable(reviews) {
        reviewsTableBody.innerHTML = '';
        
        if (reviews.length === 0) {
            reviewsTableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">No reviews found.</td>
                </tr>
            `;
            return;
        }
        
        reviews.forEach(review => {
            const row = document.createElement('tr');
            
            // Format rating stars
            let starsHtml = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= review.rating) {
                    starsHtml += '<i class="fas fa-star text-warning"></i>';
                } else {
                    starsHtml += '<i class="far fa-star text-warning"></i>';
                }
            }
            
            // Format status badge
            let statusBadge = '';
            if (review.is_approved) {
                statusBadge = '<span class="badge bg-success">Approved</span>';
            } else {
                statusBadge = '<span class="badge bg-warning">Pending</span>';
            }
            
            // Format date
            const date = new Date(review.created_at);
            const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            
            // Truncate review text
            let reviewText = review.review || 'No comment';
            if (reviewText.length > 50) {
                reviewText = reviewText.substring(0, 50) + '...';
            }
            
            // Product image
            let productImage = '';
            if (review.product_image) {
                productImage = `<img src="${baseUrlPath}/${review.product_image}" alt="${Admin.escapeHtml(review.product_name)}" class="img-thumbnail" style="max-width: 50px;">`;
            }
            
            row.innerHTML = `
                <td>${review.id}</td>
                <td>${Admin.escapeHtml(review.username)}</td>
                <td>
                    <div class="d-flex align-items-center">
                        ${productImage}
                        <div class="ms-2">${Admin.escapeHtml(review.product_name)}</div>
                    </div>
                </td>
                <td>${starsHtml}</td>
                <td>${Admin.escapeHtml(reviewText)}</td>
                <td>${statusBadge}</td>
                <td>${formattedDate}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-info view-btn" data-id="${review.id}" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success approve-btn" data-id="${review.id}" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-outline-warning reject-btn" data-id="${review.id}" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary reply-btn" data-id="${review.id}" title="Reply">
                            <i class="fas fa-reply"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger delete-btn" data-id="${review.id}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            reviewsTableBody.appendChild(row);
        });
        
        // Add event listeners to buttons
        addReviewButtonListeners();
    }
    
    /**
     * Render pagination
     */
    function renderPagination(pagination) {
        const { total, per_page, current_page, total_pages } = pagination;
        totalPages = total_pages;
        
        // Clear pagination
        paginationElement.innerHTML = '';
        
        // Don't show pagination if there's only one page
        if (total_pages <= 1) {
            return;
        }
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${current_page === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" data-page="${current_page - 1}" aria-label="Previous">
            <span aria-hidden="true">&laquo;</span>
        </a>`;
        paginationElement.appendChild(prevLi);
        
        // Page numbers
        const maxVisiblePages = 5;
        let startPage = Math.max(1, current_page - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(total_pages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        if (startPage > 1) {
            const firstLi = document.createElement('li');
            firstLi.className = 'page-item';
            firstLi.innerHTML = `<a class="page-link" href="#" data-page="1">1</a>`;
            paginationElement.appendChild(firstLi);
            
            if (startPage > 2) {
                const ellipsisLi = document.createElement('li');
                ellipsisLi.className = 'page-item disabled';
                ellipsisLi.innerHTML = '<a class="page-link" href="#">...</a>';
                paginationElement.appendChild(ellipsisLi);
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${i === current_page ? 'active' : ''}`;
            pageLi.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
            paginationElement.appendChild(pageLi);
        }
        
        if (endPage < total_pages) {
            if (endPage < total_pages - 1) {
                const ellipsisLi = document.createElement('li');
                ellipsisLi.className = 'page-item disabled';
                ellipsisLi.innerHTML = '<a class="page-link" href="#">...</a>';
                paginationElement.appendChild(ellipsisLi);
            }
            
            const lastLi = document.createElement('li');
            lastLi.className = 'page-item';
            lastLi.innerHTML = `<a class="page-link" href="#" data-page="${total_pages}">${total_pages}</a>`;
            paginationElement.appendChild(lastLi);
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${current_page === total_pages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" data-page="${current_page + 1}" aria-label="Next">
            <span aria-hidden="true">&raquo;</span>
        </a>`;
        paginationElement.appendChild(nextLi);
        
        // Add event listeners to pagination links
        paginationElement.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.getAttribute('data-page'));
                if (page && page !== currentPage) {
                    loadReviews(page, currentFilters, currentSort);
                }
            });
        });
    }
    
    /**
     * Add event listeners to review action buttons
     */
    function addReviewButtonListeners() {
        // View button
        document.querySelectorAll('.view-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-id');
                viewReviewDetails(reviewId);
            });
        });
        
        // Approve button
        document.querySelectorAll('.approve-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-id');
                approveReview(reviewId);
            });
        });
        
        // Reject button
        document.querySelectorAll('.reject-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-id');
                rejectReview(reviewId);
            });
        });
        
        // Reply button
        document.querySelectorAll('.reply-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-id');
                openReplyModal(reviewId);
            });
        });
        
        // Delete button
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-id');
                deleteReview(reviewId);
            });
        });
        
        // Sort by column
        document.querySelectorAll('th[data-sort]').forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.getAttribute('data-sort');
                const newOrder = currentSort.by === sortBy && currentSort.order === 'DESC' ? 'ASC' : 'DESC';
                loadReviews(1, currentFilters, { by: sortBy, order: newOrder });
            });
        });
    }
    
    /**
     * View review details
     */
    async function viewReviewDetails(reviewId) {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/reviews/${reviewId}`, {
                method: 'GET'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const review = data.data;
                
                // Populate modal with review details
                document.getElementById('viewUser').textContent = Admin.escapeHtml(review.username);
                document.getElementById('viewProduct').textContent = Admin.escapeHtml(review.product_name);
                
                // Format rating stars
                let starsHtml = '';
                for (let i = 1; i <= 5; i++) {
                    if (i <= review.rating) {
                        starsHtml += '<i class="fas fa-star text-warning"></i>';
                    } else {
                        starsHtml += '<i class="far fa-star text-warning"></i>';
                    }
                }
                document.getElementById('viewRating').innerHTML = starsHtml;
                
                // Format date
                const date = new Date(review.created_at);
                const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                document.getElementById('viewDate').textContent = formattedDate;
                
                // Status
                let statusBadge = '';
                if (review.is_approved) {
                    statusBadge = '<span class="badge bg-success">Approved</span>';
                } else {
                    statusBadge = '<span class="badge bg-warning">Pending</span>';
                }
                document.getElementById('viewStatus').innerHTML = statusBadge;
                
                // Other details
                document.getElementById('viewTitle').textContent = Admin.escapeHtml(review.title || 'No title');
                document.getElementById('viewVerified').textContent = review.is_verified_purchase ? 'Yes' : 'No';
                document.getElementById('viewHelpful').textContent = review.helpful_count;
                document.getElementById('viewReview').textContent = Admin.escapeHtml(review.review || 'No review text');
                document.getElementById('viewPros').textContent = Admin.escapeHtml(review.pros || 'No pros specified');
                document.getElementById('viewCons').textContent = Admin.escapeHtml(review.cons || 'No cons specified');
                
                // Load replies
                loadRepliesForReview(reviewId);
                
                // Show modal
                viewReviewModal.show();
            } else {
                Admin.showAlert(data.message || 'Failed to load review details.', 'danger');
            }
        } catch (error) {
            console.error('Error loading review details:', error);
            Admin.showAlert('Network error. Could not load review details.', 'danger');
        }
    }
    
    /**
     * Load replies for a review
     */
    async function loadRepliesForReview(reviewId) {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/reviews/${reviewId}/replies`, {
                method: 'GET'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            const repliesContainer = document.getElementById('viewReplies');
            
            if (data.success && data.data.length > 0) {
                let repliesHtml = '';
                
                data.data.forEach(reply => {
                    const date = new Date(reply.created_at);
                    const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                    
                    repliesHtml += `
                        <div class="card bg-secondary mb-2">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <h6>${Admin.escapeHtml(reply.username)} ${reply.is_admin_reply ? '(Admin)' : ''}</h6>
                                    <small>${formattedDate}</small>
                                </div>
                                <p class="mb-0">${Admin.escapeHtml(reply.reply)}</p>
                            </div>
                        </div>
                    `;
                });
                
                repliesContainer.innerHTML = repliesHtml;
            } else {
                repliesContainer.innerHTML = '<p class="text-muted">No replies yet.</p>';
            }
        } catch (error) {
            console.error('Error loading replies:', error);
            document.getElementById('viewReplies').innerHTML = '<p class="text-danger">Failed to load replies.</p>';
        }
    }
    
    /**
     * Approve a review
     */
    async function approveReview(reviewId) {
        if (!confirm('Are you sure you want to approve this review?')) {
            return;
        }
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/reviews/${reviewId}/approve`, {
                method: 'POST'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert('Review approved successfully.', 'success');
                loadReviews(currentPage, currentFilters, currentSort); // Reload reviews
                loadReviewStatistics(); // Reload statistics
            } else {
                Admin.showAlert(data.message || 'Failed to approve review.', 'danger');
            }
        } catch (error) {
            console.error('Error approving review:', error);
            Admin.showAlert('Network error. Could not approve review.', 'danger');
        }
    }
    
    /**
     * Reject a review
     */
    async function rejectReview(reviewId) {
        if (!confirm('Are you sure you want to reject this review?')) {
            return;
        }
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/reviews/${reviewId}/reject`, {
                method: 'POST'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert('Review rejected successfully.', 'success');
                loadReviews(currentPage, currentFilters, currentSort); // Reload reviews
                loadReviewStatistics(); // Reload statistics
            } else {
                Admin.showAlert(data.message || 'Failed to reject review.', 'danger');
            }
        } catch (error) {
            console.error('Error rejecting review:', error);
            Admin.showAlert('Network error. Could not reject review.', 'danger');
        }
    }
    
    /**
     * Delete a review
     */
    async function deleteReview(reviewId) {
        if (!confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/reviews/${reviewId}`, {
                method: 'DELETE'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert('Review deleted successfully.', 'success');
                loadReviews(currentPage, currentFilters, currentSort); // Reload reviews
                loadReviewStatistics(); // Reload statistics
            } else {
                Admin.showAlert(data.message || 'Failed to delete review.', 'danger');
            }
        } catch (error) {
            console.error('Error deleting review:', error);
            Admin.showAlert('Network error. Could not delete review.', 'danger');
        }
    }
    
    /**
     * Open reply modal
     */
    function openReplyModal(reviewId) {
        reviewIdInput.value = reviewId;
        replyTextInput.value = '';
        replyModal.show();
    }
    
    /**
     * Submit reply to review
     */
    submitReplyBtn.addEventListener('click', async function() {
        const reviewId = reviewIdInput.value;
        const replyText = replyTextInput.value.trim();
        
        if (!replyText) {
            Admin.showAlert('Please enter a reply.', 'warning');
            return;
        }
        
        // Disable button and show loading state
        submitReplyBtn.disabled = true;
        submitReplyBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/reviews/${reviewId}/reply`, {
                method: 'POST',
                body: JSON.stringify({
                    reply: replyText
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert('Reply submitted successfully.', 'success');
                replyModal.hide();
                loadReviews(currentPage, currentFilters, currentSort); // Reload reviews to show the reply
            } else {
                Admin.showAlert(data.message || 'Failed to submit reply.', 'danger');
            }
        } catch (error) {
            console.error('Error submitting reply:', error);
            Admin.showAlert('Network error. Could not submit reply.', 'danger');
        } finally {
            // Re-enable button and reset text
            submitReplyBtn.disabled = false;
            submitReplyBtn.innerHTML = 'Submit Reply';
        }
    });
    
    // Filter form submission
    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const filters = {
            status: statusFilter.value,
            rating: ratingFilter.value,
            search: searchFilter.value.trim()
        };
        
        loadReviews(1, filters, currentSort);
    });
    
    // Refresh button
    refreshBtn.addEventListener('click', function() {
        loadReviews(currentPage, currentFilters, currentSort);
        loadReviewStatistics();
    });
    
    // Load reviews and statistics on page load
    loadReviews();
    loadReviewStatistics();
});