<?php
/**
 * Migration: Add Role-Based Access Control (RBAC) System
 * Adds roles, teams, user activation, and audit logging
 */

$dbPath = __DIR__ . '/prompts.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Role-Based Access Control Migration ===\n\n";
    
    // Step 1: Create roles table
    echo "1. Creating roles table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            description TEXT NOT NULL,
            permissions TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "   ✓ Roles table created\n\n";
    
    // Step 2: Create teams table
    echo "2. Creating teams table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS teams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");
    echo "   ✓ Teams table created\n\n";
    
    // Step 3: Create audit_log table
    echo "3. Creating audit_log table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            ip_address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    echo "   ✓ Audit log table created\n\n";
    
    // Step 4: Add new columns to users table
    echo "4. Adding new columns to users table...\n";
    
    // Check if columns already exist
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');
    
    if (!in_array('role_id', $columnNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN role_id INTEGER REFERENCES roles(id)");
        echo "   ✓ Added role_id column\n";
    } else {
        echo "   - role_id column already exists\n";
    }
    
    if (!in_array('team_id', $columnNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN team_id INTEGER REFERENCES teams(id)");
        echo "   ✓ Added team_id column\n";
    } else {
        echo "   - team_id column already exists\n";
    }
    
    if (!in_array('is_active', $columnNames)) {
        $db->exec("ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT 1");
        echo "   ✓ Added is_active column (default: 1)\n";
    } else {
        echo "   - is_active column already exists\n";
    }
    echo "\n";
    
    // Step 5: Insert default roles
    echo "5. Creating default roles...\n";
    
    $defaultRoles = [
        [
            'name' => 'Admin',
            'description' => 'Full system access - manage users, roles, teams, prompts, and categories',
            'permissions' => json_encode([
                'manage_users' => true,
                'manage_roles' => true,
                'manage_teams' => true,
                'manage_prompts' => true,
                'manage_categories' => true,
                'view_audit_logs' => true,
                'create_prompt' => true,
                'edit_any_prompt' => true,
                'delete_any_prompt' => true,
                'view_prompts' => true
            ])
        ],
        [
            'name' => 'Editor',
            'description' => 'Manage team prompts and categories - can create, edit, and delete team content',
            'permissions' => json_encode([
                'manage_users' => false,
                'manage_roles' => false,
                'manage_teams' => false,
                'manage_prompts' => true,
                'manage_categories' => false,
                'view_audit_logs' => false,
                'create_prompt' => true,
                'edit_team_prompt' => true,
                'delete_team_prompt' => true,
                'view_prompts' => true
            ])
        ],
        [
            'name' => 'Viewer',
            'description' => 'Read-only access - can view prompts and their history',
            'permissions' => json_encode([
                'manage_users' => false,
                'manage_roles' => false,
                'manage_teams' => false,
                'manage_prompts' => false,
                'manage_categories' => false,
                'view_audit_logs' => false,
                'create_prompt' => false,
                'edit_prompt' => false,
                'delete_prompt' => false,
                'view_prompts' => true
            ])
        ]
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO roles (name, description, permissions) VALUES (?, ?, ?)");
    foreach ($defaultRoles as $role) {
        $stmt->execute([$role['name'], $role['description'], $role['permissions']]);
        echo "   ✓ Created role: {$role['name']}\n";
    }
    echo "\n";
    
    // Step 6: Assign Admin role to existing admin user
    echo "6. Assigning Admin role to existing users...\n";
    
    // Get Admin role ID
    $adminRoleStmt = $db->query("SELECT id FROM roles WHERE name = 'Admin'");
    $adminRole = $adminRoleStmt->fetch();
    
    if ($adminRole) {
        // Update admin user
        $updateStmt = $db->prepare("UPDATE users SET role_id = ?, is_active = 1 WHERE username = 'admin'");
        $updateStmt->execute([$adminRole['id']]);
        echo "   ✓ Assigned Admin role to 'admin' user\n";
        
        // Update any other existing users to Viewer role if they don't have a role
        $viewerRoleStmt = $db->query("SELECT id FROM roles WHERE name = 'Viewer'");
        $viewerRole = $viewerRoleStmt->fetch();
        
        if ($viewerRole) {
            $updateOthersStmt = $db->prepare("UPDATE users SET role_id = ?, is_active = 1 WHERE role_id IS NULL");
            $updateOthersStmt->execute([$viewerRole['id']]);
            $affectedRows = $updateOthersStmt->rowCount();
            if ($affectedRows > 0) {
                echo "   ✓ Assigned Viewer role to $affectedRows existing user(s)\n";
            }
        }
    }
    echo "\n";
    
    // Step 7: Create index for performance
    echo "7. Creating indexes for performance...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_role_id ON users(role_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_team_id ON users(team_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at)");
    echo "   ✓ Indexes created\n\n";
    
    // Step 8: Log migration
    echo "8. Logging migration to audit log...\n";
    $logStmt = $db->prepare("INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $logStmt->execute([
        1, // admin user ID
        'system_migration',
        'RBAC system migration completed: added roles, teams, user activation, and audit logging',
        'system'
    ]);
    echo "   ✓ Migration logged\n\n";
    
    echo "=== Migration Complete ✓ ===\n";
    echo "Default roles created:\n";
    echo "  - Admin: Full system access\n";
    echo "  - Editor: Manage team prompts and categories\n";
    echo "  - Viewer: Read-only access\n\n";
    echo "Database location: $dbPath\n";
    
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
