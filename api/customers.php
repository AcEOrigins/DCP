<?php
/**
 * Customers Endpoints
 * 
 * Handles customer CRUD operations
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Get all customers: GET /customers
if ($method === 'GET' && count($segments) === 1 && $segments[0] === 'customers') {
    $user = requireRole(['employee', 'manager']);
    
    $stmt = $db->prepare("SELECT id, email, name, phone, role, profile_picture, cookie_consent, created_at, updated_at FROM users WHERE role = 'customer'");
    $stmt->execute();
    $customers = $stmt->fetchAll();
    
    sendResponse(['data' => $customers]);
}

// Get customer by ID: GET /customers/:id
if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'customers' && is_numeric($segments[1])) {
    $user = requireAuth();
    $customerId = (int)$segments[1];
    
    // Check permissions
    if ($user['role'] === 'customer' && $user['id'] != $customerId) {
        sendError('Unauthorized', 403);
    }
    
    $stmt = $db->prepare("SELECT id, email, name, phone, role, profile_picture, cookie_consent, created_at, updated_at FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        sendError('Customer not found', 404);
    }
    
    sendResponse(['data' => $customer]);
}

// Update customer: PUT /customers/:id
if ($method === 'PUT' && count($segments) === 2 && $segments[0] === 'customers' && is_numeric($segments[1])) {
    $user = requireAuth();
    $customerId = (int)$segments[1];
    $data = getJsonInput();
    
    // Check permissions
    if ($user['role'] === 'customer' && $user['id'] != $customerId) {
        sendError('Unauthorized', 403);
    }
    
    // Check if customer exists
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        sendError('Customer not found', 404);
    }
    
    // Build update query
    $fields = [];
    $values = [];
    
    $allowedFields = ['name', 'phone', 'profile_picture', 'cookie_consent'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    
    // Allow password update if provided
    if (isset($data['password']) && !empty($data['password'])) {
        if (strlen($data['password']) < 6) {
            sendError('Password must be at least 6 characters', 400);
        }
        $fields[] = "password_hash = ?";
        $values[] = hashPassword($data['password']);
    }
    
    if (empty($fields)) {
        sendError('No fields to update', 400);
    }
    
    $values[] = $customerId;
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    
    $stmt = $db->prepare("SELECT id, email, name, phone, role, profile_picture, cookie_consent, created_at, updated_at FROM users WHERE id = ?");
    $stmt->execute([$customerId]);
    $updatedCustomer = $stmt->fetch();
    
    sendResponse(['data' => $updatedCustomer]);
}

// Delete customer: DELETE /customers/:id
if ($method === 'DELETE' && count($segments) === 2 && $segments[0] === 'customers' && is_numeric($segments[1])) {
    $user = requireRole(['manager']);
    $customerId = (int)$segments[1];
    
    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$customerId]);
    
    sendResponse(['success' => true, 'message' => 'Customer deleted']);
}

// 404 - Route not found
sendError('Customers endpoint not found', 404);
?>

