<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';
$user_id = $_SESSION['user_id'];

// --- Form Handling ---

$message = '';
$message_type = ''; // 'success' or 'error'

// A real application would require a database connection, which is now included.
// e.g., include_once '../includes/db.php';
// And user settings would be fetched and updated in the database.

// Change Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $message = "Please fill in all password fields.";
        $message_type = 'error';
    } elseif ($new_password !== $confirm_new_password) {
        $message = "New passwords do not match.";
        $message_type = 'error';
    } else {
        // In a real app:
        // 1. Fetch user's current hashed password from DB.
        // 2. Verify $current_password with password_verify().
        // 3. If correct, hash $new_password with password_hash().
        // 4. Update the new hashed password in the DB.
        $message = "Password updated successfully! (Demo)";
        $message_type = 'success';
    }
}

// Notification Preferences
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_preferences'])) {
    // In a real app, you would save these settings to the database for the user.
    $message = "Notification preferences saved!";
    $message_type = 'success';
}

// Profile Visibility
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_visibility'])) {
    // In a real app, save visibility to the database for the user.
    $message = "Profile visibility updated!";
    $message_type = 'success';
}

// Theme Settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_theme'])) {
    $_SESSION['theme'] = $_POST['theme'] ?? 'light';
    $message = "Theme applied! You might need to reload other pages to see the change.";
    $message_type = 'success';
}

// Language and Region
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_language_region'])) {
    $_SESSION['language'] = $_POST['language'] ?? 'en';
    $message = "Language settings saved!";
    $message_type = 'success';
}

// Delete Account
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'DELETE') {
        // This is a destructive action. In a real app, you might add more confirmation steps.
        $message = "Account deletion process initiated. (Demo)";
        $message_type = 'success';
    } else {
        $message = "You must type 'DELETE' in the confirmation box.";
        $message_type = 'error';
    }
}

$avatar_path = '../images/user.jpg'; // Default avatar
$stmt_avatar = $conn->prepare("SELECT avatar_path FROM users WHERE id = ?");
$stmt_avatar->bind_param("i", $user_id);
$stmt_avatar->execute();
$result_avatar = $stmt_avatar->get_result();
if($row = $result_avatar->fetch_assoc()) { $avatar_path = $row['avatar_path'] ?? $avatar_path; }
$stmt_avatar->close();
$conn->close();

