// review.js
window.reviewModule = (function() {
    // متغیرهای خصوصی
    let currentProductId = null;
    let mainContainer = null;

    // عناصر DOM
    let reviewFormContainer;
    let reviewForm;
    let reviewRatingStars;
    let selectedRatingInput;
    let reviewCommentInput;
    let reviewTitleInput;
    let reviewProsInput;
    let reviewConsInput;
    let reviewFormError;
    let reviewFormSuccess;
    let productReviewsList;
    let noReviewsYet;
    let prevReviewsBtn;
    let nextReviewsBtn;
    let currentReviewPageSpan;
    let messageNotLoggedIn;
    let messageNotPurchased;
    let messageAlreadyReviewed;

    // وضعیت صفحه‌بندی
    let currentPage = 1;
    let totalPages = 1;
    let perPage = 5;

    // مقداردهی اولیه ماژول
    function init(productId, container) {
        currentProductId = productId;
        mainContainer = container;

        // انتخاب عناصر DOM
        reviewFormContainer = mainContainer.querySelector('#review-form-container');
        reviewForm = mainContainer.querySelector('#review-form');
        reviewRatingStars = mainContainer.querySelector('#review-rating-stars');
        selectedRatingInput = mainContainer.querySelector('#selected-rating');
        reviewCommentInput = mainContainer.querySelector('#review-comment');
        reviewTitleInput = mainContainer.querySelector('#review-title');
        reviewProsInput = mainContainer.querySelector('#review-pros');
        reviewConsInput = mainContainer.querySelector('#review-cons');
        reviewFormError = mainContainer.querySelector('#review-form-error');
        reviewFormSuccess = mainContainer.querySelector('#review-form-success');
        productReviewsList = mainContainer.querySelector('#product-reviews-list');
        noReviewsYet = mainContainer.querySelector('#no-reviews-yet');
        prevReviewsBtn = mainContainer.querySelector('#prev-reviews-btn');
        nextReviewsBtn = mainContainer.querySelector('#next-reviews-btn');
        currentReviewPageSpan = mainContainer.querySelector('#current-review-page');
        messageNotLoggedIn = mainContainer.querySelector('#message-not-logged-in');
        messageNotPurchased = mainContainer.querySelector('#message-not-purchased');
        messageAlreadyReviewed = mainContainer.querySelector('#message-already-reviewed');

        // بررسی وضعیت نظر کاربر
        checkReviewStatus();

        // تنظیم رویدادها
        setupEventListeners();

        // بارگذاری نظرات محصول
        loadReviews();
    }

    // بررسی وضعیت نظر کاربر برای محصول
    async function checkReviewStatus() {
        try {
            const response = await fetch(`${window.API_BASE_URL}/products/${currentProductId}/review-status`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'include'
            });

            const result = await window.handleApiResponse(response);

            if (result.status === 'success') {
                const { has_purchased, has_reviewed } = result.data;

                // نمایش/عدم نمایش پیام‌ها و فرم بر اساس وضعیت
                if (!has_purchased) {
                    showMessage('not-purchased');
                } else if (has_reviewed) {
                    showMessage('already-reviewed');
                } else {
                    // کاربر می‌تواند نظر دهد
                    hideMessages();
                    if (reviewFormContainer) {
                        reviewFormContainer.style.display = 'block';
                    }
                }
            } else {
                console.error('Failed to check review status:', result.message);
                showMessage('not-logged-in');
            }
        } catch (error) {
            console.error('Error checking review status:', error);
            showMessage('not-logged-in');
        }
    }

    // نمایش پیام خاص
    function showMessage(type) {
        hideMessages();
        switch (type) {
            case 'not-logged-in':
                if (messageNotLoggedIn) messageNotLoggedIn.style.display = 'block';
                break;
            case 'not-purchased':
                if (messageNotPurchased) messageNotPurchased.style.display = 'block';
                break;
            case 'already-reviewed':
                if (messageAlreadyReviewed) messageAlreadyReviewed.style.display = 'block';
                break;
        }
    }

    // مخفی کردن همه پیام‌ها
    function hideMessages() {
        if (messageNotLoggedIn) messageNotLoggedIn.style.display = 'none';
        if (messageNotPurchased) messageNotPurchased.style.display = 'none';
        if (messageAlreadyReviewed) messageAlreadyReviewed.style.display = 'none';
        if (reviewFormContainer) reviewFormContainer.style.display = 'none';
    }

    // تنظیم رویدادها
    function setupEventListeners() {
        // امتیازدهی با ستاره
        if (reviewRatingStars) {
            const stars = reviewRatingStars.querySelectorAll('.star');
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-value'));
                    setRating(rating);
                });

                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.getAttribute('data-value'));
                    highlightStars(rating);
                });
            });

            reviewRatingStars.addEventListener('mouseleave', function() {
                const currentRating = parseInt(selectedRatingInput.value);
                highlightStars(currentRating);
            });
        }

        // ارسال فرم نظر
        if (reviewForm) {
            reviewForm.addEventListener('submit', handleReviewSubmit);
        }

        // دکمه‌های صفحه‌بندی
        if (prevReviewsBtn) {
            prevReviewsBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    loadReviews();
                }
            });
        }

        if (nextReviewsBtn) {
            nextReviewsBtn.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    loadReviews();
                }
            });
        }
    }

    // تنظیم امتیاز و به‌روزرسانی نمایش
    function setRating(rating) {
        selectedRatingInput.value = rating;
        highlightStars(rating);
    }

    // برجسته کردن ستاره‌ها تا امتیاز مشخص
    function highlightStars(rating) {
        const stars = reviewRatingStars.querySelectorAll('.star');
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('selected');
            } else {
                star.classList.remove('selected');
            }
        });
    }

    // مدیریت ارسال فرم نظر
    async function handleReviewSubmit(event) {
        event.preventDefault();

        // پاک کردن پیام‌های قبلی
        if (reviewFormError) reviewFormError.style.display = 'none';
        if (reviewFormSuccess) reviewFormSuccess.style.display = 'none';

        // اعتبارسنجی فرم
        const rating = parseInt(selectedRatingInput.value);
        const comment = reviewCommentInput.value.trim();
        const title = reviewTitleInput.value.trim();
        const pros = reviewProsInput.value.trim();
        const cons = reviewConsInput.value.trim();

        if (rating < 1 || rating > 5) {
            showFormError('Please select a rating.');
            return;
        }

        if (comment.length < 10) {
            showFormError('Your review must be at least 10 characters long.');
            return;
        }

        // آماده‌سازی داده‌ها
        const reviewData = {
            rating,
            title,
            review: comment,
            pros,
            cons
        };

        try {
            const response = await fetch(`${window.API_BASE_URL}/products/${currentProductId}/reviews`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': await window.getCsrfToken()
                },
                body: JSON.stringify(reviewData),
                credentials: 'include'
            });

            const result = await window.handleApiResponse(response);

            if (result.status === 'success') {
                showFormSuccess('Review submitted successfully and pending approval.');
                reviewForm.reset();
                setRating(0);
                // به‌روزرسانی وضعیت نظر کاربر
                checkReviewStatus();
                // بارگذاری مجدد نظرات
                currentPage = 1;
                loadReviews();
            } else {
                showFormError(result.message || 'Failed to submit review.');
            }
        } catch (error) {
            console.error('Error submitting review:', error);
            showFormError('An error occurred while submitting your review.');
        }
    }

    // نمایش پیام خطای فرم
    function showFormError(message) {
        if (reviewFormError) {
            reviewFormError.textContent = message;
            reviewFormError.style.display = 'block';
        }
    }

    // نمایش پیام موفقیت فرم
    function showFormSuccess(message) {
        if (reviewFormSuccess) {
            reviewFormSuccess.textContent = message;
            reviewFormSuccess.style.display = 'block';
        }
    }

    // بارگذاری نظرات محصول
    async function loadReviews() {
        if (!productReviewsList) return;

        try {
            const response = await fetch(`${window.API_BASE_URL}/products/${currentProductId}/reviews?page=${currentPage}&per_page=${perPage}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'include'
            });

            const result = await window.handleApiResponse(response);

            if (result.status === 'success') {
                renderReviews(result.data);
                updatePagination(result.pagination);
            } else {
                console.error('Failed to load reviews:', result.message);
                if (productReviewsList) {
                    productReviewsList.innerHTML = '<p style="text-align: center; color: var(--dark-gray);">Failed to load reviews.</p>';
                }
            }
        } catch (error) {
            console.error('Error loading reviews:', error);
            if (productReviewsList) {
                productReviewsList.innerHTML = '<p style="text-align: center; color: var(--dark-gray);">Error loading reviews.</p>';
            }
        }
    }

    // رندر کردن لیست نظرات
    function renderReviews(reviews) {
        if (!productReviewsList) return;

        if (reviews.length === 0) {
            if (noReviewsYet) {
                noReviewsYet.style.display = 'block';
            }
            productReviewsList.innerHTML = '';
            return;
        }

        if (noReviewsYet) {
            noReviewsYet.style.display = 'none';
        }

        let reviewsHtml = '';
        reviews.forEach(review => {
            reviewsHtml += createReviewCard(review);
        });

        productReviewsList.innerHTML = reviewsHtml;

        // افزودن رویدادها برای دکمه‌های مفید بودن و فرم‌های پاسخ
        setupReviewCardEventListeners();
    }

    // ایجاد HTML برای کارت نظر
    function createReviewCard(review) {
        const date = new Date(review.created_at).toLocaleDateString();
        const starsHtml = getStarHtml(review.rating);

        let repliesHtml = '';
        if (review.replies && review.replies.length > 0) {
            repliesHtml = '<div class="review-replies">';
            review.replies.forEach(reply => {
                const replyDate = new Date(reply.created_at).toLocaleDateString();
                repliesHtml += `
                    <div class="review-reply">
                        <div class="review-reply-header">
                            <span class="reply-username">${reply.username}</span>
                            <span class="reply-date">${replyDate}</span>
                            ${reply.is_admin_reply ? '<span class="admin-badge">Admin</span>' : ''}
                        </div>
                        <div class="review-reply-body">
                            <p>${reply.reply}</p>
                        </div>
                    </div>
                `;
            });
            repliesHtml += '</div>';
        }

        return `
            <div class="review-card" data-review-id="${review.id}">
                <div class="review-card-header">
                    <div class="user-info">
                        <div class="user-avatar">${review.username ? review.username.charAt(0).toUpperCase() : 'U'}</div>
                        <div>
                            <div class="username">${review.username || 'Anonymous'}</div>
                            ${review.is_verified_purchase ? '<span class="verified-purchase">Verified Purchase</span>' : ''}
                        </div>
                    </div>
                    <div class="review-meta">
                        <div class="review-rating">${starsHtml}</div>
                        <div class="review-date">${date}</div>
                    </div>
                </div>
                <div class="review-card-body">
                    ${review.title ? `<h5>${review.title}</h5>` : ''}
                    <p>${review.review}</p>
                    ${review.pros ? `<div class="review-pros"><strong>Pros:</strong> ${review.pros}</div>` : ''}
                    ${review.cons ? `<div class="review-cons"><strong>Cons:</strong> ${review.cons}</div>` : ''}
                </div>
                ${repliesHtml}
                <div class="review-card-footer">
                    <div class="review-helpful-buttons">
                        <button class="helpful-btn ${review.user_helpful === true ? 'active' : ''}" data-review-id="${review.id}" data-helpful="true">
                            <i class="fa fa-thumbs-up"></i> Helpful (${review.helpful})
                        </button>
                        <button class="helpful-btn ${review.user_helpful === false ? 'active' : ''}" data-review-id="${review.id}" data-helpful="false">
                            <i class="fa fa-thumbs-down"></i> Unhelpful (${review.unhelpful})
                        </button>
                    </div>
                    <button class="reply-btn" data-review-id="${review.id}">Reply</button>
                </div>
                <div class="reply-form-container" style="display: none;">
                    <form class="reply-form">
                        <textarea class="form-control" rows="3" placeholder="Write your reply..." required></textarea>
                        <button type="submit" class="btn btn-primary btn-sm mt-2">Submit Reply</button>
                    </form>
                </div>
            </div>
        `;
    }

    // دریافت HTML ستاره‌ها برای امتیاز
    function getStarHtml(rating) {
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= rating) {
                starsHtml += '<i class="fa fa-star filled"></i>';
            } else {
                starsHtml += '<i class="fa fa-star-o empty"></i>';
            }
        }
        return starsHtml;
    }

    // تنظیم رویدادها برای کارت‌های نظر
    function setupReviewCardEventListeners() {
        // دکمه‌های مفید بودن
        const helpfulButtons = productReviewsList.querySelectorAll('.helpful-btn');
        helpfulButtons.forEach(button => {
            button.addEventListener('click', handleHelpfulClick);
        });

        // دکمه‌های پاسخ
        const replyButtons = productReviewsList.querySelectorAll('.reply-btn');
        replyButtons.forEach(button => {
            button.addEventListener('click', handleReplyClick);
        });

        // فرم‌های پاسخ
        const replyForms = productReviewsList.querySelectorAll('.reply-form');
        replyForms.forEach(form => {
            form.addEventListener('submit', handleReplySubmit);
        });
    }

    // مدیریت کلیک دکمه مفید بودن
    async function handleHelpfulClick(event) {
        const button = event.currentTarget;
        const reviewId = parseInt(button.getAttribute('data-review-id'));
        const isHelpful = button.getAttribute('data-helpful') === 'true';

        try {
            const response = await fetch(`${window.API_BASE_URL}/reviews/${reviewId}/helpful`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': await window.getCsrfToken()
                },
                body: JSON.stringify({ is_helpful: isHelpful }),
                credentials: 'include'
            });

            const result = await window.handleApiResponse(response);

            if (result.status === 'success') {
                // بارگذاری مجدد نظرات برای به‌روزرسانی شمارنده‌ها
                loadReviews();
            } else {
                console.error('Failed to mark review as helpful:', result.message);
                window.showToast(result.message || 'Failed to vote.', 'danger');
            }
        } catch (error) {
            console.error('Error marking review as helpful:', error);
            window.showToast('An error occurred while voting.', 'danger');
        }
    }

    // مدیریت کلیک دکمه پاسخ
    function handleReplyClick(event) {
        const button = event.currentTarget;
        const reviewCard = button.closest('.review-card');
        const replyFormContainer = reviewCard.querySelector('.reply-form-container');

        if (replyFormContainer) {
            replyFormContainer.style.display = replyFormContainer.style.display === 'none' ? 'block' : 'none';
        }
    }

    // مدیریت ارسال فرم پاسخ
    async function handleReplySubmit(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const textarea = form.querySelector('textarea');
        const replyText = textarea.value.trim();

        if (!replyText) {
            window.showToast('Reply cannot be empty.', 'warning');
            return;
        }

        const reviewCard = form.closest('.review-card');
        const reviewId = parseInt(reviewCard.getAttribute('data-review-id'));

        try {
            const response = await fetch(`${window.API_BASE_URL}/reviews/${reviewId}/reply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': await window.getCsrfToken()
                },
                body: JSON.stringify({ reply: replyText }),
                credentials: 'include'
            });

            const result = await window.handleApiResponse(response);

            if (result.status === 'success') {
                window.showToast('Reply submitted successfully.', 'success');
                form.reset();
                form.closest('.reply-form-container').style.display = 'none';
                // بارگذاری مجدد نظرات برای نمایش پاسخ جدید
                loadReviews();
            } else {
                console.error('Failed to submit reply:', result.message);
                window.showToast(result.message || 'Failed to submit reply.', 'danger');
            }
        } catch (error) {
            console.error('Error submitting reply:', error);
            window.showToast('An error occurred while submitting your reply.', 'danger');
        }
    }

    // به‌روزرسانی کنترل‌های صفحه‌بندی
    function updatePagination(pagination) {
        currentPage = pagination.current_page;
        totalPages = pagination.last_page;

        if (currentReviewPageSpan) {
            currentReviewPageSpan.textContent = `Page ${currentPage} of ${totalPages}`;
        }

        if (prevReviewsBtn) {
            prevReviewsBtn.disabled = currentPage <= 1;
        }

        if (nextReviewsBtn) {
            nextReviewsBtn.disabled = currentPage >= totalPages;
        }

        // نمایش/مخفی کردن کنترل‌های صفحه‌بندی
        const paginationControls = mainContainer.querySelector('.pagination-controls');
        if (paginationControls) {
            paginationControls.style.display = totalPages > 1 ? 'flex' : 'none';
        }
    }

    // API عمومی
    return {
        init,
        loadReviews
    };
})();