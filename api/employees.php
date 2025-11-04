<?php
/**
 * Employees Endpoints (Management)
 * 
 * Handles employee management operations
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Get all employees: GET /employees
if ($method === 'GET' && count($segments) === 1 && $segments[0] === 'employees') {
    $user = requireRole(['manager']);
    
    $stmt = $db->prepare("SELECT id, email, name, phone, role, profile_picture, created_at, updated_at FROM users WHERE role = 'employee'");
    $stmt->execute();
    $employees = $stmt->fetchAll();
    
    sendResponse(['data' => $employees]);
}

// 404 - Route not found
sendError('Employees endpoint not found', 404);
?>

