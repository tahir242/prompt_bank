# Phase 4 Complete: Collaborative Editing Tracking

**Completed:** November 8, 2025
**Status:** ✅ All tests passing (22/22)

## Objectives Achieved

1. ✅ Created POST endpoint for collaborator heartbeat (update active status)
2. ✅ Created GET endpoint to list active collaborators
3. ✅ Created DELETE endpoint to remove collaborator
4. ✅ Implemented automatic cleanup of stale records (>5 minutes)
5. ✅ Integrated with access control system (edit access required)
6. ✅ Created comprehensive test suite with 22 passing tests

## Files Created

### api/collaborators.php (NEW FILE)
Real-time collaborative editing tracking endpoint.

**POST - Update Collaborator Status (Heartbeat)**
- Requires authentication
- Checks user has edit access to prompt via `canAccessPrompt()`
- Returns 403 if user lacks edit access
- Uses `INSERT ... ON CONFLICT DO UPDATE` for upsert behavior
- Automatically cleans up stale records (>5 minutes) before update
- Returns updated list of active collaborators
- Request body: `{"prompt_id": 123}`

**GET - List Active Collaborators**
- Requires authentication
- Checks user has access to view the prompt (any access level)
- Returns 403 if user lacks access
- Automatically cleans up stale records before listing
- Returns collaborators with user details (username, full_name)
- Includes `seconds_ago` calculation for each collaborator
- Query params: `?prompt_id=123`

**DELETE - Remove Collaborator**
- Requires authentication
- Removes current user from active collaborators
- Returns updated list of remaining collaborators
- Query params: `?prompt_id=123`

**Helper Function: getActiveCollaborators($promptId)**
- Joins with users table to get user details
- Filters records from last 5 minutes
- Calculates seconds since last activity
- Orders by most recent first

**Automatic Cleanup Logic:**
```sql
DELETE FROM prompt_collaborators 
WHERE last_activity < datetime('now', '-5 minutes')
```

### database/test_collaborators.php (NEW FILE)
Comprehensive test suite covering all Phase 4 functionality.

**Test Coverage (22 tests):**

*Collaborator Registration (4 tests):*
1. Owner registers as collaborator
2. Editor registers as collaborator  
3. Multiple collaborators active simultaneously
4. Get collaborators returns user details

*Heartbeat Updates (2 tests):*
5. Heartbeat updates existing record (ON CONFLICT)
6. Last activity timestamp is recent (<2 seconds)

*Stale Record Cleanup (5 tests):*
7. Create stale record (6 minutes old)
8. Stale record exists in database
9. Cleanup query executes successfully
10. Stale record removed after cleanup
11. Recent collaborators remain after cleanup

*Access Control (3 tests):*
12. Editor has edit access to team prompt
13. Editor has view-only access to view-only prompt
14. Outsider has no access to private prompt

*Collaborator Removal (3 tests):*
15. Remove specific collaborator
16. Collaborator removed from database
17. Other collaborators remain

*Edge Cases (3 tests):*
18. Can add collaborator to archived prompt (FK allows)
19. UNIQUE constraint prevents duplicates
20. ON CONFLICT updates timestamp instead of error

*Cascading Deletes (2 tests):*
21. Collaborators cascade deleted when prompt deleted
22. Collaborators cascade deleted when user deleted

**Test Data:**
- 3 test users (IDs: 9201-9203)
- 1 test team (ID: 9301)
- 3 test prompts (team edit, team view, private)
- Full cleanup after tests

## Database Schema (Existing from Phase 1)

The `prompt_collaborators` table was created in Phase 1:

```sql
CREATE TABLE prompt_collaborators (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    prompt_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_editing BOOLEAN DEFAULT 0,
    FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(prompt_id, user_id)
);
```

**Key Features:**
- `UNIQUE(prompt_id, user_id)`: Prevents duplicate entries
- `ON DELETE CASCADE`: Auto-cleanup when prompt/user deleted
- `last_activity`: Timestamp for staleness checking
- `is_editing`: Reserved for future use (currently unused)

## API Behavior

### Heartbeat Mechanism
Frontend should call `POST /api/collaborators.php` every 30-60 seconds while user is actively editing:

```javascript
// Every 30 seconds while editing
setInterval(() => {
  fetch('/api/collaborators.php', {
    method: 'POST',
    body: JSON.stringify({ prompt_id: currentPromptId })
  });
}, 30000);
```

### Real-time Collaborator List
Frontend can poll `GET /api/collaborators.php?prompt_id=X` to show active collaborators:

```javascript
// Every 10 seconds to update UI
setInterval(() => {
  fetch('/api/collaborators.php?prompt_id=' + currentPromptId)
    .then(r => r.json())
    .then(data => updateCollaboratorAvatars(data.collaborators));
}, 10000);
```

### Cleanup on Navigate Away
When user stops editing, call `DELETE`:

```javascript
window.addEventListener('beforeunload', () => {
  navigator.sendBeacon(
    '/api/collaborators.php?prompt_id=' + currentPromptId,
    new Blob([], { type: 'application/json' })
  );
});
```

## Access Control Integration

Collaborative editing respects the visibility and sharing system from Phase 3:

