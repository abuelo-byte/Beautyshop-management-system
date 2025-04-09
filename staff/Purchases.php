<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafeteria-management-system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create necessary tables if they don't exist
$createTables = [
    "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact_person VARCHAR(100),
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        notes TEXT,
        purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    )",
    "CREATE TABLE IF NOT EXISTS purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        food_item_id INT NOT NULL,
    quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (purchase_id) REFERENCES purchases(id),
        FOREIGN KEY (food_item_id) REFERENCES food_items(id)
    )"
];

foreach ($createTables as $sql) {
    if (!$conn->query($sql)) {
        die("Table creation failed: " . $conn->error);
    }
}

// Handle new purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_purchase'])) {
    $supplierId = intval($_POST['supplier_id']);
    $purchaseItems = json_decode($_POST['purchase_items'], true);
    $totalAmount = floatval($_POST['total_amount']);
    $paymentMethod = $conn->real_escape_string($_POST['payment_method']);
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert purchase record
        $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, total_amount, payment_method, notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idss", $supplierId, $totalAmount, $paymentMethod, $notes);
        $stmt->execute();
        $purchaseId = $conn->insert_id;

        // Insert purchase items and update stock
        foreach ($purchaseItems as $item) {
            // Insert purchase item
            $stmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, food_item_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
            $totalPrice = $item['quantity'] * $item['unit_price'];
            $stmt->bind_param("iiidd", $purchaseId, $item['id'], $item['quantity'], $item['unit_price'], $totalPrice);
            $stmt->execute();

            // Update stock inventory
            $upd = $conn->prepare("UPDATE stock_inventory SET quantity = quantity + ? WHERE food_item_id = ?");
            $upd->bind_param("ii", $item['quantity'], $item['id']);
        $upd->execute();
        }

        $conn->commit();
        $success_message = "Restocking completed successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error completing restocking: " . $e->getMessage();
    }
}

