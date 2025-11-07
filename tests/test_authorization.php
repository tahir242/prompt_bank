<?php
/**
 * Test: Authorization Helper Functions
 * Tests for role-based permission checking, team access, and audit logging
 */

require_once __DIR__ . '/../database/db.php';

class AuthorizationTest {
    private $db;
    private $testResults = [];
    private $testUserId = null;
    private $testTeamId = null;
    
    public function __construct() {
        $this->db = getDatabase();
        $this->setupTestData();
    }
    
    private function setupTestData() {
        // Create a test team
        $stmt = $this->db->prepare("INSERT INTO teams (name, created_by) VALUES (?, ?)");
        $stmt->execute(['Test Team ' . time(), 1]);
        $this->testTeamId = $this->db->lastInsertId();
        
        // Create a test user with Editor role
        $editorRoleStmt = $this->db->query("SELECT id FROM roles WHERE name = 'Editor'");
        $editorRole = $editorRoleStmt->fetch();
        
        $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (username, full_name, password, role_id, team_id, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['testuser_' . time(), 'Test User', $hashedPassword, $editorRole['id'], $this->testTeamId, 1]);
        $this->testUserId = $this->db->lastInsertId();
    }
    
    public function runAllTests() {
        echo "=== Authorization Helper Function Tests ===\n\n";
        
        $this->test_getUserRole_returns_role_data();
        $this->test_hasPermission_checks_correctly();
        $this->test_requireRole_allows_admin();
        $this->test_requireRole_blocks_viewer_from_admin_action();
        $this->test_inactive_user_blocked();
        $this->test_canAccessPrompt_allows_team_member();
        $this->test_canAccessPrompt_blocks_different_team();
        $this->test_logAudit_creates_entry();
        
        $this->printSummary();
        $this->cleanup();
    }
    
