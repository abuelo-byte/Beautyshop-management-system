<?php
// Database connection
$servername = "localhost";
$username = "root"; // Default username for localhost
$password = ""; // Default password for localhost
$dbname = "beautyshop";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if all required fields are set
    if (isset($_POST['name'], $_POST['contact_name'], $_POST['email'], $_POST['phone'], $_POST['product'])) {
        $name = $_POST['name'];
        $contact_name = $_POST['contact_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $product = $_POST['product'];

        // Insert into database
        $sql = "INSERT INTO suppliers (name, contact_name, email, phone, product) VALUES ('$name', '$contact_name', '$email', '$phone', '$product')";

        if ($conn->query($sql) === TRUE) {
            // Redirect to the same page to prevent form resubmission
            header("Location: suppliers.php");
            exit(); // Ensure no further code is executed after the redirect
        } else {
            echo "<script>alert('Error: " . $sql . "<br>" . $conn->error . "');</script>";
        }
    } else {
        echo "<script>alert('All fields are required');</script>";
    }
}

// Fetch suppliers from the database
$sql = "SELECT * FROM suppliers";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suppliers - Beauty Shop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff6b81;
            --secondary-color: #f9f9f9;
            --accent-color: #ff4757;
            --text-color: #333;
            --border-color: #ddd;
            --container-width: 1000px;
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
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            text-align: center;
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

        .main-content {
            margin-left: 180px;
            padding: 40px 20px;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .container {
            background: #fff;
            width: 100%;
            max-width: var(--container-width);
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

        form .form-group {
            margin-bottom: 15px;
        }

        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        form input {
            width: 100%;
            padding: 10px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 5px;
        }

        form button {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--accent-color);
            color: #fff;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background .3s;
        }

        form button:hover {
            background: var(--primary-color);
        }

        .supplier-list {
            list-style: none;
            margin-top: 30px;
        }

        .supplier-list li {
            background: var(--secondary-color);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--accent-color);
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 15px;
            transition: box-shadow .3s;
        }

        .supplier-list li:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .supplier-list h3 {
            font-size: 1.2rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .supplier-list h3 i {
            color: var(--accent-color);
        }

        .supplier-list p {
            margin: 4px 0;
            color: #555;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2 class="logo"><i class="fas fa-store"></i> Beauty Shop</h2>
        <ul>
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="sales.php"><i class="fas fa-chart-line"></i> Sales</a></li>
            <li><a href="purchases.php"><i class="fas fa-shopping-cart"></i> Purchases</a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> Customers</a></li>
            <li class="active"><a href="#"><i class="fas fa-truck"></i> Suppliers</a></li>
            <li><a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
            <li><a href="myaccount.php"><i class="fas fa-user-cog"></i> My Account</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <h1><i class="fas fa-truck-loading"></i> Suppliers</h1>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name"><i class="fas fa-industry"></i> Supplier Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="contact_name"><i class="fas fa-user"></i> Contact Name</label>
                    <input type="text" id="contact_name" name="contact_name" required>
                </div>
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone"><i class="fas fa-phone"></i> Phone</label>
                    <input type="text" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="product"><i class="fas fa-box-open"></i> Product</label>
                    <input type="text" id="product" name="product" required>
                </div>
                <div class="form-group">
                    <button type="submit"><i class="fas fa-plus-circle"></i> Add Supplier</button>
                </div>
            </form>

            <ul class="supplier-list">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <li>
                            <h3><i class="fas fa-industry"></i> <?php echo htmlspecialchars($row['name']); ?></h3>
                            <p><i class="fas fa-user"></i> Contact: <?php echo htmlspecialchars($row['contact_name']); ?></p>
                            <p><i class="fas fa-envelope"></i> Email: <?php echo htmlspecialchars($row['email']); ?></p>
                            <p><i class="fas fa-phone"></i> Phone: <?php echo htmlspecialchars($row['phone']); ?></p>
                            <p><i class="fas fa-box-open"></i> Product: <?php echo htmlspecialchars($row['product']); ?></p>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li>No suppliers found</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</body>

</html>
<?php
$conn->close();
?>