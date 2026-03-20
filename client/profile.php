<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include DB connection and course data
require_once 'db_connect.php';
$all_courses = include __DIR__ . '/study_content.php';

$user_id = $_SESSION['user_id'];

// --- Fetch all profile data ---
// Handle messages from upload script
$profile_message = $_SESSION['profile_message'] ?? '';
$profile_message_type = $_SESSION['profile_message_type'] ?? '';
unset($_SESSION['profile_message'], $_SESSION['profile_message_type']);

// 1. Fetch user details (assuming 'created_at' column exists)
$stmt_user = $conn->prepare("SELECT name, email, avatar_path FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$stmt_user->bind_result($user_name, $user_email, $avatar_path);
$stmt_user->fetch();
$stmt_user->close();

// 2. Fetch course statistics
// Active courses
$stmt_active = $conn->prepare("SELECT COUNT(id) FROM user_courses WHERE user_id = ? AND status = 'in-progress'");
$stmt_active->bind_param("i", $user_id);
$stmt_active->execute();
$stmt_active->bind_result($active_courses_count);
$stmt_active->fetch();
$stmt_active->close();

// Completed courses (also used for certificates)
$stmt_completed = $conn->prepare("SELECT COUNT(id) FROM user_courses WHERE user_id = ? AND status = 'completed'");
$stmt_completed->bind_param("i", $user_id);
$stmt_completed->execute();
$stmt_completed->bind_result($completed_courses_count);
$stmt_completed->fetch();
$stmt_completed->close();

// 3. Fetch completed courses for the certificate list
$completed_courses = [];
$stmt_certs = $conn->prepare("SELECT course_slug FROM user_courses WHERE user_id = ? AND status = 'completed'");
$stmt_certs->bind_param("i", $user_id);
$stmt_certs->execute();
$result_certs = $stmt_certs->get_result();
while ($row = $result_certs->fetch_assoc()) {
    $completed_courses[] = $row['course_slug'];
}
$stmt_certs->close();

// 4. Fetch recent activity (last 4 enrollments/status changes)
$recent_activity = [];
$stmt_activity = $conn->prepare("SELECT course_slug, status FROM user_courses WHERE user_id = ? ORDER BY id DESC LIMIT 4");
$stmt_activity->bind_param("i", $user_id);
$stmt_activity->execute();
$result_activity = $stmt_activity->get_result();
while ($row = $result_activity->fetch_assoc()) {
    $recent_activity[] = $row;
}
$stmt_activity->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - L.M.S</title>
    <link rel="stylesheet" href="../client/style.css">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <style>
          .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            background: #333;
            color: #fff;
            width: 240px;
            min-height: 100vh;
            padding: 40px 20px 20px 20px;
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
        }
        .sidebar-nav li {
            margin: 18px 0;
        }
        .sidebar-nav a {
            color: #fff;
            text-decoration: none;
            font-size: 18px;
            padding: 10px 18px;
            border-radius: 8px;
            display: block;
            transition: background 0.3s, transform 0.2s;
        }
        .sidebar-nav a.active, .sidebar-nav a:hover {
            background: #28a745;
            color: #fff;
            transform: scale(1.05);
        }
        .main-content {
            flex: 1;
            padding: 40px 5vw 40px 5vw;
            animation: fadeIn 1s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px);}
            to { opacity: 1; transform: none;}
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 36px;
        }
        .dashboard-header h1 {
            font-size: 2.2rem;
            color: #28a745;
            letter-spacing: 1px;
        }
        .profile-section { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px #28a74522; padding: 36px 48px; max-width: 700px; margin: 40px auto;}
        .profile-avatar { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 3px solid #28a745; margin-bottom: 18px; box-shadow: 0 0 0 6px #28a74544;}
        .profile-info { font-size: 1.1rem; color: #333; margin-bottom: 24px;}
        .profile-info label { font-weight: bold; color: #218838;}
        .edit-btn { background: #28a745; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-size: 1rem; cursor: pointer; margin-top: 18px;}
        .edit-btn:hover { background: #218838;}
        .profile-details-table { width: 100%; border-collapse: collapse; margin-top: 18px; margin-bottom: 24px;}
        .profile-details-table th, .profile-details-table td { padding: 10px 12px; border-bottom: 1px solid #e0e0e0; text-align: left;}
        .profile-details-table th { background: #e0ffe0; color: #218838; font-weight: bold;}
        .profile-details-table td { background: #fafafa;}
        .profile-stats { display: flex; gap: 32px; margin-bottom: 24px; flex-wrap: wrap;}
        .stat-card { background: #e0ffe0; border-radius: 12px; padding: 18px 28px; text-align: center; min-width: 120px;}
        .stat-title { color: #218838; font-size: 1em; margin-bottom: 6px;}
        .stat-value { color: #28a745; font-size: 1.5em; font-weight: bold;}
        .recent-activity { margin-top: 32px; }
        .recent-activity h3 { color: #28a745; margin-bottom: 10px;}
        .recent-activity ul { list-style: disc; margin-left: 24px; color: #218838; }
        .recent-activity li { margin-bottom: 6px; }
        .certificates-section {
            margin-top: 32px;
            background: #e0ffe0;
            border-radius: 12px;
            padding: 18px 24px;
        }
        .certificates-section h3 {
            color: #218838;
            margin-bottom: 10px;
        }
        .cert-list {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }
        .cert-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px #28a74522;
            padding: 12px 18px;
            min-width: 180px;
            text-align: center;
        }
        .profile-avatar-container {
            position: relative;
            display: inline-block;
        }
        .edit-avatar-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #fff;
            color: #28a745;
            border: 1px solid #28a745;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            transition: all 0.2s;
        }
        .edit-avatar-btn:hover {
            background: #28a745;
            color: #fff;
            transform: scale(1.1);
        }
        /* Modal for avatar upload */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center; }
        .modal-content { background-color: #fefefe; margin: auto; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 8px 30px rgba(0,0,0,0.2); position: relative; }
        .close-button { color: #aaa; position: absolute; top: 10px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-button:hover, .close-button:focus { color: #333; }
        .message { padding: 15px; margin: 0 auto 20px auto; border-radius: 8px; max-width: 600px; text-align: center; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .cert-card span {
            display: block;
            font-size: 1.1em;
            color: #28a745;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .cert-card small {
            color: #888;
        }
        @media (max-width: 700px) {
            .profile-section { padding: 18px 6vw; }
            .profile-stats { flex-direction: column; gap: 16px; }
            .cert-list { flex-direction: column; gap: 12px; }
        }
    </style>
</head>
<body>
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
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="my_enrollments.php">My Courses</a></li>
            <li><a href="courses.php">Course Catalog</a></li>
            <li><a href="profile.php" class="active">Profile</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <!-- Avatar Upload Modal -->
    <div id="avatarModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="document.getElementById('avatarModal').style.display='none'">&times;</span>
            <h2>Upload New Avatar</h2>
            <form action="upload_avatar.php" method="post" enctype="multipart/form-data">
                <p>Choose a new profile picture (max 2MB, JPG/PNG/GIF).</p>
                <input type="file" name="avatar" id="avatar" required accept="image/*">
                <button type="submit" class="edit-btn" style="margin-top: 20px;">Upload Image</button>
            </form>
        </div>
    </div>
    <div class="dashboard-container">
        <aside class="sidebar">
            <img src="<?php echo htmlspecialchars($avatar_path ?? '../images/user.jpg'); ?>" alt="User Avatar" class="sidebar-logo user-avatar active" />
            <h2>L.M.S</h2>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="my_enrollments.php">My Courses</a></li>
                <li><a href="courses.php">Course Catalog</a></li>
                <li><a href="profile.php" class="active">Profile</a></li>
                <li><a href="settings.php">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </aside>
        <main class="main-content">
            <?php if ($profile_message): ?>
                <div class="message <?php echo htmlspecialchars($profile_message_type); ?>">
                    <?php echo htmlspecialchars($profile_message); ?>
                </div>
            <?php endif; ?>
            <div class="profile-section">
                <div style="text-align:center;">
                    <div class="profile-avatar-container">
                        <img src="<?php echo htmlspecialchars($avatar_path ?? '../images/user.jpg'); ?>" alt="User Avatar" class="profile-avatar user-avatar active" />
                        <button class="edit-avatar-btn" onclick="document.getElementById('avatarModal').style.display='flex'" title="Change Avatar">✏️</button>
                    </div>
                    <h2 style="color:#28a745; margin-top:10px;"><?php echo htmlspecialchars($user_name); ?></h2>
                    <p style="color:#218838; margin-bottom:18px;"><?php echo htmlspecialchars(ucfirst($user_role ?? 'Learner')); ?></p>
                </div>
                <div class="profile-stats">
                    <div class="stat-card">
                        <div class="stat-title">Active Courses</div>
                        <div class="stat-value"><?php echo $active_courses_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Completed</div>
                        <div class="stat-value"><?php echo $completed_courses_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Certificates</div>
                        <div class="stat-value"><?php echo $completed_courses_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Messages</div>
                        <div class="stat-value">0</div>
                    </div>
                </div>
                <table class="profile-details-table">
                    <tr>
                        <th>Name</th>
                        <td><?php echo htmlspecialchars($user_name); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo htmlspecialchars($user_email); ?></td>
                    </tr>
                    <tr>
                        <th>Role</th>
                        <td><?php echo htmlspecialchars(ucfirst($user_role ?? 'Student')); ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td>Not Provided</td>
                    </tr>
                </table>
                <button class="edit-btn" onclick="window.location.href='settings.php'">Edit Profile & Settings</button>
                <div class="certificates-section">
                    <h3>Certificates</h3>
                    <div class="cert-list">
                        <?php if (empty($completed_courses)): ?>
                            <p>No certificates earned yet. Complete a course to earn one!</p>
                        <?php else: ?>
                            <?php foreach ($completed_courses as $slug): ?>
                                <a href="certificate.php?slug=<?php echo urlencode($slug); ?>" style="text-decoration: none; color: inherit;">
                                    <div class="cert-card">
                                        <span><?php echo htmlspecialchars($all_courses[$slug]['title'] ?? 'Unknown Course'); ?></span>
                                        <small>View Certificate</small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="recent-activity">
                    <h3>Recent Activity</h3>
                    <ul>
                        <?php if (empty($recent_activity)): ?>
                            <li>No recent activity to show.</li>
                        <?php else: ?>
                            <?php foreach ($recent_activity as $activity): ?>
                                <li>Enrolled in "<?php echo htmlspecialchars($all_courses[$activity['course_slug']]['title'] ?? 'a course'); ?>" (Status: <?php echo htmlspecialchars($activity['status']); ?>)</li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
<script>
    // Close modal if user clicks outside of it
    const modal = document.getElementById('avatarModal');
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>
</body>
<script src="../client/script.js"></script>
</html>