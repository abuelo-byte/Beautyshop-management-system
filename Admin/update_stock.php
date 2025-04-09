<?php
require_once 'includes/db_setup.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$food_item_id = $_GET['id'] ?? null;

if (!$food_item_id) {
    header("Location: food_items.php");
    exit();
}

// Get food item details
$stmt = $pdo->prepare("
    SELECT f.*, s.quantity, s.unit, s.min_stock_level 
    FROM food_items f 
    LEFT JOIN stock_inventory s ON f.id = s.food_item_id 
    WHERE f.id = ?
");
$stmt->execute([$food_item_id]);
$food_item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$food_item) {
    header("Location: food_items.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_stock'])) {
        $quantity = $_POST['quantity'];
        $operation = $_POST['operation']; // 'add' or 'subtract'
        
        try {
            $pdo->beginTransaction();
            
            if ($operation === 'add') {
                $new_quantity = $food_item['quantity'] + $quantity;
            } else {
                $new_quantity = $food_item['quantity'] - $quantity;
                if ($new_quantity < 0) {
                    throw new Exception("Cannot have negative stock quantity");
                }
            }
            
            // Update stock
            $stmt = $pdo->prepare("UPDATE stock_inventory SET quantity = ? WHERE food_item_id = ?");
            $stmt->execute([$new_quantity, $food_item_id]);
            
            // Check for low stock or out of stock
            if ($new_quantity <= 0) {
                $alert_type = 'out_of_stock';
            } elseif ($new_quantity <= $food_item['min_stock_level']) {
                $alert_type = 'low_stock';
            }
            
            if (isset($alert_type)) {
                $stmt = $pdo->prepare("INSERT INTO stock_alerts (food_item_id, alert_type) VALUES (?, ?)");
                $stmt->execute([$food_item_id, $alert_type]);
            }
            
            $pdo->commit();
            $success_message = "Stock updated successfully!";
            
            // Refresh food item data
            $stmt = $pdo->prepare("
                SELECT f.*, s.quantity, s.unit, s.min_stock_level 
                FROM food_items f 
                LEFT JOIN stock_inventory s ON f.id = s.food_item_id 
                WHERE f.id = ?
            ");
            $stmt->execute([$food_item_id]);
            $food_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Stock - <?php echo htmlspecialchars($food_item['name']); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Update Stock - <?php echo htmlspecialchars($food_item['name']); ?></h1>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Current Stock Information</h2>
            <div class="stock-info">
                <p><strong>Current Quantity:</strong> <?php echo $food_item['quantity'] . ' ' . $food_item['unit']; ?></p>
                <p><strong>Minimum Stock Level:</strong> <?php echo $food_item['min_stock_level']; ?></p>
                <p><strong>Status:</strong> 
                    <?php
                    if ($food_item['quantity'] <= 0) {
                        echo '<span class="badge badge-danger">Out of Stock</span>';
                    } elseif ($food_item['quantity'] <= $food_item['min_stock_level']) {
                        echo '<span class="badge badge-warning">Low Stock</span>';
                    } else {
                        echo '<span class="badge badge-success">In Stock</span>';
                    }
                    ?>
                </p>
            </div>

            <h2>Update Stock</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="operation">Operation:</label>
                    <select id="operation" name="operation" required>
                        <option value="add">Add Stock</option>
                        <option value="subtract">Subtract Stock</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input type="number" id="quantity" name="quantity" min="1" required>
                </div>
                <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
            </form>
        </div>

        <div class="card">
            <h2>Stock History</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Operation</th>
                        <th>Quantity</th>
                        <th>New Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT * FROM stock_history 
                        WHERE food_item_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([$food_item_id]);
                    while ($history = $stmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($history['created_at'])); ?></td>
                            <td><?php echo ucfirst($history['operation']); ?></td>
                            <td><?php echo $history['quantity']; ?></td>
                            <td><?php echo $history['new_total']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 