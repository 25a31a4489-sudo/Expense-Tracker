<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Get user data
$user_id = getCurrentUserId();
$query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Get user settings
$settings_query = "SELECT * FROM user_settings WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $settings_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$settings_result = mysqli_stmt_get_result($stmt);
$settings = mysqli_fetch_assoc($settings_result);

// Get user statistics
$stats_query = "SELECT 
    COUNT(*) as total_transactions,
    COALESCE(SUM(amount), 0) as total_spent,
    COALESCE(AVG(amount), 0) as avg_transaction,
    COUNT(DISTINCT DATE_FORMAT(expense_date, '%Y-%m')) as active_months,
    MAX(expense_date) as last_transaction_date
    FROM expenses WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get category breakdown
$category_query = "SELECT category, COUNT(*) as count, SUM(amount) as total 
                   FROM expenses WHERE user_id = ? 
                   GROUP BY category ORDER BY total DESC LIMIT 3";
$stmt = mysqli_prepare($conn, $category_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$category_result = mysqli_stmt_get_result($stmt);

// Format join date
$join_date = date('M Y', strtotime($user['created_at']));

// Handle form submission for profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $currency = sanitize($_POST['currency']);
        $monthly_budget = floatval($_POST['monthly_budget']);
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format";
        } else {
            // Update users table
            $update_user = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_user);
            mysqli_stmt_bind_param($stmt, "ssi", $full_name, $email, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Update or insert settings
                if ($settings) {
                    $update_settings = "UPDATE user_settings SET currency_symbol = ?, monthly_budget_cap = ?, email_notifications = ? WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $update_settings);
                    mysqli_stmt_bind_param($stmt, "sdii", $currency, $monthly_budget, $notifications, $user_id);
                } else {
                    $insert_settings = "INSERT INTO user_settings (user_id, currency_symbol, monthly_budget_cap, email_notifications) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $insert_settings);
                    mysqli_stmt_bind_param($stmt, "isdi", $user_id, $currency, $monthly_budget, $notifications);
                }
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Profile updated successfully!";
                    // Refresh user data
                    $user['full_name'] = $full_name;
                    $user['email'] = $email;
                    $settings['currency_symbol'] = $currency;
                    $settings['monthly_budget_cap'] = $monthly_budget;
                    $settings['email_notifications'] = $notifications;
                } else {
                    $error_message = "Failed to update settings.";
                }
            } else {
                $error_message = "Failed to update profile.";
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_password = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $update_password);
                    mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "Password changed successfully!";
                    } else {
                        $error_message = "Failed to change password.";
                    }
                } else {
                    $error_message = "New password must be at least 6 characters.";
                }
            } else {
                $error_message = "New passwords do not match.";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
}

// Currency symbols mapping
$currency_symbols = [
    '₹' => 'Indian Rupee (₹)',
    '$' => 'US Dollar ($)',
    '€' => 'Euro (€)',
    '£' => 'British Pound (£)',
    '¥' => 'Japanese Yen (¥)',
    '₿' => 'Bitcoin (₿)'
];

