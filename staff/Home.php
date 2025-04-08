<?php
session_start();

// Only 'staff' can see this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

// 1) DB Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop"; // Adjust if needed

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2) Fetch Number of Products
$sql = "SELECT COUNT(*) AS countProd FROM beautyshop";
$res = $conn->query($sql);
$row = $res->fetch_assoc();
$numProducts = $row['countProd'] ?? 0;

// 3) Fetch Number of Customers
$sql = "SELECT COUNT(*) AS countCust FROM customers";
$res = $conn->query($sql);
$row = $res->fetch_assoc();
$numCustomers = $row['countCust'] ?? 0;

// 4) Fetch Items Needing Restock (stock < 10)
$sql = "SELECT COUNT(*) AS countLow FROM beautyshop WHERE stock < 10";
$res = $conn->query($sql);
$row = $res->fetch_assoc();
$lowStock = $row['countLow'] ?? 0;

$conn->close();

// Optional: store staff name if you have it in session
$staffName = $_SESSION['user_name'] ?? 'Staff';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Staff Home</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Your staff CSS -->
    <link rel="stylesheet" href="Staff.css">
</head>

<body>
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-spa"></i>
            <span class="logo-text">Beauty Shop (Staff)</span>
        </div>
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class="fas fa-chevron-left" id="toggleIcon"></i>
        </button>
        <ul>
            <li><a href="#" class="active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="link-text">Home</span>
                </a></li>
            <li><a href="Products.php">
                    <i class="fas fa-pump-soap"></i>
                    <span class="link-text">Products</span>
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
            <h1>Welcome to the Staff Dashboard</h1>
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
            <!-- 1) Products Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Products</h3>
                    <div class="card-icon">
                        <i class="fas fa-pump-soap"></i>
                    </div>
                </div>
                <p>Total inventory items</p>
                <div class="card-value"><?php echo $numProducts; ?></div>
            </div>

            <!-- 2) Customers Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Customers</h3>
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <p>Registered customers</p>
                <div class="card-value"><?php echo $numCustomers; ?></div>
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
        </div>

        <!-- MAIN CONTAINER -->
        <div class="container">
            <h2> Welcome</h2>
            <p>
                Thank you for being a part of the Beauty Shop team. As a <strong>staff member</strong>,
                your role is vital in ensuring our products, sales, and customers are well managed.
            </p>
            <p>
                Use the sidebar on the left to navigate through your tasks. You can manage product
                inventory, oversee purchases, record sales, and maintain customer relationships
                all from this unified interface.
            </p>
            <p>
                If you need any assistance or have questions about your responsibilities, please
                reach out to the system administrator. We appreciate your dedication and look forward
                to continued success working together!
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