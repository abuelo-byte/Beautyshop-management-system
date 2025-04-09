<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// 1) Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop";

// First try to connect without selecting database
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if database exists
$result = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($result->num_rows == 0) {
    // Create database if it doesn't exist
    if (!$conn->query("CREATE DATABASE IF NOT EXISTS $dbname")) {
        die("Error creating database: " . $conn->error);
    }
}

// Now connect to the specific database
$conn->select_db($dbname);
if ($conn->error) {
    die("Error selecting database: " . $conn->error);
}

// Create users table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($createTableQuery)) {
    die("Error creating table: " . $conn->error);
}

// 3) Check if form is submitted
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($name && $email && $pass) {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } else {
            // Check if email already exists
            $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if (!$checkEmail) {
                $message = "Error checking email. Please try again.";
            } else {
                $checkEmail->bind_param("s", $email);
                $checkEmail->execute();
                $checkEmail->store_result();
                
                if ($checkEmail->num_rows > 0) {
                    $message = "Email already exists. Please use a different email.";
                } else {
                    // Hash the password
                    $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
                    $role = 'staff'; // Set default role to staff

                    // Insert into users table
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("ssss", $name, $email, $hashedPass, $role);
                        if ($stmt->execute()) {
                            // Set session variables
                            $_SESSION['user_id'] = $stmt->insert_id;
                            $_SESSION['user_role'] = $role;
                            $_SESSION['user_name'] = $name;
                            
                            // Debug: Print session variables
                            echo "Session variables set:<br>";
                            echo "user_id: " . $_SESSION['user_id'] . "<br>";
                            echo "user_role: " . $_SESSION['user_role'] . "<br>";
                            echo "user_name: " . $_SESSION['user_name'] . "<br>";
                            
                            // Redirect to appropriate page based on role
                            if ($role === 'admin') {
                                echo "Redirecting to Admin/index.php<br>";
                                header("Location: Admin/index.php");
                            } elseif ($role === 'staff') {
                                echo "Redirecting to staff/Home.php<br>";
                                header("Location: staff/Home.php");
                            }
                            exit;
                        } else {
                            $message = "Error creating account. Please try again.";
                        }
                        $stmt->close();
                    } else {
                        $message = "Error creating account. Please try again.";
                    }
                }
                $checkEmail->close();
            }
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Signup</title>
    <link rel="stylesheet" href="signup.css">
</head>

<body>
    <div class="auth-container">
        <h2>Create an Account</h2>

        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">

            <label for="email">Email</label>
            <input type="email" name="email" id="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>

            <button type="submit" name="signup">Sign Up</button>
        </form>

        <p>Already have an account? <a href="index.php">Login here</a></p>
    </div>
</body>

</html>