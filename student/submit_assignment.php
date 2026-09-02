<?php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$assignment_id = intval($_GET['assignment_id'] ?? 0);
$msg = '';

// Verify the assignment belongs to a course the student is enrolled in
$stmt = $conn->prepare("
    SELECT a.*, c.course_name, c.id AS course_id FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
    WHERE a.id = ?
");
$stmt->bind_param("ii", $student_id, $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
if (!$assignment) { die("Assignment not found or you are not enrolled in this course."); }

// Fetch existing submission (if any)
$stmt2 = $conn->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?");
$stmt2->bind_param("ii", $assignment_id, $student_id);
$stmt2->execute();
$submission = $stmt2->get_result()->fetch_assoc();

if ($submission && $submission['status'] === 'absent') {
    // Absent students cannot submit
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['submission_file'])) {
    $file = $_FILES['submission_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../uploads/submissions/";
        $filename = time() . "_" . $student_id . "_" . basename($file['name']);
        $target_path = $target_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $rel_path = "uploads/submissions/" . $filename;
            $stmt3 = $conn->prepare("
                INSERT INTO submissions (assignment_id, student_id, file_path, status)
                VALUES (?, ?, ?, 'submitted')
                ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), status = 'submitted', submitted_at = CURRENT_TIMESTAMP
            ");
            $stmt3->bind_param("iis", $assignment_id, $student_id, $rel_path);
            $stmt3->execute();
            $msg = "Assignment submitted successfully.";

            // Refresh submission data
            $stmt2->execute();
            $submission = $stmt2->get_result()->fetch_assoc();
        } else {
            $msg = "Upload failed. Please try again.";
        }
    } else {
        $msg = "Please choose a file to upload.";
    }
}

$pageTitle = 'Submit Assignment';
include '../includes/header.php';
?>
<h2><?php echo htmlspecialchars($assignment['title']); ?></h2>
<p><?php echo htmlspecialchars($assignment['course_name']); ?> | Due: <?php echo $assignment['due_date']; ?></p>

<?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="card">
    <h3>Assignment Description</h3>
    <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
</div>

<?php if ($submission && $submission['status'] === 'absent'): ?>
    <div class="card">
        <div class="alert alert-error" style="margin-bottom:0;">You have been marked <b>Absent</b> for this assignment by your lecturer. Submissions are disabled. Contact your lecturer if you believe this is a mistake.</div>
    </div>
<?php else: ?>
    <div class="card">
        <h3><?php echo $submission ? 'Resubmit' : 'Submit'; ?> Your Work</h3>
        <form method="POST" enctype="multipart/form-data">
            <label>File</label>
            <input type="file" name="submission_file" required>
            <button type="submit" class="btn">Upload</button>
        </form>
    </div>

    <?php if ($submission): ?>
    <div class="card">
        <h3>Your Submission</h3>
        <p><strong>Submitted:</strong> <?php echo $submission['submitted_at']; ?></p>
        <p><a href="/lms_hndit/<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="btn btn-outline">Download My Submission</a></p>
        <p><strong>Grade:</strong> <?php echo htmlspecialchars($submission['grade'] ?? 'Not graded yet'); ?></p>
        <?php if (!empty($submission['feedback'])): ?>
            <p><strong>Feedback:</strong><br><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<a href="view_assignments.php" class="btn">Back to Assignments</a>
<?php include '../includes/footer.php'; ?>
