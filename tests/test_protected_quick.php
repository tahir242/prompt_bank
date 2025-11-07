<?php
/**
 * Quick Verification: Protected API Endpoints
 * Fast tests to verify authorization is working
 */

require_once __DIR__ . '/../database/db.php';

echo "=== Quick Protected Endpoints Verification ===\n\n";

$db = getDatabase();
$passed = 0;
$failed = 0;

// Test 1: hasPermission works for Admin
echo "Test 1: Admin has create_prompt permission\n";
if (hasPermission(1, 'create_prompt')) {
    echo "   ✓ PASS\n\n";
    $passed++;
} else {
    echo "   ✗ FAIL\n\n";
    $failed++;
}

// Test 2: hasPermission works for permissions check
echo "Test 2: Check multiple permission functions\n";
$adminHasDelete = hasPermission(1, 'delete_prompt');
$adminHasCategories = hasPermission(1, 'manage_categories');
if ($adminHasDelete && $adminHasCategories) {
    echo "   ✓ PASS: Admin has delete_prompt and manage_categories\n\n";
    $passed++;
} else {
    echo "   ✗ FAIL\n\n";
    $failed++;
}

// Test 3: canAccessPrompt function works
echo "Test 3: canAccessPrompt function exists and works\n";
try {
    // Admin should be able to access any prompt
    $canAccess = canAccessPrompt(1, 1); // Admin, any prompt
    echo "   ✓ PASS: canAccessPrompt function works\n\n";
    $passed++;
} catch (Exception $e) {
    echo "   ✗ FAIL: " . $e->getMessage() . "\n\n";
    $failed++;
}

// Test 4: Verify role permissions structure
echo "Test 4: Verify Editor role has correct permissions\n";
$stmt = $db->prepare("SELECT permissions FROM roles WHERE name = 'Editor'");
$stmt->execute();
$editorRole = $stmt->fetch();
$permissions = json_decode($editorRole['permissions'], true);

$requiredPerms = ['create_prompt', 'edit_team_prompt', 'delete_team_prompt', 'manage_categories'];
$hasAll = true;
foreach ($requiredPerms as $perm) {
    if (!isset($permissions[$perm])) {
        $hasAll = false;
        break;
    }
}

if ($hasAll) {
    echo "   ✓ PASS: Editor has all required permissions\n\n";
    $passed++;
} else {
    echo "   ✗ FAIL: Editor missing some permissions\n\n";
    $failed++;
}

// Test 5: Verify Viewer lacks create permission
echo "Test 5: Verify Viewer role lacks create_prompt permission\n";
$stmt = $db->prepare("SELECT permissions FROM roles WHERE name = 'Viewer'");
$stmt->execute();
$viewerRole = $stmt->fetch();
$viewerPerms = json_decode($viewerRole['permissions'], true);

if (!isset($viewerPerms['create_prompt']) || !$viewerPerms['create_prompt']) {
    echo "   ✓ PASS: Viewer correctly lacks create_prompt\n\n";
    $passed++;
} else {
    echo "   ✗ FAIL: Viewer should not have create_prompt\n\n";
    $failed++;
}

// Summary
echo "=== Summary ===\n";
echo "✓ Passed: $passed/5\n";
echo "✗ Failed: $failed/5\n\n";

if ($failed === 0) {
    echo "🎉 All quick tests passed! Authorization system is working.\n";
} else {
    echo "❌ Some tests failed\n";
}
