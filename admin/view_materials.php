<?php
require_once '../config.php';
require_role('admin');

$course_id = intval($_GET['course_id'] ?? 0);
$selectedSemester = trim((string)($_GET['semester'] ?? ''));
$searchTerm = trim((string)($_GET['search'] ?? ''));

$courses = $conn->query("SELECT id, course_code, course_name, semester FROM courses ORDER BY semester IS NULL, semester, course_code");
$courseListForFilter = [];
while ($row = $courses->fetch_assoc()) { $courseListForFilter[] = $row; }

$semesterOptions = [];
foreach ($courseListForFilter as $course) {
    $semester = trim((string)($course['semester'] ?? ''));
    if ($semester !== '' && !in_array($semester, $semesterOptions, true)) {
        $semesterOptions[] = $semester;
    }
}

if ($selectedSemester !== '') {
    $courseListForFilter = array_values(array_filter($courseListForFilter, function ($course) use ($selectedSemester) {
        return trim((string)($course['semester'] ?? '')) === $selectedSemester;
    }));
}

$filteredCourseIds = array_map('intval', array_column($courseListForFilter, 'id'));
if ($course_id > 0 && !in_array($course_id, $filteredCourseIds, true)) {
    $course_id = 0;
}

$sql = "
    SELECT m.*, c.course_code, c.course_name, c.semester,
           COALESCE(u.name, IF(m.uploaded_by_role='admin','System Admin','Unknown')) AS uploader_name
    FROM materials m
    JOIN courses c ON m.course_id = c.id
    LEFT JOIN users u ON m.uploaded_by = u.id
    WHERE 1 = 1
";
if ($selectedSemester !== '') {
    $escapedSemester = $conn->real_escape_string($selectedSemester);
    $sql .= " AND c.semester = '" . $escapedSemester . "'";
}
if ($course_id > 0) {
    $sql .= " AND m.course_id = " . intval($course_id);
}
if ($searchTerm !== '') {
    $escapedSearch = $conn->real_escape_string($searchTerm);
    $searchPattern = "%" . $escapedSearch . "%";
    if ($course_id > 0) {
        $sql .= " AND (m.title LIKE '" . $searchPattern . "' OR c.course_code LIKE '" . $searchPattern . "' OR c.course_name LIKE '" . $searchPattern . "')";
    } else {
        $sql .= " AND (c.course_code LIKE '" . $searchPattern . "' OR c.course_name LIKE '" . $searchPattern . "' OR c.semester LIKE '" . $searchPattern . "')";
    }
}
$sql .= " ORDER BY m.uploaded_at DESC";
$materials = $conn->query($sql);
$materialRows = [];
while ($row = $materials->fetch_assoc()) { $materialRows[] = $row; }

$courseCards = [];
if ($course_id === 0) {
    $courseCardQuery = "SELECT c.id, c.course_code, c.course_name, c.semester, COUNT(m.id) AS material_count
        FROM courses c LEFT JOIN materials m ON m.course_id = c.id WHERE 1 = 1";
    if ($selectedSemester !== '') {
        $courseCardQuery .= " AND c.semester = '" . $conn->real_escape_string($selectedSemester) . "'";
    }
    if ($searchTerm !== '') {
        $courseCardQuery .= " AND (c.course_code LIKE '" . $conn->real_escape_string('%' . $searchTerm . '%') . "' OR c.course_name LIKE '" . $conn->real_escape_string('%' . $searchTerm . '%') . "' OR c.semester LIKE '" . $conn->real_escape_string('%' . $searchTerm . '%') . "')";
    }
    $courseCardQuery .= " GROUP BY c.id ORDER BY c.semester IS NULL, c.semester, c.course_code";
    $courseCardsResult = $conn->query($courseCardQuery);
    while ($row = $courseCardsResult->fetch_assoc()) { $courseCards[] = $row; }
}

$pageTitle = 'Course Materials';
$pageEyebrow = 'Admin';
include '../includes/header.php';
?>
<h2><?php echo $course_id > 0 ? 'Course Materials' : 'Courses & Subjects'; ?></h2>
<p style="color:var(--text-muted); margin-bottom:18px;"><?php echo $course_id > 0 ? 'Browse the materials available for this subject.' : 'Select a course or subject to view its learning materials.'; ?></p>

