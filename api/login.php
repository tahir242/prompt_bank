<?php
require_once __DIR__ . '/../database/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    jsonResponse(['error' => 'Username and password are required'], 400);
}

try {
    $db = getDatabase();
    
    // Fetch user with role and team information
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.password, u.is_active, u.role_id, u.team_id,
               r.name as role_name, r.permissions,
               t.name as team_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN teams t ON u.team_id = t.id
        WHERE u.username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Check if user is active
        if (!$user['is_active']) {
            logAudit($user['id'], 'login_blocked', 'Inactive user login attempt', $_SERVER['REMOTE_ADDR'] ?? '');
            jsonResponse(['error' => 'Account is inactive. Please contact administrator.'], 403);
        }
        
        session_set_cookie_params(['path' => '/']);
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['team_id'] = $user['team_id'];
        
        // Parse permissions JSON
        $permissions = $user['permissions'] ? json_decode($user['permissions'], true) : [];
        
        // Log successful login
        logAudit($user['id'], 'login', 'User logged in', $_SERVER['REMOTE_ADDR'] ?? '');
        
        jsonResponse([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role_name' => $user['role_name'],
                'permissions' => $permissions,
                'team_id' => $user['team_id'],
                'team_name' => $user['team_name']
            ]
        ]);
    } else {
        jsonResponse(['error' => 'Invalid username or password'], 401);
    }
} catch (Exception $e) {
    jsonResponse(['error' => 'Login failed: ' . $e->getMessage()], 500);
}
