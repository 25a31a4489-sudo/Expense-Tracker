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

// Get date ranges
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

// Get weekly expenses
$weekly_query = "SELECT DAYOFWEEK(expense_date) as day_num, 
                        DAYNAME(expense_date) as day_name,
                        COALESCE(SUM(amount), 0) as total
                 FROM expenses 
                 WHERE user_id = ? AND expense_date BETWEEN ? AND ?
                 GROUP BY expense_date
                 ORDER BY expense_date";
$stmt = mysqli_prepare($conn, $weekly_query);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $week_start, $week_end);
mysqli_stmt_execute($stmt);
$weekly_result = mysqli_stmt_get_result($stmt);

// Initialize weekly data
$week_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$week_data = array_fill(0, 7, 0);
$week_categories = [];

while ($row = mysqli_fetch_assoc($weekly_result)) {
    // Convert day name to index (MySQL DAYOFWEEK: 1=Sunday, 2=Monday, ..., 7=Saturday)
    $day_index = $row['day_num'] - 2; // Adjust to 0=Monday
    if ($day_index >= 0 && $day_index < 7) {
        $week_data[$day_index] = $row['total'];
    }
}
$weekly_total = array_sum($week_data);

// Get monthly expenses (by week)
$monthly_query = "SELECT 
                    WEEK(expense_date, 1) - WEEK(DATE_SUB(expense_date, INTERVAL DAYOFMONTH(expense_date)-1 DAY), 1) + 1 as week_num,
                    COALESCE(SUM(amount), 0) as total
                  FROM expenses 
                  WHERE user_id = ? AND expense_date BETWEEN ? AND ?
                  GROUP BY week_num
                  ORDER BY week_num";
$stmt = mysqli_prepare($conn, $monthly_query);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$monthly_result = mysqli_stmt_get_result($stmt);

// Initialize monthly data
$month_weeks = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
$month_data = array_fill(0, 4, 0);
$monthly_total = 0;

while ($row = mysqli_fetch_assoc($monthly_result)) {
    $week_index = $row['week_num'] - 1;
    if ($week_index >= 0 && $week_index < 4) {
        $month_data[$week_index] = $row['total'];
        $monthly_total += $row['total'];
    }
}

// Get category breakdown for current week
$weekly_cat_query = "SELECT 
                        category, 
                        COALESCE(SUM(amount), 0) as total,
                        COUNT(*) as count
                     FROM expenses 
                     WHERE user_id = ? AND expense_date BETWEEN ? AND ?
                     GROUP BY category
                     ORDER BY total DESC";
$stmt = mysqli_prepare($conn, $weekly_cat_query);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $week_start, $week_end);
mysqli_stmt_execute($stmt);
$weekly_cat_result = mysqli_stmt_get_result($stmt);

$weekly_categories = [];
while ($row = mysqli_fetch_assoc($weekly_cat_result)) {
    $weekly_categories[] = $row;
}

// Get category breakdown for current month
$monthly_cat_query = "SELECT 
                        category, 
                        COALESCE(SUM(amount), 0) as total,
                        COUNT(*) as count
                     FROM expenses 
                     WHERE user_id = ? AND expense_date BETWEEN ? AND ?
                     GROUP BY category
                     ORDER BY total DESC";
$stmt = mysqli_prepare($conn, $monthly_cat_query);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$monthly_cat_result = mysqli_stmt_get_result($stmt);

$monthly_categories = [];
while ($row = mysqli_fetch_assoc($monthly_cat_result)) {
    $monthly_categories[] = $row;
}

// Get last week's total for comparison
$last_week_start = date('Y-m-d', strtotime('monday last week'));
$last_week_end = date('Y-m-d', strtotime('sunday last week'));
$last_week_query = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $last_week_query);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $last_week_start, $last_week_end);
mysqli_stmt_execute($stmt);
$last_week_result = mysqli_stmt_get_result($stmt);
$last_week_data = mysqli_fetch_assoc($last_week_result);
$last_week_total = $last_week_data['total'];

// Get last month's total
$last_month_query = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $last_month_query);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $last_month_start, $last_month_end);
mysqli_stmt_execute($stmt);
$last_month_result = mysqli_stmt_get_result($stmt);
$last_month_data = mysqli_fetch_assoc($last_month_result);
$last_month_total = $last_month_data['total'];

// Calculate trends
$week_trend = $last_week_total > 0 ? round((($weekly_total - $last_week_total) / $last_week_total) * 100, 1) : 0;
$month_trend = $last_month_total > 0 ? round((($monthly_total - $last_month_total) / $last_month_total) * 100, 1) : 0;

