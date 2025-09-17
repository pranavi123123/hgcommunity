<?php
require_once 'includes/auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();

if (!$auth->hasPermission('manage_users')) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - HG Community</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="admin-nav">
                <img src="https://hackersgurukul.in/wp-content/uploads/2025/08/4.png" alt="HG Community logo" class="logo">
                <h1>HG Community - Manage Users</h1>
            </div>
            <div class="admin-user">
                <span><?php echo htmlspecialchars($user['username']); ?></span>
                <a href="index.php" class="btn-secondary">Back to Chat</a>
            </div>
        </div>
        
        <div class="admin-content">
            <div class="users-section">
                <div class="section-header">
                    <h2>User Management</h2>
                    <button id="refresh-users" class="btn-primary">Refresh</button>
                </div>
                
                <div class="users-table-container">
                    <table class="users-table" id="users-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody">
                            <!-- Users will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const currentUser = <?php echo json_encode($user); ?>;
    </script>
    <script src="assets/js/manage-users.js"></script>
</body>
</html>