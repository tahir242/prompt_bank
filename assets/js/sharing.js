/**
 * Sharing and Collaboration UI Functions
 * Handles share modal, access requests, visibility controls
 */

let currentSharePromptId = null;
let shareSearchTimeout = null;
let allUsers = [];
let allTeams = [];

// Initialize sharing event listeners
function initSharingListeners() {
    // Share modal
    document.getElementById('sharePromptBtn')?.addEventListener('click', openShareModal);
    document.getElementById('closeShareModalBtn')?.addEventListener('click', closeShareModal);
    document.getElementById('saveShareSettingsBtn')?.addEventListener('click', saveShareSettings);
    
    // Visibility radio buttons
    document.querySelectorAll('input[name="visibility"]').forEach(radio => {
        radio.addEventListener('change', handleVisibilityChange);
    });
    
    // Search for users/teams
    document.getElementById('shareSearchInput')?.addEventListener('input', handleShareSearch);
    document.getElementById('addShareBtn')?.addEventListener('click', addShare);
    
    // Access requests
    document.getElementById('accessRequestsBtn')?.addEventListener('click', openAccessRequestsModal);
    document.getElementById('closeAccessRequestsModalBtn')?.addEventListener('click', closeAccessRequestsModal);
    
    // Request access modal
    document.getElementById('closeRequestAccessModalBtn')?.addEventListener('click', closeRequestAccessModal);
    document.getElementById('requestAccessForm')?.addEventListener('submit', handleRequestAccess);
    
    // Poll for access requests every 30 seconds
    setInterval(checkAccessRequests, 30000);
    
    // Initial check
    checkAccessRequests();
}

// Open share modal for current prompt
async function openShareModal() {
    if (!currentPromptId) return;
    
    currentSharePromptId = currentPromptId;
    const modal = document.getElementById('shareModal');
    const prompt = prompts.find(p => p.id === currentPromptId);
    
    if (!prompt) return;
    
    // Set prompt title
    document.getElementById('sharePromptTitle').textContent = prompt.title;
    
    // Set current visibility
    document.querySelectorAll('input[name="visibility"]').forEach(radio => {
        radio.checked = radio.value === (prompt.visibility || 'private');
    });
    
    // Show/hide conditional fields
    handleVisibilityChange();
    
    // Set team access level
    if (prompt.team_access_level) {
        document.getElementById('teamAccessLevelSelect').value = prompt.team_access_level;
    }
    
    // Set anonymous access
    if (prompt.allow_anonymous) {
        document.getElementById('allowAnonymousCheck').checked = true;
    }
    
    // Load current shares
    await loadCurrentShares(currentPromptId);
    
    // Load users and teams for search
    await loadUsersAndTeams();
    
    modal.classList.remove('hidden');
}

function closeShareModal() {
    document.getElementById('shareModal').classList.add('hidden');
    currentSharePromptId = null;
    
    // Clear search
    document.getElementById('shareSearchInput').value = '';
    document.getElementById('shareSearchResults').classList.add('hidden');
    
    // Hide error
    document.getElementById('shareError').classList.add('hidden');
}

// Handle visibility radio button change
function handleVisibilityChange() {
    const selectedVisibility = document.querySelector('input[name="visibility"]:checked')?.value;
    const teamAccessDiv = document.getElementById('teamAccessLevel');
    const anonymousDiv = document.getElementById('anonymousAccess');
    
    // Show team access level for team visibility
    if (selectedVisibility === 'team') {
        teamAccessDiv.classList.remove('hidden');
        anonymousDiv.classList.add('hidden');
    }
    // Show anonymous checkbox for public visibility
    else if (selectedVisibility === 'public') {
        teamAccessDiv.classList.add('hidden');
        anonymousDiv.classList.remove('hidden');
    }
    // Hide both for private
    else {
        teamAccessDiv.classList.add('hidden');
        anonymousDiv.classList.add('hidden');
    }
}

