<?php
session_start();

// 1) Check if user is staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['staff', 'admin'])) {
    header("Location: ../login.php"); // adjust path if needed
    exit;
}

// 2) DB Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop"; // Adjust if needed

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Staff/Manager name from session
$staffName = $_SESSION['user_name'] ?? 'Staff/Manager';

/**********************************************
 * A) Decode cart_data for daily/weekly/monthly revenues
 **********************************************/
$dailyRevenue = 0;
$weeklyRevenue = 0;
$monthlyRevenue = 0;

// We'll define a helper function to decode cart_data and sum each sale's total
function decodeCartAndSum($json)
{
    $items = json_decode($json, true);
    $sum = 0.0;
    if (is_array($items)) {
        foreach ($items as $item) {
            $price = (float) ($item['price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            $sum += $price * $qty;
        }
    }
    return $sum;
}

// 1) Daily
$sqlDaily = "
    SELECT cart_data
    FROM sales
    WHERE DATE(sale_date) = CURDATE()
";
$resDaily = $conn->query($sqlDaily);
if ($resDaily && $resDaily->num_rows > 0) {
    while ($row = $resDaily->fetch_assoc()) {
        $dailyRevenue += decodeCartAndSum($row['cart_data']);
    }
}

// 2) Weekly
$sqlWeekly = "
    SELECT cart_data
    FROM sales
    WHERE YEARWEEK(sale_date, 1) = YEARWEEK(CURDATE(), 1)
";
$resWeekly = $conn->query($sqlWeekly);
if ($resWeekly && $resWeekly->num_rows > 0) {
    while ($row = $resWeekly->fetch_assoc()) {
        $weeklyRevenue += decodeCartAndSum($row['cart_data']);
    }
}

// 3) Monthly
$sqlMonthly = "
    SELECT cart_data
    FROM sales
    WHERE MONTH(sale_date) = MONTH(CURDATE())
      AND YEAR(sale_date) = YEAR(CURDATE())
";
$resMonthly = $conn->query($sqlMonthly);
if ($resMonthly && $resMonthly->num_rows > 0) {
    while ($row = $resMonthly->fetch_assoc()) {
        $monthlyRevenue += decodeCartAndSum($row['cart_data']);
    }
}

/**********************************************
 * B) Show "Sales Made Today" with staff name, ID, time of sale
 **********************************************/
$salesToday = [];
$sqlSalesToday = "
    SELECT s.id AS sale_id, s.staff_id, s.sale_date, s.cart_data,
           u.name AS staff_name
    FROM sales s
    JOIN users u ON s.staff_id = u.id
    WHERE DATE(s.sale_date) = CURDATE()
    ORDER BY s.sale_date DESC
";
$resSalesToday = $conn->query($sqlSalesToday);
if ($resSalesToday && $resSalesToday->num_rows > 0) {
    while ($row = $resSalesToday->fetch_assoc()) {
        // Decode cart_data to get sum
        $saleTotal = decodeCartAndSum($row['cart_data']);
        $row['sale_total'] = $saleTotal;
        $salesToday[] = $row;
    }
}

/**********************************************
 * C) Top-Selling Products from cart_data
 **********************************************/
// We'll decode all cart_data from the entire sales table (or for a time range) and accumulate
$topProductsMap = []; // key: product_name, value: total qty
$sqlAllSales = "
    SELECT cart_data
    FROM sales
"; // or add a date range if you want (like for the current month)
$resAll = $conn->query($sqlAllSales);
if ($resAll && $resAll->num_rows > 0) {
    while ($row = $resAll->fetch_assoc()) {
        $items = json_decode($row['cart_data'], true);
        if (is_array($items)) {
            foreach ($items as $it) {
                $pname = $it['name'] ?? 'Unknown';
                $qty = (int) ($it['quantity'] ?? 0);
                if (!isset($topProductsMap[$pname])) {
                    $topProductsMap[$pname] = 0;
                }
                $topProductsMap[$pname] += $qty;
            }
        }
    }
}
// Now sort them by total qty desc
arsort($topProductsMap);
// Take top 5 for example
$topProductsMap = array_slice($topProductsMap, 0, 5, true);

/**********************************************
 * D) Inventory stock levels, low-stock alerts, expiry dates
 **********************************************/
$lowStockAlerts = [];
$sqlLow = "SELECT name, stock, expiration_date FROM beautyshop WHERE stock < 10 ORDER BY stock ASC";
$resLow = $conn->query($sqlLow);
if ($resLow && $resLow->num_rows > 0) {
    while ($row = $resLow->fetch_assoc()) {
        $lowStockAlerts[] = $row;
    }
}

/**********************************************
 * E) Average Transaction Value (ATV) & other placeholders
 **********************************************/
$avgTransaction = 0;
$sqlATV = "SELECT cart_data FROM sales";
$resATV = $conn->query($sqlATV);
$totalSalesCount = 0;
$totalSalesSum = 0.0;
if ($resATV && $resATV->num_rows > 0) {
    while ($row = $resATV->fetch_assoc()) {
        $totalSalesSum += decodeCartAndSum($row['cart_data']);
        $totalSalesCount++;
    }
}
if ($totalSalesCount > 0) {
    $avgTransaction = $totalSalesSum / $totalSalesCount;
}

// For total bookings or services, remove if not needed
$totalBookings = 0;
// Example: if you had a `services` table, you'd do something else
// We'll skip it or keep it at 0 for now.

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Beauty Shop Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff6b81;
            --secondary-color: #f9f9f9;
            --accent-color: #ff4757;
            --text-color: #333;
            --border-color: #ddd;
            --container-max: 1000px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: var(--secondary-color);
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar (matching suppliers.php) */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 180px;
            height: 100vh;
            background: #fff;
            border-right: 1px solid var(--border-color);
            padding: 20px;
            overflow-y: auto;
        }

        .sidebar .logo {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar li {
            margin-bottom: 10px;
        }

        .sidebar a {
            display: block;
            text-decoration: none;
            color: var(--text-color);
            padding: 8px;
            border-radius: 4px;
            transition: background .3s;
        }

        .sidebar a:hover,
        .sidebar .active a {
            background: var(--primary-color);
            color: #fff;
        }

        /* Main wrapper */
        .main-content {
            margin-left: 180px;
            padding: 40px 20px;
            width: 100%;
            display: flex;
            justify-content: center;
        }

        /* Centered container */
        .container {
            background: #fff;
            max-width: var(--container-max);
            width: 100%;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .container h1 {
            margin-bottom: 20px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .container h1 i {
            color: var(--primary-color);
        }

        /* Revenue cards */
        .revenue-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .revenue-cards .card {
            background: var(--secondary-color);
            border-left: 4px solid var(--accent-color);
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            transition: box-shadow .3s;
        }

        .revenue-cards .card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .revenue-cards .card h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .revenue-cards .card h3 i {
            color: var(--accent-color);
        }

        .revenue-cards .card p {
            font-size: 1.5rem;
            font-weight: 600;
        }

        /* Section headers */
        section h2 {
            margin: 30px 0 15px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        section h2 i {
            color: var(--primary-color);
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid var(--border-color);
            text-align: left;
            font-size: 0.9rem;
        }

        thead {
            background: var(--secondary-color);
        }

        tbody tr:hover {
            background: rgba(0, 0, 0, 0.03);
        }

        /* Lists */
        .summary-stats ul {
            list-style: none;
            padding-left: 0;
        }

        .summary-stats li {
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.95rem;
        }

        .export-section a,
        .export-section button {
            color: var(--primary-color);
            text-decoration: none;
            margin-right: 10px;
            transition: color .3s;
        }

        .export-section a:hover,
        .export-section button:hover {
            color: var(--accent-color);
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <h2 class="logo"><i class="fas fa-store"></i> Beauty Shop</h2>
        <ul>
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="sales.php"><i class="fas fa-chart-line"></i> Sales</a></li>
            <li><a href="purchases.php"><i class="fas fa-shopping-cart"></i> Purchases</a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> Customers</a></li>
            <li><a href="suppliers.php"><i class="fas fa-truck"></i> Suppliers</a></li>
            <li class="active"><a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
            <li><a href="myaccount.php"><i class="fas fa-user-cog"></i> My Account</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <h1><i class="fas fa-chart-bar"></i> Beauty Shop Reports</h1>
            <p>Welcome, <?php echo htmlspecialchars($staffName); ?>!</p>

            <!-- Revenue Cards -->
            <div class="revenue-cards">
                <div class="card">
                    <h3><i class="fas fa-sun"></i> Daily Revenue</h3>
                    <p>$<?php echo number_format($dailyRevenue, 2); ?></p>
                </div>
                <div class="card">
                    <h3><i class="fas fa-calendar-week"></i> Weekly Revenue</h3>
                    <p>$<?php echo number_format($weeklyRevenue, 2); ?></p>
                </div>
                <div class="card">
                    <h3><i class="fas fa-calendar-alt"></i> Monthly Revenue</h3>
                    <p>$<?php echo number_format($monthlyRevenue, 2); ?></p>
                </div>
            </div>

            <!-- Sales Made Today -->
            <section class="today-sales">
                <h2><i class="fas fa-clock"></i> Sales Made Today</h2>
                <?php if (!empty($salesToday)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Sale ID</th>
                                <th>Staff ID</th>
                                <th>Staff Name</th>
                                <th>Time</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salesToday as $s): ?>
                                <tr>
                                    <td><?php echo $s['sale_id']; ?></td>
                                    <td><?php echo $s['staff_id']; ?></td>
                                    <td><?php echo htmlspecialchars($s['staff_name']); ?></td>
                                    <td><?php echo date("H:i:s", strtotime($s['sale_date'])); ?></td>
                                    <td>$<?php echo number_format($s['sale_total'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No sales today.</p>
                <?php endif; ?>
            </section>

            <!-- Top-Selling Products -->
            <section class="top-products">
                <h2><i class="fas fa-star"></i> Top-Selling Products</h2>
                <?php if (!empty($topProductsMap)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProductsMap as $p => $q): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p); ?></td>
                                    <td><?php echo $q; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No sales data.</p>
                <?php endif; ?>
            </section>

            <!-- Inventory Alerts -->
            <section class="inventory-section">
                <h2><i class="fas fa-exclamation-triangle"></i> Inventory Alerts</h2>
                <?php if (!empty($lowStockAlerts)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Expiry</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockAlerts as $i): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i['name']); ?></td>
                                    <td><?php echo $i['stock']; ?></td>
                                    <td><?php echo $i['expiration_date'] ?: 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No low-stock items.</p>
                <?php endif; ?>
            </section>

            <!-- Additional Stats -->
            <section class="summary-stats">
                <h2><i class="fas fa-info-circle"></i> Additional Stats</h2>
                <ul>
                    <li>Average Transaction Value: $<?php echo number_format($avgTransaction, 2); ?></li>
                    <li>Total Bookings/Services: <?php echo $totalBookings; ?></li>
                </ul>
            </section>

            <!-- Export & Sharing -->
            <section class="export-section">
                <h2><i class="fas fa-share-alt"></i> Export &amp; Sharing</h2>
                <p>
                    <a href="export.php?type=pdf"><i class="fas fa-file-pdf"></i> PDF</a> |
                    <a href="export.php?type=excel"><i class="fas fa-file-excel"></i> Excel</a> |
                    <a href="export.php?type=csv"><i class="fas fa-file-csv"></i> CSV</a> |
                    <a href="send_report.php"><i class="fas fa-envelope"></i> Email</a>
                </p>
                <p><button onclick="window.print()"><i class="fas fa-print"></i> Print</button></p>
            </section>
        </div>
    </div>
</body>

</html>