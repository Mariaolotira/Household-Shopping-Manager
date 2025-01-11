<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit();
}

// Admin-specific dashboard content
?>
<!DOCTYPE html>
<html>
<body>
    <h1>Admin Dashboard</h1>
    <!-- Add admin dashboard content -->
</body>
</html>