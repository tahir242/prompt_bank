<?php
/**
 * Test: Role-Based Access Control Database Schema
 * Tests for roles, teams, user modifications, and audit logging tables
 */

require_once __DIR__ . '/../database/db.php';

class RoleSchemaTest {
    private $db;
    private $testResults = [];
    
    public function __construct() {
        $this->db = getDatabase();
    }
    
    public function runAllTests() {
        echo "=== Role Schema Tests ===\n\n";
        
        $this->test_roles_table_exists();
        $this->test_teams_table_exists();
        $this->test_users_role_id_column_exists();
        $this->test_users_team_id_column_exists();
        $this->test_users_is_active_column_exists();
        $this->test_audit_log_table_exists();
        $this->test_default_roles_created();
        $this->test_foreign_key_constraints();
        $this->test_admin_user_has_admin_role();
        
        $this->printSummary();
    }
    
    private function test_roles_table_exists() {
        echo "Test 1: Roles table exists\n";
        try {
            $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='roles'");
            $table = $result->fetch();
            
            if ($table) {
                echo "   ✓ PASS: Roles table exists\n";
                
                // Verify columns
                $columns = $this->db->query("PRAGMA table_info(roles)")->fetchAll(PDO::FETCH_ASSOC);
                $columnNames = array_column($columns, 'name');
                
                $requiredColumns = ['id', 'name', 'description', 'permissions', 'created_at'];
                $missingColumns = array_diff($requiredColumns, $columnNames);
                
                if (empty($missingColumns)) {
                    echo "   ✓ All required columns present\n";
                    $this->testResults[] = ['name' => 'Roles table structure', 'status' => 'PASS'];
                } else {
                    echo "   ✗ FAIL: Missing columns: " . implode(', ', $missingColumns) . "\n";
                    $this->testResults[] = ['name' => 'Roles table structure', 'status' => 'FAIL'];
                }
            } else {
                echo "   ✗ FAIL: Roles table does not exist\n";
                $this->testResults[] = ['name' => 'Roles table exists', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Roles table exists', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_teams_table_exists() {
        echo "Test 2: Teams table exists\n";
        try {
            $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='teams'");
            $table = $result->fetch();
            
            if ($table) {
                echo "   ✓ PASS: Teams table exists\n";
                
                // Verify columns
                $columns = $this->db->query("PRAGMA table_info(teams)")->fetchAll(PDO::FETCH_ASSOC);
                $columnNames = array_column($columns, 'name');
                
                $requiredColumns = ['id', 'name', 'created_at', 'created_by'];
                $missingColumns = array_diff($requiredColumns, $columnNames);
                
                if (empty($missingColumns)) {
                    echo "   ✓ All required columns present\n";
                    $this->testResults[] = ['name' => 'Teams table structure', 'status' => 'PASS'];
                } else {
                    echo "   ✗ FAIL: Missing columns: " . implode(', ', $missingColumns) . "\n";
                    $this->testResults[] = ['name' => 'Teams table structure', 'status' => 'FAIL'];
                }
            } else {
                echo "   ✗ FAIL: Teams table does not exist\n";
                $this->testResults[] = ['name' => 'Teams table exists', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Teams table exists', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_users_role_id_column_exists() {
        echo "Test 3: Users table has role_id column\n";
        try {
            $columns = $this->db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
            
            if (in_array('role_id', $columnNames)) {
                echo "   ✓ PASS: role_id column exists\n";
                $this->testResults[] = ['name' => 'Users role_id column', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: role_id column missing\n";
                $this->testResults[] = ['name' => 'Users role_id column', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Users role_id column', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_users_team_id_column_exists() {
        echo "Test 4: Users table has team_id column\n";
        try {
            $columns = $this->db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
            
            if (in_array('team_id', $columnNames)) {
                echo "   ✓ PASS: team_id column exists\n";
                $this->testResults[] = ['name' => 'Users team_id column', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: team_id column missing\n";
                $this->testResults[] = ['name' => 'Users team_id column', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Users team_id column', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_users_is_active_column_exists() {
        echo "Test 5: Users table has is_active column\n";
        try {
            $columns = $this->db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
            
            if (in_array('is_active', $columnNames)) {
                echo "   ✓ PASS: is_active column exists\n";
                
                // Check default value
                foreach ($columns as $column) {
                    if ($column['name'] === 'is_active' && $column['dflt_value'] === '1') {
                        echo "   ✓ Default value is 1 (active)\n";
                    }
                }
                
                $this->testResults[] = ['name' => 'Users is_active column', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: is_active column missing\n";
                $this->testResults[] = ['name' => 'Users is_active column', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Users is_active column', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_audit_log_table_exists() {
        echo "Test 6: Audit log table exists\n";
        try {
            $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='audit_log'");
            $table = $result->fetch();
            
            if ($table) {
                echo "   ✓ PASS: Audit log table exists\n";
                
                // Verify columns
                $columns = $this->db->query("PRAGMA table_info(audit_log)")->fetchAll(PDO::FETCH_ASSOC);
                $columnNames = array_column($columns, 'name');
                
                $requiredColumns = ['id', 'user_id', 'action', 'details', 'ip_address', 'created_at'];
                $missingColumns = array_diff($requiredColumns, $columnNames);
                
                if (empty($missingColumns)) {
                    echo "   ✓ All required columns present\n";
                    $this->testResults[] = ['name' => 'Audit log table structure', 'status' => 'PASS'];
                } else {
                    echo "   ✗ FAIL: Missing columns: " . implode(', ', $missingColumns) . "\n";
                    $this->testResults[] = ['name' => 'Audit log table structure', 'status' => 'FAIL'];
                }
            } else {
                echo "   ✗ FAIL: Audit log table does not exist\n";
                $this->testResults[] = ['name' => 'Audit log table exists', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Audit log table exists', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_default_roles_created() {
        echo "Test 7: Default roles are created\n";
        try {
            $stmt = $this->db->query("SELECT name FROM roles ORDER BY id");
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $expectedRoles = ['Admin', 'Editor', 'Viewer'];
            $missingRoles = array_diff($expectedRoles, $roles);
            
            if (empty($missingRoles)) {
                echo "   ✓ PASS: All default roles exist (" . implode(', ', $roles) . ")\n";
                $this->testResults[] = ['name' => 'Default roles created', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Missing roles: " . implode(', ', $missingRoles) . "\n";
                $this->testResults[] = ['name' => 'Default roles created', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Default roles created', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_foreign_key_constraints() {
        echo "Test 8: Foreign key constraints are defined\n";
        try {
            // Check users.role_id foreign key
            $fkList = $this->db->query("PRAGMA foreign_key_list(users)")->fetchAll(PDO::FETCH_ASSOC);
            
            $hasRoleFk = false;
            $hasTeamFk = false;
            
            foreach ($fkList as $fk) {
                if ($fk['from'] === 'role_id' && $fk['table'] === 'roles') {
                    $hasRoleFk = true;
                }
                if ($fk['from'] === 'team_id' && $fk['table'] === 'teams') {
                    $hasTeamFk = true;
                }
            }
            
            if ($hasRoleFk && $hasTeamFk) {
                echo "   ✓ PASS: Foreign key constraints properly defined\n";
                $this->testResults[] = ['name' => 'Foreign key constraints', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Missing foreign keys (role_id: " . ($hasRoleFk ? 'yes' : 'no') . ", team_id: " . ($hasTeamFk ? 'yes' : 'no') . ")\n";
                $this->testResults[] = ['name' => 'Foreign key constraints', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Foreign key constraints', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_admin_user_has_admin_role() {
        echo "Test 9: Admin user has Admin role assigned\n";
        try {
            $stmt = $this->db->prepare("
                SELECT u.username, r.name as role_name, u.is_active
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.username = 'admin'
            ");
            $stmt->execute();
            $user = $stmt->fetch();
            
            if ($user && $user['role_name'] === 'Admin' && $user['is_active'] == 1) {
                echo "   ✓ PASS: Admin user has Admin role and is active\n";
                $this->testResults[] = ['name' => 'Admin user role assignment', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Admin user role: " . ($user['role_name'] ?? 'null') . ", active: " . ($user['is_active'] ?? 'null') . "\n";
                $this->testResults[] = ['name' => 'Admin user role assignment', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Admin user role assignment', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function printSummary() {
        echo "=== Test Summary ===\n";
        $passed = 0;
        $failed = 0;
        $errors = 0;
        
        foreach ($this->testResults as $result) {
            if ($result['status'] === 'PASS') $passed++;
            elseif ($result['status'] === 'FAIL') $failed++;
            elseif ($result['status'] === 'ERROR') $errors++;
        }
        
        echo "Total: " . count($this->testResults) . " tests\n";
        echo "✓ Passed: $passed\n";
        echo "✗ Failed: $failed\n";
        echo "⚠ Errors: $errors\n";
        
        if ($failed === 0 && $errors === 0) {
            echo "\n🎉 All tests passed!\n";
        }
    }
}

// Run tests
$tester = new RoleSchemaTest();
$tester->runAllTests();
