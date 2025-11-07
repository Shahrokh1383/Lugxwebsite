<?php
namespace App\Services;

use App\Models\NewsletterSubscriber;
use App\Models\EmailCampaign;
use App\Services\MailService;
use PDOException;
use Exception;

class NewsletterService
{
    private NewsletterSubscriber $subscriberModel;
    private EmailCampaign $campaignModel;
    private MailService $mailService;

    public function __construct()
    {
        $this->subscriberModel = new NewsletterSubscriber();
        $this->campaignModel = new EmailCampaign();
        $this->mailService = new MailService();
    }

    /**
     * Process scheduled campaigns that are ready to be sent
     * 
     * @return array Results of the processing
     */
    public function processScheduledCampaigns(): array
    {
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            // Get campaigns ready to be sent
            $campaigns = $this->campaignModel->getReadyToSendCampaigns();
            
            foreach ($campaigns as $campaign) {
                $results['processed']++;
                
                // Update campaign status to sending
                $this->campaignModel->updateCampaign($campaign['id'], ['status' => 'sending']);
                
                try {
                    // Get active subscribers
                    $subscribers = $this->subscriberModel->getAll(['status' => 'active']);
                    
                    if (empty($subscribers)) {
                        $this->campaignModel->updateCampaign($campaign['id'], ['status' => 'draft']);
                        $results['errors'][] = "Campaign ID {$campaign['id']}: No active subscribers";
                        $results['failed']++;
                        continue;
                    }
                    
                    // Update recipients count
                    $this->campaignModel->updateCampaign($campaign['id'], ['recipients_count' => count($subscribers)]);
                    
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
                    $this->campaignModel->updateCampaign($campaign['id'], [
                        'status' => 'sent',
                        'sent_at' => date('Y-m-d H:i:s'),
                        'recipients_count' => $successCount
                    ]);
                    
                    $results['success']++;
                } catch (Exception $e) {
                    // Reset campaign status to draft on error
                    $this->campaignModel->updateCampaign($campaign['id'], ['status' => 'draft']);
                    $results['errors'][] = "Campaign ID {$campaign['id']}: " . $e->getMessage();
                    $results['failed']++;
                }
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Database Error in processScheduledCampaigns: " . $e->getMessage());
            $results['errors'][] = "Database error: " . $e->getMessage();
            return $results;
        } catch (Exception $e) {
            error_log("General Error in processScheduledCampaigns: " . $e->getMessage());
            $results['errors'][] = "General error: " . $e->getMessage();
            return $results;
        }
    }

    /**
     * Send a test email for a campaign
     * 
     * @param int $campaignId
     * @param string $testEmail
     * @return array
     */
    public function sendTestEmail(int $campaignId, string $testEmail): array
    {
        try {
            $campaign = $this->campaignModel->getCampaignById($campaignId);
            
            if (!$campaign) {
                return ['success' => false, 'message' => 'Campaign not found.'];
            }
            
            // Send test email
            $result = $this->mailService->sendMail(
                $testEmail,
                'Test Recipient',
                $campaign['subject'],
                $campaign['content']
            );
            
            if ($result) {
                return ['success' => true, 'message' => 'Test email sent successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to send test email.'];
            }
        } catch (Exception $e) {
            error_log("Error sending test email: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while sending test email.'];
        }
    }

    /**
     * Get campaign statistics
     * 
     * @param int $campaignId
     * @return array
     */
    public function getCampaignStats(int $campaignId): array
    {
        try {
            $campaign = $this->campaignModel->getCampaignById($campaignId);
            
            if (!$campaign) {
                return ['success' => false, 'message' => 'Campaign not found.'];
            }
            
            // Calculate open rate and click rate if campaign was sent
            $openRate = 0;
            $clickRate = 0;
            
            if ($campaign['status'] === 'sent' && $campaign['recipients_count'] > 0) {
                $openRate = ($campaign['opened_count'] / $campaign['recipients_count']) * 100;
                $clickRate = ($campaign['clicked_count'] / $campaign['recipients_count']) * 100;
            }
            
            return [
                'success' => true,
                'data' => [
                    'campaign' => $campaign,
                    'open_rate' => round($openRate, 2),
                    'click_rate' => round($clickRate, 2)
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting campaign stats: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while fetching campaign stats.'];
        }
    }

    /**
     * Track email open
     * 
     * @param int $campaignId
     * @param string $subscriberEmail
     * @return bool
     */
    public function trackEmailOpen(int $campaignId, string $subscriberEmail): bool
    {
        try {
            $campaign = $this->campaignModel->getCampaignById($campaignId);
            
            if (!$campaign) {
                return false;
            }
            
            // Increment opened count
            $this->campaignModel->updateStats($campaignId, [
                'opened_count' => $campaign['opened_count'] + 1
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error tracking email open: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Track email click
     * 
     * @param int $campaignId
     * @param string $subscriberEmail
     * @return bool
     */
    public function trackEmailClick(int $campaignId, string $subscriberEmail): bool
    {
        try {
            $campaign = $this->campaignModel->getCampaignById($campaignId);
            
            if (!$campaign) {
                return false;
            }
            
            // Increment clicked count
            $this->campaignModel->updateStats($campaignId, [
                'clicked_count' => $campaign['clicked_count'] + 1
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error tracking email click: " . $e->getMessage());
            return false;
        }
    }
}