<?php
/**
 * Database Initialization Script
 * Creates SQLite database and all required tables
 */

$dbPath = __DIR__ . '/prompts.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            full_name TEXT NOT NULL,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create categories table
    $db->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            is_system BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create prompts table
    $db->exec("
        CREATE TABLE IF NOT EXISTS prompts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            category_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_archived BOOLEAN DEFAULT 0,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        )
    ");
    
    // Create prompt_versions table
    $db->exec("
        CREATE TABLE IF NOT EXISTS prompt_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prompt_id INTEGER NOT NULL,
            version_number INTEGER NOT NULL,
            content TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (prompt_id) REFERENCES prompts(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    // Insert default admin user (username: admin, password: admin123)
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT OR IGNORE INTO users (username, full_name, password) VALUES (?, ?, ?)");
    $stmt->execute(['admin', 'System Administrator', $hashedPassword]);
    
    // Insert default system categories
    $defaultCategories = [
        ['System Setup', 1],
        ['Debugging', 1],
        ['Creative Writing', 1],
        ['Code Review', 1],
        ['Documentation', 1]
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO categories (name, is_system) VALUES (?, ?)");
    foreach ($defaultCategories as $category) {
        $stmt->execute($category);
    }
    
    echo "Database initialized successfully!\n";
    echo "Default user created - Username: admin, Password: admin123\n";
    echo "Database location: $dbPath\n";
    
} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage() . "\n");
}
