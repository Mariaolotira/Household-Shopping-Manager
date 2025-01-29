<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../backend/connect.php';

try {
    // Fetch all statistics in a single query
    $statsQuery = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM home) as total_households,
            (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_users,
            (SELECT COUNT(*) FROM home WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_households
    ");
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

    // Fetch recent registrations with more detailed info
    $recentQuery = $pdo->query("
        (SELECT 
            'user' as type,
            name as name,
            email,
            created_at,
            CASE 
                WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 
                ELSE 0 
            END as is_recent
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
        UNION ALL
        (SELECT 
            'household' as type,
            name,
            code as email,
            created_at,
            CASE 
                WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 
                ELSE 0 
            END as is_recent
        FROM home 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $recentRegistrations = $recentQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $stats = [
        'total_users' => 0,
        'total_households' => 0,
        'new_users' => 0,
        'new_households' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Manager | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/modern-normalize@2.0.0/modern-normalize.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --bg-primary: #1a1a1a;
            --bg-secondary: #242424;
            --bg-tertiary: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --accent-primary: #7c4dff;
            --accent-secondary: #00e5ff;
            --success: #00c853;
            --info: #2196f3;
            --warning: #ffd600;
            --danger: #ff1744;
            --transition-speed: 0.3s;
            --border-radius: 12px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
        }

        /* Mobile Navbar */
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

        .hamburger:hover {
            color: var(--accent-primary);
        }

        .sidebar {
            background-color: var(--bg-secondary);
            padding: 20px;
            position: fixed;
            width: 250px;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-nav {
            list-style: none;
            margin-top: 20px;
        }

        .sidebar-nav li {
            margin-bottom: 10px;
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

        /* Enhanced Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            max-width: 1400px;
        }

        /* Enhanced Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            transition: transform var(--transition-speed);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }

        .stat-content h3 {
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
        }

        .trend-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            color: var(--success);
        }

        /* Enhanced Recent Registrations */
        .recent-registrations {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        .registrations-table {
            min-width: 600px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .registrations-table th {
            background-color: var(--bg-tertiary);
            padding: 1rem;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .registrations-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--bg-tertiary);
        }

        .registrations-table tr:hover {
            background-color: var(--bg-tertiary);
        }

        .registration-type {
            padding: 0.375rem 1rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .type-user {
            background-color: var(--accent-primary);
            color: white;
        }

        .type-household {
            background-color: var(--accent-secondary);
            color: var(--bg-primary);
        }

        /* Enhanced Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .new-registration {
            animation: slideIn 0.5s ease-out;
        }

        /* Enhanced Mobile Responsiveness */
        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }

            .main-content {
                margin-left: 240px;
            }
        }

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
                margin-left: 0;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .stat-icon {
                margin-bottom: 1rem;
            }

            .recent-registrations {
                padding: 1rem;
            }

            .registrations-table th,
            .registrations-table td {
                padding: 0.75rem;
                font-size: 0.875rem;
            }
        }

        @media (max-width: 480px) {
            .mobile-navbar {
                padding: 10px;
            }

            .hamburger {
                font-size: 20px;
            }

            .stat-content h3 {
                font-size: 1.5rem;
            }

            .trend-indicator {
                font-size: 0.75rem;
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
        <span>Admin Dashboard</span>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    <div class="mobile-menu" id="mobileMenu">
        <button class="hamburger" id="mobileMenuClose">
            <i class="bi bi-x-lg"></i>
        </button>
        <div class="logo-container">
            <img src="../Images/logo.jpeg" alt="Logo" style="width: 40px; height: 40px; margin-right: 10px;">
            <h2>Admin Dashboard</h2>
        </div>
        <nav>
            <ul class="sidebar-nav">
                <li><a href="#" class="active"><i class="bi bi-house"></i> Dashboard</a></li>
                <li><a href="users.php"><i class="bi bi-people"></i> Users</a></li>
                <li><a href="households.php"><i class="bi bi-building"></i> Households</a></li>
                <li><a href="../backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="dashboard-grid">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo-container">
                <img src="../Images/logo.jpeg" alt="Logo" style="width: 40px; height: 40px; margin-right: 10px;">
                <h2>Admin Dashboard</h2>
            </div>
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="#" class="active"><i class="bi bi-house"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="bi bi-people"></i> Users</a></li>
                    <li><a href="households.php"><i class="bi bi-building"></i> Households</a></li>
                    <li><a href="../backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <h1>Admin Dashboard Overview</h1>

            <div class="stats-grid">
                <!-- Stats cards remain the same -->
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Total Users</p>
                        <div class="trend-indicator trend-up">
                            <i class="bi bi-arrow-up"></i>
                            <span>+<?php echo number_format($stats['new_users']); ?> this month</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon households">
                        <i class="bi bi-building"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_households']); ?></h3>
                        <p>Total Households</p>
                        <div class="trend-indicator trend-up">
                            <i class="bi bi-arrow-up"></i>
                            <span>+<?php echo number_format($stats['new_households']); ?> this month</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Registrations Section -->
            <div class="recent-registrations">
                <h2>Recent Registrations</h2>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="registrations-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Name</th>
                                    <th>Email/Code</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody id="registrationsTableBody">
                                <?php foreach ($recentRegistrations as $registration): ?>
                                    <tr>
                                        <td>
                                            <span class="registration-type type-<?php echo $registration['type']; ?>">
                                                <?php echo ucfirst($registration['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($registration['name']); ?></td>
                                        <td><?php echo htmlspecialchars($registration['email']); ?></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($registration['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Enhanced Mobile Menu
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mobileMenuClose = document.getElementById('mobileMenuClose');
            const mobileMenu = document.getElementById('mobileMenu');
            const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

            const toggleMenu = (show) => {
                const menuState = show ? 'add' : 'remove';
                mobileMenu.classList[menuState]('active');
                mobileMenuOverlay.classList[menuState]('active');
                document.body.style.overflow = show ? 'hidden' : '';
            };

            [mobileMenuToggle, mobileMenuClose, mobileMenuOverlay].forEach(element => {
                element?.addEventListener('click', () => toggleMenu(!mobileMenu.classList.contains('active')));
            });
        });
    </script>
</body>
</html>