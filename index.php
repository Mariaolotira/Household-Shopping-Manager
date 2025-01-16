<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Shopping Manager</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- AOS (Animate on Scroll) Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-dark: #1a1a2e;
            --secondary-dark: #16213e;
            --accent-color: #0f3460;
            --text-color: #e94560;
            --light-text: #f4f4f4;
        }

        body {
            background-color: var(--primary-dark);
            color: var(--light-text);
            font-family: 'Arial', sans-serif;
        }

        .navbar {
            background-color: var(--secondary-dark);
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-dark), var(--accent-color));
            color: var(--light-text);
            padding: 100px 0;
            text-align: center;
        }

        .feature-card {
            background-color: var(--secondary-dark);
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: scale(1.05);
        }

        .cta-button {
            background-color: var(--text-color);
            color: var(--light-text);
            border: none;
        }

        .cta-button:hover {
            background-color: #ff6b81;
        }

        .contact-section {
            background-color: var(--secondary-dark);
            padding: 50px 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shopping-cart"></i> Household Shopping Manager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container" data-aos="fade-up">
            <h1 class="display-4">Smart Household Shopping Management</h1>
            <p class="lead">Track, Manage, and Never Run Out of Essentials Again!</p>
            <a href="register.php" class="btn btn-lg cta-button mt-4">
                Create Your Household Account
            </a>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Key Features</h2>
            <div class="row">
                <div class="col-md-4" data-aos="fade-right">
                    <div class="feature-card text-center">
                        <i class="fas fa-list-alt fa-3x mb-3"></i>
                        <h3>Inventory Tracking</h3>
                        <p>Real-time tracking of household items and their quantities.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up">
                    <div class="feature-card text-center">
                        <i class="fas fa-bell fa-3x mb-3"></i>
                        <h3>Low Stock Alerts</h3>
                        <p>Get notified when items are running low.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-left">
                    <div class="feature-card text-center">
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <h3>Multi-User Support</h3>
                        <p>Share and manage household inventory with family members.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="bg-dark py-5">
        <div class="container">
            <h2 class="text-center mb-5">About Our System</h2>
            <div class="row">
                <div class="col-md-6" data-aos="">
                    <img src="images/th.jpeg" alt="System Illustration" class="img-fluid rounded">
                </div>
                <div class="col-md-6" data-aos="">
                    <p>
                        Our Household Shopping Manager is designed to simplify your home inventory management. 
                        Never worry about running out of essential items again. Track, manage, and share 
                        your household inventory seamlessly.
                    </p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Easy to Use</li>
                        <li><i class="fas fa-check text-success"></i> Real-time Tracking</li>
                        <li><i class="fas fa-check text-success"></i> Multi-device Support</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section">
        <div class="container">
            <h2 class="text-center mb-5">Contact Us</h2>
            <div class="row">
                <div class="col-md-6 offset-md-3">
                    <form>
                        <div class="mb-3">
                            <input type="text" class="form-control" placeholder="Your Name">
                        </div>
                        <div class="mb-3">
                            <input type="email" class="form-control" placeholder="Your Email">
                        </div>
                        <div class="mb-3">
                            <textarea class="form-control" rows="5" placeholder="Your Message"></textarea>
                        </div>
                        <button type="submit" class="btn cta-button w-100">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-center py-3">
        <p>&copy; 
            <span id="copyright">
                <script>
                    document.getElementById("copyright").innerHTML = new Date().getFullYear();
                </script>
            </span>
        Household Shopping Manager. All Rights Reserved.</p>
        <p>Designed and developed by <a href="#">Mariao Lotira</a></p>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3. 0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>
</html>