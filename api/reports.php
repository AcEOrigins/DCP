<?php
/**
 * Reports Endpoints (Management)
 * 
 * Handles report generation for managers
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Get reports: GET /reports
if ($method === 'GET' && count($segments) === 1 && $segments[0] === 'reports') {
    $user = requireRole(['manager']);
    
    // Get date range from query params (optional)
    $startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
    $endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
    
    // Customer report
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone,
               COUNT(DISTINCT q.id) as total_quotes,
               COUNT(DISTINCT j.id) as total_jobs,
               SUM(COALESCE(j.actual_cost, j.estimated_cost, 0)) as total_value
        FROM users u
        LEFT JOIN quotes q ON q.customer_id = u.id AND q.created_at BETWEEN ? AND ?
        LEFT JOIN jobs j ON j.customer_id = u.id AND j.created_at BETWEEN ? AND ?
        WHERE u.role = 'customer'
        GROUP BY u.id, u.name, u.email, u.phone
        ORDER BY total_value DESC
    ");
    $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
    $customerReport = $stmt->fetchAll();
    
    // Employee performance report
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email,
               COUNT(DISTINCT j.id) as jobs_assigned,
               COUNT(DISTINCT CASE WHEN j.status = 'completed' THEN j.id END) as jobs_completed,
               SUM(COALESCE(j.actual_cost, j.estimated_cost, 0)) as total_value
        FROM users u
        LEFT JOIN jobs j ON j.assigned_employee_id = u.id AND j.created_at BETWEEN ? AND ?
        WHERE u.role = 'employee'
        GROUP BY u.id, u.name, u.email
        ORDER BY jobs_completed DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $employeeReport = $stmt->fetchAll();
    
    // Quote conversion report
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            COUNT(*) * 100.0 / (SELECT COUNT(*) FROM quotes WHERE created_at BETWEEN ? AND ?) as percentage
        FROM quotes
        WHERE created_at BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
    $quoteConversion = $stmt->fetchAll();
    
    $reports = [
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'customer_report' => $customerReport,
        'employee_report' => $employeeReport,
        'quote_conversion' => $quoteConversion
    ];
    
    sendResponse(['data' => $reports]);
}

// 404 - Route not found
sendError('Reports endpoint not found', 404);
?>

