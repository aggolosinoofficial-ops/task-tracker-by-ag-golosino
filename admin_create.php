<?php
/**
 * Admin Account Setup Script (XML-FIRST ARCHITECTURE)
 * Creates admin account with username: admin123 and password: Admin_123
 * PRIMARY: Writes to users.xml (OLTP)
 * SECONDARY: Syncs to database (OLAP) if available
 * 
 * Run this file ONCE, then delete or rename it for security
 * Access: http://localhost/task-tracker-by-ag-golosino/admin_create.php
 */

include 'config.php';
// Do NOT include validation.php - it includes db.php which can hang
// We work with XML first without database dependency

header('Content-Type: text/html; charset=UTF-8');

$message = '';
$type = '';
$admin_xml_exists = false;
$admin_db_exists = false;

try {
    // Step 1: Check if admin exists in XML (PRIMARY STORAGE)
    $users_xml_path = __DIR__ . '/users.xml';
    if (file_exists($users_xml_path)) {
        try {
            $xml = simplexml_load_file($users_xml_path);
            if ($xml) {
                foreach ($xml->user as $user) {
                    if ((string)$user->username === 'admin123') {
                        $admin_xml_exists = true;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error reading XML: " . $e->getMessage());
        }
    }

    // Step 2: Check if admin exists in DATABASE (SECONDARY STORAGE)
    $admin_db_exists = false;
    try {
        include 'db.php';
        if ($conn && !$conn->connect_error) {
            $check = $conn->prepare("SELECT id FROM " . DB_NAME . "." . DB_TABLE_USERS . " WHERE username = 'admin123' LIMIT 1");
            if ($check) {
                $check->execute();
                $result = $check->get_result();
                $admin_db_exists = ($result->num_rows > 0);
                $check->close();
            }
        }
    } catch (Exception $e) {
        error_log("Database check skipped: " . $e->getMessage());
    }

    // Step 3: If admin exists in either XML or DB, inform user
    if ($admin_xml_exists || $admin_db_exists) {
        $message = "✓ Admin account already exists";
        if ($admin_xml_exists) $message .= "<br>✓ Found in XML storage (primary)";
        if ($admin_db_exists) $message .= "<br>✓ Found in database (secondary)";
        $type = "info";
    } else {
        // Step 4: Create admin account
        $username = 'admin123';
        $password = 'Admin_123';
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $role = 'admin';
        $created_at = date('Y-m-d\\TH:i:s');

        // Step 4a: CREATE IN XML FIRST (PRIMARY)
        $xml_success = false;
        try {
            if (!file_exists($users_xml_path)) {
                $xml_content = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<users></users>';
                file_put_contents($users_xml_path, $xml_content);
            }
            
            $xml = simplexml_load_file($users_xml_path);
            $user_element = $xml->addChild('user');
            
            // Find next ID
            $max_id = 0;
            foreach ($xml->user as $u) {
                $current_id = (int)$u->id;
                if ($current_id > $max_id) $max_id = $current_id;
            }
            $new_id = $max_id + 1;
            
            $user_element->addChild('id', $new_id);
            $user_element->addChild('username', htmlspecialchars($username));
            $user_element->addChild('password_hash', $password_hash);
            $user_element->addChild('role', $role);
            $user_element->addChild('created_at', $created_at);
            
            $xml->asXML($users_xml_path);
            $xml_success = true;
        } catch (Exception $e) {
            error_log("Failed to create admin in XML: " . $e->getMessage());
            throw new Exception('Failed to create admin account in XML storage: ' . $e->getMessage());
        }

        // Step 4b: SYNC TO DATABASE (SECONDARY) - optional, don't fail if unavailable
        $db_sync_status = "Not attempted";
        try {
            if (isset($conn) && $conn && !$conn->connect_error) {
                // Ensure role column exists
                $check_role = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                                            WHERE TABLE_NAME='" . DB_TABLE_USERS . "' 
                                            AND COLUMN_NAME='role' 
                                            AND TABLE_SCHEMA='" . DB_NAME . "'");
                if (!$check_role || $check_role->num_rows == 0) {
                    $conn->query("ALTER TABLE " . DB_NAME . "." . DB_TABLE_USERS . " 
                                  ADD COLUMN role VARCHAR(20) DEFAULT 'user'");
                }

                $stmt = $conn->prepare("INSERT INTO " . DB_NAME . "." . DB_TABLE_USERS . " (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssss", $username, $password_hash, $role, $created_at);
                    if ($stmt->execute()) {
                        $db_sync_status = "Synced to database";
                    } else {
                        $db_sync_status = "Database sync failed (non-critical)";
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            $db_sync_status = "Database unavailable (OK, using XML)";
            error_log("Non-critical: Database sync failed: " . $e->getMessage());
        }

        // Step 5: Return success if XML creation succeeded
        if ($xml_success) {
            $message = "✓ Admin account created successfully!<br><br>";
            $message .= "<strong>Username:</strong> admin123<br>";
            $message .= "<strong>Password:</strong> Admin_123<br><br>";
            $message .= "📦 Stored in: XML (primary storage)<br>";
            $message .= "🔄 Database: " . $db_sync_status . "<br><br>";
            $message .= "⚠️ <strong>DELETE this file after viewing for security!</strong>";
            $type = "success";
        }
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