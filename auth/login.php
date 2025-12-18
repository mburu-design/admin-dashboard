<?php
/**
 * Secure Admin Authentication Backend
 * Handles login requests and token generation
 */

/**
 * Validate authentication token
 * @param string $token The token to validate
 * @return array|false Token data if valid, false otherwise
 */
function validateToken($token) {
    try {
        $decoded = base64_decode($token);
        $parts = explode('|', $decoded);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($payload, $random_bytes, $signature) = $parts;
        $token_data = json_decode($payload, true);
        
        if (!$token_data || !isset($token_data['expires'])) {
            return false;
        }
        
        // Check if token has expired
        if (time() > $token_data['expires']) {
            return false;
        }
        
        // Verify signature
        $secret_key = 'MySecureSecretKey2024!@#$%^&*()_+{}|:<>?[]\\;\'\",./' . $token_data['timestamp'];
        $expected_signature = hash_hmac('sha256', $payload . $random_bytes, $secret_key);
        
        if (!hash_equals($signature, $expected_signature)) {
            return false;
        }
        
        return $token_data;
    } catch (Exception $e) {
        return false;
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enhanced security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit();
}

// Handle different actions
$action = $data['action'] ?? '';

if ($action === 'validate_token') {
    // Token validation endpoint
    if (!isset($data['token'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token is required']);
        exit();
    }
    
    $token_data = validateToken($data['token']);
    if ($token_data) {
        echo json_encode(['success' => true, 'valid' => true, 'data' => $token_data]);
    } else {
        echo json_encode(['success' => false, 'valid' => false, 'error' => 'Invalid or expired token']);
    }
    exit();
}

// Check if this is a login request
if ($action !== 'login') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// Validate required fields
if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email and password are required']);
    exit();
}

// Sanitize and validate input
$email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
$password = $data['password'];

// Additional input validation
if (strlen($email) > 254) { // RFC 5321 limit
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email address too long']);
    exit();
}

if (strlen($password) > 128) { // Reasonable password length limit
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password too long']);
    exit();
}

// Basic validation
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email and password cannot be empty']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit();
}

// Rate limiting (simple implementation)
$rate_limit_file = 'auth_attempts.json';
$max_attempts = 50;
$lockout_time = 900; // 15 minutes

// Check rate limiting
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$attempts = [];

if (file_exists($rate_limit_file)) {
    $attempts = json_decode(file_get_contents($rate_limit_file), true) ?: [];
}

// Clean old attempts
$current_time = time();
foreach ($attempts as $ip => $data) {
    if ($current_time - $data['last_attempt'] > $lockout_time) {
        unset($attempts[$ip]);
    }
}

// Check if IP is locked out
if (isset($attempts[$client_ip]) && $attempts[$client_ip]['count'] >= $max_attempts) {
    $time_remaining = $lockout_time - ($current_time - $attempts[$client_ip]['last_attempt']);
    if ($time_remaining > 0) {
        http_response_code(429);
        echo json_encode([
            'success' => false, 
            'error' => 'Too many failed attempts. Please try again in ' . ceil($time_remaining / 60) . ' minutes.'
        ]);
        exit();
    }
}

// TODO: Replace with your actual authentication logic
// For now, using simple hardcoded credentials for demonstration
$valid_credentials = [
    'myaccuratebooks@gmail.com' => 'Admin2025#',
    'admin@example.com' => 'password123'
];

$is_valid = false;
if (isset($valid_credentials[$email]) && $valid_credentials[$email] === $password) {
    $is_valid = true;
}

if (!$is_valid) {
    // Record failed attempt
    if (!isset($attempts[$client_ip])) {
        $attempts[$client_ip] = ['count' => 0, 'last_attempt' => 0];
    }
    
    $attempts[$client_ip]['count']++;
    $attempts[$client_ip]['last_attempt'] = $current_time;
    
    // Save attempts
    file_put_contents($rate_limit_file, json_encode($attempts));
    
    // Generic error message to prevent user enumeration
    $remaining_attempts = $max_attempts - $attempts[$client_ip]['count'];
    if ($remaining_attempts > 0) {
        $error_message = 'Invalid credentials. Please check your email and password.';
    } else {
        $error_message = 'Invalid credentials. Account temporarily locked due to multiple failed attempts.';
    }
    
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $error_message]);
    exit();
}

// Clear failed attempts on successful login
if (isset($attempts[$client_ip])) {
    unset($attempts[$client_ip]);
    file_put_contents($rate_limit_file, json_encode($attempts));
}

// Generate secure token
$token_data = [
    'email' => $email,
    'timestamp' => time(),
    'expires' => time() + (8 * 60 * 60), // 8 hours
    'ip' => $client_ip
];

// Enhanced secure token generation
$secret_key = 'MySecureSecretKey2024!@#$%^&*()_+{}|:<>?[]\\;\'\",./' . time(); // In production, use environment variable
$random_bytes = bin2hex(random_bytes(16)); // Add cryptographic randomness
$token_payload = json_encode($token_data);
$signature = hash_hmac('sha256', $token_payload . $random_bytes, $secret_key);
$token = base64_encode($token_payload . '|' . $random_bytes . '|' . $signature);

// Return success response
echo json_encode([
    'success' => true,
    'token' => $token,
    'user' => [
        'email' => $email,
        'name' => 'Administrator',
        'role' => 'admin'
    ],
    'expiresAt' => $token_data['expires'] * 1000 // Convert to milliseconds for JavaScript
]);
?>