<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = $auth->getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($method) {
        case 'GET':
            // Get all users (admin/moderator only)
            if (!$auth->hasPermission('manage_users')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            $users = $auth->getAllUsers();
            echo json_encode(['success' => true, 'users' => $users]);
            break;
            
        case 'PUT':
            // Update user role or status
            if (!$auth->hasPermission('manage_users')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['user_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                exit;
            }
            
            $userId = intval($input['user_id']);
            $success = false;
            
            if (isset($input['role'])) {
                $success = $auth->updateUserRole($userId, $input['role']);
            }
            
            if (isset($input['status'])) {
                $success = $auth->updateUserStatus($userId, $input['status']);
            }
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update user']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>