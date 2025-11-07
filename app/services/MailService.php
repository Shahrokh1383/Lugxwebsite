<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// use PSpell\Config; // This import seems incorrect or unused. Should be removed.

class MailService
{
    private PHPMailer $mailer;
    private array $config;

    public function __construct()
    {
        // Ensure ROOT_PATH is defined (e.g., in public/index.php or a bootstrap file)
        $this->config = require ROOT_PATH . '/app/config/mail.php';

        $this->mailer = new PHPMailer(true); // Passing `true` enables exceptions
        try {
            // Server settings
            $this->mailer->isSMTP(); // Send using SMTP
            $this->mailer->Host = $this->config['host']; // Set the SMTP server to send through
            $this->mailer->SMTPAuth = true; // Enable SMTP authentication
            $this->mailer->Username = $this->config['username']; // SMTP username
            $this->mailer->Password = $this->config['password']; // SMTP password
            // Use PHPMailer::ENCRYPTION_SMTPS for port 465 (SSL)
            // Use PHPMailer::ENCRYPTION_STARTTLS for port 587 (TLS)
            $this->mailer->SMTPSecure = $this->config['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->Port = (int)$this->config['port']; // TCP port to connect to

            // Recipients
            $this->mailer->setFrom($this->config['from_address'], $this->config['from_name']);
            $this->mailer->isHTML(true); // Set email format to HTML
            $this->mailer->CharSet = 'UTF-8';
            // Optional: Enable SMTP debugging for troubleshooting
            $this->mailer->SMTPDebug = 2; // Enable verbose debug output
            $this->mailer->Debugoutput = function($str, $level) { error_log("SMTP Debug: $str"); };

        } catch (Exception $e) {
            // This catch block only handles PHPMailer configuration errors during __construct
            error_log("Mailer configuration error: {$e->getMessage()}"); // Use $e->getMessage() for better error
            // It's crucial to know if the mailer object is not properly initialized.
            // You might want to throw the exception or set a flag to prevent further mail sending attempts.
            // For now, we'll just log.
        }
    }

    /**
     * Sends an email.
     *
     * @param string $toEmail The recipient's email address.
     * @param string $toName The recipient's name.
     * @param string $subject The subject of the email.
     * @param string $body HTML content of the email.
     * @param string $altBody Plain text content for non-HTML mail clients.
     * @return bool True on success, false on failure.
     */
    public function sendMail(string $toEmail, string $toName, string $subject, string $body, string $altBody = ''): bool
    {
        try {
            // It's good practice to reset mailer properties before sending a new email
            // to avoid carrying over settings from previous sends (e.g., multiple recipients).
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders(); // Also clear custom headers if any

            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody;

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            // Log the specific PHPMailer error info
            error_log("Message could not be sent to {$toEmail}. Mailer Error: {$this->mailer->ErrorInfo}. Exception: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Sends an email verification link.
     *
     * @param string $toEmail The recipient's email address.
     * @param string $toName The recipient's name (username).
     * @param string $token The verification token.
     * @return bool True on success, false on failure.
     */
    public function sendVerificationEmail(string $toEmail, string $toName, string $token): bool
    {
        $subject = APP_NAME . ' - Email Verification';
        // The verification link will point to a frontend route that then calls the backend API.
        $verificationLink = BASE_URL . '/public/verify-email?token=' . $token;

        // Load HTML email template. Corrected path based on your structure.
        ob_start();
        // Ensure this path is correct: ROOT_PATH . '/views/emails/verification_email.php'
        require ROOT_PATH . '/views/emails/verification_email.php'; 
        $body = ob_get_clean();

        // Replace placeholders in the template.
        $body = str_replace('{{appName}}', APP_NAME, $body);
        $body = str_replace('{{userName}}', $toName, $body);
        $body = str_replace('{{verificationLink}}', $verificationLink, $body);
        $body = str_replace('{{currentYear}}', date('Y'), $body);

        $altBody = "Hello {$toName},\n\nPlease verify your email address by clicking on the link below:\n{$verificationLink}\n\nThank you,\n" . APP_NAME;
        return $this->sendMail($toEmail, $toName, $subject, $body, $altBody);
    }

    /**
     * Sends a password reset link.
     *
     * @param string $toEmail The recipient's email address.
     * @param string $toName The recipient's name (username).
     * @param string $token The password reset token.
     * @return bool True on success, false on failure.
     */
    public function sendResetPasswordEmail(string $toEmail, string $toName, string $token): bool
    {
        $subject = APP_NAME . ' - Password Reset Request';
        // The reset link will point to a frontend route that then calls the backend API.
        $resetLink = BASE_URL . '/public/reset-password?token=' . $token; 

        // Load HTML email template. Corrected path based on your structure.
        ob_start();
        // Ensure this path is correct: ROOT_PATH . '/views/emails/reset_password_email.php'
        require ROOT_PATH . '/views/emails/reset_password_email.php'; 
        $body = ob_get_clean();

        // Replace placeholders in the template.
        $body = str_replace('{{appName}}', APP_NAME, $body);
        $body = str_replace('{{userName}}', $toName, $body);
        $body = str_replace('{{resetLink}}', $resetLink, $body);
        $body = str_replace('{{resetLinkExpiry}}', 1, $body); // Hardcoded 1 hour expiry for template placeholder
        $body = str_replace('{{currentYear}}', date('Y'), $body);

        $altBody = "Hello {$toName},\n\nYou have requested to reset your password. Please click on the link below:\n{$resetLink}\n\nIf you did not request a password reset, please ignore this email.\n\nThank you,\n" . APP_NAME;

        return $this->sendMail($toEmail, $toName, $subject, $body, $altBody);
    }
}
