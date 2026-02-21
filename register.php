<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('home.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize($_POST['full_name'] ?? '');

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if username or email already exists
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Username or email already exists";
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_query = "INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hashed_password, $full_name);
            
            if (mysqli_stmt_execute($stmt)) {
                $user_id = mysqli_insert_id($conn);
                
                // Create default user settings
                $settings_query = "INSERT INTO user_settings (user_id) VALUES (?)";
                $stmt = mysqli_prepare($conn, $settings_query);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                
                $success = "Registration successful! Redirecting to login...";
                
                // Redirect to login page after 2 seconds
                header("refresh:2;url=login.php");
            } else {
                $error = "Registration failed. Please try again.";
            }
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
    <title>Register · ExpenseTracker</title>
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
        .register-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .register-card {
            background: white;
            border-radius: 32px;
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.3);
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            border: 3px solid #20b2aa;
        }
        .logo-area {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-icon {
            background: #20b2aa;
            color: #001f3f;
            width: 60px;
            height: 60px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        .logo-text {
            font-size: 2rem;
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
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 1rem;
            border-radius: 50px;
            margin-bottom: 1.5rem;
            border-left: 6px solid #2e7d32;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-register {
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
        .btn-register:hover {
            background: #001f3f;
            color: white;
            border-color: #20b2aa;
        }
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .login-link a {
            color: #20b2aa;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
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
    <div class="register-container">
        <div class="register-card">
            <div class="logo-area">
                <div class="logo-icon"><i class="fas fa-wallet"></i></div>
                <span class="logo-text">expn.do</span>
            </div>
            
            <h2>Create Account</h2>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name (Optional)</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="full_name" placeholder="Enter your full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Username *</label>
                    <div class="input-group">
                        <i class="fas fa-at"></i>
                        <input type="text" name="username" placeholder="Choose a username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password *</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Minimum 6 characters" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" placeholder="Re-enter password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
    
    <div class="footer-note">
        <i class="fas fa-credit-card"></i> Join ExpenseTracker today · Navy & Teal
    </div>
</body>
</html>