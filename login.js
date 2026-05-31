/**
 * Login Page JavaScript
 * Handles password visibility toggle and CSRF token management
 * Lightweight implementation with no external dependencies
 */

/**
 * Initialize CSRF token on page load
 * Fetches a fresh security token from the server for protection against CSRF attacks
 * SECURITY: Ensures the login form has a valid token before user submission
 */
function initializeCSRF() {
    fetch('get_csrf_token.php')
        .then(response => response.json())
        .then(data => {
            if (data.token) {
                document.getElementById('csrf_token').value = data.token;
            }
        })
        .catch(error => console.error('Failed to get CSRF token:', error));
}

/**
 * Initialize password visibility toggle
 * Switches between show/hide password SVG icons and input type
 * OPTIMIZATION: Single event listener for password toggle button
 */
function initPasswordToggle() {
    const toggle = document.querySelector('.password-toggle');
    const passwordInput = document.getElementById('password');

    if (!toggle || !passwordInput) {
        return;
    }

    toggle.addEventListener('click', function(e) {
        e.preventDefault();

        // Toggle between password and text input types
        const isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';

        // Update SVG icon based on visibility state
        // Show password: open eye icon
        if (isHidden) {
            toggle.classList.add('show');
            toggle.setAttribute('aria-label', 'Hide password');
            toggle.title = 'Hide password';
        } else {
            // Hide password: closed eye icon with slash
            toggle.classList.remove('show');
            toggle.setAttribute('aria-label', 'Show password');
            toggle.title = 'Show password';
        }

        // Brief visual feedback on click
        toggle.style.transform = 'translateY(-50%) scale(1.05)';
        setTimeout(() => {
            toggle.style.transform = '';
        }, 120);
    });
}

/**
 * Display status message to user (success or error)
 * @param {string} message - The message to display
 * @param {string} type - Message type: 'success' or 'error'
 */
function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    messageDiv.textContent = message;
    messageDiv.className = type;
}

/**
 * Handle login form submission
 * Validates credentials and sends to server with CSRF token
 */
function handleLoginSubmit(e) {
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const csrfToken = document.getElementById('csrf_token').value;
    const submitBtn = document.querySelector('button[type="submit"]');

    // Client-side validation
    if (!username || !password) {
        showMessage('Please enter both username and password', 'error');
        return;
    }

    if (!csrfToken) {
        showMessage('Security token missing. Please refresh the page.', 'error');
        return;
    }

    // Disable button to prevent double submission
    submitBtn.disabled = true;
    submitBtn.textContent = 'Logging in...';

    // Prepare form data with CSRF token
    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);
    formData.append('csrf_token', csrfToken);

    // Send login request to server
    fetch('login.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('Login successful! Redirecting...', 'success');
                // Redirect to dashboard after successful login
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                showMessage(data.error || 'Login failed', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Login';
                // Refresh token on failed attempt for security
                initializeCSRF();
            }
        })
        .catch(error => {
            showMessage('An error occurred. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Login';
            console.error('Error:', error);
        });
}

/**
 * Initialize all event handlers when DOM is ready
 * OPTIMIZATION: Deferred until DOM content is loaded to ensure elements exist
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get CSRF token for security
    initializeCSRF();

    // Initialize password toggle functionality
    initPasswordToggle();

    // Attach form submission handler
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLoginSubmit);
    }
});
