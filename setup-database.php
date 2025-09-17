<?php
// Database setup script - Run this once to create all tables
// Access: http://localhost/hg-community/setup-database.php

require_once 'config/database.php';

echo "<h2>HG Community - Database Setup</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p style='color: green;'>✅ Connected to database successfully!</p>";
    
    // Create tables
    $tables = [
        "users" => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(15),
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'moderator', 'member') DEFAULT 'member',
            status ENUM('active', 'banned', 'restricted', 'muted') DEFAULT 'active',
            avatar VARCHAR(255) DEFAULT 'default-avatar.png',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "channels" => "CREATE TABLE IF NOT EXISTS channels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            type ENUM('announcement', 'general', 'team', 'technical') DEFAULT 'general',
            team_name VARCHAR(50),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        
        "messages" => "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            channel_id INT,
            user_id INT,
            content TEXT NOT NULL,
            file_path VARCHAR(255),
            file_type VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            edited_at TIMESTAMP NULL,
            FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "invites" => "CREATE TABLE IF NOT EXISTS invites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invite_code VARCHAR(32) UNIQUE NOT NULL,
            created_by INT,
            email VARCHAR(100),
            phone VARCHAR(15),
            role ENUM('moderator', 'member') DEFAULT 'member',
            expires_at TIMESTAMP,
            used_at TIMESTAMP NULL,
            used_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id),
            FOREIGN KEY (used_by) REFERENCES users(id)
        )",
        
        "user_permissions" => "CREATE TABLE IF NOT EXISTS user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            channel_id INT,
            permission ENUM('read', 'write', 'moderate', 'manage') DEFAULT 'read',
            granted_by INT,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
            FOREIGN KEY (granted_by) REFERENCES users(id)
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
    
    // Insert default channels
    echo "<h3>Creating Default Channels:</h3>";
    $defaultChannels = [
        ['Announcements', 'Important updates and announcements', 'announcement'],
        ['General Chat', 'General discussions and casual conversations', 'general'],
        ['Frontend Team', 'Frontend development discussions', 'team', 'Frontend'],
        ['Backend Team', 'Backend development discussions', 'team', 'Backend'],
        ['R&D Team', 'Research and development discussions', 'team', 'R&D'],
        ['Technical Discussions', 'General coding and technical topics', 'technical'],
        ['Error Resolutions', 'Debugging help and error solving', 'technical']
    ];
    
    foreach ($defaultChannels as $channel) {
        $checkQuery = "SELECT COUNT(*) as count FROM channels WHERE name = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$channel[0]]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if (!$exists) {
            $insertQuery = "INSERT INTO channels (name, description, type, team_name) VALUES (?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $teamName = isset($channel[3]) ? $channel[3] : null;
            $insertStmt->execute([$channel[0], $channel[1], $channel[2], $teamName]);
            echo "<p style='color: green;'>✅ Channel '{$channel[0]}' created</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ Channel '{$channel[0]}' already exists</p>";
        }
    }
    
    echo "<hr>";
    echo "<h3>Setup Complete! 🎉</h3>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li><a href='create-admin.php'>Create Admin User</a></li>";
    echo "<li><a href='test-connection.php'>Test Connection</a></li>";
    echo "<li><a href='login.php'>Login to HG Community</a></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Setup Error: " . $e->getMessage() . "</p>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Make sure MySQL server is running</li>";
    echo "<li>Check database credentials in config/database.php</li>";
    echo "<li>Ensure database 'hg_community' exists</li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
h2, h3 { color: #333; }
ol, ul { margin-left: 20px; }
a { color: #667eea; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>