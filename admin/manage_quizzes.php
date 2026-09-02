<?php
require_once '../config.php';
require_role('admin');

$admin_id = $_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? 0);
$msg = '';

$allCourses = $conn->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code");
$allCourseList = [];
while ($row = $allCourses->fetch_assoc()) { $allCourseList[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_quiz'])) {
    $post_course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $due = $_POST['due_date'];

    $stmt = $conn->prepare("INSERT INTO quizzes (course_id, title, description, due_date, created_by, created_by_role) VALUES (?, ?, ?, ?, ?, 'admin')");
    $stmt->bind_param("isssi", $post_course_id, $title, $desc, $due, $admin_id);
    $stmt->execute();
    $newQuizId = $conn->insert_id;
    $msg = "Quiz created. Now add questions to it below.";
    $course_id = $post_course_id;
    header("Location: quiz_questions.php?quiz_id=$newQuizId&created=1");
    exit();
}

if (isset($_GET['delete'])) {
    $qid = intval($_GET['delete']);
    $conn->query("DELETE FROM quizzes WHERE id = $qid");
    header("Location: manage_quizzes.php");
    exit();
}

if ($course_id > 0) {
    $quizzes = $conn->query("SELECT q.*, c.course_code FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE q.course_id = $course_id ORDER BY q.due_date DESC");
} else {
    $quizzes = $conn->query("SELECT q.*, c.course_code FROM quizzes q JOIN courses c ON q.course_id = c.id ORDER BY q.due_date DESC");
}

$pageTitle = 'Quizzes';
$pageEyebrow = 'Admin';
include '../includes/header.php';
?>
<h2>Manage Quizzes</h2>

<?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="card">
    <h3>Create New Quiz</h3>
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
        <label>Quiz Title</label>
        <input type="text" name="title" required>
        <label>Description</label>
        <textarea name="description" rows="2"></textarea>
        <label>Due Date</label>
        <input type="datetime-local" name="due_date" required>
        <button type="submit" name="add_quiz" class="btn">Create Quiz &amp; Add Questions</button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <h3>All Quizzes</h3>
    <table>
        <tr><th>Title</th><th>Course</th><th>Due Date</th><th>Questions</th><th>Actions</th></tr>
        <?php while ($q = $quizzes->fetch_assoc()):
            $qCount = $conn->query("SELECT COUNT(*) AS c FROM quiz_questions WHERE quiz_id = " . $q['id'])->fetch_assoc()['c'];
        ?>
        <tr>
            <td><?php echo htmlspecialchars($q['title']); ?></td>
            <td class="course-code"><?php echo htmlspecialchars($q['course_code']); ?></td>
            <td><?php echo $q['due_date']; ?></td>
            <td><?php echo $qCount; ?></td>
            <td>
                <a href="quiz_questions.php?quiz_id=<?php echo $q['id']; ?>" class="btn btn-outline">Questions</a>
                <a href="view_quiz_results.php?quiz_id=<?php echo $q['id']; ?>" class="btn">Results</a>
                <a href="?delete=<?php echo $q['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this quiz and all its questions/results?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($quizzes->num_rows === 0): ?><div class="empty-state">No quizzes yet.</div><?php endif; ?>
</div>
<a href="dashboard.php" class="btn">Back to Dashboard</a>
<?php include '../includes/footer.php'; ?>
