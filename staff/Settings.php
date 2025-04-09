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

// Create settings table if it doesn't exist
$createSettingsTable = "CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($createSettingsTable)) {
    die("Settings table creation failed: " . $conn->error);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $settings = [
            'cafeteria_name' => $_POST['cafeteria_name'],
            'cafeteria_address' => $_POST['cafeteria_address'],
            'cafeteria_phone' => $_POST['cafeteria_phone'],
            'cafeteria_email' => $_POST['cafeteria_email'],
            'tax_rate' => $_POST['tax_rate'],
            'currency_symbol' => $_POST['currency_symbol'],
            'receipt_footer' => $_POST['receipt_footer']
        ];

        $success = true;
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) 
                                  VALUES (?, ?, 'general') 
                                  ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $key, $value, $value);
            if (!$stmt->execute()) {
                $success = false;
                $error_message = "Error updating settings: " . $conn->error;
                break;
            }
        }

        if ($success) {
            $success_message = "Settings updated successfully!";
        }
    }
}

// Fetch current settings
$settings = [];
$settingsQuery = "SELECT setting_key, setting_value FROM settings WHERE setting_group = 'general'";
$settingsResult = $conn->query($settingsQuery);
if ($settingsResult && $settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
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
            <li><a href="Customers.php">
                    <i class="fas fa-users"></i>
                    <span class="link-text">Customers</span>
                </a></li>
            <li><a href="#" class="active">
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
            <h1>Settings</h1>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- General Settings -->
        <div class="card">
            <h2>General Settings</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="cafeteria_name">Cafeteria Name:</label>
                    <input type="text" id="cafeteria_name" name="cafeteria_name" 
                           value="<?php echo htmlspecialchars($settings['cafeteria_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="cafeteria_address">Address:</label>
                    <textarea id="cafeteria_address" name="cafeteria_address" rows="3"><?php 
                        echo htmlspecialchars($settings['cafeteria_address'] ?? ''); 
                    ?></textarea>
                </div>
                <div class="form-group">
                    <label for="cafeteria_phone">Phone Number:</label>
                    <input type="tel" id="cafeteria_phone" name="cafeteria_phone" 
                           value="<?php echo htmlspecialchars($settings['cafeteria_phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="cafeteria_email">Email:</label>
                    <input type="email" id="cafeteria_email" name="cafeteria_email" 
                           value="<?php echo htmlspecialchars($settings['cafeteria_email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="tax_rate">Tax Rate (%):</label>
                    <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100" 
                           value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '0'); ?>">
                </div>
                <div class="form-group">
                    <label for="currency_symbol">Currency Symbol:</label>
                    <input type="text" id="currency_symbol" name="currency_symbol" 
                           value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'Ksh'); ?>">
                </div>
                <div class="form-group">
                    <label for="receipt_footer">Receipt Footer:</label>
                    <textarea id="receipt_footer" name="receipt_footer" rows="3"><?php 
                        echo htmlspecialchars($settings['receipt_footer'] ?? 'Thank you for your visit!'); 
                    ?></textarea>
                </div>
                <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
            </form>
        </div>

        <!-- Backup & Restore -->
        <div class="card">
            <h2>Backup & Restore</h2>
            <div class="form-group">
                <button type="button" class="btn btn-primary" onclick="backupDatabase()">
                    <i class="fas fa-download"></i> Backup Database
                </button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('restoreFile').click()">
                    <i class="fas fa-upload"></i> Restore Database
                </button>
                <input type="file" id="restoreFile" style="display: none;" accept=".sql">
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

        // Backup database function
        function backupDatabase() {
            window.location.href = 'backup_database.php';
        }

        // Handle restore file selection
        document.getElementById('restoreFile').addEventListener('change', function(e) {
            if (this.files.length > 0) {
                if (confirm('Are you sure you want to restore the database? This will overwrite all current data.')) {
                    const formData = new FormData();
                    formData.append('restore_file', this.files[0]);

                    fetch('restore_database.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Database restored successfully!');
                        } else {
                            alert('Error restoring database: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error restoring database: ' + error);
                    });
                }
            }
        });
    </script>
</body>
</html> 