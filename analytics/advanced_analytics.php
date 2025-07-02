<?php
/**
 * ROTZ Email Butler - Advanced Analytics & Business Intelligence
 * Comprehensive analytics system with real-time insights and predictive analytics
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Database.php';

class AdvancedAnalytics {
    private $db;
    private $redis;
    private $cache_ttl = 3600; // 1 hour
    
    public function __construct() {
        $this->db = new Database();
        
        // Initialize Redis for caching
        try {
            $this->redis = new Redis();
            $this->redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', $_ENV['REDIS_PORT'] ?? 6379);
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->redis = null;
        }
    }
    
    /**
     * Get comprehensive dashboard analytics
     */
    public function getDashboardAnalytics($user_id = null, $date_range = '30d') {
        $cache_key = "dashboard_analytics:" . ($user_id ?? 'global') . ":" . $date_range;
        
        // Try cache first
        if ($this->redis) {
            $cached = $this->redis->get($cache_key);
            if ($cached) {
                return json_decode($cached, true);
            }
        }
        
        $analytics = [
            'overview' => $this->getOverviewMetrics($user_id, $date_range),
            'email_trends' => $this->getEmailTrends($user_id, $date_range),
            'ai_performance' => $this->getAIPerformanceMetrics($user_id, $date_range),
            'productivity_insights' => $this->getProductivityInsights($user_id, $date_range),
            'category_distribution' => $this->getCategoryDistribution($user_id, $date_range),
            'response_patterns' => $this->getResponsePatterns($user_id, $date_range),
            'sender_analysis' => $this->getSenderAnalysis($user_id, $date_range),
            'time_analysis' => $this->getTimeAnalysis($user_id, $date_range),
            'predictive_insights' => $this->getPredictiveInsights($user_id, $date_range)
        ];
        
        // Cache results
        if ($this->redis) {
            $this->redis->setex($cache_key, $this->cache_ttl, json_encode($analytics));
        }
        
        return $analytics;
    }
    
    /**
     * Get overview metrics
     */
    private function getOverviewMetrics($user_id, $date_range) {
        $where_clause = $this->buildWhereClause($user_id, $date_range);
        
        $query = "
            SELECT 
                COUNT(*) as total_emails,
                COUNT(CASE WHEN category IS NOT NULL THEN 1 END) as processed_emails,
                COUNT(CASE WHEN is_read = 1 THEN 1 END) as read_emails,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority_emails,
                COUNT(CASE WHEN is_archived = 1 THEN 1 END) as archived_emails,
                COUNT(CASE WHEN is_deleted = 1 THEN 1 END) as deleted_emails,
                AVG(CASE WHEN ai_confidence IS NOT NULL THEN ai_confidence END) as avg_ai_confidence,
                COUNT(DISTINCT sender) as unique_senders,
                COUNT(CASE WHEN has_attachments = 1 THEN 1 END) as emails_with_attachments
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause
        ";
        
        $result = $this->db->query($query)->fetch();
        
        // Calculate processing rate
        $processing_rate = $result['total_emails'] > 0 ? 
            ($result['processed_emails'] / $result['total_emails']) * 100 : 0;
        
        // Calculate read rate
        $read_rate = $result['total_emails'] > 0 ? 
            ($result['read_emails'] / $result['total_emails']) * 100 : 0;
        
        return [
            'total_emails' => (int)$result['total_emails'],
            'processed_emails' => (int)$result['processed_emails'],
            'read_emails' => (int)$result['read_emails'],
            'high_priority_emails' => (int)$result['high_priority_emails'],
            'archived_emails' => (int)$result['archived_emails'],
            'deleted_emails' => (int)$result['deleted_emails'],
            'processing_rate' => round($processing_rate, 2),
            'read_rate' => round($read_rate, 2),
            'avg_ai_confidence' => round($result['avg_ai_confidence'] ?? 0, 2),
            'unique_senders' => (int)$result['unique_senders'],
            'emails_with_attachments' => (int)$result['emails_with_attachments']
        ];
    }
    
    /**
     * Get email trends over time
     */
    private function getEmailTrends($user_id, $date_range) {
        $where_clause = $this->buildWhereClause($user_id, $date_range);
        $group_by = $this->getGroupByClause($date_range);
        
        $query = "
            SELECT 
                $group_by as period,
                COUNT(*) as total_emails,
                COUNT(CASE WHEN category IS NOT NULL THEN 1 END) as processed_emails,
                COUNT(CASE WHEN is_read = 1 THEN 1 END) as read_emails,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority_emails,
                AVG(CASE WHEN ai_confidence IS NOT NULL THEN ai_confidence END) as avg_confidence
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause
            GROUP BY period
            ORDER BY period
        ";
        
        $results = $this->db->query($query)->fetchAll();
        
        return array_map(function($row) {
            return [
                'period' => $row['period'],
                'total_emails' => (int)$row['total_emails'],
                'processed_emails' => (int)$row['processed_emails'],
                'read_emails' => (int)$row['read_emails'],
                'high_priority_emails' => (int)$row['high_priority_emails'],
                'avg_confidence' => round($row['avg_confidence'] ?? 0, 2)
            ];
        }, $results);
    }
    
    /**
     * Get AI performance metrics
     */
    private function getAIPerformanceMetrics($user_id, $date_range) {
        $where_clause = $this->buildWhereClause($user_id, $date_range, 'apl');
        
        $query = "
            SELECT 
                provider_name,
                COUNT(*) as total_requests,
                AVG(confidence_score) as avg_confidence,
                AVG(response_time_ms) as avg_response_time,
                COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_requests,
                COUNT(CASE WHEN status = 'error' THEN 1 END) as failed_requests,
                SUM(cost_usd) as total_cost
            FROM ai_processing_logs apl
            LEFT JOIN email_accounts ea ON apl.user_id = ea.user_id
            $where_clause
            GROUP BY provider_name
            ORDER BY total_requests DESC
        ";
        
        $results = $this->db->query($query)->fetchAll();
        
        return array_map(function($row) {
            $success_rate = $row['total_requests'] > 0 ? 
                ($row['successful_requests'] / $row['total_requests']) * 100 : 0;
            
            return [
                'provider_name' => $row['provider_name'],
                'total_requests' => (int)$row['total_requests'],
                'avg_confidence' => round($row['avg_confidence'] ?? 0, 2),
                'avg_response_time' => round($row['avg_response_time'] ?? 0, 2),
                'success_rate' => round($success_rate, 2),
                'successful_requests' => (int)$row['successful_requests'],
                'failed_requests' => (int)$row['failed_requests'],
                'total_cost' => round($row['total_cost'] ?? 0, 4)
            ];
        }, $results);
    }
    
    /**
     * Get productivity insights
     */
    private function getProductivityInsights($user_id, $date_range) {
        $where_clause = $this->buildWhereClause($user_id, $date_range);
        
        // Calculate time saved by AI processing
        $ai_time_saved_query = "
            SELECT 
                COUNT(*) as ai_processed_emails,
                AVG(CASE WHEN category IS NOT NULL THEN 30 ELSE 0 END) as avg_time_saved_per_email
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause AND category IS NOT NULL
        ";
        
        $ai_time_saved = $this->db->query($ai_time_saved_query)->fetch();
        
        // Calculate response time patterns
        $response_time_query = "
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, e.received_at, e.replied_at)) as avg_response_time_hours,
                COUNT(CASE WHEN e.replied_at IS NOT NULL THEN 1 END) as replied_emails,
                COUNT(*) as total_emails
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause AND e.replied_at IS NOT NULL
        ";
        
        $response_time = $this->db->query($response_time_query)->fetch();
        
        // Calculate email processing efficiency
        $efficiency_query = "
            SELECT 
                COUNT(CASE WHEN is_read = 1 AND replied_at IS NOT NULL THEN 1 END) as actionable_emails,
                COUNT(CASE WHEN is_archived = 1 AND replied_at IS NULL THEN 1 END) as archived_without_reply,
                COUNT(CASE WHEN is_deleted = 1 THEN 1 END) as deleted_emails,
                COUNT(*) as total_emails
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause
        ";
        
        $efficiency = $this->db->query($efficiency_query)->fetch();
        
        $total_time_saved = ($ai_time_saved['ai_processed_emails'] ?? 0) * 
                           ($ai_time_saved['avg_time_saved_per_email'] ?? 0) / 60; // Convert to hours
        
        $response_rate = $response_time['total_emails'] > 0 ? 
            ($response_time['replied_emails'] / $response_time['total_emails']) * 100 : 0;
        
        $efficiency_score = $efficiency['total_emails'] > 0 ? 
            (($efficiency['actionable_emails'] + $efficiency['archived_without_reply']) / 
             $efficiency['total_emails']) * 100 : 0;
        
        return [
            'ai_processed_emails' => (int)($ai_time_saved['ai_processed_emails'] ?? 0),
            'total_time_saved_hours' => round($total_time_saved, 2),
            'avg_response_time_hours' => round($response_time['avg_response_time_hours'] ?? 0, 2),
            'response_rate' => round($response_rate, 2),
            'efficiency_score' => round($efficiency_score, 2),
            'actionable_emails' => (int)($efficiency['actionable_emails'] ?? 0),
            'archived_without_reply' => (int)($efficiency['archived_without_reply'] ?? 0),
            'deleted_emails' => (int)($efficiency['deleted_emails'] ?? 0)
        ];
    }
    
    /**
     * Get category distribution
     */
    private function getCategoryDistribution($user_id, $date_range) {
        $where_clause = $this->buildWhereClause($user_id, $date_range);
        
        $query = "
            SELECT 
                COALESCE(category, 'Unprocessed') as category,
                COUNT(*) as count,
                AVG(CASE WHEN ai_confidence IS NOT NULL THEN ai_confidence END) as avg_confidence,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority_count
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause
            GROUP BY category
            ORDER BY count DESC
        ";
        
        $results = $this->db->query($query)->fetchAll();
        $total_emails = array_sum(array_column($results, 'count'));
        
        return array_map(function($row) use ($total_emails) {
            $percentage = $total_emails > 0 ? ($row['count'] / $total_emails) * 100 : 0;
            
            return [
                'category' => $row['category'],
                'count' => (int)$row['count'],
                'percentage' => round($percentage, 2),
                'avg_confidence' => round($row['avg_confidence'] ?? 0, 2),
                'high_priority_count' => (int)$row['high_priority_count']
            ];
        }, $results);
    }
    
    /**
     * Get response patterns
     */
    private function getResponsePatterns($user_id, $date_range) {
        $where_clause = $this->buildWhereClause($user_id, $date_range);
        
        $query = "
            SELECT 
                HOUR(received_at) as hour_of_day,
                DAYOFWEEK(received_at) as day_of_week,
                COUNT(*) as email_count,
                COUNT(CASE WHEN replied_at IS NOT NULL THEN 1 END) as replied_count,
                AVG(CASE WHEN replied_at IS NOT NULL THEN 
                    TIMESTAMPDIFF(MINUTE, received_at, replied_at) END) as avg_response_time_minutes
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause
            GROUP BY hour_of_day, day_of_week
            ORDER BY day_of_week, hour_of_day
        ";
        
        $results = $this->db->query($query)->fetchAll();
        
        // Group by hour and day
        $hourly_patterns = [];
        $daily_patterns = [];
        
        foreach ($results as $row) {
            $hour = (int)$row['hour_of_day'];
            $day = (int)$row['day_of_week'];
            
            if (!isset($hourly_patterns[$hour])) {
                $hourly_patterns[$hour] = [
                    'hour' => $hour,
                    'email_count' => 0,
                    'replied_count' => 0,
                    'avg_response_time' => 0
                ];
            }
            
            if (!isset($daily_patterns[$day])) {
                $daily_patterns[$day] = [
                    'day' => $day,
                    'day_name' => $this->getDayName($day),
                    'email_count' => 0,
                    'replied_count' => 0,
                    'avg_response_time' => 0
                ];
            }
            
            $hourly_patterns[$hour]['email_count'] += (int)$row['email_count'];
            $hourly_patterns[$hour]['replied_count'] += (int)$row['replied_count'];
            
            $daily_patterns[$day]['email_count'] += (int)$row['email_count'];
            $daily_patterns[$day]['replied_count'] += (int)$row['replied_count'];
        }
        
        return [
            'hourly_patterns' => array_values($hourly_patterns),
            'daily_patterns' => array_values($daily_patterns)
        ];
    }
    
    /**
     * Get sender analysis
     */
    private function getSenderAnalysis($user_id, $date_range) {
        $where_clause = $this->buildWhereClause($user_id, $date_range);
        
        $query = "
            SELECT 
                sender,
                sender_name,
                COUNT(*) as email_count,
                COUNT(CASE WHEN is_read = 1 THEN 1 END) as read_count,
                COUNT(CASE WHEN replied_at IS NOT NULL THEN 1 END) as replied_count,
                AVG(CASE WHEN ai_confidence IS NOT NULL THEN ai_confidence END) as avg_confidence,
                MAX(received_at) as last_email_date,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority_count
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause
            GROUP BY sender, sender_name
            HAVING email_count >= 2
            ORDER BY email_count DESC
            LIMIT 50
        ";
        
        $results = $this->db->query($query)->fetchAll();
        
        return array_map(function($row) {
            $read_rate = $row['email_count'] > 0 ? 
                ($row['read_count'] / $row['email_count']) * 100 : 0;
            $reply_rate = $row['email_count'] > 0 ? 
                ($row['replied_count'] / $row['email_count']) * 100 : 0;
            
            return [
                'sender' => $row['sender'],
                'sender_name' => $row['sender_name'],
                'email_count' => (int)$row['email_count'],
                'read_count' => (int)$row['read_count'],
                'replied_count' => (int)$row['replied_count'],
                'read_rate' => round($read_rate, 2),
                'reply_rate' => round($reply_rate, 2),
                'avg_confidence' => round($row['avg_confidence'] ?? 0, 2),
                'last_email_date' => $row['last_email_date'],
                'high_priority_count' => (int)$row['high_priority_count']
            ];
        }, $results);
    }
    
    /**
     * Get time analysis
     */
    private function getTimeAnalysis($user_id, $date_range) {
        $where_clause = $this->buildWhereClause($user_id, $date_range);
        
        // Peak hours analysis
        $peak_hours_query = "
            SELECT 
                HOUR(received_at) as hour,
                COUNT(*) as email_count
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause
            GROUP BY hour
            ORDER BY email_count DESC
            LIMIT 5
        ";
        
        $peak_hours = $this->db->query($peak_hours_query)->fetchAll();
        
        // Response time by hour
        $response_time_query = "
            SELECT 
                HOUR(received_at) as hour,
                AVG(CASE WHEN replied_at IS NOT NULL THEN 
                    TIMESTAMPDIFF(MINUTE, received_at, replied_at) END) as avg_response_time_minutes,
                COUNT(CASE WHEN replied_at IS NOT NULL THEN 1 END) as replied_count
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause AND replied_at IS NOT NULL
            GROUP BY hour
            ORDER BY hour
        ";
        
        $response_times = $this->db->query($response_time_query)->fetchAll();
        
        return [
            'peak_hours' => array_map(function($row) {
                return [
                    'hour' => (int)$row['hour'],
                    'email_count' => (int)$row['email_count']
                ];
            }, $peak_hours),
            'response_times_by_hour' => array_map(function($row) {
                return [
                    'hour' => (int)$row['hour'],
                    'avg_response_time_minutes' => round($row['avg_response_time_minutes'] ?? 0, 2),
                    'replied_count' => (int)$row['replied_count']
                ];
            }, $response_times)
        ];
    }
    
    /**
     * Get predictive insights using ML models
     */
    private function getPredictiveInsights($user_id, $date_range) {
        // This would integrate with the ML predictor
        // For now, return basic predictive analytics
        
        $where_clause = $this->buildWhereClause($user_id, $date_range);
        
        // Predict email volume trends
        $volume_trend_query = "
            SELECT 
                DATE(received_at) as date,
                COUNT(*) as email_count
            FROM emails e
            LEFT JOIN email_accounts ea ON e.email_account_id = ea.id
            $where_clause
            GROUP BY date
            ORDER BY date DESC
            LIMIT 30
        ";
        
        $volume_data = $this->db->query($volume_trend_query)->fetchAll();
        
        // Simple trend calculation (can be enhanced with ML)
        $recent_avg = 0;
        $older_avg = 0;
        
        if (count($volume_data) >= 14) {
            $recent_data = array_slice($volume_data, 0, 7);
            $older_data = array_slice($volume_data, 7, 7);
            
            $recent_avg = array_sum(array_column($recent_data, 'email_count')) / count($recent_data);
            $older_avg = array_sum(array_column($older_data, 'email_count')) / count($older_data);
        }
        
        $trend_direction = $recent_avg > $older_avg ? 'increasing' : 
                          ($recent_avg < $older_avg ? 'decreasing' : 'stable');
        
        $trend_percentage = $older_avg > 0 ? 
            (($recent_avg - $older_avg) / $older_avg) * 100 : 0;
        
        return [
            'email_volume_trend' => [
                'direction' => $trend_direction,
                'percentage_change' => round($trend_percentage, 2),
                'recent_avg' => round($recent_avg, 2),
                'older_avg' => round($older_avg, 2)
            ],
            'predicted_next_week_volume' => round($recent_avg * 7, 0),
            'recommendations' => $this->generateRecommendations($user_id, $date_range)
        ];
    }
    
    /**
     * Generate AI-powered recommendations
     */
    private function generateRecommendations($user_id, $date_range) {
        $recommendations = [];
        
        // Get current metrics for analysis
        $overview = $this->getOverviewMetrics($user_id, $date_range);
        $ai_performance = $this->getAIPerformanceMetrics($user_id, $date_range);
        
        // Processing rate recommendation
        if ($overview['processing_rate'] < 80) {
            $recommendations[] = [
                'type' => 'processing',
                'priority' => 'high',
                'title' => 'Improve Email Processing Rate',
                'description' => 'Your email processing rate is ' . $overview['processing_rate'] . '%. Consider enabling more AI providers or reviewing your email rules.',
                'action' => 'Enable additional AI providers in settings'
            ];
        }
        
        // AI confidence recommendation
        if ($overview['avg_ai_confidence'] < 0.7) {
            $recommendations[] = [
                'type' => 'ai_confidence',
                'priority' => 'medium',
                'title' => 'Enhance AI Accuracy',
                'description' => 'AI confidence is ' . ($overview['avg_ai_confidence'] * 100) . '%. Training with more data could improve accuracy.',
                'action' => 'Review and correct AI categorizations to improve training'
            ];
        }
        
        // Response time recommendation
        $productivity = $this->getProductivityInsights($user_id, $date_range);
        if ($productivity['avg_response_time_hours'] > 24) {
            $recommendations[] = [
                'type' => 'response_time',
                'priority' => 'medium',
                'title' => 'Reduce Response Time',
                'description' => 'Average response time is ' . $productivity['avg_response_time_hours'] . ' hours. Consider setting up automated responses for common queries.',
                'action' => 'Set up email templates and automated responses'
            ];
        }
        
        // AI provider optimization
        $best_provider = null;
        $best_success_rate = 0;
        
        foreach ($ai_performance as $provider) {
            if ($provider['success_rate'] > $best_success_rate) {
                $best_success_rate = $provider['success_rate'];
                $best_provider = $provider['provider_name'];
            }
        }
        
        if ($best_provider && $best_success_rate > 90) {
            $recommendations[] = [
                'type' => 'ai_optimization',
                'priority' => 'low',
                'title' => 'Optimize AI Provider Usage',
                'description' => $best_provider . ' has the highest success rate (' . $best_success_rate . '%). Consider prioritizing this provider.',
                'action' => 'Adjust AI provider weights in settings'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Build WHERE clause for queries
     */
    private function buildWhereClause($user_id, $date_range, $table_alias = 'e') {
        $conditions = [];
        
        if ($user_id) {
            if ($table_alias === 'apl') {
                $conditions[] = "apl.user_id = $user_id";
            } else {
                $conditions[] = "ea.user_id = $user_id";
            }
        }
        
        // Date range condition
        $date_condition = $this->getDateCondition($date_range, $table_alias);
        if ($date_condition) {
            $conditions[] = $date_condition;
        }
        
        return empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
    }
    
    /**
     * Get date condition for queries
     */
    private function getDateCondition($date_range, $table_alias = 'e') {
        $date_column = $table_alias === 'apl' ? 'apl.created_at' : 'e.received_at';
        
        switch ($date_range) {
            case '7d':
                return "$date_column >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30d':
                return "$date_column >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '90d':
                return "$date_column >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case '1y':
                return "$date_column >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "$date_column >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }
    
    /**
     * Get GROUP BY clause for time-based queries
     */
    private function getGroupByClause($date_range) {
        switch ($date_range) {
            case '7d':
                return "DATE(e.received_at)";
            case '30d':
                return "DATE(e.received_at)";
            case '90d':
                return "YEARWEEK(e.received_at)";
            case '1y':
                return "YEAR(e.received_at), MONTH(e.received_at)";
            default:
                return "DATE(e.received_at)";
        }
    }
    
    /**
     * Get day name from day number
     */
    private function getDayName($day_number) {
        $days = [
            1 => 'Sunday',
            2 => 'Monday', 
            3 => 'Tuesday',
            4 => 'Wednesday',
            5 => 'Thursday',
            6 => 'Friday',
            7 => 'Saturday'
        ];
        
        return $days[$day_number] ?? 'Unknown';
    }
    
    /**
     * Export analytics data to various formats
     */
    public function exportAnalytics($user_id, $date_range, $format = 'json') {
        $analytics = $this->getDashboardAnalytics($user_id, $date_range);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCSV($analytics);
            case 'pdf':
                return $this->exportToPDF($analytics);
            case 'excel':
                return $this->exportToExcel($analytics);
            default:
                return json_encode($analytics, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Export to CSV format
     */
    private function exportToCSV($analytics) {
        $csv = "Metric,Value\n";
        
        // Overview metrics
        foreach ($analytics['overview'] as $key => $value) {
            $csv .= ucfirst(str_replace('_', ' ', $key)) . ",$value\n";
        }
        
        return $csv;
    }
    
    /**
     * Export to PDF format
     */
    private function exportToPDF($analytics) {
        // This would require a PDF library like TCPDF or FPDF
        // For now, return a placeholder
        return "PDF export functionality would be implemented here";
    }
    
    /**
     * Export to Excel format
     */
    private function exportToExcel($analytics) {
        // This would require a library like PhpSpreadsheet
        // For now, return a placeholder
        return "Excel export functionality would be implemented here";
    }
}

// API endpoint for analytics
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $analytics = new AdvancedAnalytics();
    $user_id = $_GET['user_id'] ?? null;
    $date_range = $_GET['date_range'] ?? '30d';
    
    try {
        switch ($_GET['action']) {
            case 'dashboard':
                $result = $analytics->getDashboardAnalytics($user_id, $date_range);
                break;
            case 'export':
                $format = $_GET['format'] ?? 'json';
                $result = $analytics->exportAnalytics($user_id, $date_range, $format);
                break;
            default:
                $result = ['error' => 'Invalid action'];
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>

