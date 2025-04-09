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

// Create suppliers table if it doesn't exist
$createSuppliersTable = "CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($createSuppliersTable)) {
    die("Suppliers table creation failed: " . $conn->error);
}

// Handle supplier addition
if (isset($_POST['add_supplier'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $contact_person = $conn->real_escape_string($_POST['contact_person']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);

    $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $contact_person, $phone, $email, $address);
    
    if ($stmt->execute()) {
        $success_message = "Supplier added successfully!";
    } else {
        $error_message = "Error adding supplier: " . $conn->error;
    }
    $stmt->close();
}

// Handle supplier update
if (isset($_POST['update_supplier'])) {
    $id = intval($_POST['id']);
    $name = $conn->real_escape_string($_POST['name']);
    $contact_person = $conn->real_escape_string($_POST['contact_person']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);

    $stmt = $conn->prepare("UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $name, $contact_person, $phone, $email, $address, $id);
    
    if ($stmt->execute()) {
        $success_message = "Supplier updated successfully!";
    } else {
        $error_message = "Error updating supplier: " . $conn->error;
    }
    $stmt->close();
}

// Handle supplier deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $success_message = "Supplier deleted successfully!";
    } else {
        $error_message = "Error deleting supplier: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all suppliers
$suppliers = [];
$result = $conn->query("SELECT * FROM suppliers ORDER BY name");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management</title>
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
            <li><a href="Purchases.php">
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
            <li><a href="supplier.php" class="active">
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
            <h1>Supplier Management</h1>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Add New Supplier Form -->
        <div class="card">
            <h2>Add New Supplier</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Supplier Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="contact_person">Contact Person:</label>
                    <input type="text" id="contact_person" name="contact_person" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email">
                </div>
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address" rows="3"></textarea>
                </div>
                <button type="submit" name="add_supplier" class="btn btn-primary">Add Supplier</button>
            </form>
        </div>

        <!-- Suppliers List -->
        <div class="card">
            <h2>Supplier List</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($suppliers)): ?>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="editSupplier(<?php echo $supplier['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="?delete=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this supplier?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No suppliers found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Supplier Modal -->
        <div id="editSupplierModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Edit Supplier</h2>
                <form method="POST" action="">
                    <input type="hidden" name="id" id="edit_supplier_id">
                    <div class="form-group">
                        <label for="edit_name">Supplier Name:</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_contact_person">Contact Person:</label>
                        <input type="text" id="edit_contact_person" name="contact_person" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone:</label>
                        <input type="tel" id="edit_phone" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email:</label>
                        <input type="email" id="edit_email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="edit_address">Address:</label>
                        <textarea id="edit_address" name="address" rows="3"></textarea>
                    </div>
                    <button type="submit" name="update_supplier" class="btn btn-primary">Update Supplier</button>
                </form>
            </div>
        </div>

        <script>
        function editSupplier(id) {
            // Fetch supplier data via AJAX
            fetch(`get_supplier.php?id=${id}`)
                .then(response => response.json())
                .then(supplier => {
                    document.getElementById('edit_supplier_id').value = supplier.id;
                    document.getElementById('edit_name').value = supplier.name;
                    document.getElementById('edit_contact_person').value = supplier.contact_person;
                    document.getElementById('edit_phone').value = supplier.phone;
                    document.getElementById('edit_email').value = supplier.email;
                    document.getElementById('edit_address').value = supplier.address;
                    document.getElementById('editSupplierModal').style.display = 'block';
                });
        }

        // Close modal when clicking the X
        document.querySelector('.close').addEventListener('click', function() {
            document.getElementById('editSupplierModal').style.display = 'none';
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('editSupplierModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        </script>
    </div>

    <script src="Staff.js"></script>
</body>

</html>