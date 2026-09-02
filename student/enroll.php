<?php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$msg = '';

// Enroll action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course_id'])) {
    $course_id = intval($_POST['enroll_course_id']);
    $stmt = $conn->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $student_id, $course_id);
    if ($stmt->execute()) {
        $msg = "Enrolled successfully.";
    } else {
        $msg = "Could not enroll. Please try again.";
    }
    $stmt->close();
}

// Unenroll action
if (isset($_GET['unenroll'])) {
    $course_id = intval($_GET['unenroll']);
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    header("Location: enroll.php");
    exit();
}

$searchTerm = trim($_GET['search'] ?? '');
$selectedSemester = trim($_GET['semester'] ?? '');
$searchClause = '';
$semesterClause = '';

if ($searchTerm !== '') {
    $escapedSearch = $conn->real_escape_string($searchTerm);
    $searchClause = " AND (c.course_code LIKE '%$escapedSearch%' OR c.course_name LIKE '%$escapedSearch%')";
}
if ($selectedSemester !== '') {
    $escapedSemester = $conn->real_escape_string($selectedSemester);
    $semesterClause = " AND c.semester = '$escapedSemester'";
}

$coursesResult = $conn->query("
    SELECT c.*, 
           EXISTS(SELECT 1 FROM enrollments e WHERE e.student_id = $student_id AND e.course_id = c.id) AS is_enrolled
    FROM courses c
    WHERE 1=1 $searchClause $semesterClause
    ORDER BY c.semester IS NULL, c.semester, c.course_code
");

$semesterOptionResult = $conn->query("SELECT DISTINCT semester FROM courses WHERE semester IS NOT NULL AND semester != '' ORDER BY semester");
$semesterOptions = [];
while ($row = $semesterOptionResult->fetch_assoc()) {
    $semesterOptions[] = $row['semester'];
}

$semesterGroups = [];
if ($coursesResult) {
    while ($course = $coursesResult->fetch_assoc()) {
        $semesterKey = trim((string)($course['semester'] ?? '')) !== '' ? $course['semester'] : 'Unassigned';
        $semesterGroups[$semesterKey][] = $course;
    }
}

$pageTitle = 'Enroll in Courses';
include '../includes/header.php';
?>
<h2>Enroll in Courses</h2>

<?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="card">
    <h3>Available Courses</h3>
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
            <a href="enroll.php" class="btn btn-outline">Reset</a>
        </div>
    </form>

    <div class="semester-card-grid">
        <?php if (!empty($semesterGroups)): ?>
            <?php foreach ($semesterGroups as $semesterName => $coursesForSemester): ?>
                <div class="semester-card">
                    <div class="semester-card__header">
                        <span class="semester-chip">Semester</span>
                        <h4><?php echo htmlspecialchars($semesterName); ?></h4>
                        <span class="semester-count"><?php echo count($coursesForSemester); ?> course<?php echo count($coursesForSemester) === 1 ? '' : 's'; ?></span>
                    </div>

                    <div class="semester-course-list">
                        <?php foreach ($coursesForSemester as $course): ?>
                            <?php $lecturerNames = get_course_lecturer_names($conn, $course['id']); $lecturerText = !empty($lecturerNames) ? implode(', ', $lecturerNames) : 'Unassigned'; ?>
                            <div class="semester-course-item" data-search="<?php echo htmlspecialchars(strtolower($course['course_code'] . ' ' . $course['course_name'] . ' ' . $lecturerText)); ?>" data-semester="<?php echo htmlspecialchars($course['semester'] ?? 'Unassigned'); ?>">
                                <div class="semester-course-main">
                                    <div class="course-code-pill"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                                        <div class="semester-course-meta"><?php echo htmlspecialchars($lecturerText); ?></div>
                                    </div>
                                </div>
                                <div class="semester-course-actions">
                                    <?php if (!empty($course['is_enrolled'])): ?>
                                        <span class="course-card-status">Enrolled</span>
                                        <a href="?unenroll=<?php echo $course['id']; ?>&search=<?php echo urlencode($searchTerm); ?>&semester=<?php echo urlencode($selectedSemester); ?>" class="btn btn-outline" onclick="return confirm('Unenroll from this course?')">Unenroll</a>
                                    <?php else: ?>
                                        <form method="POST" class="course-action-form">
                                            <input type="hidden" name="enroll_course_id" value="<?php echo $course['id']; ?>">
                                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selectedSemester); ?>">
                                            <button type="submit" class="btn">Enroll</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">No courses found for this search or semester.</div>
        <?php endif; ?>
    </div>
</div>

<script>
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
</script>

<?php include '../includes/footer.php'; ?>
