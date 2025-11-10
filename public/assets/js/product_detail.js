const BASE_ASSET_URL = 'http://localhost:8080/Lugxwebsite/public';

// DOM Elements for product details.
let productDetailContainer; // Added this line to select the main container
let productMainImage;
let productGallery;
let productCategory;
let productTitle;
let productOldPrice;
let productNewPrice;
let productShortDescription;
let productGameId;
let productGenres;
let productTags;
let productPublisherLink;
let productDeveloperLink;
let productPlatforms;
let productAverageRating;
let productReviewsCount;
let productViews;
let productSales;
let productDownloads;
let productQuantityInput;
let quantityMinusBtn;
let quantityPlusBtn;
let addToCartBtn;
let addToWishlistBtn;
let productFullDescription;
let reviewsTabCount;
let productReviewsList;
let minRequirementsList;
let recRequirementsList;
let relatedProductsGrid;
let productBreadcrumbName;

// Tab buttons
let descriptionTabBtn;
let reviewsTabBtn;
let requirementsTabBtn;

// Tab panes
let descriptionTabPane;
let reviewsTabPane;
let requirementsTabPane;

// Variable to store the current product ID and Slug
let currentProductId = null;
let currentProductSlug = null; // Added to store the product slug

/**
 * Helper function to handle API responses, checking for success, errors, and JSON parsing issues.
 * This function is now expected to be provided by main.js (window.handleApiResponse).
 */
const handleApiResponse = window.handleApiResponse;

/**
 * Helper function to convert a rating number to star icon HTML with specific classes for styling.
 * Assumes Font Awesome is loaded.
 * @param {number} rating - The numerical rating (e.g., 3.5, 4).
 * @returns {string} The HTML string containing Font Awesome star icons.
 */
function getStarCharacters(rating) {
    // We add classes to differentiate them for CSS styling
    const fullStarIcon = '<i class="fa fa-star filled"></i>';
    const halfStarIcon = '<i class="fa fa-star-half-alt half-filled"></i>';
    const emptyStarIcon = '<i class="fa fa-star-o empty"></i>'; // Using fa-star-o for outlined empty star
    // If you prefer a solid gray empty star, use: const emptyStarIcon = '<i class="fa fa-star empty"></i>';

    const totalStars = 5;
    let starsHtml = '';
    let currentRating = parseFloat(rating) || 0;

    for (let i = 1; i <= totalStars; i++) {
        if (currentRating >= i) {
            starsHtml += fullStarIcon;
        } else if (currentRating >= i - 0.5) {
            starsHtml += halfStarIcon;
        } else {
            starsHtml += emptyStarIcon;
        }
    }
    return starsHtml;
}


/**
 * Displays a loading message in a given container.
 * @param {HTMLElement} container - The DOM element to show the loading message in.
 * @param {string} message - The loading message.
 */
function showLoading(container, message = 'Loading...') {
    if (container) {
        container.innerHTML = `<p style="text-align: center; color: var(--dark-gray);">${message}</p>`;
    }
}

/**
 * Renders the main product details on the page.
 * @param {Object} product - The product data object.
 */
