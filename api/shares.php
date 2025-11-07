<?php
/**
 * Shares API Endpoint
 * Handles prompt sharing operations
 */

require_once __DIR__ . '/../database/db.php';
requireAuth();

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

// GET - List shares for a prompt
if ($method === 'GET') {
    $promptId = $_GET['prompt_id'] ?? null;
    
    if (!$promptId) {
        jsonResponse(['error' => 'prompt_id is required'], 400);
    }
    
    // Check if user can access this prompt
    $access = canAccessPrompt($_SESSION['user_id'], $promptId);
    if (!$access) {
        jsonResponse(['error' => 'Forbidden: You do not have access to this prompt'], 403);
    }
    
    // Only owner and editors with edit access can view shares
    if ($access['reason'] !== 'owner' && $access['access_level'] !== 'edit') {
        jsonResponse(['error' => 'Forbidden: Only owners and editors can view shares'], 403);
    }
    
    try {
        $shares = getPromptShares($promptId);
        jsonResponse(['shares' => $shares]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch shares: ' . $e->getMessage()], 500);
    }
}

// POST - Create new share
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $promptId = $input['prompt_id'] ?? null;
    $sharedWithUserId = $input['shared_with_user_id'] ?? null;
    $sharedWithTeamId = $input['shared_with_team_id'] ?? null;
    $accessLevel = $input['access_level'] ?? 'view';
    
    // Validate input
    if (!$promptId) {
        jsonResponse(['error' => 'prompt_id is required'], 400);
    }
    
    if (!$sharedWithUserId && !$sharedWithTeamId) {
        jsonResponse(['error' => 'Either shared_with_user_id or shared_with_team_id is required'], 400);
    }
    
    if ($sharedWithUserId && $sharedWithTeamId) {
        jsonResponse(['error' => 'Cannot share with both user and team simultaneously'], 400);
    }
    
    if (!in_array($accessLevel, ['view', 'edit'])) {
        jsonResponse(['error' => 'access_level must be either "view" or "edit"'], 400);
    }
    
    // Check if user can manage shares for this prompt
    $access = canAccessPrompt($_SESSION['user_id'], $promptId);
    if (!$access || $access['reason'] !== 'owner') {
        jsonResponse(['error' => 'Forbidden: Only the owner can create shares'], 403);
    }
    
    // Validate user/team exists
    if ($sharedWithUserId) {
        $userCheck = $db->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
        $userCheck->execute([$sharedWithUserId]);
        if (!$userCheck->fetch()) {
            jsonResponse(['error' => 'User not found or inactive'], 404);
        }
        
        // Prevent sharing with self
        if ($sharedWithUserId == $_SESSION['user_id']) {
            jsonResponse(['error' => 'Cannot share with yourself'], 400);
        }
    }
    
    if ($sharedWithTeamId) {
        $teamCheck = $db->prepare("SELECT id FROM teams WHERE id = ?");
        $teamCheck->execute([$sharedWithTeamId]);
        if (!$teamCheck->fetch()) {
            jsonResponse(['error' => 'Team not found'], 404);
        }
    }
    
    try {
        $shareId = sharePrompt($promptId, $_SESSION['user_id'], $sharedWithUserId, $sharedWithTeamId, $accessLevel);
        
        if ($shareId === false) {
            jsonResponse(['error' => 'Share already exists or failed to create'], 409);
        }
        
        // Log audit event
        $shareType = $sharedWithUserId ? "user $sharedWithUserId" : "team $sharedWithTeamId";
        logAudit($_SESSION['user_id'], 'share_created', "Shared prompt $promptId with $shareType ($accessLevel access)");
        
        // Fetch the created share
        $shareStmt = $db->prepare("
            SELECT 
                ps.*,
                u.username as shared_with_username,
                u.full_name as shared_with_full_name,
                t.name as shared_with_team_name
            FROM prompt_shares ps
            LEFT JOIN users u ON ps.shared_with_user_id = u.id
            LEFT JOIN teams t ON ps.shared_with_team_id = t.id
            WHERE ps.id = ?
        ");
        $shareStmt->execute([$shareId]);
        $share = $shareStmt->fetch();
        
        jsonResponse([
            'success' => true,
            'message' => 'Share created successfully',
            'share' => $share
        ], 201);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to create share: ' . $e->getMessage()], 500);
    }
}

// DELETE - Revoke share
elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $shareId = $input['share_id'] ?? null;
    
    if (!$shareId) {
        jsonResponse(['error' => 'share_id is required'], 400);
    }
    
    try {
        // Get share details to verify ownership
        $shareStmt = $db->prepare("
            SELECT ps.*, p.user_id as prompt_owner_id
            FROM prompt_shares ps
            JOIN prompts p ON ps.prompt_id = p.id
            WHERE ps.id = ?
        ");
        $shareStmt->execute([$shareId]);
        $share = $shareStmt->fetch();
        
        if (!$share) {
            jsonResponse(['error' => 'Share not found'], 404);
        }
        
        // Only prompt owner can revoke shares
        if ($share['prompt_owner_id'] != $_SESSION['user_id']) {
            // Check if user is admin
            $userRole = getUserRole($_SESSION['user_id']);
            if ($userRole['role_name'] !== 'Admin') {
                jsonResponse(['error' => 'Forbidden: Only the prompt owner can revoke shares'], 403);
            }
        }
        
        $success = revokeShare($shareId);
        
        if ($success) {
            // Log audit event
            $shareType = $share['shared_with_user_id'] ? "user {$share['shared_with_user_id']}" : "team {$share['shared_with_team_id']}";
            logAudit($_SESSION['user_id'], 'share_revoked', "Revoked share for prompt {$share['prompt_id']} from $shareType");
            
            jsonResponse([
                'success' => true,
                'message' => 'Share revoked successfully'
            ]);
        } else {
            jsonResponse(['error' => 'Failed to revoke share'], 500);
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to revoke share: ' . $e->getMessage()], 500);
    }
}

// Method not allowed
else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
