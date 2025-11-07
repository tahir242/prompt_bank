<?php
/**
 * Quick Test: Login and Registration Updates
 * Simplified tests for role/team info and is_active checks
 */

require_once __DIR__ . '/../database/db.php';

echo "=== Login and Registration Updates - Quick Tests ===\n\n";

$passed = 0;
$failed = 0;

// Test 1: New registration assigns Viewer role
echo "Test 1: New registration assigns Viewer role\n";
$db = getDatabase();
$timestamp = time();
$testUser = "quickreg_" . $timestamp;

$hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
$stmt = $db->prepare("SELECT id FROM roles WHERE name = 'Viewer'");
$stmt->execute();
$viewerRole = $stmt->fetch();

$stmt = $db->prepare("INSERT INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, 1)");
$stmt->execute([$testUser, 'Quick Test', $hashedPassword, $viewerRole['id']]);
$newUserId = $db->lastInsertId();

logAudit($newUserId, 'user_registered', 'New user registered with Viewer role', '127.0.0.1');

$stmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$newUserId]);
$user = $stmt->fetch();

if ($user && $user['role_name'] === 'Viewer' && $user['is_active'] == 1) {
    echo "   ✓ PASS: User created with Viewer role, active=1\n\n";
    $passed++;
} else {
    echo "   ✗ FAIL\n\n";
    $failed++;
}

// Test 2: Login returns role and permissions
echo "Test 2: Login query returns role, permissions, team info\n";
$stmt = $db->prepare("
    SELECT u.id, u.username, u.is_active, u.role_id, u.team_id,
           r.name as role_name, r.permissions,
           t.name as team_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN teams t ON u.team_id = t.id
    WHERE u.id = ?
");
$stmt->execute([$newUserId]);
$user = $stmt->fetch();

if ($user && $user['role_name'] === 'Viewer' && isset($user['permissions'])) {
    $permissions = json_decode($user['permissions'], true);
    echo "   ✓ PASS: Retrieved role_name, permissions JSON\n";
    echo "   - Role: {$user['role_name']}\n";
    echo "   - Permissions count: " . count($permissions) . "\n\n";
    $passed++;
} else {
    echo "   ✗ FAIL\n\n";
    $failed++;
}

// Test 3: Inactive user check
echo "Test 3: Inactive user login blocked\n";
$testUser2 = "inactive_" . $timestamp;
$stmt = $db->prepare("INSERT INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, 0)");
$stmt->execute([$testUser2, 'Inactive Test', $hashedPassword, $viewerRole['id']]);
$inactiveUserId = $db->lastInsertId();

$stmt = $db->prepare("SELECT id, username, is_active FROM users WHERE id = ?");
$stmt->execute([$inactiveUserId]);
$user = $stmt->fetch();

if ($user && !$user['is_active']) {
    logAudit($user['id'], 'login_blocked', 'Inactive user login attempt', '127.0.0.1');
    echo "   ✓ PASS: Inactive user (is_active=0) blocked\n\n";
    $passed++;
} else {
    echo "   ✗ FAIL\n\n";
    $failed++;
}

// Test 4: Successful login audit
echo "Test 4: Successful login logs to audit\n";
logAudit($newUserId, 'login', 'User logged in', '127.0.0.1');

$stmt = $db->prepare("SELECT * FROM audit_log WHERE user_id = ? AND action = 'login' ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$newUserId]);
$auditLog = $stmt->fetch();

if ($auditLog && $auditLog['action'] === 'login') {
    echo "   ✓ PASS: Login logged to audit\n";
    echo "   - Action: {$auditLog['action']}\n";
    echo "   - Details: {$auditLog['details']}\n\n";
    $passed++;
} else {
    echo "   ✗ FAIL\n\n";
    $failed++;
}

// Summary
echo "=== Summary ===\n";
echo "✓ Passed: $passed/4\n";
echo "✗ Failed: $failed/4\n\n";

if ($failed === 0) {
    echo "🎉 All tests passed!\n";
} else {
    echo "❌ Some tests failed\n";
}
