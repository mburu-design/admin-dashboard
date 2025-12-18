# Development Setup for MyAccurateBook Admin

## CORS Issue Solution

When running the admin pages locally, you'll encounter CORS (Cross-Origin Resource Sharing) errors because browsers block requests from `localhost` to external APIs like `https://core.myacccuratebook.com`.

## Quick Solutions

### Option 1: Use Local PHP Server (Recommended)
1. Make sure you have PHP installed
2. Open terminal/command prompt in your project folder
3. Run: `php -S localhost:8000`
4. Open your browser to: `http://localhost:8000/payments.html`
5. The system will automatically use the `api-proxy.php` to bypass CORS

### Option 2: Use Live Server with CORS Extension
1. Install a browser extension like "CORS Unblock" or "Disable CORS"
2. Enable the extension
3. Open your HTML files normally
4. **⚠️ Remember to disable the extension after development**

### Option 3: Deploy to Same Domain
1. Upload your files to the same domain as your API
2. Access them via `https://yourdomain.com/admin/payments.html`
3. No CORS issues when on the same domain

## Testing the Setup

1. Open `test-auth.html` in your browser
2. Try logging in with your MyAccurateBook credentials
3. Check the browser console (F12) for any error messages
4. If successful, you should see the JWT token displayed

## Files Created for CORS Solution

- `api-proxy.php` - Local proxy server to bypass CORS during development
- `DEVELOPMENT_SETUP.md` - This instruction file

## Production Deployment

When deploying to production:
1. Upload all files to your web server
2. The system will automatically detect it's not localhost
3. Direct API calls will be made (no proxy needed)
4. Ensure your domain is whitelisted in the API's CORS settings

## Troubleshooting

### "Failed to fetch" Error
- This indicates a CORS issue
- Follow Option 1 above (PHP server)
- Check browser console for detailed error messages

### "API request failed with status XXX"
- This means the API is responding but with an error
- Check your credentials
- Verify the API endpoint is correct
- Check network tab in browser dev tools

### Authentication Works but Data Fetching Fails
- Make sure all your PHP backend files exist
- Check that the JWT token is being sent in requests
- Verify your backend APIs accept Bearer token authentication