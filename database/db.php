<?php
/**
 * Database Connection Helper
 */

function getDatabase() {
    $dbPath = __DIR__ . '/prompts.db';
    
    try {
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $db;
    } catch (PDOException $e) {
        die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
    }
}

function requireAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get user's complete role and team information
 * @param int $userId User ID
 * @return array|null User role data with permissions, or null if user not found
 */
function getUserRole($userId) {
    $db = getDatabase();
    
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.full_name,
            u.role_id,
            u.team_id,
            u.is_active,
            r.name as role_name,
            r.description as role_description,
            r.permissions,
            t.name as team_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN teams t ON u.team_id = t.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['permissions']) {
        $user['permissions'] = json_decode($user['permissions'], true);
    }
    
    return $user;
}

/**
 * Check if user has a specific permission
 * @param int $userId User ID
 * @param string $permission Permission name (e.g., 'create_prompt', 'manage_users')
 * @return bool True if user has the permission
 */
function hasPermission($userId, $permission) {
    $userRole = getUserRole($userId);
    
    if (!$userRole || !$userRole['is_active']) {
        return false;
    }
    
    if (!isset($userRole['permissions']) || !is_array($userRole['permissions'])) {
        return false;
    }
    
    return isset($userRole['permissions'][$permission]) && $userRole['permissions'][$permission] === true;
}

/**
 * Require user to have one of the specified roles
 * Throws exception if user doesn't have required role or is inactive
 * @param array $allowedRoles Array of role names (e.g., ['Admin', 'Editor'])
 * @throws Exception If user doesn't have required role or is inactive
 */
function requireRole($allowedRoles) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Unauthorized: User not logged in');
    }
    
    $userRole = getUserRole($_SESSION['user_id']);
    
    if (!$userRole) {
        http_response_code(401);
        throw new Exception('Unauthorized: User not found');
    }
    
    if (!$userRole['is_active']) {
        http_response_code(403);
        throw new Exception('Forbidden: User account is deactivated');
    }
    
    if (!in_array($userRole['role_name'], $allowedRoles)) {
        http_response_code(403);
        throw new Exception('Forbidden: Insufficient permissions. Required role: ' . implode(' or ', $allowedRoles));
    }
    
    return $userRole;
}

/**
 * Check if user can access a specific prompt based on team membership
 * @param int $userId User ID
 * @param int $promptId Prompt ID
 * @return bool True if user can access the prompt
 */
function canAccessPrompt($userId, $promptId) {
    $db = getDatabase();
    $userRole = getUserRole($userId);
    
    if (!$userRole || !$userRole['is_active']) {
        return false;
    }
    
    // Admin can access all prompts
    if ($userRole['role_name'] === 'Admin') {
        return true;
    }
    
    // Get prompt details (will be fully functional in Phase 3 when prompts have team_id)
    $stmt = $db->prepare("SELECT id, user_id, team_id FROM prompts WHERE id = ? AND is_archived = 0");
    $stmt->execute([$promptId]);
    $prompt = $stmt->fetch();
    
    if (!$prompt) {
        return false;
    }
    
    // Check if prompt has team_id column (added in Phase 3)
    if (isset($prompt['team_id'])) {
        // Editor can access team prompts
        if ($userRole['role_name'] === 'Editor' && $prompt['team_id'] == $userRole['team_id']) {
            return true;
        }
        
        // User can access their own prompts
        if (isset($prompt['user_id']) && $prompt['user_id'] == $userId) {
            return true;
        }
    } else {
        // Fallback for prompts without team_id (backward compatibility)
        // Viewers and Editors can see all prompts (read-only for viewers)
        return true;
    }
    
    // Viewers can view all prompts (read-only)
    if ($userRole['role_name'] === 'Viewer') {
        return true;
    }
    
    return false;
}

/**
 * Log an audit event
 * @param int $userId User ID who performed the action
 * @param string $action Action performed (e.g., 'user_created', 'role_changed')
 * @param string $details Additional details about the action
 * @param string $ipAddress IP address of the user
 * @return bool True if logged successfully
 */
function logAudit($userId, $action, $details = null, $ipAddress = null) {
    $db = getDatabase();
    
    if ($ipAddress === null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    $stmt = $db->prepare("
        INSERT INTO audit_log (user_id, action, details, ip_address)
        VALUES (?, ?, ?, ?)
    ");
    
    return $stmt->execute([$userId, $action, $details, $ipAddress]);
}
