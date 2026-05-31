# Security Functions - Developer Reference Guide

## Quick Start

All authentication system files are now enhanced. **Always include** `auth_check.php` at the top of any protected file:

```php
<?php
include 'auth_check.php';

// Now you can use all security functions
?>
```

---

## Session Management

### Check if User is Authenticated

```php
$user_id = checkAuth();
if (!$user_id) {
    // Not authenticated
}
```

**Returns:** `user_id` (int) if authenticated, `false` otherwise  
**Side Effect:** Validates session timeout, updates last activity

### Require Authentication

```php
requireAuth(); // Dies with redirect if not auth
$user_id = $_SESSION['user_id']; // Now safe to use
```

**Usage:** Put at top of protected pages

### Get Current User Info

```php
$user = getCurrentUser();
if ($user) {
    echo $user['username'];
    echo $user['id'];
}
```

**Returns:** Array with `id` and `username`, or `false`

### Login User

```php
$token = loginUser($user_id, $username);
// Token is returned and stored in $_SESSION
```

**Called After:** Password verification succeeds  
**Side Effect:** Regenerates session ID (prevents fixation)

### Logout User

```php
logoutUser();
// Session destroyed, cookies cleared
```

**Use:** In logout.php handler

---

## Password Validation

### Validate Password Strength

```php
$result = validatePasswordStrength($password);
if (!$result['valid']) {
    foreach ($result['errors'] as $error) {
        echo $error;
    }
}
```

**Returns:**

```php
[
    'valid' => true/false,
    'errors' => ['Error 1', 'Error 2']
]
```

**Requirements Checked:**

- Minimum 8 characters
- Maximum 128 characters
- At least one uppercase letter
- At least one number
- At least one special character

### Example Usage

```php
<?php
include 'auth_check.php';

$password = "NewPass123!";
$pwd_validation = validatePasswordStrength($password);

if (!$pwd_validation['valid']) {
    echo json_encode([
        'success' => false,
        'errors' => $pwd_validation['errors']
    ]);
    exit();
}

// Password valid, proceed with registration
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
?>
```

---

## CSRF Protection

### Generate CSRF Token

```php
$token = generateCSRFToken();
// Token created (or retrieved if already exists)
// Automatically stored in $_SESSION['csrf_token']
```

**In HTML Form:**

```html
<form>
  <input
    type="hidden"
    name="csrf_token"
    value="<?php echo generateCSRFToken(); ?>"
  />
  <!-- form fields -->
</form>
```

### Verify CSRF Token

```php
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!verifyCSRFToken($csrf_token)) {
    http_response_code(403);
    die('Invalid request token');
}
```

**Returns:** `true` if valid, `false` if invalid/expired

**Validation Checks:**

- Token exists in session
- Token matches submitted value
- Token hasn't expired (24 hours)

### Complete Example

```php
<?php
include 'auth_check.php';
header('Content-Type: application/json');

// Verify CSRF
$csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!verifyCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request token']);
    exit();
}

// Safe to process form
$username = $_POST['username'];
// ... handle request
?>
```

---

## Rate Limiting

### Check Rate Limit

```php
$rate_check = checkRateLimit('login_attempts', 5, 3600);
if (!$rate_check['allowed']) {
    $wait = $rate_check['wait_seconds'];
    echo "Please wait " . ceil($wait / 60) . " minutes";
    exit();
}
```

**Parameters:**

- `$action` - Unique action name (e.g., 'login_attempts', 'registration')
- `$max_attempts` - Maximum attempts allowed
- `$window_seconds` - Time window (1 hour = 3600)

**Returns:**

```php
[
    'allowed' => true/false,
    'wait_seconds' => null or int
]
```

### Predefined Limits (in config.php)

```php
MAX_LOGIN_ATTEMPTS = 5          // Per lockout
LOCKOUT_DURATION = 900          // 15 minutes
MAX_REGISTRATION_PER_IP = 3     // Per hour
MAX_LOGIN_ATTEMPTS_PER_IP = 10  // Per hour
```

### Complete Example

```php
<?php
include 'auth_check.php';

// Check registration rate limit
$rate = checkRateLimit('registration', MAX_REGISTRATION_PER_IP, 3600);
if (!$rate['allowed']) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Too many registration attempts. Wait ' .
                   ceil($rate['wait_seconds'] / 60) . ' minutes'
    ]);
    exit();
}

// Safe to proceed with registration
?>
```

---

## Secure Database Queries

### Always Use Config Constants

**Before (❌ Wrong):**

```php
$stmt = $conn->prepare("SELECT * FROM test.users WHERE id = ?");
```

**After (✓ Correct):**

```php
$stmt = $conn->prepare("SELECT * FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE id = ?");
```

**Config Constants Available:**

```php
DB_NAME = 'test'                // Database name
DB_TABLE_USERS = 'users'        // Users table
DB_TABLE_TASKS = 'tasks'        // Tasks table
```

---

## Template: Creating a Secure Handler

