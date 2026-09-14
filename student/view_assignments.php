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
    SELECT a.*, c.course_code, c.course_name,
           s.id AS submission_id, s.status, s.grade, s.feedback, s.submitted_at
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = $student_id
    WHERE a.course_id IN ($idListSql)
";
if ($course_id > 0) {
    $sql .= " AND a.course_id = " . $course_id;
}
$sql .= " ORDER BY a.due_date DESC";
$assignments = $conn->query($sql);

$pageTitle = 'Assignments';
include '../includes/header.php';
?>
<h2>My Assignments</h2>

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
    <table>
        <tr><th>Course</th><th>Title</th><th>Due Date</th><th>Status</th><th>Grade</th><th>Action</th></tr>
        <?php while ($a = $assignments->fetch_assoc()): ?>
        <tr>
            <td class="course-code"><?php echo htmlspecialchars($a['course_code']); ?></td>
            <td>
                <?php echo htmlspecialchars($a['title']); ?>
                <?php if (trim((string)$a['description']) !== ''): ?>
                    <div style="margin-top:6px; color:var(--text, #1a2236) !important; font-size:13px; line-height:1.55; white-space:pre-wrap;">
                        <?php echo htmlspecialchars($a['description']); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($a['file_path'])): ?>
                    <div style="margin-top:6px; color:var(--text-muted); font-size:12px;">
                        <a href="../assignment_preview.php?assignment_id=<?php echo (int)$a['id']; ?>" target="_blank" rel="noopener">
                            📎 View <?php echo htmlspecialchars(basename($a['file_path'])); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </td>
            <td><?php echo $a['due_date']; ?></td>
            <td>
                <?php if ($a['submission_id'] === null): ?>
                    <span class="pill pill-pending">Not submitted</span>
                <?php elseif ($a['status'] === 'absent'): ?>
                    <span class="pill pill-absent">Absent</span>
                <?php elseif (!empty($a['grade'])): ?>
                    <span class="pill pill-graded">Graded</span>
                <?php else: ?>
                    <span class="pill pill-submitted">Submitted</span>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($a['grade'] ?? '-'); ?></td>
            <td>
                <?php if ($a['status'] === 'absent'): ?>
                    -
                <?php else: ?>
                    <a href="submit_assignment.php?assignment_id=<?php echo $a['id']; ?>" class="btn">
                        <?php echo $a['submission_id'] !== null ? 'View / Resubmit' : 'View & Submit'; ?>
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($assignments->num_rows === 0): ?><div class="empty-state">No assignments posted yet.</div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
