<?php
session_start();

// If user is already logged in, redirect somewhere
//if (isset($_SESSION['user_id'])) {
// If you want to differentiate role here too, you can
// but for now let's just send them to index.php if logged in
// header("Location: index.php/Home.php");
//  exit;
//}

// 1) DB Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "beautyshop"; // or your actual DB name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2) Check if form is submitted
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($email && $pass) {
        // 3) Fetch user by email, also fetch role
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $dbHashedPass = $row['password'];

                // 4) Verify password
                if (password_verify($pass, $dbHashedPass)) {
                    // 5) Set session
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_role'] = $row['role']; // store role

                    // 6) Redirect based on role
                    if ($row['role'] === 'admin') {
                        header("Location: Admin/index.php");
                    } elseif ($row['role'] === 'staff') {
                        header("Location: staff/Home.php");
                    } else {
                        // If you have other roles or a default
                        header("Location: Admin/index.php");
                    }
                    exit;
                } else {
                    $message = "Invalid email or password.";
                }
            } else {
                $message = "Invalid email or password.";
            }
            $stmt->close();
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