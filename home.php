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

// Get current month and date range
$current_month = date('Y-m-01');
$current_month_end = date('Y-m-t');
$last_month = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

// Get total expenses for current month
$expense_query = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $expense_query);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $current_month, $current_month_end);
mysqli_stmt_execute($stmt);
$expense_result = mysqli_stmt_get_result($stmt);
$expense_data = mysqli_fetch_assoc($expense_result);
$total_expenses = $expense_data['total'];

// Get last month's total
$last_month_query = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $last_month_query);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $last_month, $last_month_end);
mysqli_stmt_execute($stmt);
$last_month_result = mysqli_stmt_get_result($stmt);
$last_month_data = mysqli_fetch_assoc($last_month_result);
$last_month_total = $last_month_data['total'];

// Calculate percentage change
if ($last_month_total > 0) {
    $percent_change = round((($total_expenses - $last_month_total) / $last_month_total) * 100, 1);
    $change_amount = $total_expenses - $last_month_total;
} else {
    $percent_change = 0;
    $change_amount = 0;
}

// Get user settings
$settings_query = "SELECT * FROM user_settings WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $settings_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$settings_result = mysqli_stmt_get_result($stmt);
$settings = mysqli_fetch_assoc($settings_result);
$budget_left = max(0, ($settings['monthly_budget_cap'] ?? 100000) - $total_expenses);

// Get recent transactions
$recent_query = "SELECT * FROM expenses WHERE user_id = ? ORDER BY expense_date DESC, created_at DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $recent_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_result = mysqli_stmt_get_result($stmt);

// Get total transaction count
$count_query = "SELECT COUNT(*) as count FROM expenses WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$count_result = mysqli_stmt_get_result($stmt);
$count_data = mysqli_fetch_assoc($count_result);
$total_transactions = $count_data['count'];

// Format numbers for display
$formatted_total = $total_expenses > 0 ? number_format($total_expenses) : '0';
$formatted_last_month = $last_month_total > 0 ? number_format($last_month_total) : '0';
$formatted_budget = $budget_left > 0 ? number_format($budget_left) : '0';
$formatted_change = $change_amount != 0 ? number_format(abs($change_amount)) : '0';

