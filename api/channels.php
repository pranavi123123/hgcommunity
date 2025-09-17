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
            // Get all channels
            $query = "SELECT * FROM channels ORDER BY type, name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'channels' => $channels]);
            break;
            
        case 'POST':
            // Create new channel
            if (!$auth->hasPermission('manage_channels')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['name']) || !isset($input['type'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $name = trim($input['name']);
            $description = trim($input['description'] ?? '');
            $type = $input['type'];
            $teamName = trim($input['team_name'] ?? '');
            
            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Channel name is required']);
                exit;
            }
            
            // Check if channel name already exists
            $checkQuery = "SELECT COUNT(*) as count FROM channels WHERE name = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$name]);
            $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if ($exists) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Channel name already exists']);
                exit;
            }
            
            $insertQuery = "INSERT INTO channels (name, description, type, team_name, created_by) VALUES (?, ?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $teamNameValue = ($type === 'team' && !empty($teamName)) ? $teamName : null;
            
            if ($insertStmt->execute([$name, $description, $type, $teamNameValue, $user['id']])) {
                $channelId = $db->lastInsertId();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Channel created successfully',
                    'channel' => [
                        'id' => $channelId,
                        'name' => $name,
                        'description' => $description,
                        'type' => $type,
                        'team_name' => $teamNameValue
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create channel']);
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