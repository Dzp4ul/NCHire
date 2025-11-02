<?php
session_start();

// Set JSON header
header('Content-Type: application/json');

try {
    // Get raw POST data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (isset($data['job_id']) && is_numeric($data['job_id'])) {
        $_SESSION['view_job_id'] = intval($data['job_id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Job ID stored in session',
            'job_id' => $_SESSION['view_job_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid job ID'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
