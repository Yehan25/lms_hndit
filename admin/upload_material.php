<?php
require_once '../config.php';
require_any_role(['admin', 'lecturer']);

$current_role = $_SESSION['role'];
$actor_id = (int)$_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? 0);
$selectedSemester = trim((string)($_GET['semester'] ?? $_POST['semester'] ?? ''));
$searchTerm = trim((string)($_GET['search'] ?? $_POST['search'] ?? ''));
$selectedType = in_array(($_GET['material_type_filter'] ?? $_POST['material_type_filter'] ?? ''), ['document', 'video'], true)
    ? ($_GET['material_type_filter'] ?? $_POST['material_type_filter'])
    : '';
$msg = '';
$maxUploadSize = ini_get('upload_max_filesize');
// compute effective limits
$phpPostMax = php_size_to_bytes(ini_get('post_max_size'));
$phpUploadMax = php_size_to_bytes(ini_get('upload_max_filesize'));
$effectiveMax = min($phpPostMax, $phpUploadMax, MAX_VIDEO_UPLOAD_BYTES);
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $msg = 'Material uploaded successfully.';
}

if ($current_role === 'admin') {
    $allCourses = $conn->query("SELECT id, course_code, course_name, semester FROM courses ORDER BY semester IS NULL, semester, course_code");
} else {
    $allCourses = $conn->query("SELECT id, course_code, course_name FROM courses WHERE lecturer_id = $actor_id ORDER BY course_code");
}
$allCourseList = [];
while ($row = $allCourses->fetch_assoc()) { $allCourseList[] = $row; }

$semesterOptions = [];
foreach ($allCourseList as $courseRow) {
    $semester = trim((string)($courseRow['semester'] ?? ''));
    if ($semester !== '' && !in_array($semester, $semesterOptions, true)) {
        $semesterOptions[] = $semester;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_material'])) {
    $delete_material_id = intval($_POST['material_id'] ?? 0);
    if ($delete_material_id > 0) {
        $stmt = $conn->prepare("SELECT m.id, m.file_path, m.course_id, c.lecturer_id FROM materials m JOIN courses c ON c.id = m.course_id WHERE m.id = ?");
        $stmt->bind_param("i", $delete_material_id);
        $stmt->execute();
        $material = $stmt->get_result()->fetch_assoc();

        if ($material) {
            $can_delete = $current_role === 'admin' || ($current_role === 'lecturer' && ((int)$material['lecturer_id'] === $actor_id));
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

// Handle one or many material files in a single submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['material_file']) || !is_array($_FILES['material_file']['name'])) {
        $msg = 'Upload failed: no files were received. The files may be larger than the PHP limit (' . ini_get('post_max_size') . ').';
    } else {
        $post_course_id = intval($_POST['course_id'] ?? 0);
        $titlePrefix = trim((string)($_POST['title'] ?? ''));
        $material_type = ($_POST['material_type'] ?? '') === 'video' ? 'video' : 'document';
        if ($current_role === 'admin') {
            $stmtc = $conn->prepare("SELECT id FROM courses WHERE id = ?");
            $stmtc->bind_param("i", $post_course_id);
        } else {
            $stmtc = $conn->prepare("SELECT id FROM courses WHERE id = ? AND lecturer_id = ?");
            $stmtc->bind_param("ii", $post_course_id, $actor_id);
        }
        $stmtc->execute();

        if (!$stmtc->get_result()->fetch_assoc()) {
            $msg = 'Invalid course selection.';
        } else {
            $target_dir = "../uploads/materials/";
            ensure_upload_dir($target_dir);
            $uploadedCount = 0;
            $errors = [];
            $fileCount = count($_FILES['material_file']['name']);
            $insert = $conn->prepare("INSERT INTO materials (course_id, uploaded_by, uploaded_by_role, title, material_type, file_path) VALUES (?, ?, ?, ?, ?, ?)");

            for ($index = 0; $index < $fileCount; $index++) {
                $file = [
                    'name' => $_FILES['material_file']['name'][$index],
                    'type' => $_FILES['material_file']['type'][$index],
                    'tmp_name' => $_FILES['material_file']['tmp_name'][$index],
                    'error' => $_FILES['material_file']['error'][$index],
                    'size' => $_FILES['material_file']['size'][$index],
                ];
                $fileLabel = pathinfo($file['name'], PATHINFO_FILENAME);

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = $file['name'] . ': ' . upload_error_message($file['error']);
                    continue;
                }
                if ($material_type === 'video') {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    if ($file['size'] > MAX_VIDEO_UPLOAD_BYTES) {
                        $errors[] = $file['name'] . ': video exceeds the maximum allowed size of 50 MB.';
                        continue;
                    }
                    if (!in_array($mime, ['video/mp4', 'video/webm', 'video/ogg'], true)) {
                        $errors[] = $file['name'] . ': unsupported video format. Please upload MP4, WebM or OGG.';
                        continue;
                    }
                }
                if (!is_uploaded_file($file['tmp_name'])) {
                    $errors[] = $file['name'] . ': uploaded file is not valid.';
                    continue;
                }

                $baseName = sanitize_upload_filename($file['name']);
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = uniqid('', true) . "_" . $baseName . ($extension ? "." . $extension : '');
                $target_path = $target_dir . $filename;
                $title = $titlePrefix === '' ? $fileLabel : ($fileCount > 1 ? $titlePrefix . ' - ' . $fileLabel : $titlePrefix);

                if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                    $errors[] = $file['name'] . ': could not move the file to the uploads folder.';
                    continue;
                }

                $rel_path = "uploads/materials/" . $filename;
                $insert->bind_param("iissss", $post_course_id, $actor_id, $current_role, $title, $material_type, $rel_path);
                if ($insert->execute()) {
                    $uploadedCount++;
                } else {
                    @unlink($target_path);
                    $errors[] = $file['name'] . ': could not save the material record.';
                }
            }

            if ($uploadedCount > 0) {
                $msg = $uploadedCount . ' material' . ($uploadedCount === 1 ? '' : 's') . ' uploaded successfully.';
            }
            if (!empty($errors)) {
                $msg .= ($msg !== '' ? '<br>' : '') . 'Some files were not uploaded:<br>' . implode('<br>', array_map('htmlspecialchars', $errors));
            }
            if ($uploadedCount > 0 && empty($errors)) {
                header('Location: upload_material.php?course_id=' . $post_course_id . '&success=1');
                exit;
            }
        }
    }
}

