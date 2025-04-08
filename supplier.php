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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers - Beauty Shop</title>
    <style>
        /* If you already have these variables in product.css, 
           you can remove this :root block here to avoid duplication. */
        :root {
            --primary-color: #ff6b81;
            /* Pinkish */
            --secondary-color: #f9f9f9;
            /* Light gray background */
            --accent-color: #ff4757;
            /* A stronger pink/red accent */
            --text-color: #333;
            /* Dark text */
            --border-color: #ddd;
            /* Light gray for borders */
        }

        /* General reset / base styles */
        body {
            display: flex;
            margin: 0;
            font-family: 'Arial', sans-serif;
            background-color: var(--secondary-color);
            /* match product.css background */
            color: var(--text-color);
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--primary-color);
            /* unify with product.css primary color */
            color: #fff;
            padding: 20px;
            height: 100vh;
            box-sizing: border-box;
        }

        .sidebar h2.logo {
            text-align: center;
            font-size: 22px;
            margin-bottom: 30px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li {
            margin: 20px 0;
        }

        .sidebar ul li a {
            color: #fff;
            text-decoration: none;
            display: block;
            padding: 8px 0;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        /* Highlight the active menu item or hover */
        .sidebar ul li.active a,
        .sidebar ul li a:hover {
            background-color: var(--accent-color);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
        }

        .container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: var(--text-color);
            margin-bottom: 20px;
        }

        .supplier-list {
            list-style-type: none;
            padding: 0;
        }

        .supplier-list li {
            background-color: var(--secondary-color);
            margin: 10px 0;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            transition: box-shadow 0.3s;
        }

        .supplier-list li:hover {
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .supplier-list li h3 {
            margin: 0;
            color: var(--text-color);
        }

        .supplier-list li p {
            margin: 5px 0;
            color: #777;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group button {
            padding: 10px 15px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .form-group button:hover {
            background-color: #ff6b81;
            /* Slightly lighter accent color */
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2 class="logo">Beauty Shop</h2>
        <ul>
            <li><a href="#">Products</a></li>
            <li><a href="#">Sales</a></li>
            <li><a href="purchases.php"><i class="fas fa-shopping-cart"></i> Purchases</a></li>
            <li><a href="#">Customers</a></li>
            <li class="active"><a href="#">Suppliers</a></li>
            <li><a href="#">Reports</a></li>
            <li><a href="#">My Account</a></li>
            <li><a href="#">Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <h1>Suppliers</h1>

            <!-- Form to add new supplier -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Supplier Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="contact_name">Contact Name:</label>
                    <input type="text" id="contact_name" name="contact_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="text" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="product">Product:</label>
                    <input type="text" id="product" name="product" required>
                </div>
                <div class="form-group">
                    <button type="submit">Add Supplier</button>
                </div>
            </form>

            <!-- Display suppliers -->
            <ul class="supplier-list">
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<li>
                                <h3>" . $row["name"] . "</h3>
                                <p>Contact: " . $row["contact_name"] . "</p>
                                <p>Email: " . $row["email"] . "</p>
                                <p>Phone: " . $row["phone"] . "</p>
                                <p>Product: " . $row["product"] . "</p>
                              </li>";
                    }
                } else {
                    echo "<li>No suppliers found</li>";
                }
                ?>
            </ul>
        </div>
    </div>
</body>

</html>

<?php
$conn->close();
?>