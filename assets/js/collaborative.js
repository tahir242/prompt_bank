/**
 * Collaborative Editing Indicators
 * Handles real-time presence tracking and editing warnings
 */

let heartbeatInterval = null;
let activeCollaboratorsInterval = null;
let currentEditingPromptId = null;
let isEditingActive = false;

/**
 * Initialize collaborative editing features
 */
function initCollaborativeFeatures() {
    // Start checking for active collaborators on all prompts
    startCollaboratorPolling();
    
    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        if (isEditingActive && currentEditingPromptId) {
            stopEditing(currentEditingPromptId);
        }
    });
}

/**
 * Start editing a prompt - register presence
 */
async function startEditing(promptId) {
    if (!promptId) return;
    
    currentEditingPromptId = promptId;
    isEditingActive = true;
    
    // Send initial heartbeat
    await sendHeartbeat(promptId);
    
    // Start heartbeat interval (every 30 seconds)
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
    }
    
    heartbeatInterval = setInterval(() => {
        sendHeartbeat(promptId);
    }, 30000); // 30 seconds
    
    // Load and display current collaborators
    await loadPromptCollaborators(promptId);
}

/**
 * Stop editing a prompt - unregister presence
 */
async function stopEditing(promptId) {
    if (!promptId) return;
    
    try {
        const response = await fetch(`api/collaborators.php?prompt_id=${promptId}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) {
            console.error('Failed to stop editing session');
        }
    } catch (error) {
        console.error('Error stopping editing session:', error);
    }
    
    // Clear heartbeat interval
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
        heartbeatInterval = null;
    }
    
    currentEditingPromptId = null;
    isEditingActive = false;
    
    // Clear collaborator display
    clearCollaboratorDisplay();
}

/**
 * Send heartbeat to keep editing session alive
 */
async function sendHeartbeat(promptId) {
    try {
        const response = await fetch('api/collaborators.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ prompt_id: promptId })
        });
        
        if (response.ok) {
            // Reload collaborators after heartbeat
            await loadPromptCollaborators(promptId);
        }
    } catch (error) {
        console.error('Heartbeat failed:', error);
    }
}

/**
 * Load active collaborators for a specific prompt
 */
async function loadPromptCollaborators(promptId) {
    try {
        const response = await fetch(`api/collaborators.php?prompt_id=${promptId}`);
        if (!response.ok) throw new Error('Failed to load collaborators');
        
        const collaborators = await response.json();
        
        // Display collaborators (exclude current user)
        const otherCollaborators = collaborators.filter(c => c.user_id !== currentUser?.id);
        displayCollaborators(otherCollaborators);
        
        return otherCollaborators;
    } catch (error) {
        console.error('Error loading collaborators:', error);
        return [];
    }
}

/**
 * Display collaborators in the UI (for edit modal)
 */
function displayCollaborators(collaborators) {
    const container = document.getElementById('activeCollaborators');
    if (!container) return;
    
    if (collaborators.length === 0) {
        container.innerHTML = '';
        container.classList.add('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    
    // Create avatar list with escapeHtml
    const avatarList = collaborators.map(collab => {
        const initials = getInitials(collab.full_name || collab.username);
        const color = getColorForUser(collab.user_id);
        const displayName = collab.full_name || collab.username;
        
        return `
            <div class="flex items-center space-x-2 px-3 py-2 bg-amber-50 rounded-lg border border-amber-200" title="${displayName} is editing">
                <div class="w-8 h-8 rounded-full ${color} flex items-center justify-center text-white text-xs font-semibold animate-pulse">
                    ${initials}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">
                        ${displayName}
                    </p>
                    <p class="text-xs text-gray-500">
                        Currently editing
                    </p>
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = `
        <div class="space-y-2">
            <div class="flex items-center space-x-2 text-sm text-amber-800">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span class="font-semibold">
                    ${collaborators.length} ${collaborators.length === 1 ? 'person is' : 'people are'} currently editing this prompt
                </span>
            </div>
            <div class="space-y-2">
                ${avatarList}
            </div>
        </div>
    `;
}

/**
 * Clear collaborator display
 */
function clearCollaboratorDisplay() {
    const container = document.getElementById('activeCollaborators');
    if (container) {
        container.innerHTML = '';
        container.classList.add('hidden');
    }
}

/**
 * Check if anyone is editing a prompt before opening editor
 */
async function checkCollaboratorsBeforeEdit(promptId) {
    const collaborators = await loadPromptCollaborators(promptId);
    
    if (collaborators.length > 0) {
        const names = collaborators.map(c => c.full_name || c.username).join(', ');
        const message = collaborators.length === 1
            ? `${names} is currently editing this prompt. You can still edit, but be aware that changes may conflict.`
            : `${names} are currently editing this prompt. You can still edit, but be aware that changes may conflict.`;
        
        return confirm(message);
    }
    
    return true;
}

/**
 * Start polling for active collaborators on all prompts
 */
function startCollaboratorPolling() {
    // Update collaborator badges every 30 seconds
    if (activeCollaboratorsInterval) {
        clearInterval(activeCollaboratorsInterval);
    }
    
    updateAllCollaboratorBadges();
    
    activeCollaboratorsInterval = setInterval(() => {
        updateAllCollaboratorBadges();
    }, 30000); // 30 seconds
}

/**
 * Update collaborator count badges on all prompt cards
 */
async function updateAllCollaboratorBadges() {
    try {
        // Get all visible prompt IDs
        const promptCards = document.querySelectorAll('[data-prompt-id]');
        const promptIds = Array.from(promptCards).map(card => card.getAttribute('data-prompt-id'));
        
        if (promptIds.length === 0) return;
        
        // Fetch collaborators for all prompts
        const promises = promptIds.map(async (promptId) => {
            try {
                const response = await fetch(`api/collaborators.php?prompt_id=${promptId}`);
                if (!response.ok) return { promptId, count: 0 };
                
                const collaborators = await response.json();
                return { 
                    promptId, 
                    count: collaborators.length,
                    collaborators: collaborators
                };
            } catch {
                return { promptId, count: 0 };
            }
        });
        
        const results = await Promise.all(promises);
        
        // Update badges
        results.forEach(({ promptId, count, collaborators }) => {
            updateCollaboratorBadge(promptId, count, collaborators);
        });
    } catch (error) {
        console.error('Error updating collaborator badges:', error);
    }
}

/**
 * Update collaborator badge for a specific prompt card
 */
function updateCollaboratorBadge(promptId, count, collaborators) {
    const card = document.querySelector(`[data-prompt-id="${promptId}"]`);
    if (!card) return;
    
    // Find or create badge container
    let badgeContainer = card.querySelector('.collaborator-badge-container');
    
    if (count === 0) {
        // Remove badge if no collaborators
        if (badgeContainer) {
            badgeContainer.remove();
        }
        return;
    }
    
    // Create badge if it doesn't exist
    if (!badgeContainer) {
        badgeContainer = document.createElement('div');
        badgeContainer.className = 'collaborator-badge-container absolute top-2 left-2 z-10';
        card.appendChild(badgeContainer);
    }
    
    // Get collaborator names for tooltip
    const names = collaborators.map(c => c.full_name || c.username).join(', ');
    
    // Update badge content
    badgeContainer.innerHTML = `
        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-amber-100 text-amber-900 border-2 border-amber-400 shadow-lg animate-pulse" 
              title="${escapeHtml(names)} ${count === 1 ? 'is' : 'are'} editing">
            <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
            </svg>
            ${count} editing
        </span>
    `;
}

/**
 * Get initials from name
 */
function getInitials(name) {
    if (!name) return '?';
    
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }
    
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/**
 * Get consistent color for user based on ID
 */
function getColorForUser(userId) {
    const colors = [
        'bg-blue-500',
        'bg-green-500',
        'bg-purple-500',
        'bg-pink-500',
        'bg-indigo-500',
        'bg-red-500',
        'bg-yellow-500',
        'bg-teal-500'
    ];
    
    // Use user ID to consistently assign color
    const index = userId % colors.length;
    return colors[index];
}

/**
 * Show warning when opening a prompt that others are editing
 */
async function showEditingWarningIfNeeded(promptId) {
    const collaborators = await loadPromptCollaborators(promptId);
    const otherCollaborators = collaborators.filter(c => c.user_id !== currentUser?.id);
    
    if (otherCollaborators.length > 0) {
        const container = document.getElementById('editingWarning');
        if (!container) return;
        
        const names = otherCollaborators.map(c => c.full_name || c.username).join(', ');
        const message = otherCollaborators.length === 1
            ? `${names} is currently editing this prompt`
            : `${names} are currently editing this prompt`;
        
        container.innerHTML = `
            <div class="bg-amber-50 border-l-4 border-amber-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-amber-700">
                            <strong>Concurrent editing detected:</strong> ${escapeHtml(message)}. Changes may conflict.
                        </p>
                    </div>
                </div>
            </div>
        `;
        container.classList.remove('hidden');
    }
}

/**
 * Hide editing warning
 */
function hideEditingWarning() {
    const container = document.getElementById('editingWarning');
    if (container) {
        container.classList.add('hidden');
        container.innerHTML = '';
    }
}
