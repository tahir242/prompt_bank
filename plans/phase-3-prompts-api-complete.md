# Phase 3 Complete: Update Prompts API for Visibility Control

**Completed:** [Current Date]
**Status:** ✅ All tests passing (21/21)

## Objectives Achieved

1. ✅ Updated GET single prompt endpoint with access control
2. ✅ Updated GET list prompts endpoint with visibility filtering  
3. ✅ Updated POST create prompt endpoint with visibility settings
4. ✅ Updated PUT update prompt endpoint with visibility changes (owner-only)
5. ✅ Updated DELETE prompt endpoint with owner-only restriction
6. ✅ Created public prompts API for anonymous access
7. ✅ Created comprehensive test suite with 21 passing tests

## Files Modified

### api/prompts.php
Enhanced all CRUD operations to support the new visibility and sharing system:

**GET Single Prompt (lines 8-69)**
- Uses `canAccessPrompt()` to check user access
- Returns `user_access_level` and `user_access_reason` fields
- Includes shares array for owners and editors
- Returns 403 if user lacks access

**GET List Prompts (lines 71-140)**
- Complex SQL query with OR conditions for visibility filtering:
  - Owner sees all their prompts
  - Admins see all prompts
  - Users see public prompts
  - Users see team prompts from their team
  - Users see directly shared prompts
  - Users see team-shared prompts
- Supports `?visibility=private|team|public` filter parameter
- Returns `access_reason` for each prompt

**POST Create Prompt (lines 142-178)**
- Accepts `visibility` (private|team|public), default 'private'
- Accepts `allow_anonymous` (boolean), default false
- Accepts `team_access_level` (view|edit), default 'view'
- Validates enum constraints
- Audit logging includes visibility

**PUT Update Prompt (lines 190-306)**
- Uses `canAccessPrompt()` for access checking
- Requires `access_level === 'edit'` to modify content
- Only owner (`reason === 'owner'`) can change visibility settings
- Dynamically builds UPDATE query to include optional visibility fields
- Creates version history on every update
- Audit logging

**DELETE Prompt (lines 308-344)**
- Uses `canAccessPrompt()` for access checking
- Only owner (`reason === 'owner'`) can delete
- Soft delete (sets `is_archived = 1`)
- Audit logging with prompt title

### api/public_prompts.php (NEW FILE)
Created anonymous access endpoint with security considerations:

**Features:**
- No authentication required
- Only serves prompts with `visibility='public' AND allow_anonymous=1`
- Supports GET single prompt and list prompts
- Filters by category (`?category_id=X`)
- Full-text search (`?search=keyword`)
- Pagination (`?limit=50&offset=0`, max 100 per page)
- Returns limited fields (id, title, content, category, timestamps, author)
- CORS headers for public API use

**Security Notes:**
- Documented vulnerability to scraping
- Recommended rate limiting for production
- No sensitive data (emails, internal IDs) exposed
- Explicit security warnings in comments

### database/test_prompts_api.php (NEW FILE)
Comprehensive test suite covering all Phase 3 functionality:

**Test Coverage (21 tests):**

*Access Control Tests (5):*
1. Owner has edit access to private prompt
2. Outsider denied private prompt
3. Teammate has edit access to team prompt (team_access_level='edit')
4. Teammate has view-only access to team prompt (team_access_level='view')
5. Outsider has access to public prompt

*Direct Sharing Tests (4):*
6. Share private prompt with user (view access)
7. Shared user has view access
8. Share prompt with edit access
9. Shared user has edit access

*Visibility Query Tests (3):*
10. Owner sees all 5 prompts
11. Teammate sees 4 prompts (excludes private)
12. Outsider sees 4 prompts (2 public + 2 shared)

*Anonymous Access Tests (1):*
13. Anonymous sees only 1 prompt (public with allow_anonymous)

*Access Request Workflow Tests (4):*
14. Request access to team prompt
15. Owner sees 1 pending request
16. Approve access request
17. Approved user gains access

*Share Management Tests (4):*
18. Get shares returns correct data
19. Team prompt has share from approved request
20. Revoke share
21. Share count decreases after revoke

**Test Data:**
- 3 test users (IDs: 9101-9103)
- 1 test team (ID: 9201)
- 5 test prompts with different visibility levels
- Full cleanup after tests

## Database Schema (No Changes)
Phase 3 uses existing schema from Phase 1:
- `prompts.visibility` (private|team|public)
- `prompts.allow_anonymous` (boolean)
- `prompts.team_access_level` (view|edit)
- `prompt_shares` table
- `access_requests` table

## API Behavior Changes

### Breaking Changes
- **GET /api/prompts.php**: Now filters by visibility, users may see fewer prompts
- **PUT /api/prompts.php**: Non-owners cannot change visibility settings
- **DELETE /api/prompts.php**: Only owners can delete (not admins or editors)

