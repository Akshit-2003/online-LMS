<?php
session_start();
ini_set('display_errors', 1); // Good for development
require_once "../client/db_connect.php";

// Admin guard: ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    // Redirect to login page if not authorized
    header("Location: login.php");
    exit();
}

// --- Fetch Dashboard Stats ---
$userCount = 0;
$stmt_users = $conn->prepare("SELECT COUNT(id) FROM users");
if ($stmt_users) {
    $stmt_users->execute();
    $stmt_users->bind_result($userCount);
    $stmt_users->fetch();
    $stmt_users->close();
}

// Total Enrollments
$enrollmentCount = 0;
$stmt_enroll = $conn->prepare("SELECT COUNT(id) FROM user_courses");
if ($stmt_enroll) {
    $stmt_enroll->execute();
    $stmt_enroll->bind_result($enrollmentCount);
    $stmt_enroll->fetch();
    $stmt_enroll->close();
}

// Completed Courses
$completedCount = 0;
$stmt_completed = $conn->prepare("SELECT COUNT(id) FROM user_courses WHERE status = 'completed'");
if ($stmt_completed) {
    $stmt_completed->execute();
    $stmt_completed->bind_result($completedCount);
    $stmt_completed->fetch();
    $stmt_completed->close();
}

// --- Fetch Chart Data ---
$days = [];
$user_growth_data = [];
$completion_growth_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $days[] = date('M d', strtotime($date));
    $user_growth_data[$date] = 0;
    $completion_growth_data[$date] = 0;
}

// User Growth (last 7 days) - NOTE: Assumes a `created_at` column in `users` table
$stmt_user_growth = $conn->prepare("SELECT DATE(created_at) as date, COUNT(id) as count FROM users WHERE created_at >= CURDATE() - INTERVAL 7 DAY GROUP BY DATE(created_at)");
if ($stmt_user_growth) {
    $stmt_user_growth->execute();
    $result = $stmt_user_growth->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($user_growth_data[$row['date']])) {
            $user_growth_data[$row['date']] = $row['count'];
        }
    }
    $stmt_user_growth->close();
}

// Completion Growth (last 7 days)
$stmt_comp_growth = $conn->prepare("SELECT DATE(completion_date) as date, COUNT(id) as count FROM user_courses WHERE status = 'completed' AND completion_date >= CURDATE() - INTERVAL 7 DAY GROUP BY DATE(completion_date)");
if ($stmt_comp_growth) {
    $stmt_comp_growth->execute();
    $result = $stmt_comp_growth->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($completion_growth_data[$row['date']])) {
            $completion_growth_data[$row['date']] = $row['count'];
        }
    }
    $stmt_comp_growth->close();
}

// User Roles
$user_roles_data = ['admin' => 0, 'user' => 0];
$result_roles = $conn->query("SELECT role, COUNT(id) as count FROM users GROUP BY role");
if ($result_roles) {
    while ($row = $result_roles->fetch_assoc()) {
        if (isset($user_roles_data[$row['role']])) $user_roles_data[$row['role']] = $row['count'];
    }
}

// --- Fetch Recent Activity ---

// Recent Users
$recentUsers = [];
$stmt_recent_users = $conn->prepare("SELECT id, name, email, avatar_path FROM users ORDER BY id DESC LIMIT 5");
if ($stmt_recent_users) {
    $stmt_recent_users->execute();
    $result_users = $stmt_recent_users->get_result();
    while ($row = $result_users->fetch_assoc()) {
        $recentUsers[] = $row;
    }
    $stmt_recent_users->close();
}

// Recent Completions
$recentCompletions = [];
$stmt_recent_comp = $conn->prepare(
    "SELECT u.id, u.name, uc.course_slug, uc.completion_date 
     FROM user_courses uc 
     JOIN users u ON uc.user_id = u.id 
     WHERE uc.status = 'completed' AND uc.completion_date IS NOT NULL
     ORDER BY uc.completion_date DESC, uc.id DESC LIMIT 5"
);
if ($stmt_recent_comp) {
    $stmt_recent_comp->execute();
    $result_comp = $stmt_recent_comp->get_result();
    while ($row = $result_comp->fetch_assoc()) {
        $recentCompletions[] = $row;
    }
    $stmt_recent_comp->close();
}

// Most Popular Courses
$popularCourses = [];
$stmt_popular = $conn->prepare("SELECT course_slug, COUNT(id) as enrollments FROM user_courses GROUP BY course_slug ORDER BY enrollments DESC LIMIT 5");
if ($stmt_popular) {
    $stmt_popular->execute();
    $result_popular = $stmt_popular->get_result();
    while ($row = $result_popular->fetch_assoc()) {
        $popularCourses[] = $row;
    }
    $stmt_popular->close();
}


function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// load course count if study_content exists
$coursesCount = null;
$studyFile = __DIR__ . '/../client/study_content.php';
if (file_exists($studyFile)) {
    $courses = include $studyFile;
    if (is_array($courses)) $coursesCount = count($courses);
}

