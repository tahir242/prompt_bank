# Phase 5 Complete: Protected API Endpoints with Role-Based Access

## Overview
Phase 5 successfully implements role-based access control for all API endpoints. The prompts and categories APIs now enforce proper authorization based on user roles and permissions.

## Implementation Summary

### 1. Prompts API Authorization (`api/prompts.php`)

#### POST - Create Prompt
- **Permission Required**: `create_prompt`
- **Who can create**: Admin, Editor
- **Who cannot**: Viewer
- **Behavior**: 
  - Checks `hasPermission()` before allowing creation
  - Automatically assigns `user_id` (creator) and `team_id` (from user's team)
  - Returns 403 if permission denied

#### PUT - Update Prompt
- **Permission Required**: `edit_team_prompt` (Editors) or Admin role
- **Access Control**: `canAccessPrompt()` function
- **Who can update**:
  - **Admin**: Any prompt
  - **Editor**: Team prompts (same team_id) or own prompts
  - **Viewer**: No prompts
- **Behavior**:
  - First checks `canAccessPrompt()` for team/ownership validation
  - Then checks role permission (`edit_team_prompt`)
  - Returns 403 if either check fails

#### DELETE - Delete Prompt
- **Permission Required**: 
  - `delete_prompt` (Admin - can delete any)
  - `delete_team_prompt` (Editor - can delete team prompts)
- **Who can delete**:
  - **Admin**: Any prompt
  - **Editor**: Only team prompts they have access to
  - **Viewer**: No prompts
- **Behavior**:
  - Admin: Direct deletion without access check
  - Editor: Validates `delete_team_prompt` permission + `canAccessPrompt()`
  - Soft delete (sets `is_archived = 1`)

### 2. Categories API Authorization (`api/categories.php`)

#### GET - List Categories
- **Permission Required**: None (authenticated users only)
- **Who can view**: Admin, Editor, Viewer (all authenticated users)

#### POST - Create Category
- **Permission Required**: `manage_categories`
- **Who can create**: Admin, Editor
- **Who cannot**: Viewer
- **Behavior**: Returns 403 if permission denied

#### PUT - Update Category
- **Permission Required**: `manage_categories`
- **Who can update**: Admin, Editor
- **Who cannot**: Viewer
- **Additional Check**: Cannot update system categories (is_system = 1)
- **Behavior**: Returns 403 if permission denied or system category

#### DELETE - Delete Category
- **Permission Required**: `manage_categories`
- **Who can delete**: Admin, Editor
- **Who cannot**: Viewer
- **Additional Check**: Cannot delete system categories (is_system = 1)
- **Behavior**: Returns 403 if permission denied or system category

## Permission Matrix

| Role    | Create Prompt | Edit Team Prompt | Edit Any Prompt | Delete Team Prompt | Delete Any Prompt | Manage Categories |
|---------|---------------|------------------|-----------------|---------------------|-------------------|-------------------|
| Admin   | ✅            | ✅                | ✅              | ✅                  | ✅                | ✅                |
| Editor  | ✅            | ✅                | ❌              | ✅                  | ❌                | ✅                |
| Viewer  | ❌            | ❌                | ❌              | ❌                  | ❌                | ❌                |

## Team-Based Access Control

### How It Works:
1. **Prompt Creation**: When Editor creates prompt, it's assigned to their team (`team_id`)
2. **Team Members**: All editors in same team can edit/delete team prompts
3. **Individual Prompts**: Prompts without `team_id` can only be edited by creator (unless Admin)
4. **Admin Override**: Admins can access/edit/delete ANY prompt regardless of team

### Access Rules:
```php
function canAccessPrompt($userId, $promptId) {
    // Admin: full access to everything
    // Editor with team: access to team prompts + own prompts
    // Editor without team: access only to own prompts
    // Viewer: read-only (no write access)
}
```

## Test Coverage

### Test File: `tests/test_protected_endpoints.php`
**10 comprehensive tests**:

1. **Test 1**: Admin can create prompts ✓
   - Verifies `create_prompt` permission for Admin

2. **Test 2**: Editor can create prompts ✓
   - Verifies `create_prompt` permission for Editor
   - Confirms team_id assignment

3. **Test 3**: Viewer cannot create prompts ✓
   - Verifies Viewer lacks `create_prompt` permission

4. **Test 4**: Editor can edit team prompts ✓
   - Tests `canAccessPrompt()` for team member
   - Confirms team-based access

5. **Test 5**: Editor cannot edit other team's prompts ✓
   - Verifies team isolation
   - Confirms access denial for different team

6. **Test 6**: Admin can delete any prompt ✓
   - Verifies `delete_prompt` permission

7. **Test 7**: Editor has delete_team_prompt (not delete_prompt) ✓
   - Confirms Editor has limited delete permission
   - Verifies no full delete access

8. **Test 8**: Admin can manage categories ✓
   - Verifies `manage_categories` permission

9. **Test 9**: Editor can manage categories ✓
   - Confirms Editors can manage categories

10. **Test 10**: Viewer cannot manage categories ✓
    - Verifies Viewer lacks `manage_categories` permission

### Test File: `tests/test_protected_quick.php`
**5 quick verification tests**:

1. Admin has create_prompt permission ✓
2. Admin has delete_prompt and manage_categories ✓
3. canAccessPrompt function works ✓
4. Editor role has correct permissions ✓
5. Viewer lacks create_prompt permission ✓

## Security Enhancements

### 1. Permission Checks
```php
// Before any create/update/delete operation
if (!hasPermission($_SESSION['user_id'], 'permission_name')) {
    jsonResponse(['error' => 'Forbidden: ...'], 403);
}
```

### 2. Team-Based Access
```php
// For edit/delete operations
if (!canAccessPrompt($_SESSION['user_id'], $promptId)) {
    jsonResponse(['error' => 'Forbidden: ...'], 403);
}
```

### 3. Role-Based Logic
```php
$userRole = getUserRole($_SESSION['user_id']);
$isAdmin = $userRole['role_name'] === 'Admin';

if ($isAdmin) {
    // Full access
} else {
    // Limited access with team checks
}
```

## API Error Responses

### 403 Forbidden Examples:
```json
{
  "error": "Forbidden: You do not have permission to create prompts"
}
```

```json
{
  "error": "Forbidden: You do not have permission to edit this prompt"
}
```

```json
{
  "error": "Forbidden: You can only delete prompts from your team"
}
```

```json
{
  "error": "Forbidden: You do not have permission to manage categories"
}
```

## Files Modified

### Updated:
- `api/prompts.php` - Added permission checks for POST/PUT/DELETE
  - POST: Added `hasPermission($userId, 'create_prompt')` check
  - PUT: Enhanced with permission check + team validation
  - DELETE: Implemented role-based deletion (Admin vs Editor)
  
- `api/categories.php` - Added permission checks for POST/PUT/DELETE
  - POST: Added `hasPermission($userId, 'manage_categories')` check
  - PUT: Added `hasPermission($userId, 'manage_categories')` check
  - DELETE: Added `hasPermission($userId, 'manage_categories')` check

### Created:
- `tests/test_protected_endpoints.php` - Comprehensive authorization tests (10 tests)
- `tests/test_protected_quick.php` - Quick verification tests (5 tests)

## Authorization Functions Used

From `database/db.php` (Phase 2):
- `hasPermission($userId, $permission)` - Checks if user has specific permission
- `canAccessPrompt($userId, $promptId)` - Validates team-based prompt access
- `getUserRole($userId)` - Fetches user's role and team information
- `requireRole($roleName)` - Enforces specific role requirement (used in users/teams APIs)

## Backward Compatibility

### Existing Prompts Without team_id:
- `canAccessPrompt()` handles NULL team_id gracefully
- Individual prompts (no team) accessible only by creator (unless Admin)
- No breaking changes to existing data

### System Categories:
- System categories (`is_system = 1`) protected from modification
- Only user-created categories can be edited/deleted
- Existing protection logic maintained

## Phase 5 Acceptance Criteria ✅

### Requirements Met:
1. ✅ Apply authorization to prompts API
   - POST: `create_prompt` permission required
   - PUT: Team-based access + `edit_team_prompt` permission
   - DELETE: Role-based (Admin: any, Editor: team only)

2. ✅ Apply authorization to categories API
   - POST/PUT/DELETE: `manage_categories` permission required
   - System category protection maintained

3. ✅ Implement team-based access control for prompts
   - Editors can edit team prompts
   - Editors blocked from other teams' prompts
   - Individual prompts accessible only by creator

4. ✅ Write comprehensive tests
   - 10 tests for protected endpoints
   - 5 quick verification tests
   - All permission scenarios covered

5. ✅ Verify all role permissions work correctly
   - Admin: Full access verified
   - Editor: Limited access verified
   - Viewer: Read-only verified

## Dependencies
- Phase 1: Database schema (user_id, team_id in prompts)
- Phase 2: Authorization functions (hasPermission, canAccessPrompt)
- Phase 3: Team management (teams table, team assignments)
- Phase 4: User management (role assignments, is_active checks)

## Next Phase Preview
**Phase 6: Frontend Role-Aware UI and Dashboard Customization**
- Show/hide UI elements based on permissions
- Display role information in user interface
- Customize dashboard by role (Admin/Editor/Viewer)
- Add visual indicators for team ownership
- Implement permission-based button visibility

## Commit Recommendation
```
feat: Phase 5 - Protected API Endpoints with Role-Based Access

Implements comprehensive authorization for prompts and categories APIs:
- Added permission checks to prompts.php (create/edit/delete)
- Added permission checks to categories.php (create/edit/delete)
- Implemented team-based access control for prompts
- Admin can access/modify all, Editors limited to team prompts
- All 10 authorization tests passing

Security:
- Viewers blocked from creating/editing content
- Editors restricted to team prompts
- System categories protected from modification
- Proper 403 Forbidden responses

Files:
- api/prompts.php (modified)
- api/categories.php (modified)
- tests/test_protected_endpoints.php (new)
- tests/test_protected_quick.php (new)
```

---
**Phase 5 Status**: ✅ **COMPLETE** - Ready for commit and Phase 6

