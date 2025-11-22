<?php
/**
 * Background Email Queue Processor
 * Run this script via Windows Task Scheduler or manually
 */

define('SYSTEM_ACCESS', true);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email_helper.php';

// Set execution time limit
set_time_limit(300); // 5 minutes max

echo "Starting email queue processing...\n";

// Get pending emails (limit 50 at a time)
$result = $conn->query(
    "SELECT * FROM email_queue 
     WHERE status = 'pending' AND retry_count < 3 
     ORDER BY created_at ASC 
     LIMIT 50"
);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$processed = 0;
$sent = 0;
$failed = 0;

while ($email_job = $result->fetch_assoc()) {
    $processed++;
    
    echo "[{$processed}] Processing: {$email_job['recipient_email']}... ";
    
    try {
        $send_result = sendStudentAccountCreationEmail(
            $email_job['recipient_email'],
            $email_job['recipient_name'],
            $email_job['user_id'],
            $email_job['temporary_password']
        );
        
        if ($send_result['success']) {
            // Mark as sent
            $update_stmt = $conn->prepare(
                "UPDATE email_queue 
                 SET status = 'sent', sent_at = NOW() 
                 WHERE id = ?"
            );
            $update_stmt->bind_param('i', $email_job['id']);
            $update_stmt->execute();
            
            echo "✅ SENT\n";
            $sent++;
        } else {
            throw new Exception($send_result['message']);
        }
        
    } catch (Exception $e) {
        // Increment retry count
        $new_retry = $email_job['retry_count'] + 1;
        $new_status = ($new_retry >= 3) ? 'failed' : 'pending';
        
        $update_stmt = $conn->prepare(
            "UPDATE email_queue 
             SET retry_count = ?, 
                 error_message = ?,
                 status = ?
             WHERE id = ?"
        );
        $error_msg = $e->getMessage();
        $update_stmt->bind_param('issi', $new_retry, $error_msg, $new_status, $email_job['id']);
        $update_stmt->execute();
        
        echo "❌ FAILED (Retry {$new_retry}/3): {$error_msg}\n";
        $failed++;
    }
    
    // Small delay between emails (prevent spam filters)
    usleep(200000); // 0.2 seconds
}

echo "\n=== SUMMARY ===\n";
echo "Processed: {$processed}\n";
echo "Sent: {$sent}\n";
echo "Failed: {$failed}\n";
echo "Done!\n";
?>