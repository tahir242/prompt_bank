<?php
/**
 * Migration: Add Sharing and Collaboration Features
 * Creates tables for prompt sharing, access requests, and collaboration tracking
 * Adds visibility, anonymous access, and team permission columns to prompts
 */

$dbPath = __DIR__ . '/prompts.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Enable foreign key constraints
    $db->exec("PRAGMA foreign_keys = ON");
    
    echo "=== Sharing and Collaboration Migration ===\n\n";
    
    // Step 1: Add new columns to prompts table
    echo "1. Adding visibility and sharing columns to prompts table...\n";
    
    $columns = $db->query("PRAGMA table_info(prompts)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    if (!in_array('visibility', $columnNames)) {
        $db->exec("ALTER TABLE prompts ADD COLUMN visibility TEXT DEFAULT 'private' CHECK(visibility IN ('private', 'team', 'public'))");
        echo "   ✓ Added visibility column (private/team/public)\n";
    } else {
        echo "   - visibility column already exists\n";
    }
    
    if (!in_array('allow_anonymous', $columnNames)) {
        $db->exec("ALTER TABLE prompts ADD COLUMN allow_anonymous BOOLEAN DEFAULT 0");
        echo "   ✓ Added allow_anonymous column\n";
    } else {
        echo "   - allow_anonymous column already exists\n";
    }
    
    if (!in_array('team_access_level', $columnNames)) {
        $db->exec("ALTER TABLE prompts ADD COLUMN team_access_level TEXT DEFAULT 'view' CHECK(team_access_level IN ('view', 'edit'))");
        echo "   ✓ Added team_access_level column (view/edit)\n";
    } else {
        echo "   - team_access_level column already exists\n";
    }
    echo "\n";
    
    // Step 2: Create prompt_shares table
    echo "2. Creating prompt_shares table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS prompt_shares (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prompt_id INTEGER NOT NULL,
            shared_with_user_id INTEGER,
            shared_with_team_id INTEGER,
            access_level TEXT NOT NULL DEFAULT 'view' CHECK(access_level IN ('view', 'edit')),
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE CASCADE,
            FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (shared_with_team_id) REFERENCES teams(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id),
            CHECK ((shared_with_user_id IS NOT NULL AND shared_with_team_id IS NULL) OR 
                   (shared_with_user_id IS NULL AND shared_with_team_id IS NOT NULL)),
            UNIQUE(prompt_id, shared_with_user_id),
            UNIQUE(prompt_id, shared_with_team_id)
        )
    ");
    echo "   ✓ Created prompt_shares table with access control\n\n";
    
    // Step 3: Create access_requests table
    echo "3. Creating access_requests table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS access_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prompt_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            message TEXT,
            status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'denied')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME,
            resolved_by INTEGER,
            FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (resolved_by) REFERENCES users(id),
            UNIQUE(prompt_id, user_id, status)
        )
    ");
    echo "   ✓ Created access_requests table for request workflow\n\n";
    
    // Step 4: Create prompt_collaborators table
    echo "4. Creating prompt_collaborators table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS prompt_collaborators (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prompt_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_editing BOOLEAN DEFAULT 0,
            FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(prompt_id, user_id)
        )
    ");
    echo "   ✓ Created prompt_collaborators table for real-time tracking\n\n";
    
    // Step 5: Create indexes for performance
    echo "5. Creating indexes for performance...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompts_visibility ON prompts(visibility)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompts_allow_anonymous ON prompts(allow_anonymous)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompt_shares_prompt_id ON prompt_shares(prompt_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompt_shares_user_id ON prompt_shares(shared_with_user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompt_shares_team_id ON prompt_shares(shared_with_team_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_access_requests_prompt_id ON access_requests(prompt_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_access_requests_user_id ON access_requests(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_access_requests_status ON access_requests(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_collaborators_prompt_id ON prompt_collaborators(prompt_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_collaborators_last_activity ON prompt_collaborators(last_activity)");
    echo "   ✓ All indexes created successfully\n\n";
    
    // Step 6: Update existing prompts with default values
    echo "6. Updating existing prompts with default visibility...\n";
    $updateStmt = $db->prepare("UPDATE prompts SET visibility = 'private', allow_anonymous = 0, team_access_level = 'view' WHERE visibility IS NULL");
    $updateStmt->execute();
    $affectedRows = $updateStmt->rowCount();
    if ($affectedRows > 0) {
        echo "   ✓ Updated $affectedRows existing prompts with default visibility settings\n";
    } else {
        echo "   - No prompts needed updating\n";
    }
    echo "\n";
    
    // Step 7: Log migration
    echo "7. Logging migration to audit log...\n";
    $adminStmt = $db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    $admin = $adminStmt->fetch();
    $userId = $admin ? $admin['id'] : 1;
    
    $logStmt = $db->prepare("INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $logStmt->execute([
        $userId,
        'system_migration',
        'Sharing and collaboration migration completed: added prompt_shares, access_requests, prompt_collaborators tables and visibility columns',
        'system'
    ]);
    echo "   ✓ Migration logged to audit log\n\n";
    
    echo "=== Migration Complete ✓ ===\n";
    echo "New features enabled:\n";
    echo "  ✓ Prompt visibility control (private/team/public)\n";
    echo "  ✓ Anonymous access toggle with security controls\n";
    echo "  ✓ Team access level configuration (view/edit)\n";
    echo "  ✓ Prompt sharing with users and teams\n";
    echo "  ✓ Access request workflow\n";
    echo "  ✓ Real-time collaboration tracking\n\n";
    echo "Database location: $dbPath\n";
    
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
