<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and has a home
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to add inventory items.";
    header("Location: ../user/inventory.php");
    exit();
}

try {
    // Get user's home ID
    $stmt = $pdo->prepare("SELECT home_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user['home_id']) {
        throw new Exception("You must be part of a household to add inventory items.");
    }

    // Validate input
    $itemName = trim($_POST['name']);
    $itemQuantity = (int)$_POST['quantity'];

    if (empty($itemName)) {
        throw new Exception("Item name cannot be empty.");
    }

    if ($itemQuantity < 0) {
        throw new Exception("Quantity cannot be negative.");
    }

    // Check for duplicate item in the same home
    $duplicateCheck = $pdo->prepare("
        SELECT id FROM inventory 
        WHERE name = ? AND home_id = ?
    ");
    $duplicateCheck->execute([$itemName, $user['home_id']]);
    
    if ($duplicateCheck->fetch()) {
        throw new Exception("An item with this name already exists in your inventory.");
    }

    // Insert item into inventory
    $insertStmt = $pdo->prepare("
        INSERT INTO inventory 
        (name, quantity, home_id, added_by, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $insertStmt->execute([
        $itemName, 
        $itemQuantity, 
        $user['home_id'], 
        $_SESSION['user_id']
    ]);

    // Set success message
    $_SESSION['success_message'] = "Inventory item '{$itemName}' added successfully!";
    header("Location: ../user/inventory.php");
    exit();

} catch (Exception $e) {
    // Set error message
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: ../user/inventory.php");
    exit();
}
?>