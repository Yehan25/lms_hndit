<?php
require_once 'config.php';
require_login();

$material_id = intval($_GET['id'] ?? 0);
$download = isset($_GET['download']) && $_GET['download'] === '1';
$role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT m.*, c.course_code, c.course_name, c.lecturer_id
    FROM materials m JOIN courses c ON c.id = m.course_id WHERE m.id = ?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();

if (!$material) {
    http_response_code(404);
    exit('Material not found.');
}

$has_access = false;
if ($role === 'admin') {
    $has_access = true;
} elseif ($role === 'student') {
    $accessStmt = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $accessStmt->bind_param("ii", $user_id, $material['course_id']);
    $accessStmt->execute();
    $has_access = (bool)$accessStmt->get_result()->fetch_assoc();
} elseif ($role === 'lecturer') {
    $accessStmt = $conn->prepare("SELECT id FROM course_lecturers WHERE course_id = ? AND lecturer_id = ?
        UNION SELECT id FROM courses WHERE id = ? AND lecturer_id = ?");
    $accessStmt->bind_param("iiii", $material['course_id'], $user_id, $material['course_id'], $user_id);
    $accessStmt->execute();
    $has_access = (bool)$accessStmt->get_result()->fetch_assoc();
}

if (!$has_access) {
    http_response_code(403);
    exit('You do not have permission to view this material.');
}

$relative_path = ltrim((string)$material['file_path'], '/\\');
$file_path = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative_path));
$upload_root = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
if (!$file_path || !$upload_root || !is_file($file_path) ||
    strpos($file_path, $upload_root . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('Material file not found.');
}

if (!$download && !isset($_GET['raw'])) {
    $pageTitle = 'View Material';
    $pageEyebrow = 'Learning Resource';
    include 'includes/header.php';
    ?>
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <div>
                <h2><?php echo htmlspecialchars($material['title']); ?></h2>
                <p style="color:var(--text-muted); margin:4px 0 0;">
                    <?php echo htmlspecialchars($material['course_code'] . ' - ' . $material['course_name']); ?>
                </p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="material.php?id=<?php echo $material_id; ?>&download=1" class="btn btn-outline">Download</a>
                <a href="javascript:history.back()" class="btn btn-outline">Back</a>
            </div>
        </div>
        <div style="margin-top:18px; border:1px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; background:var(--surface);">
            <iframe src="material.php?id=<?php echo $material_id; ?>&raw=1" title="Material preview" style="display:block; width:100%; min-height:70vh; border:0;"></iframe>
        </div>
    </div>
    <?php
    include 'includes/footer.php';
    exit();
}

$mime_types = [
    'pdf' => 'application/pdf',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ogg' => 'video/ogg',
    'mp3' => 'audio/mpeg',
];
$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_type = $mime_types[$extension] ?? 'application/octet-stream';
$file_name = basename($file_path);

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . addslashes($file_name) . '"');
readfile($file_path);
exit();