function renderProductDetails(product) {
    if (!product) {
        if (typeof window.showToast === 'function') {
            window.showToast('Product not found.', 'danger');
        }
        return;
    }

    currentProductId = product.id; // Store the product ID for cart/wishlist operations
    currentProductSlug = product.slug; // Store the product slug for review operations

    // Set page title
    document.title = `Lugx Gaming - ${product.title}`;

    // Update breadcrumb
    if (productBreadcrumbName) {
        productBreadcrumbName.textContent = product.title;
    }

    // Main Image - FIXED PATH
    if (productMainImage) {
        const productImageUrl = product.featured_image 
            ? `${BASE_ASSET_URL}/${product.featured_image}` 
            : `${BASE_ASSET_URL}/assets/img/placeholder.jpg`;
        productMainImage.src = productImageUrl;
        productMainImage.alt = product.title;
    }

    // Gallery Images
    renderGallery(product.gallery);

    // Basic Info
    if (productCategory) {
        const primaryCategory = product.categories && product.categories.length > 0
                                ? (product.categories.find(cat => cat.is_primary) || product.categories[0])
                                : null;
        productCategory.textContent = primaryCategory ? primaryCategory.name : 'Uncategorized';
    }
    if (productTitle) {
        productTitle.textContent = product.title;
    }

    // Prices
    if (productOldPrice) {
        if (product.sale_price > 0 && product.sale_price < product.price) {
            productOldPrice.textContent = `$${parseFloat(product.price).toFixed(2)}`;
            productOldPrice.style.textDecoration = 'line-through';
        } else {
            productOldPrice.textContent = '';
            productOldPrice.style.textDecoration = 'none';
        }
    }
    if (productNewPrice) {
        const displayPrice = parseFloat(product.sale_price) > 0 && parseFloat(product.sale_price) < parseFloat(product.price)
                                        ? parseFloat(product.sale_price)
                                        : parseFloat(product.price);
        productNewPrice.textContent = `$${displayPrice.toFixed(2)}`;
    }

    // Descriptions
    if (productShortDescription) {
        productShortDescription.innerHTML = product.short_description || 'No short description available.';
    }
    if (productFullDescription) {
        productFullDescription.innerHTML = product.description || 'No full description available.';
    }

    // Game Details List
    if (productGameId) {
        productGameId.textContent = product.sku || 'N/A'; // Assuming SKU as Game ID
    }
    if (productGenres) {
        productGenres.textContent = product.categories ? product.categories.map(cat => cat.name).join(', ') : 'N/A';
    }
    if (productTags) {
        productTags.textContent = product.tags ? product.tags.map(tag => tag.name).join(', ') : 'N/A';
    }
    if (productPublisherLink) {
        if (product.publisher) {
            productPublisherLink.textContent = product.publisher.name;
            productPublisherLink.href = `#`; // Link to publisher page if available
        } else {
            productPublisherLink.textContent = 'N/A';
            productPublisherLink.removeAttribute('href');
        }
    }
    if (productDeveloperLink) {
        if (product.developer) {
            productDeveloperLink.textContent = product.developer.name;
            productDeveloperLink.href = `#`; // Link to developer page if available
        } else {
            productDeveloperLink.textContent = 'N/A';
            productDeveloperLink.removeAttribute('href');
        }
    }
    if (productPlatforms) {
        productPlatforms.textContent = product.platforms ? product.platforms.map(plat => plat.name).join(', ') : 'N/A';
    }

    // === MODIFIED SECTION FOR AVERAGE RATING STARS ===
    if (productAverageRating) {
        const averageRating = parseFloat(product.average_rating) || 0;
        productAverageRating.textContent = averageRating.toFixed(1); // Display the numerical rating

        const starsDisplay = window.select('.stars-display');
        if (starsDisplay) {
            // Get the full HTML string of star icons from the helper function
            const starIconsHtml = getStarCharacters(averageRating);

            // Directly set the innerHTML of the stars-display span
            starsDisplay.innerHTML = starIconsHtml;

            // Keep data-rating attribute for potential other JS logic (e.g., if you click on stars)
            starsDisplay.setAttribute('data-rating', averageRating);
        }
    }
    // === END OF MODIFIED SECTION ===

    if (productReviewsCount) {
        productReviewsCount.textContent = product.reviews_count || '0';
        if (reviewsTabCount) { // Update reviews tab count
            reviewsTabCount.textContent = product.reviews_count || '0';
        }
    }
    if (productViews) {
        productViews.textContent = product.views_count || '0';
    }
    if (productSales) {
        productSales.textContent = product.sales_count || '0';
    }
    if (productDownloads) {
        productDownloads.textContent = product.downloads_count || '0';
    }

    // System Requirements
    renderSystemRequirements(product.system_requirements);

    // Related Products (will be fetched separately, but ensure container is ready)
    renderRelatedProducts(product.related_products || []); // Ensure related_products is an array

    // AFTER product details are loaded, initialize review module
    if (typeof window.reviewModule !== 'undefined' && typeof window.reviewModule.init !== 'undefined') {
        console.log('Initializing review module with product ID:', currentProductId);
        window.reviewModule.init(currentProductId, productDetailContainer); // Pass currentProductId and the main container
    } else {
        console.warn('Review module (review.js) not fully loaded or available.');
        // Optionally, hide review form/messages if review.js is not present
        const reviewFormContainer = window.select('#review-form-container');
        const reviewStatusMessages = window.select('#review-status-messages');
        if (reviewFormContainer) reviewFormContainer.style.display = 'none';
        if (reviewStatusMessages) reviewStatusMessages.style.display = 'block';
        const messageNotLoggedIn = window.select('#message-not-logged-in');
        if (messageNotLoggedIn) messageNotLoggedIn.style.display = 'block';
        if (productReviewsList) productReviewsList.innerHTML = '<p style="text-align: center; color: var(--dark-gray);">Review functionality is not available.</p>';
    }
}

