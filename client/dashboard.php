<?php
session_start();
// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include DB connection and course data
require_once "db_connect.php";
$all_courses_details = [];
$study_content_file = __DIR__ . '/study_content.php';
if (file_exists($study_content_file)) {
    // Assuming study_content.php returns an array of courses keyed by slug
    $all_courses_details = include $study_content_file;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User'; // Use session data if available
$avatar_path = '../images/user.jpg'; // Default avatar

$stmt_avatar = $conn->prepare("SELECT avatar_path FROM users WHERE id = ?");
$stmt_avatar->bind_param("i", $user_id);
$stmt_avatar->execute();
$result_avatar = $stmt_avatar->get_result();
if($row = $result_avatar->fetch_assoc()) { $avatar_path = $row['avatar_path'] ?? $avatar_path; }
$stmt_avatar->close();

// --- Fetch dashboard data from DB ---

// Initialize stats
$enrolled_count = 0;
$completed_count = 0;
$active_courses = [];

// Fetch widget stats
$stmt_enrolled = $conn->prepare("SELECT COUNT(id) FROM user_courses WHERE user_id = ?");
$stmt_enrolled->bind_param("i", $user_id);
$stmt_enrolled->execute();
$stmt_enrolled->bind_result($enrolled_count);
$stmt_enrolled->fetch();
$stmt_enrolled->close();

$stmt_completed = $conn->prepare("SELECT COUNT(id) FROM user_courses WHERE user_id = ? AND status = 'completed'");
$stmt_completed->bind_param("i", $user_id);
$stmt_completed->execute();
$stmt_completed->bind_result($completed_count);
$stmt_completed->fetch();
$stmt_completed->close();

// Fetch active courses (e.g., in-progress, limit to 3 for the dashboard)
$stmt_courses = $conn->prepare("SELECT course_slug, progress FROM user_courses WHERE user_id = ? AND status = 'in-progress' ORDER BY id DESC LIMIT 3");
$stmt_courses->bind_param("i", $user_id);
$stmt_courses->execute();
$result = $stmt_courses->get_result();
while ($row = $result->fetch_assoc()) {
    $active_courses[] = $row;
}
$stmt_courses->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>L.M.S Dashboard</title>
    <link rel="stylesheet" href="../client/style.css">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <style>
        body {
            background: linear-gradient(120deg, #e0ffe9 0%, #f4f4f4 100%);
        }
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
        .user-profile {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #fff;
            padding: 8px 18px 8px 8px;
            border-radius: 30px;
            box-shadow: 0 2px 12px #28a74522;
            cursor: pointer;
            transition: box-shadow 0.3s;
            position: relative;
        }
        .user-profile:hover {
            box-shadow: 0 4px 24px #28a74533;
        }
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #28a745;
            box-shadow: 0 0 0 4px #28a74522;
            transition: box-shadow 0.3s;
        }
        .user-avatar.active {
            box-shadow: 0 0 0 6px #28a74588, 0 0 12px #28a74544;
            animation: pulse 1.2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 6px #28a74588, 0 0 12px #28a74544;}
            50% { box-shadow: 0 0 0 12px #28a74533, 0 0 24px #28a74522;}
            100% { box-shadow: 0 0 0 6px #28a74588, 0 0 12px #28a74544;}
        }
        .user-name {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
        }
        .profile-dropdown {
            display: none;
            position: absolute;
            top: 60px;
            right: 0;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px #28a74522;
            min-width: 160px;
            z-index: 10;
        }
        .profile-dropdown a {
            display: block;
            padding: 12px 18px;
            color: #28a745;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        .profile-dropdown a:last-child {
            border-bottom: none;
        }
        .profile-dropdown a:hover {
            background: #e0ffe9;
        }
        .user-profile.active .profile-dropdown {
            display: block;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 36px;
        }
        .widget-link {
            text-decoration: none;
            color: inherit;
        }
        .dashboard-widget {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .widget-link:hover .dashboard-widget {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(40, 167, 69, 0.15);
        }
        .dashboard-widget::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background-color: #28a745; /* Default color */
        }
        /* Unique colors for each widget bar */
        .widget-link:nth-child(1) .dashboard-widget::after { background-color: #0d6efd; }
        .widget-link:nth-child(2) .dashboard-widget::after { background-color: #198754; }
        .widget-link:nth-child(3) .dashboard-widget::after { background-color: #ffc107; }
        .widget-link:nth-child(4) .dashboard-widget::after { background-color: #6f42c1; }

        .dashboard-widget h3 {
            font-size: 1.1rem;
            color: #555;
            margin-top: 16px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        .dashboard-widget .widget-data {
            font-size: 2.8rem;
            color: #333;
            font-weight: 700;
            display: block;
            text-align: right;
            line-height: 1;
        }
        .announcements {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px #28a74522;
            padding: 24px 32px;
            margin-bottom: 36px;
        }
        .announcements h3 {
            color: #218838;
            margin-bottom: 12px;
        }
        .announcements ul {
            list-style: disc;
            margin-left: 20px;
        }
        .announcements li {
            margin-bottom: 8px;
            color: #444;
        }
        .quick-links {
            margin-top: 36px;
        }
        .quick-links h3 {
            color: #218838;
            margin-bottom: 12px;
        }
        .quick-links-list {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        .quick-link {
            background: linear-gradient(90deg, #28a745 80%, #218838 100%);
            color: #fff;
            border-radius: 12px;
            padding: 18px 32px;
            font-size: 1.1rem;
            text-decoration: none;
            font-weight: bold;
            box-shadow: 0 2px 12px #28a74522;
            transition: background 0.2s, transform 0.2s;
        }
        .quick-link:hover {
            background: linear-gradient(90deg, #218838 80%, #28a745 100%);
            transform: scale(1.05);
        }
        .courses-section {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px #28a74522;
            padding: 24px 32px;
            margin-top: 36px;
        }
        .courses-section .courses-title {
            font-size: 1.8rem;
            color: #28a745;
            margin-bottom: 24px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0ffe9;
        }
        .courses-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
        .course-card {
            background: #fdfdfd;
            border: 1px solid #eee;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.1);
        }
        .course-thumb {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }
        .course-card .course-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            padding: 16px 16px 8px 16px;
            margin: 0;
        }
        .course-desc {
            font-size: 0.9rem;
            color: #666;
            padding: 0 16px 16px 16px;
            flex-grow: 1;
        }
        .progress-label {
            font-size: 0.85rem;
            color: #555;
            padding: 0 16px;
            margin-bottom: 4px;
        }
        .progress-bar-bg {
            background-color: #e9ecef;
            border-radius: 10px;
            height: 10px;
            margin: 0 16px 16px 16px;
            overflow: hidden;
        }
        .progress-bar {
            background-color: #28a745;
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease-in-out;
        }
        .course-action {
            padding: 0 16px 16px 16px;
        }
        .course-action button {
            width: 100%;
            background-color: #28a745;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .course-action button:hover {
            background-color: #218838;
        }
        @media (max-width: 900px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                flex-direction: row;
                min-height: unset;
                padding: 20px 10px;
                justify-content: space-between;
            }
            .main-content {
                padding: 24px 2vw;
            }
            .course-card {
                width: 100%;
            }
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
    <!-- Navbar like index page -->
    <nav class="navbar">
        <div class="nav-logo">
            <img src="../images/logo.jpg" alt="L.M.S Logo" class="nav-logo-img" />
            L.M.S
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="courses.php">My Courses</a></li>
            <li><a href="courses.php">Course Catalog</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <div class="dashboard-container">
        <!-- Sidebar for all dashboard pages -->
<aside class="sidebar">
    <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="User Avatar" class="sidebar-logo user-avatar" />
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
            <div class="dashboard-header">
                <h1>Welcome back, <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>!</h1>
                <div class="user-profile" id="userProfile">
                    <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="User Avatar" class="user-avatar active" id="userAvatar" />
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <div class="profile-dropdown" id="profileDropdown">
                        <a href="profile.php">View Profile</a>
                        <a href="settings.php">Settings</a>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            </div>
            <div class="dashboard-grid">
    <!-- Widget 1: Enrolled Courses -->
    <a href="courses.php" class="widget-link">
        <div class="dashboard-widget">
            <h3>Enrolled Courses</h3>
            <p class="widget-data" data-target="<?php echo $enrolled_count; ?>"><?php echo $enrolled_count; ?></p>
        </div>
    </a>

    <!-- Widget 2: Completed Courses -->
    <a href="courses.php?status=completed" class="widget-link">
        <div class="dashboard-widget">
            <h3>Completed Courses</h3>
            <p class="widget-data" data-target="<?php echo $completed_count; ?>"><?php echo $completed_count; ?></p>
        </div>
    </a>

    <!-- Widget 3: Certificates Earned -->
    <a href="profile.php#certificates" class="widget-link">
        <div class="dashboard-widget">
            <h3>Certificates Earned</h3>
            <p class="widget-data" data-target="<?php echo $completed_count; ?>"><?php echo $completed_count; ?></p>
        </div>
    </a>

    <!-- Widget 4: Your Profile -->
    <a href="profile.php" class="widget-link">
        <div class="dashboard-widget">
            <h3>Your Profile</h3>
            <p class="widget-data" style="font-size: 2rem; padding-top: 1rem;">View</p>
        </div>
    </a>
</div>

            <div class="announcements">
                <h3>Latest Announcements</h3>
                <ul>
                    <li>🎉 New course "React for Beginners" is now available!</li>
                    <li>📅 Webinar on "Career in Data Science" this Friday.</li>
                    <li>🔔 Don't forget to complete your profile for personalized recommendations.</li>
                </ul>
            </div>
            <section class="courses-section">
                <h2 class="courses-title">Your Active Courses</h2>
                <div class="courses-list">
                    <?php if (empty($active_courses)): ?>
                        <p>You have no active courses. <a href="courses.php">Browse courses</a> to get started!</p>
                    <?php else: ?>
                        <?php foreach ($active_courses as $course): ?>
                            <?php
                                $slug = $course['course_slug'];
                                $details = $all_courses_details[$slug] ?? null;
                                if (!$details) continue; // Skip if course details not found
                            ?>
                            <div class="course-card">
                                <img src="<?php echo htmlspecialchars($details['thumb'] ?? '../images/course_default.jpg'); ?>" alt="<?php echo htmlspecialchars($details['title']); ?>" class="course-thumb" />
                                <div class="course-title"><?php echo htmlspecialchars($details['title']); ?></div>
                                <div class="course-desc"><?php echo htmlspecialchars($details['short']); ?></div>
                                <div class="progress-label">Progress: <?php echo $course['progress']; ?>%</div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar" style="width: <?php echo $course['progress']; ?>%;"></div>
                                </div>
                                <div class="course-action">
                                    <button onclick="window.location.href='course_detail.php?slug=<?php echo urlencode($slug); ?>'">Continue</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
            <div class="quick-links">
                <h3>Quick Links</h3>
                <div class="quick-links-list">
                    <a href="courses.php" class="quick-link">Browse All Courses</a>
                    <a href="profile.php" class="quick-link">Edit Profile</a>
                    <a href="settings.php" class="quick-link">Account Settings</a>
                    <a href="logout.php" class="quick-link">Logout</a>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Profile dropdown toggle
        const userProfile = document.getElementById('userProfile');
        userProfile.addEventListener('click', function(e) {
            e.stopPropagation();
            userProfile.classList.toggle('active');
        });
        document.body.addEventListener('click', function() {
            userProfile.classList.remove('active');
        });
    </script>
 
</body>
<script src="../client/script.js"></script>
</html>