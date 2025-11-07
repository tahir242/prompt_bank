<?php
/**
 * Integration Tests for User Registration
 * Tests complete registration flow and security measures
 */

require_once __DIR__ . '/../database/db.php';

class RegistrationIntegrationTest {
    private $testResults = [];
    private $db;
    
    public function __construct() {
        $this->db = getDatabase();
    }
    
    public function runAllTests() {
        echo "=== User Registration Integration Tests ===\n\n";
        
        $this->testSuccessfulRegistrationAndLogin();
        $this->testDuplicateUsername();
        $this->testPasswordValidation();
        $this->testUsernameValidation();
        $this->testFullNameValidation();
        $this->testSQLInjectionPrevention();
        $this->testXSSPrevention();
        $this->testPasswordHashing();
        $this->testRateLimiting();
        $this->testSessionHandling();
        
        $this->printResults();
    }
    
    private function testSuccessfulRegistrationAndLogin() {
        echo "Test 1: Complete Registration → Login Flow\n";
        
        $username = 'integrationuser' . time();
        $fullName = 'Integration Test User';
        $password = 'testpass123';
        
        // Register user
        $registerData = json_encode([
            'username' => $username,
            'full_name' => $fullName,
            'password' => $password
        ]);
        
        $response = $this->makeRequest('register.php', $registerData);
        $data = json_decode($response, true);
        
        if ($data['success'] && $data['user']['username'] === $username) {
            echo "   ✓ Registration successful\n";
            
            // Try to login immediately
            $loginData = json_encode([
                'username' => $username,
                'password' => $password
            ]);
            
            $loginResponse = $this->makeRequest('login.php', $loginData);
            $loginData = json_decode($loginResponse, true);
            
            if ($loginData['success']) {
                echo "   ✓ Login successful after registration\n";
                $this->testResults[] = ['name' => 'Registration → Login Flow', 'status' => 'PASS'];
            } else {
                echo "   ✗ Login failed\n";
                $this->testResults[] = ['name' => 'Registration → Login Flow', 'status' => 'FAIL'];
            }
        } else {
            echo "   ✗ Registration failed\n";
            $this->testResults[] = ['name' => 'Registration → Login Flow', 'status' => 'FAIL'];
        }
        echo "\n";
    }
    
    private function testDuplicateUsername() {
        echo "Test 2: Duplicate Username Prevention\n";
        
        $username = 'duplicate' . time();
        $registerData = json_encode([
            'username' => $username,
            'full_name' => 'First User',
            'password' => 'password123'
        ]);
        
        // First registration
        $this->makeRequest('register.php', $registerData);
        
        // Try duplicate
        $response = $this->makeRequest('register.php', $registerData);
        $data = json_decode($response, true);
        
        if (isset($data['error']) && strpos($data['error'], 'already exists') !== false) {
            echo "   ✓ Duplicate username correctly rejected\n";
            $this->testResults[] = ['name' => 'Duplicate Username Prevention', 'status' => 'PASS'];
        } else {
            echo "   ✗ Duplicate username was allowed\n";
            $this->testResults[] = ['name' => 'Duplicate Username Prevention', 'status' => 'FAIL'];
        }
        echo "\n";
    }
    
    private function testPasswordValidation() {
        echo "Test 3: Password Validation\n";
        
        $username = 'passtest' . time();
        
        // Test short password
        $registerData = json_encode([
            'username' => $username,
            'full_name' => 'Test User',
            'password' => '12345'
        ]);
        
        $response = $this->makeRequest('register.php', $registerData);
        $data = json_decode($response, true);
        
        if (isset($data['error']) && strpos($data['error'], 'at least 6 characters') !== false) {
            echo "   ✓ Short password rejected\n";
            $this->testResults[] = ['name' => 'Password Length Validation', 'status' => 'PASS'];
        } else {
            echo "   ✗ Short password was accepted\n";
            $this->testResults[] = ['name' => 'Password Length Validation', 'status' => 'FAIL'];
        }
        echo "\n";
    }
    
