<?php
require_once __DIR__ . '/../database/db.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$promptId = $input['prompt_id'] ?? null;
$versionNumber = $input['version_number'] ?? null;

if (!$promptId || !$versionNumber) {
    jsonResponse(['error' => 'Prompt ID and version number are required'], 400);
}

$db = getDatabase();

try {
    // Check access
    $access = canAccessPrompt($_SESSION['user_id'], $promptId);
    if (!$access) {
        jsonResponse(['error' => 'Forbidden: You do not have access to this prompt'], 403);
    }
    
    // Must have edit access
    if ($access['access_level'] !== 'edit') {
        jsonResponse(['error' => 'Forbidden: You only have view access to this prompt'], 403);
    }
    
    $db->beginTransaction();
    
    // Get the target version content
    $stmt = $db->prepare("SELECT content FROM prompt_versions WHERE prompt_id = ? AND version_number = ?");
    $stmt->execute([$promptId, $versionNumber]);
    $version = $stmt->fetch();
    
    if (!$version) {
        throw new Exception("Version not found");
    }
    
    $content = $version['content'];
    
    // Get current prompt data to verify it exists and is not archived
    $promptStmt = $db->prepare("SELECT title FROM prompts WHERE id = ? AND is_archived = 0");
    $promptStmt->execute([$promptId]);
    $prompt = $promptStmt->fetch();
    
    if (!$prompt) {
        throw new Exception("Prompt not found");
    }
    
    // Get max version number
    $versionStmt = $db->prepare("SELECT MAX(version_number) as max_version FROM prompt_versions WHERE prompt_id = ?");
    $versionStmt->execute([$promptId]);
    $versionResult = $versionStmt->fetch();
    $nextVersion = ($versionResult['max_version'] ?? 0) + 1;
    
    // Update the prompt
    $updateStmt = $db->prepare("UPDATE prompts SET content = ?, updated_at = datetime('now') WHERE id = ?");
    $updateStmt->execute([$content, $promptId]);
    
    // Create new version
    $insertStmt = $db->prepare("
        INSERT INTO prompt_versions (prompt_id, version_number, content, user_id, created_at) 
        VALUES (?, ?, ?, ?, datetime('now'))
    ");
    $insertStmt->execute([$promptId, $nextVersion, $content, $_SESSION['user_id']]);
    
    // Log audit
    logAudit($_SESSION['user_id'], 'prompt_restored', "Restored prompt '{$prompt['title']}' (ID: $promptId) to version $versionNumber");
    
    $db->commit();
    
    jsonResponse([
        'success' => true,
        'message' => 'Prompt restored successfully',
        'new_version' => $nextVersion
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['error' => 'Failed to restore prompt: ' . $e->getMessage()], 500);
}
