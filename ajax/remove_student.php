<?php
require_once '../config/session.php';
require_once '../config/db.php';
if (isset($_GET['grade_id'], $_GET['subject_id'])) {
$gid = intval($_GET['grade_id']);
$conn->prepare("DELETE FROM grades WHERE id = ?")
->bind_param("i", $gid)
->execute();
}
header("Location: add_student.php?subject_id=" . intval($_GET['subject_id']));
exit();
?>
