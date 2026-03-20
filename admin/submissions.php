<?php
session_start();
// --- Helpers & Config ---
function active_class($page_name) {
    if (basename($_SERVER['PHP_SELF']) == $page_name) {
        echo 'class="active"';
    }
}
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

// --- Handle Reply Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply_to = $_POST['reply_to_email'] ?? null;
    $reply_message = $_POST['reply_message'] ?? '';
    $original_subject = $_POST['original_subject'] ?? 'Your inquiry';

    if ($reply_to && filter_var($reply_to, FILTER_VALIDATE_EMAIL) && !empty($reply_message)) {
        $subject = "Re: " . $original_subject;
        // It's best practice to use a consistent "From" address from your domain.
        $headers = "From: LMS Support <support@lms.com>\r\nReply-To: support@lms.com\r\n";
        $body = "Hello,\n\nThis is a reply to your recent inquiry submitted to our LMS.\n\n---\n" . $reply_message . "\n---\n\nRegards,\nThe L.M.S Team";

        if (@mail($reply_to, $subject, $body, $headers)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Reply sent successfully to ' . esc($reply_to)];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to send email. Please check server mail configuration.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Could not send reply. Invalid email or empty message.'];
    }
    header("Location: " . $_SERVER['PHP_SELF'] . '?view=' . urlencode($_GET['view'] ?? ''));
    exit();
}

$submissions_dir = __DIR__ . '/../client/submissions';
$log_files = is_dir($submissions_dir) ? glob($submissions_dir . '/contacts_*.log') : [];
usort($log_files, function($a, $b) {
    return filemtime($b) <=> filemtime($a); // Sort by most recent
});

$view_file = $_GET['view'] ?? null;
$file_content = null;
$user_email = null;
$user_subject = null;

if ($view_file && in_array($submissions_dir . '/' . basename($view_file), $log_files)) {
    $content = file_get_contents($submissions_dir . '/' . basename($view_file));
    if ($content !== false) {
        $file_content = esc($content);

        // Parse email and subject for the reply form
        if (preg_match('/Email:\s*(.+)/', $content, $matches)) {
            $user_email = trim($matches[1]);
        }
        if (preg_match('/Subject:\s*(.+)/', $content, $matches)) {
            $user_subject = trim($matches[1]);
        } else {
            $user_subject = 'Contact Form Submission';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../client/style.css" />
  <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon" />
  <title>Submissions • Admin</title>
  <style>
    :root { --primary: #0d6efd; --success: #198754; --light: #f4f7f9; --dark: #1e2227; --text-muted: #6c757d; --bg-surface: #fff; --border-color: #dee2e6; }
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
    .submissions-layout { display: grid; grid-template-columns: 300px 1fr; gap: 24px; }
    .file-list-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 20px; }
    .file-list-card h3 { margin-top: 0; }
    .file-list { list-style: none; padding: 0; margin: 0; }
    .file-list a { display: block; padding: 10px; border-radius: 6px; text-decoration: none; color: var(--primary); }
    .file-list a:hover { background: #e9ecef; }
    .file-list a.current { background: var(--primary); color: #fff; font-weight: 600; }
    .log-view-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 20px; }
    .log-view { background: #212529; color: #f8f9fa; padding: 20px; border-radius: 8px; font-family: 'Courier New', Courier, monospace; white-space: pre-wrap; word-break: break-all; max-height: 70vh; overflow-y: auto; }
  </style>
  <style>
    /* Reply Form Styles */
    .reply-form-card { background: #f8f9fa; border-radius: 12px; padding: 20px; margin-top: 24px; border: 1px solid var(--border-color); }
    .reply-form-card h3 { margin-top: 0; color: var(--dark); }
    .reply-form-card textarea { width: 100%; min-height: 120px; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); font-family: inherit; font-size: 1rem; margin-bottom: 12px; }
    .reply-form-card button { background: var(--success); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .message.success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .message.error { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <img src="../images/logo.jpg" alt="logo">
        <span class="sidebar-brand-text">L.M.S Admin</span>
      </div>
      <nav class="sidebar-nav" aria-label="admin navigation">
        <a href="index.php" <?php active_class('index.php'); ?>><svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg><span>Dashboard</span></a>
        <a href="manage_courses.php" <?php active_class('manage_courses.php'); ?>><svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg><span>Courses</span></a>
        <a href="users.php" <?php active_class('users.php'); ?>><svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197" /></svg><span>Users</span></a>
        <a href="certification.php" <?php active_class('certification.php'); ?>><svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg><span>Certification</span></a>
        <a href="submissions.php" <?php active_class('submissions.php'); ?>><svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg><span>Submissions</span></a>
        <a href="settings.php" <?php active_class('settings.php'); ?>><svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg><span>Settings</span></a>
        <a href="logout.php"><svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg><span>Logout</span></a>
      </nav>
    </aside>

    <main class="main-content">
      <header class="page-header">
        <h1>Contact Form Submissions</h1>
      </header>

      <?php if ($message): ?>
        <div class="message <?php echo esc($message['type']); ?>"><?php echo esc($message['text']); ?></div>
      <?php endif; ?>

      <div class="submissions-layout">
        <aside class="file-list-card">
          <h3>Submission Logs</h3>
          <?php if (empty($log_files)): ?>
            <p>No submission files found in <code>client/submissions/</code>.</p>
          <?php else: ?>
            <ul class="file-list">
              <?php foreach ($log_files as $file): ?>
                <li>
                  <a href="?view=<?php echo urlencode(basename($file)); ?>" class="<?php if (basename($file) === $view_file) echo 'current'; ?>">
                    <?php echo esc(basename($file)); ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </aside>

        <section class="log-view-card">
          <?php if ($file_content): ?>
            <h3>Viewing: <?php echo esc($view_file); ?></h3>
            <pre class="log-view"><?php echo $file_content; ?></pre>

            <?php if ($user_email): ?>
              <div class="reply-form-card">
                <h3>Reply to <?php echo esc($user_email); ?></h3>
                <form method="POST" action="submissions.php?view=<?php echo urlencode($view_file); ?>">
                  <input type="hidden" name="reply_to_email" value="<?php echo esc($user_email); ?>">
                  <input type="hidden" name="original_subject" value="<?php echo esc($user_subject); ?>">
                  <textarea name="reply_message" rows="6" placeholder="Type your reply here..." required></textarea>
                  <button type="submit" name="send_reply">Send Reply</button>
                </form>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <h3>Select a file to view</h3>
            <p>Please select a log file from the list on the left to view its contents.</p>
          <?php endif; ?>
        </section>
      </div>
    </main>
  </div>
</body>
</html>