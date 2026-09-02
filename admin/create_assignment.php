<?php
require_once '../config.php';
require_any_role(['admin', 'lecturer']);

$current_role = $_SESSION['role'];
$actor_id = (int)$_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? 0);
$msg = '';

if ($current_role === 'admin') {
    $allCourses = $conn->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code");
} else {
    $allCourses = $conn->query("SELECT id, course_code, course_name FROM courses WHERE lecturer_id = $actor_id ORDER BY course_code");
}
$allCourseList = [];
while ($row = $allCourses->fetch_assoc()) { $allCourseList[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignment'])) {
    $delete_assignment_id = intval($_POST['assignment_id'] ?? 0);
    if ($delete_assignment_id > 0) {
        $stmt = $conn->prepare("SELECT a.id, a.course_id, c.lecturer_id FROM assignments a JOIN courses c ON c.id = a.course_id WHERE a.id = ?");
        $stmt->bind_param("i", $delete_assignment_id);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();

        if ($assignment) {
            $can_delete = $current_role === 'admin' || ($current_role === 'lecturer' && ((int)$assignment['lecturer_id'] === $actor_id));
            if ($can_delete) {
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
        $stmtc->bind_param("ii", $post_course_id, $actor_id);
    }
    $stmtc->execute();
    if (!$stmtc->get_result()->fetch_assoc()) {
        $msg = "Invalid course selection.";
    } else {
        $title = trim($_POST['title']);
        $desc = trim($_POST['description']);
        $due = $_POST['due_date'];

        $stmt2 = $conn->prepare("INSERT INTO assignments (course_id, created_by, created_by_role, title, description, due_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("iissss", $post_course_id, $actor_id, $current_role, $title, $desc, $due);
        $stmt2->execute();
        $msg = "Assignment created.";
        $course_id = $post_course_id;
    }
}

if ($course_id > 0) {
    if ($current_role === 'admin') {
        $assignments = $conn->query("SELECT a.*, c.course_code FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.course_id = $course_id ORDER BY a.due_date DESC");
    } else {
        $idList = count($allCourseList) ? implode(',', array_map(fn($c) => intval($c['id']), $allCourseList)) : '0';
        $assignments = $conn->query("SELECT a.*, c.course_code FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.course_id = $course_id AND a.course_id IN ($idList) ORDER BY a.due_date DESC");
    }
} else {
    if ($current_role === 'admin') {
        $assignments = $conn->query("SELECT a.*, c.course_code FROM assignments a JOIN courses c ON a.course_id = c.id ORDER BY a.due_date DESC");
    } else {
        $idList = count($allCourseList) ? implode(',', array_map(fn($c) => intval($c['id']), $allCourseList)) : '0';
        $assignments = $conn->query("SELECT a.*, c.course_code FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.course_id IN ($idList) ORDER BY a.due_date DESC");
    }
}

$pageTitle = 'Assignments';
$pageEyebrow = ucfirst($current_role);
include '../includes/header.php';
?>
<h2>Assignments</h2>

<?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="card">
    <h3>Create New Assignment</h3>
    <?php if (empty($allCourseList)): ?>
        <div class="empty-state">No courses exist yet. Create a course first.</div>
    <?php else: ?>
    <form method="POST">
        <label>Course</label>
        <select name="course_id" required>
            <?php foreach ($allCourseList as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Title</label>
        <input type="text" name="title" required>
        <label>Description</label>
        <textarea name="description" rows="3"></textarea>
        <label>Due Date</label>
        <input type="datetime-local" name="due_date" required>
        <button type="submit" name="add_assignment" class="btn">Create Assignment</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h3>All Assignments</h3>
    <table>
        <tr><th>Title</th><th>Course</th><th>Due Date</th><th>Action</th></tr>
        <?php while ($a = $assignments->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($a['title']); ?></td>
            <td class="course-code"><?php echo htmlspecialchars($a['course_code']); ?></td>
            <td><?php echo $a['due_date']; ?></td>
            <td>
                <a href="view_submissions.php?assignment_id=<?php echo $a['id']; ?>" class="btn">View Submissions</a>
                <form method="POST" onsubmit="return confirm('Delete this assignment?');" style="display:inline;">
                    <input type="hidden" name="delete_assignment" value="1">
                    <input type="hidden" name="assignment_id" value="<?php echo (int)$a['id']; ?>">
                    <button type="submit" class="btn">Delete</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($assignments->num_rows === 0): ?><div class="empty-state">No assignments yet.</div><?php endif; ?>
</div>
<a href="dashboard.php" class="btn">Back to Dashboard</a>
<?php include '../includes/footer.php'; ?>
