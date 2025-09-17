<?php
class Database {
    private $host = "localhost";
    private $db_name = "hg_community";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Auto-create tables if they don't exist
            $this->createTables();
            
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
    
    private function createTables() {
        try {
            // Create users table
            $this->conn->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                phone VARCHAR(15),
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'moderator', 'member') DEFAULT 'member',
                status ENUM('active', 'banned', 'restricted', 'muted') DEFAULT 'active',
                display_name VARCHAR(100),
                bio TEXT,
                avatar VARCHAR(255) DEFAULT 'assets/images/default-avatar.png',
                timezone VARCHAR(50) DEFAULT 'UTC',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");

            // Create roles table (STEP 1: Fix missing roles table)
            $this->conn->exec("CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) UNIQUE NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                description TEXT,
                level INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // Create role_permissions table
            $this->conn->exec("CREATE TABLE IF NOT EXISTS role_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT,
                permission VARCHAR(100) NOT NULL,
                granted BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                UNIQUE KEY unique_role_permission (role_id, permission)
            )");

            // Create channels table
            $this->conn->exec("CREATE TABLE IF NOT EXISTS channels (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                type ENUM('announcement', 'general', 'team', 'technical') DEFAULT 'general',
                team_name VARCHAR(50),
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            )");

            // Create messages table
            $this->conn->exec("CREATE TABLE IF NOT EXISTS messages (
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
            )");

            // Create invites table
            $this->conn->exec("CREATE TABLE IF NOT EXISTS invites (
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
            )");

            // Create user_permissions table
            $this->conn->exec("CREATE TABLE IF NOT EXISTS user_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                channel_id INT,
                permission ENUM('read', 'write', 'moderate', 'manage') DEFAULT 'read',
                granted_by INT,
                granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
                FOREIGN KEY (granted_by) REFERENCES users(id)
            )");

            // Insert default roles if they don't exist
            $this->insertDefaultRoles();
            
            // Insert default channels if they don't exist
            $this->insertDefaultChannels();

        } catch(PDOException $e) {
            // Silently handle table creation errors to avoid debug messages
            error_log("Table creation error: " . $e->getMessage());
        }
    }
    
    private function insertDefaultRoles() {
        try {
            $defaultRoles = [
                ['admin', 'Administrator', 'Full system administrator with all permissions', 100],
                ['moderator', 'Moderator', 'Can moderate messages and manage channels', 70],
                ['member', 'Member', 'Default participant with basic permissions', 30]
            ];
            
            foreach ($defaultRoles as $role) {
                $checkQuery = "SELECT COUNT(*) as count FROM roles WHERE name = ?";
                $checkStmt = $this->conn->prepare($checkQuery);
                $checkStmt->execute([$role[0]]);
                $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                
                if (!$exists) {
                    $insertQuery = "INSERT INTO roles (name, display_name, description, level) VALUES (?, ?, ?, ?)";
                    $insertStmt = $this->conn->prepare($insertQuery);
                    $insertStmt->execute($role);
                }
            }
        } catch(PDOException $e) {
            error_log("Role insertion error: " . $e->getMessage());
        }
    }
    
    private function insertDefaultChannels() {
        try {
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
                $checkStmt = $this->conn->prepare($checkQuery);
                $checkStmt->execute([$channel[0]]);
                $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                
                if (!$exists) {
                    $insertQuery = "INSERT INTO channels (name, description, type, team_name) VALUES (?, ?, ?, ?)";
                    $insertStmt = $this->conn->prepare($insertQuery);
                    $teamName = isset($channel[3]) ? $channel[3] : null;
                    $insertStmt->execute([$channel[0], $channel[1], $channel[2], $teamName]);
                }
            }
        } catch(PDOException $e) {
            error_log("Channel insertion error: " . $e->getMessage());
        }
    }
}
?>