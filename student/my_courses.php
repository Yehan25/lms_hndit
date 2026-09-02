<?php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];

$courses = $conn->query("
    SELECT c.* FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = $student_id
    ORDER BY c.course_code
");

$pageTitle = 'My Courses';
include '../includes/header.php';
?>
<h2>My Courses</h2>

<div class="card">
    <table>
        <tr><th>Code</th><th>Course Name</th><th>Lecturer</th><th>Actions</th></tr>
        <?php while ($c = $courses->fetch_assoc()): ?>
        <?php $lecturerNames = get_course_lecturer_names($conn, $c['id']); $lecturerText = !empty($lecturerNames) ? implode(', ', $lecturerNames) : 'Unassigned'; ?>
        <tr>
            <td class="course-code"><?php echo htmlspecialchars($c['course_code']); ?></td>
            <td><?php echo htmlspecialchars($c['course_name']); ?></td>
            <td><?php echo htmlspecialchars($lecturerText); ?></td>
            <td>
                <a href="view_materials.php?course_id=<?php echo $c['id']; ?>" class="btn btn-outline">Materials</a>
                <a href="view_assignments.php?course_id=<?php echo $c['id']; ?>" class="btn btn-outline">Assignments</a>
                <a href="view_quizzes.php?course_id=<?php echo $c['id']; ?>" class="btn btn-outline">Quizzes</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($courses->num_rows === 0): ?>
        <div class="empty-state">You are not enrolled in any course yet. <a href="enroll.php">Enroll now &rarr;</a></div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
