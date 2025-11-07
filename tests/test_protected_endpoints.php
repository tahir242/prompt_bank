<?php
/**
 * Test Suite: Protected API Endpoints with Role-Based Access
 * Tests authorization for prompts and categories APIs
 */

require_once __DIR__ . '/../database/db.php';

function testProtectedEndpoints() {
    $passed = 0;
    $failed = 0;
    $errors = 0;
    
    echo "=== Protected API Endpoints Tests ===\n\n";
    
    // Setup: Create test users with different roles
    $db = getDatabase();
    $timestamp = time();
    
    // Get roles
    $stmt = $db->prepare("SELECT id, name FROM roles ORDER BY id");
    $stmt->execute();
    $roles = $stmt->fetchAll();
    $roleMap = [];
    foreach ($roles as $role) {
        $roleMap[$role['name']] = $role['id'];
    }
    
    // Create test users for each role
    $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
    
    // Admin user
    $stmt = $db->prepare("INSERT INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute(["admin_test_$timestamp", 'Admin Test', $hashedPassword, $roleMap['Admin']]);
    $adminId = $db->lastInsertId();
    
    // Editor user with team
    $stmt = $db->prepare("INSERT INTO teams (name) VALUES (?)");
    $stmt->execute(["Test Team $timestamp"]);
    $teamId = $db->lastInsertId();
    
    $stmt = $db->prepare("INSERT INTO users (username, full_name, password, role_id, team_id, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute(["editor_test_$timestamp", 'Editor Test', $hashedPassword, $roleMap['Editor'], $teamId]);
    $editorId = $db->lastInsertId();
    
    // Viewer user
    $stmt = $db->prepare("INSERT INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute(["viewer_test_$timestamp", 'Viewer Test', $hashedPassword, $roleMap['Viewer']]);
    $viewerId = $db->lastInsertId();
    
    echo "Setup: Created Admin (ID: $adminId), Editor (ID: $editorId, Team: $teamId), Viewer (ID: $viewerId)\n\n";
    
    // Test 1: Admin can create prompts
    echo "Test 1: Admin can create prompts\n";
    try {
        if (hasPermission($adminId, 'create_prompt')) {
            // Admin creates prompt
            $stmt = $db->prepare("INSERT INTO prompts (title, content, category_id, user_id) VALUES (?, ?, ?, ?)");
            $stmt->execute(['Admin Prompt', 'Test content', 1, $adminId]);
            $promptId = $db->lastInsertId();
            
            echo "   ✓ PASS: Admin has create_prompt permission\n";
            echo "   - Created prompt ID: $promptId\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: Admin should have create_prompt permission\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 2: Editor can create prompts
    echo "Test 2: Editor can create prompts\n";
    try {
        if (hasPermission($editorId, 'create_prompt')) {
            // Editor creates prompt with team
            $stmt = $db->prepare("INSERT INTO prompts (title, content, category_id, user_id, team_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['Editor Prompt', 'Test content', 1, $editorId, $teamId]);
            $promptId = $db->lastInsertId();
            
            echo "   ✓ PASS: Editor has create_prompt permission\n";
            echo "   - Created prompt ID: $promptId (team: $teamId)\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: Editor should have create_prompt permission\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 3: Viewer cannot create prompts
    echo "Test 3: Viewer cannot create prompts\n";
    try {
        if (!hasPermission($viewerId, 'create_prompt')) {
            echo "   ✓ PASS: Viewer correctly lacks create_prompt permission\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: Viewer should NOT have create_prompt permission\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 4: Editor can edit team prompts
    echo "Test 4: Editor can edit team prompts\n";
    try {
        // Create a team prompt
        $stmt = $db->prepare("INSERT INTO prompts (title, content, category_id, user_id, team_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Team Prompt', 'Original content', 1, $adminId, $teamId]);
        $teamPromptId = $db->lastInsertId();
        
        // Check if editor can access it
        if (canAccessPrompt($editorId, $teamPromptId)) {
            echo "   ✓ PASS: Editor can access team prompt\n";
            echo "   - Team prompt ID: $teamPromptId\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: Editor should access team prompts\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 5: Editor cannot edit other team's prompts
    echo "Test 5: Editor cannot edit other team's prompts\n";
    try {
        // Create another team and prompt
        $stmt = $db->prepare("INSERT INTO teams (name) VALUES (?)");
        $stmt->execute(["Other Team $timestamp"]);
        $otherTeamId = $db->lastInsertId();
        
        $stmt = $db->prepare("INSERT INTO prompts (title, content, category_id, user_id, team_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Other Team Prompt', 'Content', 1, $adminId, $otherTeamId]);
        $otherTeamPromptId = $db->lastInsertId();
        
        // Check if editor can access it
        if (!canAccessPrompt($editorId, $otherTeamPromptId)) {
            echo "   ✓ PASS: Editor correctly blocked from other team's prompt\n";
            echo "   - Other team prompt ID: $otherTeamPromptId\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: Editor should NOT access other team's prompts\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 6: Admin can delete any prompt
    echo "Test 6: Admin can delete any prompt\n";
    try {
        if (hasPermission($adminId, 'delete_prompt')) {
            echo "   ✓ PASS: Admin has delete_prompt permission\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: Admin should have delete_prompt permission\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 7: Editor can only delete team prompts
    echo "Test 7: Editor has delete_team_prompt permission (not delete_prompt)\n";
    try {
        $hasTeamDelete = hasPermission($editorId, 'delete_team_prompt');
        $hasFullDelete = hasPermission($editorId, 'delete_prompt');
        
        if ($hasTeamDelete && !$hasFullDelete) {
            echo "   ✓ PASS: Editor has delete_team_prompt but not delete_prompt\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: Editor permissions incorrect\n";
            echo "   - delete_team_prompt: " . ($hasTeamDelete ? 'yes' : 'no') . "\n";
            echo "   - delete_prompt: " . ($hasFullDelete ? 'yes' : 'no') . "\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 8: Admin can manage categories
    echo "Test 8: Admin can manage categories\n";
    try {
        if (hasPermission($adminId, 'manage_categories')) {
            // Create category
            $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute(["Test Category $timestamp", 'Test description']);
            $categoryId = $db->lastInsertId();
            
            echo "   ✓ PASS: Admin has manage_categories permission\n";
            echo "   - Created category ID: $categoryId\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: Admin should have manage_categories permission\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 9: Editor can manage categories
    echo "Test 9: Editor can manage categories\n";
    try {
        if (hasPermission($editorId, 'manage_categories')) {
            echo "   ✓ PASS: Editor has manage_categories permission\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: Editor should have manage_categories permission\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 10: Viewer cannot manage categories
    echo "Test 10: Viewer cannot manage categories\n";
    try {
        if (!hasPermission($viewerId, 'manage_categories')) {
            echo "   ✓ PASS: Viewer correctly lacks manage_categories permission\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: Viewer should NOT have manage_categories permission\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Summary
    echo "=== Test Summary ===\n";
    $total = $passed + $failed + $errors;
    echo "Total: $total tests\n";
    echo "✓ Passed: $passed\n";
    echo "✗ Failed: $failed\n";
    echo "⚠ Errors: $errors\n\n";
    
    if ($failed === 0 && $errors === 0) {
        echo "🎉 All required tests passed!\n";
        return true;
    } else {
        echo "❌ Some tests failed or had errors\n";
        return false;
    }
}

// Run tests
testProtectedEndpoints();
