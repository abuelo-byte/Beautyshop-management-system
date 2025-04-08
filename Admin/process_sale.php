<?php
// 1) Connect to DB
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop"; // Adjust if needed

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2) Check for POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve posted data
    $cartData = $_POST['cartData'] ?? '';
    $total = $_POST['total'] ?? 0;

    // Validate
    if (empty($cartData) || !is_numeric($total)) {
        echo "Invalid data";
        exit;
    }

    // 3) Insert into 'sales' table
    $saleDate = date('Y-m-d H:i:s'); // current datetime
    $stmt = $conn->prepare("INSERT INTO sales (sale_date, total, cart_data) VALUES (?, ?, ?)");
    $stmt->bind_param("sds", $saleDate, $total, $cartData);

    if ($stmt->execute()) {
        echo "Success";
    } else {
        echo "DB Error: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "No POST data received";
}

$conn->close();
