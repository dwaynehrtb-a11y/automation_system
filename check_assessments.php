<?php
$conn = new mysqli('localhost', 'root', '', 'automation_system');
$result = $conn->query("SELECT DISTINCT gc.component_name FROM grading_components gc WHERE gc.class_code = '24_T2_CCPRGG1L_INF222' ORDER BY gc.component_name");
echo "Assessment components:\n";
while($row = $result->fetch_assoc()) {
    echo "- " . $row['component_name'] . "\n";
}
?>
