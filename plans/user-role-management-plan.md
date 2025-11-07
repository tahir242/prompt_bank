## Plan: User Role Management System

Implement a comprehensive role-based access control (RBAC) system with team support, allowing Admins to manage users and roles, Editors to manage their team's prompts, and Viewers to have read-only access. Includes user activation/deactivation, audit logging, and role-specific dashboard customization.

**Phases: 7**

### 1. **Phase 1: Database Schema Migration for Roles and Teams**
   - **Objective:** Add comprehensive role support with teams, user activation status, and audit logging to the database schema
   - **Files/Functions to Modify/Create:**
     - `database/migrate_add_roles.php` (create new migration file)
     - `database/init_db.php` (update to include new tables)
     - `database/validate_schema.php` (update to validate new schema)
   - **Tests to Write:**
     - `test_roles_table_exists`
     - `test_teams_table_exists`
     - `test_users_role_id_column_exists`
     - `test_users_team_id_column_exists`
     - `test_users_is_active_column_exists`
     - `test_audit_log_table_exists`
     - `test_default_admin_role_assigned`
     - `test_foreign_key_constraints`
   - **Steps:**
     1. Write tests to verify new tables don't exist yet (should pass initially)
     2. Create migration script to add `roles` table (id, name, description, permissions JSON)
     3. Add `teams` table (id, name, created_at, created_by)
     4. Add columns to `users` table: `role_id`, `team_id`, `is_active` (default 1)
     5. Create `audit_log` table (id, user_id, action, details, ip_address, created_at)
     6. Insert default roles: Admin (all permissions), Editor (manage team prompts/categories), Viewer (read-only)
     7. Update existing admin user to have Admin role and is_active=1
     8. Run tests to verify all schema changes applied correctly

### 2. **Phase 2: Backend Authorization Helper Functions**
   - **Objective:** Create reusable authorization functions to check user permissions, team membership, and log audit events
   - **Files/Functions to Modify/Create:**
     - `database/db.php` (add `requireRole()`, `hasPermission()`, `getUserRole()`, `canAccessPrompt()`, `logAudit()`)
     - `tests/test_authorization.php` (create comprehensive authorization tests)
   - **Tests to Write:**
     - `test_requireRole_allows_admin`
     - `test_requireRole_blocks_viewer_from_admin_action`
     - `test_hasPermission_checks_correctly`
     - `test_getUserRole_returns_role_and_team_data`
     - `test_canAccessPrompt_allows_team_member`
     - `test_canAccessPrompt_blocks_different_team`
     - `test_logAudit_creates_entry`
     - `test_inactive_user_blocked`
   - **Steps:**
     1. Write tests for authorization functions (should fail)
     2. Implement `getUserRole($userId)` to fetch user's role, team, and active status
     3. Implement `hasPermission($userId, $permission)` to check specific permission from role JSON
     4. Implement `requireRole($allowedRoles)` to enforce role-based access and check is_active
     5. Implement `canAccessPrompt($userId, $promptId)` for team-based prompt access
     6. Implement `logAudit($userId, $action, $details)` to record security events
     7. Run tests to ensure all authorization logic works correctly

### 3. **Phase 3: Team Management API and Prompt Ownership**
   - **Objective:** Create team management endpoints and update prompt creation to include team ownership
   - **Files/Functions to Modify/Create:**
     - `api/teams.php` (create new endpoint for team CRUD - Admin only)
     - `api/prompts.php` (modify to include team_id and enforce team-based access)
     - `database/migrate_add_prompt_team.php` (add team_id and user_id to prompts table)
   - **Tests to Write:**
     - `test_admin_can_create_team`
     - `test_admin_can_list_teams`
     - `test_editor_cannot_create_team`
     - `test_prompt_created_with_team_id`
     - `test_prompt_created_with_user_id`
     - `test_editor_can_edit_team_prompt`
     - `test_editor_cannot_edit_other_team_prompt`
   - **Steps:**
     1. Write tests for team management and team-based prompt access (should fail)
     2. Create migration to add `team_id` and `user_id` (creator) columns to prompts table
     3. Create `api/teams.php`: GET (all roles), POST/PUT/DELETE (Admin only)
     4. Update `api/prompts.php` POST to capture creator user_id and team_id
     5. Update `api/prompts.php` PUT/DELETE to enforce team-based access for Editors
     6. Ensure Admins can access all prompts, Editors only their team's, Viewers read-only
     7. Run tests to verify team management and ownership enforcement