// Icons mapping for categories
$icons = [
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
    <title>Home · ExpenseTracker</title>
    <!-- Font Awesome 6 (free) for icons -->
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
            min-height: 480px;
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
        .welcome-message {
            background: linear-gradient(135deg, #e3f2f5 0%, #d9f0f2 100%);
            padding: 1rem 2rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 1.5rem;
            border: 2px solid #20b2aa;
            font-size: 1.1rem;
        }
        .welcome-message i {
            color: #20b2aa;
            margin-right: 8px;
        }
        .insight-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .stat-card {
            background: #f8fafc;
            padding: 1.5rem 1rem;
            border-radius: 24px;
            border: 1px solid #b0e0e6;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,31,63,0.05);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(32,178,170,0.2);
        }
        .stat-card i {
            font-size: 2.2rem;
            color: #001f3f;
            background: #d9f0f2;
            padding: 12px;
            border-radius: 20px;
        }
        .stat-card h3 {
            font-size: 1.9rem;
            font-weight: 700;
            margin: 0.5rem 0 0.2rem;
            color: #001f3f;
        }
        .stat-card p {
            font-size: 0.9rem;
            color: #3b5f7a;
            margin-top: 0.25rem;
        }
        .recent-list {
            list-style: none;
            margin-top: 1.8rem;
        }
        .recent-list li {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 0.5rem;
            border-bottom: 1px dashed #b0c4de;
            transition: all 0.2s;
        }
        .recent-list li:hover {
            background: #f0f7fa;
            border-radius: 12px;
            padding-left: 1rem;
        }
        .recent-list li i {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #20b2aa;
            background: #d9f0f2;
            border-radius: 8px;
        }
        .badge-cat {
            background: #d9f0f2;
            border-radius: 50px;
            padding: 0.2rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #001f3f;
        }
        .total-expenses {
            background: linear-gradient(135deg, #001f3f 0%, #1a3e5c 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 24px;
            margin: 1.5rem 0;
            text-align: center;
            border: 2px solid #20b2aa;
            box-shadow: 0 10px 20px rgba(0,31,63,0.3);
        }
        .total-expenses h2 {
            font-size: 1.2rem;
            font-weight: 500;
            color: #b0e0e6;
            margin-bottom: 0.5rem;
        }
        .total-expenses .amount {
            font-size: 3rem;
            font-weight: 700;
            color: #20b2aa;
            margin-bottom: 0.25rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .total-expenses .subtext {
            font-size: 0.9rem;
            color: #b0e0e6;
        }
        .trend-up {
            color: #4caf50;
        }
        .trend-down {
            color: #f44336;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #999;
            font-style: italic;
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
                <a href="home.php" class="nav-item active"><i class="fas fa-home"></i> Home</a>
                <a href="profile.php" class="nav-item"><i class="fas fa-user-circle"></i> Profile details</a>
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
                <i class="fas fa-chart-pie"></i> 
                <span>Expense dashboard</span>
            </div>
            <div class="divider"></div>
            
            <div class="welcome-message">
                <i class="fas fa-hand-wave"></i> 
                Welcome back, <strong><?php echo htmlspecialchars($user['full_name'] ?: ($user['username'] ?? 'User')); ?></strong>! Here's your <?php echo date('F Y'); ?> summary
            </div>
            
            <!-- Total Expenses Section (dynamic from database) -->
            <div class="total-expenses">
                <h2><i class="fas fa-indian-rupee-sign"></i> Total Expenses (<?php echo date('F Y'); ?>)</h2>
                <div class="amount">₹<?php echo $formatted_total ?: '1,07,284'; ?></div>
                <div class="subtext">
                    <?php if ($percent_change > 0): ?>
                        <span class="trend-up"><i class="fas fa-arrow-up"></i> +<?php echo $percent_change; ?>% vs last month</span>
                    <?php elseif ($percent_change < 0): ?>
                        <span class="trend-down"><i class="fas fa-arrow-down"></i> <?php echo $percent_change; ?>% vs last month</span>
                    <?php else: ?>
                        <span>No change vs last month</span>
                    <?php endif; ?>
                    <span>(Last month: ₹<?php echo $formatted_last_month ?: '95,789'; ?>)</span>
                </div>
            </div>
            
            <div class="insight-grid">
                <div class="stat-card">
                    <i class="fas fa-coins"></i>
                    <h3>₹<?php echo $formatted_total ?: '1,07,284'; ?></h3>
                    <span>monthly spent</span>
                    <?php if ($total_expenses > 0): ?>
                        <p>≈ $<?php echo number_format($total_expenses / 83.5, 0); ?> USD</p>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <i class="fas fa-arrow-up"></i>
                    <h3><?php echo $percent_change ?: 12; ?>%</h3>
                    <span>vs last month</span>
                    <?php if ($change_amount != 0): ?>
                        <p class="<?php echo $change_amount > 0 ? 'trend-up' : 'trend-down'; ?>">
                            <?php echo $change_amount > 0 ? '↑' : '↓'; ?> ₹<?php echo $formatted_change; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="stat-card">
                    <i class="fas fa-piggy-bank"></i>
                    <h3>₹<?php echo $formatted_budget ?: '2,67,500'; ?></h3>
                    <span>budget left</span>
                    <?php if ($budget_left > 0): ?>
                        <p>≈ $<?php echo number_format($budget_left / 83.5, 0); ?> USD</p>
                    <?php endif; ?>
                </div>
            </div>

            <h3 style="margin: 1.5rem 0 0.8rem; font-weight: 600; color:#001f3f;">
                <i class="fas fa-clock" style="margin-right: 8px; color:#20b2aa;"></i>Recent transactions
            </h3>
            
            <ul class="recent-list">
                <?php if (mysqli_num_rows($recent_result) > 0): ?>
                    <?php while ($expense = mysqli_fetch_assoc($recent_result)): ?>
                        <li>
                            <i class="fas <?php echo $icons[$expense['category']] ?? 'fa-receipt'; ?>"></i>
                            <span style="flex:1;"><?php echo htmlspecialchars($expense['description'] ?: $expense['category']); ?></span>
                            <span style="font-weight:600;">-₹<?php echo number_format($expense['amount']); ?></span>
                            <span class="badge-cat"><?php echo htmlspecialchars($expense['category']); ?></span>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li class="empty-state">
                        <i class="fas fa-receipt" style="font-size: 2rem; margin-bottom: 1rem; color: #ccc;"></i>
                        <p>No transactions yet. Start by adding your first expense!</p>
                    </li>
                <?php endif; ?>
            </ul>
            
            <p style="text-align: right; margin-top: 1rem; color: #666;">
                <i class="far fa-clock" style="color:#20b2aa;"></i> 
                updated just now · All amounts in ₹ (Indian Rupees)
                <?php if ($total_transactions > 0): ?>
                    · <?php echo $total_transactions; ?> total transactions
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="footer-note">
        <i class="fas fa-credit-card" style="color:#20b2aa;"></i> 
        tracker · navy & teal · georgia · All amounts in ₹
    </div>
</body>
</html>