if ($course_id > 0) {
    if ($current_role === 'admin') {
        $materialsQuery = "SELECT m.*, c.course_code, c.course_name, c.semester
            FROM materials m JOIN courses c ON m.course_id = c.id
            WHERE m.course_id = " . $course_id;
    } else {
        $idList = count($allCourseList) ? implode(',', array_map(fn($c) => intval($c['id']), $allCourseList)) : '0';
        $materialsQuery = "SELECT m.*, c.course_code, c.course_name, c.semester
            FROM materials m JOIN courses c ON m.course_id = c.id
            WHERE m.course_id = " . $course_id . " AND m.course_id IN ($idList)";
    }
} else {
    if ($current_role === 'admin') {
        $materialsQuery = "SELECT m.*, c.course_code, c.course_name, c.semester
            FROM materials m JOIN courses c ON m.course_id = c.id";
    } else {
        $idList = count($allCourseList) ? implode(',', array_map(fn($c) => intval($c['id']), $allCourseList)) : '0';
        $materialsQuery = "SELECT m.*, c.course_code, c.course_name, c.semester
            FROM materials m JOIN courses c ON m.course_id = c.id
            WHERE m.course_id IN ($idList)";
    }
}

$searchPattern = $conn->real_escape_string('%' . $searchTerm . '%');
if ($searchTerm !== '') {
    $materialsQuery .= " AND (m.title LIKE '" . $searchPattern . "' OR c.course_code LIKE '" . $searchPattern . "' OR c.course_name LIKE '" . $searchPattern . "')";
}
if ($selectedSemester !== '') {
    $materialsQuery .= " AND c.semester = '" . $conn->real_escape_string($selectedSemester) . "'";
}
if ($selectedType !== '') {
    $materialsQuery .= " AND m.material_type = '" . $conn->real_escape_string($selectedType) . "'";
}
$materialsQuery .= " ORDER BY m.uploaded_at DESC";
$materials = $conn->query($materialsQuery);

$pageTitle = 'Upload Material';
$pageEyebrow = ucfirst($current_role);
include '../includes/header.php';
?>
<h2>Upload Course Material</h2>

<?php if ($msg): ?><div class="alert <?php echo strpos($msg,'success')!==false ? 'alert-success' : 'alert-error'; ?>"><?php echo $msg; ?></div><?php endif; ?>

