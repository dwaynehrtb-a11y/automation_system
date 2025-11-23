<?php
// Load error handler and helpers
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';

// Load .env file manually for production
if (!function_exists('loadEnv')) {
    function loadEnv() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
    loadEnv();
}

//if (!defined('SYSTEM_ACCESS')) {
//   http_response_code(403);
//    die('Direct access forbidden');
//}
$config = [
    'development' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'db' => 'automation_system',
        'charset' => 'utf8mb4',
        'debug' => true
    ],
    'production' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'db' => getenv('DB_NAME') ?: 'automation_system',
        'charset' => 'utf8mb4',
        'debug' => false
    ]
];

$environment = getenv('APP_ENV') ?: 'development';
$dbConfig = $config[$environment];

try {
    $conn = new mysqli(
        $dbConfig['host'],
        $dbConfig['user'], 
        $dbConfig['pass'],
        $dbConfig['db']
    );

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $conn->set_charset($dbConfig['charset']);
    $conn->query("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
    $conn->query("SET time_zone = '+00:00'");

} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    
    if ($dbConfig['debug']) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later.");
    }
}

define('DB_ACCESS_ALLOWED', true);

function executeQuery($conn, $sql, $params = [], $types = '') {
    try {
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            if (empty($types)) {
                $types = detectParamTypes($params);
            }
            
            if (!$stmt->bind_param($types, ...$params)) {
                throw new Exception("Bind param failed: " . $stmt->error);
            }
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        return $stmt;
        
    } catch (Exception $e) {
        error_log("Query execution error: " . $e->getMessage() . " | SQL: " . $sql);
        throw $e;
    }
}

function detectParamTypes($params) {
    $types = '';
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } elseif (is_string($param)) {
            $types .= 's';
        } else {
            $types .= 's';
        }
    }
    return $types;
}

function getSafeCount($conn, $table, $where = '', $params = [], $types = '') {
    try {
        // Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new Exception("Invalid table name: $table");
        }
        
        $sql = "SELECT COUNT(*) as count FROM `$table`";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        
        $stmt = executeQuery($conn, $sql, $params, $types);
        $result = $stmt->get_result();
        
        if ($result === false) {
            throw new Exception("Get result failed: " . $conn->error);
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return (int) ($row['count'] ?? 0);
        
    } catch (Exception $e) {
        error_log("getSafeCount error: " . $e->getMessage());
        return 0;
    }
}

function getSafeRecord($conn, $table, $where = '', $params = [], $columns = '*', $types = '') {
    try {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new Exception("Invalid table name: $table");
        }
        
        $sql = "SELECT $columns FROM `$table`";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        $sql .= " LIMIT 1";
        
        $stmt = executeQuery($conn, $sql, $params, $types);
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();
        $stmt->close();
        
        return $record;
        
    } catch (Exception $e) {
        error_log("getSafeRecord error: " . $e->getMessage());
        return null;
    }
}

function getSafeRecords($conn, $table, $where = '', $params = [], $columns = '*', $orderBy = '', $limit = 0, $types = '') {
    try {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new Exception("Invalid table name: $table");
        }
        
        $sql = "SELECT $columns FROM `$table`";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        if (!empty($orderBy)) {
            $sql .= " ORDER BY $orderBy";
        }
        if ($limit > 0) {
            $sql .= " LIMIT $limit";
        }
        
        $stmt = executeQuery($conn, $sql, $params, $types);
        $result = $stmt->get_result();
        
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $stmt->close();
        
        return $records;
        
    } catch (Exception $e) {
        error_log("getSafeRecords error: " . $e->getMessage());
        return [];
    }
}

function insertRecord($conn, $table, $data) {
    try {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new Exception("Invalid table name: $table");
        }
        
        if (empty($data)) {
            throw new Exception("No data provided for insert");
        }
        
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
        
        $stmt = executeQuery($conn, $sql, $values);
        $insertId = $conn->insert_id;
        $stmt->close();
        
        return $insertId;
        
    } catch (Exception $e) {
        error_log("insertRecord error: " . $e->getMessage());
        return false;
    }
}

function updateRecord($conn, $table, $data, $where, $whereParams = []) {
    try {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new Exception("Invalid table name: $table");
        }
        
        if (empty($data)) {
            throw new Exception("No data provided for update");
        }
        
        $setParts = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $setParts[] = "`$column` = ?";
            $values[] = $value;
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE $where";
        $allParams = array_merge($values, $whereParams);
        
        $stmt = executeQuery($conn, $sql, $allParams);
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
        
    } catch (Exception $e) {
        error_log("updateRecord error: " . $e->getMessage());
        return false;
    }
}

function deleteRecord($conn, $table, $where, $params = []) {
    try {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new Exception("Invalid table name: $table");
        }
        
        if (empty($where)) {
            throw new Exception("WHERE clause required for delete operation");
        }
        
        $sql = "DELETE FROM `$table` WHERE $where";
        
        $stmt = executeQuery($conn, $sql, $params);
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
        
    } catch (Exception $e) {
        error_log("deleteRecord error: " . $e->getMessage());
        return false;
    }
}

function tableExists($conn, $table) {
    try {
        $stmt = executeQuery($conn, "SHOW TABLES LIKE ?", [$table]);
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        return $exists;
        
    } catch (Exception $e) {
        error_log("tableExists error: " . $e->getMessage());
        return false;
    }
}

function getSectionTableName($conn) {
    return tableExists($conn, 'sections') ? 'sections' : 'section';
}

function getSafeSectionCount($conn, $where = '', $params = []) {
    $tableName = getSectionTableName($conn);
    return getSafeCount($conn, $tableName, $where, $params);
}

function beginTransaction($conn) {
    return $conn->autocommit(false);
}

function commitTransaction($conn) {
    $result = $conn->commit();
    $conn->autocommit(true);
    return $result;
}

function rollbackTransaction($conn) {
    $result = $conn->rollback();
    $conn->autocommit(true);
    return $result;
}

function escapeLikeString($string) {
    return str_replace(['%', '_'], ['\%', '\_'], $string);
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

if ($dbConfig['debug']) {
    error_log("Database connected successfully to: " . $dbConfig['db']);
}
?>