/**
 * Admin Authentication Integration Script
 * Easy integration for existing admin pages
 */

(function() {
    'use strict';

    /**
     * Initialize secure authentication for admin pages
     */
    function initSecureAuth() {
        // Load required CSS
        loadCSS('admin-auth.css');
        
        // Load authentication manager
        loadScript('auth-manager.js', function() {
            // Replace existing login sections with secure login form
            replaceLoginSections();
            
            // Add logout functionality to existing pages
            addLogoutFunctionality();
            
            // Initialize authentication
            if (window.authManager) {
                window.authManager.init();
            }
        });
    }

    /**
     * Load CSS file dynamically
     */
    function loadCSS(filename) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.type = 'text/css';
        link.href = filename;
        document.head.appendChild(link);
    }

    /**
     * Load JavaScript file dynamically
     */
    function loadScript(filename, callback) {
        const script = document.createElement('script');
        script.src = filename;
        script.onload = callback;
        document.head.appendChild(script);
    }

    /**
     * Replace existing login sections with secure login form
     */
    function replaceLoginSections() {
        const existingLoginSection = document.getElementById('loginSection');
        if (existingLoginSection) {
            // Create new secure login form
            const secureLoginHTML = `
                <div id="loginSection" class="login-section">
                    <div class="login-container">
                        <div class="login-box">
                            <div class="login-header">
                                <h2>Admin Login</h2>
                                <p>Enter your credentials to access the admin dashboard</p>
                            </div>
                            
                            <form id="adminLoginForm" class="login-form">
                                <div class="form-group">
                                    <label for="adminEmail">Email Address</label>
                                    <input 
                                        type="email" 
                                        id="adminEmail" 
                                        name="email" 
                                        required 
                                        autocomplete="email"
                                        placeholder="admin@example.com"
                                        class="form-input"
                                    >
                                    <div class="field-error" id="emailError"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="adminPassword">Password</label>
                                    <input 
                                        type="password" 
                                        id="adminPassword" 
                                        name="password" 
                                        required 
                                        autocomplete="current-password"
                                        placeholder="Enter your password"
                                        class="form-input"
                                    >
                                    <div class="field-error" id="passwordError"></div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" id="loginBtn" class="login-btn">
                                        <span class="btn-text">Login</span>
                                        <span class="btn-loading" style="display: none;">
                                            <span class="spinner"></span>
                                            Logging in...
                                        </span>
                                    </button>
                                </div>
                            </form>
                            
                            <div id="loginMessage" class="login-message"></div>
                        </div>
                    </div>
                </div>
            `;
            
            // Replace existing login section
            existingLoginSection.outerHTML = secureLoginHTML;
            
            // Initialize login form handler
            initLoginFormHandler();
        }
    }

    /**
     * Initialize login form handler
     */
    function initLoginFormHandler() {
        const form = document.getElementById('adminLoginForm');
        const emailInput = document.getElementById('adminEmail');
        const passwordInput = document.getElementById('adminPassword');
        const loginBtn = document.getElementById('loginBtn');
        const messageDiv = document.getElementById('loginMessage');
        
        if (!form) return;
        
        // Handle form submission
        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            
            // Clear previous messages
            clearMessages();
            
            // Validate form
            if (!validateForm()) {
                return;
            }
            
            // Show loading state
            setLoadingState(true);
            
            try {
                const email = emailInput.value.trim();
                const password = passwordInput.value;
                
                // Attempt login using AuthManager
                const result = await window.authManager.login(email, password);
                
                if (result.success) {
                    showMessage('Login successful! Loading dashboard...', 'success');
                    
                    // Hide login form and show main content
                    setTimeout(() => {
                        document.getElementById('loginSection').style.display = 'none';
                        showMainContent();
                    }, 1000);
                } else {
                    showMessage(result.error || 'Login failed. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Login error:', error);
                showMessage('Connection error. Please check your internet connection and try again.', 'error');
            } finally {
                setLoadingState(false);
            }
        });
        
        // Validation event listeners
        emailInput.addEventListener('blur', validateEmail);
        passwordInput.addEventListener('input', clearPasswordError);
        emailInput.addEventListener('input', clearEmailError);
        
        // Validation functions
        function validateForm() {
            let isValid = true;
            
            if (!validateEmail()) isValid = false;
            if (!validatePassword()) isValid = false;
            
            return isValid;
        }
        
        function validateEmail() {
            const email = emailInput.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!email) {
                showFieldError('emailError', 'Email is required');
                emailInput.classList.add('error');
                return false;
            }
            
            if (!emailRegex.test(email)) {
                showFieldError('emailError', 'Please enter a valid email address');
                emailInput.classList.add('error');
                return false;
            }
            
            clearFieldError('emailError');
            emailInput.classList.remove('error');
            return true;
        }
        
        function validatePassword() {
            const password = passwordInput.value;
            
            if (!password) {
                showFieldError('passwordError', 'Password is required');
                passwordInput.classList.add('error');
                return false;
            }
            
            clearFieldError('passwordError');
            passwordInput.classList.remove('error');
            return true;
        }
        
        function clearEmailError() {
            clearFieldError('emailError');
            emailInput.classList.remove('error');
        }
        
        function clearPasswordError() {
            clearFieldError('passwordError');
            passwordInput.classList.remove('error');
        }
        
        function showFieldError(elementId, message) {
            const errorElement = document.getElementById(elementId);
            if (errorElement) {
                errorElement.textContent = message;
            }
        }
        
        function clearFieldError(elementId) {
            const errorElement = document.getElementById(elementId);
            if (errorElement) {
                errorElement.textContent = '';
            }
        }
        
        function setLoadingState(loading) {
            const btnText = loginBtn.querySelector('.btn-text');
            const btnLoading = loginBtn.querySelector('.btn-loading');
            
            if (loading) {
                btnText.style.display = 'none';
                btnLoading.style.display = 'flex';
                loginBtn.disabled = true;
            } else {
                btnText.style.display = 'block';
                btnLoading.style.display = 'none';
                loginBtn.disabled = false;
            }
        }
        
        function showMessage(message, type = 'info') {
            messageDiv.textContent = message;
            messageDiv.className = `login-message ${type}`;
            messageDiv.style.display = 'block';
        }
        
        function clearMessages() {
            messageDiv.style.display = 'none';
            messageDiv.textContent = '';
            messageDiv.className = 'login-message';
        }
        
        function showMainContent() {
            // Show the appropriate main content based on the page
            const analysisSection = document.getElementById('analysisSection');
            const dashboardPage = document.getElementById('dashboardPage');
            const dashboard = document.querySelector('.dashboard');
            
            if (analysisSection) {
                analysisSection.style.display = 'block';
            }
            if (dashboardPage) {
                dashboardPage.style.display = 'block';
            }
            if (dashboard) {
                dashboard.style.display = 'block';
            }
        }
    }

    /**
     * Add logout functionality to existing pages
     */
    function addLogoutFunctionality() {
        // Find existing logout buttons and replace their functionality
        const logoutButtons = document.querySelectorAll('.logout-btn, [onclick*="logout"]');
        
        logoutButtons.forEach(button => {
            // Remove existing onclick handlers
            button.removeAttribute('onclick');
            
            // Add secure logout handler
            button.addEventListener('click', function(event) {
                event.preventDefault();
                if (window.authManager) {
                    window.authManager.logout();
                }
            });
        });
        
        // Add logout button to pages that don't have one
        addLogoutButtonIfMissing();
    }

    /**
     * Add logout button to pages that don't have one
     */
    function addLogoutButtonIfMissing() {
        const existingLogoutBtn = document.querySelector('.logout-btn');
        if (existingLogoutBtn) return;
        
        // Look for header or navigation area to add logout button
        const header = document.querySelector('.dashboard-header, .header-actions, .nav-item');
        if (header) {
            const logoutBtn = document.createElement('button');
            logoutBtn.className = 'logout-btn';
            logoutBtn.textContent = 'Logout';
            logoutBtn.addEventListener('click', function() {
                if (window.authManager) {
                    window.authManager.logout();
                }
            });
            
            header.appendChild(logoutBtn);
        }
    }

    /**
     * Override existing admin login functions
     */
    function overrideExistingLoginFunctions() {
        // Override common login function names
        window.adminLogin = function() {
            console.log('Legacy adminLogin() called - using secure authentication instead');
            // The secure login form will handle authentication
        };
        
        // Override other common login-related functions
        window.testPHP = function() {
            console.log('Legacy testPHP() called - connection testing handled by secure auth');
        };
    }

    /**
     * Initialize when DOM is ready
     */
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSecureAuth);
        } else {
            initSecureAuth();
        }
        
        // Override existing functions
        overrideExistingLoginFunctions();
    }

    // Start initialization
    init();
})();