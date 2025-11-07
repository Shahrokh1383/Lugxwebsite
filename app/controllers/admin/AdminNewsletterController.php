<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\NewsletterSubscriber;
use App\Models\EmailCampaign;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\NewsletterService;
use PDOException;
use Exception;

class AdminNewsletterController extends Controller
{
    private AuthService $authService;
    private NewsletterSubscriber $subscriberModel;
    private EmailCampaign $campaignModel;
    private MailService $mailService;
    private NewsletterService $newsletterService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->subscriberModel = new NewsletterSubscriber();
        $this->campaignModel = new EmailCampaign();
        $this->mailService = new MailService();
        $this->newsletterService = new NewsletterService();

        if (!$this->subscriberModel) {
            error_log("ERROR: AdminNewsletterController - Failed to load NewsletterSubscriber model.");
        }
        
        if (!$this->campaignModel) {
            error_log("ERROR: AdminNewsletterController - Failed to load EmailCampaign model.");
        }
    }

    /**
     * Renders the static HTML view for managing newsletter subscribers and campaigns.
     * GET /admin/newsletter
     */
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->redirect('/admin/login');
            return;
        }
        
        $this->renderHtmlView('frontend/admin/admin_newsletter.html');
    }

    /**
     * Retrieves all newsletter subscribers for the admin panel.
     * This method acts as an API endpoint.
     * GET /api/admin/newsletter/subscribers
     */
    public function getSubscribers(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $filters = $_GET ?? [];
            $subscribers = $this->subscriberModel->getAll($filters);
            
            // Decode preferences JSON for each subscriber
            foreach ($subscribers as &$subscriber) {
                if (!empty($subscriber['preferences'])) {
                    $subscriber['preferences'] = json_decode($subscriber['preferences'], true);
                }
            }
            
            $this->renderApiJson([
                'success' => true,
                'message' => 'Newsletter subscribers fetched successfully.',
                'data' => $subscribers
            ]);
        } catch (PDOException $e) {
            error_log("Database Error in getSubscribers: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in getSubscribers: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Retrieves all email campaigns for the admin panel.
     * This method acts as an API endpoint.
     * GET /api/admin/newsletter/campaigns
     */
    public function getCampaigns(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $filters = $_GET ?? [];
            $campaigns = $this->campaignModel->getAllCampaigns($filters);
            
            $this->renderApiJson([
                'success' => true,
                'message' => 'Email campaigns fetched successfully.',
                'data' => $campaigns
            ]);
        } catch (PDOException $e) {
            error_log("Database Error in getCampaigns: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in getCampaigns: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Get a specific campaign by ID
     * GET /api/admin/newsletter/campaigns/{id}
     */
    public function getCampaign(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $campaign = $this->campaignModel->getCampaignById($id);
            
            if (!$campaign) {
                $this->renderApiJson(['success' => false, 'message' => 'Campaign not found.'], 404);
                return;
            }
            
            $this->renderApiJson([
                'success' => true,
                'message' => 'Campaign fetched successfully.',
                'data' => $campaign
            ]);
        } catch (PDOException $e) {
            error_log("Database Error in getCampaign: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in getCampaign: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Create a new email campaign
     * POST /api/admin/newsletter/campaigns
     */
    public function createCampaign(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $this->getJsonData();
        
        // Validate required fields
        $requiredFields = ['name', 'subject', 'content', 'sender_name', 'sender_email'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $this->renderApiJson(['success' => false, 'message' => "Field '{$field}' is required."], 400);
                return;
            }
        }
        
        // Validate email
        if (!filter_var($data['sender_email'], FILTER_VALIDATE_EMAIL)) {
            $this->renderApiJson(['success' => false, 'message' => 'Invalid sender email format.'], 400);
            return;
        }
        
        // Set default status to draft
        $data['status'] = 'draft';
        
        try {
            $campaignId = $this->campaignModel->createCampaign($data);
            
            if ($campaignId) {
                $this->renderApiJson([
                    'success' => true,
                    'message' => 'Campaign created successfully.',
                    'data' => ['id' => $campaignId]
                ]);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to create campaign.'], 500);
            }
        } catch (PDOException $e) {
            error_log("Database Error in createCampaign: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in createCampaign: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Update an existing campaign
     * PUT /api/admin/newsletter/campaigns/{id}
     */
    public function updateCampaign(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $this->getJsonData();
        
        // Check if campaign exists
        $campaign = $this->campaignModel->getCampaignById($id);
        if (!$campaign) {
            $this->renderApiJson(['success' => false, 'message' => 'Campaign not found.'], 404);
            return;
        }
        
        // Check if campaign is already sent
        if ($campaign['status'] === 'sent') {
            $this->renderApiJson(['success' => false, 'message' => 'Cannot update a sent campaign.'], 400);
            return;
        }
        
        // Validate email if provided
        if (isset($data['sender_email']) && !filter_var($data['sender_email'], FILTER_VALIDATE_EMAIL)) {
            $this->renderApiJson(['success' => false, 'message' => 'Invalid sender email format.'], 400);
            return;
        }
        
        try {
            $result = $this->campaignModel->updateCampaign($id, $data);
            
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Campaign updated successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to update campaign.'], 500);
            }
        } catch (PDOException $e) {
            error_log("Database Error in updateCampaign: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in updateCampaign: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Delete a campaign
     * DELETE /api/admin/newsletter/campaigns/{id}
     */
    public function deleteCampaign(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $campaign = $this->campaignModel->getCampaignById($id);
            
            if (!$campaign) {
                $this->renderApiJson(['success' => false, 'message' => 'Campaign not found.'], 404);
                return;
            }
            
            // Check if campaign is already sent
            if ($campaign['status'] === 'sent') {
                $this->renderApiJson(['success' => false, 'message' => 'Cannot delete a sent campaign.'], 400);
                return;
            }
            
            $result = $this->campaignModel->deleteCampaign($id);
            
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Campaign deleted successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to delete campaign.'], 500);
            }
        } catch (PDOException $e) {
            error_log("Database Error in deleteCampaign: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in deleteCampaign: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Schedule a campaign to be sent
     * POST /api/admin/newsletter/campaigns/{id}/schedule
     */
    public function scheduleCampaign(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $this->getJsonData();
        $scheduledAt = $data['scheduled_at'] ?? null;
        
        if (!$scheduledAt) {
            $this->renderApiJson(['success' => false, 'message' => 'Scheduled date is required.'], 400);
            return;
        }
        
        // Validate date format
        $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt);
        if (!$dateTime || $dateTime < new \DateTime()) {
            $this->renderApiJson(['success' => false, 'message' => 'Invalid scheduled date. Must be a future date in YYYY-MM-DD HH:MM:SS format.'], 400);
            return;
        }
        
        try {
            $campaign = $this->campaignModel->getCampaignById($id);
            
            if (!$campaign) {
                $this->renderApiJson(['success' => false, 'message' => 'Campaign not found.'], 404);
                return;
            }
            
            // Check if campaign is already sent or scheduled
            if (in_array($campaign['status'], ['sent', 'sending'])) {
                $this->renderApiJson(['success' => false, 'message' => 'Cannot schedule a sent or sending campaign.'], 400);
                return;
            }
            
            $result = $this->campaignModel->updateCampaign($id, [
                'status' => 'scheduled',
                'scheduled_at' => $scheduledAt
            ]);
            
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Campaign scheduled successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to schedule campaign.'], 500);
            }
        } catch (PDOException $e) {
            error_log("Database Error in scheduleCampaign: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in scheduleCampaign: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Send a campaign immediately
     * POST /api/admin/newsletter/campaigns/{id}/send
     */
    public function sendCampaign(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $campaign = $this->campaignModel->getCampaignById($id);
            
            if (!$campaign) {
                $this->renderApiJson(['success' => false, 'message' => 'Campaign not found.'], 404);
                return;
            }
            
            // Check if campaign is already sent or sending
            if (in_array($campaign['status'], ['sent', 'sending'])) {
                $this->renderApiJson(['success' => false, 'message' => 'Campaign is already sent or being sent.'], 400);
                return;
            }
            
            // Update campaign status to sending
            $this->campaignModel->updateCampaign($id, ['status' => 'sending']);
            
            // Get active subscribers
            $subscribers = $this->subscriberModel->getAll(['status' => 'active']);
            
            if (empty($subscribers)) {
                $this->campaignModel->updateCampaign($id, ['status' => 'draft']);
                $this->renderApiJson(['success' => false, 'message' => 'No active subscribers to send email to.'], 404);
                return;
            }
            
            // Update recipients count
            $this->campaignModel->updateCampaign($id, ['recipients_count' => count($subscribers)]);
            
            // Send emails to all subscribers
            $successCount = 0;
            foreach ($subscribers as $subscriber) {
                $subscriberName = $subscriber['name'] ?? '';
                $result = $this->mailService->sendMail(
                    $subscriber['email'],
                    $subscriberName,
                    $campaign['subject'],
                    $campaign['content']
                );
                
                if ($result) {
                    $successCount++;
                }
            }
            
            // Update campaign status and stats
            $this->campaignModel->updateCampaign($id, [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
                'recipients_count' => $successCount
            ]);
            
            $this->renderApiJson([
                'success' => true, 
                'message' => "Campaign sent successfully to {$successCount} subscribers."
            ]);
            
        } catch (PDOException $e) {
            error_log("Database Error in sendCampaign: " . $e->getMessage());
            
            // Reset campaign status to draft on error
            $this->campaignModel->updateCampaign($id, ['status' => 'draft']);
            
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in sendCampaign: " . $e->getMessage());
            
            // Reset campaign status to draft on error
            $this->campaignModel->updateCampaign($id, ['status' => 'draft']);
            
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Send a test email for a campaign
     * POST /api/admin/newsletter/campaigns/{id}/test
     */
    public function sendTestEmail(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $this->getJsonData();
        $testEmail = $data['test_email'] ?? null;
        
        if (!$testEmail) {
            $this->renderApiJson(['success' => false, 'message' => 'Test email is required.'], 400);
            return;
        }
        
        // Validate email
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $this->renderApiJson(['success' => false, 'message' => 'Invalid test email format.'], 400);
            return;
        }
        
        try {
            $result = $this->newsletterService->sendTestEmail($id, $testEmail);
            $this->renderApiJson($result);
        } catch (Exception $e) {
            error_log("Error in sendTestEmail: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Get campaign statistics
     * GET /api/admin/newsletter/campaigns/{id}/stats
     */
    public function getCampaignStats(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $result = $this->newsletterService->getCampaignStats($id);
            $this->renderApiJson($result);
        } catch (Exception $e) {
            error_log("Error in getCampaignStats: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Deletes a specific newsletter subscriber.
     * DELETE /api/admin/newsletter/subscribers/{id}
     */
    public function deleteSubscriber(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $result = $this->subscriberModel->delete($id);
            if ($result) {
                $this->renderApiJson(['success' => true, 'message' => 'Subscriber deleted successfully.']);
            } else {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to delete subscriber. Subscriber not found.'], 404);
            }
        } catch (PDOException $e) {
            error_log("Database Error in deleteSubscriber: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        }
    }

    /**
     * Sends a group email to all active subscribers.
     * POST /api/admin/newsletter/send-email
     */
    public function sendGroupEmail(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        $data = $this->getJsonData();
        $subject = $data['subject'] ?? null;
        $body = $data['body'] ?? null;
        $senderName = $data['sender_name'] ?? 'Admin';
        $senderEmail = $data['sender_email'] ?? 'admin@example.com';

        if (!$subject || !$body) {
            $this->renderApiJson(['success' => false, 'message' => 'Subject and body are required.'], 400);
            return;
        }

        try {
            $subscribers = $this->subscriberModel->where(['status' => 'active']);
    
            if (empty($subscribers)) {
                $this->renderApiJson(['success' => false, 'message' => 'No active subscribers to send email to.'], 404);
                return;
            }
            
            // Create a temporary campaign record for tracking
            $campaignData = [
                'name' => 'Quick Email: ' . $subject,
                'subject' => $subject,
                'content' => $body,
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'status' => 'sending',
                'recipients_count' => count($subscribers)
            ];
            
            $campaignId = $this->campaignModel->createCampaign($campaignData);
            
            if (!$campaignId) {
                $this->renderApiJson(['success' => false, 'message' => 'Failed to create campaign record.'], 500);
                return;
            }
    
            $successCount = 0;
            foreach ($subscribers as $subscriber) {
                $subscriberName = $subscriber['name'] ?? '';
                $result = $this->mailService->sendMail(
                    $subscriber['email'],
                    $subscriberName,
                    $subject,
                    $body
                );
                
                if ($result) {
                    $successCount++;
                }
            }
            
            // Update campaign status and stats
            $this->campaignModel->updateCampaign($campaignId, [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
                'recipients_count' => $successCount
            ]);
    
            $this->renderApiJson([
                'success' => true, 
                'message' => "Group email sent successfully to {$successCount} subscribers."
            ]);
    
        } catch (PDOException $e) {
            error_log("Database Error in sendGroupEmail: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in sendGroupEmail: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Get newsletter statistics
     * GET /api/admin/newsletter/stats
     */
    public function getStats(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $totalSubscribers = $this->subscriberModel->getActiveSubscribersCount();
            
            // Get campaign stats
            $campaigns = $this->campaignModel->getAllCampaigns();
            $totalCampaigns = count($campaigns);
            $sentCampaigns = 0;
            $scheduledCampaigns = 0;
            $draftCampaigns = 0;
            
            foreach ($campaigns as $campaign) {
                switch ($campaign['status']) {
                    case 'sent':
                        $sentCampaigns++;
                        break;
                    case 'scheduled':
                        $scheduledCampaigns++;
                        break;
                    case 'draft':
                        $draftCampaigns++;
                        break;
                }
            }
            
            $this->renderApiJson([
                'success' => true,
                'message' => 'Newsletter statistics fetched successfully.',
                'data' => [
                    'total_subscribers' => $totalSubscribers,
                    'total_campaigns' => $totalCampaigns,
                    'sent_campaigns' => $sentCampaigns,
                    'scheduled_campaigns' => $scheduledCampaigns,
                    'draft_campaigns' => $draftCampaigns
                ]
            ]);
        } catch (PDOException $e) {
            error_log("Database Error in getStats: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'Database error.'], 500);
        } catch (Exception $e) {
            error_log("General Error in getStats: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }

    /**
     * Process scheduled campaigns (for cron job)
     * POST /api/admin/newsletter/process-scheduled
     */
    public function processScheduledCampaigns(): void
    {
        // This endpoint should be protected by a special token or IP whitelist for cron jobs
        // For now, we'll just check for admin access
        
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
            return;
        }

        try {
            $results = $this->newsletterService->processScheduledCampaigns();
            $this->renderApiJson([
                'success' => true,
                'message' => 'Scheduled campaigns processed.',
                'data' => $results
            ]);
        } catch (Exception $e) {
            error_log("Error in processScheduledCampaigns: " . $e->getMessage());
            $this->renderApiJson(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
        }
    }
}