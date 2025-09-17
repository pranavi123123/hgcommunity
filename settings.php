<?php
require_once 'includes/auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();

if (!$auth->hasPermission('manage_settings')) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HG Community</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="admin-nav">
                <img src="https://hackersgurukul.in/wp-content/uploads/2025/08/4.png" alt="HG Community logo" class="logo">
                <h1>HG Community - Settings</h1>
            </div>
            <div class="admin-user">
                <span><?php echo htmlspecialchars($user['username']); ?></span>
                <a href="index.php" class="btn-secondary">Back to Chat</a>
            </div>
        </div>
        
        <div class="admin-content">
            <div class="settings-section">
                <h2>Community Settings</h2>
                
                <div class="setting-group">
                    <h3>Permissions</h3>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" id="mods-can-invite"> 
                            Allow Moderators to Create Invites
                        </label>
                    </div>
                </div>
                
                <div class="setting-group">
                    <h3>Channel Settings</h3>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" id="members-can-post"> 
                            Allow Members to Post in General Channels
                        </label>
                    </div>
                </div>
                
                <button id="save-settings" class="btn-primary">Save Settings</button>
            </div>
        </div>
    </div>
    
    <script>
        const currentUser = <?php echo json_encode($user); ?>;
    </script>
    <script src="assets/js/settings.js"></script>
</body>
</html>