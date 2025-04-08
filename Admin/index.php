<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ./login.php");
    exit;
}
// else show the protected content

/********************************************
 * 1) Database Connection
 ********************************************/
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop"; // Adjust if needed

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/********************************************
 * 2) Fetch Totals (products, sales, customers, suppliers)
 ********************************************/
/** 2.1) Total Products (beautyshop) */
$sqlProducts = "SELECT COUNT(*) AS totalProducts FROM beautyshop";
$resProd = $conn->query($sqlProducts);
$rowProd = $resProd->fetch_assoc();
$totalProducts = $rowProd['totalProducts'] ?? 0;

/** 2.2) Total Sales (sum of total_price in 'sales') */
$sqlSales = "SELECT SUM(total_price) AS totalSales FROM sales";
$resSales = $conn->query($sqlSales);
$rowSales = $resSales->fetch_assoc();
$totalSales = $rowSales['totalSales'] ?? 0;

/** 2.3) Total Customers */
$sqlCustomers = "SELECT COUNT(*) AS totalCustomers FROM customers";
$resCust = $conn->query($sqlCustomers);
$rowCust = $resCust->fetch_assoc();
$totalCustomers = $rowCust['totalCustomers'] ?? 0;

/** 2.4) Total Suppliers */
$sqlSuppliers = "SELECT COUNT(*) AS totalSuppliers FROM suppliers";
$resSupp = $conn->query($sqlSuppliers);
$rowSupp = $resSupp->fetch_assoc();
$totalSuppliers = $rowSupp['totalSuppliers'] ?? 0;

/********************************************
 * 3) Top-Selling Products from cart_data (JSON)
 ********************************************/
/** 
 * We'll read every row in 'sales', decode 'cart_data', 
 * accumulate quantity & amount for each product ID. 
 * Then we optionally look up product names from 'beautyshop' 
 * if needed. Finally, we sort & take top 5.
 */
$acc = []; // $acc[product_id] = ['qty'=>..., 'amount'=>...]

// 3.1) Fetch all rows from 'sales' to decode cart_data
$cartSql = "SELECT cart_data FROM sales";
$cartRes = $conn->query($cartSql);
if ($cartRes && $cartRes->num_rows > 0) {
    while ($row = $cartRes->fetch_assoc()) {
        $cartJson = $row['cart_data'];
        $cartItems = json_decode($cartJson, true);
        if (is_array($cartItems)) {
            foreach ($cartItems as $item) {
                // Expecting: { "id":..., "name":..., "price":..., "quantity":... }
                $pid = $item['id'] ?? 0;
                $qty = (int) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? 0);
                if ($pid > 0 && $qty > 0) {
                    if (!isset($acc[$pid])) {
                        $acc[$pid] = ['qty' => 0, 'amount' => 0.0];
                    }
                    $acc[$pid]['qty'] += $qty;
                    $acc[$pid]['amount'] += $qty * $price;
                }
            }
        }
    }
}
$cartRes->close();

// 3.2) Optionally look up official product names from 'beautyshop'
$topSellers = [];
if (!empty($acc)) {
    $ids = array_keys($acc); // e.g. [2,5,10,...]
    $idList = implode(',', array_map('intval', $ids));
    $sqlNames = "SELECT id, name FROM beautyshop WHERE id IN ($idList)";
    $resNames = $conn->query($sqlNames);
    $nameMap = [];
    if ($resNames && $resNames->num_rows > 0) {
        while ($r = $resNames->fetch_assoc()) {
            $nameMap[$r['id']] = $r['name'];
        }
    }
    $resNames->close();

    // Merge & create final array
    foreach ($acc as $pid => $info) {
        $pname = isset($nameMap[$pid]) ? $nameMap[$pid] : ("Unknown #$pid");
        $qty = $info['qty'];
        $amt = $info['amount'];
        $topSellers[] = [
            'id' => $pid,
            'name' => $pname,
            'qty' => $qty,
            'amount' => $amt
        ];
    }

    // 3.3) Sort by qty desc
    usort($topSellers, function ($a, $b) {
        return $b['qty'] - $a['qty']; // desc
    });

    // 3.4) Take top 5
    $topSellers = array_slice($topSellers, 0, 5);
}

/********************************************
 * 4) Inventory Alerts (Low Stock, Expiring Soon, Not Selling)
 ********************************************/
/** 4.1) Low Stock Items (stock < 10) */
$lowStockSql = "SELECT id, name, stock, company FROM beautyshop WHERE stock < 10 ORDER BY stock ASC";
$lowStockItems = [];
$lowRes = $conn->query($lowStockSql);
if ($lowRes && $lowRes->num_rows > 0) {
    while ($row = $lowRes->fetch_assoc()) {
        $lowStockItems[] = $row;
    }
}
$lowRes->close();

/** 4.2) Expiring Soon (expiration_date <= NOW()+14 days) */
$expSoonSql = "
    SELECT id, name, company, expiration_date,
           DATEDIFF(expiration_date, CURDATE()) AS days_left
    FROM beautyshop
    WHERE expiration_date IS NOT NULL
      AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    ORDER BY expiration_date
";
$expSoonItems = [];
$expRes = $conn->query($expSoonSql);
if ($expRes && $expRes->num_rows > 0) {
    while ($row = $expRes->fetch_assoc()) {
        $expSoonItems[] = $row;
    }
}
$expRes->close();

