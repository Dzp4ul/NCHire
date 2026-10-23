<?php
/**
 * Shared CSRF helpers for applicant-facing mutation endpoints.
 */

if (!function_exists('nc_csrf_token')) {
    function nc_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }
}

if (!function_exists('nc_csrf_is_valid')) {
    function nc_csrf_is_valid(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return !empty($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals((string)$_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('nc_require_csrf')) {
    function nc_require_csrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (nc_csrf_is_valid(is_string($token) ? $token : '')) {
            return;
        }
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}
