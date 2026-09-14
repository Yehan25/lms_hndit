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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submission'])) {
    if ($submission && $submission['status'] === 'submitted') {
        delete_submission_file($submission['file_path']);
        $delete_stmt = $conn->prepare("DELETE FROM submissions WHERE id = ? AND assignment_id = ? AND student_id = ?");
        $delete_stmt->bind_param("iii", $submission['id'], $assignment_id, $student_id);
        $delete_stmt->execute();
        $msg = $delete_stmt->affected_rows > 0
            ? 'Your submission was deleted. You can submit a new file.'
            : 'The submission could not be deleted. Please try again.';
        $stmt2->execute();
        $submission = $stmt2->get_result()->fetch_assoc();
    } else {
        $msg = 'Only a submitted assignment can be deleted.';
    }
}

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
    <p style="color:var(--text) !important; font-size:14px; line-height:1.7; white-space:pre-wrap;"><?php echo htmlspecialchars($assignment['description']); ?></p>
    <?php if (!empty($assignment['file_path'])): ?>
        <?php
            $assignment_file_url = '/lms_hndit/' . ltrim($assignment['file_path'], '/');
            $assignment_file_ext = strtolower(pathinfo($assignment['file_path'], PATHINFO_EXTENSION));
            $assignment_file_name = basename($assignment['file_path']);
        ?>
        <div style="margin-top:18px; padding:16px; background:var(--surface); color:var(--text); border:1px solid var(--border); border-radius:10px;">
            <h4 style="margin:0 0 8px;">Assignment File</h4>
            <p style="margin:0 0 12px; color:var(--text-muted);">
                <?php echo htmlspecialchars($assignment_file_name); ?>
                <span style="text-transform:uppercase; font-size:12px;">(<?php echo htmlspecialchars($assignment_file_ext); ?>)</span>
            </p>
            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <a href="../assignment_preview.php?assignment_id=<?php echo (int)$assignment_id; ?>" target="_blank" rel="noopener" class="btn btn-outline">View Assignment File</a>
                <a href="<?php echo htmlspecialchars($assignment_file_url); ?>" download class="btn btn-outline">Download File</a>
            </div>
            <?php if ($assignment_file_ext === 'pdf'): ?>
                <div style="margin-top:16px;">
                    <p style="margin:0 0 8px; font-weight:600;">Read the assignment here before submitting:</p>
                    <iframe
                        src="<?php echo htmlspecialchars($assignment_file_url); ?>"
                        title="Assignment PDF"
                        style="width:100%; height:650px; border:1px solid #d9dce5; border-radius:8px; background:#fff;"
                    ></iframe>
                </div>
            <?php elseif (in_array($assignment_file_ext, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'], true)): ?>
                <p style="margin:14px 0 0; color:var(--text-muted); font-size:13px;">
                    This Office document is ready to open or download. If your browser cannot display it directly, use
                    <strong>Download File</strong> and open it with Microsoft Office or LibreOffice.
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="empty-state" style="margin-top:12px;">No assignment file was attached.</div>
    <?php endif; ?>
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
        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete your submitted assignment?');">
            <button type="submit" name="delete_submission" value="1" class="btn btn-danger">Delete My Submission</button>
        </form>
        <p><strong>Grade:</strong> <?php echo htmlspecialchars($submission['grade'] ?? 'Not graded yet'); ?></p>
        <?php if (!empty($submission['feedback'])): ?>
            <p><strong>Feedback:</strong><br><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<a href="view_assignments.php" class="btn">Back to Assignments</a>
<?php include '../includes/footer.php'; ?>
