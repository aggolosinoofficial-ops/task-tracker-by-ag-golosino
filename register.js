/**
 * Register Page JavaScript - Enhanced Version with Real-Time Username Validation
 * Handles:
 * - Real-time username availability checking
 * - Password visibility toggles
 * - Real-time password strength requirements
 * - Form submission with comprehensive validation
 * - Error handling and user feedback
 */

// Password special characters pattern
const PASSWORD_SPECIAL_CHARS = /[!@#$%^&*]/;
let usernameAvailable = false;
let usernameCheckTimeout;

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
 * Check username availability in real-time (debounced)
 * Prevents excessive server requests while typing
 */
function checkUsernameAvailability(username) {
    // Clear previous timeout
    clearTimeout(usernameCheckTimeout);

    const usernameField = document.getElementById('username');
    if (!usernameField) return;

    // Reset state (we treat this as "unknown" until the server responds)
    usernameAvailable = false;

    // Validate client-side first
    const usernameError = validateUsername(username);

    if (!username) {
        usernameField.style.borderColor = '#e2e8f0';
        return;
    }

    if (usernameError) {
        usernameField.style.borderColor = '#fc8181';
        return;
    }

    // Debounce server check (wait 500ms after user stops typing)
    usernameCheckTimeout = setTimeout(() => {
        const formData = new FormData();
        formData.append('username', username);

        fetch('check_username.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    usernameAvailable = true;
                    usernameField.style.borderColor = '#9ae6b4'; // Green
                } else {
                    usernameAvailable = false;
                    usernameField.style.borderColor = '#fc8181'; // Red
                    showMessage('Username not available. Try another one', 'warning');
                }
            })
            .catch(error => {
                // Network/server error -> do NOT block registration
                console.error('Username check error:', error);
                usernameAvailable = false;
                usernameField.style.borderColor = '#e2e8f0';
            });
    }, 500); // Wait 500ms after user stops typing
}

/**
 * Validate username format and length
 * UPDATED RULES: 2-30 characters, any characters allowed
 * Returns error message if invalid, null if valid
 */
function validateUsername(username) {
    if (!username) {
        return 'Username is required';
    }
    if (username.length < 2 || username.length > 30) {
        return 'Username must be 2-30 characters long';
    }
    // Allow any characters (no format restriction)
    return null;
}

/**
 * Validate individual password requirements (UPDATED: RELAXED RULES)
 * Returns object with boolean flags for each requirement
 * NOTE: Only 8+ chars is REQUIRED, others are OPTIONAL (shown as warnings only)
 */
function validatePasswordRequirements(password) {
    return {
        length: password.length >= 8,  // REQUIRED
        uppercase: /[A-Z]/.test(password),  // Optional - for info only
        number: /[0-9]/.test(password),  // Optional - for info only
        special: PASSWORD_SPECIAL_CHARS.test(password)  // Optional - for info only
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
 * Display message to user with type (success, error, or warning)
 */
function showMessage(text, type) {
    const msg = document.getElementById('message');
    if (!msg) return;
    
    msg.textContent = text;
    msg.className = type;
    msg.style.display = 'block';
    
    // Auto-hide success/warning messages after 3 seconds
    if (type === 'success' || type === 'warning') {
        setTimeout(() => {
            msg.style.display = 'none';
        }, 3000);
    }
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
    
    // VALIDATION: Check username format
    const usernameError = validateUsername(username);
    if (usernameError) {
        showMessage(usernameError, 'error');
        return;
    }
    
    // VALIDATION: Check if username is available
    // If the availability check hasn't completed (or failed), don't block registration.
    // Server-side registration.php will still enforce uniqueness.
    if (username && username.length >= 2 && username.length <= 30) {
        if (usernameAvailable === false) {
            // Only show a warning when the check completed negatively.
            // (Avoid hard-blocking when request hasn't returned yet.)
            // Heuristic: if border is red, user tried a value that is confirmed unavailable.
            const usernameField = document.getElementById('username');
            if (usernameField && usernameField.style.borderColor === '#fc8181') {
                showMessage('Username not available. Try another one', 'error');
                return;
            }
        }
    }

    
    // VALIDATION: Check if passwords match
    if (password !== confirmPassword) {
        showMessage('Passwords do not match', 'error');
        return;
    }
    
    // VALIDATION: Check password requirements
    // UPDATED: Only 8+ chars is REQUIRED
    // Uppercase, numbers, and special chars are optional (informational only)
    const reqs = validatePasswordRequirements(password);
    if (!reqs.length) {
        showMessage('Password must be at least 8 characters long', 'error');
        return;
    }
    
    // OPTIONAL: Warn about weak passwords (but allow them)
    let weakWarning = [];
    if (!reqs.uppercase) weakWarning.push('no uppercase');
    if (!reqs.number) weakWarning.push('no numbers');
    if (!reqs.special) weakWarning.push('no special characters');
    
    if (weakWarning.length > 0) {
        // Show warning but allow submission
        console.warn('Weak password detected:', weakWarning);
    }
    
    // Disable submit button to prevent double-submission
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating account...';
    
    // SUBMISSION: Prepare form data (NO CSRF TOKEN for new users)
    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);
    formData.append('confirm_password', confirmPassword);
    
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
            submitBtn.textContent = 'Create Account';
        }
    })
    .catch(err => {
        console.error('Registration error:', err);
        showMessage('Connection error: ' + err.message, 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Account';
    });
}

/**
 * Initialize page on load
 * Sets up password toggles, validators, and form handlers
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialize password toggle buttons
    initPasswordToggles();
    
    // Add real-time username availability checking
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        usernameInput.addEventListener('input', (e) => {
            checkUsernameAvailability(e.target.value.trim());
        });
    }
    
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
