<?php
/**
 * Test Script: Validate Sharing Schema
 * Tests the sharing and collaboration database schema
 */

$dbPath = __DIR__ . '/prompts.db';
$testsPassed = 0;
$testsFailed = 0;

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Enable foreign key constraints for SQLite
    $db->exec("PRAGMA foreign_keys = ON");
    
    echo "=== Sharing Schema Validation Tests ===\n\n";
    
    // Test 1: Verify prompts table has new columns
    echo "Test 1: Prompts table columns...\n";
    $columns = $db->query("PRAGMA table_info(prompts)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    $requiredColumns = ['visibility', 'allow_anonymous', 'team_access_level'];
    $allPresent = true;
    foreach ($requiredColumns as $col) {
        if (in_array($col, $columnNames)) {
            echo "   ✓ Column '$col' exists\n";
        } else {
            echo "   ✗ Column '$col' missing\n";
            $allPresent = false;
        }
    }
    
    if ($allPresent) {
        echo "   PASS: All required columns present\n";
        $testsPassed++;
    } else {
        echo "   FAIL: Missing required columns\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 2: Verify prompt_shares table exists
    echo "Test 2: prompt_shares table structure...\n";
    try {
        $shareColumns = $db->query("PRAGMA table_info(prompt_shares)")->fetchAll(PDO::FETCH_ASSOC);
        $shareColumnNames = array_column($shareColumns, 'name');
        
        $requiredShareColumns = ['id', 'prompt_id', 'shared_with_user_id', 'shared_with_team_id', 'access_level', 'created_by', 'created_at'];
        $allSharesPresent = true;
        foreach ($requiredShareColumns as $col) {
            if (in_array($col, $shareColumnNames)) {
                echo "   ✓ Column '$col' exists\n";
            } else {
                echo "   ✗ Column '$col' missing\n";
                $allSharesPresent = false;
            }
        }
        
        if ($allSharesPresent) {
            echo "   PASS: prompt_shares table structure valid\n";
            $testsPassed++;
        } else {
            echo "   FAIL: prompt_shares table incomplete\n";
            $testsFailed++;
        }
    } catch (Exception $e) {
        echo "   FAIL: prompt_shares table does not exist\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 3: Verify access_requests table exists
    echo "Test 3: access_requests table structure...\n";
    try {
        $requestColumns = $db->query("PRAGMA table_info(access_requests)")->fetchAll(PDO::FETCH_ASSOC);
        $requestColumnNames = array_column($requestColumns, 'name');
        
        $requiredRequestColumns = ['id', 'prompt_id', 'user_id', 'message', 'status', 'created_at', 'resolved_at', 'resolved_by'];
        $allRequestsPresent = true;
        foreach ($requiredRequestColumns as $col) {
            if (in_array($col, $requestColumnNames)) {
                echo "   ✓ Column '$col' exists\n";
            } else {
                echo "   ✗ Column '$col' missing\n";
                $allRequestsPresent = false;
            }
        }
        
        if ($allRequestsPresent) {
            echo "   PASS: access_requests table structure valid\n";
            $testsPassed++;
        } else {
            echo "   FAIL: access_requests table incomplete\n";
            $testsFailed++;
        }
    } catch (Exception $e) {
        echo "   FAIL: access_requests table does not exist\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 4: Verify prompt_collaborators table exists
    echo "Test 4: prompt_collaborators table structure...\n";
    try {
        $collabColumns = $db->query("PRAGMA table_info(prompt_collaborators)")->fetchAll(PDO::FETCH_ASSOC);
        $collabColumnNames = array_column($collabColumns, 'name');
        
        $requiredCollabColumns = ['id', 'prompt_id', 'user_id', 'last_activity', 'is_editing'];
        $allCollabPresent = true;
        foreach ($requiredCollabColumns as $col) {
            if (in_array($col, $collabColumnNames)) {
                echo "   ✓ Column '$col' exists\n";
            } else {
                echo "   ✗ Column '$col' missing\n";
                $allCollabPresent = false;
            }
        }
        
        if ($allCollabPresent) {
            echo "   PASS: prompt_collaborators table structure valid\n";
            $testsPassed++;
        } else {
            echo "   FAIL: prompt_collaborators table incomplete\n";
            $testsFailed++;
        }
    } catch (Exception $e) {
        echo "   FAIL: prompt_collaborators table does not exist\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 5: Test visibility enum constraint
    echo "Test 5: Visibility enum constraint...\n";
    try {
        $db->exec("INSERT INTO prompts (title, content, visibility) VALUES ('Test Invalid', 'Test', 'invalid')");
        echo "   FAIL: Invalid visibility value was accepted\n";
        $testsFailed++;
        $db->exec("DELETE FROM prompts WHERE title = 'Test Invalid'");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'constraint failed') !== false || strpos($e->getMessage(), 'CHECK constraint') !== false) {
            echo "   ✓ Invalid visibility rejected correctly\n";
            echo "   PASS: Visibility enum constraint working\n";
            $testsPassed++;
        } else {
            echo "   FAIL: Unexpected error: " . $e->getMessage() . "\n";
            $testsFailed++;
        }
    }
    echo "\n";
    
    // Test 6: Test access_level enum constraint
    echo "Test 6: Access level enum constraint...\n";
    try {
        // Need a valid prompt and user first
        $db->exec("INSERT INTO prompts (title, content, user_id) VALUES ('Test Prompt', 'Content', 1)");
        $promptId = $db->lastInsertId();
        
        $db->exec("INSERT INTO prompt_shares (prompt_id, shared_with_user_id, access_level, created_by) VALUES ($promptId, 1, 'invalid', 1)");
        echo "   FAIL: Invalid access_level value was accepted\n";
        $testsFailed++;
        $db->exec("DELETE FROM prompts WHERE id = $promptId");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'constraint failed') !== false || strpos($e->getMessage(), 'CHECK constraint') !== false) {
            echo "   ✓ Invalid access_level rejected correctly\n";
            echo "   PASS: Access level enum constraint working\n";
            $testsPassed++;
        } else {
            echo "   FAIL: Unexpected error: " . $e->getMessage() . "\n";
            $testsFailed++;
        }
        // Cleanup
        $db->exec("DELETE FROM prompts WHERE title = 'Test Prompt'");
    }
    echo "\n";
    
    // Test 7: Test foreign key relationships and cascade delete
    echo "Test 7: Foreign key relationships and cascade delete...\n";
    try {
        // Create test prompt and share
        $db->exec("INSERT INTO prompts (title, content, user_id, visibility) VALUES ('FK Test Prompt', 'Content', 1, 'private')");
        $promptId = $db->lastInsertId();
        
        // Get a valid user
        $userStmt = $db->query("SELECT id FROM users LIMIT 1 OFFSET 0");
        $user = $userStmt->fetch();
        $userId = $user ? $user['id'] : 1;
        
        $db->exec("INSERT INTO prompt_shares (prompt_id, shared_with_user_id, access_level, created_by) VALUES ($promptId, $userId, 'view', 1)");
        $shareId = $db->lastInsertId();
        
        // Verify share exists
        $checkStmt = $db->query("SELECT COUNT(*) as cnt FROM prompt_shares WHERE id = $shareId");
        $result = $checkStmt->fetch();
        
        if ($result['cnt'] == 1) {
            echo "   ✓ Share created successfully\n";
            
            // Delete prompt and check if share is also deleted (CASCADE)
            $db->exec("DELETE FROM prompts WHERE id = $promptId");
            
            $checkAfterStmt = $db->query("SELECT COUNT(*) as cnt FROM prompt_shares WHERE id = $shareId");
            $resultAfter = $checkAfterStmt->fetch();
            
            if ($resultAfter['cnt'] == 0) {
                echo "   ✓ Share deleted via CASCADE\n";
                echo "   PASS: Foreign key cascade delete working\n";
                $testsPassed++;
            } else {
                echo "   FAIL: Share not deleted via CASCADE\n";
                $testsFailed++;
            }
        } else {
            echo "   FAIL: Share creation failed\n";
            $testsFailed++;
        }
    } catch (Exception $e) {
        echo "   FAIL: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Test 8: Verify indexes exist
    echo "Test 8: Checking indexes...\n";
    $indexes = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name LIKE 'idx_%'")->fetchAll(PDO::FETCH_ASSOC);
    $indexNames = array_column($indexes, 'name');
    
    $requiredIndexes = [
        'idx_prompts_visibility',
        'idx_prompt_shares_prompt_id',
        'idx_access_requests_status',
        'idx_collaborators_prompt_id'
    ];
    
    $indexesPresent = 0;
    foreach ($requiredIndexes as $idx) {
        if (in_array($idx, $indexNames)) {
            echo "   ✓ Index '$idx' exists\n";
            $indexesPresent++;
        }
    }
    
    if ($indexesPresent >= 4) {
        echo "   PASS: Key indexes created\n";
        $testsPassed++;
    } else {
        echo "   FAIL: Some indexes missing\n";
        $testsFailed++;
    }
    echo "\n";
    
    // Summary
    echo "=== Test Summary ===\n";
    echo "Tests Passed: $testsPassed\n";
    echo "Tests Failed: $testsFailed\n";
    echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n\n";
    
    if ($testsFailed == 0) {
        echo "✓ ALL TESTS PASSED - Schema is valid!\n";
        exit(0);
    } else {
        echo "✗ SOME TESTS FAILED - Please review the schema\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    die("Test failed with database error: " . $e->getMessage() . "\n");
}
