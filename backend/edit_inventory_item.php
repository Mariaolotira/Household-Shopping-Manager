<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and has a home
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to edit inventory items.";
    header("Location: ../user/inventory.php");
    exit();
}

try {
    // Get user's home ID
    $stmt = $pdo->prepare("SELECT home_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user['home_id']) {
        throw new Exception("You must be part of a household to edit inventory items.");
    }

    // Validate input
    $itemId = (int)$_POST['id'];
    $itemName = trim($_POST['name']);
    $itemQuantity = (int)$_POST['quantity'];

    if (empty($itemName)) {
        throw new Exception("Item name cannot be empty.");
    }

    if ($itemQuantity < 0) {
        throw new Exception("Quantity cannot be negative.");
    }

    // Verify item belongs to user's home and get current item details
    $verifyStmt = $pdo->prepare("
        SELECT name FROM inventory 
        WHERE id = ? AND home_id = ?
    ");
    $verifyStmt->execute([$itemId, $user['home_id']]);
    $currentItem = $verifyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentItem) {
        throw new Exception("You do not have permission to edit this item.");
    }

    // Check for duplicate item in the same home (excluding current item)
    $duplicateCheck = $pdo->prepare("
        SELECT id FROM inventory 
        WHERE name = ? AND home_id = ? AND id != ?
    ");
    $duplicateCheck->execute([$itemName, $user['home_id'], $itemId]);
    
    if ($duplicateCheck->fetch()) {
        throw new Exception("An item with this name already exists in your inventory.");
    }

    // Update item in inventory
    $updateStmt = $pdo->prepare("
        UPDATE inventory 
        SET name = ?, quantity = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([
        $itemName, 
        $itemQuantity, 
        $itemId
    ]);

    // Set success message
    $_SESSION['success_message'] = "Inventory item '{$currentItem['name']}' updated successfully!";
    header("Location: ../user/inventory.php");
    exit();

} catch (Exception $e) {
    // Set error message
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: ../user/inventory.php");
    exit();
}
?>