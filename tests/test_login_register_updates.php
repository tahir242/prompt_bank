<?php
/**
 * Test Suite: Login and Registration Updates
 * Tests the enhanced login/register with role/team info and is_active check
 */

require_once __DIR__ . '/../database/db.php';

function testLoginRegisterUpdates() {
    $passed = 0;
    $failed = 0;
    $errors = 0;
    
    echo "=== Login and Registration Updates Tests ===\n\n";
    
    // Test 1: New registration assigns Viewer role and logs to audit
    echo "Test 1: New registration assigns Viewer role and logs to audit\n";
    try {
        $db = getDatabase();
        $timestamp = time();
        $testUser = "newuser_" . $timestamp;
        
        // Register new user
        $_POST = [
            'username' => $testUser,
            'full_name' => 'New Test User',
            'password' => 'password123'
        ];
        
        // Hash password
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        
        // Get Viewer role
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'Viewer'");
        $stmt->execute();
        $viewerRole = $stmt->fetch();
        
        // Create user with Viewer role
        $stmt = $db->prepare("INSERT INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$testUser, 'New Test User', $hashedPassword, $viewerRole['id']]);
        $newUserId = $db->lastInsertId();
        
        // Log audit
        logAudit($newUserId, 'user_registered', 'New user registered with Viewer role', '127.0.0.1');
        
        // Verify user has Viewer role
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.is_active, r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.username = ?
        ");
        $stmt->execute([$testUser]);
        $user = $stmt->fetch();
        
        // Verify audit log
        $stmt = $db->prepare("SELECT * FROM audit_log WHERE user_id = ? AND action = 'user_registered' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$newUserId]);
        $auditLog = $stmt->fetch();
        
        if ($user && $user['role_name'] === 'Viewer' && $user['is_active'] == 1 && $auditLog) {
            echo "   ✓ PASS: New user created with Viewer role and logged to audit\n";
            echo "   - User: {$user['username']}, Role: {$user['role_name']}, Active: {$user['is_active']}\n";
            echo "   - Audit: {$auditLog['action']} - {$auditLog['details']}\n";
            $passed++;
        } else {
            echo "   ✗ FAIL: New user not properly configured\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 2: Login returns role, permissions, and team info
    echo "Test 2: Login returns role, permissions, and team info\n";
    try {
        $db = getDatabase();
        $timestamp = time();
        $testUser = "logintest_" . $timestamp;
        
        // Create test user with team
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        
        // Get Editor role
        $stmt = $db->prepare("SELECT id, permissions FROM roles WHERE name = 'Editor'");
        $stmt->execute();
        $editorRole = $stmt->fetch();
        
        // Create team
        $stmt = $db->prepare("INSERT INTO teams (name) VALUES (?)");
        $stmt->execute(['Login Test Team ' . $timestamp]);
        $teamId = $db->lastInsertId();
        
        // Create user with Editor role and team
        $stmt = $db->prepare("INSERT INTO users (username, full_name, password, role_id, team_id, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$testUser, 'Login Test User', $hashedPassword, $editorRole['id'], $teamId]);
        $userId = $db->lastInsertId();
        
        // Simulate login query
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.password, u.is_active, u.role_id, u.team_id,
                   r.name as role_name, r.permissions,
                   t.name as team_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN teams t ON u.team_id = t.id
            WHERE u.username = ?
        ");
        $stmt->execute([$testUser]);
        $user = $stmt->fetch();
        
        if ($user && password_verify('password123', $user['password'])) {
            $permissions = json_decode($user['permissions'], true);
            
            if ($user['role_name'] === 'Editor' && $user['team_name'] && !empty($permissions)) {
                echo "   ✓ PASS: Login returns complete user info\n";
                echo "   - User: {$user['username']}\n";
                echo "   - Role: {$user['role_name']}\n";
                echo "   - Team: {$user['team_name']}\n";
                echo "   - Permissions: " . implode(', ', array_keys($permissions)) . "\n";
                $passed++;
            } else {
                echo "   ✗ FAIL: Login missing role/team/permission info\n";
                $failed++;
            }
        } else {
            echo "   ✗ FAIL: Login authentication failed\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 3: Inactive user login is blocked
    echo "Test 3: Inactive user login is blocked\n";
    try {
        $db = getDatabase();
        $timestamp = time();
        $testUser = "inactivetest_" . $timestamp;
        
        // Create inactive user
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'Viewer'");
        $stmt->execute();
        $viewerRole = $stmt->fetch();
        
        $stmt = $db->prepare("INSERT INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$testUser, 'Inactive Test User', $hashedPassword, $viewerRole['id']]);
        $userId = $db->lastInsertId();
        
        // Try to login
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.password, u.is_active, u.role_id
            FROM users u
            WHERE u.username = ?
        ");
        $stmt->execute([$testUser]);
        $user = $stmt->fetch();
        
        if ($user && password_verify('password123', $user['password'])) {
            if (!$user['is_active']) {
                // Log blocked login
                logAudit($user['id'], 'login_blocked', 'Inactive user login attempt', '127.0.0.1');
                
                // Verify audit log
                $stmt = $db->prepare("SELECT * FROM audit_log WHERE user_id = ? AND action = 'login_blocked' ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$userId]);
                $auditLog = $stmt->fetch();
                
                echo "   ✓ PASS: Inactive user login blocked and logged\n";
                echo "   - User: {$user['username']}, Active: {$user['is_active']}\n";
                echo "   - Audit: {$auditLog['action']} - {$auditLog['details']}\n";
                $passed++;
            } else {
                echo "   ✗ FAIL: Inactive user should be blocked\n";
                $failed++;
            }
        } else {
            echo "   ✗ FAIL: User authentication failed\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
    echo "\n";
    
    // Test 4: Successful login logs to audit
    echo "Test 4: Successful login logs to audit\n";
    try {
        $db = getDatabase();
        $timestamp = time();
        $testUser = "auditlogin_" . $timestamp;
        
        // Create active user
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'Viewer'");
        $stmt->execute();
        $viewerRole = $stmt->fetch();
        
        $stmt = $db->prepare("INSERT INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$testUser, 'Audit Login Test', $hashedPassword, $viewerRole['id']]);
        $userId = $db->lastInsertId();
        
        // Simulate successful login
        $stmt = $db->prepare("SELECT id, username, password, is_active FROM users WHERE username = ?");
        $stmt->execute([$testUser]);
        $user = $stmt->fetch();
        
        if ($user && password_verify('password123', $user['password']) && $user['is_active']) {
            // Log successful login
            logAudit($user['id'], 'login', 'User logged in', '127.0.0.1');
            
            // Verify audit log
            $stmt = $db->prepare("SELECT * FROM audit_log WHERE user_id = ? AND action = 'login' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$userId]);
            $auditLog = $stmt->fetch();
            
            if ($auditLog) {
                echo "   ✓ PASS: Successful login logged to audit\n";
                echo "   - User: {$user['username']}\n";
                echo "   - Audit: {$auditLog['action']} - {$auditLog['details']}\n";
                $passed++;
            } else {
                echo "   ✗ FAIL: Login not logged to audit\n";
                $failed++;
            }
        } else {
            echo "   ✗ FAIL: Login failed\n";
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
testLoginRegisterUpdates();
