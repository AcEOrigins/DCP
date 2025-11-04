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

// Create employee: POST /employees
if ($method === 'POST' && count($segments) === 1 && $segments[0] === 'employees') {
    $user = requireRole(['manager']);
    $data = getJsonInput();
    
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $name = $data['name'] ?? '';
    $phone = $data['phone'] ?? '';
    
    if (empty($email) || empty($password) || empty($name)) {
        sendError('Email, password, and name are required', 400);
    }
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendError('Email already registered', 409);
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email format', 400);
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        sendError('Password must be at least 6 characters', 400);
    }
    
    $passwordHash = hashPassword($password);
    
    $stmt = $db->prepare("
        INSERT INTO users (email, password_hash, name, phone, role)
        VALUES (?, ?, ?, ?, 'employee')
    ");
    $stmt->execute([$email, $passwordHash, $name, $phone]);
    
    $userId = $db->lastInsertId();
    
    // Get created employee
    $stmt = $db->prepare("SELECT id, email, name, phone, role, profile_picture, created_at, updated_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $employee = $stmt->fetch();
    
    sendResponse(['data' => $employee], 201);
}

// Update user role: PUT /employees/:id/role
if ($method === 'PUT' && count($segments) === 3 && $segments[0] === 'employees' && is_numeric($segments[1]) && $segments[2] === 'role') {
    $user = requireRole(['manager']);
    $userId = (int)$segments[1];
    $data = getJsonInput();
    
    $newRole = $data['role'] ?? '';
    $allowedRoles = ['customer', 'employee', 'manager'];
    
    if (!in_array($newRole, $allowedRoles)) {
        sendError('Invalid role. Must be one of: ' . implode(', ', $allowedRoles), 400);
    }
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        sendError('User not found', 404);
    }
    
    // Prevent downgrading the last manager
    if ($targetUser['role'] === 'manager' && $newRole !== 'manager') {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'manager'");
        $stmt->execute();
        $managerCount = $stmt->fetch()['count'];
        if ($managerCount <= 1) {
            sendError('Cannot remove the last manager', 400);
        }
    }
    
    // Update role
    $stmt = $db->prepare("UPDATE users SET role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$newRole, $userId]);
    
    // Get updated user
    $stmt = $db->prepare("SELECT id, email, name, phone, role, profile_picture, created_at, updated_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $updatedUser = $stmt->fetch();
    
    sendResponse(['data' => $updatedUser]);
}

// 404 - Route not found
sendError('Employees endpoint not found', 404);
?>

