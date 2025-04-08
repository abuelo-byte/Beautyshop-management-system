<?php
// 1) DB Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop"; // Adjust if needed

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------------------------------------------------------------------
// 2) Create the 'purchases' table if it doesn't exist
// ---------------------------------------------------------------------
$createPurchasesTable = "CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    purchase_date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
if (!$conn->query($createPurchasesTable)) {
    die("Could not create purchases table: " . $conn->error);
}

// ---------------------------------------------------------------------
// 3) Fetch All Products from 'beautyshop' to display
// ---------------------------------------------------------------------
$sqlProducts = "SELECT * FROM beautyshop";
$resultProds = $conn->query($sqlProducts);
$allProducts = [];
if ($resultProds && $resultProds->num_rows > 0) {
    while ($row = $resultProds->fetch_assoc()) {
        $allProducts[] = $row;
    }
}

// ---------------------------------------------------------------------
// 4) Process New Purchase Form
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_purchase'])) {
    $productId = intval($_POST['purchase_product_id'] ?? 0);
    $purchaseQty = intval($_POST['purchase_quantity'] ?? 0);
    $purchasePrice = floatval($_POST['purchase_price'] ?? 0);
    $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d'); // default to today if not given

    if ($productId > 0 && $purchaseQty > 0 && $purchasePrice > 0) {
        // 1) Insert into 'purchases'
        $stmt = $conn->prepare("INSERT INTO purchases (product_id, quantity, purchase_price, purchase_date)
                                VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iids", $productId, $purchaseQty, $purchasePrice, $purchaseDate);
        $stmt->execute();
        $stmt->close();

        // 2) Update 'beautyshop' stock
        $upd = $conn->prepare("UPDATE beautyshop SET stock = stock + ? WHERE id = ?");
        $upd->bind_param("ii", $purchaseQty, $productId);
        $upd->execute();
        $upd->close();
    }
    // Refresh
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ---------------------------------------------------------------------
// 5) Fetch All Purchases (with product info) for listing
// ---------------------------------------------------------------------
$sqlPurchases = "SELECT p.*, b.name AS product_name, b.category, b.subcategory, b.company
                 FROM purchases p
                 JOIN beautyshop b ON p.product_id = b.id
                 ORDER BY p.id DESC";
$resultPurchases = $conn->query($sqlPurchases);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Purchases - Beauty Shop</title>
    <link rel="stylesheet" href="products.css"><!-- Reuse your products.css or create a new one -->
</head>

<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <h2 class="logo">abuelo jua code</h2>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="products.php">Products</a></li>
            <li class="active"><a href="purchases.php">Purchases</a></li>
            <li><a href="sales.php">Sales</a></li>
            <li><a href="Customers.php">Customers</a></li>
            <li><a href="suppliers.php">Suppliers</a></li>
            <li><a href="Reports.php">Reports</a></li>
            <li><a href="myaccount.php">MyAccount</a></li>
            <li><a href="settings.php">Settings</a></li>
        </ul>
    </aside>

    <header>
        <h1>Beauty Shop - Purchases</h1>
    </header>

    <div class="container">

        <!-- Filter & Category Section (Similar to Products) -->
        <div class="search-container">
            <div class="search-box">
                <input type="text" id="search-input" placeholder="Search products...">
            </div>
            <div class="category-filter">
                <select id="category-select">
                    <option value="">All Categories</option>
                    <option value="hair">Hair</option>
                    <option value="hairfood">Hair Food</option>
                    <option value="oils">Oils</option>
                    <option value="gels">Gels</option>
                    <option value="treatment">Treatment</option>
                    <option value="wax">Wax</option>
                    <option value="shampoos">Shampoos</option>
                    <option value="conditioners">Conditioners</option>
                    <option value="dyes">Dyes</option>
                    <option value="relaxers">Relaxers</option>
                    <option value="hairsprays">Hair Sprays</option>
                    <option value="toiletries">Toiletries</option>
                    <option value="body-lotions">Body Lotions</option>
                    <option value="sprays">Sprays</option>
                    <option value="hair-clips">Hair Clips</option>
                </select>
            </div>
            <!-- We might not need company filter here, but let's keep it for consistency -->
            <div class="company-filter">
                <input type="text" id="company-input" placeholder="Filter by company/brand...">
            </div>
        </div>

        <div class="categories-section">
            <div class="category-tabs">
                <button class="category-tab active" data-category="all">All Products</button>
                <button class="category-tab" data-category="hair">Hair</button>
                <button class="category-tab" data-category="hairfood">Hair Food</button>
                <button class="category-tab" data-category="oils">Oils</button>
                <button class="category-tab" data-category="gels">Gels</button>
                <button class="category-tab" data-category="treatment">Treatment</button>
                <button class="category-tab" data-category="wax">Wax</button>
                <button class="category-tab" data-category="shampoos">Shampoos</button>
                <button class="category-tab" data-category="conditioners">Conditioners</button>
                <button class="category-tab" data-category="dyes">Dyes</button>
                <button class="category-tab" data-category="relaxers">Relaxers</button>
                <button class="category-tab" data-category="hairsprays">Hair Sprays</button>
                <button class="category-tab" data-category="toiletries">Toiletries</button>
                <button class="category-tab" data-category="body-lotions">Body Lotions</button>
                <button class="category-tab" data-category="sprays">Sprays</button>
                <button class="category-tab" data-category="hair-clips">Hair Clips</button>
            </div>
            <!-- No subcategories displayed for purchases, or you can replicate the subcategory logic if you like. -->
        </div>

        <!-- New Purchase Form -->
        <button class="add-product-btn" id="show-purchase-form-btn">+ Add New Purchase</button>
        <form id="purchase-form" method="post" action="" style="display: none;">
            <h2>New Purchase</h2>
            <input type="hidden" name="new_purchase" value="1">

            <div>
                <label for="purchase-product-id">Select Product:</label>
                <select id="purchase-product-id" name="purchase_product_id" required>
                    <option value="">-- Choose a product --</option>
                    <?php foreach ($allProducts as $prod): ?>
                        <option value="<?php echo $prod['id']; ?>">
                            <?php echo htmlspecialchars($prod['name'] . " (" . $prod['company'] . ")"); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="purchase-quantity">Quantity:</label>
                <input type="number" id="purchase-quantity" name="purchase_quantity" min="1" required>
            </div>
            <div>
                <label for="purchase-price">Purchase Price:</label>
                <input type="number" step="0.01" min="0" id="purchase-price" name="purchase_price" required>
            </div>
            <div>
                <label for="purchase-date">Purchase Date:</label>
                <input type="date" id="purchase-date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div>
                <button type="submit">Save Purchase</button>
                <button type="button" id="cancel-purchase-btn">Cancel</button>
            </div>
        </form>

        <!-- Filtered Products Grid (like products page) -->
        <div class="products-grid" id="products-container">
            <!-- Filtered products (for reference) will be rendered here by JS -->
        </div>

        <!-- List of Purchases -->
        <h2 style="margin-top: 2rem;">Purchase Records</h2>
        <?php if ($resultPurchases && $resultPurchases->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Purchase Price</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $resultPurchases->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><?php echo $row['quantity']; ?></td>
                                <td>$<?php echo number_format($row['purchase_price'], 2); ?></td>
                                <td><?php echo $row['purchase_date']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No purchase records found.</p>
        <?php endif; ?>
    </div>

    <script>
        // We'll reuse the same logic from 'products.php' for filtering
        // We'll store the 'allProducts' in a JS array for filtering
        let products = <?php echo json_encode($allProducts); ?>;

        let selectedCategory = 'all';
        let selectedSubcategory = 'all'; // We won't actually show subcategories here, but let's keep the variable

        // RENDER
        function renderProducts(productsList) {
            const container = document.getElementById('products-container');
            container.innerHTML = '';

            if (!productsList.length) {
                container.innerHTML = '<div class="no-products-message">No products found matching your criteria.</div>';
                return;
            }

            productsList.forEach(product => {
                const stockStatus = product.stock > 10 ? 'in-stock'
                    : (product.stock > 0 ? 'low-stock' : 'out-of-stock');
                const stockText = product.stock > 10 ? 'In Stock'
                    : (product.stock > 0 ? 'Low Stock' : 'Out of Stock');

                const card = document.createElement('div');
                card.className = 'product-card';
                card.innerHTML = `
        <div class="product-image">
          <img src="${product.image}" alt="${product.name}">
        </div>
        <div class="product-details">
          <div class="product-title">${product.name}</div>
          <div class="product-company">${product.company}</div>
          <div class="product-price">$${parseFloat(product.price).toFixed(2)}</div>
          <div class="stock-status ${stockStatus}">${stockText}</div>
        </div>
      `;
                container.appendChild(card);
            });
        }

        // FILTER
        function filterProducts() {
            const searchQuery = document.getElementById('search-input').value.toLowerCase();
            const categorySelect = document.getElementById('category-select').value;
            const companyFilter = document.getElementById('company-input').value.toLowerCase();

            const filtered = products.filter(prod => {
                const matchesSearch = prod.name.toLowerCase().includes(searchQuery);
                const matchesCategoryTab = (selectedCategory === 'all' || prod.category === selectedCategory);
                const matchesCategoryInput = (categorySelect === '' || prod.category === categorySelect);

                let matchesSubcat = true;
                if (selectedCategory === 'hair') {
                    matchesSubcat = (selectedSubcategory === 'all' || prod.subcategory === selectedSubcategory);
                } else if (selectedCategory === 'toiletries') {
                    matchesSubcat = (selectedSubcategory === 'all' || prod.subcategory === selectedSubcategory);
                }

                const matchesCompany = prod.company.toLowerCase().includes(companyFilter);

                return matchesSearch && matchesCategoryTab && matchesCategoryInput && matchesSubcat && matchesCompany;
            });

            renderProducts(filtered);
        }

        // INITIAL RENDER
        renderProducts(products);

        // LISTENERS for search, category, company filter
        document.getElementById('search-input').addEventListener('input', filterProducts);
        document.getElementById('category-select').addEventListener('change', filterProducts);
        document.getElementById('company-input').addEventListener('input', filterProducts);

        // CATEGORY TABS
        const catTabs = document.querySelectorAll('.category-tab');
        catTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                catTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                selectedCategory = tab.dataset.category;

                // We won't do subcategories for purchases, but let's keep consistent
                if (selectedCategory === 'hair') {
                    selectedSubcategory = 'all';
                } else if (selectedCategory === 'toiletries') {
                    selectedSubcategory = 'all';
                } else {
                    selectedSubcategory = 'all';
                }

                filterProducts();
            });
        });

        // ========== New Purchase Form Toggle ==========
        const showPurchaseFormBtn = document.getElementById('show-purchase-form-btn');
        const purchaseForm = document.getElementById('purchase-form');
        const cancelPurchaseBtn = document.getElementById('cancel-purchase-btn');

        showPurchaseFormBtn.addEventListener('click', () => {
            purchaseForm.style.display = 'block';
        });
        cancelPurchaseBtn.addEventListener('click', () => {
            purchaseForm.style.display = 'none';
        });
    </script>
</body>

</html>