<?php
session_start();
require_once "../client/db_connect.php";



// Helper function for active sidebar link
function active_class($page_name) {
    if (basename($_SERVER['PHP_SELF']) == $page_name) {
        echo 'class="active"';
    }
}

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Handle Actions (POST) ---
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $delete_id = (int)$_POST['delete_user_id'];

    // Security: Don't allow an admin to delete their own account
    if ($delete_id === $_SESSION['user_id']) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'You cannot delete your own account.'];
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Delete user's course enrollments first
            $stmt_del_courses = $conn->prepare("DELETE FROM user_courses WHERE user_id = ?");
            $stmt_del_courses->bind_param("i", $delete_id);
            $stmt_del_courses->execute();
            $stmt_del_courses->close();

            // Delete the user
            $stmt_del_user = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del_user->bind_param("i", $delete_id);
            $stmt_del_user->execute();
            $stmt_del_user->close();

            $conn->commit();
            $_SESSION['message'] = ['type' => 'success', 'text' => 'User and their enrollments have been deleted successfully.'];
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to delete user. Error: ' . $exception->getMessage()];
        }
    }
    header("Location: users.php");
    exit();
}

// Check if a specific user is requested
$view_user_id = $_GET['id'] ?? null;
$user_data = null;
$all_users = [];
$user_courses = [];
$all_courses_meta = file_exists('../client/study_content.php') ? include '../client/study_content.php' : [];

