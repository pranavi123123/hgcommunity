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
        case 'POST':
            // Create new invite
            if (!$auth->hasPermission('create_invites')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
                exit;
            }
            
            $email = trim($input['email'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $role = $input['role'] ?? 'member';
            $expiryHours = intval($input['expiry_hours'] ?? 24);
            
            // Generate unique invite code
            $inviteCode = bin2hex(random_bytes(16));
            
            // Calculate expiry time
            $expiresAt = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));
            
            $insertQuery = "INSERT INTO invites (invite_code, created_by, email, phone, role, expires_at) VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            
            $emailValue = !empty($email) ? $email : null;
            $phoneValue = !empty($phone) ? $phone : null;
            
            if ($insertStmt->execute([$inviteCode, $user['id'], $emailValue, $phoneValue, $role, $expiresAt])) {
                $inviteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                           '://' . $_SERVER['HTTP_HOST'] . 
                           dirname(dirname($_SERVER['REQUEST_URI'])) . 
                           '/register.php?invite=' . $inviteCode;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Invite created successfully',
                    'invite' => [
                        'code' => $inviteCode,
                        'url' => $inviteUrl,
                        'expires_at' => $expiresAt,
                        'role' => $role
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create invite']);
            }
            break;
            
        case 'GET':
            // Get all invites (admin only)
            if (!$auth->hasPermission('create_invites')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            $query = "SELECT i.*, u.username as created_by_username 
                     FROM invites i 
                     LEFT JOIN users u ON i.created_by = u.id 
                     ORDER BY i.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $invites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'invites' => $invites]);
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