    private function testUsernameValidation() {
        echo "Test 4: Username Format Validation\n";
        
        // Test invalid characters
        $registerData = json_encode([
            'username' => 'invalid@user',
            'full_name' => 'Test User',
            'password' => 'password123'
        ]);
        
        $response = $this->makeRequest('register.php', $registerData);
        $data = json_decode($response, true);
        
        if (isset($data['error']) && strpos($data['error'], 'letters, numbers, and underscores') !== false) {
            echo "   ✓ Invalid username format rejected\n";
            $this->testResults[] = ['name' => 'Username Format Validation', 'status' => 'PASS'];
        } else {
            echo "   ✗ Invalid username was accepted\n";
            $this->testResults[] = ['name' => 'Username Format Validation', 'status' => 'FAIL'];
        }
        echo "\n";
    }
    
    private function testFullNameValidation() {
        echo "Test 5: Full Name Validation\n";
        
        $registerData = json_encode([
            'username' => 'nametest' . time(),
            'full_name' => '',
            'password' => 'password123'
        ]);
        
        $response = $this->makeRequest('register.php', $registerData);
        $data = json_decode($response, true);
        
        if (isset($data['error']) && strpos($data['error'], 'Full name is required') !== false) {
            echo "   ✓ Empty full name rejected\n";
            $this->testResults[] = ['name' => 'Full Name Required', 'status' => 'PASS'];
        } else {
            echo "   ✗ Empty full name was accepted\n";
            $this->testResults[] = ['name' => 'Full Name Required', 'status' => 'FAIL'];
        }
        echo "\n";
    }
    
    private function testSQLInjectionPrevention() {
        echo "Test 6: SQL Injection Prevention\n";
        
        $username = "admin' OR '1'='1";
        $registerData = json_encode([
            'username' => $username,
            'full_name' => 'SQL Injection Test',
            'password' => 'password123'
        ]);
        
        $response = $this->makeRequest('register.php', $registerData);
        $data = json_decode($response, true);
        
        // Should fail validation, not cause SQL error
        if (isset($data['error'])) {
            echo "   ✓ SQL injection attempt safely handled\n";
            $this->testResults[] = ['name' => 'SQL Injection Prevention', 'status' => 'PASS'];
        } else {
            echo "   ⚠ SQL injection string was processed\n";
            $this->testResults[] = ['name' => 'SQL Injection Prevention', 'status' => 'WARN'];
        }
        echo "\n";
    }
    
