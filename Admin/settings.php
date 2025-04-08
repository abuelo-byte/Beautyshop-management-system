<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop"; // U

// Create database connection and store it in $conn
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to update system settings
function updateSystemSetting($conn, $setting_name, $setting_value)
{
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value) 
                           VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $setting_name, $setting_value, $setting_value);
    return $stmt->execute();
}

// Function to update config file
function updateConfigFile($settings)
{
    $configFile = __DIR__ . '/config.php';
    $configContent = "<?php\nreturn " . var_export($settings, true) . ";\n";
    file_put_contents($configFile, $configContent);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = '';
    $messageType = '';

    // System Settings
    if (isset($_POST['update_system'])) {
        $system_name = filter_input(INPUT_POST, 'system_name', FILTER_SANITIZE_STRING);
        $system_email = filter_input(INPUT_POST, 'system_email', FILTER_SANITIZE_EMAIL);
        $inventory_alert = filter_input(INPUT_POST, 'inventory_alert', FILTER_VALIDATE_INT);

        if (
            updateSystemSetting($conn, 'system_name', $system_name) &&
            updateSystemSetting($conn, 'system_email', $system_email) &&
            updateSystemSetting($conn, 'inventory_alert', $inventory_alert)
        ) {
            $message = "System settings updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating system settings!";
            $messageType = "danger";
        }
    }

    // Appearance Settings
    if (isset($_POST['update_appearance'])) {
        $primary_color = filter_input(INPUT_POST, 'primary_color', FILTER_SANITIZE_STRING);
        $secondary_color = filter_input(INPUT_POST, 'secondary_color', FILTER_SANITIZE_STRING);
        $sidebar_color = filter_input(INPUT_POST, 'sidebar_color', FILTER_SANITIZE_STRING);

        if (
            updateSystemSetting($conn, 'primary_color', $primary_color) &&
            updateSystemSetting($conn, 'secondary_color', $secondary_color) &&
            updateSystemSetting($conn, 'sidebar_color', $sidebar_color)
        ) {
            $message = "Appearance settings updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating appearance settings!";
            $messageType = "danger";
        }
    }
    // Fetch current settings
    $stmt = $conn->prepare("SELECT setting_name, setting_value FROM system_settings");
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }

    $settings = include __DIR__ . '/config.php';
    var_dump($settings); // Debugging: Check if settings are loaded

    // Update the config file with the latest settings
    updateConfigFile($settings);
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Inventory Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary:
                <?php echo $settings['primary_color'] ?? '#ff6b81'; ?>
            ;
            --secondary:
                <?php echo $settings['secondary_color'] ?? '#333'; ?>
            ;
            --sidebar-bg:
                <?php echo $settings['sidebar_color'] ?? '#f9f9f9'; ?>
            ;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: var(--sidebar-bg);
            color: #ff6b81;
            padding-top: 20px;
            transition: all 0.3s ease;
        }

        .sidebar .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar .menu-item {
            padding: 15px 20px;
            color: black;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .sidebar .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            padding-left: 25px;
        }

        .sidebar .menu-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar .menu-item.active {
            background: var(--primary);
            color: white;
        }

        /* Main Content Styles */
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

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }

        .card-body {
            padding: 20px;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary);
            opacity: 0.9;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(32, 216, 124, 0.25);
        }

        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 5px;
            margin-left: 10px;
            border: 2px solid #ddd;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h4><?php echo htmlspecialchars($settings['system_name'] ?? 'Inventory System'); ?></h4>
        </div>
        <a href="index.php" class="menu-item">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="products.php" class="menu-item">
            <i class="fas fa-box"></i> products
        </a>
        <a href="sales.php" class="menu-item">
            <i class="fas fa-tags"></i> sales
        </a>
        <a href="suppliers.php" class="menu-item">
            <i class="fas fa-truck"></i> Suppliers
        </a>
        <a href="customers.php" class="menu-item">
            <i class="fas fa-shopping-cart"></i> customers
        </a>
        <a href="Reports.php" class="menu-item">
            <i class="fas fa-users"></i> Reports
        </a>
        <a href="myaccount.php" class="menu-item">
            <i class="fas fa-user-cog"></i> My Account
        </a>

        <a href="settings.php" class="menu-item active">
            <i class="fas fa-cog"></i> Settings
        </a>
        <a href="logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h1 class="mb-4">System Settings</h1>

            <h1>Welcome to <?php echo htmlspecialchars($settings['system_name'] ?? 'Inventory System'); ?></h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- System Settings -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-cogs me-2"></i>System Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="system_name" class="form-label">System Name</label>
                                    <input type="text" class="form-control" id="system_name" name="system_name"
                                        value="<?php echo htmlspecialchars($settings['system_name'] ?? ''); ?>"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label for="system_email" class="form-label">System Email</label>
                                    <input type="email" class="form-control" id="system_email" name="system_email"
                                        value="<?php echo htmlspecialchars($settings['system_email'] ?? ''); ?>"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label for="inventory_alert" class="form-label">Low Inventory Alert
                                        Threshold</label>
                                    <input type="number" class="form-control" id="inventory_alert"
                                        name="inventory_alert"
                                        value="<?php echo htmlspecialchars($settings['inventory_alert'] ?? '10'); ?>"
                                        required>
                                </div>
                                <button type="submit" name="update_system" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save System Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Appearance Settings -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-palette me-2"></i>Appearance Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="primary_color" class="form-label">Primary Color</label>
                                    <div class="d-flex align-items-center">
                                        <input type="color" class="form-control form-control-color" id="primary_color"
                                            name="primary_color"
                                            value="<?php echo $settings['primary_color'] ?? '#20d87c'; ?>">
                                        <div class="color-preview"
                                            style="background-color: <?php echo $settings['primary_color'] ?? '#20d87c'; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="secondary_color" class="form-label">Secondary Color</label>
                                    <div class="d-flex align-items-center">
                                        <input type="color" class="form-control form-control-color" id="secondary_color"
                                            name="secondary_color"
                                            value="<?php echo $settings['secondary_color'] ?? '#6c757d'; ?>">
                                        <div class="color-preview"
                                            style="background-color: <?php echo $settings['secondary_color'] ?? '#6c757d'; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="sidebar_color" class="form-label">Sidebar Color</label>
                                    <div class="d-flex align-items-center">
                                        <input type="color" class="form-control form-control-color" id="sidebar_color"
                                            name="sidebar_color"
                                            value="<?php echo $settings['sidebar_color'] ?? '#333333'; ?>">
                                        <div class="color-preview"
                                            style="background-color: <?php echo $settings['sidebar_color'] ?? '#333333'; ?>">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" name="update_appearance" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Appearance Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live preview of color changes
        document.querySelectorAll('input[type="color"]').forEach(input => {
            input.addEventListener('input', function () {
                this.nextElementSibling.style.backgroundColor = this.value;
            });
        });

        // Update CSS variables when colors change
        document.getElementById('primary_color').addEventListener('change', function () {
            document.documentElement.style.setProperty('--primary', this.value);
        });

        document.getElementById('secondary_color').addEventListener('change', function () {
            document.documentElement.style.setProperty('--secondary', this.value);
        });

        document.getElementById('sidebar_color').addEventListener('change', function () {
            document.documentElement.style.setProperty('--sidebar-bg', this.value);
        });
    </script>
</body>

</html>