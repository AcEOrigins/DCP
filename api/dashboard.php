<?php
/**
 * Dashboard Endpoints
 * 
 * Handles dashboard statistics
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Get dashboard stats: GET /dashboard/stats
if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'dashboard' && $segments[1] === 'stats') {
    $user = requireAuth();
    
    $stats = [];
    
    if ($user['role'] === 'customer') {
        // Customer dashboard stats
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM quotes WHERE customer_id = ?");
        $stmt->execute([$user['id']]);
        $quotesCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM jobs WHERE customer_id = ?");
        $stmt->execute([$user['id']]);
        $jobsCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM properties WHERE customer_id = ?");
        $stmt->execute([$user['id']]);
        $propertiesCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM jobs WHERE customer_id = ? AND status = 'in_progress'");
        $stmt->execute([$user['id']]);
        $activeJobsCount = $stmt->fetch()['count'];
        
        $stats = [
            'quotes' => (int)$quotesCount,
            'jobs' => (int)$jobsCount,
            'properties' => (int)$propertiesCount,
            'active_jobs' => (int)$activeJobsCount
        ];
        
    } else if ($user['role'] === 'employee') {
        // Employee dashboard stats
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM quotes WHERE status = 'pending'");
        $stmt->execute();
        $pendingQuotesCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM jobs WHERE assigned_employee_id = ?");
        $stmt->execute([$user['id']]);
        $myJobsCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM jobs WHERE assigned_employee_id = ? AND status = 'in_progress'");
        $stmt->execute([$user['id']]);
        $activeJobsCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM customers");
        $stmt->execute();
        $customersCount = $stmt->fetch()['count'];
        
        $stats = [
            'pending_quotes' => (int)$pendingQuotesCount,
            'my_jobs' => (int)$myJobsCount,
            'active_jobs' => (int)$activeJobsCount,
            'customers' => (int)$customersCount
        ];
        
    } else if ($user['role'] === 'manager') {
        // Manager dashboard stats
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM quotes");
        $stmt->execute();
        $totalQuotesCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM quotes WHERE status = 'pending'");
        $stmt->execute();
        $pendingQuotesCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM jobs");
        $stmt->execute();
        $totalJobsCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM jobs WHERE status = 'in_progress'");
        $stmt->execute();
        $activeJobsCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
        $stmt->execute();
        $customersCount = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'employee'");
        $stmt->execute();
        $employeesCount = $stmt->fetch()['count'];
        
        $stats = [
            'total_quotes' => (int)$totalQuotesCount,
            'pending_quotes' => (int)$pendingQuotesCount,
            'total_jobs' => (int)$totalJobsCount,
            'active_jobs' => (int)$activeJobsCount,
            'customers' => (int)$customersCount,
            'employees' => (int)$employeesCount
        ];
    }
    
    sendResponse(['data' => $stats]);
}

// 404 - Route not found
sendError('Dashboard endpoint not found', 404);
?>

