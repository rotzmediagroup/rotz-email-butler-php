<?php
/**
 * ROTZ Email Butler - Prometheus Metrics Endpoint
 * Comprehensive application metrics for monitoring
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = new Database();
    $metrics = [];
    
    // Application info
    $metrics[] = '# HELP rotz_app_info Application information';
    $metrics[] = '# TYPE rotz_app_info gauge';
    $metrics[] = 'rotz_app_info{version="1.0.0",environment="' . ($_ENV['PHP_ENV'] ?? 'production') . '"} 1';
    
    // Database metrics
    $metrics[] = '# HELP rotz_database_connections_total Total database connections';
    $metrics[] = '# TYPE rotz_database_connections_total counter';
    
    $dbStats = $db->query("SHOW STATUS LIKE 'Connections'")->fetch();
    $metrics[] = 'rotz_database_connections_total ' . ($dbStats['Value'] ?? 0);
    
    // User metrics
    $metrics[] = '# HELP rotz_users_total Total number of users';
    $metrics[] = '# TYPE rotz_users_total gauge';
    
    $userCount = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch();
    $metrics[] = 'rotz_users_total ' . ($userCount['count'] ?? 0);
    
    $metrics[] = '# HELP rotz_users_active_24h Active users in last 24 hours';
    $metrics[] = '# TYPE rotz_users_active_24h gauge';
    
    $activeUsers = $db->query("SELECT COUNT(DISTINCT user_id) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch();
    $metrics[] = 'rotz_users_active_24h ' . ($activeUsers['count'] ?? 0);
    
    // Email metrics
    $metrics[] = '# HELP rotz_emails_total Total number of emails';
    $metrics[] = '# TYPE rotz_emails_total gauge';
    
    $emailCount = $db->query("SELECT COUNT(*) as count FROM emails")->fetch();
    $metrics[] = 'rotz_emails_total ' . ($emailCount['count'] ?? 0);
    
    $metrics[] = '# HELP rotz_emails_processed_total Total processed emails';
    $metrics[] = '# TYPE rotz_emails_processed_total gauge';
    
    $processedEmails = $db->query("SELECT COUNT(*) as count FROM emails WHERE category IS NOT NULL")->fetch();
    $metrics[] = 'rotz_emails_processed_total ' . ($processedEmails['count'] ?? 0);
    
    $metrics[] = '# HELP rotz_emails_unprocessed_total Total unprocessed emails';
    $metrics[] = '# TYPE rotz_emails_unprocessed_total gauge';
    
    $unprocessedEmails = $db->query("SELECT COUNT(*) as count FROM emails WHERE category IS NULL")->fetch();
    $metrics[] = 'rotz_emails_unprocessed_total ' . ($unprocessedEmails['count'] ?? 0);
    
    // Email categories
    $categories = $db->query("SELECT category, COUNT(*) as count FROM emails WHERE category IS NOT NULL GROUP BY category")->fetchAll();
    $metrics[] = '# HELP rotz_emails_by_category_total Emails by category';
    $metrics[] = '# TYPE rotz_emails_by_category_total gauge';
    
    foreach ($categories as $category) {
        $metrics[] = 'rotz_emails_by_category_total{category="' . $category['category'] . '"} ' . $category['count'];
    }
    
    // Email priorities
    $priorities = $db->query("SELECT priority, COUNT(*) as count FROM emails WHERE priority IS NOT NULL GROUP BY priority")->fetchAll();
    $metrics[] = '# HELP rotz_emails_by_priority_total Emails by priority';
    $metrics[] = '# TYPE rotz_emails_by_priority_total gauge';
    
    foreach ($priorities as $priority) {
        $metrics[] = 'rotz_emails_by_priority_total{priority="' . $priority['priority'] . '"} ' . $priority['count'];
    }
    
    // AI Provider metrics
    $metrics[] = '# HELP rotz_ai_providers_total Total AI providers';
    $metrics[] = '# TYPE rotz_ai_providers_total gauge';
    
    $aiProviderCount = $db->query("SELECT COUNT(*) as count FROM ai_providers")->fetch();
    $metrics[] = 'rotz_ai_providers_total ' . ($aiProviderCount['count'] ?? 0);
    
    $metrics[] = '# HELP rotz_ai_providers_enabled_total Enabled AI providers';
    $metrics[] = '# TYPE rotz_ai_providers_enabled_total gauge';
    
    $enabledProviders = $db->query("SELECT COUNT(*) as count FROM ai_providers WHERE is_enabled = 1")->fetch();
    $metrics[] = 'rotz_ai_providers_enabled_total ' . ($enabledProviders['count'] ?? 0);
    
    // AI Provider performance
    $providerStats = $db->query("
        SELECT 
            provider_name,
            COUNT(*) as total_requests,
            AVG(confidence_score) as avg_confidence,
            AVG(response_time_ms) as avg_response_time,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_requests,
            SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_requests
        FROM ai_processing_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY provider_name
    ")->fetchAll();
    
    $metrics[] = '# HELP rotz_ai_requests_total_24h AI requests in last 24 hours';
    $metrics[] = '# TYPE rotz_ai_requests_total_24h gauge';
    
    $metrics[] = '# HELP rotz_ai_requests_successful_24h Successful AI requests in last 24 hours';
    $metrics[] = '# TYPE rotz_ai_requests_successful_24h gauge';
    
    $metrics[] = '# HELP rotz_ai_requests_failed_24h Failed AI requests in last 24 hours';
    $metrics[] = '# TYPE rotz_ai_requests_failed_24h gauge';
    
    $metrics[] = '# HELP rotz_ai_confidence_avg_24h Average AI confidence in last 24 hours';
    $metrics[] = '# TYPE rotz_ai_confidence_avg_24h gauge';
    
    $metrics[] = '# HELP rotz_ai_response_time_avg_24h Average AI response time in last 24 hours (ms)';
    $metrics[] = '# TYPE rotz_ai_response_time_avg_24h gauge';
    
    foreach ($providerStats as $stat) {
        $provider = $stat['provider_name'];
        $metrics[] = 'rotz_ai_requests_total_24h{provider="' . $provider . '"} ' . $stat['total_requests'];
        $metrics[] = 'rotz_ai_requests_successful_24h{provider="' . $provider . '"} ' . $stat['successful_requests'];
        $metrics[] = 'rotz_ai_requests_failed_24h{provider="' . $provider . '"} ' . $stat['failed_requests'];
        $metrics[] = 'rotz_ai_confidence_avg_24h{provider="' . $provider . '"} ' . round($stat['avg_confidence'], 2);
        $metrics[] = 'rotz_ai_response_time_avg_24h{provider="' . $provider . '"} ' . round($stat['avg_response_time'], 2);
    }
    
    // Email Account metrics
    $metrics[] = '# HELP rotz_email_accounts_total Total email accounts';
    $metrics[] = '# TYPE rotz_email_accounts_total gauge';
    
    $accountCount = $db->query("SELECT COUNT(*) as count FROM email_accounts")->fetch();
    $metrics[] = 'rotz_email_accounts_total ' . ($accountCount['count'] ?? 0);
    
    $metrics[] = '# HELP rotz_email_accounts_active_total Active email accounts';
    $metrics[] = '# TYPE rotz_email_accounts_active_total gauge';
    
    $activeAccounts = $db->query("SELECT COUNT(*) as count FROM email_accounts WHERE status = 'active'")->fetch();
    $metrics[] = 'rotz_email_accounts_active_total ' . ($activeAccounts['count'] ?? 0);
    
    // Email Account by provider
    $accountProviders = $db->query("SELECT provider, COUNT(*) as count FROM email_accounts GROUP BY provider")->fetchAll();
    $metrics[] = '# HELP rotz_email_accounts_by_provider_total Email accounts by provider';
    $metrics[] = '# TYPE rotz_email_accounts_by_provider_total gauge';
    
    foreach ($accountProviders as $provider) {
        $metrics[] = 'rotz_email_accounts_by_provider_total{provider="' . $provider['provider'] . '"} ' . $provider['count'];
    }
    
    // Sync metrics
    $metrics[] = '# HELP rotz_sync_operations_total_24h Sync operations in last 24 hours';
    $metrics[] = '# TYPE rotz_sync_operations_total_24h gauge';
    
    $syncCount = $db->query("SELECT COUNT(*) as count FROM sync_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch();
    $metrics[] = 'rotz_sync_operations_total_24h ' . ($syncCount['count'] ?? 0);
    
    $metrics[] = '# HELP rotz_sync_operations_successful_24h Successful sync operations in last 24 hours';
    $metrics[] = '# TYPE rotz_sync_operations_successful_24h gauge';
    
    $successfulSyncs = $db->query("SELECT COUNT(*) as count FROM sync_logs WHERE status = 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch();
    $metrics[] = 'rotz_sync_operations_successful_24h ' . ($successfulSyncs['count'] ?? 0);
    
    $metrics[] = '# HELP rotz_sync_operations_failed_24h Failed sync operations in last 24 hours';
    $metrics[] = '# TYPE rotz_sync_operations_failed_24h gauge';
    
    $failedSyncs = $db->query("SELECT COUNT(*) as count FROM sync_logs WHERE status = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch();
    $metrics[] = 'rotz_sync_operations_failed_24h ' . ($failedSyncs['count'] ?? 0);
    
    // System metrics
    $metrics[] = '# HELP rotz_system_uptime_seconds System uptime in seconds';
    $metrics[] = '# TYPE rotz_system_uptime_seconds gauge';
    
    $uptime = file_get_contents('/proc/uptime');
    $uptimeSeconds = floatval(explode(' ', $uptime)[0]);
    $metrics[] = 'rotz_system_uptime_seconds ' . $uptimeSeconds;
    
    // Memory usage
    $metrics[] = '# HELP rotz_memory_usage_bytes Memory usage in bytes';
    $metrics[] = '# TYPE rotz_memory_usage_bytes gauge';
    
    $memoryUsage = memory_get_usage(true);
    $metrics[] = 'rotz_memory_usage_bytes ' . $memoryUsage;
    
    $metrics[] = '# HELP rotz_memory_peak_usage_bytes Peak memory usage in bytes';
    $metrics[] = '# TYPE rotz_memory_peak_usage_bytes gauge';
    
    $peakMemoryUsage = memory_get_peak_usage(true);
    $metrics[] = 'rotz_memory_peak_usage_bytes ' . $peakMemoryUsage;
    
    // Response time metrics
    $metrics[] = '# HELP rotz_response_time_seconds Response time for this request';
    $metrics[] = '# TYPE rotz_response_time_seconds gauge';
    
    $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
    $responseTime = microtime(true) - $startTime;
    $metrics[] = 'rotz_response_time_seconds ' . $responseTime;
    
    // Queue metrics (if using Redis for queues)
    try {
        $redis = new Redis();
        $redis->connect('redis', 6379);
        
        $metrics[] = '# HELP rotz_queue_size Queue size';
        $metrics[] = '# TYPE rotz_queue_size gauge';
        
        $queueSize = $redis->lLen('email_processing_queue');
        $metrics[] = 'rotz_queue_size{queue="email_processing"} ' . $queueSize;
        
        $redis->close();
    } catch (Exception $e) {
        // Redis not available, skip queue metrics
    }
    
    // Output all metrics
    echo implode("\n", $metrics) . "\n";
    
} catch (Exception $e) {
    http_response_code(500);
    echo "# Error generating metrics: " . $e->getMessage() . "\n";
}
?>

