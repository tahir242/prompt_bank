<?php
/**
 * Database Connection Helper
 */

function getDatabase() {
    $dbPath = __DIR__ . '/prompts.db';
    
    try {
        $db = new PDO('sqlite:' . $dbPath);
        $db->exec('PRAGMA journal_mode = WAL;');
        $db->exec('PRAGMA foreign_keys = ON;');
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
/**
 * Check if a user can access a specific prompt based on visibility and shares
 * @param int $userId User ID
 * @param int $promptId Prompt ID
 * @return array|false Access info with level, or false if no access
 */
function canAccessPrompt($userId, $promptId) {
    $db = getDatabase();
    
    // Get prompt with ownership info
    $stmt = $db->prepare("
        SELECT p.*, u.username as owner_username
        FROM prompts p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.is_archived = 0
    ");
    $stmt->execute([$promptId]);
    $prompt = $stmt->fetch();
    
    if (!$prompt) {
        return false;
    }
    
    // Get user info
    $userRole = getUserRole($userId);
    if (!$userRole || !$userRole['is_active']) {
        return false;
    }
    
    // Owner always has edit access
    if (isset($prompt['user_id']) && $prompt['user_id'] == $userId) {
        return ['access_level' => 'edit', 'reason' => 'owner'];
    }
    
    // Admin has edit access to everything
    if ($userRole['role_name'] === 'Admin') {
        return ['access_level' => 'edit', 'reason' => 'admin'];
    }
    
    // Check visibility
    $visibility = $prompt['visibility'] ?? 'private';
    
    // Public prompts - all authenticated users get view access
    if ($visibility === 'public') {
        return ['access_level' => 'view', 'reason' => 'public'];
    }
    
    // Team prompts - team members get configured access level
    if ($visibility === 'team' && isset($prompt['team_id']) && $prompt['team_id'] == $userRole['team_id']) {
        $teamAccessLevel = $prompt['team_access_level'] ?? 'view';
        return ['access_level' => $teamAccessLevel, 'reason' => 'team'];
    }
    
    // Check direct shares
    $shareStmt = $db->prepare("
        SELECT access_level
        FROM prompt_shares
        WHERE prompt_id = ? AND shared_with_user_id = ?
    ");
    $shareStmt->execute([$promptId, $userId]);
    $share = $shareStmt->fetch();
    
    if ($share) {
        return ['access_level' => $share['access_level'], 'reason' => 'direct_share'];
    }
    
    // Check team shares
    if (isset($userRole['team_id'])) {
        $teamShareStmt = $db->prepare("
            SELECT access_level
            FROM prompt_shares
            WHERE prompt_id = ? AND shared_with_team_id = ?
        ");
        $teamShareStmt->execute([$promptId, $userRole['team_id']]);
        $teamShare = $teamShareStmt->fetch();
        
        if ($teamShare) {
            return ['access_level' => $teamShare['access_level'], 'reason' => 'team_share'];
        }
    }
    
    // No access
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

/**
 * Get all shares for a specific prompt
 * @param int $promptId Prompt ID
 * @return array Array of shares with user/team info
 */
function getPromptShares($promptId) {
    $db = getDatabase();
    
    $stmt = $db->prepare("
        SELECT 
            ps.*,
            u.username as shared_with_username,
            u.full_name as shared_with_full_name,
            t.name as shared_with_team_name,
            creator.username as created_by_username
        FROM prompt_shares ps
        LEFT JOIN users u ON ps.shared_with_user_id = u.id
        LEFT JOIN teams t ON ps.shared_with_team_id = t.id
        LEFT JOIN users creator ON ps.created_by = creator.id
        WHERE ps.prompt_id = ?
        ORDER BY ps.created_at DESC
    ");
    $stmt->execute([$promptId]);
    
    return $stmt->fetchAll();
}

/**
 * Create a new prompt share
 * @param int $promptId Prompt ID
 * @param int $createdBy User ID creating the share
 * @param int|null $sharedWithUserId User ID to share with (null if team share)
 * @param int|null $sharedWithTeamId Team ID to share with (null if user share)
 * @param string $accessLevel 'view' or 'edit'
 * @return int|false Share ID if successful, false on failure
 */
function sharePrompt($promptId, $createdBy, $sharedWithUserId, $sharedWithTeamId, $accessLevel = 'view') {
    $db = getDatabase();
    
    // Validate access level
    if (!in_array($accessLevel, ['view', 'edit'])) {
        return false;
    }
    
    // Ensure only one of user_id or team_id is set
    if (($sharedWithUserId && $sharedWithTeamId) || (!$sharedWithUserId && !$sharedWithTeamId)) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO prompt_shares (prompt_id, shared_with_user_id, shared_with_team_id, access_level, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$promptId, $sharedWithUserId, $sharedWithTeamId, $accessLevel, $createdBy]);
        
        return $db->lastInsertId();
    } catch (PDOException $e) {
        // Handle duplicate shares
        if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
            return false;
        }
        throw $e;
    }
}

/**
 * Revoke a prompt share
 * @param int $shareId Share ID to revoke
 * @return bool True if successful
 */
function revokeShare($shareId) {
    $db = getDatabase();
    
    $stmt = $db->prepare("DELETE FROM prompt_shares WHERE id = ?");
    return $stmt->execute([$shareId]);
}

/**
 * Request access to a prompt
 * @param int $promptId Prompt ID
 * @param int $userId User ID requesting access
 * @param string|null $message Optional message with the request
 * @return int|false Request ID if successful, false on failure
 */
function requestAccess($promptId, $userId, $message = null) {
    $db = getDatabase();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO access_requests (prompt_id, user_id, message, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$promptId, $userId, $message]);
        
        return $db->lastInsertId();
    } catch (PDOException $e) {
        // Handle duplicate requests
        if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
            return false;
        }
        throw $e;
    }
}

/**
 * Get pending access requests for prompts owned by a user
 * @param int $userId Owner user ID
 * @return array Array of pending requests
 */
function getPendingRequests($userId) {
    $db = getDatabase();
    
    $stmt = $db->prepare("
        SELECT 
            ar.*,
            p.title as prompt_title,
            u.username as requester_username,
            u.full_name as requester_full_name
        FROM access_requests ar
        JOIN prompts p ON ar.prompt_id = p.id
        JOIN users u ON ar.user_id = u.id
        WHERE p.user_id = ? AND ar.status = 'pending' AND p.is_archived = 0
        ORDER BY ar.created_at DESC
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetchAll();
}

/**
 * Approve an access request and create a share
 * @param int $requestId Request ID
 * @param int $resolvedBy User ID approving the request
 * @param string $accessLevel 'view' or 'edit'
 * @return bool True if successful
 */
function approveRequest($requestId, $resolvedBy, $accessLevel = 'view') {
    $db = getDatabase();
    
    try {
        $db->beginTransaction();
        
        // Get request details
        $stmt = $db->prepare("SELECT * FROM access_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        
        if (!$request) {
            $db->rollBack();
            return false;
        }
        
        // Update request status
        $updateStmt = $db->prepare("
            UPDATE access_requests
            SET status = 'approved', resolved_at = datetime('now'), resolved_by = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$resolvedBy, $requestId]);
        
        // Create share
        $shareStmt = $db->prepare("
            INSERT INTO prompt_shares (prompt_id, shared_with_user_id, access_level, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $shareStmt->execute([$request['prompt_id'], $request['user_id'], $accessLevel, $resolvedBy]);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

/**
 * Deny an access request
 * @param int $requestId Request ID
 * @param int $resolvedBy User ID denying the request
 * @return bool True if successful
 */
function denyRequest($requestId, $resolvedBy) {
    $db = getDatabase();
    
    $stmt = $db->prepare("
        UPDATE access_requests
        SET status = 'denied', resolved_at = datetime('now'), resolved_by = ?
        WHERE id = ? AND status = 'pending'
    ");
    
    return $stmt->execute([$resolvedBy, $requestId]);
}
