<?php
/**
 * API Router for Declutter Pros
 * 
 * Routes all API requests to appropriate endpoint handlers
 */

require_once __DIR__ . '/config.php';

// Get the request path
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove base path (e.g., /api)
$path = str_replace('/api', '', $path);
$path = trim($path, '/');

// Split path into segments
$segments = explode('/', $path);

// Route the request
try {
    // Authentication routes
    if ($segments[0] === 'auth') {
        require_once __DIR__ . '/auth.php';
        exit();
    }
    
    // Quotes routes
    if ($segments[0] === 'quotes') {
        require_once __DIR__ . '/quotes.php';
        exit();
    }
    
    // Jobs routes
    if ($segments[0] === 'jobs') {
        require_once __DIR__ . '/jobs.php';
        exit();
    }
    
    // Properties routes
    if ($segments[0] === 'properties') {
        require_once __DIR__ . '/properties.php';
        exit();
    }
    
    // Customers routes
    if ($segments[0] === 'customers') {
        require_once __DIR__ . '/customers.php';
        exit();
    }
    
    // Dashboard routes
    if ($segments[0] === 'dashboard') {
        require_once __DIR__ . '/dashboard.php';
        exit();
    }
    
    // Employees routes (for management)
    if ($segments[0] === 'employees') {
        require_once __DIR__ . '/employees.php';
        exit();
    }
    
    // Analytics routes (for management)
    if ($segments[0] === 'analytics') {
        require_once __DIR__ . '/analytics.php';
        exit();
    }
    
    // Reports routes (for management)
    if ($segments[0] === 'reports') {
        require_once __DIR__ . '/reports.php';
        exit();
    }
    
    // 404 - Route not found
    sendError('Endpoint not found', 404);
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        sendError('Server error: ' . $e->getMessage(), 500);
    } else {
        sendError('Internal server error', 500);
    }
}
?>

