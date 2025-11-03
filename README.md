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

### 🎨 Modern UI/UX
- **Single Page Application**: Smooth, responsive interface without page reloads
- **Beautiful Modals**: Gradient headers with backdrop blur effects
- **Toast Notifications**: Real-time feedback for all actions
- **Card-Based Layout**: Clean grid display with hover effects and version badges
- **Inline Editing**: Edit categories directly without separate forms
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile devices

### 🔐 Security & Performance
- **Session-based Authentication**: Secure user login system with PHP sessions
- **Password Hashing**: BCrypt password protection
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
├── index.php                 # Main entry (login and SPA container)
├── api/
│   ├── login.php            # Authentication endpoint
│   ├── logout.php           # Logout endpoint
│   ├── prompts.php          # CRUD endpoints for prompts
│   └── categories.php       # Category management endpoints
├── assets/
│   ├── css/
│   │   └── styles.css       # Custom CSS styles
│   └── js/
│       ├── app.js           # ES6 SPA logic
│       └── diff.js          # Diff comparison library
├── database/
│   ├── init_db.php          # Database initialization script
│   ├── db.php               # Database connection helper
│   └── prompts.db           # SQLite database (created after init)
└── templates/
    └── components/          # HTML fragments (for future use)
```

## Database Schema

### Tables

**users**
- `id` (INTEGER, Primary Key)
- `username` (TEXT, Unique)
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

## Future Enhancements

- Prompt sharing and collaboration
- AI-based category suggestions
- Dark/light mode themes
- Prompt restore (rollback to previous version)
- Bulk operations
- Import/export functionality
- Advanced search filters
- User role management
- API rate limiting
- Audit logging

## License

This project is open source and available for educational purposes.

## Support

For issues or questions, please check the code comments or modify as needed for your use case.
