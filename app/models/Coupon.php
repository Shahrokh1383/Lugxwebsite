<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use PDOException;
class Coupon extends Model
{
    protected string $table = 'coupons';
    private string $invalidReason = ''; // New property to store the reason for invalidity
    
    public function __construct()
    {
        parent::__construct();
        error_log("DEBUG: Coupon::__construct - Initialized for table: {$this->table}");
    }
    
    /**
     * Create a new coupon
     * 
     * @param array $data Coupon data
     * @return int|false The ID of the new coupon or false on failure
     */
    public function create(array $data): int|false
    {
        // Ensure required fields are set
        $data['used_count'] = $data['used_count'] ?? 0;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        try {
            // Build the SQL query
            $fields = [];
            $placeholders = [];
            $params = [];
            
            foreach ($data as $field => $value) {
                if ($value === null && in_array($field, ['maximum_discount', 'usage_limit'])) {
                    continue; // Skip null values for nullable fields
                }
                
                $fields[] = "`$field`";
                $placeholders[] = ":$field";
                $params[":$field"] = $value;
            }
            
            $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            if ($stmt->execute()) {
                $newId = $this->db->lastInsertId();
                error_log("DEBUG: Coupon::create - Successfully created coupon with ID: {$newId}");
                return $newId;
            } else {
                error_log("ERROR: Coupon::create - Failed to create coupon.");
                return false;
            }
        } catch (PDOException $e) {
            error_log("ERROR: Coupon::create PDOException: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update a coupon
     * 
     * @param int $id Coupon ID
     * @param array $data Coupon data to update
     * @return bool True on success, false on failure
     */
    public function update(mixed $id, array $data): bool
    {
        // Remove any fields that are not in the table
        $validFields = [
            'code', 'type', 'value', 'minimum_amount', 'maximum_discount',
            'usage_limit', 'per_user_limit', 'start_date', 'end_date', 'is_active'
        ];
        
        $updateData = array_intersect_key($data, array_flip($validFields));
        
        // Set updated_at timestamp
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        // Build the SQL query
        $fields = [];
        $params = [];
        
        foreach ($updateData as $field => $value) {
            if ($value === null && in_array($field, ['maximum_discount', 'usage_limit'])) {
                $fields[] = "`$field` = NULL";
            } else {
                $fields[] = "`$field` = :$field";
                $params[":$field"] = $value;
            }
        }
        
        if (empty($fields)) {
            error_log("ERROR: Coupon::update - No valid fields to update.");
            return false;
        }
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $fields) . " WHERE `id` = :id";
        $params[':id'] = $id;
        
        try {
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $result = $stmt->execute();
            
            if ($result) {
                error_log("DEBUG: Coupon::update - Successfully updated coupon with ID: {$id}");
                return true;
            } else {
                error_log("ERROR: Coupon::update - Failed to update coupon with ID: {$id}");
                return false;
            }
        } catch (PDOException $e) {
            error_log("ERROR: Coupon::update PDOException: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a coupon
     * 
     * @param int $id Coupon ID
     * @return bool True on success, false on failure
     */
    public function delete(mixed $id): bool
    {
        try {
            $sql = "DELETE FROM `{$this->table}` WHERE `id` = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                error_log("DEBUG: Coupon::delete - Successfully deleted coupon with ID: {$id}");
                return true;
            } else {
                error_log("ERROR: Coupon::delete - Failed to delete coupon with ID: {$id}");
                return false;
            }
        } catch (PDOException $e) {
            error_log("ERROR: Coupon::delete PDOException: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Finds a coupon by its code.
     *
     * @param string $code The coupon code.
     * @return array|null The coupon data if found, null otherwise.
     */
    public function findByCode(string $code): ?array
    {
        error_log("DEBUG: Coupon::findByCode - Attempting to find coupon with code: '{$code}'.");
        try {
            // Explicitly prepare and execute the query for better debugging
            $sql = "SELECT * FROM {$this->table} WHERE code = :code LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':code', $code, PDO::PARAM_STR);
            $stmt->execute();
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($coupon) {
                error_log("DEBUG: Coupon::findByCode - Coupon '{$code}' FOUND: " . json_encode($coupon));
                return $coupon;
            } else {
                error_log("DEBUG: Coupon::findByCode - Coupon '{$code}' NOT FOUND in database.");
                return null;
            }
        } catch (PDOException $e) {
            error_log("ERROR: Coupon::findByCode PDOException: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Checks if a coupon is valid based on its properties and current cart total.
     * Sets an internal message for the reason of invalidity if applicable.
     *
     * @param array $couponData The coupon data array (fetched from DB).
     * @param float $cartTotal The current total amount of the cart before discount.
     * @param int $userId The ID of the user attempting to use the coupon.
     * @return bool True if the coupon is valid, false otherwise.
     */
    public function isValid(array $couponData, float $cartTotal, int $userId): bool
    {
        $this->invalidReason = ''; // Reset reason at the start of validation
        error_log("DEBUG: Coupon::isValid - Validating coupon '{$couponData['code']}' for user {$userId}, cart total {$cartTotal}.");
        // 1. Check if coupon is active
        if (!(bool)$couponData['is_active']) {
            $this->invalidReason = 'Coupon is not active.';
            error_log("DEBUG: Coupon::isValid - Failed: Coupon not active (is_active: {$couponData['is_active']}).");
            return false;
        }
        // 2. Check dates
        $currentDate = date('Y-m-d');
        if ($currentDate < $couponData['start_date']) {
            $this->invalidReason = 'Coupon is not yet active.';
            error_log("DEBUG: Coupon::isValid - Failed: Coupon not yet active. Current: {$currentDate}, Start: {$couponData['start_date']}");
            return false;
        }
        if ($currentDate > $couponData['end_date']) {
            $this->invalidReason = 'Coupon has expired.';
            error_log("DEBUG: Coupon::isValid - Failed: Coupon has expired. Current: {$currentDate}, End: {$couponData['end_date']}");
            return false;
        }
        // 3. Check usage limit (overall)
        if ($couponData['usage_limit'] !== null && (int)$couponData['used_count'] >= (int)$couponData['usage_limit']) {
            $this->invalidReason = 'Coupon has reached its global usage limit.';
            error_log("DEBUG: Coupon::isValid - Failed: Global usage limit reached. Used: {$couponData['used_count']}, Limit: {$couponData['usage_limit']}");
            return false;
        }
        // 4. Check per-user limit
        if ($couponData['per_user_limit'] > 0) {
            // اصلاح شده: استفاده از کد کوپن به جای شناسه
            $userUsageCount = $this->getUserUsageCount($userId, $couponData['code']);
            if ($userUsageCount >= $couponData['per_user_limit']) {
                $this->invalidReason = 'You have already used this coupon the maximum number of times.';
                error_log("DEBUG: Coupon::isValid - Failed: Per-user limit reached. User ID: {$userId}, Coupon Code: {$couponData['code']}");
                return false;
            }
        }
        // 5. Check minimum amount
        if ((float)$couponData['minimum_amount'] > 0 && $cartTotal < (float)$couponData['minimum_amount']){
            $this->invalidReason = 'Minimum purchase of $' . number_format((float)$couponData['minimum_amount'], 2) . ' required.';
            error_log("DEBUG: Coupon::isValid - Failed: Minimum amount not met. Cart Total: {$cartTotal}, Minimum: {$couponData['minimum_amount']}");
            return false;
        }
        error_log("DEBUG: Coupon::isValid - Coupon '{$couponData['code']}' is valid.");
        return true; // Coupon is valid
    }
    
    /**
     * Get the number of times a specific user has used a specific coupon.
     * This method uses the coupon_code instead of coupon_id.
     *
     * @param int $userId The ID of the user.
     * @param string $couponCode The code of the coupon.
     * @return int The count of uses.
     */
    public function getUserUsageCount(int $userId, string $couponCode): int
    {
        try {
            // اصلاح شده: استفاده از coupon_code به جای coupon_id
            $query = "
                SELECT COUNT(*) 
                FROM orders 
                WHERE user_id = :user_id AND coupon_code = :coupon_code AND status IN ('completed', 'delivered')
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            // اصلاح شده: استفاده از coupon_code به جای coupon_id
            $stmt->bindParam(':coupon_code', $couponCode, PDO::PARAM_STR);
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("ERROR: Coupon::getUserUsageCount PDOException: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Returns the reason why the last coupon validation failed.
     *
     * @return string The reason for invalidity, or an empty string if valid.
     */
    public function getInvalidReason(): string
    {
        return $this->invalidReason;
    }
    
    /**
     * Increments the used_count for a coupon.
     * This should typically be called after a coupon is successfully applied to an order.
     *
     * @param int $couponId The ID of the coupon.
     * @return bool True on success, false on failure.
     */
    public function incrementUsedCount(int $couponId): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET used_count = used_count + 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->bindParam(':id', $couponId, PDO::PARAM_INT);
            $success = $stmt->execute();
            if ($success) {
                error_log("DEBUG: Coupon::incrementUsedCount - Successfully incremented used_count for coupon ID: {$couponId}.");
            } else {
                error_log("ERROR: Coupon::incrementUsedCount - Failed to increment used_count for coupon ID: {$couponId}.");
            }
            return $success;
        } catch (PDOException $e) {
            error_log("ERROR: Coupon::incrementUsedCount PDOException: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculates the discount amount for a given cart total using coupon data.
     *
     * @param array $couponData The coupon data array.
     * @param float $cartTotal The total amount of the cart before discount.
     * @return float The calculated discount amount.
     */
    public function calculateDiscount(array $couponData, float $cartTotal): float
    {
        $discount = 0.0;
        // Ensure value is cast to float for calculation
        $couponValue = (float)$couponData['value'];
        switch ($couponData['type']) {
            case 'fixed_amount':
                $discount = $couponValue;
                break;
            case 'percentage':
                $discount = ($cartTotal * $couponValue) / 100;
                break;
        }
        
        // Apply maximum discount limit if set
        if ($couponData['maximum_discount'] !== null && (float)$couponData['maximum_discount'] > 0 && $discount > (float)$couponData['maximum_discount']) {
            $discount = (float)$couponData['maximum_discount'];
        }
        
        // Ensure discount does not exceed cart total
        $finalDiscount = min($discount, $cartTotal);
        error_log("DEBUG: Coupon::calculateDiscount - Coupon '{$couponData['code']}', Type: {$couponData['type']}, Value: {$couponValue}, Cart Total: {$cartTotal}, Calculated Discount: {$finalDiscount}.");
        return $finalDiscount;
    }
    /**
     * Decrements the used_count for a coupon.
     * This should be called when a coupon is removed from cart or if the order is canceled.
     *
     * @param int $couponId The ID of the coupon.
     * @return bool True on success, false on failure.
     */
    public function decrementUsedCount(int $couponId): bool
    {
        try{
            $stmt = $this->db->prepare("UPDATE {$this->table} SET used_count = GREATEST(0, used_count - 1), updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->bindParam(':id', $couponId, PDO::PARAM_INT);
            $success = $stmt->execute();
            if ($success) {
                error_log("DEBUG: Coupon::decrementUsedCount - Successfully decremented used_count for coupon ID: {$couponId}.");
            } else {
                error_log("ERROR: Coupon::decrementUsedCount - Failed to decrement used_count for coupon ID: {$couponId}.");
            }
            return $success;
        }catch (PDOException $e) {
            error_log("ERROR: Coupon::decrementUsedCount PDOException: " . $e->getMessage());
            return false;
        }
    }
}