<?php
require_once __DIR__ . '/../database/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Simple rate limiting: max 3 registrations per IP per hour
function checkRateLimit($ip) {
    $db = getDatabase();
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    // Create rate_limit table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS registration_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Clean old entries
    $db->exec("DELETE FROM registration_attempts WHERE attempted_at < '$oneHourAgo'");
    
    // Count recent attempts from this IP
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM registration_attempts WHERE ip_address = ? AND attempted_at >= ?");
    $stmt->execute([$ip, $oneHourAgo]);
    $result = $stmt->fetch();
    
    if ($result['count'] >= 3) {
        return false;
    }
    
    return true;
}

function logRegistrationAttempt($ip) {
    $db = getDatabase();
    $stmt = $db->prepare("INSERT INTO registration_attempts (ip_address) VALUES (?)");
    $stmt->execute([$ip]);
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$fullName = trim($input['full_name'] ?? '');
$password = $input['password'] ?? '';

// Get client IP address
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Check rate limit
if (!checkRateLimit($clientIp)) {
    jsonResponse(['error' => 'Too many registration attempts. Please try again later.'], 429);
}

// Validate inputs
if (empty($username)) {
    jsonResponse(['error' => 'Username is required'], 400);
}

if (empty($fullName)) {
    jsonResponse(['error' => 'Full name is required'], 400);
}

if (empty($password)) {
    jsonResponse(['error' => 'Password is required'], 400);
}

if (strlen($password) < 6) {
    jsonResponse(['error' => 'Password must be at least 6 characters long'], 400);
}

// Validate username format (alphanumeric and underscores only)
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    jsonResponse(['error' => 'Username must be 3-20 characters long and contain only letters, numbers, and underscores'], 400);
}

try {
    $db = getDatabase();
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Username already exists'], 409);
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $db->prepare("INSERT INTO users (username, full_name, password) VALUES (?, ?, ?)");
    $stmt->execute([$username, $fullName, $hashedPassword]);
    
    // Log successful registration attempt for rate limiting
    logRegistrationAttempt($clientIp);
    
    jsonResponse([
        'success' => true,
        'message' => 'Registration successful! Please log in with your credentials.',
        'user' => [
            'id' => $db->lastInsertId(),
            'username' => $username,
            'full_name' => $fullName
        ]
    ], 201);
} catch (Exception $e) {
    jsonResponse(['error' => 'Registration failed: ' . $e->getMessage()], 500);
}
