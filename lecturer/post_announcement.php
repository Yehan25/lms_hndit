<?php
require_once '../config.php';
require_any_role(['admin', 'lecturer']);

$current_role = $_SESSION['role'];
$current_user_id = (int)$_SESSION['user_id'];
$msg = '';
$editAnnouncement = null;

function fetch_allowed_courses($conn, $role, $user_id) {
    if ($role === 'admin') {
        $result = $conn->query("SELECT id, course_code, course_name, semester FROM courses ORDER BY semester IS NULL, semester, course_code");
        $rows = [];
        while ($row = $result->fetch_assoc()) { $rows[] = $row; }
        return $rows;
    }

    $stmt = $conn->prepare("SELECT DISTINCT c.id, c.course_code, c.course_name, c.semester
        FROM courses c
        LEFT JOIN course_lecturers cl ON cl.course_id = c.id
        WHERE c.lecturer_id = ? OR cl.lecturer_id = ?
        ORDER BY c.semester IS NULL, c.semester, c.course_code");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    return $rows;
}

$allowedCourses = fetch_allowed_courses($conn, $current_role, $current_user_id);
$allowedCourseIds = [];
foreach ($allowedCourses as $courseRow) {
    $allowedCourseIds[] = (int)$courseRow['id'];
}
$idListSql = count($allowedCourseIds) ? implode(',', array_map('intval', $allowedCourseIds)) : '0';

$course_id = intval($_GET['course_id'] ?? 0);
if ($course_id === 0 && !empty($allowedCourses)) {
    $course_id = (int)$allowedCourses[0]['id'];
}
if (!in_array($course_id, $allowedCourseIds, true)) {
    $course_id = !empty($allowedCourseIds) ? $allowedCourseIds[0] : 0;
}

if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT a.*, c.course_code, c.course_name FROM announcements a JOIN courses c ON c.id = a.course_id WHERE a.id = ? AND a.course_id IN ($idListSql)");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editAnnouncement = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postCourseId = intval($_POST['course_id'] ?? 0);
    $announcementId = intval($_POST['announcement_id'] ?? 0);

    if (!in_array($postCourseId, $allowedCourseIds, true)) {
        $msg = 'You do not have permission to manage announcements for that course.';
    } else {
        if ($action === 'delete') {
            $stmt = $conn->prepare("SELECT file_path FROM announcements WHERE id = ? AND course_id = ?");
            $stmt->bind_param("ii", $announcementId, $postCourseId);
            $stmt->execute();
            $announcement = $stmt->get_result()->fetch_assoc();

            if ($announcement) {
                if (!empty($announcement['file_path'])) {
                    $physical_path = dirname(__DIR__) . '/' . ltrim($announcement['file_path'], '/');
                    if (file_exists($physical_path)) {
                        @unlink($physical_path);
                    }
                }

                $deleteStmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND course_id = ?");
                $deleteStmt->bind_param("ii", $announcementId, $postCourseId);
                $deleteStmt->execute();
                $msg = 'Announcement deleted successfully.';
            } else {
                $msg = 'Announcement not found.';
            }
        }

        if ($action === 'save') {
            $title = trim((string)($_POST['title'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));
            $file_path = null;

            if ($title === '' || $message === '') {
                $msg = 'Title and message are required.';
            } else {
                if (isset($_FILES['attachment']) && is_array($_FILES['attachment']) && $_FILES['attachment']['name'] !== '') {
                    $file = $_FILES['attachment'];
                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $msg = 'Announcement attachment upload failed. Please try again.';
                    } elseif ($file['size'] > 10 * 1024 * 1024) {
                        $msg = 'Attachment is too large. Maximum size is 10 MB.';
                    } else {
                        $target_dir = '../uploads/announcements/';
                        ensure_upload_dir($target_dir);
                        $target_name = time() . '_' . sanitize_upload_filename($file['name']);
                        $target_path = $target_dir . $target_name;
                        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                            $msg = 'Failed to save the uploaded attachment.';
                        } else {
                            $file_path = 'uploads/announcements/' . $target_name;
                        }
                    }
                }

                if ($msg === '') {
                    if ($announcementId > 0) {
                        $existingStmt = $conn->prepare("SELECT file_path FROM announcements WHERE id = ? AND course_id = ?");
                        $existingStmt->bind_param("ii", $announcementId, $postCourseId);
                        $existingStmt->execute();
                        $existing = $existingStmt->get_result()->fetch_assoc();

                        if ($file_path === null && $existing && !empty($existing['file_path'])) {
                            $file_path = $existing['file_path'];
                        } elseif ($file_path !== null && $existing && !empty($existing['file_path']) && $existing['file_path'] !== $file_path) {
                            $old_path = dirname(__DIR__) . '/' . ltrim($existing['file_path'], '/');
                            if (file_exists($old_path)) {
                                @unlink($old_path);
                            }
                        }

                        $updateStmt = $conn->prepare("UPDATE announcements SET title = ?, message = ?, file_path = ? WHERE id = ? AND course_id = ?");
                        $updateStmt->bind_param("sssii", $title, $message, $file_path, $announcementId, $postCourseId);
                        $updateStmt->execute();
                        $msg = 'Announcement updated successfully.';
                    } else {
                        $insertStmt = $conn->prepare("INSERT INTO announcements (course_id, title, message, file_path) VALUES (?, ?, ?, ?)");
                        $insertStmt->bind_param("isss", $postCourseId, $title, $message, $file_path);
                        $insertStmt->execute();
                        $msg = 'Announcement added successfully.';
                    }

                    $course_id = $postCourseId;
                    header('Location: post_announcement.php?course_id=' . $course_id . '&success=1');
                    exit;
                }
            }
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $msg = $msg === '' ? 'Announcement saved successfully.' : $msg;
}

$announcements = [];
$announcementQuery = "SELECT a.*, c.course_code, c.course_name FROM announcements a JOIN courses c ON c.id = a.course_id WHERE a.course_id IN ($idListSql)";
if ($course_id > 0) {
    $announcementQuery .= " AND a.course_id = " . intval($course_id);
}
$announcementQuery .= " ORDER BY a.posted_at DESC";
$announcementResult = $conn->query($announcementQuery);
if ($announcementResult) {
    while ($row = $announcementResult->fetch_assoc()) {
        $announcements[] = $row;
    }
}

$pageTitle = 'Announcements';
include '../includes/header.php';
?>

<h2>Announcements</h2>

<?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <h3><?php echo $editAnnouncement ? 'Edit Announcement' : 'Create New Announcement'; ?></h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">
        <?php if ($editAnnouncement): ?>
            <input type="hidden" name="announcement_id" value="<?php echo (int)$editAnnouncement['id']; ?>">
        <?php endif; ?>

        <label>Course</label>
        <select name="course_id" required>
            <?php foreach ($allowedCourses as $courseRow): ?>
                <option value="<?php echo (int)$courseRow['id']; ?>" <?php echo (int)$courseRow['id'] === $course_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($courseRow['course_code'] . ' - ' . $courseRow['course_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Title</label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($editAnnouncement['title'] ?? ''); ?>" required>

        <label>Message</label>
        <textarea name="message" rows="5" required><?php echo htmlspecialchars($editAnnouncement['message'] ?? ''); ?></textarea>

        <label>Attachment (optional)</label>
        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.mp4,.zip,.txt">
        <?php if ($editAnnouncement && !empty($editAnnouncement['file_path'])): ?>
            <div style="margin-top:8px;">
                <strong>Current attachment:</strong>
                <a href="/lms_hndit/<?php echo htmlspecialchars($editAnnouncement['file_path']); ?>" target="_blank" style="margin-left:8px;">View file</a>
            </div>
        <?php endif; ?>
        <small style="display:block; margin-top:8px; color:var(--text-muted);">Max size: 10 MB</small>

        <div style="display:flex; gap:10px; align-items:center; margin-top:16px; flex-wrap:wrap;">
            <button type="submit" class="btn"><?php echo $editAnnouncement ? 'Update Announcement' : 'Add Announcement'; ?></button>
            <?php if ($editAnnouncement): ?>
                <a href="post_announcement.php?course_id=<?php echo (int)$course_id; ?>" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
        <h3 style="margin:0;">Recent Announcements</h3>
        <select onchange="window.location='post_announcement.php?course_id=' + this.value" style="min-width:220px;">
            <option value="">All eligible courses</option>
            <?php foreach ($allowedCourses as $courseRow): ?>
                <option value="<?php echo (int)$courseRow['id']; ?>" <?php echo (int)$courseRow['id'] === $course_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($courseRow['course_code'] . ' - ' . $courseRow['course_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (empty($announcements)): ?>
        <div class="empty-state">No announcements have been added for this course yet.</div>
    <?php else: ?>
        <div class="announcement-list">
            <?php foreach ($announcements as $a): ?>
                <div class="announcement-card">
                    <div class="announcement-card__header">
                        <button type="button" class="announcement-toggle" data-target="announcement-<?php echo (int)$a['id']; ?>" aria-expanded="false">
                            <span class="announcement-toggle__title"><?php echo htmlspecialchars($a['title']); ?></span>
                            <span class="announcement-toggle__meta"><?php echo htmlspecialchars($a['course_code']); ?> · Posted on <?php echo htmlspecialchars($a['posted_at']); ?></span>
                        </button>
                        <div class="announcement-actions">
                            <span class="announcement-pill"><?php echo htmlspecialchars($a['course_code']); ?></span>
                            <a href="post_announcement.php?course_id=<?php echo (int)$course_id; ?>&edit=<?php echo (int)$a['id']; ?>" class="btn btn-outline" style="padding:8px 12px;">Edit</a>
                            <form method="POST" onsubmit="return confirm('Delete this announcement?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="course_id" value="<?php echo (int)$a['course_id']; ?>">
                                <input type="hidden" name="announcement_id" value="<?php echo (int)$a['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding:8px 12px;">Delete</button>
                            </form>
                        </div>
                    </div>

                    <div id="announcement-<?php echo (int)$a['id']; ?>" class="announcement-details" hidden>
                        <p class="announcement-message"><?php echo nl2br(htmlspecialchars($a['message'])); ?></p>
                        <?php if (!empty($a['file_path'])): ?>
                            <div class="announcement-attachment">
                                <a href="/lms_hndit/<?php echo htmlspecialchars($a['file_path']); ?>" target="_blank" class="btn btn-outline">View Attachment</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<a href="dashboard.php" class="btn">Back to Dashboard</a>
<?php include '../includes/footer.php'; ?>