<div class="card">
    <h3>Upload New Material</h3>
    <?php if (empty($allCourseList)): ?>
        <div class="empty-state">No courses exist yet. Create a course first.</div>
    <?php else: ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Course</label>
        <select name="course_id" required>
            <?php foreach ($allCourseList as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Title (optional)</label>
        <input type="text" name="title" placeholder="Leave blank to use each filename">
        <label>Material Type</label>
        <select name="material_type" id="materialType">
            <option value="document">Document / File (PDF, DOCX, PPT, ZIP...)</option>
            <option value="video">Video (MP4)</option>
        </select>
        <label>Files</label>
        <input type="file" name="material_file[]" id="materialFileInput" multiple required>
        <div id="selectedFiles" class="form-help">You can select multiple files. Leave the title blank to use each filename.</div>
        <div class="form-help">Max upload size per video: <?php echo (MAX_VIDEO_UPLOAD_BYTES/1024/1024) . ' MB'; ?> (server: <?php echo htmlspecialchars($maxUploadSize); ?>). Select "Video" for MP4/WebM/OGG files.</div>
        <button type="submit" class="btn">Upload Materials</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Uploaded Materials <?php echo $course_id ? '' : '(All Courses)'; ?></h3>
    <form method="GET" class="filters-bar">
        <div class="filter-field">
            <label for="admin-material-search">Search materials</label>
            <input type="search" id="admin-material-search" name="search"
                   value="<?php echo htmlspecialchars($searchTerm); ?>"
                   placeholder="Title, course code or course name">
        </div>
        <div class="filter-field">
            <label for="admin-material-semester">Filter by semester</label>
            <select id="admin-material-semester" name="semester">
                <option value="">All Semesters</option>
                <?php foreach ($semesterOptions as $semester): ?>
                    <option value="<?php echo htmlspecialchars($semester); ?>" <?php echo $selectedSemester === $semester ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($semester); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="admin-material-course">Filter by course</label>
            <select id="admin-material-course" name="course_id">
                <option value="0">All Courses</option>
                <?php foreach ($allCourseList as $courseOption): ?>
                    <option value="<?php echo (int)$courseOption['id']; ?>" <?php echo $course_id === (int)$courseOption['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($courseOption['course_code'] . ' - ' . $courseOption['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="admin-material-type">Filter by type</label>
            <select id="admin-material-type" name="material_type_filter">
                <option value="">All Types</option>
                <option value="document" <?php echo $selectedType === 'document' ? 'selected' : ''; ?>>Document</option>
                <option value="video" <?php echo $selectedType === 'video' ? 'selected' : ''; ?>>Video</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn">Search / Filter</button>
            <?php if ($searchTerm !== '' || $selectedSemester !== '' || $selectedType !== ''): ?>
                <a href="upload_material.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </div>
    </form>
    <p class="table-meta">
        <?php echo $materials->num_rows; ?> material<?php echo $materials->num_rows === 1 ? '' : 's'; ?> found
    </p>
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
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selectedSemester); ?>">
                    <input type="hidden" name="course_id" value="<?php echo (int)$course_id; ?>">
                    <button type="submit" class="btn">Delete</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($materials->num_rows === 0): ?>
        <div class="empty-state">
            <?php echo ($searchTerm !== '' || $selectedSemester !== '' || $selectedType !== '')
                ? 'No materials match the selected search or filter.'
                : 'No materials uploaded yet.'; ?>
        </div>
    <?php endif; ?>
</div>
<a href="dashboard.php" class="btn">Back to Dashboard</a>
<script>
(function(){
    const typeSelect = document.getElementById('materialType');
    const fileInput = document.getElementById('materialFileInput');
    const selectedFiles = document.getElementById('selectedFiles');
    if (!typeSelect || !fileInput) return;
    const updateAccept = () => {
        if (typeSelect.value === 'video') {
            fileInput.accept = 'video/mp4,video/*';
        } else {
            fileInput.accept = '';
        }
    };
    fileInput.addEventListener('change', () => {
        const count = fileInput.files.length;
        selectedFiles.textContent = count ? count + ' file' + (count === 1 ? '' : 's') + ' selected.' : 'You can select multiple files.';
    });
    typeSelect.addEventListener('change', updateAccept);
    updateAccept();
})();
</script>
<?php include '../includes/footer.php'; ?>
