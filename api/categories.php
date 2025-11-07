<?php
require_once __DIR__ . '/../database/db.php';
requireAuth();

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

// GET - List all categories
if ($method === 'GET') {
    try {
        $stmt = $db->query("SELECT * FROM categories ORDER BY is_system DESC, name ASC");
        $categories = $stmt->fetchAll();
        jsonResponse($categories);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch categories: ' . $e->getMessage()], 500);
    }
}

// POST - Create new category
if ($method === 'POST') {
    // Check manage_categories permission (Admin and Editor)
    if (!hasPermission($_SESSION['user_id'], 'manage_categories')) {
        jsonResponse(['error' => 'Forbidden: You do not have permission to manage categories'], 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['name'] ?? '';
    
    if (empty($name)) {
        jsonResponse(['error' => 'Category name is required'], 400);
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO categories (name, is_system) VALUES (?, 0)");
        $stmt->execute([$name]);
        
        jsonResponse([
            'success' => true,
            'id' => $db->lastInsertId(),
            'message' => 'Category created successfully'
        ], 201);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
            jsonResponse(['error' => 'Category already exists'], 409);
        }
        jsonResponse(['error' => 'Failed to create category: ' . $e->getMessage()], 500);
    }
}

// PUT - Update category (only user-defined)
if ($method === 'PUT') {
    // Check manage_categories permission (Admin and Editor)
    if (!hasPermission($_SESSION['user_id'], 'manage_categories')) {
        jsonResponse(['error' => 'Forbidden: You do not have permission to manage categories'], 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    $name = $input['name'] ?? '';
    
    if (!$id || empty($name)) {
        jsonResponse(['error' => 'ID and name are required'], 400);
    }
    
    try {
        // Check if it's a system category
        $stmt = $db->prepare("SELECT is_system FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if ($category && $category['is_system']) {
            jsonResponse(['error' => 'Cannot edit system categories'], 403);
        }
        
        $stmt = $db->prepare("UPDATE categories SET name = ? WHERE id = ? AND is_system = 0");
        $stmt->execute([$name, $id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Category updated successfully'
        ]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
            jsonResponse(['error' => 'Category name already exists'], 409);
        }
        jsonResponse(['error' => 'Failed to update category: ' . $e->getMessage()], 500);
    }
}

// DELETE - Delete category (only user-defined)
if ($method === 'DELETE') {
    // Check manage_categories permission (Admin and Editor)
    if (!hasPermission($_SESSION['user_id'], 'manage_categories')) {
        jsonResponse(['error' => 'Forbidden: You do not have permission to manage categories'], 403);
    }
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['error' => 'ID is required'], 400);
    }
    
    try {
        // Check if it's a system category
        $stmt = $db->prepare("SELECT is_system FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if ($category && $category['is_system']) {
            jsonResponse(['error' => 'Cannot delete system categories'], 403);
        }
        
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ? AND is_system = 0");
        $stmt->execute([$id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to delete category: ' . $e->getMessage()], 500);
    }
}
