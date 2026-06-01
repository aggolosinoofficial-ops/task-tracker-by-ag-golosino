/**
 * Login Page JavaScript - Enhanced Version
 * Handles:
 * - CSRF token management for security
 * - Password visibility toggle
 * - Form submission with validation
 * - Error recovery and user feedback
 */

/**
 * Fetch CSRF token from server
 * Required for secure login submission
 */
function initializeCSRF() {
    fetch('get_csrf_token.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch CSRF token');
            }
            return response.json();
        })
        .then(data => {
            if (data.token) {
                document.getElementById('csrf_token').value = data.token;
            } else {
                throw new Error('No CSRF token in response');
            }
        })
        .catch(error => {
            console.error('CSRF token error:', error);
            showMessage('Security setup error. Please refresh the page.', 'error');
        });
}

/**
 * Initialize password visibility toggle
 * Allows users to show/hide their password
 */
function initPasswordToggle() {
    const toggle = document.querySelector('.password-toggle');
    const passwordInput = document.getElementById('password');

    if (!toggle || !passwordInput) return;

    toggle.addEventListener('click', function(e) {
        e.preventDefault();

        const isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';

        if (isHidden) {
            toggle.classList.add('show');
        } else {
            toggle.classList.remove('show');
        }
    });
}

/**
 * Display message to user (error or success)
 */
function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    if (!messageDiv) return;
    
    messageDiv.textContent = message;
    messageDiv.className = type;
    messageDiv.style.display = type ? 'block' : 'none';
}

/**
 * Handle login form submission
 * Validates credentials and submits to server
 */
function handleLoginSubmit(e) {
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const csrfToken = document.getElementById('csrf_token').value;
    const submitBtn = document.querySelector('button[type="submit"]');

    // VALIDATION: Check if fields are empty
    if (!username || !password) {
        showMessage('Please enter username and password', 'error');
        return;
    }

    // SECURITY: Check if CSRF token is available
    if (!csrfToken) {
        showMessage('Security token missing. Refreshing...', 'error');
        // Attempt to refresh CSRF token
        initializeCSRF();
        return;
    }

    // OPTIMIZATION: Disable submit button to prevent double-submission
    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in...';

    // SUBMISSION: Prepare form data
    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);
    formData.append('csrf_token', csrfToken);

    // SUBMISSION: Send credentials to server
    fetch('login.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            // Check HTTP status
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || `HTTP Error: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showMessage('Login successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                showMessage(data.error || 'Login failed', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign In';
                
                // Refresh CSRF token on login failure for next attempt
                initializeCSRF();
            }
        })
        .catch(error => {
            console.error('Login error:', error);
            showMessage(error.message || 'Error during login. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Sign In';
            
            // Refresh CSRF token for next attempt
            initializeCSRF();
        });
}

/**
 * Initialize page on load
 * Sets up CSRF token, password toggle, and form handler
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get CSRF token from server
    initializeCSRF();
    
    // Initialize password visibility toggle
    initPasswordToggle();
    
    // Set up form submission handler
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLoginSubmit);
    }
});
