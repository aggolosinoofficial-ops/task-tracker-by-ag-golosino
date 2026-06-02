<?php
/**
 * Test admin creation with XML only (no database)
 * This proves the XML-first architecture works independently
 */

header('Content-Type: text/html; charset=UTF-8');

$message = '';
$type = '';

try {
    $users_xml_path = __DIR__ . '/users.xml';
    echo "<h2>XML-First Admin Test</h2>";
    echo "<p>Testing: " . $users_xml_path . "</p>";
    
    // Check if admin exists in XML
    if (file_exists($users_xml_path)) {
        $xml = simplexml_load_file($users_xml_path);
        if ($xml) {
            foreach ($xml->user as $user) {
                if ((string)$user->username === 'admin123') {
                    echo "<p style='color:green;'>✓ Admin123 found in XML!</p>";
                    echo "<pre>";
                    var_dump($user);
                    echo "</pre>";
                    exit;
                }
            }
        }
    }
    
    echo "<p style='color:blue;'>Admin123 not found, creating...</p>";
    
    // Create admin
    $username = 'admin123';
    $password = 'Admin_123';
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $role = 'admin';
    $created_at = date('Y-m-d\TH:i:s');
    
    if (!file_exists($users_xml_path)) {
        file_put_contents($users_xml_path, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<users></users>');
    }
    
    $xml = simplexml_load_file($users_xml_path);
    
    // Find next ID
    $max_id = 0;
    foreach ($xml->user as $u) {
        $current_id = (int)$u->id;
        if ($current_id > $max_id) $max_id = $current_id;
    }
    $new_id = $max_id + 1;
    
    $user_element = $xml->addChild('user');
    $user_element->addChild('id', $new_id);
    $user_element->addChild('username', htmlspecialchars($username));
    $user_element->addChild('password_hash', $password_hash);
    $user_element->addChild('role', $role);
    $user_element->addChild('created_at', $created_at);
    
    $xml->asXML($users_xml_path);
    
    echo "<p style='color:green;'>✓ Admin123 created in XML successfully!</p>";
    echo "<p>ID: " . $new_id . "</p>";
    echo "<p>Password Hash: " . substr($password_hash, 0, 20) . "...</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Error: " . $e->getMessage() . "</p>";
}
?>
