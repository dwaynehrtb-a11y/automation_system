<?php
/**
 * Improved AJAX Endpoint Example
 * Demonstrates security best practices and centralized error handling
 */

// Session must start before requiring db
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration and security
require_once '../../config/db.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Verify AJAX request
    SecurityMiddleware::verifyAjaxRequest();

    // Require authentication
    Auth::requireLogin();

    // Get action from POST
    $action = $_POST['action'] ?? null;

    if (!$action) {
        $response = new ApiResponse();
        $response->badRequest('Action is required')->send();
    }

    // Validate and sanitize inputs
    $input = [
        'action' => Sanitizer::string($action),
        'id' => Sanitizer::integer($_POST['id'] ?? 0),
        'name' => Sanitizer::string($_POST['name'] ?? ''),
        'email' => Sanitizer::email($_POST['email'] ?? '')
    ];

    // Define validation rules
    $rules = [
        'action' => 'required|in:create,update,delete',
        'id' => 'numeric',
        'name' => 'required|min:2|max:100',
        'email' => 'required|email'
    ];

    // Validate input
    SecurityMiddleware::validateInput($input, $rules);

    // Log the access
    SecurityMiddleware::logAccess('example_endpoint.php', $input['action'], $input);

    // Handle different actions
    switch ($input['action']) {
        case 'create':
            handleCreate($input);
            break;

        case 'update':
            handleUpdate($input);
            break;

        case 'delete':
            handleDelete($input);
            break;

        default:
            $response = new ApiResponse();
            $response->badRequest('Invalid action')->send();
    }

} catch (Exception $e) {
    ErrorHandler::log(['error' => $e->getMessage(), 'file' => __FILE__]);
    
    $response = new ApiResponse();
    $response->serverError('An error occurred: ' . $e->getMessage())->send();
}

/**
 * Handle create action
 */
function handleCreate($input) {
    global $conn;
    
    // Use QueryBuilder for secure queries
    $existing = query('users')
        ->where('email', '=', $input['email'])
        ->first();

    if ($existing) {
        $response = new ApiResponse();
        $response->conflict('Email already exists')->send();
    }

    // Example: Insert data
    $stmt = $conn->prepare("INSERT INTO users (name, email, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param('ss', $input['name'], $input['email']);

    if ($stmt->execute()) {
        $response = new ApiResponse();
        $response->created(['id' => $conn->insert_id], 'User created successfully')->send();
    } else {
        $response = new ApiResponse();
        $response->serverError('Failed to create user')->send();
    }
}

/**
 * Handle update action
 */
function handleUpdate($input) {
    global $conn;

    if (!$input['id']) {
        $response = new ApiResponse();
        $response->badRequest('ID is required')->send();
    }

    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ssi', $input['name'], $input['email'], $input['id']);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $response = new ApiResponse();
        $response->success(null, 'User updated successfully')->send();
    } else {
        $response = new ApiResponse();
        $response->notFound('User not found')->send();
    }
}

/**
 * Handle delete action
 */
function handleDelete($input) {
    global $conn;

    if (!$input['id']) {
        $response = new ApiResponse();
        $response->badRequest('ID is required')->send();
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $input['id']);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $response = new ApiResponse();
        $response->success(null, 'User deleted successfully')->send();
    } else {
        $response = new ApiResponse();
        $response->notFound('User not found')->send();
    }
}
