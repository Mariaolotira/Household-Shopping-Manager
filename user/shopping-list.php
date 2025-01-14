<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../backend/connect.php';

try {
    // Check if user has a home
    $homeStmt = $pdo->prepare("SELECT home_id FROM users WHERE id = ?");
    $homeStmt->execute([$_SESSION['user_id']]);
    $user = $homeStmt->fetch(PDO::FETCH_ASSOC);

    $hasHome = $user && !is_null($user['home_id']);

    // Default home details and shopping items
    $homeDetails = ['name' => 'My Household'];
    $shoppingItems = [];

    if ($hasHome) {
        // Fetch home details
        $homeDetailsStmt = $pdo->prepare("
            SELECT h.id, h.name, 
                   (SELECT COUNT(*) FROM users WHERE home_id = h.id) AS home_members 
            FROM home h
            WHERE h.id = ?
        ");
        $homeDetailsStmt->execute([$user['home_id']]);
        $homeDetails = $homeDetailsStmt->fetch(PDO::FETCH_ASSOC);

        // Pagination and search
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $itemsPerPage = 10;
        $offset = ($page - 1) * $itemsPerPage;

        // Fetch low quantity items
        $listQuery = "
            SELECT 
                i.id, 
                i.name, 
                i.quantity, 
                u2.name AS added_by,
                i.created_at
            FROM inventory i
            LEFT JOIN users u2 ON i.added_by = u2.id
            WHERE i.home_id = :home_id 
              AND i.quantity <= 5
              AND (i.name LIKE :search)
            ORDER BY i.quantity ASC, i.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $listStmt = $pdo->prepare($listQuery);

        // Bind parameters
        $searchParam = "%{$search}%";
        $listStmt->bindValue(':home_id', $user['home_id'], PDO::PARAM_INT);
        $listStmt->bindValue(':search', $searchParam, PDO::PARAM_STR);
        $listStmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        // Execute and fetch items
        $listStmt->execute();
        $shoppingItems = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total items
        $countQuery = "
            SELECT COUNT(*) as total
            FROM inventory 
            WHERE home_id = :home_id 
              AND quantity <= 5
              AND name LIKE :search
        ";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->bindValue(':home_id', $user['home_id'], PDO::PARAM_INT);
        $countStmt->bindValue(':search', $searchParam, PDO::PARAM_STR);
        $countStmt->execute();
        $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalItems / $itemsPerPage);
    } else {
        error_log("User does not have a home assigned");
    }
} catch (PDOException $e) {
    error_log("Shopping list database error: " . $e->getMessage());
    $shoppingItems = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping List</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

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

        /* Responsive Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
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

        /* Main Content */
        .main-content {
            background-color: var(--bg-primary);
            padding: 20px;
            overflow-y: auto;
        }

        /* Shopping List Header */
        .shopping-list-header {
            background-color: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .shopping-list-header h1 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .search-container {
            display: flex;
            width: 100%;
        }

        .search-container input {
            flex-grow: 1;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--accent-primary);
        }

        /* Table Styles */
        .table {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 8px;
            overflow: hidden;
        }

        .table th {
            background-color: var(--accent-primary);
            color: var(--bg-primary);
            font-weight: bold;
        }

        .table td {
            vertical-align: middle;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .quantity-badge {
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
        }

        .quantity-badge-critical {
            background-color: var(--danger);
            color: white;
        }

        .quantity-badge-low {
            background-color: var(--accent-primary);
            color: var(--bg-primary);
        }

        /* Responsive Table */
        @media (max-width: 768px) {
            .table {
                font-size: 0.9rem;
            }
        }


        /* Responsive Breakpoints */
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

            .shopping-list-header {
                flex-direction: column;
            }

            .search-container {
                width: 100%;
            }

            .search-container input {
                background-color: var(--bg-primary);
                border: 1px solid var(--accent-primary);
                color: var(--text-primary);
            }

            .search-container .btn {
                background-color: var(--accent-primary);
                border-color: var(--accent-primary);
            }
        }


        /* Additional Mobile Menu Styles */
        .mobile-menu {
            position: fixed;
            top: 0;
            left: -100%;
            width: 250px;
            height: 100%;
            background-color: var(--bg-secondary);
            transition: 0.3s;
            z-index: 1001;
        }

        .mobile-menu.active {
            left: 0;
        }

        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }

        .mobile-menu-overlay.active {
            display: block;
        }

        .download-btn {
            background-color: var(--accent-secondary);
            color: var(--bg-primary);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .download-btn:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }

        .quantity-badge {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 20px;
        }

        .quantity-badge-critical {
            background-color: var(--danger);
            color: white;
        }

        .quantity-badge-low {
            background-color: var(--accent-primary);
            color: var(--bg-primary);
        }

        /* Printable Shopping List Styles */
        @media print {
            body * {
                visibility: hidden;
            }

            #printableShoppingList,
            #printableShoppingList * {
                visibility: visible;
            }

            #printableShoppingList {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
                color: black;
                padding: 20px;
                font-size: 14px;
            }

            .shopping-list-header,
            .pagination,
            .sidebar,
            .mobile-navbar {
                display: none !important;
            }
        }

        .printable-list-container {
            background-color: white;
            color: black;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .printable-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .printable-list-items {
            margin-bottom: 20px;
        }

        .printable-list-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #ddd;
            padding: 10px 0;
        }

        .printable-list-footer {
            display: flex;
            justify-content: space-between;
            border-top: 2px solid #000;
            padding-top: 10px;
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
    </style>
</head>

<body>
    <!-- Mobile Navbar -->
    <nav class="mobile-navbar"> <button class="hamburger" id="mobileMenuToggle">
            <i class="bi bi-list"></i>
        </button>
        <span><?php echo htmlspecialchars($homeDetails['name'] ?? 'Shopping List'); ?></span>
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
                <li><a href="inventory.php"><i class="bi bi-box"></i> Inventory</a></li>
                <li><a href="shopping-list.php" class="active"><i class="bi bi-cart"></i> Shopping List</a></li>
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
                    <li><a href="inventory.php"><i class="bi bi-box"></i> Inventory</a></li>
                    <li><a href="shopping-list.php" class="active"><i class="bi bi-cart"></i> Shopping List</a></li>
                    <li><a href="members.php"><i class="bi bi-people"></i> Household Members</a></li>
                    <li><a href="../backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="shopping-list-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1>Shopping List</h1>
                    <button class="download-btn" onclick="openPrintPreview()">
                        <i class="bi bi-printer"></i> Print Shopping List
                    </button>
                </div>
                <div class="search-container">
                    <form method="GET" class="d-flex w-100">
                        <input
                            type="search"
                            name="search"
                            placeholder="Search low stock items..."
                            class="form-control me-2"
                            value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="shopping-list-container">
                <?php if (empty($shoppingItems)): ?>
                    <div class="empty-state text-center p-5">
                        <i class="bi bi-box" style="font-size: 4rem; color: var(--text-secondary);"></i>
                        <h2 class="mt-3 mb-2">No Low Stock Items</h2>
                        <p class="text-muted">All inventory items are well-stocked</p>
                    </div>
                <?php else: ?>
                    <div class="shopping-list-summary mb-3">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="summary-card bg-secondary p-3 rounded">
                                    <h5 class="mb-2">Total Low Stock Items</h5>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-exclamation-triangle me-2 text-warning" style="font-size: 1.5rem;"></i>
                                        <span class="h4 mb-0"><?php echo $totalItems; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="summary-card bg-secondary p-3 rounded">
                                    <h5 class="mb-2">Lowest Quantity</h5>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-graph-down me-2 text-danger" style="font-size: 1.5rem;"></i>
                                        <span class="h4 mb-0">
                                            <?php
                                            $minQuantity = min(array_column($shoppingItems, 'quantity'));
                                            echo $minQuantity;
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="summary-card bg-secondary p-3 rounded">
                                    <h5 class="mb-2">Last Updated</h5>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-clock me-2" style="font-size: 1.5rem;"></i>
                                        <span class="h4 mb-0">
                                            <?php
                                            echo $shoppingItems ?
                                                date('M d', strtotime($shoppingItems[0]['created_at'])) :
                                                'N/A';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col">Quantity</th>
                                <th scope="col">Added By</th>
                                <th scope="col">Date Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shoppingItems as $item): ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-box"></i> <!-- Add an icon for the item -->
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </td>
                                    <td>
                                        <span class="badge quantity-badge <?php echo $item['quantity'] <= 2 ? 'quantity-badge-critical' : 'quantity-badge-low'; ?>">
                                            <?php echo htmlspecialchars($item['quantity']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['added_by'] ?? 'Unknown'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="modal fade" id="shoppingListPrintModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Printable Shopping List</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="printableShoppingList" class="printable-list-container">
                                        <div class="printable-list-header">
                                            <div>
                                                <h2><?php echo htmlspecialchars($homeDetails['name']); ?></h2>
                                                <p>Shopping List</p>
                                            </div>
                                            <div>
                                                <p>Date: <?php echo date('M d, Y'); ?></p>
                                            </div>
                                        </div>

                                        <div class="printable-list-items">
                                            <?php foreach ($shoppingItems as $item): ?>
                                                <div class="printable-list-item">
                                                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                                                    <span>Qty: <?php echo htmlspecialchars($item['quantity']); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="printable-list-footer">
                                            <div>
                                                <strong>Total Low Stock Items:</strong> <?php echo count($shoppingItems); ?>
                                            </div>
                                            <div>
                                                Generated: <?php echo date('M d, Y H:i'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" onclick="printShoppingList()">
                                        <i class="bi bi-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Shopping list navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>">
                                            <i class="bi bi-chevron-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="bi bi-chevron-double-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openAddItemModal() {
            // Ensure Bootstrap modal is loaded before trying to use it
            if (typeof bootstrap !== 'undefined') {
                const addItemModal = new bootstrap.Modal(document.getElementById('addItemModal'));
                addItemModal.show();
            } else {
                console.error('Bootstrap modal script not loaded');
            }
        }

        function openPrintPreview() {
            // Create and show the modal
            const printModal = new bootstrap.Modal(document.getElementById('shoppingListPrintModal'));
            printModal.show();
        }

        function printShoppingList() {
            window.print();
        }

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
</body>

</html>