```php
<?php
/**
 * Secure Handler Template
 * Copy and adapt this template for new handlers
 */

include 'auth_check.php';

header('Content-Type: application/json');

try {
    // 1. Check authentication
    $user_id = checkAuth();
    if (!$user_id) {
        http_response_code(401);
        throw new Exception('Please log in');
    }

    // 2. Check HTTP method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid request method');
    }

    // 3. Verify CSRF token
    $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verifyCSRFToken($csrf)) {
        http_response_code(403);
        throw new Exception('Invalid request token');
    }

    // 4. Get and validate input
    $input = isset($_POST['input']) ? trim($_POST['input']) : '';
    if (empty($input)) {
        throw new Exception('Input is required');
    }

    // 5. Check length
    if (strlen($input) > 255) {
        throw new Exception('Input is too long');
    }

    // 6. Database operation
    $stmt = $conn->prepare("UPDATE " . DB_NAME . "." . DB_TABLE_TASKS .
                           " SET field = ? WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $stmt->bind_param("sii", $input, $id, $user_id);
    if (!$stmt->execute()) {
        throw new Exception('Operation failed');
    }

    // Success response
    echo json_encode(['success' => true, 'message' => 'Success']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>
```

---

## IP Detection

### Get Client IP (Proxy-Aware)

```php
$ip = getClientIP();
// Returns IPv4/IPv6 or 'unknown'
// Handles proxies via X-Forwarded-For header
```

**Usage:**

```php
// Used internally by rate limiting
// getClientIP() is called automatically by checkRateLimit()
```

---

## Error Handling Pattern

Always use try-catch-finally:

```php
try {
    // Your logic here
    throw new Exception('Something went wrong');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    // Always clean up
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
```

**Benefits:**

- Consistent error messages
- Proper HTTP status codes
- Guaranteed resource cleanup
- Easy logging integration

---

## Session Configuration

### Default Settings

```php
SESSION_TIMEOUT = 3600              // 1 hour
SESSION_SECURE = false              // Set to true in production with HTTPS
SESSION_HTTPONLY = true             // Prevents JavaScript access
SESSION_COOKIE_DURATION = 3600      // Same as timeout
CSRF_TOKEN_EXPIRY = 86400           // 24 hours
```

### Modify Session Settings

Edit `config.php`:

```php
// Make sessions 30 minutes instead of 1 hour
define('SESSION_TIMEOUT', 1800);
define('SESSION_COOKIE_DURATION', 1800);
```

---

## Best Practices Checklist

When writing new features:

- [ ] Always include `auth_check.php` first
- [ ] Call `checkAuth()` at the start
- [ ] Verify CSRF token for POST requests
- [ ] Use prepared statements with `?` placeholders
- [ ] Use config constants for table names
- [ ] Validate input length
- [ ] Use try-catch-finally blocks
- [ ] Set proper HTTP status codes
- [ ] Close statements in finally block
- [ ] Filter queries with `WHERE user_id = ?`

---

## Common Security Mistakes ❌

### 1. Forgetting to Authenticate

```php
// ❌ WRONG - No auth check
$user_id = $_POST['user_id']; // User can spoof ID!

// ✓ CORRECT
$user_id = checkAuth();
```

### 2. No CSRF Protection

```php
// ❌ WRONG - No CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process request

// ✓ CORRECT
if (!verifyCSRFToken($_POST['csrf_token'])) die();
```

### 3. Hardcoded Database Names

```php
// ❌ WRONG
$stmt = $conn->prepare("SELECT * FROM test.users");

// ✓ CORRECT
$stmt = $conn->prepare("SELECT * FROM " . DB_NAME . "." . DB_TABLE_USERS);
```

### 4. Missing Resource Cleanup

```php
// ❌ WRONG - Leaks on error
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit();
// ... code ...
$stmt->close();

// ✓ CORRECT - Always cleanup
try {
    // code
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
```

### 5. No WHERE user_id Filter

```php
// ❌ WRONG - Users can see others' tasks
SELECT * FROM tasks WHERE id = ?

// ✓ CORRECT - Only user's tasks
SELECT * FROM tasks WHERE id = ? AND user_id = ?
```

---

## Testing Functions

### Test Password Strength

```php
<?php
include 'auth_check.php';

$passwords = [
    'weak',           // Fails: too short
    'Weak1',          // Fails: no special char
    'Strong@123',     // Passes!
];

foreach ($passwords as $pwd) {
    $result = validatePasswordStrength($pwd);
    echo $pwd . ': ' . ($result['valid'] ? 'PASS' : 'FAIL') . "\n";
    if (!$result['valid']) {
        foreach ($result['errors'] as $err) {
            echo "  - $err\n";
        }
    }
}
?>
```

### Test Rate Limiting

```php
<?php
include 'auth_check.php';

// Simulate 6 failed attempts
for ($i = 0; $i < 6; $i++) {
    $check = checkRateLimit('test_action', 5, 3600);
    if (!$check['allowed']) {
        echo "Blocked after attempt $i. Wait: " . $check['wait_seconds'] . "s\n";
        break;
    }
    echo "Attempt $i: Allowed\n";
}
?>
```

---

## Additional Resources

- [OWASP Password Guidelines](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [NIST Cybersecurity Framework](https://www.nist.gov/cyberframework)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [CSRF Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)

---

**Last Updated:** 2026-03-13  
**Version:** 2.0 (Enhanced Security)
