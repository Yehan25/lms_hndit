<?php
require_once '../config.php';
require_role('lecturer');

$lecturer_id = $_SESSION['user_id'];
$quiz_id = intval($_GET['quiz_id'] ?? 0);
$msg = isset($_GET['created']) ? "Quiz created. Add multiple-choice questions below." : '';

$stmt = $conn->prepare("
    SELECT q.*, c.course_name FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    WHERE q.id = ? AND c.lecturer_id = ?
");
$stmt->bind_param("ii", $quiz_id, $lecturer_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
if (!$quiz) { die("Quiz not found or access denied."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $qtext = trim($_POST['question_text']);
    $a = trim($_POST['option_a']);
    $b = trim($_POST['option_b']);
    $c = trim($_POST['option_c']);
    $d = trim($_POST['option_d']);
    $correct = $_POST['correct_option'];
    $marks = max(1, intval($_POST['marks']));

    $stmt2 = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt2->bind_param("issssssi", $quiz_id, $qtext, $a, $b, $c, $d, $correct, $marks);
    $stmt2->execute();
    $msg = "Question added.";
}

if (isset($_GET['delete_q'])) {
    $qid = intval($_GET['delete_q']);
    $conn->query("DELETE FROM quiz_questions WHERE id = $qid AND quiz_id = $quiz_id");
    header("Location: quiz_questions.php?quiz_id=$quiz_id");
    exit();
}

$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY id");

$pageTitle = 'Quiz Questions';
include '../includes/header.php';
?>
<h2>Questions - <?php echo htmlspecialchars($quiz['title']); ?></h2>
<p><?php echo htmlspecialchars($quiz['course_name']); ?> | Due: <?php echo $quiz['due_date']; ?></p>

<?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="card">
    <h3>Add Multiple-Choice Question</h3>
    <form method="POST">
        <label>Question</label>
        <textarea name="question_text" rows="2" required></textarea>
        <label>Option A</label>
        <input type="text" name="option_a" required>
        <label>Option B</label>
        <input type="text" name="option_b" required>
        <label>Option C</label>
        <input type="text" name="option_c" required>
        <label>Option D</label>
        <input type="text" name="option_d" required>
        <label>Correct Option</label>
        <select name="correct_option" required>
            <option value="A">A</option>
            <option value="B">B</option>
            <option value="C">C</option>
            <option value="D">D</option>
        </select>
        <label>Marks</label>
        <input type="number" name="marks" value="1" min="1" required>
        <button type="submit" name="add_question" class="btn">Add Question</button>
    </form>
</div>

<div class="card">
    <h3>Questions (<?php echo $questions->num_rows; ?>)</h3>
    <?php while ($q = $questions->fetch_assoc()): ?>
        <div class="quiz-question">
            <h4><?php echo htmlspecialchars($q['question_text']); ?> <span style="color:var(--text-faint); font-weight:400;">(<?php echo $q['marks']; ?> mark<?php echo $q['marks']>1?'s':''; ?>)</span></h4>
            <ul style="margin:0 0 8px 18px; padding:0; font-size:13.5px;">
                <li<?php echo $q['correct_option']==='A'?' style="color:var(--teal-dark); font-weight:700;"':''; ?>>A. <?php echo htmlspecialchars($q['option_a']); ?></li>
                <li<?php echo $q['correct_option']==='B'?' style="color:var(--teal-dark); font-weight:700;"':''; ?>>B. <?php echo htmlspecialchars($q['option_b']); ?></li>
                <li<?php echo $q['correct_option']==='C'?' style="color:var(--teal-dark); font-weight:700;"':''; ?>>C. <?php echo htmlspecialchars($q['option_c']); ?></li>
                <li<?php echo $q['correct_option']==='D'?' style="color:var(--teal-dark); font-weight:700;"':''; ?>>D. <?php echo htmlspecialchars($q['option_d']); ?></li>
            </ul>
            <a href="?quiz_id=<?php echo $quiz_id; ?>&delete_q=<?php echo $q['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this question?')">Delete</a>
        </div>
    <?php endwhile; ?>
    <?php if ($questions->num_rows === 0): ?><div class="empty-state">No questions added yet.</div><?php endif; ?>
</div>
<a href="manage_quizzes.php" class="btn">Back to Quizzes</a>
<?php include '../includes/footer.php'; ?>
