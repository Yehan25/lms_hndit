<?php
require_once '../config.php';
require_any_role(['admin', 'lecturer']);

$current_role = $_SESSION['role'];
$current_user_id = (int)$_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? 0);
$msg = '';
$maxUploadSize = ini_get('upload_max_filesize');
// compute effective limits
$phpPostMax = php_size_to_bytes(ini_get('post_max_size'));
$phpUploadMax = php_size_to_bytes(ini_get('upload_max_filesize'));
$effectiveMax = min($phpPostMax, $phpUploadMax, MAX_VIDEO_UPLOAD_BYTES);
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $msg = 'Material uploaded successfully.';
}

// Courses available for the current actor (admin sees all, lecturer sees their own)
if ($current_role === 'admin') {
    $myCourses = $conn->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code");
} else {
    $myCourses = $conn->query("SELECT id, course_code, course_name FROM courses WHERE lecturer_id = $current_user_id ORDER BY course_code");
}
$myCourseList = [];
while ($row = $myCourses->fetch_assoc()) { $myCourseList[] = $row; }

$course = null;
if ($course_id > 0) {
    if ($current_role === 'admin') {
        $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND lecturer_id = ?");
        $stmt->bind_param("ii", $course_id, $current_user_id);
    }
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    if (!$course) { die("Course not found or access denied."); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_material'])) {
    $delete_material_id = intval($_POST['material_id'] ?? 0);
    if ($delete_material_id > 0) {
        $stmt = $conn->prepare("SELECT m.id, m.file_path, m.course_id, c.lecturer_id FROM materials m JOIN courses c ON c.id = m.course_id WHERE m.id = ?");
        $stmt->bind_param("i", $delete_material_id);
        $stmt->execute();
        $material = $stmt->get_result()->fetch_assoc();

        if ($material) {
            $can_delete = $current_role === 'admin' || ($current_role === 'lecturer' && ((int)$material['lecturer_id'] === $current_user_id));
            if ($can_delete) {
                $file_path = $material['file_path'];
                $physical_path = dirname(__DIR__) . '/' . ltrim($file_path, '/');
                if (file_exists($physical_path)) {
                    @unlink($physical_path);
                }
                $stmt2 = $conn->prepare("DELETE FROM materials WHERE id = ?");
                $stmt2->bind_param("i", $delete_material_id);
                $stmt2->execute();
                $msg = "Material deleted successfully.";
            } else {
                $msg = "You do not have permission to delete this material.";
            }
        } else {
            $msg = "Material not found.";
        }
    }
}

function upload_error_message($code) {
    return match ($code) {
        UPLOAD_ERR_OK => 'No upload error.',
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the allowed size. Increase upload_max_filesize/post_max_size in php.ini.',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded. Please choose a file first.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write the file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        default => 'Unknown upload error.'
    };
}

function sanitize_upload_filename($name) {
    $name = pathinfo($name, PATHINFO_FILENAME);
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    return $name ?: 'file';
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['material_file'])) {
        $msg = 'Upload failed: no file was received. The file may be larger than the PHP limit (' . ini_get('post_max_size') . ').';
    } else {
        $file = $_FILES['material_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = 'Upload failed: ' . upload_error_message($file['error']);
        } else {
            $post_course_id = intval($_POST['course_id']);
            if ($current_role === 'admin') {
                $stmtc = $conn->prepare("SELECT id FROM courses WHERE id = ?");
                $stmtc->bind_param("i", $post_course_id);
            } else {
                $stmtc = $conn->prepare("SELECT id FROM courses WHERE id = ? AND lecturer_id = ?");
                $stmtc->bind_param("ii", $post_course_id, $current_user_id);
            }
            $stmtc->execute();
            if (!$stmtc->get_result()->fetch_assoc()) {
                $msg = "Invalid course selection.";
            } else {
                $title = trim($_POST['title']) ?: pathinfo($file['name'], PATHINFO_FILENAME);
                $material_type = ($_POST['material_type'] === 'video') ? 'video' : 'document';
                // Server-side validation: enforce 40MB for videos and allowed mime types
                if ($material_type === 'video') {
                    if ($file['size'] > MAX_VIDEO_UPLOAD_BYTES) {
                        $msg = 'Upload failed: video exceeds maximum allowed size of 40 MB.';
                    }
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    $allowedVideoMimes = ['video/mp4','video/webm','video/ogg'];
                    if (!in_array($mime, $allowedVideoMimes, true)) {
                        $msg = 'Upload failed: unsupported video format. Please upload MP4/WebM/OGG.';
                    }
                }
                if (empty($msg)) {
                    $target_dir = "../uploads/materials/";
                    ensure_upload_dir($target_dir);
                    $baseName = sanitize_upload_filename($file['name']);
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = time() . "_" . $baseName . ($extension ? "." . $extension : '');
                    $target_path = $target_dir . $filename;

                    if (!is_uploaded_file($file['tmp_name'])) {
                        $msg = 'Upload failed: Uploaded file is not valid.';
                    } elseif (move_uploaded_file($file['tmp_name'], $target_path)) {
                        $stmt2 = $conn->prepare("INSERT INTO materials (course_id, uploaded_by, uploaded_by_role, title, material_type, file_path) VALUES (?, ?, ?, ?, ?, ?)");
                        $rel_path = "uploads/materials/" . $filename;
                        $stmt2->bind_param("iissss", $post_course_id, $current_user_id, $current_role, $title, $material_type, $rel_path);
                        if ($stmt2->execute()) {
                            header('Location: upload_material.php?course_id=' . $post_course_id . '&success=1');
                            exit;
                        } else {
                            $msg = "Upload failed: could not save material record. " . $stmt2->error;
                        }
                    } else {
                        $msg = 'Upload failed: could not move uploaded file to the uploads folder. Check folder permissions and php.ini settings.';
                    }
                }
            }
        }
    }
}

