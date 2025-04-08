<?php
session_start();

// 1) Destroy the session
session_unset();   // remove all session variables
session_destroy(); // destroy the session itself

// 2) Redirect to login (in the same folder)
header("Location: login.php");
exit;
?>