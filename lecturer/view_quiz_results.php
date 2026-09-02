<?php
require_once '../config.php';
require_role('lecturer');

$lecturer_id = $_SESSION['user_id'];
$quiz_id = intval($_GET['quiz_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT q.*, c.course_name, c.id AS course_id FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    WHERE q.id = ? AND c.lecturer_id = ?
");
$stmt->bind_param("ii", $quiz_id, $lecturer_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
if (!$quiz) { die("Quiz not found or access denied."); }

$totalMarks = $conn->query("SELECT COALESCE(SUM(marks),0) AS t FROM quiz_questions WHERE quiz_id = $quiz_id")->fetch_assoc()['t'];

$roster = $conn->query("
    SELECT u.id AS student_id, u.name AS student_name, u.reg_no,
           qa.id AS attempt_id, qa.score, qa.total_marks, qa.submitted_at
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    LEFT JOIN quiz_attempts qa ON qa.quiz_id = $quiz_id AND qa.student_id = u.id
    WHERE e.course_id = " . intval($quiz['course_id']) . "
    ORDER BY u.name
");

$pageTitle = 'Quiz Results';
include '../includes/header.php';
?>
<h2>Results - <?php echo htmlspecialchars($quiz['title']); ?></h2>
<p><?php echo htmlspecialchars($quiz['course_name']); ?> | Total Marks: <?php echo $totalMarks; ?></p>

<div class="card">
    <table>
        <tr><th>Student</th><th>Reg No.</th><th>Score</th><th>Percentage</th><th>Submitted</th></tr>
        <?php while ($r = $roster->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($r['student_name']); ?></td>
            <td><?php echo htmlspecialchars($r['reg_no']); ?></td>
            <td>
                <?php if ($r['attempt_id'] === null): ?>
                    <span class="pill pill-pending">Not attempted</span>
                <?php else: ?>
                    <?php echo $r['score']; ?> / <?php echo $r['total_marks']; ?>
                <?php endif; ?>
            </td>
            <td><?php echo ($r['attempt_id'] !== null && $r['total_marks'] > 0) ? round(($r['score']/$r['total_marks'])*100) . '%' : '-'; ?></td>
            <td><?php echo $r['submitted_at'] ?? '-'; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($roster->num_rows === 0): ?><p>No students enrolled in this course yet.</p><?php endif; ?>
</div>
<a href="manage_quizzes.php" class="btn">Back to Quizzes</a>
<?php include '../includes/footer.php'; ?>
