<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Simple file-based storage (replace with database)
$dataFile = '../data/jobs.json';

function loadJobs() {
    global $dataFile;
    if (file_exists($dataFile)) {
        return json_decode(file_get_contents($dataFile), true);
    }
    return [];
}

function saveJobs($jobs) {
    global $dataFile;
    $dir = dirname($dataFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($dataFile, json_encode($jobs, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        echo json_encode(loadJobs());
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        $jobs = loadJobs();
        
        $newJob = [
            'id' => count($jobs) + 1,
            'title' => $input['title'],
            'department' => $input['department'],
            'type' => $input['type'],
            'location' => $input['location'],
            'salary' => $input['salary'],
            'applications' => 0,
            'status' => 'Active',
            'posted' => date('Y-m-d'),
            'deadline' => $input['deadline'],
            'description' => $input['description'],
            'requirements' => $input['requirements']
        ];
        
        $jobs[] = $newJob;
        saveJobs($jobs);
        
        echo json_encode(['success' => true, 'job' => $newJob]);
        break;
        
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        $jobs = loadJobs();
        
        foreach ($jobs as &$job) {
            if ($job['id'] == $input['id']) {
                $job = array_merge($job, $input);
                break;
            }
        }
        
        saveJobs($jobs);
        echo json_encode(['success' => true]);
        break;
        
    case 'DELETE':
        $id = $_GET['id'];
        $jobs = loadJobs();
        
        $jobs = array_filter($jobs, function($job) use ($id) {
            return $job['id'] != $id;
        });
        
        saveJobs(array_values($jobs));
        echo json_encode(['success' => true]);
        break;
}
?>