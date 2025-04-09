<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafeteria-management-system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create necessary tables if they don't exist
$createTables = [
    "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS loans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        interest_rate DECIMAL(5,2) DEFAULT 0,
        start_date DATE NOT NULL,
        due_date DATE NOT NULL,
        status ENUM('active', 'paid', 'overdue') DEFAULT 'active',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    )",
    "CREATE TABLE IF NOT EXISTS loan_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_date DATE NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (loan_id) REFERENCES loans(id)
    )"
];

foreach ($createTables as $sql) {
    if (!$conn->query($sql)) {
        die("Table creation failed: " . $conn->error);
    }
}

// Handle new customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);

    $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $phone, $address);
    
    if ($stmt->execute()) {
        $success_message = "Customer added successfully!";
    } else {
        $error_message = "Error adding customer: " . $stmt->error;
    }
}

// Handle new loan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_loan'])) {
    $customerId = intval($_POST['customer_id']);
    $amount = floatval($_POST['amount']);
    $interestRate = floatval($_POST['interest_rate']);
    $startDate = $conn->real_escape_string($_POST['start_date']);
    $dueDate = $conn->real_escape_string($_POST['due_date']);
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');

    // Check if customer already has an active loan
    $checkLoan = $conn->prepare("SELECT id FROM loans WHERE customer_id = ? AND status = 'active'");
    $checkLoan->bind_param("i", $customerId);
    $checkLoan->execute();
    $result = $checkLoan->get_result();
    
    if ($result->num_rows > 0) {
        $error_message = "Customer already has an active loan. Please clear the existing loan first.";
    } else {
        $stmt = $conn->prepare("INSERT INTO loans (customer_id, amount, interest_rate, start_date, due_date, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iddsss", $customerId, $amount, $interestRate, $startDate, $dueDate, $notes);
        
        if ($stmt->execute()) {
            $success_message = "Loan added successfully!";
        } else {
            $error_message = "Error adding loan: " . $stmt->error;
        }
    }
}

// Handle loan payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $loanId = intval($_POST['loan_id']);
    $amount = floatval($_POST['payment_amount']);
    $paymentDate = $conn->real_escape_string($_POST['payment_date']);
    $paymentMethod = $conn->real_escape_string($_POST['payment_method']);
    $notes = $conn->real_escape_string($_POST['payment_notes'] ?? '');

    // Start transaction
    $conn->begin_transaction();

    try {
        // Get current loan details
        $loanQuery = $conn->prepare("SELECT l.*, 
            (SELECT COALESCE(SUM(amount), 0) FROM loan_payments WHERE loan_id = l.id) as total_payments 
            FROM loans l WHERE l.id = ?");
        $loanQuery->bind_param("i", $loanId);
        $loanQuery->execute();
        $loanResult = $loanQuery->get_result();
        $loan = $loanResult->fetch_assoc();

        if (!$loan) {
            throw new Exception("Loan not found");
        }

        // Calculate remaining amount
        $remainingAmount = $loan['amount'] - $loan['total_payments'];

        // Check if payment exceeds remaining amount
        if ($amount > $remainingAmount) {
            throw new Exception("Payment amount (Ksh " . number_format($amount, 2) . ") exceeds remaining loan amount (Ksh " . number_format($remainingAmount, 2) . ")");
        }

        // Add payment
        $stmt = $conn->prepare("INSERT INTO loan_payments (loan_id, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idsss", $loanId, $amount, $paymentDate, $paymentMethod, $notes);
        $stmt->execute();

        // Update loan status
        $newTotalPayments = $loan['total_payments'] + $amount;
        $newStatus = ($newTotalPayments >= $loan['amount']) ? 'paid' : 'active';
        
        $updateStatus = $conn->prepare("UPDATE loans SET status = ? WHERE id = ?");
        $updateStatus->bind_param("si", $newStatus, $loanId);
        $updateStatus->execute();

        $conn->commit();
        $success_message = "Payment of Ksh " . number_format($amount, 2) . " added successfully! Remaining amount: Ksh " . number_format(($remainingAmount - $amount), 2);
        
        // Redirect to refresh the page and update the table
        header("Location: customers.php?success=" . urlencode($success_message));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error adding payment: " . $e->getMessage();
    }
}

