<?php
session_start();

require_once 'db_connect.php';
$all_courses = include __DIR__ . '/study_content.php';

$slug = $_GET['slug'] ?? '';
$user_id = null;

// Allow admin to view any user's certificate by passing user_id in URL
if (($_SESSION['user_role'] ?? '') === 'admin' && isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
} else {
    // Default to logged-in user
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    $user_id = $_SESSION['user_id'];
}

if (empty($slug) || !isset($all_courses[$slug]) || !$user_id) {
    die("Invalid course specified.");
}

// --- Data Fetching ---
$user_name = '';
$completion_date = '';
$course_title = $all_courses[$slug]['title'];
$instructor_name = $all_courses[$slug]['instructor'] ?? 'The L.M.S Team';

// Verify the user has completed this course
$stmt = $conn->prepare(
    "SELECT uc.completion_date, u.name 
     FROM user_courses uc
     JOIN users u ON uc.user_id = u.id
     WHERE uc.user_id = ? AND uc.course_slug = ? AND uc.status = 'completed'"
);
$stmt->bind_param("is", $user_id, $slug);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $user_name = $row['name'];
    // Use completion date from DB, fallback to today
    $completion_date = $row['completion_date'] ? date("F j, Y", strtotime($row['completion_date'])) : date("F j, Y");
} else {
    // Not completed or not enrolled
    die("Access Denied: You have not completed this course or are not enrolled.");
}

$stmt->close();
$conn->close();

function esc($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion - <?php echo esc($course_title); ?></title>
      <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">

    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #f0f2f5;
        }
        .cert-container {
            width: 800px;
            height: 565px;
            margin: 50px auto;
            padding: 40px;
            background: #fff;
            border: 10px solid #28a745;
            position: relative;
            font-family: 'Georgia', serif;
            color: #333;
            box-shadow: 0 0 20px rgba(0,0,0,0.15);
        }
        .cert-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .cert-header h1 {
            font-size: 48px;
            color: #218838;
            margin: 0;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .cert-body {
            text-align: center;
        }
        .cert-body p {
            font-size: 18px;
            margin: 15px 0;
        }
        .cert-name {
            font-size: 40px;
            font-weight: bold;
            color: #28a745;
            border-bottom: 2px solid #ddd;
            display: inline-block;
            padding-bottom: 5px;
            margin: 10px 0;
        }
        .cert-course {
            font-size: 28px;
            font-style: italic;
            color: #555;
            margin: 20px 0;
        }
        .cert-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            font-size: 16px;
        }
        .cert-footer .signature, .cert-footer .date {
            border-top: 1px solid #555;
            padding-top: 10px;
            width: 40%;
            text-align: center;
        }
        .cert-logo {
            position: absolute;
            top: 40px;
            left: 40px;
            width: 80px;
        }
        .cert-seal {
            position: absolute;
            bottom: 40px;
            right: 40px;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            border: 5px double #b02a37;
            color: #b02a37;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            font-family: 'Arial', sans-serif;
            font-weight: bold;
            transform: rotate(-12deg);
            opacity: 0.85;
        }
        .seal-content {
            border: 2px solid #b02a37;
            border-radius: 50%;
            width: 88%;
            height: 88%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 5px;
        }
        .seal-text-top { font-size: 1.2em; line-height: 1; }
        .seal-icon { font-size: 1.5em; line-height: 1; margin: 2px 0; }
        .seal-text-bottom {
            font-size: 0.8em; letter-spacing: 1px; line-height: 1;
        }
        .print-button-container {
            text-align: center;
            margin: 40px 0;
        }
        .print-button {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .print-button:hover {
            background: #218838;
        }

        @media print {
            body {
                background: #fff;
            }
            .navbar, .footer, .print-button-container {
                display: none;
            }
            .cert-container {
                margin: 0;
                width: 100%;
                height: auto;
                box-shadow: none;
                border: 10px solid #28a745;
            }
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
            <li><a href="my_enrollments.php">My Courses</a></li>
            <li><a href="courses.php">Course Catalog</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="print-button-container">
        <button class="print-button" onclick="window.print()">Print Certificate</button>
    </div>

    <div class="cert-container">
        <img src="../images/logo.jpg" alt="LMS Logo" class="cert-logo">
        <div class="cert-seal" aria-label="Official Seal">
            <div class="seal-content">
                <div class="seal-text-top">L.M.S</div>
                <div class="seal-icon">★</div>
                <div class="seal-text-bottom">VERIFIED</div>
            </div>
        </div>

        <div class="cert-header">
            <h1>Certificate of Completion</h1>
        </div>

        <div class="cert-body">
            <p>This is to certify that</p>
            <div class="cert-name"><?php echo esc($user_name); ?></div>
            <p>has successfully completed the online course</p>
            <div class="cert-course">"<?php echo esc($course_title); ?>"</div>
        </div>

        <div class="cert-footer">
            <div class="date">
                <strong>Date of Issue</strong><br>
                <?php echo esc($completion_date); ?>
            </div>
            <div class="signature">
                <strong>Instructor</strong><br>
                <?php echo esc($instructor_name); ?>
            </div>
        </div>
    </div>

    <footer class="footer" style="text-align: center; padding: 20px;">
        &copy; <?php echo date('Y'); ?> L.M.S. All rights reserved.
    </footer>
</body>
</html>