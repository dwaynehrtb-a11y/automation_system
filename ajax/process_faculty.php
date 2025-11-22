    <?php
    error_reporting(0);
    ini_set('display_errors', 0);

    try {
    require_once '../config/session.php';
    require_once '../config/db.php';

    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
    } catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Configuration error']);
    exit();
    }

    header('Content-Type: application/json');


    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    try {
    switch ($action) {
    case 'delete':
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid faculty ID']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'faculty'");
    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Faculty deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Faculty not found or already deleted']);
    }
    $stmt->close();
    break;

    case 'update':
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $employee_id = trim($_POST['employee_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($id <= 0 || empty($name) || empty($email) || empty($employee_id)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    if (!empty($password) && trim($password) !== '') {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, employee_id = ?, password = ? WHERE id = ? AND role = 'faculty'");
        $stmt->bind_param("ssssi", $name, $email, $employee_id, $hashed_password, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, employee_id = ? WHERE id = ? AND role = 'faculty'");
        $stmt->bind_param("sssi", $name, $email, $employee_id, $id);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Faculty member updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
    $stmt->close();
    break;

    default:
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    break;
    }

    } catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
    ?>