// Icons for categories
$category_icons = [
    'Food & Dining' => 'fa-utensils',
    'Transport' => 'fa-bus',
    'Entertainment' => 'fa-film',
    'Utilities' => 'fa-bolt',
    'Shopping' => 'fa-bag-shopping',
    'Health' => 'fa-heart-pulse',
    'Travel' => 'fa-plane',
    'Rent' => 'fa-house-chimney',
    'Education' => 'fa-graduation-cap',
    'Gifts' => 'fa-gift'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile · ExpenseTracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Georgia, 'Times New Roman', Times, serif;
        }
        body {
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar {
            background-color: #001f3f;
            padding: 0.9rem 2rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 8px 16px rgba(0, 20, 30, 0.2);
            border-bottom: 3px solid #20b2aa;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-icon {
            background: #20b2aa;
            color: #001f3f;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .logo-text {
            color: #f0f4f8;
            font-weight: 600;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }
        .nav-links {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
        }
        .nav-item {
            color: #cfddee;
            text-decoration: none;
            padding: 0.6rem 1.2rem;
            border-radius: 40px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 1rem;
        }
        .nav-item i {
            font-size: 1.1rem;
            color: #20b2aa;
        }
        .nav-item:hover {
            background: #1a3e5c;
            color: white;
        }
        .nav-item.active {
            background: #20b2aa;
            color: #001f3f;
            box-shadow: 0 4px 10px rgba(32,178,170,0.4);
        }
        .nav-item.active i {
            color: #001f3f;
        }
        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .user-info {
            padding: 0.3rem 1rem;
            background: #20b2aa;
            border-radius: 40px;
            color: #001f3f;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .user-info i {
            font-size: 1rem;
        }
        .logout-btn {
            color: white;
            text-decoration: none;
            padding: 0.3rem 1rem;
            border-radius: 40px;
            background: #c62828;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background: #b71c1c;
            transform: scale(1.05);
        }
        .page-container {
            flex: 1;
            max-width: 1300px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .page-card {
            background: #ffffff;
            border-radius: 32px;
            box-shadow: 0 20px 40px -12px rgba(0,31,63,0.25);
            padding: 2.2rem 2.5rem;
            border: 2px solid #b0e0e6;
        }
        .page-title {
            font-size: 2.2rem;
            font-weight: 650;
            color: #001f3f;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-title i {
            color: #20b2aa;
            font-size: 2.2rem;
        }
        .divider {
            height: 4px;
            width: 80px;
            background: #20b2aa;
            border-radius: 10px;
            margin: 1rem 0 2rem 0;
        }
        .profile-header {
            display: flex;
            gap: 2.5rem;
            flex-wrap: wrap;
            align-items: center;
            margin: 1.5rem 0 2rem;
        }
        .avatar-lg {
            background: linear-gradient(135deg, #001f3f 0%, #1a3e5c 100%);
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: #20b2aa;
            border: 4px solid #20b2aa;
            box-shadow: 0 10px 20px rgba(0,31,63,0.3);
        }
        .info-row {
            display: flex;
            margin: 1rem 0;
            border-bottom: 1px solid #b0e0e6;
            padding-bottom: 0.8rem;
        }
        .info-label {
            width: 150px;
            font-weight: 600;
            color: #001f3f;
        }
        .info-value {
            color: #1d3e5e;
            flex: 1;
        }
        .edit-btn {
            color: #20b2aa;
            text-decoration: none;
            font-size: 0.9rem;
            margin-left: 1rem;
            cursor: pointer;
        }
        .edit-btn:hover {
            text-decoration: underline;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .stat-box {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            padding: 1.2rem;
            border-radius: 20px;
            text-align: center;
            border: 2px solid #20b2aa;
        }
        .stat-box i {
            font-size: 1.8rem;
            color: #20b2aa;
            margin-bottom: 0.5rem;
        }
        .stat-box .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #001f3f;
        }
        .stat-box .stat-label {
            color: #3b5f7a;
            font-size: 0.9rem;
        }
        .tab-container {
            margin: 2rem 0;
        }
        .tab-buttons {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid #b0e0e6;
            padding-bottom: 0.5rem;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 0.5rem 1.5rem;
            background: none;
            border: none;
            font-size: 1.1rem;
            font-weight: 600;
            color: #3b5f7a;
            cursor: pointer;
            position: relative;
            font-family: Georgia, serif;
        }
        .tab-btn.active {
            color: #20b2aa;
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -0.7rem;
            left: 0;
            width: 100%;
            height: 3px;
            background: #20b2aa;
            border-radius: 3px;
        }
        .tab-pane {
            display: none;
            padding: 2rem 0;
        }
        .tab-pane.active {
            display: block;
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
        .input-group input, .input-group select {
            flex: 1;
            padding: 0.8rem;
            border: none;
            outline: none;
            font-size: 1rem;
            font-family: Georgia, serif;
            background: white;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .checkbox-group input {
            width: 20px;
            height: 20px;
        }
        .btn-save {
            background: #20b2aa;
            color: #001f3f;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            margin-right: 1rem;
        }
        .btn-save:hover {
            background: #001f3f;
            color: white;
            border-color: #20b2aa;
        }
        .btn-change {
            background: #001f3f;
            color: white;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid #20b2aa;
        }
        .btn-change:hover {
            background: #20b2aa;
            color: #001f3f;
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
        .category-badge {
            display: inline-block;
            background: #d9f0f2;
            padding: 0.3rem 1rem;
            border-radius: 50px;
            margin: 0.2rem;
            font-size: 0.85rem;
            color: #001f3f;
        }
        .category-badge i {
            margin-right: 5px;
            color: #20b2aa;
        }
        .footer-note {
            text-align: center;
            margin: 1rem 0 0.5rem;
            color: #3b5f7a;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-wallet"></i></div>
            <span class="logo-text">expn.do</span>
        </div>
        <div class="user-section">
            <div class="nav-links">
                <a href="home.php" class="nav-item"><i class="fas fa-home"></i> Home</a>
                <a href="profile.php" class="nav-item active"><i class="fas fa-user-circle"></i> Profile details</a>
                <a href="categories.php" class="nav-item"><i class="fas fa-tags"></i> Categories</a>
                <a href="graphs.php" class="nav-item"><i class="fas fa-chart-line"></i> Graphs</a>
                <a href="help.php" class="nav-item"><i class="fas fa-question-circle"></i> Help</a>
                <a href="about.php" class="nav-item"><i class="fas fa-info-circle"></i> About us</a>
            </div>
            <span class="user-info">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['username'] ?? 'User'); ?>
            </span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="page-container">
        <div class="page-card">
            <div class="page-title">
                <i class="fas fa-id-card"></i>
                <span>Profile details</span>
            </div>
            <div class="divider"></div>

            <?php if ($success_message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="profile-header">
                <div class="avatar-lg">
                    <i class="fas fa-user-astronaut"></i>
                </div>
                <div style="flex:1;">
                    <h2 style="font-size: 2rem; color:#001f3f;"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h2>
                    <p style="color:#20b2aa;">
                        <i class="fas fa-envelope" style="color:#001f3f;"></i> 
                        <?php echo htmlspecialchars($user['email']); ?> · 
                        joined <?php echo $join_date; ?>
                    </p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-box">
                    <i class="fas fa-receipt"></i>
                    <div class="stat-value"><?php echo $stats['total_transactions'] ?: 0; ?></div>
                    <div class="stat-label">Transactions</div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-indian-rupee-sign"></i>
                    <div class="stat-value">₹<?php echo number_format($stats['total_spent'] ?: 0); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-calendar-alt"></i>
                    <div class="stat-value"><?php echo $stats['active_months'] ?: 0; ?></div>
                    <div class="stat-label">Active Months</div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-chart-line"></i>
                    <div class="stat-value">₹<?php echo number_format($stats['avg_transaction'] ?: 0); ?></div>
                    <div class="stat-label">Avg. Transaction</div>
                </div>
            </div>

            <!-- Favorite Categories -->
            <?php if (mysqli_num_rows($category_result) > 0): ?>
                <div style="margin: 1.5rem 0;">
                    <p style="color:#001f3f; margin-bottom: 0.5rem;"><i class="fas fa-star" style="color:#20b2aa;"></i> Top spending categories:</p>
                    <?php while ($cat = mysqli_fetch_assoc($category_result)): ?>
                        <span class="category-badge">
                            <i class="fas <?php echo $category_icons[$cat['category']] ?? 'fa-tag'; ?>"></i>
                            <?php echo htmlspecialchars($cat['category']); ?> (₹<?php echo number_format($cat['total']); ?>)
                        </span>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <!-- Tabs for Edit Profile and Change Password -->
            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="showTab('profile')">Profile Information</button>
                    <button class="tab-btn" onclick="showTab('password')">Change Password</button>
                    <button class="tab-btn" onclick="showTab('settings')">Account Settings</button>
                </div>

                <!-- Profile Information Tab -->
                <div id="profile-tab" class="tab-pane active">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Full Name</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" placeholder="Enter your full name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Username</label>
                            <div class="input-group">
                                <i class="fas fa-at"></i>
                                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled style="background:#f0f4f8;">
                            </div>
                            <small style="color:#666;">Username cannot be changed</small>
                        </div>

                        <div class="form-group">
                            <label>Email Address</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Preferred Currency</label>
                            <div class="input-group">
                                <i class="fas fa-coins"></i>
                                <select name="currency">
                                    <?php foreach ($currency_symbols as $symbol => $name): ?>
                                        <option value="<?php echo $symbol; ?>" <?php echo ($settings['currency_symbol'] ?? '₹') == $symbol ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Monthly Budget Cap (<?php echo $settings['currency_symbol'] ?? '₹'; ?>)</label>
                            <div class="input-group">
                                <i class="fas fa-chart-line"></i>
                                <input type="number" name="monthly_budget" step="1000" value="<?php echo $settings['monthly_budget_cap'] ?? 100000; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notifications</label>
                            <div class="checkbox-group">
                                <input type="checkbox" name="notifications" <?php echo ($settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                <span>Receive email notifications and daily summary</span>
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn-save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>

                <!-- Change Password Tab -->
                <div id="password-tab" class="tab-pane">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Current Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="current_password" required placeholder="Enter current password">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>New Password</label>
                            <div class="input-group">
                                <i class="fas fa-key"></i>
                                <input type="password" name="new_password" required placeholder="Minimum 6 characters">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <div class="input-group">
                                <i class="fas fa-check-circle"></i>
                                <input type="password" name="confirm_password" required placeholder="Re-enter new password">
                            </div>
                        </div>

                        <button type="submit" name="change_password" class="btn-change">
                            <i class="fas fa-sync-alt"></i> Change Password
                        </button>
                    </form>
                </div>

                <!-- Account Settings Tab -->
                <div id="settings-tab" class="tab-pane">
                    <div class="info-row">
                        <span class="info-label">Account created</span>
                        <span class="info-value"><?php echo date('F j, Y \a\t g:i A', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last updated</span>
                        <span class="info-value"><?php echo date('F j, Y \a\t g:i A', strtotime($user['updated_at'] ?? $user['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last transaction</span>
                        <span class="info-value">
                            <?php echo $stats['last_transaction_date'] ? date('F j, Y', strtotime($stats['last_transaction_date'])) : 'No transactions yet'; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Account status</span>
                        <span class="info-value">
                            <span style="color:#2e7d32; font-weight:600;">Active</span> 
                            <i class="fas fa-circle" style="color:#2e7d32; font-size:0.6rem;"></i>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Linked accounts</span>
                        <span class="info-value">
                            <i class="fas fa-university" style="color:#20b2aa;"></i> 2 banks · 
                            <i class="fas fa-credit-card" style="color:#20b2aa;"></i> 1 credit card
                        </span>
                    </div>
                    
                    <p style="margin-top: 2rem; padding: 1rem; background: #f0f7fa; border-radius: 50px;">
                        <i class="fas fa-shield-alt" style="color: #20b2aa; margin-right: 10px;"></i> 
                        Your data is private and secure – only visible to you
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-note">
        <i class="fas fa-credit-card" style="color:#20b2aa;"></i> tracker · navy & teal · georgia
    </div>

    <script>
        function showTab(tab) {
            // Hide all tabs
            document.getElementById('profile-tab').classList.remove('active');
            document.getElementById('password-tab').classList.remove('active');
            document.getElementById('settings-tab').classList.remove('active');
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tab + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>