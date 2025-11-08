<?php
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Models\SiteSetting;
use App\Models\ActivityLog;
use App\Services\AuthService;
use App\Services\ValidationService;
use App\Services\UploadService;
use PDO;
use Exception;

class AdminSettingsController extends Controller
{
    private SiteSetting $siteSettingModel;
    private ActivityLog $activityLogModel;
    private AuthService $authService;
    private ValidationService $validator;
    private UploadService $uploadService;
    
    public function __construct()
    {
        $this->siteSettingModel = new SiteSetting();
        $this->activityLogModel = new ActivityLog();
        $this->authService = new AuthService();
        $this->validator = new ValidationService();
        $this->uploadService = new UploadService();
    }
    
    //-------------------------------------------------------------
    // View Management
    //-------------------------------------------------------------
    /**
     * Renders the static HTML view for managing settings.
     * GET /admin/settings
     */
    public function index(): void
    {
        // This is a view handler. Authentication is handled by a middleware.
        $this->renderHtmlView('frontend/admin/admin_settings.html');
    }
    
    //-------------------------------------------------------------
    // API Endpoints
    // All API methods are protected by an authentication check.
    //-------------------------------------------------------------
    /**
     * Get all site settings.
     * GET /api/admin/settings
     */
    public function getSettings(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        try {
            $settings = $this->siteSettingModel->getAll();
            
            // Group settings by group_name
            $groupedSettings = [];
            foreach ($settings as $setting) {
                $groupedSettings[$setting['group_name']][] = $setting;
            }
            
            $this->renderApiJson([
                'success' => true,
                'data' => $groupedSettings,
                'message' => 'Settings fetched successfully.'
            ]);
        } catch (Exception $e) {
            error_log("Error fetching settings: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Update site settings.
     * PUT /api/admin/settings
     */
    public function updateSettings(): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        // Get settings data - check both JSON and form data
        $settingsData = null;
        
        // First try to get JSON data
        $jsonInput = file_get_contents('php://input');
        error_log("JSON input: " . $jsonInput);
        
        if (!empty($jsonInput)) {
            $jsonData = json_decode($jsonInput, true);
            error_log("Decoded JSON: " . print_r($jsonData, true));
            
            if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['settings'])) {
                $settingsData = $jsonData['settings'];
                error_log("Settings from JSON: " . print_r($settingsData, true));
            }
        }
        
        // If no JSON data, try form data
        if ($settingsData === null) {
            error_log("Checking POST data: " . print_r($_POST, true));
            
            if (isset($_POST['settings'])) {
                error_log("Found settings in POST: " . print_r($_POST['settings'], true));
                
                if (is_string($_POST['settings'])) {
                    // If settings is a JSON string, decode it
                    $decodedSettings = json_decode($_POST['settings'], true);
                    error_log("Decoded settings from POST: " . print_r($decodedSettings, true));
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $settingsData = $decodedSettings;
                    }
                } elseif (is_array($_POST['settings'])) {
                    $settingsData = $_POST['settings'];
                    error_log("Settings array from POST: " . print_r($settingsData, true));
                }
            }
        }
        
        // Validate that we have settings data
        if ($settingsData === null || !is_array($settingsData)) {
            error_log("No valid settings data found!");
            $this->renderApiJson([
                'success' => false,
                'error' => 'No settings data provided.'
            ], 400);
            return;
        }
        
        $updatedSettings = [];
        $errors = [];
        $currentUserId = $this->authService->getAuthenticatedUserId();
        
