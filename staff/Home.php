<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Debug: Print session variables
echo "Current session variables:<br>";
echo "user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . "<br>";
echo "user_role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'not set') . "<br>";
echo "user_name: " . (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'not set') . "<br>";

// Only 'staff' can see this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    echo "Redirecting to index.php because:<br>";
    if (!isset($_SESSION['user_id'])) {
        echo "- user_id is not set<br>";
    }
    if ($_SESSION['user_role'] !== 'staff') {
        echo "- user_role is not 'staff'<br>";
    }
    header("Location: ../index.php");
    exit;
}

// 1) DB Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafeteria-management-system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize counts
$numFoodItems = 0;
$totalSales = 0;
$lowStock = 0;
$todaySales = 0;

// 2) Fetch Number of Food Items
$sql = "SELECT COUNT(*) AS countItems FROM food_items";
$res = $conn->query($sql);
if ($res) {
    $row = $res->fetch_assoc();
    $numFoodItems = $row['countItems'] ?? 0;
}

// 3) Fetch Total Sales
$sql = "SELECT SUM(total_amount) AS totalSales FROM daily_sales";
$res = $conn->query($sql);
if ($res) {
    $row = $res->fetch_assoc();
    $totalSales = $row['totalSales'] ?? 0;
}

// 4) Fetch Items Needing Restock (stock < min_stock_level)
$sql = "SELECT COUNT(*) AS countLow 
        FROM stock_inventory si 
        JOIN food_items fi ON si.food_item_id = fi.id 
        WHERE si.quantity <= si.min_stock_level";
$res = $conn->query($sql);
if ($res) {
    $row = $res->fetch_assoc();
    $lowStock = $row['countLow'] ?? 0;
}

// 5) Fetch Today's Sales
$today = date('Y-m-d');
$sql = "SELECT SUM(total_amount) AS todaySales FROM daily_sales WHERE sale_date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $todaySales = $row['todaySales'] ?? 0;
}

$conn->close();

// Get staff name from session
$staffName = $_SESSION['user_name'] ?? 'Staff';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Cafeteria Staff Dashboard</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Your staff CSS -->
    <link rel="stylesheet" href="Staff.css">
</head>

<body>
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-utensils"></i>
            <span class="logo-text">Cafeteria Management</span>
        </div>
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class="fas fa-chevron-left" id="toggleIcon"></i>
        </button>
        <ul>
            <li><a href="#" class="active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="link-text">Dashboard</span>
                </a></li>
            <li><a href="Products.php">
                    <i class="fas fa-hamburger"></i>
                    <span class="link-text">Food Items</span>
                </a></li>
            <li><a href="Purchases.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="link-text">Purchases</span>
                </a></li>
            <li><a href="Sales.php">
                    <i class="fas fa-cash-register"></i>
                    <span class="link-text">Sales</span>
                </a></li>
            <li><a href="Customers.php">
                    <i class="fas fa-users"></i>
                    <span class="link-text">Customers</span>
                </a></li>
            <li><a href="Settings.php">
                    <i class="fas fa-cog"></i>
                    <span class="link-text">Settings</span>
                </a></li>
            <li><a href="/inventory_management/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="link-text">Logout</span>
                </a></li>
        </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-content" id="mainContent">
        <!-- HEADER -->
        <header>
            <h1>Welcome to the Cafeteria Dashboard</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <p><strong><?php echo htmlspecialchars($staffName); ?></strong></p>
                    <small>Staff Member</small>
                </div>
            </div>
        </header>

        <!-- DASHBOARD CARDS -->
        <div class="dashboard">
            <!-- 1) Food Items Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Food Items</h3>
                    <div class="card-icon">
                        <i class="fas fa-hamburger"></i>
                    </div>
                </div>
                <p>Total menu items</p>
                <div class="card-value"><?php echo $numFoodItems; ?></div>
            </div>

            <!-- 2) Today's Sales Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Today's Sales</h3>
                    <div class="card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <p>Total sales for today</p>
                <div class="card-value">Ksh <?php echo number_format($todaySales, 2); ?></div>
            </div>

            <!-- 3) Low Stock Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Low Stock</h3>
                    <div class="card-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <p>Items needing restock</p>
                <div class="card-value"><?php echo $lowStock; ?></div>
            </div>

            <!-- 4) Total Sales Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Total Sales</h3>
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <p>All-time sales</p>
                <div class="card-value">Ksh <?php echo number_format($totalSales, 2); ?></div>
            </div>
        </div>

        <!-- MAIN CONTAINER -->
        <div class="container">
            <h2>Welcome to the Cafeteria Management System</h2>
            <p>
                As a <strong>staff member</strong>, you have access to manage the cafeteria's daily operations.
                Use the sidebar to navigate through different sections:
            </p>
            <ul>
                <li><strong>Food Items:</strong> View and manage the menu items, prices, and stock levels</li>
                <li><strong>Purchases:</strong> Record new inventory purchases and manage suppliers</li>
                <li><strong>Sales:</strong> Process customer orders and track daily sales</li>
                <li><strong>Customers:</strong> Manage customer information and loyalty programs</li>
                <li><strong>Settings:</strong> Configure system preferences and user settings</li>
            </ul>
            <p>
                The dashboard above shows key metrics including total food items, today's sales,
                low stock alerts, and overall sales performance.
            </p>
        </div>
    </div>

    <!-- SIDEBAR TOGGLE SCRIPT -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('toggleSidebar');
            const toggleIcon = document.getElementById('toggleIcon');

            toggleBtn.addEventListener('click', function () {
                sidebar.classList.toggle('sidebar-collapsed');

                if (sidebar.classList.contains('sidebar-collapsed')) {
                    toggleIcon.classList.replace('fa-chevron-left', 'fa-chevron-right');
                } else {
                    toggleIcon.classList.replace('fa-chevron-right', 'fa-chevron-left');
                }
            });

            // For mobile responsiveness
            if (window.innerWidth <= 768) {
                sidebar.classList.add('sidebar-collapsed');
                toggleIcon.classList.replace('fa-chevron-left', 'fa-chevron-right');
            }
        });
    </script>
</body>

</html>