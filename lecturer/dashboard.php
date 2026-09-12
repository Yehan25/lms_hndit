<?php
require_once '../config.php';
require_role('lecturer');

$lecturer_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM courses WHERE lecturer_id = ?");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$courses = $stmt->get_result();
$courseList = [];
while ($row = $courses->fetch_assoc()) { $courseList[] = $row; }
$courseCount = count($courseList);

$courseIds = array_column($courseList, 'id');
$idListSql = count($courseIds) ? implode(',', array_map('intval', $courseIds)) : '0';

$totalStudents = $conn->query("SELECT COUNT(DISTINCT student_id) AS c FROM enrollments WHERE course_id IN ($idListSql)")->fetch_assoc()['c'];
$totalAssignments = $conn->query("SELECT COUNT(*) AS c FROM assignments WHERE course_id IN ($idListSql)")->fetch_assoc()['c'];
$totalSubs = $conn->query("SELECT COUNT(*) AS c FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE a.course_id IN ($idListSql)")->fetch_assoc()['c'];
$gradedSubs = $conn->query("SELECT COUNT(*) AS c FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE a.course_id IN ($idListSql) AND s.grade IS NOT NULL AND s.grade != ''")->fetch_assoc()['c'];
$pendingSubs = $totalSubs - $gradedSubs;

