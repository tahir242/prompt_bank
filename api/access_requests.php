<?php
/**
 * Access Requests API Endpoint
 * Handles prompt access request workflow
 */

require_once __DIR__ . '/../database/db.php';
requireAuth();

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

// GET - List access requests
if ($method === 'GET') {
    $promptId = $_GET['prompt_id'] ?? null;
    
    if ($promptId) {
        // Get requests for a specific prompt (owner only)
        $access = canAccessPrompt($_SESSION['user_id'], $promptId);
        if (!$access || $access['reason'] !== 'owner') {
            jsonResponse(['error' => 'Forbidden: Only the owner can view access requests'], 403);
        }
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    ar.*,
                    u.username as requester_username,
                    u.full_name as requester_full_name
                FROM access_requests ar
                JOIN users u ON ar.user_id = u.id
                WHERE ar.prompt_id = ?
                ORDER BY 
                    CASE ar.status 
                        WHEN 'pending' THEN 1 
                        WHEN 'approved' THEN 2 
                        WHEN 'denied' THEN 3 
                    END,
                    ar.created_at DESC
            ");
            $stmt->execute([$promptId]);
            $requests = $stmt->fetchAll();
            
            jsonResponse(['requests' => $requests]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Failed to fetch requests: ' . $e->getMessage()], 500);
        }
    } else {
        // Get all pending requests for user's prompts
        try {
            $requests = getPendingRequests($_SESSION['user_id']);
            jsonResponse(['requests' => $requests]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Failed to fetch pending requests: ' . $e->getMessage()], 500);
        }
    }
}

// POST - Create access request
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $promptId = $input['prompt_id'] ?? null;
    $message = $input['message'] ?? null;
    
    if (!$promptId) {
        jsonResponse(['error' => 'prompt_id is required'], 400);
    }
    
    // Verify prompt exists and is not archived
    $promptStmt = $db->prepare("SELECT id, title, user_id, visibility FROM prompts WHERE id = ? AND is_archived = 0");
    $promptStmt->execute([$promptId]);
    $prompt = $promptStmt->fetch();
    
    if (!$prompt) {
        jsonResponse(['error' => 'Prompt not found'], 404);
    }
    
    // Cannot request access to own prompts
    if ($prompt['user_id'] == $_SESSION['user_id']) {
        jsonResponse(['error' => 'Cannot request access to your own prompt'], 400);
    }
    
    // Check if user already has access
    $access = canAccessPrompt($_SESSION['user_id'], $promptId);
    if ($access) {
        jsonResponse(['error' => 'You already have access to this prompt'], 400);
    }
    
    // Check for existing pending request
    $existingStmt = $db->prepare("
        SELECT id FROM access_requests 
        WHERE prompt_id = ? AND user_id = ? AND status = 'pending'
    ");
    $existingStmt->execute([$promptId, $_SESSION['user_id']]);
    if ($existingStmt->fetch()) {
        jsonResponse(['error' => 'You already have a pending request for this prompt'], 409);
    }
    
    try {
        $requestId = requestAccess($promptId, $_SESSION['user_id'], $message);
        
        if ($requestId === false) {
            jsonResponse(['error' => 'Failed to create access request'], 500);
        }
        
        // Log audit event
        logAudit($_SESSION['user_id'], 'access_requested', "Requested access to prompt $promptId");
        
        // Fetch the created request
        $requestStmt = $db->prepare("
            SELECT 
                ar.*,
                p.title as prompt_title,
                u.username as requester_username,
                u.full_name as requester_full_name
            FROM access_requests ar
            JOIN prompts p ON ar.prompt_id = p.id
            JOIN users u ON ar.user_id = u.id
            WHERE ar.id = ?
        ");
        $requestStmt->execute([$requestId]);
        $request = $requestStmt->fetch();
        
        jsonResponse([
            'success' => true,
            'message' => 'Access request created successfully',
            'request' => $request
        ], 201);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to create access request: ' . $e->getMessage()], 500);
    }
}

// PUT - Approve or deny access request
elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $requestId = $input['request_id'] ?? null;
    $action = $input['action'] ?? null; // 'approve' or 'deny'
    $accessLevel = $input['access_level'] ?? 'view';
    
    if (!$requestId || !$action) {
        jsonResponse(['error' => 'request_id and action are required'], 400);
    }
    
    if (!in_array($action, ['approve', 'deny'])) {
        jsonResponse(['error' => 'action must be either "approve" or "deny"'], 400);
    }
    
    if ($action === 'approve' && !in_array($accessLevel, ['view', 'edit'])) {
        jsonResponse(['error' => 'access_level must be either "view" or "edit"'], 400);
    }
    
    try {
        // Get request details
        $requestStmt = $db->prepare("
            SELECT ar.*, p.user_id as prompt_owner_id, p.title as prompt_title
            FROM access_requests ar
            JOIN prompts p ON ar.prompt_id = p.id
            WHERE ar.id = ?
        ");
        $requestStmt->execute([$requestId]);
        $request = $requestStmt->fetch();
        
        if (!$request) {
            jsonResponse(['error' => 'Access request not found'], 404);
        }
        
        if ($request['status'] !== 'pending') {
            jsonResponse(['error' => 'Request has already been ' . $request['status']], 400);
        }
        
        // Only prompt owner can approve/deny
        if ($request['prompt_owner_id'] != $_SESSION['user_id']) {
            // Check if user is admin
            $userRole = getUserRole($_SESSION['user_id']);
            if ($userRole['role_name'] !== 'Admin') {
                jsonResponse(['error' => 'Forbidden: Only the prompt owner can manage access requests'], 403);
            }
        }
        
        if ($action === 'approve') {
            $success = approveRequest($requestId, $_SESSION['user_id'], $accessLevel);
            $message = 'Access request approved and share created';
            $auditAction = 'access_approved';
            $auditDetails = "Approved access request $requestId for prompt {$request['prompt_id']} with $accessLevel access";
        } else {
            $success = denyRequest($requestId, $_SESSION['user_id']);
            $message = 'Access request denied';
            $auditAction = 'access_denied';
            $auditDetails = "Denied access request $requestId for prompt {$request['prompt_id']}";
        }
        
        if ($success) {
            logAudit($_SESSION['user_id'], $auditAction, $auditDetails);
            
            jsonResponse([
                'success' => true,
                'message' => $message
            ]);
        } else {
            jsonResponse(['error' => 'Failed to process request'], 500);
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to process request: ' . $e->getMessage()], 500);
    }
}

// Method not allowed
else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
