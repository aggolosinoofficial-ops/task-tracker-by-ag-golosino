I apologize for cutting the code short. I understand you need the full, original file including the styling so you can drop it directly into your project without losing any layout.

Here is the complete `admin_promote.php` file. I have integrated the **CSRF security fix** and the **database include fix** into your original structure. You can copy this entire block, paste it into your file, and it should work perfectly with your existing CSS.

```php
<?php
/**
 * Admin User Promotion Tool
 * Promotes a regular user to admin status
 */

session_start();
include 'config.php';
require_once 'db.php'; // Fixed: Included at the top

// Generate CSRF token if one doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

header('Content-Type: text/html; charset=UTF-8');

$message = '';
$type = '';
$current_users = [];

try {
    // Get action
    $action = isset($_POST['action']) ? trim($_POST['action']) : (isset($_GET['action']) ? trim($_GET['action']) : '');
    $target_username = isset($_POST['username']) ? trim($_POST['username']) : (isset($_GET['username']) ? trim($_GET['username']) : '');
    
    // Load users from XML
    $users_xml_path = __DIR__ . '/users.xml';
    if (!file_exists($users_xml_path)) {
        throw new Exception('users.xml not found. Register some users first.');
    }
    
    $xml = simplexml_load_file($users_xml_path);
    if (!$xml) {
        throw new Exception('Failed to load users.xml');
    }
    
    // Build user list
    foreach ($xml->user as $user) {
        $current_users[] = [
            'id' => (int)$user->id,
            'username' => (string)$user->username,
            'role' => (string)$user->role,
            'created_at' => (string)$user->created_at
        ];
    }
    
    // Process promotion/demotion
    if ($action && $target_username) {
        // Security: Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid security token. Action aborted.');
        }

        $found = false;
        foreach ($xml->user as $user) {
            if ((string)$user->username === $target_username) {
                $found = true;
                $old_role = (string)$user->role;
                $new_role = ($action === 'promote') ? 'admin' : 'user';
                
                if (($action === 'promote' && $old_role !== 'admin') || ($action === 'demote' && $old_role === 'admin')) {
                    $user->role = $new_role;
                    $xml->asXML($users_xml_path);
                    
                    // DB Sync
                    if (isset($conn) && $conn && !$conn->connect_error) {
                         // Define the variable first to hold the integer value
                        $user_id = (int)$user->id;

                        $stmt = $conn->prepare("UPDATE " . DB_NAME . "." . DB_TABLE_USERS . " SET role = ? WHERE id = ?");
                        // Use the variable $user_id instead of (int)$user->id
                        $stmt->bind_param('si', $new_role, $user_id);
                        $stmt->execute();
                        $message = "✓ User '$target_username' is now " . strtoupper($new_role) . " (DB synced)";
                        $stmt->close();
                    } else {
                        $message = "✓ User '$target_username' is now " . strtoupper($new_role);
                    }
                    $type = 'success';
                } else {
                    $message = "User '$target_username' is already " . strtoupper($old_role);
                    $type = 'info';
                }
                break;
            }
        }
        
        if (!$found) {
            $message = "✗ User '$target_username' not found";
            $type = 'error';
        }
    }
    
} catch (Exception $e) {
    $message = "✗ " . $e->getMessage();
    $type = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 600px; width: 100%; padding: 40px; }
        h1 { color: #333; margin-bottom: 30px; text-align: center; font-size: 28px; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-weight: 500; border-left: 5px solid; }
        .message.success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .message.error { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .message.info { background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; font-size: 14px; }
        select, input[type="text"] { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; font-family: inherit; transition: border-color 0.3s; }
        select:focus, input[type="text"]:focus { outline: none; border-color: #667eea; }
        .button-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        button { padding: 12px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 14px; }
        .btn-promote { background: #28a745; color: white; }
        .btn-promote:hover { background: #218838; }
        .btn-demote { background: #dc3545; color: white; }
        .btn-demote:hover { background: #c82333; }
        .users-table { margin-top: 40px; border-collapse: collapse; width: 100%; }
        .users-table th { padding: 12px; text-align: left; color: #333; font-weight: 600; border-bottom: 2px solid #ddd; font-size: 13px; }
        .users-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .role-badge.admin { background: #d4edda; color: #155724; }
        .role-badge.user { background: #d1ecf1; color: #0c5460; }
        .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px; font-size: 13px; color: #666; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <h1>👤 Admin User Management</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-group">
                <label for="username">Select User:</label>
                <select name="username" id="username" required>
                    <option value="">-- Choose a user --</option>
                    <?php foreach ($current_users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['username']); ?>">
                            <?php echo htmlspecialchars($user['username']); ?> (<?php echo strtoupper($user['role']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="button-group">
                <button type="submit" name="action" value="promote" class="btn-promote">⬆️ Promote to Admin</button>
                <button type="submit" name="action" value="demote" class="btn-demote">⬇️ Demote to User</button>
            </div>
        </form>
        
        <div class="info-box">
            <strong>ℹ️ How it works:</strong><br>
            • Changes are saved to users.xml and synced to the database.
        </div>
        
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($current_users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td>
                        <span class="role-badge <?php echo strtolower($user['role']); ?>">
                            <?php echo strtoupper($user['role']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

```