// Submissions per assignment (chart)
$assignRes = $conn->query("
    SELECT a.title, COUNT(s.id) AS submitted
    FROM assignments a LEFT JOIN submissions s ON s.assignment_id = a.id
    WHERE a.course_id IN ($idListSql)
    GROUP BY a.id ORDER BY a.due_date DESC LIMIT 6
");
$assignLabels = []; $assignData = [];
while ($row = $assignRes->fetch_assoc()) {
    $assignLabels[] = $row['title'];
    $assignData[] = (int)$row['submitted'];
}
$latestVideos = $conn->query("SELECT m.*, c.course_code, c.course_name FROM materials m JOIN courses c ON m.course_id = c.id WHERE m.material_type = 'video' AND c.lecturer_id = $lecturer_id ORDER BY m.uploaded_at DESC LIMIT 4");

$searchTerm = trim($_GET['search'] ?? '');
$selectedSemester = trim($_GET['semester'] ?? '');
$searchClause = '';
$semesterClause = '';

if ($searchTerm !== '') {
    $escapedSearch = $conn->real_escape_string($searchTerm);
    $searchClause = " AND (course_code LIKE '%$escapedSearch%' OR course_name LIKE '%$escapedSearch%')";
}
if ($selectedSemester !== '') {
    $escapedSemester = $conn->real_escape_string($selectedSemester);
    $semesterClause = " AND semester = '$escapedSemester'";
}

$semesterOptionResult = $conn->query("SELECT DISTINCT c.semester FROM courses c
    LEFT JOIN course_lecturers cl ON cl.course_id = c.id
    WHERE (c.lecturer_id = $lecturer_id OR cl.lecturer_id = $lecturer_id)
      AND c.semester IS NOT NULL AND c.semester != ''
    ORDER BY c.semester");
$semesterOptions = [];
while ($row = $semesterOptionResult->fetch_assoc()) {
    $semesterOptions[] = $row['semester'];
}

$courseQuery = "SELECT c.* FROM courses c
    LEFT JOIN course_lecturers cl ON cl.course_id = c.id
    WHERE (c.lecturer_id = $lecturer_id OR cl.lecturer_id = $lecturer_id) $searchClause $semesterClause
    GROUP BY c.id
    ORDER BY c.semester IS NULL, c.semester, c.course_code";
$courseResult = $conn->query($courseQuery);
$courseList = [];
while ($row = $courseResult->fetch_assoc()) { $courseList[] = $row; }
$courseCount = count($courseList);

$pageTitle = "Overview";
$pageEyebrow = "Lecturer";
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-ring"><svg width="58" height="58" viewBox="0 0 58 58">
            <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
            <circle cx="29" cy="29" r="24" fill="none" stroke="#f2a93b" stroke-width="6" stroke-dasharray="150.7" stroke-linecap="round"/>
        </svg><div class="stat-ring-value">📘</div></div>
        <div class="stat-info"><div class="stat-num"><?php echo $courseCount; ?></div><div class="stat-label">My Courses</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ring"><svg width="58" height="58" viewBox="0 0 58 58">
            <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
            <circle cx="29" cy="29" r="24" fill="none" stroke="#12b3a8" stroke-width="6" stroke-dasharray="150.7" stroke-linecap="round"/>
        </svg><div class="stat-ring-value">🎓</div></div>
        <div class="stat-info"><div class="stat-num"><?php echo $totalStudents; ?></div><div class="stat-label">Students Taught</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ring"><svg width="58" height="58" viewBox="0 0 58 58">
            <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
            <circle cx="29" cy="29" r="24" fill="none" stroke="#7c5cff" stroke-width="6" stroke-dasharray="150.7" stroke-linecap="round"/>
        </svg><div class="stat-ring-value">📝</div></div>
        <div class="stat-info"><div class="stat-num"><?php echo $totalAssignments; ?></div><div class="stat-label">Assignments Posted</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ring"><svg width="58" height="58" viewBox="0 0 58 58">
            <circle class="stat-ring-track" cx="29" cy="29" r="24" fill="none" stroke-width="6"/>
            <circle cx="29" cy="29" r="24" fill="none" stroke="#ef6461" stroke-width="6"
                stroke-dasharray="<?php echo $totalSubs>0 ? round(150.7*$gradedSubs/$totalSubs,1) : 0; ?> 150.7" stroke-linecap="round"/>
        </svg><div class="stat-ring-value"><?php echo $pendingSubs; ?></div></div>
        <div class="stat-info"><div class="stat-num"><?php echo $totalSubs; ?></div><div class="stat-label">Submissions (Pending: <?php echo $pendingSubs; ?>)</div></div>
    </div>
</div>

<div class="chart-grid">
    <div class="chart-card">
        <h3>Submissions per Assignment</h3>
        <canvas id="assignChart"></canvas>
    </div>
    <div class="chart-card">
        <h3>Grading Progress</h3>
        <canvas id="gradeChart"></canvas>
    </div>
</div>

<div class="card">
    <h3>My Courses</h3>
    <form method="GET" class="filters-bar">
        <div class="filter-field">
            <label for="courseSearch">Search</label>
            <input type="text" id="courseSearch" name="search" placeholder="Search by course code or name" value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <div class="filter-field">
            <label for="semesterFilter">Semester</label>
            <select id="semesterFilter" name="semester">
                <option value="">All Semesters</option>
                <?php foreach ($semesterOptions as $semesterOption): ?>
                    <option value="<?php echo htmlspecialchars($semesterOption); ?>" <?php echo $selectedSemester === $semesterOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($semesterOption); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn">Apply</button>
            <a href="dashboard.php" class="btn btn-outline">Reset</a>
        </div>
    </form>

    <div class="semester-card-grid">
        <?php if ($courseCount > 0): ?>
            <?php $groupedCourses = []; foreach ($courseList as $course) { $semesterKey = trim((string)($course['semester'] ?? '')) !== '' ? $course['semester'] : 'Unassigned'; $groupedCourses[$semesterKey][] = $course; } ?>
            <?php foreach ($groupedCourses as $semesterName => $coursesForSemester): ?>
                <div class="semester-card">
                    <div class="semester-card__header">
                        <span class="semester-chip">Semester</span>
                        <h4><?php echo htmlspecialchars($semesterName); ?></h4>
                        <span class="semester-count"><?php echo count($coursesForSemester); ?> course<?php echo count($coursesForSemester) === 1 ? '' : 's'; ?></span>
                    </div>

                    <div class="semester-course-list">
                        <?php foreach ($coursesForSemester as $c): ?>
                            <?php $lecturerNames = get_course_lecturer_names($conn, $c['id']); $lecturerText = !empty($lecturerNames) ? implode(', ', $lecturerNames) : 'Unassigned'; ?>
                            <div class="semester-course-item" data-search="<?php echo htmlspecialchars(strtolower($c['course_code'] . ' ' . $c['course_name'] . ' ' . $lecturerText)); ?>" data-semester="<?php echo htmlspecialchars($c['semester'] ?? 'Unassigned'); ?>">
                                <div class="semester-course-main">
                                    <div class="course-code-pill"><?php echo htmlspecialchars($c['course_code']); ?></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($c['course_name']); ?></strong>
                                        <div class="semester-course-meta"><?php echo htmlspecialchars($lecturerText); ?></div>
                                    </div>
                                </div>
                                <div class="semester-course-actions">
                                    <a href="upload_material.php?course_id=<?php echo $c['id']; ?>" class="btn btn-outline">Manage / Bulk Upload</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">No courses assigned yet. Contact your administrator.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== UNIFIED SWIPER SLIDER ===== -->
<div class="dashboard-slider">
    <div class="swiper" id="lecturerSwiper">
        <div class="swiper-wrapper">
            <div class="swiper-slide">
                <img src="../assets/images/slider/slide1.jpg" alt="Engage your classes">
                <div class="swiper-slide-caption">
                    <span>Engage your classes</span>
                    <strong>Highlight new resources and keep students connected to your courses.</strong>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="../assets/images/slider/slide2.jpg" alt="Monitor progress">
                <div class="swiper-slide-caption">
                    <span>Monitor progress</span>
                    <strong>Review submissions and keep grading moving with clear visibility.</strong>
                </div>
            </div>
            <div class="swiper-slide">
                <img src="../assets/images/slider/slide3.jpg" alt="Drive momentum">
                <div class="swiper-slide-caption">
                    <span>Drive momentum</span>
                    <strong>Keep learning experiences polished with timely course updates.</strong>
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
        <div class="empty-state">No videos uploaded for your courses yet.</div>
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

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Swiper('#lecturerSwiper', {
        loop: true,
        autoplay: { delay: 4500, disableOnInteraction: false, pauseOnMouseEnter: true },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        pagination: { el: '.swiper-pagination', clickable: true },
        speed: 600
    });
});

