<?php
/**
 * login.php file
 * librarian login page
 */

//---start session---
session_start();

//--- DB Connection---
require_once 'config/database.php';

//---Redirect if already logged in---
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'librarian') {
    header('Location: admin/dashboard.php');
    exit();
}

//--- handle login from submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get form data
    $username = trim($_POST['username' ?? '']);
    $password = $_POST['password'] ?? '';

    // validate inputs
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        try {
            $db = getDB();

            // query for librarian with same user name
            $stmt = $db->prepare("
                SELECT
                    librarian_id,
                    username,
                    password_hash,
                    full_name,
                    email,
                    role,
                    status
                FROM librarians
                WHERE username = :username            
            ");
            $stmt->execute([':username' => $username]);
            $librarian = $stmt->fetch();
            
            // check if user exists
            if ($librarian) {
                // check if account is active
                if ($librarian['status'] !== 'active') {
                    $error = 'Your account is ' . $librarian['status'] . '. Please contact admin.';
                } else {
                    // verify password
                    // using md5() ...but production use password_verify()
                    if(md5($password, $librarian['password_hash'])) {
                        //--- Login Success---

                        // update login time
                        $stmt = $db->prepare("
                            UPDATE librarians
                            SET last_login = NOW()
                            WHERE librarian_id = :id
                        ");
                        $stmt->execute([':id' => $librarian['librarian_id']]);

                        // store user data in session
                        $_SESSION['user_id'] = $librarian['librarian_id'];
                        $_SESSION['user_type'] = 'librarian';
                        $_SESSION['username'] = $librarian['username'];
                        $_SESSION['fullname'] = $librarian['full_name'];
                        $_SESSION['email'] = $librarian['email'];
                        $_SESSION['role'] = $librarian['role'];
                        $_SESSION['permissions'] = explode(',', $librarian['permissions'] ?? '');
                    

                         // Redirect to dashboard
                        header('Location: admin/dashboard.php');
                        exit();

                    } else {
                        $error ='Invalid password. Please try again.';
                    }
                }
            } else {
                $error = 'Username not fount.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Login failed. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BSS Library Management System</title>

    <link rel="stylesheet" href="css/login.css">
</head>
<body>

    <div class="login-container">

        <!-- logo/ title--->
        <div class="logo">
            <h1> 📚 BSS Library </h1>
            <p>Librarian Login</p>
        </div>

        <!-- error message--->
        <div id="errorAlert" class="alert alert-error <?php echo $error ? 'show' : ''; ?>">

            <?php echo htmlspecialchars($error); ?>
        </div>

        <!-- success message--->
        <div id="successAlert" class="alert alert-success <?php echo $success ? 'show' : ''; ?>">
             
            <?php echo htmlspecialchars($success)?>
        </div>

        <!---login form--->
        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text"
                       id="username" 
                       name="username"
                       placeholder="Enter your username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       required
                       autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       placeholder="Enter your password"
                       required>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                 🔑 Login
            </button>
           
        </form> 

         <div class="login-footer">
            <a href="forgot-password.php">Forgot Password?</a>
            <span style="color: #ddd; margin: 0 10px;">|></span>
            <a href="contactus.html">Contact Support</a>
        </div>

    </div>

    <!-- JavaScript for form validation-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginform');
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            const errorAlert = document.getElementById('errorAlert');
        });

        // auto-hide alerts after 5 secs
        if (errorAlert.classList.contains('show')) {
            setTimeout(function() {
                errorAlert.classList.remove('show');
            }, 5000)
        }

        // client-side validation
        form.addEventListener('submit', function(e) {
            let hasError = false;

            // reset error states
            username.classList.remove('error');
            password.classList.remove('error');
            errorAlert.classList.remove('show');

            // validate username
            if (username.value.trim() === ''){
                username.classList.add('error');
                username.focus();
                showError('Please enter your username.');
                e.preventDefault();
                return;
            }

            // validate password
            if (password.value.trim() === '') {
                password.classList.add('error');
                password.focus();
                showError('Please enter your password.');
                e.preventDefault();
                return;
            }

            // min length check
            if (password.value.length < 6) {
                password.classList.add('error');
                password.focus();
                showError('Password must be atleast 6 characters.');
                e.preventDefault();
                return;
            }

            function showError(message) {
                errorAlert.textContent = message;
                errorAlert.classList.add('show');
            }
        });
    </script>
    
</body>
</html>

