<?php
/**
 * Public Prompts API - No Authentication Required
 * 
 * This endpoint serves public prompts to anonymous visitors.
 * 
 * SECURITY WARNING:
 * - Only prompts with visibility='public' AND allow_anonymous=1 are returned
 * - No sensitive data (user emails, internal IDs) should be exposed
 * - Rate limiting should be implemented for production use
 * - This endpoint is vulnerable to scraping - consider implementing rate limits
 */

require_once '../database/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

// Only GET method is supported
if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// GET - List public prompts (anonymous access)
if ($method === 'GET') {
    $promptId = $_GET['id'] ?? null;
    $categoryId = $_GET['category_id'] ?? null;
    $search = $_GET['search'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100 results
    $offset = (int)($_GET['offset'] ?? 0);
    
    try {
        // Get a single public prompt
        if ($promptId) {
            $stmt = $db->prepare("
                SELECT 
                    p.id,
                    p.title,
                    p.content,
                    p.category_id,
                    p.created_at,
                    p.updated_at,
                    c.name as category_name,
                    u.username as author_username,
                    u.full_name as author_name
                FROM prompts p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.id = ? 
                    AND p.is_archived = 0 
                    AND p.visibility = 'public' 
                    AND p.allow_anonymous = 1
            ");
            $stmt->execute([$promptId]);
            $prompt = $stmt->fetch();
            
            if (!$prompt) {
                jsonResponse(['error' => 'Prompt not found or not publicly accessible'], 404);
            }
            
            jsonResponse(['prompt' => $prompt]);
        }
        
        // List all public prompts
        $query = "
            SELECT 
                p.id,
                p.title,
                p.content,
                p.category_id,
                p.created_at,
                p.updated_at,
                c.name as category_name,
                u.username as author_username,
                u.full_name as author_name
            FROM prompts p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.is_archived = 0 
                AND p.visibility = 'public' 
                AND p.allow_anonymous = 1
        ";
        
        $params = [];
        
        // Filter by category if provided
        if ($categoryId) {
            $query .= " AND p.category_id = ?";
            $params[] = $categoryId;
        }
        
        // Search in title and content if provided
        if (!empty($search)) {
            $query .= " AND (p.title LIKE ? OR p.content LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Order by most recent first
        $query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $prompts = $stmt->fetchAll();
        
        // Get total count for pagination
        $countQuery = "
            SELECT COUNT(*) as total
            FROM prompts p
            WHERE p.is_archived = 0 
                AND p.visibility = 'public' 
                AND p.allow_anonymous = 1
        ";
        
        $countParams = [];
        if ($categoryId) {
            $countQuery .= " AND p.category_id = ?";
            $countParams[] = $categoryId;
        }
        
        if (!empty($search)) {
            $countQuery .= " AND (p.title LIKE ? OR p.content LIKE ?)";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }
        
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch()['total'];
        
        jsonResponse([
            'prompts' => $prompts,
            'pagination' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch public prompts: ' . $e->getMessage()], 500);
    }
}
