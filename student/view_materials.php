<?php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? 0);

$enrolledCourses = $conn->query("
    SELECT c.id, c.course_code, c.course_name FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = $student_id
    ORDER BY c.course_code
");
$courseListForFilter = [];
while ($row = $enrolledCourses->fetch_assoc()) { $courseListForFilter[] = $row; }
$enrolledIds = array_column($courseListForFilter, 'id');

if ($course_id > 0 && !in_array($course_id, $enrolledIds)) {
    die("You are not enrolled in that course.");
}

$idListSql = count($enrolledIds) ? implode(',', array_map('intval', $enrolledIds)) : '0';
$sql = "
    SELECT m.*, c.course_code, c.course_name FROM materials m
    JOIN courses c ON m.course_id = c.id
    WHERE m.course_id IN ($idListSql)
";
if ($course_id > 0) {
    $sql .= " AND m.course_id = " . $course_id;
}
$sql .= " ORDER BY m.uploaded_at DESC";
$materials = $conn->query($sql);
$materialRows = [];
while ($row = $materials->fetch_assoc()) { $materialRows[] = $row; }

$pageTitle = 'Course Materials';
include '../includes/header.php';
?>
<h2>Course Materials</h2>

<div class="card">
    <form method="GET">
        <label>Filter by course</label>
        <select name="course_id" onchange="this.form.submit()">
            <option value="0">-- All My Courses --</option>
            <?php foreach ($courseListForFilter as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <?php if (empty($materialRows)): ?>
        <div class="empty-state">No materials available yet.</div>
    <?php endif; ?>
    <?php foreach ($materialRows as $m): ?>
        <div style="border-bottom:1px solid var(--border); padding:14px 0;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div>
                    <strong><?php echo htmlspecialchars($m['title']); ?></strong>
                    <span class="type-tag type-tag-<?php echo $m['material_type']; ?>" style="margin-left:6px;"><?php echo $m['material_type']; ?></span>
                    <div style="font-size:12.5px; color:var(--text-muted); margin-top:2px;">
                        <span class="course-code"><?php echo htmlspecialchars($m['course_code']); ?></span> · <?php echo $m['uploaded_at']; ?>
                    </div>
                </div>
                <a href="/lms_hndit/<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank" class="btn btn-outline">Download</a>
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
<?php include '../includes/footer.php'; ?>
