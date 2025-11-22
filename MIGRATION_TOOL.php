<?php
/**
 * Migration Tool - Update Existing AJAX Endpoints to Use New Security System
 * 
 * Usage:
 * 1. Review the patterns below
 * 2. Manually update each AJAX endpoint using the template
 * 3. Test thoroughly before deploying
 */

echo "=" . str_repeat("=", 78) . "\n";
echo "AJAX Endpoint Migration Guide\n";
echo "=" . str_repeat("=", 78) . "\n\n";

$patterns = [
    [
        'title' => 'Pattern 1: Add Security Headers',
        'before' => <<<'BEFORE'
<?php
require_once '../../config/db.php';

$result = $_POST['id'];
BEFORE,
        'after' => <<<'AFTER'
<?php
require_once '../../config/db.php';
header('Content-Type: application/json');

try {
    SecurityMiddleware::verifyAjaxRequest();
    Auth::requireLogin();
    
    $result = Sanitizer::integer($_POST['id'] ?? 0);
AFTER,
    ],
    
    [
        'title' => 'Pattern 2: Add Input Validation',
        'before' => <<<'BEFORE'
$email = $_POST['email'];
$name = $_POST['name'];
BEFORE,
        'after' => <<<'AFTER'
$input = [
    'email' => Sanitizer::email($_POST['email'] ?? ''),
    'name' => Sanitizer::string($_POST['name'] ?? '')
];

SecurityMiddleware::validateInput($input, [
    'email' => 'required|email',
    'name' => 'required|min:2|max:100'
]);

$email = $input['email'];
$name = $input['name'];
AFTER,
    ],
    
    [
        'title' => 'Pattern 3: Replace Direct Queries',
        'before' => <<<'BEFORE'
$sql = "SELECT * FROM users WHERE id = $id";
$result = $conn->query($sql);
BEFORE,
        'after' => <<<'AFTER'
$result = query('users')
    ->where('id', '=', $id)
    ->first();
AFTER,
    ],
    
    [
        'title' => 'Pattern 4: Update Response Format',
        'before' => <<<'BEFORE'
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error']);
}
BEFORE,
        'after' => <<<'AFTER'
$response = new ApiResponse();
if ($stmt->execute()) {
    $response->success(null, 'Updated')->send();
} else {
    $response->serverError('Update failed')->send();
}
AFTER,
    ],
    
    [
        'title' => 'Pattern 5: Add Error Handling',
        'before' => <<<'BEFORE'
$stmt->execute();
echo json_encode(['success' => true]);
BEFORE,
        'after' => <<<'AFTER'
if (!$stmt->execute()) {
    ErrorHandler::log(['error' => $stmt->error]);
    $response = new ApiResponse();
    $response->serverError('Execution failed')->send();
}

$response = new ApiResponse();
$response->success(null, 'Success')->send();
AFTER,
    ]
];

foreach ($patterns as $index => $pattern) {
    echo "\n" . ($index + 1) . ". " . $pattern['title'] . "\n";
    echo str_repeat("-", 80) . "\n";
    echo "BEFORE:\n";
    echo $pattern['before'] . "\n\n";
    echo "AFTER:\n";
    echo $pattern['after'] . "\n";
}

echo "\n\n" . "=" . str_repeat("=", 78) . "\n";
echo "MIGRATION CHECKLIST\n";
echo "=" . str_repeat("=", 78) . "\n\n";

$checklist = [
    "1. All endpoints require(): config/db.php (which loads helpers)",
    "2. All endpoints set header('Content-Type: application/json')",
    "3. All endpoints verify CSRF: SecurityMiddleware::verifyAjaxRequest()",
    "4. All endpoints check auth: Auth::requireLogin() or Auth::requireRole('faculty')",
    "5. All inputs sanitized: Sanitizer::string(), ::email(), ::integer(), etc.",
    "6. All inputs validated: SecurityMiddleware::validateInput() or Validator::validate()",
    "7. All database queries use QueryBuilder or prepared statements",
    "8. All responses use ApiResponse class (->success(), ->created(), ->error(), etc.)",
    "9. All exceptions logged: ErrorHandler::log() or via ApiResponse",
    "10. Critical operations logged: SecurityMiddleware::logAccess()",
];

