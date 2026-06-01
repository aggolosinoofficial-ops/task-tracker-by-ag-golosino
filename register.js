/**
 * Register Page JavaScript - Enhanced Version
 * Handles:
 * - Password visibility toggles
 * - Real-time password strength requirements
 * - CSRF token validation
 * - Form submission with comprehensive validation
 * - Error handling and user feedback
 */

// Password special characters pattern
const PASSWORD_SPECIAL_CHARS = /[!@#$%^&*]/;

/**
 * Initialize password visibility toggle buttons
 * Allows users to show/hide password on demand
 */
function initPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            // Find the corresponding password input
            const input = btn.previousElementSibling;
            if (!input) return;
            
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.classList.toggle('show', isPassword);
        });
    });
}

/**
 * Validate username format and length
 * Returns error message if invalid, null if valid
 */
function validateUsername(username) {
    if (!username) {
        return 'Username is required';
    }
    if (username.length < 3 || username.length > 30) {
        return 'Username must be 3-30 characters long';
    }
    if (!/^[\w\s\u0080-\uFFFF]+$/.test(username)) {
        return 'Username contains invalid characters. Use letters, numbers, spaces, and emojis only';
    }
    return null;
}

/**
 * Validate individual password requirements
 * Returns object with boolean flags for each requirement
 */
function validatePasswordRequirements(password) {
    return {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        number: /[0-9]/.test(password),
        special: PASSWORD_SPECIAL_CHARS.test(password)
    };
}

/**
 * Update password requirements display in real-time
 * Shows checkmarks as user types password
 */
function updatePasswordRequirements() {
    const password = document.getElementById('password').value;
    const reqs = validatePasswordRequirements(password);
    
    // Update each requirement indicator
    document.getElementById('req-length').classList.toggle('met', reqs.length);
    document.getElementById('req-length').classList.toggle('unmet', !reqs.length);
    
    document.getElementById('req-uppercase').classList.toggle('met', reqs.uppercase);
    document.getElementById('req-uppercase').classList.toggle('unmet', !reqs.uppercase);
    
    document.getElementById('req-number').classList.toggle('met', reqs.number);
    document.getElementById('req-number').classList.toggle('unmet', !reqs.number);
    
    document.getElementById('req-special').classList.toggle('met', reqs.special);
    document.getElementById('req-special').classList.toggle('unmet', !reqs.special);
}

/**
 * Display message to user with type (success or error)
 */
function showMessage(text, type) {
    const msg = document.getElementById('message');
    if (!msg) return;
    
    msg.textContent = text;
    msg.className = type;
    msg.style.display = 'block';
    
    // Auto-hide success messages after 3 seconds
    if (type === 'success') {
        setTimeout(() => {
            msg.style.display = 'none';
        }, 3000);
    }
}

/**
 * Fetch CSRF token from server
 * Prevents cross-site attacks on registration
 */
function fetchCSRFToken() {
    return fetch('get_csrf_token.php')
        .then(response => response.json())
        .then(data => {
            if (data.token) {
                document.getElementById('csrf_token').value = data.token;
                return true;
            }
            return false;
        })
        .catch(error => {
            console.error('Failed to fetch CSRF token:', error);
            return false;
        });
}

/**
 * Handle registration form submission
 * Validates all fields and submits to server
 */
function handleRegistrationSubmit(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const submitBtn = document.querySelector('button[type="submit"]');
    
    // VALIDATION: Check username
    const usernameError = validateUsername(username);
    if (usernameError) {
        showMessage(usernameError, 'error');
        return;
    }
    
    // VALIDATION: Check if passwords match
    if (password !== confirmPassword) {
        showMessage('Passwords do not match', 'error');
        return;
    }
    
    // VALIDATION: Check password requirements
    const reqs = validatePasswordRequirements(password);
    if (!reqs.length || !reqs.uppercase || !reqs.number || !reqs.special) {
        showMessage('Password must meet all requirements', 'error');
        return;
    }
    
    // Disable submit button to prevent double-submission
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating account...';
    
    // SUBMISSION: Prepare form data
    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);
    formData.append('confirm_password', confirmPassword);
    formData.append('csrf_token', document.getElementById('csrf_token').value);
    
    // SUBMISSION: Send to server
    fetch('register.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Account created successfully! Redirecting to login...', 'success');
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 2000);
        } else {
            showMessage(data.error || data.message || 'Registration failed', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Register';
        }
    })
    .catch(err => {
        console.error('Registration error:', err);
        showMessage('Connection error: ' + err.message, 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Register';
    });
}

/**
 * Initialize page on load
 * Sets up password toggles, validators, and form handlers
 */
document.addEventListener('DOMContentLoaded', () => {
    // Fetch CSRF token on page load
    fetchCSRFToken();
    
    // Initialize password toggle buttons
    initPasswordToggles();
    
    // Add real-time password requirement validation
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', updatePasswordRequirements);
    }
    
    // Handle form submission
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegistrationSubmit);
    }
});
