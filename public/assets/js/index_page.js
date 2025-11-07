// public/assets/js/index_page.js

// Ensure main.js and auth.js are loaded before this script.

// Define the base URL for API calls.
const BASE_URL = 'http://localhost:8080/Lugxwebsite/public/api';

window.IndexPage = {
    trendingGamesGrid: null,
    mostPlayedGamesGrid: null,
    categoriesGridContainer: null,
    heroSearchInput: null,
    heroSearchButton: null,
    messageContainer: null,

    init: function() {
        console.log('Initializing index page specific functionalities...');

        this.trendingGamesGrid = window.select('#trending-games-grid');
        this.mostPlayedGamesGrid = window.select('#most-played-games-grid');
        this.categoriesGridContainer = window.select('#categories-grid-container');
        this.heroSearchInput = window.select('#hero-search-input');
        this.heroSearchButton = window.select('#hero-search-button');
        this.messageContainer = window.select('#message-container');

        this.setupHeroSearchEventListeners();
        this.setupFeaturesSectionAccessControl(); // Access control for static features section
        this.loadDynamicContent(); // Load dynamic content for other sections
    },

    renderProductCard: function(product, isSmallGrid = false) {
        let cardHtml = '';
        let primaryCategoryName = 'Uncategorized';
        if (product.categories && product.categories.length > 0) {
            const primaryCat = product.categories.find(cat => cat.is_primary) || product.categories[0];
            primaryCategoryName = primaryCat.name;
        }

        const productImageUrl = `../assets/img/${product.featured_image || 'placeholder.jpg'}`;

        if (isSmallGrid) {
            cardHtml = `
                <div class="game-card-small">
                    <img src="${productImageUrl}" alt="${product.title}">
                    <div class="game-info-small">
                        <span class="category">${primaryCategoryName}</span>
                        <h3>${product.title}</h3>
                        <a href="product_detail.html?slug=${product.slug}" class="explore-btn">Explore</a>
                    </div>
                </div>
            `;
        } else {
            const price = parseFloat(product.price);
            const salePrice = parseFloat(product.sale_price);

            const displayPrice = salePrice > 0 && salePrice < price
                                             ? salePrice
                                             : price;
            const oldPriceHtml = salePrice > 0 && salePrice < price
                                             ? `<span class="old-price">$${price.toFixed(2)}</span>`
                                             : '';

            cardHtml = `
                <div class="game-card">
                    <img src="${productImageUrl}" alt="${product.title}">
                    <div class="game-info">
                        <span class="category">${primaryCategoryName}</span>
                        <h3>${product.title}</h3>
                        <div class="price-info">
                            ${oldPriceHtml}
                            <span class="new-price">$${displayPrice.toFixed(2)}</span>
                        </div>
                    </div>
                    <a href="product_detail.html?slug=${product.slug}" class="play-btn">
                        <i class="fa fa-shopping-bag"></i>
                    </a>
                </div>
            `;
        }
        return cardHtml;
    },

    fetchTrendingGames: async function() {
        if (!this.trendingGamesGrid) {
            console.warn('Trending games grid not found. Skipping trending games fetch.');
            return;
        }
        this.trendingGamesGrid.innerHTML = '<p style="text-align: center; color: var(--dark-gray);">Loading trending games...</p>';

        try {
            const response = await fetch(`${BASE_URL}/products?trending=true&limit=4&sort_by=created_at&sort_order=DESC`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const result = await response.json();

            if (result.status === 'success' && result.data && result.data.products && result.data.products.length > 0) {
                this.trendingGamesGrid.innerHTML = '';
                result.data.products.forEach(product => {
                    this.trendingGamesGrid.innerHTML += this.renderProductCard(product);
                });
            } else {
                this.trendingGamesGrid.innerHTML = '<p style="text-align: center; color: var(--text-color);">No trending games to display.</p>';
                console.warn('No trending games found or API error:', result.message);
            }
        } catch (error) {
            console.error('Error fetching trending games:', error);
            this.trendingGamesGrid.innerHTML = '<p style="text-align: center; color: var(--red-discount);">Error loading trending games.</p>';
            window.displayMessage('Error loading trending games.', 'danger', this.messageContainer.id);
        }
    },

    fetchMostPlayedGames: async function() {
        if (!this.mostPlayedGamesGrid) {
            console.warn('Most played games grid not found. Skipping most played games fetch.');
            return;
        }
        this.mostPlayedGamesGrid.innerHTML = '<p style="text-align: center; color: var(--dark-gray);">Loading most played games...</p>';

        try {
            const response = await fetch(`${BASE_URL}/products?sort_by=downloads_count&sort_order=DESC&limit=5`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const result = await response.json();

            if (result.status === 'success' && result.data && result.data.products && result.data.products.length > 0) {
                this.mostPlayedGamesGrid.innerHTML = '';
                result.data.products.forEach(product => {
                    this.mostPlayedGamesGrid.innerHTML += this.renderProductCard(product, true);
                });
            } else {
                this.mostPlayedGamesGrid.innerHTML = '<p style="text-align: center; color: var(--text-color);">No most played games to display.</p>';
                console.warn('No most played games found or API error:', result.message);
            }
        } catch (error) {
            console.error('Error fetching most played games:', error);
            this.mostPlayedGamesGrid.innerHTML = '<p style="text-align: center; color: var(--red-discount);">Error loading most played games.</p>';
            window.displayMessage('Error loading most played games.', 'danger', this.messageContainer.id);
        }
    },

    fetchTopCategories: async function() {
        if (!this.categoriesGridContainer) {
            console.warn('Categories grid container not found. Skipping categories fetch.');
            return;
        }
        this.categoriesGridContainer.innerHTML = '<p style="text-align: center; color: var(--dark-gray);">Loading categories...</p>';

        try {
            const response = await fetch(`${BASE_URL}/categories?limit=6`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const result = await response.json();

            if (result.status === 'success' && result.data.length > 0) {
                this.categoriesGridContainer.innerHTML = '';
                result.data.forEach(category => {
                    const categoryCard = document.createElement('div');
                    categoryCard.className = 'category-card';
                    const categoryImageUrl = `../assets/img/${category.image || 'placeholder-category.png'}`;
                    categoryCard.innerHTML = `
                        <div class="category-icon">
                            <img src="${categoryImageUrl}" alt="${category.name}">
                        </div>
                        <h4>${category.name}</h4>
                        <a href="./shop.html?category=${category.slug}" class="category-link">Shop Now</a>
                    `;
                    this.categoriesGridContainer.appendChild(categoryCard);
                });
            } else {
                this.categoriesGridContainer.innerHTML = '<p style="text-align: center; color: var(--text-color);">No categories to display.</p>';
                console.warn('No categories found or API error:', result.message);
            }
        } catch (error) {
            console.error('Error fetching categories:', error);
            this.categoriesGridContainer.innerHTML = '<p style="text-align: center; color: var(--red-discount);">Error loading categories.</p>';
            window.displayMessage('Error loading categories.', 'danger', this.messageContainer.id);
        }
    },

    handleHeroSearch: function() {
        if (this.heroSearchInput) {
            const searchQuery = this.heroSearchInput.value.trim();
            if (searchQuery) {
                window.location.href = `./shop.html?search=${encodeURIComponent(searchQuery)}`;
            } else {
                window.displayMessage('Please enter a search query.', 'info', this.messageContainer.id);
            }
        }
    },

    setupHeroSearchEventListeners: function() {
        if (this.heroSearchInput) {
            this.heroSearchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.handleHeroSearch();
                }
            });
        }
        if (this.heroSearchButton) {
            this.heroSearchButton.addEventListener('click', this.handleHeroSearch.bind(this));
        }
    },

    loadDynamicContent: async function() {
        await this.fetchTrendingGames();
        await this.fetchMostPlayedGames();
        await this.fetchTopCategories();
    },

    setupFeaturesSectionAccessControl: function() {
        const featuresSection = window.select('.features-section');

        if (featuresSection) {
            featuresSection.addEventListener('click', async (event) => {
                const link = event.target.closest('.feature-card-link');

                if (link) {
                    const href = link.getAttribute('href');

                    if (href === './shop.html') {
                        return; // Allow direct access to shop.html
                    }

                    // --- CRITICAL CHANGE HERE ---
                    // Always prevent default navigation for restricted links immediately
                    event.preventDefault(); 

                    const authStatus = await window.Auth.checkAuthStatus();

                    if (!authStatus.logged_in) {
                        window.showToast('Please log in to access this feature.', 'info');
                        setTimeout(() => {
                            window.location.href = './login.html?return_to=' + encodeURIComponent(href);
                        }, 1500);
                    } else {
                        // If logged in, manually navigate to the intended page
                        window.location.href = href;
                    }
                }
            });
        } else {
            console.warn('Features section not found. Access control not applied.');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('index.html') || window.location.pathname === '/' || window.location.pathname === '/Lugxwebsite/public/') {
        if (typeof window.select === 'function' && typeof window.displayMessage === 'function' &&
            typeof window.Auth !== 'undefined' && typeof window.Auth.checkAuthStatus === 'function' &&
            typeof window.showToast === 'function') {
            window.IndexPage.init();
        } else {
            console.error('Required global functions or objects (main.js, auth.js) are not available. Ensure scripts are loaded in correct order.');
        }
    }
});
