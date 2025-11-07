<?php
require_once __DIR__ . '/../database/db.php';
requireAuth();

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

// GET - List all prompts or get single prompt
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        // Get single prompt with details
        $promptId = $_GET['id'];
        
        // Check access using new visibility/sharing system
        $access = canAccessPrompt($_SESSION['user_id'], $promptId);
        if (!$access) {
            jsonResponse(['error' => 'Forbidden: You do not have access to this prompt'], 403);
        }
        
        $stmt = $db->prepare("
            SELECT p.*, c.name as category_name, u.username as owner_username, u.full_name as owner_full_name,
                   t.name as team_name
            FROM prompts p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN teams t ON p.team_id = t.id
            WHERE p.id = ? AND p.is_archived = 0
        ");
        $stmt->execute([$promptId]);
        $prompt = $stmt->fetch();
        
        if ($prompt) {
            // Add access information
            $prompt['user_access_level'] = $access['access_level'];
            $prompt['user_access_reason'] = $access['reason'];
            
            // Get version history
            $versionStmt = $db->prepare("
                SELECT pv.*, u.username 
                FROM prompt_versions pv 
                JOIN users u ON pv.user_id = u.id 
                WHERE pv.prompt_id = ? 
                ORDER BY pv.version_number DESC
            ");
            $versionStmt->execute([$promptId]);
            $prompt['versions'] = $versionStmt->fetchAll();
            
            // Get shares (if user is owner or has edit access)
            if ($access['reason'] === 'owner' || $access['access_level'] === 'edit') {
                $prompt['shares'] = getPromptShares($promptId);
            }
            
            jsonResponse($prompt);
        } else {
            jsonResponse(['error' => 'Prompt not found'], 404);
        }
    } else {
        // List all accessible prompts based on visibility and shares
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $visibility = $_GET['visibility'] ?? ''; // Filter by visibility
        
        $userId = $_SESSION['user_id'];
        $userRole = getUserRole($userId);
        $teamId = $userRole['team_id'] ?? null;
        $isAdmin = $userRole['role_name'] === 'Admin';
        
        // Build query to get accessible prompts
        $sql = "
            SELECT DISTINCT p.*, c.name as category_name, u.username as owner_username,
                   (SELECT MAX(version_number) FROM prompt_versions WHERE prompt_id = p.id) as current_version,
                   CASE 
                       WHEN p.user_id = ? THEN 'owner'
                       WHEN p.visibility = 'public' THEN 'public'
                       WHEN p.visibility = 'team' AND p.team_id = ? THEN 'team'
                       WHEN EXISTS (SELECT 1 FROM prompt_shares WHERE prompt_id = p.id AND shared_with_user_id = ?) THEN 'shared'
                       WHEN EXISTS (SELECT 1 FROM prompt_shares WHERE prompt_id = p.id AND shared_with_team_id = ?) THEN 'team_shared'
                       ELSE 'none'
                   END as access_reason
            FROM prompts p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.is_archived = 0
            AND (
                p.user_id = ?  -- User's own prompts
                OR ? = 1  -- Admin sees all
                OR p.visibility = 'public'  -- Public prompts
                OR (p.visibility = 'team' AND p.team_id = ?)  -- Team prompts
                OR EXISTS (SELECT 1 FROM prompt_shares WHERE prompt_id = p.id AND shared_with_user_id = ?)  -- Direct shares
                OR EXISTS (SELECT 1 FROM prompt_shares WHERE prompt_id = p.id AND shared_with_team_id = ?)  -- Team shares
            )
        ";
        
        $params = [$userId, $teamId, $userId, $teamId, $userId, $isAdmin ? 1 : 0, $teamId, $userId, $teamId];
        
        if ($search) {
            $sql .= " AND (p.title LIKE ? OR p.content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($category) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category;
        }
        
        if ($visibility) {
            $sql .= " AND p.visibility = ?";
            $params[] = $visibility;
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
    $visibility = $input['visibility'] ?? 'private';
    $allowAnonymous = $input['allow_anonymous'] ?? false;
    $teamAccessLevel = $input['team_access_level'] ?? 'view';
    
    if (empty($title) || empty($content)) {
        jsonResponse(['error' => 'Title and content are required'], 400);
    }
    
    // Validate visibility
    if (!in_array($visibility, ['private', 'team', 'public'])) {
        jsonResponse(['error' => 'Visibility must be private, team, or public'], 400);
    }
    
    // Validate team_access_level
    if (!in_array($teamAccessLevel, ['view', 'edit'])) {
        jsonResponse(['error' => 'Team access level must be view or edit'], 400);
    }
    
    try {
        // Get user's role and team info
        $userRole = getUserRole($_SESSION['user_id']);
        $teamId = $userRole['team_id'] ?? null;
        
        $db->beginTransaction();
        
        // Insert prompt with visibility settings
        $stmt = $db->prepare("
            INSERT INTO prompts (title, content, category_id, user_id, team_id, visibility, allow_anonymous, team_access_level, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
        ");
        $stmt->execute([$title, $content, $categoryId, $_SESSION['user_id'], $teamId, $visibility, $allowAnonymous ? 1 : 0, $teamAccessLevel]);
        $promptId = $db->lastInsertId();
        
        // Create initial version
        $versionStmt = $db->prepare("
            INSERT INTO prompt_versions (prompt_id, version_number, content, user_id, created_at) 
            VALUES (?, 1, ?, ?, datetime('now'))
        ");
        $versionStmt->execute([$promptId, $content, $_SESSION['user_id']]);
        
        // Log audit event
        logAudit($_SESSION['user_id'], 'prompt_created', "Created prompt: $title (ID: $promptId, visibility: $visibility)");
        
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
    $visibility = $input['visibility'] ?? null;
    $allowAnonymous = $input['allow_anonymous'] ?? null;
    $teamAccessLevel = $input['team_access_level'] ?? null;
    
    if (!$id || empty($title) || empty($content)) {
        jsonResponse(['error' => 'ID, title, and content are required'], 400);
    }
    
    // Validate visibility if provided
    if ($visibility !== null && !in_array($visibility, ['private', 'team', 'public'])) {
        jsonResponse(['error' => 'Visibility must be private, team, or public'], 400);
    }
    
    // Validate team_access_level if provided
    if ($teamAccessLevel !== null && !in_array($teamAccessLevel, ['view', 'edit'])) {
        jsonResponse(['error' => 'Team access level must be view or edit'], 400);
    }
    
    try {
        // Check if user can access this prompt using new visibility/sharing system
        $access = canAccessPrompt($_SESSION['user_id'], $id);
        if (!$access) {
            jsonResponse(['error' => 'Forbidden: You do not have access to this prompt'], 403);
        }
        
        // Only users with edit access can edit
        if ($access['access_level'] !== 'edit') {
            jsonResponse(['error' => 'Forbidden: You only have view access to this prompt'], 403);
        }
        
        // Only owner can change visibility settings
        if (($visibility !== null || $allowAnonymous !== null || $teamAccessLevel !== null) && $access['reason'] !== 'owner') {
            jsonResponse(['error' => 'Forbidden: Only the owner can change visibility settings'], 403);
        }
        
        $db->beginTransaction();
        
        // Get current prompt data
        $currentStmt = $db->prepare("SELECT * FROM prompts WHERE id = ? AND is_archived = 0");
        $currentStmt->execute([$id]);
        $currentPrompt = $currentStmt->fetch();
        
        if (!$currentPrompt) {
            jsonResponse(['error' => 'Prompt not found'], 404);
        }
        
        // Get current version number
        $versionStmt = $db->prepare("
            SELECT MAX(version_number) as max_version 
            FROM prompt_versions 
            WHERE prompt_id = ?
        ");
        $versionStmt->execute([$id]);
        $versionResult = $versionStmt->fetch();
        $nextVersion = ($versionResult['max_version'] ?? 0) + 1;
        
        // Build update query dynamically
        $updates = ['title = ?', 'content = ?', 'category_id = ?', 'updated_at = datetime(\'now\')'];
        $updateParams = [$title, $content, $categoryId];
        
        if ($visibility !== null) {
            $updates[] = 'visibility = ?';
            $updateParams[] = $visibility;
        }
        
        if ($allowAnonymous !== null) {
            $updates[] = 'allow_anonymous = ?';
            $updateParams[] = $allowAnonymous ? 1 : 0;
        }
        
        if ($teamAccessLevel !== null) {
            $updates[] = 'team_access_level = ?';
            $updateParams[] = $teamAccessLevel;
        }
        
        $updateParams[] = $id;
        
        // Update prompt
        $stmt = $db->prepare("
            UPDATE prompts 
            SET " . implode(', ', $updates) . "
            WHERE id = ? AND is_archived = 0
        ");
        $stmt->execute($updateParams);
        
        // Create new version
        $versionStmt = $db->prepare("
            INSERT INTO prompt_versions (prompt_id, version_number, content, user_id, created_at) 
            VALUES (?, ?, ?, ?, datetime('now'))
        ");
        $versionStmt->execute([$id, $nextVersion, $content, $_SESSION['user_id']]);
        
        // Log audit event
        logAudit($_SESSION['user_id'], 'prompt_updated', "Updated prompt: $title (ID: $id)");
        
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
        // Check if user can access this prompt
        $access = canAccessPrompt($_SESSION['user_id'], $id);
        if (!$access) {
            jsonResponse(['error' => 'Forbidden: You do not have access to this prompt'], 403);
        }
        
        // Only the owner can delete a prompt
        if ($access['reason'] !== 'owner') {
            jsonResponse(['error' => 'Forbidden: Only the owner can delete this prompt'], 403);
        }
        
        // Get prompt title for audit log
        $stmt = $db->prepare("SELECT title FROM prompts WHERE id = ? AND is_archived = 0");
        $stmt->execute([$id]);
        $prompt = $stmt->fetch();
        
        if (!$prompt) {
            jsonResponse(['error' => 'Prompt not found'], 404);
        }
        
        // Soft delete the prompt
        $stmt = $db->prepare("
            UPDATE prompts 
            SET is_archived = 1, updated_at = datetime('now') 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        logAudit($_SESSION['user_id'], 'prompt_deleted', "Deleted prompt: {$prompt['title']} (ID: $id)");
        
        jsonResponse([
            'success' => true,
            'message' => 'Prompt deleted successfully'
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to delete prompt: ' . $e->getMessage()], 500);
    }
}
