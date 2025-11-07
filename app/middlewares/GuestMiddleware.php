<?php
namespace App\Middlewares;

use App\Services\AuthService;
use App\Core\Controller; // Used for redirecting

class GuestMiddleware extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Handles guest check.
     * If user is already logged in, redirects to their dashboard.
     *
     * @return bool True if user is a guest, false otherwise (request terminated/redirected).
     */
    public function handle(): bool
    {
        if ($this->authService->isLoggedIn()) {
            // Redirect logged-in users away from guest-only pages (e.g., login, register)
            $this->redirect('/public/frontend/user_dashboard.html'); // Redirect to user dashboard
            return false; // Stop further request processing
        }
        return true; // User is a guest, continue to controller
    }
}
