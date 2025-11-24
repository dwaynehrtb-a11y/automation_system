<?php
require_once 'config/db.php';

$class_code = '25_T2_CTAPROJ1_INF223';

echo "<h2>Component Percentages for $class_code</h2>";

$stmt = $conn->prepare("
    SELECT id, component_name, term_type, percentage, order_index
    FROM grading_components
    WHERE class_code = ?
    ORDER BY term_type, order_index, id
");
$stmt->bind_param("s", $class_code);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Component Name</th><th>Term</th><th>Percentage</th><th>Order</th></tr>";

$total_midterm = 0;
$total_finals = 0;

while ($row = $result->fetch_assoc()) {
    $pct_color = $row['percentage'] == 0 ? 'red' : 'green';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td><strong>" . htmlspecialchars($row['component_name']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['term_type']) . "</td>";
    echo "<td style='color: $pct_color; font-weight: bold; font-size: 16px;'>" . htmlspecialchars($row['percentage']) . "%</td>";
    echo "<td>" . htmlspecialchars($row['order_index']) . "</td>";
    echo "</tr>";
    
    if ($row['term_type'] === 'midterm') {
        $total_midterm += $row['percentage'];
    } else {
        $total_finals += $row['percentage'];
    }
}

echo "</table>";

echo "<br>";
echo "<h3>Totals:</h3>";
echo "<p><strong>Midterm Total:</strong> <span style='color: " . ($total_midterm == 100 ? 'green' : 'red') . "; font-size: 18px;'>$total_midterm%</span> " . ($total_midterm == 100 ? '✓' : '⚠️ Should be 100%') . "</p>";
echo "<p><strong>Finals Total:</strong> <span style='color: " . ($total_finals == 100 ? 'green' : 'red') . "; font-size: 18px;'>$total_finals%</span> " . ($total_finals == 100 ? '✓' : '⚠️ Should be 100%') . "</p>";

if ($total_midterm != 100 || $total_finals != 100) {
    echo "<hr>";
    echo "<h3 style='color: red;'>⚠️ Issue Found!</h3>";
    echo "<p>Component percentages don't add up to 100%. You need to edit the components and set their percentages correctly.</p>";
    echo "<p>For example: Classwork 10%, Quiz 10%, Lab 20%, Exam 60%, etc.</p>";
}
