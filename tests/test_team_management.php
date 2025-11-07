<?php
/**
 * Test: Team Management API and Prompt Ownership
 */

require_once __DIR__ . '/../database/db.php';

class TeamManagementTest {
    private $db;
    private $testResults = [];
    private $adminSessionId;
    private $editorSessionId;
    private $testTeamId;
    
    public function __construct() {
        $this->db = getDatabase();
    }
    
    public function runAllTests() {
        echo "=== Team Management API Tests ===\n\n";
        
        $this->test_prompts_table_has_ownership_columns();
        $this->test_admin_can_create_team();
        $this->test_admin_can_list_teams();
        $this->test_editor_cannot_create_team();
        $this->test_prompt_created_with_user_id();
        $this->test_prompt_created_with_team_id();
        $this->test_editor_can_edit_team_prompt();
        $this->test_editor_cannot_edit_other_team_prompt();
        
        $this->printSummary();
        $this->cleanup();
    }
    
    private function test_prompts_table_has_ownership_columns() {
        echo "Test 1: Prompts table has user_id and team_id columns\n";
        try {
            $columns = $this->db->query("PRAGMA table_info(prompts)")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
            
            $hasUserId = in_array('user_id', $columnNames);
            $hasTeamId = in_array('team_id', $columnNames);
            
            if ($hasUserId && $hasTeamId) {
                echo "   ✓ PASS: Both user_id and team_id columns exist\n";
                $this->testResults[] = ['name' => 'Prompts ownership columns', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Missing columns (user_id: " . ($hasUserId ? 'yes' : 'no') . ", team_id: " . ($hasTeamId ? 'yes' : 'no') . ")\n";
                $this->testResults[] = ['name' => 'Prompts ownership columns', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Prompts ownership columns', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_admin_can_create_team() {
        echo "Test 2: Admin can create team via API\n";
        try {
            // Simulate admin session
            $_SESSION = [];
            $adminStmt = $this->db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
            $admin = $adminStmt->fetch();
            $_SESSION['user_id'] = $admin['id'];
            
            // Create team directly (API will be created next)
            $stmt = $this->db->prepare("INSERT INTO teams (name, created_by) VALUES (?, ?)");
            $teamName = 'Test Team ' . time();
            $stmt->execute([$teamName, $admin['id']]);
            $this->testTeamId = $this->db->lastInsertId();
            
            if ($this->testTeamId > 0) {
                echo "   ✓ PASS: Team created successfully (ID: {$this->testTeamId})\n";
                $this->testResults[] = ['name' => 'Admin creates team', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Team creation failed\n";
                $this->testResults[] = ['name' => 'Admin creates team', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Admin creates team', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_admin_can_list_teams() {
        echo "Test 3: Admin can list all teams\n";
        try {
            $stmt = $this->db->query("SELECT id, name, created_at FROM teams");
            $teams = $stmt->fetchAll();
            
            if (count($teams) > 0) {
                echo "   ✓ PASS: Retrieved " . count($teams) . " teams\n";
                $this->testResults[] = ['name' => 'Admin lists teams', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: No teams found\n";
                $this->testResults[] = ['name' => 'Admin lists teams', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Admin lists teams', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_editor_cannot_create_team() {
        echo "Test 4: Editor cannot create team (Admin only)\n";
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
                    echo "   ✓ PASS: Editor correctly blocked from creating team\n";
                    $this->testResults[] = ['name' => 'Editor blocked from team creation', 'status' => 'PASS'];
                } else {
                    echo "   ✗ FAIL: Editor was allowed (should be blocked)\n";
                    $this->testResults[] = ['name' => 'Editor blocked from team creation', 'status' => 'FAIL'];
                }
            } else {
                echo "   ⚠ SKIP: No Editor user found\n";
                $this->testResults[] = ['name' => 'Editor blocked from team creation', 'status' => 'SKIP'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Editor blocked from team creation', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_prompt_created_with_user_id() {
        echo "Test 5: Prompt created with user_id (creator)\n";
        try {
            $adminStmt = $this->db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
            $admin = $adminStmt->fetch();
            $_SESSION['user_id'] = $admin['id'];
            
            // Create a test prompt with user_id
            $stmt = $this->db->prepare("
                INSERT INTO prompts (title, content, category_id, user_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute(['Test Prompt', 'Test content', 1, $admin['id']]);
            $promptId = $this->db->lastInsertId();
            
            // Verify prompt has user_id
            $checkStmt = $this->db->prepare("SELECT user_id FROM prompts WHERE id = ?");
            $checkStmt->execute([$promptId]);
            $prompt = $checkStmt->fetch();
            
            if ($prompt && $prompt['user_id'] == $admin['id']) {
                echo "   ✓ PASS: Prompt created with correct user_id\n";
                $this->testResults[] = ['name' => 'Prompt has user_id', 'status' => 'PASS'];
                
                // Cleanup
                $this->db->exec("DELETE FROM prompts WHERE id = $promptId");
            } else {
                echo "   ✗ FAIL: Prompt user_id not set correctly\n";
                $this->testResults[] = ['name' => 'Prompt has user_id', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Prompt has user_id', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_prompt_created_with_team_id() {
        echo "Test 6: Prompt created with team_id\n";
        try {
            $adminStmt = $this->db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
            $admin = $adminStmt->fetch();
            
            // Create a test prompt with team_id
            $stmt = $this->db->prepare("
                INSERT INTO prompts (title, content, category_id, user_id, team_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute(['Team Test Prompt', 'Team content', 1, $admin['id'], $this->testTeamId]);
            $promptId = $this->db->lastInsertId();
            
            // Verify prompt has team_id
            $checkStmt = $this->db->prepare("SELECT user_id, team_id FROM prompts WHERE id = ?");
            $checkStmt->execute([$promptId]);
            $prompt = $checkStmt->fetch();
            
            if ($prompt && $prompt['team_id'] == $this->testTeamId) {
                echo "   ✓ PASS: Prompt created with correct team_id\n";
                $this->testResults[] = ['name' => 'Prompt has team_id', 'status' => 'PASS'];
                
                // Cleanup
                $this->db->exec("DELETE FROM prompts WHERE id = $promptId");
            } else {
                echo "   ✗ FAIL: Prompt team_id not set correctly\n";
                $this->testResults[] = ['name' => 'Prompt has team_id', 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Prompt has team_id', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_editor_can_edit_team_prompt() {
        echo "Test 7: Editor can edit team prompt using canAccessPrompt()\n";
        try {
            // Create an editor user with a team
            $editorRoleStmt = $this->db->query("SELECT id FROM roles WHERE name = 'Editor'");
            $editorRole = $editorRoleStmt->fetch();
            
            $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, full_name, password, role_id, team_id, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['editor_test_' . time(), 'Editor Test', $hashedPassword, $editorRole['id'], $this->testTeamId, 1]);
            $editorUserId = $this->db->lastInsertId();
            
            // Create a prompt for this team
            $stmt = $this->db->prepare("INSERT INTO prompts (title, content, category_id, user_id, team_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['Team Prompt', 'Content', 1, $editorUserId, $this->testTeamId]);
            $promptId = $this->db->lastInsertId();
            
            // Test canAccessPrompt
            $canAccess = canAccessPrompt($editorUserId, $promptId);
            
            if ($canAccess === true) {
                echo "   ✓ PASS: Editor can access team prompt\n";
                $this->testResults[] = ['name' => 'Editor accesses team prompt', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Editor blocked from team prompt\n";
                $this->testResults[] = ['name' => 'Editor accesses team prompt', 'status' => 'FAIL'];
            }
            
            // Cleanup
            $this->db->exec("DELETE FROM prompts WHERE id = $promptId");
            $this->db->exec("DELETE FROM users WHERE id = $editorUserId");
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Editor accesses team prompt', 'status' => 'ERROR'];
        }
        echo "\n";
    }
    
    private function test_editor_cannot_edit_other_team_prompt() {
        echo "Test 8: Editor cannot edit other team's prompt\n";
        try {
            // Create another team
            $stmt = $this->db->prepare("INSERT INTO teams (name, created_by) VALUES (?, ?)");
            $stmt->execute(['Other Team ' . time(), 1]);
            $otherTeamId = $this->db->lastInsertId();
            
            // Create editor in first team
            $editorRoleStmt = $this->db->query("SELECT id FROM roles WHERE name = 'Editor'");
            $editorRole = $editorRoleStmt->fetch();
            
            $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, full_name, password, role_id, team_id, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['editor_team1_' . time(), 'Editor Team 1', $hashedPassword, $editorRole['id'], $this->testTeamId, 1]);
            $editor1Id = $this->db->lastInsertId();
            
            // Create prompt in other team
            $stmt = $this->db->prepare("INSERT INTO prompts (title, content, category_id, user_id, team_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['Other Team Prompt', 'Content', 1, 1, $otherTeamId]);
            $promptId = $this->db->lastInsertId();
            
            // Test canAccessPrompt
            $canAccess = canAccessPrompt($editor1Id, $promptId);
            
            if ($canAccess === false) {
                echo "   ✓ PASS: Editor correctly blocked from other team's prompt\n";
                $this->testResults[] = ['name' => 'Editor blocked from other team', 'status' => 'PASS'];
            } else {
                echo "   ✗ FAIL: Editor allowed access to other team's prompt\n";
                $this->testResults[] = ['name' => 'Editor blocked from other team', 'status' => 'FAIL'];
            }
            
            // Cleanup
            $this->db->exec("DELETE FROM prompts WHERE id = $promptId");
            $this->db->exec("DELETE FROM users WHERE id = $editor1Id");
            $this->db->exec("DELETE FROM teams WHERE id = $otherTeamId");
        } catch (Exception $e) {
            echo "   ✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[] = ['name' => 'Editor blocked from other team', 'status' => 'ERROR'];
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
        // Clean up test team
        if ($this->testTeamId) {
            $this->db->exec("DELETE FROM teams WHERE id = {$this->testTeamId}");
        }
    }
}

// Run tests
session_start();
$tester = new TeamManagementTest();
$tester->runAllTests();
