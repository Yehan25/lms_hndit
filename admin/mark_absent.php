<?php
require_once '../config.php';
require_role('admin');

$assignment_id = intval($_GET['assignment_id'] ?? 0);
$student_id = intval($_GET['student_id'] ?? 0);

$stmt = $conn->prepare("SELECT id, course_id FROM assignments WHERE id = ?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
if (!$assignment) { die("Assignment not found."); }

$stmt2 = $conn->prepare("SELECT id FROM enrollments WHERE course_id = ? AND student_id = ?");
$stmt2->bind_param("ii", $assignment['course_id'], $student_id);
$stmt2->execute();
if (!$stmt2->get_result()->fetch_assoc()) {
    die("Student is not enrolled in this course.");
}

$stmt3 = $conn->prepare("
    INSERT INTO submissions (assignment_id, student_id, file_path, status)
    VALUES (?, ?, NULL, 'absent')
    ON DUPLICATE KEY UPDATE status = 'absent', file_path = NULL
");
$stmt3->bind_param("ii", $assignment_id, $student_id);
$stmt3->execute();

header("Location: view_submissions.php?assignment_id=" . $assignment_id);
exit();
?>
