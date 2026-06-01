// Username validation pattern - allows letters, numbers, emojis, spaces
// Blocks: SQL injection chars (;'"), bash/command chars ($()`), special chars (!@#%^&*<>|&)
const PASSWORD_SPECIAL_CHARS = /[!@#$%^&*]/;

function initPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const input = btn.previousElementSibling;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.classList.toggle('show', isPassword);
        });
    });
}

function validateUsername(username) {
    if (!username || username.length < 3 || username.length > 30) {
        return 'Username must be 3-30 characters long';
    }
    if (!/^[\w\s\u0080-\uFFFF]+$/.test(username)) {
        return 'Username contains invalid characters';
    }
    return null;
}

function validatePasswordRequirements(password) {
    return {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        number: /[0-9]/.test(password),
        special: PASSWORD_SPECIAL_CHARS.test(password)
    };
}

function updatePasswordRequirements() {
    const password = document.getElementById('password').value;
    const reqs = validatePasswordRequirements(password);
    const reqsDiv = document.getElementById('passwordReqs');
    
    if (password.length > 0) {
        reqsDiv.style.display = 'block';
    } else {
        reqsDiv.style.display = 'none';
    }

    document.getElementById('req-length').classList.toggle('met', reqs.length);
    document.getElementById('req-upper').classList.toggle('met', reqs.uppercase);
    document.getElementById('req-number').classList.toggle('met', reqs.number);
    document.getElementById('req-special').classList.toggle('met', reqs.special);
}

function showMessage(text, type) {
    const msg = document.getElementById('message');
    msg.textContent = text;
    msg.className = type;
    msg.style.display = 'block';
    
    if (type === 'success') {
        setTimeout(() => {
            msg.style.display = 'none';
        }, 3000);
    }
}

function handleRegistrationSubmit(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    // Validate username
    const usernameError = validateUsername(username);
    if (usernameError) {
        showMessage(usernameError, 'error');
        return;
    }
    
    // Validate password requirements
    const reqs = validatePasswordRequirements(password);
    if (!reqs.length || !reqs.uppercase || !reqs.number || !reqs.special) {
        showMessage('Password must meet all requirements', 'error');
        return;
    }
    
    // Validate matching passwords
    if (password !== confirmPassword) {
        showMessage('Passwords do not match', 'error');
        return;
    }
    
    // Submit to server (no CSRF token required for new user registration)
    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);
    
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
            showMessage(data.message || 'Registration failed', 'error');
        }
    })
    .catch(err => {
        showMessage('Connection error: ' + err.message, 'error');
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
    document.getElementById('password').addEventListener('input', updatePasswordRequirements);
    document.getElementById('registerForm').addEventListener('submit', handleRegistrationSubmit);
});