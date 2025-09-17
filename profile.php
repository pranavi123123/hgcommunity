<?php
require_once 'includes/auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - HG Community</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/profile.css">
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-nav">
                <img src="https://hackersgurukul.in/wp-content/uploads/2025/08/4.png" alt="HG Community logo" class="logo">
                <h1>Profile Settings</h1>
            </div>
            <a href="index.php" class="btn-secondary">Back to Chat</a>
        </div>
        
        <div class="profile-content">
            <div class="profile-section">
                <div class="avatar-section">
                    <div class="avatar-container">
                        <img src="<?php echo $user['avatar'] ?: 'assets/images/default-avatar.png'; ?>" alt="Profile Picture" id="profile-avatar">
                        <div class="avatar-overlay">
                            <span>Change Photo</span>
                        </div>
                    </div>
                    <input type="file" id="avatar-input" accept="image/*" style="display: none;">
                </div>
                
                <form id="profile-form" class="profile-form">
                    <div class="form-group">
                        <label for="display-name">Display Name</label>
                        <input type="text" id="display-name" name="display_name" value="<?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        <small>Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?: ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?: ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="timezone">Timezone</label>
                        <select id="timezone" name="timezone">
                            <option value="UTC">UTC</option>
                            <option value="America/New_York">Eastern Time</option>
                            <option value="America/Chicago">Central Time</option>
                            <option value="America/Denver">Mountain Time</option>
                            <option value="America/Los_Angeles">Pacific Time</option>
                            <option value="Asia/Kolkata">India Standard Time</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        const currentUser = <?php echo json_encode($user); ?>;
    </script>
    <script src="assets/js/profile.js"></script>
</body>
</html>