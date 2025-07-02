<?php
/**
 * ROTZ Email Butler - Application Configuration
 * 
 * This file contains the main application configuration settings.
 * It is created during the setup process and should not be modified manually.
 */

// Prevent direct access
if (!defined('ROTZ_EMAIL_BUTLER')) {
    define('ROTZ_EMAIL_BUTLER', true);
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rotz_email_butler');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'ROTZ Email Butler');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost');
define('APP_TIMEZONE', 'UTC');

// Security Settings
define('ENCRYPTION_KEY', 'your-32-character-encryption-key-here');
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Email Processing Settings
define('AUTO_PROCESS_EMAILS', true);
define('ENABLE_SMART_CATEGORIZATION', true);
define('ENABLE_PRIORITY_DETECTION', true);
define('ENABLE_FOLLOW_UP_SUGGESTIONS', true);
define('SYNC_INTERVAL_MINUTES', 15);
define('MAX_EMAILS_PER_SYNC', 50);

// AI Processing Settings
define('ENABLE_MULTI_AI_CONSENSUS', true);
define('ENABLE_COST_OPTIMIZATION', true);
define('ENABLE_AI_LEARNING', false);
define('AI_CONFIDENCE_THRESHOLD', 70);

// Security Features
define('ENABLE_2FA', false);
define('ENABLE_LOGIN_ATTEMPTS_LIMIT', true);
define('ENABLE_ACTIVITY_LOGGING', true);

// Registration Settings
define('ALLOW_REGISTRATION', false);

// API Rate Limiting
define('API_RATE_LIMIT', 1000); // requests per hour
define('API_BURST_LIMIT', 100); // requests per minute

// File Upload Settings
define('MAX_UPLOAD_SIZE', '10M');
define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,txt');

// Email Provider Timeouts
define('IMAP_TIMEOUT', 30);
define('SMTP_TIMEOUT', 30);
define('API_TIMEOUT', 30);

// AI Provider Timeouts
define('AI_REQUEST_TIMEOUT', 60);
define('AI_MAX_RETRIES', 3);

// Logging Settings
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_MAX_FILES', 30);
define('LOG_MAX_SIZE', '10M');

// Cache Settings
define('ENABLE_CACHE', true);
define('CACHE_LIFETIME', 3600); // 1 hour

// Development Settings
define('DEBUG_MODE', false);
define('SHOW_ERRORS', false);

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Set error reporting based on debug mode
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

// Set memory limit for email processing
ini_set('memory_limit', '512M');

// Set execution time limit for sync operations
ini_set('max_execution_time', 300); // 5 minutes

// Auto-load composer dependencies
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Helper function to get configuration value
function getConfig($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

// Helper function to check if feature is enabled
function isFeatureEnabled($feature) {
    return getConfig($feature, false) === true;
}

// Helper function to get database connection details
function getDatabaseConfig() {
    return [
        'host' => DB_HOST,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASS,
        'charset' => DB_CHARSET,
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ];
}

// Helper function to generate secure random string
function generateSecureKey($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Helper function to validate email address
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Helper function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Helper function to log messages
function logMessage($level, $message, $context = []) {
    if (!isFeatureEnabled('ENABLE_ACTIVITY_LOGGING')) {
        return;
    }
    
    $logFile = __DIR__ . '/../logs/' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Helper function to check rate limits
function checkRateLimit($identifier, $limit, $window = 3600) {
    $cacheFile = __DIR__ . '/../cache/rate_limit_' . md5($identifier) . '.json';
    $cacheDir = dirname($cacheFile);
    
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $now = time();
    $data = [];
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true) ?: [];
    }
    
    // Clean old entries
    $data = array_filter($data, function($timestamp) use ($now, $window) {
        return ($now - $timestamp) < $window;
    });
    
    // Check if limit exceeded
    if (count($data) >= $limit) {
        return false;
    }
    
    // Add current request
    $data[] = $now;
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    
    return true;
}

// Helper function to get client IP
function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            return trim($ips[0]);
        }
    }
    
    return '0.0.0.0';
}

// Helper function to validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper function to generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Initialize application
function initializeApp() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate CSRF token
    generateCSRFToken();
    
    // Set up error handler
    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        logMessage('ERROR', "PHP Error: {$message} in {$file} on line {$line}");
        
        if (DEBUG_MODE) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
        
        return true;
    });
    
    // Set up exception handler
    set_exception_handler(function($exception) {
        logMessage('ERROR', "Uncaught Exception: " . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        if (DEBUG_MODE) {
            echo "Uncaught Exception: " . $exception->getMessage();
        } else {
            echo "An error occurred. Please try again later.";
        }
    });
    
    // Clean up old log files
    cleanupOldLogs();
    
    // Clean up old cache files
    cleanupOldCache();
}

// Helper function to clean up old log files
function cleanupOldLogs() {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        return;
    }
    
    $files = glob($logDir . '/*.log');
    if (count($files) > LOG_MAX_FILES) {
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $filesToDelete = array_slice($files, 0, count($files) - LOG_MAX_FILES);
        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }
}

// Helper function to clean up old cache files
function cleanupOldCache() {
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) {
        return;
    }
    
    $files = glob($cacheDir . '/*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > CACHE_LIFETIME) {
            unlink($file);
        }
    }
}

// Initialize the application
initializeApp();
?>

