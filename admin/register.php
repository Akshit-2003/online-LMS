<?php
session_start();
require_once "../client/db_connect.php";

// Load admin secret key from config file
$config_file = __DIR__ . '/../config.php';
$admin_secret_key = '12345'; // Default fallback
if (file_exists($config_file)) {
    $config = include $config_file;
    $admin_secret_key = $config['admin_secret_key'] ?? $admin_secret_key;
}

$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $admin_key = trim($_POST['admin_key'] ?? '');

    // --- Validation ---
    if (empty($name) || empty($email) || empty($password) || empty($admin_key)) {
        $errors[] = "All fields are required.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if ($admin_key !== $admin_secret_key) {
        $errors[] = "Invalid Admin Secret Key.";
    }

    // Check if email already exists if there are no other errors
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "An account with this email already exists.";
        }
        $stmt->close();
    }

    // --- Create Admin User ---
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user with 'admin' role into the 'users' table
        $role = 'admin';
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
        
        if ($stmt->execute()) {
            // Redirect to login page with a success message
            header("Location: ../client/login.php?registered=success");
            exit();
        } else {
            $errors[] = "Registration failed due to a server error. Please try again.";
        }
        $stmt->close();
    }
    $conn->close();
}

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>L.M.S Register</title>
    <link rel="stylesheet" href="../client/style.css">
    <link rel="shortcut icon" href="" type="image/x-icon">
</head>
<body>
    <nav class="navbar">
        <div class="nav-logo">
            <img src="../images/logo.jpg" alt="L.M.S Logo" class="nav-logo-img" />
            L.M.S
        </div>
        <ul class="nav-links">
            <li><a href="index.html">Home</a></li>
            <li><a href="#courses">Courses</a></li>
            <li><a href="#about">About Us</a></li>
            <li><a href="#contact">Contact</a></li>
            <li><a href="../client/login.php">Login</a></li>
            <li><a href="../client/register.php" class="active">Register</a></li>
        </ul>
    </nav>
    <div class="login-container">
        <form class="login-form" method="POST" action="register.php">
            <h2>Create Your Account</h2>
            <div class="input-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" placeholder="Enter your full name" required>
            </div>
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
            </div>
            <div class="input-group">
                <label for="confirm-password">Confirm Password</label>
                <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm your password" required>
            </div>
            <div class="input-group">
          <label for="admin_key">Admin Secret Key</label>
          <input type="password" id="admin_key" name="admin_key" required>
        </div>
            <button type="submit" onclick="window.location.href='../client/login.php'">Register</button>
            <div class="login-links">
                <a href="login.html"onclick="window.location.href='../client/login.php'">Already have an account?</a>
            </div>
        </form>
        <div class="login-image">
            <img src="../images/Report concept illustration _ Premium Vector.jpg" alt="Register Illustration">
        </div>
    </div>
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-col footer-logo">
                <span>L.M.S</span>
                <p>Your gateway to modern learning.</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.html">Home</a></li>
                    <li><a href="#courses">Courses</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li>Email: <a href="mailto:support@lms.com">support@lms.com</a></li>
                    <li>Phone: <a href="tel:+91 9870534710">+91 9870534710</a></li>
                    <li>Address: 123 Learning Ave, Edutown</li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Newsletter</h4>
                <form class="footer-newsletter">
                    <input type="email" placeholder="Your email" required>
                    <button type="submit">Subscribe</button>
                </form>
                <div class="footer-social">
                    <a href="#" title="Facebook"><img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/facebook.svg" alt="Facebook"></a>
                    <a href="#" title="Twitter"><img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/twitter.svg" alt="Twitter"></a>
                    <a href="#" title="Instagram"><img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/instagram.svg" alt="Instagram"></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; 2025 L.M.S. All rights reserved.</span>
        </div>
    </footer>
    </body>
<script src="../client/script.js"></script>
</html>