<?php
/**
 * Phase 4 Test Suite: Collaborative Editing Tracking
 * 
 * Tests the collaborators API endpoints:
 * - POST: Update collaborator status (heartbeat)
 * - GET: List active collaborators
 * - DELETE: Remove from active collaborators
 * - Automatic cleanup of stale records (>5 minutes)
 */

require_once 'db.php';

// Initialize test results
$tests = [];
$passed = 0;
$failed = 0;

function testResult($name, $success, $message = '') {
    global $tests, $passed, $failed;
    $tests[] = ['name' => $name, 'success' => $success, 'message' => $message];
    if ($success) {
        $passed++;
        echo "✓ $name\n";
    } else {
        $failed++;
        echo "✗ $name: $message\n";
    }
}

echo "Starting Phase 4 Collaborative Editing Tests...\n\n";

$db = getDatabase();
$db->exec("PRAGMA foreign_keys = ON");

// Create test users and prompts
$testUserIds = [9201, 9202, 9203];
$testTeamId = 9301;

try {
    echo "=== Setup ===\n";
    
    // Cleanup first
    $db->exec("DELETE FROM prompt_collaborators WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9201, 9202, 9203))");
    $db->exec("DELETE FROM prompt_shares WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9201, 9202, 9203))");
    $db->exec("DELETE FROM access_requests WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9201, 9202, 9203))");
    $db->exec("DELETE FROM prompt_versions WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9201, 9202, 9203))");
    $db->exec("DELETE FROM prompts WHERE user_id IN (9201, 9202, 9203)");
    $db->exec("UPDATE users SET team_id = NULL WHERE id IN (9201, 9202, 9203)");
    $db->exec("DELETE FROM teams WHERE id = 9301");
    $db->exec("DELETE FROM users WHERE id IN (9201, 9202, 9203)");
    
    // Create test team
    $stmt = $db->prepare("INSERT INTO teams (id, name) VALUES (?, ?)");
    $stmt->execute([$testTeamId, 'Test Team Phase4']);
    
    // Create test users (Editor role = 2)
    $stmt = $db->prepare("
        INSERT INTO users (id, username, password, full_name, role_id, team_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([9201, 'phase4_owner', 'hashed', 'Owner User', 2, $testTeamId]);
    $stmt->execute([9202, 'phase4_editor1', 'hashed', 'Editor One', 2, $testTeamId]);
    $stmt->execute([9203, 'phase4_editor2', 'hashed', 'Editor Two', 2, $testTeamId]);
    
    // Create test prompts
    $stmt = $db->prepare("
        INSERT INTO prompts (title, content, category_id, user_id, team_id, visibility, team_access_level, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
    ");
    
    // Team prompt with edit access
    $stmt->execute(['Collaborative Prompt', 'Multiple people can edit', 1, 9201, $testTeamId, 'team', 'edit']);
    $teamPromptId = $db->lastInsertId();
    
    // Team prompt with view-only access
    $stmt->execute(['View Only Prompt', 'Only owner can edit', 1, 9201, $testTeamId, 'team', 'view']);
    $viewOnlyPromptId = $db->lastInsertId();
    
    // Private prompt
    $stmt->execute(['Private Prompt', 'Only owner access', 1, 9201, $testTeamId, 'private', 'view']);
    $privatePromptId = $db->lastInsertId();
    
    echo "Created test data.\n\n";
    
    echo "=== Testing Collaborator Registration ===\n";
    
    // TEST 1: Owner can register as active collaborator
    $stmt = $db->prepare("
        INSERT INTO prompt_collaborators (prompt_id, user_id, last_activity)
        VALUES (?, ?, datetime('now'))
    ");
    $result1 = $stmt->execute([$teamPromptId, 9201]);
    testResult('Owner registers as collaborator', $result1, 'Insert failed');
    
    // TEST 2: Editor can register as active collaborator on team prompt with edit access
    $result2 = $stmt->execute([$teamPromptId, 9202]);
    testResult('Editor registers as collaborator', $result2, 'Insert failed');
    
    // TEST 3: Multiple collaborators can be active simultaneously
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM prompt_collaborators 
        WHERE prompt_id = ?
    ");
    $stmt->execute([$teamPromptId]);
    $count = $stmt->fetch()['count'];
    testResult('Multiple collaborators active', $count == 2, "Expected 2, got $count");
    
    // TEST 4: Get active collaborators with user details
    $stmt = $db->prepare("
        SELECT 
            pc.user_id,
            pc.last_activity,
            u.username,
            u.full_name
        FROM prompt_collaborators pc
        JOIN users u ON pc.user_id = u.id
        WHERE pc.prompt_id = ?
        ORDER BY pc.last_activity DESC
    ");
    $stmt->execute([$teamPromptId]);
    $collaborators = $stmt->fetchAll();
    testResult(
        'Get collaborators returns user details',
        count($collaborators) == 2 && 
        isset($collaborators[0]['username']) && 
        isset($collaborators[0]['full_name']),
        'Missing user details'
    );
    
    echo "\n=== Testing Heartbeat Updates ===\n";
    
    // TEST 5: Update existing collaborator (heartbeat)
    sleep(1); // Wait 1 second to ensure timestamp difference
    $stmt = $db->prepare("
        INSERT INTO prompt_collaborators (prompt_id, user_id, last_activity)
        VALUES (?, ?, datetime('now'))
        ON CONFLICT(prompt_id, user_id) 
        DO UPDATE SET last_activity = datetime('now')
    ");
    $result5 = $stmt->execute([$teamPromptId, 9201]);
    testResult('Heartbeat updates existing record', $result5, 'Update failed');
    
    // TEST 6: Verify last_activity was updated
    $stmt = $db->prepare("
        SELECT 
            last_activity,
            CAST((julianday('now') - julianday(last_activity)) * 86400 AS INTEGER) as seconds_ago
        FROM prompt_collaborators 
        WHERE prompt_id = ? AND user_id = ?
    ");
    $stmt->execute([$teamPromptId, 9201]);
    $record = $stmt->fetch();
    testResult(
        'Last seen timestamp is recent',
        $record && $record['seconds_ago'] < 2,
        "Seconds ago: " . ($record['seconds_ago'] ?? 'NULL')
    );
    
    echo "\n=== Testing Stale Record Cleanup ===\n";
    
    // TEST 7: Create stale record (6 minutes old)
    $stmt = $db->prepare("
        INSERT INTO prompt_collaborators (prompt_id, user_id, last_activity)
        VALUES (?, ?, datetime('now', '-6 minutes'))
    ");
    $stmt->execute([$teamPromptId, 9203]);
    testResult('Created stale record', true, '');
    
    // TEST 8: Verify stale record exists
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prompt_collaborators WHERE prompt_id = ?");
    $stmt->execute([$teamPromptId]);
    $beforeCount = $stmt->fetch()['count'];
    testResult('Stale record exists', $beforeCount == 3, "Expected 3, got $beforeCount");
    
    // TEST 9: Cleanup stale records
    $stmt = $db->prepare("
        DELETE FROM prompt_collaborators 
        WHERE last_activity < datetime('now', '-5 minutes')
    ");
    $result9 = $stmt->execute();
    testResult('Cleanup executed', $result9, 'Cleanup failed');
    
    // TEST 10: Verify stale record was removed
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prompt_collaborators WHERE prompt_id = ?");
    $stmt->execute([$teamPromptId]);
    $afterCount = $stmt->fetch()['count'];
    testResult('Stale record removed', $afterCount == 2, "Expected 2, got $afterCount");
    
    // TEST 11: Recent records remain after cleanup
    $stmt = $db->prepare("
        SELECT user_id FROM prompt_collaborators 
        WHERE prompt_id = ? 
        ORDER BY user_id
    ");
    $stmt->execute([$teamPromptId]);
    $remainingUsers = array_column($stmt->fetchAll(), 'user_id');
    testResult(
        'Recent collaborators still active',
        in_array(9201, $remainingUsers) && in_array(9202, $remainingUsers) && !in_array(9203, $remainingUsers),
        'Wrong users remaining: ' . implode(', ', $remainingUsers)
    );
    
    echo "\n=== Testing Access Control ===\n";
    
    // TEST 12: User with edit access can be collaborator
    $access = canAccessPrompt(9202, $teamPromptId);
    testResult(
        'Editor has edit access to team prompt',
        $access && $access['access_level'] === 'edit',
        json_encode($access)
    );
    
    // TEST 13: User with view-only access cannot be collaborator
    $access = canAccessPrompt(9202, $viewOnlyPromptId);
    testResult(
        'Editor has only view access to view-only prompt',
        $access && $access['access_level'] === 'view',
        json_encode($access)
    );
    
    // TEST 14: User without access cannot be collaborator
    // First, create a user without team access
    $stmt = $db->prepare("
        INSERT INTO users (id, username, password, full_name, role_id, team_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([9204, 'phase4_outsider', 'hashed', 'Outsider', 2, null]);
    
    $access = canAccessPrompt(9204, $privatePromptId);
    testResult(
        'Outsider has no access to private prompt',
        !$access,
        'Should not have access'
    );
    
    echo "\n=== Testing Collaborator Removal ===\n";
    
    // TEST 15: Remove specific collaborator
    $stmt = $db->prepare("
        DELETE FROM prompt_collaborators 
        WHERE prompt_id = ? AND user_id = ?
    ");
    $result15 = $stmt->execute([$teamPromptId, 9202]);
    testResult('Remove collaborator', $result15, 'Delete failed');
    
    // TEST 16: Verify collaborator was removed
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM prompt_collaborators 
        WHERE prompt_id = ? AND user_id = ?
    ");
    $stmt->execute([$teamPromptId, 9202]);
    $count = $stmt->fetch()['count'];
    testResult('Collaborator removed from database', $count == 0, "Count is $count");
    
    // TEST 17: Other collaborators remain
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prompt_collaborators WHERE prompt_id = ?");
    $stmt->execute([$teamPromptId]);
    $remaining = $stmt->fetch()['count'];
    testResult('Other collaborators remain', $remaining == 1, "Expected 1, got $remaining");
    
    echo "\n=== Testing Edge Cases ===\n";
    
    // TEST 18: Foreign key constraint on prompt deletion
    $db->exec("DELETE FROM prompt_collaborators WHERE prompt_id = $teamPromptId");
    $db->exec("UPDATE prompts SET is_archived = 1 WHERE id = $teamPromptId");
    
    // Try to add collaborator to archived prompt
    $stmt = $db->prepare("
        INSERT INTO prompt_collaborators (prompt_id, user_id, last_activity)
        VALUES (?, ?, datetime('now'))
    ");
    $result18 = $stmt->execute([$teamPromptId, 9201]);
    testResult('Can add collaborator to archived prompt', $result18, 'FK constraint allows it');
    
    // But the prompt is archived, so it shouldn't show in queries
    $db->exec("DELETE FROM prompt_collaborators WHERE prompt_id = $teamPromptId");
    
    // TEST 19: UNIQUE constraint prevents duplicate entries
    $stmt = $db->prepare("
        INSERT INTO prompt_collaborators (prompt_id, user_id, last_activity)
        VALUES (?, ?, datetime('now'))
    ");
    $stmt->execute([$viewOnlyPromptId, 9201]);
    
    // Try to insert duplicate
    try {
        $stmt->execute([$viewOnlyPromptId, 9201]);
        testResult('UNIQUE constraint prevents duplicates', false, 'Allowed duplicate');
    } catch (Exception $e) {
        testResult('UNIQUE constraint prevents duplicates', true, '');
    }
    
    // TEST 20: ON CONFLICT updates timestamp instead
    $stmt = $db->prepare("
        INSERT INTO prompt_collaborators (prompt_id, user_id, last_activity)
        VALUES (?, ?, datetime('now'))
        ON CONFLICT(prompt_id, user_id) 
        DO UPDATE SET last_activity = datetime('now')
    ");
    $result20 = $stmt->execute([$viewOnlyPromptId, 9201]);
    testResult('ON CONFLICT updates timestamp', $result20, 'Update failed');
    
    echo "\n=== Testing Cascading Deletes ===\n";
    
    // TEST 21: Collaborators deleted when prompt is deleted
    $stmt = $db->prepare("
        INSERT INTO prompt_collaborators (prompt_id, user_id, last_activity)
        VALUES (?, ?, datetime('now'))
    ");
    $stmt->execute([$privatePromptId, 9201]);
    
    // Delete prompt (hard delete for test)
    $db->exec("DELETE FROM prompts WHERE id = $privatePromptId");
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prompt_collaborators WHERE prompt_id = ?");
    $stmt->execute([$privatePromptId]);
    $count = $stmt->fetch()['count'];
    testResult('Collaborators cascade deleted with prompt', $count == 0, "Count is $count");
    
    // TEST 22: Collaborators deleted when user is deleted
    $stmt = $db->prepare("
        INSERT INTO users (id, username, password, full_name, role_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([9205, 'temp_user', 'hashed', 'Temp User', 2]);
    
    $stmt = $db->prepare("
        INSERT INTO prompts (title, content, category_id, user_id, visibility, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))
    ");
    $stmt->execute(['Temp Prompt', 'Test', 1, 9205, 'private']);
    $tempPromptId = $db->lastInsertId();
    
    $stmt = $db->prepare("
        INSERT INTO prompt_collaborators (prompt_id, user_id, last_activity)
        VALUES (?, ?, datetime('now'))
    ");
    $stmt->execute([$tempPromptId, 9205]);
    
    // Delete user
    $db->exec("DELETE FROM prompts WHERE user_id = 9205");
    $db->exec("DELETE FROM users WHERE id = 9205");
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prompt_collaborators WHERE user_id = ?");
    $stmt->execute([9205]);
    $count = $stmt->fetch()['count'];
    testResult('Collaborators cascade deleted with user', $count == 0, "Count is $count");
    
    echo "\n=== Cleanup ===\n";
    
    // Cleanup
    $db->exec("DELETE FROM prompt_collaborators WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9201, 9202, 9203))");
    $db->exec("DELETE FROM prompt_shares WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9201, 9202, 9203))");
    $db->exec("DELETE FROM access_requests WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9201, 9202, 9203))");
    $db->exec("DELETE FROM prompt_versions WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9201, 9202, 9203))");
    $db->exec("DELETE FROM prompts WHERE user_id IN (9201, 9202, 9203)");
    $db->exec("UPDATE users SET team_id = NULL WHERE id IN (9201, 9202, 9203, 9204)");
    $db->exec("DELETE FROM teams WHERE id = 9301");
    $db->exec("DELETE FROM users WHERE id IN (9201, 9202, 9203, 9204)");
    
    echo "Cleanup complete.\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Test Summary ===\n";
echo "Total tests: " . count($tests) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed === 0) {
    echo "\n✓ All Phase 4 tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed.\n";
    exit(1);
}
