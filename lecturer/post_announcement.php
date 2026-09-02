<?php
require_once '../config.php';
require_role('lecturer');

$course_id = intval($_GET['course_id'] ?? 0);
$msg = '';

$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND lecturer_id = ?");
$stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) { die("Course not found or access denied."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $stmt2 = $conn->prepare("INSERT INTO announcements (course_id, title, message) VALUES (?, ?, ?)");
    $stmt2->bind_param("iss", $course_id, $title, $message);
    $stmt2->execute();
    $msg = "Announcement posted.";
}

$announcements = $conn->query("SELECT * FROM announcements WHERE course_id = $course_id ORDER BY posted_at DESC");

$pageTitle = 'Announcements';
include '../includes/header.php';
?>
<h2>Announcements - <?php echo htmlspecialchars($course['course_name']); ?></h2>

<?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="card">
    <h3>Post Announcement</h3>
    <form method="POST">
        <label>Title</label>
        <input type="text" name="title" required>
        <label>Message</label>
        <textarea name="message" rows="4" required></textarea>
        <button type="submit" class="btn">Post</button>
    </form>
</div>

<div class="card">
    <h3>Past Announcements</h3>
    <?php while ($a = $announcements->fetch_assoc()): ?>
        <div style="border-bottom:1px solid #eee; padding:10px 0;">
            <strong><?php echo htmlspecialchars($a['title']); ?></strong>
            <p><?php echo nl2br(htmlspecialchars($a['message'])); ?></p>
            <small style="color:#888;"><?php echo $a['posted_at']; ?></small>
        </div>
    <?php endwhile; ?>
</div>
<a href="dashboard.php" class="btn">Back to Dashboard</a>
<?php include '../includes/footer.php'; ?>