/**
 * Renders gallery images.
 * @param {Array} galleryImages - Array of image URLs.
 */
function renderGallery(galleryImages) {
    if (!productGallery) return;

    productGallery.innerHTML = ''; // Clear existing gallery

    if (galleryImages && galleryImages.length > 0) {
        galleryImages.forEach(imageUrl => {
            const imgElement = document.createElement('img');
            // FIXED PATH
            const galleryImageUrl = imageUrl 
                ? `${BASE_ASSET_URL}/${imageUrl}` 
                : `${BASE_ASSET_URL}/assets/img/placeholder.jpg`;
            imgElement.src = galleryImageUrl;
            imgElement.alt = 'Product Gallery Image';
            imgElement.classList.add('gallery-thumbnail'); // Add a class for styling
            imgElement.addEventListener('click', () => {
                if (productMainImage) {
                    // FIXED PATH
                    const mainImageUrl = imageUrl 
                        ? `${BASE_ASSET_URL}/${imageUrl}` 
                        : `${BASE_ASSET_URL}/assets/img/placeholder.jpg`;
                    productMainImage.src = mainImageUrl;
                }
            });
            productGallery.appendChild(imgElement);
        });
    } else {
        // If no gallery images, maybe just show a message or hide the container
    }
}

/**
 * Renders system requirements.
 * @param {Object} requirements - Object containing min and recommended requirements.
 */
function renderSystemRequirements(requirements) {
    if (!minRequirementsList || !recRequirementsList) return;

    minRequirementsList.innerHTML = '';
    recRequirementsList.innerHTML = '';

    if (requirements && typeof requirements === 'object') {
        // Minimum Requirements
        if (requirements.minimum && Object.keys(requirements.minimum).length > 0) {
            for (const key in requirements.minimum) {
                const li = document.createElement('li');
                li.innerHTML = `<strong>${key.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase())}:</strong> ${requirements.minimum[key]}`;
                minRequirementsList.appendChild(li);
            }
        } else {
            minRequirementsList.innerHTML = '<li>No minimum requirements specified.</li>';
        }

        // Recommended Requirements
        if (requirements.recommended && Object.keys(requirements.recommended).length > 0) {
            for (const key in requirements.recommended) {
                const li = document.createElement('li');
                li.innerHTML = `<strong>${key.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase())}:</strong> ${requirements.recommended[key]}`;
                recRequirementsList.appendChild(li);
            }
        } else {
            recRequirementsList.innerHTML = '<li>No recommended requirements specified.</li>';
        }
    } else {
        minRequirementsList.innerHTML = '<li>No system requirements specified.</li>';
        recRequirementsList.innerHTML = '<li>No system requirements specified.</li>';
    }
}

/**
 * Renders related product cards.
 * @param {Array} products - An array of related product objects.
 */
