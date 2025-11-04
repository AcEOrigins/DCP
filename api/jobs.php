<?php
/**
 * Jobs Endpoints
 * 
 * Handles job CRUD operations
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Get all jobs: GET /jobs
if ($method === 'GET' && count($segments) === 1 && $segments[0] === 'jobs') {
    $user = requireAuth();
    
    $sql = "SELECT j.*, u.name as customer_name, u.email as customer_email,
            e.name as employee_name, p.address as property_address
            FROM jobs j
            LEFT JOIN users u ON j.customer_id = u.id
            LEFT JOIN users e ON j.assigned_employee_id = e.id
            LEFT JOIN properties p ON j.property_id = p.id";
    
    // Filter by role
    if ($user['role'] === 'customer') {
        $sql .= " WHERE j.customer_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user['id']]);
    } else if ($user['role'] === 'employee') {
        $sql .= " WHERE j.assigned_employee_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user['id']]);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute();
    }
    
    $jobs = $stmt->fetchAll();
    
    sendResponse(['data' => $jobs]);
}

// Get jobs by customer: GET /jobs/customer/:customerId
if ($method === 'GET' && count($segments) === 3 && $segments[0] === 'jobs' && 
    $segments[1] === 'customer' && is_numeric($segments[2])) {
    $user = requireAuth();
    $customerId = (int)$segments[2];
    
    // Check permissions
    if ($user['role'] === 'customer' && $user['id'] != $customerId) {
        sendError('Unauthorized', 403);
    }
    
    $stmt = $db->prepare("
        SELECT j.*, u.name as customer_name, u.email as customer_email,
        e.name as employee_name, p.address as property_address
        FROM jobs j
        LEFT JOIN users u ON j.customer_id = u.id
        LEFT JOIN users e ON j.assigned_employee_id = e.id
        LEFT JOIN properties p ON j.property_id = p.id
        WHERE j.customer_id = ?
    ");
    $stmt->execute([$customerId]);
    $jobs = $stmt->fetchAll();
    
    sendResponse(['data' => $jobs]);
}

// Get job by ID: GET /jobs/:id
if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'jobs' && is_numeric($segments[1])) {
    $user = requireAuth();
    $jobId = (int)$segments[1];
    
    $stmt = $db->prepare("
        SELECT j.*, u.name as customer_name, u.email as customer_email,
        e.name as employee_name, p.address as property_address
        FROM jobs j
        LEFT JOIN users u ON j.customer_id = u.id
        LEFT JOIN users e ON j.assigned_employee_id = e.id
        LEFT JOIN properties p ON j.property_id = p.id
        WHERE j.id = ?
    ");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    
    if (!$job) {
        sendError('Job not found', 404);
    }
    
    // Check permissions
    if ($user['role'] === 'customer' && $job['customer_id'] != $user['id']) {
        sendError('Unauthorized', 403);
    }
    
    sendResponse(['data' => $job]);
}

// Create job: POST /jobs
if ($method === 'POST' && count($segments) === 1 && $segments[0] === 'jobs') {
    $user = requireRole(['employee', 'manager']);
    $data = getJsonInput();
    
    $required = ['customer_id', 'title', 'address', 'city', 'state', 'zip_code'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Field '$field' is required", 400);
        }
    }
    
    $stmt = $db->prepare("
        INSERT INTO jobs (
            quote_id, customer_id, property_id, assigned_employee_id, title, description,
            address, city, state, zip_code, start_date, end_date,
            estimated_cost, actual_cost, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
    ");
    
    $stmt->execute([
        $data['quote_id'] ?? null,
        $data['customer_id'],
        $data['property_id'] ?? null,
        $data['assigned_employee_id'] ?? null,
        $data['title'],
        $data['description'] ?? null,
        $data['address'],
        $data['city'],
        $data['state'],
        $data['zip_code'],
        $data['start_date'] ?? null,
        $data['end_date'] ?? null,
        $data['estimated_cost'] ?? null,
        $data['actual_cost'] ?? null,
        $data['notes'] ?? null
    ]);
    
    $jobId = $db->lastInsertId();
    
    $stmt = $db->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    
    sendResponse(['data' => $job], 201);
}

// Update job: PUT /jobs/:id
if ($method === 'PUT' && count($segments) === 2 && $segments[0] === 'jobs' && is_numeric($segments[1])) {
    $user = requireAuth();
    $jobId = (int)$segments[1];
    $data = getJsonInput();
    
    // Check if job exists
    $stmt = $db->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    
    if (!$job) {
        sendError('Job not found', 404);
    }
    
    // Check permissions
    if ($user['role'] === 'customer' && $job['customer_id'] != $user['id']) {
        sendError('Unauthorized', 403);
    }
    
    // Build update query
    $fields = [];
    $values = [];
    
    $allowedFields = ['title', 'description', 'address', 'city', 'state', 'zip_code',
                      'start_date', 'end_date', 'estimated_cost', 'actual_cost', 'notes',
                      'assigned_employee_id', 'property_id'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    
    if (empty($fields)) {
        sendError('No fields to update', 400);
    }
    
    $values[] = $jobId;
    $sql = "UPDATE jobs SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    
    $stmt = $db->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $updatedJob = $stmt->fetch();
    
    sendResponse(['data' => $updatedJob]);
}

// Update job status: PATCH /jobs/:id/status
if ($method === 'PATCH' && count($segments) === 3 && $segments[0] === 'jobs' && 
    is_numeric($segments[1]) && $segments[2] === 'status') {
    $user = requireAuth();
    $jobId = (int)$segments[1];
    $data = getJsonInput();
    $status = $data['status'] ?? '';
    
    $validStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        sendError('Invalid status', 400);
    }
    
    $stmt = $db->prepare("UPDATE jobs SET status = ? WHERE id = ?");
    $stmt->execute([$status, $jobId]);
    
    $stmt = $db->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    
    sendResponse(['data' => $job]);
}

// Delete job: DELETE /jobs/:id
if ($method === 'DELETE' && count($segments) === 2 && $segments[0] === 'jobs' && is_numeric($segments[1])) {
    $user = requireRole(['employee', 'manager']);
    $jobId = (int)$segments[1];
    
    $stmt = $db->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    
    sendResponse(['success' => true, 'message' => 'Job deleted']);
}

// 404 - Route not found
sendError('Jobs endpoint not found', 404);
?>