// Materials list: this course if selected, else all of the available courses for the current actor
if ($course_id > 0) {
    if ($current_role === 'admin') {
        $materials = $conn->query("SELECT m.*, c.course_code FROM materials m JOIN courses c ON m.course_id = c.id WHERE m.course_id = $course_id ORDER BY m.uploaded_at DESC");
    } else {
        $idList = count($myCourseList) ? implode(',', array_map(fn($c) => intval($c['id']), $myCourseList)) : '0';
        $materials = $conn->query("SELECT m.*, c.course_code FROM materials m JOIN courses c ON m.course_id = c.id WHERE m.course_id = $course_id AND m.course_id IN ($idList) ORDER BY m.uploaded_at DESC");
    }
} else {
    if ($current_role === 'admin') {
        $materials = $conn->query("SELECT m.*, c.course_code FROM materials m JOIN courses c ON m.course_id = c.id ORDER BY m.uploaded_at DESC");
    } else {
        $idList = count($myCourseList) ? implode(',', array_map(fn($c) => intval($c['id']), $myCourseList)) : '0';
        $materials = $conn->query("SELECT m.*, c.course_code FROM materials m JOIN courses c ON m.course_id = c.id WHERE m.course_id IN ($idList) ORDER BY m.uploaded_at DESC");
    }
}

$pageTitle = 'Course Materials';
include '../includes/header.php';
?>
<h2><?php echo $course ? 'Materials - ' . htmlspecialchars($course['course_name']) : 'Course Materials'; ?></h2>

<?php if ($msg): ?><div class="alert <?php echo strpos($msg,'success')!==false ? 'alert-success' : 'alert-error'; ?>"><?php echo $msg; ?></div><?php endif; ?>

<div class="card">
    <h3>Upload New Material</h3>
    <?php if (empty($myCourseList)): ?>
        <div class="empty-state">You have no assigned courses yet. Contact your administrator.</div>
    <?php else: ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Course</label>
        <select name="course_id" required>
            <?php foreach ($myCourseList as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Title</label>
        <input type="text" name="title" required>
        <label>Material Type</label>
        <select name="material_type" id="materialType">
            <option value="document">Document / File (PDF, DOCX, PPT, ZIP...)</option>
            <option value="video">Video (MP4)</option>
        </select>
        <label>File</label>
        <input type="file" name="material_file" id="materialFileInput" required>
        <div class="form-help">Max upload size: <?php echo (MAX_VIDEO_UPLOAD_BYTES/1024/1024) . ' MB'; ?> (server: <?php echo htmlspecialchars($maxUploadSize); ?>). Select "Video" for MP4 upload.</div>
        <button type="submit" class="btn">Upload</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Uploaded Materials <?php echo $course ? '' : '(All My Courses)'; ?></h3>
    <table>
        <tr><th>Title</th><th>Course</th><th>Type</th><th>Uploaded</th><th>File</th><th>Action</th></tr>
        <?php while ($m = $materials->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($m['title']); ?></td>
            <td class="course-code"><?php echo htmlspecialchars($m['course_code']); ?></td>
            <td><span class="type-tag type-tag-<?php echo $m['material_type']; ?>"><?php echo $m['material_type']; ?></span></td>
            <td><?php echo $m['uploaded_at']; ?></td>
            <td><a href="/lms_hndit/<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank">Download</a></td>
            <td>
                <form method="POST" onsubmit="return confirm('Delete this material?');" style="display:inline;">
                    <input type="hidden" name="delete_material" value="1">
                    <input type="hidden" name="material_id" value="<?php echo (int)$m['id']; ?>">
                    <button type="submit" class="btn">Delete</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($materials->num_rows === 0): ?><div class="empty-state">No materials uploaded yet.</div><?php endif; ?>
</div>
<a href="dashboard.php" class="btn">Back to Dashboard</a>
<script>
(function(){
    const typeSelect = document.getElementById('materialType');
    const fileInput = document.getElementById('materialFileInput');
    if (!typeSelect || !fileInput) return;
    const updateAccept = () => {
        if (typeSelect.value === 'video') {
            fileInput.accept = 'video/mp4,video/*';
        } else {
            fileInput.accept = '';
        }
    };
    typeSelect.addEventListener('change', updateAccept);
    updateAccept();
})();
</script>
<?php include '../includes/footer.php'; ?>
