<?php
/**
 * Teams API Endpoint
 * Handles team CRUD operations (Admin only)
 */

require_once __DIR__ . '/../database/db.php';
requireAuth();

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

// GET - List all teams
if ($method === 'GET') {
    try {
        // Any authenticated user can view teams
        $stmt = $db->query("
            SELECT 
                t.id,
                t.name,
                t.created_at,
                t.created_by,
                u.username as created_by_username,
                u.full_name as created_by_name,
                (SELECT COUNT(*) FROM users WHERE team_id = t.id) as member_count
            FROM teams t
            LEFT JOIN users u ON t.created_by = u.id
            ORDER BY t.name ASC
        ");
        $teams = $stmt->fetchAll();
        
        jsonResponse(['teams' => $teams]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch teams: ' . $e->getMessage()], 500);
    }
}

// POST - Create new team (Admin only)
elseif ($method === 'POST') {
    try {
        // Require Admin role
        requireRole(['Admin']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        
        // Validate input
        if (empty($name)) {
            jsonResponse(['error' => 'Team name is required'], 400);
        }
        
        if (strlen($name) < 2 || strlen($name) > 50) {
            jsonResponse(['error' => 'Team name must be between 2 and 50 characters'], 400);
        }
        
        // Check if team name already exists
        $checkStmt = $db->prepare("SELECT id FROM teams WHERE name = ?");
        $checkStmt->execute([$name]);
        if ($checkStmt->fetch()) {
            jsonResponse(['error' => 'Team name already exists'], 409);
        }
        
        // Create team
        $stmt = $db->prepare("INSERT INTO teams (name, created_by) VALUES (?, ?)");
        $stmt->execute([$name, $_SESSION['user_id']]);
        $teamId = $db->lastInsertId();
        
        // Log audit event
        logAudit($_SESSION['user_id'], 'team_created', "Created team: $name (ID: $teamId)");
        
        // Fetch the created team
        $fetchStmt = $db->prepare("
            SELECT 
                t.id,
                t.name,
                t.created_at,
                t.created_by,
                u.username as created_by_username
            FROM teams t
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.id = ?
        ");
        $fetchStmt->execute([$teamId]);
        $team = $fetchStmt->fetch();
        
        jsonResponse([
            'success' => true,
            'message' => 'Team created successfully',
            'team' => $team
        ], 201);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Forbidden') !== false) {
            jsonResponse(['error' => $e->getMessage()], 403);
        } else {
            jsonResponse(['error' => 'Failed to create team: ' . $e->getMessage()], 500);
        }
    }
}

// PUT - Update team (Admin only)
elseif ($method === 'PUT') {
    try {
        // Require Admin role
        requireRole(['Admin']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $name = trim($input['name'] ?? '');
        
        // Validate input
        if (empty($id)) {
            jsonResponse(['error' => 'Team ID is required'], 400);
        }
        
        if (empty($name)) {
            jsonResponse(['error' => 'Team name is required'], 400);
        }
        
        if (strlen($name) < 2 || strlen($name) > 50) {
            jsonResponse(['error' => 'Team name must be between 2 and 50 characters'], 400);
        }
        
        // Check if team exists
        $checkStmt = $db->prepare("SELECT id, name FROM teams WHERE id = ?");
        $checkStmt->execute([$id]);
        $existingTeam = $checkStmt->fetch();
        
        if (!$existingTeam) {
            jsonResponse(['error' => 'Team not found'], 404);
        }
        
        // Check if new name conflicts with another team
        $conflictStmt = $db->prepare("SELECT id FROM teams WHERE name = ? AND id != ?");
        $conflictStmt->execute([$name, $id]);
        if ($conflictStmt->fetch()) {
            jsonResponse(['error' => 'Team name already exists'], 409);
        }
        
        // Update team
        $stmt = $db->prepare("UPDATE teams SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        
        // Log audit event
        logAudit($_SESSION['user_id'], 'team_updated', "Updated team: {$existingTeam['name']} → $name (ID: $id)");
        
        jsonResponse([
            'success' => true,
            'message' => 'Team updated successfully'
        ]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Forbidden') !== false) {
            jsonResponse(['error' => $e->getMessage()], 403);
        } else {
            jsonResponse(['error' => 'Failed to update team: ' . $e->getMessage()], 500);
        }
    }
}

// DELETE - Delete team (Admin only)
elseif ($method === 'DELETE') {
    try {
        // Require Admin role
        requireRole(['Admin']);
        
        $id = $_GET['id'] ?? null;
        
        if (empty($id)) {
            jsonResponse(['error' => 'Team ID is required'], 400);
        }
        
        // Check if team exists
        $checkStmt = $db->prepare("SELECT id, name FROM teams WHERE id = ?");
        $checkStmt->execute([$id]);
        $team = $checkStmt->fetch();
        
        if (!$team) {
            jsonResponse(['error' => 'Team not found'], 404);
        }
        
        // Check if team has members
        $memberStmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE team_id = ?");
        $memberStmt->execute([$id]);
        $memberCount = $memberStmt->fetch()['count'];
        
        if ($memberCount > 0) {
            jsonResponse([
                'error' => "Cannot delete team: $memberCount user(s) still assigned to this team. Please reassign users first."
            ], 409);
        }
        
        // Delete team
        $stmt = $db->prepare("DELETE FROM teams WHERE id = ?");
        $stmt->execute([$id]);
        
        // Log audit event
        logAudit($_SESSION['user_id'], 'team_deleted', "Deleted team: {$team['name']} (ID: $id)");
        
        jsonResponse([
            'success' => true,
            'message' => 'Team deleted successfully'
        ]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Forbidden') !== false) {
            jsonResponse(['error' => $e->getMessage()], 403);
        } else {
            jsonResponse(['error' => 'Failed to delete team: ' . $e->getMessage()], 500);
        }
    }
}

else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