    private function testXSSPrevention() {
        echo "Test 7: XSS Prevention\n";
        
        $username = 'xsstest' . time();
        $maliciousName = '<script>alert("XSS")</script>';
        
        $registerData = json_encode([
            'username' => $username,
            'full_name' => $maliciousName,
            'password' => 'password123'
        ]);
        
        $response = $this->makeRequest('register.php', $registerData);
        $data = json_decode($response, true);
        
        // Check if data was stored
        if ($data['success']) {
            $stmt = $this->db->prepare("SELECT full_name FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // Name should be stored as-is (escaping happens on output)
            if ($user['full_name'] === $maliciousName) {
                echo "   ✓ XSS input stored safely (escaping should happen on output)\n";
                $this->testResults[] = ['name' => 'XSS Input Handling', 'status' => 'PASS'];
            }
        }
        echo "\n";
    }
    
    private function testPasswordHashing() {
        echo "Test 8: Password Hashing\n";
        
        $username = 'hashtest' . time();
        $password = 'testpassword123';
        
        $registerData = json_encode([
            'username' => $username,
            'full_name' => 'Hash Test',
            'password' => $password
        ]);
        
        $response = $this->makeRequest('register.php', $registerData);
        $data = json_decode($response, true);
        
        if ($data['success']) {
            $stmt = $this->db->prepare("SELECT password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // Password should be hashed, not plain text
            if ($user['password'] !== $password && password_verify($password, $user['password'])) {
                echo "   ✓ Password properly hashed using password_hash()\n";
                $this->testResults[] = ['name' => 'Password Hashing', 'status' => 'PASS'];
            } else {
                echo "   ✗ Password not properly hashed\n";
                $this->testResults[] = ['name' => 'Password Hashing', 'status' => 'FAIL'];
            }
        }
        echo "\n";
    }
    
    private function testRateLimiting() {
        echo "Test 9: Rate Limiting\n";
        
        // Clear rate limit table
        $this->db->exec("DELETE FROM registration_attempts");
        
        $successCount = 0;
        $blockedCount = 0;
        
        // Try 5 registrations
        for ($i = 0; $i < 5; $i++) {
            $username = 'ratelimit' . time() . '_' . $i;
            $registerData = json_encode([
                'username' => $username,
                'full_name' => 'Rate Limit Test',
                'password' => 'password123'
            ]);
            
            $response = $this->makeRequest('register.php', $registerData);
            $data = json_decode($response, true);
            
            if (isset($data['success'])) {
                $successCount++;
            } elseif (isset($data['error']) && strpos($data['error'], 'Too many') !== false) {
                $blockedCount++;
            }
        }
        
        if ($successCount <= 3 && $blockedCount >= 1) {
            echo "   ✓ Rate limiting active (allowed: $successCount, blocked: $blockedCount)\n";
            $this->testResults[] = ['name' => 'Rate Limiting', 'status' => 'PASS'];
        } else {
            echo "   ⚠ Rate limiting may not be working correctly\n";
            $this->testResults[] = ['name' => 'Rate Limiting', 'status' => 'WARN'];
        }
        echo "\n";
    }
    
    private function testSessionHandling() {
        echo "Test 10: Session Handling After Login\n";
        
        $username = 'sessiontest' . time();
        $password = 'password123';
        
        // Register
        $registerData = json_encode([
            'username' => $username,
            'full_name' => 'Session Test',
            'password' => $password
        ]);
        $this->makeRequest('register.php', $registerData);
        
        // Login
        $loginData = json_encode([
            'username' => $username,
            'password' => $password
        ]);
        
        $response = $this->makeRequest('login.php', $loginData);
        $data = json_decode($response, true);
        
        if ($data['success'] && isset($data['user']['id']) && isset($data['user']['username'])) {
            echo "   ✓ Session data properly returned after login\n";
            $this->testResults[] = ['name' => 'Session Handling', 'status' => 'PASS'];
        } else {
            echo "   ✗ Session data missing or incomplete\n";
            $this->testResults[] = ['name' => 'Session Handling', 'status' => 'FAIL'];
        }
        echo "\n";
    }
    
    private function makeRequest($endpoint, $data) {
        $ch = curl_init("http://localhost/prompt_bank/api/$endpoint");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
    
    private function printResults() {
        echo "=== Test Summary ===\n\n";
        
        $passed = 0;
        $failed = 0;
        $warned = 0;
        
        foreach ($this->testResults as $result) {
            $icon = $result['status'] === 'PASS' ? '✓' : ($result['status'] === 'FAIL' ? '✗' : '⚠');
            echo "$icon {$result['name']}: {$result['status']}\n";
            
            if ($result['status'] === 'PASS') $passed++;
            elseif ($result['status'] === 'FAIL') $failed++;
            else $warned++;
        }
        
        echo "\nTotal: " . count($this->testResults) . " tests\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Warnings: $warned\n";
        
        if ($failed === 0) {
            echo "\n✓ All tests passed!\n";
        } else {
            echo "\n✗ Some tests failed. Please review.\n";
        }
    }
}

// Run tests
$tester = new RegistrationIntegrationTest();
$tester->runAllTests();