// Fetch suppliers
$suppliers = [];
$suppliersQuery = "SELECT * FROM suppliers ORDER BY name";
$suppliersResult = $conn->query($suppliersQuery);
if ($suppliersResult && $suppliersResult->num_rows > 0) {
    while ($row = $suppliersResult->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// Fetch food items
$foodItems = [];
$foodItemsQuery = "SELECT fi.*, si.quantity, si.unit 
                  FROM food_items fi 
                  JOIN stock_inventory si ON fi.id = si.food_item_id 
                  ORDER BY fi.name";
$foodItemsResult = $conn->query($foodItemsQuery);
if ($foodItemsResult && $foodItemsResult->num_rows > 0) {
    while ($row = $foodItemsResult->fetch_assoc()) {
        $foodItems[] = $row;
    }
}

// Pagination settings
$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Fetch total number of purchases
$totalPurchasesQuery = "SELECT COUNT(*) as total FROM purchases";
$totalPurchasesResult = $conn->query($totalPurchasesQuery);
$totalPurchases = $totalPurchasesResult->fetch_assoc()['total'];
$totalPages = $totalPurchases > 0 ? ceil($totalPurchases / $itemsPerPage) : 1;

// Fetch purchases with pagination
$offset = ($page - 1) * $itemsPerPage;
$purchasesQuery = "SELECT p.*, s.name as supplier_name 
                 FROM purchases p
                  LEFT JOIN suppliers s ON p.supplier_id = s.id 
                  ORDER BY p.purchase_date DESC 
                  LIMIT $offset, $itemsPerPage";
$purchasesResult = $conn->query($purchasesQuery);

// Store purchases in array
$purchases = [];
if ($purchasesResult && $purchasesResult->num_rows > 0) {
    while ($row = $purchasesResult->fetch_assoc()) {
        // Fetch purchase items
        $itemsQuery = "SELECT pi.*, fi.name as item_name 
                      FROM purchase_items pi 
                      JOIN food_items fi ON pi.food_item_id = fi.id 
                      WHERE pi.purchase_id = " . $row['id'];
        $itemsResult = $conn->query($itemsQuery);
        $row['items'] = [];
        if ($itemsResult && $itemsResult->num_rows > 0) {
            while ($item = $itemsResult->fetch_assoc()) {
                $row['items'][] = $item;
            }
        }
        $purchases[] = $row;
    }
}

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Management</title>
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
            <li><a href="Products.php">
                    <i class="fas fa-hamburger"></i>
                    <span class="link-text">Food Items</span>
                </a></li>
            <li><a href="#" class="active">
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
            <h1>Purchase Management</h1>
    </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- New Purchase Form -->
        <div class="card">
            <h2>New Purchase</h2>
            <form method="POST" action="" id="purchaseForm">
                <div class="form-group">
                    <label for="supplier_id">Supplier:</label>
                    <select id="supplier_id" name="supplier_id" required>
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>">
                                <?php echo htmlspecialchars($supplier['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

                <div class="purchase-items">
                    <h3>Purchase Items</h3>
                    <div class="items-list" id="purchaseItems">
                        <!-- Items will be added here dynamically -->
                    </div>
                    <button type="button" class="btn btn-secondary" id="addItem">Add Item</button>
            </div>

                <div class="form-group">
                    <label for="payment_method">Payment Method:</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
            </div>

                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
            </div>

                <div class="purchase-total">
                    <span>Total Amount:</span>
                    <span id="totalAmount">Ksh 0.00</span>
            </div>

                <input type="hidden" name="purchase_items" id="purchaseItemsData">
                <input type="hidden" name="total_amount" id="totalAmountData">
                <button type="submit" name="complete_purchase" class="btn btn-primary">Complete Purchase</button>
        </form>
        </div>

        <!-- Purchases List -->
        <div class="card">
            <h2>Restocking History</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($purchases)): ?>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($purchase['purchase_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($purchase['supplier_name']); ?></td>
                                    <td>
                                        <?php 
                                        $itemsCount = count($purchase['items']);
                                        echo $itemsCount . " items";
                                        ?>
                                    </td>
                                    <td>Ksh <?php echo number_format($purchase['total_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($purchase['payment_method']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $purchase['status'] === 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo ucfirst($purchase['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewPurchase(<?php echo $purchase['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No restocking records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>" class="btn btn-sm">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="btn btn-sm <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>" class="btn btn-sm">Next</a>
                    <?php endif; ?>
                </div>
        <?php endif; ?>
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

    <!-- Purchase Management Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize variables
            const foodItems = <?php echo json_encode($foodItems); ?>;
            let purchaseItems = [];
            const purchaseItemsContainer = document.getElementById('purchaseItems');
            const totalAmountDisplay = document.getElementById('totalAmount');
            const totalAmountData = document.getElementById('totalAmountData');
            const purchaseItemsData = document.getElementById('purchaseItemsData');

            // Add item button handler
            document.getElementById('addItem').addEventListener('click', function() {
                const itemHtml = `
                    <div class="purchase-item">
                        <select class="food-item-select" required>
                            <option value="">Select Food Item</option>
                            ${foodItems.map(item => `
                                <option value="${item.id}" 
                                        data-price="${item.unit_price}"
                                        data-unit="${item.unit}">
                                    ${item.name} (${item.unit})
                                </option>
                            `).join('')}
                        </select>
                        <input type="number" class="quantity-input" min="1" value="1" required>
                        <input type="number" class="unit-price-input" min="0" step="0.01" required>
                        <button type="button" class="remove-item">×</button>
        </div>
      `;
                purchaseItemsContainer.insertAdjacentHTML('beforeend', itemHtml);
                updateTotal();
            });

            // Remove item button handler
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-item')) {
                    e.target.closest('.purchase-item').remove();
                    updateTotal();
                }
            });

            // Update unit price when food item is selected
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('food-item-select')) {
                    const selectedOption = e.target.options[e.target.selectedIndex];
                    const unitPriceInput = e.target.closest('.purchase-item').querySelector('.unit-price-input');
                    if (selectedOption.value) {
                        unitPriceInput.value = selectedOption.dataset.price;
                        updateTotal();
                    }
                }
            });

            // Update total when quantity or unit price changes
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('quantity-input') || 
                    e.target.classList.contains('unit-price-input')) {
                    updateTotal();
                }
            });

            // Update total amount
            function updateTotal() {
                let total = 0;
                purchaseItems = [];

                document.querySelectorAll('.purchase-item').forEach(item => {
                    const foodItemSelect = item.querySelector('.food-item-select');
                    const quantityInput = item.querySelector('.quantity-input');
                    const unitPriceInput = item.querySelector('.unit-price-input');

                    const foodItemId = foodItemSelect.value;
                    const quantity = parseInt(quantityInput.value);
                    const unitPrice = parseFloat(unitPriceInput.value);

                    if (foodItemId && quantity && unitPrice) {
                        const itemTotal = quantity * unitPrice;
                        total += itemTotal;
                        
                        // Find the selected food item
                        const selectedFoodItem = foodItems.find(item => item.id == foodItemId);
                        
                        purchaseItems.push({
                            id: foodItemId,
                            name: selectedFoodItem.name,
                            quantity: quantity,
                            unit_price: unitPrice,
                            total_price: itemTotal,
                            unit: selectedFoodItem.unit
                        });
                    }
                });

                totalAmountDisplay.textContent = `Ksh ${total.toFixed(2)}`;
                totalAmountData.value = total;
                purchaseItemsData.value = JSON.stringify(purchaseItems);
            }

            // Form submission validation
            document.getElementById('purchaseForm').addEventListener('submit', function(e) {
                if (purchaseItems.length === 0) {
                    e.preventDefault();
                    alert('Please add at least one item to the purchase.');
                    return;
                }

                // Validate all items have required fields
                const invalidItems = Array.from(document.querySelectorAll('.purchase-item')).filter(item => {
                    const foodItemSelect = item.querySelector('.food-item-select');
                    const quantityInput = item.querySelector('.quantity-input');
                    const unitPriceInput = item.querySelector('.unit-price-input');
                    
                    return !foodItemSelect.value || !quantityInput.value || !unitPriceInput.value;
                });

                if (invalidItems.length > 0) {
                    e.preventDefault();
                    alert('Please fill in all required fields for each item.');
                    return;
                }
            });

            // Initialize the first item if none exists
            if (document.querySelectorAll('.purchase-item').length === 0) {
                document.getElementById('addItem').click();
            }
        });
    </script>

    <!-- View Purchase Modal -->
    <div id="viewPurchaseModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Restocking Details</h2>
            <div id="purchaseDetails">
                <!-- Purchase details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
    // Store purchases data for view modal
    const purchases = <?php echo json_encode($purchases); ?>;

    function viewPurchase(id) {
        const purchase = purchases.find(p => p.id === id);
        if (purchase) {
            let itemsHtml = '<table class="data-table">';
            itemsHtml += '<thead><tr><th>Item</th><th>Quantity</th><th>Unit Price</th><th>Total</th></tr></thead>';
            itemsHtml += '<tbody>';
            
            purchase.items.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td>${item.item_name}</td>
                        <td>${item.quantity} ${item.unit}</td>
                        <td>Ksh ${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td>Ksh ${parseFloat(item.total_price).toFixed(2)}</td>
                    </tr>
                `;
            });
            
            itemsHtml += '</tbody></table>';
            
            document.getElementById('purchaseDetails').innerHTML = `
                <p><strong>Date:</strong> ${new Date(purchase.purchase_date).toLocaleString()}</p>
                <p><strong>Supplier:</strong> ${purchase.supplier_name}</p>
                <p><strong>Payment Method:</strong> ${purchase.payment_method}</p>
                <p><strong>Status:</strong> ${purchase.status}</p>
                <p><strong>Notes:</strong> ${purchase.notes || 'None'}</p>
                <h3>Restocked Items</h3>
                ${itemsHtml}
                <p><strong>Total Amount:</strong> Ksh ${parseFloat(purchase.total_amount).toFixed(2)}</p>
            `;
            
            document.getElementById('viewPurchaseModal').style.display = 'block';
        }
    }

    // Close modal when clicking the X
    document.querySelector('.close').addEventListener('click', function() {
        document.getElementById('viewPurchaseModal').style.display = 'none';
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('viewPurchaseModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
        });
    </script>
</body>
</html>