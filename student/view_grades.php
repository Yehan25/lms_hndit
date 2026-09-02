<?php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];

$rows = $conn->query("
    SELECT a.title AS assignment_title, a.due_date, c.course_code, c.course_name,
           s.status, s.grade, s.feedback, s.submitted_at
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = $student_id
    LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = $student_id
    ORDER BY a.due_date DESC
");

$quizRows = $conn->query("
    SELECT qz.title AS quiz_title, qz.due_date, c.course_code, c.course_name,
           qa.score, qa.total_marks, qa.submitted_at
    FROM quizzes qz
    JOIN courses c ON qz.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = $student_id
    LEFT JOIN quiz_attempts qa ON qa.quiz_id = qz.id AND qa.student_id = $student_id
    ORDER BY qz.due_date DESC
");

$pageTitle = 'My Grades';
include '../includes/header.php';
?>
<h2>My Grades</h2>

<div class="card">
    <h3>Assignments</h3>
    <table>
        <tr><th>Course</th><th>Assignment</th><th>Status</th><th>Grade</th><th>Feedback</th></tr>
        <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
            <td class="course-code"><?php echo htmlspecialchars($r['course_code']); ?></td>
            <td><?php echo htmlspecialchars($r['assignment_title']); ?></td>
            <td>
                <?php if ($r['status'] === null): ?>
                    <span class="pill pill-pending">Not submitted</span>
                <?php elseif ($r['status'] === 'absent'): ?>
                    <span class="pill pill-absent">Absent</span>
                <?php elseif (!empty($r['grade'])): ?>
                    <span class="pill pill-graded">Graded</span>
                <?php else: ?>
                    <span class="pill pill-submitted">Submitted</span>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($r['grade'] ?? '-'); ?></td>
            <td><?php echo $r['feedback'] ? nl2br(htmlspecialchars($r['feedback'])) : '-'; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($rows->num_rows === 0): ?><div class="empty-state">No assignments found yet.</div><?php endif; ?>
</div>

<div class="card">
    <h3>Quizzes</h3>
    <table>
        <tr><th>Course</th><th>Quiz</th><th>Score</th><th>Percentage</th></tr>
        <?php while ($q = $quizRows->fetch_assoc()): ?>
        <tr>
            <td class="course-code"><?php echo htmlspecialchars($q['course_code']); ?></td>
            <td><?php echo htmlspecialchars($q['quiz_title']); ?></td>
            <td>
                <?php if ($q['submitted_at'] === null): ?>
                    <span class="pill pill-pending">Not attempted</span>
                <?php else: ?>
                    <?php echo $q['score']; ?> / <?php echo $q['total_marks']; ?>
                <?php endif; ?>
            </td>
            <td><?php echo ($q['submitted_at'] !== null && $q['total_marks'] > 0) ? round(($q['score']/$q['total_marks'])*100) . '%' : '-'; ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($quizRows->num_rows === 0): ?><div class="empty-state">No quizzes found yet.</div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
