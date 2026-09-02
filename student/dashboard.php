<?php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];

$enrolledCount = $conn->query("SELECT COUNT(*) AS c FROM enrollments WHERE student_id = $student_id")->fetch_assoc()['c'];

$courseIdsRes = $conn->query("SELECT course_id FROM enrollments WHERE student_id = $student_id");
$courseIds = [];
while ($row = $courseIdsRes->fetch_assoc()) { $courseIds[] = $row['course_id']; }
$idListSql = count($courseIds) ? implode(',', array_map('intval', $courseIds)) : '0';

$totalAssignments = $conn->query("SELECT COUNT(*) AS c FROM assignments WHERE course_id IN ($idListSql)")->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM submissions WHERE student_id = ? AND status = 'submitted'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$submittedCount = $stmt->get_result()->fetch_assoc()['c'];

$stmt2 = $conn->prepare("SELECT COUNT(*) AS c FROM submissions WHERE student_id = ? AND status = 'absent'");
$stmt2->bind_param("i", $student_id);
$stmt2->execute();
$absentCount = $stmt2->get_result()->fetch_assoc()['c'];

$stmt3 = $conn->prepare("SELECT COUNT(*) AS c FROM submissions WHERE student_id = ? AND grade IS NOT NULL AND grade != ''");
$stmt3->bind_param("i", $student_id);
$stmt3->execute();
$gradedCount = $stmt3->get_result()->fetch_assoc()['c'];

$pendingAssignments = max(0, $totalAssignments - $submittedCount - $absentCount);
$latestVideos = $conn->query("SELECT m.*, c.course_code, c.course_name FROM materials m JOIN courses c ON m.course_id = c.id WHERE m.material_type = 'video' AND m.course_id IN ($idListSql) ORDER BY m.uploaded_at DESC LIMIT 4");
// Recent announcements from enrolled courses
$announcements = $conn->query("
    SELECT an.*, c.course_name FROM announcements an
    JOIN courses c ON an.course_id = c.id
    WHERE an.course_id IN ($idListSql)
    ORDER BY an.posted_at DESC LIMIT 5
");

$pageTitle = "Overview";
$pageEyebrow = "Student";
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-ring"><svg width="58" height="58" viewBox="0 0 58 58">
            <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
            <circle cx="29" cy="29" r="24" fill="none" stroke="#7c5cff" stroke-width="6" stroke-dasharray="150.7" stroke-linecap="round"/>
        </svg><div class="stat-ring-value">📘</div></div>
        <div class="stat-info"><div class="stat-num"><?php echo $enrolledCount; ?></div><div class="stat-label">Enrolled Courses</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ring"><svg width="58" height="58" viewBox="0 0 58 58">
            <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
            <circle cx="29" cy="29" r="24" fill="none" stroke="#f2a93b" stroke-width="6" stroke-dasharray="150.7" stroke-linecap="round"/>
        </svg><div class="stat-ring-value">📝</div></div>
        <div class="stat-info"><div class="stat-num"><?php echo $pendingAssignments; ?></div><div class="stat-label">Pending Assignments</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ring"><svg width="58" height="58" viewBox="0 0 58 58">
            <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
            <circle cx="29" cy="29" r="24" fill="none" stroke="#12b3a8" stroke-width="6" stroke-dasharray="150.7" stroke-linecap="round"/>
        </svg><div class="stat-ring-value">✅</div></div>
        <div class="stat-info"><div class="stat-num"><?php echo $submittedCount; ?></div><div class="stat-label">Submitted</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ring"><svg width="58" height="58" viewBox="0 0 58 58">
            <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
            <circle cx="29" cy="29" r="24" fill="none" stroke="#ef6461" stroke-width="6" stroke-dasharray="150.7" stroke-linecap="round"/>
        </svg><div class="stat-ring-value"><?php echo $absentCount; ?></div></div>
        <div class="stat-info"><div class="stat-num"><?php echo $gradedCount; ?></div><div class="stat-label">Graded (Absences: <?php echo $absentCount; ?>)</div></div>
    </div>
</div>

<!-- ===== UNIFIED SWIPER SLIDER ===== -->
<div class="dashboard-slider">
    <div class="swiper" id="studentSwiper">
        <div class="swiper-wrapper">
            <div class="swiper-slide">
                <img src="../assets/images/slider/slide1.jpg" alt="Welcome to your modern learning portal">
                <div class="swiper-slide-caption">
                    <span>HNDIT LMS</span>
                    <strong>Welcome to your modern learning portal</strong>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="../assets/images/slider/slide2.jpg" alt="Track progress, submissions, and course milestones">
                <div class="swiper-slide-caption">
                    <span>Academic Excellence</span>
                    <strong>Track progress, submissions, and course milestones</strong>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="../assets/images/slider/slide3.jpg" alt="Stay connected with every course update">
                <div class="swiper-slide-caption">
                    <span>Advanced Technological Institute</span>
                    <strong>Stay connected with every course update</strong>
                </div>
            </div>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
    </div>
</div>

<div class="card">
    <h3>Recent Course Videos</h3>
    <?php if ($latestVideos->num_rows === 0): ?>
        <div class="empty-state">No videos are available for your enrolled courses yet.</div>
    <?php else: ?>
        <?php while ($video = $latestVideos->fetch_assoc()): ?>
            <div style="margin-bottom:18px;">
                <strong><?php echo htmlspecialchars($video['title']); ?></strong>
                <span class="type-tag type-tag-video" style="margin-left:8px;">Video</span>
                <div style="font-size:12px; color:var(--text-muted); margin-bottom:6px;">
                    <?php echo htmlspecialchars($video['course_code'] . ' - ' . $video['course_name']); ?> · <?php echo $video['uploaded_at']; ?>
                </div>
                <video class="material-video" controls preload="metadata">
                    <source src="/lms_hndit/<?php echo htmlspecialchars($video['file_path']); ?>" type="video/mp4">
                    Your browser does not support video playback.
                </video>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Recent Announcements</h3>
    <?php if ($announcements->num_rows === 0): ?>
        <div class="empty-state">No announcements yet.</div>
    <?php endif; ?>
    <?php while ($a = $announcements->fetch_assoc()): ?>
        <div style="border-bottom:1px solid #eee; padding:10px 0;">
            <strong><?php echo htmlspecialchars($a['title']); ?></strong>
            <span style="color:var(--text-faint); font-size:12px;"> · <?php echo htmlspecialchars($a['course_name']); ?></span>
            <p style="margin:6px 0 0 0;"><?php echo nl2br(htmlspecialchars($a['message'])); ?></p>
            <small style="color:#888;"><?php echo $a['posted_at']; ?></small>
        </div>
    <?php endwhile; ?>
</div>

<div class="card">
    <a href="my_courses.php" class="btn">My Courses</a>
    <a href="enroll.php" class="btn btn-outline">Enroll in a Course</a>
    <a href="view_assignments.php" class="btn btn-outline">View Assignments</a>
    <a href="view_quizzes.php" class="btn btn-outline">View Quizzes</a>
    <a href="view_materials.php" class="btn btn-outline">View Materials</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Swiper('#studentSwiper', {
        loop: true,
        autoplay: { delay: 4500, disableOnInteraction: false, pauseOnMouseEnter: true },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        pagination: { el: '.swiper-pagination', clickable: true },
        speed: 600
    });
});
</script>

<?php include '../includes/footer.php'; ?>