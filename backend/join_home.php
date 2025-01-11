<?php
session_start();
require_once 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Check if home exists and verify password
        $stmt = $pdo->prepare("SELECT id, password FROM home WHERE name = ?");
        $stmt->execute([$_POST['home_name']]);
        $home = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($home && password_verify($_POST['home_password'], $home['password'])) {
            // Update user's home_id
            $updateStmt = $pdo->prepare("UPDATE users SET home_id = ? WHERE id = ?");
            $updateStmt->execute([$home['id'], $_SESSION['user_id']]);

            // Redirect to dashboard
            $_SESSION['success_message'] = "Joined home successfully!";
            header("Location: ../user/dashboard.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Invalid home name or password.";
            header("Location: ../user/dashboard.php");
            exit();
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error joining home: " . $e->getMessage();
        header("Location: ../user/dashboard.php");
        exit();
    }
}
?>