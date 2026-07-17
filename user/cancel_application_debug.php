<?php
// Absolute minimal version for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/cancel_errors.log');

// Log everything
file_put_contents(__DIR__ . '/cancel_debug.log', "Script started\n", FILE_APPEND);

ob_start();
session_start();
ob_end_clean();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Test - script reached']);
exit;
?>
