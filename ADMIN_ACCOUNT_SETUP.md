# 🔐 Admin Account & Initial Setup

## Default Admin Account

| Property | Value |
|----------|-------|
| **Username** | `admin123` |
| **Raw Password** | `Admin_123` |
| **Role** | `admin` |
| **Purpose** | Demo & Testing |
| **Storage** | Bcrypt hash (cost=10) in users.xml and MySQL |

---

## ⚠️ IMPORTANT: First Login

### For Production Deployments

**Before putting this system live, MUST DO:**

1. ✅ **Change Admin Password Immediately**
   ```php
   // Admin login page should show:
   "First login detected. Please change your password."
   ```

2. ✅ **Enforce Password Change**
   ```php
   // In dashboard.php, check if user is 'admin' on first login
   if ($_SESSION['username'] === 'admin123') {
       // Redirect to password change page
       header('Location: change_password.php?forced=1');
   }
   ```

3. ✅ **Update Password Policy**
   - Minimum 8 characters
   - Must contain uppercase letter
   - Must contain number
   - Must contain special character

### For Testing/Demo

You can use the default credentials:
- Username: `admin123`
- Password: `Admin_123`

---

## 🔧 Creating the Admin Account

### Automatic (Recommended)

If you have a setup script (create one):

```php
<?php
require_once 'storage_adapter.php';

// Create admin account
$adminPassword = 'Admin_123';
$adminHash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 10]);

$result = $storageAdapter->registerUser('admin123', $adminHash, 'admin');

if ($result['success']) {
    echo "Admin account created successfully!\n";
    echo "Username: admin123\n";
    echo "Password: Admin_123 (CHANGE THIS IN PRODUCTION)\n";
} else {
    echo "Error: " . $result['error']\n";
}
?>
```

### Manual (If needed)

1. Register via registration page:
   - Username: `admin123`
   - Password: `Admin_123`
   - Confirm: `Admin_123`

2. Update database manually (XML):
   ```xml
   <user id="1">
     <username>admin123</username>
     <password_hash>$2y$10$...</password_hash>
     <role>admin</role>
     <created_at>2026-06-01 12:00:00</created_at>
   </user>
   ```

---

## 🔐 Hash Verification

### Verify the Hash

```php
<?php
// Test password verification
$password = 'Admin_123';
$hash = '$2y$10$...';  // From users.xml

if (password_verify($password, $hash)) {
    echo "✓ Password matches!";
} else {
    echo "✗ Password does not match";
}
?>
```

### Generate Your Own Hash

```php
<?php
// Create hash for new password
$password = 'YourNewPassword123!';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

echo "Hash: " . $hash;

// This can be inserted into users.xml manually
?>
```

---

## 📝 Setting Up Admin Role

### Add Permission Checks

In your admin pages, check role:

```php
<?php
require_once 'auth_check.php';

function requireAdmin() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        die('Access Denied: Admin privileges required');
    }
}

requireAuth();
requireAdmin();  // Add this to admin pages

// Admin page content here
?>
```

### Admin Panel Setup

```php
<?php
require_once 'auth_check.php';
requireAuth();
requireAdmin();
?>

<h1>Admin Dashboard</h1>

<section>
    <h2>User Management</h2>
    <!-- User list, create, edit, delete -->
</section>

<section>
    <h2>System Health</h2>
    <button onclick="checkStorageStatus()">Check XML/MySQL Status</button>
    <button onclick="compactXML()">Optimize XML Files</button>
    <button onclick="rebuildMySQL()">Rebuild MySQL (if corrupted)</button>
</section>
```

---

## 🧪 Testing the Admin Account

### Login Test

```bash
# 1. Start your app
php -S localhost:8000

# 2. Go to login page
# http://localhost:8000/task-tracker-by-ag-golosino/login.html

# 3. Enter credentials
# Username: admin123
# Password: Admin_123

# 4. Should login successfully ✓
```

### Verify Admin Status

```php
<?php
// Add to any page after login
echo "User: " . $_SESSION['username'];
echo "Role: " . $_SESSION['user_role'];
// Should show:
// User: admin123
// Role: admin
?>
```

---

## 🔄 Password Reset Procedure

### If Admin Password Forgotten

```bash
# 1. Use Python to rebuild from MySQL
python3 xml_sync_optimizer.py --restore

# 2. Or manually create new admin in XML
# Edit users.xml, change password_hash to new hash

# 3. Or create new admin account
# Run setup script to create second admin
```

### Safe Reset Script

```php
<?php
// admin_reset.php - SECURE THIS FILE!
if ($_POST && $_POST['admin_key'] === 'YOUR_SECRET_KEY') {
    require_once 'storage_adapter.php';
    
    $newPassword = $_POST['new_password'];
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
    
    // Update in storage
    $result = $storageAdapter->updateAdmin('admin123', $newHash);
    
    echo "Admin password reset successfully";
}
?>
```

---

## 📋 Security Checklist

### Before Production

- [ ] Change default admin password
- [ ] Enforce password change on first login
- [ ] Add password complexity validation
- [ ] Create secondary admin account
- [ ] Remove this setup documentation
- [ ] Store users.xml above webroot
- [ ] Set file permissions to 755
- [ ] Enable HTTPS
- [ ] Enable CSRF protection
- [ ] Test admin panel thoroughly

### Monthly Review

- [ ] Check admin account access logs
- [ ] Review user accounts
- [ ] Test password reset
- [ ] Verify backup strategy
- [ ] Check file permissions

---

## 🆘 Troubleshooting

### "Admin account won't login"

```bash
# 1. Verify admin exists
grep -i "admin123" users.xml

# 2. Verify password
echo 'Admin_123' | php -r "echo password_hash(file_get_contents('php://stdin'), PASSWORD_BCRYPT, ['cost' => 10]);"

# 3. Check session setup
grep SESSION_NAME config.php
```

### "Got 'role not defined' error"

```php
// Add to auth check
$_SESSION['user_role'] = $_SESSION['user_role'] ?? 'user';

// Or ensure role is set during login
$user = $storageAdapter->getUserByUsername($username);
$_SESSION['user_role'] = $user['role'] ?? 'user';
```

---

## 📖 Related Files

- `config.php` - Contains session and security settings
- `auth_check.php` - Contains authentication logic
- `storage_adapter.php` - User registration and retrieval
- `users.xml` - User data storage

---

**Admin Account Setup Complete!**

Next: Integrate storage adapter, test login, then change password before production deployment.

