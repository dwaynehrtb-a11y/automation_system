<?php
// Bulk flexible grade upload feature removed. Endpoint retired.
header('Content-Type: application/json');
http_response_code(410); // Gone
echo json_encode(['success'=>false,'message'=>'Bulk flexible grade upload removed.']);
?>
