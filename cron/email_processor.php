<?php
/**
 * ROTZ Email Butler - Automated Email Processor
 * 
 * This script runs as a cron job to automatically sync and process emails
 * Run this script every 15 minutes: */15 * * * * /usr/bin/php /path/to/email_processor.php
 */

// Prevent web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line');
}

// Load configuration
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/MultiAIEnsemble.php';
require_once __DIR__ . '/../classes/EmailProvider.php';

use Rotz\EmailButler\Classes\Database;
use Rotz\EmailButler\Classes\MultiAIEnsemble;
use Rotz\EmailButler\Classes\EmailProvider;

// Initialize database
try {
    $db = Database::getInstance();
    echo "[" . date('Y-m-d H:i:s') . "] Email processor started\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if auto-processing is enabled
if (!getConfig('AUTO_PROCESS_EMAILS', false)) {
    echo "[" . date('Y-m-d H:i:s') . "] Auto-processing is disabled\n";
    exit(0);
}

// Get all active email accounts
$accounts = $db->fetchAll("
    SELECT ea.*, u.id as user_id 
    FROM email_accounts ea 
    JOIN users u ON ea.user_id = u.id 
    WHERE ea.status = 'active' 
    AND u.status = 'active'
");

if (empty($accounts)) {
    echo "[" . date('Y-m-d H:i:s') . "] No active email accounts found\n";
    exit(0);
}

$emailProvider = new EmailProvider();
$aiEnsemble = new MultiAIEnsemble();
$totalSynced = 0;
$totalProcessed = 0;

foreach ($accounts as $account) {
    echo "[" . date('Y-m-d H:i:s') . "] Processing account: {$account['email_address']}\n";
    
    try {
        // Check if it's time to sync this account
        $lastSync = strtotime($account['last_sync'] ?? '1970-01-01');
        $syncInterval = getConfig('SYNC_INTERVAL_MINUTES', 15) * 60;
        
        if ((time() - $lastSync) < $syncInterval) {
            echo "[" . date('Y-m-d H:i:s') . "] Skipping {$account['email_address']} - synced recently\n";
            continue;
        }
        
        // Sync emails
        $syncResult = $emailProvider->syncEmails($account);
        
        if ($syncResult['success']) {
            $newEmails = $syncResult['new_emails'];
            $totalSynced += $newEmails;
            
            echo "[" . date('Y-m-d H:i:s') . "] Synced {$newEmails} new emails from {$account['email_address']}\n";
            
            // Update last sync time
            $db->update('email_accounts', 
                ['last_sync' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$account['id']]
            );
            
            // Process new emails with AI if enabled
            if (getConfig('ENABLE_SMART_CATEGORIZATION', true) && $newEmails > 0) {
                $processedCount = processUserEmails($account['user_id'], $db, $aiEnsemble);
                $totalProcessed += $processedCount;
                echo "[" . date('Y-m-d H:i:s') . "] Processed {$processedCount} emails with AI for user {$account['user_id']}\n";
            }
            
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] Sync failed for {$account['email_address']}: {$syncResult['error']}\n";
            
            // Update account status if there are repeated failures
            $failureCount = $account['failure_count'] ?? 0;
            $failureCount++;
            
            if ($failureCount >= 5) {
                $db->update('email_accounts', 
                    ['status' => 'error', 'failure_count' => $failureCount], 
                    'id = ?', 
                    [$account['id']]
                );
                echo "[" . date('Y-m-d H:i:s') . "] Account {$account['email_address']} marked as error after {$failureCount} failures\n";
            } else {
                $db->update('email_accounts', 
                    ['failure_count' => $failureCount], 
                    'id = ?', 
                    [$account['id']]
                );
            }
        }
        
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error processing {$account['email_address']}: " . $e->getMessage() . "\n";
        logMessage('ERROR', "Email processing error for {$account['email_address']}: " . $e->getMessage());
    }
    
    // Small delay to prevent overwhelming servers
    sleep(1);
}

// Process any remaining unprocessed emails
if (getConfig('ENABLE_SMART_CATEGORIZATION', true)) {
    $users = $db->fetchAll("SELECT DISTINCT user_id FROM emails WHERE category IS NULL OR category = ''");
    
    foreach ($users as $user) {
        try {
            $processedCount = processUserEmails($user['user_id'], $db, $aiEnsemble);
            if ($processedCount > 0) {
                $totalProcessed += $processedCount;
                echo "[" . date('Y-m-d H:i:s') . "] Processed {$processedCount} remaining emails for user {$user['user_id']}\n";
            }
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error processing emails for user {$user['user_id']}: " . $e->getMessage() . "\n";
        }
    }
}

// Clean up old processed emails if configured
cleanupOldEmails($db);

// Generate summary
echo "[" . date('Y-m-d H:i:s') . "] Email processor completed\n";
echo "[" . date('Y-m-d H:i:s') . "] Total emails synced: {$totalSynced}\n";
echo "[" . date('Y-m-d H:i:s') . "] Total emails processed: {$totalProcessed}\n";

// Log activity
logMessage('INFO', "Email processor completed", [
    'synced' => $totalSynced,
    'processed' => $totalProcessed,
    'accounts' => count($accounts)
]);

/**
 * Process emails for a specific user with AI analysis
 */
function processUserEmails($userId, $db, $aiEnsemble) {
    // Get active AI providers for this user
    $providers = $db->fetchAll("
        SELECT * FROM ai_providers 
        WHERE user_id = ? AND is_enabled = 1 
        ORDER BY created_at ASC
    ", [$userId]);
    
    if (empty($providers)) {
        return 0;
    }
    
    // Get unprocessed emails for this user
    $emails = $db->fetchAll("
        SELECT * FROM emails 
        WHERE user_id = ? AND (category IS NULL OR category = '') 
        ORDER BY received_at DESC 
        LIMIT ?
    ", [$userId, getConfig('MAX_EMAILS_PER_SYNC', 50)]);
    
    if (empty($emails)) {
        return 0;
    }
    
    $processedCount = 0;
    
    foreach ($emails as $email) {
        try {
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
                
                // Check if follow-up is needed
                if (getConfig('ENABLE_FOLLOW_UP_SUGGESTIONS', true) && $analysisResult['needs_followup']) {
                    createFollowUpReminder($email, $analysisResult, $db);
                }
                
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] AI analysis failed for email {$email['id']}: {$analysisResult['error']}\n";
            }
            
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error analyzing email {$email['id']}: " . $e->getMessage() . "\n";
        }
        
        // Small delay to prevent API rate limiting
        usleep(500000); // 0.5 seconds
    }
    
    return $processedCount;
}

/**
 * Create follow-up reminder for important emails
 */
function createFollowUpReminder($email, $analysisResult, $db) {
    // Calculate follow-up date based on priority
    $followUpDays = 7; // Default
    
    switch ($analysisResult['priority']) {
        case 'high':
            $followUpDays = 1;
            break;
        case 'medium':
            $followUpDays = 3;
            break;
        case 'low':
            $followUpDays = 7;
            break;
    }
    
    $followUpDate = date('Y-m-d H:i:s', strtotime("+{$followUpDays} days"));
    
    $db->insert('follow_ups', [
        'user_id' => $email['user_id'],
        'email_id' => $email['id'],
        'follow_up_date' => $followUpDate,
        'priority' => $analysisResult['priority'],
        'reason' => $analysisResult['followup_reason'] ?? 'AI suggested follow-up',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Clean up old emails based on retention policy
 */
function cleanupOldEmails($db) {
    $retentionDays = getConfig('EMAIL_RETENTION_DAYS', 365); // Default 1 year
    
    if ($retentionDays <= 0) {
        return; // No cleanup if retention is disabled
    }
    
    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
    
    // Delete old emails
    $deletedCount = $db->delete('emails', 'received_at < ?', [$cutoffDate]);
    
    if ($deletedCount > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Cleaned up {$deletedCount} old emails\n";
        logMessage('INFO', "Cleaned up {$deletedCount} old emails older than {$retentionDays} days");
    }
}

/**
 * Send notification emails for important events
 */
function sendNotificationEmail($userId, $subject, $message, $db) {
    if (!getConfig('ENABLE_EMAIL_NOTIFICATIONS', false)) {
        return;
    }
    
    $user = $db->fetchOne("SELECT email, display_name FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        return;
    }
    
    // Simple email notification (you can enhance this with proper email templates)
    $headers = [
        'From: ' . getConfig('APP_NAME', 'ROTZ Email Butler') . ' <noreply@' . parse_url(getConfig('APP_URL', 'localhost'), PHP_URL_HOST) . '>',
        'Reply-To: noreply@' . parse_url(getConfig('APP_URL', 'localhost'), PHP_URL_HOST),
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: ROTZ Email Butler'
    ];
    
    $body = "
    <html>
    <body>
        <h2>{$subject}</h2>
        <p>Hello {$user['display_name']},</p>
        <p>{$message}</p>
        <p>Best regards,<br>ROTZ Email Butler</p>
    </body>
    </html>
    ";
    
    mail($user['email'], $subject, $body, implode("\r\n", $headers));
}

exit(0);
?>

