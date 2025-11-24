<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "<h2>🔍 Final Verification & Force Fix</h2>";
echo "<pre>";

echo "Step 1: Check Current Database Values\n";
echo "=" . str_repeat("=", 70) . "\n\n";

$query = "SELECT class_code, midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' ORDER BY class_code";
$result = $conn->query($query);

$ccprgg_code = null;
$data = null;

while ($row = $result->fetch_assoc()) {
    if (strpos($row['class_code'], 'CCPRGG') !== false) {
        $ccprgg_code = $row['class_code'];
        $data = $row;
    }
    echo "Class: " . $row['class_code'] . "\n";
    echo "  Mid: " . $row['midterm_percentage'] . "%, Finals: " . $row['finals_percentage'] . "%, Term: " . $row['term_percentage'] . "%, Grade: " . $row['term_grade'] . "\n\n";
}

if (!$ccprgg_code) {
    echo "❌ No CCPRGG class found!\n";
    exit;
}

echo "\nCCPRGG1L Class Code: $ccprgg_code\n\n";

// Check if database has updated values
if ($data['midterm_percentage'] == 62.03 && $data['term_percentage'] == 84.81 && $data['term_grade'] == 3.0) {
    echo "✅ DATABASE HAS CORRECT VALUES!\n\n";
} elseif ($data['midterm_percentage'] == 55.94 && $data['term_percentage'] == 82.38 && $data['term_grade'] == 2.5) {
    echo "❌ DATABASE STILL HAS OLD VALUES - FORCING FIX NOW\n\n";
    
    echo "Executing UPDATE...\n";
    
    $update = "UPDATE grade_term SET midterm_percentage=62.03, finals_percentage=100.00, term_percentage=84.81, term_grade='3.0' WHERE student_id='2022-126653' AND class_code=?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("s", $ccprgg_code);
    
    if ($stmt->execute()) {
        echo "✅ UPDATE executed, rows affected: " . $stmt->affected_rows . "\n\n";
        
        // Verify
        $verify = "SELECT midterm_percentage, finals_percentage, term_percentage, term_grade FROM grade_term WHERE student_id='2022-126653' AND class_code=?";
        $vstmt = $conn->prepare($verify);
        $vstmt->bind_param("s", $ccprgg_code);
        $vstmt->execute();
        $vrow = $vstmt->get_result()->fetch_assoc();
        $vstmt->close();
        
        echo "After update:\n";
        echo "  Mid: " . $vrow['midterm_percentage'] . "%\n";
        echo "  Finals: " . $vrow['finals_percentage'] . "%\n";
        echo "  Term: " . $vrow['term_percentage'] . "%\n";
        echo "  Grade: " . $vrow['term_grade'] . "\n\n";
        
        if ($vrow['term_grade'] == 3.0) {
            echo "✅ ✅ ✅ DATABASE NOW HAS CORRECT GRADE 3.0!\n";
        }
    } else {
        echo "❌ UPDATE failed: " . $stmt->error . "\n";
    }
    $stmt->close();
} else {
    echo "⚠️ Unexpected values\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "Step 2: Browser Cache Issue\n";
echo "=" . str_repeat("=", 70) . "\n\n";

echo "The console shows v=1764016555 which means page reloaded, but\n";
echo "browser is showing OLD cached AJAX response (55.94%, 2.5).\n\n";

echo "To see correct grade (3.0), student needs to:\n\n";
echo "OPTION 1 - Hard Refresh:\n";
echo "  Windows: Press Ctrl+Shift+R\n";
echo "  Mac: Press Cmd+Shift+R\n";
echo "  This clears AJAX response cache\n\n";

echo "OPTION 2 - Incognito Window:\n";
echo "  Open in private/incognito window (no cache)\n";
echo "  Should immediately show 3.0\n\n";

echo "OPTION 3 - Clear All Cache:\n";
echo "  Ctrl+Shift+Delete (or Cmd+Shift+Delete on Mac)\n";
echo "  Clear \"Cached images and files\"\n";
echo "  Then reload page\n\n";

$conn->close();
?>