// Get current theme and language from session for rendering
$current_theme = $_SESSION['theme'] ?? 'light';
$current_language = $_SESSION['language'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_language); ?>">
<head>
    <meta charset="UTF-8">
    <title>Settings - L.M.S</title>
    <link rel="stylesheet" href="../client/style.css">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <style>
        /* Theme Styles */
        body.theme-dark { background: #22272e; color: #adbac7; }
        body.theme-dark .navbar, body.theme-dark .sidebar { background: #1c2128; }
        body.theme-dark .settings-section { background: #1c2128; box-shadow: 0 4px 24px #00000044; }
        body.theme-dark .settings-section h2, body.theme-dark .settings-form label { color: #28a745; }
        body.theme-dark .settings-form input, body.theme-dark .settings-form select { background: #22272e; border-color: #444c56; color: #cdd9e5; }
        /* General Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .main-content {
            flex: 1;
            padding: 40px 5vw;
            animation: fadeIn 1s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px);}
            to { opacity: 1; transform: none;}
        }

        /* Sidebar */
        .sidebar {
            background: #333;
            color: #fff;
            width: 240px;
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 2px 0 16px rgba(40,167,69,0.08);
        }
        .sidebar-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 16px;
            border: 3px solid #28a745;
            object-fit: cover;
            transition: box-shadow 0.3s;
        }
        .sidebar-logo:hover {
            box-shadow: 0 0 0 6px #28a74544;
        }
        .sidebar h2 {
            font-size: 22px;
            margin-bottom: 30px;
            color: #28a745;
        }
        .sidebar-nav {
            width: 100%;
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-nav li {
            margin: 18px 0;
        }
        .sidebar-nav a {
            color: #fff;
            text-decoration: none;
            font-size: 1.1rem;
            padding: 10px 18px;
            border-radius: 8px;
            display: block;
            transition: background 0.3s, transform 0.2s;
        }
        .sidebar-nav a.active,
        .sidebar-nav a:hover {
            background: #28a745;
            color: #fff;
            transform: scale(1.05);
        }

        /* Settings Section & Form */
        .settings-section {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px #28a74522;
            padding: 36px 48px;
            max-width: 600px;
            margin: 0 auto 40px auto;
            animation: fadeIn 0.8s ease;
        }
        .settings-section h2 {
            color: #28a745;
            margin-bottom: 24px;
            font-size: 1.6rem;
            border-bottom: 2px solid #28a74533;
            padding-bottom: 8px;
        }
        .settings-form {
            display: flex;
            flex-direction: column;
        }
        .settings-form label {
            margin-bottom: 12px;
            color: #218838;
            font-weight: 600;
            font-size: 1rem;
        }
        .settings-form input[type="password"],
        .settings-form input[type="text"],
        .settings-form select {
            width: 100%;
            padding: 10px 14px;
            border-radius: 6px;
            border: 1px solid #ccc;
            margin-bottom: 18px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .settings-form input:focus,
        .settings-form select:focus {
            border-color: #28a745;
            outline: none;
        }
        .settings-form input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        .settings-form button {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            align-self: flex-start;
            transition: background 0.3s, transform 0.2s;
        }
        .settings-form button:hover {
            background: #218838;
            transform: scale(1.03);
        }
        .settings-form button[name="delete_account"] {
            background: #dc3545;
        }
        .settings-form button[name="delete_account"]:hover {
            background: #c82333;
        }

        /* Message Styles */
        .message {
            padding: 15px;
            margin: 0 auto 20px auto;
            border-radius: 8px;
            max-width: 600px;
            text-align: center;
            font-weight: bold;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body class="theme-<?php echo htmlspecialchars($current_theme); ?>">
    <!-- Add this to the <body> of each page, ideally just after <body> tag -->
<div id="pageLoader" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(255,255,255,0.7);z-index:99999;align-items:center;justify-content:center;">
  <div style="background:#fff;padding:32px 40px;border-radius:16px;box-shadow:0 8px 32px #28a74522;display:flex;flex-direction:column;align-items:center;">
    <svg width="48" height="48" viewBox="0 0 48 48" style="margin-bottom:12px;">
      <circle cx="24" cy="24" r="20" stroke="#28a745" stroke-width="4" fill="none" stroke-dasharray="100" stroke-dashoffset="60">
        <animateTransform attributeName="transform" type="rotate" from="0 24 24" to="360 24 24" dur="1s" repeatCount="indefinite"/>
      </circle>
    </svg>
    <span style="color:#28a745;font-weight:bold;font-size:1.2em;">Loading...</span>
  </div>
</div>

<script>
(function(){
  // Show loader on navigation
  function showLoader() {
    document.getElementById('pageLoader').style.display = 'flex';
  }
  function hideLoader() {
    document.getElementById('pageLoader').style.display = 'none';
  }

  // Intercept all <a> clicks (except target="_blank" or download)
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a[href]').forEach(function(link){
      link.addEventListener('click', function(e){
        const href = link.getAttribute('href');
        if (
          href &&
          !href.startsWith('#') &&
          !link.hasAttribute('download') &&
          (!link.target || link.target === '_self')
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
    window.addEventListener('pageshow', hideLoader);
    window.addEventListener('load', hideLoader);
  });

  // For SPA navigation or AJAX, call showLoader() before navigation and hideLoader() after load.
})();
</script>
   <nav class="navbar">
        <div class="nav-logo">
            <img src="../images/logo.jpg" alt="L.M.S Logo" class="nav-logo-img" />
            L.M.S
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="courses.php">My Courses</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <div class="dashboard-container">
        <aside class="sidebar">
            <img src="<?php echo htmlspecialchars($avatar_path ?? '../images/user.jpg'); ?>" alt="User Avatar" class="sidebar-logo user-avatar active" />
            <h2>L.M.S</h2>
            <ul class="sidebar-nav">
            <li><a href="dashboard.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='dashboard.php') echo 'active'; ?>">Dashboard</a></li>
          <li><a href="my_enrollments.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='my_enrollments.php') echo 'active'; ?>" >My Courses</a></li>
         <li><a href="courses.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='courses.php') echo 'active'; ?>">Course Catalog</a></li>
        <li><a href="profile.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='profile.php') echo 'active'; ?>">Profile</a></li>
        <li><a href="settings.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='settings.php') echo 'active'; ?>">Settings</a></li>
        <li><a href="logout.php">Logout</a></li>
            </ul>
        </aside>
        <main class="main-content">
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($message_type); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="settings-section">
                <h2>Change Password</h2>
                <form class="settings-form" method="post" action="">
                    <label for="current-password">Current Password</label>
                    <input type="password" id="current-password" name="current_password" required>

                    <label for="new-password">New Password</label>
                    <input type="password" id="new-password" name="new_password" required>

                    <label for="confirm-new-password">Confirm New Password</label>
                    <input type="password" id="confirm-new-password" name="confirm_new_password" required>

                    <button type="submit" name="change_password">Change Password</button>
                </form>
            </div>

            <div class="settings-section">
                <h2>Notification Preferences</h2>
                <form class="settings-form" method="post" action="">
                    <label><input type="checkbox" name="email_notifications" checked> Email Notifications</label>
                    <label><input type="checkbox" name="sms_alerts"> SMS Alerts</label>
                    <label><input type="checkbox" name="course_updates" checked> Course Updates</label>
                    <button type="submit" name="save_preferences">Save Preferences</button>
                </form>
            </div>

            <div class="settings-section">
                <h2>Profile Visibility</h2>
                <form class="settings-form" method="post" action="">
                    <label for="visibility">Who can see your profile?</label>
                    <select id="visibility" name="visibility">
                        <option value="everyone">Everyone</option>
                        <option value="students">Only Students</option>
                        <option value="private">Only Me</option>
                    </select>
                    <button type="submit" name="update_visibility">Update Visibility</button>
                </form>
            </div>

            <div class="settings-section">
                <h2>Theme Settings</h2>
                <form class="settings-form" method="post" action="">
                    <label for="theme">Choose Theme</label>
                    <select id="theme" name="theme" aria-label="Choose Theme">
                        <option value="light" <?php if ($current_theme == 'light') echo 'selected'; ?>>Light</option>
                        <option value="dark" <?php if ($current_theme == 'dark') echo 'selected'; ?>>Dark</option>
                        <option value="green" <?php if ($current_theme == 'green') echo 'selected'; ?>>Green LMS</option>
                    </select>
                    <button type="submit" name="apply_theme">Apply Theme</button>
                </form>
            </div>

            <div class="settings-section">
                <h2>Language & Region</h2>
                <form class="settings-form" method="post" action="">
                    <label for="language">Language</label>
                    <select id="language" name="language" aria-label="Select Language">
                        <option value="en" <?php if ($current_language == 'en') echo 'selected'; ?>>English</option>
                        <option value="es" <?php if ($current_language == 'es') echo 'selected'; ?>>Español (Spanish)</option>
                        <option value="fr" <?php if ($current_language == 'fr') echo 'selected'; ?>>Français (French)</option>
                    </select>
                    <button type="submit" name="save_language_region">Save Changes</button>
                </form>
            </div>

            <div class="settings-section">
                <h2>Delete Account</h2>
                <form class="settings-form" method="post" action="">
                    <label for="confirm_delete">Type "DELETE" to confirm</label>
                    <input type="text" id="confirm_delete" name="confirm_delete" placeholder="DELETE">
                    <button type="submit" name="delete_account">Delete Account</button>
                </form>
            </div>
        </main>
    </div>
</body>
<script src="../client/script.js"></script>
</html>