<?php
/**
 * Phase 3 Test Suite: Prompts API with Visibility Control
 * 
 * Tests the database helper functions and validates the visibility logic
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

echo "Starting Phase 3 Prompts API Tests...\n\n";

$db = getDatabase();
$db->exec("PRAGMA foreign_keys = ON");

// Create test users with unique IDs
$testUserIds = [9101, 9102, 9103];
$testTeamId = 9201;

try {
    echo "=== Setup ===\n";
    
    // Cleanup first
    $db->exec("DELETE FROM prompt_collaborators WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompt_shares WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM access_requests WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompt_versions WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompts WHERE user_id IN (9101, 9102, 9103)");
    $db->exec("UPDATE users SET team_id = NULL WHERE id IN (9101, 9102, 9103)");
    $db->exec("DELETE FROM teams WHERE id = 9201");
    $db->exec("DELETE FROM users WHERE id IN (9101, 9102, 9103)");
    
    // Create test team
    $stmt = $db->prepare("INSERT INTO teams (id, name) VALUES (?, ?)");
    $stmt->execute([$testTeamId, 'Test Team Phase3']);
    
    // Create test users (Editor role = 2, not Admin)
    $stmt = $db->prepare("
        INSERT INTO users (id, username, password, full_name, role_id, team_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([9101, 'phase3_owner', 'hashed', 'Owner User', 2, $testTeamId]);
    $stmt->execute([9102, 'phase3_teammate', 'hashed', 'Teammate User', 2, $testTeamId]);
    $stmt->execute([9103, 'phase3_outsider', 'hashed', 'Outsider User', 2, null]);
    
    echo "Test setup complete.\n\n";
    
    echo "=== Creating Test Prompts ===\n";
    
    // Create test prompts with different visibility levels
    $stmt = $db->prepare("
        INSERT INTO prompts (title, content, category_id, user_id, team_id, visibility, allow_anonymous, team_access_level, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
    ");
    
    // Private prompt
    $stmt->execute(['Private Prompt', 'This is private', 1, 9101, $testTeamId, 'private', 0, 'view']);
    $privatePromptId = $db->lastInsertId();
    
    // Team prompt with edit access
    $stmt->execute(['Team Prompt Edit', 'Team can edit this', 1, 9101, $testTeamId, 'team', 0, 'edit']);
    $teamEditPromptId = $db->lastInsertId();
    
    // Team prompt with view access only
    $stmt->execute(['Team Prompt View', 'Team can view this', 1, 9101, $testTeamId, 'team', 0, 'view']);
    $teamViewPromptId = $db->lastInsertId();
    
    // Public prompt with anonymous allowed
    $stmt->execute(['Public Anon', 'Everyone can see', 1, 9101, $testTeamId, 'public', 1, 'view']);
    $publicAnonId = $db->lastInsertId();
    
    // Public prompt without anonymous
    $stmt->execute(['Public No Anon', 'Authenticated only', 1, 9101, $testTeamId, 'public', 0, 'view']);
    $publicNoAnonId = $db->lastInsertId();
    
    echo "Created 5 test prompts.\n\n";
    
    echo "=== Testing Access Control ===\n";
    
    // TEST 1: Owner has edit access to own prompt
    $access = canAccessPrompt(9101, $privatePromptId);
    testResult(
        'Owner has edit access to private prompt',
        $access && $access['access_level'] === 'edit' && $access['reason'] === 'owner',
        json_encode($access)
    );
    
    // TEST 2: Outsider cannot access private prompt
    $access = canAccessPrompt(9103, $privatePromptId);
    testResult(
        'Outsider denied private prompt',
        !$access,
        'Should be false'
    );
    
    // TEST 3: Teammate can edit team prompt with edit access
    $access = canAccessPrompt(9102, $teamEditPromptId);
    testResult(
        'Teammate has edit access to team prompt',
        $access && $access['access_level'] === 'edit' && $access['reason'] === 'team',
        json_encode($access)
    );
    
    // TEST 4: Teammate can only view team prompt with view access
    $access = canAccessPrompt(9102, $teamViewPromptId);
    testResult(
        'Teammate has view-only access to team prompt',
        $access && $access['access_level'] === 'view' && $access['reason'] === 'team',
        json_encode($access)
    );
    
    // TEST 5: Outsider can access public prompt
    $access = canAccessPrompt(9103, $publicAnonId);
    testResult(
        'Outsider has access to public prompt',
        $access && $access['reason'] === 'public',
        json_encode($access)
    );
    
    echo "\n=== Testing Direct Sharing ===\n";
    
    // TEST 6: Share private prompt with outsider (view access)
    $shareResult = sharePrompt($privatePromptId, 9101, 9103, null, 'view');
    testResult('Share private prompt with user', $shareResult, 'Share failed');
    
    // TEST 7: Outsider now has view access via share
    $access = canAccessPrompt(9103, $privatePromptId);
    testResult(
        'Shared user has view access',
        $access && $access['access_level'] === 'view' && $access['reason'] === 'direct_share',
        json_encode($access)
    );
    
    // TEST 8: Share with edit access
    $shareResult = sharePrompt($teamViewPromptId, 9101, 9103, null, 'edit');
    testResult('Share prompt with edit access', $shareResult, 'Share failed');
    
    $access = canAccessPrompt(9103, $teamViewPromptId);
    testResult(
        'Shared user has edit access',
        $access && $access['access_level'] === 'edit' && $access['reason'] === 'direct_share',
        json_encode($access)
    );
    
    echo "\n=== Testing Visibility Queries ===\n";
    
    // TEST 9: Get prompts accessible to owner
    $query = "
        SELECT DISTINCT p.id
        FROM prompts p
        WHERE p.is_archived = 0 AND (
            p.user_id = ? 
            OR p.visibility = 'public'
            OR (p.visibility = 'team' AND p.team_id = ?)
            OR EXISTS (SELECT 1 FROM prompt_shares ps WHERE ps.prompt_id = p.id AND ps.shared_with_user_id = ?)
        )
        ORDER BY p.id
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([9101, $testTeamId, 9101]);
    $ownerPrompts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    testResult(
        'Owner sees all 5 prompts',
        count($ownerPrompts) === 5,
        'Expected 5, got ' . count($ownerPrompts)
    );
    
    // TEST 10: Get prompts accessible to teammate
    $stmt = $db->prepare($query);
    $stmt->execute([9102, $testTeamId, 9102]);
    $teammatePrompts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    testResult(
        'Teammate sees 4 prompts (not private)',
        count($teammatePrompts) === 4 && !in_array($privatePromptId, $teammatePrompts),
        'Expected 4 without private, got ' . count($teammatePrompts)
    );
    
    // TEST 11: Get prompts accessible to outsider
    $stmt = $db->prepare($query);
    $stmt->execute([9103, null, 9103]); // No team
    $outsiderPrompts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    testResult(
        'Outsider sees 4 prompts (2 public + 2 shared)',
        count($outsiderPrompts) === 4,
        'Expected 4, got ' . count($outsiderPrompts) . ': ' . implode(', ', $outsiderPrompts)
    );
    
    echo "\n=== Testing Anonymous Access ===\n";
    
    // TEST 12: Anonymous can only access public prompts with allow_anonymous = 1
    $query = "
        SELECT id FROM prompts 
        WHERE is_archived = 0 AND visibility = 'public' AND allow_anonymous = 1
    ";
    $stmt = $db->query($query);
    $anonPrompts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    testResult(
        'Anonymous sees only 1 prompt (public with anonymous flag)',
        count($anonPrompts) === 1 && $anonPrompts[0] == $publicAnonId,
        'Expected only publicAnonId, got: ' . implode(', ', $anonPrompts)
    );
    
    echo "\n=== Testing Access Requests ===\n";
    
    // TEST 13: Request access to team prompt (outsider doesn't have access yet)
    $requestResult = requestAccess($teamEditPromptId, 9103, 'Need to see this team prompt');
    testResult('Request access to team prompt', $requestResult, 'Request failed');
    
    // TEST 14: Get pending requests for owner
    $requests = getPendingRequests(9101);
    testResult(
        'Owner sees 1 pending request',
        count($requests) === 1 && $requests[0]['prompt_id'] == $teamEditPromptId,
        'Expected 1 request, got ' . count($requests)
    );
    
    // TEST 15: Approve request creates share
    $approveResult = approveRequest($requests[0]['id'], 9101, 'view');
    testResult('Approve access request', $approveResult, 'Approval failed');
    
    // Now outsider should have access
    $access = canAccessPrompt(9103, $teamEditPromptId);
    testResult(
        'Approved user has access',
        $access && $access['reason'] === 'direct_share',
        json_encode($access)
    );
    
    echo "\n=== Testing Get Shares ===\n";
    
    // TEST 16: Get shares for private prompt (1 from TEST 6)
    $shares = getPromptShares($privatePromptId);
    testResult(
        'Get shares returns correct data',
        count($shares) === 1,
        'Expected 1 share, got ' . count($shares)
    );
    
    // TEST 17: Get shares for team prompt (1 from approved request)
    $teamShares = getPromptShares($teamEditPromptId);
    testResult(
        'Team prompt has share from approved request',
        count($teamShares) === 1,
        'Expected 1 share, got ' . count($teamShares)
    );
    
    // TEST 18: Revoke share from private prompt
    $revokeResult = revokeShare($shares[0]['id']);
    testResult('Revoke share', $revokeResult, 'Revoke failed');
    
    $shares = getPromptShares($privatePromptId);
    testResult(
        'Share count decreased after revoke',
        count($shares) === 0,
        'Expected 0 shares, got ' . count($shares)
    );
    
    echo "\n=== Cleanup ===\n";
    
    // Cleanup
    $db->exec("DELETE FROM prompt_collaborators WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompt_shares WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM access_requests WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompt_versions WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompts WHERE user_id IN (9101, 9102, 9103)");
    $db->exec("UPDATE users SET team_id = NULL WHERE id IN (9101, 9102, 9103)");
    $db->exec("DELETE FROM teams WHERE id = 9201");
    $db->exec("DELETE FROM users WHERE id IN (9101, 9102, 9103)");
    
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
    echo "\n✓ All Phase 3 tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed.\n";
    exit(1);
}
$db->exec("PRAGMA foreign_keys = ON");

// Create test users with unique IDs
$testUserIds = [9101, 9102, 9103];
$testTeamId = 9201;

try {
    // Cleanup first
    $db->exec("DELETE FROM prompt_collaborators WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompt_shares WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM access_requests WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompt_versions WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompts WHERE user_id IN (9101, 9102, 9103)");
    $db->exec("UPDATE users SET team_id = NULL WHERE id IN (9101, 9102, 9103)");
    $db->exec("DELETE FROM teams WHERE id = 9201");
    $db->exec("DELETE FROM users WHERE id IN (9101, 9102, 9103)");
    
    // Create test team
    $stmt = $db->prepare("INSERT INTO teams (id, name) VALUES (?, ?)");
    $stmt->execute([$testTeamId, 'Test Team Phase3']);
    
    // Create test users (Editor role = 2, not Admin)
    $stmt = $db->prepare("
        INSERT INTO users (id, username, password, full_name, role_id, team_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([9101, 'phase3_owner', 'hashed', 'Owner User', 2, $testTeamId]);
    $stmt->execute([9102, 'phase3_teammate', 'hashed', 'Teammate User', 2, $testTeamId]);
    $stmt->execute([9103, 'phase3_outsider', 'hashed', 'Outsider User', 2, null]);
    
    echo "Test setup complete.\n\n";
    
    // TEST 1: Create private prompt
    echo "Test 1: Create private prompt\n";
    $result = apiRequest('prompts.php', 'POST', [
        'title' => 'Private Prompt',
        'content' => 'This is private',
        'category_id' => 1,
        'visibility' => 'private',
        'allow_anonymous' => false
    ], 9101);
    testResult('Create private prompt', isset($result['id']), $result['error'] ?? '');
    $privatePromptId = $result['id'] ?? null;
    
    // TEST 2: Create team prompt with edit access
    echo "Test 2: Create team prompt with edit access\n";
    $result = apiRequest('prompts.php', 'POST', [
        'title' => 'Team Prompt',
        'content' => 'Team can edit this',
        'category_id' => 1,
        'visibility' => 'team',
        'allow_anonymous' => false,
        'team_access_level' => 'edit'
    ], 9101);
    testResult('Create team prompt with edit access', isset($result['id']), $result['error'] ?? '');
    $teamPromptId = $result['id'] ?? null;
    
    // TEST 3: Create public prompt (anonymous allowed)
    echo "Test 3: Create public prompt with anonymous access\n";
    $result = apiRequest('prompts.php', 'POST', [
        'title' => 'Public Prompt',
        'content' => 'Everyone can see this',
        'category_id' => 1,
        'visibility' => 'public',
        'allow_anonymous' => true
    ], 9101);
    testResult('Create public prompt with anonymous', isset($result['id']), $result['error'] ?? '');
    $publicPromptId = $result['id'] ?? null;
    
    // TEST 4: Create public prompt (anonymous NOT allowed)
    echo "Test 4: Create public prompt without anonymous access\n";
    $result = apiRequest('prompts.php', 'POST', [
        'title' => 'Public No Anonymous',
        'content' => 'Authenticated only',
        'category_id' => 1,
        'visibility' => 'public',
        'allow_anonymous' => false
    ], 9101);
    testResult('Create public prompt without anonymous', isset($result['id']), $result['error'] ?? '');
    $publicNoAnonId = $result['id'] ?? null;
    
    // TEST 5: Owner can access their private prompt
    echo "Test 5: Owner accesses private prompt\n";
    $result = apiRequest('prompts.php', 'GET', ['id' => $privatePromptId], 9101);
    testResult(
        'Owner accesses private prompt',
        isset($result['prompt']) && $result['prompt']['user_access_reason'] === 'owner',
        $result['error'] ?? 'No access reason'
    );
    
    // TEST 6: Outsider CANNOT access private prompt
    echo "Test 6: Outsider cannot access private prompt\n";
    $result = apiRequest('prompts.php', 'GET', ['id' => $privatePromptId], 9103);
    testResult(
        'Outsider denied private prompt',
        isset($result['error']) && strpos($result['error'], 'Forbidden') !== false,
        'Should be forbidden'
    );
    
    // TEST 7: Teammate can access team prompt with edit permission
    echo "Test 7: Teammate accesses team prompt\n";
    $result = apiRequest('prompts.php', 'GET', ['id' => $teamPromptId], 9102);
    testResult(
        'Teammate accesses team prompt',
        isset($result['prompt']) && 
        $result['prompt']['user_access_reason'] === 'team' &&
        $result['prompt']['user_access_level'] === 'edit',
        $result['error'] ?? 'Wrong access level/reason'
    );
    
    // TEST 8: Outsider can access public prompt
    echo "Test 8: Outsider accesses public prompt\n";
    $result = apiRequest('prompts.php', 'GET', ['id' => $publicPromptId], 9103);
    testResult(
        'Outsider accesses public prompt',
        isset($result['prompt']) && $result['prompt']['user_access_reason'] === 'public',
        $result['error'] ?? 'No access'
    );
    
    // TEST 9: List prompts shows correct visibility filtering
    echo "Test 9: List prompts with visibility filtering\n";
    $result = apiRequest('prompts.php', 'GET', [], 9102); // Teammate
    $promptIds = array_column($result['prompts'] ?? [], 'id');
    testResult(
        'Teammate sees team and public prompts, not private',
        in_array($teamPromptId, $promptIds) && 
        in_array($publicPromptId, $promptIds) && 
        !in_array($privatePromptId, $promptIds),
        'Wrong prompts visible'
    );
    
    // TEST 10: Update prompt - owner can change visibility
    echo "Test 10: Owner updates prompt visibility\n";
    $result = apiRequest('prompts.php', 'PUT', [
        'id' => $privatePromptId,
        'title' => 'Now Public Prompt',
        'content' => 'Changed to public',
        'category_id' => 1,
        'visibility' => 'public'
    ], 9101);
    testResult('Owner changes visibility', isset($result['success']), $result['error'] ?? '');
    
    // TEST 11: Update prompt - non-owner CANNOT change visibility
    echo "Test 11: Teammate cannot change visibility\n";
    $result = apiRequest('prompts.php', 'PUT', [
        'id' => $teamPromptId,
        'title' => 'Team Prompt',
        'content' => 'Trying to change',
        'category_id' => 1,
        'visibility' => 'private'
    ], 9102);
    testResult(
        'Teammate denied visibility change',
        isset($result['error']) && strpos($result['error'], 'owner can change visibility') !== false,
        'Should be forbidden'
    );
    
    // TEST 12: Teammate CAN edit content (without visibility change)
    echo "Test 12: Teammate edits team prompt content\n";
    $result = apiRequest('prompts.php', 'PUT', [
        'id' => $teamPromptId,
        'title' => 'Updated Team Prompt',
        'content' => 'Edited by teammate',
        'category_id' => 1
    ], 9102);
    testResult('Teammate edits content', isset($result['success']), $result['error'] ?? '');
    
    // TEST 13: Delete prompt - owner only
    echo "Test 13: Only owner can delete\n";
    $result = apiRequest('prompts.php', 'DELETE', ['id' => $teamPromptId], 9102); // Teammate tries
    testResult(
        'Teammate denied delete',
        isset($result['error']) && strpos($result['error'], 'Only the owner') !== false,
        'Should require owner'
    );
    
    // TEST 14: Owner can delete
    echo "Test 14: Owner deletes prompt\n";
    $result = apiRequest('prompts.php', 'DELETE', ['id' => $teamPromptId], 9101);
    testResult('Owner deletes prompt', isset($result['success']), $result['error'] ?? '');
    
    // TEST 15: Public API - anonymous access to allowed prompt
    echo "Test 15: Anonymous accesses public prompt via public API\n";
    $result = apiRequest('public_prompts.php', 'GET', ['id' => $publicPromptId], null);
    testResult(
        'Anonymous sees public prompt',
        isset($result['prompt']) && $result['prompt']['id'] == $publicPromptId,
        $result['error'] ?? 'Not accessible'
    );
    
    // TEST 16: Public API - cannot access public prompt with anonymous disabled
    echo "Test 16: Anonymous cannot access public prompt without anonymous flag\n";
    $result = apiRequest('public_prompts.php', 'GET', ['id' => $publicNoAnonId], null);
    testResult(
        'Anonymous denied non-anonymous public prompt',
        isset($result['error']),
        'Should be denied'
    );
    
    // TEST 17: Public API - list shows only anonymous-allowed prompts
    echo "Test 17: Anonymous lists only anonymous-allowed prompts\n";
    $result = apiRequest('public_prompts.php', 'GET', [], null);
    $publicIds = array_column($result['prompts'] ?? [], 'id');
    testResult(
        'Anonymous list shows correct prompts',
        in_array($publicPromptId, $publicIds) && 
        !in_array($publicNoAnonId, $publicIds),
        'Wrong prompts in list'
    );
    
    echo "\n=== Cleanup ===\n";
    
    // Cleanup
    $db->exec("DELETE FROM prompt_collaborators WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompt_shares WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM access_requests WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompt_versions WHERE prompt_id IN (SELECT id FROM prompts WHERE user_id IN (9101, 9102, 9103))");
    $db->exec("DELETE FROM prompts WHERE user_id IN (9101, 9102, 9103)");
    $db->exec("UPDATE users SET team_id = NULL WHERE id IN (9101, 9102, 9103)");
    $db->exec("DELETE FROM teams WHERE id = 9201");
    $db->exec("DELETE FROM users WHERE id IN (9101, 9102, 9103)");
    
    echo "Cleanup complete.\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Summary ===\n";
echo "Total tests: " . count($tests) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed === 0) {
    echo "\n✓ All Phase 3 tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed.\n";
    exit(1);
}
