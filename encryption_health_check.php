<?php
/**
 * Encryption System Health Check
 * Verifies the encryption class is working correctly
 */
define('SYSTEM_ACCESS', true);
require_once 'config/session.php';
require_once 'config/db.php';
require_once 'config/encryption.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Encryption System Health Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #003082; }
        .test { padding: 15px; margin: 15px 0; border-radius: 4px; border-left: 4px solid #ddd; }
        .pass { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .fail { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8; }
        .code { font-family: monospace; background: #f0f0f0; padding: 10px; border-radius: 4px; margin: 10px 0; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #003082; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔐 Encryption System Health Check</h1>

    <?php
    $tests_passed = 0;
    $tests_failed = 0;

    // TEST 1: Check if Encryption class exists
    echo "<div class='test info'><strong>TEST 1: Encryption Class</strong></div>";
    if (class_exists('Encryption')) {
        echo "<div class='test pass'>✓ PASS: Encryption class is defined</div>";
        $tests_passed++;
    } else {
        echo "<div class='test fail'>✗ FAIL: Encryption class not found</div>";
        $tests_failed++;
    }

    // TEST 2: Check if encryption key is set
    echo "<div class='test info'><strong>TEST 2: Environment Configuration</strong></div>";
    try {
        $key = getenv('APP_ENCRYPTION_KEY') ?: getenv('APP_KEY');
        if ($key) {
            echo "<div class='test pass'>✓ PASS: APP_ENCRYPTION_KEY or APP_KEY is set</div>";
            echo "<div class='code'>Key preview: " . htmlspecialchars(substr($key, 0, 20) . '...[' . strlen($key) . ' chars]') . "</div>";
            $tests_passed++;
        } else {
            echo "<div class='test fail'>✗ FAIL: No encryption key found in environment</div>";
            $tests_failed++;
        }
    } catch (Exception $e) {
        echo "<div class='test fail'>✗ FAIL: Error checking environment: " . $e->getMessage() . "</div>";
        $tests_failed++;
    }

    // TEST 3: Initialize encryption
    echo "<div class='test info'><strong>TEST 3: Encryption Initialization</strong></div>";
    try {
        Encryption::init();
        echo "<div class='test pass'>✓ PASS: Encryption::init() succeeded</div>";
        $tests_passed++;
    } catch (Exception $e) {
        echo "<div class='test fail'>✗ FAIL: Encryption::init() failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        $tests_failed++;
    }

    // TEST 4: Test encryption
    echo "<div class='test info'><strong>TEST 4: Test Encryption Operation</strong></div>";
    try {
        $test_value = "85.5";
        $encrypted = Encryption::encrypt($test_value);
        
        if ($encrypted && strlen($encrypted) > 0) {
            echo "<div class='test pass'>✓ PASS: Encryption works</div>";
            echo "<div class='code'>Original: " . htmlspecialchars($test_value) . "<br>";
            echo "Encrypted: " . htmlspecialchars(substr($encrypted, 0, 50)) . "...[" . strlen($encrypted) . " chars]</div>";
            $tests_passed++;
            
            // TEST 5: Test decryption
            echo "<div class='test info'><strong>TEST 5: Test Decryption Operation</strong></div>";
            try {
                $decrypted = Encryption::decrypt($encrypted);
                if ($decrypted === $test_value) {
                    echo "<div class='test pass'>✓ PASS: Decryption works correctly</div>";
                    echo "<div class='code'>Decrypted: " . htmlspecialchars($decrypted) . "<br>";
                    echo "Matches original: YES</div>";
                    $tests_passed++;
                } else {
                    echo "<div class='test fail'>✗ FAIL: Decrypted value doesn't match original</div>";
                    echo "<div class='code'>Expected: " . htmlspecialchars($test_value) . "<br>";
                    echo "Got: " . htmlspecialchars($decrypted) . "</div>";
                    $tests_failed++;
                }
            } catch (Exception $e) {
                echo "<div class='test fail'>✗ FAIL: Decryption failed: " . htmlspecialchars($e->getMessage()) . "</div>";
                $tests_failed++;
            }
        } else {
            echo "<div class='test fail'>✗ FAIL: Encryption returned empty or null</div>";
            $tests_failed++;
        }
    } catch (Exception $e) {
        echo "<div class='test fail'>✗ FAIL: Encryption test failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        $tests_failed++;
    }

    // TEST 6: Test with actual database grade
    echo "<div class='test info'><strong>TEST 6: Database Grade Decryption Test</strong></div>";
    try {
        $stmt = $conn->prepare("SELECT id, term_grade, is_encrypted FROM grade_term WHERE is_encrypted = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "<div>Found encrypted grade record (ID: " . $row['id'] . ")</div>";
            
            try {
                $decrypted_value = Encryption::decrypt($row['term_grade']);
                echo "<div class='test pass'>✓ PASS: Successfully decrypted database value</div>";
                echo "<div class='code'>Encrypted: " . htmlspecialchars(substr($row['term_grade'], 0, 40)) . "...<br>";
                echo "Decrypted: " . htmlspecialchars($decrypted_value) . "</div>";
                $tests_passed++;
            } catch (Exception $e) {
                echo "<div class='test fail'>✗ FAIL: Failed to decrypt database value: " . htmlspecialchars($e->getMessage()) . "</div>";
                $tests_failed++;
            }
        } else {
            echo "<div class='test info'>ℹ️ INFO: No encrypted grades found in database to test</div>";
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "<div class='test fail'>✗ FAIL: Database query error: " . htmlspecialchars($e->getMessage()) . "</div>";
        $tests_failed++;
    }

    // TEST 7: Check database tables
    echo "<div class='test info'><strong>TEST 7: Database Tables Status</strong></div>";
    try {
        $check = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('grade_term', 'grade_visibility_status')");
        $tables = [];
        while ($t = $check->fetch_assoc()) {
            $tables[] = $t['TABLE_NAME'];
        }
        
        if (in_array('grade_term', $tables)) {
            echo "<div class='test pass'>✓ PASS: grade_term table exists</div>";
            $tests_passed++;
        } else {
            echo "<div class='test fail'>✗ FAIL: grade_term table not found</div>";
            $tests_failed++;
        }
        
        if (in_array('grade_visibility_status', $tables)) {
            echo "<div class='test pass'>✓ PASS: grade_visibility_status table exists</div>";
            $tests_passed++;
        } else {
            echo "<div class='test fail'>✗ FAIL: grade_visibility_status table not found</div>";
            $tests_failed++;
        }
    } catch (Exception $e) {
        echo "<div class='test fail'>✗ FAIL: Database table check error: " . htmlspecialchars($e->getMessage()) . "</div>";
        $tests_failed++;
    }

    // TEST 8: Check required columns
    echo "<div class='test info'><strong>TEST 8: Required Database Columns</strong></div>";
    try {
        $check = $conn->query("DESCRIBE grade_term");
        $columns = [];
        while ($c = $check->fetch_assoc()) {
            $columns[] = $c['Field'];
        }
        
        $required = ['is_encrypted', 'term_grade', 'midterm_percentage', 'finals_percentage', 'term_percentage'];
        $missing = array_diff($required, $columns);
        
        if (empty($missing)) {
            echo "<div class='test pass'>✓ PASS: All required columns present in grade_term</div>";
            $tests_passed++;
        } else {
            echo "<div class='test fail'>✗ FAIL: Missing columns: " . implode(', ', $missing) . "</div>";
            $tests_failed++;
        }
    } catch (Exception $e) {
        echo "<div class='test fail'>✗ FAIL: Column check error: " . htmlspecialchars($e->getMessage()) . "</div>";
        $tests_failed++;
    }

    // SUMMARY
    echo "<div style='margin-top: 30px; padding: 20px; background: #f0f0f0; border-radius: 8px;'>";
    echo "<h2 style='color: #003082; margin-top: 0;'>Health Check Summary</h2>";
    echo "<table>";
    echo "<tr><th>Metric</th><th>Result</th></tr>";
    echo "<tr><td>Tests Passed</td><td style='color: #28a745; font-weight: bold;'>" . $tests_passed . "</td></tr>";
    echo "<tr><td>Tests Failed</td><td style='color: #dc3545; font-weight: bold;'>" . $tests_failed . "</td></tr>";
    echo "<tr><td>Total Tests</td><td>" . ($tests_passed + $tests_failed) . "</td></tr>";
    echo "</table>";

    if ($tests_failed === 0) {
        echo "<div class='test pass' style='margin-top: 20px;'><strong>✓ ALL TESTS PASSED</strong><br>The encryption system is working correctly. If students still see locked grades, the issue is likely:<br>1. Faculty hasn't clicked the Show Grades button<br>2. The decrypt operation isn't being called<br>3. Browser cache issue<br><br>Try using grade_visibility_debug.php to check and manually fix the encryption status.</div>";
    } else {
        echo "<div class='test fail' style='margin-top: 20px;'><strong>✗ SOME TESTS FAILED</strong><br>The encryption system has issues. Please check the error details above and ensure:<br>1. APP_ENCRYPTION_KEY is set in .env file<br>2. Encryption class is properly defined<br>3. Database tables have required columns<br><br>Contact system administrator for help.</div>";
    }
    echo "</div>";

    ?>

</div>
</body>
</html>
