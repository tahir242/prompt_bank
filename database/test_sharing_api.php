<?php
/**
 * Test Script: Sharing and Access Requests API
 * Tests all sharing and access request endpoints
 */

$dbPath = __DIR__ . '/prompts.db';
$testsPassed = 0;
$testsFailed = 0;

// Helper function to simulate API calls
function testAPIFunction($functionName, $args, $expectedResult, $testName) {
    global $testsPassed, $testsFailed;
    
    echo "Test: $testName...\n";
    try {
        $result = call_user_func_array($functionName, $args);
        
        if ($result === $expectedResult || ($expectedResult === 'not-false' && $result !== false)) {
            echo "   ✓ PASS\n";
            $testsPassed++;
            return $result;
        } else {
            echo "   ✗ FAIL - Expected " . var_export($expectedResult, true) . ", got " . var_export($result, true) . "\n";
            $testsFailed++;
            return false;
        }
    } catch (Exception $e) {
        echo "   ✗ FAIL - Exception: " . $e->getMessage() . "\n";
        $testsFailed++;
        return false;
    }
}

try {
    require_once __DIR__ . '/db.php';
    
    $db = getDatabase();
    
    echo "=== Sharing and Access Requests API Tests ===\n\n";
    
    // Cleanup first (in case of previous test run)
    echo "Cleanup: Removing any existing test data...\n";
    $db->exec("DELETE FROM prompt_collaborators WHERE prompt_id IN (9001, 9002, 9003)");
    $db->exec("DELETE FROM prompt_shares WHERE prompt_id IN (9001, 9002, 9003)");
    $db->exec("DELETE FROM access_requests WHERE prompt_id IN (9001, 9002, 9003)");
    $db->exec("DELETE FROM prompt_versions WHERE prompt_id IN (9001, 9002, 9003)");
    $db->exec("DELETE FROM prompts WHERE id IN (9001, 9002, 9003)");
    $db->exec("UPDATE users SET team_id = NULL WHERE id IN (9001, 9002, 9003)");
    $db->exec("DELETE FROM teams WHERE id = 9001");
    $db->exec("DELETE FROM users WHERE id IN (9001, 9002, 9003)");
    echo "   ✓ Cleanup complete\n\n";
    
    // Setup: Create test data
    echo "Setup: Creating test data...\n";
    
    // Get existing role IDs
    $adminRole = $db->query("SELECT id FROM roles WHERE name = 'Admin' LIMIT 1")->fetch();
    $editorRole = $db->query("SELECT id FROM roles WHERE name = 'Editor' LIMIT 1")->fetch();
    $viewerRole = $db->query("SELECT id FROM roles WHERE name = 'Viewer' LIMIT 1")->fetch();
    $adminRoleId = $adminRole ? $adminRole['id'] : 1;
    $editorRoleId = $editorRole ? $editorRole['id'] : 2;
    $viewerRoleId = $viewerRole ? $viewerRole['id'] : 3;
    
    // Get existing category ID
    $category = $db->query("SELECT id FROM categories LIMIT 1")->fetch();
    $categoryId = $category ? $category['id'] : 1;
    
    // Create test users
    $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
    
    $db->exec("INSERT INTO users (id, username, full_name, password, role_id, is_active) VALUES 
        (9001, 'testowner_api', 'Test Owner', '$hashedPassword', $editorRoleId, 1),
        (9002, 'testuser_api', 'Test User', '$hashedPassword', $editorRoleId, 1),
        (9003, 'testviewer_api', 'Test Viewer', '$hashedPassword', $viewerRoleId, 1)
    ");
    
    // Create test team
    $db->exec("INSERT INTO teams (id, name, created_by) VALUES (9001, 'Test Team API', 9001)");
    
    // Update test users with team
    $db->exec("UPDATE users SET team_id = 9001 WHERE id IN (9001, 9002)");
    
    // Create test prompt
    $db->exec("INSERT INTO prompts (id, title, content, user_id, team_id, visibility, team_access_level, category_id) VALUES 
        (9001, 'Test Prompt for Sharing', 'Test content', 9001, 9001, 'private', 'view', $categoryId)
    ");
    
    echo "   ✓ Test data created\n\n";
    
    // Test 1: canAccessPrompt - Owner access
    echo "Test 1: canAccessPrompt - Owner has edit access...\n";
    $access = canAccessPrompt(9001, 9001);
    if ($access && $access['access_level'] === 'edit' && $access['reason'] === 'owner') {
        echo "   ✓ PASS\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 2: canAccessPrompt - No access for non-shared user
    echo "Test 2: canAccessPrompt - Private prompt blocks non-owner...\n";
    $access = canAccessPrompt(9003, 9001);
    if ($access === false) {
        echo "   ✓ PASS\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL - User should not have access\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 3: sharePrompt - Create user share
    echo "Test 3: sharePrompt - Create share with user...\n";
    $shareId = sharePrompt(9001, 9001, 9002, null, 'view');
    if ($shareId !== false) {
        echo "   ✓ PASS - Share created with ID: $shareId\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 4: canAccessPrompt - User has access via share
    echo "Test 4: canAccessPrompt - Shared user has view access...\n";
    $access = canAccessPrompt(9002, 9001);
    if ($access && $access['access_level'] === 'view' && $access['reason'] === 'direct_share') {
        echo "   ✓ PASS\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL - Access result: " . var_export($access, true) . "\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 5: sharePrompt - Prevent duplicate shares
    echo "Test 5: sharePrompt - Prevent duplicate shares...\n";
    $duplicateShare = sharePrompt(9001, 9001, 9002, null, 'view');
    if ($duplicateShare === false) {
        echo "   ✓ PASS - Duplicate prevented\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL - Duplicate was allowed\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 6: sharePrompt - Team share
    echo "Test 6: sharePrompt - Create share with team...\n";
    
    // Create another prompt for team sharing test
    $db->exec("INSERT INTO prompts (id, title, content, user_id, visibility, category_id) VALUES 
        (9002, 'Test Prompt for Team Share', 'Test content', 9001, 'private', $categoryId)
    ");
    
    $teamShareId = sharePrompt(9002, 9001, null, 9001, 'edit');
    if ($teamShareId !== false) {
        echo "   ✓ PASS - Team share created with ID: $teamShareId\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 7: canAccessPrompt - Team share access
    echo "Test 7: canAccessPrompt - Team member has edit access via team share...\n";
    $access = canAccessPrompt(9002, 9002);
    if ($access && $access['access_level'] === 'edit' && $access['reason'] === 'team_share') {
        echo "   ✓ PASS\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 8: getPromptShares
    echo "Test 8: getPromptShares - List all shares for prompt...\n";
    $shares = getPromptShares(9001);
    if (is_array($shares) && count($shares) === 1) {
        echo "   ✓ PASS - Found " . count($shares) . " share(s)\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL - Expected 1 share, found " . count($shares) . "\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 9: Visibility - Public prompt
    echo "Test 9: canAccessPrompt - Public prompt accessible to all...\n";
    $db->exec("UPDATE prompts SET visibility = 'public' WHERE id = 9001");
    $access = canAccessPrompt(9003, 9001);
    if ($access && $access['access_level'] === 'view' && $access['reason'] === 'public') {
        echo "   ✓ PASS\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL\n";
        $testsFailed++;
    }
    $db->exec("UPDATE prompts SET visibility = 'private' WHERE id = 9001"); // Reset
    echo "\n";
    
    // Test 10: Visibility - Team prompt
    echo "Test 10: canAccessPrompt - Team visibility with team_access_level...\n";
    $db->exec("UPDATE prompts SET visibility = 'team', team_access_level = 'edit' WHERE id = 9001");
    $access = canAccessPrompt(9002, 9001);
    if ($access && $access['access_level'] === 'edit' && $access['reason'] === 'team') {
        echo "   ✓ PASS\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL\n";
        $testsFailed++;
    }
    $db->exec("UPDATE prompts SET visibility = 'private', team_access_level = 'view' WHERE id = 9001"); // Reset
    echo "\n";
    
    // Test 11: requestAccess
    echo "Test 11: requestAccess - Create access request...\n";
    $db->exec("INSERT INTO prompts (id, title, content, user_id, visibility, category_id) VALUES 
        (9003, 'Test Prompt for Access Request', 'Test content', 9001, 'private', $categoryId)
    ");
    
    $requestId = requestAccess(9003, 9003, 'Please grant me access');
    if ($requestId !== false) {
        echo "   ✓ PASS - Request created with ID: $requestId\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 12: getPendingRequests
    echo "Test 12: getPendingRequests - List pending requests for owner...\n";
    $requests = getPendingRequests(9001);
    if (is_array($requests) && count($requests) >= 1) {
        echo "   ✓ PASS - Found " . count($requests) . " pending request(s)\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 13: approveRequest
    echo "Test 13: approveRequest - Approve request and create share...\n";
    $approved = approveRequest($requestId, 9001, 'view');
    if ($approved) {
        echo "   ✓ PASS - Request approved\n";
        $testsPassed++;
        
        // Verify share was created
        $access = canAccessPrompt(9003, 9003);
        if ($access && $access['access_level'] === 'view') {
            echo "   ✓ Share created successfully\n";
        } else {
            echo "   ✗ Share not created\n";
        }
    } else {
        echo "   ✗ FAIL\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 14: denyRequest
    echo "Test 14: denyRequest - Deny access request...\n";
    $requestId2 = requestAccess(9001, 9003, 'Another request');
    $denied = denyRequest($requestId2, 9001);
    if ($denied) {
        echo "   ✓ PASS - Request denied\n";
        $testsPassed++;
    } else {
        echo "   ✗ FAIL\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 15: revokeShare
    echo "Test 15: revokeShare - Revoke a share...\n";
    if ($shareId) {
        $revoked = revokeShare($shareId);
        if ($revoked) {
            echo "   ✓ PASS - Share revoked\n";
            $testsPassed++;
            
            // Verify access removed
            $db->exec("UPDATE prompts SET visibility = 'private' WHERE id = 9001");
            $access = canAccessPrompt(9002, 9001);
            if ($access === false) {
                echo "   ✓ Access removed successfully\n";
            } else {
                echo "   ✗ Access still exists\n";
            }
        } else {
            echo "   ✗ FAIL\n";
            $testsFailed++;
        }
    }
    echo "\n";
    
    // Cleanup
    echo "Cleanup: Removing test data...\n";
    $db->exec("DELETE FROM prompt_collaborators WHERE prompt_id IN (9001, 9002, 9003)");
    $db->exec("DELETE FROM prompt_shares WHERE prompt_id IN (9001, 9002, 9003)");
    $db->exec("DELETE FROM access_requests WHERE prompt_id IN (9001, 9002, 9003)");
    $db->exec("DELETE FROM prompt_versions WHERE prompt_id IN (9001, 9002, 9003)");
    $db->exec("DELETE FROM prompts WHERE id IN (9001, 9002, 9003)");
    $db->exec("UPDATE users SET team_id = NULL WHERE id IN (9001, 9002, 9003)");
    $db->exec("DELETE FROM teams WHERE id = 9001");
    $db->exec("DELETE FROM users WHERE id IN (9001, 9002, 9003)");
    echo "   ✓ Cleanup complete\n\n";
    
    // Summary
    echo "=== Test Summary ===\n";
    echo "Tests Passed: $testsPassed\n";
    echo "Tests Failed: $testsFailed\n";
    echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n\n";
    
    if ($testsFailed == 0) {
        echo "✓ ALL TESTS PASSED - API functions are working!\n";
        exit(0);
    } else {
        echo "✗ SOME TESTS FAILED - Please review the implementation\n";
        exit(1);
    }
    
} catch (Exception $e) {
    die("Test failed with error: " . $e->getMessage() . "\n");
}
