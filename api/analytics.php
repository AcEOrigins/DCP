<?php
/**
 * Analytics Endpoints (Management)
 * 
 * Handles analytics data for managers
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Get analytics: GET /analytics
if ($method === 'GET' && count($segments) === 1 && $segments[0] === 'analytics') {
    $user = requireRole(['manager']);
    
    // Get quote status breakdown
    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM quotes 
        GROUP BY status
    ");
    $stmt->execute();
    $quoteStatuses = $stmt->fetchAll();
    
    // Get job status breakdown
    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM jobs 
        GROUP BY status
    ");
    $stmt->execute();
    $jobStatuses = $stmt->fetchAll();
    
    // Get monthly quote trends (last 6 months)
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
        FROM quotes
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthlyQuotes = $stmt->fetchAll();
    
    // Get monthly job trends (last 6 months)
    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
        FROM jobs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthlyJobs = $stmt->fetchAll();
    
    // Get revenue data (if available)
    $stmt = $db->prepare("
        SELECT 
            SUM(COALESCE(actual_cost, estimated_cost, 0)) as total_revenue,
            SUM(CASE WHEN status = 'completed' THEN COALESCE(actual_cost, estimated_cost, 0) ELSE 0 END) as completed_revenue
        FROM jobs
    ");
    $stmt->execute();
    $revenue = $stmt->fetch();
    
    $analytics = [
        'quote_statuses' => $quoteStatuses,
        'job_statuses' => $jobStatuses,
        'monthly_quotes' => $monthlyQuotes,
        'monthly_jobs' => $monthlyJobs,
        'revenue' => [
            'total' => (float)($revenue['total_revenue'] ?? 0),
            'completed' => (float)($revenue['completed_revenue'] ?? 0)
        ]
    ];
    
    sendResponse(['data' => $analytics]);
}

// 404 - Route not found
sendError('Analytics endpoint not found', 404);
?>