### 4. **Phase 4: User Management API with Role Assignment**
   - **Objective:** Build complete user management API for Admins to manage users, assign roles, teams, and activation status
   - **Files/Functions to Modify/Create:**
     - `api/users.php` (create comprehensive user management endpoint)
     - `api/login.php` (update to check is_active status and return role/team info)
     - `api/register.php` (update to assign default Viewer role and log registration)
   - **Tests to Write:**
     - `test_admin_can_list_all_users`
     - `test_admin_can_update_user_role`
     - `test_admin_can_assign_user_to_team`
     - `test_admin_can_deactivate_user`
     - `test_inactive_user_cannot_login`
     - `test_role_change_logged_to_audit`
     - `test_non_admin_blocked_from_user_api`
     - `test_new_registration_gets_viewer_role`
   - **Steps:**
     1. Write tests for user management API (should fail)
     2. Create `api/users.php` with GET (list users), PUT (update role/team/status) - Admin only
     3. Update `api/login.php` to check is_active=1 and return role, team info
     4. Update `api/register.php` to assign default Viewer role and log to audit
     5. Implement audit logging for all role changes, team assignments, status changes
     6. Add proper error messages when inactive user attempts login
     7. Run tests to ensure user management works with proper authorization

### 5. **Phase 5: Protected API Endpoints with Role-Based Access**
   - **Objective:** Apply comprehensive role and team-based authorization to all API endpoints
   - **Files/Functions to Modify/Create:**
     - `api/prompts.php` (finalize all role and team checks)
     - `api/categories.php` (add role checks for POST, DELETE)
     - `api/audit.php` (create endpoint for viewing audit logs - Admin only)
   - **Tests to Write:**
     - `test_viewer_cannot_create_prompt`
     - `test_editor_can_create_prompt_for_team`
     - `test_editor_cannot_delete_other_team_prompt`
     - `test_admin_can_delete_any_prompt`
     - `test_admin_can_create_category`
     - `test_editor_cannot_delete_category`
     - `test_viewer_can_read_prompts`
     - `test_admin_can_view_audit_logs`
   - **Steps:**
     1. Write tests for complete endpoint access control (should fail)
     2. Finalize `api/prompts.php`: GET (all active users), POST (Editor/Admin with team), PUT (team member/Admin), DELETE (Admin only)
     3. Update `api/categories.php`: GET (all active users), POST/DELETE (Admin only)
     4. Create `api/audit.php`: GET (Admin only) to retrieve audit log entries
     5. Ensure all operations log appropriate audit events
     6. Run tests to verify role and team enforcement on all endpoints

### 6. **Phase 6: Frontend Role-Aware UI and Dashboard Customization**
   - **Objective:** Update SPA interface to show/hide features based on user role and customize dashboard per role
   - **Files/Functions to Modify/Create:**
     - `assets/js/app.js` (add role management logic, team awareness, dashboard customization)
     - `index.php` (add conditional UI elements for different roles)
   - **Tests to Write:**
     - `test_viewer_ui_hides_create_button`
     - `test_editor_ui_shows_prompt_management`
     - `test_editor_ui_shows_only_team_prompts`
     - `test_admin_ui_shows_all_features`
     - `test_dashboard_stats_customized_by_role`
     - `test_canUserPerform_logic`
   - **Steps:**
     1. Write tests for UI permission and customization logic (manual verification)
     2. Update `handleLogin()` to store role, team, permissions in `currentUser` object
     3. Add `canUserPerform(action)` helper to check permissions from role data
     4. Conditionally show/hide UI elements: Add Prompt, Edit/Delete buttons, Category management, User/Team management
     5. Implement role-specific dashboard: Admin (all stats), Editor (team stats), Viewer (personal stats)
     6. Add team filter for Editors to view team prompts
     7. Run manual UI tests to verify correct feature visibility and dashboard customization per role

### 7. **Phase 7: User and Team Management UI**
   - **Objective:** Build complete admin interface for managing users, teams, and viewing audit logs
   - **Files/Functions to Modify/Create:**
     - `assets/js/app.js` (add `loadUsers()`, `loadTeams()`, `updateUserRole()`, `toggleUserStatus()`, `viewAuditLog()`)
     - `index.php` (add user management, team management, and audit log sections)
   - **Tests to Write:**
     - `test_admin_sees_user_management_tab`
     - `test_admin_can_change_user_role_via_ui`
     - `test_admin_can_create_team`
     - `test_admin_can_assign_user_to_team`
     - `test_admin_can_deactivate_user`
     - `test_admin_can_view_audit_logs`
     - `test_role_changes_appear_in_audit_log`
   - **Steps:**
     1. Write tests for user/team management UI (manual verification)
     2. Create User Management UI: table with username, role, team, status, actions (edit role, toggle status)
     3. Create Team Management UI: list teams, create team, assign users to teams
     4. Create Audit Log Viewer: searchable table showing all security events
     5. Implement role assignment dropdown with confirmation
     6. Implement user deactivation toggle with confirmation
     7. Add real-time audit log updates when changes occur
     8. Test complete admin workflow: create team, assign user to team, change role, view audit
     9. Run all tests to ensure complete user/team management functionality