function renderRelatedProducts(products) {
    if (!relatedProductsGrid) return;

    relatedProductsGrid.innerHTML = ''; // Clear previous products

    if (products.length === 0) {
        relatedProductsGrid.innerHTML = '<p style="text-align: center; color: var(--text-color);">No related products to display.</p>';
        return;
    }

    products.forEach(product => {
        const displayPrice = parseFloat(product.sale_price) > 0 && parseFloat(product.sale_price) < parseFloat(product.price)
                                        ? parseFloat(product.sale_price)
                                        : parseFloat(product.price);
        const oldPriceHtml = parseFloat(product.sale_price) > 0 && parseFloat(product.sale_price) < parseFloat(product.price)
                                        ? `<span class="old-price">$${parseFloat(product.price).toFixed(2)}</span>`
                                        : '';

        const productCard = document.createElement('div');
        productCard.className = 'game-card'; // Reusing game-card class from shop.html
        
        // --- FINAL FIX FOR RELATED PRODUCTS IMAGE PATH ---
        // Using the same BASE_ASSET_URL pattern from index_page.js, but with 'thumbnail'
        // because the related products API likely provides this key.
        const relatedProductImageUrl = product.thumbnail 
            ? `${BASE_ASSET_URL}/${product.thumbnail}` 
            : `${BASE_ASSET_URL}/assets/img/placeholder.jpg`;
        
        productCard.innerHTML = `
            <img src="${relatedProductImageUrl}" alt="${product.name || product.title}">
            <div class="game-info">
                <h3>${product.name || product.title}</h3>
                <div class="price-info">
                    ${oldPriceHtml}
                    <span class="new-price">$${displayPrice.toFixed(2)}</span>
                </div>
            </div>
            <a href="product_detail.html?slug=${product.slug}" class="play-btn">
                <i class="fa fa-shopping-bag"></i>
            </a>
        `;
        relatedProductsGrid.appendChild(productCard);
    });
}

/**
 * Fetches product details from the API.
 * @param {string} productSlug - The slug of the product to fetch.
 */
async function fetchProductDetails(productSlug) {
    // Show loading messages for all relevant sections
    showLoading(productTitle, 'Loading Product...');
    showLoading(productShortDescription, 'Loading short description...');
    showLoading(productFullDescription, 'Loading full description...');
    showLoading(minRequirementsList, 'Loading minimum requirements...');
    showLoading(recRequirementsList, 'Loading recommended requirements...');
    showLoading(productReviewsList, 'Loading reviews...');
    showLoading(relatedProductsGrid, 'Loading related games...');

    try {
        const response = await fetch(`${window.API_BASE_URL}/products/${productSlug}`);
        const result = await handleApiResponse(response);

        if (result.status === 'success' && result.data) {
            renderProductDetails(result.data);
            // Reviews will be loaded by review.js in a later phase, but ensure initial state
            // Initial state set in renderProductDetails based on reviewModule.init()
        } else {
            if (typeof window.showToast === 'function') {
                window.showToast(result.message || 'Error loading product details.', 'danger');
            }
            console.error('API error:', result.message);
            // Clear all dynamic content areas or show a specific error message
            if (productTitle) productTitle.textContent = 'Error Loading Product';
            if (productShortDescription) productShortDescription.textContent = '';
            if (productFullDescription) productFullDescription.textContent = '';
            // FIXED PATH
            if (productMainImage) productMainImage.src = `${BASE_ASSET_URL}/assets/img/placeholder.jpg`;
            if (productGallery) productGallery.innerHTML = '';
            if (minRequirementsList) minRequirementsList.innerHTML = '<li>Error loading requirements.</li>';
            if (recRequirementsList) recRequirementsList.innerHTML = '<li>Error loading requirements.</li>';
            if (productReviewsList) productReviewsList.innerHTML = '<p style="text-align: center; color: var(--red-discount);">Error loading reviews.</p>';
            if (relatedProductsGrid) relatedProductsGrid.innerHTML = '<p style="text-align: center; color: var(--red-discount);">Error loading related games.</p>';
        }
    } catch (error) {
        console.error('Error fetching product details:', error);
        if (typeof window.showToast === 'function') {
            window.showToast('An error occurred while connecting to the server to load product details.', 'danger');
        }
        // Clear all dynamic content areas or show a specific error message
        if (productTitle) productTitle.textContent = 'Error Loading Product';
        if (productShortDescription) productShortDescription.textContent = '';
        if (productFullDescription) productFullDescription.textContent = '';
        // FIXED PATH
        if (productMainImage) productMainImage.src = `${BASE_ASSET_URL}/assets/img/placeholder.jpg`;
        if (productGallery) productGallery.innerHTML = '';
        if (minRequirementsList) minRequirementsList.innerHTML = '<li>Error loading requirements.</li>';
        if (recRequirementsList) recRequirementsList.innerHTML = '<li>Error loading requirements.</li>';
        if (productReviewsList) productReviewsList.innerHTML = '<p style="text-align: center; color: var(--red-discount);">Error loading reviews.</p>';
        if (relatedProductsGrid) relatedProductsGrid.innerHTML = '<p style="text-align: center; color: var(--red-discount);">Error loading related games.</p>';
    }
}

