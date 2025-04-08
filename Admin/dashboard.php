<?php
$page_title = 'Dashboard';
$current_page = 'dashboard';
require_once('includes/header.php');

// Fetch quick stats
$stats = [
    'total_products' => $conn->query("SELECT COUNT(*) as count FROM beautyshop")->fetch_assoc()['count'],
    'low_stock' => $conn->query("SELECT COUNT(*) as count FROM beautyshop WHERE stock <= " . ($settings['inventory_alert'] ?? 10))->fetch_assoc()['count'],
    'total_sales' => $conn->query("SELECT COUNT(*) as count FROM sales")->fetch_assoc()['count'],
    'pending_orders' => $conn->query("SELECT COUNT(*) as count FROM sales WHERE status = 'pending'")->fetch_assoc()['count']
];

// Fetch recent sales
$recent_sales = $conn->query("SELECT * FROM sales ORDER BY created_at DESC LIMIT 5");

// Fetch low stock products
$low_stock_products = $conn->query("SELECT * FROM beautyshop WHERE stock <= " . ($settings['inventory_alert'] ?? 10) . " ORDER BY stock ASC LIMIT 5");
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Dashboard</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="window.location.href='sales.php'">
                    <i class="fas fa-plus"></i> New Sale
                </button>
                <button class="btn btn-outline-primary" onclick="window.location.href='products.php'">
                    <i class="fas fa-box"></i> Manage Products
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card bg-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_products']; ?></div>
                            <div class="stats-text">Total Products</div>
                        </div>
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card bg-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $stats['low_stock']; ?></div>
                            <div class="stats-text">Low Stock Items</div>
                        </div>
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card bg-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_sales']; ?></div>
                            <div class="stats-text">Total Sales</div>
                        </div>
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card bg-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo $stats['pending_orders']; ?></div>
                            <div class="stats-text">Pending Orders</div>
                        </div>
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Sales -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>Recent Sales
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo $sale['id']; ?></td>
                                            <td>KSH <?php echo number_format($sale['total_amount'], 2); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $sale['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($sale['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Low Stock Products -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exclamation-circle me-2"></i>Low Stock Products
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Stock</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($product = $low_stock_products->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $product['stock']; ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="window.location.href='purchases.php?product_id=<?php echo $product['id']; ?>'">
                                                    <i class="fas fa-plus"></i> Restock
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once('includes/footer.php'); ?> 