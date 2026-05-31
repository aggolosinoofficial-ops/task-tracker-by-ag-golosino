/**
 * Registration Page JavaScript
 * Handles password validation, visibility toggle, and CSRF token management
 * Lightweight implementation with no external dependencies for low-resource environments
 */

// Password validation requirements configuration
const PASSWORD_REQUIREMENTS = {
    minLength: 8,
    uppercase: true,
    number: true,
    special: true
};

/**
 * Initialize CSRF token on page load
 * SECURITY: Generates/fetches CSRF token for all accounts (admin and non-admin)
 * Ensures the registration form has a valid security token before submission
 * Validates token server-side before registration
 */
function initializeCSRF() {
    fetch('get_csrf_token.php')
        .then(response => response.json())
        .then(data => {
            if (data.token) {
                document.getElementById('csrf_token').value = data.token;
            } else {
                showMessage('Security token could not be generated. Please refresh the page.', 'error');
            }
        })
        .catch(error => {
            console.error('Failed to get CSRF token:', error);
            showMessage('Security error. Please refresh the page.', 'error');
        });
}

/**
 * Setup password visibility toggle for all password fields
 * Allows users to show/hide password text by clicking the eye icon
 * OPTIMIZATION: Uses event delegation for multiple password fields
 */
function setupPasswordToggle() {
    const toggleButtons = document.querySelectorAll('.password-toggle');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.dataset.target;
            const inputField = document.getElementById(targetId);
            
            if (!inputField) return;
            
            // Toggle between password and text input types
            const isHidden = inputField.type === 'password';
            inputField.type = isHidden ? 'text' : 'password';
            
            // Update button visual state to show which icon is displayed
            if (isHidden) {
                this.classList.add('show');
                this.setAttribute('aria-label', 'Hide password');
            } else {
                this.classList.remove('show');
                this.setAttribute('aria-label', 'Show password');
            }
        });
    });
}

/**
 * Validate password in real-time as user types
 * Updates UI to show which requirements are met (green checkmark) or unmet (red)
 * Prevents form submission until all requirements are satisfied
 */
function validatePassword(password) {
    const requirements = {
        'req-length': password.length >= PASSWORD_REQUIREMENTS.minLength,
        'req-uppercase': /[A-Z]/.test(password),
        'req-number': /[0-9]/.test(password),
        'req-special': /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
    };

    // Update each requirement display with visual feedback
    let allMet = true;
    for (const [id, passed] of Object.entries(requirements)) {
        const element = document.getElementById(id);
        if (element) {
            if (passed) {
                element.classList.remove('unmet');
                element.classList.add('met');
                // Add checkmark if not already present
                if (!element.textContent.includes('✓')) {
                    element.textContent = '✓ ' + element.textContent;
                }
            } else {
                element.classList.remove('met');
                element.classList.add('unmet');
                // Remove checkmark
                element.textContent = element.textContent.replace('✓ ', '');
                allMet = false;
            }
        }
    }

    return allMet;
}

/**
 * Display message to user (success or error)
 * @param {string} message - The message to display
 * @param {string} type - Message type: 'success' or 'error'
 */
function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    messageDiv.textContent = message;
    messageDiv.className = type;
}

/**
 * Handle registration form submission
 * Validates all fields and sends registration request with CSRF token
 * SECURITY: Validates token server-side before processing registration
 * Shows clear error if token missing/expired with auto-refresh option
 */
function handleRegistrationSubmit(e) {
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const csrfToken = document.getElementById('csrf_token').value;
    const submitBtn = document.querySelector('button[type="submit"]');

    // Client-side validation - Username
    if (username.length < 3) {
        showMessage('Username must be at least 3 characters long', 'error');
        return;
    }

    // Client-side validation - Password requirements (minimum 8 chars, uppercase, number, special)
    if (password.length < 8) {
        showMessage('Password must be at least 8 characters long', 'error');
        return;
    }

    if (!/[A-Z]/.test(password)) {
        showMessage('Password must contain an uppercase letter', 'error');
        return;
    }

    if (!/[0-9]/.test(password)) {
        showMessage('Password must contain a number', 'error');
        return;
    }

    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
        showMessage('Password must contain a special character', 'error');
        return;
    }

    // Ensure "Confirm Password" matches "Password"
    if (password !== confirmPassword) {
        showMessage('Passwords do not match', 'error');
        return;
    }

    // Verify CSRF token is present
    if (!csrfToken) {
        showMessage('Security token missing. Please refresh the page.', 'error');
        // Auto-refresh option
        setTimeout(() => {
            location.reload();
        }, 2000);
        return;
    }

    // Prepare form data for POST request
    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);
    formData.append('confirm_password', confirmPassword);
    formData.append('csrf_token', csrfToken);

    // Disable button to prevent double submission
    submitBtn.disabled = true;
    submitBtn.textContent = 'Registering...';

    // Send registration request to server
    fetch('register.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('Registration successful! Redirecting to login...', 'success');
                // Token automatically assigned to both admin and non-admin users
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                // Handle missing/expired token error from server
                if (data.error && data.error.includes('token')) {
                    showMessage(data.error + ' Refreshing...', 'error');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage(data.error || 'Registration failed. Please try again.', 'error');
                }
                submitBtn.disabled = false;
                submitBtn.textContent = 'Register';
                // Refresh CSRF token on failed attempt
                initializeCSRF();
            }
        })
        .catch(error => {
            showMessage('An error occurred. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Register';
            console.error('Error:', error);
            // Refresh CSRF token on error
            initializeCSRF();
        });
}

// Real-time password validation as user types
const passwordInput = document.getElementById('password');
if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        validatePassword(this.value);
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Get CSRF token from server for ALL accounts (admin and non-admin)
    initializeCSRF();

    // Setup password toggle buttons
    setupPasswordToggle();

    // Attach form submission handler
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegistrationSubmit);
    }
});
