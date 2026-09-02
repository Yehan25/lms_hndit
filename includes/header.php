<?php
// Expects (optionally) $pageTitle and $pageEyebrow to be set before include.
$pageTitle = $pageTitle ?? 'Overview';
$pageEyebrow = $pageEyebrow ?? ucfirst($_SESSION['role'] ?? '');
$current = basename($_SERVER['PHP_SELF']);

function nav_active($file, $current) {
    return $file === $current ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> · HNDIT LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<script>
(function () {
    const storedTheme = localStorage.getItem('lms-theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = storedTheme || (prefersDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
})();
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<link rel="stylesheet" href="/lms_hndit/assets/css/style.css?v=6">
</head>
<body>
<noscript><style>#pageLoader,#pageProgressBar{display:none!important;}.app-shell{opacity:1!important;transform:none!important;}</style></noscript>
<div id="pageLoader" role="status" aria-live="polite" aria-label="Loading page">
    <div class="page-loader__inner">
        <div class="page-loader__badge-wrap">
            <span class="page-loader__ring"></span>
            <span class="page-loader__ring page-loader__ring--reverse"></span>
            <span class="page-loader__badge">
                <img src="/lms_hndit/assets/images/hndit-logo.jpg" alt="HNDIT LMS" loading="eager">
            </span>
        </div>
        <div class="page-loader__text">HNDIT LMS</div>
        <div class="page-loader__dots"><span></span><span></span><span></span></div>
    </div>
</div>
<div id="pageProgressBar"></div>
<div class="app-shell">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark">HD</div>
            <div class="brand-text"><strong>HNDIT LMS</strong><span>LEARNING PORTAL</span></div>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="nav-group-label">Menu</div>

            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="/lms_hndit/admin/dashboard.php" class="nav-link <?php echo nav_active('dashboard.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                    Overview
                </a>
                <a href="/lms_hndit/admin/manage_courses.php" class="nav-link <?php echo nav_active('manage_courses.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    Courses
                </a>
                <a href="/lms_hndit/admin/manage_users.php" class="nav-link <?php echo nav_active('manage_users.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
                <a href="/lms_hndit/admin/manage_enrollments.php" class="nav-link <?php echo nav_active('manage_enrollments.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg>
                    Enrollments
                </a>
                <a href="/lms_hndit/admin/upload_material.php" class="nav-link <?php echo nav_active('upload_material.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
                    Upload Material
                </a>
                <a href="/lms_hndit/admin/view_materials.php" class="nav-link <?php echo nav_active('view_materials.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M9 8h6M9 12h6"/></svg>
                    Materials
                </a>
                <a href="/lms_hndit/admin/create_assignment.php" class="nav-link <?php echo nav_active('create_assignment.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Assignments
                </a>
                <a href="/lms_hndit/admin/manage_quizzes.php" class="nav-link <?php echo nav_active('manage_quizzes.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4"/><path d="M12 17h.01"/></svg>
                    Quizzes
                </a>
            <?php elseif ($_SESSION['role'] === 'lecturer'): ?>
                <a href="/lms_hndit/lecturer/dashboard.php" class="nav-link <?php echo nav_active('dashboard.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                    Overview
                </a>
                <a href="/lms_hndit/lecturer/upload_material.php" class="nav-link <?php echo nav_active('upload_material.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
                    Materials
                </a>
                <a href="/lms_hndit/lecturer/create_assignment.php" class="nav-link <?php echo nav_active('create_assignment.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Assignments
                </a>
                <a href="/lms_hndit/lecturer/manage_quizzes.php" class="nav-link <?php echo nav_active('manage_quizzes.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4"/><path d="M12 17h.01"/></svg>
                    Quizzes
                </a>
            <?php elseif ($_SESSION['role'] === 'student'): ?>
                <a href="/lms_hndit/student/dashboard.php" class="nav-link <?php echo nav_active('dashboard.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                    Overview
                </a>
                <a href="/lms_hndit/student/my_courses.php" class="nav-link <?php echo nav_active('my_courses.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    My Courses
                </a>
                <a href="/lms_hndit/student/enroll.php" class="nav-link <?php echo nav_active('enroll.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg>
                    Enroll
                </a>
                <a href="/lms_hndit/student/view_assignments.php" class="nav-link <?php echo nav_active('view_assignments.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Assignments
                </a>
                <a href="/lms_hndit/student/view_quizzes.php" class="nav-link <?php echo nav_active('view_quizzes.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4"/><path d="M12 17h.01"/></svg>
                    Quizzes
                </a>
                <a href="/lms_hndit/student/view_materials.php" class="nav-link <?php echo nav_active('view_materials.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    Materials
                </a>
                <a href="/lms_hndit/student/view_grades.php" class="nav-link <?php echo nav_active('view_grades.php', $current); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4.5L6 21l1.5-7.5L2 9h7z"/></svg>
                    My Grades
                </a>
            <?php endif; ?>

            <div class="sidebar-footer">
                <div class="user-chip">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                    <div class="user-meta">
                        <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>
                        <span><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                    </div>
                </div>
                <a href="/lms_hndit/logout.php" class="logout-link">Log out</a>
            </div>
        <?php endif; ?>
    </aside>

    <div class="main-wrap">
        <?php if (isset($_SESSION['role'])): ?>
            <?php include __DIR__ . '/header_banner.php'; ?>
        <?php endif; ?>

        <header class="topbar">
            <div style="display:flex; align-items:center;">
                <button type="button" class="mobile-nav-toggle" id="sidebarToggle" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <div class="eyebrow"><?php echo htmlspecialchars($pageEyebrow); ?></div>
                    <h2 style="margin:0; font-size: 20px;"><?php echo htmlspecialchars($pageTitle); ?></h2>
                </div>
            </div>
            <div class="topbar-actions">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Switch color theme" aria-pressed="false">
                    <span class="theme-toggle__icon">🌙</span>
                    <span class="theme-toggle__label">Dark mode</span>
                </button>
                <?php if (isset($_SESSION['role'])): ?>
                    <span class="badge badge-<?php echo $_SESSION['role']; ?>"><?php echo ucfirst($_SESSION['role']); ?></span>
                <?php endif; ?>
            </div>
        </header>
        <main class="content">
