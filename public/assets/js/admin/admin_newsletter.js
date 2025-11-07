/**
 * public/assets/js/admin/admin_newsletter.js
 *
 * This file handles all functionality for the newsletter management page in the admin panel.
 * It includes functions for managing subscribers and email campaigns.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get the base URL path from the global variable injected by PHP
    const baseUrlPath = window.AppBaseUrlPath || '';
    
    // DOM Elements
    const loadingSpinner = document.getElementById('loadingSpinner');
    const subscribersTableCard = document.getElementById('subscribersTableCard');
    const subscribersTableBody = document.getElementById('subscribersTableBody');
    const messageDiv = document.getElementById('message');
    const exportSubscribersBtn = document.getElementById('exportSubscribersBtn');
    
    // Tab elements
    const subscribersTab = document.getElementById('subscribers-tab');
    const campaignsTab = document.getElementById('campaigns-tab');
    const subscribersPane = document.getElementById('subscribers-pane');
    const campaignsPane = document.getElementById('campaigns-pane');
    
    // Campaign form elements
    const campaignForm = document.getElementById('campaignForm');
    const campaignModal = document.getElementById('campaignModal');
    const campaignModalTitle = document.getElementById('campaignModalTitle');
    const campaignIdInput = document.getElementById('campaignId');
    const campaignNameInput = document.getElementById('campaignName');
    const campaignSubjectInput = document.getElementById('campaignSubject');
    const campaignContentInput = document.getElementById('campaignContent');
    const campaignSenderNameInput = document.getElementById('campaignSenderName');
    const campaignSenderEmailInput = document.getElementById('campaignSenderEmail');
    const campaignScheduledAtInput = document.getElementById('campaignScheduledAt');
    const saveCampaignBtn = document.getElementById('saveCampaignBtn');
    const sendCampaignBtn = document.getElementById('sendCampaignBtn');
    const scheduleCampaignBtn = document.getElementById('scheduleCampaignBtn');
    const testEmailBtn = document.getElementById('testEmailBtn');
    const testEmailInput = document.getElementById('testEmail');
    
    // Campaign table elements
    const campaignsTableBody = document.getElementById('campaignsTableBody');
    const campaignsTableCard = document.getElementById('campaignsTableCard');
    
    // Statistics elements
    const totalSubscribersStat = document.getElementById('totalSubscribersStat');
    const totalCampaignsStat = document.getElementById('totalCampaignsStat');
    const sentCampaignsStat = document.getElementById('sentCampaignsStat');
    const scheduledCampaignsStat = document.getElementById('scheduledCampaignsStat');
    
    // Quick email form elements
    const quickEmailForm = document.getElementById('quickEmailForm');
    const quickEmailSubjectInput = document.getElementById('quickEmailSubject');
    const quickEmailContentInput = document.getElementById('quickEmailContent');
    const quickEmailSenderNameInput = document.getElementById('quickEmailSenderName');
    const quickEmailSenderEmailInput = document.getElementById('quickEmailSenderEmail');
    const sendQuickEmailBtn = document.getElementById('sendQuickEmailBtn');
    
    // Initialize the page
    init();
    
    /**
     * Initialize the newsletter management page
     */
    async function init() {
        try {
            // Load statistics
            await loadNewsletterStats();
            
            // Load subscribers
            await loadSubscribers();
            
            // Load campaigns
            await loadCampaigns();
            
            // Set up event listeners
            setupEventListeners();
            
            // Hide loading spinner and show content
            loadingSpinner.style.display = 'none';
            subscribersTableCard.style.display = 'block';
            campaignsTableCard.style.display = 'block';
        } catch (error) {
            console.error('Error initializing newsletter page:', error);
            Admin.showAlert('Failed to load newsletter data. Please refresh the page.', 'danger');
        }
    }
    
    /**
     * Set up event listeners for various UI elements
     */
    function setupEventListeners() {
        // Export subscribers button
        if (exportSubscribersBtn) {
            exportSubscribersBtn.addEventListener('click', exportSubscribers);
        }
        
        // Campaign form submission
        if (campaignForm) {
            campaignForm.addEventListener('submit', saveCampaign);
        }
        
        // Send campaign button
        if (sendCampaignBtn) {
            sendCampaignBtn.addEventListener('click', sendCampaign);
        }
        
        // Schedule campaign button
        if (scheduleCampaignBtn) {
            scheduleCampaignBtn.addEventListener('click', scheduleCampaign);
        }
        
        // Test email button
        if (testEmailBtn) {
            testEmailBtn.addEventListener('click', sendTestEmail);
        }
        
        // Quick email form submission
        if (quickEmailForm) {
            quickEmailForm.addEventListener('submit', sendQuickEmail);
        }
        
        // Tab switching
        if (subscribersTab) {
            subscribersTab.addEventListener('click', function() {
                showSubscribersTab();
            });
        }
        
        if (campaignsTab) {
            campaignsTab.addEventListener('click', function() {
                showCampaignsTab();
            });
        }
    }
    
    /**
     * Load newsletter statistics from the API
     */
    async function loadNewsletterStats() {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/stats`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const stats = data.data;
                
                if (totalSubscribersStat) {
                    totalSubscribersStat.textContent = stats.total_subscribers || 0;
                }
                
                if (totalCampaignsStat) {
                    totalCampaignsStat.textContent = stats.total_campaigns || 0;
                }
                
                if (sentCampaignsStat) {
                    sentCampaignsStat.textContent = stats.sent_campaigns || 0;
                }
                
                if (scheduledCampaignsStat) {
                    scheduledCampaignsStat.textContent = stats.scheduled_campaigns || 0;
                }
            } else {
                console.error('Failed to load newsletter stats:', data.message);
            }
        } catch (error) {
            console.error('Error loading newsletter stats:', error);
        }
    }
    
    /**
     * Load subscribers from the API and populate the table
     */
    async function loadSubscribers() {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/subscribers`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                populateSubscribersTable(data.data);
            } else {
                Admin.showAlert(data.message || 'Failed to load subscribers.', 'danger');
            }
        } catch (error) {
            console.error('Error loading subscribers:', error);
            Admin.showAlert('Network error. Could not load subscribers.', 'danger');
        }
    }
    
    /**
     * Populate the subscribers table with data
     */
    function populateSubscribersTable(subscribers) {
        if (!subscribersTableBody) return;
        
        subscribersTableBody.innerHTML = '';
        
        if (subscribers.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = `<td colspan="4" class="text-center">No subscribers found.</td>`;
            subscribersTableBody.appendChild(row);
            return;
        }
        
        subscribers.forEach(subscriber => {
            const row = document.createElement('tr');
            
            const subscribedDate = subscriber.subscribed_at 
                ? new Date(subscriber.subscribed_at).toLocaleDateString() 
                : 'N/A';
            
            const statusBadge = getStatusBadge(subscriber.status);
            
            row.innerHTML = `
                <td>${Admin.escapeHtml(subscriber.id)}</td>
                <td>${Admin.escapeHtml(subscriber.email)}</td>
                <td>${subscribedDate}</td>
                <td>
                    ${statusBadge}
                    <button class="btn btn-sm btn-danger ms-2 delete-subscriber-btn" data-id="${subscriber.id}">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            `;
            
            subscribersTableBody.appendChild(row);
        });
        
        // Add event listeners to delete buttons
        document.querySelectorAll('.delete-subscriber-btn').forEach(button => {
            button.addEventListener('click', function() {
                const subscriberId = this.getAttribute('data-id');
                deleteSubscriber(subscriberId);
            });
        });
    }
    
    /**
     * Get a status badge HTML based on the status
     */
    function getStatusBadge(status) {
        switch (status) {
            case 'active':
                return '<span class="badge bg-success">Active</span>';
            case 'unsubscribed':
                return '<span class="badge bg-warning">Unsubscribed</span>';
            case 'bounced':
                return '<span class="badge bg-danger">Bounced</span>';
            default:
                return '<span class="badge bg-secondary">Unknown</span>';
        }
    }
    
    /**
     * Delete a subscriber
     */
    async function deleteSubscriber(subscriberId) {
        if (!confirm('Are you sure you want to delete this subscriber?')) {
            return;
        }
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/subscribers/${subscriberId}`, {
                method: 'DELETE'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                await loadSubscribers(); // Reload the subscribers table
                await loadNewsletterStats(); // Update statistics
            } else {
                Admin.showAlert(data.message || 'Failed to delete subscriber.', 'danger');
            }
        } catch (error) {
            console.error('Error deleting subscriber:', error);
            Admin.showAlert('Network error. Could not delete subscriber.', 'danger');
        }
    }
    
    /**
     * Export subscribers to CSV
     */
    async function exportSubscribers() {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/subscribers`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const subscribers = data.data;
                
                // Create CSV content
                let csvContent = "ID,Email,Status,Subscribed At\n";
                
                subscribers.forEach(subscriber => {
                    const subscribedDate = subscriber.subscribed_at 
                        ? new Date(subscriber.subscribed_at).toLocaleDateString() 
                        : 'N/A';
                    
                    csvContent += `${subscriber.id},"${subscriber.email}",${subscriber.status},${subscribedDate}\n`;
                });
                
                // Create a blob and download link
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `newsletter_subscribers_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                Admin.showAlert('Subscribers exported successfully.', 'success');
            } else {
                Admin.showAlert(data.message || 'Failed to export subscribers.', 'danger');
            }
        } catch (error) {
            console.error('Error exporting subscribers:', error);
            Admin.showAlert('Network error. Could not export subscribers.', 'danger');
        }
    }
    
    /**
     * Load campaigns from the API and populate the table
     */
    async function loadCampaigns() {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/campaigns`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                populateCampaignsTable(data.data);
            } else {
                Admin.showAlert(data.message || 'Failed to load campaigns.', 'danger');
            }
        } catch (error) {
            console.error('Error loading campaigns:', error);
            Admin.showAlert('Network error. Could not load campaigns.', 'danger');
        }
    }
    
    /**
     * Populate the campaigns table with data
     */
    function populateCampaignsTable(campaigns) {
        if (!campaignsTableBody) return;
        
        campaignsTableBody.innerHTML = '';
        
        if (campaigns.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = `<td colspan="7" class="text-center">No campaigns found.</td>`;
            campaignsTableBody.appendChild(row);
            return;
        }
        
        campaigns.forEach(campaign => {
            const row = document.createElement('tr');
            
            const createdDate = campaign.created_at 
                ? new Date(campaign.created_at).toLocaleDateString() 
                : 'N/A';
            
            const sentDate = campaign.sent_at 
                ? new Date(campaign.sent_at).toLocaleDateString() 
                : 'N/A';
            
            const statusBadge = getCampaignStatusBadge(campaign.status);
            
            row.innerHTML = `
                <td>${Admin.escapeHtml(campaign.id)}</td>
                <td>${Admin.escapeHtml(campaign.name)}</td>
                <td>${Admin.escapeHtml(campaign.subject)}</td>
                <td>${statusBadge}</td>
                <td>${campaign.recipients_count || 0}</td>
                <td>${sentDate}</td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-info view-campaign-btn" data-id="${campaign.id}" title="View">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-primary edit-campaign-btn" data-id="${campaign.id}" title="Edit">
                            <i class="fa-solid fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-success send-campaign-btn" data-id="${campaign.id}" title="Send" 
                            ${campaign.status === 'sent' || campaign.status === 'sending' ? 'disabled' : ''}>
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                        <button class="btn btn-sm btn-warning test-campaign-btn" data-id="${campaign.id}" title="Test">
                            <i class="fa-solid fa-flask"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-campaign-btn" data-id="${campaign.id}" title="Delete"
                            ${campaign.status === 'sent' ? 'disabled' : ''}>
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            campaignsTableBody.appendChild(row);
        });
        
        // Add event listeners to action buttons
        document.querySelectorAll('.view-campaign-btn').forEach(button => {
            button.addEventListener('click', function() {
                const campaignId = this.getAttribute('data-id');
                viewCampaign(campaignId);
            });
        });
        
        document.querySelectorAll('.edit-campaign-btn').forEach(button => {
            button.addEventListener('click', function() {
                const campaignId = this.getAttribute('data-id');
                editCampaign(campaignId);
            });
        });
        
        document.querySelectorAll('.send-campaign-btn').forEach(button => {
            button.addEventListener('click', function() {
                const campaignId = this.getAttribute('data-id');
                sendCampaignById(campaignId);
            });
        });
        
        document.querySelectorAll('.test-campaign-btn').forEach(button => {
            button.addEventListener('click', function() {
                const campaignId = this.getAttribute('data-id');
                showTestEmailModal(campaignId);
            });
        });
        
        document.querySelectorAll('.delete-campaign-btn').forEach(button => {
            button.addEventListener('click', function() {
                const campaignId = this.getAttribute('data-id');
                deleteCampaign(campaignId);
            });
        });
    }
    
    /**
     * Get a campaign status badge HTML based on the status
     */
    function getCampaignStatusBadge(status) {
        switch (status) {
            case 'draft':
                return '<span class="badge bg-secondary">Draft</span>';
            case 'scheduled':
                return '<span class="badge bg-warning">Scheduled</span>';
            case 'sending':
                return '<span class="badge bg-info">Sending</span>';
            case 'sent':
                return '<span class="badge bg-success">Sent</span>';
            case 'cancelled':
                return '<span class="badge bg-danger">Cancelled</span>';
            default:
                return '<span class="badge bg-secondary">Unknown</span>';
        }
    }
    
    /**
     * Show the subscribers tab
     */
    function showSubscribersTab() {
        if (subscribersTab) {
            subscribersTab.classList.add('active');
        }
        if (campaignsTab) {
            campaignsTab.classList.remove('active');
        }
        if (subscribersPane) {
            subscribersPane.classList.add('show', 'active');
        }
        if (campaignsPane) {
            campaignsPane.classList.remove('show', 'active');
        }
    }
    
    /**
     * Show the campaigns tab
     */
    function showCampaignsTab() {
        if (campaignsTab) {
            campaignsTab.classList.add('active');
        }
        if (subscribersTab) {
            subscribersTab.classList.remove('active');
        }
        if (campaignsPane) {
            campaignsPane.classList.add('show', 'active');
        }
        if (subscribersPane) {
            subscribersPane.classList.remove('show', 'active');
        }
    }
    
    /**
     * Show the campaign modal for creating a new campaign
     */
    function showNewCampaignModal() {
        if (campaignModalTitle) {
            campaignModalTitle.textContent = 'Create New Campaign';
        }
        
        // Clear form fields
        if (campaignIdInput) {
            campaignIdInput.value = '';
        }
        if (campaignNameInput) {
            campaignNameInput.value = '';
        }
        if (campaignSubjectInput) {
            campaignSubjectInput.value = '';
        }
        if (campaignContentInput) {
            campaignContentInput.value = '';
        }
        if (campaignSenderNameInput) {
            campaignSenderNameInput.value = '';
        }
        if (campaignSenderEmailInput) {
            campaignSenderEmailInput.value = '';
        }
        if (campaignScheduledAtInput) {
            campaignScheduledAtInput.value = '';
        }
        
        // Show send and schedule buttons
        if (sendCampaignBtn) {
            sendCampaignBtn.style.display = 'inline-block';
        }
        if (scheduleCampaignBtn) {
            scheduleCampaignBtn.style.display = 'inline-block';
        }
        
        // Show the modal
        const modal = new bootstrap.Modal(campaignModal);
        modal.show();
    }
    
    /**
     * View a campaign
     */
    async function viewCampaign(campaignId) {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/campaigns/${campaignId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const campaign = data.data;
                
                // Populate modal with campaign data
                if (campaignModalTitle) {
                    campaignModalTitle.textContent = 'View Campaign';
                }
                
                if (campaignIdInput) {
                    campaignIdInput.value = campaign.id;
                }
                if (campaignNameInput) {
                    campaignNameInput.value = campaign.name;
                }
                if (campaignSubjectInput) {
                    campaignSubjectInput.value = campaign.subject;
                }
                if (campaignContentInput) {
                    campaignContentInput.value = campaign.content;
                }
                if (campaignSenderNameInput) {
                    campaignSenderNameInput.value = campaign.sender_name;
                }
                if (campaignSenderEmailInput) {
                    campaignSenderEmailInput.value = campaign.sender_email;
                }
                if (campaignScheduledAtInput) {
                    campaignScheduledAtInput.value = campaign.scheduled_at || '';
                }
                
                // Disable form fields for viewing
                disableFormFields(true);
                
                // Hide send and schedule buttons
                if (sendCampaignBtn) {
                    sendCampaignBtn.style.display = 'none';
                }
                if (scheduleCampaignBtn) {
                    scheduleCampaignBtn.style.display = 'none';
                }
                
                // Show the modal
                const modal = new bootstrap.Modal(campaignModal);
                modal.show();
            } else {
                Admin.showAlert(data.message || 'Failed to load campaign.', 'danger');
            }
        } catch (error) {
            console.error('Error viewing campaign:', error);
            Admin.showAlert('Network error. Could not load campaign.', 'danger');
        }
    }
    
    /**
     * Edit a campaign
     */
    async function editCampaign(campaignId) {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/campaigns/${campaignId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const campaign = data.data;
                
                // Check if campaign can be edited
                if (campaign.status === 'sent') {
                    Admin.showAlert('Cannot edit a sent campaign.', 'warning');
                    return;
                }
                
                // Populate modal with campaign data
                if (campaignModalTitle) {
                    campaignModalTitle.textContent = 'Edit Campaign';
                }
                
                if (campaignIdInput) {
                    campaignIdInput.value = campaign.id;
                }
                if (campaignNameInput) {
                    campaignNameInput.value = campaign.name;
                }
                if (campaignSubjectInput) {
                    campaignSubjectInput.value = campaign.subject;
                }
                if (campaignContentInput) {
                    campaignContentInput.value = campaign.content;
                }
                if (campaignSenderNameInput) {
                    campaignSenderNameInput.value = campaign.sender_name;
                }
                if (campaignSenderEmailInput) {
                    campaignSenderEmailInput.value = campaign.sender_email;
                }
                if (campaignScheduledAtInput) {
                    campaignScheduledAtInput.value = campaign.scheduled_at || '';
                }
                
                // Enable form fields for editing
                disableFormFields(false);
                
                // Show send and schedule buttons
                if (sendCampaignBtn) {
                    sendCampaignBtn.style.display = 'inline-block';
                }
                if (scheduleCampaignBtn) {
                    scheduleCampaignBtn.style.display = 'inline-block';
                }
                
                // Show the modal
                const modal = new bootstrap.Modal(campaignModal);
                modal.show();
            } else {
                Admin.showAlert(data.message || 'Failed to load campaign.', 'danger');
            }
        } catch (error) {
            console.error('Error editing campaign:', error);
            Admin.showAlert('Network error. Could not load campaign.', 'danger');
        }
    }
    
    /**
     * Disable or enable form fields
     */
    function disableFormFields(disable) {
        const formFields = [
            campaignNameInput,
            campaignSubjectInput,
            campaignContentInput,
            campaignSenderNameInput,
            campaignSenderEmailInput,
            campaignScheduledAtInput
        ];
        
        formFields.forEach(field => {
            if (field) {
                field.disabled = disable;
            }
        });
        
        if (saveCampaignBtn) {
            saveCampaignBtn.disabled = disable;
        }
    }
    
    /**
     * Save a campaign (create or update)
     */
    async function saveCampaign(event) {
        event.preventDefault();
        
        const campaignId = campaignIdInput ? campaignIdInput.value : '';
        const isUpdate = campaignId !== '';
        
        const campaignData = {
            name: campaignNameInput ? campaignNameInput.value : '',
            subject: campaignSubjectInput ? campaignSubjectInput.value : '',
            content: campaignContentInput ? campaignContentInput.value : '',
            sender_name: campaignSenderNameInput ? campaignSenderNameInput.value : '',
            sender_email: campaignSenderEmailInput ? campaignSenderEmailInput.value : ''
        };
        
        // Validate form data
        if (!campaignData.name || !campaignData.subject || !campaignData.content || 
            !campaignData.sender_name || !campaignData.sender_email) {
            Admin.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        // Validate email
        if (!validateEmail(campaignData.sender_email)) {
            Admin.showAlert('Please enter a valid sender email.', 'warning');
            return;
        }
        
        try {
            // Disable save button
            if (saveCampaignBtn) {
                saveCampaignBtn.disabled = true;
                saveCampaignBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            }
            
            const url = isUpdate 
                ? `${baseUrlPath}/api/admin/newsletter/campaigns/${campaignId}`
                : `${baseUrlPath}/api/admin/newsletter/campaigns`;
            
            const method = isUpdate ? 'PUT' : 'POST';
            
            const response = await Admin.fetchWithCsrf(url, {
                method: method,
                body: campaignData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                
                // Close the modal
                const modal = bootstrap.Modal.getInstance(campaignModal);
                modal.hide();
                
                // Reload campaigns table
                await loadCampaigns();
                
                // Update statistics
                await loadNewsletterStats();
            } else {
                Admin.showAlert(data.message || 'Failed to save campaign.', 'danger');
            }
        } catch (error) {
            console.error('Error saving campaign:', error);
            Admin.showAlert('Network error. Could not save campaign.', 'danger');
        } finally {
            // Re-enable save button
            if (saveCampaignBtn) {
                saveCampaignBtn.disabled = false;
                saveCampaignBtn.innerHTML = 'Save Campaign';
            }
        }
    }
    
    /**
     * Send a campaign immediately
     */
    async function sendCampaign() {
        const campaignId = campaignIdInput ? campaignIdInput.value : '';
        
        if (!campaignId) {
            Admin.showAlert('No campaign selected.', 'warning');
            return;
        }
        
        if (!confirm('Are you sure you want to send this campaign to all active subscribers?')) {
            return;
        }
        
        try {
            // Disable send button
            if (sendCampaignBtn) {
                sendCampaignBtn.disabled = true;
                sendCampaignBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
            }
            
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/campaigns/${campaignId}/send`, {
                method: 'POST'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                
                // Close the modal
                const modal = bootstrap.Modal.getInstance(campaignModal);
                modal.hide();
                
                // Reload campaigns table
                await loadCampaigns();
                
                // Update statistics
                await loadNewsletterStats();
            } else {
                Admin.showAlert(data.message || 'Failed to send campaign.', 'danger');
            }
        } catch (error) {
            console.error('Error sending campaign:', error);
            Admin.showAlert('Network error. Could not send campaign.', 'danger');
        } finally {
            // Re-enable send button
            if (sendCampaignBtn) {
                sendCampaignBtn.disabled = false;
                sendCampaignBtn.innerHTML = 'Send Now';
            }
        }
    }
    
    /**
     * Send a campaign by ID (from the campaigns table)
     */
    async function sendCampaignById(campaignId) {
        if (!confirm('Are you sure you want to send this campaign to all active subscribers?')) {
            return;
        }
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/campaigns/${campaignId}/send`, {
                method: 'POST'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                
                // Reload campaigns table
                await loadCampaigns();
                
                // Update statistics
                await loadNewsletterStats();
            } else {
                Admin.showAlert(data.message || 'Failed to send campaign.', 'danger');
            }
        } catch (error) {
            console.error('Error sending campaign:', error);
            Admin.showAlert('Network error. Could not send campaign.', 'danger');
        }
    }
    
    /**
     * Schedule a campaign
     */
    async function scheduleCampaign() {
        const campaignId = campaignIdInput ? campaignIdInput.value : '';
        const scheduledAt = campaignScheduledAtInput ? campaignScheduledAtInput.value : '';
        
        if (!campaignId) {
            Admin.showAlert('No campaign selected.', 'warning');
            return;
        }
        
        if (!scheduledAt) {
            Admin.showAlert('Please select a date and time to schedule the campaign.', 'warning');
            return;
        }
        
        // Validate date
        const scheduledDate = new Date(scheduledAt);
        if (scheduledDate <= new Date()) {
            Admin.showAlert('Please select a future date and time.', 'warning');
            return;
        }
        
        try {
            // Disable schedule button
            if (scheduleCampaignBtn) {
                scheduleCampaignBtn.disabled = true;
                scheduleCampaignBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Scheduling...';
            }
            
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/campaigns/${campaignId}/schedule`, {
                method: 'POST',
                body: {
                    scheduled_at: scheduledAt
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                
                // Close the modal
                const modal = bootstrap.Modal.getInstance(campaignModal);
                modal.hide();
                
                // Reload campaigns table
                await loadCampaigns();
                
                // Update statistics
                await loadNewsletterStats();
            } else {
                Admin.showAlert(data.message || 'Failed to schedule campaign.', 'danger');
            }
        } catch (error) {
            console.error('Error scheduling campaign:', error);
            Admin.showAlert('Network error. Could not schedule campaign.', 'danger');
        } finally {
            // Re-enable schedule button
            if (scheduleCampaignBtn) {
                scheduleCampaignBtn.disabled = false;
                scheduleCampaignBtn.innerHTML = 'Schedule';
            }
        }
    }
    
    /**
     * Show the test email modal
     */
    async function showTestEmailModal(campaignId) {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/campaigns/${campaignId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const campaign = data.data;
                
                // Store campaign ID in the test email button
                if (testEmailBtn) {
                    testEmailBtn.setAttribute('data-campaign-id', campaignId);
                }
                
                // Clear test email input
                if (testEmailInput) {
                    testEmailInput.value = '';
                }
                
                // Show the test email modal
                const testEmailModal = new bootstrap.Modal(document.getElementById('testEmailModal'));
                testEmailModal.show();
            } else {
                Admin.showAlert(data.message || 'Failed to load campaign.', 'danger');
            }
        } catch (error) {
            console.error('Error loading campaign for test email:', error);
            Admin.showAlert('Network error. Could not load campaign.', 'danger');
        }
    }
    
    /**
     * Send a test email
     */
    async function sendTestEmail() {
        const campaignId = testEmailBtn ? testEmailBtn.getAttribute('data-campaign-id') : '';
        const testEmail = testEmailInput ? testEmailInput.value : '';
        
        if (!campaignId) {
            Admin.showAlert('No campaign selected.', 'warning');
            return;
        }
        
        if (!testEmail) {
            Admin.showAlert('Please enter an email address.', 'warning');
            return;
        }
        
        // Validate email
        if (!validateEmail(testEmail)) {
            Admin.showAlert('Please enter a valid email address.', 'warning');
            return;
        }
        
        try {
            // Disable test email button
            if (testEmailBtn) {
                testEmailBtn.disabled = true;
                testEmailBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
            }
            
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/campaigns/${campaignId}/test`, {
                method: 'POST',
                body: {
                    test_email: testEmail
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('testEmailModal'));
                modal.hide();
            } else {
                Admin.showAlert(data.message || 'Failed to send test email.', 'danger');
            }
        } catch (error) {
            console.error('Error sending test email:', error);
            Admin.showAlert('Network error. Could not send test email.', 'danger');
        } finally {
            // Re-enable test email button
            if (testEmailBtn) {
                testEmailBtn.disabled = false;
                testEmailBtn.innerHTML = 'Send Test';
            }
        }
    }
    
    /**
     * Delete a campaign
     */
    async function deleteCampaign(campaignId) {
        if (!confirm('Are you sure you want to delete this campaign?')) {
            return;
        }
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/campaigns/${campaignId}`, {
                method: 'DELETE'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                
                // Reload campaigns table
                await loadCampaigns();
                
                // Update statistics
                await loadNewsletterStats();
            } else {
                Admin.showAlert(data.message || 'Failed to delete campaign.', 'danger');
            }
        } catch (error) {
            console.error('Error deleting campaign:', error);
            Admin.showAlert('Network error. Could not delete campaign.', 'danger');
        }
    }
    
    /**
     * Send a quick email
     */
    async function sendQuickEmail(event) {
        event.preventDefault();
        
        const subject = quickEmailSubjectInput ? quickEmailSubjectInput.value : '';
        const content = quickEmailContentInput ? quickEmailContentInput.value : '';
        const senderName = quickEmailSenderNameInput ? quickEmailSenderNameInput.value : '';
        const senderEmail = quickEmailSenderEmailInput ? quickEmailSenderEmailInput.value : '';
        
        // Validate form data
        if (!subject || !content || !senderName || !senderEmail) {
            Admin.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        // Validate email
        if (!validateEmail(senderEmail)) {
            Admin.showAlert('Please enter a valid sender email.', 'warning');
            return;
        }
        
        if (!confirm('Are you sure you want to send this email to all active subscribers?')) {
            return;
        }
        
        try {
            // Disable send button
            if (sendQuickEmailBtn) {
                sendQuickEmailBtn.disabled = true;
                sendQuickEmailBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
            }
            
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/newsletter/send-email`, {
                method: 'POST',
                body: {
                    subject: subject,
                    body: content,
                    sender_name: senderName,
                    sender_email: senderEmail
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                
                // Clear form
                if (quickEmailSubjectInput) {
                    quickEmailSubjectInput.value = '';
                }
                if (quickEmailContentInput) {
                    quickEmailContentInput.value = '';
                }
                if (quickEmailSenderNameInput) {
                    quickEmailSenderNameInput.value = '';
                }
                if (quickEmailSenderEmailInput) {
                    quickEmailSenderEmailInput.value = '';
                }
                
                // Update statistics
                await loadNewsletterStats();
            } else {
                Admin.showAlert(data.message || 'Failed to send email.', 'danger');
            }
        } catch (error) {
            console.error('Error sending quick email:', error);
            Admin.showAlert('Network error. Could not send email.', 'danger');
        } finally {
            // Re-enable send button
            if (sendQuickEmailBtn) {
                sendQuickEmailBtn.disabled = false;
                sendQuickEmailBtn.innerHTML = 'Send Email';
            }
        }
    }
    
    /**
     * Validate an email address
     */
    function validateEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    // Expose functions to global scope for inline event handlers
    window.showNewCampaignModal = showNewCampaignModal;
});