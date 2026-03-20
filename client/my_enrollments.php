<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include DB connection and load all available course details
require_once 'db_connect.php';
$all_courses = [];
$study_content_file = __DIR__ . '/study_content.php';
if (file_exists($study_content_file)) {
    $all_courses = include $study_content_file;
}

$user_id = $_SESSION['user_id'];

$avatar_path = '../images/user.jpg'; // Default avatar
$stmt_avatar = $conn->prepare("SELECT avatar_path FROM users WHERE id = ?");
$stmt_avatar->bind_param("i", $user_id);
$stmt_avatar->execute();
$result_avatar = $stmt_avatar->get_result();
if($row = $result_avatar->fetch_assoc()) { $avatar_path = $row['avatar_path'] ?? $avatar_path; }
$stmt_avatar->close();

// Fetch user's enrolled courses from the database
$enrolled_courses_data = [];
$stmt = $conn->prepare(
    "SELECT course_slug, progress, status FROM user_courses WHERE user_id = ? ORDER BY id DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Use slug as key for easy access
    $enrolled_courses_data[$row['course_slug']] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Enrollments - L.M.S</title>
    <link rel="stylesheet" href="../client/style.css">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <style>
        /* Re-using styles from courses.php for consistency */
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { background: #333; color: #fff; width: 240px; min-height: 100vh; padding: 40px 20px; display: flex; flex-direction: column; align-items: center; box-shadow: 2px 0 16px rgba(40,167,69,0.08); }
        .sidebar-logo { width: 80px; height: 80px; border-radius: 50%; margin-bottom: 16px; border: 3px solid #28a745; object-fit: cover; }
        .sidebar h2 { font-size: 22px; margin-bottom: 30px; color: #28a745; }
        .sidebar-nav { width: 100%; list-style: none; padding: 0; }
        .sidebar-nav li { margin: 18px 0; }
        .sidebar-nav a { color: #fff; text-decoration: none; font-size: 18px; padding: 10px 18px; border-radius: 8px; display: block; transition: background 0.3s, transform 0.2s; }
        .sidebar-nav a.active, .sidebar-nav a:hover { background: #28a745; color: #fff; transform: scale(1.05); }
        .main-content { flex: 1; padding: 40px 5vw; animation: fadeIn 1s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px);} to { opacity: 1; transform: none;} }
        .courses-header { margin-bottom: 32px; }
        .courses-header h1 { color: #28a745; font-size: 2.2em; }
        .courses-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 28px; }
        .course-card { background: #fff; border-radius: 18px; box-shadow: 0 4px 24px #28a74522; padding: 20px; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; position: relative; overflow: hidden;}
        .course-card:hover { transform: translateY(-6px); box-shadow: 0 8px 32px #28a74533;}
        .course-thumb { width: 100%; height: 160px; object-fit: cover; border-radius: 12px; margin-bottom: 16px;}
        .course-title { font-size: 1.3rem; font-weight: bold; color: #28a745; margin-bottom: 8px;}
        .course-meta { font-size: 0.9em; color: #777; margin-bottom: 12px;}
        .progress-label { font-size: 0.9rem; color: #218838; margin-bottom: 4px;}
        .progress-bar-bg { width: 100%; height: 10px; background: #e9ecef; border-radius: 8px; margin-bottom: 16px;}
        .progress-bar { height: 100%; background: linear-gradient(90deg, #28a745 70%, #218838 100%); border-radius: 8px; transition: width 1s ease-out; }
        .course-action { margin-top: auto; }
        .course-action button { background: #28a745; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-size: 1rem; cursor: pointer; transition: background 0.2s; width: 100%;}
        .course-action button:hover { background: #218838;}
        .course-status { position: absolute; top: 12px; right: 12px; background: #28a745; color: #fff; padding: 4px 12px; border-radius: 16px; font-size: 0.85em; font-weight: bold; }
        .no-courses { background: #fff; padding: 40px; border-radius: 16px; text-align: center; }
        .no-courses p { font-size: 1.2rem; color: #555; }
        .no-courses a { display: inline-block; margin-top: 20px; background: #28a745; color: #fff; padding: 12px 24px; border-radius: 8px; font-weight: bold; }
        .course-syllabus {
            font-size: 0.9em;
            margin-top: 12px;
            margin-bottom: 12px;
            color: #555;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }
        .course-syllabus h4 {
            margin: 0 0 8px 0;
            font-size: 1em;
            color: #218838;
            font-weight: bold;
        }
        .course-syllabus ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        .course-syllabus li {
            padding-left: 1.3em;
            position: relative;
            margin-bottom: 4px;
        }
        .course-syllabus li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #28a745;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-logo">
            <img src="../images/logo.jpg" alt="L.M.S Logo" class="nav-logo-img" />
            L.M.S
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="my_enrollments.php" class="active">My Courses</a></li>
            <li><a href="courses.php">Course Catalog</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <div class="dashboard-container">
        <aside class="sidebar">
            <img src="<?php echo htmlspecialchars($avatar_path ?? '../images/user.jpg'); ?>" alt="User Avatar" class="sidebar-logo user-avatar" />
            <h2>L.M.S</h2>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="my_enrollments.php" class="active">My Courses</a></li>
                <li><a href="courses.php">Course Catalog</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="settings.php">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </aside>
        <main class="main-content">
            <div class="courses-header">
                <h1>My Enrolled Courses</h1>
            </div>
            <div class="courses-list">
                <?php if (empty($enrolled_courses_data)): ?>
                    <div class="no-courses">
                        <p>You haven't enrolled in any courses yet.</p>
                        <a href="courses.php">Browse Course Catalog</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($enrolled_courses_data as $slug => $enrollment): ?>
                        <?php
                            $details = $all_courses[$slug] ?? null;
                            if (!$details) continue;

                            $status_text = ucfirst($enrollment['status']);
                            $status_style = '';
                            if ($enrollment['status'] === 'completed') {
                                $status_style = 'background:#dc3545;';
                            } elseif ($enrollment['status'] === 'in-progress' && $enrollment['progress'] > 0) {
                                $status_style = 'background:#ffc107;color:#333;';
                            }
                        ?>
                        <div class="course-card" data-slug="<?php echo htmlspecialchars($slug); ?>">
                            <span class="course-status" style="<?php echo $status_style; ?>"><?php echo htmlspecialchars($status_text); ?></span>
                            <img src="<?php echo htmlspecialchars($details['thumb'] ?? '../images/course_default.jpg'); ?>" alt="<?php echo htmlspecialchars($details['title']); ?>" class="course-thumb" />
                            <h3 class="course-title"><?php echo htmlspecialchars($details['title']); ?></h3>
                            <p class="course-meta">Instructor: <?php echo htmlspecialchars($details['instructor'] ?? 'N/A'); ?></p>
                            
                            <?php if (!empty($details['syllabus'])): ?>
                                <div class="course-syllabus">
                                    <h4>Syllabus Overview:</h4>
                                    <ul>
                                        <?php foreach ($details['syllabus'] as $section): ?>
                                            <li><?php echo htmlspecialchars($section['title']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="progress-label">Progress: <?php echo $enrollment['progress']; ?>%</div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar" style="width: <?php echo $enrollment['progress']; ?>%;"></div>
                            </div>

                            <div class="course-action">
                                <?php if ($enrollment['status'] === 'completed'): ?>
                                    <button data-action="certificate" style="background:#dc3545;">View Certificate</button>
                                <?php else: ?>
                                    <button data-action="continue">Continue Learning</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Utility to build URLs, resolving from the current page's directory
    function toUrl(path, params = {}) {
        const u = new URL(path, window.location.href);
        Object.keys(params).forEach(k => u.searchParams.set(k, params[k]));
        return u.toString();
    }

    document.querySelectorAll('.course-card').forEach(card => {
        const slug = card.dataset.slug || '';

        // Make card clickable to go to course details
        card.addEventListener('click', (ev) => {
            if (ev.target.closest('button') || ev.target.closest('a')) return;
            if (slug) {
                window.location.href = toUrl('course_detail.php', { slug });
            }
        });

        // Add event listeners to buttons inside the card
        card.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = (btn.dataset.action || '').trim().toLowerCase();

                if (!slug) return;

                if (action.includes('continue')) {
                    window.location.href = toUrl('course_detail.php', { slug: slug });
                } else if (action.includes('certificate')) {
                    window.location.href = toUrl('certificate.php', { slug: slug });
                }
            });
        });
    });
});
</script>
</body>
</html>
