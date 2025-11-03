<?php
/**
 * Configuration File
 * Modify these settings as needed for your environment
 */

// Database Configuration
define('DB_PATH', __DIR__ . '/database/prompts.db');

// Session Configuration
define('SESSION_NAME', 'prompt_bank_session');
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

// Application Settings
define('APP_NAME', 'System Prompt Bank');
define('APP_VERSION', '1.0.0');

// Security Settings
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);

// Pagination Settings
define('PROMPTS_PER_PAGE', 50);

// File Upload Settings (for future use)
define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['txt', 'md', 'json']);

// Timezone
date_default_timezone_set('UTC');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enable session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