<div class="card">
    <form method="GET" style="display:grid; gap:12px;">
        <div>
            <label>Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="<?php echo $course_id > 0 ? 'Search materials in this course' : 'Search courses or subjects'; ?>">
        </div>
        <div>
            <label>Semester</label>
            <select name="semester" onchange="this.form.submit()">
                <option value="">-- All Semesters --</option>
                <?php foreach ($semesterOptions as $semester): ?>
                    <option value="<?php echo htmlspecialchars($semester); ?>" <?php echo $selectedSemester === $semester ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($semester); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Course / Subject</label>
            <select name="course_id" onchange="this.form.submit()">
                <option value="0">-- All Courses --</option>
                <?php foreach ($courseListForFilter as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(($c['semester'] ?? '') ? $c['semester'] . ' - ' : '') . htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <button type="submit" class="btn btn-outline">Search</button>
            <?php if ($selectedSemester !== '' || $course_id > 0 || $searchTerm !== ''): ?>
                <a href="view_materials.php" class="btn btn-outline">Clear filters</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($course_id === 0): ?>
<div class="card">
    <h3>Choose a Course</h3>
    <?php if (empty($courseCards)): ?>
        <div class="empty-state">No courses found for the selected filter.</div>
    <?php else: ?>
    <div class="material-course-grid">
        <?php foreach ($courseCards as $courseCard): ?>
            <article class="material-course-card">
                <div class="material-course-card__top">
                    <div>
                        <span class="course-code-pill"><?php echo htmlspecialchars($courseCard['course_code']); ?></span>
                        <h4><?php echo htmlspecialchars($courseCard['course_name']); ?></h4>
                        <div class="material-course-card__meta"><?php echo htmlspecialchars($courseCard['semester'] ?: 'Semester not set'); ?></div>
                    </div>
                    <span class="material-course-card__count"><?php echo (int)$courseCard['material_count']; ?> file<?php echo (int)$courseCard['material_count'] === 1 ? '' : 's'; ?></span>
                </div>
                <div class="material-course-card__footer">
                    <span class="material-course-card__meta">View subject content</span>
                    <a class="btn btn-outline" href="view_materials.php?course_id=<?php echo (int)$courseCard['id']; ?>">Open Course</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <h3>Materials in This Course</h3>
        <a href="view_materials.php" class="btn btn-outline">← Back to Courses</a>
    </div>
    <?php if (empty($materialRows)): ?>
        <div class="empty-state">No materials uploaded yet for the selected filter. <a href="upload_material.php">Upload one &rarr;</a></div>
    <?php endif; ?>
    <?php foreach ($materialRows as $m): ?>
        <div style="border-bottom:1px solid var(--border); padding:14px 0;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div>
                    <strong><?php echo htmlspecialchars($m['title']); ?></strong>
                    <span class="type-tag type-tag-<?php echo $m['material_type']; ?>" style="margin-left:6px;"><?php echo $m['material_type']; ?></span>
                    <div style="font-size:12.5px; color:var(--text-muted); margin-top:2px;">
                        <span class="course-code"><?php echo htmlspecialchars($m['course_code']); ?></span>
                        · <?php echo htmlspecialchars($m['semester'] ?? 'Semester not set'); ?>
                        · <?php echo htmlspecialchars($m['course_name']); ?>
                        · Uploaded by <?php echo htmlspecialchars($m['uploader_name']); ?> (<?php echo htmlspecialchars($m['uploaded_by_role'] ?? 'lecturer'); ?>)
                        · <?php echo $m['uploaded_at']; ?>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a href="../material.php?id=<?php echo (int)$m['id']; ?>" target="_blank" class="btn btn-outline">View</a>
                    <a href="../material.php?id=<?php echo (int)$m['id']; ?>&download=1" class="btn btn-outline">Download</a>
                </div>
            </div>
            <?php if ($m['material_type'] === 'video'): ?>
                <video class="material-video" controls preload="metadata">
                    <source src="/lms_hndit/<?php echo htmlspecialchars($m['file_path']); ?>">
                    Your browser does not support video playback.
                </video>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
