<?php
session_start();
require_once('../includes/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Fetch system settings
$stmt = $conn->prepare("SELECT setting_name, setting_value FROM system_settings");
$stmt->execute();
$result = $stmt->get_result();
$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Beauty Shop Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <?php if (isset($additional_css)): ?>
        <?php echo $additional_css; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h4><?php echo htmlspecialchars($settings['system_name'] ?? 'Beauty Shop'); ?></h4>
        </div>
        <ul>
            <li class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li class="<?php echo $current_page === 'products' ? 'active' : ''; ?>">
                <a href="products.php"><i class="fas fa-box"></i> Products</a>
            </li>
            <li class="<?php echo $current_page === 'purchases' ? 'active' : ''; ?>">
                <a href="purchases.php"><i class="fas fa-shopping-cart"></i> Purchases</a>
            </li>
            <li class="<?php echo $current_page === 'sales' ? 'active' : ''; ?>">
                <a href="sales.php"><i class="fas fa-dollar-sign"></i> Sales</a>
            </li>
            <li class="<?php echo $current_page === 'customers' ? 'active' : ''; ?>">
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
            </li>
            <li class="<?php echo $current_page === 'suppliers' ? 'active' : ''; ?>">
                <a href="suppliers.php"><i class="fas fa-truck"></i> Suppliers</a>
            </li>
            <li class="<?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            </li>
            <li class="<?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            </li>
            <li>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </div>
</body>
</html> 