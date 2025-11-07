// public/assets/js/user_game_library.js

// Ensure main.js and auth.js are loaded before this script.

window.UserGameLibrary = {
    elements: {
        gameLibraryContainer: null,
        loadingMessage: null,
        noGamesMessage: null,
        errorMessage: null,
    },

    /**
     * Initializes the game library page by caching DOM elements and loading games.
     */
    init: function() {
        console.log('Initializing user game library...');
        this.cacheElements();
        this.loadUserGames();
    },

    /**
     * Caches frequently used DOM elements.
     */
    cacheElements: function() {
        this.elements.gameLibraryContainer = window.select('#game-library-container');
        this.elements.loadingMessage = window.select('#loading-message');
        this.elements.noGamesMessage = window.select('#no-games-message');
        this.elements.errorMessage = window.select('#error-message');
    },

    /**
     * Renders a single game card for the library.
     * @param {Object} game - The game data object from the API.
     * @returns {string} The HTML string for the game card.
     */
    renderGameCard: function(game) {
        // Corrected image path: assumes product_image contains the filename directly
        const productImageUrl = `${window.BASE_URL}/assets/img/products/${game.product_image || 'placeholder.jpg'}`;
        const displayPrice = parseFloat(game.product_price).toFixed(2);
        const displaySalePrice = parseFloat(game.product_sale_price).toFixed(2);

        let priceHtml = `<span class="new-price">$${displayPrice}</span>`;
        if (game.product_sale_price > 0 && game.product_sale_price < game.product_price) {
            priceHtml = `<span class="old-price text-decoration-line-through me-2">$${displayPrice}</span> <span class="new-price text-danger">$${displaySalePrice}</span>`;
        }

        const ratingStars = this.generateStarRating(game.product_average_rating);

        // Conditional rendering for download link
        const downloadButtonHtml = game.download_link
            ? `<button class="btn btn-primary btn-sm mt-2 download-game-btn" data-download-link="${game.download_link}">
                   <i class="fas fa-download me-1"></i> Download
               </button>`
            : '';

        return `
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="game-library-card card h-100 shadow-sm rounded-3">
                    <img src="${productImageUrl}" class="card-img-top rounded-top-3" alt="${game.product_title}" onerror="this.onerror=null;this.src='https://placehold.co/300x200/cccccc/000000?text=No+Image';">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title text-dark-gray">${game.product_title}</h5>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="price-info">
                                ${priceHtml}
                            </div>
                            <div class="rating d-flex align-items-center">
                                ${ratingStars}
                                <span class="ms-1 text-medium-gray">${parseFloat(game.product_average_rating).toFixed(1)}</span>
                            </div>
                        </div>
                        <p class="card-text text-medium-gray small mb-3">
                            Order: <a href="./user_order_detail.html?order_id=${game.order_item_id}" class="text-decoration-none text-primary fw-bold">${game.order_number}</a>
                            <br>
                            Purchased on: ${new Date(game.order_date).toLocaleDateString()}
                        </p>
                        <div class="license-key-section mb-3">
                            <strong class="text-dark-gray">License Key:</strong>
                            <div class="input-group mt-1">
                                <input type="text" class="form-control form-control-sm license-key-input" value="${game.license_key}" readonly>
                                <button class="btn btn-outline-secondary btn-sm copy-key-btn" type="button" data-key="${game.license_key}">
                                    <i class="far fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <a href="./product_detail.html?slug=${game.product_slug}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-info-circle me-1"></i> Details
                            </a>
                            ${downloadButtonHtml}
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    /**
     * Generates star rating HTML.
     * @param {number} rating - The average rating (0-5).
     * @returns {string} HTML string for star icons.
     */
    generateStarRating: function(rating) {
        let starsHtml = '';
        const fullStars = Math.floor(rating);
        const halfStar = rating - fullStars >= 0.5;
        const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);

        for (let i = 0; i < fullStars; i++) {
            starsHtml += '<i class="fa fa-star text-warning"></i>';
        }
        if (halfStar) {
            starsHtml += '<i class="fa fa-star-half-alt text-warning"></i>';
        }
        for (let i = 0; i < emptyStars; i++) {
            starsHtml += '<i class="far fa-star text-warning"></i>'; // Outline star for empty
        }
        return starsHtml;
    },

    /**
     * Fetches and displays the user's purchased games.
     */
    loadUserGames: async function() {
        if (!this.elements.gameLibraryContainer) {
            console.warn('Game library container not found. Skipping game loading.');
            return;
        }

        this.elements.loadingMessage.style.display = 'block';
        this.elements.noGamesMessage.style.display = 'none';
        this.elements.errorMessage.style.display = 'none';
        this.elements.gameLibraryContainer.innerHTML = ''; // Clear previous content

        try {
            const response = await fetch(`${window.API_BASE_URL}/user/games`);
            const result = await window.handleApiResponse(response);

            if (result.status === 'success' && result.data && result.data.length > 0) {
                this.elements.loadingMessage.style.display = 'none';
                result.data.forEach(game => {
                    this.elements.gameLibraryContainer.insertAdjacentHTML('beforeend', this.renderGameCard(game));
                });
                this.attachEventListeners(); // Attach listeners after rendering all cards
            } else if (result.status === 'success' && result.data && result.data.length === 0) {
                this.elements.loadingMessage.style.display = 'none';
                this.elements.noGamesMessage.style.display = 'block';
            } else {
                // Handle API error
                this.elements.loadingMessage.style.display = 'none';
                this.elements.errorMessage.style.display = 'block';
                this.elements.errorMessage.textContent = `Error: ${result.message || 'Failed to load your game library.'}`;
                window.showToast(result.message || 'Failed to load game library.', 'danger');
            }
        } catch (error) {
            console.error('Error fetching user games:', error);
            this.elements.loadingMessage.style.display = 'none';
            this.elements.errorMessage.style.display = 'block';
            this.elements.errorMessage.textContent = 'A network error occurred. Please try again.';
            window.showToast('A network error occurred while loading your game library.', 'danger');
        }
    },

    /**
     * Attaches event listeners to dynamically created elements (e.g., copy buttons).
     */
    attachEventListeners: function() {
        // Attach copy button listeners
        window.selectAll('.copy-key-btn').forEach(button => {
            button.addEventListener('click', (event) => {
                const keyToCopy = event.currentTarget.dataset.key;
                this.copyToClipboard(keyToCopy);
            });
        });

        // Attach download button listeners
        window.selectAll('.download-game-btn').forEach(button => {
            button.addEventListener('click', (event) => {
                const downloadLink = event.currentTarget.dataset.downloadLink;
                if (downloadLink) {
                    window.open(downloadLink, '_blank');
                } else {
                    window.showToast('Download link not available for this game.', 'warning');
                }
            });
        });
    },

    /**
     * Copies text to the clipboard.
     * @param {string} text - The text to copy.
     */
    copyToClipboard: function(text) {
        // Use a temporary textarea for copying to clipboard, as navigator.clipboard.writeText might not work in iframes
        const tempTextArea = document.createElement('textarea');
        tempTextArea.value = text;
        document.body.appendChild(tempTextArea);
        tempTextArea.select();
        try {
            document.execCommand('copy');
            window.showToast('License key copied to clipboard!', 'success');
        } catch (err) {
            console.error('Failed to copy text: ', err);
            window.showToast('Failed to copy key. Please copy manually.', 'danger');
        }
        document.body.removeChild(tempTextArea);
    }
};

// Initialize the script when the DOM is fully loaded.
document.addEventListener('DOMContentLoaded', () => {
    // Check if required global utility functions are available from main.js and auth.js
    if (typeof window.select === 'function' && typeof window.selectAll === 'function' &&
        typeof window.showToast === 'function' && typeof window.handleApiResponse === 'function' &&
        typeof window.API_BASE_URL !== 'undefined' && typeof window.Auth !== 'undefined' &&
        typeof window.Auth.checkAuthStatus === 'function') {

        // The initial auth check for cart.html was moved to the HTML file itself for immediate redirection.
        // For user_game_library.html, the check is also in the HTML.
        // If we reach here, it means the user is likely authenticated, or the HTML redirect is handling it.
        // So, we can proceed with initializing the game library.
        window.UserGameLibrary.init();
    } else {
        console.error('Required global functions or objects (main.js, auth.js) are not available. Ensure scripts are loaded in correct order.');
        // Optionally, redirect to login if core functionalities are missing
        // window.location.href = './login.html';
    }
});
