<?php
/**
 * reset-password.php file
 */

session_start();
require_once 'config/database.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$showForm = false;
$librarianId = null;

// validate token
if (!empty($token)) {
    try {
        $db = getDB();
        
        // check if token exists and is valid
        $stmt = $db->prepare("
            SELECT * FROM password_resets 
            WHERE token = :token 
            AND used = FALSE 
            AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $resetRequest = $stmt->fetch();
        
        if ($resetRequest) {
            $showForm = true;
            $librarianId = $resetRequest['librarian_id'];
        } else {
            $error = 'Invalid or expired reset token. Please request a new one.';
        }
        
    } catch(PDOException $e) {
        error_log("Reset error: " . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

// handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm && $librarianId) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $db = getDB();
            
            // hash new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // update password
            $stmt = $db->prepare("
                UPDATE librarians 
                SET password_hash = :password_hash 
                WHERE librarian_id = :librarian_id
            ");
            $stmt->execute([
                ':password_hash' => $hashedPassword,
                ':librarian_id' => $librarianId
            ]);
            
            // mark token as used
            $stmt = $db->prepare("
                UPDATE password_resets 
                SET used = TRUE 
                WHERE token = :token
            ");
            $stmt->execute([':token' => $token]);
            
            $success = 'Password reset successfully! You can now login.';
            $showForm = false;
            
        } catch(PDOException $e) {
            error_log("Password update error: " . $e->getMessage());
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Library System</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🔑 Reset Password</h1>
            <p>Enter your new password</p>
        </div>
        
        <div id="errorAlert" class="alert alert-error <?php echo $error ? 'show' : ''; ?>">
            <?php echo htmlspecialchars($error); ?>
        </div>
        
        <div id="successAlert" class="alert alert-success <?php echo $success ? 'show' : ''; ?>">
            <?php echo htmlspecialchars($success); ?>
        </div>
        
        <?php if ($showForm): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="Enter new password (min 6 characters)"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           placeholder="Confirm new password"
                           required>
                </div>
                
                <button type="submit" class="btn-login">🔐 Reset Password</button>
            </form>
            
            <div class="login-footer">
                <a href="login.php">← Back to Login</a>
            </div>
        <?php elseif ($success): ?>
            <div class="login-footer">
                <a href="login.php" class="btn-login" style="text-align: center; text-decoration: none; display: block;">
                    🔐 Go to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>