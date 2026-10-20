<?php
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$baseDir = rtrim(dirname($script), '/');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$rootPath = ($baseDir === '' ? '/' : $baseDir . '/');

// Unknown paths must not fall through to this redirect (PHP's built-in server does that).
if ($path !== $rootPath && $path !== $script) {
    http_response_code(404);
    exit();
}

$target = ($baseDir === '' ? '' : $baseDir) . '/public/index.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}

header('Location: ' . $target);
exit();
