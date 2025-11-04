<?php
/**
 * Properties Endpoints
 * 
 * Handles property CRUD operations
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Get all properties: GET /properties
if ($method === 'GET' && count($segments) === 1 && $segments[0] === 'properties') {
    $user = requireAuth();
    
    $sql = "SELECT p.*, u.name as customer_name, u.email as customer_email
            FROM properties p
            JOIN users u ON p.customer_id = u.id";
    
    // Filter by role
    if ($user['role'] === 'customer') {
        $sql .= " WHERE p.customer_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user['id']]);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute();
    }
    
    $properties = $stmt->fetchAll();
    
    sendResponse(['data' => $properties]);
}

// Get properties by customer: GET /properties/customer/:customerId
if ($method === 'GET' && count($segments) === 3 && $segments[0] === 'properties' && 
    $segments[1] === 'customer' && is_numeric($segments[2])) {
    $user = requireAuth();
    $customerId = (int)$segments[2];
    
    // Check permissions
    if ($user['role'] === 'customer' && $user['id'] != $customerId) {
        sendError('Unauthorized', 403);
    }
    
    $stmt = $db->prepare("
        SELECT p.*, u.name as customer_name, u.email as customer_email
        FROM properties p
        JOIN users u ON p.customer_id = u.id
        WHERE p.customer_id = ?
    ");
    $stmt->execute([$customerId]);
    $properties = $stmt->fetchAll();
    
    sendResponse(['data' => $properties]);
}

// Get property by ID: GET /properties/:id
if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'properties' && is_numeric($segments[1])) {
    $user = requireAuth();
    $propertyId = (int)$segments[1];
    
    $stmt = $db->prepare("
        SELECT p.*, u.name as customer_name, u.email as customer_email
        FROM properties p
        JOIN users u ON p.customer_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();
    
    if (!$property) {
        sendError('Property not found', 404);
    }
    
    // Check permissions
    if ($user['role'] === 'customer' && $property['customer_id'] != $user['id']) {
        sendError('Unauthorized', 403);
    }
    
    sendResponse(['data' => $property]);
}

// Create property: POST /properties
if ($method === 'POST' && count($segments) === 1 && $segments[0] === 'properties') {
    $user = requireAuth();
    $data = getJsonInput();
    
    $required = ['customer_id', 'address', 'city', 'state', 'zip_code'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Field '$field' is required", 400);
        }
    }
    
    // Check permissions
    if ($user['role'] === 'customer' && $user['id'] != $data['customer_id']) {
        sendError('Unauthorized', 403);
    }
    
    $stmt = $db->prepare("
        INSERT INTO properties (
            customer_id, address, city, state, zip_code, property_type, square_feet, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['customer_id'],
        $data['address'],
        $data['city'],
        $data['state'],
        $data['zip_code'],
        $data['property_type'] ?? null,
        $data['square_feet'] ?? null,
        $data['notes'] ?? null
    ]);
    
    $propertyId = $db->lastInsertId();
    
    $stmt = $db->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();
    
    sendResponse(['data' => $property], 201);
}

// Update property: PUT /properties/:id
if ($method === 'PUT' && count($segments) === 2 && $segments[0] === 'properties' && is_numeric($segments[1])) {
    $user = requireAuth();
    $propertyId = (int)$segments[1];
    $data = getJsonInput();
    
    // Check if property exists
    $stmt = $db->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();
    
    if (!$property) {
        sendError('Property not found', 404);
    }
    
    // Check permissions
    if ($user['role'] === 'customer' && $property['customer_id'] != $user['id']) {
        sendError('Unauthorized', 403);
    }
    
    // Build update query
    $fields = [];
    $values = [];
    
    $allowedFields = ['address', 'city', 'state', 'zip_code', 'property_type', 'square_feet', 'notes'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    
    if (empty($fields)) {
        sendError('No fields to update', 400);
    }
    
    $values[] = $propertyId;
    $sql = "UPDATE properties SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    
    $stmt = $db->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    $updatedProperty = $stmt->fetch();
    
    sendResponse(['data' => $updatedProperty]);
}

// Delete property: DELETE /properties/:id
if ($method === 'DELETE' && count($segments) === 2 && $segments[0] === 'properties' && is_numeric($segments[1])) {
    $user = requireAuth();
    $propertyId = (int)$segments[1];
    
    // Check if property exists
    $stmt = $db->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();
    
    if (!$property) {
        sendError('Property not found', 404);
    }
    
    // Check permissions
    if ($user['role'] === 'customer' && $property['customer_id'] != $user['id']) {
        sendError('Unauthorized', 403);
    }
    
    $stmt = $db->prepare("DELETE FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    
    sendResponse(['success' => true, 'message' => 'Property deleted']);
}

// 404 - Route not found
sendError('Properties endpoint not found', 404);
?>

