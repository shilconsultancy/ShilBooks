<?php
/**
 * Validation Helper Functions
 * Provides comprehensive form validation and security checks
 */

class Validation {

    private $errors = [];
    private $data = [];

    public function __construct($data = []) {
        $this->data = $data;
    }

    /**
     * Validate required fields
     */
    public function required($fields) {
        foreach ($fields as $field) {
            if (empty(trim($this->data[$field] ?? ''))) {
                $this->errors[$field] = ucfirst($field) . ' is required';
            }
        }
        return $this;
    }

    /**
     * Validate email format
     */
    public function email($field) {
        if (!empty($this->data[$field]) && !validateEmail($this->data[$field])) {
            $this->errors[$field] = 'Please enter a valid email address';
        }
        return $this;
    }

    /**
     * Validate minimum length
     */
    public function minLength($field, $length) {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field] = ucfirst($field) . ' must be at least ' . $length . ' characters';
        }
        return $this;
    }

    /**
     * Validate maximum length
     */
    public function maxLength($field, $length) {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field] = ucfirst($field) . ' must be no more than ' . $length . ' characters';
        }
        return $this;
    }

    /**
     * Validate numeric values
     */
    public function numeric($field) {
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = ucfirst($field) . ' must be a number';
        }
        return $this;
    }

    /**
     * Validate positive numbers
     */
    public function positive($field) {
        if (!empty($this->data[$field]) && (!is_numeric($this->data[$field]) || $this->data[$field] <= 0)) {
            $this->errors[$field] = ucfirst($field) . ' must be greater than zero';
        }
        return $this;
    }

    /**
     * Validate date format
     */
    public function date($field) {
        if (!empty($this->data[$field])) {
            $date = DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            if (!$date || $date->format('Y-m-d') !== $this->data[$field]) {
                $this->errors[$field] = 'Please enter a valid date';
            }
        }
        return $this;
    }

    /**
     * Validate phone number
     */
    public function phone($field) {
        if (!empty($this->data[$field])) {
            $phone = preg_replace('/[^0-9]/', '', $this->data[$field]);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $this->errors[$field] = 'Please enter a valid phone number';
            }
        }
        return $this;
    }

    /**
     * Validate currency amount
     */
    public function currency($field) {
        if (!empty($this->data[$field])) {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $this->data[$field])) {
                $this->errors[$field] = 'Please enter a valid currency amount';
            }
        }
        return $this;
    }

    /**
     * Validate file upload
     */
    public function file($field, $allowedTypes = [], $maxSize = MAX_FILE_SIZE) {
        if (!empty($_FILES[$field]['name'])) {
            if (!isValidFileUpload($_FILES[$field])) {
                $this->errors[$field] = 'Invalid file upload';
            }
        }
        return $this;
    }

    /**
     * Validate password strength
     */
    public function password($field) {
        if (!empty($this->data[$field])) {
            if (!validatePassword($this->data[$field])) {
                $this->errors[$field] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters with uppercase, lowercase, and number';
            }
        }
        return $this;
    }

    /**
     * Validate matching fields
     */
    public function matches($field1, $field2) {
        if ($this->data[$field1] !== $this->data[$field2]) {
            $this->errors[$field2] = ucfirst($field2) . ' does not match ' . ucfirst($field1);
        }
        return $this;
    }

    /**
     * Validate unique value in database
     */
    public function unique($field, $table, $column, $excludeId = null) {
        if (!empty($this->data[$field])) {
            try {
                $pdo = getDBConnection();
                $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
                $params = [$this->data[$field]];

                if ($excludeId) {
                    $sql .= " AND id != ?";
                    $params[] = $excludeId;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $result = $stmt->fetch();

                if ($result['count'] > 0) {
                    $this->errors[$field] = ucfirst($field) . ' already exists';
                }
            } catch(PDOException $e) {
                $this->errors[$field] = 'Validation error occurred';
            }
        }
        return $this;
    }

    /**
     * Validate date range
     */
    public function dateRange($startField, $endField) {
        if (!empty($this->data[$startField]) && !empty($this->data[$endField])) {
            if ($this->data[$startField] > $this->data[$endField]) {
                $this->errors[$endField] = 'End date must be after start date';
            }
        }
        return $this;
    }

    /**
     * Validate tax rate
     */
    public function taxRate($field) {
        if (!empty($this->data[$field])) {
            $rate = floatval($this->data[$field]);
            if ($rate < 0 || $rate > 100) {
                $this->errors[$field] = 'Tax rate must be between 0 and 100';
            }
        }
        return $this;
    }

    /**
     * Validate invoice number format
     */
    public function invoiceNumber($field) {
        if (!empty($this->data[$field])) {
            if (!preg_match('/^INV-\d{4}-\d{4}$/', $this->data[$field])) {
                $this->errors[$field] = 'Invoice number must follow format INV-YYYY-0001';
            }
        }
        return $this;
    }

    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails() {
        return !empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function errors() {
        return $this->errors;
    }

    /**
     * Get first error message
     */
    public function firstError() {
        return reset($this->errors) ?: '';
    }

    /**
     * Get error for specific field
     */
    public function error($field) {
        return $this->errors[$field] ?? '';
    }

    /**
     * Get sanitized data
     */
    public function data() {
        return array_map('sanitizeInput', $this->data);
    }

    /**
     * Get specific field value
     */
    public function get($field, $default = null) {
        return sanitizeInput($this->data[$field] ?? $default);
    }
}

/**
 * Helper function to create validation instance
 */
function validate($data = []) {
    return new Validation($data);
}

/**
 * Helper function to display validation errors
 */
function displayErrors($errors, $field = null) {
    if ($field && isset($errors[$field])) {
        return '<div class="error-message text-red-600 text-sm mt-1">' . $errors[$field] . '</div>';
    }

    if (empty($errors)) {
        return '';
    }

    $html = '<div class="error-container mb-4">';
    foreach ($errors as $error) {
        $html .= '<div class="error-message bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-2">' . $error . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Helper function to check if field has error
 */
function hasError($errors, $field) {
    return isset($errors[$field]);
}

/**
 * Helper function to get error class
 */
function errorClass($errors, $field) {
    return hasError($errors, $field) ? 'border-red-500' : '';
}

/**
 * Helper function to get field value with old input
 */
function old($field, $default = '') {
    return sanitizeInput($_POST[$field] ?? $default);
}

/**
 * Helper function to check if form was submitted
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Helper function to check if request is AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Helper function to get client IP address
 */
function getClientIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
?>