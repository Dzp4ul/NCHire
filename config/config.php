<?php
/**
 * NCHire - Configuration File
 * Central configuration for the entire application
 */

// Define root paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('USER_PATH', ROOT_PATH . '/user');
define('SHARED_PATH', ROOT_PATH . '/shared');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('VENDOR_PATH', ROOT_PATH . '/vendor');

// Define URL paths (adjust based on your server configuration)
define('BASE_URL', '/FinalResearch - Copy'); // Update this for production
define('PUBLIC_URL', BASE_URL . '/public');
define('ADMIN_URL', BASE_URL . '/admin');
define('USER_URL', BASE_URL . '/user');
define('ASSETS_URL', PUBLIC_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Database configuration
require_once CONFIG_PATH . '/db.php';

// Application settings
define('APP_NAME', 'NCHire');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development'); // development, staging, production

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USERNAME', 'nchire.norzagaray@gmail.com');
define('SMTP_PASSWORD', 'rqsg tlub bwwd kqoo');
define('SMTP_FROM_EMAIL', 'no-reply@nchire.local');
define('SMTP_FROM_NAME', 'NCHire - Norzagaray College');

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf']);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (adjust for production)
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
