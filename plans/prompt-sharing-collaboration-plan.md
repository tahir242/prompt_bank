## Plan: Prompt Sharing and Collaboration Features

This plan implements comprehensive sharing and collaboration capabilities for the System Prompt Bank, enabling users to share prompts with specific users, teams, or make them public. The implementation includes access control, collaborative editing notifications, and sharing management UI.

**Phases: 7**

1. **Phase 1: Database Schema for Sharing**
   - **Objective:** Create database tables and columns to support prompt sharing with granular access control and request workflows
   - **Files/Functions to Modify/Create:**
     - `database/migrate_add_sharing.php` (new migration file)
     - `database/init_db.php` (add sharing tables to initialization)
   - **Tests to Write:**
     - Test sharing table creation
     - Test visibility enum constraint
     - Test foreign key relationships
     - Test cascade deletes for shares
     - Test access_requests table creation
   - **Steps:**
     1. Write tests for database schema validation (failing tests)
     2. Create `prompt_shares` table with fields: id, prompt_id, shared_with_user_id, shared_with_team_id, access_level (view/edit), created_by, created_at
     3. Add `visibility` column to `prompts` table (private/team/public)
     4. Add `allow_anonymous` column to `prompts` table (boolean, default false)
     5. Add `team_access_level` column to `prompts` table (view/edit, default view)
     6. Create `access_requests` table with fields: id, prompt_id, user_id, message, status (pending/approved/denied), created_at, resolved_at, resolved_by
     7. Create `prompt_collaborators` table for tracking active collaborators with fields: id, prompt_id, user_id, last_activity, is_editing
     8. Create indexes on foreign keys, visibility column, and status column
     9. Run tests to verify schema changes pass

2. **Phase 2: Backend API for Sharing and Access Requests**
   - **Objective:** Implement REST endpoints for sharing prompts, managing access control, and handling access requests
   - **Files/Functions to Modify/Create:**
     - `api/shares.php` (new file)
     - `api/access_requests.php` (new file)
     - `database/db.php` (add helper functions: `getPromptShares()`, `canAccessPrompt()`, `sharePrompt()`, `revokeShare()`, `requestAccess()`, `getPendingRequests()`, `approveRequest()`, `denyRequest()`)
   - **Tests to Write:**
     - Test GET /api/shares.php?prompt_id=X returns shares list
     - Test POST /api/shares.php creates new share
     - Test DELETE /api/shares.php removes share
     - Test POST /api/access_requests.php creates access request
     - Test GET /api/access_requests.php lists pending requests (owner only)
     - Test PUT /api/access_requests.php approves/denies request
     - Test duplicate request prevention
     - Test access control validation (owner/editor only can share)
     - Test invalid user/team handling
   - **Steps:**
     1. Write tests for sharing and access request API endpoints (failing tests)
     2. Implement `canAccessPrompt($userId, $promptId)` helper to check visibility + shares
     3. Implement GET endpoint to list shares for a prompt (owner/editors only)
     4. Implement POST endpoint to create shares with user_id OR team_id and access_level
     5. Implement DELETE endpoint to revoke shares (owner only)
     6. Implement POST endpoint to request access with optional message
     7. Implement GET endpoint to list pending requests for user's owned prompts
     8. Implement PUT endpoint to approve/deny access requests (owner only)
     9. Add notification on request approval (create share automatically)
     10. Run tests to verify all endpoints pass

3. **Phase 3: Update Prompts API for Visibility Control**
   - **Objective:** Modify prompt listing and CRUD operations to respect visibility, sharing rules, and anonymous access
   - **Files/Functions to Modify/Create:**
     - `api/prompts.php` (modify GET, POST, PUT, DELETE methods)
     - `database/db.php` (add `getAccessiblePrompts()` function)
     - `api/public_prompts.php` (new file for anonymous access)
   - **Tests to Write:**
     - Test private prompts only visible to owner and shares
     - Test team prompts visible to team members with configured access_level
     - Test public prompts visible to all authenticated users
     - Test anonymous access only works when allow_anonymous is true
     - Test edit permissions enforced based on access_level
     - Test prompt creation sets default visibility (private) and team_access_level (view)
     - Test visibility and team_access_level update permissions
   - **Steps:**
     1. Write tests for visibility-based filtering (failing tests)
     2. Modify GET /prompts endpoint to filter by accessibility using joins on prompt_shares
     3. Add team_access_level check for team visibility prompts
     4. Add `visibility`, `allow_anonymous`, and `team_access_level` fields to POST /prompts endpoint
     5. Add visibility check to PUT /prompts endpoint (respect access_level for edits)
     6. Add access validation to DELETE /prompts endpoint (owner only)
     7. Update prompt detail GET endpoint to include shares, visibility, and access info
     8. Create public_prompts.php for anonymous users (only allow_anonymous=true prompts)
     9. Run tests to verify all access control passes

4. **Phase 4: Collaborative Editing Tracking**
   - **Objective:** Implement real-time tracking of who is actively viewing/editing prompts
   - **Files/Functions to Modify/Create:**
     - `api/collaborators.php` (new file)
     - `database/db.php` (add `updateCollaboratorActivity()`, `getActiveCollaborators()`)
   - **Tests to Write:**
     - Test POST /collaborators.php updates activity timestamp
     - Test GET /collaborators.php returns active users (last 5 minutes)
     - Test automatic cleanup of stale collaborator records
     - Test is_editing flag toggle functionality
   - **Steps:**
     1. Write tests for collaborator tracking (failing tests)
     2. Implement POST endpoint to update last_activity and is_editing status
     3. Implement GET endpoint to fetch active collaborators for a prompt (activity within 5 minutes)
     4. Add automatic cleanup query to remove stale records (>10 minutes old)
     5. Add permission check: only users with access can join as collaborators
     6. Run tests to verify activity tracking passes

