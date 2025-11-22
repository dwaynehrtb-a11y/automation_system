<?php
// Export flexible columns feature removed alongside bulk grade import.
header('Content-Type: text/plain');
http_response_code(410);
echo 'export_flexible_columns removed';
?>