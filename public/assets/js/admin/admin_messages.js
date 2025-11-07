/**
 * public/assets/js/admin/admin_messages.js
 *
 * This file handles the functionality for the admin messages page.
 * It includes loading messages, marking messages as read, and deleting messages.
 */
document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const messagesTableBody = document.getElementById('messagesTableBody');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const messagesTableCard = document.getElementById('messagesTableCard');
    const baseUrlPath = window.AppBaseUrlPath || '';
    
    // State
    let messages = [];
    
    /**
     * Loads all messages from the server and displays them in the table.
     */
    async function loadMessages() {
        try {
            // Show loading spinner
            loadingSpinner.style.display = 'block';
            messagesTableCard.style.display = 'none';
            
            // Fetch messages from API
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/messages/data`, {
                method: 'GET'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                messages = data.data;
                renderMessagesTable(messages);
                messagesTableCard.style.display = 'block';
            } else {
                Admin.showAlert(data.message || 'Failed to load messages.', 'danger');
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            Admin.showAlert('Network error. Could not load messages.', 'danger');
        } finally {
            loadingSpinner.style.display = 'none';
        }
    }
    
    /**
     * Renders the messages table with the provided data.
     * @param {Array} messagesData - The messages data to render.
     */
    function renderMessagesTable(messagesData) {
        messagesTableBody.innerHTML = '';
        
        if (messagesData.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = `
                <td colspan="7" class="text-center py-4">
                    <div class="text-muted">
                        <i class="fa-solid fa-inbox fa-3x mb-3 d-block"></i>
                        <p>No messages found.</p>
                    </div>
                </td>
            `;
            messagesTableBody.appendChild(emptyRow);
            return;
        }
        
        messagesData.forEach(message => {
            const row = document.createElement('tr');
            
            // Format date
            const createdDate = new Date(message.created_at);
            const formattedDate = createdDate.toLocaleDateString() + ' ' + createdDate.toLocaleTimeString();
            
            // Determine status badge
            let statusBadge = '';
            if (message.status === 'new') {
                statusBadge = '<span class="badge bg-primary">New</span>';
            } else if (message.status === 'in_progress') {
                statusBadge = '<span class="badge bg-info">In Progress</span>';
            } else if (message.status === 'resolved') {
                statusBadge = '<span class="badge bg-success">Resolved</span>';
            } else if (message.status === 'closed') {
                statusBadge = '<span class="badge bg-secondary">Closed</span>';
            }
            
            // Determine priority badge
            let priorityBadge = '';
            if (message.priority === 'low') {
                priorityBadge = '<span class="badge bg-success">Low</span>';
            } else if (message.priority === 'medium') {
                priorityBadge = '<span class="badge bg-warning text-dark">Medium</span>';
            } else if (message.priority === 'high') {
                priorityBadge = '<span class="badge bg-danger">High</span>';
            } else if (message.priority === 'urgent') {
                priorityBadge = '<span class="badge bg-danger">Urgent</span>';
            }
            
            // Truncate message for display
            const truncatedMessage = message.message.length > 100 
                ? message.message.substring(0, 100) + '...' 
                : message.message;
            
            row.innerHTML = `
                <td>${message.id}</td>
                <td>${Admin.escapeHtml(message.name)}</td>
                <td>${Admin.escapeHtml(message.email)}</td>
                <td>${Admin.escapeHtml(message.subject)}</td>
                <td>
                    <div>${Admin.escapeHtml(truncatedMessage)}</div>
                    <div class="mt-1">
                        ${statusBadge} ${priorityBadge}
                    </div>
                </td>
                <td>${formattedDate}</td>
                <td>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-primary view-message-btn" data-id="${message.id}" data-bs-toggle="modal" data-bs-target="#viewMessageModal">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        ${message.status === 'new' ? `
                        <button type="button" class="btn btn-sm btn-success mark-read-btn" data-id="${message.id}">
                            <i class="fa-solid fa-check"></i>
                        </button>
                        ` : ''}
                        <button type="button" class="btn btn-sm btn-danger delete-message-btn" data-id="${message.id}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            messagesTableBody.appendChild(row);
        });
        
        // Add event listeners to the buttons
        addMessageButtonListeners();
    }
    
    /**
     * Adds event listeners to message action buttons.
     */
    function addMessageButtonListeners() {
        // Mark as read buttons
        document.querySelectorAll('.mark-read-btn').forEach(button => {
            button.addEventListener('click', async function() {
                const messageId = this.getAttribute('data-id');
                await markMessageAsRead(messageId);
            });
        });
        
        // Delete message buttons
        document.querySelectorAll('.delete-message-btn').forEach(button => {
            button.addEventListener('click', async function() {
                const messageId = this.getAttribute('data-id');
                await deleteMessage(messageId);
            });
        });
        
        // View message buttons
        document.querySelectorAll('.view-message-btn').forEach(button => {
            button.addEventListener('click', function() {
                const messageId = this.getAttribute('data-id');
                viewMessage(messageId);
            });
        });
    }
    
    /**
     * Marks a message as read (in_progress).
     * @param {number} messageId - The ID of the message to mark as read.
     */
    async function markMessageAsRead(messageId) {
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/messages/${messageId}/mark-read`, {
                method: 'POST'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                // Reload messages to update the table
                await loadMessages();
            } else {
                Admin.showAlert(data.message || 'Failed to mark message as read.', 'danger');
            }
        } catch (error) {
            console.error('Error marking message as read:', error);
            Admin.showAlert('Network error. Could not mark message as read.', 'danger');
        }
    }
    
    /**
     * Deletes a message.
     * @param {number} messageId - The ID of the message to delete.
     */
    async function deleteMessage(messageId) {
        if (!confirm('Are you sure you want to delete this message? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await Admin.fetchWithCsrf(`${baseUrlPath}/api/admin/messages/${messageId}`, {
                method: 'DELETE'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                Admin.showAlert(data.message, 'success');
                // Reload messages to update the table
                await loadMessages();
            } else {
                Admin.showAlert(data.message || 'Failed to delete message.', 'danger');
            }
        } catch (error) {
            console.error('Error deleting message:', error);
            Admin.showAlert('Network error. Could not delete message.', 'danger');
        }
    }
    
    /**
     * Views a message in a modal.
     * @param {number} messageId - The ID of the message to view.
     */
    function viewMessage(messageId) {
        const message = messages.find(m => m.id == messageId);
    if (!message) return;
    
    // Create modal if it doesn't exist
    let modal = document.getElementById('viewMessageModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.className = 'modal fade'; // اضافه کردن کلاس modal-dark برای پس زمینه تیره
        modal.id = 'viewMessageModal';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-labelledby', 'viewMessageModalLabel');
        modal.setAttribute('aria-hidden', 'true');
        
        modal.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content bg-dark text-white border-secondary"> <!-- تغییرات اینجا -->
                    <div class="modal-header bg-dark border-secondary">
                        <h5 class="modal-title" id="viewMessageModalLabel">Message Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body bg-dark">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Name:</strong> <span id="modal-message-name"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Email:</strong> <span id="modal-message-email"></span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Phone:</strong> <span id="modal-message-phone"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Received:</strong> <span id="modal-message-date"></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <strong>Status:</strong> <span id="modal-message-status"></span>
                        </div>
                        <div class="mb-3">
                            <strong>Priority:</strong> <span id="modal-message-priority"></span>
                        </div>
                        <div class="mb-3">
                            <strong>Subject:</strong> <span id="modal-message-subject"></span>
                        </div>
                        <div class="mb-3">
                            <strong>Message:</strong>
                            <div class="mt-2 p-3 bg-secondary bg-opacity-25 rounded" id="modal-message-content"></div>
                        </div>
                    </div>
                    <div class="modal-footer bg-dark border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                        ${message.status === 'new' ? `
                        <button type="button" class="btn btn-success modal-mark-read-btn" data-id="${message.id}">
                            Mark as Read
                        </button>
                        ` : ''}
                        <button type="button" class="btn btn-danger modal-delete-btn" data-id="${message.id}">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Add event listeners to modal buttons
        modal.querySelector('.modal-mark-read-btn')?.addEventListener('click', async function() {
            await markMessageAsRead(messageId);
            bootstrap.Modal.getInstance(modal).hide();
        });
        
        modal.querySelector('.modal-delete-btn').addEventListener('click', async function() {
            if (confirm('Are you sure you want to delete this message?')) {
                await deleteMessage(messageId);
                bootstrap.Modal.getInstance(modal).hide();
            }
        });
    }
    
    // Populate modal with message data
    document.getElementById('modal-message-name').textContent = Admin.escapeHtml(message.name);
    document.getElementById('modal-message-email').textContent = Admin.escapeHtml(message.email);
    document.getElementById('modal-message-phone').textContent = Admin.escapeHtml(message.phone || 'Not provided');
    document.getElementById('modal-message-date').textContent = new Date(message.created_at).toLocaleString();
    
    // Status badge
    let statusBadge = '';
    if (message.status === 'new') {
        statusBadge = '<span class="badge bg-primary">New</span>';
    } else if (message.status === 'in_progress') {
        statusBadge = '<span class="badge bg-info">In Progress</span>';
    } else if (message.status === 'resolved') {
        statusBadge = '<span class="badge bg-success">Resolved</span>';
    } else if (message.status === 'closed') {
        statusBadge = '<span class="badge bg-secondary">Closed</span>';
    }
    document.getElementById('modal-message-status').innerHTML = statusBadge;
    
    // Priority badge
    let priorityBadge = '';
    if (message.priority === 'low') {
        priorityBadge = '<span class="badge bg-success">Low</span>';
    } else if (message.priority === 'medium') {
        priorityBadge = '<span class="badge bg-warning text-dark">Medium</span>';
    } else if (message.priority === 'high') {
        priorityBadge = '<span class="badge bg-danger">High</span>';
    } else if (message.priority === 'urgent') {
        priorityBadge = '<span class="badge bg-danger">Urgent</span>';
    }
    document.getElementById('modal-message-priority').innerHTML = priorityBadge;
    
    document.getElementById('modal-message-subject').textContent = Admin.escapeHtml(message.subject);
    document.getElementById('modal-message-content').textContent = Admin.escapeHtml(message.message);
    
    // Show modal
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
    }
    
    // Initialize the page by loading messages
    loadMessages();
});