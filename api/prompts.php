<?php
require_once __DIR__ . '/../database/db.php';
requireAuth();

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

// GET - List all prompts or get single prompt
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        // Get single prompt with details
        $stmt = $db->prepare("
            SELECT p.*, c.name as category_name 
            FROM prompts p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ? AND p.is_archived = 0
        ");
        $stmt->execute([$_GET['id']]);
        $prompt = $stmt->fetch();
        
        if ($prompt) {
            // Get version history
            $versionStmt = $db->prepare("
                SELECT pv.*, u.username 
                FROM prompt_versions pv 
                JOIN users u ON pv.user_id = u.id 
                WHERE pv.prompt_id = ? 
                ORDER BY pv.version_number DESC
            ");
            $versionStmt->execute([$_GET['id']]);
            $prompt['versions'] = $versionStmt->fetchAll();
            
            jsonResponse($prompt);
        } else {
            jsonResponse(['error' => 'Prompt not found'], 404);
        }
    } else {
        // List all prompts
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        
        $sql = "
            SELECT p.*, c.name as category_name,
                   (SELECT MAX(version_number) FROM prompt_versions WHERE prompt_id = p.id) as current_version
            FROM prompts p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.is_archived = 0
        ";
        
        $params = [];
        
        if ($search) {
            $sql .= " AND (p.title LIKE ? OR p.content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($category) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY p.updated_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $prompts = $stmt->fetchAll();
        
        jsonResponse($prompts);
    }
}

// POST - Create new prompt
if ($method === 'POST') {
    // Check create_prompt permission
    if (!hasPermission($_SESSION['user_id'], 'create_prompt')) {
        jsonResponse(['error' => 'Forbidden: You do not have permission to create prompts'], 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    $categoryId = $input['category_id'] ?? null;
    
    if (empty($title) || empty($content)) {
        jsonResponse(['error' => 'Title and content are required'], 400);
    }
    
    try {
        // Get user's role and team info
        $userRole = getUserRole($_SESSION['user_id']);
        $teamId = $userRole['team_id'] ?? null;
        
        $db->beginTransaction();
        
        // Insert prompt with user_id and team_id
        $stmt = $db->prepare("
            INSERT INTO prompts (title, content, category_id, user_id, team_id, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))
        ");
        $stmt->execute([$title, $content, $categoryId, $_SESSION['user_id'], $teamId]);
        $promptId = $db->lastInsertId();
        
        // Create initial version
        $versionStmt = $db->prepare("
            INSERT INTO prompt_versions (prompt_id, version_number, content, user_id, created_at) 
            VALUES (?, 1, ?, ?, datetime('now'))
        ");
        $versionStmt->execute([$promptId, $content, $_SESSION['user_id']]);
        
        $db->commit();
        
        jsonResponse([
            'success' => true,
            'id' => $promptId,
            'message' => 'Prompt created successfully'
        ], 201);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Failed to create prompt: ' . $e->getMessage()], 500);
    }
}

// PUT - Update prompt
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $title = $input['title'] ?? '';
    $content = $input['content'] ?? '';
    $categoryId = $input['category_id'] ?? null;
    
    if (!$id || empty($title) || empty($content)) {
        jsonResponse(['error' => 'ID, title, and content are required'], 400);
    }
    
    try {
        // Check if user can access this prompt (checks role + team permissions)
        if (!canAccessPrompt($_SESSION['user_id'], $id)) {
            jsonResponse(['error' => 'Forbidden: You do not have permission to edit this prompt'], 403);
        }
        
        // Check edit permission (edit_team_prompt for editors, or full access for admin)
        $userRole = getUserRole($_SESSION['user_id']);
        $isAdmin = $userRole['role_name'] === 'Admin';
        $canEdit = $isAdmin || hasPermission($_SESSION['user_id'], 'edit_team_prompt');
        
        if (!$canEdit) {
            jsonResponse(['error' => 'Forbidden: You do not have permission to edit prompts'], 403);
        }
        
        $db->beginTransaction();
        
        // Get current version number
        $versionStmt = $db->prepare("
            SELECT MAX(version_number) as max_version 
            FROM prompt_versions 
            WHERE prompt_id = ?
        ");
        $versionStmt->execute([$id]);
        $versionResult = $versionStmt->fetch();
        $nextVersion = ($versionResult['max_version'] ?? 0) + 1;
        
        // Update prompt
        $stmt = $db->prepare("
            UPDATE prompts 
            SET title = ?, content = ?, category_id = ?, updated_at = datetime('now') 
            WHERE id = ? AND is_archived = 0
        ");
        $stmt->execute([$title, $content, $categoryId, $id]);
        
        // Create new version
        $versionStmt = $db->prepare("
            INSERT INTO prompt_versions (prompt_id, version_number, content, user_id, created_at) 
            VALUES (?, ?, ?, ?, datetime('now'))
        ");
        $versionStmt->execute([$id, $nextVersion, $content, $_SESSION['user_id']]);
        
        $db->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Prompt updated successfully'
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Failed to update prompt: ' . $e->getMessage()], 500);
    }
}

// DELETE - Soft delete (archive) prompt
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['error' => 'ID is required'], 400);
    }
    
    try {
        // Check permissions: Admins can delete any prompt, Editors can delete team prompts
        $userRole = getUserRole($_SESSION['user_id']);
        $isAdmin = $userRole['role_name'] === 'Admin';
        
        if ($isAdmin) {
            // Admin can delete anything
            $stmt = $db->prepare("UPDATE prompts SET is_archived = 1 WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            // Editors can only delete team prompts they have access to
            if (!hasPermission($_SESSION['user_id'], 'delete_team_prompt')) {
                jsonResponse(['error' => 'Forbidden: You do not have permission to delete prompts'], 403);
            }
            
            if (!canAccessPrompt($_SESSION['user_id'], $id)) {
                jsonResponse(['error' => 'Forbidden: You can only delete prompts from your team'], 403);
            }
            
            $stmt = $db->prepare("UPDATE prompts SET is_archived = 1 WHERE id = ?");
            $stmt->execute([$id]);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Prompt deleted successfully'
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to delete prompt: ' . $e->getMessage()], 500);
    }
}