// Load current shares for a prompt
async function loadCurrentShares(promptId) {
    try {
        const response = await fetch(`api/shares.php?prompt_id=${promptId}`);
        const data = await response.json();
        
        if (response.ok && data.shares) {
            displayCurrentShares(data.shares);
        } else {
            document.getElementById('currentShares').innerHTML = 
                '<p class="text-sm text-gray-500">No shares yet</p>';
        }
    } catch (error) {
        console.error('Error loading shares:', error);
        document.getElementById('currentShares').innerHTML = 
            '<p class="text-sm text-red-600">Error loading shares</p>';
    }
}

// Display current shares
function displayCurrentShares(shares) {
    const container = document.getElementById('currentShares');
    
    if (shares.length === 0) {
        container.innerHTML = '<p class="text-sm text-gray-500">No shares yet</p>';
        return;
    }
    
    container.innerHTML = shares.map(share => `
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    ${share.shared_with_user_id ? `
                        <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center text-white font-medium text-sm">
                            ${(share.shared_with_username || 'U').charAt(0).toUpperCase()}
                        </div>
                    ` : `
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    `}
                </div>
                <div>
                    <div class="font-medium text-gray-900">
                        ${share.shared_with_user_id ? share.shared_with_username : share.shared_with_team_name}
                    </div>
                    <div class="text-sm text-gray-600">
                        ${share.access_level === 'edit' ? 'Can edit' : 'Can view'}
                    </div>
                </div>
            </div>
            <button onclick="removeShare(${share.id})" class="text-red-600 hover:text-red-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    `).join('');
}

// Load users and teams for search
async function loadUsersAndTeams() {
    try {
        // Load users
        const usersResponse = await fetch('api/users.php');
        const usersData = await usersResponse.json();
        allUsers = usersData.users || [];
        
        // Load teams
        const teamsResponse = await fetch('api/teams.php');
        const teamsData = await teamsResponse.json();
        allTeams = teamsData.teams || [];
    } catch (error) {
        console.error('Error loading users/teams:', error);
    }
}

// Handle share search input
function handleShareSearch(e) {
    const query = e.target.value.trim().toLowerCase();
    
    if (query.length < 2) {
        document.getElementById('shareSearchResults').classList.add('hidden');
        return;
    }
    
    // Debounce search
    clearTimeout(shareSearchTimeout);
    shareSearchTimeout = setTimeout(() => {
        performShareSearch(query);
    }, 300);
}

// Perform share search
function performShareSearch(query) {
    const results = [];
    
    // Search users
    allUsers.forEach(user => {
        if (user.username.toLowerCase().includes(query) || 
            user.full_name.toLowerCase().includes(query)) {
            results.push({
                type: 'user',
                id: user.id,
                name: user.username,
                subtitle: user.full_name
            });
        }
    });
    
    // Search teams
    allTeams.forEach(team => {
        if (team.name.toLowerCase().includes(query)) {
            results.push({
                type: 'team',
                id: team.id,
                name: team.name,
                subtitle: 'Team'
            });
        }
    });
    
    displayShareSearchResults(results);
}

// Display share search results
function displayShareSearchResults(results) {
    const container = document.getElementById('shareSearchResults');
    
    if (results.length === 0) {
        container.innerHTML = '<div class="p-3 text-sm text-gray-500">No users or teams found</div>';
        container.classList.remove('hidden');
        return;
    }
    
    container.innerHTML = results.map(result => `
        <div class="flex items-center justify-between p-3 hover:bg-gray-50 cursor-pointer" 
             onclick="selectShareTarget('${result.type}', ${result.id}, '${result.name}')">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    ${result.type === 'user' ? `
                        <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center text-white font-medium text-sm">
                            ${result.name.charAt(0).toUpperCase()}
                        </div>
                    ` : `
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    `}
                </div>
                <div>
                    <div class="font-medium text-gray-900">${result.name}</div>
                    <div class="text-sm text-gray-600">${result.subtitle}</div>
                </div>
            </div>
        </div>
    `).join('');
    
    container.classList.remove('hidden');
}

