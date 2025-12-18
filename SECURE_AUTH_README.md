# Secure Admin Authentication System

This system replaces hardcoded authentication with a proper form-based login system across all admin pages.

## Files Created

1. **auth-manager.js** - Core authentication logic and session management
2. **login-form.html** - Reusable login form component (standalone)
3. **admin-auth.css** - Consistent styling for all admin pages
4. **admin-auth-integration.js** - Easy integration script for existing pages
5. **auth/login.php** - Backend authentication handler
6. **SECURE_AUTH_README.md** - This documentation

## Quick Integration

### Method 1: Automatic Integration (Recommended)

Add this single line to the `<head>` section of each admin HTML file:

```html
<script src="admin-auth-integration.js"></script>
```

This will automatically:
- Load required CSS and JavaScript files
- Replace existing login sections with secure forms
- Add logout functionality
- Initialize authentication

### Method 2: Manual Integration

1. Add CSS and JavaScript files to your HTML:

```html
<head>
    <!-- Existing head content -->
    <link rel="stylesheet" href="admin-auth.css">
    <script src="auth-manager.js"></script>
</head>
```

2. Replace existing login sections with the secure login form from `login-form.html`

3. Update logout buttons to use `window.authManager.logout()`

## Backend Setup

The system now uses your production API endpoint:
- **API Endpoint**: `https://core.myacccuratebook.com/admin/login`
- **Request Format**: `{"email": "user@example.com", "password": "password"}`
- **Response Format**: `{"message": "Login successful", "token": "JWT_TOKEN"}`

### Authentication Credentials

Use your actual MyAccurateBook admin credentials:
- Email: Your admin email address
- Password: Your admin password

The system will authenticate against your production API and store the JWT token for subsequent requests.

## Security Features

- ✅ Form-based authentication with email/password
- ✅ Enhanced input validation and sanitization
- ✅ Rate limiting (5 attempts per 15 minutes)
- ✅ Session management with automatic expiration
- ✅ Cross-page authentication persistence
- ✅ Cryptographically secure token generation with HMAC
- ✅ Enhanced session expiration warnings with user-friendly UI
- ✅ Automatic logout on suspicious activity detection
- ✅ Session hijacking protection (hostname validation)
- ✅ Security event logging and monitoring
- ✅ Enhanced security headers (XSS, CSRF, etc.)
- ✅ Token validation endpoint for session verification
- ✅ Generic error messages to prevent user enumeration
- ✅ Input length limits and sanitization

## Session Management

- **Active Session**: 8 hours with activity
- **Persistent Session**: 24 hours across browser sessions
- **Expiration Warning**: 30 minutes before expiration
- **Auto-logout**: On session expiration or manual logout

## File Integration Status

Integration completed for all admin files:

- [x] admin_subscriptions.html ✅ **COMPLETED**
- [x] dashboard.html ✅ **COMPLETED**
- [x] churned_customers.html ✅ **COMPLETED**
- [x] never_paid_customers.html ✅ **COMPLETED**
- [x] payments.html ✅ **COMPLETED**
- [x] loginlogs.html ✅ **COMPLETED**
- [x] all_paying_customers.html ✅ **SKIPPED** (file does not exist)

All files now use the secure authentication system with:
- Form-based email/password login
- Session management and persistence
- Automatic token handling
- Consistent styling and user experience

## Next Steps

1. **Test the system**: Try the login form with test credentials
2. **Integrate files**: Add the integration script to each HTML file
3. **Update backend**: Replace hardcoded tokens with the new authentication system
4. **Configure production**: Update credentials and security settings for production use

## Troubleshooting

### Common Issues

1. **Login form not appearing**: Check that `admin-auth-integration.js` is loaded
2. **Styling issues**: Ensure `admin-auth.css` is loaded and not overridden
3. **Authentication fails**: Check PHP error logs and ensure `auth/login.php` is accessible
4. **Rate limiting**: Wait 15 minutes or clear `auth_attempts.json`

### Browser Console

Check the browser console for error messages. The system logs authentication attempts and errors.

## Production Deployment

Before deploying to production:

1. **Update credentials**: Replace test credentials in `auth/login.php`
2. **Secure backend**: Implement proper database authentication
3. **HTTPS only**: Ensure all authentication happens over HTTPS
4. **Update tokens**: Change the SECRET_KEY in the PHP file
5. **File permissions**: Secure file permissions on the server

## API Compatibility

The new system maintains compatibility with existing PHP backends by:
- Using the same token format where possible
- Preserving existing API endpoints
- Maintaining session data structure

## Support

This implementation follows the requirements and design specified in:
- `.kiro/specs/secure-admin-login/requirements.md`
- `.kiro/specs/secure-admin-login/design.md`
- `.kiro/specs/secure-admin-login/tasks.md`