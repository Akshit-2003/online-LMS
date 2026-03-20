<?php
session_start();
require_once "../client/db_connect.php";  // include connection

$errors = [];
$success_message = '';

// Check for registration success
if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $success_message = "Registration successful! Please log in to continue.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"] ?? '');
    $password_form = $_POST["password"] ?? '';

    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ? AND role = 'admin'");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
     $stmt->bind_result($user_id, $user_name, $hashed_password, $user_role);
        $stmt->fetch(); // The role is implicitly 'admin' due to the WHERE clause

        if (password_verify($password_form, $hashed_password)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $user_name;
            $_SESSION['user_role'] = 'admin'; // Set role to admin on successful login
            $stmt->close();
            $conn->close();

           // Instead of role check, just send to dashboard
header("Location: index.php");

            exit();
        } else {
            $errors[] = "Invalid email or password.";
        }
    } else {
        $errors[] = "Invalid email or password.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>L.M.S Login</title>
    <link rel="stylesheet" href="../client/style.css">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
</head>
<body>
    <!-- Add this to the <body> of each page, ideally just after <body> tag -->
<div id="pageLoader" class="page-loader">
    <div class="loader-spinner"></div>
</div>

<script>
(function(){
  // Show/hide loader by toggling a class on the body
  function showLoader() {
    document.body.classList.add('is-loading');
  }
  function hideLoader() {
    document.body.classList.remove('is-loading');
  }

  // Intercept all <a> clicks (except special cases)
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a[href]').forEach(function(link){
      link.addEventListener('click', function(e){
        const href = link.getAttribute('href');
        const target = link.getAttribute('target');
        // Don't show loader for same-page links, new tabs, or downloads
        if (
          href &&
          !href.startsWith('#') &&
          !link.hasAttribute('download') &&
          target !== '_blank'
        ) {
          showLoader();
        }
      });
    });

    // Intercept form submits
    document.querySelectorAll('form').forEach(function(form){
      form.addEventListener('submit', function(e){
        showLoader();
      });
    });
    // Hide loader after page load
    // 'pageshow' is better than 'load' for back/forward button navigation
    window.addEventListener('pageshow', hideLoader);

    // As a fallback, ensure it's hidden on initial load
    hideLoader();
  });
})();
</script>
    <nav class="navbar">
        <div class="nav-logo">
            <img src="../images/logo.jpg" alt="L.M.S Logo" class="nav-logo-img" />
            L.M.S
        </div>
        <ul class="nav-links">
            <li><a href="../index.html">Home</a></li>
            <li><a href="#courses">Courses</a></li>
            <li><a href="#about">About Us</a></li>
            <li><a href="#contact">Contact</a></li>
            <li><a href="login.php" class="active">Login</a></li>
            <li><a href="register.php">Register</a></li>
        </ul>
    </nav>
    <div class="login-container">
        <form class="login-form" method="POST" action="login.php">
            <?php if (!empty($success_message)): ?>
                <div class="form-messages success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="form-messages error">
                    <strong>Login failed:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <h2>Login to L.M.S</h2>
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" >Login</button>
            <div class="login-links">
                <a href="#">Forgot Password?</a>
                <span>|</span>
                <a href="register.php">Create Account</a>
            </div>
        </form>
        <div class="login-image">
            <img src="../images/Account concept illustration _ Free Vector.jpg" alt="Login Illustration">
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
                    <li><a href="../index.html">Home</a></li>
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