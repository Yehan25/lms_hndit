<?php
require_once '../config.php';
require_role('admin');

$courses = $conn->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];
$students = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='student'")->fetch_assoc()['c'];
$lecturers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='lecturer'")->fetch_assoc()['c'];
$submissions = $conn->query("SELECT COUNT(*) AS c FROM submissions")->fetch_assoc()['c'];
$graded = $conn->query("SELECT COUNT(*) AS c FROM submissions WHERE grade IS NOT NULL AND grade != ''")->fetch_assoc()['c'];
$gradedPct = $submissions > 0 ? round(($graded / $submissions) * 100) : 0;

// Top courses by enrollment (for bar chart)
$enrollRes = $conn->query("
    SELECT c.course_code, COUNT(e.id) AS total
    FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id
    GROUP BY c.id ORDER BY total DESC LIMIT 6
");
$courseLabels = []; $courseData = [];
while ($row = $enrollRes->fetch_assoc()) {
    $courseLabels[] = $row['course_code'];
    $courseData[] = (int)$row['total'];
}

// Role distribution (for doughnut chart)
$roleData = [$students, $lecturers, 1]; // 1 = admin (fixed baseline)
$latestVideos = $conn->query("SELECT m.*, c.course_code, c.course_name FROM materials m JOIN courses c ON m.course_id = c.id WHERE m.material_type = 'video' ORDER BY m.uploaded_at DESC LIMIT 4");

$pageTitle = "Overview";
$pageEyebrow = "Admin";
include '../includes/header.php';
?>

<!-- Swiper CSS (if not in header, load here) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-ring">
            <svg width="58" height="58" viewBox="0 0 58 58">
                <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
                <circle cx="29" cy="29" r="24" fill="none" stroke="#7c5cff" stroke-width="6"
                    stroke-dasharray="150.7" stroke-dashoffset="0" stroke-linecap="round"/>
            </svg>
            <div class="stat-ring-value">📘</div>
        </div>
        <div class="stat-info"><div class="stat-num"><?php echo $courses; ?></div><div class="stat-label">Active Courses</div></div>
    </div>

    <div class="stat-card">
        <div class="stat-ring">
            <svg width="58" height="58" viewBox="0 0 58 58">
                <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
                <circle cx="29" cy="29" r="24" fill="none" stroke="#12b3a8" stroke-width="6"
                    stroke-dasharray="150.7" stroke-dashoffset="0" stroke-linecap="round"/>
            </svg>
            <div class="stat-ring-value">🎓</div>
        </div>
        <div class="stat-info"><div class="stat-num"><?php echo $students; ?></div><div class="stat-label">Enrolled Students</div></div>
    </div>

    <div class="stat-card">
        <div class="stat-ring">
            <svg width="58" height="58" viewBox="0 0 58 58">
                <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
                <circle cx="29" cy="29" r="24" fill="none" stroke="#f2a93b" stroke-width="6"
                    stroke-dasharray="150.7" stroke-dashoffset="0" stroke-linecap="round"/>
            </svg>
            <div class="stat-ring-value">👨‍🏫</div>
        </div>
        <div class="stat-info"><div class="stat-num"><?php echo $lecturers; ?></div><div class="stat-label">Lecturers</div></div>
    </div>

    <div class="stat-card">
        <div class="stat-ring">
            <svg width="58" height="58" viewBox="0 0 58 58">
                <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
                <circle cx="29" cy="29" r="24" fill="none" stroke="#ef6461" stroke-width="6"
                    stroke-dasharray="<?php echo round(150.7 * $gradedPct / 100, 1); ?> 150.7" stroke-linecap="round"/>
            </svg>
            <div class="stat-ring-value"><?php echo $gradedPct; ?>%</div>
        </div>
        <div class="stat-info"><div class="stat-num"><?php echo $submissions; ?></div><div class="stat-label">Submissions Graded</div></div>
    </div>
</div>

<!-- ===== UNIFIED SWIPER SLIDER ===== -->
<div class="dashboard-slider">
    <div class="swiper" id="adminSwiper">
        <div class="swiper-wrapper">
            <div class="swiper-slide">
                <img src="../assets/images/slider/slide1.jpg" alt="Monitor the platform">
                <div class="swiper-slide-caption">
                    <span>Monitor the platform</span>
                    <strong>Keep course delivery, user management, and engagement running smoothly.</strong>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="../assets/images/slider/slide2.jpg" alt="Spot the latest trends">
                <div class="swiper-slide-caption">
                    <span>Spot the latest trends</span>
                    <strong>Review activity snapshots and keep your academic community informed.</strong>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="../assets/images/slider/slide3.jpg" alt="Drive consistency">
                <div class="swiper-slide-caption">
                    <span>Drive consistency</span>
                    <strong>Support academic operations with clear dashboards and timely updates.</strong>
                </div>
            </div>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
    </div>
</div>

<div class="chart-grid">
    <div class="chart-card">
        <h3>Enrollment by Course</h3>
        <canvas id="courseChart"></canvas>
    </div>
    <div class="chart-card">
        <h3>User Distribution</h3>
        <canvas id="roleChart"></canvas>
    </div>
</div>

<div class="card">
    <h3>Recent Course Videos</h3>
    <?php if ($latestVideos->num_rows === 0): ?>
        <div class="empty-state">No videos uploaded yet.</div>
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
    <a href="manage_courses.php" class="btn">Manage Courses</a>
    <a href="manage_users.php" class="btn btn-outline">Manage Users</a>
    <a href="manage_enrollments.php" class="btn btn-outline">Manage Enrollments</a>
    <a href="upload_material.php" class="btn btn-outline">Upload Material</a>
    <a href="view_materials.php" class="btn btn-outline">View Materials</a>
    <a href="create_assignment.php" class="btn btn-outline">Assignments</a>
    <a href="manage_quizzes.php" class="btn btn-outline">Quizzes</a>
</div>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Swiper('#adminSwiper', {
        loop: true,
        autoplay: { delay: 4500, disableOnInteraction: false, pauseOnMouseEnter: true },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        pagination: { el: '.swiper-pagination', clickable: true },
        speed: 600
    });
});

new Chart(document.getElementById('courseChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($courseLabels); ?>,
        datasets: [{
            label: 'Enrolled students',
            data: <?php echo json_encode($courseData); ?>,
            backgroundColor: '#7c5cff',
            borderRadius: 6,
            maxBarThickness: 34
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

new Chart(document.getElementById('roleChart'), {
    type: 'doughnut',
    data: {
        labels: ['Students', 'Lecturers', 'Admins'],
        datasets: [{
            data: <?php echo json_encode($roleData); ?>,
            backgroundColor: ['#12b3a8', '#f2a93b', '#7c5cff'],
            borderWidth: 0
        }]
    },
    options: {
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
        cutout: '68%'
    }
});
</script>

<?php include '../includes/footer.php'; ?>