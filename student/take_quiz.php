<?php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$quiz_id = intval($_GET['quiz_id'] ?? 0);

// Verify enrollment
$stmt = $conn->prepare("
    SELECT qz.*, c.course_name FROM quizzes qz
    JOIN courses c ON qz.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
    WHERE qz.id = ?
");
$stmt->bind_param("ii", $student_id, $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
if (!$quiz) { die("Quiz not found or you are not enrolled in this course."); }

// Already attempted?
$stmt2 = $conn->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
$stmt2->bind_param("ii", $quiz_id, $student_id);
$stmt2->execute();
$attempt = $stmt2->get_result()->fetch_assoc();

$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY id");
$questionRows = [];
while ($row = $questions->fetch_assoc()) { $questionRows[] = $row; }

// Handle submission (only if not already attempted)
if (!$attempt && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $score = 0;
    $total = 0;
    $answersToSave = [];

    foreach ($questionRows as $q) {
        $total += (int)$q['marks'];
        $selected = $_POST['q' . $q['id']] ?? null;
        $isCorrect = ($selected === $q['correct_option']) ? 1 : 0;
        if ($isCorrect) { $score += (int)$q['marks']; }
        if ($selected) {
            $answersToSave[] = [$q['id'], $selected, $isCorrect];
        }
    }

    $stmt3 = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, student_id, score, total_marks) VALUES (?, ?, ?, ?)");
    $stmt3->bind_param("iiii", $quiz_id, $student_id, $score, $total);
    $stmt3->execute();
    $attemptId = $conn->insert_id;

    $stmt4 = $conn->prepare("INSERT INTO quiz_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?)");
    foreach ($answersToSave as $ans) {
        $stmt4->bind_param("iisi", $attemptId, $ans[0], $ans[1], $ans[2]);
        $stmt4->execute();
    }

    header("Location: take_quiz.php?quiz_id=$quiz_id");
    exit();
}

// Fetch saved answers if already attempted, for review
$savedAnswers = [];
if ($attempt) {
    $ansRes = $conn->query("SELECT * FROM quiz_answers WHERE attempt_id = " . $attempt['id']);
    while ($a = $ansRes->fetch_assoc()) { $savedAnswers[$a['question_id']] = $a; }
}

$pageTitle = 'Quiz';
include '../includes/header.php';
?>
<h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
<p><?php echo htmlspecialchars($quiz['course_name']); ?> | Due: <?php echo $quiz['due_date']; ?></p>

<?php if ($attempt): ?>
    <div class="card">
        <div class="quiz-score-banner">
            <div class="score-num"><?php echo $attempt['score']; ?> / <?php echo $attempt['total_marks']; ?></div>
            <div class="score-label">Your Score &middot; Submitted <?php echo $attempt['submitted_at']; ?></div>
        </div>
    </div>

    <div class="card">
        <h3>Review</h3>
        <?php foreach ($questionRows as $q):
            $yourAnswer = $savedAnswers[$q['id']]['selected_option'] ?? null;
            $wasCorrect = $savedAnswers[$q['id']]['is_correct'] ?? 0;
        ?>
        <div class="quiz-question">
            <h4><?php echo htmlspecialchars($q['question_text']); ?> <span style="color:var(--text-faint); font-weight:400;">(<?php echo $q['marks']; ?> mark<?php echo $q['marks']>1?'s':''; ?>)</span></h4>
            <?php foreach (['A','B','C','D'] as $opt):
                $optText = $q['option_' . strtolower($opt)];
                $isCorrectOpt = ($q['correct_option'] === $opt);
                $isYourAnswer = ($yourAnswer === $opt);
                $style = '';
                if ($isCorrectOpt) { $style = 'color:var(--teal-dark); font-weight:700;'; }
                if ($isYourAnswer && !$isCorrectOpt) { $style = 'color:var(--coral); font-weight:700;'; }
            ?>
                <div style="font-size:13.5px; padding:2px 0; <?php echo $style; ?>">
                    <?php echo $opt; ?>. <?php echo htmlspecialchars($optText); ?>
                    <?php if ($isYourAnswer): ?> &larr; your answer<?php endif; ?>
                    <?php if ($isCorrectOpt): ?> &check; correct<?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php if (empty($questionRows)): ?>
        <div class="card"><div class="empty-state">This quiz has no questions yet.</div></div>
    <?php else: ?>
    <form method="POST">
        <?php foreach ($questionRows as $i => $q): ?>
        <div class="quiz-question">
            <h4>Q<?php echo $i+1; ?>. <?php echo htmlspecialchars($q['question_text']); ?> <span style="color:var(--text-faint); font-weight:400;">(<?php echo $q['marks']; ?> mark<?php echo $q['marks']>1?'s':''; ?>)</span></h4>
            <div class="quiz-options">
                <label><input type="radio" name="q<?php echo $q['id']; ?>" value="A" required> A. <?php echo htmlspecialchars($q['option_a']); ?></label>
                <label><input type="radio" name="q<?php echo $q['id']; ?>" value="B"> B. <?php echo htmlspecialchars($q['option_b']); ?></label>
                <label><input type="radio" name="q<?php echo $q['id']; ?>" value="C"> C. <?php echo htmlspecialchars($q['option_c']); ?></label>
                <label><input type="radio" name="q<?php echo $q['id']; ?>" value="D"> D. <?php echo htmlspecialchars($q['option_d']); ?></label>
            </div>
        </div>
        <?php endforeach; ?>
        <button type="submit" name="submit_quiz" class="btn btn-amber" onclick="return confirm('Submit your answers? You cannot change them afterwards.')">Submit Quiz</button>
    </form>
    <?php endif; ?>
<?php endif; ?>

<p style="margin-top:16px;"><a href="view_quizzes.php" class="btn btn-outline">Back to Quizzes</a></p>
<?php include '../includes/footer.php'; ?>