// Select share target from search
function selectShareTarget(type, id, name) {
    // Store in hidden input or data attribute
    document.getElementById('shareSearchInput').value = name;
    document.getElementById('shareSearchInput').dataset.targetType = type;
    document.getElementById('shareSearchInput').dataset.targetId = id;
    document.getElementById('shareSearchResults').classList.add('hidden');
}

// Add share
async function addShare() {
    const input = document.getElementById('shareSearchInput');
    const targetType = input.dataset.targetType;
    const targetId = input.dataset.targetId;
    const accessLevel = document.getElementById('shareAccessLevel').value;
    
    if (!targetType || !targetId) {
        showToast('Please select a user or team', 'error');
        return;
    }
    
    const payload = {
        prompt_id: currentSharePromptId,
        access_level: accessLevel
    };
    
    if (targetType === 'user') {
        payload.shared_with_user_id = parseInt(targetId);
    } else {
        payload.shared_with_team_id = parseInt(targetId);
    }
    
    try {
        const response = await fetch('api/shares.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast('Share added successfully');
            
            // Clear search
            input.value = '';
            delete input.dataset.targetType;
            delete input.dataset.targetId;
            
            // Reload shares
            await loadCurrentShares(currentSharePromptId);
        } else {
            showError('shareError', data.error || 'Failed to add share');
        }
    } catch (error) {
        console.error('Error adding share:', error);
        showError('shareError', 'Failed to add share');
    }
}

