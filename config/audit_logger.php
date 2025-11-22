<?php
/**
 * Simple audit logger
 * Usage: audit_log($actorId, $actorRole, $action, $classCode, $detailsArray, $affectedRows, $durationMs)
 */
function audit_log($actorId, $actorRole, $action, $classCode, $details = [], $affectedRows = 0, $durationMs = 0) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    $file = $logDir . '/audit.log';
    $entry = [
        'ts' => date('Y-m-d H:i:s'),
        'actor_id' => $actorId,
        'actor_role' => $actorRole,
        'action' => $action,
        'class_code' => $classCode,
        'details' => $details,
        'affected_rows' => $affectedRows,
        'duration_ms' => $durationMs,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
    ];
    @file_put_contents($file, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}
?>