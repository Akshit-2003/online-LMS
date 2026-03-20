<?php
session_start();
require_once "../client/db_connect.php";




// --- Helpers & Config ---
function active_class($page_name) {
    if (basename($_SERVER['PHP_SELF']) == $page_name) {
        echo 'class="active"';
    }
}
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$all_courses = [];
$study_content_file = __DIR__ . '/../client/study_content.php';
if (file_exists($study_content_file)) {
    $all_courses = include $study_content_file;
}

// --- Fetch Certifications ---
$certifications = [];
$stmt = $conn->prepare(
    "SELECT u.name AS user_name, u.id AS user_id, uc.course_slug, uc.completion_date
     FROM user_courses uc
     JOIN users u ON uc.user_id = u.id
     WHERE uc.status = 'completed' AND uc.completion_date IS NOT NULL
     ORDER BY uc.completion_date DESC"
);

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $certifications[] = $row;
    }
    $stmt->close();
}
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../client/style.css" />
  <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon" />
  <title>Certifications • Admin</title>
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
    .content-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }
    .content-table th, .content-table td { padding: 16px; text-align: left; border-bottom: 1px solid var(--border-color); }
    .content-table th { background: #f8f9fa; font-weight: 600; color: #495057; }
    .content-table tbody tr:last-child td { border-bottom: none; }
    .content-table tbody tr:hover { background: #f1f3f5; }
    .actions a { margin-right: 10px; color: var(--primary); text-decoration: none; font-weight: 600; }
    .actions a:hover { text-decoration: underline; }
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
        <h1>Course Certifications</h1>
      </header>

      <section>
        <table class="content-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Course Title</th>
              <th>Completion Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($certifications)): ?>
              <tr><td colspan="4">No certifications have been issued yet.</td></tr>
            <?php else: ?>
              <?php foreach ($certifications as $cert): ?>
                <tr>
                  <td><a href="users.php?id=<?php echo esc($cert['user_id']); ?>"><?php echo esc($cert['user_name']); ?></a></td>
                  <td><?php echo esc($all_courses[$cert['course_slug']]['title'] ?? 'Unknown Course'); ?></td>
                  <td><?php echo esc(date('F j, Y', strtotime($cert['completion_date']))); ?></td>
                  <td class="actions">
                    <a href="../client/certificate.php?slug=<?php echo urlencode($cert['course_slug']); ?>&user_id=<?php echo $cert['user_id']; ?>" target="_blank">View Certificate</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>