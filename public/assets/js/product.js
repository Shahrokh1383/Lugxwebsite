let currentPage = 1;
let currentFilters = {
    category_slug: 'all', // Default to 'all' categories
    search_query: '',
    sort_by: 'created_at',
    sort_order: 'DESC',
    // Add other filters here as needed (e.g., price_min, price_max, platform_slug)
};
const productsLimit = 9; // Number of products to display per page

// DOM elements
const productsContainer = select('#product-grid');
const categoryFiltersContainer = select('#category-filter-container');
const paginationContainer = select('#pagination-pagination-container');
const searchInput = select('#shop-search-input');
const searchButton = select('#shop-search-button');

/**
 * Displays a loading spinner or message.
 */
function showLoading() {
    if (productsContainer) {
        productsContainer.innerHTML = '<div class="col-12"><p style="text-align: center; color: var(--dark-gray);">Loading products...</p></div>';
    }
    if (paginationContainer) {
        paginationContainer.innerHTML = ''; // Clear pagination during loading
    }
}

/**
 * Hides the loading message.
 */
function hideLoading() {
    // The content will be replaced by renderProducts, so no explicit hide needed here.
    // window.displayMessage will handle showing/hiding messages.
}

/**
 * Fetches categories from the backend API and renders them as filter buttons.
 */
async function fetchCategories() {
    if (!categoryFiltersContainer) {
        console.warn('Category filters container not found. Skipping category fetch.');
        return;
    }

    try {
        const response = await fetch(`${window.API_BASE_URL}/categories`); // Use window.API_BASE_URL
        const result = await window.handleApiResponse(response);

        if (result.status === 'success' && result.data && result.data.length > 0) {
            categoryFiltersContainer.innerHTML = ''; // Clear all existing buttons

            // Add "Show All" button dynamically
            const showAllBtn = document.createElement('button');
            showAllBtn.className = 'filter-btn';
            showAllBtn.setAttribute('data-category-slug', 'all');
            showAllBtn.textContent = 'SHOW ALL';
            showAllBtn.addEventListener('click', handleFilterClick);
            categoryFiltersContainer.appendChild(showAllBtn);

            result.data.forEach(category => {
                const button = document.createElement('button');
                button.className = 'filter-btn';
                button.setAttribute('data-category-slug', category.slug);
                button.textContent = category.name.toUpperCase();
                button.addEventListener('click', handleFilterClick);
                categoryFiltersContainer.appendChild(button);
            });

            // Set active class for the currently selected category
            const currentCategoryButton = select(`.filter-btn[data-category-slug="${currentFilters.category_slug}"]`);
            if (currentCategoryButton) {
                currentCategoryButton.classList.add('active');
            } else {
                // If no specific category is active, default to "SHOW ALL"
                showAllBtn.classList.add('active');
            }

        } else {
            console.warn('No categories found or API error:', result.message);
            window.displayMessage(result.message || 'No categories available for filtering.', 'info', 'message-container');
        }
    } catch (error) {
        console.error('Error fetching categories:', error);
        window.displayMessage('Error loading categories for filter. Please try again.', 'danger');
    }
}

/**
 * Fetches products from the backend API based on current filters and pagination.
 */
async function fetchProducts() {
    showLoading(); // Show loading indicator

    const params = new URLSearchParams({
        limit: productsLimit,
        page: currentPage,
        sort_by: currentFilters.sort_by,
        sort_order: currentFilters.sort_order,
    });

    if (currentFilters.category_slug && currentFilters.category_slug !== 'all') {
        params.append('category', currentFilters.category_slug);
    }
    if (currentFilters.search_query) {
        params.append('search', currentFilters.search_query);
    }
    // Add other filter parameters here (e.g., price_min, price_max, platform)

    try {
        const response = await fetch(`${window.API_BASE_URL}/products?${params.toString()}`); // Use window.API_BASE_URL
        const result = await window.handleApiResponse(response);

        if (result.status === 'success' && result.data && result.data.products) { // Check result.data and result.data.products
            renderProducts(result.data.products); // Pass only the products array
            renderPagination(result.data.total_products, result.data.current_page, result.data.limit); // Pass correct pagination data
            if (result.data.products.length === 0) {
                window.displayMessage('No products found matching the applied filters.', 'info');
            } else {
                window.clearErrors(); // Clear any previous messages if products are found
            }
        } else {
            window.displayMessage(result.message || 'Error loading products.', 'danger');
            productsContainer.innerHTML = ''; // Clear products on error
            paginationContainer.innerHTML = ''; // Clear pagination on error
        }
    } catch (error) {
        console.error('Error fetching products:', error);
        window.displayMessage('An error occurred while connecting to the server to load products. Please try again.', 'danger');
        productsContainer.innerHTML = ''; // Clear products on error
        paginationContainer.innerHTML = ''; // Clear pagination on error
    } finally {
        hideLoading(); // Hide loading indicator
    }
}

