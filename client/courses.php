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
    "SELECT course_slug, progress, status FROM user_courses WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Use slug as key for easy access
    $enrolled_courses_data[$row['course_slug']] = $row;
}
$stmt->close();

// Determine recommended courses (courses not enrolled in)
$recommended_courses = array_diff_key($all_courses, $enrolled_courses_data);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Courses - L.M.S</title>
    <link rel="stylesheet" href="../client/style.css">
    <link rel="shortcut icon" href="../images/logo.jpg" type="image/x-icon">
    <style>
        /* ===== Base Styles ===== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(120deg, #e0ffe0 60%, #f4f4f4 100%);          
            line-height: 1.6;
        }
        a {
            color: #28a745;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        h1, h2, h3, h4, h5, h6 {
            margin: 0 0 16px 0;
            color: #212529;
        }
        h1 { font-size: 2.5rem; }
        h2 { font-size: 2rem; }
        h3 { font-size: 1.75rem; }
        h4 { font-size: 1.5rem; }
        h5 { font-size: 1.25rem; }
        h6 { font-size: 1rem; }

        /* ===== Layout ===== */
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
        .courses-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        .courses-header h1 {
            color: #28a745;
            font-size: 2em;
        }
        .search-bar {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .search-bar input[type="text"] {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1.5px solid #28a745;
            font-size: 1em;
            outline: none;
            transition: border 0.2s;
        }
        .search-bar input[type="text"]:focus {
            border: 1.5px solid #218838;
            background: #e0ffe0;
        }
        .search-bar button {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-bar button:hover {
            background: #218838;
        }
        .category-filter {
            margin-bottom: 24px;
        }
        .category-filter label {
            font-weight: bold;
            color: #218838;
            margin-right: 8px;
        }
        .category-filter select {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1.5px solid #28a745;
            font-size: 1em;
        }
        .courses-list { display: flex; flex-wrap: wrap; gap: 28px; }
        .course-card { background: #fff; border-radius: 18px; box-shadow: 0 4px 24px #28a74522; width: 320px; padding: 24px 20px 20px 20px; display: flex; flex-direction: column; align-items: flex-start; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; position: relative; overflow: hidden;}
        .course-card:hover { transform: translateY(-6px) scale(1.03); box-shadow: 0 8px 32px #28a74533;}
        .course-thumb { width: 100%; height: 120px; object-fit: cover; border-radius: 12px; margin-bottom: 16px;}
        .course-title { font-size: 1.2rem; font-weight: bold; color: #28a745; margin-bottom: 8px;}
        .course-meta { font-size: 0.95em; color: #888; margin-bottom: 8px;}
        .course-desc { font-size: 1rem; color: #555; margin-bottom: 14px;}
        .progress-bar-bg { width: 100%; height: 10px; background: #e0e0e0; border-radius: 8px; margin-bottom: 8px;}
        .progress-bar { height: 10px; background: linear-gradient(90deg, #28a745 70%, #218838 100%); border-radius: 8px;}
        .progress-label { font-size: 0.95rem; color: #218838; margin-bottom: 6px;}
        .course-action { margin-top: 10px; align-self: flex-end;}
        .course-action button { background: #28a745; color: #fff; border: none; padding: 8px 18px; border-radius: 8px; font-size: 1rem; cursor: pointer; transition: background 0.2s;}
        .course-action button:hover { background: #218838;}
        .course-tags { margin-bottom: 10px; }
        .course-tag {
            display: inline-block;
            background: #e0ffe0;
            color: #218838;
            border-radius: 6px;
            padding: 2px 10px;
            font-size: 0.9em;
            margin-right: 6px;
            margin-bottom: 2px;
        }
        .course-status {
            position: absolute;
            top: 18px;
            right: 18px;
            background: #28a745;
            color: #fff;
            padding: 4px 14px;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: bold;
            box-shadow: 0 2px 8px #28a74522;
        }
        .recommend-section {
            margin-top: 40px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px #28a74522;
            padding: 28px 32px;
        }
        .recommend-section h2 {
            color: #28a745;
            margin-bottom: 16px;
        }
        .recommend-list {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
        }
        .recommend-card {
            background: #e0ffe0;
            border-radius: 12px;
            padding: 18px 22px;
            min-width: 220px;
            flex: 1;
            box-shadow: 0 2px 12px #28a74511;
            transition: background 0.2s, transform 0.2s;
        }
        .recommend-card:hover {
            background: #28a745;
            color: #fff;
            transform: scale(1.04);
        }
        .recommend-card h4 {
            margin-bottom: 6px;
            color: #218838;
        }
        .recommend-card p {
            font-size: 0.98em;
            color: #218838;
        }
        .course-card.marked { outline: 3px solid rgba(40,167,69,0.12); }
        .highlight { background: linear-gradient(90deg,#fff68f,#fff1a8); padding:0 2px; border-radius:2px; }
        .search-count { color:#218838; font-weight:700; margin-left:12px; }

        /* ===== Animations & Motion (added) ===== */
        :root {
            --accent: #28a745;
            --accent-2: #1f7a33;
            --card-elev: 0 8px 30px rgba(27,122,51,0.06);
        }

        .animate {
            opacity: 0;
            transform: translateY(18px) scale(.996);
            transition: opacity .6s ease, transform .6s cubic-bezier(.2,.9,.2,1);
            will-change: opacity, transform;
        }
        .in-view {
            opacity: 1 !important;
            transform: none !important;
        }

        @keyframes pop {
          from { opacity: 0; transform: translateY(12px) scale(.98); }
          60% { transform: translateY(-6px) scale(1.02); opacity: 1; }
          to { transform: none; opacity: 1; }
        }

        @keyframes floatBounce {
          0% { transform: translateY(0); }
          50% { transform: translateY(-6px); }
          100% { transform: translateY(0); }
        }

        /* course card entrance & hover polish */
        .course-card {
            transition: transform .28s ease, box-shadow .28s ease, filter .18s ease;
            will-change: transform, box-shadow;
        }
        .course-card.in-view { animation: pop .46s cubic-bezier(.2,.9,.2,1) both; }
        .course-card:hover { transform: translateY(-10px); box-shadow: 0 20px 48px rgba(27,122,51,0.12); filter: saturate(1.03); }

        /* subtle floating for featured/status */
        .course-status { transition: transform .6s ease, box-shadow .6s ease; }
        .course-status.in-view { animation: floatBounce 4s ease-in-out infinite; box-shadow: 0 8px 30px rgba(40,167,69,0.08); }

        /* progress bar animated (start collapsed) */
        .progress-bar { width: 0 !important; transition: width 1s cubic-bezier(.2,.9,.2,1); }

        /* sidebar, hero, recommend cards reveal */
        .sidebar.animate, .main-content.animate, .recommend-card.animate, .hero.animate {
            opacity: 0; transform: translateY(12px);
        }
        .sidebar.in-view, .main-content.in-view, .recommend-card.in-view, .hero.in-view {
            opacity: 1; transform: none;
        }

        /* small accessibility: reduced motion */
        @media (prefers-reduced-motion: reduce) {
            .animate, .course-card, .progress-bar { transition: none !important; animation: none !important; }
        }

        @media (max-width: 900px) {
            .courses-header { flex-direction: column; gap: 18px; }
            .courses-list { flex-direction: column; gap: 18px; }
            .course-card { width: 100%; }
            .recommend-list { flex-direction: column; gap: 16px; }
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
    <nav class="navbar animate">
        <div class="nav-logo">
            <img src="../images/logo.jpg" alt="L.M.S Logo" class="nav-logo-img" />
            L.M.S
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="my_enrollments.php">My Courses</a></li>
            <li><a href="courses.php">Course Catalog</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <div class="dashboard-container">
        <aside class="sidebar animate">
            <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="User Avatar" class="sidebar-logo user-avatar active" />
            <h2>L.M.S</h2>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="my_enrollments.php">My Courses</a></li>
                <li><a href="courses.php">Course Catalog</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="settings.php">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>

            </ul>
        </aside>
        <main class="main-content animate">
            <div class="courses-header">
                <h1>Courses Catalogs</h1>
                <form class="search-bar" onsubmit="return false;" role="search" aria-label="Search courses">
                    <input id="searchInput" type="text" name="search" placeholder="Search courses, tags, instructor..." autocomplete="off" />
                    <button id="searchBtn" type="button">Search</button>
                    <span id="searchCount" class="search-count" aria-live="polite"></span>
                </form>
            </div>
            <div class="category-filter">
                <label for="category">Filter by Category:</label>
                <select id="category" name="category" aria-label="Category filter">
                    <option value="">All</option>
                    <option value="web">Web Development</option>
                    <option value="python">Python</option>
                    <option value="data">Data Science</option>
                    <option value="design">Design</option>
                    <option value="ai">AI</option>
                    <option value="languages">Languages</option>
                </select>
            </div>
            <div class="courses-list" id="coursesList">
                <?php if (empty($all_courses)): ?>
                    <p>No courses are available at the moment. Please check back later.</p>
                <?php else: ?>
                    <?php foreach ($all_courses as $slug => $details): ?>
                        <?php
                            $is_enrolled = isset($enrolled_courses_data[$slug]);
                        ?>
                        <div class="course-card animate" data-slug="<?php echo htmlspecialchars($slug); ?>" data-title="<?php echo htmlspecialchars(strtolower($details['title'])); ?>" data-tags="<?php echo htmlspecialchars(implode(',', $details['tags'] ?? [])); ?>" data-instructor="<?php echo htmlspecialchars(strtolower($details['instructor'] ?? '')); ?>" data-cat="<?php echo htmlspecialchars($details['category'] ?? ''); ?>">
                            <?php if ($is_enrolled): ?>
                                <?php
                                    $enrollment = $enrolled_courses_data[$slug];
                                    // Determine status text and style
                                    $status_text = ucfirst($enrollment['status']);
                                    $status_style = '';
                                    if ($enrollment['status'] === 'completed') {
                                        $status_style = 'background:#dc3545;';
                                    } elseif ($enrollment['status'] === 'in-progress' && $enrollment['progress'] > 0) {
                                        $status_style = 'background:#ffc107;color:#333;';
                                    }
                                ?>
                                <span class="course-status" style="<?php echo $status_style; ?>"><?php echo htmlspecialchars($status_text); ?></span>
                                <img src="<?php echo htmlspecialchars($details['thumb'] ?? '../images/course_default.jpg'); ?>" alt="<?php echo htmlspecialchars($details['title']); ?>" class="course-thumb" />
                                <div class="course-title"><?php echo htmlspecialchars($details['title']); ?></div>
                                <div class="course-meta">Category: <?php echo htmlspecialchars(ucfirst($details['category'] ?? 'General')); ?> &nbsp;|&nbsp; Instructor: <?php echo htmlspecialchars($details['instructor'] ?? 'N/A'); ?></div>
                                <?php if (!empty($details['tags'])): ?>
                                    <div class="course-tags">
                                        <?php foreach($details['tags'] as $tag): ?><span class="course-tag"><?php echo htmlspecialchars($tag); ?></span><?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="course-desc"><?php echo htmlspecialchars($details['short']); ?></div>
                                <div class="progress-label">Progress: <?php echo $enrollment['progress']; ?>%</div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar" data-width="<?php echo $enrollment['progress']; ?>%"></div>
                                </div>
                                <div class="course-action">
                                    <?php if ($enrollment['status'] === 'completed'): ?>
                                        <button data-action="certificate" style="background:#dc3545;">View Certificate</button>
                                    <?php else: ?>
                                        <button data-action="continue">Continue Learning</button>
                                    <?php endif; ?>
                                </div>
                            <?php else: // This is a recommended course (not enrolled) ?>
                                <span class="course-status" style="background:#17a2b8;">Recommended</span>
                                <img src="<?php echo htmlspecialchars($details['thumb'] ?? '../images/course_default.jpg'); ?>" alt="<?php echo htmlspecialchars($details['title']); ?>" class="course-thumb" />
                                <div class="course-title"><?php echo htmlspecialchars($details['title']); ?></div>
                                <div class="course-meta">Category: <?php echo htmlspecialchars(ucfirst($details['category'] ?? 'General')); ?> &nbsp;|&nbsp; Instructor: <?php echo htmlspecialchars($details['instructor'] ?? 'N/A'); ?></div>
                                <?php if (!empty($details['tags'])): ?>
                                    <div class="course-tags">
                                        <?php foreach($details['tags'] as $tag): ?><span class="course-tag"><?php echo htmlspecialchars($tag); ?></span><?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="course-desc"><?php echo htmlspecialchars($details['short']); ?></div>
                                <div class="progress-label">Progress: Not Enrolled</div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar" data-width="0%"></div>
                                </div>
                                <div class="course-action">
                                    <button onclick="enrollCourse('<?php echo htmlspecialchars($slug); ?>', this)" data-action="enroll">Enroll Now</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- The separate recommendations section is now integrated above, so it can be removed. -->
        </main>
    </div>
    
<script>
// filepath: c:\xampp\htdocs\online LMS\client\courses.php (inline script section)
// Active client-side search: debounce, category filter, highlight matches, accessibility
(function(){
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    const category = document.getElementById('category');
    const courses = Array.from(document.querySelectorAll('.course-card'));
    const countEl = document.getElementById('searchCount');

    // utility: escape regex
    const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    // highlight function (idempotent)
    function highlightText(container, term){
        const original = container.querySelectorAll('.course-title, .course-desc, .course-meta, .course-tags');
        original.forEach(el=>{
            el.innerHTML = el.textContent;
        });
        if (!term) return;
        const re = new RegExp('(' + esc(term) + ')', 'ig');
        original.forEach(el=>{
            el.innerHTML = el.textContent.replace(re, '<span class="highlight">$1</span>');
        });
    }

    function filter(q, catVal){
        const query = (q||'').trim().toLowerCase();
        let visible = 0;
        courses.forEach(card => {
            const title = (card.dataset.title || '').toLowerCase();
            const tags = (card.dataset.tags || '').toLowerCase();
            const instructor = (card.dataset.instructor || '').toLowerCase();
            const cat = (card.dataset.cat || '').toLowerCase();

            const matchesCat = !catVal || cat === catVal;
            const matchesQuery = !query || title.includes(query) || tags.includes(query) || instructor.includes(query) || (card.querySelector('.course-desc')?.textContent || '').toLowerCase().includes(query);

            if (matchesCat && matchesQuery) {
                card.style.display = '';
                card.classList.add('marked');
                highlightText(card, query);
                visible++;
            } else {
                card.style.display = 'none';
                card.classList.remove('marked');
                const els = card.querySelectorAll('.course-title, .course-desc, .course-meta, .course-tags');
                els.forEach(el => el.innerHTML = el.textContent);
            }
        });
        countEl.textContent = visible ? `${visible} result${visible>1 ? 's' : ''}` : 'No results';
    }

    // debounce
    function debounce(fn, wait=220){
        let t;
        return (...args)=>{ clearTimeout(t); t = setTimeout(()=>fn.apply(this,args), wait); };
    }

    const runSearch = debounce(()=> filter(searchInput.value, category.value), 180);

    searchInput.addEventListener('input', runSearch);
    searchBtn.addEventListener('click', ()=> filter(searchInput.value, category.value));
    category.addEventListener('change', ()=> filter(searchInput.value, category.value));

    // keyboard: / to focus search
    window.addEventListener('keydown', e=>{
        if (e.key === '/' && document.activeElement !== searchInput) {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });

    // initial animate-aware trigger: reveal counts
    document.addEventListener('DOMContentLoaded', ()=> {
        // ensure initial count shows total
        countEl.textContent = courses.length + ' results';
    });

    // ===== IntersectionObserver for reveal & progress animation =====
    const revealTargets = document.querySelectorAll('.animate, .course-card, .recommend-card, .sidebar, .navbar');
    const observerOpts = { root: null, rootMargin: '0px 0px -8% 0px', threshold: 0.12 };

    function onIntersect(entries, obs){
        entries.forEach(entry=>{
            if (entry.isIntersecting) {
                const el = entry.target;
                el.classList.add('in-view');
                // animate progress bars inside element
                const bars = el.querySelectorAll ? el.querySelectorAll('.progress-bar') : [];
                bars.forEach(b=>{
                    if (!b.dataset.animated) {
                        const w = b.dataset.width || b.getAttribute('data-width') || b.style.width || '100%';
                        // ensure percent formatting
                        b.style.width = (w.toString().endsWith('%') ? w : w + '%');
                        b.dataset.animated = '1';
                    }
                });
                // animate status badge
                const status = el.querySelector && el.querySelector('.course-status');
                if (status && !status.dataset.animated) {
                    status.classList.add('in-view');
                    status.dataset.animated = '1';
                }
                obs.unobserve(el);
            }
        });
    }

    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(onIntersect, observerOpts);
        revealTargets.forEach(t => io.observe(t));
    } else {
        // fallback: reveal all and animate bars
        revealTargets.forEach(t => t.classList.add('in-view'));
        document.querySelectorAll('.progress-bar').forEach(b=>{
            const w = b.dataset.width || b.getAttribute('data-width') || b.style.width || '100%';
            b.style.width = (w.toString().endsWith('%') ? w : w + '%');
        });
    }

    // ===== Activate all buttons (Continue / Start / Details / Certificate) =====
    // Adds semantic navigation and sensible fallbacks. Adjust endpoints if you have real routes.
    function toUrl(path, params = {}) {
        // Correctly resolve relative paths from the current page's directory
        const u = new URL(path, window.location.href);
        Object.keys(params).forEach(k => u.searchParams.set(k, params[k]));
        return u.toString();
    }

    // make entire card clickable (secondary to individual buttons)
    document.querySelectorAll('.course-card').forEach(card => {
        const slug = (card.dataset.slug || '').trim();
        // open details when clicking the card (but not when clicking a button/link inside)
        card.addEventListener('click', (ev) => {
            if (ev.target.closest('button') || ev.target.closest('a')) return;
            if (slug) window.location.href = toUrl('course_detail.php', { slug: slug });
        });

        // attach handlers to buttons inside each card
        Array.from(card.querySelectorAll('button')).forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = (btn.dataset.action || btn.textContent || '').trim().toLowerCase();
                // prefer data-action or data-course if present
                const courseSlug = card.dataset.slug || '';
                if (action.includes('continue') || action.includes('start')) {
                    // go to course player
                    window.location.href = toUrl('course_detail.php', { slug: courseSlug });
                    return;
                }
                if (action.includes('details')) {
                    window.location.href = toUrl('course_detail.php', { slug: courseSlug });
                    return;
                }
                if (action.includes('certificate')) {
                    // certificate or view certificate
                    window.location.href = toUrl('certificate.php', { slug: courseSlug });
                    return;
                }
                if (action.includes('enroll')) {
                    // This is now handled by the onclick attribute and enrollCourse() function
                    // to ensure the correct slug is used.
                    return;
                }
                // fallback: show quick info
                alert('Action: ' + btn.textContent + '\nCourse: ' + courseSlug);
            });
        });
    });

    // keyboard accessibility: Enter on focused card triggers details
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && document.activeElement && document.activeElement.classList.contains('course-card')) {
            const card = document.activeElement;
            const slug = card.dataset.slug || '';
            window.location.href = toUrl('course_detail.php', { slug });
        }
    });

    // set tabindex for cards to be keyboard-focusable
    document.querySelectorAll('.course-card').forEach(c => {
        if (!c.hasAttribute('tabindex')) c.setAttribute('tabindex', '0');
        c.style.outline = 'none';
    });

})(); 

function enrollCourse(courseSlug, buttonElement) {
    fetch('enroll.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ course: courseSlug })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message); // Show the message from the server
        if (data.success) {
            buttonElement.textContent = 'Enrolled'; // Change button text
            buttonElement.disabled = true; // Disable the button
            // Optional: Reload the page to see the course move to "enrolled" state visually
            // setTimeout(() => {
            //     window.location.reload();
            // }, 1500);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while enrolling. Please try again.');
    });
}
</script>
</body>
</html>