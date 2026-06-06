<?php
/**
 * Admin Account Setup Script
 * 
 * PURPOSE: Initialize or reset the admin account during system setup
 * This script should be run ONCE during initial deployment
 * 
 * FUNCTIONALITY:
 * 1. Creates users table if it doesn't exist
 * 2. Creates or updates admin account with secure credentials
 * 3. Adds role column to track admin vs user privileges
 * 4. Syncs admin to XML backup
 * 5. Initializes CSRF token for admin first login
 * 
 * SECURITY:
 * - Uses bcrypt hashing (cost=10) for low-RAM optimization
 * - Sets role='admin' to grant administrative privileges
 * - Pre-generates CSRF token for immediate use after login
 * 
 * INSTRUCTIONS:
 * 1. Access this file via browser: http://localhost/path/to/admin_setup.php
 * 2. Review admin credentials displayed on screen
 * 3. Save credentials in a secure location (password manager)
 * 4. Delete or rename this file after setup
 * 5. Log in with admin credentials at login.html
 */

include 'config.php';
include 'db.php';

// Check if the sync handler exists before including it
if (!file_exists('xml_sync_handler.php')) {
    die("Setup Error: xml_sync_handler.php not found. Please ensure all files are uploaded.");
}
include 'xml_sync_handler.php';
// Prevent unauthorized access after setup
if (file_exists('setup.lock')) {
    die("<h1>Access Denied</h1><p>Setup has already been completed. To run again, delete the <code>setup.lock</code> file from the server.</p>");
}

header('Content-Type: text/html; charset=UTF-8');

$setup_complete = false;
$admin_username = 'admin123';
$admin_password = 'Admin_123';
$admin_id = null;
$messages = [];