// read latest contact submissions (tail)
$recentContacts = '';
$subDir = __DIR__ . '/../client/submissions';
if (is_dir($subDir)) {
    $files = glob($subDir . '/contacts_*.log');
    if ($files) {
        usort($files, function($a,$b){ return filemtime($b) <=> filemtime($a); });
        $latest = $files[0];
        $content = @file_get_contents($latest);
        if ($content !== false) $recentContacts = nl2br(esc(mb_substr($content, -1400)));
    }
}

// Helper function to set active class on sidebar links
function active_class($page_name) {
    if (basename($_SERVER['PHP_SELF']) == $page_name) {
        echo 'class="active"';
    }
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
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <title>Admin • L.M.S</title>
  <style>
    :root {
        --primary: #0d6efd; --success: #198754; --light: #f4f7f9;
        --dark: #1e2227; --text-muted: #6c757d; --bg-surface: #fff;
        --border-color: #dee2e6;
    }
    body { background: var(--light); font-family: 'Segoe UI', sans-serif; color: #333; }
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar { background: var(--dark); color: #fff; width: 260px; display: flex; flex-direction: column; transition: width 0.3s; }
    .sidebar-brand { padding: 20px; display: flex; align-items: center; gap: 12px; background: #343a40; }
    .sidebar-brand img { width: 40px; height: 40px; border-radius: 50%; }
    .sidebar-brand-text { font-size: 1.2rem; font-weight: 600; }
    .sidebar-nav { flex-grow: 1; padding: 20px 0; }
    .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: #adb5bd; text-decoration: none; transition: background 0.2s, color 0.2s; border-left: 4px solid transparent; }
    .sidebar-nav a:hover { background: #343a40; color: #fff; }
    .sidebar-nav a.active { background: var(--primary); color: #fff; border-left-color: #fff; font-weight: 600; }
    .sidebar-nav a .icon { width: 20px; height: 20px; }
    .main-content { flex: 1; padding: 40px; }
    .dashboard-header { margin-bottom: 30px; }
    .dashboard-header h1 { color: #343a40; font-size: 2.2rem; }
    .dashboard-header .small { color: var(--text-muted); font-size: 1.1rem; margin-top: 4px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 30px; }
    .card { 
        background: var(--bg-surface); border-radius: 12px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 24px; 
        display: flex; align-items: center; gap: 20px;
        transition: transform 0.2s, box-shadow 0.2s; position: relative; overflow: hidden;
    }
    .card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .card-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .card-icon svg { width: 28px; height: 28px; }
    .card-body .small { margin: 0 0 4px 0; color: var(--text-muted); font-size: 0.9em; }
    .card-title { margin: 0; font-size: 2.2rem; font-weight: 700; color: var(--dark); line-height: 1; }
    .main-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
    .chart-card { background: var(--bg-surface); border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 24px; }
    .chart-card h3 { margin-top: 0; color: var(--dark); }
    .activity-card { background: var(--bg-surface); border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 24px; }
    .activity-card h3 { margin-top: 0; color: var(--dark); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; font-size: 1.2rem; }
    .activity-list { list-style: none; padding: 0; margin: 0; }
    .activity-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f2f4; }
    .activity-item:last-child { border-bottom: none; }
    .activity-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
    .activity-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; flex-shrink: 0; }
    .activity-details { flex-grow: 1; }
    .activity-details strong { display: block; color: #333; font-weight: 600; }
    .activity-details small { color: var(--text-muted); font-size: 0.9em; }
    .activity-link { background: #e7f1ff; color: var(--primary); padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85em; font-weight: 600; transition: background 0.2s; white-space: nowrap; }
    .activity-link:hover { background: #d0e3ff; }
    @media (max-width: 992px) {
        .main-layout { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .dashboard-container { flex-direction: column; }
        .sidebar { width: 100%; min-height: auto; flex-direction: row; justify-content: space-between; }
        .sidebar-nav { display: flex; flex-wrap: wrap; }
    }
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
      <header class="dashboard-header">
        <h1>Dashboard</h1>
        <p class="small">Welcome back, <?php echo esc($_SESSION['user_name'] ?? 'Admin'); ?>! Here's an overview of your LMS.</p>
      </header>

      <section class="grid" role="region" aria-label="overview stats">
        <div class="card">
            <div class="card-icon" style="background:#e7f1ff; color:#0d6efd;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            </div>
            <div class="card-body">
                <p class="small">Total Courses</p>
                <h3 class="card-title" data-count="<?php echo esc($coursesCount ?? 0); ?>">0</h3>
            </div>
        </div>

        <div class="card">
            <div class="card-icon" style="background:#d1e7dd; color:#198754;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197" /></svg>
            </div>
            <div class="card-body">
                <p class="small">Total Users</p>
                <h3 class="card-title" data-count="<?php echo esc($userCount); ?>">0</h3>
            </div>
        </div>

        <div class="card">
            <div class="card-icon" style="background:#fff3cd; color:#ffc107;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
            </div>
            <div class="card-body">
                <p class="small">Total Enrollments</p>
                <h3 class="card-title" data-count="<?php echo esc($enrollmentCount); ?>">0</h3>
            </div>
        </div>

        <div class="card">
            <div class="card-icon" style="background:#f8d7da; color:#dc3545;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div class="card-body">
                <p class="small">Completed Courses</p>
                <h3 class="card-title" data-count="<?php echo esc($completedCount); ?>">0</h3>
            </div>
        </div>
      </section>

      <section class="main-layout">
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="chart-card">
                <h3>User Registrations (Last 7 Days)</h3>
                <canvas id="userGrowthChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Course Completions (Last 7 Days)</h3>
                <canvas id="completionGrowthChart"></canvas>
            </div>
            <div class="activity-card">
                <h3>Most Popular Courses</h3>
                <ul class="activity-list">
                    <?php if (empty($popularCourses)): ?>
                        <li>No enrollment data available.</li>
                    <?php else: ?>
                        <?php foreach($popularCourses as $pCourse): ?>
                            <li class="activity-item">
                                <div class="activity-icon" style="background-color: #e7f1ff; color: #0d6efd;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                </div>
                                <div class="activity-details">
                                    <strong><?php echo esc($courses[$pCourse['course_slug']]['title'] ?? 'Unknown Course'); ?></strong>
                                    <small><?php echo esc($pCourse['enrollments']); ?> enrollments</small>
                                </div>
                                <a href="manage_courses.php" class="activity-link">View</a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <aside style="display: flex; flex-direction: column; gap: 24px;">
            <div class="chart-card">
                <h3>User Roles</h3>
                <canvas id="userRolesChart"></canvas>
            </div>
            <div class="activity-card">
                <h3>Newest Users</h3>
                <ul class="activity-list">
                    <?php if (empty($recentUsers)): ?>
                        <li>No recent users found.</li>
                    <?php else: ?>
                        <?php foreach($recentUsers as $user): ?>
                            <li class="activity-item">
                                <img src="<?php echo esc($user['avatar_path'] ? str_replace('../', '../client/', $user['avatar_path']) : '../images/user.jpg'); ?>" alt="avatar" class="activity-avatar">
                                <div class="activity-details">
                                    <strong><?php echo esc($user['name']); ?></strong>
                                    <small><?php echo esc($user['email']); ?></small>
                                </div>
                                <a href="users.php?id=<?php echo $user['id']; ?>" class="activity-link">View</a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="activity-card">
                <h3>Recent Completions</h3>
                <ul class="activity-list">
                    <?php if (empty($recentCompletions)): ?>
                        <li>No recent course completions.</li>
                    <?php else: ?>
                        <?php foreach($recentCompletions as $comp): ?>
                            <li class="activity-item">
                                <div class="activity-icon" style="background-color: #d1e7dd; color: #198754;">✓</div>
                                <div class="activity-details">
                                    <strong><a href="users.php?id=<?php echo $comp['id']; ?>"><?php echo esc($comp['name']); ?></a></strong>
                                    <small>Completed "<?php echo esc($courses[$comp['course_slug']]['title'] ?? 'Unknown Course'); ?>"</small>
                                </div>
                                <a href="certification.php" class="activity-link">Verify</a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </aside>
      </section>
    </main>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animated Count-Up for Stat Cards
        const counters = document.querySelectorAll('.card-title[data-count]');
        counters.forEach(counter => {
            const target = +counter.dataset.count;
            let current = 0;
            const increment = target / 100;

            const updateCounter = () => {
                if (current < target) {
                    current += increment;
                    counter.textContent = Math.ceil(current);
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.textContent = target;
                }
            };
            updateCounter();
        });

        // Chart.js Implementation
        const chartFont = "'Segoe UI', sans-serif";
        const chartLabels = <?php echo json_encode(array_values($days)); ?>;

        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart')?.getContext('2d');
        if (userGrowthCtx) {
            new Chart(userGrowthCtx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'New Users',
                        data: <?php echo json_encode(array_values($user_growth_data)); ?>,
                        backgroundColor: 'rgba(13, 110, 253, 0.6)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }

        // Completion Growth Chart
        const completionGrowthCtx = document.getElementById('completionGrowthChart')?.getContext('2d');
        if (completionGrowthCtx) {
            new Chart(completionGrowthCtx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Completions',
                        data: <?php echo json_encode(array_values($completion_growth_data)); ?>,
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }

        // User Roles Chart
        const userRolesCtx = document.getElementById('userRolesChart')?.getContext('2d');
        if (userRolesCtx) {
            new Chart(userRolesCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Admins', 'Users'],
                    datasets: [{
                        label: 'User Roles',
                        data: <?php echo json_encode(array_values($user_roles_data)); ?>,
                        backgroundColor: ['rgba(220, 53, 69, 0.7)', 'rgba(13, 110, 253, 0.7)'],
                        borderColor: ['#fff', '#fff'],
                        borderWidth: 2
                    }]
                },
                options: { responsive: true, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
            });
        }
    });
  </script>
</body>
</html>