// Display success message from URL parameter
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// Fetch customers
$customers = [];
$customersQuery = "SELECT * FROM customers ORDER BY name";
$customersResult = $conn->query($customersQuery);
if ($customersResult && $customersResult->num_rows > 0) {
    while ($row = $customersResult->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Fetch loans with customer information and payment details
$loans = [];
$loansQuery = "SELECT l.*, c.name as customer_name,
               (SELECT COALESCE(SUM(amount), 0) FROM loan_payments WHERE loan_id = l.id) as total_payments
               FROM loans l 
               JOIN customers c ON l.customer_id = c.id 
               ORDER BY l.status, l.due_date";
$loansResult = $conn->query($loansQuery);
if ($loansResult && $loansResult->num_rows > 0) {
    while ($row = $loansResult->fetch_assoc()) {
        // Fetch payments for each loan
        $paymentsQuery = "SELECT * FROM loan_payments WHERE loan_id = " . $row['id'] . " ORDER BY payment_date";
        $paymentsResult = $conn->query($paymentsQuery);
        $row['payments'] = [];
        if ($paymentsResult && $paymentsResult->num_rows > 0) {
            while ($payment = $paymentsResult->fetch_assoc()) {
                $row['payments'][] = $payment;
            }
        }
        $row['remaining_amount'] = $row['amount'] - $row['total_payments'];
        $loans[] = $row;
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management</title>
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
            <li><a href="#" class="active">
                    <i class="fas fa-users"></i>
                    <span class="link-text">Customers</span>
                </a></li>
            <li><a href="supplier.php">
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
            <h1>Customer Management</h1>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- New Customer Form -->
        <div class="card">
            <h2>Add New Customer</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email">
                </div>
                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone">
                </div>
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address" rows="3"></textarea>
                </div>
                <button type="submit" name="add_customer" class="btn btn-primary">Add Customer</button>
            </form>
        </div>

        <!-- New Loan Form -->
        <div class="card">
            <h2>Add New Loan</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="customer_id">Customer:</label>
                    <select id="customer_id" name="customer_id" required>
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>">
                                <?php echo htmlspecialchars($customer['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="amount">Loan Amount:</label>
                    <input type="number" id="amount" name="amount" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="interest_rate">Interest Rate (%):</label>
                    <input type="number" id="interest_rate" name="interest_rate" min="0" max="100" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" required>
                </div>
                <div class="form-group">
                    <label for="due_date">Due Date:</label>
                    <input type="date" id="due_date" name="due_date" required>
                </div>
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                <button type="submit" name="add_loan" class="btn btn-primary">Add Loan</button>
            </form>
        </div>

        <!-- Active Loans List -->
        <div class="card">
            <h2>Active Loans</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Remaining</th>
                            <th>Interest Rate</th>
                            <th>Start Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($loans)): ?>
                            <?php foreach ($loans as $loan): ?>
                                <?php if ($loan['status'] !== 'paid'): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($loan['customer_name']); ?></td>
                                        <td>Ksh <?php echo number_format($loan['amount'], 2); ?></td>
                                        <td>Ksh <?php echo number_format($loan['total_payments'], 2); ?></td>
                                        <td>Ksh <?php echo number_format($loan['amount'] - $loan['total_payments'], 2); ?></td>
                                        <td><?php echo $loan['interest_rate']; ?>%</td>
                                        <td><?php echo date('Y-m-d', strtotime($loan['start_date'])); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($loan['due_date'])); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $loan['status'] === 'active' ? 'badge-success' : 
                                                    ($loan['status'] === 'overdue' ? 'badge-danger' : 'badge-warning'); 
                                            ?>">
                                                <?php echo ucfirst($loan['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewLoanDetails(<?php echo $loan['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-payment" onclick="showAddPaymentModal(<?php echo $loan['id']; ?>)">
                                                <i class="fas fa-money-bill-wave"></i> Add Payment
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center;">No active loans found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Payment Modal -->
        <div id="addPaymentModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Add Loan Payment</h2>
                <form method="POST" action="">
                    <input type="hidden" name="add_payment" value="1">
                    <input type="hidden" name="loan_id" id="modal_loan_id">
                    <div class="form-group">
                        <label for="payment_amount">Payment Amount (Ksh)</label>
                        <input type="number" name="payment_amount" id="payment_amount" step="0.01" min="0" required>
                        <small id="remaining_amount_display"></small>
                    </div>
                    <div class="form-group">
                        <label for="payment_date">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_notes">Notes</label>
                        <textarea name="payment_notes" id="payment_notes" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-payment">Add Payment</button>
                </form>
            </div>
        </div>

        <!-- Customers List -->
        <div class="card">
            <h2>Customers</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['address']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewCustomerLoans(<?php echo $customer['id']; ?>)">
                                            <i class="fas fa-file-invoice-dollar"></i> View Loans
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No customers found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SIDEBAR TOGGLE SCRIPT -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('toggleSidebar');
            const toggleIcon = document.getElementById('toggleIcon');

            toggleBtn.addEventListener('click', function () {
                sidebar.classList.toggle('sidebar-collapsed');

                if (sidebar.classList.contains('sidebar-collapsed')) {
                    toggleIcon.classList.replace('fa-chevron-left', 'fa-chevron-right');
                } else {
                    toggleIcon.classList.replace('fa-chevron-right', 'fa-chevron-left');
                }
            });

            // For mobile responsiveness
            if (window.innerWidth <= 768) {
                sidebar.classList.add('sidebar-collapsed');
                toggleIcon.classList.replace('fa-chevron-left', 'fa-chevron-right');
            }
        });
    </script>

    <!-- View Customer Loans Modal -->
    <div id="customerLoansModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Customer Loans</h2>
            <div id="customerLoansDetails">
                <!-- Customer loans will be loaded here -->
            </div>
        </div>
    </div>

    <!-- View Loan Details Modal -->
    <div id="loanDetailsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Loan Details</h2>
            <div id="loanDetails">
                <!-- Loan details will be loaded here -->
            </div>
        </div>
    </div>

    <script src="js/loan-modal.js"></script>

    <style>
        /* Add Payment Button Style */
        .btn-payment {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-payment:hover {
            background-color: #218838;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-payment i {
            font-size: 14px;
        }

        .btn-payment.btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 50%;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }
    </style>

    <script>
        // Store loans data for modals
        const loans = <?php echo json_encode($loans); ?>;

        function viewCustomerLoans(customerId) {
            const customerLoans = loans.filter(loan => loan.customer_id === customerId);
            let loansHtml = '<table class="data-table">';
            loansHtml += '<thead><tr><th>Amount</th><th>Paid</th><th>Remaining</th><th>Interest Rate</th><th>Start Date</th><th>Due Date</th><th>Status</th></tr></thead>';
            loansHtml += '<tbody>';
            
            customerLoans.forEach(loan => {
                const remainingAmount = parseFloat(loan.amount) - parseFloat(loan.total_payments);
                loansHtml += `
                    <tr>
                        <td>Ksh ${parseFloat(loan.amount).toFixed(2)}</td>
                        <td>Ksh ${parseFloat(loan.total_payments).toFixed(2)}</td>
                        <td>Ksh ${remainingAmount.toFixed(2)}</td>
                        <td>${loan.interest_rate}%</td>
                        <td>${new Date(loan.start_date).toLocaleDateString()}</td>
                        <td>${new Date(loan.due_date).toLocaleDateString()}</td>
                        <td>
                            <span class="badge ${loan.status === 'active' ? 'badge-success' : 
                                (loan.status === 'overdue' ? 'badge-danger' : 'badge-warning')}">
                                ${loan.status.charAt(0).toUpperCase() + loan.status.slice(1)}
                            </span>
                        </td>
                    </tr>
                `;
            });
            
            loansHtml += '</tbody></table>';
            
            document.getElementById('customerLoansDetails').innerHTML = loansHtml;
            document.getElementById('customerLoansModal').style.display = 'block';
        }

        function viewLoanDetails(loanId) {
            const loan = loans.find(l => l.id === loanId);
            if (loan) {
                const remainingAmount = parseFloat(loan.amount) - parseFloat(loan.total_payments);
                let paymentsHtml = '<table class="data-table">';
                paymentsHtml += '<thead><tr><th>Date</th><th>Amount</th><th>Payment Method</th><th>Notes</th></tr></thead>';
                paymentsHtml += '<tbody>';
                
                if (loan.payments && loan.payments.length > 0) {
                    loan.payments.forEach(payment => {
                        paymentsHtml += `
                            <tr>
                                <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
                                <td>Ksh ${parseFloat(payment.amount).toFixed(2)}</td>
                                <td>${payment.payment_method}</td>
                                <td>${payment.notes || ''}</td>
                            </tr>
                        `;
                    });
                } else {
                    paymentsHtml += '<tr><td colspan="4">No payments made yet</td></tr>';
                }
                
                paymentsHtml += '</tbody></table>';
                
                document.getElementById('loanDetails').innerHTML = `
                    <p><strong>Customer:</strong> ${loan.customer_name}</p>
                    <p><strong>Loan Amount:</strong> Ksh ${parseFloat(loan.amount).toFixed(2)}</p>
                    <p><strong>Total Paid:</strong> Ksh ${parseFloat(loan.total_payments).toFixed(2)}</p>
                    <p><strong>Remaining Amount:</strong> Ksh ${remainingAmount.toFixed(2)}</p>
                    <p><strong>Interest Rate:</strong> ${loan.interest_rate}%</p>
                    <p><strong>Start Date:</strong> ${new Date(loan.start_date).toLocaleDateString()}</p>
                    <p><strong>Due Date:</strong> ${new Date(loan.due_date).toLocaleDateString()}</p>
                    <p><strong>Status:</strong> 
                        <span class="badge ${loan.status === 'active' ? 'badge-success' : 
                            (loan.status === 'overdue' ? 'badge-danger' : 'badge-warning')}">
                            ${loan.status.charAt(0).toUpperCase() + loan.status.slice(1)}
                        </span>
                    </p>
                    <p><strong>Notes:</strong> ${loan.notes || 'None'}</p>
                    <h3>Payment History</h3>
                    ${paymentsHtml}
                `;
                
                document.getElementById('loanDetailsModal').style.display = 'block';
            }
        }

        function showAddPaymentModal(loanId) {
            console.log('Opening modal for loan:', loanId); // Debug log
            const loan = loans.find(l => l.id === loanId);
            if (loan) {
                const remainingAmount = parseFloat(loan.amount) - parseFloat(loan.total_payments);
                document.getElementById('modal_loan_id').value = loanId;
                document.getElementById('payment_amount').max = remainingAmount;
                document.getElementById('remaining_amount_display').textContent = 
                    `Maximum payment amount: Ksh ${remainingAmount.toFixed(2)}`;
                document.getElementById('remaining_amount_display').style.display = 'block';
                
                // Show the modal
                const modal = document.getElementById('addPaymentModal');
                modal.style.display = 'block';
                console.log('Modal displayed'); // Debug log
            }
        }

        // Close modals when clicking the X
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.addEventListener('click', function() {
                const modal = this.closest('.modal');
                modal.style.display = 'none';
                console.log('Modal closed by X'); // Debug log
            });
        });

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                console.log('Modal closed by outside click'); // Debug log
            }
        });

        // Initialize modals when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded, initializing modals'); // Debug log
            // Add any additional initialization code here if needed
        });
    </script>
</body>
</html>
