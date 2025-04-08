<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "beautyshop");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission for adding a customer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['addCustomer'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $status = $conn->real_escape_string($_POST['status']);
    $due_amount = !empty($_POST['due_amount']) ? floatval($_POST['due_amount']) : 0.00;

    // Handle Image Upload
    $image = "default.jpg"; // Default image
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "customers_images/";
        $imageFileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $imageFileName;
        move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath);
        $image = $imageFileName;
    }

    $sql = "INSERT INTO customers (name, email, phone, status, due_amount, image) 
            VALUES ('$name', '$email', '$phone', '$status', '$due_amount', '$image')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>window.location='customers.php';</script>";
        exit;
    } else {
        echo "Error: " . $conn->error;
    }
}

// Fetch customers
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

$sql = "SELECT * FROM customers WHERE name LIKE '%$search%'";
if (!empty($filter)) {
    $sql .= " AND status = '$filter'";
}

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beauty Shop - Customers</title>
    <link rel="stylesheet" href="customers.css">
    <script>
        function openAddModal() {
            document.getElementById("addCustomerModal").style.display = "flex";
        }

        function closeAddModal() {
            document.getElementById("addCustomerModal").style.display = "none";
        }

        function applyFilter() {
            let search = document.getElementById("search").value;
            let filter = document.getElementById("filter").value;
            window.location.href = `customers.php?search=${search}&filter=${filter}`;
        }
    </script>
    <style>
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 20px;
            width: 400px;
            border-radius: 5px;
            text-align: center;
            position: relative;
        }

        .modal-content h2 {
        text-align: center;
        margin-bottom: 15px;
        color: #333;
    }

    .modal-content input,
    .modal-content select {
        width: 100%;
        padding: 10px;
        margin-bottom: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .file-upload-container {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
    }

    .file-upload-label {
        background-color: #28a745;
        color: white;
        padding: 8px 12px;
        border-radius: 5px;
        cursor: pointer;
        display: inline-block;
        text-align: center;
        font-weight: bold;
    }

    .file-upload-container input {
        display: none;
    }

    .modal-content .btn-container {
        text-align: center;
        margin-top: 15px;
    }

    .modal-content .add-customer-btn {
        background-color: #28a745;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        width: 100%;
    }
        .close-btn {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            color: red;
            font-size: 20px;
        }
        .btn {
            padding: 10px 15px;
            margin-top: 10px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-radius: 5px;
        }
        .btn-primary {
            background-color: #28a745;
            color: white;
        }
        .btn-secondary {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>abuelo jua code</h2>
    <ul>
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="purchases.php">Purchases</a></li>
        <li><a href="sales.php">Sales</a></li>
        <li><a class="active" href="customers.php">Customers</a></li>
        <li><a href="supplier.php">Suppliers</a></li>
        <li><a href="reports.php">Reports</a></li>
        <li><a href="settings.php">Settings</a></li>
    </ul>
</div>

<div class="main-content">
    <h1>Beauty Shop - Customers</h1>

    <div class="search-filter">
        <input type="text" placeholder="Search customers..." id="search" value="<?= $search ?>">
        <select id="filter">
            <option value="">All Status</option>
            <option value="Active" <?= ($filter == 'Active') ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= ($filter == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
            <option value="VIP" <?= ($filter == 'VIP') ? 'selected' : '' ?>>VIP</option>
        </select>
        <button onclick="applyFilter()">Search</button>
    </div>

    <button class="btn btn-primary" onclick="openAddModal()">+ Add New Customer</button>

    <div class="customers-grid">
        <?php while ($row = $result->fetch_assoc()) { ?>
            <div class="customer-card">
                <img src="customers_images/<?= !empty($row['image']) ? $row['image'] : '' ?>" alt="<?= $row['name'] ?>" width="100">
                <h3><?= $row['name'] ?></h3>
                <p>Email: <?= $row['email'] ?></p>
                <p>Phone: <?= $row['phone'] ?></p>
                <p>Due Amount: $<?= number_format($row['due_amount'], 2) ?></p>
                <span class="status <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span>
            </div>
        <?php } ?>
    </div>
</div>

<!-- Add Customer Modal -->
<div id="addCustomerModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeAddModal()">&times;</span>
        <h2>Add New Customer</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" >
            <input type="text" name="phone" placeholder="Phone" required>
            <select name="status">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="VIP">VIP</option>
            </select>
            <input type="number" name="due_amount" placeholder="Due Amount ($)">
            
            <input type="file" class="file-upload-btn" name="image">
            <button type="submit" class="btn btn-primary" name="addCustomer">Add Customer</button>
        </form>
    </div>
</div>

</body>
</html>

<?php $conn->close(); ?>
