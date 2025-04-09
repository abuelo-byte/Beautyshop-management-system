<?php
// -----------------
// 1) DB Connection
// -----------------
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafeteria-management-system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------------------------------------------------------------------
// A) Ensure the 'processed' column exists in the 'sales' table
//    (so we can mark which sales have already been stock-adjusted).
//    This silently ignores any error if the column already exists.
// ---------------------------------------------------------------------
@$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS processed TINYINT(1) NOT NULL DEFAULT 0");

// ---------------------------------------------------------------------
// B) Detect unprocessed sales, subtract from stock, then mark processed
// ---------------------------------------------------------------------
$salesSql = "SELECT id, cart_data FROM sales WHERE processed = 0";
$salesRes = $conn->query($salesSql);
if ($salesRes && $salesRes->num_rows > 0) {
    while ($saleRow = $salesRes->fetch_assoc()) {
        $cartData = json_decode($saleRow['cart_data'], true);
        if (is_array($cartData)) {
            foreach ($cartData as $item) {
                // Subtract 'quantity' from food item stock by 'id'
                $upd = $conn->prepare("UPDATE stock_inventory SET quantity = quantity - ? WHERE food_item_id = ?");
                $upd->bind_param("ii", $item['quantity'], $item['id']);
                $upd->execute();
            }
        }
        // Mark this sale as processed
        $mark = $conn->prepare("UPDATE sales SET processed = 1 WHERE id = ?");
        $mark->bind_param("i", $saleRow['id']);
        $mark->execute();
    }
}

// -----------------------------------------
// 2) Create Tables if not exists
// -----------------------------------------
$createFoodItemsTable = "CREATE TABLE IF NOT EXISTS food_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    unit_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$createStockInventoryTable = "CREATE TABLE IF NOT EXISTS stock_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    food_item_id INT,
    quantity INT NOT NULL,
    unit VARCHAR(50) NOT NULL,
    min_stock_level INT NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (food_item_id) REFERENCES food_items(id)
)";

if (!$conn->query($createFoodItemsTable)) {
    die("Food items table creation failed: " . $conn->error);
}

if (!$conn->query($createStockInventoryTable)) {
    die("Stock inventory table creation failed: " . $conn->error);
}

// -----------------------------------------
// 3) Handle form submission for adding new food item
// -----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_food_item'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $description = $conn->real_escape_string($_POST['description']);
    $category = $conn->real_escape_string($_POST['category']);
    $unit_price = floatval($_POST['unit_price']);
    $quantity = intval($_POST['quantity']);
    $unit = $conn->real_escape_string($_POST['unit']);
    $min_stock_level = intval($_POST['min_stock_level']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert food item
        $stmt = $conn->prepare("INSERT INTO food_items (name, description, category, unit_price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssd", $name, $description, $category, $unit_price);
        $stmt->execute();
        $food_item_id = $conn->insert_id;

        // Insert stock information
        $stmt = $conn->prepare("INSERT INTO stock_inventory (food_item_id, quantity, unit, min_stock_level) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $food_item_id, $quantity, $unit, $min_stock_level);
        $stmt->execute();

        $conn->commit();
        $success_message = "Food item added successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error adding food item: " . $e->getMessage();
    }
}

// -----------------------------------------
// 4) Fetch all food items with stock information
// -----------------------------------------
$sql = "SELECT fi.*, si.quantity, si.unit, si.min_stock_level 
        FROM food_items fi 
        LEFT JOIN stock_inventory si ON fi.id = si.food_item_id 
        ORDER BY fi.name";
$result = $conn->query($sql);
$food_items = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $food_items[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Items Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <li><a href="Home.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="link-text">Dashboard</span>
                </a></li>
            <li><a href="#" class="active">
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
            <li><a href="customers.php">
                    <i class="fas fa-users"></i>
                    <span class="link-text">Customers</span>
                </a></li>
            <li><a href="supplier.php">
                    <i class="fas fa-truck"></i>
                    <span class="link-text">Suppliers</span>
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
    <header>
            <h1>Food Items Management</h1>
    </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Add New Food Item Form -->
        <div class="card">
            <h2>Add New Food Item</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description"></textarea>
            </div>
                <div class="form-group">
                    <label for="category">Category:</label>
                    <select id="category" name="category" required>
                        <option value="main-course">Main Course</option>
                        <option value="side-dish">Side Dish</option>
                        <option value="beverage">Beverage</option>
                        <option value="dessert">Dessert</option>
                        <option value="snack">Snack</option>
                </select>
            </div>
                <div class="form-group">
                    <label for="unit_price">Unit Price (Ksh):</label>
                    <input type="number" id="unit_price" name="unit_price" step="0.01" required>
            </div>
                <div class="form-group">
                    <label for="quantity">Initial Quantity:</label>
                    <input type="number" id="quantity" name="quantity" required>
        </div>
                <div class="form-group">
                    <label for="unit">Unit:</label>
                    <select id="unit" name="unit" required>
                        <option value="piece">Piece</option>
                        <option value="plate">Plate</option>
                        <option value="cup">Cup</option>
                        <option value="bottle">Bottle</option>
                        <option value="kg">Kilogram</option>
                        <option value="g">Gram</option>
                        <option value="l">Liter</option>
                        <option value="ml">Milliliter</option>
                    </select>
            </div>
                <div class="form-group">
                    <label for="min_stock_level">Minimum Stock Level:</label>
                    <input type="number" id="min_stock_level" name="min_stock_level" required>
            </div>
                <button type="submit" name="add_food_item" class="btn btn-primary">Add Food Item</button>
            </form>
        </div>

        <!-- Food Items List -->
        <div class="card">
            <h2>Food Items List</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Unit Price</th>
                        <th>Current Stock</th>
                        <th>Unit</th>
                        <th>Min Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($food_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td>Ksh <?php echo number_format($item['unit_price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($item['unit']); ?></td>
                            <td><?php echo $item['min_stock_level']; ?></td>
                            <td>
                                <?php if ($item['quantity'] <= $item['min_stock_level']): ?>
                                    <span class="badge badge-warning">Low Stock</span>
                                <?php else: ?>
                                    <span class="badge badge-success">In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="editItem(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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