<?php
/**
 * Migration: Add full_name column to users table
 */

$dbPath = __DIR__ . '/prompts.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if full_name column exists
    $result = $db->query("PRAGMA table_info(users)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $hasFullName = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'full_name') {
            $hasFullName = true;
            break;
        }
    }
    
    if (!$hasFullName) {
        echo "Adding full_name column to users table...\n";
        
        // Add the column
        $db->exec("ALTER TABLE users ADD COLUMN full_name TEXT NOT NULL DEFAULT 'User'");
        
        // Update existing admin user
        $db->exec("UPDATE users SET full_name = 'System Administrator' WHERE username = 'admin'");
        
        echo "Migration completed successfully!\n";
    } else {
        echo "Column full_name already exists. No migration needed.\n";
    }
    
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
