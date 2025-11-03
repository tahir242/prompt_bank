# Quick Start Guide - System Prompt Bank

## ✅ Installation Complete!

Your System Prompt Bank application has been successfully set up.

## 🚀 Getting Started

### Step 1: Access the Application

Open your web browser and navigate to:
```
http://localhost/prompt_bank/
```

### Step 2: Login

Use these default credentials:
- **Username**: `admin`
- **Password**: `admin123`

### Step 3: Start Using the App

Once logged in, you can:

1. **Create Your First Prompt**
   - Click the "+ Add Prompt" button
   - Fill in the title, select a category, and add your prompt content
   - Click "Save"

2. **Browse Existing Prompts**
   - View all your prompts in the grid layout
   - Click any prompt card to see full details

3. **Search and Filter**
   - Use the search box to find specific prompts
   - Filter by category using the dropdown

4. **Edit Prompts**
   - Open any prompt and click "Edit"
   - Make changes and save
   - Version history is automatically maintained

5. **View Version History**
   - Open a prompt and switch to "Version History" tab
   - Click "View Diff" to see changes between versions

## 📁 File Structure

```
prompt_bank/
├── index.php              # Main application file
├── api/                   # Backend API endpoints
│   ├── login.php
│   ├── logout.php
│   ├── prompts.php
│   └── categories.php
├── assets/
│   ├── css/styles.css     # Custom styles
│   └── js/
│       ├── app.js         # Main JavaScript
│       └── diff.js        # Diff comparison
├── database/
│   ├── prompts.db         # SQLite database
│   ├── db.php             # Database helper
│   └── init_db.php        # Initialization script
└── README.md              # Full documentation
```

## 🗃️ Database

The SQLite database has been initialized with:
- ✅ Users table (with admin user)
- ✅ Categories table (with 5 default categories)
- ✅ Prompts table
- ✅ Prompt versions table

Database location: `c:\xampp\htdocs\prompt_bank\database\prompts.db`

## 🔒 Security

- Passwords are hashed using PHP's bcrypt
- Session-based authentication
- SQL injection protection
- Database file is protected via .htaccess

## 🎨 Features

✨ **Implemented Features:**
- User authentication (login/logout)
- CRUD operations for prompts
- Version control for all prompt changes
- Visual diff comparison (side-by-side)
- Category management
- Search and filter functionality
- Responsive mobile design
- Soft delete (archive)
- Real-time updates (SPA)

## 🔧 Customization

### Change Admin Password

1. Login with default credentials
2. Manually update in database or add user management UI
3. Use PHP's `password_hash()` for new passwords

### Add New Categories

Categories can be added through the API endpoint:
```php
POST /api/categories.php
{
    "name": "Your Category Name"
}
```

### Modify Styles

Edit `assets/css/styles.css` to customize the appearance.

## 📝 Next Steps

1. **Change the default password** for security
2. **Create your first prompt** to test the system
3. **Add custom categories** for your use case
4. **Test the version control** by editing a prompt
5. **Explore the search and filter** features

## 🐛 Troubleshooting

**Can't login?**
- Ensure database was initialized (check for `prompts.db` in database folder)
- Use correct credentials: admin / admin123

**Database errors?**
- Check that PHP SQLite extension is enabled
- Verify file permissions on the database folder

**Styling issues?**
- Clear browser cache
- Check that TailwindCSS CDN is accessible

**JavaScript errors?**
- Open browser console (F12) to see errors
- Ensure both `diff.js` and `app.js` are loading

## 📚 Documentation

For detailed documentation, see `README.md` in the project root.

## 🎉 Enjoy!

Your System Prompt Bank is ready to use. Start organizing and versioning your prompts!
