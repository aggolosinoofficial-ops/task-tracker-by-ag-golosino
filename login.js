/**
 * Login Page JavaScript
 * Handles password visibility toggle and CSRF token management
 */

function initializeCSRF() {
    fetch('get_csrf_token.php')
        .then(response => response.json())
        .then(data => {
            if (data.token) {
                document.getElementById('csrf_token').value = data.token;
            } else {
                showMessage('Security setup error. Please try again.', 'error');
            }
        })
        .catch(error => {
            console.error('CSRF token error:', error);
            showMessage('Connection error. Please check your internet.', 'error');
        });
}

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

function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    messageDiv.textContent = message;
    messageDiv.className = type;
}

function handleLoginSubmit(e) {
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const csrfToken = document.getElementById('csrf_token').value;
    const submitBtn = document.querySelector('button[type="submit"]');

    if (!username || !password) {
        showMessage('Please enter username and password', 'error');
        return;
    }

    if (!csrfToken) {
        showMessage('Security token missing. Retrying...', 'error');
        initializeCSRF();
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in...';

    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);
    formData.append('csrf_token', csrfToken);

    fetch('login.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('Login successful!', 'success');
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                showMessage(data.error || 'Login failed', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign In';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error during login. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Sign In';
        });
}

document.addEventListener('DOMContentLoaded', function() {
    initializeCSRF();
    initPasswordToggle();
    
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLoginSubmit);
    }
});
