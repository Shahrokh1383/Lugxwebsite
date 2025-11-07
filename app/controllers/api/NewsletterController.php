<?php
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\MailService;
use Exception;

class NewsletterController extends Controller
{
    private NewsletterSubscriber $subscriberModel;

    public function __construct()
    {
        parent::__construct();
        try {
            $this->subscriberModel = new NewsletterSubscriber();
        }catch (\Throwable $th) {
            error_log("CRITICAL ERROR: NewsletterController::__construct - Failed to instantiate NewsletterSubscriber model: " . $th->getMessage());
            $this->renderApiJson(['status' => 'error', 'message' => 'Internal server error during model initialization.'], 500);
        }
    }

     /**
     * Handles newsletter subscription request.
     * POST /api/newsletter/subscribe
     */
    public function subscribe(): void
    {
        // Get the raw POST data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Basic validation
        if (empty($data) || !isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->renderApiJson(['status' => 'error', 'message' => 'Invalid or empty email address provided.'], 400);
            return;
        }

        $email = $data['email'];

        try {
            if ($this->subscriberModel->subscribe($email)) {
                // Subscription was successful
                // Send a notification email to the admin
                $mailService = new MailService();
                $subject = "New Newsletter Subscriber!";
                $body = "<h1>New Subscription to Newsletter</h1><p>A new user with the email address <strong>{$email}</strong> has subscribed to the newsletter.</p><p>Subscription Time: " . date('Y-m-d H:i:s') . "</p>";
                $altBody = "A new user with email: {$email} has subscribed to the newsletter.";

                $mailService->sendMail('lugx@gamil.com', 'Admin', $subject, $body, $altBody);

                $this->renderApiJson(['status' => 'success', 'message' => 'You have been successfully subscribed to our newsletter!'], 200);
            } else {
                // Subscription failed for an unknown reason (e.g., database error)
                $this->renderApiJson(['status' => 'error', 'message' => 'Could not subscribe you at this time. Please try again later.'], 500);
            }
        } catch (\Throwable $th) {
            // Log any unexpected errors
            error_log("ERROR: NewsletterController::subscribe - An unexpected error occurred: " . $th->getMessage());
            $this->renderApiJson(['status' => 'error', 'message' => 'An unexpected server error occurred.'], 500);
        }
    }
}