// Course search filter (client-side)
const courseSearchInput = document.getElementById('courseSearch');
const semesterFilterSelect = document.getElementById('semesterFilter');
const courseItems = Array.from(document.querySelectorAll('.semester-course-item'));
const semesterCards = Array.from(document.querySelectorAll('.semester-card'));

function applyCourseFilters() {
    const query = (courseSearchInput?.value || '').trim().toLowerCase();
    const selectedSemester = semesterFilterSelect?.value || '';

    courseItems.forEach((item) => {
        const text = (item.getAttribute('data-search') || '').toLowerCase();
        const itemSemester = item.getAttribute('data-semester') || '';
        const matchesQuery = !query || text.includes(query);
        const matchesSemester = !selectedSemester || itemSemester === selectedSemester;
        item.style.display = matchesQuery && matchesSemester ? '' : 'none';
    });

    semesterCards.forEach((card) => {
        const visibleItems = card.querySelectorAll('.semester-course-item:not([style*="display: none"])');
        card.style.display = visibleItems.length > 0 ? '' : 'none';
    });
}

courseSearchInput?.addEventListener('input', applyCourseFilters);
semesterFilterSelect?.addEventListener('change', applyCourseFilters);

// Charts
new Chart(document.getElementById('assignChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($assignLabels); ?>,
        datasets: [{ label: 'Submissions', data: <?php echo json_encode($assignData); ?>, backgroundColor: '#f2a93b', borderRadius: 6, maxBarThickness: 32 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('gradeChart'), {
    type: 'doughnut',
    data: {
        labels: ['Graded', 'Pending'],
        datasets: [{ data: [<?php echo $gradedSubs; ?>, <?php echo $pendingSubs; ?>], backgroundColor: ['#12b3a8', '#e4e8f0'], borderWidth: 0 }]
    },
    options: { plugins: { legend: { position: 'bottom' } }, cutout: '68%' }
});
</script>

<?php include '../includes/footer.php'; ?>