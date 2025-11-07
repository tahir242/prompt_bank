<?php
/**
 * Database Initialization Script
 * Creates SQLite database and all required tables
 */

$dbPath = __DIR__ . '/prompts.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create roles table
    $db->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            description TEXT NOT NULL,
            permissions TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create teams table
    $db->exec("
        CREATE TABLE IF NOT EXISTS teams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by INTEGER,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");
    
    // Create users table
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            full_name TEXT NOT NULL,
            password TEXT NOT NULL,
            role_id INTEGER,
            team_id INTEGER,
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (role_id) REFERENCES roles(id),
            FOREIGN KEY (team_id) REFERENCES teams(id)
        )
    ");
    
    // Create audit_log table
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
            user_id INTEGER,
            team_id INTEGER,
            visibility TEXT DEFAULT 'private' CHECK(visibility IN ('private', 'team', 'public')),
            allow_anonymous BOOLEAN DEFAULT 0,
            team_access_level TEXT DEFAULT 'view' CHECK(team_access_level IN ('view', 'edit')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_archived BOOLEAN DEFAULT 0,
            FOREIGN KEY (category_id) REFERENCES categories(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (team_id) REFERENCES teams(id)
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
    
    // Create prompt_shares table for sharing prompts
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
    
    // Create access_requests table for request access workflow
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
    
    // Create prompt_collaborators table for real-time collaboration tracking
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
    
    // Insert default admin user (username: admin, password: admin123)
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Insert default roles first
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
    
    $roleStmt = $db->prepare("INSERT OR IGNORE INTO roles (name, description, permissions) VALUES (?, ?, ?)");
    foreach ($defaultRoles as $role) {
        $roleStmt->execute([$role['name'], $role['description'], $role['permissions']]);
    }
    
    // Get Admin role ID
    $adminRoleStmt = $db->query("SELECT id FROM roles WHERE name = 'Admin'");
    $adminRole = $adminRoleStmt->fetch();
    $adminRoleId = $adminRole ? $adminRole['id'] : null;
    
    // Create admin user with Admin role
    $stmt = $db->prepare("INSERT OR IGNORE INTO users (username, full_name, password, role_id, is_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'System Administrator', $hashedPassword, $adminRoleId, 1]);
    
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
    
    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_role_id ON users(role_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_team_id ON users(team_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at)");
    
    // Sharing and collaboration indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompts_visibility ON prompts(visibility)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompts_allow_anonymous ON prompts(allow_anonymous)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompts_user_id ON prompts(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompts_team_id ON prompts(team_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompt_shares_prompt_id ON prompt_shares(prompt_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompt_shares_user_id ON prompt_shares(shared_with_user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_prompt_shares_team_id ON prompt_shares(shared_with_team_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_access_requests_prompt_id ON access_requests(prompt_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_access_requests_user_id ON access_requests(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_access_requests_status ON access_requests(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_collaborators_prompt_id ON prompt_collaborators(prompt_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_collaborators_last_activity ON prompt_collaborators(last_activity)");
    
    echo "Database initialized successfully!\n";
    echo "Default user created - Username: admin, Password: admin123\n";
    echo "Default roles created: Admin, Editor, Viewer\n";
    echo "Database location: $dbPath\n";
    
} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage() . "\n");
}
