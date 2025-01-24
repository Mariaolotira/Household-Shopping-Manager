<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and has a home
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to delete inventory items.";
    header("Location: ../user/inventory.php");
    exit();
}

try {
    // Get user's home ID
    $stmt = $pdo->prepare("SELECT home_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user['home_id']) {
        throw new Exception("You must be part of a household to delete inventory items.");
    }

    // Validate input
    $itemId = (int)$_GET['id'];  // Using GET for delete action

    // Verify item belongs to user's home and get item name
    $verifyStmt = $pdo->prepare("
        SELECT name FROM inventory 
        WHERE id = ? AND home_id = ?
    ");
    $verifyStmt->execute([$itemId, $user['home_id']]);
    $item = $verifyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("You do not have permission to delete this item.");
    }

    // Delete item from inventory
    $deleteStmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
    $deleteStmt->execute([$itemId]);

    // Set success message
    $_SESSION['success_message'] = "Inventory item '{$item['name']}' deleted successfully!";
    header("Location: ../user/inventory.php");
    exit();

} catch (Exception $e) {
    // Set error message
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: ../user/inventory.php");
    exit();
}
?>