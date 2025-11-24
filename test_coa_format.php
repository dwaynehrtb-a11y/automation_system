<?php
chdir(__DIR__ . '/faculty/ajax');
session_start();
$_GET['class_code'] = '24_T2_CCPRGG1L_INF222';
$_SESSION['user_id'] = 114;
$_SESSION['role'] = 'faculty';

include 'generate_coa_html.php';
?>
