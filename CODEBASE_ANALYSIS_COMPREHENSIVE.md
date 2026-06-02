# Task Tracker Codebase - Comprehensive Analysis

## Executive Summary

- **Architecture**: PHP/MySQL with XML backup, optimized for 2GB RAM
- **Authentication**: Custom session-based, CSRF-protected
- **Validation**: Multi-layered (client-side JS + server-side PHP + XML Schema)
- **Security Status**: Good practices, but **DEV_MODE enabled** disables critical protections

---

## 1. VALIDATION FUNCTIONS & LOCATIONS

### 1.1 Username Validation

| Location                                     | Rule                                                         | Enforced |
| -------------------------------------------- | ------------------------------------------------------------ | -------- |
| [register.js](register.js#L89)               | Length: 3-30 chars                                           | Client ✓ |
| [register.php](register.php#L79)             | Length: 3-30 chars                                           | Server ✓ |
| [register.js](register.js#L93)               | Regex: `[\w\s\u0080-\uFFFF]` (alphanumeric, spaces, unicode) | Client ✓ |
| [register.php](register.php#L83)             | Regex: `[\w\s\u0080-\uFFFF]`                                 | Server ✓ |
| [check_username.php](check_username.php#L43) | Database uniqueness check                                    | Server ✓ |
| [users.xsd](users.xsd#L11)                   | XML Schema: 3-50 chars                                       | Backup ✓ |

**Problem**: XML schema allows 3-50, PHP enforces 3-30 → inconsistency

### 1.2 Password Validation

| Location                     | Rule                  | Value     | Enforced |
| ---------------------------- | --------------------- | --------- | -------- |
| [config.php](config.php#L28) | Minimum length        | 8 chars   | Both     |
| [config.php](config.php#L29) | Maximum length        | 128 chars | Both     |
| [config.php](config.php#L30) | Require uppercase     | `true`    | Both     |
| [config.php](config.php#L31) | Require numbers       | `true`    | Both     |
| [config.php](config.php#L32) | Require special chars | `true`    | Both     |

**Special character set** (from [auth_check.php](auth_check.php#L378)):

```
!@#$%^&*()_+-=[]{};\'":\|,.<>/?
```

**Client-side pattern** (from [register.js](register.js#L11)):

```javascript
const PASSWORD_SPECIAL_CHARS = /[!@#$%^&*]/;
```

**Problem**: JS only requires 5 special chars `!@#$%^&*`, server accepts much larger set

### 1.3 Email Validation

**Status**: NOT implemented. No email field in any forms or database.

---

## 2. CURRENT VALIDATION RULES ENFORCED

### Server-Side Validation (PHP)

**File**: [auth_check.php](auth_check.php#L368) - `validatePasswordStrength()` function

```php
- MIN_PASSWORD_LENGTH: 8
- MAX_PASSWORD_LENGTH: 128
- PASSWORD_REQUIRE_UPPERCASE: true (regex: /[A-Z]/)
- PASSWORD_REQUIRE_NUMBERS: true (regex: /[0-9]/)
- PASSWORD_REQUIRE_SPECIAL: true (regex: /[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/)
```

### Client-Side Validation (JavaScript)

**File**: [register.js](register.js)

```javascript
Username:
  - Length: 3-30 (client only)
  - Regex: /^[\w\s\u0080-\uFFFF]+$/u
  - Availability check: via check_username.php

Password:
  - Length: 8+ (visual indicator)
  - Uppercase: /[A-Z]/
  - Number: /[0-9]/
  - Special: /[!@#$%^&*]/ (5 char subset!)
```

### Database Schema Validation

**Username constraints**:

- `VARCHAR(50)` max length
- `UNIQUE` constraint on username
- `NOT NULL`

**Password constraints**:

- `VARCHAR(255)` hash storage
- `NOT NULL`

---

## 3. REGISTRATION FLOW

### 3.1 Frontend (register.html → register.js)

**Form Fields**:

1. Username input (`id="username"`)
2. Password input (`id="password"`)
3. Confirm password (`id="confirmPassword"`)
4. Submit button

**Real-Time Validation**:

- Username availability check (500ms debounce)
- Username format validation
- Password requirements display
- Password/confirm match

**Flow**:

```
User Input → Debounce (500ms) → validateUsername() →
POST to check_username.php → Color feedback (green/red) →
On Submit: All validations checked → POST to register.php
```

### 3.2 Backend (register.php)

**Location**: [register.php](register.php)

**Validation Steps**:

1. POST method check
2. Username length (3-30) ✓
3. Username format regex ✓
4. Username database uniqueness ✓
5. Password confirmation match ✓
6. Password strength validation (calls [auth_check.php::validatePasswordStrength()](auth_check.php#L368)) ✓
7. Bcrypt hash (cost=10) ✓

**NO CSRF Token Required** for registration (new users have no session)

**Database**: Insert into `users` table

```sql
INSERT INTO users (username, password_hash, created_at)
VALUES (?, ?, NOW())
```

**XML Sync**: Calls [xml_sync_handler.php](xml_sync_handler.php) → `syncUserToXML()`

**Response**: JSON with `success`, `message`, `user_id`

---

## 4. LOGIN FLOW

### 4.1 Frontend (login.html → login.js)

**Form Fields**:

1. Username input (`id="username"`)
2. Password input (`id="password"`)
3. CSRF token hidden field (`id="csrf_token"`)
4. Sign In button

**Initialization** (page load):

```javascript
1. Fetch CSRF token from get_csrf_token.php
2. Set token in hidden form field
3. Attach form submit handler
4. Setup password toggle
```

**Validation On Submit**:

- Username required
- Password required
- CSRF token present

**Flow**:

```
Page Load → Fetch CSRF Token → POST login.php with:
  - username
  - password
  - csrf_token
→ JSON response with success/error
→ Redirect to index.php on success
```

### 4.2 Backend (login.php)

**Location**: [login.php](login.php)

**Validation Steps**:

1. POST method check
2. CSRF token validation (calls [verifyCSRFToken()](auth_check.php#L279))
3. Rate limiting check (calls [checkRateLimit()](auth_check.php#L316))
4. Username/password required check
5. Database lookup: `SELECT id, username, password_hash FROM users WHERE username = ?`
6. Password verification: `password_verify()` (constant-time)
7. Session regeneration: `session_regenerate_id(true)`

**Rate Limiting**:

- Max: 10 attempts per IP per hour (defined in [config.php](config.php#L40))
- Lockout: 15 minutes (900 seconds)
- **BYPASS**: When `DEV_MODE = true`

**Session Creation** (on success):

```php
$_SESSION['user_id'] = user_id
$_SESSION['username'] = username
$_SESSION['token'] = random token
$_SESSION['login_time'] = time()
$_SESSION['last_activity'] = time()
```

**Response**: JSON with `success`, `user_id`, `username`, `token`, `message`

---

## 5. ADMIN SETUP/CREATION

### 5.1 File: [admin_setup.php](admin_setup.php)

**Purpose**: Initial admin account creation during deployment

**Default Credentials**:

```
Username: admin123
Password: Admin_123
Role: admin
```

**Security**:

- Uses bcrypt hashing (cost=10)
- Prepared statements
- Creates users table if not exists
- Adds `role` column if missing
- Syncs to XML backup

**Intended Usage**:

1. Access via browser: `http://localhost/...task-tracker.../admin_setup.php`
2. View credentials on screen
3. Save to password manager
4. **DELETE or RENAME this file**

**Current Status**: File still exists in repo (security risk)

### 5.2 File: [admin_create.php](admin_create.php)

**Purpose**: Alternative admin creation script (identical to admin_setup.php)

**Same functionality as admin_setup.php**

**Table Schema Created**:

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

---

## 6. XML/DB SCHEMA FILES

### 6.1 Users Schema ([users.xsd](users.xsd))

**User Element Validation**:

```xml
<user>
  <id type="xs:positiveInteger" />
  <username>
    <restriction base="xs:string">
      <minLength value="3" />
      <maxLength value="50" />
    </restriction>
  </username>
  <password_hash type="xs:string" />
  <role type="roleType" />  <!-- enum: user, admin, moderator -->
  <created_at type="xs:dateTime" />
</user>
```

**Inconsistency**: Max username length = 50 in XSD, but PHP enforces 30

### 6.2 Tasks Schema ([tasks.xsd](tasks.xsd))

**Task Element Validation**:

```xml
<task>
  <id type="xs:positiveInteger" />
  <user_id type="xs:positiveInteger" />
  <title>
    <restriction>
      <minLength value="1" />
      <maxLength value="255" />
    </restriction>
  </title>
  <description>
    <restriction>
      <minLength value="0" />
      <maxLength value="1000" />
    </restriction>
  </description>
  <status type="statusType" />  <!-- enum: pending, completed, in_progress, cancelled -->
  <created_at type="xs:dateTime" />
</task>
```

### 6.3 Archived Tasks Schema ([archive_tasks.xsd](archive_tasks.xsd))

**Archived Task Element**:

```xml
<archive_task>
  <id type="xs:positiveInteger" />
  <user_id type="xs:positiveInteger" />
  <title>
    <restriction>
      <minLength value="1" />
      <maxLength value="255" />
    </restriction>
  </title>
  <description>
    <restriction>
      <minLength value="0" />
      <maxLength value="1000" />
    </restriction>
  </description>
  <status type="statusType" />  <!-- enum: pending, completed, in_progress, cancelled -->
  <created_at type="xs:dateTime" />
  <archived_at type="xs:dateTime" />
</archive_task>
```

### 6.4 Deleted Tasks Schema ([deleted_tasks.xsd](deleted_tasks.xsd))

**Similar to archive_tasks with `deleted_at` timestamp**

### 6.5 Database Tables

**users table**:

- `id` (INT, AUTO_INCREMENT, PRIMARY KEY)
- `username` (VARCHAR(50), UNIQUE, NOT NULL)
- `password_hash` (VARCHAR(255), NOT NULL)
- `role` (VARCHAR(20), DEFAULT 'user')
- `created_at` (TIMESTAMP)
- INDEX on `username`

**tasks table**:

- `id`, `user_id`, `title`, `description`, `status`, `created_at`

**Parallel XML files** for backup:

- `users.xml`, `tasks.xml`, `archive_tasks.xml`, `deleted_tasks.xml`

---

## 7. SECURITY TOKEN HANDLING

### 7.1 CSRF Token Generation ([get_csrf_token.php](get_csrf_token.php))

**Endpoint**: `get_csrf_token.php`

**Method**: GET or POST

**Process**:

1. Call [generateCSRFToken()](auth_check.php#L267) from [auth_check.php](auth_check.php)
2. Check if token exists in `$_SESSION['csrf_token']`
3. If not, generate: `bin2hex(random_bytes(32))` (64 hex chars)
4. Set timestamp: `$_SESSION['csrf_token_time'] = time()`
5. Return JSON: `{ success, token, expiry }`

**Expiry**: 24 hours (CSRF_TOKEN_EXPIRY = 86400 seconds)

### 7.2 CSRF Token Verification ([auth_check.php](auth_check.php#L279))

**Function**: `verifyCSRFToken($token)`

**Checks**:

1. Token exists in `$_SESSION['csrf_token']`
2. Timestamp exists in `$_SESSION['csrf_token_time']`
3. Token not expired (24 hours)
4. Constant-time comparison: `hash_equals()`

**Used By**:

- [login.php](login.php#L30) - Login requires CSRF token
- Any authenticated page via [auth_check.php](auth_check.php)

### 7.3 Token Configuration ([config.php](config.php))

```php
define('CSRF_TOKEN_LENGTH', 32);           // bytes for random_bytes()
define('CSRF_TOKEN_EXPIRY', 86400);        // 24 hours
define('SESSION_HTTPONLY', true);          // JavaScript can't access
define('SESSION_SECURE', false);           // Set to true for HTTPS
define('SESSION_TIMEOUT', 3600);           // 1 hour session timeout
```

### 7.4 Registration & CSRF Token

**Key Finding**: NO CSRF token required for registration!

**Reason**: New users have no session yet

**Token Generation**: Created AFTER successful registration

```php
// In register.php after INSERT
$_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
$_SESSION['csrf_token_time'] = time();
```

---

## 8. STRICT VALIDATION CONSTRAINTS

### 8.1 Password Strength

**Requirements** (from [config.php](config.php#L28-32)):

```
Length:        8-128 characters (strict)
Uppercase:     Required (at least 1)
Numbers:       Required (at least 1)
Special Chars: Required (at least 1 from: !@#$%^&*()_+-=[]{};\'":\|,.<>/?​)
```

**Server Enforcement**: Regex patterns + error messages (see [auth_check.php](auth_check.php#L368))

**Client Display**: Visual requirements list (see [register.js](register.js#L117))

**Issue**: Client only checks 5 special chars (`!@#$%^&*`), server accepts larger set

### 8.2 Username Constraints

**Length**: 3-30 characters (strict, enforced both sides)
**Format**: Alphanumeric, spaces, underscores, emoji/unicode only
**Uniqueness**: Database level (UNIQUE constraint) + application check
**Blocked Characters**: SQL special chars (`;'"`) and bash chars (`$()\``)

### 8.3 Rate Limiting

**Configuration** (from [config.php](config.php#L38-40)):

```php
MAX_LOGIN_ATTEMPTS_PER_IP = 10       // per hour
LOCKOUT_DURATION = 900               // 15 minutes
MAX_REGISTRATION_PER_IP = 3          // per hour
```

**Implementation**: [checkRateLimit()](auth_check.php#L316) in auth_check.php

**Session Storage**: Rate limit counters in `$_SESSION['ratelimit_*']`

**BYPASS**: When `DEV_MODE = true` (currently enabled in [config.php](config.php#L45))

### 8.4 Session Constraints

```php
Timeout:           3600 seconds (1 hour)
Cookie Duration:   3600 seconds
HttpOnly:          true (JavaScript can't access)
Secure:            false (should be true for production)
SameSite:          Strict
Name:              todo_app
```

---

## 9. PROBLEM AREAS & INCONSISTENCIES

### 9.1 Critical Issues

| Issue                                   | Location                                                                         | Severity | Impact                            |
| --------------------------------------- | -------------------------------------------------------------------------------- | -------- | --------------------------------- |
| **DEV_MODE enabled**                    | [config.php:45](config.php#L45)                                                  | HIGH     | Rate limiting completely disabled |
| **Default admin credentials hardcoded** | [admin_setup.php](admin_setup.php#L19), [admin_create.php](admin_create.php#L26) | HIGH     | Credentials visible in code       |
| **Setup files not deleted**             | Both setup files exist                                                           | HIGH     | Security exposure                 |
| **No HTTPS enforcement**                | [config.php:39](config.php#L39) `SESSION_SECURE=false`                           | HIGH     | Session hijacking risk            |

### 9.2 Validation Inconsistencies

| Component         | Client      | Server   | XSD  | Problem                       |
| ----------------- | ----------- | -------- | ---- | ----------------------------- |
| Username length   | 3-30        | 3-30     | 3-50 | XSD allows longer             |
| Special chars     | 5 subset    | Full set | N/A  | Client restricts more         |
| Password strength | Visual only | Enforced | N/A  | Client doesn't prevent submit |

### 9.3 Design Issues

| Issue                         | File                            | Impact                        |
| ----------------------------- | ------------------------------- | ----------------------------- |
| No email field                | All                             | Can't recover account         |
| No password recovery          | N/A                             | Permanent account lock        |
| Plaintext password in confirm | [register.js](register.js#L188) | Memory security issue         |
| Session in files              | PHP default                     | Vulnerable to file access     |
| XML backup without encryption | XML files                       | Credentials visible in backup |

### 9.4 Frontend Issues

| Issue                                   | File                            | Line                                   | Problem |
| --------------------------------------- | ------------------------------- | -------------------------------------- | ------- |
| `PASSWORD_SPECIAL_CHARS = /[!@#$%^&*]/` | [register.js](register.js#L11)  | Client doesn't check all special chars |
| `confirmPassword` never hashed          | [register.js](register.js#L188) | Stored in DOM memory                   |
| No rate limit message                   | [login.js](login.js)            | User experience inconsistent           |

---

## 10. SECURITY FEATURES SUMMARY

### Implemented ✓

- Bcrypt password hashing (cost=10)
- CSRF token protection (login only, not registration)
- Prepared statements (SQL injection prevention)
- Session timeout (1 hour)
- Rate limiting (when DEV_MODE off)
- Role-based access (admin/user)
- Password strength requirements
- Username uniqueness enforcement
- HTTP-only cookies
- Constant-time comparison for tokens
- Session regeneration on login

### Missing ✗

- Email verification
- Password recovery mechanism
- Account lockout (soft/temporary)
- Failed login logging
- Two-factor authentication
- Password history
- Secure password reset via email
- Account activity audit trail
- IP whitelist/blacklist

---

## 11. FILE LOCATION QUICK REFERENCE

| Component                | File                                                               | Type          |
| ------------------------ | ------------------------------------------------------------------ | ------------- |
| **Validation Functions** | [auth_check.php](auth_check.php)                                   | PHP           |
| **Password Validation**  | [auth_check.php](auth_check.php#L368) `validatePasswordStrength()` | PHP Function  |
| **Rate Limiting**        | [auth_check.php](auth_check.php#L316) `checkRateLimit()`           | PHP Function  |
| **CSRF Generation**      | [auth_check.php](auth_check.php#L267) `generateCSRFToken()`        | PHP Function  |
| **CSRF Verification**    | [auth_check.php](auth_check.php#L279) `verifyCSRFToken()`          | PHP Function  |
| **Username Check**       | [check_username.php](check_username.php)                           | Endpoint      |
| **Registration**         | [register.php](register.php)                                       | Endpoint      |
| **Login**                | [login.php](login.php)                                             | Endpoint      |
| **CSRF Token Endpoint**  | [get_csrf_token.php](get_csrf_token.php)                           | Endpoint      |
| **Register UI**          | [register.html](register.html)                                     | HTML          |
| **Register Logic**       | [register.js](register.js)                                         | JavaScript    |
| **Login UI**             | [login.html](login.html)                                           | HTML          |
| **Login Logic**          | [login.js](login.js)                                               | JavaScript    |
| **Admin Setup**          | [admin_setup.php](admin_setup.php)                                 | Setup Script  |
| **Admin Create**         | [admin_create.php](admin_create.php)                               | Setup Script  |
| **Config**               | [config.php](config.php)                                           | Configuration |
| **Database**             | [db.php](db.php)                                                   | Connection    |
| **Auth Helper**          | [auth_check.php](auth_check.php)                                   | Utilities     |
| **User Schema**          | [users.xsd](users.xsd)                                             | XSD           |
| **Tasks Schema**         | [tasks.xsd](tasks.xsd)                                             | XSD           |
| **Archive Schema**       | [archive_tasks.xsd](archive_tasks.xsd)                             | XSD           |
| **Deleted Schema**       | [deleted_tasks.xsd](deleted_tasks.xsd)                             | XSD           |

---

## 12. CONFIGURATION CONSTANTS

**All defined in [config.php](config.php)**:

```php
// Database
DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASS = '' (empty)
DB_NAME = 'test'
DB_TABLE_USERS = 'users'

// Session
SESSION_TIMEOUT = 3600 (1 hour)
TOKEN_LENGTH = 32 bytes
SESSION_NAME = 'todo_app'
SESSION_SECURE = false
SESSION_HTTPONLY = true

// Password Policy
MIN_PASSWORD_LENGTH = 8
MAX_PASSWORD_LENGTH = 128
PASSWORD_REQUIRE_UPPERCASE = true
PASSWORD_REQUIRE_NUMBERS = true
PASSWORD_REQUIRE_SPECIAL = true

// Rate Limiting
MAX_LOGIN_ATTEMPTS = 5 (unused?)
LOCKOUT_DURATION = 900 (15 min)
MAX_REGISTRATION_PER_IP = 3 (per hour)
MAX_LOGIN_ATTEMPTS_PER_IP = 10 (per hour)

// CSRF
CSRF_TOKEN_LENGTH = 32 bytes
CSRF_TOKEN_EXPIRY = 86400 (24 hours)

// Development
DEV_MODE = true (DISABLES ALL RATE LIMITING!)
```

---

## RECOMMENDATIONS

### Immediate (Security Critical)

1. **Disable DEV_MODE** in [config.php](config.php#L45)
2. **Delete/rename** [admin_setup.php](admin_setup.php) and [admin_create.php](admin_create.php)
3. **Change default admin** password from `Admin_123`
4. **Enable HTTPS** and set `SESSION_SECURE = true`

### Short-term (Security Important)

1. Unify special character validation between client/server
2. Remove password confirmation from DOM (hash it client-side)
3. Add email field and password recovery
4. Implement failed login attempt logging
5. Fix XSD schema max username length (50 → 30)

### Long-term (Security Enhancement)

1. Implement two-factor authentication
2. Add account activity audit trail
3. Session storage in database instead of files
4. Encrypt XML backup files
5. Add password strength meter
6. Implement account lockout (permanent with admin unlock)
