<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// -----------------
// DB Connection
// -----------------
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop"; // Adjust if needed

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ------------------------------
// Ensure required columns exist in the customers table
// ------------------------------
$cols = $conn->query("SHOW COLUMNS FROM customers LIKE 'debt_amount'");
if ($cols->num_rows == 0) {
    $alterDebt = "ALTER TABLE customers ADD COLUMN debt_amount DECIMAL(10,2) NOT NULL DEFAULT 0";
    if (!$conn->query($alterDebt)) {
        die("Error adding debt_amount column: " . $conn->error);
    }
}

$cols = $conn->query("SHOW COLUMNS FROM customers LIKE 'debt_settled'");
if ($cols->num_rows == 0) {
    $alterSettled = "ALTER TABLE customers ADD COLUMN debt_settled TINYINT(1) NOT NULL DEFAULT 1";
    if (!$conn->query($alterSettled)) {
        die("Error adding debt_settled column: " . $conn->error);
    }
}

$cols = $conn->query("SHOW COLUMNS FROM customers LIKE 'last_payment_date'");
if ($cols->num_rows == 0) {
    $alterPayment = "ALTER TABLE customers ADD COLUMN last_payment_date DATETIME DEFAULT NULL";
    if (!$conn->query($alterPayment)) {
        die("Error adding last_payment_date column: " . $conn->error);
    }
}

