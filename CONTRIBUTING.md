# Contributing to System Prompt Bank

We love your input! We want to make contributing to System Prompt Bank as easy and transparent as possible. Please read this document to understand our contribution process.

## Ways to Contribute

- **Report bugs**: Submit detailed bug reports with reproduction steps
- **Suggest features**: Share ideas for new features or improvements
- **Fix issues**: Submit pull requests to resolve open issues
- **Improve documentation**: Help enhance README, guides, or code comments
- **Write tests**: Add or improve test coverage
- **Share feedback**: Provide constructive feedback on existing features

## Code of Conduct

This project adheres to the Contributor Covenant [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to the maintainers.

## Getting Started

### Prerequisites

- PHP 7.4 or higher with SQLite extension
- A modern text editor or IDE
- Git installed on your machine
- XAMPP or similar local development environment

### Local Development Setup

1. **Fork the repository** on GitHub
2. **Clone your fork**:
   ```bash
   git clone https://github.com/YOUR_USERNAME/prompt_bank.git
   cd prompt_bank
   ```

3. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   # or for bug fixes:
   git checkout -b fix/issue-description
   ```

4. **Initialize the database** (if needed):
   - Open `http://localhost/prompt_bank/database/init_db.php` in your browser
   - Default credentials: `admin` / `admin123`

5. **Make your changes** following the guidelines below

### Development Guidelines

#### Code Style

- **PHP**: Follow PSR-12 coding standards
  - Use spaces (4 spaces per indent level)
  - Use meaningful variable and function names
  - Add docblock comments for functions
  - Keep functions focused and modular

- **JavaScript**: Follow ES6+ standards
  - Use `const`/`let` (avoid `var`)
  - Use arrow functions where appropriate
  - Add JSDoc comments for complex functions
  - Keep code readable and maintainable

- **SQL**: Keep queries efficient
  - Use prepared statements (always!)
  - Index frequently queried columns
  - Avoid N+1 query problems

#### Commit Messages

Write clear, descriptive commit messages:

```
feat: add real-time collaboration indicators
fix: resolve XSS vulnerability in prompt display
docs: update installation instructions
refactor: simplify authentication logic
test: add unit tests for sharing API
perf: optimize database queries
```

Format:
- Start with type: `feat`, `fix`, `docs`, `refactor`, `test`, `perf`, `chore`
- Use imperative mood ("add" not "added")
- Keep first line under 50 characters
- Provide detailed explanation in body if needed

#### Pull Request Process

1. **Before submitting**:
   - Ensure your code follows the style guide
   - Test your changes thoroughly
   - Update relevant documentation
   - Add tests if applicable

2. **Create your pull request**:
   - Use a clear, descriptive title
   - Link related issues using `closes #123`
   - Describe what changes you made and why
   - Include any relevant screenshots or examples

3. **PR Description Template**:
   ```markdown
   ## Description
   Brief description of changes

   ## Related Issues
   Closes #123

   ## Type of Change
   - [ ] Bug fix (non-breaking)
   - [ ] New feature (non-breaking)
   - [ ] Breaking change
   - [ ] Documentation update

   ## Testing
   Steps to test the changes

   ## Screenshots (if applicable)
   Add screenshots or GIFs

   ## Checklist
   - [ ] My code follows the style guidelines
   - [ ] I have tested my changes
   - [ ] I have updated documentation
   - [ ] No new warnings generated
   ```

### Testing

#### Running Tests

```bash
# Run all tests
php tests/test_*.php

# Run specific test file
php tests/test_prompts_api.php
```

#### Writing Tests

- Place tests in the `/tests/` directory
- Name test files: `test_[feature].php`
- Test API endpoints thoroughly
- Include both happy path and error cases
- Test with different user roles when applicable

### Security

- **Never commit sensitive data** (passwords, API keys, tokens)
- Use prepared statements for all database queries
- Validate and sanitize all user inputs
- Report security vulnerabilities privately (see [SECURITY.md](SECURITY.md))
- Follow OWASP best practices

### Database Migrations

When modifying the database schema:

1. Create a new migration file: `database/migrate_[feature].php`
2. Include rollback instructions in comments
3. Test migration thoroughly
4. Document changes in CHANGELOG.md

Example structure:
```php
<?php
// Migration: Add new column to prompts table
// Usage: Navigate to this file in browser or run via CLI

// Create connection
require_once __DIR__ . '/db.php';

try {
    // Migration code here
    $db->exec("ALTER TABLE prompts ADD COLUMN new_field TEXT");
    echo "Migration successful!";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
?>
```

## Reporting Bugs

When reporting bugs, please include:

- **Description**: What is the bug?
- **Reproduction steps**: How do you reproduce it?
- **Expected behavior**: What should happen?
- **Actual behavior**: What actually happens?
- **Environment**: PHP version, browser, OS
- **Screenshots/logs**: Any relevant error messages or logs

**Example bug report:**
```markdown
## Bug: Users unable to share prompts with teams

### Description
When attempting to share a prompt with a team, the share button remains disabled even with proper permissions.

### Reproduction
1. Login as Editor user
2. Create a new prompt
3. Click Share button
4. Try to add team from dropdown
5. Team selection remains empty

### Expected
Teams should appear in dropdown and selection should work

### Actual
Dropdown is empty or dropdown doesn't respond

### Environment
- PHP 7.4
- Chrome 96
- Windows 10
```

## Suggesting Features

When suggesting features, explain:

- **Use case**: Why do you need this feature?
- **Solution**: How should it work?
- **Alternatives**: Are there other ways to solve this?
- **Additional context**: Any mockups, examples, or links?

## Documentation

- Update README.md when adding features
- Document API changes in inline comments
- Add JSDoc comments for JavaScript functions
- Update CHANGELOG.md with all changes

## Review Process

1. Maintainers will review your PR within a few days
2. Address review feedback promptly
3. Push new commits to the same branch
4. Once approved, your PR will be merged
5. Celebrate your contribution! 🎉

## Questions?

- Check existing issues and PRs
- Review the [README.md](README.md) and documentation
- Open an issue for questions

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

Thank you for contributing to System Prompt Bank! 🚀
