<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('home.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please enter username/email and password";
    } else {
        // Check if user exists (by username or email)
        $query = "SELECT id, username, email, password, full_name FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ss", $username, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $row['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['logged_in'] = true;
                
                // Redirect to home page
                redirect('home.php');
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · ExpenseTracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Georgia, 'Times New Roman', Times, serif;
        }
        body {
            background: linear-gradient(135deg, #001f3f 0%, #1a3e5c 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .login-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .login-card {
            background: white;
            border-radius: 32px;
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.3);
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            border: 3px solid #20b2aa;
        }
        .logo-area {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-icon {
            background: #20b2aa;
            color: #001f3f;
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 1rem;
        }
        .logo-text {
            font-size: 2.2rem;
            font-weight: 600;
            color: #001f3f;
        }
        h2 {
            color: #001f3f;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #001f3f;
            font-weight: 600;
        }
        .input-group {
            display: flex;
            align-items: center;
            border: 2px solid #b0e0e6;
            border-radius: 50px;
            overflow: hidden;
            transition: all 0.3s;
        }
        .input-group:focus-within {
            border-color: #20b2aa;
            box-shadow: 0 0 0 3px rgba(32,178,170,0.2);
        }
        .input-group i {
            padding: 0.8rem 1rem;
            background: #f0f4f8;
            color: #20b2aa;
            min-width: 50px;
            text-align: center;
        }
        .input-group input {
            flex: 1;
            padding: 0.8rem;
            border: none;
            outline: none;
            font-size: 1rem;
            font-family: Georgia, serif;
        }
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: 50px;
            margin-bottom: 1.5rem;
            border-left: 6px solid #c62828;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: #20b2aa;
            color: #001f3f;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .btn-login:hover {
            background: #001f3f;
            color: white;
            border-color: #20b2aa;
        }
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .register-link a {
            color: #20b2aa;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .footer-note {
            text-align: center;
            margin: 1rem 0;
            color: white;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-area">
                <div class="logo-icon"><i class="fas fa-wallet"></i></div>
                <span class="logo-text">expn.do</span>
            </div>
            
            <h2>Welcome Back</h2>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Username or Email</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" placeholder="Enter username or email" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="register-link">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
    </div>
    
    <div class="footer-note">
        <i class="fas fa-credit-card"></i> Track your expenses wisely · Navy & Teal
    </div>
</body>
</html>