/**
 * Renders product cards in the products container.
 * @param {Array} products - An array of product objects.
 */
function renderProducts(products) {
    if (!productsContainer) return;

    productsContainer.innerHTML = ''; // Clear previous products

    if (products.length === 0) {
        productsContainer.innerHTML = '<div class="col-12"><p style="text-align: center; color: var(--text-color);">No products to display.</p></div>';
        return;
    }

    products.forEach(product => {
        // Convert price and sale_price to numbers explicitly
        const price = parseFloat(product.price);
        const salePrice = parseFloat(product.sale_price);

        // Determine the price to display
        const displayPrice = salePrice > 0 && salePrice < price
                                        ? salePrice
                                        : price;
        const oldPriceHtml = salePrice > 0 && salePrice < price
                                        ? `<span class="old-price">$${price.toFixed(2)}</span>`
                                        : '';

        // Determine the main category for the card's category tag
        let primaryCategoryName = '';
        if (product.categories && product.categories.length > 0) {
            const primaryCat = product.categories.find(cat => cat.is_primary) || product.categories[0];
            primaryCategoryName = primaryCat.name;
        }

        // CORRECTED: Use window.BASE_URL for product image path consistency
        const productImageUrl = `${window.BASE_URL}/assets/img/products/${product.featured_image || 'placeholder.jpg'}`;

        const productCardWrapper = document.createElement('div'); // Wrapper div for grid column
        productCardWrapper.className = 'col-lg-4 col-md-6'; // Bootstrap grid classes

        // Use the .game-card structure
        // ADDED: Anchor tag around the product title to link to product_detail.html
        productCardWrapper.innerHTML = `
            <div class="game-card">
                <img src="${productImageUrl}" alt="${product.title}">
                <div class="game-info">
                    <span class="category">${primaryCategoryName || 'Uncategorized'}</span>
                    <h3><a href="./product_detail.html?slug=${product.slug}" class="product-title-link">${product.title}</a></h3>
                    <div class="price-info">
                        ${oldPriceHtml}
                        <span class="new-price">$${displayPrice.toFixed(2)}</span>
                    </div>
                </div>
                <div class="product-actions">
                    <button class="add-to-cart-btn" data-product-id="${product.id}" title="Add to Cart">
                        <i class="fa fa-shopping-bag"></i>
                    </button>
                    <button class="add-to-wishlist-btn" data-product-id="${product.id}" title="Add to Wishlist">
                        <i class="fa fa-heart"></i>
                    </button>
                </div>
            </div>
        `;
        productsContainer.appendChild(productCardWrapper);
    });
}

/**
 * Renders pagination links.
 * @param {number} totalProducts - Total number of products.
 * @param {number} currentPage - Current active page.
 * @param {number} limit - Number of products per page.
 */
function renderPagination(totalProducts, currentPage, limit) {
    if (!paginationContainer) return;

    paginationContainer.innerHTML = ''; // Clear previous pagination
    const totalPages = Math.ceil(totalProducts / limit);

    if (totalPages <= 1) {
        return; // No pagination needed for 1 or less pages
    }

    const ul = document.createElement('ul');
    ul.className = 'pagination-list'; // Assuming you have styles for this class

    // Previous button
    const prevLi = document.createElement('li');
    const prevLink = document.createElement('a');
    prevLink.href = '#';
    prevLink.innerHTML = '&lt;';
    prevLink.classList.toggle('disabled', currentPage === 1);
    prevLink.addEventListener('click', (e) => {
        e.preventDefault();
        if (currentPage > 1) {
            currentPage--;
            fetchProducts();
            window.scrollTo({ top: 0, behavior: 'smooth' }); // Scroll to top on page change
        }
    });
    prevLi.appendChild(prevLink);
    ul.appendChild(prevLi);

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        const li = document.createElement('li');
        const pageLink = document.createElement('a');
        pageLink.href = '#';
        pageLink.textContent = i;
        if (i === currentPage) {
            pageLink.classList.add('active');
        }
        pageLink.addEventListener('click', (e) => {
            e.preventDefault();
            currentPage = i;
            fetchProducts();
            window.scrollTo({ top: 0, behavior: 'smooth' }); // Scroll to top on page change
        });
        li.appendChild(pageLink);
        ul.appendChild(li);
    }

    // Next button
    const nextLi = document.createElement('li');
    const nextLink = document.createElement('a');
    nextLink.href = '#';
    nextLink.innerHTML = '&gt;';
    nextLink.classList.toggle('disabled', currentPage === totalPages);
    nextLink.addEventListener('click', (e) => {
        e.preventDefault();
        if (currentPage < totalPages) {
            currentPage++;
            fetchProducts();
            window.scrollTo({ top: 0, behavior: 'smooth' }); // Scroll to top on page change
        }
    });
    nextLi.appendChild(nextLink);
    ul.appendChild(nextLi);

    paginationContainer.appendChild(ul);
}

