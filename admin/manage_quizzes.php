<?php
require_once '../config.php';
require_role('admin');

$admin_id = $_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? 0);
$searchTerm = trim($_GET['search'] ?? '');
$filterCourseId = intval($_GET['filter_course_id'] ?? 0);
$dueFilter = $_GET['due_filter'] ?? '';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz'])) {
    $qid = intval($_POST['quiz_id'] ?? 0);
    if ($qid > 0) {
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
        $stmt->bind_param("i", $qid);
        $stmt->execute();
        $msg = "Quiz deleted successfully.";
    }
}

$quizWhere = [];
if ($course_id > 0 && $filterCourseId === 0) $filterCourseId = $course_id;
if ($filterCourseId > 0) $quizWhere[] = "q.course_id = $filterCourseId";
if ($searchTerm !== '') {
    $safeSearch = $conn->real_escape_string($searchTerm);
    $quizWhere[] = "(q.title LIKE '%$safeSearch%' OR q.description LIKE '%$safeSearch%' OR c.course_code LIKE '%$safeSearch%' OR c.course_name LIKE '%$safeSearch%')";
}
if ($dueFilter === 'upcoming') $quizWhere[] = "q.due_date >= NOW()";
if ($dueFilter === 'past') $quizWhere[] = "q.due_date < NOW()";
$quizWhereSql = count($quizWhere) ? 'WHERE ' . implode(' AND ', $quizWhere) : '';
$quizzes = $conn->query("SELECT q.*, c.course_code FROM quizzes q JOIN courses c ON q.course_id = c.id $quizWhereSql ORDER BY q.due_date DESC");

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
    <form method="GET" class="filters-bar">
        <div class="filter-field">
            <label for="quizSearch">Search</label>
            <input type="text" id="quizSearch" name="search" placeholder="Title, description or course" value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <div class="filter-field">
            <label for="quizCourse">Course</label>
            <select id="quizCourse" name="filter_course_id">
                <option value="">All Courses</option>
                <?php foreach ($allCourseList as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>" <?php echo $filterCourseId === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['course_code']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="quizDue">Due date</label>
            <select id="quizDue" name="due_filter">
                <option value="">All</option>
                <option value="upcoming" <?php echo $dueFilter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                <option value="past" <?php echo $dueFilter === 'past' ? 'selected' : ''; ?>>Past</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn">Filter</button>
            <a href="manage_quizzes.php" class="btn btn-outline">Reset</a>
        </div>
    </form>
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
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this quiz and all its questions/results?');">
                    <input type="hidden" name="delete_quiz" value="1">
                    <input type="hidden" name="quiz_id" value="<?php echo (int)$q['id']; ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($quizzes->num_rows === 0): ?><div class="empty-state">No quizzes yet.</div><?php endif; ?>
</div>
<a href="dashboard.php" class="btn">Back to Dashboard</a>
<?php include '../includes/footer.php'; ?>
