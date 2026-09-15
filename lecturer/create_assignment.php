<?php
require_once '../config.php';
require_any_role(['admin', 'lecturer']);

$current_role = $_SESSION['role'];
$current_user_id = (int)$_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? 0);
$searchTerm = trim($_GET['search'] ?? '');
$filterCourseId = intval($_GET['filter_course_id'] ?? 0);
$dueFilter = $_GET['due_filter'] ?? '';
$msg = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignment'])) {
    $delete_assignment_id = intval($_POST['assignment_id'] ?? 0);
    if ($delete_assignment_id > 0) {
        $stmt = $conn->prepare("SELECT a.id, a.course_id, c.lecturer_id FROM assignments a JOIN courses c ON c.id = a.course_id WHERE a.id = ?");
        $stmt->bind_param("i", $delete_assignment_id);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();

        if ($assignment) {
            $can_delete = $current_role === 'admin' || ($current_role === 'lecturer' && ((int)$assignment['lecturer_id'] === $current_user_id));
            if ($can_delete) {
                $file_path = $assignment['file_path'] ?? null;
                if (!empty($file_path)) {
                    $physical_path = dirname(__DIR__) . '/' . ltrim($file_path, '/');
                    if (file_exists($physical_path)) {
                        @unlink($physical_path);
                    }
                }
                $stmt2 = $conn->prepare("DELETE FROM assignments WHERE id = ?");
                $stmt2->bind_param("i", $delete_assignment_id);
                $stmt2->execute();
                $msg = "Assignment deleted successfully.";
            } else {
                $msg = "You do not have permission to delete this assignment.";
            }
        } else {
            $msg = "Assignment not found.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignment'])) {
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
        $title = trim($_POST['title']);
        $desc = trim($_POST['description']);
        $due = $_POST['due_date'];
        $assignment_file_path = null;

        if (isset($_FILES['assignment_file']) && !is_array($_FILES['assignment_file']['name']) && $_FILES['assignment_file']['name'] !== '') {
            $file = [
                'name' => $_FILES['assignment_file']['name'],
                'tmp_name' => $_FILES['assignment_file']['tmp_name'],
                'error' => $_FILES['assignment_file']['error'],
                'size' => $_FILES['assignment_file']['size'],
            ];

            if ($file['error'] !== UPLOAD_ERR_NO_FILE && $file['size'] > 0) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $msg = 'Assignment file upload failed. Please try again.';
                } elseif ($file['size'] > MAX_ASSIGNMENT_FILE_BYTES) {
                    $msg = 'The assignment file is too large. Maximum allowed size is 20 MB.';
                } else {
                    $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed, true)) {
                        $msg = 'Unsupported file type. Please upload PDF, DOC, DOCX, PPT, PPTX, XLS or XLSX files only.';
                    } else {
                        ensure_upload_dir('../uploads/assignments/');
                        $safeName = sanitize_upload_filename($file['name']);
                        $target_name = uniqid('', true) . '_' . $safeName . ($ext ? '.' . $ext : '');
                        $target_path = '../uploads/assignments/' . $target_name;
                        if (move_uploaded_file($file['tmp_name'], $target_path)) {
                            $assignment_file_path = 'uploads/assignments/' . $target_name;
                        } else {
                            $msg = 'Assignment file could not be saved.';
                        }
                    }
                }
            }
        }

        if ($msg === '') {
            if ($assignment_file_path !== null) {
                $stmt2 = $conn->prepare("INSERT INTO assignments (course_id, created_by, created_by_role, title, description, due_date, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("iisssss", $post_course_id, $current_user_id, $current_role, $title, $desc, $due, $assignment_file_path);
            } else {
                $stmt2 = $conn->prepare("INSERT INTO assignments (course_id, created_by, created_by_role, title, description, due_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("iissss", $post_course_id, $current_user_id, $current_role, $title, $desc, $due);
            }
            $stmt2->execute();
            $msg = "Assignment created.";
            $course_id = $post_course_id;
        }
    }
}

