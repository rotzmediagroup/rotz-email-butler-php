<?php
/**
 * ROTZ Email Butler - API Router
 * 
 * This file handles all API requests and routes them to appropriate handlers
 */

// Start session and load configuration
session_start();

// Load configuration
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Load classes
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/MultiAIEnsemble.php';
require_once __DIR__ . '/../classes/EmailProvider.php';

use Rotz\EmailButler\Classes\Database;
use Rotz\EmailButler\Classes\MultiAIEnsemble;
use Rotz\EmailButler\Classes\EmailProvider;

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize database
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Authentication check for protected endpoints
function requireAuth() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    return $_SESSION['user_id'];
}

// Admin check
function requireAdmin() {
    $userId = requireAuth();
    global $db;
    $user = $db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
    return $userId;
}

// Get request path and method
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$method = $_SERVER['REQUEST_METHOD'];

// Route the request
try {
    switch ($path) {
        case '/dashboard':
            if ($method === 'GET') {
                handleDashboard();
            }
            break;
            
        case '/ai-providers':
            if ($method === 'GET') {
                handleGetAIProviders();
            } elseif ($method === 'POST') {
                handleAddAIProvider();
            }
            break;
            
        case (preg_match('/^\/ai-providers\/(\d+)$/', $path, $matches) ? true : false):
            $providerId = $matches[1];
            if ($method === 'DELETE') {
                handleDeleteAIProvider($providerId);
            } elseif ($method === 'PUT') {
                handleUpdateAIProvider($providerId);
            }
            break;
            
        case (preg_match('/^\/ai-providers\/(\d+)\/toggle$/', $path, $matches) ? true : false):
            $providerId = $matches[1];
            if ($method === 'POST') {
                handleToggleAIProvider($providerId);
            }
            break;
            
        case (preg_match('/^\/ai-providers\/(\d+)\/test$/', $path, $matches) ? true : false):
            $providerId = $matches[1];
            if ($method === 'POST') {
                handleTestAIProvider($providerId);
            }
            break;
            
        case '/email-accounts':
            if ($method === 'GET') {
                handleGetEmailAccounts();
            } elseif ($method === 'POST') {
                handleAddEmailAccount();
            }
            break;
            
        case (preg_match('/^\/email-accounts\/(\d+)$/', $path, $matches) ? true : false):
            $accountId = $matches[1];
            if ($method === 'DELETE') {
                handleDeleteEmailAccount($accountId);
            } elseif ($method === 'PUT') {
                handleUpdateEmailAccount($accountId);
            }
            break;
            
        case (preg_match('/^\/email-accounts\/(\d+)\/sync$/', $path, $matches) ? true : false):
            $accountId = $matches[1];
            if ($method === 'POST') {
                handleSyncEmailAccount($accountId);
            }
            break;
            
        case '/emails':
            if ($method === 'GET') {
                handleGetEmails();
            }
            break;
            
        case '/emails/sync-all':
            if ($method === 'POST') {
                handleSyncAllEmails();
            }
            break;
            
        case '/emails/process':
            if ($method === 'POST') {
                handleProcessEmails();
            }
            break;
            
        case '/settings':
            if ($method === 'GET') {
                handleGetSettings();
            } elseif ($method === 'POST') {
                handleUpdateSettings();
            }
            break;
            
        case '/analytics':
            if ($method === 'GET') {
                handleGetAnalytics();
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}

// API Handler Functions

function handleDashboard() {
    $userId = requireAuth();
    global $db;
    
    // Get dashboard statistics
    $totalEmails = $db->fetchOne("SELECT COUNT(*) as count FROM emails WHERE user_id = ?", [$userId])['count'];
    $activeAIProviders = $db->fetchOne("SELECT COUNT(*) as count FROM ai_providers WHERE user_id = ? AND is_enabled = 1", [$userId])['count'];
    $connectedAccounts = $db->fetchOne("SELECT COUNT(*) as count FROM email_accounts WHERE user_id = ? AND status = 'active'", [$userId])['count'];
    
    // Calculate processing accuracy
    $accuracyData = $db->fetchOne("SELECT AVG(ai_confidence) as avg_confidence FROM emails WHERE user_id = ? AND ai_confidence > 0", [$userId]);
    $processingAccuracy = round($accuracyData['avg_confidence'] ?? 0);
    
    // Get recent emails
    $recentEmails = $db->fetchAll("
        SELECT sender_name, sender_email, subject, 
               SUBSTRING(body_text, 1, 100) as preview, 
               category, received_at 
        FROM emails 
        WHERE user_id = ? 
        ORDER BY received_at DESC 
        LIMIT 5
    ", [$userId]);
    
    // Get AI provider status
    $aiStatus = $db->fetchAll("
        SELECT provider_name as name, 
               CASE WHEN is_enabled = 1 THEN 'active' ELSE 'inactive' END as status
        FROM ai_providers 
        WHERE user_id = ?
    ", [$userId]);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_emails' => $totalEmails,
            'active_ai_providers' => $activeAIProviders,
            'connected_accounts' => $connectedAccounts,
            'processing_accuracy' => $processingAccuracy
        ],
        'recent_emails' => $recentEmails,
        'ai_status' => $aiStatus
    ]);
}

function handleGetAIProviders() {
    $userId = requireAuth();
    global $db;
    
    $providers = $db->fetchAll("
        SELECT id, provider_name, model_name, is_enabled, 
               total_cost, total_requests, accuracy, status, created_at
        FROM ai_providers 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ", [$userId]);
    
    echo json_encode(['success' => true, 'providers' => $providers]);
}

function handleAddAIProvider() {
    $userId = requireAuth();
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $providerName = $data['provider_name'] ?? '';
    $modelName = $data['model_name'] ?? '';
    $apiKey = $data['api_key'] ?? '';
    $isEnabled = isset($data['is_enabled']) ? 1 : 0;
    
    if (empty($providerName) || empty($modelName) || empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Test the API key
    $aiEnsemble = new MultiAIEnsemble();
    $testResult = $aiEnsemble->testProvider($providerName, $modelName, $apiKey);
    
    if (!$testResult['success']) {
        echo json_encode(['success' => false, 'message' => 'API key test failed: ' . $testResult['error']]);
        return;
    }
    
    // Encrypt API key
    $encryptedApiKey = $db->encrypt($apiKey);
    
    $providerId = $db->insert('ai_providers', [
        'user_id' => $userId,
        'provider_name' => $providerName,
        'model_name' => $modelName,
        'api_key' => $encryptedApiKey,
        'is_enabled' => $isEnabled,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if ($providerId) {
        $db->logActivity($userId, 'ai_provider_added', "Added {$providerName} provider");
        echo json_encode(['success' => true, 'provider_id' => $providerId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add AI provider']);
    }
}

function handleToggleAIProvider($providerId) {
    $userId = requireAuth();
    global $db;
    
    $provider = $db->fetchOne("SELECT * FROM ai_providers WHERE id = ? AND user_id = ?", [$providerId, $userId]);
    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Provider not found']);
        return;
    }
    
    $newStatus = $provider['is_enabled'] ? 0 : 1;
    
    $updated = $db->update('ai_providers', 
        ['is_enabled' => $newStatus], 
        'id = ? AND user_id = ?', 
        [$providerId, $userId]
    );
    
    if ($updated) {
        $action = $newStatus ? 'enabled' : 'disabled';
        $db->logActivity($userId, 'ai_provider_toggled', "Provider {$provider['provider_name']} {$action}");
        echo json_encode(['success' => true, 'enabled' => (bool)$newStatus]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update provider']);
    }
}

function handleTestAIProvider($providerId) {
    $userId = requireAuth();
    global $db;
    
    $provider = $db->fetchOne("SELECT * FROM ai_providers WHERE id = ? AND user_id = ?", [$providerId, $userId]);
    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Provider not found']);
        return;
    }
    
    $apiKey = $db->decrypt($provider['api_key']);
    $aiEnsemble = new MultiAIEnsemble();
    
    $startTime = microtime(true);
    $testResult = $aiEnsemble->testProvider($provider['provider_name'], $provider['model_name'], $apiKey);
    $responseTime = round((microtime(true) - $startTime) * 1000);
    
    if ($testResult['success']) {
        echo json_encode([
            'success' => true, 
            'response_time' => $responseTime,
            'message' => 'Test successful'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => $testResult['error']
        ]);
    }
}

function handleDeleteAIProvider($providerId) {
    $userId = requireAuth();
    global $db;
    
    $provider = $db->fetchOne("SELECT provider_name FROM ai_providers WHERE id = ? AND user_id = ?", [$providerId, $userId]);
    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Provider not found']);
        return;
    }
    
    $deleted = $db->delete('ai_providers', 'id = ? AND user_id = ?', [$providerId, $userId]);
    
    if ($deleted) {
        $db->logActivity($userId, 'ai_provider_deleted', "Deleted {$provider['provider_name']} provider");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete provider']);
    }
}

function handleGetEmailAccounts() {
    $userId = requireAuth();
    global $db;
    
    $accounts = $db->fetchAll("
        SELECT id, email_address, provider_type, status, last_sync, 
               (SELECT COUNT(*) FROM emails WHERE email_account_id = email_accounts.id) as email_count,
               created_at
        FROM email_accounts 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ", [$userId]);
    
    echo json_encode(['success' => true, 'accounts' => $accounts]);
}

function handleAddEmailAccount() {
    $userId = requireAuth();
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $emailAddress = $data['email_address'] ?? '';
    $providerType = $data['provider_type'] ?? '';
    $password = $data['password'] ?? '';
    $apiKey = $data['api_key'] ?? '';
    
    if (empty($emailAddress) || empty($providerType)) {
        echo json_encode(['success' => false, 'message' => 'Email address and provider type are required']);
        return;
    }
    
    // Check if account already exists
    $existing = $db->fetchOne("SELECT id FROM email_accounts WHERE email_address = ? AND user_id = ?", [$emailAddress, $userId]);
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Email account already exists']);
        return;
    }
    
    // Test connection
    $emailProvider = new EmailProvider();
    $testResult = $emailProvider->testConnection($providerType, $emailAddress, $password, $apiKey, $data);
    
    if (!$testResult['success']) {
        echo json_encode(['success' => false, 'message' => 'Connection test failed: ' . $testResult['error']]);
        return;
    }
    
    // Encrypt credentials
    $encryptedPassword = $password ? $db->encrypt($password) : null;
    $encryptedApiKey = $apiKey ? $db->encrypt($apiKey) : null;
    
    $accountData = [
        'user_id' => $userId,
        'email_address' => $emailAddress,
        'provider_type' => $providerType,
        'encrypted_password' => $encryptedPassword,
        'encrypted_api_key' => $encryptedApiKey,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Add provider-specific settings
    if (isset($data['imap_host'])) $accountData['imap_host'] = $data['imap_host'];
    if (isset($data['imap_port'])) $accountData['imap_port'] = $data['imap_port'];
    if (isset($data['smtp_host'])) $accountData['smtp_host'] = $data['smtp_host'];
    if (isset($data['smtp_port'])) $accountData['smtp_port'] = $data['smtp_port'];
    if (isset($data['use_ssl'])) $accountData['use_ssl'] = $data['use_ssl'] ? 1 : 0;
    
    $accountId = $db->insert('email_accounts', $accountData);
    
    if ($accountId) {
        $db->logActivity($userId, 'email_account_added', "Added email account {$emailAddress}");
        echo json_encode(['success' => true, 'account_id' => $accountId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add email account']);
    }
}

function handleSyncEmailAccount($accountId) {
    $userId = requireAuth();
    global $db;
    
    $account = $db->fetchOne("SELECT * FROM email_accounts WHERE id = ? AND user_id = ?", [$accountId, $userId]);
    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        return;
    }
    
    $emailProvider = new EmailProvider();
    $syncResult = $emailProvider->syncEmails($account);
    
    if ($syncResult['success']) {
        // Update last sync time
        $db->update('email_accounts', 
            ['last_sync' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$accountId]
        );
        
        $db->logActivity($userId, 'email_sync', "Synced {$syncResult['new_emails']} new emails from {$account['email_address']}");
        echo json_encode(['success' => true, 'new_emails' => $syncResult['new_emails']]);
    } else {
        echo json_encode(['success' => false, 'message' => $syncResult['error']]);
    }
}

function handleDeleteEmailAccount($accountId) {
    $userId = requireAuth();
    global $db;
    
    $account = $db->fetchOne("SELECT email_address FROM email_accounts WHERE id = ? AND user_id = ?", [$accountId, $userId]);
    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        return;
    }
    
    // Delete associated emails first
    $db->delete('emails', 'email_account_id = ?', [$accountId]);
    
    // Delete the account
    $deleted = $db->delete('email_accounts', 'id = ? AND user_id = ?', [$accountId, $userId]);
    
    if ($deleted) {
        $db->logActivity($userId, 'email_account_deleted', "Deleted email account {$account['email_address']}");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete account']);
    }
}

function handleGetEmails() {
    $userId = requireAuth();
    global $db;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;
    
    $emails = $db->fetchAll("
        SELECT e.*, ea.email_address as account_email
        FROM emails e
        JOIN email_accounts ea ON e.email_account_id = ea.id
        WHERE e.user_id = ?
        ORDER BY e.received_at DESC
        LIMIT ? OFFSET ?
    ", [$userId, $limit, $offset]);
    
    $totalCount = $db->fetchOne("SELECT COUNT(*) as count FROM emails WHERE user_id = ?", [$userId])['count'];
    
    echo json_encode([
        'success' => true, 
        'emails' => $emails,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'pages' => ceil($totalCount / $limit)
        ]
    ]);
}

function handleSyncAllEmails() {
    $userId = requireAuth();
    global $db;
    
    $accounts = $db->fetchAll("SELECT * FROM email_accounts WHERE user_id = ? AND status = 'active'", [$userId]);
    
    $totalNewEmails = 0;
    $emailProvider = new EmailProvider();
    
    foreach ($accounts as $account) {
        $syncResult = $emailProvider->syncEmails($account);
        if ($syncResult['success']) {
            $totalNewEmails += $syncResult['new_emails'];
            
            // Update last sync time
            $db->update('email_accounts', 
                ['last_sync' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$account['id']]
            );
        }
    }
    
    $db->logActivity($userId, 'email_sync_all', "Synced {$totalNewEmails} new emails from all accounts");
    echo json_encode(['success' => true, 'total_new_emails' => $totalNewEmails]);
}

function handleProcessEmails() {
    $userId = requireAuth();
    global $db;
    
    // Get unprocessed emails
    $emails = $db->fetchAll("
        SELECT * FROM emails 
        WHERE user_id = ? AND (category IS NULL OR category = '') 
        ORDER BY received_at DESC 
        LIMIT 50
    ", [$userId]);
    
    if (empty($emails)) {
        echo json_encode(['success' => true, 'processed_emails' => 0, 'message' => 'No emails to process']);
        return;
    }
    
    // Get active AI providers
    $providers = $db->fetchAll("SELECT * FROM ai_providers WHERE user_id = ? AND is_enabled = 1", [$userId]);
    
    if (empty($providers)) {
        echo json_encode(['success' => false, 'message' => 'No active AI providers configured']);
        return;
    }
    
    $aiEnsemble = new MultiAIEnsemble();
    $processedCount = 0;
    
    foreach ($emails as $email) {
        $analysisResult = $aiEnsemble->analyzeEmail($email, $providers, $db);
        
        if ($analysisResult['success']) {
            // Update email with AI analysis
            $db->update('emails', [
                'category' => $analysisResult['category'],
                'priority' => $analysisResult['priority'],
                'sentiment' => $analysisResult['sentiment'],
                'ai_confidence' => $analysisResult['confidence'],
                'ai_summary' => $analysisResult['summary'],
                'processed_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$email['id']]);
            
            $processedCount++;
        }
    }
    
    $db->logActivity($userId, 'email_processing', "Processed {$processedCount} emails with AI");
    echo json_encode(['success' => true, 'processed_emails' => $processedCount]);
}

function handleGetSettings() {
    $userId = requireAuth();
    global $db;
    
    $settings = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE user_id = ? OR user_id IS NULL", [$userId]);
    
    $settingsArray = [];
    foreach ($settings as $setting) {
        $settingsArray[$setting['setting_key']] = $setting['setting_value'];
    }
    
    echo json_encode(['success' => true, 'settings' => $settingsArray]);
}

function handleUpdateSettings() {
    $userId = requireAuth();
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $setting = $data['setting'] ?? '';
    $value = $data['value'] ?? '';
    
    if (empty($setting)) {
        echo json_encode(['success' => false, 'message' => 'Setting key is required']);
        return;
    }
    
    // Check if setting exists
    $existing = $db->fetchOne("SELECT id FROM settings WHERE setting_key = ? AND (user_id = ? OR user_id IS NULL)", [$setting, $userId]);
    
    if ($existing) {
        $updated = $db->update('settings', 
            ['setting_value' => $value], 
            'setting_key = ? AND (user_id = ? OR user_id IS NULL)', 
            [$setting, $userId]
        );
    } else {
        $updated = $db->insert('settings', [
            'user_id' => $userId,
            'setting_key' => $setting,
            'setting_value' => $value,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    if ($updated) {
        $db->logActivity($userId, 'setting_updated', "Updated setting {$setting}");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update setting']);
    }
}

function handleGetAnalytics() {
    $userId = requireAuth();
    global $db;
    
    // Email processing analytics
    $emailStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_emails,
            COUNT(CASE WHEN category IS NOT NULL THEN 1 END) as processed_emails,
            AVG(ai_confidence) as avg_confidence,
            COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority,
            COUNT(CASE WHEN priority = 'medium' THEN 1 END) as medium_priority,
            COUNT(CASE WHEN priority = 'low' THEN 1 END) as low_priority
        FROM emails 
        WHERE user_id = ?
    ", [$userId]);
    
    // Category distribution
    $categoryStats = $db->fetchAll("
        SELECT category, COUNT(*) as count 
        FROM emails 
        WHERE user_id = ? AND category IS NOT NULL 
        GROUP BY category
    ", [$userId]);
    
    // AI provider performance
    $providerStats = $db->fetchAll("
        SELECT provider_name, total_requests, total_cost, accuracy 
        FROM ai_providers 
        WHERE user_id = ?
    ", [$userId]);
    
    // Daily email volume (last 30 days)
    $dailyVolume = $db->fetchAll("
        SELECT DATE(received_at) as date, COUNT(*) as count 
        FROM emails 
        WHERE user_id = ? AND received_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(received_at)
        ORDER BY date
    ", [$userId]);
    
    echo json_encode([
        'success' => true,
        'analytics' => [
            'email_stats' => $emailStats,
            'category_distribution' => $categoryStats,
            'provider_performance' => $providerStats,
            'daily_volume' => $dailyVolume
        ]
    ]);
}
?>

