/**
 * Login Form Handler
 * Fetches CSRF token and handles login submission
 */

// Get CSRF token from server on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Login] Page loaded, fetching CSRF token...');
    
    // Fetch CSRF token
    fetch('get_csrf_token.php', {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('[CSRF] Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('[CSRF] Token received:', data.token ? 'Yes' : 'No');
        if (data.token) {
            document.getElementById('csrf_token').value = data.token;
            console.log('[CSRF] Token set successfully');
        } else {
            console.error('[CSRF] No token in response:', data);
            showMessage('Security token error. Please refresh the page.', 'error');
        }
    })
    .catch(error => {
        console.error('[CSRF] Fetch error:', error);
        showMessage('Failed to load security token. Please refresh the page.', 'error');
    });

    // Setup login form handler
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
        console.log('[Login] Form handler attached');
    }

    // Setup password toggle
    setupPasswordToggle();
});

/**
 * Handle login form submission
 */
function handleLogin(e) {
    e.preventDefault();
    console.log('[Login] Form submitted');

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const csrfToken = document.getElementById('csrf_token').value;
    const submitBtn = document.querySelector('button[type="submit"]');

    // Validation
    if (!username || !password) {
        showMessage('Username and password are required', 'error');
        return;
    }

    if (!csrfToken) {
        showMessage('Security token missing. Please refresh the page.', 'error');
        return;
    }

    // Disable button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in...';

    console.log('[Login] Sending login request for user:', username);

    // Send login request
    fetch('login.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        credentials: 'same-origin',
        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => {
        console.log('[Login] Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('[Login] Response data:', data);
        
        if (data.success) {
            console.log('[Login] Login successful!');
            showMessage('✓ Login successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);
        } else {
            console.error('[Login] Error:', data.error);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Sign In';
            
            // Show error message
            const errorMsg = data.error || 'Login failed';
            if (errorMsg.includes('refresh')) {
                showMessage('Invalid security token. Please refresh the page.', 'error');
            } else if (errorMsg.includes('Too many')) {
                showMessage('Too many login attempts. Please wait before trying again.', 'error');
            } else {
                showMessage('✗ ' + errorMsg, 'error');
            }
        }
    })
    .catch(error => {
        console.error('[Login] Fetch error:', error);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Sign In';
        
        // Check if it's a connection error
        if (error.message.includes('Failed to fetch')) {
            showMessage('✗ Connection error: Could not reach server. Is XAMPP running?', 'error');
        } else {
            showMessage('✗ Network error: ' + error.message, 'error');
        }
    });
}

/**
 * Show message in the message div
 */
function showMessage(message, type = 'info') {
    const messageDiv = document.getElementById('message');
    messageDiv.textContent = message;
    messageDiv.className = type;
    messageDiv.style.display = 'block';
    console.log('[Message]', type.toUpperCase(), message);
}

/**
 * Setup password visibility toggle
 */
function setupPasswordToggle() {
    const toggleBtn = document.querySelector('.password-toggle');
    const passwordInput = document.getElementById('password');

    if (!toggleBtn || !passwordInput) {
        console.warn('[Password] Toggle button or input not found');
        return;
    }

    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        toggleBtn.classList.toggle('show', isPassword);
        console.log('[Password] Visibility toggled');
    });
}