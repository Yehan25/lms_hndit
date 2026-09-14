<?php
require_once '../config.php';
require_role('lecturer');

$assignment_id = intval($_GET['assignment_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT a.*, c.course_name, c.id AS course_id FROM assignments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.id = ? AND c.lecturer_id = ?
");
$stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
if (!$assignment) { die("Assignment not found or access denied."); }

// Every student enrolled in this course, LEFT JOINed with their submission (if any)
// for this specific assignment. This way students who never submitted still show up,
// so the lecturer can mark them Absent.
$roster = $conn->query("
    SELECT u.id AS student_id, u.name AS student_name, u.reg_no,
           s.id AS submission_id, s.file_path, s.status, s.submitted_at, s.grade, s.feedback
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    LEFT JOIN submissions s ON s.assignment_id = $assignment_id AND s.student_id = u.id
    WHERE e.course_id = " . intval($assignment['course_id']) . "
    ORDER BY u.name
");

$pageTitle = 'Submissions';
include '../includes/header.php';
?>
<h2>Submissions - <?php echo htmlspecialchars($assignment['title']); ?></h2>
<p><?php echo htmlspecialchars($assignment['course_name']); ?> | Due: <?php echo $assignment['due_date']; ?></p>

<div class="card">
    <table>
        <tr><th>Student</th><th>Reg No.</th><th>Status</th><th>Submitted</th><th>File</th><th>Grade</th><th>Action</th></tr>
        <?php while ($s = $roster->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($s['student_name']); ?></td>
            <td><?php echo htmlspecialchars($s['reg_no']); ?></td>
            <td>
                <?php if ($s['submission_id'] === null): ?>
                    <span class="pill pill-pending">Not submitted</span>
                <?php elseif ($s['status'] === 'absent'): ?>
                    <span class="pill pill-absent">Absent</span>
                <?php elseif (!empty($s['grade'])): ?>
                    <span class="pill pill-graded">Graded</span>
                <?php else: ?>
                    <span class="pill pill-submitted">Submitted</span>
                <?php endif; ?>
            </td>
            <td><?php echo $s['submitted_at'] ? $s['submitted_at'] : '-'; ?></td>
            <td>
                <?php if ($s['status'] === 'submitted' && $s['file_path']): ?>
                    <a href="grade_submission.php?id=<?php echo (int)$s['submission_id']; ?>" class="btn btn-outline">View Submission</a>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($s['grade'] ?? 'Not graded'); ?></td>
            <td>
                <?php if ($s['submission_id'] !== null): ?>
                    <a href="grade_submission.php?id=<?php echo (int)$s['submission_id']; ?>" class="btn">View / Grade</a>
                <?php else: ?>
                    <a href="mark_absent.php?assignment_id=<?php echo $assignment_id; ?>&student_id=<?php echo $s['student_id']; ?>"
                       class="btn btn-danger" onclick="return confirm('Mark <?php echo htmlspecialchars($s['student_name'], ENT_QUOTES); ?> as absent for this assignment?')">Mark Absent</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($roster->num_rows === 0): ?><p>No students enrolled in this course yet.</p><?php endif; ?>
</div>
<a href="create_assignment.php?course_id=<?php echo $assignment['course_id']; ?>" class="btn">Back</a>
<?php include '../includes/footer.php'; ?>
