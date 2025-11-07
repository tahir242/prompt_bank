<?php
/**
 * Migration: Add team and user ownership to prompts table
 * Adds team_id and user_id columns for team-based access control
 */

$dbPath = __DIR__ . '/prompts.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Prompt Ownership Migration ===\n\n";
    
    // Check if columns already exist
    $columns = $db->query("PRAGMA table_info(prompts)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    echo "1. Adding ownership columns to prompts table...\n";
    
    if (!in_array('user_id', $columnNames)) {
        $db->exec("ALTER TABLE prompts ADD COLUMN user_id INTEGER REFERENCES users(id)");
        echo "   ✓ Added user_id column (creator)\n";
    } else {
        echo "   - user_id column already exists\n";
    }
    
    if (!in_array('team_id', $columnNames)) {
        $db->exec("ALTER TABLE prompts ADD COLUMN team_id INTEGER REFERENCES teams(id)");
        echo "   ✓ Added team_id column\n";
    } else {
        echo "   - team_id column already exists\n";
    }
    echo "\n";
    
    // Create indexes for performance
    echo "2. Creating indexes for performance...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompts_user_id ON prompts(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompts_team_id ON prompts(team_id)");
    echo "   ✓ Indexes created\n\n";
    
    // Update existing prompts to have admin as creator
    echo "3. Updating existing prompts with default ownership...\n";
    $adminStmt = $db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    $admin = $adminStmt->fetch();
    
    if ($admin) {
        $updateStmt = $db->prepare("UPDATE prompts SET user_id = ? WHERE user_id IS NULL");
        $updateStmt->execute([$admin['id']]);
        $affectedRows = $updateStmt->rowCount();
        if ($affectedRows > 0) {
            echo "   ✓ Assigned $affectedRows existing prompts to admin user\n";
        } else {
            echo "   - No prompts needed updating\n";
        }
    }
    echo "\n";
    
    // Log migration
    echo "4. Logging migration to audit log...\n";
    $logStmt = $db->prepare("INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $logStmt->execute([
        1,
        'system_migration',
        'Prompt ownership migration completed: added user_id and team_id columns',
        'system'
    ]);
    echo "   ✓ Migration logged\n\n";
    
    echo "=== Migration Complete ✓ ===\n";
    echo "Prompts now support:\n";
    echo "  - User ownership (creator tracking)\n";
    echo "  - Team ownership (team-based access control)\n\n";
    echo "Database location: $dbPath\n";
    
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
