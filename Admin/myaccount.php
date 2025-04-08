<?php
// 1) Connect to DB
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop"; // adjust if needed

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch sales data with pagination
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Date filter
$dateFilter = '';
$filterParams = [];

if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $dateFilter .= " AND sale_date >= ?";
    $filterParams[] = $_GET['start_date'];
}

if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $dateFilter .= " AND sale_date <= ?";
    $filterParams[] = $_GET['end_date'] . ' 23:59:59';
}

// Count total records for pagination
$countSql = "SELECT COUNT(*) as total FROM sales WHERE 1=1" . $dateFilter;
$countStmt = $conn->prepare($countSql);

if (!empty($filterParams)) {
    $countStmt->bind_param(str_repeat('s', count($filterParams)), ...$filterParams);
}

$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $itemsPerPage);

// Fetch paginated sales data
$sql = "SELECT id, sale_date, total, cart_data FROM sales WHERE 1=1" . $dateFilter . " ORDER BY id DESC LIMIT ?, ?";
$stmt = $conn->prepare($sql);

$params = $filterParams;
$params[] = $offset;
$params[] = $itemsPerPage;

$types = str_repeat('s', count($filterParams)) . 'ii';
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();

// Calculate sales stats
$statsSql = "SELECT 
    COUNT(*) as total_sales, 
    SUM(total) as revenue, 
    AVG(total) as average_sale 
    FROM sales WHERE 1=1" . $dateFilter;

$statsStmt = $conn->prepare($statsSql);

if (!empty($filterParams)) {
    $statsStmt->bind_param(str_repeat('s', count($filterParams)), ...$filterParams);
}

$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beauty Shop - Sales Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="account.css">
</head>

<body>
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <h2 class="logo">abuelo jua code</h2>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="purchases.php">Purchases</a></li>
            <li><a href="sales.php">Sales</a></li>
            <li><a href="Customers.php">Customers</a></li>
            <li><a href="suppliers.php">Suppliers</a></li>
            <li><a href="Reports.php">Reports</a></li>
            <li class="active"><a href="#">MyAccount</a></li>
            <li><a href="settings.php">Settings</a></li>
        </ul>
    </aside>
    <nav class="navbar">
        <div class="container">
            <a href="#" class="logo">
                <i class="fas fa-spa"></i>
                <span>Beauty</span>Shop
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Sales Dashboard
            </h1>
        </div>

        <div class="card">
            <form method="get" action="">
                <div class="filters">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date"
                            value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date"
                            value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                    </div>
                    <div class="filter-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>

                    </div>
                </div>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card stat-card-primary">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-title">products sold</div>
                <div class="stat-value"><?php echo number_format($stats['total_sales']); ?></div>
            </div>
            <div class="stat-card stat-card-success">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-title">Total Revenue</div>
                <div class="stat-value">ksh<?php echo number_format($stats['revenue'], 2); ?></div>
            </div>
            <div class="stat-card stat-card-info">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-title">Average Sale</div>
                <div class="stat-value">ksh<?php echo number_format($stats['average_sale'], 2); ?></div>
            </div>
        </div>

        <div class="card">
            <h2 class="stat-title" style="margin-bottom: 1.5rem;">Recent Sales</h2>

            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Items</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php $cartData = json_decode($row['cart_data'], true); ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td>
                                        <div class="date-badge">
                                            <i class="far fa-calendar-alt"></i>
                                            <?php echo date('M d, Y', strtotime($row['sale_date'])); ?>
                                            <span class="badge badge-info hidden-sm">
                                                <?php echo date('h:i A', strtotime($row['sale_date'])); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="sale-amount">ksh<?php echo number_format($row['total'], 2); ?></td>
                                    <td><?php echo count($cartData); ?> items</td>
                                    <td>
                                        <button class="toggle-details" onclick="toggleDetails(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-chevron-down" id="icon-<?php echo $row['id']; ?>"></i>
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="5" style="padding: 0 1rem;">
                                        <div id="details-<?php echo $row['id']; ?>" class="cart-details">
                                            <?php foreach ($cartData as $item): ?>
                                                <div class="cart-item">
                                                    <div>
                                                        <span class="badge badge-primary"><?php echo $item['quantity']; ?>x</span>
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </div>
                                                    <div>ksh<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <div class="pagination-item">
                                <a href="?page=<?php echo $currentPage - 1; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . htmlspecialchars($_GET['start_date']) : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . htmlspecialchars($_GET['end_date']) : ''; ?>"
                                    class="pagination-link">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <div class="pagination-item">
                                <a href="?page=<?php echo $i; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . htmlspecialchars($_GET['start_date']) : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . htmlspecialchars($_GET['end_date']) : ''; ?>"
                                    class="pagination-link <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </div>
                        <?php endfor; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <div class="pagination-item">
                                <a href="?page=<?php echo $currentPage + 1; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . htmlspecialchars($_GET['start_date']) : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . htmlspecialchars($_GET['end_date']) : ''; ?>"
                                    class="pagination-link">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No sales found</h3>
                    <p>There are no sales records for the selected period. Try changing your filter criteria or add some
                        sales.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <script>
        function toggleDetails(id) {
            const details = document.getElementById('details-' + id);
            const icon = document.getElementById('icon-' + id);

            details.classList.toggle('expanded');

            if (details.classList.contains('expanded')) {
                icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
            } else {
                icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
            }
        }

        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const icon = themeToggle.querySelector('i');

        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');

            if (document.body.classList.contains('dark-mode')) {
                icon.classList.replace('fa-moon', 'fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                icon.classList.replace('fa-sun', 'fa-moon');
                localStorage.setItem('theme', 'light');
            }
        });

        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            icon.classList.replace('fa-moon', 'fa-sun');
        }
    </script>
</body>

</html>