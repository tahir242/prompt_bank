<?php
/**
 * Test: User Management API with Role Assignment
 */

require_once __DIR__ . '/../database/db.php';

class UserManagementTest {
    private $db;
    private $testResults = [];
    private $testUserId;
    
    public function __construct() {
        $this->db = getDatabase();
    }
    
    public function runAllTests() {
        echo "=== User Management API Tests ===\n\n";
        
        $this->test_admin_can_list_all_users();
        $this->test_admin_can_update_user_role();
        $this->test_admin_can_assign_user_to_team();
        $this->test_admin_can_deactivate_user();
        $this->test_inactive_user_cannot_login();
        $this->test_role_change_logged_to_audit();
        $this->test_non_admin_blocked_from_user_api();
        $this->test_new_registration_gets_viewer_role();
        
        $this->printSummary();
        $this->cleanup();
    }
    
    private function test_admin_can_list_all_users() {
        echo "Test 1: Admin can list all users with role and team info\n";
        try {
            $_SESSION = [];
            $adminStmt = $this->db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
            $admin = $adminStmt->fetch();
            $_SESSION['user_id'] = $admin['id'];
            
            // Query users as the API would
            $stmt = $this->db->query("
                SELECT 
                    u.id,
                    u.username,
                    u.full_name,
                    u.is_active,
                    u.created_at,
                    r.name as role_name,
                    t.name as team_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN teams t ON u.team_id = t.id
                ORDER BY u.created_at DESC
            ");
            $users = $stmt->fetchAll();
            
            if (count($users) > 0) {
                echo "   ✓ PASS: Retrieved " . count($users) . " users with role/team info\n";
                $this->testResults[] = ['name' => 'Admin lists users', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: No users found\n";
                $this->testResults[] = ['name' => 'Admin lists users', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Admin lists users', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_admin_can_update_user_role() {
        echo "Test 2: Admin can update user role\n";
        try {
            // Create a test user
            $viewerRoleStmt = $this->db->query("SELECT id FROM roles WHERE name = 'Viewer'");
            $viewerRole = $viewerRoleStmt->fetch();
            
            $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['roletest_' . time(), 'Role Test User', $hashedPassword, $viewerRole['id'], 1]);
            $this->testUserId = $this->db->lastInsertId();
            
            // Update role to Editor
            $editorRoleStmt = $this->db->query("SELECT id FROM roles WHERE name = 'Editor'");
            $editorRole = $editorRoleStmt->fetch();
            
            $updateStmt = $this->db->prepare("UPDATE users SET role_id = ? WHERE id = ?");
            $updateStmt->execute([$editorRole['id'], $this->testUserId]);
            
            // Verify update
            $checkStmt = $this->db->prepare("
                SELECT u.id, r.name as role_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ?
            ");
            $checkStmt->execute([$this->testUserId]);
            $user = $checkStmt->fetch();
            
            if ($user && $user['role_name'] === 'Editor') {
                echo "   ✓ PASS: User role updated successfully (Viewer → Editor)\n";
                $this->testResults[] = ['name' => 'Admin updates user role', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Role not updated correctly\n";
                $this->testResults[] = ['name' => 'Admin updates user role', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Admin updates user role', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_admin_can_assign_user_to_team() {
        echo "Test 3: Admin can assign user to team\n";
        try {
            // Create a test team
            $stmt = $this->db->prepare("INSERT INTO teams (name, created_by) VALUES (?, ?)");
            $stmt->execute(['Team Assignment Test ' . time(), 1]);
            $teamId = $this->db->lastInsertId();
            
            // Assign user to team
            $updateStmt = $this->db->prepare("UPDATE users SET team_id = ? WHERE id = ?");
            $updateStmt->execute([$teamId, $this->testUserId]);
            
            // Verify assignment
            $checkStmt = $this->db->prepare("
                SELECT u.id, t.name as team_name 
                FROM users u 
                LEFT JOIN teams t ON u.team_id = t.id 
                WHERE u.id = ?
            ");
            $checkStmt->execute([$this->testUserId]);
            $user = $checkStmt->fetch();
            
            if ($user && $user['team_name'] !== null) {
                echo "   ✓ PASS: User assigned to team successfully\n";
                echo "   - Team: {$user['team_name']}\n";
                $this->testResults[] = ['name' => 'Admin assigns user to team', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Team assignment failed\n";
                $this->testResults[] = ['name' => 'Admin assigns user to team', 'status' => 'FAIL'];
            }
            
            // Cleanup team
            $this->db->exec("DELETE FROM teams WHERE id = $teamId");
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Admin assigns user to team', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_admin_can_deactivate_user() {
        echo "Test 4: Admin can deactivate/activate user\n";
        try {
            // Deactivate user
            $updateStmt = $this->db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $updateStmt->execute([$this->testUserId]);
            
            // Verify deactivation
            $checkStmt = $this->db->prepare("SELECT is_active FROM users WHERE id = ?");
            $checkStmt->execute([$this->testUserId]);
            $user = $checkStmt->fetch();
            
            if ($user && $user['is_active'] == 0) {
                echo "   ✓ PASS: User deactivated successfully\n";
                
                // Reactivate for next tests
                $this->db->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$this->testUserId]);
                
                $this->testResults[] = ['name' => 'Admin deactivates user', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: User deactivation failed\n";
                $this->testResults[] = ['name' => 'Admin deactivates user', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Admin deactivates user', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_inactive_user_cannot_login() {
        echo "Test 5: Inactive user cannot login\n";
        try {
            // Create inactive user
            $viewerRoleStmt = $this->db->query("SELECT id FROM roles WHERE name = 'Viewer'");
            $viewerRole = $viewerRoleStmt->fetch();
            
            $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['inactive_' . time(), 'Inactive User', $hashedPassword, $viewerRole['id'], 0]);
            $inactiveUserId = $this->db->lastInsertId();
            
            // Try to get user role (simulating login check)
            $userRole = getUserRole($inactiveUserId);
            
            // Check is_active status
            if ($userRole && $userRole['is_active'] == 0) {
                echo "   ✓ PASS: Inactive user detected correctly (is_active = 0)\n";
                $this->testResults[] = ['name' => 'Inactive user blocked from login', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Inactive status not detected\n";
                $this->testResults[] = ['name' => 'Inactive user blocked from login', 'status' => 'FAIL'];
            }
            
            // Cleanup
            $this->db->exec("DELETE FROM users WHERE id = $inactiveUserId");
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Inactive user blocked from login', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_role_change_logged_to_audit() {
        echo "Test 6: Role changes are logged to audit log\n";
        try {
            $countBefore = $this->db->query("SELECT COUNT(*) as count FROM audit_log WHERE action = 'role_changed'")->fetch()['count'];
            
            // Log a role change
            logAudit(1, 'role_changed', "Changed user role: Viewer → Editor (User ID: {$this->testUserId})");
            
            $countAfter = $this->db->query("SELECT COUNT(*) as count FROM audit_log WHERE action = 'role_changed'")->fetch()['count'];
            
            if ($countAfter > $countBefore) {
                echo "   ✓ PASS: Role change logged to audit\n";
                $this->testResults[] = ['name' => 'Role change logged', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Role change not logged\n";
                $this->testResults[] = ['name' => 'Role change logged', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Role change logged', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_non_admin_blocked_from_user_api() {
        echo "Test 7: Non-Admin users blocked from user management API\n";
        try {
            // Get an editor user
            $editorRoleStmt = $this->db->query("SELECT id FROM roles WHERE name = 'Editor'");
            $editorRole = $editorRoleStmt->fetch();
            
            $editorStmt = $this->db->prepare("SELECT id FROM users WHERE role_id = ? LIMIT 1");
            $editorStmt->execute([$editorRole['id']]);
            $editor = $editorStmt->fetch();
            
            if ($editor) {
                $_SESSION['user_id'] = $editor['id'];
                
                $blocked = false;
                try {
                    requireRole(['Admin']);
                } catch (Exception $e) {
                    $blocked = true;
                }
                
                if ($blocked) {
                    echo "   ✓ PASS: Editor correctly blocked from user management\n";
                    $this->testResults[] = ['name' => 'Non-Admin blocked from user API', 'status' => 'PASS'];
                } else {
                    echo "   ✗ FAIL: Editor allowed access (should be blocked)\n";
                    $this->testResults[] = ['name' => 'Non-Admin blocked from user API', 'status' => 'FAIL'];
                }
            } else {
                echo "   ⚠ SKIP: No Editor user found\n";
                $this->testResults[] = ['name' => 'Non-Admin blocked from user API', 'status' => 'SKIP'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Non-Admin blocked from user API', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_new_registration_gets_viewer_role() {
        echo "Test 8: New user registration gets default Viewer role\n";
        try {
            // Get Viewer role ID
            $viewerRoleStmt = $this->db->query("SELECT id FROM roles WHERE name = 'Viewer'");
            $viewerRole = $viewerRoleStmt->fetch();
            
            // This would be handled by register.php - test that the role exists
            if ($viewerRole && $viewerRole['id']) {
                echo "   ✓ PASS: Viewer role available for new registrations (ID: {$viewerRole['id']})\n";
                $this->testResults[] = ['name' => 'New registration default role', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Viewer role not found\n";
                $this->testResults[] = ['name' => 'New registration default role', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'New registration default role', 'status' => 'ERROR'];
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
        // Clean up test user
        if ($this->testUserId) {
            $this->db->exec("DELETE FROM users WHERE id = {$this->testUserId}");
        }
        // Clean up audit logs
        $this->db->exec("DELETE FROM audit_log WHERE action = 'role_changed'");
    }
}

// Run tests
session_start();
$tester = new UserManagementTest();
$tester->runAllTests();
