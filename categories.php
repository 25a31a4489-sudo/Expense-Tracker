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

// Handle form submissions
$success_message = '';
$error_message = '';

// Add new category
if (isset($_POST['add_category'])) {
    $category_name = sanitize($_POST['category_name']);
    $category_icon = sanitize($_POST['category_icon']);
    
    if (empty($category_name)) {
        $error_message = "Category name is required";
    } else {
        // Check if category already exists for this user
        $check_query = "SELECT id FROM categories WHERE user_id = ? AND category_name = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "is", $user_id, $category_name);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error_message = "Category already exists";
        } else {
            $insert_query = "INSERT INTO categories (user_id, category_name, category_icon, is_default) VALUES (?, ?, ?, 0)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "iss", $user_id, $category_name, $category_icon);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Category added successfully!";
            } else {
                $error_message = "Failed to add category";
            }
        }
    }
}

// Delete category
if (isset($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    
    // Check if category belongs to user and is not default
    $check_query = "SELECT id FROM categories WHERE id = ? AND user_id = ? AND is_default = 0";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $category_id, $user_id);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        $delete_query = "DELETE FROM categories WHERE id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $category_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Category deleted successfully!";
        } else {
            $error_message = "Failed to delete category";
        }
    } else {
        $error_message = "Cannot delete default category";
    }
}

// Edit category
if (isset($_POST['edit_category'])) {
    $category_id = intval($_POST['category_id']);
    $category_name = sanitize($_POST['category_name']);
    $category_icon = sanitize($_POST['category_icon']);
    
    // Check if category belongs to user and is not default
    $check_query = "SELECT id FROM categories WHERE id = ? AND user_id = ? AND is_default = 0";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $category_id, $user_id);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        $update_query = "UPDATE categories SET category_name = ?, category_icon = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ssi", $category_name, $category_icon, $category_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Category updated successfully!";
        } else {
            $error_message = "Failed to update category";
        }
    } else {
        $error_message = "Cannot edit default category";
    }
}

// Get all categories (default + user's custom categories)
$categories_query = "SELECT * FROM categories WHERE user_id IS NULL OR user_id = ? ORDER BY is_default DESC, category_name ASC";
$stmt = mysqli_prepare($conn, $categories_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$categories_result = mysqli_stmt_get_result($stmt);

// Get spending by category for current month
$current_month = date('Y-m-01');
$current_month_end = date('Y-m-t');
$spending_query = "SELECT category, COALESCE(SUM(amount), 0) as total, COUNT(*) as count 
                   FROM expenses 
                   WHERE user_id = ? AND expense_date BETWEEN ? AND ? 
                   GROUP BY category";
$stmt = mysqli_prepare($conn, $spending_query);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $current_month, $current_month_end);
mysqli_stmt_execute($stmt);
$spending_result = mysqli_stmt_get_result($stmt);

$category_spending = [];
while ($row = mysqli_fetch_assoc($spending_result)) {
    $category_spending[$row['category']] = $row;
}

