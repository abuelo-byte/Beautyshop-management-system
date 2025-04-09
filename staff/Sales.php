<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

/********************************************
 * 1) Database Connection
 ********************************************/
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafeteria-management-system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create customers table if it doesn't exist
$createCustomersTable = "CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    loyalty_points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($createCustomersTable)) {
    die("Customers table creation failed: " . $conn->error);
}

// Create food_items table if it doesn't exist
$createFoodItemsTable = "CREATE TABLE IF NOT EXISTS food_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($createFoodItemsTable)) {
    die("Food items table creation failed: " . $conn->error);
}

// Create stock_inventory table if it doesn't exist
$createStockInventoryTable = "CREATE TABLE IF NOT EXISTS stock_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    food_item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(20) NOT NULL,
    min_stock_level INT NOT NULL DEFAULT 10,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (food_item_id) REFERENCES food_items(id)
)";

if (!$conn->query($createStockInventoryTable)) {
    die("Stock inventory table creation failed: " . $conn->error);
}

// Create sales table if it doesn't exist
$createSalesTable = "CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    cart_data TEXT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
)";

if (!$conn->query($createSalesTable)) {
    die("Sales table creation failed: " . $conn->error);
}

// Handle cart updates via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_action']) && $_POST['cart_action'] === 'update') {
    header('Content-Type: application/json');
    
    try {
        $cartData = $_POST['cart_data'];
        $totalAmount = floatval($_POST['total_amount']);
        $customerId = isset($_POST['customer_id']) && !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        
        // Look for an existing pending sale
        $pendingSale = null;
        $pendingQuery = "SELECT id FROM sales WHERE processed = 0 ORDER BY sale_date DESC LIMIT 1";
        $pendingResult = $conn->query($pendingQuery);
        
        if ($pendingResult && $pendingResult->num_rows > 0) {
            $pendingSale = $pendingResult->fetch_assoc();
            
            // Update existing pending sale
            $updateStmt = $conn->prepare("UPDATE sales SET cart_data = ?, total_amount = ?, customer_id = ? WHERE id = ?");
            $updateStmt->bind_param("sdii", $cartData, $totalAmount, $customerId, $pendingSale['id']);
            $updateStmt->execute();
            
            echo json_encode(['success' => true, 'sale_id' => $pendingSale['id'], 'message' => 'Cart updated']);
        } else {
            // Create a new pending sale
            $insertStmt = $conn->prepare("INSERT INTO sales (customer_id, cart_data, total_amount, payment_method, processed) VALUES (?, ?, ?, 'pending', 0)");
            $insertStmt->bind_param("isd", $customerId, $cartData, $totalAmount);
            $insertStmt->execute();
            
            $newSaleId = $conn->insert_id;
            echo json_encode(['success' => true, 'sale_id' => $newSaleId, 'message' => 'New cart created']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}

// Handle new sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_sale'])) {
    $cartData = json_decode($_POST['cart_data'], true);
    $totalAmount = floatval($_POST['total_amount']);
    $paymentMethod = $conn->real_escape_string($_POST['payment_method']);
    $customerId = isset($_POST['customer_id']) && !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $pendingSaleId = isset($_POST['pending_sale_id']) ? intval($_POST['pending_sale_id']) : null;

    // Validate cart data
    if (empty($cartData) || !is_array($cartData)) {
        $error_message = "No items in cart. Please add items before completing the sale.";
    } else {
        // Start transaction
        $conn->begin_transaction();

        try {
            if ($pendingSaleId) {
                // Update existing pending sale
                $stmt = $conn->prepare("UPDATE sales SET customer_id = ?, cart_data = ?, total_amount = ?, payment_method = ?, processed = 1 WHERE id = ?");
                $stmt->bind_param("isdsi", $customerId, $_POST['cart_data'], $totalAmount, $paymentMethod, $pendingSaleId);
            } else {
                // Insert sale record
                $stmt = $conn->prepare("INSERT INTO sales (customer_id, cart_data, total_amount, payment_method, processed) VALUES (?, ?, ?, ?, 1)");
                $stmt->bind_param("isds", $customerId, $_POST['cart_data'], $totalAmount, $paymentMethod);
            }
            $stmt->execute();

            // Update stock levels
            foreach ($cartData as $item) {
                $upd = $conn->prepare("UPDATE stock_inventory SET quantity = quantity - ? WHERE food_item_id = ?");
                $upd->bind_param("ii", $item['quantity'], $item['id']);
                $upd->execute();
            }

            $conn->commit();
            $success_message = "Sale completed successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error completing sale: " . $e->getMessage();
        }
    }
}

// Fetch food items for the menu
$menuItems = [];
$menuQuery = "SELECT fi.*, si.quantity 
              FROM food_items fi 
              JOIN stock_inventory si ON fi.id = si.food_item_id 
              WHERE si.quantity > 0 
              ORDER BY fi.category, fi.name";
$menuResult = $conn->query($menuQuery);
if ($menuResult && $menuResult->num_rows > 0) {
    while ($row = $menuResult->fetch_assoc()) {
        $menuItems[] = $row;
    }
}

// Fetch recent sales
$recentSales = [];
$salesQuery = "SELECT s.*, c.name as customer_name 
               FROM sales s 
               LEFT JOIN customers c ON s.customer_id = c.id 
               ORDER BY s.sale_date DESC 
               LIMIT 10";
$salesResult = $conn->query($salesQuery);
if ($salesResult && $salesResult->num_rows > 0) {
    while ($row = $salesResult->fetch_assoc()) {
        $recentSales[] = $row;
    }
}

// Check for a pending sale
$pendingSale = null;
$pendingQuery = "SELECT id, cart_data, total_amount, customer_id FROM sales WHERE processed = 0 ORDER BY sale_date DESC LIMIT 1";
$pendingResult = $conn->query($pendingQuery);
if ($pendingResult && $pendingResult->num_rows > 0) {
    $pendingSale = $pendingResult->fetch_assoc();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="Staff.css">
    
    <!-- Make addItemToCart available immediately, before any other script -->
    <script>
        // Define the function globally and immediately
        window.addItemToCart = function(itemId) {
            console.log("addItemToCart called for:", itemId);
            
            // Function implementation will follow when the page has fully loaded
            // This placeholder ensures the function exists when buttons are clicked
            if (!window.cartInitialized) {
                console.log("Cart not initialized yet, queuing item:", itemId);
                window.pendingItems = window.pendingItems || [];
                window.pendingItems.push(itemId);
                return false;
            }
            
            try {
                return window.addItemToCartImpl(itemId);
            } catch(err) {
                console.error("Error in addItemToCart:", err);
                return false;
            }
        };
    </script>
    
    <!-- Immediate execution script for cart functionality -->
    <script>
        // Immediately initialize cart functionality
        (function() {
            // Debug helper
            window.DEBUG = true;
            window.log = function(message, data) {
                if (window.DEBUG) {
                    console.log(message, data || '');
                }
            };
            
            // Initialize cart
            window.cart = [];
            
            // Function to save cart to database
            window.saveCartToDatabase = function() {
                // Don't save empty cart
                if (!window.cart || window.cart.length === 0) {
                    return;
                }
                
                log('Saving cart to database');
                
                // Create form data
                const formData = new FormData();
                formData.append('cart_action', 'update');
                formData.append('cart_data', JSON.stringify(window.cart));
                formData.append('total_amount', window.cart.reduce((total, item) => total + (item.price * item.quantity), 0));
                
                // Get customer ID if available
                const customerSelect = document.getElementById('customer_id');
                if (customerSelect && customerSelect.value) {
                    formData.append('customer_id', customerSelect.value);
                }
                
                // Send AJAX request
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        log('Cart saved successfully:', data);
                    } else {
                        console.error('Error saving cart:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error sending cart data:', error);
                });
            };
            
            // Define addToCart function immediately
            window.addToCart = function(itemId) {
                log("Add to cart called for item:", itemId);
                
                // Convert to number
                itemId = parseInt(itemId);
                
                // If menu items aren't loaded yet, store the request for later processing
                if (!window.foodItems || !window.foodItems.length) {
                    log("Food items not loaded yet, storing request");
                    window.pendingCartItems = window.pendingCartItems || [];
                    window.pendingCartItems.push(itemId);
                    
                    // Show feedback to user
                    const button = document.querySelector(`.menu-item[data-id="${itemId}"] .add-to-cart`);
                    if (button) {
                        button.textContent = "Processing...";
                    }
                    return;
                }
                
                // Find the item
                const item = window.foodItems.find(i => parseInt(i.id) === itemId);
                if (!item) {
                    log('Item not found in food items:', itemId);
                    alert('Error: Item not found.');
                    return;
                }
                
                log('Found item:', item);
                
                // Add to cart
                const existingItem = window.cart.find(i => parseInt(i.id) === itemId);
                if (existingItem) {
                    if (existingItem.quantity < parseInt(item.quantity)) {
                        existingItem.quantity += 1;
                        log('Increased quantity for item:', itemId);
                    } else {
                        alert('Cannot add more items. Stock limit reached.');
                        return;
                    }
                } else {
                    window.cart.push({
                        id: parseInt(item.id),
                        name: item.name,
                        price: parseFloat(item.unit_price),
                        quantity: 1,
                        stock: parseInt(item.quantity)
                    });
                    log('Added new item to cart:', itemId);
                }
                
                // Update cart display if function is available
                if (typeof window.updateCartDisplay === 'function') {
                    window.updateCartDisplay();
                }
                
                // Save cart data to database
                window.saveCartToDatabase();
                
                // Visual feedback
                const addedButton = document.querySelector(`.menu-item[data-id="${itemId}"] .add-to-cart`);
                if (addedButton) {
                    addedButton.classList.add('added');
                    addedButton.textContent = 'Added!';
                    setTimeout(() => {
                        addedButton.classList.remove('added');
                        addedButton.textContent = 'Add to Cart';
                    }, 1000);
                }
            };
            
            // Define the updateCartDisplay function immediately
            window.updateCartDisplay = function() {
                const cartItemsContainer = document.getElementById('orderItems');
                const cartTotalElement = document.getElementById('orderTotal');
                
                if (!cartItemsContainer || !cartTotalElement) {
                    console.error("Cart display elements not found");
                    return;
                }
                
                let total = 0;
                
                log('Updating cart display. Cart has', window.cart.length + ' items');
                
                cartItemsContainer.innerHTML = '';
                
                window.cart.forEach(item => {
                    const itemTotal = item.price * item.quantity;
                    total += itemTotal;
                    
                    const itemElement = document.createElement('div');
                    itemElement.className = 'order-item';
                    itemElement.innerHTML = `
                        <div class="item-quantity">
                            <button class="quantity-btn decrease" onclick="updateQuantity(${item.id}, -1)">-</button>
                            <input type="number" class="quantity-input" value="${item.quantity}" min="1" max="${item.stock}" 
                                   onchange="updateQuantityInput(${item.id}, this.value)">
                            <button class="quantity-btn increase" onclick="updateQuantity(${item.id}, 1)">+</button>
                        </div>
                        <div class="item-details">
                            <span class="item-name">${item.name}</span>
                            <span class="item-price">Ksh ${itemTotal.toFixed(2)}</span>
                        </div>
                        <button class="remove-item" onclick="removeFromCart(${item.id})">×</button>
                    `;
                    cartItemsContainer.appendChild(itemElement);
                });
                
                cartTotalElement.textContent = `Ksh ${total.toFixed(2)}`;
                
                const cartDataField = document.getElementById('cartData');
                const totalAmountField = document.getElementById('totalAmount');
                
                if (cartDataField) cartDataField.value = JSON.stringify(window.cart);
                if (totalAmountField) totalAmountField.value = total;
                
                // Enable/disable complete sale button based on cart
                const completeSaleBtn = document.getElementById('completeSale');
                if (completeSaleBtn) {
                    completeSaleBtn.disabled = window.cart.length === 0;
                }
            };
            
            // Define other helper functions immediately
            window.updateQuantity = function(itemId, change) {
                try {
                    log('Updating quantity for item:', itemId);
                    const item = window.cart.find(i => parseInt(i.id) === parseInt(itemId));
                    if (item) {
                        const newQuantity = item.quantity + change;
                        if (newQuantity >= 1 && newQuantity <= item.stock) {
                            item.quantity = newQuantity;
                            window.updateCartDisplay();
                            
                            // Save cart data to database
                            window.saveCartToDatabase();
                        }
                    }
                } catch (err) {
                    console.error("Error updating quantity:", err);
                }
            };
            
            window.updateQuantityInput = function(itemId, value) {
                try {
                    log('Manual quantity update for item:', itemId);
                    const item = window.cart.find(i => parseInt(i.id) === parseInt(itemId));
                    if (item) {
                        const newQuantity = parseInt(value);
                        if (!isNaN(newQuantity) && newQuantity >= 1 && newQuantity <= item.stock) {
                            item.quantity = newQuantity;
                            window.updateCartDisplay();
                            
                            // Save cart data to database
                            window.saveCartToDatabase();
                        } else {
                            alert('Invalid quantity. Must be between 1 and ' + item.stock);
                        }
                    }
                } catch (err) {
                    console.error("Error updating quantity input:", err);
                }
            };
            
            window.removeFromCart = function(itemId) {
                try {
                    log('Removing item from cart:', itemId);
                    window.cart = window.cart.filter(item => parseInt(item.id) !== parseInt(itemId));
                    window.updateCartDisplay();
                    
                    // Save cart data to database
                    window.saveCartToDatabase();
                } catch (err) {
                    console.error("Error removing from cart:", err);
                }
            };
            
            // Document ready handler that will execute once DOM is loaded
            document.addEventListener('DOMContentLoaded', function() {
                log('DOM loaded, initializing cart functionality');
                
                // Load pending sale if it exists
                <?php if ($pendingSale): ?>
                try {
                    window.pendingSale = <?php echo json_encode($pendingSale); ?>;
                    log('Found pending sale:', window.pendingSale);
                    
                    // Load cart data from pending sale
                    if (window.pendingSale.cart_data) {
                        window.cart = JSON.parse(window.pendingSale.cart_data);
                        log('Loaded ' + window.cart.length + ' items from pending sale');
                        
                        // Set customer if available
                        if (window.pendingSale.customer_id) {
                            const customerSelect = document.getElementById('customer_id');
                            if (customerSelect) {
                                customerSelect.value = window.pendingSale.customer_id;
                            }
                        }
                        
                        // Update cart display
                        window.updateCartDisplay();
                    }
                } catch (err) {
                    console.error('Error loading pending sale:', err);
                }
                <?php endif; ?>
                
                // Process any pending cart items
                if (window.pendingCartItems && window.pendingCartItems.length && window.foodItems) {
                    log('Processing pending cart items:', window.pendingCartItems);
                    window.pendingCartItems.forEach(itemId => window.addToCart(itemId));
                    window.pendingCartItems = [];
                }
                
                window.updateCartDisplay();
            });
        })();
    </script>
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
            <li><a href="Purchases.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="link-text">Purchases</span>
                </a></li>
            <li><a href="#" class="active">
                    <i class="fas fa-cash-register"></i>
                    <span class="link-text">Sales</span>
                </a></li>
            <li><a href="Customers.php">
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
            <h1>Sales Management</h1>
    </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Point of Sale Interface -->
        <div class="card">
            <h2>Point of Sale</h2>
            <div class="pos-container">
                <!-- Menu Items -->
                <div class="menu-section">
                    <h3>Menu Items</h3>
                    <div class="menu-search">
                        <input type="text" id="menuSearch" placeholder="Search menu items...">
                </div>
                    <div class="menu-categories">
                        <button class="category-btn active" data-category="all">All</button>
                        <button class="category-btn" data-category="main course">Main Course</button>
                        <button class="category-btn" data-category="side dish">Side Dish</button>
                        <button class="category-btn" data-category="beverage">Beverage</button>
                        <button class="category-btn" data-category="dessert">Dessert</button>
                        <button class="category-btn" data-category="snack">Snack</button>
                </div>
                    <div class="menu-grid">
                        <?php foreach ($menuItems as $item): ?>
                            <div class="menu-item" data-id="<?php echo $item['id']; ?>" 
                                 data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                 data-price="<?php echo $item['unit_price']; ?>"
                                 data-stock="<?php echo $item['quantity']; ?>"
                                 data-category="<?php echo htmlspecialchars($item['category']); ?>">
                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="item-price">Ksh <?php echo number_format($item['unit_price'], 2); ?></div>
                                <div class="item-stock">Stock: <?php echo $item['quantity']; ?></div>
                                <button 
                                    style="background-color: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 500; margin-top: 5px; width: 100%;" 
                                    onclick="addItemToCart(<?php echo $item['id']; ?>); return false;" 
                                    class="add-to-cart-btn" 
                                    data-id="<?php echo $item['id']; ?>"
                                >
                                    Add to Cart
                                </button>
                            </div>
                        <?php endforeach; ?>
                </div>
                </div>

                <!-- Order Summary -->
                <div class="order-section">
                    <h3>Order Summary</h3>
                    <div class="order-items" id="orderItems">
                        <!-- Items will be added here dynamically -->
                    </div>
                    <div class="order-total">
                        <span>Total:</span>
                        <span id="orderTotal">Ksh 0.00</span>
                    </div>
                    <form method="POST" action="" id="saleForm">
                        <input type="hidden" name="cart_data" id="cartData">
                        <input type="hidden" name="total_amount" id="totalAmount">
                        <?php if ($pendingSale): ?>
                        <input type="hidden" name="pending_sale_id" value="<?php echo $pendingSale['id']; ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="customer_id">Customer (Optional):</label>
                            <select id="customer_id" name="customer_id">
                                <option value="">Walk-in Customer</option>
                                <?php
                                $customersQuery = "SELECT id, name FROM customers ORDER BY name";
                                $customersResult = $conn->query($customersQuery);
                                if ($customersResult && $customersResult->num_rows > 0) {
                                    while ($customer = $customersResult->fetch_assoc()) {
                                        echo "<option value='{$customer['id']}'>{$customer['name']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="payment_method">Payment Method:</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="card">Card</option>
                            </select>
                    </div>
                        <button type="submit" name="complete_sale" class="btn btn-primary" id="completeSale">Complete Sale</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="card">
            <h2>Recent Sales</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentSales)): ?>
                        <?php foreach ($recentSales as $sale): 
                            $cartData = json_decode($sale['cart_data'], true);
                            $itemsCount = 0;
                            if ($cartData !== null) {
                                $itemsCount = count($cartData);
                            }
                        ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($sale['sale_date'])); ?></td>
                                <td><?php echo $sale['customer_name'] ?? 'Walk-in Customer'; ?></td>
                                <td><?php echo $itemsCount; ?> items</td>
                                <td>Ksh <?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td><?php echo ucfirst($sale['payment_method']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No sales records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- POS System Script to initialize food items -->
    <script>
        // Initialize cart
        window.cart = window.cart || [];
        
        // Check for pending sale data first
        <?php if ($pendingSale && !empty($pendingSale['cart_data'])): ?>
        try {
            const pendingSaleData = JSON.parse('<?php echo addslashes($pendingSale['cart_data']); ?>');
            if (Array.isArray(pendingSaleData) && pendingSaleData.length > 0) {
                window.cart = pendingSaleData;
                console.log("Loaded pending sale data:", window.cart.length + " items");
            }
        } catch (err) {
            console.error("Error parsing pending sale data:", err);
        }
        <?php endif; ?>
        
        // Make sure window.foodItems is directly assigned to avoid any scoping issues
        try {
            // Directly assign to window object to ensure global scope
            window.foodItems = <?php echo json_encode($menuItems); ?>;
            console.log("Menu items loaded:", window.foodItems.length, "items");
            
            // Force a sync operation to ensure the assignment has happened
            document.currentScript.dataset.loaded = "true";
            
            // If the cart was initialized earlier, process any pending items now
            if (window.pendingItems && window.pendingItems.length && window.cartInitialized) {
                console.log("Processing pending items now that food items are loaded");
                window.pendingItems.forEach(itemId => {
                    if (window.addItemToCartImpl) {
                        window.addItemToCartImpl(itemId);
                    }
                });
                window.pendingItems = [];
            }
        } catch (err) {
            console.error("Error loading menu items:", err);
            // Create a fallback empty array to prevent null reference errors
            window.foodItems = [];
        }
    </script>

    <!-- Include a script to update the cart display on page load -->
    <script>
        // Initialize cart display when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Page loaded, initializing cart display");
            
            // Update cart display if we have items
            if (window.cart && window.cart.length > 0) {
                if (typeof updateCartSimple === 'function') {
                    console.log("Updating cart display with", window.cart.length, "items");
                    updateCartSimple();
                } else if (typeof window.updateCartDisplay === 'function') {
                    window.updateCartDisplay();
                }
            }
        });
    </script>

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

    <style>
        /* Cart Styles */
        .cart-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .order-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .item-details {
            flex: 2;
        }

        .item-name {
            font-weight: 500;
            color: #333;
        }

        .item-price {
            color: #666;
            font-size: 0.9em;
        }

        .item-quantity {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            background: #f0f0f0;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: #e0e0e0;
        }

        .quantity-input {
            width: 40px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 4px;
        }

        .remove-item {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2em;
            padding: 5px;
        }

        .remove-item:hover {
            color: #c82333;
        }

        /* Add to Cart Button Styles */
        .add-to-cart-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .add-to-cart-btn:hover {
            background-color: #218838;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .add-to-cart-btn.added {
            background-color: #198754;
            transform: scale(1.05);
        }

        .add-to-cart-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        /* Menu item styles - make them more clickable */
        .menu-item {
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .menu-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
    
    <!-- Direct script to ensure addItemToCart is available immediately -->
    <script>
        // The real implementation of the cart functionality
        window.addItemToCartImpl = function(itemId) {
            console.log("Direct addItemToCartImpl called for:", itemId);
            
            // Make sure we have the menu items
            if (!window.foodItems || !window.foodItems.length) {
                console.error("Food items not available");
                alert("Error: Menu items not loaded. Please refresh the page.");
                return false;
            }
            
            // Find the item
            const item = window.foodItems.find(i => parseInt(i.id) === parseInt(itemId));
            if (!item) {
                console.error("Item not found:", itemId);
                alert("Error: Item not found");
                return false;
            }
            
            console.log("Found item to add:", item);
            
            // Check if item already in cart
            if (!window.cart) window.cart = [];
            const existingItem = window.cart.find(i => parseInt(i.id) === parseInt(itemId));
            
            if (existingItem) {
                // Check if we have enough stock
                if (existingItem.quantity < parseInt(item.quantity)) {
                    existingItem.quantity += 1;
                    console.log("Increased quantity for item:", itemId);
                } else {
                    alert("Cannot add more items. Stock limit reached.");
                    return false;
                }
            } else {
                // Add new item to cart
                window.cart.push({
                    id: parseInt(item.id),
                    name: item.name,
                    price: parseFloat(item.unit_price),
                    quantity: 1,
                    stock: parseInt(item.quantity)
                });
                console.log("Added new item to cart:", itemId);
            }
            
            // Visual feedback
            const button = document.querySelector(`button[data-id="${itemId}"]`);
            if (button) {
                const originalText = button.textContent;
                button.textContent = "Added!";
                button.style.backgroundColor = "#198754";
                button.style.transform = "scale(1.05)";
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.backgroundColor = "#28a745";
                    button.style.transform = "none";
                }, 1000);
            }
            
            // Update cart display
            if (typeof window.updateCartDisplay === 'function') {
                window.updateCartDisplay();
            } else {
                // Call our own simple version
                updateCartSimple();
            }
            
            // Save to database if available
            if (typeof window.saveCartToDatabase === 'function') {
                window.saveCartToDatabase();
            }
            
            return false; // Prevent default
        };
        
        // Mark cart as initialized immediately (don't wait for DOMContentLoaded)
        window.cartInitialized = true;
        
        // Process any pending items right away
        if (window.pendingItems && window.pendingItems.length) {
            console.log("Processing pending items immediately:", window.pendingItems);
            window.pendingItems.forEach(itemId => {
                window.addItemToCartImpl(itemId);
            });
            window.pendingItems = [];
        }
        
        // Initialize cart when document is ready (keep this for backward compatibility)
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Additional cart initialization on DOMContentLoaded");
            
            // Process any pending items again (in case more were added)
            if (window.pendingItems && window.pendingItems.length) {
                console.log("Processing additional pending items:", window.pendingItems);
                window.pendingItems.forEach(itemId => {
                    window.addItemToCartImpl(itemId);
                });
                window.pendingItems = [];
            }
        });
        
        // Simple cart display update if the main one fails
        function updateCartSimple() {
            try {
                const cartItemsContainer = document.getElementById('orderItems');
                const cartTotalElement = document.getElementById('orderTotal');
                
                if (!cartItemsContainer || !cartTotalElement) {
                    console.error("Cart display elements not found");
                    return;
                }
                
                let total = 0;
                cartItemsContainer.innerHTML = '';
                
                window.cart.forEach(item => {
                    const itemTotal = item.price * item.quantity;
                    total += itemTotal;
                    
                    const itemElement = document.createElement('div');
                    itemElement.className = 'order-item';
                    itemElement.innerHTML = `
                        <div class="item-details">
                            <span class="item-name">${item.name} x ${item.quantity}</span>
                            <span class="item-price">Ksh ${itemTotal.toFixed(2)}</span>
                        </div>
                        <button onclick="removeCartItem(${item.id})" style="background: none; border: none; color: red; cursor: pointer;">×</button>
                    `;
                    cartItemsContainer.appendChild(itemElement);
                });
                
                cartTotalElement.textContent = `Ksh ${total.toFixed(2)}`;
                
                const cartDataField = document.getElementById('cartData');
                const totalAmountField = document.getElementById('totalAmount');
                
                if (cartDataField) cartDataField.value = JSON.stringify(window.cart);
                if (totalAmountField) totalAmountField.value = total;
            } catch (err) {
                console.error("Error in updateCartSimple:", err);
            }
        }
        
        // Simple remove function
        function removeCartItem(itemId) {
            if (!window.cart) return;
            window.cart = window.cart.filter(item => parseInt(item.id) !== parseInt(itemId));
            updateCartSimple();
            if (typeof window.saveCartToDatabase === 'function') {
                window.saveCartToDatabase();
            }
        }
    </script>
</body>

</html>