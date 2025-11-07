// admin_products.js
// This file contains all the JavaScript logic for the admin product management page.
document.addEventListener('DOMContentLoaded', function () {
    // A class to manage product-related functionalities in the admin panel
    class AdminProductManager {
        /**
         * @param {string} baseUrl The base URL of the application.
         */
        constructor(baseUrl) {
            this.baseUrl = baseUrl;
            this.currentPage = 1;
            this.currentSearchTerm = '';
            this.currentStatusFilter = '';
            this.currentProductIdToDelete = null;
            this.productExistingImages = [];
            
            // DOM elements
            this.productsTableBody = document.getElementById('productsTableBody');
            this.paginationControls = document.getElementById('paginationControls');
            this.productModalElement = document.getElementById('productModal');
            this.productModal = new bootstrap.Modal(this.productModalElement);
            this.productForm = document.getElementById('productForm');
            this.productModalTitle = document.getElementById('productModalLabel');
            this.saveProductBtn = document.getElementById('saveProductBtn');
            this.addNewProductBtn = document.getElementById('addNewProductBtn');
            this.productSearchInput = document.getElementById('productSearchInput');
            this.searchProductsBtn = document.getElementById('searchProductsBtn');
            this.productStatusFilter = document.getElementById('productStatusFilter');
            this.productsTable = document.querySelector('#productsTableBody');
            this.deleteConfirmModalElement = document.getElementById('deleteConfirmModal');
            this.deleteConfirmModal = new bootstrap.Modal(this.deleteConfirmModalElement);
            this.confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            this.addKeyBtn = document.getElementById('addKeyBtn');
            this.generateKeysBtn = document.getElementById('generateKeysBtn');
            this.productKeysContainer = document.getElementById('productKeysContainer');
            this.modalMessageContainer = document.getElementById('modalMessage');
            
            // Form inputs
            this.productIdInput = document.getElementById('productId');
            this.productNameInput = document.getElementById('productName');
            this.productSlugInput = document.getElementById('productSlug');
            this.productDescriptionInput = document.getElementById('productDescription');
            this.productShortDescriptionInput = document.getElementById('productShortDescription');
            this.productPriceInput = document.getElementById('productPrice');
            this.productSalePriceInput = document.getElementById('productSalePrice');
            this.productKeyCountInput = document.getElementById('productKeyCount');
            this.productStockStatusInput = document.getElementById('productStockStatus');
            this.productStatusInput = document.getElementById('productStatus');
            this.productReleaseDateInput = document.getElementById('productReleaseDate');
            this.productSKUInput = document.getElementById('productSKU');
            this.productAgeRatingInput = document.getElementById('productAgeRating');
            this.productFileSizeInput = document.getElementById('productFileSize');
            this.productVideoTrailerInput = document.getElementById('productVideoTrailer');
            this.productCategoriesInput = document.getElementById('productCategories');
            this.productPlatformsInput = document.getElementById('productPlatforms');
            this.productTagsInput = document.getElementById('productTags');
            this.productRelatedProductsInput = document.getElementById('productRelatedProducts');
            this.productDeveloperInput = document.getElementById('productDeveloper');
            this.productPublisherInput = document.getElementById('productPublisher');
            this.productFeaturedImageInput = document.getElementById('productFeaturedImage');
            this.productGalleryInput = document.getElementById('productGallery');
            this.featuredImagePreview = document.getElementById('featuredImagePreview');
            this.galleryPreview = document.getElementById('galleryPreview');
            this.productIsFeaturedInput = document.getElementById('productIsFeatured');
            this.productIsTrendingInput = document.getElementById('productIsTrending');
            this.productMetaTitleInput = document.getElementById('productMetaTitle');
            this.productMetaDescriptionInput = document.getElementById('productMetaDescription');
            this.csrfTokenInput = document.getElementById('productFormCsrfToken');
            
            // System requirements inputs - Minimum
            this.minReqOSInput = document.getElementById('minReqOS');
            this.minReqProcessorInput = document.getElementById('minReqProcessor');
            this.minReqMemoryInput = document.getElementById('minReqMemory');
            this.minReqGraphicsInput = document.getElementById('minReqGraphics');
            this.minReqStorageInput = document.getElementById('minReqStorage');
            
            // System requirements inputs - Recommended
            this.recReqOSInput = document.getElementById('recReqOS');
            this.recReqProcessorInput = document.getElementById('recReqProcessor');
            this.recReqMemoryInput = document.getElementById('recReqMemory');
            this.recReqGraphicsInput = document.getElementById('recReqGraphics');
            this.recReqStorageInput = document.getElementById('recReqStorage');
            
            // Initialize event listeners
            this.initEventListeners();
            
            // Load initial data
            this.loadProducts(this.currentPage);
        }
        
        /**
         * Initializes all the necessary event listeners for the page.
         */
        initEventListeners() {
            this.addNewProductBtn.addEventListener('click', () => this.openProductModal());
            this.productForm.addEventListener('submit', (e) => this.handleProductFormSubmit(e));
            this.productSearchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.handleSearch();
                }
            });
            this.searchProductsBtn.addEventListener('click', () => this.handleSearch());
            this.productStatusFilter.addEventListener('change', () => this.handleStatusFilter());
            this.productsTable.addEventListener('click', (e) => this.handleTableActions(e));
            this.confirmDeleteBtn.addEventListener('click', () => this.deleteProduct());
            this.productGalleryInput.addEventListener('change', () => this.previewNewImages());
            this.productFeaturedImageInput.addEventListener('change', () => this.previewFeaturedImage());
            
            // Handle remove button clicks on images
            this.galleryPreview.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('.remove-image-btn');
                if (removeBtn) {
                    const imageId = removeBtn.dataset.imageId;
                    const elementToRemove = removeBtn.closest('.image-preview');
                    this.removeExistingImage(imageId, elementToRemove);
                }
            });
            
            // Key management
            this.addKeyBtn.addEventListener('click', () => this.addKeyInput());
            this.generateKeysBtn.addEventListener('click', () => this.generateKeys());
            this.productKeysContainer.addEventListener('click', (e) => {
                if (e.target.closest('.remove-key-btn')) {
                    this.removeKeyInput(e.target.closest('.input-group'));
                }
            });
            
            // Update key count when key value changes
            this.productKeysContainer.addEventListener('input', (e) => {
                if (e.target.closest('.product-key-input')) {
                    this.updateKeyCountFromKeys();
                }
            });
            
            // Auto-generate slug from title
            this.productNameInput.addEventListener('input', () => {
                if (!this.productIdInput.value) { // Only auto-generate for new products
                    this.generateSlug();
                }
            });
            
            // Handle tab switching to ensure form elements are properly accessible
            document.querySelectorAll('#productTabs button[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', (e) => {
                    // When switching to the Relations tab, ensure developer and publisher selects are visible
                    if (e.target.getAttribute('data-bs-target') === '#relations') {
                        // Make sure the select elements are visible and accessible
                        this.productDeveloperInput.style.display = '';
                        this.productPublisherInput.style.display = '';
                    }
                });
            });
        }
        
        // --- Core Data Management ---
        /**
         * Loads a list of products from the server and renders the table.
         * @param {number} page The page number to load.
         * @param {string} searchTerm The search term for filtering products.
         * @param {string} statusFilter The status filter for products.
         */
        async loadProducts(page = 1, searchTerm = '', statusFilter = '') {
            this.currentPage = page;
            this.currentSearchTerm = searchTerm;
            this.currentStatusFilter = statusFilter;
            this.productsTableBody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">Loading products...</td></tr>`;
            
            try {
                let url = `${this.baseUrl}/api/admin/products?page=${page}&limit=10`;
                if (searchTerm) {
                    url += `&search=${encodeURIComponent(searchTerm)}`;
                }
                if (statusFilter) {
                    url += `&status=${encodeURIComponent(statusFilter)}`;
                }
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (response.status === 401 || response.status === 403) {
                    window.location.href = `${this.baseUrl}/frontend/admin/admin_login.html`;
                    return;
                }
                
                if (data.success) {
                    this.renderProductsTable(data.data);
                    this.generatePagination(data.current_page, data.total_pages);
                } else {
                    this.productsTableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${data.message || 'Failed to load products.'}</td></tr>`;
                    Admin.showAlert(data.message || 'Failed to load products.', 'danger', 'productManagementMessage');
                }
            } catch (error) {
                console.error('Error loading products:', error);
                this.productsTableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Network error. Please try again.</td></tr>`;
                Admin.showAlert('Network error. Could not load products.', 'danger', 'productManagementMessage');
            }
        }
        
        /**
         * Renders the product table with the given data.
         * @param {Array<Object>} products The array of product objects.
         */
        renderProductsTable(products) {
            this.productsTableBody.innerHTML = '';
            if (products.length === 0) {
                this.productsTableBody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No products found.</td></tr>`;
                return;
            }
            
            products.forEach(product => {
                // FIX: Add baseUrl to the image path
                const imageUrl = product.featured_image ? `${this.baseUrl}/${product.featured_image}` : 'https://via.placeholder.com/50';
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${product.id}</td>
                    <td><img src="${Admin.escapeHtml(imageUrl)}" alt="${Admin.escapeHtml(product.title)}" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;"></td>
                    <td>${Admin.escapeHtml(product.title)}</td>
                    <td>$${parseFloat(product.price).toFixed(2)}</td>
                    <td>${product.key_count}</td>
                    <td>
                        <span class="badge ${product.stock_status === 'in_stock' ? 'bg-success' : product.stock_status === 'out_of_stock' ? 'bg-danger' : 'bg-warning'}">${Admin.escapeHtml(product.stock_status)}</span>
                        <span class="badge ${product.status === 'published' ? 'bg-primary' : product.status === 'draft' ? 'bg-secondary' : 'bg-dark'} ms-1">${Admin.escapeHtml(product.status)}</span>
                    </td>
                    <td>${new Date(product.created_at).toLocaleDateString()}</td>
                    <td>
                        <button class="btn btn-warning btn-sm edit-product-btn me-2" data-id="${product.id}"><i class="fa-solid fa-edit"></i></button>
                        <button class="btn btn-danger btn-sm delete-product-btn" data-id="${product.id}"><i class="fa-solid fa-trash"></i></button>
                    </td>
                `;
                this.productsTableBody.appendChild(row);
            });
        }
        
        /**
         * Opens the product modal for adding or editing a product.
         * @param {number|null} productId The ID of the product to edit, or null to add a new one.
         */
        async openProductModal(productId = null) {
            this.productForm.reset();
            Admin.clearFormErrors(this.productForm);
            Admin.hideAlert('modalMessage');
            this.productModalTitle.textContent = productId ? 'Edit Product' : 'Add New Product';
            this.productIdInput.value = productId || '';
            this.featuredImagePreview.innerHTML = '';
            this.galleryPreview.innerHTML = '';
            this.productExistingImages = [];
            
            // Reset file inputs
            this.productFeaturedImageInput.value = '';
            this.productGalleryInput.value = '';
            
            // Reset keys
            this.productKeysContainer.innerHTML = '';
            this.addKeyInput();
            
            if (!productId) {
                this.productKeyCountInput.value = 0;
                this.productStatusInput.value = 'draft';
            }
            
            if (productId) {
                try {
                    const response = await fetch(`${this.baseUrl}/api/admin/products/${productId}`);
                    const data = await response.json();
                    
                    if (response.ok && data.success) {
                        this.fillProductForm(data.data);
                        this.productExistingImages = data.data.images || [];
                    } else {
                        Admin.showAlert(data.message || 'Failed to load product data.', 'danger', 'modalMessage');
                    }
                } catch (error) {
                    Admin.showAlert('Network error. Could not load product data.', 'danger', 'modalMessage');
                }
            } else {
                const removeButton = this.productKeysContainer.querySelector('.remove-key-btn');
                if (removeButton) removeButton.disabled = true;
            }
            
            // Load form data (categories, platforms, etc.)
            await this.loadFormData();
            
            // Get CSRF token
            const csrfToken = await Admin.getCsrfToken();
            if (csrfToken) {
                this.csrfTokenInput.value = csrfToken;
            } else {
                Admin.showAlert('Failed to get CSRF token.', 'danger', 'modalMessage');
            }
            
            // Show the modal after a short delay to ensure all elements are properly rendered
            setTimeout(() => {
                this.productModal.show();
                
                // Ensure the Relations tab is active and visible when opening the modal
                const relationsTab = document.querySelector('#relations-tab');
                if (relationsTab) {
                    const tab = new bootstrap.Tab(relationsTab);
                    tab.show();
                }
            }, 100);
        }
        
        /**
         * Loads form data (categories, platforms, etc.) from the server.
         */
        async loadFormData() {
            try {
                const response = await fetch(`${this.baseUrl}/api/admin/products/form-data`);
                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Check if developers and publishers data exists
                    if (!data.data.developers || !data.data.publishers) {
                        console.error('Developers or publishers data is missing');
                        Admin.showAlert('Required form data is missing. Please try again.', 'danger', 'modalMessage');
                        return;
                    }
                    
                    this.populateSelectOptions('productCategories', data.data.categories, 'id', 'name');
                    this.populateSelectOptions('productPlatforms', data.data.platforms, 'id', 'name');
                    this.populateSelectOptions('productTags', data.data.tags, 'id', 'name');
                    this.populateSelectOptions('productDeveloper', data.data.developers, 'id', 'name', true);
                    this.populateSelectOptions('productPublisher', data.data.publishers, 'id', 'name', true);
                    
                    // For related products, we need to load all products except the current one
                    if (this.productIdInput.value) {
                        await this.loadRelatedProductsOptions();
                    }
                } else {
                    Admin.showAlert(data.message || 'Failed to load form data.', 'danger', 'modalMessage');
                }
            } catch (error) {
                console.error('Error loading form data:', error);
                Admin.showAlert('Network error. Could not load form data.', 'danger', 'modalMessage');
            }
        }
        
        /**
         * Loads related products options for the select dropdown.
         */
        async loadRelatedProductsOptions() {
            try {
                const response = await fetch(`${this.baseUrl}/api/admin/products?limit=1000`);
                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Filter out the current product
                    const products = data.data.filter(p => p.id !== parseInt(this.productIdInput.value));
                    this.populateSelectOptions('productRelatedProducts', products, 'id', 'title', false, true);
                }
            } catch (error) {
                console.error('Error loading related products:', error);
            }
        }
        
        /**
         * Populates a select element with options.
         * @param {string} selectId The ID of the select element.
         * @param {Array} options The array of options.
         * @param {string} valueKey The key to use for the option value.
         * @param {string} textKey The key to use for the option text.
         * @param {boolean} includeEmptyOption Whether to include an empty option.
         * @param {boolean} isMultiple Whether the select is multiple.
         */
        populateSelectOptions(selectId, options, valueKey, textKey, includeEmptyOption = false, isMultiple = false) {
            const select = document.getElementById(selectId);
            if (!select) {
                console.error(`Select element with ID ${selectId} not found`);
                return;
            }
            
            // Clear all options
            select.innerHTML = '';
            
            // Add empty option if requested
            if (includeEmptyOption) {
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = 'Select...';
                select.appendChild(emptyOption);
            }
            
            // Add options if they exist
            if (options && options.length > 0) {
                options.forEach(option => {
                    const optionElement = document.createElement('option');
                    optionElement.value = option[valueKey];
                    optionElement.textContent = option[textKey];
                    select.appendChild(optionElement);
                });
            } else {
                console.warn(`No options provided for select ${selectId}`);
                // Add a disabled option to indicate no data
                const noDataOption = document.createElement('option');
                noDataOption.value = '';
                noDataOption.textContent = 'No data available';
                noDataOption.disabled = true;
                select.appendChild(noDataOption);
            }
        }
        
        /**
         * Fills the product form with data from a given product object.
         * @param {Object} product The product object to fill the form with.
         */
        fillProductForm(product) {
            // Basic Information
            this.productNameInput.value = product.title;
            this.productSlugInput.value = product.slug;
            this.productShortDescriptionInput.value = product.short_description;
            this.productDescriptionInput.value = product.description;
            this.productSKUInput.value = product.sku || '';
            this.productReleaseDateInput.value = product.release_date;
            
            // Pricing & Inventory
            this.productPriceInput.value = product.price;
            this.productSalePriceInput.value = product.sale_price || '';
            this.productKeyCountInput.value = product.key_count;
            this.productStockStatusInput.value = product.stock_status;
            this.productStatusInput.value = product.status;
            this.productAgeRatingInput.value = product.age_rating || 'E';
            this.productFileSizeInput.value = product.file_size || '';
            
            // Media
            this.productVideoTrailerInput.value = product.video_trailer || '';
            this.previewExistingImages(product.images);
            
            // Relations
            if (product.categories && product.categories.length > 0) {
                this.setSelectedOptions('productCategories', product.categories.map(c => c.id));
            }
            
            if (product.platforms && product.platforms.length > 0) {
                this.setSelectedOptions('productPlatforms', product.platforms.map(p => p.id));
            }
            
            if (product.tags && product.tags.length > 0) {
                this.setSelectedOptions('productTags', product.tags.map(t => t.id));
            }
            
            if (product.related_products && product.related_products.length > 0) {
                this.setSelectedOptions('productRelatedProducts', product.related_products.map(p => p.id));
            }
            
            // Ensure developer and publisher are set correctly
            if (product.developer && product.developer.id) {
                this.productDeveloperInput.value = product.developer.id;
                console.log('Set developer ID to:', product.developer.id);
            } else {
                console.warn('No developer data found for product');
            }
            
            if (product.publisher && product.publisher.id) {
                this.productPublisherInput.value = product.publisher.id;
                console.log('Set publisher ID to:', product.publisher.id);
            } else {
                console.warn('No publisher data found for product');
            }
            
            // System Requirements - Minimum and Recommended
            if (product.min_requirements) {
                this.minReqOSInput.value = product.min_requirements.os || '';
                this.minReqProcessorInput.value = product.min_requirements.processor || '';
                this.minReqMemoryInput.value = product.min_requirements.memory || '';
                this.minReqGraphicsInput.value = product.min_requirements.graphics || '';
                this.minReqStorageInput.value = product.min_requirements.storage || '';
            }
            
            if (product.rec_requirements) {
                this.recReqOSInput.value = product.rec_requirements.os || '';
                this.recReqProcessorInput.value = product.rec_requirements.processor || '';
                this.recReqMemoryInput.value = product.rec_requirements.memory || '';
                this.recReqGraphicsInput.value = product.rec_requirements.graphics || '';
                this.recReqStorageInput.value = product.rec_requirements.storage || '';
            }
            
            // Product Keys
            this.productKeysContainer.innerHTML = '';
            if (product.keys && product.keys.length > 0) {
                product.keys.forEach(key => this.addKeyInput(key));
            } else {
                this.addKeyInput();
            }
            
            // Advanced
            this.productIsFeaturedInput.checked = product.is_featured;
            this.productIsTrendingInput.checked = product.is_trending;
            this.productMetaTitleInput.value = product.meta_title || '';
            this.productMetaDescriptionInput.value = product.meta_description || '';
        }
        
        /**
         * Sets the selected options for a multiple select element.
         * @param {string} selectId The ID of the select element.
         * @param {Array} values The array of values to select.
         */
        setSelectedOptions(selectId, values) {
            const select = document.getElementById(selectId);
            if (!select) return;
            
            Array.from(select.options).forEach(option => {
                option.selected = values.includes(parseInt(option.value));
            });
        }
        
        /**
         * Handles the submission of the product form.
         * @param {Event} e The form submit event.
         */
        async handleProductFormSubmit(e) {
            e.preventDefault();
            Admin.clearFormErrors(this.productForm);
            Admin.hideAlert('modalMessage');
            
            // Validate required fields
            if (!this.productDeveloperInput.value) {
                Admin.showInputError('developer_id', 'Please select a developer.', 'relations');
                Admin.showAlert('Please correct the validation errors.', 'danger', 'modalMessage');
                
                // Switch to the Relations tab to show the error
                this.switchToRelationsTab(() => {
                    // Focus on the developer select after switching tabs
                    setTimeout(() => {
                        this.productDeveloperInput.focus();
                    }, 300);
                });
                
                return;
            }
            
            if (!this.productPublisherInput.value) {
                Admin.showInputError('publisher_id', 'Please select a publisher.', 'relations');
                Admin.showAlert('Please correct the validation errors.', 'danger', 'modalMessage');
                
                // Switch to the Relations tab to show the error
                this.switchToRelationsTab(() => {
                    // Focus on the publisher select after switching tabs
                    setTimeout(() => {
                        this.productPublisherInput.focus();
                    }, 300);
                });
                
                return;
            }
            
            this.toggleSaveButtonState(true);
            const productId = this.productIdInput.value;
            const actionUrl = productId ? `${this.baseUrl}/api/admin/products/${productId}` : `${this.baseUrl}/api/admin/products`;
            
            // Create FormData object
            const formData = new FormData();
            
            // Add basic form fields
            formData.append('title', this.productNameInput.value);
            formData.append('slug', this.productSlugInput.value);
            formData.append('short_description', this.productShortDescriptionInput.value);
            formData.append('long_description', this.productDescriptionInput.value);
            formData.append('sku', this.productSKUInput.value);
            formData.append('price', this.productPriceInput.value);
            formData.append('sale_price', this.productSalePriceInput.value);
            formData.append('key_count', this.productKeyCountInput.value);
            formData.append('stock_status', this.productStockStatusInput.value);
            formData.append('status', this.productStatusInput.value);
            formData.append('release_date', this.productReleaseDateInput.value);
            formData.append('age_rating', this.productAgeRatingInput.value);
            formData.append('file_size', this.productFileSizeInput.value);
            formData.append('video_trailer', this.productVideoTrailerInput.value);
            formData.append('developer_id', this.productDeveloperInput.value);
            formData.append('publisher_id', this.productPublisherInput.value);
            formData.append('is_featured', this.productIsFeaturedInput.checked ? '1' : '0');
            formData.append('is_trending', this.productIsTrendingInput.checked ? '1' : '0');
            formData.append('meta_title', this.productMetaTitleInput.value);
            formData.append('meta_description', this.productMetaDescriptionInput.value);
            
            // Add minimum requirements
            const minRequirements = {
                os: this.minReqOSInput.value,
                processor: this.minReqProcessorInput.value,
                memory: this.minReqMemoryInput.value,
                graphics: this.minReqGraphicsInput.value,
                storage: this.minReqStorageInput.value
            };
            
            // Add recommended requirements
            const recRequirements = {
                os: this.recReqOSInput.value,
                processor: this.recReqProcessorInput.value,
                memory: this.recReqMemoryInput.value,
                graphics: this.recReqGraphicsInput.value,
                storage: this.recReqStorageInput.value
            };
            
            // Add minimum and recommended requirements as JSON strings
            formData.append('min_requirements', JSON.stringify(minRequirements));
            formData.append('rec_requirements', JSON.stringify(recRequirements));
            
            // Add categories, platforms, tags, and related products
            this.getSelectedValues('productCategories').forEach(id => formData.append('categories[]', id));
            this.getSelectedValues('productPlatforms').forEach(id => formData.append('platforms[]', id));
            this.getSelectedValues('productTags').forEach(id => formData.append('tags[]', id));
            this.getSelectedValues('productRelatedProducts').forEach(id => formData.append('related_products[]', id));
            
            // Add product keys
            const keys = Array.from(this.productKeysContainer.querySelectorAll('.product-key-input'))
                .map(input => input.value)
                .filter(key => key.trim() !== '');
            formData.append('keys', JSON.stringify(keys));
            
            // Add existing images
            const existingImageUrls = this.productExistingImages.map(img => img.image_url);
            formData.append('existing_images', JSON.stringify(existingImageUrls));
            
            // Add files
            if (this.productFeaturedImageInput.files.length > 0) {
                formData.append('featured_image', this.productFeaturedImageInput.files[0]);
            }
            
            if (this.productGalleryInput.files.length > 0) {
                Array.from(this.productGalleryInput.files).forEach(file => {
                    formData.append('images[]', file);
                });
            }
            
            // Add method for PUT request (Laravel style)
            if (productId) {
                formData.append('_method', 'PUT');
            }
            
            try {
                // Use Admin.fetchWithCsrf for form submission
                const response = await Admin.fetchWithCsrf(actionUrl, {
                    method: 'POST',
                    body: formData
                });
                
                if (response.status === 401 || response.status === 403) {
                    window.location.href = `${this.baseUrl}/frontend/admin/admin_login.html`;
                    return;
                }
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    Admin.showAlert(data.message, 'success', 'productManagementMessage');
                    this.productModal.hide();
                    this.loadProducts(this.currentPage, this.currentSearchTerm, this.currentStatusFilter);
                } else {
                    this.handleFormErrors(data);
                }
            } catch (error) {
                console.error('Error submitting form:', error);
                Admin.showAlert('Network error. Could not save product.', 'danger', 'modalMessage');
            } finally {
                this.toggleSaveButtonState(false);
            }
        }
        
        /**
         * Gets the selected values from a multiple select element.
         * @param {string} selectId The ID of the select element.
         * @returns {Array} The array of selected values.
         */
        getSelectedValues(selectId) {
            const select = document.getElementById(selectId);
            if (!select) return [];
            
            return Array.from(select.selectedOptions).map(option => parseInt(option.value));
        }
        
        /**
         * Handles and displays validation errors from the server response.
         * @param {Object} data The response data from the server.
         */
        handleFormErrors(data) {
            Admin.clearFormErrors(this.productForm);
            let errorMessage = data.message || 'An unknown error occurred.';
            
            if (data.errors) {
                let shouldSwitchToRelationsTab = false;
                
                for (const field in data.errors) {
                    if (field === 'featured_image') {
                        Admin.showInputError('featured_image', data.errors[field][0]);
                    } else if (field.startsWith('images.')) {
                        Admin.showAlert(data.errors[field][0], 'danger', 'modalMessage');
                        continue;
                    } else if (field === 'developer_id') {
                        Admin.showInputError('developer_id', data.errors[field][0], 'relations');
                        shouldSwitchToRelationsTab = true;
                    } else if (field === 'publisher_id') {
                        Admin.showInputError('publisher_id', data.errors[field][0], 'relations');
                        shouldSwitchToRelationsTab = true;
                    }
                    
                    const inputElement = document.querySelector(`[name="${field}"]`);
                    if (inputElement) {
                        Admin.showInputError(field, data.errors[field][0]);
                    }
                }
                
                Admin.showAlert('Please correct the validation errors.', 'danger', 'modalMessage');
                
                // Switch to the Relations tab if there are errors with developer_id or publisher_id
                if (shouldSwitchToRelationsTab) {
                    this.switchToRelationsTab();
                }
            } else {
                Admin.showAlert(errorMessage, 'danger', 'modalMessage');
            }
        }
        
        /**
         * Switches to the Relations tab and optionally executes a callback after switching.
         * @param {Function} callback Optional callback function to execute after switching tabs.
         */
        switchToRelationsTab(callback) {
            const relationsTab = document.querySelector('#relations-tab');
            if (relationsTab) {
                const tab = new bootstrap.Tab(relationsTab);
                tab.show();
                
                // Execute callback if provided
                if (typeof callback === 'function') {
                    setTimeout(callback, 300);
                }
            }
        }
        
        // --- Image and Key Management ---
        /**
         * Previews newly selected images in the modal.
         */
        previewNewImages() {
            const files = this.productGalleryInput.files;
            
            // Clear previous new image previews
            this.galleryPreview.querySelectorAll('.new-image-wrapper').forEach(el => el.remove());
            
            if (files) {
                Array.from(files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const imgWrapper = this.createImagePreviewElement(e.target.result, false, null);
                        this.galleryPreview.appendChild(imgWrapper);
                    };
                    reader.readAsDataURL(file);
                });
            }
        }
        
        /**
         * Previews the newly selected featured image.
         */
        previewFeaturedImage() {
            const file = this.productFeaturedImageInput.files[0];
            
            // Clear previous featured image preview
            this.featuredImagePreview.innerHTML = '';
            
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const imgWrapper = this.createImagePreviewElement(e.target.result, true, null);
                    this.featuredImagePreview.appendChild(imgWrapper);
                };
                reader.readAsDataURL(file);
            }
        }
        
        /**
         * Previews existing images fetched from the server.
         * @param {Array<Object>} images The array of image objects.
         */
        previewExistingImages(images) {
            this.featuredImagePreview.innerHTML = '';
            this.galleryPreview.innerHTML = '';
            
            if (!images || images.length === 0) return;
            
            const featuredImage = images.find(img => img.is_featured);
            if (featuredImage && featuredImage.image_url) {
                // FIX: Add baseUrl to the image path
                const imgWrapper = this.createImagePreviewElement(`${this.baseUrl}/${featuredImage.image_url}`, true, featuredImage.id);
                this.featuredImagePreview.appendChild(imgWrapper);
            }
            
            const otherImages = images.filter(img => !img.is_featured);
            if (otherImages.length > 0) {
                otherImages.forEach(image => {
                    if (image.image_url) {
                        // FIX: Add baseUrl to the image path
                        const imgWrapper = this.createImagePreviewElement(`${this.baseUrl}/${image.image_url}`, false, image.id);
                        this.galleryPreview.appendChild(imgWrapper);
                    }
                });
            }
        }
        
        /**
         * Creates an image preview element.
         * @param {string} src The image source URL or base64 data.
         * @param {boolean} isFeatured Whether the image is featured.
         * @param {number|null} imageId The ID of the image if it's an existing one.
         * @returns {HTMLElement} The created image preview element.
         */
        createImagePreviewElement(src, isFeatured, imageId = null) {
            const wrapper = document.createElement('div');
            wrapper.classList.add('image-preview', 'd-inline-block', 'me-2', 'mb-2', 'position-relative');
            wrapper.classList.add(isFeatured ? 'featured-image-wrapper' : 'other-image-wrapper', imageId ? 'existing-image-wrapper' : 'new-image-wrapper');
            
            const img = document.createElement('img');
            img.src = src;
            img.classList.add('img-thumbnail');
            img.style.objectFit = 'cover';
            img.style.width = '100px';
            img.style.height = '100px';
            
            if (imageId) {
                img.dataset.imageId = imageId;
            }
            
            wrapper.appendChild(img);
            
            if (imageId && !isFeatured) {
                const removeBtn = document.createElement('button');
                removeBtn.classList.add('btn', 'btn-danger', 'btn-sm', 'remove-image-btn', 'position-absolute', 'top-0', 'end-0', 'p-1');
                removeBtn.dataset.imageId = imageId;
                removeBtn.innerHTML = '<i class="fa-solid fa-times text-white"></i>';
                wrapper.appendChild(removeBtn);
            }
            
            return wrapper;
        }
        
        /**
         * Removes an existing image from the preview and the internal list.
         * @param {string} imageId The ID of the image to remove.
         * @param {HTMLElement} elementToRemove The DOM element to remove.
         */
        removeExistingImage(imageId, elementToRemove) {
            this.productExistingImages = this.productExistingImages.filter(img => img.id != imageId);
            elementToRemove.remove();
        }
        
        /**
         * Adds a new input field for a product key.
         * @param {string} defaultValue The default value for the input field.
         */
        addKeyInput(defaultValue = '') {
            const inputGroup = document.createElement('div');
            inputGroup.classList.add('input-group', 'mb-2');
            inputGroup.innerHTML = `
                <input type="text" class="form-control product-key-input" name="keys[]" placeholder="Enter product key" value="${Admin.escapeHtml(defaultValue)}">
                <button type="button" class="btn btn-outline-danger remove-key-btn"><i class="fa-solid fa-trash"></i></button>
            `;
            this.productKeysContainer.appendChild(inputGroup);
            this.updateRemoveKeyButtons();
        }
        
        /**
         * Removes a product key input field.
         * @param {HTMLElement} inputGroupElement The input group element to remove.
         */
        removeKeyInput(inputGroupElement) {
            inputGroupElement.remove();
            this.updateRemoveKeyButtons();
            this.updateKeyCountFromKeys();
        }
        
        /**
         * Updates the disabled state of the remove key buttons.
         */
        updateRemoveKeyButtons() {
            const keyInputs = this.productKeysContainer.querySelectorAll('.input-group');
            keyInputs.forEach((group, index) => {
                const removeBtn = group.querySelector('.remove-key-btn');
                if (removeBtn) {
                    removeBtn.disabled = keyInputs.length === 1;
                }
            });
        }
        
        /**
         * Updates the key count based on the number of keys.
         */
        updateKeyCountFromKeys() {
            const keys = Array.from(this.productKeysContainer.querySelectorAll('.product-key-input'))
                .filter(input => input.value.trim() !== '');
            this.productKeyCountInput.value = keys.length;
        }
        
        /**
         * Generates random product keys based on the key count.
         */
        generateKeys() {
            const keyCount = parseInt(this.productKeyCountInput.value) || 0;
            if (keyCount <= 0) {
                Admin.showAlert('Please enter a valid key count.', 'warning', 'modalMessage');
                return;
            }
            
            // Clear existing keys
            this.productKeysContainer.innerHTML = '';
            
            // Generate new keys
            for (let i = 0; i < keyCount; i++) {
                const randomKey = this.generateRandomKey();
                this.addKeyInput(randomKey);
            }
            
            Admin.showAlert(`${keyCount} keys generated successfully.`, 'success', 'modalMessage');
        }
        
        /**
         * Generates a random product key.
         * @returns {string} A random product key.
         */
        generateRandomKey() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = '';
            
            // Format: XXXX-XXXX-XXXX-XXXX
            for (let i = 0; i < 4; i++) {
                for (let j = 0; j < 4; j++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                if (i < 3) result += '-';
            }
            
            return result;
        }
        
        /**
         * Generates a URL-friendly slug from the product title.
         */
        generateSlug() {
            const title = this.productNameInput.value;
            if (!title) return;
            
            const slug = title
                .toLowerCase()
                .replace(/[^\w\s-]/g, '') // Remove special characters
                .replace(/\s+/g, '-') // Replace spaces with hyphens
                .replace(/-+/g, '-'); // Replace multiple hyphens with a single hyphen
            
            this.productSlugInput.value = slug;
        }
        
        // --- Helper methods ---
        /**
         * Toggles the state of the save button (enabled/disabled, loading spinner).
         * @param {boolean} isDisabled
         */
        toggleSaveButtonState(isDisabled) {
            if (!this.saveProductBtn) return;
            
            this.saveProductBtn.disabled = isDisabled;
            this.saveProductBtn.innerHTML = isDisabled ?
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...' :
                '<i class="fa-solid fa-save me-2"></i>Save Product';
        }
        
        /**
         * Handles the search action.
         */
        handleSearch() {
            const searchTerm = this.productSearchInput.value.trim();
            this.loadProducts(1, searchTerm, this.currentStatusFilter);
        }
        
        /**
         * Handles the status filter action.
         */
        handleStatusFilter() {
            const statusFilter = this.productStatusFilter.value;
            this.loadProducts(1, this.currentSearchTerm, statusFilter);
        }
        
        /**
         * Handles actions (edit/delete) from the main product table.
         * @param {Event} event The click event.
         */
        handleTableActions(event) {
            const editBtn = event.target.closest('.edit-product-btn');
            const deleteBtn = event.target.closest('.delete-product-btn');
            
            if (editBtn) {
                const productId = editBtn.dataset.id;
                this.openProductModal(productId);
            } else if (deleteBtn) {
                this.currentProductIdToDelete = deleteBtn.dataset.id;
                this.deleteConfirmModal.show();
            }
        }
        
        /**
         * Generates and renders the pagination controls.
         * @param {number} currentPage The current page number.
         * @param {number} totalPages The total number of pages.
         */
        generatePagination(currentPage, totalPages) {
            if (!this.paginationControls) return;
            
            this.paginationControls.innerHTML = '';
            if (totalPages <= 1) return;
            
            const ul = document.createElement('ul');
            ul.className = 'pagination';
            
            const createPageItem = (page, text, isActive = false, isDisabled = false) => {
                const li = document.createElement('li');
                li.className = `page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`;
                
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = text;
                
                if (!isDisabled && !isActive) {
                    a.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.loadProducts(page, this.currentSearchTerm, this.currentStatusFilter);
                    });
                }
                
                li.appendChild(a);
                return li;
            };
            
            ul.appendChild(createPageItem(currentPage - 1, 'Previous', false, currentPage === 1));
            
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);
            
            if (currentPage <= 3) {
                endPage = Math.min(totalPages, 5);
            } else if (currentPage >= totalPages - 2) {
                startPage = Math.max(1, totalPages - 4);
            }
            
            for (let i = startPage; i <= endPage; i++) {
                ul.appendChild(createPageItem(i, i, i === currentPage));
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const ellipsisItem = document.createElement('li');
                    ellipsisItem.className = 'page-item disabled';
                    ellipsisItem.innerHTML = `<span class="page-link">...</span>`;
                    ul.appendChild(ellipsisItem);
                }
                ul.appendChild(createPageItem(totalPages, totalPages, totalPages === currentPage));
            }
            
            ul.appendChild(createPageItem(currentPage + 1, 'Next', false, currentPage === totalPages));
            this.paginationControls.appendChild(ul);
        }
        
        /**
         * Deletes a product after confirmation.
         */
        async deleteProduct() {
            if (!this.currentProductIdToDelete) return;
            
            this.confirmDeleteBtn.disabled = true;
            this.confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
            
            try {
                // Use Admin.fetchWithCsrf for delete request
                const response = await Admin.fetchWithCsrf(`${this.baseUrl}/api/admin/products/${this.currentProductIdToDelete}`, {
                    method: 'DELETE'
                });
                
                if (response.status === 401 || response.status === 403) {
                    window.location.href = `${this.baseUrl}/frontend/admin/admin_login.html`;
                    return;
                }
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    Admin.showAlert(data.message, 'success', 'productManagementMessage');
                    this.deleteConfirmModal.hide();
                    this.loadProducts(this.currentPage, this.currentSearchTerm, this.currentStatusFilter);
                } else {
                    Admin.showAlert(data.message || 'Failed to delete product.', 'danger', 'productManagementMessage');
                }
            } catch (error) {
                console.error('Error deleting product:', error);
                Admin.showAlert('Network error. Could not delete product.', 'danger', 'productManagementMessage');
            } finally {
                this.confirmDeleteBtn.disabled = false;
                this.confirmDeleteBtn.innerHTML = 'Delete';
            }
        }
    }
    
    // Initialize the AdminProductManager class
    const baseUrlPath = window.AppBaseUrlPath || '';
    new AdminProductManager(baseUrlPath);
});