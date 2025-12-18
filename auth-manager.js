/**
 * Secure Admin Authentication Manager
 * Handles login, logout, token management, and session handling
 */
class AuthManager {
    constructor() {
        this.tokenKey = 'admin_auth_token';
        this.sessionKey = 'admin_session_data';
        this.maxSessionHours = 8;
        this.maxPersistentHours = 24;
        
        // API configuration
        this.apiBaseUrl = 'https://core.myacccuratebook.com';
        
        // Check for saved override
        const savedMode = localStorage.getItem('FORCE_LIVE_MODE');
        if (savedMode !== null) {
            window.FORCE_LIVE_MODE = savedMode === 'true';
        }
        
        // Auto-detect if running on localhost for development
        // Can be manually overridden by setting window.FORCE_LIVE_MODE = true
        this.useLocalProxy = window.FORCE_LIVE_MODE ? false : this.isLocalhost();
        
        // Log configuration for debugging
        console.log('AuthManager Configuration:', {
            hostname: window.location.hostname,
            useLocalProxy: this.useLocalProxy,
            forceLiveMode: window.FORCE_LIVE_MODE || false
        });
    }

    /**
     * Authenticate user with email and password
     * @param {string} email - Administrator email
     * @param {string} password - Administrator password
     * @returns {Promise<Object>} Authentication result
     */
    async login(email, password) {
        try {
            // Validate input
            if (!this.validateEmail(email)) {
                throw new Error('Please enter a valid email address');
            }
            if (!password || password.trim().length === 0) {
                throw new Error('Password is required');
            }

            // Make authentication request to backend
            console.log('Attempting login to API...');
            
            const apiUrl = this.useLocalProxy 
                ? 'api-proxy.php?endpoint=admin/login'  // Use local PHP proxy for development
                : `${this.apiBaseUrl}/admin/login`;     // Direct API call for production
                
            console.log('Using API URL:', apiUrl);
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email.trim(),
                    password: password
                })
            });

            console.log('API Response status:', response.status);
            console.log('API Response headers:', response.headers);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.log('API Error response:', errorText);
                throw new Error(`API request failed with status ${response.status}: ${errorText}`);
            }

            const data = await response.json();
            console.log('API Response data:', data);

            if (response.ok && data.token) {
                // Store authentication data
                const sessionData = {
                    token: data.token,
                    user: { email: email, message: data.message },
                    expiresAt: Date.now() + (this.maxSessionHours * 60 * 60 * 1000),
                    lastActivity: Date.now(),
                    createdAt: Date.now(),
                    hostname: window.location.hostname
                };

                this.storeSession(sessionData);
                this.logSecurityEvent('login_success', { email: email });
                return { success: true, user: sessionData.user };
            } else {
                throw new Error(data.message || data.error || 'Invalid email or password');
            }
        } catch (error) {
            console.error('Login error:', error);
            
            // Provide user-friendly error messages
            let errorMessage = error.message;
            
            if (error.message.includes('Failed to fetch')) {
                if (this.useLocalProxy) {
                    errorMessage = 'CORS Error: Cannot access the API from localhost. Solutions: 1. Deploy your files to the same domain as the API 2. Use a local server with CORS proxy 3. Ask your backend team to add CORS headers 4. Use a browser extension to disable CORS (for development only) Technical details: ' + error.message;
                } else {
                    errorMessage = 'Unable to connect to the authentication server. Please check your internet connection and try again.';
                }
            } else if (error.message.includes('NetworkError')) {
                errorMessage = 'Network error occurred. Please check your connection and try again.';
            }
            
            this.logSecurityEvent('login_failure', { email: email, error: error.message });
            return { success: false, error: errorMessage };
        }
    }

    /**
     * Logout and clear all authentication data
     */
    logout() {
        try {
            this.logSecurityEvent('logout', { reason: 'User initiated or security logout' });
            
            // Clear stored data
            localStorage.removeItem(this.tokenKey);
            localStorage.removeItem(this.sessionKey);
            sessionStorage.removeItem(this.tokenKey);
            sessionStorage.removeItem(this.sessionKey);

            // Redirect to login
            this.redirectToLogin();
        } catch (error) {
            console.error('Logout error:', error);
        }
    }

    /**
     * Check if user is currently authenticated
     * @returns {boolean} Authentication status
     */
    isAuthenticated() {
        const sessionData = this.getSessionData();
        if (!sessionData || !sessionData.token) {
            return false;
        }

        // Check if session has expired
        if (Date.now() > sessionData.expiresAt) {
            this.logout();
            return false;
        }

        // Enhanced security: Check for suspicious activity
        if (this.detectSuspiciousActivity(sessionData)) {
            this.logSecurityEvent('suspicious_activity_detected', { 
                reason: 'Session security check failed',
                sessionAge: Date.now() - sessionData.createdAt
            });
            console.warn('Suspicious activity detected, logging out for security');
            this.logout();
            return false;
        }

        // Update last activity
        sessionData.lastActivity = Date.now();
        this.storeSession(sessionData);
        return true;
    }

    /**
     * Detect suspicious activity patterns
     * @private
     */
    detectSuspiciousActivity(sessionData) {
        // Check for session hijacking indicators
        const currentUrl = window.location.hostname;
        const sessionUrl = sessionData.hostname || currentUrl;
        
        // Store hostname on first use
        if (!sessionData.hostname) {
            sessionData.hostname = currentUrl;
        }
        
        // Check if hostname has changed (potential session hijacking)
        if (sessionUrl !== currentUrl) {
            return true;
        }
        
        // Check for excessive session duration (over 24 hours of continuous use)
        const sessionDuration = Date.now() - (sessionData.createdAt || sessionData.lastActivity);
        if (sessionDuration > (24 * 60 * 60 * 1000)) {
            return true;
        }
        
        return false;
    }

    /**
     * Get current authentication token
     * @returns {string|null} Authentication token
     */
    getAuthToken() {
        const sessionData = this.getSessionData();
        return sessionData ? sessionData.token : null;
    }

    /**
     * Refresh/extend the current session
     * @returns {Promise<boolean>} Success status
     */
    async refreshToken() {
        const sessionData = this.getSessionData();
        if (!sessionData) {
            return false;
        }

        try {
            // For now, just extend the session locally since we don't have a token validation endpoint
            // In production, you might want to add a token validation endpoint to your API
            sessionData.expiresAt = Date.now() + (this.maxSessionHours * 60 * 60 * 1000);
            sessionData.lastActivity = Date.now();
            this.storeSession(sessionData);
            return true;
        } catch (error) {
            console.error('Token refresh error:', error);
            return false;
        }
    }

    /**
     * Get current user information
     * @returns {Object|null} User data
     */
    getCurrentUser() {
        const sessionData = this.getSessionData();
        return sessionData ? sessionData.user : null;
    }

    /**
     * Check if session is about to expire (within 30 minutes)
     * @returns {boolean} Warning status
     */
    isSessionExpiring() {
        const sessionData = this.getSessionData();
        if (!sessionData) return false;

        const thirtyMinutes = 30 * 60 * 1000;
        return (sessionData.expiresAt - Date.now()) < thirtyMinutes;
    }

    /**
     * Store session data in both localStorage and sessionStorage
     * @private
     */
    storeSession(sessionData) {
        const dataString = JSON.stringify(sessionData);
        
        // Store in localStorage for persistence across browser sessions
        localStorage.setItem(this.sessionKey, dataString);
        localStorage.setItem(this.tokenKey, sessionData.token);
        
        // Also store in sessionStorage for current session
        sessionStorage.setItem(this.sessionKey, dataString);
        sessionStorage.setItem(this.tokenKey, sessionData.token);
    }

    /**
     * Retrieve session data from storage
     * @private
     */
    getSessionData() {
        try {
            // Try sessionStorage first, then localStorage
            let dataString = sessionStorage.getItem(this.sessionKey) || 
                           localStorage.getItem(this.sessionKey);
            
            if (!dataString) return null;

            const sessionData = JSON.parse(dataString);
            
            // Check if persistent session has expired (24 hours)
            const maxPersistentTime = this.maxPersistentHours * 60 * 60 * 1000;
            if (Date.now() - sessionData.lastActivity > maxPersistentTime) {
                this.logout();
                return null;
            }

            return sessionData;
        } catch (error) {
            console.error('Error retrieving session data:', error);
            this.logout(); // Clear corrupted data
            return null;
        }
    }

    /**
     * Detect if running on localhost for development
     * @private
     */
    isLocalhost() {
        const hostname = window.location.hostname;
        const protocol = window.location.protocol;
        
        // Log for debugging
        console.log('Environment Detection:', { hostname, protocol, fullUrl: window.location.href });
        
        // Check for explicit localhost indicators
        const isLocalDev = hostname === 'localhost' || 
                          hostname === '127.0.0.1' || 
                          hostname.startsWith('192.168.') ||
                          hostname.startsWith('10.') ||
                          protocol === 'file:' ||
                          (hostname.endsWith('.local') && !hostname.includes('.')) || // Only .local TLD, not subdomains
                          hostname === '' || // File protocol
                          /^localhost:\d+$/.test(hostname); // localhost with port
        
        console.log('Is localhost detected:', isLocalDev);
        return isLocalDev;
    }

    /**
     * Validate email format
     * @private
     */
    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Make authenticated API request with stored token
     * @param {string} url - API endpoint URL
     * @param {Object} options - Fetch options (method, body, etc.)
     * @returns {Promise<Response>} Fetch response
     */
    async makeAuthenticatedRequest(url, options = {}) {
        const token = this.getAuthToken();
        if (!token) {
            throw new Error('No authentication token available');
        }

        // If using local proxy, modify the URL
        const finalUrl = this.useLocalProxy && !url.startsWith('http') 
            ? `api-proxy.php?endpoint=${encodeURIComponent(url)}`
            : url;

        const headers = {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            ...options.headers
        };

        // For local PHP files that expect token in body, add it to the request body
        let body = options.body;
        if (body && typeof body === 'string' && url.endsWith('.php')) {
            try {
                const bodyData = JSON.parse(body);
                bodyData.token = token;
                body = JSON.stringify(bodyData);
            } catch (e) {
                // If body is not JSON, leave it as is
            }
        }

        return fetch(finalUrl, {
            ...options,
            headers,
            body
        });
    }

    /**
     * Log security events for monitoring
     * @private
     */
    logSecurityEvent(event, details = {}) {
        const logEntry = {
            timestamp: new Date().toISOString(),
            event: event,
            userAgent: navigator.userAgent,
            url: window.location.href,
            ...details
        };
        
        // In production, send to security monitoring service
        console.log('Security Event:', logEntry);
        
        // Store locally for debugging (limit to last 50 events)
        try {
            const logs = JSON.parse(localStorage.getItem('security_logs') || '[]');
            logs.push(logEntry);
            if (logs.length > 50) {
                logs.shift(); // Remove oldest entry
            }
            localStorage.setItem('security_logs', JSON.stringify(logs));
        } catch (error) {
            console.error('Failed to log security event:', error);
        }
    }

    /**
     * Redirect to login form
     * @private
     */
    redirectToLogin() {
        // Show login form and hide main content
        const loginSection = document.getElementById('loginSection');
        const mainContent = document.querySelector('.analysis-section, .dashboard, #analysisSection, #dashboardPage');
        
        if (loginSection) {
            loginSection.style.display = 'block';
        }
        if (mainContent) {
            mainContent.style.display = 'none';
        }
    }

    /**
     * Initialize authentication on page load
     */
    init() {
        // Check authentication status on page load
        if (this.isAuthenticated()) {
            // User is authenticated, show main content
            this.showMainContent();
            
            // Set up session expiration warning
            this.setupExpirationWarning();
        } else {
            // User not authenticated, show login form
            this.redirectToLogin();
        }
    }

    /**
     * Show main content and hide login form
     * @private
     */
    showMainContent() {
        const loginSection = document.getElementById('loginSection');
        const mainContent = document.querySelector('.analysis-section, .dashboard, #analysisSection, #dashboardPage');
        
        if (loginSection) {
            loginSection.style.display = 'none';
        }
        if (mainContent) {
            mainContent.style.display = 'block';
        }
    }

    /**
     * Setup session expiration warning
     * @private
     */
    setupExpirationWarning() {
        // Check every 5 minutes for session expiration
        setInterval(() => {
            if (this.isSessionExpiring()) {
                this.showExpirationWarning();
            }
        }, 5 * 60 * 1000);
    }

    /**
     * Show session expiration warning
     * @private
     */
    async showExpirationWarning() {
        // Create a more user-friendly warning dialog
        const warningDiv = document.createElement('div');
        warningDiv.id = 'session-warning';
        warningDiv.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            font-family: Arial, sans-serif;
        `;
        
        warningDiv.innerHTML = `
            <div style="background: white; padding: 30px; border-radius: 10px; max-width: 400px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <h3 style="margin-top: 0; color: #ff6b35;">Session Expiring Soon</h3>
                <p style="margin: 20px 0; color: #666;">Your session will expire in a few minutes. Would you like to extend it?</p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button id="extend-session" style="padding: 10px 20px; background: #4361ee; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Extend Session
                    </button>
                    <button id="logout-now" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Logout
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(warningDiv);
        
        // Handle button clicks
        document.getElementById('extend-session').onclick = async () => {
            const success = await this.refreshToken();
            if (success) {
                document.body.removeChild(warningDiv);
            } else {
                alert('Failed to extend session. Please login again.');
                this.logout();
            }
        };
        
        document.getElementById('logout-now').onclick = () => {
            document.body.removeChild(warningDiv);
            this.logout();
        };
        
        // Auto-logout after 2 minutes if no action taken
        setTimeout(() => {
            if (document.getElementById('session-warning')) {
                document.body.removeChild(warningDiv);
                this.logout();
            }
        }, 2 * 60 * 1000);
    }
}

// Create global instance
window.authManager = new AuthManager();

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.authManager.init();
});