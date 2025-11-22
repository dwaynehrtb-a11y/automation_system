<?php
/**
 * Input Validation & Sanitization Helper
 * Provides consistent data validation across the application
 */

class Validator {
    private static $errors = [];

    public static function validate($data, $rules) {
        self::$errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            foreach ($fieldRules as $rule) {
                self::applyRule($field, $value, $rule);
            }
        }

        return empty(self::$errors);
    }

    private static function applyRule($field, $value, $rule) {
        if (strpos($rule, ':') !== false) {
            [$ruleName, $ruleValue] = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $ruleValue = null;
        }

        switch ($ruleName) {
            case 'required':
                if (empty($value)) {
                    self::$errors[$field][] = ucfirst($field) . ' is required';
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    self::$errors[$field][] = 'Invalid email format';
                }
                break;

            case 'min':
                if (!empty($value) && strlen((string)$value) < (int)$ruleValue) {
                    self::$errors[$field][] = ucfirst($field) . ' must be at least ' . $ruleValue . ' characters';
                }
                break;

            case 'max':
                if (!empty($value) && strlen((string)$value) > (int)$ruleValue) {
                    self::$errors[$field][] = ucfirst($field) . ' must not exceed ' . $ruleValue . ' characters';
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    self::$errors[$field][] = ucfirst($field) . ' must be numeric';
                }
                break;

            case 'integer':
                if (!empty($value) && !ctype_digit((string)$value)) {
                    self::$errors[$field][] = ucfirst($field) . ' must be an integer';
                }
                break;

            case 'in':
                $allowed = explode(',', $ruleValue);
                if (!empty($value) && !in_array($value, $allowed)) {
                    self::$errors[$field][] = ucfirst($field) . ' must be one of: ' . $ruleValue;
                }
                break;

            case 'regex':
                if (!empty($value) && !preg_match($ruleValue, $value)) {
                    self::$errors[$field][] = ucfirst($field) . ' format is invalid';
                }
                break;

            case 'date':
                if (!empty($value) && !self::isValidDate($value)) {
                    self::$errors[$field][] = ucfirst($field) . ' must be a valid date';
                }
                break;
        }
    }

    private static function isValidDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    public static function getErrors() {
        return self::$errors;
    }

    public static function getFirstError() {
        foreach (self::$errors as $field => $errors) {
            return $errors[0];
        }
        return null;
    }
}

/**
 * Input Sanitizer
 * Sanitizes user input to prevent XSS and injection attacks
 */
class Sanitizer {
    public static function string($input) {
        return trim(htmlspecialchars($input ?? '', ENT_QUOTES, 'UTF-8'));
    }

    public static function email($input) {
        return filter_var($input, FILTER_SANITIZE_EMAIL);
    }

    public static function integer($input) {
        return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function float($input) {
        return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function url($input) {
        return filter_var($input, FILTER_SANITIZE_URL);
    }

    public static function html($input) {
        return htmlspecialchars($input ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function array($array) {
        $sanitized = [];
        foreach ($array as $key => $value) {
            $sanitized[self::string($key)] = is_array($value) ? self::array($value) : self::string($value);
        }
        return $sanitized;
    }

    public static function escape($input) {
        return htmlspecialchars($input ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * CSRF Token Manager
 */
class CsrfToken {
    public static function generate() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
    }

    public static function verify($sourceField = 'csrf_token') {
        $token = $_POST[$sourceField] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!self::validate($token)) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
        }
    }
}

/**
 * Authentication Helper
 */
class Auth {
    public static function requireLogin() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /automation_system/auth/login.php');
            exit;
        }
    }

    public static function requireRole($role) {
        self::requireLogin();
        
        if ($_SESSION['role'] !== $role) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
        }
    }

    public static function requireAnyRole(...$roles) {
        self::requireLogin();
        
        if (!in_array($_SESSION['role'], $roles)) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
        }
    }

    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public static function getId() {
        return $_SESSION['user_id'] ?? null;
    }

    public static function getRole() {
        return $_SESSION['role'] ?? null;
    }

    public static function getName() {
        return $_SESSION['name'] ?? 'User';
    }
}
