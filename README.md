# 🏠 Household Shopping Manager

## 📝 Project Overview

Household Shopping Manager is a web-based application designed to help families and households manage their inventory, shopping lists, and collaborative household tasks efficiently.

### 🌟 Key Features

- User Authentication (Registration/Login)
- Household Creation and Management
- Inventory Tracking
- Shopping List Management
- Member Collaboration
- Responsive Design
- Dark Mode UI

## 🚀 Technologies Used

### Frontend
- HTML5
- CSS3
- Bootstrap 5
- JavaScript

### Backend
- PHP 8.0+
- MySQL
- PDO for Database Interaction

### Security Features
- Password Hashing (Argon2ID)
- Input Sanitization
- CSRF Protection
- Session Management

## 🔧 Prerequisites

- PHP 8.0+
- MySQL 5.7+
- Apache/Nginx Web Server
- Composer (Dependency Management)

## 📦 Installation

### 1. Clone the Repository
- git clone https://github.com/Mariaolotira/Household-Shopping-Manager
- cd household-shopping-manager

### 2. Database Setup
Create Database Schema if you can't access the sql file inside the project folder.
Import the housemanager.sql to your database operator and update the connect.php inside the backend folder with the right configurations(it's better if use a .env file for security).

sql
-- Home Table
CREATE TABLE home (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    home_id INT,
    FOREIGN KEY (home_id) REFERENCES home(id) ON DELETE SET NULL
);

-- Inventory Table
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    home_id INT NOT NULL,
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (home_id) REFERENCES home(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

### 3. Configure Database Connection
- Create backend/connect.php:

php

<?php
try {
    $host = 'localhost';
    $dbname = 'household_shopping';
    $username = 'root';
    $password = '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

### 4. Project Structure

household-shopping-manager/
│
├── backend/
│   ├── connect.php
│   ├── login.php
│   ├── register.php
│   ├── create_home.php
│   └── logout.php
│
├── user/
│   ├── dashboard.php
│   ├── inventory.php
│   ├── shopping-list.php
│   └── members.php
│
├── assets/
│   ├── css/
│   └── js/
│
└── index.php

## 🔐 Security Best Practices
- Use HTTPS
- Implement input validation
- Use prepared statements
- Hash passwords with Argon2ID
- Implement proper session management


## 🌐 Responsive Design
The application features:

- Mobile-friendly hamburger menu
- Adaptive layouts
- Touch-friendly interfaces
- Dark mode UI


## 🚧 Roadmap
Upcoming Features
 - Real-time collaboration
 - Push notifications
 - Advanced inventory reporting
 - Mobile app companion


## 🤝 Contributing
- Fork the repository
- Create a feature branch
- git checkout -b feature/AmazingFeature
- Commit your changes
- git commit -m 'Add some AmazingFeature'
- Push to the branch
- git push origin feature/AmazingFeature
- Open a Pull Request


## 🛠️Development Setup
- Local Development
- Install XAMPP/WAMP/MAMP
- Clone repository to htdocs
- Configure database
- Run in local server
- Testing
- Use PHPUnit for backend testing
- Manual testing for frontend interactions


## 📊 Performance Optimization
- Use PHP opcache
- Implement database indexing
- Minimize database queries
- Use caching mechanisms


## 🔍 Debugging
- Enable error reporting in development
php

error_reporting(E_ALL);
ini_set('display_errors', 1);
Use browser developer tools
Check server error logs


## 📄 License
- Distributed under the MIT License.

📞 Contact
- Project Link: [GitHub Repository](https://github.com/Mariaolotira/Household-Shopping-Manager)
- Email: mariaolotira@gmail.com
- Phone: +254748927062

🙏 Acknowledgements
- Bootstrap
- PHP-PDO
- Font Awesome
- Modern Normalize CSS


# Happy Household Management! 🏡✨
