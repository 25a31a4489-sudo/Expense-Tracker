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

// Get company statistics from database
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM expenses) as total_expenses,
    (SELECT COALESCE(SUM(amount), 0) FROM expenses) as total_amount_tracked,
    (SELECT COUNT(DISTINCT user_id) FROM expenses WHERE expense_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_users_month
    FROM dual";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Team members data
$team_members = [
    [
        'name' => 'Alex Morgan',
        'role' => 'Founder & CEO',
        'bio' => 'Former finance analyst who wanted to make expense tracking simple for everyone.',
        'icon' => 'fa-user-astronaut'
    ],
    [
        'name' => 'Sarah Chen',
        'role' => 'Lead Developer',
        'bio' => 'Full-stack developer with a passion for creating beautiful, intuitive interfaces.',
        'icon' => 'fa-user-ninja'
    ],
    [
        'name' => 'Raj Patel',
        'role' => 'UX Designer',
        'bio' => 'Designer focused on making financial data accessible and easy to understand.',
        'icon' => 'fa-user-pen'
    ],
    [
        'name' => 'Priya Sharma',
        'role' => 'Customer Success',
        'bio' => 'Dedicated to helping users get the most out of their expense tracking experience.',
        'icon' => 'fa-user-headset'
    ]
];

// Milestones data
$milestones = [
    ['year' => '2025', 'event' => 'expn.do founded with a simple mission: make expense tracking simple'],
    ['year' => '2025', 'event' => 'Launched first version with basic expense tracking features'],
    ['year' => '2026', 'event' => 'Reached 10,000 active users milestone'],
    ['year' => '2026', 'event' => 'Introduced advanced analytics and reporting features'],
    ['year' => '2027', 'event' => 'Launched mobile apps for iOS and Android']
];

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us · ExpenseTracker</title>
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
        
        /* Mission Section */
        .mission-section {
            background: linear-gradient(135deg, #001f3f 0%, #1a3e5c 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 30px;
            margin: 2rem 0;
            border: 3px solid #20b2aa;
            text-align: center;
        }
        .mission-section h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #20b2aa;
        }
        .mission-section p {
            font-size: 1.2rem;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            color: #b0e0e6;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .stat-item {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 24px;
            text-align: center;
            border: 2px solid #b0e0e6;
            transition: all 0.3s;
        }
        .stat-item:hover {
            transform: translateY(-5px);
            border-color: #20b2aa;
            box-shadow: 0 10px 20px rgba(32,178,170,0.2);
        }
        .stat-item i {
            font-size: 2.5rem;
            color: #20b2aa;
            margin-bottom: 1rem;
        }
        .stat-item .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #001f3f;
        }
        .stat-item .stat-label {
            color: #3b5f7a;
            font-size: 1rem;
        }
        
        /* About Flex (original) */
        .about-flex {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin: 2rem 0;
        }
        .about-text-block {
            flex: 1.2;
        }
        .about-text-block h3 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #001f3f;
            margin-bottom: 1rem;
        }
        .about-text-block p {
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 1rem 0;
            color: #1d3e5e;
        }
        .about-quote {
            flex: 1;
            background: #e7f1f9;
            border-radius: 48px;
            padding: 2rem;
            text-align: center;
            border: 2px solid #20b2aa;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .about-quote i {
            font-size: 3rem;
            color: #20b2aa;
            margin-bottom: 1rem;
        }
        .about-quote p {
            font-size: 1.2rem;
            color: #001f3f;
            line-height: 1.5;
        }
        
        /* Team Section */
        .team-section {
            margin: 3rem 0;
        }
        .team-section h3 {
            color: #001f3f;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        .team-card {
            background: #f8fafc;
            border-radius: 30px;
            padding: 2rem 1.5rem;
            text-align: center;
            border: 2px solid #b0e0e6;
            transition: all 0.3s;
        }
        .team-card:hover {
            transform: translateY(-5px);
            border-color: #20b2aa;
            box-shadow: 0 15px 30px rgba(32,178,170,0.15);
        }
        .team-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #001f3f 0%, #1a3e5c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            border: 3px solid #20b2aa;
        }
        .team-icon i {
            font-size: 3rem;
            color: #20b2aa;
        }
        .team-card h4 {
            color: #001f3f;
            font-size: 1.3rem;
            margin-bottom: 0.3rem;
        }
        .team-card .role {
            color: #20b2aa;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .team-card .bio {
            color: #1d3e5e;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        /* Milestones */
        .milestones-section {
            margin: 3rem 0;
        }
        .milestones-section h3 {
            color: #001f3f;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .timeline {
            position: relative;
            padding: 2rem 0;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 100%;
            background: #20b2aa;
            border-radius: 2px;
        }
        .timeline-item {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            position: relative;
        }
        .timeline-item:nth-child(even) {
            flex-direction: row-reverse;
        }
        .timeline-content {
            width: 45%;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 24px;
            border: 2px solid #b0e0e6;
            position: relative;
        }
        .timeline-content::before {
            content: '';
            position: absolute;
            top: 50%;
            width: 20px;
            height: 20px;
            background: #20b2aa;
            border-radius: 50%;
        }
        .timeline-item:nth-child(odd) .timeline-content::before {
            right: -40px;
            transform: translateY(-50%);
        }
        .timeline-item:nth-child(even) .timeline-content::before {
            left: -40px;
            transform: translateY(-50%);
        }
        .timeline-year {
            font-size: 1.5rem;
            font-weight: 700;
            color: #20b2aa;
            margin-bottom: 0.5rem;
        }
        .timeline-event {
            color: #001f3f;
        }
        
        /* Values Section */
        .values-section {
            margin: 3rem 0;
        }
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        .value-card {
            background: #f8fafc;
            padding: 2rem 1.5rem;
            border-radius: 30px;
            text-align: center;
            border: 2px solid #b0e0e6;
        }
        .value-card i {
            font-size: 2.5rem;
            color: #20b2aa;
            margin-bottom: 1rem;
        }
        .value-card h4 {
            color: #001f3f;
            margin-bottom: 0.5rem;
        }
        .value-card p {
            color: #1d3e5e;
            font-size: 0.95rem;
        }
        
        /* Social Links */
        .social-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 2rem 0;
        }
        .social-link {
            width: 50px;
            height: 50px;
            background: #f8fafc;
            border: 2px solid #b0e0e6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #001f3f;
            font-size: 1.5rem;
            transition: all 0.3s;
            text-decoration: none;
        }
        .social-link:hover {
            background: #20b2aa;
            border-color: #001f3f;
            transform: translateY(-3px);
        }
        
        .footer-note {
            text-align: center;
            margin: 1rem 0 0.5rem;
            color: #3b5f7a;
            font-size: 0.95rem;
        }
        hr {
            border: none;
            height: 1px;
            background: #c0dae8;
            margin: 1.8rem 0;
        }
        
        @media (max-width: 768px) {
            .timeline::before {
                left: 30px;
            }
            .timeline-item {
                flex-direction: column !important;
                margin-left: 60px;
            }
            .timeline-content {
                width: 100%;
            }
            .timeline-content::before {
                left: -40px !important;
                right: auto !important;
            }
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
                <a href="home.php" class="nav-item <?php echo $current_page == 'home.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="profile.php" class="nav-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i> Profile details
                </a>
                <a href="categories.php" class="nav-item <?php echo $current_page == 'categories.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i> Categories
                </a>
                <a href="graphs.php" class="nav-item <?php echo $current_page == 'graphs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i> Graphs
                </a>
                <a href="help.php" class="nav-item <?php echo $current_page == 'help.php' ? 'active' : ''; ?>">
                    <i class="fas fa-question-circle"></i> Help
                </a>
                <a href="about.php" class="nav-item <?php echo $current_page == 'about.php' ? 'active' : ''; ?>">
                    <i class="fas fa-info-circle"></i> About us
                </a>
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
                <i class="fas fa-people-group"></i>
                <span>About us</span>
            </div>
            <div class="divider"></div>

            <!-- Mission Statement -->
            <div class="mission-section">
                <h2>Making Finance Simple</h2>
                <p>Our mission is to help everyone understand and control their finances through simple, beautiful, and intuitive tools. No complicated jargon, no hidden fees – just clarity.</p>
            </div>

            <!-- Company Stats -->
            <div class="stats-grid">
                <div class="stat-item">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo number_format($stats['total_users'] ?? 15000); ?>+</div>
                    <div class="stat-label">Happy Users</div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-receipt"></i>
                    <div class="stat-number"><?php echo number_format($stats['total_expenses'] ?? 250000); ?>+</div>
                    <div class="stat-label">Expenses Tracked</div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-indian-rupee-sign"></i>
                    <div class="stat-number">₹<?php echo number_format(($stats['total_amount_tracked'] ?? 50000000) / 10000000, 1); ?>Cr+</div>
                    <div class="stat-label">Amount Tracked</div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-calendar-check"></i>
                    <div class="stat-number"><?php echo $stats['active_users_month'] ?? 5000; ?>+</div>
                    <div class="stat-label">Monthly Active</div>
                </div>
            </div>

            <!-- Original About Content -->
            <div class="about-flex">
                <div class="about-text-block">
                    <h3>expn.do</h3>
                    <p>We're a tiny team passionate about making expense tracking actually <em>simple</em>. No ads, no clutter — just clear insights into your spending habits.</p>
                    <p>Founded in 2025, we believe that understanding where your money goes should be beautiful and effortless. What started as a personal project grew into a tool used by thousands to take control of their finances.</p>
                    <p>Our team is distributed across the globe, working remotely to build the best expense tracking experience. We're bootstrapped, independent, and focused on our users – not investors.</p>
                    
                    <!-- Social Links -->
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-github"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="about-quote">
                    <i class="fas fa-hand-holding-heart"></i>
                    <p>"clarity starts here"</p>
                    <p style="margin-top: 1rem; font-size: 0.9rem;">— no images, just purpose</p>
                </div>
            </div>

            <!-- Our Values -->
            <div class="values-section">
                <h3 style="color:#001f3f; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-heart" style="color:#20b2aa;"></i> Our Values
                </h3>
                <div class="values-grid">
                    <div class="value-card">
                        <i class="fas fa-shield-alt"></i>
                        <h4>Privacy First</h4>
                        <p>Your data belongs to you. We never sell or share your information.</p>
                    </div>
                    <div class="value-card">
                        <i class="fas fa-paint-brush"></i>
                        <h4>Beautiful Design</h4>
                        <p>Finance shouldn't be ugly. We make tracking your money a pleasure.</p>
                    </div>
                    <div class="value-card">
                        <i class="fas fa-smile"></i>
                        <h4>User Focused</h4>
                        <p>Every feature is built with our users' needs in mind.</p>
                    </div>
                    <div class="value-card">
                        <i class="fas fa-leaf"></i>
                        <h4>Sustainable</h4>
                        <p>Building a company that lasts, not chasing quick profits.</p>
                    </div>
                </div>
            </div>

            <!-- Team Section -->
            <div class="team-section">
                <h3>
                    <i class="fas fa-users" style="color:#20b2aa;"></i> Meet the Team
                </h3>
                <div class="team-grid">
                    <?php foreach ($team_members as $member): ?>
                        <div class="team-card">
                            <div class="team-icon">
                                <i class="fas <?php echo $member['icon']; ?>"></i>
                            </div>
                            <h4><?php echo htmlspecialchars($member['name']); ?></h4>
                            <div class="role"><?php echo htmlspecialchars($member['role']); ?></div>
                            <p class="bio"><?php echo htmlspecialchars($member['bio']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Milestones -->
            <div class="milestones-section">
                <h3>
                    <i class="fas fa-trophy" style="color:#20b2aa;"></i> Our Journey
                </h3>
                <div class="timeline">
                    <?php foreach ($milestones as $milestone): ?>
                        <div class="timeline-item">
                            <div class="timeline-content">
                                <div class="timeline-year"><?php echo $milestone['year']; ?></div>
                                <div class="timeline-event"><?php echo $milestone['event']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <hr>

            <!-- Footer Info -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <p><i class="far fa-copyright" style="color:#20b2aa;"></i> 2025-<?php echo date('Y'); ?> expn.do · Version 2.0</p>
                <p style="color:#1d3e5e;">
                    <i class="fas fa-map-pin" style="color:#20b2aa;"></i> Made with ❤️ · Navy & Teal · Georgia
                </p>
                <p>
                    <i class="fas fa-envelope" style="color:#20b2aa;"></i> 
                    <a href="mailto:hello@expn.do" style="color:#001f3f; text-decoration: none;">hello@expn.do</a>
                </p>
            </div>
        </div>
    </div>

    <div class="footer-note">
        <i class="fas fa-credit-card" style="color:#20b2aa;"></i> tracker · navy & teal · georgia
    </div>
</body>
</html>