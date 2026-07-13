<?php
/**
 * forgot-password file
 */

session_start();

//---DB Connection---
require_once 'config/database.php';

//--- handle form DB connection
$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    //validate email
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = 'Please enter a valid email address.';
    } else {
        try{
            $db = getDB();

            // check if librarian exists with this very email
            $stmt = $db->prepare("
                SELECT librarian_id, full_name, username
                FROM librarians
                WHERE email = :email AND status = 'active'
            ");
            $stmt->execute([':email' => $email]);
            $librarian = $stmt->fetch();

            if($librarian){
                //---Generate reset token ---
                // create unique token for password reset
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // save token in DB
                $stmt = $db->prepare("
                    INSERT INTO password_resets 
                    (librarian_id, token, email, expires_at, ip_address, user_agent) 
                    VALUES (:librarian_id, :token, :email, :expires_at, :ip, :user_agent)
                ");

                $stmt->execute([
                    ':librarian_id' => $librarian['librarian_id'],
                    ':token' => $token,
                    ':email' => $email,
                    ':expires_at' => $expires,
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);

                // show success message 
                // NB ... for production we email the reset link

                $success = "A password reset link has been sent to your email address. 
                           Please check your inbox.";

                 // In production, send email with reset link
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/library/reset-password.php?token=" . $token;

                // log reset request ...for security
                error_log("Password reset requested for: " . $librarian['username'] . " at " . date('Y-m-d H:i:s'));

                // If email is enabled ...uncomment in production 
                // mail($email, "Password Reset Request", "Click this link to reset: $resetLink");

            }else {
                // dont reveal is email exist or not ...for security
                $success = "If your email is registered, you will receive a reset link.";
            }

        } catch(PDOException $e) {
            error_log("Passord reset error: " . $e->getMessage());
            $error = 'An error occured. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Library System</title>
    <link rel="stylesheet" href="css/login.css">
    <style>
        .back-link {
            display: inline-block;
            margin-top: 15px;
            color: #3498db;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .info-text {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        
        <!---logo -->
        <div class="logo">
            <h1>🔑 Forgot Password</h1>
            <p>Enter your email to reset your password</p>
        </div>
        
        <!---error message -->
        <div id="errorAlert" class="alert alert-error <?php echo $error ? 'show' : ''; ?>">
            <?php echo htmlspecialchars($error); ?>
        </div>
        
        <!--success message -->
        <div id="successAlert" class="alert alert-success <?php echo $success ? 'show' : ''; ?>">
            <?php echo htmlspecialchars($success); ?>
        </div>
        
        <!-- Info Message -->
        <?php if (!$success): ?>
            <div class="info-text">
                Enter your registered email address and we'll send you a link to reset your password.
            </div>
        <?php endif; ?>
        
        <!---forgot password form--->
        <form method="POST" action="">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       placeholder="Enter your email address"
                       value="<?php echo htmlspecialchars($email); ?>"
                       required
                       autofocus
                       <?php echo $success ? 'disabled' : ''; ?>>
            </div>
            
            <?php if (!$success): ?>
                <button type="submit" class="btn-login">
                    📧 Send Reset Link
                </button>
            <?php else: ?>
                <a href="login.php" class="btn-login" style="text-align: center; text-decoration: none; display: block;">
                    🔐 Back to Login
                </a>
            <?php endif; ?>
            
        </form>
        
        <div class="login-footer">
            <a href="login.php">← Back to Login</a>
            <span style="color: #ddd; margin: 0 10px;">|</span>
            <a href="contactus.html">Contact Support</a>
        </div>
        
    </div>
    <script>
        // auto-hide alerts after 5 secs for error and 10 secs for success
        document.addEventListener('DOMContentLoaded', function() {
            const errAlert = document.getElementById('errorAlert');
            const successAlert = document.getElementById('successAlert');

            if (errAlert.classList.contains('show')) {
                setTimeout(function() {
                    errAlert.classList.remove('show');
                }, 5000);
            }

            if (successAlert.classList.contains('show')) {
                setTimeout(function() {
                    successAlert.classList.remove('show');
                }, 10000);
            }
        });
    </script>
    
</body>
</html>