5. **Phase 5: Frontend Sharing UI Components**
   - **Objective:** Create user interface for managing prompt sharing, visibility settings, and access requests
   - **Files/Functions to Modify/Create:**
     - `assets/js/app.js` (add functions: `openShareModal()`, `handleSharePrompt()`, `handleRevokeShare()`, `updateVisibility()`, `handleRequestAccess()`, `openAccessRequestsModal()`, `handleApproveRequest()`, `handleDenyRequest()`)
     - `index.php` (add share modal, access request modal, and request access button HTML templates)
     - `assets/css/styles.css` (add share UI styling)
   - **Tests to Write:**
     - Test share modal opens with current shares list
     - Test user/team dropdown population
     - Test share creation with access level selection and UI update
     - Test share revocation and removal from list
     - Test visibility toggle (private/team/public) with team_access_level selector
     - Test allow_anonymous checkbox shows security warning
     - Test request access button appears for inaccessible prompts
     - Test access request submission with message
     - Test access requests modal shows pending requests
     - Test approve/deny request functionality
   - **Steps:**
     1. Write tests for share modal interactions (failing tests using DOM testing)
     2. Add "Share" button to prompt detail modal (owner/editors only)
     3. Add "Request Access" button for prompts user cannot access
     4. Create share modal HTML with user/team selector, access level dropdown, current shares list
     5. Add visibility selector with private/team/public radio buttons
     6. Add team_access_level dropdown (view/edit) for team visibility
     7. Add allow_anonymous checkbox with security warning tooltip
     8. Implement `loadSharesForPrompt(promptId)` to fetch and display current shares
     9. Implement `handleSharePrompt()` to POST new share, wait for confirmation, then refresh list
     10. Implement `handleRevokeShare(shareId)` to DELETE share with confirmation
     11. Implement `handleRequestAccess()` to POST access request with message
     12. Create access requests modal to list pending requests for owned prompts
     13. Implement approve/deny handlers that update request status
     14. Update prompt list UI to show visibility badges (lock/team/globe/globe-unlocked icons)
     15. Run tests to verify all UI interactions pass

6. **Phase 6: Collaborative Editing Indicators**
   - **Objective:** Display real-time indicators showing who is actively viewing or editing prompts
   - **Files/Functions to Modify/Create:**
     - `assets/js/app.js` (add functions: `startCollaboratorPing()`, `displayActiveCollaborators()`, `stopCollaboratorPing()`)
     - `index.php` (add collaborators section to detail modal)
     - `assets/css/styles.css` (add avatar/badge styling)
   - **Tests to Write:**
     - Test collaborator ping starts on prompt detail open
     - Test ping stops on modal close
     - Test active collaborators display with avatars
     - Test editing indicator shows correctly
     - Test collaborator list updates every 10 seconds
   - **Steps:**
     1. Write tests for collaborator indicator functionality (failing tests)
     2. Add collaborators section to prompt detail modal (avatar list area)
     3. Implement `startCollaboratorPing()` to POST activity every 30 seconds
     4. Implement `fetchActiveCollaborators()` to GET and display active users every 10 seconds
     5. Display user avatars/initials with "viewing" or "editing" badges
     6. Add is_editing flag toggle when user clicks edit button
     7. Implement `stopCollaboratorPing()` cleanup on modal close
     8. Run tests to verify real-time indicators pass

7. **Phase 7: Access Request Notifications and Badge Indicators**
   - **Objective:** Add visual indicators for pending access requests and notification system
   - **Files/Functions to Modify/Create:**
     - `assets/js/app.js` (add functions: `fetchPendingRequestsCount()`, `updateAccessRequestBadge()`, `pollAccessRequests()`)
     - `index.php` (add notification badge to user menu)
     - `database/db.php` (add `getPendingRequestsForUser()` function)
   - **Tests to Write:**
     - Test pending requests count API endpoint
     - Test badge appears when requests > 0
     - Test badge count updates on approval/denial
     - Test polling updates badge every 60 seconds
     - Test clicking badge opens access requests modal
   - **Steps:**
     1. Write tests for notification badge functionality (failing tests)
     2. Add GET endpoint to return count of pending requests for user's prompts
     3. Add notification badge element to user menu/header
     4. Implement `fetchPendingRequestsCount()` to get count
     5. Implement `updateAccessRequestBadge(count)` to show/hide and update badge
     6. Implement polling every 60 seconds to refresh badge count
     7. Add click handler to badge to open access requests modal
     8. Add badge indicator to prompts list showing "X requests" for owned prompts
     9. Run tests to verify badge system passes

**Decisions Made:**
1. ✅ Public prompts visible to authenticated users by default, with optional anonymous sharing toggle (with security warning)
2. ✅ Implement "request access" workflow for private prompts
3. ✅ Team visibility permissions configurable per prompt (view/edit selector)
4. ✅ Wait for server confirmation before updating UI (no optimistic updates)
5. 🔄 Notifications deferred to future phase
