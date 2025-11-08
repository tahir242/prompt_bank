<?php
/**
 * Collaborative Editing Tracking API
 * 
 * Tracks which users are actively editing which prompts in real-time.
 * Stale records (>5 minutes) are automatically cleaned up.
 * 
 * Endpoints:
 * - POST: Update user's active editing status (heartbeat)
 * - GET: List active collaborators for a prompt
 * - DELETE: Remove user from active collaborators
 */

require_once '../database/db.php';

header('Content-Type: application/json');

$db = getDatabase();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];

// POST - Update active collaborator status (heartbeat)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $promptId = $input['prompt_id'] ?? null;
    
    if (!$promptId) {
        jsonResponse(['error' => 'Prompt ID is required'], 400);
    }
    
    try {
        // Check if user has edit access to the prompt
        $access = canAccessPrompt($userId, $promptId);
        if (!$access || $access['access_level'] !== 'edit') {
            jsonResponse(['error' => 'Forbidden: You need edit access to collaborate on this prompt'], 403);
        }
        
        // Cleanup stale records (>5 minutes)
        $cleanupStmt = $db->prepare("
            DELETE FROM prompt_collaborators 
            WHERE last_activity < datetime('now', '-5 minutes')
        ");
        $cleanupStmt->execute();
        
        // Insert or update collaborator record
        $stmt = $db->prepare("
            INSERT INTO prompt_collaborators (prompt_id, user_id, last_activity)
            VALUES (?, ?, datetime('now'))
            ON CONFLICT(prompt_id, user_id) 
            DO UPDATE SET last_activity = datetime('now')
        ");
        $stmt->execute([$promptId, $userId]);
        
        // Get updated list of active collaborators
        $collaborators = getActiveCollaborators($promptId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Collaborator status updated',
            'collaborators' => $collaborators
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to update collaborator status: ' . $e->getMessage()], 500);
    }
}

// GET - List active collaborators for a prompt
if ($method === 'GET') {
    $promptId = $_GET['prompt_id'] ?? null;
    
    if (!$promptId) {
        jsonResponse(['error' => 'Prompt ID is required'], 400);
    }
    
    try {
        // Check if user has access to view the prompt
        $access = canAccessPrompt($userId, $promptId);
        if (!$access) {
            jsonResponse(['error' => 'Forbidden: You do not have access to this prompt'], 403);
        }
        
        // Cleanup stale records before returning list
        $cleanupStmt = $db->prepare("
            DELETE FROM prompt_collaborators 
            WHERE last_activity < datetime('now', '-5 minutes')
        ");
        $cleanupStmt->execute();
        
        // Get active collaborators
        $collaborators = getActiveCollaborators($promptId);
        
        jsonResponse([
            'collaborators' => $collaborators
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch collaborators: ' . $e->getMessage()], 500);
    }
}

// DELETE - Remove user from active collaborators
if ($method === 'DELETE') {
    $promptId = $_GET['prompt_id'] ?? null;
    
    if (!$promptId) {
        jsonResponse(['error' => 'Prompt ID is required'], 400);
    }
    
    try {
        // Remove collaborator record
        $stmt = $db->prepare("
            DELETE FROM prompt_collaborators 
            WHERE prompt_id = ? AND user_id = ?
        ");
        $stmt->execute([$promptId, $userId]);
        
        // Get updated list
        $collaborators = getActiveCollaborators($promptId);
        
        jsonResponse([
            'success' => true,
            'message' => 'Removed from active collaborators',
            'collaborators' => $collaborators
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to remove collaborator: ' . $e->getMessage()], 500);
    }
}

/**
 * Helper function to get active collaborators for a prompt
 */
function getActiveCollaborators($promptId) {
    $db = getDatabase();
    
    $stmt = $db->prepare("
        SELECT 
            pc.user_id,
            pc.last_activity,
            u.username,
            u.full_name,
            CAST((julianday('now') - julianday(pc.last_activity)) * 86400 AS INTEGER) as seconds_ago
        FROM prompt_collaborators pc
        JOIN users u ON pc.user_id = u.id
        WHERE pc.prompt_id = ?
            AND pc.last_activity >= datetime('now', '-5 minutes')
        ORDER BY pc.last_activity DESC
    ");
    $stmt->execute([$promptId]);
    
    return $stmt->fetchAll();
}