/**
 * Handles quantity changes for the product.
 * @param {Event} event - The click event.
 */
function handleQuantityChange(event) {
    let currentQuantity = parseInt(productQuantityInput.value);
    if (isNaN(currentQuantity)) {
        currentQuantity = 1; // Default to 1 if invalid
    }

    if (event.currentTarget.id === 'quantity-plus-btn') {
        currentQuantity++;
    } else if (event.currentTarget.id === 'quantity-minus-btn') {
        currentQuantity--;
    }

    // Ensure quantity is at least 1
    if (currentQuantity < 1) {
        currentQuantity = 1;
    }

    productQuantityInput.value = currentQuantity;
}

/**
 * Handles tab clicks to switch content.
 * @param {Event} event - The click event.
 */
function handleTabClick(event) {
    // Remove 'active' from all tab buttons and panes
    window.selectAll('.tabs .tab-item').forEach(btn => btn.classList.remove('active'));
    window.selectAll('.tab-pane').forEach(pane => pane.classList.remove('active', 'show'));

    // Add 'active' to the clicked button
    const clickedTabBtn = event.currentTarget;
    clickedTabBtn.classList.add('active');

    // Show the corresponding tab pane
    const targetTabId = clickedTabBtn.getAttribute('data-tab');
    const targetTabPane = window.select(`#${targetTabId}`);
    if (targetTabPane) {
        targetTabPane.classList.add('active', 'show');
    }

    // If reviews tab is clicked, ensure reviews are loaded
    if (targetTabId === 'reviews' && currentProductId && typeof window.reviewModule !== 'undefined' && typeof window.reviewModule.loadReviews !== 'undefined') {
        window.reviewModule.loadReviews(currentProductId);
    }
}

/**
 * Handles the Add to Cart button click.
 * Connects to window.Cart.addItemToCart.
 */
async function handleAddToCartClick() {
    const quantity = parseInt(productQuantityInput.value);

    if (isNaN(quantity) || quantity < 1) {
        if (typeof window.showToast === 'function') {
            window.showToast('Please enter a valid quantity (at least 1).', 'warning');
        }
        return;
    }

    if (currentProductId === null) {
        console.error('Product ID is not set. Cannot add to cart.');
        if (typeof window.showToast === 'function') {
            window.showToast('Error: Could not determine product to add to cart.', 'danger');
        }
        return;
    }

    if (typeof window.Cart === 'undefined' || typeof window.Cart.addItemToCart !== 'function') {
        console.error('Cart functionality (cart.js) is not loaded or available.');
        if (typeof window.showToast === 'function') {
            window.showToast('Shopping cart functionality is not available. Please ensure cart.js is loaded.', 'danger');
        }
        return;
    }

    const success = await window.Cart.addItemToCart(currentProductId, quantity);
}

/**
 * Handles the Add to Wishlist button click.
 * Connects to window.Wishlist.addToWishlist.
 */
async function handleAddToWishlistClick() {
    if (currentProductId === null) {
        console.error('Product ID is not set. Cannot add to wishlist.');
        if (typeof window.showToast === 'function') {
            window.showToast('Error: Could not determine product to add to wishlist.', 'danger');
        }
        return;
    }

    if (typeof window.wishlist === 'undefined' || typeof window.wishlist.addToWishlist !== 'function') {
        console.error('Wishlist functionality (wishlist.js) is not loaded or available.');
        if (typeof window.showToast === 'function') {
            window.showToast('Wishlist functionality is not available. Please ensure wishlist.js is loaded.', 'danger');
        }
        return;
    }

    const success = await window.wishlist.addToWishlist(currentProductId);
}


/**
 * Initializes the product detail page.
 */
