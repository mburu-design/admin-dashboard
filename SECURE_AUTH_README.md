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

The system is configured for live deployment and uses your production API endpoint:
- **API Endpoint**: `https://core.myaccuratebook.com/admin/login`
- **Request Format**: `{"email": "user@example.com", "password": "password"}`
- **Response Format**: `{"message": "Login successful", "token": "JWT_TOKEN"}`
- **Deployment**: Configured for live server (localhost proxy disabled)

### Authentication Credentials

Use your actual MyAccurateBook admin credentials:
- Email: Your admin email address
- Password: Your admin password

The system will authenticate against your production API and store the JWT token for subsequent requests.

### Environment Configuration

The authentication system automatically detects the environment:

**Live Server (Production):**
- Direct API calls to `https://core.myaccuratebook.com`
- No CORS proxy needed (files deployed to live server)
- JWT token automatically included in all authenticated requests

**Localhost Development:**
- Uses `api-proxy.php` to handle CORS issues
- Automatically detected when running on localhost, 127.0.0.1, or local networks
- Start local server with: `php -S localhost:8000`
- Access via: `http://localhost:8000/`

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

### Current Status (Live Server Deployment)

The authentication system has been updated for live server deployment:
- ✅ Direct API calls to `https://core.myaccuratebook.com/admin/login`
- ✅ JWT token handling in both Authorization header and request body
- ✅ All PHP backend files updated to handle both token methods
- ✅ CORS issues resolved (no proxy needed on live server)

### Testing the System

**For Localhost Development:**
1. Start PHP development server: `php -S localhost:8000`
2. Open `http://localhost:8000/auth-test-simple.html`
3. The system will automatically use the CORS proxy for API calls

**For Live Server:**
1. Open `auth-test-simple.html` directly in your browser
2. The system will make direct API calls (no proxy needed)

**Test Components:**
- **Direct API Login**: Tests the core API connection
- **Auth Manager**: Tests the JavaScript authentication wrapper  
- **Payments API**: Tests authenticated requests to backend

### Common Issues

1. **Blank pages when fetching data**: 
   - **Cause**: Token not being passed correctly to PHP backends
   - **Solution**: Updated all PHP files to handle JWT tokens from Authorization header
   - **Test**: Use `auth-test-simple.html` to verify token handling

2. **Login form not appearing**: Check that `admin-auth-integration.js` is loaded

3. **Authentication fails**: 
   - Check browser console for detailed error messages
   - Verify credentials are correct for MyAccurateBook admin
   - Ensure API endpoint `https://core.myaccuratebook.com/admin/login` is accessible

4. **CORS errors on localhost**: 
   - **Cause**: Browser blocking cross-origin requests to external API
   - **Solution**: Use PHP development server (`php -S localhost:8000`) and access via `http://localhost:8000/`
   - **Check**: Verify `api-proxy.php` exists and is accessible

5. **"Unable to connect to authentication server" on localhost**:
   - **Cause**: System not detecting localhost properly or proxy not working
   - **Solution**: Ensure you're accessing via `http://localhost:8000/` not `file://`
   - **Debug**: Check browser console for proxy-related errors

6. **Rate limiting**: Wait 15 minutes or clear `auth_attempts.json`

### Browser Console

Check the browser console for error messages. The system logs:
- Authentication attempts and responses
- API request details and errors
- Token validation status
- Backend communication issues

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