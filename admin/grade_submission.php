<?php
require_once '../config.php';
require_role('admin');

$id = intval($_GET['id'] ?? 0);
$msg = '';

$stmt = $conn->prepare("
    SELECT s.*, u.name AS student_name, a.title AS assignment_title, a.course_id
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    JOIN assignments a ON s.assignment_id = a.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
if (!$sub) { die("Submission not found."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grade = trim($_POST['grade']);
    $feedback = trim($_POST['feedback']);
    $stmt2 = $conn->prepare("UPDATE submissions SET grade = ?, feedback = ? WHERE id = ?");
    $stmt2->bind_param("ssi", $grade, $feedback, $id);
    $stmt2->execute();
    $msg = "Grade saved.";
    $sub['grade'] = $grade;
    $sub['feedback'] = $feedback;
}

$pageTitle = 'Grade Submission';
$pageEyebrow = 'Admin';
include '../includes/header.php';
?>
<h2>Grade Submission</h2>
<p><strong>Student:</strong> <?php echo htmlspecialchars($sub['student_name']); ?> |
   <strong>Assignment:</strong> <?php echo htmlspecialchars($sub['assignment_title']); ?> |
   <strong>Status:</strong>
   <?php if ($sub['status'] === 'absent'): ?>
        <span class="pill pill-absent">Absent</span>
   <?php else: ?>
        <span class="pill pill-submitted">Submitted</span>
   <?php endif; ?>
</p>

<?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<?php if ($sub['status'] === 'submitted' && $sub['file_path']): ?>
<div class="card">
    <h3>Submitted File</h3>
    <a href="/lms_hndit/<?php echo htmlspecialchars($sub['file_path']); ?>" target="_blank" class="btn btn-outline">Download Submission</a>
</div>
<?php elseif ($sub['status'] === 'absent'): ?>
<div class="card">
    <div class="alert alert-error" style="margin-bottom:0;">This student was marked <b>Absent</b> for this assignment.</div>
</div>
<?php endif; ?>

<div class="card">
    <form method="POST">
        <label>Grade</label>
        <input type="text" name="grade" value="<?php echo htmlspecialchars($sub['grade'] ?? ''); ?>" placeholder="e.g. A, B+, 85">
        <label>Feedback</label>
        <textarea name="feedback" rows="4"><?php echo htmlspecialchars($sub['feedback'] ?? ''); ?></textarea>
        <button type="submit" class="btn">Save Grade</button>
    </form>
</div>
<a href="view_submissions.php?assignment_id=<?php echo $sub['assignment_id']; ?>" class="btn">Back</a>
<?php include '../includes/footer.php'; ?>
