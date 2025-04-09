<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Debug: Print current session
echo "Current session before login:<br>";
echo "user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . "<br>";
echo "user_role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'not set') . "<br>";

// If user is already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    echo "User already logged in, redirecting...<br>";
    if ($_SESSION['user_role'] === 'admin') {
        echo "Redirecting to Admin/index.php<br>";
        header("Location: Admin/index.php");
    } elseif ($_SESSION['user_role'] === 'staff') {
        echo "Redirecting to staff/Home.php<br>";
        header("Location: staff/Home.php");
    }
    exit;
}

// 1) DB Connection
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

// 2) Check if form is submitted
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    echo "Login attempt with email: " . htmlspecialchars($email) . "<br>";

    if ($email && $pass) {
        // 3) Fetch user by email, also fetch role and name
        $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $dbHashedPass = $row['password'];

                // 4) Verify password
                if (password_verify($pass, $dbHashedPass)) {
                    echo "Password verified successfully<br>";
                    
                    // 5) Set session
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_role'] = $row['role'];
                    $_SESSION['user_name'] = $row['name'];

                    echo "Session variables set:<br>";
                    echo "user_id: " . $_SESSION['user_id'] . "<br>";
                    echo "user_role: " . $_SESSION['user_role'] . "<br>";
                    echo "user_name: " . $_SESSION['user_name'] . "<br>";

                    // 6) Redirect based on role
                    if ($row['role'] === 'admin') {
                        echo "Redirecting to Admin/index.php<br>";
                        header("Location: Admin/index.php");
                    } elseif ($row['role'] === 'staff') {
                        echo "Redirecting to staff/Home.php<br>";
                        header("Location: staff/Home.php");
                    }
                    exit;
                } else {
                    echo "Password verification failed<br>";
                    $message = "Invalid email or password.";
                }
            } else {
                echo "No user found with that email<br>";
                $message = "Invalid email or password.";
            }
            $stmt->close();
        } else {
            echo "Error preparing statement: " . $conn->error . "<br>";
            $message = "Error during login. Please try again.";
        }
    } else {
        echo "Email or password empty<br>";
        $message = "Please fill in all fields.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="login.css">
</head>

<body>
    <div class="auth-container">
        <h2>Login</h2>

        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>

            <button type="submit" name="login">Login</button>
        </form>

        <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
    </div>
</body>

</html>