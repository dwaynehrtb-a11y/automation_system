<?php
/**
 * Security Middleware
 * Provides common security checks for API endpoints
 */

class SecurityMiddleware {
    
    /**
     * Verify AJAX request and CSRF token
     */
    public static function verifyAjaxRequest() {
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Invalid request']));
        }

        CsrfToken::verify();
    }

    /**
     * Validate input based on rules
     */
    public static function validateInput($data, $rules) {
        if (!Validator::validate($data, $rules)) {
            $response = new ApiResponse();
            $response->badRequest('Validation failed', Validator::getErrors())->send();
        }
    }

    /**
     * Rate limit API calls
     */
    public static function rateLimit($key, $maxRequests = 60, $timeWindow = 60) {
        $cacheKey = 'rate_limit_' . md5($key);
        $cache = apcu_cache_exists($cacheKey) ? apcu_fetch($cacheKey) : 0;

        if ($cache >= $maxRequests) {
            $response = new ApiResponse();
            $response->statusCode = 429;
            $response->message = 'Rate limit exceeded';
            $response->send();
        }

        apcu_store($cacheKey, $cache + 1, $timeWindow);
    }

    /**
     * Log API access
     */
    public static function logAccess($endpoint, $action, $details = []) {
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => Auth::getId(),
            'endpoint' => $endpoint,
            'action' => $action,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'details' => $details
        ];

        error_log(json_encode($log));
    }

    /**
     * Sanitize output for HTML context
     */
    public static function escapeOutput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'escapeOutput'], $data);
        }
        return Sanitizer::html($data);
    }
}

/**
 * Query Builder Helper
 * Simplifies building secure parameterized queries
 */
class QueryBuilder {
    private $table;
    private $conn;
    private $select = [];
    private $where = [];
    private $whereParams = [];
    private $limit;
    private $offset;
    private $orderBy = [];

    public function __construct($conn, $table) {
        $this->conn = $conn;
        $this->table = $table;
    }

    public function select(...$columns) {
        $this->select = $columns ?: ['*'];
        return $this;
    }

    public function where($column, $operator, $value) {
        $this->where[] = "`$column` $operator ?";
        $this->whereParams[] = $value;
        return $this;
    }

    public function andWhere($column, $operator, $value) {
        return $this->where($column, $operator, $value);
    }

    public function limit($limit) {
        $this->limit = (int)$limit;
        return $this;
    }

    public function offset($offset) {
        $this->offset = (int)$offset;
        return $this;
    }

    public function orderBy($column, $direction = 'ASC') {
        $this->orderBy[] = "`$column` " . (strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC');
        return $this;
    }

    public function build() {
        $sql = 'SELECT ' . implode(', ', $this->select ?: ['*']) . ' FROM `' . $this->table . '`';

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    public function execute() {
        $sql = $this->build();
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            ErrorHandler::log([
                'type' => 'Database Error',
                'message' => $this->conn->error,
                'query' => $sql
            ]);
            return false;
        }

        if (!empty($this->whereParams)) {
            $types = str_repeat('s', count($this->whereParams));
            $stmt->bind_param($types, ...$this->whereParams);
        }

        if (!$stmt->execute()) {
            ErrorHandler::log([
                'type' => 'Query Execution Error',
                'message' => $stmt->error,
                'query' => $sql
            ]);
            return false;
        }

        return $stmt->get_result();
    }

    public function first() {
        $this->limit = 1;
        $result = $this->execute();
        return $result ? $result->fetch_assoc() : null;
    }

    public function get() {
        $result = $this->execute();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function count() {
        $sql = 'SELECT COUNT(*) as count FROM `' . $this->table . '`';
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        $stmt = $this->conn->prepare($sql);
        if (!empty($this->whereParams)) {
            $types = str_repeat('s', count($this->whereParams));
            $stmt->bind_param($types, ...$this->whereParams);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }
}

/**
 * Helper function to create QueryBuilder instance
 */
function query($table) {
    global $conn;
    return new QueryBuilder($conn, $table);
}