try {
    /**
     * SETUP: Create users table if not exists
     * Defines schema with all required columns:
     * - id: unique identifier (primary key)
     * - username: unique login name
     * - password_hash: bcrypt hashed password
     * - role: 'admin' or 'user' (controls access level)
     * - created_at: account creation timestamp
     * - Index on username for fast login lookups
     */
    $sql_users = "CREATE TABLE IF NOT EXISTS " . DB_NAME . "." . DB_TABLE_USERS . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_users) === TRUE) {
        $messages[] = "✓ Users table created/verified";
    } else {
        throw new Exception("Error creating users table: " . $conn->error);
    }

    /**
     * SECURITY: Hash admin password using bcrypt
     * cost=10 = ~50 iterations (2^10)
     * Takes ~200-300ms per hash = defeats brute force without being too slow for login
     * Cost can be increased later if needed without rehashing (password_needs_rehash() function)
     */
    $password_hash = password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 10]);

    /**
     * SETUP: Check if admin account already exists
     * Prepared statement prevents SQL injection
     * Allows update of existing admin or creation of new one
     */
    $stmt = $conn->prepare("SELECT id FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE username = ?");
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("s", $admin_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        /**
         * UPDATE: Admin already exists - reset credentials
         * Updates password_hash and ensures role='admin'
         * Useful for recovery or credential reset procedures
         */
        $admin_row = $result->fetch_assoc();
        $admin_id = $admin_row['id'];
        $stmt->close();

        $stmt = $conn->prepare("UPDATE " . DB_NAME . "." . DB_TABLE_USERS . " 
                                SET password_hash = ?, role = 'admin' 
                                WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("ss", $password_hash, $admin_username);
        if ($stmt->execute()) {
            $messages[] = "✓ Admin account updated with new credentials";
        } else {
            throw new Exception("Error updating admin account: " . $stmt->error);
        }
        $stmt->close();
    } else {
        /**
         * CREATE: Admin doesn't exist - create new account
         * Sets role='admin' to give full system access
         * created_at timestamp set by MySQL automatically
         */
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_USERS . " 
                                (username, password_hash, role, created_at) 
                                VALUES (?, ?, 'admin', NOW())");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("ss", $admin_username, $password_hash);
        if ($stmt->execute()) {
            $admin_id = $stmt->insert_id;
            $messages[] = "✓ Admin account created successfully";
        } else {
            throw new Exception("Error creating admin account: " . $stmt->error);
        }
        $stmt->close();
    }

    /**
     * SETUP: Ensure role column exists
     * Checks INFORMATION_SCHEMA to see if column already exists
     * Only adds if missing (prevents error on re-runs)
     * Role column is essential for admin vs user distinction
     */
    $check_role = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_NAME='" . DB_TABLE_USERS . "' 
                                AND COLUMN_NAME='role' 
                                AND TABLE_SCHEMA='" . DB_NAME . "'");

    if ($check_role && $check_role->num_rows == 0) {
        if (
            $conn->query("ALTER TABLE " . DB_NAME . "." . DB_TABLE_USERS . " 
                          ADD COLUMN role VARCHAR(20) DEFAULT 'user'") === TRUE
        ) {
            $messages[] = "✓ Role column added to users table";
        }
    }

    /**
     * BACKUP: Sync admin account to XML users file
     * Creates backup copy of admin account in users.xml
     * Ensures data consistency between database and XML
     * If database fails, admin data can be recovered from XML
     */
    if ($admin_id) {
        $sync = getXMLSyncHandler();
        $sync->syncUserToXML($admin_id, $admin_username, $password_hash, 'admin', date('Y-m-d H:i:s'));
        $messages[] = "✓ Admin account synced to XML backup";
    }

    /**
     * SECURITY: Initialize CSRF token for admin
     * Generates random token for session
     * Token stored in $_SESSION (server-side only)
     * Ready for immediate use when admin logs in
     * Prevents unauthorized form submissions
     */
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    $_SESSION['csrf_token_time'] = time();
    $messages[] = "✓ CSRF token generated for secure form handling";

    $setup_complete = true;

} catch (Exception $e) {
    $messages[] = "✗ " . $e->getMessage();
}

// Create a lock file to prevent future re-runs
file_put_contents('setup.lock', 'locked');

if ($conn) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Account Setup - To-Do App</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .credentials-box {
            background: #f0f0f0;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
        }

        .credentials-box p {
            margin: 10px 0;
            color: #333;
        }

        .credentials-box strong {
            color: #667eea;
            display: inline-block;
            width: 120px;
        }

        .message {
            padding: 12px 15px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
            background: #f0f8f4;
            border-radius: 4px;
            color: #155724;
            font-size: 14px;
        }

        .message.error {
            border-left-color: #dc3545;
            background: #fef5f5;
            color: #721c24;
        }

        .success-section {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .success-section h3 {
            color: #155724;
            margin-bottom: 15px;
        }

        .success-section p {
            color: #155724;
            margin: 10px 0;
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn.secondary {
            background: #6c757d;
        }

        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
            font-size: 13px;
        }

        .warning strong {
            display: block;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔐 Admin Account Setup</h1>
        <p class="subtitle">Initialize admin credentials for To-Do App</p>

        <?php foreach ($messages as $msg): ?>
            <div class="message <?php echo strpos($msg, '✗') === 0 ? 'error' : ''; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endforeach; ?>

        <?php if ($setup_complete): ?>
            <div class="success-section">
                <h3>✓ Admin Account Setup Complete!</h3>
                <p>Your admin account has been successfully configured.</p>
            </div>

            <div class="credentials-box">
                <p><strong>Username:</strong> <code><?php echo htmlspecialchars($admin_username); ?></code></p>
                <p><strong>Password:</strong> <code><?php echo htmlspecialchars($admin_password); ?></code></p>
            </div>

            <div class="warning">
                <strong>⚠️ Important Security Notes:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Login immediately and change your password</li>
                    <li>Use a strong, unique password for production</li>
                    <li>Delete this file after setup</li>
                    <li>Never share these credentials</li>
                </ul>
            </div>

            <div class="action-buttons">
                <a href="login.html" class="btn">Go to Login</a>
                <a href="index.php" class="btn secondary">Go to App</a>
            </div>
        <?php else: ?>
            <div class="warning">
                <strong>⚠️ Setup Failed</strong>
                <p>Please check the error messages above and try again.</p>
            </div>
            <div class="action-buttons">
                <button class="btn" onclick="location.reload()">Retry</button>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>