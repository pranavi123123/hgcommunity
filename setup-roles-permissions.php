<?php
// One-time script to set up roles and permissions system
// Run this once: http://localhost/hg-community/setup-roles-permissions.php

require_once 'config/database.php';

echo "<h2>HG Community - Roles & Permissions Setup</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p style='color: green;'>✅ Connected to database successfully!</p>";
    
    // Create new tables for roles and permissions
    $tables = [
        "roles" => "CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            description TEXT,
            level INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "role_permissions" => "CREATE TABLE IF NOT EXISTS role_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_id INT,
            permission VARCHAR(100) NOT NULL,
            granted BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            UNIQUE KEY unique_role_permission (role_id, permission)
        )",
        
        "channel_acl" => "CREATE TABLE IF NOT EXISTS channel_acl (
            id INT AUTO_INCREMENT PRIMARY KEY,
            channel_id INT,
            role_name VARCHAR(50),
            can_read BOOLEAN DEFAULT TRUE,
            can_post BOOLEAN DEFAULT FALSE,
            can_moderate BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
            UNIQUE KEY unique_channel_role (channel_id, role_name)
        )",
        
        "audit_logs" => "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_id INT,
            target_id INT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (actor_id) REFERENCES users(id),
            FOREIGN KEY (target_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    ];
    
    echo "<h3>Creating Tables:</h3>";
    foreach ($tables as $tableName => $sql) {
        try {
            $db->exec($sql);
            echo "<p style='color: green;'>✅ Table '$tableName' created successfully</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error creating table '$tableName': " . $e->getMessage() . "</p>";
        }
    }
    
    // Insert default roles
    echo "<h3>Setting up Default Roles:</h3>";
    $defaultRoles = [
        ['owner', 'Owner / Admin', 'Full system administrator with all permissions', 100],
        ['co_admin', 'Co-Admin', 'Administrator with all permissions except owner management', 90],
        ['moderator', 'Moderator', 'Can moderate messages and manage channels', 70],
        ['trusted_member', 'Trusted Member / Recruiter', 'Can invite users and manage members', 50],
        ['member', 'Member', 'Default participant with basic permissions', 30],
        ['guest', 'Guest / Read-Only', 'Can read specified channels but cannot post', 10]
    ];
    
    foreach ($defaultRoles as $role) {
        $checkQuery = "SELECT COUNT(*) as count FROM roles WHERE name = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$role[0]]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if (!$exists) {
            $insertQuery = "INSERT INTO roles (name, display_name, description, level) VALUES (?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->execute($role);
            echo "<p style='color: green;'>✅ Role '{$role[1]}' created</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ Role '{$role[1]}' already exists</p>";
        }
    }
    
    // Set up role permissions
    echo "<h3>Setting up Role Permissions:</h3>";
    $rolePermissions = [
        'owner' => [
            'manage_users', 'manage_roles', 'manage_channels', 'manage_settings',
            'create_invites', 'delete_messages', 'ban_users', 'post_anywhere'
        ],
        'co_admin' => [
            'manage_users', 'manage_channels', 'manage_settings',
            'create_invites', 'delete_messages', 'ban_users', 'post_anywhere'
        ],
        'moderator' => [
            'delete_messages', 'mute_users', 'post_in_most_channels'
        ],
        'trusted_member' => [
            'create_invites', 'manage_members', 'post_in_member_channels'
        ],
        'member' => [
            'post_in_member_channels', 'read_channels'
        ],
        'guest' => [
            'read_channels'
        ]
    ];
    
    foreach ($rolePermissions as $roleName => $permissions) {
        // Get role ID
        $roleQuery = "SELECT id FROM roles WHERE name = ?";
        $roleStmt = $db->prepare($roleQuery);
        $roleStmt->execute([$roleName]);
        $roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($roleData) {
            $roleId = $roleData['id'];
            
            foreach ($permissions as $permission) {
                $checkPermQuery = "SELECT COUNT(*) as count FROM role_permissions WHERE role_id = ? AND permission = ?";
                $checkPermStmt = $db->prepare($checkPermQuery);
                $checkPermStmt->execute([$roleId, $permission]);
                $permExists = $checkPermStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                
                if (!$permExists) {
                    $insertPermQuery = "INSERT INTO role_permissions (role_id, permission) VALUES (?, ?)";
                    $insertPermStmt = $db->prepare($insertPermQuery);
                    $insertPermStmt->execute([$roleId, $permission]);
                }
            }
            echo "<p style='color: green;'>✅ Permissions set for role '$roleName'</p>";
        }
    }
    
    // Update existing users table to use new role system
    echo "<h3>Updating User Roles:</h3>";
    try {
        // Check if role column needs updating
        $alterQuery = "ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'co_admin', 'moderator', 'trusted_member', 'member', 'guest') DEFAULT 'member'";
        $db->exec($alterQuery);
        
        // Update existing admin users to owner
        $updateAdminQuery = "UPDATE users SET role = 'owner' WHERE role = 'admin'";
        $db->exec($updateAdminQuery);
        
        echo "<p style='color: green;'>✅ User roles updated successfully</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ Role update: " . $e->getMessage() . "</p>";
    }
    
    // Set up channel ACL for existing channels
    echo "<h3>Setting up Channel Permissions:</h3>";
    $channelPermissions = [
        'Announcements' => [
            'owner' => ['read' => true, 'post' => true, 'moderate' => true],
            'co_admin' => ['read' => true, 'post' => true, 'moderate' => true],
            'moderator' => ['read' => true, 'post' => false, 'moderate' => false],
            'trusted_member' => ['read' => true, 'post' => false, 'moderate' => false],
            'member' => ['read' => true, 'post' => false, 'moderate' => false],
            'guest' => ['read' => true, 'post' => false, 'moderate' => false]
        ],
        'General Chat' => [
            'owner' => ['read' => true, 'post' => true, 'moderate' => true],
            'co_admin' => ['read' => true, 'post' => true, 'moderate' => true],
            'moderator' => ['read' => true, 'post' => true, 'moderate' => true],
            'trusted_member' => ['read' => true, 'post' => true, 'moderate' => false],
            'member' => ['read' => true, 'post' => true, 'moderate' => false],
            'guest' => ['read' => true, 'post' => false, 'moderate' => false]
        ]
    ];
    
    foreach ($channelPermissions as $channelName => $rolePerms) {
        // Get channel ID
        $channelQuery = "SELECT id FROM channels WHERE name = ?";
        $channelStmt = $db->prepare($channelQuery);
        $channelStmt->execute([$channelName]);
        $channelData = $channelStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($channelData) {
            $channelId = $channelData['id'];
            
            foreach ($rolePerms as $roleName => $perms) {
                $checkAclQuery = "SELECT COUNT(*) as count FROM channel_acl WHERE channel_id = ? AND role_name = ?";
                $checkAclStmt = $db->prepare($checkAclQuery);
                $checkAclStmt->execute([$channelId, $roleName]);
                $aclExists = $checkAclStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                
                if (!$aclExists) {
                    $insertAclQuery = "INSERT INTO channel_acl (channel_id, role_name, can_read, can_post, can_moderate) VALUES (?, ?, ?, ?, ?)";
                    $insertAclStmt = $db->prepare($insertAclQuery);
                    $insertAclStmt->execute([
                        $channelId, 
                        $roleName, 
                        $perms['read'], 
                        $perms['post'], 
                        $perms['moderate']
                    ]);
                }
            }
            echo "<p style='color: green;'>✅ ACL set for channel '$channelName'</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3>Roles & Permissions Setup Complete! 🎉</h3>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Test the permission system</li>";
    echo "<li>Proceed to Priority 2: Trusted Member role implementation</li>";
    echo "<li><a href='login.php'>Login to test new roles</a></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Setup Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
h2, h3 { color: #333; }
ol, ul { margin-left: 20px; }
a { color: #667eea; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>