        try {
            // Start transaction for atomic updates
            $this->siteSettingModel->beginTransaction();
            
            foreach ($settingsData as $key => $value) {
                // Get the existing setting to validate type and get old value
                $existingSetting = $this->siteSettingModel->findByKey($key);
                
                if (!$existingSetting) {
                    $errors[$key] = 'Setting not found.';
                    continue;
                }
                
                // Store old value for activity log
                $oldValue = $existingSetting['value'];
                
                // Validate based on setting type
                $validationRules = $this->getValidationRulesForType($existingSetting['type']);
                $isValid = $this->validator->validate(['value' => $value], ['value' => $validationRules]);
                
                if (!$isValid) {
                    $validationErrors = $this->validator->getErrors();
                    $errors[$key] = $validationErrors['value'] ?? 'Invalid value.';
                    continue;
                }
                
                // Handle file uploads
                $newValue = $value;
                if ($existingSetting['type'] === 'file' && isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                    $uploadPath = $this->uploadService->uploadFile($_FILES[$key], 'settings');
                    if ($uploadPath) {
                        $newValue = $uploadPath;
                    } else {
                        $errors[$key] = 'Failed to upload file.';
                        continue;
                    }
                }
                
                // Cast value to appropriate type
                $castValue = $this->castValueToType($newValue, $existingSetting['type']);
                
                // Update the setting
                $success = $this->siteSettingModel->save([
                    'key_name' => $key,
                    'value' => $castValue,
                    'type' => $existingSetting['type'],
                    'group_name' => $existingSetting['group_name'],
                    'description' => $existingSetting['description'],
                    'is_autoload' => $existingSetting['is_autoload']
                ]);
                
                if ($success) {
                    $updatedSettings[$key] = $castValue;
                    
                    // Log the activity for important settings
                    if ($this->isImportantSetting($key)) {
                        ActivityLog::addLog(
                            'update_setting',
                            "Updated setting: {$key}",
                            'SiteSetting',
                            null,
                            ['old_value' => $oldValue],
                            ['new_value' => $castValue],
                            $currentUserId
                        );
                    }
                } else {
                    $errors[$key] = 'Failed to update setting.';
                }
            }
            
            if (!empty($errors)) {
                $this->siteSettingModel->rollBack();
                $this->renderApiJson([
                    'success' => false,
                    'error' => 'Some settings could not be updated.',
                    'errors' => $errors,
                    'updated' => $updatedSettings
                ], 400);
                return;
            }
            
            // Commit transaction
            $this->siteSettingModel->commit();
            
            // Log the activity for bulk update
            ActivityLog::addLog(
                'update_settings',
                'Updated multiple site settings',
                'SiteSetting',
                null,
                null,
                ['updated_settings' => $updatedSettings],
                $currentUserId
            );
            
            $this->renderApiJson([
                'success' => true,
                'data' => $updatedSettings,
                'message' => 'Settings updated successfully!'
            ]);
        } catch (Exception $e) {
            if (isset($this->siteSettingModel)) {
                $this->siteSettingModel->rollBack();
            }
            error_log("Error updating settings: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Get a specific setting by key.
     * GET /api/admin/settings/{key}
     */
    public function getSetting(string $key): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        try {
            $setting = $this->siteSettingModel->findByKey($key);
            
            if (!$setting) {
                $this->renderApiJson([
                    'success' => false,
                    'error' => 'Setting not found.'
                ], 404);
                return;
            }
            
            $this->renderApiJson([
                'success' => true,
                'data' => $setting,
                'message' => 'Setting fetched successfully.'
            ]);
        } catch (Exception $e) {
            error_log("Error fetching setting: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Update a specific setting by key.
     * PUT /api/admin/settings/{key}
     */
    public function updateSetting(string $key): void
    {
        if (!$this->authService->isLoggedIn() || !$this->authService->hasRole('admin')) {
            $this->renderApiJson(['error' => 'Unauthorized access.'], 401);
            return;
        }
        
        // Get form data instead of JSON data
        $data = $_POST;
        
        // Validate that we have value data
        if (!isset($data['value'])) {
            $this->renderApiJson([
                'success' => false,
                'error' => 'No value provided.'
            ], 400);
            return;
        }
        
        $value = $data['value'];
        $currentUserId = $this->authService->getAuthenticatedUserId();
        
        try {
            // Get the existing setting to validate type and get old value
            $existingSetting = $this->siteSettingModel->findByKey($key);
            
            if (!$existingSetting) {
                $this->renderApiJson([
                    'success' => false,
                    'error' => 'Setting not found.'
                ], 404);
                return;
            }
            
            // Store old value for activity log
            $oldValue = $existingSetting['value'];
            
            // Validate based on setting type
            $validationRules = $this->getValidationRulesForType($existingSetting['type']);
            $isValid = $this->validator->validate(['value' => $value], ['value' => $validationRules]);
            
            if (!$isValid) {
                $validationErrors = $this->validator->getErrors();
                $this->renderApiJson([
                    'success' => false,
                    'error' => 'Validation failed.',
                    'errors' => ['value' => $validationErrors['value'] ?? 'Invalid value.']
                ], 400);
                return;
            }
            
            // Handle file uploads
            $newValue = $value;
            if ($existingSetting['type'] === 'file' && isset($_FILES['value']) && $_FILES['value']['error'] === UPLOAD_ERR_OK) {
                $uploadPath = $this->uploadService->uploadFile($_FILES['value'], 'settings');
                if ($uploadPath) {
                    $newValue = $uploadPath;
                } else {
                    $this->renderApiJson([
                        'success' => false,
                        'error' => 'Failed to upload file.'
                    ], 400);
                    return;
                }
            }
            
            // Cast value to appropriate type
            $castValue = $this->castValueToType($newValue, $existingSetting['type']);
            
            // Update the setting
            $success = $this->siteSettingModel->save([
                'key_name' => $key,
                'value' => $castValue,
                'type' => $existingSetting['type'],
                'group_name' => $existingSetting['group_name'],
                'description' => $existingSetting['description'],
                'is_autoload' => $existingSetting['is_autoload']
            ]);
            
            if (!$success) {
                $this->renderApiJson([
                    'success' => false,
                    'error' => 'Failed to update setting.'
                ], 500);
                return;
            }
            
            // Log the activity
            ActivityLog::addLog(
                'update_setting',
                "Updated setting: {$key}",
                'SiteSetting',
                null,
                ['old_value' => $oldValue],
                ['new_value' => $castValue],
                $currentUserId
            );
            
            $this->renderApiJson([
                'success' => true,
                'data' => [
                    'key_name' => $key,
                    'value' => $castValue,
                    'type' => $existingSetting['type'],
                    'group_name' => $existingSetting['group_name'],
                    'description' => $existingSetting['description']
                ],
                'message' => 'Setting updated successfully!'
            ]);
        } catch (Exception $e) {
            error_log("Error updating setting: " . $e->getMessage());
            $this->renderApiJson([
                'success' => false,
                'error' => 'An unexpected error occurred.'
            ], 500);
        }
    }
    
    /**
     * Get validation rules for a setting type.
     * @param string $type The setting type.
     * @return string The validation rule.
     */
    private function getValidationRulesForType(string $type): string
    {
        switch ($type) {
            case 'boolean':
                return 'boolean';
            case 'number':
                return 'numeric';
            case 'email':
                return 'email';
            case 'url':
                return 'url';
            case 'json':
                return 'json';
            default:
                return 'string';
        }
    }
    
    /**
     * Cast a value to the appropriate type.
     * @param mixed $value The value to cast.
     * @param string $type The type to cast to.
     * @return mixed The cast value.
     */
    private function castValueToType($value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return (bool) $value;
            case 'number':
                return is_numeric($value) ? (strpos($value, '.') !== false ? (float) $value : (int) $value) : 0;
            case 'json':
                return is_string($value) ? $value : json_encode($value);
            default:
                return (string) $value;
        }
    }
    
    /**
     * Check if a setting is important enough to log individually.
     * @param string $key The setting key.
     * @return bool True if important, false otherwise.
     */
    private function isImportantSetting(string $key): bool
    {
        $importantSettings = [
            'site_name',
            'site_description',
            'site_email',
            'currency',
            'maintenance_mode',
            'registration_enabled',
            'email_verification_required'
        ];
        
        return in_array($key, $importantSettings);
    }
}