| User Type | Can Be Collaborator? |
|-----------|---------------------|
| Owner | ✅ Yes (edit access) |
| Admin | ✅ Yes (edit access) |
| Same Team (edit level) | ✅ Yes (edit access) |
| Same Team (view level) | ❌ No (view only) |
| Direct Share (edit) | ✅ Yes (edit access) |
| Direct Share (view) | ❌ No (view only) |
| Other User | ❌ No (no access) |
| Anonymous | ❌ No (no access) |

**Logic:**
```php
$access = canAccessPrompt($userId, $promptId);
if (!$access || $access['access_level'] !== 'edit') {
    // Cannot be collaborator
}
```

## Security Considerations

1. **Edit Access Required**: Users must have `edit` permission to appear as collaborators
2. **Access Checked on Every Request**: POST/GET/DELETE all verify access
3. **Automatic Cleanup**: Stale records (>5 minutes) automatically removed
4. **Foreign Key Constraints**: Cascading deletes prevent orphaned records
5. **UNIQUE Constraint**: Prevents duplicate collaborator entries
6. **No Anonymous Collaboration**: Authentication required for all endpoints

## Performance Considerations

1. **Automatic Cleanup**: Runs on every POST/GET request
   - Optimized with datetime comparison
   - Indexed by `prompt_id` and `user_id`
   
2. **Query Optimization**: JOIN with users table for details
   - Could add index on `last_activity` if performance issues
   
3. **Heartbeat Frequency**: Recommended 30-60 seconds
   - Too frequent: Unnecessary database writes
   - Too infrequent: Appears offline to others
   
4. **5-Minute Threshold**: Reasonable timeout
   - Covers network hiccups
   - Not so long that stale records linger

## Testing Results

```
Starting Phase 4 Collaborative Editing Tests...

=== Setup ===
Created test data.

=== Testing Collaborator Registration ===
✓ Owner registers as collaborator
✓ Editor registers as collaborator
✓ Multiple collaborators active
✓ Get collaborators returns user details

=== Testing Heartbeat Updates ===
✓ Heartbeat updates existing record
✓ Last seen timestamp is recent

=== Testing Stale Record Cleanup ===
✓ Created stale record
✓ Stale record exists
✓ Cleanup executed
✓ Stale record removed
✓ Recent collaborators still active

=== Testing Access Control ===
✓ Editor has edit access to team prompt
✓ Editor has only view access to view-only prompt
✓ Outsider has no access to private prompt

=== Testing Collaborator Removal ===
✓ Remove collaborator
✓ Collaborator removed from database
✓ Other collaborators remain

=== Testing Edge Cases ===
✓ Can add collaborator to archived prompt
✓ UNIQUE constraint prevents duplicates
✓ ON CONFLICT updates timestamp

=== Testing Cascading Deletes ===
✓ Collaborators cascade deleted with prompt
✓ Collaborators cascade deleted with user

=== Cleanup ===
Cleanup complete.

=== Test Summary ===
Total tests: 22
Passed: 22
Failed: 0

✓ All Phase 4 tests passed!
```

## Integration Points

### With Phase 3 (Prompts API)
- `canAccessPrompt()` function used to verify edit access
- Collaborators only tracked for prompts user can edit
- Works with all visibility levels (private/team/public)

### With Phase 5 (Frontend UI - Next)
- Frontend will call POST endpoint every 30-60 seconds
- Frontend will poll GET endpoint to display avatars
- Frontend will call DELETE on navigation away

### With Phase 6 (Collaborative Indicators - Next)
- Collaborator list will be used to show real-time avatars
- "Currently editing" warnings based on collaborator presence
- Color-coded presence indicators

## Known Issues & Future Enhancements

1. **is_editing Column**: Currently unused, reserved for future enhanced presence
2. **Race Conditions**: Multiple simultaneous updates could conflict (rare, benign)
3. **Scalability**: For high-traffic scenarios, consider Redis for presence tracking
4. **Websockets**: Real-time updates currently require polling, could use WebSockets
5. **Cursor Position**: Not tracked, could add in future for true collaborative editing

## Next Steps

**Phase 5: Frontend Sharing UI Components**
- Create share modal with user/team search
- Add visibility toggle controls (private/team/public)
- Implement "Request Access" button for inaccessible prompts
- Create access requests management modal
- Add sharing indicators to prompt cards
- Show collaborator count on prompts

**Phase 6: Collaborative Editing Indicators**
- Display real-time avatars using collaborators API
- Show "Currently editing" warnings when opening prompt
- Implement presence indicators with colors
- Add "X users editing" badge to prompt list

**Phase 7: Access Request Notifications**
- Add visual badges for pending requests
- Implement polling for new access requests
- Create notification UI components
- Link to access requests management

## Summary

Phase 4 successfully implemented real-time collaborative editing tracking with automatic cleanup, comprehensive access control, and full integration with the visibility system. The heartbeat mechanism efficiently tracks active editors with a 5-minute timeout. All 22 tests pass, validating registration, heartbeat updates, stale cleanup, access control, and cascading deletes. Ready to proceed to Phase 5 for frontend UI implementation.
