<?php
require_once 'includes/db_setup.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_food_item'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $category = $_POST['category'];
        $unit_price = $_POST['unit_price'];
        $quantity = $_POST['quantity'];
        $unit = $_POST['unit'];
        $min_stock_level = $_POST['min_stock_level'];

        try {
            $pdo->beginTransaction();

            // Insert food item
            $stmt = $pdo->prepare("INSERT INTO food_items (name, description, category, unit_price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $category, $unit_price]);
            $food_item_id = $pdo->lastInsertId();

            // Insert stock information
            $stmt = $pdo->prepare("INSERT INTO stock_inventory (food_item_id, quantity, unit, min_stock_level) VALUES (?, ?, ?, ?)");
            $stmt->execute([$food_item_id, $quantity, $unit, $min_stock_level]);

            $pdo->commit();
            $success_message = "Food item added successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get all food items with stock information
$stmt = $pdo->query("
    SELECT f.*, s.quantity, s.unit, s.min_stock_level 
    FROM food_items f 
    LEFT JOIN stock_inventory s ON f.id = s.food_item_id 
    ORDER BY f.name
");
$food_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Items Management</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Food Items Management</h1>

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
                    <input type="text" id="category" name="category" required>
                </div>
                <div class="form-group">
                    <label for="unit_price">Unit Price:</label>
                    <input type="number" id="unit_price" name="unit_price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="quantity">Initial Quantity:</label>
                    <input type="number" id="quantity" name="quantity" required>
                </div>
                <div class="form-group">
                    <label for="unit">Unit:</label>
                    <input type="text" id="unit" name="unit" required>
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
                        <th>Min Stock Level</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($food_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td><?php echo number_format($item['unit_price'], 2); ?></td>
                            <td><?php echo $item['quantity'] . ' ' . $item['unit']; ?></td>
                            <td><?php echo $item['min_stock_level']; ?></td>
                            <td>
                                <?php
                                if ($item['quantity'] <= 0) {
                                    echo '<span class="badge badge-danger">Out of Stock</span>';
                                } elseif ($item['quantity'] <= $item['min_stock_level']) {
                                    echo '<span class="badge badge-warning">Low Stock</span>';
                                } else {
                                    echo '<span class="badge badge-success">In Stock</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="edit_food_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                <a href="update_stock.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">Update Stock</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 