foreach ($checklist as $item) {
    echo "☐ " . $item . "\n";
}

echo "\n\n" . "=" . str_repeat("=", 78) . "\n";
echo "UPDATED FILE TEMPLATE\n";
echo "=" . str_repeat("=", 78) . "\n\n";

$template = <<<'TEMPLATE'
<?php
/**
 * [Endpoint Name]
 * [Description]
 * 
 * POST Parameters:
 * - action: required - create|update|delete
 * - id: optional - record ID
 */

require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

try {
    // Security checks
    SecurityMiddleware::verifyAjaxRequest();
    Auth::requireLogin();
    
    // Get and sanitize input
    $input = [
        'action' => Sanitizer::string($_POST['action'] ?? ''),
        'id' => Sanitizer::integer($_POST['id'] ?? 0),
    ];
    
    // Validate input
    SecurityMiddleware::validateInput($input, [
        'action' => 'required|in:create,update,delete',
        'id' => 'numeric',
    ]);
    
    // Log access
    SecurityMiddleware::logAccess(__FILE__, $input['action'], ['id' => $input['id']]);
    
    // Handle action
    $response = new ApiResponse();
    
    switch ($input['action']) {
        case 'create':
            // TODO: Implement create logic
            $response->created(null, 'Created successfully')->send();
            break;
            
        case 'update':
            // TODO: Implement update logic
            $response->success(null, 'Updated successfully')->send();
            break;
            
        case 'delete':
            // TODO: Implement delete logic
            $response->success(null, 'Deleted successfully')->send();
            break;
            
        default:
            $response->badRequest('Invalid action')->send();
    }
    
} catch (Exception $e) {
    ErrorHandler::log(['error' => $e->getMessage()]);
    $response = new ApiResponse();
    $response->serverError($e->getMessage())->send();
}
TEMPLATE;

echo $template . "\n";

echo "\n\n" . "=" . str_repeat("=", 78) . "\n";
echo "ENDPOINTS TO MIGRATE\n";
echo "=" . str_repeat("=", 78) . "\n\n";

$endpoints = [
    "ajax/create_faculty_account.php",
    "ajax/delete_grade.php",
    "ajax/download_student_template_excel.php",
    "ajax/generate_excel_template.php",
    "ajax/generate_excel.php",
    "ajax/get_analytics_data.php",
    "ajax/get_class_group_schedules.php",
    "ajax/get_class_schedules.php",
    "ajax/get_course_info.php",
    "ajax/get_faculty_classes.php",
    "ajax/get_faculty_list.php",
    "ajax/get_sections_list.php",
    "ajax/get_student_details.php",
    "ajax/get_student_outcomes.php",
    "ajax/get_subject_outcomes.php",
    "ajax/import_classes.php",
    "ajax/manage_co_so_mapping.php",
    "ajax/manage_course_outcomes.php",
    "ajax/process_bulk_student_import.php",
    "ajax/process_class.php",
    "ajax/process_faculty.php",
    "ajax/process_section.php",
    "ajax/process_student.php",
    "ajax/process_subject.php",
    "ajax/remove_student.php",
    "ajax/resend_faculty_credentials.php",
    "ajax/resend_student_credentials.php",
    "ajax/update_grade.php",
    "ajax/update_preferences.php",
    "ajax/update_profile.php",
    "ajax/upload_grades.php",
    "ajax/validate_faculty_subjects.php",
];

$count = count($endpoints);
echo "Total endpoints to update: " . $count . "\n\n";

foreach ($endpoints as $index => $endpoint) {
    echo "[ ] (" . ($index + 1) . "/" . $count . ") " . $endpoint . "\n";
}

echo "\n\nNext steps:\n";
echo "1. Run this script to understand the migration patterns\n";
echo "2. Review DEVELOPER_GUIDE.md for detailed examples\n";
echo "3. Migrate endpoints one by one\n";
echo "4. Test each endpoint thoroughly\n";
echo "5. Deploy with confidence!\n";
