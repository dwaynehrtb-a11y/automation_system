<?php
/**
 * Centralized Error Handler & Response Manager
 * Provides consistent error handling, logging, and API responses
 */

class ErrorHandler {
    private static $logFile;
    private static $environment;

    public static function init() {
        self::$logFile = __DIR__ . '/../logs/errors.log';
        self::$environment = getenv('APP_ENV') ?: 'development';
        
        // Create logs directory if it doesn't exist
        if (!is_dir(dirname(self::$logFile))) {
            mkdir(dirname(self::$logFile), 0755, true);
        }

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError($errno, $errstr, $errfile, $errline) {
        $errorType = self::getErrorType($errno);
        $context = [
            'type' => $errorType,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? 'anonymous'
        ];

        self::log($context);

        if (self::$environment === 'production') {
            http_response_code(500);
            die('An error occurred. Please try again later.');
        }

        return true;
    }

    public static function handleException($exception) {
        $context = [
            'type' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? 'anonymous'
        ];

        self::log($context);

        if (self::$environment === 'production') {
            http_response_code(500);
            die('An error occurred. Please try again later.');
        }
    }

    public static function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    private static function getErrorType($errno) {
        $errorTypes = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
        ];
        return $errorTypes[$errno] ?? 'Unknown Error';
    }

    public static function log($context) {
        $logMessage = json_encode($context) . PHP_EOL;
        error_log($logMessage, 3, self::$logFile);
    }

    public static function getEnvironment() {
        return self::$environment;
    }
}

/**
 * API Response Formatter
 * Ensures consistent JSON responses across all endpoints
 */
class ApiResponse {
    private $statusCode = 200;
    private $data = [];
    private $message = '';
    private $errors = [];

    public function success($data = null, $message = 'Success') {
        $this->statusCode = 200;
        $this->data = $data;
        $this->message = $message;
        return $this;
    }

    public function created($data = null, $message = 'Resource created successfully') {
        $this->statusCode = 201;
        $this->data = $data;
        $this->message = $message;
        return $this;
    }

    public function badRequest($message = 'Bad request', $errors = []) {
        $this->statusCode = 400;
        $this->message = $message;
        $this->errors = $errors;
        return $this;
    }

    public function unauthorized($message = 'Unauthorized') {
        $this->statusCode = 401;
        $this->message = $message;
        return $this;
    }

    public function forbidden($message = 'Forbidden') {
        $this->statusCode = 403;
        $this->message = $message;
        return $this;
    }

    public function notFound($message = 'Resource not found') {
        $this->statusCode = 404;
        $this->message = $message;
        return $this;
    }

    public function conflict($message = 'Resource conflict') {
        $this->statusCode = 409;
        $this->message = $message;
        return $this;
    }

    public function serverError($message = 'Internal server error') {
        $this->statusCode = 500;
        $this->message = $message;
        return $this;
    }

    public function send() {
        header('Content-Type: application/json');
        http_response_code($this->statusCode);

        $response = [
            'success' => $this->statusCode >= 200 && $this->statusCode < 300,
            'status_code' => $this->statusCode,
            'message' => $this->message,
            'data' => $this->data,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if (!empty($this->errors)) {
            $response['errors'] = $this->errors;
        }

        echo json_encode($response);
        exit;
    }
}

// Initialize error handler
ErrorHandler::init();
