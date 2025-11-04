<?php
/**
 * Database Configuration for Declutter Pros API
 * 
 * Update these values with your Hostinger database credentials
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'declutterpros');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

// JWT Secret Key (generate a secure random string)
// You can generate one using: openssl rand -base64 32
define('JWT_SECRET', 'your-secret-key-change-this-in-production');

// Token expiration (24 hours)
define('TOKEN_EXPIRATION', 86400);

// CORS Configuration
define('ALLOWED_ORIGINS', [
    'http://localhost',
    'https://yourdomain.com',
    'https://www.yourdomain.com'
]);

// Error Reporting (set to false in production)
define('DEBUG_MODE', true);

// Database Connection
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            } else {
                die(json_encode(['error' => 'Database connection failed']));
            }
        }
    }
    
    return $conn;
}

// CORS Headers
function setCorsHeaders() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, ALLOWED_ORIGINS) || DEBUG_MODE) {
        header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");
    header("Content-Type: application/json; charset=UTF-8");
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Get Authorization Token
function getAuthToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $matches[1];
    }
    
    return null;
}

// Verify Token and Get User
function verifyToken() {
    $token = getAuthToken();
    
    if (!$token) {
        return null;
    }
    
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT u.*, at.expires_at 
        FROM auth_tokens at
        JOIN users u ON at.user_id = u.id
        WHERE at.token = ? AND at.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $result = $stmt->fetch();
    
    if ($result) {
        // Remove sensitive data
        unset($result['password_hash']);
        return $result;
    }
    
    return null;
}

// Require Authentication
function requireAuth() {
    $user = verifyToken();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Invalid or expired token']);
        exit();
    }
    
    return $user;
}

// Require Specific Role
function requireRole($allowedRoles) {
    $user = requireAuth();
    
    if (!in_array($user['role'], (array)$allowedRoles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden', 'message' => 'Insufficient permissions']);
        exit();
    }
    
    return $user;
}

// Generate Token
function generateToken($userId) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRATION);
    
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expiresAt]);
    
    return $token;
}

// Hash Password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify Password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Get JSON Input
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

// Send JSON Response
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

// Send Error Response
function sendError($message, $statusCode = 400) {
    sendResponse(['error' => $message], $statusCode);
}

// Initialize
setCorsHeaders();
?>