    private function test_getUserRole_returns_role_data() {
        echo "Test 1: getUserRole() returns complete role and team data\n";
        try {
            $userRole = getUserRole($this->testUserId);
            
            if ($userRole && 
                isset($userRole['role_name']) && 
                isset($userRole['permissions']) && 
                isset($userRole['team_id']) &&
                isset($userRole['is_active'])) {
                
                echo "   ✓ PASS: getUserRole returns all required fields\n";
                echo "   - Role: {$userRole['role_name']}\n";
                echo "   - Team ID: {$userRole['team_id']}\n";
                echo "   - Active: {$userRole['is_active']}\n";
                $this->testResults[] = ['name' => 'getUserRole returns data', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Missing required fields in getUserRole response\n";
                $this->testResults[] = ['name' => 'getUserRole returns data', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'getUserRole returns data', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_hasPermission_checks_correctly() {
        echo "Test 2: hasPermission() correctly checks permissions\n";
        try {
            // Editor should have create_prompt permission
            $hasCreate = hasPermission($this->testUserId, 'create_prompt');
            
            // Editor should NOT have manage_users permission
            $hasManageUsers = hasPermission($this->testUserId, 'manage_users');
            
            if ($hasCreate === true && $hasManageUsers === false) {
                echo "   ✓ PASS: Permission checks work correctly\n";
                echo "   - create_prompt: true (correct for Editor)\n";
                echo "   - manage_users: false (correct for Editor)\n";
                $this->testResults[] = ['name' => 'hasPermission checks', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Permission check returned incorrect values\n";
                echo "   - create_prompt: " . ($hasCreate ? 'true' : 'false') . " (expected: true)\n";
                echo "   - manage_users: " . ($hasManageUsers ? 'true' : 'false') . " (expected: false)\n";
                $this->testResults[] = ['name' => 'hasPermission checks', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'hasPermission checks', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_requireRole_allows_admin() {
        echo "Test 3: requireRole() allows Admin user\n";
        try {
            // Get admin user ID
            $adminStmt = $this->db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
            $admin = $adminStmt->fetch();
            
            // Simulate session
            $_SESSION['user_id'] = $admin['id'];
            
            // This should NOT throw an exception
            requireRole(['Admin', 'Editor']);
            
            echo "   ✓ PASS: Admin user allowed through requireRole\n";
            $this->testResults[] = ['name' => 'requireRole allows Admin', 'status' => 'PASS'];
        } catch (Exception $e) {
            echo "   ✗ FAIL: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'requireRole allows Admin', 'status' => 'FAIL'];
        }
        echo "\n";
    }
    
    private function test_requireRole_blocks_viewer_from_admin_action() {
        echo "Test 4: requireRole() blocks Viewer from Admin-only action\n";
        try {
            // Get a viewer user
            $viewerRoleStmt = $this->db->query("SELECT id FROM roles WHERE name = 'Viewer'");
            $viewerRole = $viewerRoleStmt->fetch();
            
            $viewerStmt = $this->db->prepare("SELECT id FROM users WHERE role_id = ? LIMIT 1");
            $viewerStmt->execute([$viewerRole['id']]);
            $viewer = $viewerStmt->fetch();
            
            if ($viewer) {
                $_SESSION['user_id'] = $viewer['id'];
                
                $blocked = false;
                try {
                    requireRole(['Admin']);
                } catch (Exception $e) {
                    $blocked = true;
                }
                
                if ($blocked) {
                    echo "   ✓ PASS: Viewer correctly blocked from Admin-only action\n";
                    $this->testResults[] = ['name' => 'requireRole blocks Viewer', 'status' => 'PASS'];
                } else {
                    echo "   ✗ FAIL: Viewer was allowed through (should be blocked)\n";
                    $this->testResults[] = ['name' => 'requireRole blocks Viewer', 'status' => 'FAIL'];
                }
            } else {
                echo "   ⚠ SKIP: No Viewer user found to test\n";
                $this->testResults[] = ['name' => 'requireRole blocks Viewer', 'status' => 'SKIP'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'requireRole blocks Viewer', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_inactive_user_blocked() {
        echo "Test 5: Inactive user is blocked from accessing system\n";
        try {
            // Create an inactive user
            $viewerRoleStmt = $this->db->query("SELECT id FROM roles WHERE name = 'Viewer'");
            $viewerRole = $viewerRoleStmt->fetch();
            
            $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['inactiveuser_' . time(), 'Inactive User', $hashedPassword, $viewerRole['id'], 0]);
            $inactiveUserId = $this->db->lastInsertId();
            
            $_SESSION['user_id'] = $inactiveUserId;
            
            $blocked = false;
            try {
                requireRole(['Viewer', 'Editor', 'Admin']);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'inactive') !== false || strpos($e->getMessage(), 'deactivated') !== false) {
                    $blocked = true;
                }
            }
            
            if ($blocked) {
                echo "   ✓ PASS: Inactive user correctly blocked\n";
                $this->testResults[] = ['name' => 'Inactive user blocked', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Inactive user was allowed through\n";
                $this->testResults[] = ['name' => 'Inactive user blocked', 'status' => 'FAIL'];
            }
            
            // Cleanup
            $this->db->exec("DELETE FROM users WHERE id = $inactiveUserId");
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Inactive user blocked', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_canAccessPrompt_allows_team_member() {
        echo "Test 6: canAccessPrompt() allows team member to access team prompt\n";
        try {
            // This will be tested once we have prompts with team_id
            // For now, test that the function exists and is callable
            if (function_exists('canAccessPrompt')) {
                echo "   ✓ PASS: canAccessPrompt function exists\n";
                $this->testResults[] = ['name' => 'canAccessPrompt exists', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: canAccessPrompt function does not exist\n";
                $this->testResults[] = ['name' => 'canAccessPrompt exists', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'canAccessPrompt exists', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_canAccessPrompt_blocks_different_team() {
        echo "Test 7: canAccessPrompt() blocks access to different team's prompt\n";
        try {
            // Will be fully tested in Phase 3 when prompts have team_id
            echo "   ⚠ SKIP: Deferred to Phase 3 (prompts need team_id column)\n";
            $this->testResults[] = ['name' => 'canAccessPrompt blocks other team', 'status' => 'SKIP'];
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'canAccessPrompt blocks other team', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_logAudit_creates_entry() {
        echo "Test 8: logAudit() creates audit log entry\n";
        try {
            $countBefore = $this->db->query("SELECT COUNT(*) as count FROM audit_log")->fetch()['count'];
            
            logAudit($this->testUserId, 'test_action', 'Test audit log entry', '127.0.0.1');
            
            $countAfter = $this->db->query("SELECT COUNT(*) as count FROM audit_log")->fetch()['count'];
            
            if ($countAfter > $countBefore) {
                // Verify the entry
                $stmt = $this->db->prepare("SELECT * FROM audit_log WHERE user_id = ? AND action = 'test_action' ORDER BY id DESC LIMIT 1");
                $stmt->execute([$this->testUserId]);
                $entry = $stmt->fetch();
                
                if ($entry && $entry['details'] === 'Test audit log entry' && $entry['ip_address'] === '127.0.0.1') {
                    echo "   ✓ PASS: Audit log entry created correctly\n";
                    echo "   - Action: {$entry['action']}\n";
                    echo "   - Details: {$entry['details']}\n";
                    echo "   - IP: {$entry['ip_address']}\n";
                    $this->testResults[] = ['name' => 'logAudit creates entry', 'status' => 'PASS'];
                } else {
                    echo "   ✗ FAIL: Audit log entry created but with incorrect data\n";
                    $this->testResults[] = ['name' => 'logAudit creates entry', 'status' => 'FAIL'];
                }
            } else {
                echo "   ✗ FAIL: No audit log entry was created\n";
                $this->testResults[] = ['name' => 'logAudit creates entry', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'logAudit creates entry', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function printSummary() {
        echo "=== Test Summary ===\n";
        $passed = 0;
        $failed = 0;
        $errors = 0;
        $skipped = 0;
        
        foreach ($this->testResults as $result) {
            if ($result['status'] === 'PASS') $passed++;
            elseif ($result['status'] === 'FAIL') $failed++;
            elseif ($result['status'] === 'ERROR') $errors++;
            elseif ($result['status'] === 'SKIP') $skipped++;
        }
        
        echo "Total: " . count($this->testResults) . " tests\n";
        echo "✓ Passed: $passed\n";
        echo "✗ Failed: $failed\n";
        echo "⚠ Errors: $errors\n";
        echo "⊘ Skipped: $skipped\n";
        
        if ($failed === 0 && $errors === 0) {
            echo "\n🎉 All required tests passed!\n";
        }
    }
    
    private function cleanup() {
        // Clean up test data
        if ($this->testUserId) {
            $this->db->exec("DELETE FROM users WHERE id = {$this->testUserId}");
        }
        if ($this->testTeamId) {
            $this->db->exec("DELETE FROM teams WHERE id = {$this->testTeamId}");
        }
        // Clean up test audit logs
        $this->db->exec("DELETE FROM audit_log WHERE action = 'test_action'");
    }
}

// Run tests
session_start();
$tester = new AuthorizationTest();
$tester->runAllTests();