/** 4.3) Not Selling (last_sold_date + no sales for >30 days) */
$notSellingSql = "
    SELECT id, name, company, last_sold_date,
           DATEDIFF(CURDATE(), last_sold_date) AS days_no_sale
    FROM beautyshop
    WHERE last_sold_date IS NOT NULL
      AND DATEDIFF(CURDATE(), last_sold_date) > 30
    ORDER BY last_sold_date
";
$notSellingItems = [];
$nsRes = $conn->query($notSellingSql);
if ($nsRes && $nsRes->num_rows > 0) {
    while ($row = $nsRes->fetch_assoc()) {
        $notSellingItems[] = $row;
    }
}
$nsRes->close();

/********************************************
 * 5) Close the DB Connection
 ********************************************/
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <h2 class="logo">abuelo jua code</h2>
        <ul>
            <li class="active"><a href="#">Dashboard</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="purchases.php">Purchases</a></li>
            <li><a href="sales.php">Sales</a></li>
            <li><a href="Customers.php">Customers</a></li>
            <li><a href="suppliers.php">Suppliers</a></li>
            <li><a href="Reports.php">Reports</a></li>
            <li><a href="myaccount.php">MyAccount</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">logout</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="content">
        <header>
            <div class="search">
                <input type="text" placeholder="Search...">
            </div>
            <div class="profile">
                <img src="images/user.jpg" alt="User">
                <span>Kristin Watson</span>
            </div>
        </header>

        <!-- Dashboard Cards -->
        <section class="dashboard-cards">
            <div class="card">
                <h3>Total Products</h3>
                <p><?php echo $totalProducts; ?></p>
            </div>
            <div class="card">
                <h3>Total Sales</h3>
                <p>ksh <?php echo number_format($totalSales, 2); ?></p>
            </div>
            <div class="card">
                <h3>Total Customers</h3>
                <p><?php echo $totalCustomers; ?></p>
            </div>
            <div class="card">
                <h3>Total Suppliers</h3>
                <p><?php echo $totalSuppliers; ?></p>
            </div>
        </section>

        <!-- 
         We'll place the inventory alerts container on the left 
         and the top-sellers table on the right side by side 
    -->
        <section class="dashboard-details">
            <!-- Left: Inventory Alerts -->
            <div class="inventory-alerts-container">
                <h2>Inventory Health Dashboard</h2>

                <!-- Low Stock Items -->
                <div class="alert-section">
                    <h3>Low Stock Items</h3>
                    <div class="alert-list">
                        <?php if (!empty($lowStockItems)): ?>
                            <?php foreach ($lowStockItems as $item): ?>
                                <?php
                                $stock = (int) $item['stock'];
                                $max = 50;
                                $progress = max(0, min(100, round(($stock / $max) * 100)));
                                ?>
                                <div class="alert-item">
                                    <strong class="alert-title"><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <span class="alert-info">Stock: <?php echo $stock; ?></span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                                    </div>
                                    <button class="alert-action restock-btn">Restock</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-alert">No low-stock alerts. Everything is good!</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Expiring Soon -->
                <div class="alert-section">
                    <h3>Expiring Soon</h3>
                    <div class="alert-list">
                        <?php if (!empty($expSoonItems)): ?>
                            <?php foreach ($expSoonItems as $item): ?>
                                <?php
                                $daysLeft = (int) $item['days_left'];
                                $msg = $daysLeft . " day" . ($daysLeft !== 1 ? "s" : "") . " left";
                                ?>
                                <div class="alert-item">
                                    <strong class="alert-title"><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <span class="alert-info">Expires in <?php echo $msg; ?></span>
                                    <?php if ($daysLeft < 3): ?>
                                        <span class="critical">CRITICAL</span>
                                    <?php endif; ?>
                                    <button class="alert-action discount-btn">Discount</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-alert">No expiring-soon alerts. Everything is good!</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Not Selling -->
                <div class="alert-section">
                    <h3>Not Selling (Over 30 days)</h3>
                    <div class="alert-list">
                        <?php if (!empty($notSellingItems)): ?>
                            <?php foreach ($notSellingItems as $item): ?>
                                <?php $daysNoSale = (int) $item['days_no_sale']; ?>
                                <div class="alert-item">
                                    <strong class="alert-title"><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <span class="alert-info">
                                        No sales for <?php echo $daysNoSale; ?> days
                                    </span>
                                    <?php if ($daysNoSale > 60): ?>
                                        <span class="warning">WARNING</span>
                                    <?php endif; ?>
                                    <button class="alert-action discount-btn">Discount</button>
                                    <button class="alert-action feature-btn">Feature</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-alert">No stagnant inventory alerts. Everything is selling!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Top Sellers from cart_data -->
            <div class="top-selling-table">
                <h3>Top Selling Products</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($topSellers)): ?>
                            <?php foreach ($topSellers as $ts): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ts['id']); ?></td>
                                    <td><?php echo htmlspecialchars($ts['name']); ?></td>
                                    <td><?php echo $ts['qty']; ?></td>
                                    <td>ksh <?php echo number_format($ts['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No sales data found in cart_data.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

</body>

</html>