<?php
require_once '../config.php';
require_role('admin');

$course_id = intval($_GET['course_id'] ?? 0);

$courses = $conn->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code");
$courseListForFilter = [];
while ($row = $courses->fetch_assoc()) { $courseListForFilter[] = $row; }

$sql = "
    SELECT m.*, c.course_code, c.course_name,
           COALESCE(u.name, IF(m.uploaded_by_role='admin','System Admin','Unknown')) AS uploader_name
    FROM materials m
    JOIN courses c ON m.course_id = c.id
    LEFT JOIN users u ON m.uploaded_by = u.id
";
if ($course_id > 0) {
    $sql .= " WHERE m.course_id = " . $course_id;
}
$sql .= " ORDER BY m.uploaded_at DESC";
$materials = $conn->query($sql);
$materialRows = [];
while ($row = $materials->fetch_assoc()) { $materialRows[] = $row; }

$pageTitle = 'Course Materials';
$pageEyebrow = 'Admin';
include '../includes/header.php';
?>
<h2>All Course Materials</h2>
<p style="color:var(--text-muted); margin-bottom:18px;">Review and download materials uploaded by lecturers and admins across every course.</p>

<div class="card">
    <form method="GET">
        <label>Filter by course</label>
        <select name="course_id" onchange="this.form.submit()">
            <option value="0">-- All Courses --</option>
            <?php foreach ($courseListForFilter as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <h3>Materials</h3>
    <?php if (empty($materialRows)): ?>
        <div class="empty-state">No materials uploaded yet. <a href="upload_material.php">Upload one &rarr;</a></div>
    <?php endif; ?>
    <?php foreach ($materialRows as $m): ?>
        <div style="border-bottom:1px solid var(--border); padding:14px 0;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div>
                    <strong><?php echo htmlspecialchars($m['title']); ?></strong>
                    <span class="type-tag type-tag-<?php echo $m['material_type']; ?>" style="margin-left:6px;"><?php echo $m['material_type']; ?></span>
                    <div style="font-size:12.5px; color:var(--text-muted); margin-top:2px;">
                        <span class="course-code"><?php echo htmlspecialchars($m['course_code']); ?></span> ·
                        Uploaded by <?php echo htmlspecialchars($m['uploader_name']); ?> (<?php echo htmlspecialchars($m['uploaded_by_role'] ?? 'lecturer'); ?>) ·
                        <?php echo $m['uploaded_at']; ?>
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
