<?php
session_start();

// 1) Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  // If not logged in, redirect to login
  header("Location: ../login.php"); // or "login.php" if it's in the same folder
  exit;
}
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
        // Subtract 'quantity' from product stock by 'id'
        $upd = $conn->prepare("UPDATE beautyshop SET stock = stock - ? WHERE id = ?");
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
// 2) Create Table if not exists for products
// -----------------------------------------
$createTableQuery = "CREATE TABLE IF NOT EXISTS beautyshop (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(255) NOT NULL,
    subcategory VARCHAR(255) DEFAULT '',
    company VARCHAR(255) NOT NULL,
    stock INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if (!$conn->query($createTableQuery)) {
  die("Table creation failed: " . $conn->error);
}

// -----------------------------------------
// 3) Helper function to get default images
// -----------------------------------------
function getDefaultImage($category, $subcategory = "")
{
  // Common images by category
  $defaultImages = [
    "hair" => "images/Braid.jpeg",
    "hairfood" => "images/naturaloils.jpeg",
    "oils" => "images/oils.jpeg",
    "gels" => "images/gel.jpeg",
    "treatment" => "images/treatment.jpeg",
    "wax" => "images/wax.jpeg",
    "shampoos" => "images/shampoos.jpeg",
    "conditioners" => "images/conditioners.jpeg",
    "dyes" => "images/dyes.jpeg",
    "relaxers" => "images/relaxers.jpeg",
    "hairsprays" => "images/hairspray.jpeg",
    "toiletries" => "images/toiletries.jpeg",
    "body-lotions" => "images/lotions.jpg",
    "sprays" => "images/sprays.jpg",
    "hair-clips" => "images/clips.jpg"
  ];

  // Hair subcategories
  $hairSubImages = [
    "braids" => "images/Braid.jpeg",
    "general" => "images/general.jpeg",
    "extensions" => "images/hair-extensions.png",
    "sewing-threads" => "images/hair-sewing-threads.png",
    "weaves" => "images/hair-weaves.png"
  ];

  // Toiletries subcategories
  $toiletriesSubImages = [
    "tissues" => "images/tissues.jpg",
    "sanitary-pads" => "images/pads.jpg",
    "liners" => "images/liners.jpg",
    "wipes" => "images/wipes.jpg",
    "hair-removers-shavers" => "images/hair-removers-shavers.jpg",
    "shower-gels" => "images/shower_gel.jpg",
    "liquid-detergents" => "images/liquid-detergents.jpg",
    "bleach" => "images/bleach.jpg",
    "powder-soap" => "images/powder.jpg"
  ];

  // If Hair subcategory
  if ($category === "hair" && $subcategory && isset($hairSubImages[$subcategory])) {
    return $hairSubImages[$subcategory];
  }
  // If Toiletries subcategory
  if ($category === "toiletries" && $subcategory && isset($toiletriesSubImages[$subcategory])) {
    return $toiletriesSubImages[$subcategory];
  }

  // Fallback
  return $defaultImages[$category] ?? "images/default.png";
}

