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

// Fetch Household Members
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

    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $membersPerPage = 10;
    $offset = ($page - 1) * $membersPerPage;

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

        // Fetch Members Query
        $membersQuery = "
            SELECT 
                u.id, 
                u.name, 
                u.email, 
                u.phone,
                u.is_admin,
                h.code as home_code
            FROM users u
            JOIN home h ON u.home_id = h.id
            WHERE u.home_id = :home_id 
            AND (
                u.id LIKE :search OR
                u.name LIKE :search OR 
                u.email LIKE :search OR 
                u.phone LIKE :search
            )
            ORDER BY u.id DESC, u.name ASC
            LIMIT :limit OFFSET :offset
        ";

        $membersStmt = $pdo->prepare($membersQuery);
        $searchParam = "%{$search}%";
        $membersStmt->bindValue(':home_id', $user['home_id'], PDO::PARAM_INT);
        $membersStmt->bindValue(':search', $searchParam, PDO::PARAM_STR);
        $membersStmt->bindValue(':limit', $membersPerPage, PDO::PARAM_INT);
        $membersStmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        // Log the prepared query and parameters
        addDebugError("Members Query: " . $membersQuery);
        addDebugError("Home ID: " . $user['home_id']);
        addDebugError("Search Param: " . $searchParam);

        $membersStmt->execute();
        $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

        // Log members count
        addDebugError("Members Count: " . count($members));
        if (empty($members)) {
            addDebugError("No members found. Possible reasons:");
            addDebugError("1. No users in this home");
            addDebugError("2. Incorrect home_id");
            addDebugError("3. Database connection issue");
        }

        // Count total members for pagination
        $countQuery = "
            SELECT COUNT(*) as total 
            FROM users 
            WHERE home_id = :home_id 
            AND (
                name LIKE :search OR 
                email LIKE :search OR 
                phone LIKE :search
            )
        ";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->bindValue(':home_id', $user['home_id'], PDO::PARAM_INT);
        $countStmt->bindValue(':search', $searchParam, PDO::PARAM_STR);
        $countStmt->execute();
        $totalMembers = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalMembers / $membersPerPage);

        // Log total members
        addDebugError("Total Members: " . $totalMembers);
    } else {
        addDebugError("User does not have a home");
        $members = [];
        $totalMembers = 0;
        $totalPages = 0;
    }
} catch (PDOException $e) {
    // Detailed error logging
    addDebugError("PDO Error Code: " . $e->getCode());
    addDebugError("PDO Error Message: " . $e->getMessage());
    addDebugError("PDO Error Trace: " . $e->getTraceAsString());

    $members = [];
    $totalMembers = 0;
    $totalPages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Manager | Members</title>

    <!-- Modern CSS Reset -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/modern-normalize@2.0.0/modern-normalize.min.css">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

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

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
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

        .main-content {
            background-color: var(--bg-primary);
            padding: 30px;
            overflow-y: auto;
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


        /* Home Setup Styles */
        .home-setup-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: var(--bg-primary);
            padding: 20px;
        }

        .home-setup-card {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 30px;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .home-setup-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .home-setup-btn {
            background-color: var(--accent-primary);
            color: var(--bg-primary);
            border: none;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background-color 0.3s ease;
        }

        .home-setup-btn:hover {
            background-color: var(--accent-secondary);
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

        user/shopping-list.php

        /* Dark mode for Bootstrap components */
        .form-control,
        .form-control:focus {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--text-secondary);
        }

        .btn-primary {
            background-color: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        .btn-outline-primary {
            color: var(--accent-primary);
            border-color: var(--accent-primary);
        }

        .table-dark {
            --bs-table-bg: var(--bg-secondary);
            --bs-table-color: var(--text-primary);
            --bs-table-hover-bg: rgba(255, 255, 255, 0.1);
        }

        /* Responsive Layout */
        @media (max-width: 768px) {
            .dashboard-grid {
                display: block;
            }

            .sidebar {
                display: none;
            }

            .mobile-navbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: var(--bg-secondary);
                padding: 15px;
                position: sticky;
                top: 0;
                z-index: 1000;
            }
        }

        /* Mobile Menu */
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

        /* Utility Classes */
        .bg-dark-custom {
            background-color: var(--bg-secondary) !important;
        }

        .text-muted-custom {
            color: var(--text-secondary) !important;
        }

        /* Debug Errors Style */
        .debug-errors-container {
            background-color: var(--bg-secondary);
            color: var(--danger);
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
        }

        .debug-errors-container h3 {
            margin-bottom: 10px;
            color: var(--danger);
        }

        .debug-errors-container ul {
            list-style-type: none;
            padding: 0;
        }

        .debug-errors-container li {
            margin-bottom: 5px;
            padding: 5px;
            background-color: rgba(207, 102, 121, 0.1);
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <!-- Debug Errors Container -->
    <!-- <?php if (!empty($debugErrors)): ?> -->
    <!-- <div class="debug-errors-container">
            <h3>Debug Errors</h3>
            <ul>
                <?php foreach ($debugErrors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div> -->
    <!-- <?php endif; ?> -->
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
                <li><a href="inventory.php"><i class="bi bi-box"></i> Inventory</a></li>
                <li><a href="shopping-list.php"><i class="bi bi-cart"></i> Shopping List</a></li>
                <li><a href="members.php" class="active"><i class="bi bi-people"></i> Household Members</a></li>
                <li><a href="../backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>
    </div>

    <?php if (!$hasHome): ?>
        <div class="home-setup-container">
            <div class="home-setup-card">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                <p>You haven't joined a household yet.</p>
                <div class="home-setup-actions">
                    <button class="home-setup-btn" data-bs-toggle="modal" data-bs-target="#createHomeModal">
                        <i class="bi bi-plus-circle"></i> Create Home
                    </button>
                    <button class="home-setup-btn" data-bs-toggle="modal" data-bs-target="#joinHomeModal">
                        <i class="bi bi-house-add"></i> Join Home
                    </button>
                </div>
            </div>
            <!-- Create Home Modal -->
            <div class="modal fade" id="createHomeModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content bg-dark-custom text-muted-custom">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Create New Home</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="../backend/create_home.php" method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Home Name</label>
                                    <input type="text" class="form-control" name="home_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Home Password</label>
                                    <input type="password" class="form-control" name="home_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm Home Password</label>
                                    <input type="password" class="form-control" name="confirm_home_password" required>
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="submit" class="btn btn-primary">Create Home</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Join Home Modal -->
            <div class="modal fade" id="joinHomeModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content bg-dark-custom text-muted-custom">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Join Existing Home</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="../backend/join_home.php" method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Home Name or Code</label>
                                    <input type="text" class="form-control" name="home_identifier" required placeholder="Enter home name or code">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Home Password</label>
                                    <input type="password" class="form-control" name="home_password" required>
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="submit" class="btn btn-primary">Join Home</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="dashboard-grid">
            <aside class="sidebar">
                <div class="logo-container">
                    <img src="../Images/logo.jpeg" alt="Logo" class="navbar-logo">
                    <h2>Household Manager</h2>
                </div>
                <nav>
                    <ul class="sidebar-nav">
                        <li><a href="dashboard.php"><i class="bi bi-house"></i> Dashboard</a></li>
                        <li><a href="inventory.php"><i class="bi bi-box"></i> Inventory</a></li>
                        <li><a href="shopping-list.php"><i class="bi bi-cart"></i> Shopping List</a></li>
                        <li><a href="members.php" class="active"><i class="bi bi-people"></i> Household Members</a></li>
                        <li><a href="../backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </nav>
            </aside>

            <main class="main-content">
                <div class="members-header d-flex justify-content-between align-items-center mb-4">
                    <h1><?php echo htmlspecialchars($homeDetails['name']); ?> Members</h1>
                    <div class="home-code-section">
                        <button class="btn btn-outline-primary" id="shareHomeCodeBtn">
                            <i class="bi bi-share"></i> Share Home Code
                        </button>
                    </div>
                </div>

                <div class="search-container mb-3">
                    <form method="GET" class="d-flex">
                        <input
                            type="search"
                            name="search"
                            placeholder="Search members..."
                            class="form-control me-2"
                            value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>

                <?php if (empty($members)): ?>
                    <div class="alert alert-info text-center">
                        No members found in this household.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['id']); ?></td>
                                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Members navigation" class="mt-4">
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
                                        <a class="page-link" href="?page=<?php echo $i; ?>& search=<?php echo urlencode($search); ?>">
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
            </main>

            <!-- Home Code Modal -->
            <div class="modal fade" id="homeCodeModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content bg-dark-custom text-muted-custom">
                        <div class="modal-header">
                            <h5 class="modal-title">Home Joining Code</h5>
                            <button type="button" class="btn-close btn-primary" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <h3><?php echo htmlspecialchars($homeDetails['code']); ?></h3>
                            <p>Share this code with people you want to invite to your household.</p>
                            <button class="btn btn-success" id="whatsappShareBtn">
                                <i class="bi bi-whatsapp"></i> Share on WhatsApp
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

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

            const shareHomeCodeBtn = document.getElementById('shareHomeCodeBtn');
            const whatsappShareBtn = document.getElementById('whatsappShareBtn');
            const homeCode = "<?php echo htmlspecialchars($homeDetails['code']); ?>";

            shareHomeCodeBtn.addEventListener('click', () => {
                const homeCodeModal = new bootstrap.Modal(document.getElementById('homeCodeModal'));
                homeCodeModal.show();
            });

            whatsappShareBtn.addEventListener('click', () => {
                const message = `Join my household! Use this code: ${homeCode}. Download the app and enter this code to join.`;
                const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
                window.open(whatsappUrl, '_blank');
            });
        });
    </script>
</body>

</html>