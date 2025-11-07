<?php
namespace App\Controllers;

use App\Core\Controller;

class FrontendController extends Controller
{
    // User-facing pages - continue to use redirect()
    public function index(): void
    {
        $this->redirect('/public/frontend/index.html');
    }

    public function shop(): void
    {
        $this->redirect('/public/frontend/shop.html');
    }

    public function productDetail(string $id): void
    {
        $this->redirect('/public/frontend/product_detail.html?id=' . $id);
    }

    public function cart(): void
    {
        $this->redirect('/public/frontend/cart.html');
    }

    public function checkout(): void
    {
        $this->redirect('/public/frontend/checkout.html');
    }

    public function login(): void
    {
        $this->redirect('/public/frontend/login.html');
    }

    public function register(): void
    {
        $this->redirect('/public/frontend/register.html');
    }

    public function forgotPassword(): void
    {
        $this->redirect('/public/frontend/forgot_password.html');
    }

    public function resetPassword(): void
    {
        $this->redirect('/public/frontend/reset_password.html');
    }

    public function userDashboard(): void
    {
        $this->redirect('/public/frontend/user_dashboard.html');
    }

    public function userProfile(): void
    {
        $this->redirect('/public/frontend/user_profile.html');
    }

    public function userOrders(): void
    {
        $this->redirect('/public/frontend/user_orders.html');
    }

    public function userOrderDetail(string $id): void
    {
        $this->redirect('/public/frontend/user_order_detail.html?id=' . $id);
    }

    public function userAddresses(): void
    {
        $this->redirect('/public/frontend/user_addresses.html');
    }

    public function wishlist(): void
    {
        $this->redirect('/public/frontend/wishlist.html');
    }

    public function contact(): void
    {
        $this->redirect('/public/frontend/contact.html');
    }

    public function about(): void
    {
        $this->redirect('/public/frontend/about.html');
    }

    public function termsConditions(): void
    {
        $this->redirect('/public/frontend/terms_conditions.html');
    }

    public function privacyPolicy(): void
    {
        $this->redirect('/public/frontend/privacy_policy.html');
    }

    // Admin pages - now use renderHtmlView() to inject BASE_URL_PATH
    // These routes will now be handled by PHP to inject dynamic data.
    public function adminLogin(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_login.html');
    }

    public function adminDashboard(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_dashboard.html');
    }

    public function adminProducts(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_products.html');
    }

    public function adminProductAddEdit(string $id = ''): void
    {
        // The ID will be available in the URL query string, accessible via JavaScript.
        $this->renderHtmlView('public/frontend/admin/admin_product_add_edit.html');
    }

    public function adminCategories(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_categories.html');
    }

    public function adminOrders(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_orders.html');
    }

    public function adminOrderDetail(string $id): void
    {
        // The ID will be available in the URL query string, accessible via JavaScript.
        $this->renderHtmlView('public/frontend/admin/admin_order_detail.html');
    }

    public function adminUsers(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_users.html');
    }

    public function adminReviews(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_reviews.html');
    }

    public function adminCoupons(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_coupons.html');
    }

    public function adminSettings(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_settings.html');
    }

    public function adminMessages(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_messages.html');
    }

    public function adminNewsletter(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_newsletter.html');
    }

    public function adminBanners(): void
    {
        $this->renderHtmlView('public/frontend/admin/admin_banners.html');
    }
}
