<?php
require_once '../config.php';
require_role('lecturer');

$assignment_id = intval($_GET['assignment_id'] ?? 0);
$student_id = intval($_GET['student_id'] ?? 0);

// Verify the assignment's course belongs to this lecturer
$stmt = $conn->prepare("
    SELECT a.id FROM assignments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.id = ? AND c.lecturer_id = ?
");
$stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    die("Assignment not found or access denied.");
}

// Verify the student is actually enrolled in that course
$stmt2 = $conn->prepare("
    SELECT e.id FROM enrollments e
    JOIN assignments a ON a.course_id = e.course_id
    WHERE a.id = ? AND e.student_id = ?
");
$stmt2->bind_param("ii", $assignment_id, $student_id);
$stmt2->execute();
if (!$stmt2->get_result()->fetch_assoc()) {
    die("Student is not enrolled in this course.");
}

// Insert (or update) an absent submission row -- no file, status = absent
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
