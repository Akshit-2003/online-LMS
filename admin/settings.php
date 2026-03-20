<?php
session_start();



// --- Config & Helpers ---
$config_file = __DIR__ . '/../config.php';
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function active_class($page_name) {
    if (basename($_SERVER['PHP_SELF']) == $page_name) {
        echo 'class="active"';
    }
}

function load_config($path) {
    $defaults = [
        'site_title' => 'L.M.S', 'site_logo' => '../images/logo.jpg',
        'contact_email' => 'support@lms.com', 'contact_phone' => '', 'contact_address' => '',
        'admin_secret_key' => '12345', 'maintenance_mode' => false,
    ];
    if (file_exists($path)) {
        // array_merge will overwrite defaults with saved values
        return array_merge($defaults, include $path);
    }
    return $defaults;
}

$config = load_config($config_file);
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

// --- Handle Form Submission (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update config from POST data, preserving existing values if not set
    $config['site_title'] = trim($_POST['site_title'] ?? $config['site_title']);
    $config['contact_email'] = trim($_POST['contact_email'] ?? $config['contact_email']);
    $config['contact_phone'] = trim($_POST['contact_phone'] ?? $config['contact_phone']);
    $config['contact_address'] = trim($_POST['contact_address'] ?? $config['contact_address']);
    $config['admin_secret_key'] = trim($_POST['admin_secret_key'] ?? $config['admin_secret_key']);
    $config['maintenance_mode'] = isset($_POST['maintenance_mode']);

    // Save back to file
    $output = "<?php\n\n// LMS Configuration File - Managed from the Admin Settings page.\n\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($config_file, $output, LOCK_EX)) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Settings updated successfully!'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error saving settings. Check file permissions for config.php.'];
    }
    header("Location: settings.php");
    exit();
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../client/style.css" />
  <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon" />
  <title>Settings • Admin</title>
  <style>
    :root {
        --primary: #0d6efd; --success: #198754; --light: #f4f7f9;
        --dark: #1e2227; --text-muted: #6c757d; --bg-surface: #fff;
        --border-color: #dee2e6;
    }
    body { background: var(--light); font-family: 'Segoe UI', sans-serif; color: #333; }
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar { background: var(--dark); color: #fff; width: 260px; display: flex; flex-direction: column; }
    .sidebar-brand { padding: 20px; display: flex; align-items: center; gap: 12px; background: #343a40; }
    .sidebar-brand img { width: 40px; height: 40px; border-radius: 50%; }
    .sidebar-brand-text { font-size: 1.2rem; font-weight: 600; }
    .sidebar-nav { flex-grow: 1; padding: 20px 0; }
    .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: #adb5bd; text-decoration: none; transition: background 0.2s, color 0.2s; border-left: 4px solid transparent; }
    .sidebar-nav a:hover { background: #343a40; color: #fff; }
    .sidebar-nav a.active { background: var(--primary); color: #fff; border-left-color: #fff; font-weight: 600; }
    .sidebar-nav a .icon { width: 20px; height: 20px; }
    .main-content { flex: 1; padding: 40px; }
    .page-header { margin-bottom: 30px; }
    .page-header h1 { color: #343a40; font-size: 2.2rem; margin:0; }
    .settings-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); max-width: 800px; margin: 0 auto; }
    .settings-section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color); }
    .settings-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
    .settings-section h2 { margin-top: 0; color: var(--dark); font-size: 1.4rem; }
    .input-group { margin-bottom: 20px; }
    .input-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #495057; }
    .input-group input { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; }
    .form-actions { text-align: right; }
    .btn { padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; }
    .btn-success { background: var(--success); color: #fff; }
    .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .message.success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .message.error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
    .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--primary); }
    input:checked + .slider:before { transform: translateX(26px); }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <img src="<?php echo esc($config['site_logo']); ?>" alt="logo">
        <span class="sidebar-brand-text"><?php echo esc($config['site_title']); ?> Admin</span>
      </div>
      <nav class="sidebar-nav" aria-label="admin navigation">
        <a href="index.php" <?php active_class('index.php'); ?>>
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
            <span>Dashboard</span>
        </a>
        <a href="manage_courses.php" <?php active_class('manage_courses.php'); ?>>
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            <span>Courses</span>
        </a>
        <a href="users.php" <?php active_class('users.php'); ?>>
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197" /></svg>
            <span>Users</span>
        </a>
        <a href="certification.php" <?php active_class('certification.php'); ?>>
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg>
            <span>Certification</span>
        </a>
        <a href="submissions.php" <?php active_class('submissions.php'); ?>>
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
            <span>Submissions</span>
        </a>
        <a href="settings.php" <?php active_class('settings.php'); ?>>
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            <span>Settings</span>
        </a>
        <a href="../client/index.html" target="_blank">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
            <span>View Site</span>
        </a>
        <a href="logout.php">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
            <span>Logout</span>
        </a>
      </nav>
    </aside>

    <main class="main-content">
      <header class="page-header">
        <h1>Admin Settings</h1>
      </header>

      <?php if ($message): ?>
        <div class="message <?php echo esc($message['type']); ?>"><?php echo esc($message['text']); ?></div>
      <?php endif; ?>

      <form class="settings-card" method="POST" action="settings.php">
        <div class="settings-section">
          <h2>Site Information</h2>
          <div class="input-group">
            <label for="site_title">Site Title</label>
            <input type="text" id="site_title" name="site_title" value="<?php echo esc($config['site_title']); ?>">
          </div>
          <div class="input-group">
            <label for="contact_email">Contact Email</label>
            <input type="email" id="contact_email" name="contact_email" value="<?php echo esc($config['contact_email']); ?>">
          </div>
          <div class="input-group">
            <label for="contact_phone">Contact Phone</label>
            <input type="text" id="contact_phone" name="contact_phone" value="<?php echo esc($config['contact_phone']); ?>">
          </div>
          <div class="input-group">
            <label for="contact_address">Contact Address</label>
            <input type="text" id="contact_address" name="contact_address" value="<?php echo esc($config['contact_address']); ?>">
          </div>
        </div>

        <div class="settings-section">
          <h2>Security & Maintenance</h2>
          <div class="input-group">
            <label for="admin_secret_key">Admin Registration Secret Key</label>
            <input type="text" id="admin_secret_key" name="admin_secret_key" value="<?php echo esc($config['admin_secret_key']); ?>">
          </div>
          <div class="input-group">
            <label for="maintenance_mode">Enable Maintenance Mode</label>
            <label class="switch">
              <input type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php if ($config['maintenance_mode']) echo 'checked'; ?>>
              <span class="slider"></span>
            </label>
            <small style="display:block; margin-top:8px; color:var(--text-muted);">When enabled, non-admin users will see a maintenance page instead of the main site.</small>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-success">Save Settings</button>
        </div>
      </form>
    </main>
  </div>
</body>
</html>