// ------------------------------
// Handle debt settlement
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_debt'])) {
    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_SANITIZE_NUMBER_INT);
    $payment_amount = filter_input(INPUT_POST, 'payment_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    if ($customer_id && $payment_amount) {
        // Deduct the payment from the debt.
        // The query uses GREATEST() to avoid negative debt and sets debt_settled = 1 if fully paid.
        $stmt = $conn->prepare("UPDATE customers SET 
                              debt_amount = GREATEST(0, debt_amount - ?),
                              last_payment_date = CURRENT_TIMESTAMP,
                              debt_settled = CASE WHEN (debt_amount - ?) <= 0 THEN 1 ELSE 0 END
                              WHERE id = ?");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ddi", $payment_amount, $payment_amount, $customer_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Payment recorded successfully!";
        } else {
            $_SESSION['error_message'] = "Error recording payment!";
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ------------------------------
// Process Adding a New Customer
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $initial_debt = filter_input(INPUT_POST, 'debt_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    if ($initial_debt === false || $initial_debt === null) {
        $initial_debt = 0;
    }
    // If there's a debt, mark it as pending (0); if 0, mark as settled (1)
    $debt_settled = ($initial_debt > 0) ? 0 : 1;

    if ($name && $phone && $email) {
        $stmt = $conn->prepare("INSERT INTO customers (name, phone, email, debt_amount, debt_settled) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("sssdi", $name, $phone, $email, $initial_debt, $debt_settled);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Customer added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding customer!";
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['error_message'] = "Please fill in all fields for the new customer.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ------------------------------
// Fetch customers with debts
// ------------------------------
$sql = "SELECT id, name, phone, email, debt_amount, last_payment_date, debt_settled 
        FROM customers 
        WHERE debt_amount > 0 OR debt_settled = 0 
        ORDER BY debt_settled ASC, debt_amount DESC";
$result = $conn->query($sql);
if (!$result) {
    die("Query error: " . $conn->error);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Debt Management - Beauty Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff6b81;
            --secondary-color: #f9f9f9;
            --accent-color: #ff4757;
            --text-color: #333;
            --border-color: #ddd;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar fixed to the left */
        .sidebar {
            position: fixed;
            /* Stays in place when scrolling */
            left: 0;
            top: 0;
            width: 180px;
            /* Adjust as needed */
            height: 100vh;
            /* Full viewport height */
            background-color: #fff;
            border-right: 1px solid var(--border-color);
            padding: 20px;
            overflow-y: auto;
            /* Scroll if content is tall */
        }

        /* Sidebar logo */
        .sidebar .logo {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.2rem;
            font-weight: bold;
        }

        /* Sidebar nav */
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li {
            margin-bottom: 10px;
        }

        .sidebar ul li a {
            color: var(--text-color);
            text-decoration: none;
            display: block;
            padding: 8px 12px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .sidebar ul li a:hover {
            background-color: var(--primary-color);
            color: #fff;
        }

        .sidebar ul li.active a {
            background-color: var(--primary-color);
            color: #fff;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            font-weight: 600;
        }

        .badge-debt {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .badge-settled {
            background-color: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .btn-settle {
            padding: 5px 15px;
            border-radius: 20px;
        }

        .debt-amount {
            font-weight: bold;
            color: #dc3545;
        }

        .settled-amount {
            font-weight: bold;
            color: var(--primary);
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h4>Beauty Shop</h4>
        </div>
        <ul>
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="products.php"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="sales.php"><i class="fas fa-dollar-sign"></i> Sales</a></li>
            <li><a href="purchases.php"><i class="fas fa-shopping-cart"></i> Purchases</a></li>
            <li class="active"><a href="customers.php"><i class="fas fa-users"></i> Customers</a></li>
            <li><a href="suppliers.php"><i class="fas fa-truck"></i> Suppliers</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="myaccount.php"><i class="fas fa-user-cog"></i>My account</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Customer Debt Management</h1>
                <!-- The Add New Customer button opens the modal -->
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="fas fa-plus"></i> Add New Customer
                </button>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Customers with Outstanding Debts</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Contact</th>
                                    <th>Debt Amount</th>
                                    <th>Last Payment</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($row['phone']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                            </td>
                                            <td
                                                class="<?php echo ($row['debt_amount'] > 0) ? 'debt-amount' : 'settled-amount'; ?>">
                                                KSH <?php echo number_format($row['debt_amount'], 2); ?>
                                            </td>
                                            <td>
                                                <?php echo $row['last_payment_date'] ? date('M d, Y', strtotime($row['last_payment_date'])) : 'No payments'; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['debt_amount'] > 0): ?>
                                                    <span class="badge badge-debt">Pending</span>
                                                <?php else: ?>
                                                    <span class="badge badge-settled">Settled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['debt_amount'] > 0): ?>
                                                    <button class="btn btn-primary btn-settle" data-bs-toggle="modal"
                                                        data-bs-target="#settleDebtModal"
                                                        data-customer-id="<?php echo $row['id']; ?>"
                                                        data-debt-amount="<?php echo $row['debt_amount']; ?>">
                                                        <i class="fas fa-money-bill-wave"></i> Settle
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No customers with outstanding debts</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCustomerModalLabel">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="add_customer" value="1">
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Customer Name</label>
                            <input type="text" name="name" id="customer_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_phone" class="form-label">Phone</label>
                            <input type="text" name="phone" id="customer_phone" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_email" class="form-label">Email</label>
                            <input type="email" name="email" id="customer_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="customer_debt" class="form-label">Initial Debt Amount (KSH)</label>
                            <input type="number" step="0.01" name="debt_amount" id="customer_debt" class="form-control"
                                value="0" required>
                            <div class="form-text">Enter the debt amount if applicable; otherwise, leave as 0.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Settle Debt Modal -->
    <div class="modal fade" id="settleDebtModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Settle Customer Debt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" id="modal-customer-id">
                        <div class="mb-3">
                            <label for="payment_amount" class="form-label">Payment Amount (KSH)</label>
                            <input type="number" step="0.01" class="form-control" id="payment_amount"
                                name="payment_amount" required>
                            <div class="form-text">Outstanding debt: KSH <span id="modal-debt-amount"></span></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="settle_debt" class="btn btn-primary">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle debt settlement modal
        document.addEventListener('DOMContentLoaded', function () {
            const settleDebtModal = document.getElementById('settleDebtModal');
            if (settleDebtModal) {
                settleDebtModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const customerId = button.getAttribute('data-customer-id');
                    const debtAmount = button.getAttribute('data-debt-amount');

                    document.getElementById('modal-customer-id').value = customerId;
                    document.getElementById('modal-debt-amount').textContent = parseFloat(debtAmount).toFixed(2);
                    document.getElementById('payment_amount').max = debtAmount;
                });
            }
        });
    </script>
</body>

</html>