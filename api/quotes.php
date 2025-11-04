<?php
/**
 * Quotes Endpoints
 * 
 * Handles quote request CRUD operations
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Get all quotes: GET /quotes
if ($method === 'GET' && count($segments) === 1 && $segments[0] === 'quotes') {
    $user = requireAuth();
    
    $sql = "SELECT q.*, u.name as customer_name, u.email as customer_email 
            FROM quotes q 
            LEFT JOIN users u ON q.customer_id = u.id";
    
    // Filter by role
    if ($user['role'] === 'customer') {
        $sql .= " WHERE q.customer_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user['id']]);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute();
    }
    
    $quotes = $stmt->fetchAll();
    
    sendResponse(['data' => $quotes]);
}

// Get quote by ID: GET /quotes/:id
if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'quotes' && is_numeric($segments[1])) {
    $user = requireAuth();
    $quoteId = (int)$segments[1];
    
    $sql = "SELECT q.*, u.name as customer_name, u.email as customer_email 
            FROM quotes q 
            LEFT JOIN users u ON q.customer_id = u.id
            WHERE q.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$quoteId]);
    $quote = $stmt->fetch();
    
    if (!$quote) {
        sendError('Quote not found', 404);
    }
    
    // Check permissions
    if ($user['role'] === 'customer' && $quote['customer_id'] != $user['id']) {
        sendError('Unauthorized', 403);
    }
    
    sendResponse(['data' => $quote]);
}

// Create quote: POST /quotes
if ($method === 'POST' && count($segments) === 1 && $segments[0] === 'quotes') {
    $data = getJsonInput();
    
    // Get user if authenticated, otherwise allow anonymous
    $user = verifyToken();
    $customerId = $user ? $user['id'] : null;
    
    $required = ['name', 'email', 'address', 'city', 'state', 'zip_code'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Field '$field' is required", 400);
        }
    }
    
    $stmt = $db->prepare("
        INSERT INTO quotes (
            customer_id, name, email, phone, address, city, state, zip_code,
            service_type, property_size, timeline, budget_range, additional_info, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->execute([
        $customerId,
        $data['name'],
        $data['email'],
        $data['phone'] ?? null,
        $data['address'],
        $data['city'],
        $data['state'],
        $data['zip_code'],
        $data['service_type'] ?? null,
        $data['property_size'] ?? null,
        $data['timeline'] ?? null,
        $data['budget_range'] ?? null,
        $data['additional_info'] ?? null
    ]);
    
    $quoteId = $db->lastInsertId();
    
    $stmt = $db->prepare("SELECT * FROM quotes WHERE id = ?");
    $stmt->execute([$quoteId]);
    $quote = $stmt->fetch();
    
    sendResponse(['data' => $quote], 201);
}

// Update quote: PUT /quotes/:id
if ($method === 'PUT' && count($segments) === 2 && $segments[0] === 'quotes' && is_numeric($segments[1])) {
    $user = requireAuth();
    $quoteId = (int)$segments[1];
    $data = getJsonInput();
    
    // Check if quote exists
    $stmt = $db->prepare("SELECT * FROM quotes WHERE id = ?");
    $stmt->execute([$quoteId]);
    $quote = $stmt->fetch();
    
    if (!$quote) {
        sendError('Quote not found', 404);
    }
    
    // Check permissions
    if ($user['role'] === 'customer' && $quote['customer_id'] != $user['id']) {
        sendError('Unauthorized', 403);
    }
    
    // Build update query
    $fields = [];
    $values = [];
    
    $allowedFields = ['name', 'email', 'phone', 'address', 'city', 'state', 'zip_code', 
                      'service_type', 'property_size', 'timeline', 'budget_range', 'additional_info'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    
    if (empty($fields)) {
        sendError('No fields to update', 400);
    }
    
    $values[] = $quoteId;
    $sql = "UPDATE quotes SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    
    $stmt = $db->prepare("SELECT * FROM quotes WHERE id = ?");
    $stmt->execute([$quoteId]);
    $updatedQuote = $stmt->fetch();
    
    sendResponse(['data' => $updatedQuote]);
}

// Update quote status: PATCH /quotes/:id/status
if ($method === 'PATCH' && count($segments) === 3 && $segments[0] === 'quotes' && 
    is_numeric($segments[1]) && $segments[2] === 'status') {
    $user = requireRole(['employee', 'manager']);
    $quoteId = (int)$segments[1];
    $data = getJsonInput();
    $status = $data['status'] ?? '';
    
    $validStatuses = ['pending', 'reviewed', 'approved', 'declined', 'completed'];
    if (!in_array($status, $validStatuses)) {
        sendError('Invalid status', 400);
    }
    
    $stmt = $db->prepare("UPDATE quotes SET status = ? WHERE id = ?");
    $stmt->execute([$status, $quoteId]);
    
    $stmt = $db->prepare("SELECT * FROM quotes WHERE id = ?");
    $stmt->execute([$quoteId]);
    $quote = $stmt->fetch();
    
    sendResponse(['data' => $quote]);
}

// Delete quote: DELETE /quotes/:id
if ($method === 'DELETE' && count($segments) === 2 && $segments[0] === 'quotes' && is_numeric($segments[1])) {
    $user = requireRole(['employee', 'manager']);
    $quoteId = (int)$segments[1];
    
    $stmt = $db->prepare("DELETE FROM quotes WHERE id = ?");
    $stmt->execute([$quoteId]);
    
    sendResponse(['success' => true, 'message' => 'Quote deleted']);
}

// 404 - Route not found
sendError('Quotes endpoint not found', 404);
?>

