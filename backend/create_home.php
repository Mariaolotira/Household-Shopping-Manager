<?php
session_start();
require_once 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_POST['home_password'] !== $_POST['confirm_home_password']) {
        $_SESSION['error_message'] = "Passwords do not match.";
        header("Location: ../user/dashboard.php");
        exit();
    }

    try {
        // Generate a unique home code
        $home_code = bin2hex(random_bytes(5)); // 10-character unique code

        // Insert new home
        $stmt = $pdo->prepare("INSERT INTO home (name, code, password) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['home_name'], $home_code, password_hash($_POST['home_password'], PASSWORD_DEFAULT)]);
        $home_id = $pdo->lastInsertId();

        // Update user's home_id
        $updateStmt = $pdo->prepare("UPDATE users SET home_id = ? WHERE id = ?");
        $updateStmt->execute([$home_id, $_SESSION['user_id']]);

        // Redirect to dashboard
        $_SESSION['success_message'] = "Home created successfully!";
        header("Location: ../user/dashboard.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error creating home: " . $e->getMessage();
        header("Location: ../user/dashboard.php");
        exit();
    }
}
?>