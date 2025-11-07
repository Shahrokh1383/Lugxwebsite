<?php
namespace App\Services;
use App\Models\ContactMessage;
use App\Services\ValidationService;
use App\Services\SecurityService;
class ContactService
{
    private ContactMessage $contactMessageModel;
    private ValidationService $validationService;
    private SecurityService $securityService;
    public function __construct()
    {
        $this->contactMessageModel = new ContactMessage();
        $this->validationService = new ValidationService();
        $this->securityService = new SecurityService();
    }
    
    /**
     * Validates and stores a new contact message.
     *
     * @param array $data
     * @return array
     */
    public function submitMessage(array $data): array
    {
        // 1. Validate the input data with rules for all required fields
        $rules = [
            'name' => 'required|min:2',
            'surname' => 'required|min:2',
            'email' => 'required|email',
            'phone' => 'optional|phone',
            'subject' => 'optional|max:255',
            'message' => 'required|min:10',
            'priority' => 'required|in:low,medium,high,urgent',
        ];
        
        // Perform validation and get the result
        $isValid = $this->validationService->validate($data, $rules);
        
        // Get validation errors if any
        $errors = $this->validationService->getErrors();
        
        if (!empty($errors)) {
            // Return detailed errors array with field names as keys
            return ['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors];
        }
        
        // 2. Sanitize and combine 'name' and 'surname' for database storage
        $full_name = $this->securityService->sanitizeString($data['name']) . ' ' . $this->securityService->sanitizeString($data['surname']);
        $sanitizedData = [
            'name' => $full_name,
            'email' => $this->securityService->sanitizeEmail($data['email']),
            'phone' => $this->securityService->sanitizeString($data['phone'] ?? ''),
            'subject' => $this->securityService->sanitizeString($data['subject'] ?? ''),
            'message' => $this->securityService->sanitizeString($data['message']),
            'priority' => $data['priority'],
            'status' => 'new', // Set default status to 'new'
        ];
        
        // 3. Create the message in the database
        $messageId = $this->contactMessageModel->createMessage($sanitizedData);
        if ($messageId) {
            return ['status' => 'success', 'message' => 'Your message has been sent successfully. We will get back to you shortly!', 'message_id' => $messageId];
        } else {
            return ['status' => 'error', 'message' => 'Failed to save your message. Please try again later.'];
        }
    }
}