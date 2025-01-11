<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'connect.php'; 

// Input Validation Function
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Phone Number Validation
function validatePhoneNumber($phone) {
    // Remove all non-digit characters except '+'
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Check if the phone number is valid (10-14 digits, optional + at start)
    return preg_match('/^\+?[0-9]{10,14}$/', $cleanPhone) ? $cleanPhone : false;
}

// Email Validation
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Check if Email Already Exists
function emailExists($pdo, $email) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetchColumn() > 0;
}

// Registration Process
function registerUser($pdo, $name, $email, $phone, $password) {
    try {
        // Validate inputs
        $name = validateInput($name);
        $email = validateInput($email);
        
        // Validate email
        if (!validateEmail($email)) {
            throw new Exception("Invalid email format");
        }

        // Validate phone number
        $phone = validatePhoneNumber($phone);
        if (!$phone) {
            throw new Exception("Invalid phone number");
        }

        // Check if email already exists
        if (emailExists($pdo, $email)) {
            throw new Exception("Email already registered");
        }

        // Hash the password (use strong hashing)
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

        // Prepare SQL statement
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, is_admin) 
            VALUES (?, ?, ?, ?, ?)
        ");

        // Execute the statement
        $result = $stmt->execute([
            $name, 
            $email, 
            $phone, 
            $hashedPassword, 
            false // Default is not an admin
        ]);

        // Check if registration was successful
        if ($result) {
            $_SESSION['success_message'] = "Registration successful! Please log in.";
            
            // Redirect to login page
            header("Location: ../login.php");
            exit();
        } else {
            throw new Exception("Registration failed");
        }

    } catch (Exception $e) {
        // Handle errors
        $_SESSION['error_message'] = $e->getMessage();
        
        // Redirect back to registration page
        header("Location: ../register.php");
        exit();
    }
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $terms = $_POST['iAgree'] ?? false;

    // Validate terms agreement
    // if (!$terms) {
    //     $_SESSION['error_message'] = "You must agree to the terms and conditions";
    //     header("Location: ../register.php");
    //     exit();
    // }

    // Attempt to register the user
    registerUser($pdo, $name, $email, $phone, $password);
}
?>