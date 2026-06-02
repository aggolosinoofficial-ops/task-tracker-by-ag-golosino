<?php
/**
 * Admin Account Setup Script
 * Creates admin account with username: admin123 and password: Admin_123
 * Run this file ONCE, then delete or rename it for security
 * Access: http://localhost/task-tracker-by-ag-golosino/admin_create.php
 */


include 'config.php';
include 'db.php';
// DO NOT include auth_check.php here - admin setup should work without authentication

header('Content-Type: text/html; charset=UTF-8');

$message = '';
$type = '';

try {
    // Ensure role column exists first
    $check_role = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_NAME='" . DB_TABLE_USERS . "' 
                                AND COLUMN_NAME='role' 
                                AND TABLE_SCHEMA='" . DB_NAME . "'");

    if (!$check_role || $check_role->num_rows == 0) {
        // Add role column if missing
        $alter_sql = "ALTER TABLE " . DB_NAME . "." . DB_TABLE_USERS . " 
                      ADD COLUMN role VARCHAR(20) DEFAULT 'user'";
        if ($conn->query($alter_sql) !== TRUE) {
            throw new Exception('Failed to add role column: ' . $conn->error);
        }
    }

    // Check if admin already exists
    $check = $conn->prepare("SELECT id FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE username = 'admin123'");
    if (!$check) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $message = "✓ Admin account already exists";
        $type = "info";
        $check->close();
    } else {
        $check->close();

        // Create admin account with role
        $username = 'admin123';
        $password = 'Admin_123';
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $role = 'admin';

        $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_USERS . " (username, password_hash, role, created_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $stmt->bind_param("sss", $username, $password_hash, $role);

        if ($stmt->execute()) {
            $message = "✓ Admin account created successfully!<br><strong>Username:</strong> admin123<br><strong>Password:</strong> Admin_123<br><br>⚠️ DELETE this file after viewing for security!";
            $type = "success";
        } else {
            throw new Exception('Failed to create admin account: ' . $stmt->error);
        }
        $stmt->close();
    }
} catch (Exception $e) {
    $message = "✗ Error: " . $e->getMessage();
    $type = "error";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - To-Do App</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }

        .container {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            background: #f9f9f9;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border: 1px solid #bee5eb;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
        }

        a {
            color: #667eea;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Admin Account Setup</h1>

        <?php if ($message): ?>
            <div class="<?php echo $type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <p>
            <a href="login.html">← Back to Login</a>
        </p>
    </div>
</body>

</html>