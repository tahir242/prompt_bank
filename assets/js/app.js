// Global State
let currentUser = null;
let userRole = null;
let userPermissions = {};
let prompts = [];
let categories = [];
let currentPromptId = null;
let editingPromptId = null;
let easyMDE = null;

// Initialize App
document.addEventListener('DOMContentLoaded', () => {
    checkSession();
    setupEventListeners();
});

function checkSession() {
    // Check if user is logged in via PHP session
    const loginScreen = document.getElementById('loginScreen');
    const dashboard = document.getElementById('dashboard');
    
    if (dashboard.classList.contains('hidden')) {
        // Not logged in, show login screen
        loginScreen.classList.remove('hidden');
    } else {
        // Logged in, initialize dashboard
        initDashboard();
    }
}

function setupEventListeners() {
    // Login Form
    document.getElementById('loginForm')?.addEventListener('submit', handleLogin);
    
    // Registration
    document.getElementById('showRegisterBtn')?.addEventListener('click', openRegisterModal);
    document.getElementById('registerForm')?.addEventListener('submit', handleRegister);
    document.getElementById('cancelRegisterBtn')?.addEventListener('click', closeRegisterModal);
    
    // Logout
    document.getElementById('logoutBtn')?.addEventListener('click', handleLogout);
    
    // Add Prompt Button
    document.getElementById('addPromptBtn')?.addEventListener('click', () => {
        editingPromptId = null;
        openPromptModal();
    });
    
    // Prompt Form
    document.getElementById('promptForm')?.addEventListener('submit', handleSavePrompt);
    
    // Modal Controls
    document.getElementById('cancelModalBtn')?.addEventListener('click', closePromptModal);
    document.getElementById('closeDetailBtn')?.addEventListener('click', closeDetailModal);
    document.getElementById('cancelDeleteBtn')?.addEventListener('click', closeDeleteModal);
    document.getElementById('confirmDeleteBtn')?.addEventListener('click', confirmDelete);
    document.getElementById('closeDiffBtn')?.addEventListener('click', closeDiffModal);
    
    // Detail Modal Actions
    document.getElementById('copyPromptBtn')?.addEventListener('click', handleCopyPrompt);
    document.getElementById('editPromptBtn')?.addEventListener('click', handleEditPrompt);
    document.getElementById('deletePromptBtn')?.addEventListener('click', () => {
        openDeleteModal();
    });
    
    // Search and Filter
    document.getElementById('searchInput')?.addEventListener('input', filterPrompts);
    document.getElementById('categoryFilter')?.addEventListener('change', filterPrompts);
    
    // Category Management
    document.getElementById('manageCategoriesBtn')?.addEventListener('click', openCategoryModal);
    document.getElementById('closeCategoryModalBtn')?.addEventListener('click', closeCategoryModal);
    document.getElementById('closeCategoryModalFooterBtn')?.addEventListener('click', closeCategoryModal);
    document.getElementById('addCategoryForm')?.addEventListener('submit', handleAddCategory);
    
    // Admin Panel
    document.getElementById('adminPanelBtn')?.addEventListener('click', openAdminPanel);
    document.getElementById('closeAdminPanelBtn')?.addEventListener('click', closeAdminPanel);
    document.getElementById('closeAdminPanelFooterBtn')?.addEventListener('click', closeAdminPanel);
    
    // Admin Tabs
    document.querySelectorAll('.admin-tab').forEach(tab => {
        tab.addEventListener('click', (e) => {
            const button = e.currentTarget;
            switchAdminTab(button.dataset.tab);
        });
    });
    
    // User Management
    document.getElementById('editUserForm')?.addEventListener('submit', handleEditUser);
    document.getElementById('cancelEditUserBtn')?.addEventListener('click', closeEditUserModal);
    
    // Team Management
    document.getElementById('addTeamBtn')?.addEventListener('click', openAddTeamModal);
    document.getElementById('addTeamForm')?.addEventListener('submit', handleAddTeam);
    document.getElementById('cancelAddTeamBtn')?.addEventListener('click', closeAddTeamModal);
    
    // Detail Tabs
    document.querySelectorAll('.detail-tab').forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            // Use currentTarget instead of target to always get the button element
            const button = e.currentTarget;
            switchTab(button.dataset.tab);
        });
    });
}

// Authentication
async function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const errorDiv = document.getElementById('loginError');
    
    try {
        const response = await fetch('api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            userRole = data.user.role_name;
            userPermissions = data.user.permissions || {};
            
            document.getElementById('loginScreen').classList.add('hidden');
            document.getElementById('dashboard').classList.remove('hidden');
            initDashboard();
        } else {
            errorDiv.textContent = data.error || 'Login failed';
            errorDiv.classList.remove('hidden');
        }
    } catch (error) {
        errorDiv.textContent = 'Network error. Please try again.';
        errorDiv.classList.remove('hidden');
    }
}

async function handleLogout() {
    try {
        await fetch('api/logout.php');
        location.reload();
    } catch (error) {
        console.error('Logout failed:', error);
        location.reload();
    }
}

// Registration Modal
function openRegisterModal() {
    document.getElementById('registerModal').classList.remove('hidden');
    document.getElementById('registerForm').reset();
    document.getElementById('registerError').classList.add('hidden');
    document.getElementById('registerSuccess').classList.add('hidden');
}

function closeRegisterModal() {
    document.getElementById('registerModal').classList.add('hidden');
    document.getElementById('registerForm').reset();
    document.getElementById('registerError').classList.add('hidden');
    document.getElementById('registerSuccess').classList.add('hidden');
}

