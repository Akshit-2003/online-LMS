<?php
session_start();
$courses = include __DIR__ . '/study_content.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '' || !isset($courses[$slug])) {
    // fallback to first course if not found
    $keys = array_keys($courses);
    $slug = $keys[0] ?? '';
}
$course = $courses[$slug] ?? null;
if (!$course) {
    http_response_code(404);
    echo "Course not found.";
    exit;
}

// Check if user is enrolled to show progress features
$is_enrolled = false;
$user_progress = 0;
$avatar_path = '../images/user.jpg'; // Default avatar
if (isset($_SESSION['user_id'])) {
    require_once 'db_connect.php';
    $stmt = $conn->prepare("SELECT progress FROM user_courses WHERE user_id = ? AND course_slug = ?");
    $stmt->bind_param("is", $_SESSION['user_id'], $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $is_enrolled = true;
        $user_progress = $row['progress'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT avatar_path FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) { $avatar_path = $row['avatar_path'] ?? $avatar_path; }
    $stmt->close();
    $conn->close();
}

// Function to sanitize and escape output
function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo esc($course['title']); ?> — Study Materials</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    /* Layout styles from dashboard for consistency */
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
    .main-content h1 { color: #28a745; }
    .main-content h2 { color: #1f7a33; }
    .main-content h3 { color: #28a745; }
    .content-card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 24px #28a7451a; margin-bottom: 24px; }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e0ffe9;
    }

    /* Modal Styles */
    .modal {
      display: none; /* Hidden by default */
      position: fixed; /* Stay in place */
      z-index: 10000; /* Sit on top */
      left: 0;
      top: 0;
      width: 100%; /* Full width */
      height: 100%; /* Full height */
      overflow: auto; /* Enable scroll if needed */
      background-color: rgba(0,0,0,0.6); /* Black w/ opacity */
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background-color: #fefefe;
      margin: auto;
      padding: 30px;
      border-radius: 12px;
      width: 80%;
      max-width: 700px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.2);
      animation: modal-pop 0.4s cubic-bezier(.2,.9,.2,1);
      position: relative;
    }
    .close-button {
      color: #aaa;
      position: absolute;
      top: 10px;
      right: 20px;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }
    .close-button:hover,
    .close-button:focus {
      color: #333;
      text-decoration: none;
      cursor: pointer;
    }
    .modal-title {
      color: #28a745;
      margin-top: 0;
      margin-bottom: 15px;
    }
    .modal-body {
      line-height: 1.6;
      color: #555;
    }
    .modal-video-container {
        position: relative;
        padding-bottom: 56.25%; /* 16:9 aspect ratio */
        height: 0;
        overflow: hidden;
    }
    .modal-video-container iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    .quiz-question { margin-bottom: 16px; }
    .quiz-options label { display: block; margin-bottom: 8px; padding: 8px; border-radius: 6px; border: 1px solid #ddd; cursor: pointer; }
    .quiz-options label:hover { background: #f0f0f0; }
    #quizSubmitBtn { background: #28a745; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-top: 10px; }
    #quizSubmitBtn:disabled { background: #6c757d; cursor: not-allowed; }
    .quiz-options label.correct-answer { background-color: #d1e7dd; border-color: #a3cfbb; font-weight: bold; }
    .quiz-options label.incorrect-answer { background-color: #f8d7da; border-color: #f1aeb5; }
    .modal-footer {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #e9ecef;
    }
    .modal-footer button {
        background: #6c757d; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;
    }
    .modal-footer button:hover { background: #5a6268; }
    #quizResult { margin-top: 15px; font-weight: bold; }
    .lesson-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 8px;
        transition: background-color 0.2s, border-color 0.2s;
    }
    .lesson-item:hover { background-color: #f8f9fa; border-color: #dee2e6; }
    .lesson-item.completed > button { background: #198754 !important; }
    .lesson-item.completed::before { content: '✔'; color: #198754; font-weight: bold; margin-right: 6px; }
    .lesson-item button { margin-left:8px;padding:6px 12px;border-radius:6px;border:0;background:#28a745;color:#fff;cursor:pointer; font-weight: bold; transition: background-color 0.2s; }
    .lesson-item button:hover { background-color: #218838; }
    .progress-bar-container { width: 100%; background: #e9ecef; border-radius: 8px; height: 18px; margin-bottom: 12px; }
    .progress-bar { background: #28a745; height: 100%; border-radius: 8px; transition: width 0.5s ease; text-align: center; color: white; font-weight: bold; font-size: 0.8em; line-height: 18px; }
    .resource-list li { margin-bottom: 10px; }
    .resource-list a { color: #218838; font-weight: 500; text-decoration: none; border-bottom: 1px dashed #218838; }
    .resource-list a:hover { color: #28a745; border-bottom-style: solid; }
    .quick-links-list li { margin-bottom: 10px; }
    .quick-links-list a { text-decoration: none; font-weight: 500; }
    .course-details-list { list-style: none; padding: 0; margin: 12px 0 0 0; font-size: 0.95em; color: #555; }
    .course-details-list li { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .course-details-list li:last-child { border-bottom: none; }
    .course-details-list strong { color: #333; }
    .course-details-list span { text-align: right; }
    .course-video-wrapper {
        position: relative; padding-bottom: 56.25%; /* 16:9 */ height: 0;
        border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px #28a7451a; margin-bottom: 24px;
    }
    .course-video-wrapper iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
    .quick-links-list a.current { color: #1f7a33; font-weight: bold; }
    @keyframes modal-pop { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
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
            <li><a href="courses.php" class="active">Course Catalog</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
<div class="dashboard-container">
    <aside class="sidebar">
        <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="User Avatar" class="sidebar-logo user-avatar" />
        
        <h2>L.M.S</h2>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="my_enrollments.php">My Courses</a></li>
            <li><a href="courses.php" class="active">Course Catalog</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>
  <main class="main-content">
    <header class="page-header">
        <div>
            <h1 style="margin:0;"><?php echo esc($course['title']); ?></h1>
            <p style="color:#444;margin:6px 0 0 0;"><?php echo esc($course['short']); ?></p>
        </div>
        <div>
            <a href="courses.php" style="color:#218838;text-decoration:none; margin-right: 16px;">&larr; Back to Catalog</a>
            <a href="course_detail.php?slug=<?php echo urlencode($slug); ?>&print=1" style="background:#eee;padding:8px 12px;border-radius:8px;text-decoration:none;color:#333">Print View</a>
        </div>
    </header>

    <section style="display:grid;grid-template-columns: 1fr 320px; gap:28px;">
      <article>
        <?php if (!empty($course['intro_video_id'])): ?>
            <div class="course-video-wrapper">
                <iframe 
                    src="https://www.youtube.com/embed/<?php echo esc($course['intro_video_id']); ?>" 
                    title="YouTube video player for <?php echo esc($course['title']); ?>" 
                    frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
        <?php endif; ?>
        <?php if ($is_enrolled): ?>
            <div class="content-card">
                <h3>Your Progress</h3>
                <div class="progress-bar-container">
                    <div id="courseProgressBar" class="progress-bar" style="width: <?php echo $user_progress; ?>%;"><?php echo $user_progress; ?>%</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <h2>Course Overview</h2>
            <p><?php echo esc($course['description']); ?></p>
        </div>

        <div class="content-card">
            <h3>Syllabus</h3>
            <?php foreach($course['syllabus'] as $sectionIdx => $section): ?>
              <div style="margin-bottom:20px;">
                <strong><?php echo esc(($sectionIdx + 1) . '. ' . $section['title']); ?></strong>
                <div style="margin-top:12px;color:#444;list-style:none;padding-left:0;">
                  <?php foreach($section['lessons'] as $lessonIdx => $lesson): ?>
                    <div class="lesson-item" data-section-idx="<?php echo $sectionIdx; ?>" data-lesson-idx="<?php echo $lessonIdx; ?>">
                        <span><?php echo esc($lesson['title']); ?></span>
                        <button onclick="openLesson(<?php echo $sectionIdx; ?>, <?php echo $lessonIdx; ?>)">
                            <?php echo (($lesson['type'] ?? 'text') === 'quiz') ? 'Start Quiz' : 'Open'; ?>
                        </button>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
        </div>

        <div class="content-card">
            <h3>Resources</h3>
            <ul class="resource-list" style="list-style:none; padding-left:0;">
              <?php foreach($course['resources'] as $r): ?>
                <li><a href="<?php echo esc($r['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc($r['label']); ?></a></li>
              <?php endforeach; ?>
            </ul>
        </div>
      </article>

      <aside style="position:relative;">
        <div class="content-card">
          <h4 style="margin-top:0;color:#28a745">About this course</h4>
          <ul class="course-details-list">
            <?php if (!empty($course['level'])): ?>
                <li><strong>Level</strong> <span><?php echo esc($course['level']); ?></span></li>
            <?php endif; ?>
            <?php if (!empty($course['duration'])): ?>
                <li><strong>Duration</strong> <span><?php echo esc($course['duration']); ?></span></li>
            <?php endif; ?>
            <?php if (!empty($course['language'])): ?>
                <li><strong>Language</strong> <span><?php echo esc($course['language']); ?></span></li>
            <?php endif; ?>
            <?php if (!empty($course['prerequisites'])): ?>
                <li><strong>Prerequisites</strong> <span><?php echo esc(implode(', ', $course['prerequisites'])); ?></span></li>
            <?php endif; ?>
            <li><strong>Access</strong> <span>Full lifetime</span></li>
          </ul>
        </div>

        <div class="content-card">
          <h4>Quick Links</h4>
          <ul class="quick-links-list" style="list-style:none;padding:0;margin:8px 0 0 0;">
            <?php foreach($courses as $s => $c): ?>
              <li><a href="course_detail.php?slug=<?php echo urlencode($s); ?>" class="<?php echo $s===$slug?'current':''; ?>"><?php echo esc($c['title']); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <?php if (!empty($course['related'])): ?>
        <div class="content-card">
          <h4>Related Courses</h4>
          <ul class="quick-links-list" style="list-style:none;padding:0;margin:8px 0 0 0;">
            <?php foreach($course['related'] as $rel): if (!isset($courses[$rel])) continue; $rc = $courses[$rel]; ?>
              <li><a href="course_detail.php?slug=<?php echo urlencode($rel); ?>"><?php echo esc($rc['title']); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </aside>
    </section>
  </main>
</div>

  <!-- Lesson Modal Structure -->
  <div id="lessonModal" class="modal">
    <div class="modal-content">
      <span class="close-button" onclick="closeLessonModal()">&times;</span>
      <h2 id="modalTitle" class="modal-title"></h2>
      <div id="modalBody" class="modal-body">
        <!-- Content will be injected here -->
      </div>
      <div id="modalFooter" class="modal-footer">
        <button id="prevLessonBtn">Previous Lesson</button>
        <button id="nextLessonBtn">Next Lesson</button>
      </div>
    </div>
  </div>

  <script>
    const courseData = <?php echo json_encode($course); ?>;
    const courseSlug = '<?php echo $slug; ?>';
    const isEnrolled = <?php echo json_encode($is_enrolled); ?>;

    const lessonModal = document.getElementById('lessonModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const progressBar = document.getElementById('courseProgressBar');
    const modalFooter = document.getElementById('modalFooter');
    const prevLessonBtn = document.getElementById('prevLessonBtn');
    const nextLessonBtn = document.getElementById('nextLessonBtn');

    let currentLesson = { section: -1, lesson: -1 };
    let completedLessons = new Set();

    // Load progress from localStorage
    function loadProgress() {
        if (!isEnrolled) return;
        const saved = localStorage.getItem(`progress_${courseSlug}`);
        if (saved) {
            completedLessons = new Set(JSON.parse(saved));
        }
        updateUI();
    }

    function findAdjacentLessons(currentSectionIdx, currentLessonIdx) {
        let allLessons = [];
        courseData.syllabus.forEach((section, sIdx) => {
            section.lessons.forEach((lesson, lIdx) => {
                allLessons.push({ section: sIdx, lesson: lIdx });
            });
        });

        const currentIndex = allLessons.findIndex(
            l => l.section === currentSectionIdx && l.lesson === currentLessonIdx
        );

        return {
            prev: currentIndex > 0 ? allLessons[currentIndex - 1] : null,
            next: currentIndex < allLessons.length - 1 ? allLessons[currentIndex + 1] : null
        };
    }

    function openLesson(sectionIdx, lessonIdx) {
        currentLesson = { section: sectionIdx, lesson: lessonIdx };
        const lesson = courseData.syllabus[sectionIdx].lessons[lessonIdx];
        modalTitle.textContent = lesson.title;
        modalBody.innerHTML = ''; // Clear previous content

        if (lesson.type === 'video') {
            modalBody.innerHTML = `
                <div class="modal-video-container">
                    <iframe src="https://www.youtube.com/embed/${lesson.videoId}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>`;
        } else if (lesson.type === 'quiz') {
            let quizHtml = '<form id="quizForm">';
            lesson.questions.forEach((q, index) => {
                quizHtml += `<div class="quiz-question"><strong>${index + 1}. ${q.q}</strong><div class="quiz-options">`;
                q.options.forEach((opt, optIndex) => {
                    quizHtml += `<label><input type="radio" name="q${index}" value="${optIndex}"> ${opt}</label>`;
                });
                quizHtml += `</div></div>`;
            });
            quizHtml += `<button type="submit" id="quizSubmitBtn">Submit Quiz</button></form><div id="quizResult"></div>`;
            modalBody.innerHTML = quizHtml;

            document.getElementById('quizForm').addEventListener('submit', (e) => {
                e.preventDefault();
                handleQuizSubmit(lesson.questions);
            });
        } else { // 'text'
            modalBody.innerHTML = `<p>${lesson.content}</p>`;
        }

        // Handle nav buttons
        const { prev, next } = findAdjacentLessons(sectionIdx, lessonIdx);
        modalFooter.style.display = 'flex';
        prevLessonBtn.style.display = prev ? 'inline-block' : 'none';
        if (prev) {
            prevLessonBtn.onclick = () => openLesson(prev.section, prev.lesson);
        }
        nextLessonBtn.style.display = next ? 'inline-block' : 'none';
        if (next) {
            nextLessonBtn.onclick = () => openLesson(next.section, next.lesson);
        }

        lessonModal.style.display = 'flex';
    }

    function closeLessonModal() {
        // For non-quiz content, mark as complete on close
        const lesson = courseData.syllabus[currentLesson.section]?.lessons[currentLesson.lesson];
        if (lesson && lesson.type !== 'quiz' && isEnrolled) {
            markLessonComplete(currentLesson.section, currentLesson.lesson);
        }
        lessonModal.style.display = 'none';
    }

    function handleQuizSubmit(questions) {
        const form = document.getElementById('quizForm');
        const quizSubmitBtn = document.getElementById('quizSubmitBtn');
        let score = 0;

        // Disable form elements after submission
        quizSubmitBtn.disabled = true;
        form.querySelectorAll('input[type="radio"]').forEach(radio => radio.disabled = true);

        questions.forEach((q, index) => {
            const options = form.querySelectorAll(`input[name="q${index}"]`);
            const selectedRadio = form.querySelector(`input[name="q${index}"]:checked`);
            const correctAnswerIndex = q.answer;
            const correctLabel = options[correctAnswerIndex].parentElement;

            // Always highlight the correct answer
            correctLabel.classList.add('correct-answer');

            if (selectedRadio) {
                const selectedLabel = selectedRadio.parentElement;
                if (parseInt(selectedRadio.value) === correctAnswerIndex) {
                    score++;
                } else {
                    // If selected is wrong, mark it as incorrect
                    selectedLabel.classList.add('incorrect-answer');
                }
            }
        });

        const percentage = (score / questions.length) * 100;
        const resultEl = document.getElementById('quizResult');
        resultEl.innerHTML = `You scored <strong>${score}/${questions.length}</strong> (${percentage.toFixed(0)}%).`;

        if (percentage >= 70) { // Passing score
            resultEl.style.color = 'green';
            if (isEnrolled) {
                markLessonComplete(currentLesson.section, currentLesson.lesson);
            }
        } else {
            resultEl.style.color = 'red';
            resultEl.innerHTML += '<br>Please review the material. The quiz will reset when you reopen it.';
        }
    }

    function markLessonComplete(sectionIdx, lessonIdx) {
        const lessonKey = `${sectionIdx}-${lessonIdx}`;
        if (completedLessons.has(lessonKey)) return; // Already complete

        completedLessons.add(lessonKey);
        localStorage.setItem(`progress_${courseSlug}`, JSON.stringify(Array.from(completedLessons)));
        updateUI();
        updateCourseProgress();
    }

    function updateUI() {
        if (!isEnrolled) return;
        document.querySelectorAll('.lesson-item').forEach(item => {
            const sIdx = item.dataset.sectionIdx;
            const lIdx = item.dataset.lessonIdx;
            if (completedLessons.has(`${sIdx}-${lIdx}`)) {
                item.classList.add('completed');
                const button = item.querySelector('button');
                if (button) button.textContent = 'Completed';
            }
        });
    }

    function updateCourseProgress() {
        if (!isEnrolled) return;
        const totalLessons = courseData.syllabus.reduce((acc, section) => acc + section.lessons.length, 0);
        const progress = totalLessons > 0 ? Math.round((completedLessons.size / totalLessons) * 100) : 0;

        if (progressBar) {
            progressBar.style.width = `${progress}%`;
            progressBar.textContent = `${progress}%`;
        }

        saveProgressToBackend(progress);
    }

    async function saveProgressToBackend(progress) {
        try {
            const response = await fetch('update_progress.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ course_slug: courseSlug, progress: progress })
            });
            if (!response.ok) {
                console.error('Failed to save progress to server.');
            }
        } catch (error) {
            console.error('Error saving progress:', error);
        }
    }

    window.onclick = function(event) {
        if (event.target == lessonModal) {
            closeLessonModal();
        }
    };

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lessonModal.style.display === 'flex') {
            closeLessonModal();
        }
    });

    // Initial load
    document.addEventListener('DOMContentLoaded', loadProgress);
  </script>
</body>
</html>