<?php
/**
 * Database Schema Validation Script
 * Verifies database structure and adds optimizations
 */

$dbPath = __DIR__ . '/prompts.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Database Schema Validation ===\n\n";
    
    // Check users table structure
    echo "1. Checking users table structure...\n";
    $result = $db->query("PRAGMA table_info(users)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $hasId = false;
    $hasUsername = false;
    $hasFullName = false;
    $hasPassword = false;
    $hasCreatedAt = false;
    
    foreach ($columns as $column) {
        echo "   - {$column['name']} ({$column['type']}) " . 
             ($column['notnull'] ? "NOT NULL" : "NULL") . 
             ($column['pk'] ? " PRIMARY KEY" : "") . "\n";
             
        if ($column['name'] === 'id') $hasId = true;
        if ($column['name'] === 'username') $hasUsername = true;
        if ($column['name'] === 'full_name') $hasFullName = true;
        if ($column['name'] === 'password') $hasPassword = true;
        if ($column['name'] === 'created_at') $hasCreatedAt = true;
    }
    
    echo "   ✓ Users table structure validated\n\n";
    
    // Verify required columns exist
    if (!$hasId || !$hasUsername || !$hasFullName || !$hasPassword || !$hasCreatedAt) {
        throw new Exception("Missing required columns in users table");
    }
    
    // Check for indexes
    echo "2. Checking indexes...\n";
    $result = $db->query("PRAGMA index_list(users)");
    $indexes = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $hasUsernameIndex = false;
    foreach ($indexes as $index) {
        echo "   - {$index['name']}" . ($index['unique'] ? " (UNIQUE)" : "") . "\n";
        
        // Check if this is a username index
        $indexInfo = $db->query("PRAGMA index_info({$index['name']})")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexInfo as $col) {
            if ($col['name'] === 'username') {
                $hasUsernameIndex = true;
            }
        }
    }
    
    // Add index on username for performance if not exists
    if (!$hasUsernameIndex) {
        echo "   + Creating index on username for performance...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
        echo "   ✓ Index created\n";
    } else {
        echo "   ✓ Username index already exists\n";
    }
    
    echo "\n";
    
    // Check registration_attempts table
    echo "3. Checking registration_attempts table...\n";
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='registration_attempts'")->fetchAll();
    
    if (count($tables) > 0) {
        $result = $db->query("PRAGMA table_info(registration_attempts)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "   - {$column['name']} ({$column['type']})\n";
        }
        
        // Add index on ip_address and attempted_at for rate limiting performance
        echo "   + Creating index for rate limiting queries...\n";
        $db->exec("CREATE INDEX IF NOT EXISTS idx_registration_attempts_ip_time ON registration_attempts(ip_address, attempted_at)");
        echo "   ✓ Index created\n";
    } else {
        echo "   ⚠ registration_attempts table will be created on first registration attempt\n";
    }
    
    echo "\n";
    
    // Test queries
    echo "4. Testing critical queries...\n";
    
    // Test unique constraint on username
    echo "   - Testing username UNIQUE constraint...\n";
    try {
        $db->exec("INSERT INTO users (username, full_name, password) VALUES ('test_duplicate', 'Test', 'hash')");
        $db->exec("INSERT INTO users (username, full_name, password) VALUES ('test_duplicate', 'Test2', 'hash2')");
        echo "   ✗ FAILED: Duplicate username was allowed!\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
            echo "   ✓ UNIQUE constraint working correctly\n";
            // Clean up
            $db->exec("DELETE FROM users WHERE username = 'test_duplicate'");
        } else {
            throw $e;
        }
    }
    
    // Test user query performance
    echo "   - Testing user lookup query...\n";
    $stmt = $db->prepare("SELECT id, username, full_name, password FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch();
    if ($user) {
        echo "   ✓ User lookup successful (found: {$user['username']})\n";
    }
    
    echo "\n";
    
    // Count users
    echo "5. Database statistics...\n";
    $userCount = $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    echo "   - Total users: $userCount\n";
    
    if ($tables && count($tables) > 0) {
        $attemptCount = $db->query("SELECT COUNT(*) as count FROM registration_attempts")->fetch()['count'];
        echo "   - Registration attempts logged: $attemptCount\n";
    }
    
    echo "\n=== Schema Validation Complete ✓ ===\n";
    
} catch (PDOException $e) {
    die("Schema validation failed: " . $e->getMessage() . "\n");
}
