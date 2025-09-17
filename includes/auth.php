<?php
require_once 'config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function login($username, $password) {
        try {
            $query = "SELECT * FROM users WHERE (username = :username OR email = :username) AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Update last active
                    $updateQuery = "UPDATE users SET last_active = NOW() WHERE id = :id";
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->bindParam(':id', $user['id']);
                    $updateStmt->execute();
                    
                    return ['success' => true, 'message' => 'Login successful'];
                }
            }
            
            return ['success' => false, 'message' => 'Invalid username or password'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
        }
    }
    
    public function register($username, $email, $phone, $password, $inviteCode) {
        try {
            // Validate invite code
            $inviteQuery = "SELECT * FROM invites WHERE invite_code = :code AND expires_at > NOW() AND used_at IS NULL";
            $inviteStmt = $this->db->prepare($inviteQuery);
            $inviteStmt->bindParam(':code', $inviteCode);
            $inviteStmt->execute();
            
            if ($inviteStmt->rowCount() == 0) {
                return ['success' => false, 'message' => 'Invalid or expired invite code'];
            }
            
            $invite = $inviteStmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if username or email already exists
            $checkQuery = "SELECT COUNT(*) as count FROM users WHERE username = :username OR email = :email";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':username', $username);
            $checkStmt->bindParam(':email', $email);
            $checkStmt->execute();
            
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($result['count'] > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Create user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertQuery = "INSERT INTO users (username, email, phone, password, role) VALUES (:username, :email, :phone, :password, :role)";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->bindParam(':username', $username);
            $insertStmt->bindParam(':email', $email);
            $insertStmt->bindParam(':phone', $phone);
            $insertStmt->bindParam(':password', $hashedPassword);
            $insertStmt->bindParam(':role', $invite['role']);
            
            if ($insertStmt->execute()) {
                $userId = $this->db->lastInsertId();
                
                // Mark invite as used
                $updateInviteQuery = "UPDATE invites SET used_at = NOW(), used_by = :user_id WHERE id = :invite_id";
                $updateInviteStmt = $this->db->prepare($updateInviteQuery);
                $updateInviteStmt->bindParam(':user_id', $userId);
                $updateInviteStmt->bindParam(':invite_id', $invite['id']);
                $updateInviteStmt->execute();
                
                return ['success' => true, 'message' => 'Registration successful! You can now login.'];
            }
            
            return ['success' => false, 'message' => 'Registration failed'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Registration error: ' . $e->getMessage()];
        }
    }
    
    public function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $query = "SELECT * FROM users WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    public function hasPermission($permission) {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        // Admin has all permissions
        if ($user['role'] == 'admin') return true;
        
        // Define role-based permissions
        $permissions = [
            'admin' => ['manage_users', 'manage_channels', 'manage_settings', 'create_invites', 'delete_messages', 'ban_users'],
            'moderator' => ['delete_messages', 'create_invites'],
            'member' => []
        ];
        
        return in_array($permission, $permissions[$user['role']] ?? []);
    }
    
    public function getAllUsers() {
        try {
            $query = "SELECT id, username, email, role, status, last_active, created_at FROM users ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function updateUserRole($userId, $role) {
        try {
            $query = "UPDATE users SET role = :role WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':id', $userId);
            
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function updateUserStatus($userId, $status) {
        try {
            $query = "UPDATE users SET status = :status WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $userId);
            
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}
?>