/**
 * Register Page JavaScript - Complete Version
 * Handles:
 * - Real-time username availability (with debounce)
 * - Password visibility toggles
 * - Real-time password strength requirements
 * - Form submission and user feedback
 */

// Global Variables
const PASSWORD_SPECIAL_CHARS = /[!@#$%^&*(),.?":{}|<>]/;
let usernameAvailable = false;
let usernameCheckTimeout;

/**
 * Initialize password visibility toggle buttons
 */
function initPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const input = btn.previousElementSibling;
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
        });
    });
}

/**
 * Check username availability in real-time (debounced)
 * Prevents excessive server requests while typing
 */
function checkUsernameAvailability(username) {
    clearTimeout(usernameCheckTimeout);
    const usernameField = document.getElementById('username');
    if (!usernameField) return;

    // Reset availability state
    usernameAvailable = false;
    
    // Clear validation if empty
    if (!username) {
        usernameField.style.borderColor = '#e2e8f0';
        return;
    }

    // Client-side length check before hitting server
    if (username.length < 2 || username.length > 30) {
        usernameField.style.borderColor = '#fc8181';
        return;
    }

    // Debounce: Wait 500ms after user stops typing
    usernameCheckTimeout = setTimeout(() => {
        fetch('/api/check_username', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: username })
        })
        .then(response => response.json())
        .then(data => {
            if (data.available) {
                usernameAvailable = true;
                usernameField.style.borderColor = '#9ae6b4'; // Green
            } else {
                usernameAvailable = false;
                usernameField.style.borderColor = '#fc8181'; // Red
                showMessage('Username already taken.', 'warning');
            }
        })
        .catch(err => {
            console.error('Username check error:', err);
            usernameField.style.borderColor = '#e2e8f0';
        });
    }, 500);
}

/**
 * Validate password requirements based on current input
 */
function validatePasswordRequirements(password) {
    return {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        number: /\d/.test(password),
        special: PASSWORD_SPECIAL_CHARS.test(password)
    };
}

/**
 * Update the UI for password requirements in real-time
 */
function updatePasswordRequirements() {
    const password = document.getElementById('password').value;
    const reqs = validatePasswordRequirements(password);
    
    document.getElementById('req-length').className = reqs.length ? 'requirement met' : 'requirement unmet';
    document.getElementById('req-uppercase').className = reqs.uppercase ? 'requirement met' : 'requirement unmet';
    document.getElementById('req-number').className = reqs.number ? 'requirement met' : 'requirement unmet';
    document.getElementById('req-special').className = reqs.special ? 'requirement met' : 'requirement unmet';
}

/**
 * Show messages in the UI
 */
function showMessage(text, type) {
    const msg = document.getElementById('message');
    if (!msg) return;
    
    msg.textContent = text;
    msg.className = type;
    msg.style.display = 'block';
    
    // Auto-hide non-error messages
    if (type !== 'error') {
        setTimeout(() => { msg.style.display = 'none'; }, 3000);
    }
}

/**
 * Handle form submission
 */
function handleRegistrationSubmit(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const submitBtn = e.target.querySelector('button[type="submit"]');

    // 1. Username validation
    if (username.length < 2 || username.length > 30) {
        showMessage('Username must be 2-30 characters.', 'error');
        return;
    }

    // 2. Availability validation
    if (!usernameAvailable) {
        showMessage('Username is taken or unavailable.', 'error');
        return;
    }

    // 3. Password length validation
    if (password.length < 8) {
        showMessage('Password must be at least 8 characters.', 'error');
        return;
    }

    // 4. Password match validation
    if (password !== confirmPassword) {
        showMessage('Passwords do not match.', 'error');
        return;
    }

    // Submit the form
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating...';

    fetch('/register', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            username: username, 
            password: password, 
            confirm_password: confirmPassword 
        })
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid response from server:', text);
            throw new Error('Server returned an error page instead of data. Check server logs.');
        }
    })
    .then(data => {
        if (data.success) {
            showMessage('Registration successful! Redirecting...', 'success');
            setTimeout(() => { window.location.href = '/login'; }, 2000);
        } else {
            showMessage(data.error || 'Registration failed.', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Account';
        }
    })
    .catch(err => {
        console.error(err);
        showMessage('Connection failed. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Account';
    });
}

/**
 * Initialization
 */
document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
    
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        usernameInput.addEventListener('input', (e) => checkUsernameAvailability(e.target.value.trim()));
    }
    
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', updatePasswordRequirements);
    }
    
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegistrationSubmit);
    }
});