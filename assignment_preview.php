<?php
require_once 'config.php';
require_login();

$assignment_id = intval($_GET['assignment_id'] ?? 0);
$role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT a.*, c.course_name, c.lecturer_id
    FROM assignments a
    JOIN courses c ON c.id = a.course_id
    WHERE a.id = ?
");
if (!$stmt) {
    http_response_code(500);
    die('Unable to load the assignment. Please check the assignments database table.');
}
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment || empty($assignment['file_path'])) {
    http_response_code(404);
    die('Assignment file was not found.');
}

$allowed = $role === 'admin';
if ($role === 'lecturer' && (int)$assignment['lecturer_id'] === $user_id) {
    $allowed = true;
} elseif ($role === 'student') {
    $enrollment = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    if (!$enrollment) {
        http_response_code(500);
        die('Unable to verify your course enrollment.');
    }
    $enrollment->bind_param("ii", $user_id, $assignment['course_id']);
    $enrollment->execute();
    $allowed = (bool)$enrollment->get_result()->fetch_assoc();
}

if (!$allowed) {
    http_response_code(403);
    die('You do not have permission to view this assignment file.');
}

$file_url = '/lms_hndit/' . ltrim($assignment['file_path'], '/');
$extension = strtolower(pathinfo($assignment['file_path'], PATHINFO_EXTENSION));
$is_pdf = $extension === 'pdf';
$is_office = in_array($extension, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'], true);

$pageTitle = 'View Assignment File';
include 'includes/header.php';
?>
<div class="card">
    <h2><?php echo htmlspecialchars($assignment['title']); ?></h2>
    <p><?php echo htmlspecialchars($assignment['course_name']); ?></p>
    <div style="margin:18px 0; padding:16px; background:var(--surface); color:var(--text); border:1px solid var(--border); border-radius:10px;">
        <h3 style="margin:0 0 8px;">Assignment Description</h3>
        <?php if (trim((string)$assignment['description']) !== ''): ?>
            <p style="margin:0; color:var(--text) !important; font-size:14px; line-height:1.7; white-space:pre-wrap;"><?php echo htmlspecialchars($assignment['description']); ?></p>
        <?php else: ?>
            <p style="margin:0; color:var(--text-muted);">No description was provided for this assignment.</p>
        <?php endif; ?>
    </div>
    <p style="color:var(--text-muted);"><?php echo htmlspecialchars(basename($assignment['file_path'])); ?></p>

    <?php if ($is_pdf): ?>
        <iframe
            src="<?php echo htmlspecialchars($file_url); ?>"
            title="Assignment PDF"
            style="width:100%; height:75vh; min-height:600px; border:1px solid #d9dce5; border-radius:8px; background:#fff;"
        ></iframe>
    <?php elseif ($is_office): ?>
        <iframe
            src="<?php echo htmlspecialchars(assignment_file_preview_url($assignment['file_path'])); ?>"
            title="Assignment document preview"
            style="width:100%; height:75vh; min-height:600px; border:1px solid #d9dce5; border-radius:8px; background:#fff;"
        ></iframe>
        <p style="color:var(--text-muted); font-size:13px; margin-top:10px;">
            If the Office preview does not load, use Download File below. Office previews require the LMS URL to be reachable from the internet.
        </p>
    <?php else: ?>
        <p>This file type cannot be previewed in the browser.</p>
    <?php endif; ?>

    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:16px;">
        <a href="<?php echo htmlspecialchars($file_url); ?>" download class="btn">Download File</a>
        <a href="javascript:history.back()" class="btn btn-outline">Back</a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
