<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Coupon;
use App\Services\AuthService;
use App\Services\ValidationService;
use Exception;

class AdminCouponController extends Controller
{
    private Coupon $couponModel;
    private AuthService $authService;
    private ValidationService $validator;
    private array $validCouponTypes = ['percentage', 'fixed_amount'];
    
    public function __construct()
    {
        $this->couponModel = new Coupon();
        $this->authService = new AuthService();
        $this->validator = new ValidationService();
    }
    
    //-------------------------------------------------------------
    // View Management
    //-------------------------------------------------------------
    /**
     * Renders the static HTML view for managing coupons.
     * GET /admin/coupons
     */
    public function index(): void
    {
        // This is a view handler. Authentication is handled by a middleware.
        $this->renderHtmlView('frontend/admin/admin_coupons.html');
    }
    
    //-------------------------------------------------------------
    // API Endpoints
    //-------------------------------------------------------------
    /**
     * Get a list of all coupons.
     * GET /api/admin/coupons
     */
    public function indexApi(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        try {
            $coupons = $this->couponModel->all();
            $this->renderApiJson($coupons);
        } catch (Exception $e) {
            error_log("Error fetching coupons: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Get details for a single coupon.
     * GET /api/admin/coupons/{id}
     * @param int $id The ID of the coupon.
     */
    public function show(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        try {
            $coupon = $this->couponModel->find($id);
            if (!$coupon) {
                $this->renderApiJson(['error' => 'Coupon not found.'], 404);
                return;
            }
            $this->renderApiJson($coupon);
        } catch (Exception $e) {
            error_log("Error fetching coupon details: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Create a new coupon.
     * POST /api/admin/coupons
     */
    public function store(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        $data = $this->getJsonData();
        
        $rules = [
            'code' => 'required|max:50|unique:coupons',
            'type' => 'required|in:' . implode(',', $this->validCouponTypes),
            'value' => 'required|numeric|min:0.01', 
            'minimum_amount' => 'required|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'required|boolean',
        ];
        
        if (!$this->validator->validate($data, $rules)) {
            $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
            return;
        }
        
        try {
            $data['usage_limit'] = $data['usage_limit'] ?? null;
            $data['maximum_discount'] = $data['maximum_discount'] ?? null;
            $data['used_count'] = 0;
            
            $newCouponId = $this->couponModel->create($data);
            
            if ($newCouponId) {
                $newCoupon = $this->couponModel->find($newCouponId);
                $this->renderApiJson(['message' => 'Coupon created successfully!', 'coupon' => $newCoupon], 201);
            } else {
                $this->renderApiJson(['error' => 'Failed to create coupon.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error creating coupon: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Update an existing coupon.
     * PUT /api/admin/coupons/{id}
     * @param int $id The ID of the coupon.
     */
    public function update(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        $data = $this->getJsonData();
        
        try {
            $coupon = $this->couponModel->find($id);
            if (!$coupon) {
                $this->renderApiJson(['error' => 'Coupon not found.'], 404);
                return;
            }
            
            // For update, we need to ensure that required fields are not null
            // So we'll merge with existing data for fields that are not provided
            $updateData = array_merge($coupon, $data);
            
            $rules = [
                'code' => 'required|max:50|unique:coupons,code,' . $id,
                'type' => 'required|in:' . implode(',', $this->validCouponTypes),
                'value' => 'required|numeric|min:0.01',
                'minimum_amount' => 'required|numeric|min:0',
                'maximum_discount' => 'nullable|numeric|min:0',
                'usage_limit' => 'nullable|integer|min:1',
                'per_user_limit' => 'required|integer|min:1',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'is_active' => 'required|boolean',
            ];
            
            if (!$this->validator->validate($updateData, $rules)) {
                $this->renderApiJson(['error' => 'Validation failed.', 'errors' => $this->validator->getErrors()], 400);
                return;
            }
            
            // Convert null values for nullable fields
            if (isset($updateData['maximum_discount']) && $updateData['maximum_discount'] === null) {
                $updateData['maximum_discount'] = null;
            }
            
            if (isset($updateData['usage_limit']) && $updateData['usage_limit'] === null) {
                $updateData['usage_limit'] = null;
            }
            
            // Remove fields that should not be updated
            unset($updateData['id'], $updateData['created_at'], $updateData['updated_at'], $updateData['used_count']);
            
            if ($this->couponModel->update($id, $updateData)) {
                $updatedCoupon = $this->couponModel->find($id);
                $this->renderApiJson(['message' => 'Coupon updated successfully!', 'coupon' => $updatedCoupon]);
            } else {
                $this->renderApiJson(['error' => 'Failed to update coupon.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error updating coupon: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }
    
    /**
     * Delete a coupon.
     * DELETE /api/admin/coupons/{id}
     * @param int $id The ID of the coupon.
     */
    public function destroy(int $id): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        try {
            if ($this->couponModel->delete($id)) {
                $this->renderApiJson(['message' => 'Coupon deleted successfully!']);
            } else {
                $this->renderApiJson(['error' => 'Failed to delete coupon. It might not exist or has been used.'], 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting coupon: " . $e->getMessage());
            $this->renderApiJson(['error' => 'An unexpected error occurred.'], 500);
        }
    }
}