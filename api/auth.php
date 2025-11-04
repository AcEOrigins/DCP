<?php
/**
 * Authentication Endpoints
 * 
 * Handles login, registration, token verification, and logout
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Customer Login: POST /auth/customer/login
if ($method === 'POST' && isset($segments[1]) && $segments[1] === 'customer' && isset($segments[2]) && $segments[2] === 'login') {
    $data = getJsonInput();
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendError('Email and password are required', 400);
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'customer'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        sendError('Invalid email or password', 401);
    }
    
    $token = generateToken($user['id']);
    unset($user['password_hash']);
    
    sendResponse([
        'success' => true,
        'token' => $token,
        'user' => $user
    ]);
}

// Employee Login: POST /auth/employee/login
if ($method === 'POST' && isset($segments[1]) && $segments[1] === 'employee' && isset($segments[2]) && $segments[2] === 'login') {
    $data = getJsonInput();
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendError('Email and password are required', 400);
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'employee'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        sendError('Invalid email or password', 401);
    }
    
    $token = generateToken($user['id']);
    unset($user['password_hash']);
    
    sendResponse([
        'success' => true,
        'token' => $token,
        'user' => $user
    ]);
}

// Manager Login: POST /auth/manager/login
if ($method === 'POST' && isset($segments[1]) && $segments[1] === 'manager' && isset($segments[2]) && $segments[2] === 'login') {
    $data = getJsonInput();
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendError('Email and password are required', 400);
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'manager'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        sendError('Invalid email or password', 401);
    }
    
    $token = generateToken($user['id']);
    unset($user['password_hash']);
    
    sendResponse([
        'success' => true,
        'token' => $token,
        'user' => $user
    ]);
}

// Customer Registration: POST /auth/customer/register
if ($method === 'POST' && isset($segments[1]) && $segments[1] === 'customer' && isset($segments[2]) && $segments[2] === 'register') {
    $data = getJsonInput();
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $name = $data['name'] ?? '';
    $phone = $data['phone'] ?? '';
    $cookieConsent = $data['cookieConsent'] ?? 'pending';
    
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
    $cookieConsentValue = in_array($cookieConsent, ['accepted', 'declined']) ? $cookieConsent : 'pending';
    
    try {
        $stmt = $db->prepare("
            INSERT INTO users (email, password_hash, name, phone, role, cookie_consent)
            VALUES (?, ?, ?, ?, 'customer', ?)
        ");
        $stmt->execute([$email, $passwordHash, $name, $phone, $cookieConsentValue]);
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            sendError('Database error: ' . $e->getMessage(), 500);
        } else {
            sendError('Failed to create account', 500);
        }
    }
    
    $userId = $db->lastInsertId();
    $token = generateToken($userId);
    
    // Get created user
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    unset($user['password_hash']);
    
    sendResponse([
        'success' => true,
        'token' => $token,
        'user' => $user
    ], 201);
}

// Manager Registration: POST /auth/manager/register
if ($method === 'POST' && isset($segments[1]) && $segments[1] === 'manager' && isset($segments[2]) && $segments[2] === 'register') {
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
    
    try {
        $stmt = $db->prepare("
            INSERT INTO users (email, password_hash, name, phone, role)
            VALUES (?, ?, ?, ?, 'manager')
        ");
        $stmt->execute([$email, $passwordHash, $name, $phone]);
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            sendError('Database error: ' . $e->getMessage(), 500);
        } else {
            sendError('Failed to create account', 500);
        }
    }
    
    $userId = $db->lastInsertId();
    $token = generateToken($userId);
    
    // Get created user
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    unset($user['password_hash']);
    
    sendResponse([
        'success' => true,
        'token' => $token,
        'user' => $user
    ], 201);
}

// Verify Token: GET /auth/verify
if ($method === 'GET' && isset($segments[1]) && $segments[1] === 'verify') {
    $user = verifyToken();
    
    if (!$user) {
        sendError('Invalid or expired token', 401);
    }
    
    sendResponse([
        'success' => true,
        'user' => $user
    ]);
}

// Logout: POST /auth/logout
if ($method === 'POST' && isset($segments[1]) && $segments[1] === 'logout') {
    $token = getAuthToken();
    
    if ($token) {
        $stmt = $db->prepare("DELETE FROM auth_tokens WHERE token = ?");
        $stmt->execute([$token]);
    }
    
    sendResponse([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

// 404 - Route not found
sendError('Auth endpoint not found', 404);
?>