// Get highest spending day
$highest_day_query = "SELECT 
                        DATE_FORMAT(expense_date, '%W') as day_name,
                        COALESCE(SUM(amount), 0) as total
                      FROM expenses 
                      WHERE user_id = ? AND expense_date BETWEEN ? AND ?
                      GROUP BY expense_date
                      ORDER BY total DESC
                      LIMIT 1";
$stmt = mysqli_prepare($conn, $highest_day_query);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $week_start, $week_end);
mysqli_stmt_execute($stmt);
$highest_day_result = mysqli_stmt_get_result($stmt);
$highest_day = mysqli_fetch_assoc($highest_day_result);

// Calculate average daily
$avg_daily = $weekly_total > 0 ? round($weekly_total / 7, 2) : 0;

// Icons mapping
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
    <title>Graphs · ExpenseTracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
        
        /* Time period selector */
        .time-selector {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .time-btn {
            background: #f0f4f8;
            border: 2px solid #b0e0e6;
            color: #001f3f;
            padding: 0.8rem 2rem;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .time-btn i {
            color: #20b2aa;
        }
        .time-btn.active {
            background: #20b2aa;
            color: #001f3f;
            border-color: #001f3f;
        }
        .time-btn.active i {
            color: #001f3f;
        }
        .time-btn:hover {
            background: #20b2aa;
            color: #001f3f;
        }
        
        /* Chart container with glass effect */
        .chart-container {
            max-width: 800px;
            margin: 2rem auto;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            padding: 2rem 1.5rem;
            border-radius: 40px;
            border: 2px solid #20b2aa;
            box-shadow: 0 20px 30px -10px rgba(0,31,63,0.2);
        }
        
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .stat-card-small {
            background: linear-gradient(135deg, #001f3f 0%, #1a3e5c 100%);
            padding: 1.2rem;
            border-radius: 24px;
            text-align: center;
            border: 2px solid #20b2aa;
        }
        .stat-card-small i {
            font-size: 1.8rem;
            color: #20b2aa;
            margin-bottom: 0.5rem;
        }
        .stat-card-small .label {
            color: #b0e0e6;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }
        .stat-card-small .value {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        /* Category breakdown */
        .category-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem;
            margin: 2rem 0;
        }
        .cat-stat {
            background: #f0f4f8;
            padding: 1rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid #b0e0e6;
        }
        .cat-stat i {
            font-size: 1.5rem;
            color: #20b2aa;
            background: white;
            padding: 10px;
            border-radius: 15px;
        }
        .cat-stat .cat-info {
            flex: 1;
        }
        .cat-stat .cat-name {
            font-weight: 600;
            color: #001f3f;
            margin-bottom: 0.2rem;
        }
        .cat-stat .cat-amount {
            font-weight: 700;
            color: #20b2aa;
        }
        .cat-stat .progress-bar {
            width: 100%;
            height: 6px;
            background: #d9f0f2;
            border-radius: 10px;
            margin-top: 0.3rem;
        }
        .cat-stat .progress-fill {
            height: 100%;
            background: #20b2aa;
            border-radius: 10px;
            width: 0%;
        }
        
        /* Insight cards */
        .insight-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .insight-card {
            background: linear-gradient(135deg, #20b2aa10 0%, #20b2aa20 100%);
            padding: 1.2rem;
            border-radius: 24px;
            border: 1px solid #20b2aa;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .insight-card i {
            font-size: 2rem;
            color: #20b2aa;
            background: white;
            padding: 12px;
            border-radius: 20px;
        }
        .insight-card .insight-text {
            flex: 1;
        }
        .insight-card .insight-title {
            color: #001f3f;
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        .insight-card .insight-value {
            color: #001f3f;
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
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
                <a href="profile.php" class="nav-item"><i class="fas fa-user-circle"></i> Profile details</a>
                <a href="categories.php" class="nav-item"><i class="fas fa-tags"></i> Categories</a>
                <a href="graphs.php" class="nav-item active"><i class="fas fa-chart-line"></i> Graphs</a>
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
                <i class="fas fa-chart-simple"></i>
                <span>Expense Analytics</span>
            </div>
            <div class="divider"></div>
            
            <!-- Time Period Selector -->
            <div class="time-selector">
                <button class="time-btn active" onclick="switchTimePeriod('week')">
                    <i class="fas fa-calendar-week"></i> This Week
                </button>
                <button class="time-btn" onclick="switchTimePeriod('month')">
                    <i class="fas fa-calendar-alt"></i> This Month
                </button>
            </div>

            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card-small">
                    <i class="fas fa-indian-rupee-sign"></i>
                    <div class="label">Weekly Total</div>
                    <div class="value" id="weeklyTotal">₹<?php echo number_format($weekly_total); ?></div>
                </div>
                <div class="stat-card-small">
                    <i class="fas fa-chart-line"></i>
                    <div class="label">Monthly Total</div>
                    <div class="value" id="monthlyTotal">₹<?php echo number_format($monthly_total); ?></div>
                </div>
                <div class="stat-card-small">
                    <i class="fas fa-trending-up"></i>
                    <div class="label">Avg. Daily</div>
                    <div class="value" id="avgDaily">₹<?php echo number_format($avg_daily); ?></div>
                </div>
            </div>
            
            <!-- Main Chart -->
            <div class="chart-container">
                <?php if ($weekly_total > 0 || $monthly_total > 0): ?>
                    <canvas id="expenseChart" width="400" height="250" style="width:100%; height:auto;"></canvas>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <p>No expense data available. Add some expenses to see your analytics!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Category Breakdown -->
            <h3 style="margin: 2rem 0 1rem; color:#001f3f; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-pie-chart" style="color:#20b2aa;"></i> Category Breakdown <span id="categoryPeriod">(This Week)</span>
            </h3>
            <div class="category-breakdown" id="categoryBreakdown">
                <?php if (empty($weekly_categories)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #999;">
                        <i class="fas fa-chart-pie" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>No category data for this period</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $total = $weekly_total;
                    foreach ($weekly_categories as $cat): 
                        $percentage = $total > 0 ? round(($cat['total'] / $total) * 100, 1) : 0;
                        $icon = $category_icons[$cat['category']] ?? 'fa-tag';
                    ?>
                    <div class="cat-stat">
                        <i class="fas <?php echo $icon; ?>"></i>
                        <div class="cat-info">
                            <div class="cat-name"><?php echo htmlspecialchars($cat['category']); ?></div>
                            <div class="cat-amount">₹<?php echo number_format($cat['total']); ?></div>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Insights Section -->
            <h3 style="margin: 2rem 0 1rem; color:#001f3f; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-lightbulb" style="color:#20b2aa;"></i> Key Insights
            </h3>
            <div class="insight-cards" id="insightCards">
                <div class="insight-card">
                    <i class="fas fa-arrow-up"></i>
                    <div class="insight-text">
                        <div class="insight-title">Highest Spending Day</div>
                        <div class="insight-value" id="highestDay">
                            <?php echo $highest_day ? $highest_day['day_name'] . ' (₹' . number_format($highest_day['total']) . ')' : 'No data'; ?>
                        </div>
                    </div>
                </div>
                <div class="insight-card">
                    <i class="fas fa-utensils"></i>
                    <div class="insight-text">
                        <div class="insight-title">Top Category</div>
                        <div class="insight-value" id="topCategory">
                            <?php 
                            if (!empty($weekly_categories)) {
                                $top = $weekly_categories[0];
                                $percentage = $weekly_total > 0 ? round(($top['total'] / $weekly_total) * 100, 1) : 0;
                                echo htmlspecialchars($top['category']) . ' (' . $percentage . '%)';
                            } else {
                                echo 'No data';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="insight-card">
                    <i class="fas fa-chart-line"></i>
                    <div class="insight-text">
                        <div class="insight-title">vs Last Week</div>
                        <div class="insight-value" id="weekTrend">
                            <?php if ($week_trend > 0): ?>
                                <span style="color:#4caf50;">↑ <?php echo $week_trend; ?>% increase</span>
                            <?php elseif ($week_trend < 0): ?>
                                <span style="color:#f44336;">↓ <?php echo abs($week_trend); ?>% decrease</span>
                            <?php else: ?>
                                No change
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Options -->
            <p style="text-align: center; margin-top: 2rem; background:#e3f2f5; padding:1rem; border-radius: 60px; display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap;">
                <span style="cursor: pointer;" onclick="exportData('pdf')"><i class="fas fa-download" style="color:#20b2aa;"></i> Export as PDF</span>
                <span style="cursor: pointer;" onclick="exportData('csv')"><i class="fas fa-file-csv" style="color:#20b2aa;"></i> Export as CSV</span>
                <span style="cursor: pointer;" onclick="showDatePicker()"><i class="fas fa-calendar-alt" style="color:#20b2aa;"></i> Select Date Range</span>
            </p>
        </div>
    </div>

    <div class="footer-note">
        <i class="fas fa-credit-card" style="color:#20b2aa;"></i> tracker · navy & teal · georgia · All amounts in ₹
    </div>

    <script>
        let currentChart;
        let currentPeriod = 'week';

        // Data from PHP
        const weekData = {
            labels: <?php echo json_encode($week_days); ?>,
            data: <?php echo json_encode($week_data); ?>,
            total: <?php echo $weekly_total; ?>,
            avgDaily: <?php echo $avg_daily; ?>,
            categories: <?php echo json_encode($weekly_categories); ?>,
            categoryIcons: <?php echo json_encode($category_icons); ?>
        };

        const monthData = {
            labels: <?php echo json_encode($month_weeks); ?>,
            data: <?php echo json_encode($month_data); ?>,
            total: <?php echo $monthly_total; ?>,
            avgDaily: <?php echo round($monthly_total / 30, 2); ?>,
            categories: <?php echo json_encode($monthly_categories); ?>,
            categoryIcons: <?php echo json_encode($category_icons); ?>
        };

        function createChart(period) {
            const ctx = document.getElementById('expenseChart')?.getContext('2d');
            if (!ctx) return;
            
            if (currentChart) {
                currentChart.destroy();
            }

            const data = period === 'week' ? weekData : monthData;
            
            currentChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Expenses (₹)',
                        data: data.data,
                        borderColor: '#20b2aa',
                        backgroundColor: 'rgba(32, 178, 170, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: '#001f3f',
                        pointBorderColor: '#20b2aa',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '₹' + context.raw.toLocaleString('en-IN');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#d3e0e8' },
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString('en-IN');
                                }
                            }
                        }
                    }
                }
            });

            // Update stats
            document.getElementById('weeklyTotal').textContent = '₹' + weekData.total.toLocaleString('en-IN');
            document.getElementById('monthlyTotal').textContent = '₹' + monthData.total.toLocaleString('en-IN');
            document.getElementById('avgDaily').textContent = '₹' + data.avgDaily.toLocaleString('en-IN');
            document.getElementById('categoryPeriod').textContent = period === 'week' ? '(This Week)' : '(This Month)';

            // Update category breakdown
            updateCategoryBreakdown(data.categories, data.total, period);
            
            // Update insights
            updateInsights(period);
        }

        function updateCategoryBreakdown(categories, total, period) {
            const container = document.getElementById('categoryBreakdown');
            
            if (!categories || categories.length === 0) {
                container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #999;"><i class="fas fa-chart-pie" style="font-size: 2rem; margin-bottom: 1rem;"></i><p>No category data for this period</p></div>';
                return;
            }

            const icons = period === 'week' ? weekData.categoryIcons : monthData.categoryIcons;
            
            container.innerHTML = categories.map(cat => {
                const percentage = total > 0 ? ((cat.total / total) * 100).toFixed(1) : 0;
                const icon = icons[cat.category] || 'fa-tag';
                return `
                    <div class="cat-stat">
                        <i class="fas ${icon}"></i>
                        <div class="cat-info">
                            <div class="cat-name">${cat.category}</div>
                            <div class="cat-amount">₹${Number(cat.total).toLocaleString('en-IN')}</div>
                            <div class="progress-bar"><div class="progress-fill" style="width: ${percentage}%"></div></div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function updateInsights(period) {
            const data = period === 'week' ? weekData : monthData;
            const trend = period === 'week' ? <?php echo $week_trend; ?> : <?php echo $month_trend; ?>;
            
            // Update top category
            if (data.categories && data.categories.length > 0) {
                const top = data.categories[0];
                const percentage = data.total > 0 ? ((top.total / data.total) * 100).toFixed(1) : 0;
                document.querySelector('#insightCards .insight-card:nth-child(2) .insight-value').textContent = 
                    `${top.category} (${percentage}%)`;
            }

            // Update trend
            const trendElement = document.querySelector('#insightCards .insight-card:nth-child(3) .insight-value');
            if (trend > 0) {
                trendElement.innerHTML = `<span style="color:#4caf50;">↑ ${trend}% increase</span>`;
            } else if (trend < 0) {
                trendElement.innerHTML = `<span style="color:#f44336;">↓ ${Math.abs(trend)}% decrease</span>`;
            } else {
                trendElement.textContent = 'No change';
            }
        }

        function switchTimePeriod(period) {
            currentPeriod = period;
            
            // Update button states
            document.querySelectorAll('.time-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update chart
            createChart(period);
        }

        function exportData(type) {
            alert(`Export as ${type.toUpperCase()} - This feature will be available soon!`);
        }

        function showDatePicker() {
            alert('Date range picker - This feature will be available soon!');
        }

        window.onload = function() {
            if (<?php echo $weekly_total > 0 || $monthly_total > 0 ? 'true' : 'false'; ?>) {
                createChart('week');
            }
        };
    </script>
</body>
</html>