/**
 * Handles clicks on category filter buttons.
 * @param {Event} event - The click event.
 */
function handleFilterClick(event) {
    const clickedButton = event.currentTarget;
    const categorySlug = clickedButton.getAttribute('data-category-slug');

    // Remove 'active' class from all filter buttons
    window.selectAll('.filter-btn').forEach(btn => { // Use window.selectAll
        btn.classList.remove('active');
    });

    // Add 'active' class to the clicked button
    clickedButton.classList.add('active');

    currentFilters.category_slug = categorySlug;
    currentPage = 1; // Reset to first page when applying a new filter
    fetchProducts();
}

/**
 * Handles search input and button clicks.
 */
function handleSearch() {
    if (searchInput) {
        currentFilters.search_query = searchInput.value.trim();
        currentPage = 1; // Reset to first page on new search
        fetchProducts();
    }
}

/**
 * Initializes the shop page by fetching categories and products.
 */
async function initializeShopPage() {
    console.log('Initializing shop page...');

    // Add event listeners for search input and button
    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                handleSearch();
            }
        });
    }
    if (searchButton) {
        searchButton.addEventListener('click', handleSearch);
    }

    // Event delegation for Add to Cart and Add to Wishlist buttons
    if (productsContainer) {
        productsContainer.addEventListener('click', (event) => {
            const target = event.target.closest('button.add-to-cart-btn, button.add-to-wishlist-btn');
            if (target) {
                event.preventDefault(); // Prevent default button behavior if any
                const productId = parseInt(target.getAttribute('data-product-id'));
                if (isNaN(productId)) {
                    console.error('Invalid product ID for cart/wishlist button:', target.getAttribute('data-product-id'));
                    return;
                }

                if (target.classList.contains('add-to-cart-btn')) {
                    // CORRECTED: Removed productPrice argument as cart.js addItemToCart does not need it
                    if (window.Cart && typeof window.Cart.addItemToCart === 'function') {
                        window.Cart.addItemToCart(productId, 1); // Default quantity to 1
                    } else {
                        window.showToast('Cart functionality not loaded.', 'error');
                        console.error('cart.js addItemToCart function not available.');
                    }
                } else if (target.classList.contains('add-to-wishlist-btn')) {
                    // Call wishlist.js function
                    // CORRECTED: Changed window.Wishlist to window.wishlist (lowercase 'w')
                    if (window.wishlist && typeof window.wishlist.addToWishlist === 'function') {
                        window.wishlist.addToWishlist(productId);
                    } else {
                        window.showToast('Wishlist functionality not loaded.', 'error');
                        console.error('wishlist.js addToWishlist function not available.');
                    }
                }
            }
        });
    }


    // Check URL parameters for initial filters (e.g., if coming from index.html "View All" links)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('category')) {
        currentFilters.category_slug = urlParams.get('category');
    }
    if (urlParams.has('search')) {
        currentFilters.search_query = urlParams.get('search');
        if (searchInput) {
            searchInput.value = currentFilters.search_query; // Populate search input if coming from search
        }
    }
    // Add checks for 'trending', 'most_played' etc. if shop.html is reused for those pages
    // Example: if (urlParams.has('trending')) { currentFilters.is_trending = true; }

    await fetchCategories(); // Fetch and render category filters
    await fetchProducts(); // Fetch and render products
}

// Event listener to initialize the shop page when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
    // Ensure select, displayMessage, and showToast are available from main.js
    if (typeof window.select === 'function' && typeof window.displayMessage === 'function' && typeof window.showToast === 'function' && typeof window.handleApiResponse === 'function' && typeof window.preparePostRequest === 'function') {
        initializeShopPage();
    } else {
        console.error('Required functions (select, displayMessage, showToast, handleApiResponse, preparePostRequest) from main.js or other libs not available. Ensure main.js is loaded before product.js.');
    }
});