### New Responses
- **GET single prompt**: Added `user_access_level`, `user_access_reason`, optional `shares` array
- **GET list prompts**: Added `access_reason` per prompt
- **All prompts**: More granular 403 errors ("view access only", "owner required", etc.)

### New Endpoint
- **GET /api/public_prompts.php**: Anonymous access to public prompts
  - `?id=X` - Get single prompt
  - `?category_id=X` - Filter by category
  - `?search=keyword` - Full-text search
  - `?limit=50&offset=0` - Pagination

## Access Control Matrix

| User Type | Private | Team (view) | Team (edit) | Public | Public (anon) |
|-----------|---------|-------------|-------------|--------|---------------|
| Owner | Edit | Edit | Edit | Edit | Edit |
| Admin | Edit | Edit | Edit | Edit | Edit |
| Same Team | None | View | Edit | View | View |
| Direct Share (view) | View | View | View | View | View |
| Direct Share (edit) | Edit | Edit | Edit | Edit | Edit |
| Other User | None | None | None | View | View |
| Anonymous | None | None | None | None | View |

## Security Improvements

1. **Granular Access Control**: Users only see prompts they have access to
2. **Owner-Only Visibility Changes**: Prevents permission escalation
3. **Owner-Only Deletion**: Protects against accidental/malicious deletions
4. **Anonymous Access Control**: Explicit opt-in with `allow_anonymous` flag
5. **Audit Logging**: All changes logged with user ID and details

## Testing Results

```
Starting Phase 3 Prompts API Tests...

=== Setup ===
Test setup complete.

=== Creating Test Prompts ===
Created 5 test prompts.

=== Testing Access Control ===
✓ Owner has edit access to private prompt
✓ Outsider denied private prompt
✓ Teammate has edit access to team prompt
✓ Teammate has view-only access to team prompt
✓ Outsider has access to public prompt

=== Testing Direct Sharing ===
✓ Share private prompt with user
✓ Shared user has view access
✓ Share prompt with edit access
✓ Shared user has edit access

=== Testing Visibility Queries ===
✓ Owner sees all 5 prompts
✓ Teammate sees 4 prompts (not private)
✓ Outsider sees 4 prompts (2 public + 2 shared)

=== Testing Anonymous Access ===
✓ Anonymous sees only 1 prompt (public with anonymous flag)

=== Testing Access Requests ===
✓ Request access to team prompt
✓ Owner sees 1 pending request
✓ Approve access request
✓ Approved user has access

=== Testing Get Shares ===
✓ Get shares returns correct data
✓ Team prompt has share from approved request
✓ Revoke share
✓ Share count decreased after revoke

=== Cleanup ===
Cleanup complete.

=== Test Summary ===
Total tests: 21
Passed: 21
Failed: 0

✓ All Phase 3 tests passed!
```

## Migration Notes

No database migration required - Phase 3 uses existing schema from Phase 1.

**Frontend Updates Required:**
- Update prompt creation forms to include visibility settings
- Add visibility indicators to prompt lists
- Show access level/reason to users
- Disable edit/delete buttons for view-only access
- Show "Request Access" button for inaccessible prompts

## Next Steps

**Phase 4: Collaborative Editing Tracking**
- Implement `POST /api/collaborators.php` to track active editors
- Implement `GET /api/collaborators.php` to list active collaborators
- Add cleanup for stale collaborator records (>5 minutes)
- Create heartbeat mechanism for real-time tracking

**Phase 5: Frontend Sharing UI Components**
- Create share modal with user/team search
- Add visibility toggle controls
- Implement access request button and workflow
- Create access requests management modal
- Add sharing indicators to prompt cards

**Phase 6: Collaborative Editing Indicators**
- Display real-time avatars of active editors
- Show "Currently editing" warnings
- Implement presence indicators

**Phase 7: Access Request Notifications**
- Add visual badges for pending requests
- Implement polling for new requests
- Create notification UI components

## Known Issues & Future Enhancements

1. **approveRequest Duplicate Handling**: Currently fails if share already exists. Should use INSERT OR IGNORE or check first.
2. **Rate Limiting**: public_prompts.php needs rate limiting for production.
3. **Search Optimization**: Full-text search in public_prompts.php could use FTS5 for better performance.
4. **Pagination Metadata**: Could add total_pages calculation for better UX.
5. **Access Reason Priority**: Complex logic could be documented in API docs with examples.

## Summary

Phase 3 successfully integrated the visibility and sharing system into the core prompts API. All CRUD operations now respect access control, with comprehensive testing ensuring correctness. The system provides granular permissions while maintaining backward compatibility where possible. Anonymous access is supported with explicit opt-in. Ready to proceed to Phase 4.
