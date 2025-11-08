# 📝 System Prompt Bank

A modern, feature-rich web application for managing and organizing system prompts with version control, markdown support, and intuitive category management.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![SQLite](https://img.shields.io/badge/SQLite-3-blue.svg)

## ✨ Features

### 🎯 Core Functionality
- **Prompt Management**: Create, read, update, and delete system prompts with ease
- **Version Control**: Automatic versioning with complete history tracking for every change
- **Diff Comparison**: Visual side-by-side comparison between any two versions
- **Category Organization**: Organize prompts with both system and user-defined categories
- **Rich Text Editor**: Built-in markdown editor (EasyMDE) with live preview
- **Search & Filter**: Quick search across prompts and filter by categories
- **Copy to Clipboard**: One-click copying from cards or detail view with visual feedback

### 🤝 Sharing & Collaboration (NEW!)
- **Granular Sharing**: Share prompts with specific users or teams with view/edit permissions
- **Visibility Control**: Three-level access (Private, Team, Public) with configurable settings
- **Access Requests**: Users can request access to prompts with approval workflow
- **Real-time Collaboration**: See who's currently editing with live presence indicators
- **Anonymous Sharing**: Optional public sharing with warning for sensitive content
- **Smart Notifications**: Toast notifications with sound for access requests (configurable)
- **Team Permissions**: Team-wide visibility with customizable access levels
- **Share Management**: Add/remove shares, track share counts with visual badges

### 🎨 Modern UI/UX
- **Single Page Application**: Smooth, responsive interface without page reloads
- **Beautiful Modals**: Gradient headers with backdrop blur effects
- **Toast Notifications**: Real-time feedback for all actions
- **Card-Based Layout**: Clean grid display with hover effects and version badges
- **Inline Editing**: Edit categories directly without separate forms
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile devices

### 🔐 Security & Performance
- **User Registration**: Self-service account creation with validation
- **Session-based Authentication**: Secure user login system with PHP sessions
- **Password Hashing**: BCrypt password protection
- **Rate Limiting**: Protection against spam registrations (3 per IP per hour)
- **SQL Injection Prevention**: Prepared statements throughout the codebase
- **XSS Protection**: HTML escaping for all user-generated content
- **System Category Protection**: Prevents accidental modification of core categories

### 📊 Advanced Features
- **Metadata View**: Comprehensive statistics including character, word, and line counts
- **Version Badges**: Visual indicators showing current version on prompt cards
- **User Tracking**: Track who created and last modified each prompt
- **Helpful Empty States**: Clear messages when no content exists
- **Graceful Error Handling**: User-friendly error messages and recovery

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher with SQLite extension
- Web server (Apache, Nginx) or PHP built-in server
- Modern web browser (Chrome, Firefox, Safari, Edge)

## Technology Stack

### Backend
- **PHP 7.4+/8.x**: Server-side logic
- **SQLite 3**: Lightweight, zero-configuration database
- **RESTful API**: Clean, organized API architecture

### Frontend
- **Vanilla JavaScript (ES6)**: No heavy framework dependencies
- **TailwindCSS (CDN)**: Utility-first CSS framework
- **EasyMDE v2.x**: Markdown editor with toolbar and preview
- **marked.js v9.x**: Fast markdown-to-HTML parser

### Architecture
- **Single Page Application (SPA)**: Dynamic content loading without page refreshes
- **RESTful API**: Separate API endpoints for clean architecture
- **Session Management**: Secure authentication handling

## Installation

### Prerequisites

- XAMPP (or similar PHP environment)
- PHP 7.4 or higher
- SQLite support enabled in PHP

### Setup Instructions

1. **Clone or extract the project** to your XAMPP htdocs folder:
   ```
   c:\xampp\htdocs\prompt_bank\
   ```

2. **Initialize the database**:
   - Open your browser and navigate to:
     ```
     http://localhost/prompt_bank/database/init_db.php
     ```
   - This will create the SQLite database and populate it with default data
   - Default user credentials:
     - Username: `admin`
     - Password: `admin123`

3. **Access the application**:
   ```
   http://localhost/prompt_bank/
   ```

4. **Login** with the default credentials and start managing your prompts!

## Project Structure

```
prompt_bank/
│
├── index.php                      # Main entry (login and SPA container)
├── config.php                     # Configuration settings
├── api/
│   ├── login.php                  # Authentication endpoint
│   ├── logout.php                 # Logout endpoint
│   ├── register.php               # User registration endpoint
│   ├── prompts.php                # CRUD endpoints for prompts (enhanced with visibility)
│   ├── categories.php             # Category management endpoints
│   ├── shares.php                 # NEW: Share management API
│   ├── access_requests.php        # NEW: Access request workflow API
│   ├── collaborators.php          # NEW: Real-time presence tracking API
│   ├── public_prompts.php         # NEW: Anonymous public access API
│   ├── users.php                  # User lookup API
│   └── teams.php                  # Team lookup API
├── assets/
│   ├── css/
│   │   └── styles.css             # Custom CSS styles + animations
│   └── js/
│       ├── app.js                 # ES6 SPA logic (1700+ lines)
│       ├── sharing.js             # NEW: Sharing UI module (800+ lines)
│       ├── collaborative.js       # NEW: Collaboration module (400+ lines)
│       └── diff.js                # Diff comparison library
├── database/
│   ├── init_db.php                # Database initialization script
│   ├── db.php                     # Database connection + sharing helpers
│   ├── migrate_add_sharing.php    # NEW: Sharing feature migration
│   ├── validate_schema.php        # Schema validation tool
│   └── prompts.db                 # SQLite database (created after init)
├── plans/                         # Feature implementation documentation
└── templates/
    └── components/                # HTML fragments (for future use)
```

## Database Schema

### Tables

**users**
- `id` (INTEGER, Primary Key)
- `username` (TEXT, Unique)
- `full_name` (TEXT)
- `password` (TEXT, Hashed)
- `created_at` (DATETIME)

**categories**
- `id` (INTEGER, Primary Key)
- `name` (TEXT, Unique)
- `is_system` (BOOLEAN)
- `created_at` (DATETIME)

**prompts**
- `id` (INTEGER, Primary Key)
- `title` (TEXT)
- `content` (TEXT)
- `category_id` (INTEGER, Foreign Key)
- `user_id` (INTEGER, Foreign Key)
- `team_id` (INTEGER, Foreign Key)
- `visibility` (TEXT: private/team/public) - NEW
- `allow_anonymous` (BOOLEAN) - NEW
- `team_access_level` (TEXT: view/edit) - NEW
- `created_at` (DATETIME)
- `updated_at` (DATETIME)
- `is_archived` (BOOLEAN)

**prompt_versions**
- `id` (INTEGER, Primary Key)
- `prompt_id` (INTEGER, Foreign Key)
- `version_number` (INTEGER)
- `content` (TEXT)
- `user_id` (INTEGER, Foreign Key)
- `created_at` (DATETIME)

**prompt_shares** (NEW)
- `id` (INTEGER, Primary Key)
- `prompt_id` (INTEGER, Foreign Key)
- `shared_with_user_id` (INTEGER, Foreign Key, nullable)
- `shared_with_team_id` (INTEGER, Foreign Key, nullable)
- `access_level` (TEXT: view/edit)
- `shared_by_user_id` (INTEGER, Foreign Key)
- `created_at` (DATETIME)

**access_requests** (NEW)
- `id` (INTEGER, Primary Key)
- `prompt_id` (INTEGER, Foreign Key)
- `requester_user_id` (INTEGER, Foreign Key)
- `status` (TEXT: pending/approved/denied)
- `message` (TEXT)
- `reviewed_by_user_id` (INTEGER, Foreign Key, nullable)
- `reviewed_at` (DATETIME, nullable)
- `created_at` (DATETIME)

**prompt_collaborators** (NEW)
- `id` (INTEGER, Primary Key)
- `prompt_id` (INTEGER, Foreign Key)
- `user_id` (INTEGER, Foreign Key)
- `last_activity` (DATETIME)
- `created_at` (DATETIME)

## Usage Guide

### Managing Prompts

1. **Add a New Prompt**
   - Click the "+ Add Prompt" button
   - Fill in the title, select a category, and enter your prompt text
   - Click "Save" to create the prompt

2. **View Prompt Details**
   - Click on any prompt card to view full details
   - Switch between "Content" and "Version History" tabs
   - View version comparisons using the "View Diff" button

3. **Edit a Prompt**
   - Open the prompt details
   - Click "Edit" button
   - Make your changes and save
   - A new version will be automatically created

4. **Delete a Prompt**
   - Open the prompt details
   - Click "Delete" button
   - Confirm the deletion
   - The prompt will be archived (soft delete)

### Search and Filter

- Use the search box to find prompts by title or content
- Filter by category using the dropdown menu
- Results update in real-time

### Version History

- Each edit creates a new version
- View all versions in the "Version History" tab
- Compare versions side-by-side using the diff viewer
- Changes are highlighted in green (additions) and red (deletions)

## Security Features

- Password hashing using PHP's `password_hash()`
- Session-based authentication
- SQL injection prevention with prepared statements
- Input validation and sanitization
- CSRF protection ready

## Default Categories

The system comes with these pre-defined categories:
- System Setup
- Debugging
- Creative Writing
- Code Review
- Documentation

You can add custom categories as needed (feature can be added via the categories API endpoint).

## API Endpoints

### Authentication
- `POST /api/login.php` - User login
- `POST /api/register.php` - User registration (new users)
- `GET /api/logout.php` - User logout

### Prompts
- `GET /api/prompts.php` - List all prompts
- `GET /api/prompts.php?id={id}` - Get single prompt with versions
- `POST /api/prompts.php` - Create new prompt
- `PUT /api/prompts.php` - Update existing prompt
- `DELETE /api/prompts.php?id={id}` - Delete (archive) prompt

### Categories
- `GET /api/categories.php` - List all categories
- `POST /api/categories.php` - Create new category
- `DELETE /api/categories.php?id={id}` - Delete category

### Sharing & Collaboration (NEW)

**Shares**
- `GET /api/shares.php?prompt_id={id}` - List shares for a prompt
- `POST /api/shares.php` - Create new share (user or team)
- `DELETE /api/shares.php?id={id}` - Remove share

**Access Requests**
- `GET /api/access_requests.php` - List requests (incoming for owners, outgoing for requesters)
- `GET /api/access_requests.php?prompt_id={id}` - List requests for specific prompt
- `POST /api/access_requests.php` - Request access to a prompt
- `PUT /api/access_requests.php` - Approve/deny access request

**Collaborators**
- `GET /api/collaborators.php?prompt_id={id}` - List active editors
- `POST /api/collaborators.php` - Register/update presence (heartbeat)
- `DELETE /api/collaborators.php?prompt_id={id}` - Remove presence

**Public Access**
- `GET /api/public_prompts.php` - Anonymous access to public prompts (no auth required)

## Troubleshooting

**Database not found error:**
- Make sure you've run the initialization script: `http://localhost/prompt_bank/database/init_db.php`

**Login not working:**
- Verify the database was initialized properly
- Check that PHP sessions are enabled
- Try using the default credentials: admin / admin123

**Changes not saving:**
- Check browser console for JavaScript errors
- Verify API endpoints are accessible
- Check file permissions on the database file

**Diff view not working:**
- Ensure `diff.js` is loaded before `app.js`
- Check browser console for JavaScript errors

## Sharing & Collaboration Features

### Quick Start Guide

1. **Share a Prompt**
   - Open any prompt you own
   - Click the "Share" button
   - Select visibility level (Private/Team/Public)
   - Add specific users or teams with view/edit permissions
   - Enable anonymous access for public prompts (optional)

2. **Request Access**
   - View any inaccessible prompt
   - Click "Request Access"
   - Add a message explaining why you need access
   - Wait for owner approval

3. **Collaborate in Real-Time**
   - Open any prompt with edit access
   - See who's currently editing with live avatars
   - Get warnings before editing if others are active
   - Your presence is automatically tracked

4. **Manage Notifications**
   - Click the bell icon to view pending access requests
   - Toast notifications appear for new requests
   - Configure notification sound in settings (gear icon)
   - Approve or deny requests with one click

### Implementation Details

**7 Phases Completed:**
- Phase 1: Database schema with 3 new tables
- Phase 2: Backend sharing API (66/66 tests passing)
- Phase 3: Enhanced prompts API with visibility filtering
- Phase 4: Real-time collaborative editing tracking
- Phase 5: Frontend sharing UI with modals and badges
- Phase 6: Collaborative editing indicators and warnings
- Phase 7: Smart notifications with sound and settings

**Code Statistics:**
- 3000+ lines of code added
- 15+ files modified/created
- 5 new API endpoints
- 800+ lines in sharing.js
- 400+ lines in collaborative.js

For complete documentation, see `FINAL-DOCUMENTATION.md`

## Future Enhancements

- WebSocket integration for instant updates
- Email notifications for access requests
- Desktop notifications via Browser API
- Share templates and bulk operations
- Time-limited shares with expiration
- Activity feed and audit logging
- AI-based category suggestions
- Dark/light mode themes
- Prompt restore (rollback to previous version)
- Import/export functionality
- Advanced search filters

## License

This project is open source and available for educational purposes.

## Support

For issues or questions, please check the code comments or modify as needed for your use case.