// Available icons for categories
$available_icons = [
    'fa-utensils' => 'Food & Dining',
    'fa-bus' => 'Transport',
    'fa-house-chimney' => 'Housing',
    'fa-lightbulb' => 'Utilities',
    'fa-bag-shopping' => 'Shopping',
    'fa-heart-pulse' => 'Health',
    'fa-plane' => 'Travel',
    'fa-film' => 'Entertainment',
    'fa-graduation-cap' => 'Education',
    'fa-gift' => 'Gifts',
    'fa-coffee' => 'Coffee',
    'fa-wine-bottle' => 'Drinks',
    'fa-dumbbell' => 'Fitness',
    'fa-dog' => 'Pets',
    'fa-mobile' => 'Mobile',
    'fa-wifi' => 'Internet',
    'fa-water' => 'Water',
    'fa-gas-pump' => 'Fuel',
    'fa-book' => 'Books',
    'fa-gamepad' => 'Gaming',
    'fa-music' => 'Music',
    'fa-camera' => 'Photography',
    'fa-cut' => 'Beauty',
    'fa-tshirt' => 'Clothing',
    'fa-shoe-prints' => 'Footwear'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories · ExpenseTracker</title>
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
        .cat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .cat-item {
            background: #f8fafc;
            border-radius: 30px;
            padding: 1.5rem 1rem;
            text-align: center;
            border: 1px solid #9fc7d0;
            transition: all 0.3s;
            position: relative;
        }
        .cat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(32,178,170,0.2);
            border-color: #20b2aa;
        }
        .cat-item i {
            font-size: 2.2rem;
            background: white;
            padding: 15px;
            border-radius: 50%;
            color: #001f3f;
            box-shadow: 0 4px 8px rgba(0,31,63,0.08);
            margin-bottom: 0.5rem;
        }
        .cat-item span {
            display: block;
            margin-top: 10px;
            font-weight: 600;
            color: #001f3f;
            font-size: 1.1rem;
        }
        .cat-stats {
            margin-top: 0.8rem;
            font-size: 0.85rem;
            color: #3b5f7a;
        }
        .cat-stats .amount {
            font-weight: 700;
            color: #20b2aa;
        }
        .default-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #20b2aa;
            color: #001f3f;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .cat-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .cat-item:hover .cat-actions {
            opacity: 1;
        }
        .cat-actions button, .cat-actions a {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            transition: all 0.2s;
        }
        .edit-btn {
            color: #001f3f;
            background: #e3f2f5;
        }
        .edit-btn:hover {
            background: #20b2aa;
            color: white;
        }
        .delete-btn {
            color: #c62828;
            background: #ffebee;
        }
        .delete-btn:hover {
            background: #c62828;
            color: white;
        }
        .add-category-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 30px;
            border: 2px dashed #b0e0e6;
        }
        .add-category-section h3 {
            color: #001f3f;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .add-category-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: center;
        }
        .form-group {
            position: relative;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #b0e0e6;
            border-radius: 50px;
            font-size: 1rem;
            font-family: Georgia, serif;
            outline: none;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #20b2aa;
            box-shadow: 0 0 0 3px rgba(32,178,170,0.2);
        }
        .btn-add {
            background: #20b2aa;
            color: #001f3f;
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .btn-add:hover {
            background: #001f3f;
            color: white;
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
        .category-summary {
            background: linear-gradient(135deg, #001f3f 0%, #1a3e5c 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            margin-bottom: 1.5rem;
            display: inline-block;
        }
        .footer-note {
            text-align: center;
            margin: 1rem 0 0.5rem;
            color: #3b5f7a;
            font-size: 0.95rem;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 32px;
            max-width: 500px;
            width: 90%;
            border: 3px solid #20b2aa;
        }
        .modal-content h3 {
            color: #001f3f;
            margin-bottom: 1.5rem;
        }
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
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
        }
        .btn-cancel {
            background: #f0f4f8;
            color: #001f3f;
            padding: 0.8rem 2rem;
            border: 2px solid #b0e0e6;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
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
                <a href="categories.php" class="nav-item active"><i class="fas fa-tags"></i> Categories</a>
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
                <i class="fas fa-tags"></i>
                <span>Expense categories</span>
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

            <!-- Category Summary -->
            <div class="category-summary">
                <i class="fas fa-chart-pie" style="color:#20b2aa;"></i>
                <?php 
                $total_categories = mysqli_num_rows($categories_result);
                $custom_categories = 0;
                mysqli_data_seek($categories_result, 0);
                while ($cat = mysqli_fetch_assoc($categories_result)) {
                    if ($cat['user_id'] == $user_id) $custom_categories++;
                }
                mysqli_data_seek($categories_result, 0);
                ?>
                <span><?php echo $total_categories; ?> total categories (<?php echo $custom_categories; ?> custom)</span>
            </div>

            <!-- Categories Grid -->
            <div class="cat-grid">
                <?php while ($category = mysqli_fetch_assoc($categories_result)): 
                    $is_default = $category['is_default'] == 1;
                    $spending = $category_spending[$category['category_name']] ?? null;
                    $total_spent = $spending ? $spending['total'] : 0;
                    $transaction_count = $spending ? $spending['count'] : 0;
                ?>
                    <div class="cat-item">
                        <?php if ($is_default): ?>
                            <span class="default-badge"><i class="fas fa-star"></i> Default</span>
                        <?php endif; ?>
                        
                        <i class="fas <?php echo htmlspecialchars($category['category_icon']); ?>"></i>
                        <span><?php echo htmlspecialchars($category['category_name']); ?></span>
                        
                        <?php if ($total_spent > 0): ?>
                            <div class="cat-stats">
                                <span class="amount">₹<?php echo number_format($total_spent); ?></span>
                                <span>(<?php echo $transaction_count; ?> txns)</span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$is_default): ?>
                            <div class="cat-actions">
                                <button class="edit-btn" onclick="openEditModal(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['category_name']); ?>', '<?php echo htmlspecialchars($category['category_icon']); ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?php echo $category['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this category?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Add Category Section -->
            <div class="add-category-section">
                <h3>
                    <i class="fas fa-plus-circle" style="color:#20b2aa;"></i>
                    Add Custom Category
                </h3>
                
                <form method="POST" action="" class="add-category-form">
                    <div class="form-group">
                        <input type="text" name="category_name" placeholder="Category name" required>
                    </div>
                    
                    <div class="form-group">
                        <select name="category_icon" required>
                            <option value="">Select icon</option>
                            <?php foreach ($available_icons as $icon => $name): ?>
                                <option value="<?php echo $icon; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="add_category" class="btn-add">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                </form>
            </div>
            
            <p style="margin-top: 1.5rem; text-align: center; color: #666;">
                <i class="fas fa-info-circle" style="color:#20b2aa;"></i>
                Default categories cannot be edited or deleted. Custom categories can be managed freely.
            </p>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-edit" style="color:#20b2aa;"></i> Edit Category</h3>
            
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #001f3f;">Category Name</label>
                    <input type="text" name="category_name" id="edit_category_name" required style="width: 100%; padding: 0.8rem; border: 2px solid #b0e0e6; border-radius: 50px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #001f3f;">Category Icon</label>
                    <select name="category_icon" id="edit_category_icon" required style="width: 100%; padding: 0.8rem; border: 2px solid #b0e0e6; border-radius: 50px;">
                        <option value="">Select icon</option>
                        <?php foreach ($available_icons as $icon => $name): ?>
                            <option value="<?php echo $icon; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" name="edit_category" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="footer-note">
        <i class="fas fa-credit-card" style="color:#20b2aa;"></i> tracker · navy & teal · georgia
    </div>

    <script>
        function openEditModal(id, name, icon) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_category_icon').value = icon;
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>