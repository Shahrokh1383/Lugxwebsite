<?php
namespace App\Services;
use App\Core\Database;
use PDO;
use PDOException;
use finfo;
class ValidationService
{
    private array $errors = [];
    private ?PDO $db = null;
    public function __construct()
    {
        // Correctly get the PDO instance from the singleton Database class
        try {
            $this->db = Database::getInstance();
        } catch (PDOException $e) {
            error_log("Failed to get database instance for ValidationService: " . $e->getMessage());
        }
    }
    
    /**
     * Resets all validation errors.
     *
     * @return void
     */
    public function resetErrors(): void
    {
        $this->errors = [];
    }
    
    /**
     * Get validation errors.
     *
     * @return array An associative array of validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Add an error to the errors list.
     *
     * @param string $field The name of the field that failed validation.
     * @param string $message The error message for the field.
     * @return void
     */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }
    
    /**
     * Safely retrieves a nested value from an array using dot notation.
     *
     * @param array $data The array to search in.
     * @param string $key The dot-separated key (e.g., 'address.city').
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The value found, or the default value.
     */
    private function getNestedValue(array $data, string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $current = $data;
        foreach ($parts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } else {
                return $default;
            }
        }
        return $current;
    }
    
    /**
     * Validate if a field is not empty.
     *
     * @param mixed $value The value to validate.
     * @param string $field The name of the field.
     * @param string $message The error message to use if validation fails.
     * @return bool True if the value is not empty, false otherwise.
     */
    public function required(mixed $value, string $field, string $message = 'This field is required.'): bool
    {
        if (is_array($value)) {
            if (empty($value)) {
                $this->addError($field, $message);
                return false;
            }
        } elseif (empty(trim((string)$value))) {
            $this->addError($field, $message);
            return false;
        }
        return true;
    }
    
    /**
     * Validate if a string meets minimum length.
     *
     * @param string $value The string value to validate.
     * @param string $field The name of the field.
     * @param int $min The minimum allowed length.
     * @param string $message The error message template.
     * @return bool True if the length is sufficient, false otherwise.
     */
    public function minLength(string $value, string $field, int $min, string $message = 'Must be at least %d characters long.'): bool
    {
        if (strlen($value) < $min) {
            $this->addError($field, sprintf($message, $min));
            return false;
        }
        return true;
    }
    
    /**
     * Validate if a string exceeds maximum length.
     *
     * @param string $value The string value to validate.
     * @param string $field The name of the field.
     * @param int $max The maximum allowed length.
     * @param string $message The error message template.
     * @return bool True if the length is within limits, false otherwise.
     */
    public function maxLength(string $value, string $field, int $max, string $message = 'Cannot exceed %d characters.'): bool
    {
        if (strlen($value) > $max) {
            $this->addError($field, sprintf($message, $max));
            return false;
        }
        return true;
    }
    
    /**
     * Validate if an email address is valid.
     *
     * @param string $value The email address to validate.
     * @param string $field The name of the field.
     * @param string $message The error message.
     * @return bool True if the email format is valid, false otherwise.
     */
    public function email(string $value, string $field, string $message = 'Invalid email format.'): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $message);
            return false;
        }
        return true;
    }
    
    /**
     * Validate if a phone number is valid.
     *
     * @param string $value The phone number to validate.
     * @param string $field The name of the field.
     * @param string $message The error message.
     * @return bool True if the phone format is valid, false otherwise.
     */
    public function phone(string $value, string $field, string $message = 'Invalid phone number format.'): bool
    {
        // Allow international formats with +, spaces, dashes, and parentheses
        if (!empty($value) && !preg_match('/^[\+]?[(]?[0-9]{1,3}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,4}[-\s\.]?[0-9]{1,9}$/', $value)) {
            $this->addError($field, $message);
            return false;
        }
        return true;
    }
    
    /**
     * Validate if two fields match (e.g., password and confirm password).
     *
     * @param string $value1 The first value.
     * @param string $value2 The second value to compare against.
     * @param string $field The name of the field (where error will be logged).
     * @param string $message The error message.
     * @return bool True if values match, false otherwise.
     */
    public function matches(string $value1, string $value2, string $field, string $message = 'Fields do not match.'): bool
    {
        if ($value1 !== $value2) {
            $this->addError($field, $message);
            return false;
        }
        return true;
    }
    
    /**
     * Validate if a value is a valid integer.
     *
     * @param mixed $value The value to validate.
     * @param string $field The name of the field.
     * @param string $message The error message.
     * @return bool True if the value is an integer, false otherwise.
     */
    public function isInt(mixed $value, string $field, string $message = 'Must be an integer.'): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_INT) && $value !== 0 && $value !== '0') {
            $this->addError($field, $message);
            return false;
        }
        return true;
    }
    
    /**
     * Validate if a value is a valid numeric value (integer or float).
     *
     * @param mixed $value The value to validate.
     * @param string $field The name of the field.
     * @param string $message The error message.
     * @return bool True if the value is numeric, false otherwise.
     */
    public function isNumeric(mixed $value, string $field, string $message = 'Must be a number.'): bool
    {
        if (!is_numeric($value)) {
            $this->addError($field, $message);
            return false;
        }
        return true;
    }
    
    /**
    * Validate if a value is a string.
    *
    * @param mixed $value The value to validate.
    * @param string $field The name of the field.
    * @param string $message The error message.
    * @return bool True if the value is a string, false otherwise.
    */
    public function isString(mixed $value, string $field, string $message = 'Must be a string.'): bool
    {
        if (!is_string($value)) {
            $this->addError($field, $message);
            return false;
        }
        return true;
    }
    
    /**
     * Validate if a value is a valid boolean.
     *
     * @param mixed $value The value to validate.
     * @param string $field The name of the field.
     * @param string $message The error message.
     * @return bool True if the value is a boolean, false otherwise.
     */
    public function isBoolean(mixed $value, string $field, string $message = 'Must be a boolean (true/false).'): bool
    {
        // Accept boolean values, string representations of boolean, and integer representations
        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1'], true)) {
            $this->addError($field, $message);
            return false;
        }
        return true;
    }
    
    /**
     * Validate if a value is a valid date format (YYYY-MM-DD).
     *
     * @param string $value The date string to validate.
     * @param string $field The name of the field.
     * @param string $message The error message.
     * @return bool True if the date format is valid, false otherwise.
     */
    public function date(string $value, string $field, string $message = 'Invalid date format (YYYY-MM-DD).'): bool
    {
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $value)) {
            $this->addError($field, $message);
            return false;
        }
        $parts = explode('-', $value);
        if (!checkdate($parts[1], $parts[2], $parts[0])) {
            $this->addError($field, $message);
            return false;
        }
        return true;
    }
    
    /**
     * Validate if a value is within a given set of allowed values.
     *
     * @param mixed $value The value to validate.
     * @param string $field The name of the field.
     * @param array $allowedValues An array of allowed values.
     * @param string $message The error message.
     * @return bool True if the value is in the allowed set, false otherwise.
     */
    public function in(mixed $value, string $field, array $allowedValues, string $message = 'Invalid value.'): bool
    {
        if (!in_array($value, $allowedValues)) {
            $this->addError($field, $message);
            return false;
        }
        return true;
    }
    
    /**
     * Validate if a value is unique in a database table.
     *
     * @param mixed $value The value to check for uniqueness.
     * @param string $field The name of the field.
     * @param string $table The database table to check.
     * @param string $column The column to check.
     * @param int|null $ignoreId An ID to ignore (for updates).
     * @param string $message The error message.
     * @return bool True if the value is unique, false otherwise.
     */
    public function unique(mixed $value, string $field, string $table, string $column, ?int $ignoreId = null, string $message = 'This value already exists.'): bool
    {
        if (!$this->db) {
            error_log("Database connection is not available for unique validation.");
            return false;
        }
        try {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";
            $params = [':value' => $value];
            if ($ignoreId !== null) {
                $sql .= " AND id != :ignoreId";
                $params[':ignoreId'] = $ignoreId;
            }
            // The fix is here: use $this->db which is now the PDO instance
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetchColumn() > 0) {
                $this->addError($field, $message);
                return false;
            }
            return true;
        } catch (PDOException $e) {
            error_log("Database error during unique validation: " . $e->getMessage());
            $this->addError($field, 'A database error occurred.');
            return false;
        }
    }
    
    /**
     * Validate if a file was uploaded successfully and is of a valid type.
     *
     * @param array $fileData The file data from $_FILES.
     * @param string $field The name of the file input field.
     * @param string $allowedType The allowed file type ('image', 'video', etc.).
     * @param string $message The error message.
     * @return bool True if the file is valid, false otherwise.
     */
    public function file(array $fileData, string $field, string $allowedType = '', string $message = 'Invalid file uploaded.'): bool
    {
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, 'File upload failed with error code: ' . $fileData['error']);
            return false;
        }
        if (!empty($allowedType)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileData['tmp_name']);
            finfo_close($finfo);
            if ($allowedType === 'image' && !str_starts_with($mimeType, 'image/')) {
                $this->addError($field, 'Only image files are allowed.');
                return false;
            }
            if ($allowedType === 'video' && !str_starts_with($mimeType, 'video/')) {
                $this->addError($field, 'Only video files are allowed.');
                return false;
            }
        }
        return true;
    }
    
    /**
     * Validate if a value is a valid JSON string.
     *
     * @param mixed $value The value to validate.
     * @param string $field The name of the field.
     * @param string $message The error message.
     * @return bool True if the value is a valid JSON, false otherwise.
     */
    public function isJson(mixed $value, string $field, string $message = 'Invalid JSON format.'): bool
    {
        if (empty($value)) {
            return true; // Empty values are considered valid
        }
        
        if (!is_string($value)) {
            $this->addError($field, $message);
            return false;
        }
        
        json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError($field, $message);
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate if a value is optional (skip validation if empty).
     * This method always returns true and is used to indicate that a field is optional.
     *
     * @param mixed $value The value to validate.
     * @param string $field The name of the field.
     * @return bool Always returns true.
     */
    public function optional(mixed $value, string $field): bool
    {
        // This method always returns true as it's used to indicate optional fields
        return true;
    }
    
    /**
     * Validate if a date is after or equal to another date
     *
     * @param string $value The date value to validate
     * @param string $field The name of the field
     * @param string $targetValue The target date to compare against
     * @param string $targetField The name of the target field (for error message)
     * @param string $message The error message template
     * @return bool True if the date is after or equal, false otherwise
     */
    public function afterOrEqual(string $value, string $field, string $targetValue, string $targetField, string $message = 'Must be after or equal to %s.'): bool
    {
        // Skip validation if either date is empty (required validation should handle this separately)
        if (empty($value) || empty($targetValue)) {
            return true;
        }
        
        try {
            $date = new \DateTime($value);
            $targetDate = new \DateTime($targetValue);
            
            if ($date < $targetDate) {
                $this->addError($field, sprintf($message, $targetField));
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            // If dates can't be parsed, let the date validation rule handle it
            return true;
        }
    }
    
    /**
     * Check if a specific field has an error.
     *
     * @param string $field The name of the field to check.
     * @return bool True if an error exists for the field, false otherwise.
     */
    private function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }
    
    /**
     * Validate input data against a set of rules.
     *
     * @param array $data The input data (e.g., from $_POST or JSON body).
     * @param array $rules Associative array where keys are field names and values are pipe-separated rules.
     * @return bool True if all validations pass, false otherwise.
     */
    public function validate(array $data, array $rules): bool
    {
        $this->resetErrors();
        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            
            // Check for file uploads
            if (isset($_FILES[$field]) && is_array($_FILES[$field]) && isset($_FILES[$field]['error'])) {
                $fileData = $_FILES[$field];
                
                if (in_array('required', $fieldRules) && $fileData['error'] === UPLOAD_ERR_NO_FILE) {
                    $this->addError($field, 'This field is required.');
                    continue;
                }
                if ($fileData['error'] !== UPLOAD_ERR_NO_FILE) {
                    foreach ($fieldRules as $rule) {
                        $rule = trim($rule);
                        if (preg_match('/^file:(.*)$/', $rule, $matches)) {
                            $allowedType = $matches[1] ?? '';
                            $this->file($fileData, $field, $allowedType);
                            if ($this->hasError($field)) {
                                break;
                            }
                        }
                    }
                }
            } else {
                // Handle regular fields
                $fieldValue = $this->getNestedValue($data, $field);
                $isNullable = in_array('nullable', $fieldRules);
                $isOptional = in_array('optional', $fieldRules);
                $isRequired = in_array('required', $fieldRules);
                
                if ($isOptional && ($fieldValue === null || $fieldValue === '')) {
                    continue;
                }
                
                if ($isNullable && ($fieldValue === null || $fieldValue === '')) {
                    continue;
                }
                
                if ($isRequired && ($fieldValue === null || (is_string($fieldValue) && trim($fieldValue) === '') || (is_array($fieldValue) && empty($fieldValue)))) {
                    $this->addError($field, 'This field is required.');
                    continue;
                }
                
                if ($fieldValue !== null && $fieldValue !== '') {
                    foreach ($fieldRules as $rule) {
                        $rule = trim($rule);
                        if (in_array($rule, ['required', 'nullable', 'optional'])) {
                            continue;
                        }
                        $params = [];
                        $ruleName = $rule;
                        if (preg_match('/^([a-zA-Z_]+):(.*)$/', $rule, $matches)) {
                            $ruleName = $matches[1];
                            $params = explode(',', $matches[2]);
                        }
                        
                        $valueForValidation = is_array($fieldValue) ? $fieldValue : $fieldValue;
                        switch ($ruleName) {
                            case 'min':
                                if (is_string($valueForValidation)) {
                                    $this->minLength($valueForValidation, $field, (int)$params[0]);
                                }
                                break;
                            case 'max':
                                if (is_string($valueForValidation)) {
                                    $this->maxLength($valueForValidation, $field, (int)$params[0]);
                                }
                                break;
                            case 'email':
                                $this->email($valueForValidation, $field);
                                break;
                            case 'phone':
                                $this->phone($valueForValidation, $field);
                                break;
                            case 'unique':
                                if (count($params) >= 2) {
                                    $table = $params[0];
                                    $column = $params[1];
                                    $ignoreId = isset($params[2]) ? (int)$params[2] : null;
                                    $this->unique($valueForValidation, $field, $table, $column, $ignoreId);
                                }
                                break;
                            case 'int':
                            case 'integer':
                                $this->isInt($valueForValidation, $field, 'Must be a valid integer.');
                                break;
                            case 'numeric':
                                $this->isNumeric($valueForValidation, $field);
                                break;
                            case 'boolean':
                                $this->isBoolean($valueForValidation, $field);
                                break;
                            case 'date':
                                $this->date($valueForValidation, $field);
                                break;
                            case 'in':
                                $this->in($valueForValidation, $field, $params);
                                break;
                            case 'json':
                                $this->isJson($valueForValidation, $field);
                                break;
                            // FIX: Added case for string validation
                            case 'string':
                                $this->isString($valueForValidation, $field);
                                break;
                            case 'confirmed':
                                $confirmationField = $field . '_confirmation';
                                $confirmationValue = $this->getNestedValue($data, $confirmationField);
                                $this->matches((string)$valueForValidation, (string)$confirmationValue, $confirmationField, 'The ' . str_replace('_', ' ', $field) . ' confirmation does not match.');
                                break;
                            case 'after_or_equal':
                                $targetField = $params[0] ?? '';
                                $targetValue = $this->getNestedValue($data, $targetField);
                                $this->afterOrEqual($valueForValidation, $field, $targetValue, $targetField);
                                break;
                            default:
                                error_log("Unrecognized validation rule: {$ruleName}");
                                $this->addError($field, "Validation rule '{$ruleName}' not supported.");
                                break;
                        }
                    }
                }
            }
        }
        return $this->passes();
    }
    
    /**
     * Check if validation passed (i.e., no errors were added).
     *
     * @return bool True if there are no errors, false otherwise.
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }
}