$assignmentWhere = [];
if ($current_role === 'lecturer') {
    $idList = count($myCourseList) ? implode(',', array_map(fn($c) => intval($c['id']), $myCourseList)) : '0';
    $assignmentWhere[] = "a.course_id IN ($idList)";
}
if ($course_id > 0 && $filterCourseId === 0) $filterCourseId = $course_id;
if ($filterCourseId > 0) $assignmentWhere[] = "a.course_id = $filterCourseId";
if ($searchTerm !== '') {
    $safeSearch = $conn->real_escape_string($searchTerm);
    $assignmentWhere[] = "(a.title LIKE '%$safeSearch%' OR a.description LIKE '%$safeSearch%' OR c.course_code LIKE '%$safeSearch%' OR c.course_name LIKE '%$safeSearch%')";
}
if ($dueFilter === 'upcoming') $assignmentWhere[] = "a.due_date >= NOW()";
if ($dueFilter === 'past') $assignmentWhere[] = "a.due_date < NOW()";
$assignmentWhereSql = count($assignmentWhere) ? 'WHERE ' . implode(' AND ', $assignmentWhere) : '';
$assignments = $conn->query("SELECT a.*, c.course_code FROM assignments a JOIN courses c ON a.course_id = c.id $assignmentWhereSql ORDER BY a.due_date DESC");

$pageTitle = 'Assignments';
include '../includes/header.php';
?>
<h2><?php echo $course ? 'Assignments - ' . htmlspecialchars($course['course_name']) : 'Assignments'; ?></h2>

<?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="card">
    <h3>Create New Assignment</h3>
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
        <label>Description</label>
        <textarea name="description" rows="3"></textarea>
        <label>Assignment File (PDF, DOCX, PPTX, XLSX)</label>
        <input type="file" name="assignment_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
        <div class="form-help">Optional. Maximum size: 20 MB.</div>
        <label>Due Date</label>
        <input type="datetime-local" name="due_date" required>
        <button type="submit" name="add_assignment" class="btn">Create Assignment</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Assignments <?php echo $course ? '' : '(All My Courses)'; ?></h3>
    <form method="GET" class="filters-bar">
        <div class="filter-field">
            <label for="assignmentSearch">Search</label>
            <input type="text" id="assignmentSearch" name="search" placeholder="Title, description or course" value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <div class="filter-field">
            <label for="assignmentCourse">Course</label>
            <select id="assignmentCourse" name="filter_course_id">
                <option value="">All Courses</option>
                <?php foreach ($myCourseList as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>" <?php echo $filterCourseId === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['course_code']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="assignmentDue">Due date</label>
            <select id="assignmentDue" name="due_filter">
                <option value="">All</option>
                <option value="upcoming" <?php echo $dueFilter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                <option value="past" <?php echo $dueFilter === 'past' ? 'selected' : ''; ?>>Past</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn">Filter</button>
            <a href="create_assignment.php" class="btn btn-outline">Reset</a>
        </div>
    </form>
    <table>
        <tr><th>Title</th><?php echo $course ? '' : '<th>Course</th>'; ?><th>Due Date</th><th>Action</th></tr>
        <?php while ($a = $assignments->fetch_assoc()): ?>
        <tr>
            <td>
                <?php echo htmlspecialchars($a['title']); ?>
                <?php if (!empty($a['file_path'])): ?>
                    <div style="margin-top:6px;">
                        <?php if (assignment_supports_inline_preview($a['file_path'])): ?>
                            <a href="../assignment_preview.php?assignment_id=<?php echo (int)$a['id']; ?>" target="_blank" rel="noopener" class="btn btn-outline">View File</a>
                        <?php endif; ?>
                        <a href="/lms_hndit/<?php echo htmlspecialchars($a['file_path']); ?>" target="_blank" class="btn btn-outline">Download</a>
                    </div>
                <?php endif; ?>
            </td>
            <?php if (!$course): ?><td class="course-code"><?php echo htmlspecialchars($a['course_code']); ?></td><?php endif; ?>
            <td><?php echo $a['due_date']; ?></td>
            <td>
                <a href="view_submissions.php?assignment_id=<?php echo $a['id']; ?>" class="btn">View Submissions</a>
                <form method="POST" onsubmit="return confirm('Delete this assignment?');" style="display:inline;">
                    <input type="hidden" name="delete_assignment" value="1">
                    <input type="hidden" name="assignment_id" value="<?php echo (int)$a['id']; ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($assignments->num_rows === 0): ?><div class="empty-state">No assignments yet.</div><?php endif; ?>
</div>
<a href="dashboard.php" class="btn">Back to Dashboard</a>
<?php include '../includes/footer.php'; ?>