if ($view_user_id && is_numeric($view_user_id)) {
    // Fetch a single user
    $stmt = $conn->prepare("SELECT id, name, email, role, avatar_path FROM users WHERE id = ?");
    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    // Fetch their courses if user exists
    if ($user_data) {
        $stmt_courses = $conn->prepare("SELECT course_slug, progress, status, completion_date FROM user_courses WHERE user_id = ? ORDER BY id DESC");
        $stmt_courses->bind_param("i", $view_user_id);
        $stmt_courses->execute();
        $result_courses = $stmt_courses->get_result();
        while ($row = $result_courses->fetch_assoc()) {
            $user_courses[] = $row;
        }
        $stmt_courses->close();
    }
} else {
    // Fetch all users
    $result = $conn->query("SELECT id, name, email, role, avatar_path FROM users ORDER BY id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_users[] = $row;
        }
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
  <title>Manage Users • Admin</title>
  <style>
    :root {
        --primary: #0d6efd; --success: #198754; --danger: #dc3545; --light: #f4f7f9;
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
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .page-header h1 { color: #343a40; font-size: 2.2rem; margin:0; }
    .btn { padding: 8px 14px; border-radius: 8px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; display: inline-block; }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-danger { background: var(--danger); color: #fff; }
    .btn-secondary { background: #6c757d; color: #fff; }
    /* User Grid & Cards */
    .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; }
    .user-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 20px; display: flex; flex-direction: column; text-align: center; transition: transform 0.2s, box-shadow 0.2s; }
    .user-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .user-card-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin: 0 auto 12px auto; border: 3px solid var(--border-color); }
    .user-card-name { font-size: 1.2rem; font-weight: 600; margin: 0; }
    .user-card-email { color: var(--text-muted); margin: 4px 0 16px 0; font-size: 0.9rem; }
    .user-card-actions { display: flex; gap: 10px; justify-content: center; margin-top: auto; }
    .user-role-badge { font-size: 0.8rem; font-weight: 600; padding: 3px 8px; border-radius: 6px; margin-bottom: 12px; display: inline-block; }
    .user-role-badge.admin { background: #f8d7da; color: #842029; }
    .user-role-badge.user { background: #e7f1ff; color: #0d6efd; }
    /* User Filters */
    .user-filters { display: flex; gap: 16px; margin-bottom: 24px; }
    .user-filters input, .user-filters select { padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
    .user-filters input { flex-grow: 1; }
    .user-detail-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); max-width: 700px; margin: 0 auto; }
    /* User Detail View */
    .user-detail-header { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color); }
    .user-detail-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; }
    .user-detail-header h2 { margin: 0; font-size: 1.8rem; }
    .user-detail-section { margin-top: 24px; }
    .user-detail-section h3 { font-size: 1.3rem; color: var(--dark); border-bottom: 1px solid var(--border-color); padding-bottom: 8px; margin-bottom: 12px; }
    .course-progress-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; }
    .progress-bar-container { width: 100px; background: #e9ecef; border-radius: 4px; height: 8px; }
    .progress-bar { background: var(--success); height: 100%; border-radius: 4px; }
    /* Messages */
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
      <?php if ($message): ?>
        <div class="message <?php echo esc($message['type']); ?>"><?php echo esc($message['text']); ?></div>
      <?php endif; ?>

      <?php if ($user_data): // Single User View ?>
        <header class="page-header">
          <h1>User Details</h1>
          <a href="users.php" class="btn btn-secondary">&larr; Back to All Users</a>
        </header>
        <div class="user-detail-card">
          <div class="user-detail-header">
            
            <div>
              <h2><?php echo esc($user_data['name']); ?></h2>
              <p style="color: #6c757d; margin:0;"><?php echo esc($user_data['email']); ?></p>
              <span style="background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: 600;"><?php echo esc(ucfirst($user_data['role'])); ?></span>
            </div>
          </div>

          <div class="user-detail-section">
            <h3>Course Activity</h3>
            <?php if (empty($user_courses)): ?>
                <p>This user has not enrolled in any courses yet.</p>
            <?php else: ?>
                <?php foreach($user_courses as $course): ?>
                    <div class="course-progress-item">
                        <span><?php echo esc($all_courses_meta[$course['course_slug']]['title'] ?? 'Unknown Course'); ?></span>
                        <?php if($course['status'] === 'completed'): ?>
                            <a href="../client/certificate.php?slug=<?php echo urlencode($course['course_slug']); ?>&user_id=<?php echo $user_data['id']; ?>" target="_blank" class="btn btn-primary" style="padding: 4px 8px; font-size: 0.8rem;">View Certificate</a>
                        <?php else: ?>
                            <div class="progress-bar-container" title="<?php echo esc($course['progress']); ?>% complete">
                                <div class="progress-bar" style="width: <?php echo esc($course['progress']); ?>%;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php elseif ($view_user_id): ?>
        <header class="page-header"><h1>User Not Found</h1></header>
        <p>The requested user could not be found. <a href="users.php">Return to user list</a>.</p>
      <?php else: // All Users View ?>
        <header class="page-header">
          <h1>All Users</h1>
          <span id="userCount" style="color: var(--text-muted); font-weight: 600;"><?php echo count($all_users); ?> Users</span>
        </header>
        <div class="user-filters">
            <input type="search" id="userSearch" placeholder="Search by name or email..." aria-label="Search users">
            <select id="roleFilter" aria-label="Filter by role">
                <option value="">All Roles</option>
                <option value="admin">Admins</option>
                <option value="user">Users</option>
            </select>
        </div>
        <div class="user-grid">
          <?php if (empty($all_users)): ?>
            <p>No users found.</p>
          <?php else: ?>
            <?php foreach ($all_users as $user): ?> 
              <div class="user-card">
                ik
                <h3 class="user-card-name"><?php echo esc($user['name']); ?></h3>
                <p class="user-card-email"><?php echo esc($user['email']); ?></p>
                <div class="user-card-actions">
                  <a href="users.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">View Profile</a>
                  <form action="users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user and all their data? This cannot be undone.');">
                      <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                      <button type="submit" class="btn btn-danger">Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('userSearch');
        const roleFilter = document.getElementById('roleFilter');
        const userGrid = document.querySelector('.user-grid');
        const userCards = Array.from(userGrid.querySelectorAll('.user-card'));
        const userCountSpan = document.getElementById('userCount');

        function filterUsers() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const selectedRole = roleFilter.value;
            let visibleCount = 0;

            userCards.forEach(card => {
                const name = card.querySelector('.user-card-name').textContent.toLowerCase();
                const email = card.querySelector('.user-card-email').textContent.toLowerCase();
                const roleBadge = card.querySelector('.user-role-badge');
                const role = roleBadge ? roleBadge.textContent.toLowerCase() : '';

                const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
                const matchesRole = !selectedRole || role === selectedRole;

                if (matchesSearch && matchesRole) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            userCountSpan.textContent = `${visibleCount} User${visibleCount !== 1 ? 's' : ''}`;
        }

        searchInput.addEventListener('input', filterUsers);
        roleFilter.addEventListener('change', filterUsers);
    });
  </script>
</body>
</html>