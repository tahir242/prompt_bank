# System Prompt Bank

A lightweight PHP-based mobile-responsive web application for managing, categorizing, and versioning reusable system prompts.

## Features

✨ **Core Functionality**
- User authentication with secure session management
- Single Page Application (SPA) architecture
- CRUD operations for system prompts
- Version control with visual diff comparison
- Category management (system and user-defined)
- Search and filter capabilities
- Soft delete (archive) functionality
- Responsive design for mobile, tablet, and desktop

## Technology Stack

- **Frontend**: TailwindCSS, ES6 JavaScript
- **Backend**: PHP 7.4+ / 8.x
- **Database**: SQLite
- **Architecture**: Single Page Application (SPA)

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
