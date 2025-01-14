<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and has a home
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to add items.";
    header("Location: ../user/shopping-list.php");
    exit();
}

try {
    // Get user's home ID
    $stmt = $pdo->prepare("SELECT home_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user['home_id']) {
        throw new Exception("You must be part of a household to add items.");
    }

    // Validate input
    $itemName = trim($_POST['item_name']);
    $itemQuantity = (int)$_POST['item_quantity'];

    if (empty($itemName) || $itemQuantity <= 0) {
        throw new Exception("Invalid item name or quantity.");
    }

    // Insert item into inventory with low quantity
    $insertStmt = $pdo->prepare("
        INSERT INTO inventory 
        (name, quantity, home_id, added_by, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $insertStmt->execute([
        $itemName, 
        $itemQuantity, 
        $user['home_id'], 
        $_SESSION['user_id']
    ]);

    $_SESSION['success_message'] = "Item added to shopping list successfully!";
    header("Location: ../user/shopping-list.php");
    exit();

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: ../user/shopping-list.php");
    exit();
}
?>