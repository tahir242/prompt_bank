## Plan: User Registration Feature

This plan implements user registration functionality for the System Prompt Bank application, following TDD principles. The feature will allow new users to create accounts with username/full name/password, validate inputs, prevent duplicate usernames, implement rate limiting, and securely hash passwords using existing patterns. Users will be redirected to login after successful registration.

**Phases: 5**

### 1. **Phase 1: Backend API Endpoint for User Registration**

- **Objective:** Create a secure backend API endpoint that handles user registration with validation, rate limiting, and error handling
- **Files/Functions to Modify/Create:**
  - `api/register.php` (new file)
  - `database/init_db.php` (add full_name column to users table)
- **Tests to Write:**
  - Test registration with valid username, full name, and password
  - Test registration with empty username
  - Test registration with empty full name
  - Test registration with empty password
  - Test registration with short password (< 6 characters)
  - Test registration with duplicate username
  - Test that password is properly hashed in database
  - Test rate limiting (multiple registrations from same IP)
  - Test proper JSON response structure
- **Steps:**
  1. Write PHPUnit tests for the registration endpoint covering all validation scenarios
  2. Run tests to verify they fail (red)
  3. Update `database/init_db.php` to add `full_name` column to users table
  4. Create `api/register.php` with input validation (username/full_name/password required, password min length 6)
  5. Implement simple rate limiting (track registration attempts by IP, max 3 per hour)
  6. Implement duplicate username check
  7. Implement password hashing using `password_hash()` matching existing login.php pattern
  8. Insert new user into database with proper error handling
  9. Return appropriate JSON responses (success/error)
  10. Run tests to verify they pass (green)
  11. Verify code follows existing patterns in `api/login.php`

### 2. **Phase 2: Frontend Registration Form UI**

- **Objective:** Create a registration form modal UI matching existing login screen design patterns with username, full name, and password fields
- **Files/Functions to Modify/Create:**
  - `index.php` - Add registration modal HTML
  - `assets/css/styles.css` - Add any custom styles if needed
- **Tests to Write:**
  - Test that registration link appears on login screen
  - Test that registration modal opens when link is clicked
  - Test that registration modal has all required form fields (username, full name, password, confirm password)
  - Test that cancel button closes the modal
  - Test that form validation displays errors properly
- **Steps:**
  1. Write JavaScript tests for registration modal functionality
  2. Run tests to verify they fail (red)
  3. Add registration modal HTML to `index.php` after login screen section
  4. Add "Create Account" link to login screen
  5. Style registration form to match existing TailwindCSS patterns from login form
  6. Add form fields: username, full name, password, confirm password
  7. Add error message display area
  8. Add success message display area
  9. Add modal open/close functionality
  10. Run tests to verify they pass (green)
  11. Verify responsive design on mobile/tablet

### 3. **Phase 3: Frontend Registration Logic**

- **Objective:** Implement client-side validation and API integration for user registration with redirect to login on success
- **Files/Functions to Modify/Create:**
  - `assets/js/app.js` - Add handleRegister function and event listeners
- **Tests to Write:**
  - Test client-side password confirmation validation
  - Test client-side password minimum length validation
  - Test full name field validation
  - Test successful registration shows success message and redirects to login
  - Test error messages display correctly
  - Test registration API call with proper headers
  - Test network error handling
- **Steps:**
  1. Write JavaScript unit tests for registration form validation and submission
  2. Run tests to verify they fail (red)
  3. Add event listener for registration form in setupEventListeners()
  4. Create handleRegister() function following existing handleLogin() pattern
  5. Implement client-side validation (all fields required, password confirmation match, min length)
  6. Make fetch POST request to `api/register.php` with username, full_name, and password
  7. Handle success response (show success message, close modal, show login form)
  8. Handle error responses (display appropriate error messages including rate limit)
  9. Implement network error handling
  10. Run tests to verify they pass (green)
  11. Test complete registration flow manually

### 4. **Phase 4: Database Schema Validation**
- **Objective:** Ensure database schema supports registration and add any missing constraints or indexes
- **Files/Functions to Modify/Create:**
  - `database/init_db.php` - Review and update if needed
- **Tests to Write:**
  - Test that users table has UNIQUE constraint on username
  - Test that duplicate username insertion fails at database level
  - Test that created_at timestamp is set automatically
  - Test that user records can be queried after insertion
- **Steps:**
  1. Write database schema tests
  2. Run tests to verify current state (may pass if schema is correct)
  3. Review `database/init_db.php` users table definition
  4. Verify UNIQUE constraint exists on username (already present)
  5. Verify created_at default timestamp (already present)
  6. Add database index on username for performance if not present
  7. Run tests to verify they pass (green)
  8. Test database operations manually

### 5. **Phase 5: Integration Testing and Security Review**
- **Objective:** Perform end-to-end integration testing and security validation of the complete registration flow
- **Files/Functions to Modify/Create:**
  - All registration-related files for testing
  - Update documentation if needed
- **Tests to Write:**
  - End-to-end test: register new user and immediately login
  - Test SQL injection prevention in registration
  - Test XSS prevention in error messages
  - Test session handling after registration
  - Test rate limiting or spam prevention (if implemented)
- **Steps:**
  1. Write integration tests covering complete registration->login flow
  2. Run tests to verify they fail (red)
  3. Fix any issues discovered during integration testing
  4. Verify password hashing uses PASSWORD_DEFAULT
  5. Verify input sanitization and validation throughout
  6. Verify error messages don't leak sensitive information
  7. Test registration with various edge cases (special characters, long usernames)
  8. Run all tests to verify they pass (green)
  9. Perform manual security testing
  10. Update README.md with registration feature documentation

**Implementation Decisions (Approved):**
1. ✅ Use username only (no email validation required)
2. ✅ Implement rate limiting for registration to prevent spam
3. ✅ Redirect users to login page after successful registration
4. ✅ No email verification needed
5. ✅ Add full name field to user profile
