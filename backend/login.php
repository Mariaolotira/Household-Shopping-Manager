<?php
session_start();
require_once 'connect.php';

// Input Validation Function
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Login Process
function loginUser($pdo, $email, $password) {
    try {
        // Validate and sanitize email
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new Exception("Invalid email format");
        }

        // Prepare SQL statement to fetch user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if user exists
        if (!$user) {
            throw new Exception("No account found with this email");
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            throw new Exception("Incorrect password");
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Store user information in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_phone'] = $user['phone'];
        $_SESSION['user_home'] = $user['home_id'];
        $_SESSION['is_admin'] = $user['is_admin'];

        // Redirect based on user role
        if ($user['is_admin']) {
            header("Location: ../admin/dashboard.php");
        } else {
            header("Location: ../user/dashboard.php");
        }
        exit();

    } catch (Exception $e) {
        // Store error message in session
        $_SESSION['login_error'] = $e->getMessage();
        
        // Redirect back to login page
        header("Location: ../login.php");
        exit();
    }
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and validate form data
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate inputs are not empty
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "Email and password are required";
        header("Location: ../login.php");
        exit();
    }

    // Attempt to log in the user
    loginUser($pdo, $email, $password);
}
?>