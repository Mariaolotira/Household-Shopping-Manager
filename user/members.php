<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../backend/connect.php';

// Check if user has a home
try {
    $homeStmt = $pdo->prepare("SELECT home_id FROM users WHERE id = ?");
    $homeStmt->execute([$_SESSION['user_id']]);
    $user = $homeStmt->fetch(PDO::FETCH_ASSOC);
    $hasHome = $user && !is_null($user['home_id']);

    // If user has a home, fetch home details and dashboard data
    $homeDetails = ['name' => 'My Household'];
    $inventoryStats = [];
    $recentProducts = [];

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
    }
} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $hasHome = false;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Inventory</title>

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
                    <div class="modal-content" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Create New Home</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="../backend/create_home.php" method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Home Name</label>
                                    <input type="text" class="form-control" name="home_name" required
                                        style="background-color: var(--bg-primary); color: var(--text-primary);">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Home Password</label>
                                    <input type="password" class="form-control" name="home_password" required
                                        style="background-color: var(--bg-primary); color: var(--text-primary);">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm Home Password</label>
                                    <input type="password" class="form-control" name="confirm_home_password" required
                                        style="background-color: var(--bg-primary); color: var(--text-primary);">
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
                    <div class="modal-content" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                        <div class="modal-header border-0">
                            <h5 class="modal-title">Join Existing Home</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="../backend/join_home.php" method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Home Name</label>
                                    <input type="text" class="form-control" name="home_name" required
                                        style="background-color: var(--bg-primary); color: var(--text-primary);">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Home Password</label>
                                    <input type="password" class="form-control" name="home_password" required
                                        style="background-color: var(--bg-primary); color: var(--text-primary);">
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
                        <li><a href="shopping-list.php"><i class="bi bi-cart"></i> Shopping List</a></li>
                        <li><a href="members.php" class="active"><i class="bi bi-people"></i> Household Members</a></li>
                        <li><a href="../backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h1><?php echo htmlspecialchars($homeDetails['name']); ?> Members</h1>

            </main>
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
        });
    </script>

</body>

</html>