// -----------------------------------------
// 4) Process New Product (Create)
// -----------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['new_product'])) {
  $name = trim($_POST['product_name'] ?? '');
  $category = trim($_POST['product_category'] ?? '');
  $company = trim($_POST['product_company'] ?? '');
  $stock = intval($_POST['product_stock'] ?? 0);
  $price = floatval($_POST['product_price'] ?? 0);
  $subcategory = "";

  if ($category === "hair" && isset($_POST['product_subcategory'])) {
    $subcategory = trim($_POST['product_subcategory']);
  } elseif ($category === "toiletries" && isset($_POST['product_subcategory'])) {
    $subcategory = trim($_POST['product_subcategory']);
  }

  if ($name && $category && $company) {
    $image = getDefaultImage($category, $subcategory);

    $stmt = $conn->prepare("INSERT INTO beautyshop (name, category, subcategory, company, stock, price, image)
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssids", $name, $category, $subcategory, $company, $stock, $price, $image);
    $stmt->execute();
    $stmt->close();
  }
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// -----------------------------------------
// 5) Process Delete
// -----------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_product'])) {
  $deleteId = intval($_POST['delete_id'] ?? 0);
  if ($deleteId > 0) {
    $stmt = $conn->prepare("DELETE FROM beautyshop WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
  }
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// -----------------------------------------
// 6) Process Edit (Update)
// -----------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_product'])) {
  $editId = intval($_POST['edit_id'] ?? 0);
  $editName = trim($_POST['edit_name'] ?? '');
  $editCategory = trim($_POST['edit_category'] ?? '');
  $editCompany = trim($_POST['edit_company'] ?? '');
  $editStock = intval($_POST['edit_stock'] ?? 0);
  $editPrice = floatval($_POST['edit_price'] ?? 0);
  $editSubcat = "";

  if ($editCategory === "hair" && isset($_POST['edit_subcategory'])) {
    $editSubcat = trim($_POST['edit_subcategory']);
  } elseif ($editCategory === "toiletries" && isset($_POST['edit_subcategory'])) {
    $editSubcat = trim($_POST['edit_subcategory']);
  }

  if ($editId > 0 && $editName && $editCategory && $editCompany) {
    $newImage = getDefaultImage($editCategory, $editSubcat);

    $stmt = $conn->prepare("UPDATE beautyshop
                            SET name = ?, category = ?, subcategory = ?, company = ?, stock = ?, price = ?, image = ?
                            WHERE id = ?");
    $stmt->bind_param("ssssidsi", $editName, $editCategory, $editSubcat, $editCompany, $editStock, $editPrice, $newImage, $editId);
    $stmt->execute();
    $stmt->close();
  }
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// -----------------------------------------
// 7) Fetch All Products
// -----------------------------------------
$sql = "SELECT * FROM beautyshop";
$result = $conn->query($sql);
$products = [];
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $products[] = $row;
  }
}
$products_json = json_encode($products);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Beauty Shop Inventory Management</title>
  <link rel="stylesheet" href="products.css">
</head>

<body>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <h2 class="logo">abuelo jua code</h2>
    <ul>
      <li><a href="index.php">Dashboard</a></li>
      <li class="active"><a href="products.php">Products</a></li>
      <li><a href="purchases.php">Purchases</a></li>
      <li><a href="sales.php">Sales</a></li>
      <li><a href="Customers.php">Customers</a></li>
      <li><a href="suppliers.php">Suppliers</a></li>
      <li><a href="Reports.php">Reports</a></li>
      <li><a href="myaccount.php">MyAccount</a></li>
      <li><a href="settings.php">Settings</a></li>
    </ul>
  </aside>

  <!-- MAIN HEADER -->
  <header>
    <h1>Beauty Shop Inventory Management</h1>
  </header>

  <!-- MAIN CONTENT CONTAINER -->
  <div class="container">

    <!-- Search / Filter -->
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
      <div class="company-filter">
        <input type="text" id="company-input" placeholder="Filter by company/brand...">
      </div>
    </div>

    <!-- Category Tabs -->
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

      <!-- Hair Subcategories -->
      <div id="hair-subcategories" class="subcategory-container" style="display: none;">
        <button class="subcategory-button active" data-subcategory="all">All Hair</button>
        <button class="subcategory-button" data-subcategory="braids">Braids</button>
        <button class="subcategory-button" data-subcategory="general">General</button>
        <button class="subcategory-button" data-subcategory="extensions">Extensions</button>
        <button class="subcategory-button" data-subcategory="sewing-threads">Sewing Threads</button>
        <button class="subcategory-button" data-subcategory="weaves">Weaves</button>
      </div>

      <!-- Toiletries Subcategories -->
      <div id="toiletries-subcategories" class="subcategory-container" style="display: none;">
        <button class="subcategory-button active" data-subcategory="all">All Toiletries</button>
        <button class="subcategory-button" data-subcategory="tissues">Tissues</button>
        <button class="subcategory-button" data-subcategory="sanitary-pads">Sanitary Pads</button>
        <button class="subcategory-button" data-subcategory="liners">Liners</button>
        <button class="subcategory-button" data-subcategory="wipes">Wipes</button>
        <button class="subcategory-button" data-subcategory="hair-removers-shavers">Hair Removers/Shavers</button>
        <button class="subcategory-button" data-subcategory="shower-gels">Shower Gels</button>
        <button class="subcategory-button" data-subcategory="liquid-detergents">Liquid Detergents</button>
        <button class="subcategory-button" data-subcategory="bleach">Bleach</button>
        <button class="subcategory-button" data-subcategory="powder-soap">Powder Soap</button>
      </div>
    </div>

    <!-- Add Product Button -->
    <button class="add-product-btn" id="show-add-product-btn">+ Add New Product</button>

    <!-- New Product Form -->
    <form id="new-product-form" method="post" action="" style="display: none;">
      <h2>Add New Product</h2>
      <input type="hidden" name="new_product" value="1">
      <div>
        <label for="product-name">Product Name:</label>
        <input type="text" id="product-name" name="product_name" required>
      </div>
      <div>
        <label for="product-category">Category:</label>
        <select id="product-category" name="product_category" required>
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
      <!-- Hair subcategory for new product -->
      <div id="hair-subcategory-form" style="display: none;">
        <label for="product-subcategory">Hair Subcategory:</label>
        <select id="product-subcategory" name="product_subcategory">
          <option value="braids">Braids</option>
          <option value="general">General</option>
          <option value="extensions">Extensions</option>
          <option value="sewing-threads">Sewing Threads</option>
          <option value="weaves">Weaves</option>
        </select>
      </div>
      <!-- Toiletries subcategory for new product -->
      <div id="toiletries-subcategory-form" style="display: none;">
        <label for="toiletries-subcat-select">Toiletries Subcategory:</label>
        <select id="toiletries-subcat-select" name="product_subcategory">
          <option value="tissues">Tissues</option>
          <option value="sanitary-pads">Sanitary Pads</option>
          <option value="liners">Liners</option>
          <option value="wipes">Wipes</option>
          <option value="hair-removers-shavers">Hair Removers/Shavers</option>
          <option value="shower-gels">Shower Gels</option>
          <option value="liquid-detergents">Liquid Detergents</option>
          <option value="bleach">Bleach</option>
          <option value="powder-soap">Powder Soap</option>
        </select>
      </div>
      <div>
        <label for="product-company">Company/Brand:</label>
        <input type="text" id="product-company" name="product_company" required>
      </div>
      <div>
        <label for="product-stock">Stock Quantity:</label>
        <input type="number" id="product-stock" name="product_stock" min="0" required>
      </div>
      <div>
        <label for="product-price">Price:</label>
        <input type="number" id="product-price" name="product_price" step="0.01" min="0" required>
      </div>
      <div>
        <button type="submit">Add Product</button>
        <button type="button" id="cancel-add-product">Cancel</button>
      </div>
    </form>

    <!-- Edit Product Form -->
    <form id="edit-product-form" method="post" action="" style="display: none;">
      <h2>Edit Product</h2>
      <input type="hidden" name="edit_product" value="1">
      <input type="hidden" name="edit_id" id="edit-id">
      <div>
        <label for="edit-name">Product Name:</label>
        <input type="text" id="edit-name" name="edit_name" required>
      </div>
      <div>
        <label for="edit-category">Category:</label>
        <select id="edit-category" name="edit_category" required>
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
      <!-- Hair subcategory for edit form -->
      <div id="edit-hair-subcategory-form" style="display: none;">
        <label for="edit-subcategory">Hair Subcategory:</label>
        <select id="edit-subcategory" name="edit_subcategory">
          <option value="braids">Braids</option>
          <option value="general">General</option>
          <option value="extensions">Extensions</option>
          <option value="sewing-threads">Sewing Threads</option>
          <option value="weaves">Weaves</option>
        </select>
      </div>
      <!-- Toiletries subcategory for edit form -->
      <div id="edit-toiletries-subcat-form" style="display: none;">
        <label for="edit-toiletries-subcat">Toiletries Subcategory:</label>
        <select id="edit-toiletries-subcat" name="edit_subcategory">
          <option value="tissues">Tissues</option>
          <option value="sanitary-pads">Sanitary Pads</option>
          <option value="liners">Liners</option>
          <option value="wipes">Wipes</option>
          <option value="hair-removers-shavers">Hair Removers/Shavers</option>
          <option value="shower-gels">Shower Gels</option>
          <option value="liquid-detergents">Liquid Detergents</option>
          <option value="bleach">Bleach</option>
          <option value="powder-soap">Powder Soap</option>
        </select>
      </div>
      <div>
        <label for="edit-company">Company/Brand:</label>
        <input type="text" id="edit-company" name="edit_company" required>
      </div>
      <div>
        <label for="edit-stock">Stock Quantity:</label>
        <input type="number" id="edit-stock" name="edit_stock" min="0" required>
      </div>
      <div>
        <label for="edit-price">Price:</label>
        <input type="number" id="edit-price" name="edit_price" step="0.01" min="0" required>
      </div>
      <div>
        <button type="submit">Update Product</button>
        <button type="button" id="cancel-edit-product">Cancel</button>
      </div>
    </form>

    <!-- Hidden Delete Form -->
    <form id="delete-form" method="post" action="" style="display: none;">
      <input type="hidden" name="delete_product" value="1">
      <input type="hidden" name="delete_id" id="delete-id">
    </form>

    <!-- Product Grid -->
    <div class="products-grid" id="products-container">
      <!-- Products will be rendered here by JavaScript -->
    </div>
  </div>

  <script>
    // Products from PHP
    let products = <?php echo $products_json; ?>;

    // Track currently selected category & subcategory
    let selectedCategory = 'all';
    let selectedSubcategory = 'all';

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
            <div class="product-price">ksh${parseFloat(product.price).toFixed(2)}</div>
            <div class="stock-status ${stockStatus}">${stockText}</div>
            <div class="action-buttons">
              <button class="edit-btn" data-id="${product.id}">Edit</button>
              <button class="delete-btn" data-id="${product.id}">Delete</button>
            </div>
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
        const matchesCategorySelect = (categorySelect === '' || prod.category === categorySelect);

        let matchesSubcat = true;
        if (selectedCategory === 'hair') {
          matchesSubcat = (selectedSubcategory === 'all' || prod.subcategory === selectedSubcategory);
        } else if (selectedCategory === 'toiletries') {
          matchesSubcat = (selectedSubcategory === 'all' || prod.subcategory === selectedSubcategory);
        }

        const matchesCompany = prod.company.toLowerCase().includes(companyFilter);

        return matchesSearch && matchesCategoryTab && matchesCategorySelect && matchesSubcat && matchesCompany;
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

        // Show hair subcategories if category=hair
        if (selectedCategory === 'hair') {
          document.getElementById('hair-subcategories').style.display = 'flex';
          document.getElementById('toiletries-subcategories').style.display = 'none';

          selectedSubcategory = 'all';
          document.querySelectorAll('#hair-subcategories .subcategory-button').forEach(b => b.classList.remove('active'));
          document.querySelector('#hair-subcategories .subcategory-button[data-subcategory="all"]').classList.add('active');
        }
        // Show toiletries subcategories if category=toiletries
        else if (selectedCategory === 'toiletries') {
          document.getElementById('hair-subcategories').style.display = 'none';
          document.getElementById('toiletries-subcategories').style.display = 'flex';

          selectedSubcategory = 'all';
          document.querySelectorAll('#toiletries-subcategories .subcategory-button').forEach(b => b.classList.remove('active'));
          document.querySelector('#toiletries-subcategories .subcategory-button[data-subcategory="all"]').classList.add('active');
        }
        // Hide both if it's any other category
        else {
          document.getElementById('hair-subcategories').style.display = 'none';
          document.getElementById('toiletries-subcategories').style.display = 'none';
          selectedSubcategory = 'all';
        }

        filterProducts();
      });
    });

    // HAIR SUBCATEGORY BUTTONS
    const hairSubButtons = document.querySelectorAll('#hair-subcategories .subcategory-button');
    hairSubButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        hairSubButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedSubcategory = btn.dataset.subcategory;
        filterProducts();
      });
    });

    // TOILETRIES SUBCATEGORY BUTTONS
    const toiletriesSubButtons = document.querySelectorAll('#toiletries-subcategories .subcategory-button');
    toiletriesSubButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        toiletriesSubButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedSubcategory = btn.dataset.subcategory;
        filterProducts();
      });
    });

    // "ADD NEW PRODUCT" FORM
    const addProductBtn = document.getElementById('show-add-product-btn');
    const newProductForm = document.getElementById('new-product-form');
    const cancelAddBtn = document.getElementById('cancel-add-product');
    addProductBtn.addEventListener('click', () => {
      const catSelect = document.getElementById('product-category');
      if (selectedCategory !== 'all') {
        catSelect.value = selectedCategory;
      }
      toggleNewSubcat();
      newProductForm.style.display = 'block';
    });
    cancelAddBtn.addEventListener('click', () => {
      newProductForm.style.display = 'none';
    });

    // EDIT PRODUCT FORM
    const editProductForm = document.getElementById('edit-product-form');
    const cancelEditBtn = document.getElementById('cancel-edit-product');
    const editCategorySel = document.getElementById('edit-category');
    cancelEditBtn.addEventListener('click', () => {
      editProductForm.style.display = 'none';
    });
    editCategorySel.addEventListener('change', () => {
      toggleEditSubcat();
    });

    // DELETE FORM
    const deleteForm = document.getElementById('delete-form');

    // GLOBAL CLICK for Edit/Delete
    document.addEventListener('click', e => {
      // DELETE
      if (e.target.classList.contains('delete-btn')) {
        const id = e.target.dataset.id;
        document.getElementById('delete-id').value = id;
        deleteForm.submit();
      }

      // EDIT
      if (e.target.classList.contains('edit-btn')) {
        const id = parseInt(e.target.dataset.id, 10);
        const product = products.find(p => p.id === id);
        if (!product) return;

        document.getElementById('edit-id').value = product.id;
        document.getElementById('edit-name').value = product.name;
        document.getElementById('edit-category').value = product.category;
        document.getElementById('edit-company').value = product.company;
        document.getElementById('edit-stock').value = product.stock;
        document.getElementById('edit-price').value = product.price;

        toggleEditSubcat();
        if (product.category === 'hair' && product.subcategory) {
          document.getElementById('edit-subcategory').value = product.subcategory;
        } else if (product.category === 'toiletries' && product.subcategory) {
          document.getElementById('edit-toiletries-subcat').value = product.subcategory;
        }

        editProductForm.style.display = 'block';
      }
    });

    // Toggle subcategory for NEW
    document.getElementById('product-category').addEventListener('change', toggleNewSubcat);
    function toggleNewSubcat() {
      const cat = document.getElementById('product-category').value;
      const hair = document.getElementById('hair-subcategory-form');
      const toils = document.getElementById('toiletries-subcategory-form');
      if (cat === 'hair') {
        hair.style.display = 'block';
        toils.style.display = 'none';
      } else if (cat === 'toiletries') {
        hair.style.display = 'none';
        toils.style.display = 'block';
      } else {
        hair.style.display = 'none';
        toils.style.display = 'none';
      }
    }

    // Toggle subcategory for EDIT
    function toggleEditSubcat() {
      const cat = document.getElementById('edit-category').value;
      const hair = document.getElementById('edit-hair-subcategory-form');
      const toils = document.getElementById('edit-toiletries-subcat-form');
      if (cat === 'hair') {
        hair.style.display = 'block';
        toils.style.display = 'none';
      } else if (cat === 'toiletries') {
        hair.style.display = 'none';
        toils.style.display = 'block';
      } else {
        hair.style.display = 'none';
        toils.style.display = 'none';
      }
    }
  </script>
</body>

</html>