function initializeProductDetailPage() {
    console.log('Initializing product detail page...');

    // Select the main product detail container
    productDetailContainer = window.select('#product-detail-container');
    if (!productDetailContainer) {
        console.error('Product detail container with ID #product-detail-container not found.');
        // Exit early if the main container isn't found, as other elements depend on it
        return;
    }

    // Initialize DOM elements
    productMainImage = window.select('#product-main-image');
    productGallery = window.select('#product-gallery');
    productCategory = window.select('#product-category');
    productTitle = window.select('#product-title');
    productOldPrice = window.select('#product-old-price');
    productNewPrice = window.select('#product-new-price');
    productShortDescription = window.select('#product-short-description');
    productGameId = window.select('#product-game-id');
    productGenres = window.select('#product-genres');
    productTags = window.select('#product-tags');
    productPublisherLink = window.select('#product-publisher-link');
    productDeveloperLink = window.select('#product-developer-link');
    productPlatforms = window.select('#product-platforms');
    productAverageRating = window.select('#product-average-rating');
    productReviewsCount = window.select('#product-reviews-count');
    productViews = window.select('#product-views');
    productSales = window.select('#product-sales');
    productDownloads = window.select('#product-downloads');
    productQuantityInput = window.select('#product-quantity-input');
    quantityMinusBtn = window.select('#quantity-minus-btn');
    quantityPlusBtn = window.select('#quantity-plus-btn');
    addToCartBtn = window.select('#add-to-cart-btn');
    addToWishlistBtn = window.select('#add-to-wishlist-btn');
    productFullDescription = window.select('#product-full-description');
    reviewsTabCount = window.select('#reviews-tab-count');
    productReviewsList = window.select('#product-reviews-list');
    minRequirementsList = window.select('#min-requirements-list');
    recRequirementsList = window.select('#rec-requirements-list');
    relatedProductsGrid = window.select('#related-products-grid');
    productBreadcrumbName = window.select('#product-breadcrumb-name');

    // Tab buttons
    descriptionTabBtn = window.select('#description-tab-btn');
    reviewsTabBtn = window.select('#reviews-tab-btn');
    requirementsTabBtn = window.select('#requirements-tab-btn');

    // Tab panes
    descriptionTabPane = window.select('#description');
    reviewsTabPane = window.select('#reviews');
    requirementsTabPane = window.select('#requirements');

    // Get product slug from URL
    const urlParams = new URLSearchParams(window.location.search);
    const productSlug = urlParams.get('slug');

    if (productSlug) {
        fetchProductDetails(productSlug);
    } else {
        if (typeof window.showToast === 'function') {
            window.showToast('No product specified in the URL.', 'danger');
        }
        if (productTitle) productTitle.textContent = 'Product Not Found';
        if (productShortDescription) productShortDescription.textContent = '';
        if (productFullDescription) productFullDescription.textContent = '';
    }

    // Add event listeners for quantity buttons
    if (quantityPlusBtn) {
        quantityPlusBtn.addEventListener('click', handleQuantityChange);
    }
    if (quantityMinusBtn) {
        quantityMinusBtn.addEventListener('click', handleQuantityChange);
    }

    // Add event listener for Add to Cart button
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', handleAddToCartClick);
    }

    // Add event listener for Add to Wishlist button
    if (addToWishlistBtn) {
        addToWishlistBtn.addEventListener('click', handleAddToWishlistClick);
    }

    // Add event listeners for tab buttons
    if (descriptionTabBtn) {
        descriptionTabBtn.addEventListener('click', handleTabClick);
    }
    if (reviewsTabBtn) {
        reviewsTabBtn.addEventListener('click', handleTabClick);
    }
    if (requirementsTabBtn) {
        requirementsTabBtn.addEventListener('click', handleTabClick);
    }

    // Initial tab activation (ensure description is active by default)
    if (descriptionTabPane) {
        descriptionTabPane.classList.add('active', 'show');
    }
}

// Event listener to initialize the page when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
    // Ensure select and showToast are available from main.js
    if (typeof window.select === 'function' && typeof window.selectAll === 'function' && typeof window.showToast === 'function' && typeof window.handleApiResponse === 'function') {
        initializeProductDetailPage();
    } else {
        console.error('Required main.js functions (select, selectAll, showToast, handleApiResponse) not available. Ensure main.js is loaded before product_detail.js.');
    }
});