// Remove share
async function removeShare(shareId) {
    if (!confirm('Remove this share?')) return;
    
    try {
        const response = await fetch(`api/shares.php?id=${shareId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast('Share removed');
            await loadCurrentShares(currentSharePromptId);
        } else {
            showError('shareError', data.error || 'Failed to remove share');
        }
    } catch (error) {
        console.error('Error removing share:', error);
        showError('shareError', 'Failed to remove share');
    }
}

// Save share settings (visibility changes)
async function saveShareSettings() {
    const visibility = document.querySelector('input[name="visibility"]:checked')?.value;
    const teamAccessLevel = document.getElementById('teamAccessLevelSelect').value;
    const allowAnonymous = document.getElementById('allowAnonymousCheck').checked;
    
    const payload = {
        id: currentSharePromptId,
        title: prompts.find(p => p.id === currentSharePromptId)?.title,
        content: prompts.find(p => p.id === currentSharePromptId)?.content,
        category_id: prompts.find(p => p.id === currentSharePromptId)?.category_id,
        visibility,
        team_access_level: teamAccessLevel,
        allow_anonymous: allowAnonymous ? 1 : 0
    };
    
    try {
        const response = await fetch('api/prompts.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast('Settings saved successfully');
            closeShareModal();
            
            // Reload prompts to get updated visibility
            await loadPrompts();
        } else {
            showError('shareError', data.error || 'Failed to save settings');
        }
    } catch (error) {
        console.error('Error saving settings:', error);
        showError('shareError', 'Failed to save settings');
    }
}

// Check for pending access requests
async function checkAccessRequests() {
    try {
        const response = await fetch('api/access_requests.php');
        const data = await response.json();
        
        if (response.ok && data.requests) {
            const pendingCount = data.requests.filter(r => r.status === 'pending').length;
            
            const badge = document.getElementById('accessRequestsBadge');
            if (pendingCount > 0) {
                badge.textContent = pendingCount;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    } catch (error) {
        console.error('Error checking access requests:', error);
    }
}

// Open access requests modal
async function openAccessRequestsModal() {
    const modal = document.getElementById('accessRequestsModal');
    modal.classList.remove('hidden');
    
    await loadAccessRequests();
}

function closeAccessRequestsModal() {
    document.getElementById('accessRequestsModal').classList.add('hidden');
}

// Load access requests
async function loadAccessRequests() {
    try {
        const response = await fetch('api/access_requests.php');
        const data = await response.json();
        
        if (response.ok && data.requests) {
            displayAccessRequests(data.requests.filter(r => r.status === 'pending'));
        } else {
            document.getElementById('accessRequestsList').innerHTML = '';
            document.getElementById('noAccessRequests').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading access requests:', error);
        showError('accessRequestsError', 'Failed to load access requests');
    }
}

// Display access requests
function displayAccessRequests(requests) {
    const container = document.getElementById('accessRequestsList');
    const emptyState = document.getElementById('noAccessRequests');
    
    if (requests.length === 0) {
        container.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
    }
    
    emptyState.classList.add('hidden');
    
    container.innerHTML = requests.map(request => `
        <div class="border rounded-lg p-4 bg-white">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <div class="font-medium text-gray-900">${request.username}</div>
                    <div class="text-sm text-gray-600">Wants access to: <span class="font-medium">${request.prompt_title}</span></div>
                </div>
                <span class="text-xs text-gray-500">${new Date(request.requested_at).toLocaleDateString()}</span>
            </div>
            
            ${request.message ? `
                <div class="mt-2 p-2 bg-gray-50 rounded text-sm text-gray-700">
                    "${request.message}"
                </div>
            ` : ''}
            
            <div class="mt-3 flex gap-2">
                <button onclick="approveAccessRequest(${request.id}, 'view')" 
                        class="flex-1 px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm font-medium">
                    Approve (View)
                </button>
                <button onclick="approveAccessRequest(${request.id}, 'edit')" 
                        class="flex-1 px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium">
                    Approve (Edit)
                </button>
                <button onclick="denyAccessRequest(${request.id})" 
                        class="flex-1 px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm font-medium">
                    Deny
                </button>
            </div>
        </div>
    `).join('');
}

// Approve access request
async function approveAccessRequest(requestId, accessLevel) {
    try {
        const response = await fetch('api/access_requests.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: requestId,
                action: 'approve',
                access_level: accessLevel
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast('Access request approved');
            await loadAccessRequests();
            await checkAccessRequests();
        } else {
            showError('accessRequestsError', data.error || 'Failed to approve request');
        }
    } catch (error) {
        console.error('Error approving request:', error);
        showError('accessRequestsError', 'Failed to approve request');
    }
}

// Deny access request
async function denyAccessRequest(requestId) {
    try {
        const response = await fetch('api/access_requests.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: requestId,
                action: 'deny'
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast('Access request denied');
            await loadAccessRequests();
            await checkAccessRequests();
        } else {
            showError('accessRequestsError', data.error || 'Failed to deny request');
        }
    } catch (error) {
        console.error('Error denying request:', error);
        showError('accessRequestsError', 'Failed to deny request');
    }
}

// Open request access modal
function openRequestAccessModal(promptId, promptTitle) {
    document.getElementById('requestAccessPromptTitle').textContent = promptTitle;
    document.getElementById('requestAccessModal').dataset.promptId = promptId;
    document.getElementById('requestAccessModal').classList.remove('hidden');
}

function closeRequestAccessModal() {
    document.getElementById('requestAccessModal').classList.add('hidden');
    document.getElementById('requestAccessMessage').value = '';
    document.getElementById('requestAccessError').classList.add('hidden');
}

// Handle request access form submission
async function handleRequestAccess(e) {
    e.preventDefault();
    
    const modal = document.getElementById('requestAccessModal');
    const promptId = modal.dataset.promptId;
    const message = document.getElementById('requestAccessMessage').value.trim();
    
    try {
        const response = await fetch('api/access_requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                prompt_id: parseInt(promptId),
                message: message || null
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showToast('Access request sent successfully');
            closeRequestAccessModal();
        } else {
            showError('requestAccessError', data.error || 'Failed to send request');
        }
    } catch (error) {
        console.error('Error sending request:', error);
        showError('requestAccessError', 'Failed to send request');
    }
}

// Show error message in modal
function showError(elementId, message) {
    const errorDiv = document.getElementById(elementId);
    errorDiv.textContent = message;
    errorDiv.classList.remove('hidden');
    
    setTimeout(() => {
        errorDiv.classList.add('hidden');
    }, 5000);
}
