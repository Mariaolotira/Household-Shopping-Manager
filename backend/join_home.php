<?php
session_start();
require_once 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Prepare a query to find the home by name or code
        $stmt = $pdo->prepare("
            SELECT id, password 
            FROM home 
            WHERE name = ? OR code = ?
        ");
        
        // Execute the query with both home name and code
        $stmt->execute([$_POST['home_identifier'], $_POST['home_identifier']]);
        $home = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($home && password_verify($_POST['home_password'], $home['password'])) {
            // Check if user is already in a home
            $checkHomeStmt = $pdo->prepare("SELECT home_id FROM users WHERE id = ?");
            $checkHomeStmt->execute([$_SESSION['user_id']]);
            $currentHome = $checkHomeStmt->fetch(PDO::FETCH_ASSOC);

            if ($currentHome && $currentHome['home_id'] !== null) {
                // User is already in a home
                $_SESSION['error_message'] = "You are already a member of a household. Leave current household first.";
                header("Location: ../user/dashboard.php");
                exit();
            }

            // Update user's home_id
            $updateStmt = $pdo->prepare("UPDATE users SET home_id = ? WHERE id = ?");
            $updateStmt->execute([$home['id'], $_SESSION['user_id']]);

            // Redirect to dashboard
            $_SESSION['success_message'] = "Joined home successfully!";
            header("Location: ../user/dashboard.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Invalid home name/code or password.";
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