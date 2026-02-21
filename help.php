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

// Get user settings for personalized help
$settings_query = "SELECT * FROM user_settings WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $settings_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$settings_result = mysqli_stmt_get_result($stmt);
$settings = mysqli_fetch_assoc($settings_result);

// Handle contact form submission
$contact_success = '';
$contact_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    if (empty($subject) || empty($message)) {
        $contact_error = "Please fill in all fields";
    } else {
        // In a real application, you would send an email here
        // For now, we'll just show a success message
        $contact_success = "Your message has been sent! We'll get back to you within 24 hours.";
    }
}

// FAQ categories
$faq_categories = [
    'getting_started' => 'Getting Started',
    'expenses' => 'Managing Expenses',
    'budget' => 'Budget & Reports',
    'account' => 'Account Settings',
    'troubleshooting' => 'Troubleshooting'
];

// FAQ items
$faqs = [
    'getting_started' => [
        [
            'question' => 'How do I create an account?',
            'answer' => 'Click on the "Register" link on the login page. Fill in your details (username, email, password) and you\'re ready to start tracking your expenses!'
        ],
        [
            'question' => 'How do I log in to my account?',
            'answer' => 'Enter your username/email and password on the login page. If you\'ve forgotten your password, click on "Forgot Password" to reset it.'
        ],
        [
            'question' => 'Is my data secure?',
            'answer' => 'Yes! All your data is encrypted and stored securely. We use industry-standard security practices to protect your information.'
        ]
    ],
    'expenses' => [
        [
            'question' => 'How do I add an expense?',
            'answer' => 'Click the "Add Expense" button on your dashboard. Fill in the amount, category, date, and description. You can also add expenses quickly using the quick entry form.'
        ],
        [
            'question' => 'Can I edit or delete an expense?',
            'answer' => 'Yes! Go to the "All Expenses" page, find the expense you want to modify, and click the edit or delete button next to it.'
        ],
        [
            'question' => 'How do I categorize my expenses?',
            'answer' => 'When adding an expense, you can select from existing categories or create your own custom categories in the Categories page.'
        ],
        [
            'question' => 'Can I add recurring expenses?',
            'answer' => 'Yes! You can set up recurring expenses (daily, weekly, monthly) in the "Recurring Expenses" section. (Premium feature)'
        ]
    ],
    'budget' => [
        [
            'question' => 'How do I set a monthly budget?',
            'answer' => 'Go to your Profile page and look for "Monthly Budget Cap". Enter your desired budget amount and save changes.'
        ],
        [
            'question' => 'Can I export my expense data?',
            'answer' => 'Yes! You can export your data as CSV or PDF from the Graphs page. Click on "Export as CSV" or "Export as PDF" to download your data.'
        ],
        [
            'question' => 'How do I view expense reports?',
            'answer' => 'Visit the Graphs page to see visual representations of your spending. You can toggle between weekly and monthly views.'
        ],
        [
            'question' => 'What do the progress bars mean?',
            'answer' => 'Progress bars show how much of your budget you\'ve spent in each category. They help you visualize your spending at a glance.'
        ]
    ],
    'account' => [
        [
            'question' => 'How do I change my password?',
            'answer' => 'Go to your Profile page and click on the "Change Password" tab. Enter your current password and new password to update.'
        ],
        [
            'question' => 'How do I update my email address?',
            'answer' => 'In your Profile page, you can edit your email address in the "Profile Information" tab. Don\'t forget to save your changes!'
        ],
        [
            'question' => 'Can I change my preferred currency?',
            'answer' => 'Yes! In the Profile page, you can select your preferred currency from the dropdown menu. Available currencies include ₹, $, €, £, and more.'
        ],
        [
            'question' => 'How do I enable/disable notifications?',
            'answer' => 'In your Profile settings, you can toggle email notifications on/off using the checkbox in the Profile Information tab.'
        ]
    ],
    'troubleshooting' => [
        [
            'question' => 'What if I forget my password?',
            'answer' => 'Click on "Forgot Password" on the login page. Enter your email address, and we\'ll send you instructions to reset your password.'
        ],
        [
            'question' => 'Why are my totals not adding up?',
            'answer' => 'Check that all your expenses are in the correct category and date range. You can filter expenses by date to verify.'
        ],
        [
            'question' => 'How do I contact support?',
            'answer' => 'Use the contact form at the bottom of this page, or email us directly at support@expn.do. We typically respond within 24 hours.'
        ],
        [
            'question' => 'Is there a mobile app?',
            'answer' => 'Currently, we offer a mobile-friendly website. A dedicated mobile app is in development and will be available soon!'
        ]
    ]
];

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help · ExpenseTracker</title>
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
        
        /* Search bar */
        .search-container {
            margin: 2rem 0;
            position: relative;
        }
        .search-input {
            width: 100%;
            padding: 1rem 1.5rem;
            padding-left: 3.5rem;
            border: 2px solid #b0e0e6;
            border-radius: 60px;
            font-size: 1.1rem;
            font-family: Georgia, serif;
            outline: none;
            transition: all 0.3s;
        }
        .search-input:focus {
            border-color: #20b2aa;
            box-shadow: 0 0 0 4px rgba(32,178,170,0.2);
        }
        .search-icon {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #20b2aa;
            font-size: 1.2rem;
        }
        
        /* Category tabs */
        .category-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: 2rem 0;
            justify-content: center;
        }
        .category-tab {
            padding: 0.6rem 1.5rem;
            background: #f0f4f8;
            border: 2px solid #b0e0e6;
            border-radius: 40px;
            color: #001f3f;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .category-tab i {
            color: #20b2aa;
        }
        .category-tab:hover {
            background: #20b2aa;
            border-color: #001f3f;
        }
        .category-tab:hover i {
            color: #001f3f;
        }
        .category-tab.active {
            background: #20b2aa;
            border-color: #001f3f;
        }
        .category-tab.active i {
            color: #001f3f;
        }
        
        /* FAQ sections */
        .faq-section {
            margin: 2rem 0;
            display: none;
        }
        .faq-section.active {
            display: block;
        }
        .faq-section h3 {
            color: #001f3f;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3rem;
        }
        .faq-item {
            background: #f8fafc;
            border-radius: 20px;
            margin-bottom: 1rem;
            border: 1px solid #b0e0e6;
            overflow: hidden;
            transition: all 0.3s;
        }
        .faq-item:hover {
            border-color: #20b2aa;
            box-shadow: 0 5px 15px rgba(32,178,170,0.1);
        }
        .faq-question {
            padding: 1.2rem 1.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 600;
            color: #001f3f;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }
        .faq-question i {
            color: #20b2aa;
            transition: transform 0.3s;
        }
        .faq-question.active i {
            transform: rotate(90deg);
        }
        .faq-answer {
            padding: 0 1.8rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease-in-out;
            color: #1d3e5e;
            line-height: 1.6;
        }
        .faq-answer.show {
            padding: 0 1.8rem 1.5rem 1.8rem;
            max-height: 200px;
        }
        
        /* Contact form */
        .contact-section {
            margin-top: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, #001f3f 0%, #1a3e5c 100%);
            border-radius: 30px;
            border: 2px solid #20b2aa;
        }
        .contact-section h3 {
            color: white;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .contact-section h3 i {
            color: #20b2aa;
        }
        .contact-form {
            display: grid;
            gap: 1.5rem;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid #b0e0e6;
            border-radius: 50px;
            font-size: 1rem;
            font-family: Georgia, serif;
            outline: none;
            background: white;
        }
        .form-group textarea {
            border-radius: 30px;
            resize: vertical;
            min-height: 120px;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #20b2aa;
            box-shadow: 0 0 0 4px rgba(32,178,170,0.2);
        }
        .btn-send {
            background: #20b2aa;
            color: #001f3f;
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            width: fit-content;
        }
        .btn-send:hover {
            background: white;
            border-color: #20b2aa;
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
        .help-faq p {
            background: #f0f7fa;
            padding: 1.2rem 1.8rem;
            border-radius: 40px;
            margin: 1rem 0;
            border-left: 6px solid #20b2aa;
            color: #001f3f;
        }
        .support-email {
            margin-top: 2rem;
            background: #d9f0f2;
            padding: 1rem;
            border-radius: 50px;
            text-align: center;
        }
        .support-email a {
            color: #001f3f;
            text-decoration: none;
            font-weight: 600;
        }
        .support-email a:hover {
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
                <i class="fas fa-headset"></i>
                <span>Help Center</span>
            </div>
            <div class="divider"></div>

            <!-- Search Bar -->
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Search for help topics..." onkeyup="searchFAQ()">
            </div>

            <!-- Category Tabs -->
            <div class="category-tabs">
                <?php foreach ($faq_categories as $key => $label): ?>
                    <button class="category-tab <?php echo $key == 'getting_started' ? 'active' : ''; ?>" 
                            onclick="showCategory('<?php echo $key; ?>')">
                        <?php
                        $icons = [
                            'getting_started' => 'fa-rocket',
                            'expenses' => 'fa-wallet',
                            'budget' => 'fa-chart-pie',
                            'account' => 'fa-user-cog',
                            'troubleshooting' => 'fa-wrench'
                        ];
                        ?>
                        <i class="fas <?php echo $icons[$key]; ?>"></i>
                        <?php echo $label; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- FAQ Sections -->
            <?php foreach ($faqs as $category => $items): ?>
                <div id="faq-<?php echo $category; ?>" class="faq-section <?php echo $category == 'getting_started' ? 'active' : ''; ?>">
                    <h3>
                        <?php
                        $section_icons = [
                            'getting_started' => 'fa-rocket',
                            'expenses' => 'fa-wallet',
                            'budget' => 'fa-chart-pie',
                            'account' => 'fa-user-cog',
                            'troubleshooting' => 'fa-wrench'
                        ];
                        ?>
                        <i class="fas <?php echo $section_icons[$category]; ?>" style="color:#20b2aa;"></i>
                        <?php echo $faq_categories[$category]; ?>
                    </h3>
                    
                    <?php foreach ($items as $index => $faq): ?>
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFAQ(this)">
                                <i class="fas fa-chevron-right" style="color:#20b2aa;"></i>
                                <?php echo htmlspecialchars($faq['question']); ?>
                            </div>
                            <div class="faq-answer">
                                <?php echo htmlspecialchars($faq['answer']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <!-- Quick Help Section (Original FAQ) -->
            <div style="margin-top: 3rem;">
                <h3 style="color:#001f3f; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-bolt" style="color:#20b2aa;"></i> Quick Answers
                </h3>
                <div class="help-faq">
                    <p><i class="fas fa-angle-right" style="margin-right: 10px; color:#20b2aa;"></i> 
                        <strong>How to add an expense?</strong> – Click the "Add Expense" button on your dashboard or use the quick entry form.
                    </p>
                    <p><i class="fas fa-angle-right" style="margin-right: 10px; color:#20b2aa;"></i> 
                        <strong>Can I export data?</strong> – Yes, go to the Graphs page and click on "Export as CSV" or "Export as PDF".
                    </p>
                    <p><i class="fas fa-angle-right" style="margin-right: 10px; color:#20b2aa;"></i> 
                        <strong>Set monthly budget:</strong> Go to Profile → Profile Information tab → Monthly Budget Cap.
                    </p>
                    <p><i class="fas fa-angle-right" style="margin-right: 10px; color:#20b2aa;"></i> 
                        <strong>Multi‑currency?</strong> Supported in our premium plan. Upgrade to access multiple currencies.
                    </p>
                    <p><i class="fas fa-angle-right" style="margin-right: 10px; color:#20b2aa;"></i> 
                        <strong>Lost data?</strong> Your data is automatically backed up in the cloud. Contact support if you need recovery.
                    </p>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="contact-section">
                <h3>
                    <i class="fas fa-envelope"></i>
                    Still need help? Contact us
                </h3>

                <?php if ($contact_success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $contact_success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($contact_error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $contact_error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="contact-form">
                    <div class="form-group">
                        <input type="text" name="subject" placeholder="Subject" required>
                    </div>
                    <div class="form-group">
                        <textarea name="message" placeholder="Describe your issue or question..." required></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn-send">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>

            <!-- Support Email -->
            <div class="support-email">
                <i class="fas fa-envelope-open-text" style="color:#20b2aa;"></i> 
                Email us directly: <a href="mailto:support@expn.do">support@expn.do</a>
                <br>
                <small style="color:#666;">We typically respond within 24 hours</small>
            </div>
        </div>
    </div>

    <div class="footer-note">
        <i class="fas fa-credit-card" style="color:#20b2aa;"></i> tracker · navy & teal · georgia
    </div>

    <script>
        // Toggle FAQ answers
        function toggleFAQ(element) {
            element.classList.toggle('active');
            const answer = element.nextElementSibling;
            answer.classList.toggle('show');
        }

        // Show category
        function showCategory(category) {
            // Hide all sections
            document.querySelectorAll('.faq-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById('faq-' + category).classList.add('active');
            
            // Update tab buttons
            document.querySelectorAll('.category-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        // Search functionality
        function searchFAQ() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Open first FAQ item by default
        window.onload = function() {
            const firstFaq = document.querySelector('.faq-question');
            if (firstFaq) {
                firstFaq.classList.add('active');
                firstFaq.nextElementSibling.classList.add('show');
            }
        };
    </script>
</body>
</html>