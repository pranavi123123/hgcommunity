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
            // Get messages for a channel
            $channelId = $_GET['channel_id'] ?? null;
            
            if (!$channelId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Channel ID is required']);
                exit;
            }
            
            $query = "SELECT m.*, u.username, u.display_name, u.avatar, u.role 
                     FROM messages m 
                     JOIN users u ON m.user_id = u.id 
                     WHERE m.channel_id = ? 
                     ORDER BY m.created_at ASC 
                     LIMIT 100";
            $stmt = $db->prepare($query);
            $stmt->execute([$channelId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        case 'POST':
            // Send new message
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['channel_id']) || !isset($input['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $channelId = intval($input['channel_id']);
            $content = trim($input['content']);
            
            if (empty($content)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Message content cannot be empty']);
                exit;
            }
            
            // Check if channel exists
            $channelQuery = "SELECT COUNT(*) as count FROM channels WHERE id = ?";
            $channelStmt = $db->prepare($channelQuery);
            $channelStmt->execute([$channelId]);
            $channelExists = $channelStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if (!$channelExists) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Channel not found']);
                exit;
            }
            
            $insertQuery = "INSERT INTO messages (channel_id, user_id, content) VALUES (?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            
            if ($insertStmt->execute([$channelId, $user['id'], $content])) {
                $messageId = $db->lastInsertId();
                
                // Get the complete message data
                $getMessageQuery = "SELECT m.*, u.username, u.display_name, u.avatar, u.role 
                                   FROM messages m 
                                   JOIN users u ON m.user_id = u.id 
                                   WHERE m.id = ?";
                $getMessageStmt = $db->prepare($getMessageQuery);
                $getMessageStmt->execute([$messageId]);
                $message = $getMessageStmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Message sent successfully',
                    'data' => $message
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to send message']);
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