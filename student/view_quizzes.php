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
    SELECT qz.*, c.course_code, c.course_name,
           qa.id AS attempt_id, qa.score, qa.total_marks
    FROM quizzes qz
    JOIN courses c ON qz.course_id = c.id
    LEFT JOIN quiz_attempts qa ON qa.quiz_id = qz.id AND qa.student_id = $student_id
    WHERE qz.course_id IN ($idListSql)
";
if ($course_id > 0) {
    $sql .= " AND qz.course_id = " . $course_id;
}
$sql .= " ORDER BY qz.due_date DESC";
$quizzes = $conn->query($sql);

$pageTitle = 'Quizzes';
include '../includes/header.php';
?>
<h2>My Quizzes</h2>

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
        <tr><th>Course</th><th>Quiz</th><th>Due Date</th><th>Status</th><th>Action</th></tr>
        <?php while ($q = $quizzes->fetch_assoc()):
            $qCount = $conn->query("SELECT COUNT(*) AS c FROM quiz_questions WHERE quiz_id = " . $q['id'])->fetch_assoc()['c'];
        ?>
        <tr>
            <td class="course-code"><?php echo htmlspecialchars($q['course_code']); ?></td>
            <td><?php echo htmlspecialchars($q['title']); ?></td>
            <td><?php echo $q['due_date']; ?></td>
            <td>
                <?php if ($q['attempt_id'] !== null): ?>
                    <span class="pill pill-graded">Completed: <?php echo $q['score']; ?>/<?php echo $q['total_marks']; ?></span>
                <?php else: ?>
                    <span class="pill pill-pending">Not attempted</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($q['attempt_id'] !== null): ?>
                    <a href="take_quiz.php?quiz_id=<?php echo $q['id']; ?>" class="btn btn-outline">View Result</a>
                <?php elseif ($qCount === 0): ?>
                    <span style="color:var(--text-faint); font-size:12.5px;">No questions yet</span>
                <?php else: ?>
                    <a href="take_quiz.php?quiz_id=<?php echo $q['id']; ?>" class="btn">Take Quiz</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($quizzes->num_rows === 0): ?><div class="empty-state">No quizzes posted yet.</div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
