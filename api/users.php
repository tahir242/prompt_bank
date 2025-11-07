<?php
/**
 * Users API Endpoint
 * Handles user management operations (Admin only)
 */

require_once __DIR__ . '/../database/db.php';
requireAuth();

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

// GET - List all users (Admin only)
if ($method === 'GET') {
    try {
        // Require Admin role
        requireRole(['Admin']);
        
        $stmt = $db->query("
            SELECT 
                u.id,
                u.username,
                u.full_name,
                u.is_active,
                u.created_at,
                u.role_id,
                u.team_id,
                r.name as role_name,
                r.description as role_description,
                t.name as team_name,
                (SELECT COUNT(*) FROM prompts WHERE user_id = u.id AND is_archived = 0) as prompt_count
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN teams t ON u.team_id = t.id
            ORDER BY u.created_at DESC
        ");
        $users = $stmt->fetchAll();
        
        jsonResponse(['users' => $users]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Forbidden') !== false || strpos($e->getMessage(), 'Unauthorized') !== false) {
            jsonResponse(['error' => $e->getMessage()], 403);
        } else {
            jsonResponse(['error' => 'Failed to fetch users: ' . $e->getMessage()], 500);
        }
    }
}

// PUT - Update user (role, team, status) (Admin only)
elseif ($method === 'PUT') {
    try {
        // Require Admin role
        $adminUser = requireRole(['Admin']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['id'] ?? null;
        $roleId = $input['role_id'] ?? null;
        $teamId = $input['team_id'] ?? null;
        $isActive = $input['is_active'] ?? null;
        
        // Validate input
        if (empty($userId)) {
            jsonResponse(['error' => 'User ID is required'], 400);
        }
        
        // Check if user exists
        $checkStmt = $db->prepare("
            SELECT u.id, u.username, u.role_id, u.team_id, u.is_active, r.name as role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $checkStmt->execute([$userId]);
        $existingUser = $checkStmt->fetch();
        
        if (!$existingUser) {
            jsonResponse(['error' => 'User not found'], 404);
        }
        
        // Prevent admin from deactivating themselves
        if ($userId == $_SESSION['user_id'] && $isActive === 0) {
            jsonResponse(['error' => 'You cannot deactivate your own account'], 400);
        }
        
        // Prevent admin from changing their own role (safety measure)
        if ($userId == $_SESSION['user_id'] && $roleId !== null && $roleId != $existingUser['role_id']) {
            jsonResponse(['error' => 'You cannot change your own role'], 400);
        }
        
        $updates = [];
        $params = [];
        $auditDetails = [];
        
        // Handle role change
        if ($roleId !== null && $roleId != $existingUser['role_id']) {
            // Validate role exists
            $roleStmt = $db->prepare("SELECT id, name FROM roles WHERE id = ?");
            $roleStmt->execute([$roleId]);
            $newRole = $roleStmt->fetch();
            
            if (!$newRole) {
                jsonResponse(['error' => 'Invalid role ID'], 400);
            }
            
            $updates[] = "role_id = ?";
            $params[] = $roleId;
            $auditDetails[] = "Role changed: {$existingUser['role_name']} → {$newRole['name']}";
        }
        
        // Handle team assignment
        if ($teamId !== null) {
            if ($teamId === 0 || $teamId === '') {
                // Unassign from team
                $updates[] = "team_id = NULL";
                $auditDetails[] = "Unassigned from team";
            } elseif ($teamId != $existingUser['team_id']) {
                // Validate team exists
                $teamStmt = $db->prepare("SELECT id, name FROM teams WHERE id = ?");
                $teamStmt->execute([$teamId]);
                $newTeam = $teamStmt->fetch();
                
                if (!$newTeam) {
                    jsonResponse(['error' => 'Invalid team ID'], 400);
                }
                
                $updates[] = "team_id = ?";
                $params[] = $teamId;
                $auditDetails[] = "Assigned to team: {$newTeam['name']}";
            }
        }
        
        // Handle activation status
        if ($isActive !== null && $isActive != $existingUser['is_active']) {
            $updates[] = "is_active = ?";
            $params[] = $isActive ? 1 : 0;
            $auditDetails[] = $isActive ? "Account activated" : "Account deactivated";
        }
        
        if (empty($updates)) {
            jsonResponse(['error' => 'No changes specified'], 400);
        }
        
        // Update user
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Log audit event
        $auditMessage = "Updated user {$existingUser['username']} (ID: $userId): " . implode(', ', $auditDetails);
        logAudit($_SESSION['user_id'], 'user_updated', $auditMessage);
        
        jsonResponse([
            'success' => true,
            'message' => 'User updated successfully',
            'changes' => $auditDetails
        ]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Forbidden') !== false || strpos($e->getMessage(), 'Unauthorized') !== false) {
            jsonResponse(['error' => $e->getMessage()], 403);
        } else {
            jsonResponse(['error' => 'Failed to update user: ' . $e->getMessage()], 500);
        }
    }
}

else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