async function handleRegister(e) {
    e.preventDefault();
    
    const username = document.getElementById('registerUsername').value.trim();
    const fullName = document.getElementById('registerFullName').value.trim();
    const password = document.getElementById('registerPassword').value;
    const confirmPassword = document.getElementById('registerConfirmPassword').value;
    const errorDiv = document.getElementById('registerError');
    const successDiv = document.getElementById('registerSuccess');
    
    // Hide previous messages
    errorDiv.classList.add('hidden');
    successDiv.classList.add('hidden');
    
    // Client-side validation
    if (password !== confirmPassword) {
        errorDiv.textContent = 'Passwords do not match';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    if (password.length < 6) {
        errorDiv.textContent = 'Password must be at least 6 characters long';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    if (!username.match(/^[a-zA-Z0-9_]{3,20}$/)) {
        errorDiv.textContent = 'Username must be 3-20 characters long and contain only letters, numbers, and underscores';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    if (fullName.length < 2) {
        errorDiv.textContent = 'Please enter your full name';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    try {
        const response = await fetch('api/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, full_name: fullName, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            successDiv.textContent = data.message || 'Registration successful! Redirecting to login...';
            successDiv.classList.remove('hidden');
            
            // Clear form
            document.getElementById('registerForm').reset();
            
            // Close modal and show login after 2 seconds
            setTimeout(() => {
                closeRegisterModal();
                // Show a success message on the login screen
                const loginError = document.getElementById('loginError');
                loginError.textContent = 'Account created successfully! Please log in.';
                loginError.classList.remove('bg-red-50', 'border-red-200', 'text-red-700');
                loginError.classList.add('bg-green-50', 'border-green-200', 'text-green-700');
                loginError.classList.remove('hidden');
                
                // Reset login error styling after a few seconds
                setTimeout(() => {
                    loginError.classList.add('hidden');
                    loginError.classList.remove('bg-green-50', 'border-green-200', 'text-green-700');
                    loginError.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
                }, 5000);
            }, 2000);
        } else {
            errorDiv.textContent = data.error || 'Registration failed';
            errorDiv.classList.remove('hidden');
        }
    } catch (error) {
        errorDiv.textContent = 'Network error. Please try again.';
        errorDiv.classList.remove('hidden');
    }
}

// Dashboard Initialization
async function initDashboard() {
    // Set current user and role
    const userElement = document.getElementById('currentUser');
    if (userElement) {
        userElement.textContent = currentUser?.username || 'User';
    }
    
    // Update UI based on role and permissions
    updateUIForRole();
    
    // Initialize sharing listeners
    if (typeof initSharingListeners === 'function') {
        initSharingListeners();
    }
    
    await loadCategories();
    await loadPrompts();
}

// Helper: Check if user has permission
function hasPermission(permission) {
    return userPermissions && userPermissions[permission] === true;
}

// Helper: Check if user is in a specific role
function isRole(roleName) {
    return userRole === roleName;
}

// Update UI elements based on user role and permissions
function updateUIForRole() {
    // Display role badge
    displayRoleBadge();
    
    // Control button visibility
    const addPromptBtn = document.getElementById('addPromptBtn');
    const manageCategoriesBtn = document.getElementById('manageCategoriesBtn');
    const adminPanelBtn = document.getElementById('adminPanelBtn');
    
    // Show/hide Admin Panel button (Admin only)
    if (adminPanelBtn) {
        if (isRole('Admin')) {
            adminPanelBtn.classList.remove('hidden');
        } else {
            adminPanelBtn.classList.add('hidden');
        }
    }
    
    // Show/hide Add Prompt button based on create_prompt permission
    if (addPromptBtn) {
        if (hasPermission('create_prompt')) {
            addPromptBtn.classList.remove('hidden');
        } else {
            addPromptBtn.classList.add('hidden');
        }
    }
    
    // Show/hide Manage Categories button based on manage_categories permission
    if (manageCategoriesBtn) {
        if (hasPermission('manage_categories')) {
            manageCategoriesBtn.classList.remove('hidden');
        } else {
            manageCategoriesBtn.classList.add('hidden');
        }
    }
    
    // Add visual indicator for read-only users
    if (isRole('Viewer')) {
        showViewerNotice();
    }
}

// Display role badge in header
function displayRoleBadge() {
    const userElement = document.getElementById('currentUser');
    if (userElement && userRole) {
        const roleColors = {
            'Admin': 'bg-red-100 text-red-800',
            'Editor': 'bg-blue-100 text-blue-800',
            'Viewer': 'bg-gray-100 text-gray-800'
        };
        
        const colorClass = roleColors[userRole] || 'bg-gray-100 text-gray-800';
        const roleBadge = `<span class="ml-2 px-2 py-1 text-xs font-semibold rounded ${colorClass}">${userRole}</span>`;
        
        // Check if badge already exists
        if (!userElement.innerHTML.includes('role-badge')) {
            userElement.innerHTML += roleBadge;
        }
    }
}

// Show notice for read-only viewers
function showViewerNotice() {
    const promptsContainer = document.getElementById('promptsList');
    if (promptsContainer && !document.getElementById('viewerNotice')) {
        const notice = document.createElement('div');
        notice.id = 'viewerNotice';
        notice.className = 'bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg mb-4';
        notice.innerHTML = `
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <span class="font-medium">Read-Only Access:</span>
                <span>You have view-only permissions. Contact an administrator to request edit access.</span>
            </div>
        `;
        promptsContainer.parentElement.insertBefore(notice, promptsContainer);
    }
}

// Categories
async function loadCategories() {
    try {
        const response = await fetch('api/categories.php');
        categories = await response.json();
        
        // Populate category filter
        const filterSelect = document.getElementById('categoryFilter');
        filterSelect.innerHTML = '<option value="">All Categories</option>';
        categories.forEach(cat => {
            filterSelect.innerHTML += `<option value="${cat.id}">${cat.name}</option>`;
        });
        
        // Populate category select in prompt form
        const promptSelect = document.getElementById('promptCategory');
        promptSelect.innerHTML = '<option value="">Select a category</option>';
        categories.forEach(cat => {
            promptSelect.innerHTML += `<option value="${cat.id}">${cat.name}</option>`;
        });
    } catch (error) {
        console.error('Failed to load categories:', error);
    }
}

// Prompts
async function loadPrompts(search = '', category = '') {
    try {
        let url = 'api/prompts.php';
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (category) params.append('category', category);
        if (params.toString()) url += '?' + params.toString();
        
        const response = await fetch(url);
        prompts = await response.json();
        
        renderPrompts(prompts);
    } catch (error) {
        console.error('Failed to load prompts:', error);
    }
}

function renderPrompts(promptsToRender) {
    const container = document.getElementById('promptsList');
    const emptyState = document.getElementById('emptyState');
    
    if (promptsToRender.length === 0) {
        container.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
    }
    
    emptyState.classList.add('hidden');
    
    container.innerHTML = promptsToRender.map(prompt => {
        // Strip markdown for preview
        const previewText = prompt.content.replace(/[#*`\[\]()_~>-]/g, '').substring(0, 150);
        
        // Determine ownership indicator
        let ownershipBadge = '';
        if (prompt.team_id && currentUser?.team_id === prompt.team_id) {
            ownershipBadge = `
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-green-100 text-green-800 border border-green-300" title="Team Prompt">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
                    </svg>
                    Team
                </span>
            `;
        } else if (prompt.user_id === currentUser?.id) {
            ownershipBadge = `
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-purple-100 text-purple-800 border border-purple-300" title="Your Prompt">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                    </svg>
                    Yours
                </span>
            `;
        }
        
        // Determine visibility badge
        let visibilityBadge = '';
        const visibility = prompt.visibility || 'private';
        if (visibility === 'public') {
            visibilityBadge = `
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-blue-100 text-blue-800 border border-blue-300" title="Public - Anyone can view">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                    </svg>
                    Public
                </span>
            `;
            
            // Add warning if anonymous access is enabled
            if (prompt.allow_anonymous) {
                visibilityBadge += `
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-amber-100 text-amber-800 border border-amber-300" title="Accessible to anonymous visitors">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Anonymous
                    </span>
                `;
            }
        } else if (visibility === 'team') {
            visibilityBadge = `
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-300" title="Team only - Visible to team members">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
                    </svg>
                    Team
                </span>
            `;
        } else {
            visibilityBadge = `
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-800 border border-gray-300" title="Private - Only visible to you and shared users">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                    Private
                </span>
            `;
        }
        
        // Add share count badge if shared
        let shareBadge = '';
        if (prompt.share_count && prompt.share_count > 0) {
            shareBadge = `
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-indigo-100 text-indigo-800 border border-indigo-300" title="Shared with ${prompt.share_count} ${prompt.share_count === 1 ? 'user/team' : 'users/teams'}">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"/>
                    </svg>
                    Shared ${prompt.share_count}
                </span>
            `;
        }
        
        // Show access level if not owner
        let accessLevelBadge = '';
        if (prompt.user_id !== currentUser?.id && prompt.access_level) {
            const level = prompt.access_level;
            const levelColors = {
                'view': 'bg-cyan-100 text-cyan-800 border-cyan-300',
                'edit': 'bg-violet-100 text-violet-800 border-violet-300'
            };
            accessLevelBadge = `
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium ${levelColors[level] || 'bg-gray-100 text-gray-800 border-gray-300'}" title="You have ${level} access">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        ${level === 'edit' ? 
                            '<path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>' :
                            '<path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>'
                        }
                    </svg>
                    ${level.charAt(0).toUpperCase() + level.slice(1)}
                </span>
            `;
        }
        
        return `
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow relative group border-l-4 border-indigo-500">
            <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                <button class="copy-card-btn p-2 bg-white rounded-md shadow-md hover:bg-green-50 transition-colors" 
                    data-prompt-id="${prompt.id}"
                    title="Copy to clipboard">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                </button>
            </div>
            <div onclick="viewPrompt(${prompt.id})" class="cursor-pointer">
                <div class="flex justify-between items-start mb-3 pr-8">
                    <h3 class="text-lg font-semibold text-gray-900 truncate flex-1">${escapeHtml(prompt.title)}</h3>
                    <div class="flex items-center gap-1.5 ml-2 flex-shrink-0 flex-wrap justify-end">
                        ${ownershipBadge}
                        ${accessLevelBadge}
                        ${visibilityBadge}
                        ${shareBadge}
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700 border border-gray-300">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            v${prompt.current_version || 1}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                            ${escapeHtml(prompt.category_name || 'Uncategorized')}
                        </span>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4 line-clamp-3">${escapeHtml(previewText)}${prompt.content.length > 150 ? '...' : ''}</p>
                <div class="flex justify-between items-center text-xs text-gray-500">
                    <span class="flex items-center">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        ${formatDate(prompt.created_at)}
                    </span>
                    <span class="flex items-center">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        ${formatDate(prompt.updated_at)}
                    </span>
                </div>
            </div>
        </div>
    `}).join('');
    
    // Add event listeners to copy buttons
    document.querySelectorAll('.copy-card-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const promptId = this.getAttribute('data-prompt-id');
            copyPromptCard(promptId, this);
        });
    });
}

function filterPrompts() {
    const search = document.getElementById('searchInput').value;
    const category = document.getElementById('categoryFilter').value;
    loadPrompts(search, category);
}

async function viewPrompt(id) {
    try {
        const response = await fetch(`api/prompts.php?id=${id}`);
        const prompt = await response.json();
        
        if (prompt.error) {
            alert('Failed to load prompt details');
            return;
        }
        
        currentPromptId = id;
        
        // Store the raw content for copying
        window.currentPromptContent = prompt.content;
        
        // Populate header
        document.getElementById('detailTitle').textContent = prompt.title;
        document.getElementById('detailCategory').textContent = prompt.category_name || 'Uncategorized';
        
        // Current version
        const currentVersion = prompt.versions && prompt.versions.length > 0 ? prompt.versions[0].version_number : 1;
        document.getElementById('detailVersionNumber').textContent = `Version ${currentVersion}`;
        document.getElementById('detailDateTime').textContent = formatDate(prompt.updated_at);
        
        // Version count badge
        document.getElementById('versionCount').textContent = prompt.versions ? prompt.versions.length : 0;
        
        // Render markdown content
        const detailContent = document.getElementById('detailContent');
        if (typeof marked !== 'undefined') {
            detailContent.innerHTML = marked.parse(prompt.content);
        } else {
            detailContent.textContent = prompt.content;
        }
        
        // Reset copy button text
        document.getElementById('copyBtnText').textContent = 'Copy to Clipboard';
        
        // Control edit/delete button visibility based on permissions
        updatePromptActionButtons(prompt);
        
        // Load version history
        renderVersionHistory(prompt.versions);
        
        // Populate metadata tab
        populateMetadata(prompt);
        
        // Switch to content tab
        switchTab('content');
        
        // Open modal
        document.getElementById('detailModal').classList.remove('hidden');
    } catch (error) {
        console.error('Failed to load prompt:', error);
        alert('Failed to load prompt details');
    }
}

// Update visibility of edit/delete buttons based on user permissions and prompt ownership
function updatePromptActionButtons(prompt) {
    const editBtn = document.getElementById('editPromptBtn');
    const deleteBtn = document.getElementById('deletePromptBtn');
    
    // Determine if user can edit this prompt
    let canEdit = false;
    let canDelete = false;
    
    if (isRole('Admin')) {
        // Admin can edit and delete everything
        canEdit = true;
        canDelete = true;
    } else if (isRole('Editor')) {
        // Editor can edit team prompts or own prompts
        const isOwnPrompt = prompt.user_id === currentUser?.id;
        const isSameTeam = prompt.team_id && prompt.team_id === currentUser?.team_id;
        
        canEdit = hasPermission('edit_team_prompt') && (isOwnPrompt || isSameTeam);
        canDelete = hasPermission('delete_team_prompt') && (isOwnPrompt || isSameTeam);
    } else {
        // Viewer cannot edit or delete
        canEdit = false;
        canDelete = false;
    }
    
    // Show/hide edit button
    if (editBtn) {
        if (canEdit) {
            editBtn.classList.remove('hidden');
        } else {
            editBtn.classList.add('hidden');
        }
    }
    
    // Show/hide delete button
    if (deleteBtn) {
        if (canDelete) {
            deleteBtn.classList.remove('hidden');
        } else {
            deleteBtn.classList.add('hidden');
        }
    }
}

function populateMetadata(prompt) {
    // Creation info
    document.getElementById('metaCreatedAt').textContent = formatDate(prompt.created_at);
    const createdByVersion = prompt.versions && prompt.versions.length > 0 ? 
        prompt.versions[prompt.versions.length - 1].username : 'Unknown';
    document.getElementById('metaCreatedBy').textContent = createdByVersion;
    
    // Last modified info
    document.getElementById('metaUpdatedAt').textContent = formatDate(prompt.updated_at);
    const updatedByVersion = prompt.versions && prompt.versions.length > 0 ? 
        prompt.versions[0].username : 'Unknown';
    document.getElementById('metaUpdatedBy').textContent = updatedByVersion;
    
    // Content statistics
    const content = prompt.content || '';
    document.getElementById('metaCharCount').textContent = content.length.toLocaleString() + ' characters';
    
    const words = content.trim().split(/\s+/).filter(word => word.length > 0);
    document.getElementById('metaWordCount').textContent = words.length.toLocaleString() + ' words';
    
    const lines = content.split('\n');
    document.getElementById('metaLineCount').textContent = lines.length.toLocaleString() + ' lines';
    
    // Version info
    const currentVersion = prompt.versions && prompt.versions.length > 0 ? prompt.versions[0].version_number : 1;
    document.getElementById('metaCurrentVersion').textContent = 'v' + currentVersion;
    document.getElementById('metaTotalVersions').textContent = prompt.versions ? prompt.versions.length : 0;
}

function renderVersionHistory(versions) {
    const container = document.getElementById('versionsList');
    
    if (!versions || versions.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="mt-2 text-sm text-gray-500">No version history available</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = versions.map((version, index) => {
        const isLatest = index === 0;
        return `
        <div class="bg-white border ${isLatest ? 'border-indigo-300 shadow-md' : 'border-gray-200'} rounded-lg p-4 hover:shadow-lg transition-shadow relative">
            ${isLatest ? '<div class="absolute -top-2 -right-2"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-600 text-white shadow">Latest</span></div>' : ''}
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <h4 class="font-semibold text-gray-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            Version ${version.version_number}
                        </h4>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            ${escapeHtml(version.username)}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            ${formatDate(version.created_at)}
                        </span>
                    </div>
                </div>
                ${index < versions.length - 1 ? `
                    <button onclick="viewDiff(${version.version_number}, ${versions[index + 1].version_number})" 
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-white hover:bg-indigo-600 border border-indigo-600 rounded-md transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        Compare
                    </button>
                ` : ''}
            </div>
        </div>
    `}).join('');
}

async function viewDiff(newVersion, oldVersion) {
    try {
        const response = await fetch(`api/prompts.php?id=${currentPromptId}`);
        const prompt = await response.json();
        
        const newVersionContent = prompt.versions.find(v => v.version_number === newVersion)?.content || '';
        const oldVersionContent = prompt.versions.find(v => v.version_number === oldVersion)?.content || '';
        
        const diffHtml = generateDiff(oldVersionContent, newVersionContent);
        document.getElementById('diffContent').innerHTML = diffHtml;
        document.getElementById('diffModal').classList.remove('hidden');
    } catch (error) {
        console.error('Failed to generate diff:', error);
        alert('Failed to generate diff');
    }
}

function switchTab(tabName) {
    if (!tabName) {
        console.error('switchTab: tabName is undefined');
        return;
    }
    
    // Update tab buttons
    document.querySelectorAll('.detail-tab').forEach(tab => {
        if (tab.dataset.tab === tabName) {
            tab.classList.add('active', 'border-indigo-500', 'text-indigo-600', 'font-semibold');
            tab.classList.remove('border-transparent', 'text-gray-500', 'font-medium');
        } else {
            tab.classList.remove('active', 'border-indigo-500', 'text-indigo-600', 'font-semibold');
            tab.classList.add('border-transparent', 'text-gray-500', 'font-medium');
        }
    });
    
    // Hide all tab contents first
    const contentTab = document.getElementById('contentTab');
    const versionsTab = document.getElementById('versionsTab');
    const metadataTab = document.getElementById('metadataTab');
    
    if (contentTab) contentTab.classList.add('hidden');
    if (versionsTab) versionsTab.classList.add('hidden');
    if (metadataTab) metadataTab.classList.add('hidden');
    
    // Show the selected tab
    if (tabName === 'content' && contentTab) {
        contentTab.classList.remove('hidden');
    } else if (tabName === 'versions' && versionsTab) {
        versionsTab.classList.remove('hidden');
    } else if (tabName === 'metadata' && metadataTab) {
        metadataTab.classList.remove('hidden');
    }
}

// Prompt CRUD Operations
function openPromptModal(prompt = null) {
    const modal = document.getElementById('promptModal');
    const form = document.getElementById('promptForm');
    const title = document.getElementById('modalTitle');
    
    // Initialize EasyMDE if not already initialized
    if (!easyMDE) {
        easyMDE = new EasyMDE({
            element: document.getElementById('promptContent'),
            spellChecker: false,
            status: false,
            toolbar: [
                "bold", "italic", "heading", "|",
                "quote", "unordered-list", "ordered-list", "|",
                "link", "code", "|",
                "preview", "side-by-side", "fullscreen", "|",
                "guide"
            ],
            placeholder: "Enter your system prompt here... (Markdown supported)",
            autosave: {
                enabled: false
            }
        });
    }
    
    if (prompt) {
        title.textContent = 'Edit Prompt';
        document.getElementById('promptTitle').value = prompt.title;
        document.getElementById('promptCategory').value = prompt.category_id || '';
        easyMDE.value(prompt.content);
    } else {
        title.textContent = 'Add New Prompt';
        form.reset();
        easyMDE.value('');
    }
    
    modal.classList.remove('hidden');
}

function closePromptModal() {
    document.getElementById('promptModal').classList.add('hidden');
    document.getElementById('promptForm').reset();
    if (easyMDE) {
        easyMDE.value('');
    }
    editingPromptId = null;
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
    currentPromptId = null;
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

function closeDiffModal() {
    document.getElementById('diffModal').classList.add('hidden');
}

function openDeleteModal() {
    document.getElementById('deleteModal').classList.remove('hidden');
}

async function handleCopyPrompt() {
    try {
        const content = window.currentPromptContent || '';
        
        // Use modern clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(content);
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = content;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Failed to copy:', err);
                throw err;
            } finally {
                textArea.remove();
            }
        }
        
        // Show toast notification
        showToast('Copied to clipboard!');
        
        // Visual feedback
        const copyBtn = document.getElementById('copyBtnText');
        const originalText = copyBtn.textContent;
        copyBtn.textContent = 'Copied!';
        
        // Reset after 2 seconds
        setTimeout(() => {
            copyBtn.textContent = originalText;
        }, 2000);
        
    } catch (error) {
        console.error('Failed to copy to clipboard:', error);
        showToast('Failed to copy!', 'error');
    }
}

async function handleEditPrompt() {
    try {
        const response = await fetch(`api/prompts.php?id=${currentPromptId}`);
        const prompt = await response.json();
        
        editingPromptId = currentPromptId;
        closeDetailModal();
        openPromptModal(prompt);
    } catch (error) {
        console.error('Failed to load prompt for editing:', error);
        alert('Failed to load prompt for editing');
    }
}

async function handleSavePrompt(e) {
    e.preventDefault();
    
    const title = document.getElementById('promptTitle').value;
    const categoryId = document.getElementById('promptCategory').value;
    const content = easyMDE ? easyMDE.value() : document.getElementById('promptContent').value;
    
    // Validate content
    if (!content || content.trim() === '') {
        alert('Please enter prompt content');
        return;
    }
    
    const data = {
        title,
        category_id: categoryId || null,
        content
    };
    
    try {
        let response;
        
        if (editingPromptId) {
            // Update existing prompt
            data.id = editingPromptId;
            response = await fetch('api/prompts.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        } else {
            // Create new prompt
            response = await fetch('api/prompts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        }
        
        const result = await response.json();
        
        if (result.success) {
            closePromptModal();
            await loadPrompts();
        } else {
            alert(result.error || 'Failed to save prompt');
        }
    } catch (error) {
        console.error('Failed to save prompt:', error);
        alert('Failed to save prompt');
    }
}

async function confirmDelete() {
    if (!currentPromptId) return;
    
    try {
        const response = await fetch(`api/prompts.php?id=${currentPromptId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeDeleteModal();
            closeDetailModal();
            await loadPrompts();
        } else {
            alert(result.error || 'Failed to delete prompt');
        }
    } catch (error) {
        console.error('Failed to delete prompt:', error);
        alert('Failed to delete prompt');
    }
}

// Copy from card
async function copyPromptCard(id, button) {
    try {
        // Fetch full content
        const response = await fetch(`api/prompts.php?id=${id}`);
        const prompt = await response.json();
        const fullContent = prompt.content;
        
        // Use modern clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(fullContent);
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = fullContent;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Failed to copy:', err);
                throw err;
            } finally {
                textArea.remove();
            }
        }
        
        // Show toast notification
        showToast('Copied to clipboard!');
        
        // Visual feedback - change icon temporarily
        const originalHTML = button.innerHTML;
        button.innerHTML = `
            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        `;
        button.classList.add('bg-green-50');
        
        // Reset after 2 seconds
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('bg-green-50');
        }, 2000);
        
    } catch (error) {
        console.error('Failed to copy:', error);
        showToast('Failed to copy!', 'error');
        // Show error feedback
        button.classList.add('bg-red-50');
        setTimeout(() => {
            button.classList.remove('bg-red-50');
        }, 2000);
    }
}

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    
    toastMessage.textContent = message;
    
    // Update color based on type
    if (type === 'error') {
        toast.classList.remove('bg-green-600');
        toast.classList.add('bg-red-600');
    } else {
        toast.classList.remove('bg-red-600');
        toast.classList.add('bg-green-600');
    }
    
    // Show toast
    toast.classList.remove('hidden');
    
    // Hide after 3 seconds
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}

// Utility Functions
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Category Management
function openCategoryModal() {
    document.getElementById('categoryModal').classList.remove('hidden');
    loadCategoriesForManagement();
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.add('hidden');
    document.getElementById('newCategoryName').value = '';
}

async function loadCategoriesForManagement() {
    try {
        const response = await fetch('api/categories.php');
        const allCategories = await response.json();
        
        renderCategoriesManagement(allCategories);
    } catch (error) {
        console.error('Failed to load categories:', error);
        showToast('Failed to load categories', 'error');
    }
}

function renderCategoriesManagement(categories) {
    const container = document.getElementById('categoriesList');
    const countElement = document.getElementById('categoryCount');
    
    countElement.textContent = `${categories.length} total`;
    
    if (categories.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <p class="mt-2 text-sm text-gray-500">No categories found</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = categories.map(category => {
        const isSystem = category.is_system == 1;
        return `
            <div class="flex items-center justify-between p-3 bg-white border ${isSystem ? 'border-blue-200 bg-blue-50' : 'border-gray-200'} rounded-lg hover:shadow-md transition-shadow group">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="flex-shrink-0">
                        ${isSystem ? `
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        ` : `
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        `}
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="category-name-display text-sm font-medium text-gray-900 truncate block">${escapeHtml(category.name)}</span>
                        <input type="text" class="category-name-edit hidden w-full px-2 py-1 text-sm border border-indigo-300 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500" value="${escapeHtml(category.name)}">
                        ${isSystem ? '<span class="text-xs text-blue-600 font-medium">System Category</span>' : '<span class="text-xs text-gray-500">User Category</span>'}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    ${!isSystem ? `
                        <button onclick="editCategory(${category.id}, '${escapeHtml(category.name).replace(/'/g, "\\'")}', this)" 
                            class="edit-category-btn opacity-0 group-hover:opacity-100 p-2 text-indigo-600 hover:bg-indigo-50 rounded transition-all"
                            title="Edit category">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button onclick="saveCategory(${category.id}, this)" 
                            class="save-category-btn hidden p-2 text-green-600 hover:bg-green-50 rounded transition-all"
                            title="Save">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                        <button onclick="cancelEditCategory(this)" 
                            class="cancel-edit-btn hidden p-2 text-gray-600 hover:bg-gray-50 rounded transition-all"
                            title="Cancel">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <button onclick="deleteCategory(${category.id}, '${escapeHtml(category.name).replace(/'/g, "\\'")}', this)" 
                            class="delete-category-btn opacity-0 group-hover:opacity-100 p-2 text-red-600 hover:bg-red-50 rounded transition-all"
                            title="Delete category">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
}

async function handleAddCategory(e) {
    e.preventDefault();
    
    const nameInput = document.getElementById('newCategoryName');
    const name = nameInput.value.trim();
    
    if (!name) {
        showToast('Category name is required', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/categories.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });
        
        const result = await response.json();
        
        if (result.success) {
            nameInput.value = '';
            showToast('Category added successfully');
            await loadCategoriesForManagement();
            await loadCategories(); // Refresh categories in filters
        } else {
            showToast(result.error || 'Failed to add category', 'error');
        }
    } catch (error) {
        console.error('Failed to add category:', error);
        showToast('Failed to add category', 'error');
    }
}

function editCategory(id, name, button) {
    const row = button.closest('.flex');
    const displaySpan = row.querySelector('.category-name-display');
    const editInput = row.querySelector('.category-name-edit');
    const editBtn = row.querySelector('.edit-category-btn');
    const saveBtn = row.querySelector('.save-category-btn');
    const cancelBtn = row.querySelector('.cancel-edit-btn');
    const deleteBtn = row.querySelector('.delete-category-btn');
    
    // Hide display, show input
    displaySpan.classList.add('hidden');
    editInput.classList.remove('hidden');
    editInput.focus();
    editInput.select();
    
    // Hide edit/delete buttons, show save/cancel
    editBtn.classList.add('hidden');
    deleteBtn.classList.add('hidden');
    saveBtn.classList.remove('hidden');
    cancelBtn.classList.remove('hidden');
}

function cancelEditCategory(button) {
    const row = button.closest('.flex');
    const displaySpan = row.querySelector('.category-name-display');
    const editInput = row.querySelector('.category-name-edit');
    const editBtn = row.querySelector('.edit-category-btn');
    const saveBtn = row.querySelector('.save-category-btn');
    const cancelBtn = row.querySelector('.cancel-edit-btn');
    const deleteBtn = row.querySelector('.delete-category-btn');
    
    // Reset input value
    editInput.value = displaySpan.textContent.trim();
    
    // Show display, hide input
    displaySpan.classList.remove('hidden');
    editInput.classList.add('hidden');
    
    // Show edit/delete buttons, hide save/cancel
    editBtn.classList.remove('hidden');
    deleteBtn.classList.remove('hidden');
    saveBtn.classList.add('hidden');
    cancelBtn.classList.add('hidden');
}

async function saveCategory(id, button) {
    const row = button.closest('.flex');
    const editInput = row.querySelector('.category-name-edit');
    const name = editInput.value.trim();
    
    if (!name) {
        showToast('Category name cannot be empty', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/categories.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, name })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Category updated successfully');
            await loadCategoriesForManagement();
            await loadCategories(); // Refresh categories in filters
            await loadPrompts(); // Refresh prompts to show updated category names
        } else {
            showToast(result.error || 'Failed to update category', 'error');
            cancelEditCategory(button);
        }
    } catch (error) {
        console.error('Failed to update category:', error);
        showToast('Failed to update category', 'error');
        cancelEditCategory(button);
    }
}

async function deleteCategory(id, name, button) {
    if (!confirm(`Are you sure you want to delete the category "${name}"?\n\nPrompts in this category will not be deleted, but will become uncategorized.`)) {
        return;
    }
    
    try {
        const response = await fetch(`api/categories.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Category deleted successfully');
            await loadCategoriesForManagement();
            await loadCategories(); // Refresh categories in filters
            await loadPrompts(); // Refresh prompts
        } else {
            showToast(result.error || 'Failed to delete category', 'error');
        }
    } catch (error) {
        console.error('Failed to delete category:', error);
        showToast('Failed to delete category', 'error');
    }
}

// ===== ADMIN PANEL =====

// Open Admin Panel
async function openAdminPanel() {
    document.getElementById('adminPanelModal').classList.remove('hidden');
    switchAdminTab('users'); // Default to users tab
    await loadUsers();
}

// Close Admin Panel
function closeAdminPanel() {
    document.getElementById('adminPanelModal').classList.add('hidden');
}

// Switch Admin Tabs
function switchAdminTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.admin-tab').forEach(tab => {
        if (tab.dataset.tab === tabName) {
            tab.classList.add('border-red-500', 'text-red-600');
            tab.classList.remove('border-transparent', 'text-gray-500');
        } else {
            tab.classList.remove('border-red-500', 'text-red-600');
            tab.classList.add('border-transparent', 'text-gray-500');
        }
    });
    
    // Show/hide content
    document.querySelectorAll('.admin-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    const activeContent = document.getElementById(tabName + 'Tab');
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
    
    // Load data for active tab
    if (tabName === 'users') {
        loadUsers();
    } else if (tabName === 'teams') {
        loadTeams();
    }
}

// Load Users
async function loadUsers() {
    try {
        const response = await fetch('api/users.php');
        const data = await response.json();
        
        if (data.users) {
            renderUsers(data.users);
        }
    } catch (error) {
        console.error('Failed to load users:', error);
        showToast('Failed to load users', 'error');
    }
}

// Render Users Table
function renderUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No users found</td></tr>';
        return;
    }
    
    tbody.innerHTML = users.map(user => {
        const roleColors = {
            'Admin': 'bg-red-100 text-red-800',
            'Editor': 'bg-blue-100 text-blue-800',
            'Viewer': 'bg-gray-100 text-gray-800'
        };
        const roleClass = roleColors[user.role_name] || 'bg-gray-100 text-gray-800';
        const statusClass = user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
        const statusText = user.is_active ? 'Active' : 'Inactive';
        
        // Disable edit for current user
        const isSelf = user.id === currentUser?.id;
        const disableEdit = isSelf ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer';
        
        return `
            <tr>
                <td class="px-4 py-3">
                    <div class="text-sm font-medium text-gray-900">${escapeHtml(user.username)}</div>
                    <div class="text-sm text-gray-500">${escapeHtml(user.full_name || '')}</div>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs font-semibold rounded ${roleClass}">${user.role_name}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-900">${user.team_name || '<span class="text-gray-400">No team</span>'}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs font-semibold rounded ${statusClass}">${statusText}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-900">${user.prompt_count || 0}</td>
                <td class="px-4 py-3">
                    <button onclick="${isSelf ? '' : `editUser(${user.id})`}" 
                        class="text-indigo-600 ${disableEdit} text-sm font-medium"
                        ${isSelf ? 'disabled title="Cannot edit your own account"' : ''}>
                        Edit
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// Edit User
async function editUser(userId) {
    try {
        // Fetch user details
        const response = await fetch('api/users.php');
        const data = await response.json();
        const user = data.users.find(u => u.id === userId);
        
        if (!user) {
            showToast('User not found', 'error');
            return;
        }
        
        // Populate form
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserUsername').value = user.username;
        document.getElementById('editUserFullName').value = user.full_name || '';
        document.getElementById('editUserActive').checked = user.is_active;
        
        // Load roles
        await loadRolesForEdit();
        document.getElementById('editUserRole').value = user.role_id;
        
        // Load teams
        await loadTeamsForEdit();
        document.getElementById('editUserTeam').value = user.team_id || '';
        
        // Open modal
        document.getElementById('editUserModal').classList.remove('hidden');
    } catch (error) {
        console.error('Failed to load user:', error);
        showToast('Failed to load user details', 'error');
    }
}

// Load Roles for Edit
async function loadRolesForEdit() {
    try {
        const response = await fetch('api/users.php');
        const data = await response.json();
        
        // Get unique roles from users
        const roles = [...new Set(data.users.map(u => ({ id: u.role_id, name: u.role_name })))];
        const uniqueRoles = roles.filter((role, index, self) => 
            index === self.findIndex(r => r.id === role.id)
        );
        
        const select = document.getElementById('editUserRole');
        select.innerHTML = uniqueRoles.map(role => 
            `<option value="${role.id}">${role.name}</option>`
        ).join('');
    } catch (error) {
        console.error('Failed to load roles:', error);
    }
}

// Load Teams for Edit
async function loadTeamsForEdit() {
    try {
        const response = await fetch('api/teams.php');
        const teams = await response.json();
        
        const select = document.getElementById('editUserTeam');
        select.innerHTML = '<option value="">No Team</option>' + 
            teams.map(team => `<option value="${team.id}">${escapeHtml(team.name)}</option>`).join('');
    } catch (error) {
        console.error('Failed to load teams:', error);
    }
}

// Handle Edit User Submit
async function handleEditUser(e) {
    e.preventDefault();
    
    const userId = document.getElementById('editUserId').value;
    const roleId = document.getElementById('editUserRole').value;
    const teamId = document.getElementById('editUserTeam').value;
    const isActive = document.getElementById('editUserActive').checked ? 1 : 0;
    
    try {
        const response = await fetch('api/users.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: parseInt(userId),
                role_id: parseInt(roleId),
                team_id: teamId ? parseInt(teamId) : null,
                is_active: isActive
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('User updated successfully');
            closeEditUserModal();
            await loadUsers();
        } else {
            showToast(result.error || 'Failed to update user', 'error');
        }
    } catch (error) {
        console.error('Failed to update user:', error);
        showToast('Failed to update user', 'error');
    }
}

// Close Edit User Modal
function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}

// Load Teams
async function loadTeams() {
    try {
        const response = await fetch('api/teams.php');
        const teams = await response.json();
        renderTeams(teams);
    } catch (error) {
        console.error('Failed to load teams:', error);
        showToast('Failed to load teams', 'error');
    }
}

// Render Teams Grid
function renderTeams(teams) {
    const grid = document.getElementById('teamsGrid');
    
    if (teams.length === 0) {
        grid.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500">No teams found</div>';
        return;
    }
    
    grid.innerHTML = teams.map(team => `
        <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-3">
                <h5 class="text-lg font-semibold text-gray-900">${escapeHtml(team.name)}</h5>
                <button onclick="deleteTeam(${team.id}, '${escapeHtml(team.name)}')" 
                    class="text-red-600 hover:text-red-800 text-sm font-medium"
                    ${team.member_count > 0 ? 'disabled title="Cannot delete team with members" class="opacity-50 cursor-not-allowed"' : ''}>
                    Delete
                </button>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
                </svg>
                <span>${team.member_count || 0} member${team.member_count !== 1 ? 's' : ''}</span>
            </div>
        </div>
    `).join('');
}

// Open Add Team Modal
function openAddTeamModal() {
    document.getElementById('addTeamModal').classList.remove('hidden');
    document.getElementById('newTeamName').value = '';
}

// Close Add Team Modal
function closeAddTeamModal() {
    document.getElementById('addTeamModal').classList.add('hidden');
}

// Handle Add Team
async function handleAddTeam(e) {
    e.preventDefault();
    
    const name = document.getElementById('newTeamName').value.trim();
    
    if (!name) {
        showToast('Team name is required', 'error');
        return;
    }
    
    try {
        const response = await fetch('api/teams.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Team created successfully');
            closeAddTeamModal();
            await loadTeams();
        } else {
            showToast(result.error || 'Failed to create team', 'error');
        }
    } catch (error) {
        console.error('Failed to create team:', error);
        showToast('Failed to create team', 'error');
    }
}

// Delete Team
async function deleteTeam(teamId, teamName) {
    if (!confirm(`Are you sure you want to delete the team "${teamName}"?`)) {
        return;
    }
    
    try {
        const response = await fetch(`api/teams.php?id=${teamId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Team deleted successfully');
            await loadTeams();
        } else {
            showToast(result.error || 'Failed to delete team', 'error');
        }
    } catch (error) {
        console.error('Failed to delete team:', error);
        showToast('Failed to delete team', 'error');
    }
}
