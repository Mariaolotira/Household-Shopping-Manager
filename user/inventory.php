<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../backend/connect.php';

// Debugging variables
$debugErrors = [];

// Debugging function
function addDebugError($message)
{
    global $debugErrors;
    $debugErrors[] = $message;
}

// Check if user has a home
try {
    // Log user ID
    addDebugError("Current User ID: " . $_SESSION['user_id']);

    $homeStmt = $pdo->prepare("SELECT home_id FROM users WHERE id = ?");
    $homeStmt->execute([$_SESSION['user_id']]);
    $user = $homeStmt->fetch(PDO::FETCH_ASSOC);

    // Log home_id
    addDebugError("User Home ID: " . ($user['home_id'] ?? 'NULL'));

    $hasHome = $user && !is_null($user['home_id']);

    // Default home details
    $homeDetails = ['name' => 'My Household', 'code' => ''];

    if ($hasHome) {
        // Fetch home details
        $homeDetailsStmt = $pdo->prepare("
            SELECT h.id, h.name, h.code,
                   (SELECT COUNT(*) FROM users WHERE home_id = h.id) AS home_members 
            FROM home h
            WHERE h.id = ?
        ");
        $homeDetailsStmt->execute([$user['home_id']]);
        $homeDetails = $homeDetailsStmt->fetch(PDO::FETCH_ASSOC) ?: $homeDetails;

        // Log home details
        addDebugError("Home Details: " . json_encode($homeDetails));
    } else {
        addDebugError("User does not have a home");
        header("Location: dashboard.php");
        exit();
    }

    // Pagination and Search
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $itemsPerPage = 10;
    $offset = ($page - 1) * $itemsPerPage;

    // Prepare inventory query with search and pagination
    $query = "SELECT i.*, u.name as added_by_name 
              FROM inventory i
              LEFT JOIN users u ON i.added_by = u.id
              WHERE i.home_id = :home_id";

    $params = ['home_id' => $user['home_id']];

    if (!empty($searchTerm)) {
        $query .= " AND (i.name LIKE :search)";
        $params['search'] = "%{$searchTerm}%";
    }

    $query .= " ORDER BY i.created_at DESC LIMIT :limit OFFSET :offset";

    // Prepare and execute the main query
    $stmt = $pdo->prepare($query);

    // Bind parameters
    foreach ($params as $key => $value) {
        if ($key === 'limit') {
            $stmt->bindValue($key, $itemsPerPage, PDO::PARAM_INT);
        } elseif ($key === 'offset') {
            $stmt->bindValue($key, $offset, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->bindValue('limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log inventory items
    addDebugError("Inventory Items Count: " . count($inventoryItems));

    // Count total inventory items for pagination
    $countQuery = "SELECT COUNT(*) FROM inventory WHERE home_id = :home_id";
    $countParams = ['home_id' => $user['home_id']];

    if (!empty($searchTerm)) {
        $countQuery .= " AND (name LIKE :search)";
        $countParams['search'] = "%{$searchTerm}%";
    }

    $countStmt = $pdo->prepare($countQuery);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);

    // Log pagination details
    addDebugError("Total Items: " . $totalItems);
    addDebugError("Total Pages: " . $totalPages);
} catch (PDOException $e) {
    // Detailed error logging
    addDebugError("PDO Error Code: " . $e->getCode());
    addDebugError("PDO Error Message: " . $e->getMessage());
    addDebugError("PDO Error Trace: " . $e->getTraceAsString());

    // Reset variables in case of error
    $hasHome = false;
    $inventoryItems = [];
    $totalItems = 0;
    $totalPages = 0;
    $homeDetails = ['name' => 'My Household', 'code' => ''];

    // Optional: Log the error or redirect
    error_log("Inventory page error: " . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// Optional: Debug output (can be removed in production)
if (!empty($debugErrors)) {
    echo "<div style='display:none;' id='debug-info'>";
    foreach ($debugErrors as $error) {
        echo htmlspecialchars($error) . "<br>";
    }
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Manager | Inventory</title>

    <!-- Modern CSS Reset -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/modern-normalize@2.0.0/modern-normalize.min.css">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --accent-primary: #bb86fc;
            --accent-secondary: #03dac6;
            --danger: #cf6679;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            line-height: 1.6;
        }

        /* Mobile Hamburger Menu */
        .mobile-navbar {
            display: none;
            background-color: var(--bg-secondary);
            padding: 15px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .hamburger {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            left: -100%;
            width: 250px;
            height: 100%;
            background-color: var(--bg-secondary);
            transition: 0.3s;
            z-index: 1001;
            padding: 20px;
        }

        .mobile-menu.active {
            left: 0;
        }

        .mobile-menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .mobile-menu-overlay.active {
            display: block;
        }

        .mobile-menu-nav {
            list-style: none;
            margin-top: 30px;
        }

        .mobile-menu-nav li {
            margin-bottom: 15px;
        }

        .mobile-menu-nav a {
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 8px;
        }

        .mobile-menu-nav a:hover {
            background-color: rgba(187, 134, 252, 0.1);
            color: var(--accent-primary);
        }

        .mobile-menu-nav a i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background-color: var(--bg-secondary);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-nav {
            list-style: none;
            margin-top: 10px;
        }

        .sidebar-nav li {
            margin-bottom: 15px;
        }

        .sidebar-nav a {
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background-color: rgba(187, 134, 252, 0.1);
            color: var(--accent-primary);
        }

        .sidebar-nav a i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .mobile-navbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .main-content {
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }
        }

        .logo-container {
            display: flex;
            align-items: center;
            padding: 20px 0;
        }

        .navbar-logo {
            width: 50px;
            height: auto;
            margin-right: 10px;
        }

        .logo-container h2 {
            font-size: 1.5rem;
            margin: 0;
        }

        @media (max-width: 768px) {
            .navbar-logo {
                width: 40px;
            }
        }

        /* Enhanced Main Content Styles */
        .main-content {
            background-color: var(--bg-primary);
            padding: 5px;
            overflow-y: auto;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Improved Inventory Header */

        /* Modal Display Fix */
        .modal {
            display: none;
            /* Hidden by default */
        }

        .modal.fade {
            background: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: block;
        }

        /* Enhanced Modal Styling */
        .modal-content {
            background-color: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
        }

        /* Form Control Improvements */
        .form-control {
            background-color: var(--bg-tertiary) !important;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text-primary) !important;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 2px rgba(187, 134, 252, 0.2);
            outline: none;
        }

        /* Button Improvements */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--accent-primary);
            color: var(--bg-primary);
        }

        .btn-primary:hover {
            background-color: var(--accent-secondary);
            transform: translateY(-1px);
        }

        /* Table Enhancements */
        .table-responsive {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .inventory-table th {
            color: var(--accent-primary);
            font-weight: 600;
            padding: 1rem;
            background-color: var(--bg-tertiary);
            border-bottom: 2px solid var(--accent-primary);
        }

        .inventory-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Search Input Enhancement */
        /* .inventory-search {
            position: relative;
            max-width: 600px;
            margin-left: auto;
        } */

        .inventory-search form {
            display: flex;
            width: 100%;
        }


        .inventory-header h1 {
            margin: 0;
            color: var(--accent-primary);
            font-size: 2rem;
        }

        .inventory-header {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .inventory-header h1 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: var(--accent-primary);
        }

        /* Enhanced Search Container */
        /* .inventory-search {
            position: relative;
            max-width: 600px;
        } */

        .inventory-search form {
            display: flex;
            gap: 12px;
        }

        .inventory-search form input {
            flex: 1;
            padding: 12px 16px;
            border-radius: 8px;
            border: 2px solid var(--bg-primary);
            background-color: var(--text-primary) !important;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .inventory-search input:focus {
            border-color: var(--accent-primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(187, 134, 252, 0.2);
        }

        .inventory-search button {
            padding: 12px 24px;
            border-radius: 8px;
            background-color: var(--accent-primary);
            color: var(--bg-primary);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .inventory-search button:hover {
            background-color: var(--accent-secondary);
            transform: translateY(-1px);
        }

        /* Improved Table Styles */
        .table-responsive {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .inventory-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 20px;
        }

        .inventory-table th {
            background-color: var(--bg-primary);
            color: var(--accent-primary);
            font-weight: 600;
            padding: 16px;
            text-align: left;
            border-bottom: 2px solid var(--accent-primary);
        }

        .inventory-table td {
            padding: 16px;
            border-bottom: 1px solid rgba(187, 134, 252, 0.1);
            color: var(--text-primary);
        }

        .inventory-table tr:hover td {
            background-color: rgba(187, 134, 252, 0.05);
        }

        /* Enhanced Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
        }

        .btn-edit,
        .btn-delete {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-edit {
            background-color: var(--accent-secondary);
            color: var(--bg-primary);
        }

        .btn-delete {
            background-color: var(--danger);
            color: var(--text-primary);
        }

        .btn-edit:hover,
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Improved Modal Styles */
        .modal-content {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            border: 1px solid rgba(187, 134, 252, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(187, 134, 252, 0.1);
        }

        .modal-title {
            color: var(--accent-primary);
            font-size: 1.5rem;
            font-weight: 600;
            margin-right: 15px;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid rgba(187, 134, 252, 0.1);
        }

        /* Modal Z-Index Enhancement */
        .modal {
            z-index: 9999 !important;
        }

        .modal-backdrop {
            z-index: 9998 !important;
        }

        .modal.show {
            display: block !important;
            overflow: visible !important;
        }

        .modal-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) !important;
            margin: 0;
            max-width: 500px;
            width: 90%;
            z-index: 10000 !important;
        }

        /* Ensure modal is fully visible */
        @media (max-width: 768px) {
            .modal-dialog {
                max-width: 95%;
                width: 95%;
            }
        }

        /* Enhanced Input Group Styles */
        .input-group {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            width: 100%;
        }

        .input-group-text {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: var(--text-secondary);
            text-align: center;
            white-space: nowrap;
            background-color: var(--bg-primary);
            background-color: var(--bg-primary);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-right: none;
        }

        .input-group .form-control {
            position: relative;
            flex: 1 1 auto;
            width: 1%;
            min-width: 0;
            background-color: var(--bg-primary);
            border: 2px solid transparent;
            color: var(--text-primary);
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.2) !important;
        }

        .input-group .form-control:focus {
            z-index: 1;
            border-color: var(--accent-primary) !important;
            box-shadow: 0 0 0 2px rgba(187, 134, 252, 0.2);
        }

        .input-group .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        /* Modal Close Button Enhancement */
        .btn-close {
            position: absolute;
            right: 24px;
            transform: translateY(-50%);
            background: var(--danger);
            opacity: 0.7;
            top: 10%;
            color: var(--text-primary);
            transition: all 0.3s ease;
            padding: 10px;
            margin: 0;
        }

        .btn-close:hover {
            opacity: 1;
            color: var(--danger);
        }

        .btn-close-white {
            filter: none;
        }

        .btn-close:focus {
            box-shadow: none;
        }

        .form-control {
            background-color: var(--bg-primary);
            border: 2px solid transparent;
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 2px rgba(187, 134, 252, 0.2);
        }

        /* Improved Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
        }

        .pagination a {
            padding: 10px 16px;
            border-radius: 8px;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background-color: rgba(187, 134, 252, 0.1);
            color: var(--accent-primary);
        }

        .pagination .active {
            background-color: var(--accent-primary);
            color: var(--bg-primary);
        }

        /* Enhanced FAB */
        .fab-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .fab {
            width: 60px;
            height: 60px;
            background-color: var(--accent-primary);
            color: var(--bg-primary);
            border-radius: 50%;
            border: none;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(187, 134, 252, 0.3);
            transition: all 0.3s ease;
        }

        .fab:hover {
            background-color: var(--accent-secondary);
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(187, 134, 252, 0.4);
        }

        /* Improved Alert Styles */
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            max-width: 400px;
            padding: 16px 20px;
            border-radius: 8px;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 1050;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }

        .alert.show {
            opacity: 1;
            transform: translateX(0);
        }

        .alert-success {
            border-left: 4px solid var(--accent-secondary);
        }

        .alert-danger {
            border-left: 4px solid var(--danger);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .inventory-header {
                padding: 16px;
            }

            .inventory-search form {
                flex-direction: column;
            }

            .inventory-search button {
                width: 100%;
            }

            .table-responsive {
                padding: 10px;
            }

            .inventory-table th,
            .inventory-table td {
                padding: 12px 8px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-edit,
            .btn-delete {
                width: 100%;
                text-align: center;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>
    <!-- Mobile Navbar -->
    <nav class="mobile-navbar">
        <button class="hamburger" id="mobileMenuToggle">
            <i class="bi bi-list"></i>
        </button>
        <span><?php echo htmlspecialchars($homeDetails['name'] ?? 'Household Manager'); ?></span>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    <div class="mobile-menu" id="mobileMenu">
        <button class="hamburger" id="mobileMenuClose">
            <i class="bi bi-x-lg"></i>
        </button>
        <div class="logo-container">
            <img src="../Images/logo.jpeg" alt="Logo" class="navbar-logo">
            <h2>Household Manager</h2>
        </div>
        <nav>
            <ul class="mobile-menu-nav">
                <li><a href="dashboard.php"><i class="bi bi-house"></i> Dashboard</a></li>
                <li><a href="inventory.php" class="active"><i class="bi bi-box"></i> Inventory</a></li>
                <li><a href="shopping-list.php"><i class="bi bi-cart"></i> Shopping List</a></li>
                <li><a href="members.php"><i class="bi bi-people"></i> Household Members</a></li>
                <li><a href="../backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>
    </div>
    <div class="dashboard-grid">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo-container">
                <img src="../Images/logo.jpeg" alt="Logo" class="navbar-logo">
                <h2>Household Manager</h2>
            </div>
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php"><i class="bi bi-house"></i> Dashboard</a></li>
                    <li><a href="inventory.php" class="active"><i class="bi bi-box"></i> Inventory</a></li>
                    <li><a href="shopping-list.php"><i class="bi bi-cart"></i> Shopping List</a></li>
                    <li><a href="members.php"><i class="bi bi-people"></i> Household Members</a></li>
                    <li><a href="../backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Success and Error Message Display -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php
                    echo htmlspecialchars($_SESSION['success_message']);
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php
                    echo htmlspecialchars($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="inventory-header">
                <h1>Inventory</h1>
                <div class="inventory-search">
                    <form method="GET" class="d-flex">
                        <input type="text" name="search" class="form-control"
                            placeholder="Search items..."
                            value="<?php echo htmlspecialchars($searchTerm); ?>"
                            style="background-color: var(--bg-secondary); color: var(--text-primary);">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Quantity</th>
                            <th>Added By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventoryItems as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($item['added_by_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <button class="btn-edit" data-bs-toggle="modal"
                                        data-bs-target="#editItemModal" data-id="<?php echo $item['id']; ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <a href="../backend/delete_item.php?id=<?php echo $item['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this item?');">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>

            <!-- Add Item Modal -->
            <div class="modal fade" id="addItemModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Item</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <form action="../backend/add_inventory_item.php" method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Item Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-box"></i></span>
                                        <input type="text" class="form-control" name="name" placeholder="Enter item name" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-123"></i></span>
                                        <input type="number" class="form-control" name="quantity" placeholder="Enter quantity" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Item</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Item Modal -->
            <div class="modal fade" id="editItemModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Item</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <form action="../backend/edit_Inventory_item.php" method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="id" id="editItemId">
                                <div class="mb-3">
                                    <label class="form-label">Item Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-box"></i></span>
                                        <input type="text" class="form-control" name="name" id="editItemName" placeholder="Enter item name" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-123"></i></span>
                                        <input type="number" class="form-control" name="quantity" id="editItemQuantity" placeholder="Enter quantity" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Item</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Floating Action Button -->
            <div class="fab-container">
                <button class="fab" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus"></i>
                </button>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mobileMenuClose = document.getElementById('mobileMenuClose');
            const mobileMenu = document.getElementById('mobileMenu');
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

            // Open mobile menu
            mobileMenuToggle.addEventListener('click', () => {
                mobileMenu.classList.add('active');
                mobileMenuOverlay.classList.add('active');
            });

            // Close mobile menu
            mobileMenuClose.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
                mobileMenuOverlay.classList.remove('active');
            });

            // Close menu when clicking overlay
            mobileMenuOverlay.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
                mobileMenuOverlay.classList.remove('active');
            });

            // Close menu when a menu item is clicked
            const mobileMenuItems = document.querySelectorAll('.mobile-menu-nav a');
            mobileMenuItems.forEach(item => {
                item.addEventListener('click', () => {
                    mobileMenu.classList.remove('active');
                    mobileMenuOverlay.classList.remove('active');
                });
            });
        });
    </script>
    <script>
        // Enhanced Modal Functionality
        document.addEventListener('DOMContentLoaded', () => {
            // Modal Handling
            const editItemModal = document.getElementById('editItemModal');
            if (editItemModal) {
                editItemModal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    const itemId = button.getAttribute('data-id');
                    const itemRow = button.closest('tr');

                    // Get item details with animation
                    const itemName = itemRow.querySelector('td:nth-child(1)').textContent.trim();
                    const itemQuantity = itemRow.querySelector('td:nth-child(2)').textContent.trim();

                    // Populate modal fields with fade effect
                    const modalFields = {
                        '#editItemId': itemId,
                        '#editItemName': itemName,
                        '#editItemQuantity': itemQuantity
                    };

                    Object.entries(modalFields).forEach(([selector, value]) => {
                        const element = editItemModal.querySelector(selector);
                        if (element) {
                            element.style.opacity = '0';
                            element.value = value;
                            setTimeout(() => {
                                element.style.opacity = '1';
                            }, 100);
                        }
                    });
                });
            }

            // Enhanced Form Validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', (e) => {
                    const nameInput = form.querySelector('input[name="name"]');
                    const quantityInput = form.querySelector('input[name="quantity"]');
                    let isValid = true;

                    // Validate name
                    if (nameInput && !nameInput.value.trim()) {
                        showValidationError(nameInput, 'Item name cannot be empty');
                        isValid = false;
                    }

                    // Validate quantity
                    if (quantityInput && (parseInt(quantityInput.value) < 0 || !quantityInput.value.trim())) {
                        showValidationError(quantityInput, 'Please enter a valid quantity');
                        isValid = false;
                    }

                    if (!isValid) {
                        e.preventDefault();
                    }
                });
            });

            // Improved Alert Handling
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                // Show alert with animation
                setTimeout(() => {
                    alert.classList.add('show');
                }, 100);

                // Auto-hide alert
                setTimeout(() => {
                    alert.classList.remove('show');
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });

            // Enhanced Delete Confirmation
            document.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const itemName = button.closest('tr').querySelector('td:nth-child(1)').textContent;

                    if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
                        window.location.href = button.getAttribute('href');
                    }
                });
            });
        });

        // Validation Error Helper
        function showValidationError(input, message) {
            input.classList.add('is-invalid');

            // Create error message element
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            errorDiv.textContent = message;

            // Remove existing error message if any
            const existingError = input.nextElementSibling;
            if (existingError && existingError.className === 'invalid-feedback') {
                existingError.remove();
            }

            input.parentNode.appendChild(errorDiv);

            // Remove error state on input
            input.addEventListener('input', () => {
                input.classList.remove('is-invalid');
                errorDiv.remove();
            });
        }

        // Search Input Enhancement
        const searchInput = document.querySelector('.inventory-search input');
        if (searchInput) {
            // Add debounced search functionality
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const form = e.target.closest('form');
                    if (form) form.submit();
                }, 500);
            });
        }
    </script>
</body>

</html>