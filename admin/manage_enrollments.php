<?php
require_once '../config.php';
require_role('admin');

$msg = '';
$searchTerm = trim((string)($_GET['search'] ?? ''));
$selectedCourseId = intval($_GET['course_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $student_id = intval($_POST['student_id']);
    $course_id = intval($_POST['course_id']);

    if ($student_id > 0 && $course_id > 0) {
        $stmt = $conn->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $student_id, $course_id);
        if ($stmt->execute()) {
            $msg = "Student enrolled successfully.";
        } else {
            $msg = "Could not enroll the student. Please try again.";
        }
        $stmt->close();
    } else {
        $msg = "Please select both a student and a course.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_enrollment'])) {
    $enrollment_id = intval($_POST['enrollment_id']);
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
    $stmt->bind_param("i", $enrollment_id);
    if ($stmt->execute()) {
        $msg = "Enrollment removed successfully.";
    } else {
        $msg = "Could not remove this enrollment.";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_lecturer'])) {
    $lecturer_id = intval($_POST['lecturer_id']);
    $course_id = intval($_POST['course_id']);

    if ($lecturer_id > 0 && $course_id > 0) {
        $stmt = $conn->prepare("UPDATE courses SET lecturer_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $lecturer_id, $course_id);
        if ($stmt->execute()) {
            $msg = "Lecturer assigned to the course successfully.";
        } else {
            $msg = "Could not assign the lecturer to the course.";
        }
        $stmt->close();
    } else {
        $msg = "Please select both a lecturer and a course.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_all_students'])) {
    $course_id = intval($_POST['bulk_course_id']);
    if ($course_id > 0) {
        $studentsResult = $conn->query("SELECT id FROM users WHERE role='student' ORDER BY id");
        $stmt = $conn->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
        $enrolledCount = 0;
        $skippedCount = 0;

        while ($student = $studentsResult->fetch_assoc()) {
            $stmt->bind_param("ii", $student['id'], $course_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $enrolledCount++;
                } else {
                    $skippedCount++;
                }
            }
            $stmt->reset();
        }

        $stmt->close();
        $msg = "Enrolled $enrolledCount students into the selected course.";
        if ($skippedCount > 0) {
            $msg .= " $skippedCount students were already enrolled.";
        }
    } else {
        $msg = "Please select a course for bulk enrollment.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student_all_courses'])) {
    $student_id = intval($_POST['bulk_student_id']);
    if ($student_id > 0) {
        $coursesResult = $conn->query("SELECT id FROM courses ORDER BY id");
        $stmt = $conn->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
        $enrolledCount = 0;
        $skippedCount = 0;

        while ($course = $coursesResult->fetch_assoc()) {
            $stmt->bind_param("ii", $student_id, $course['id']);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $enrolledCount++;
                } else {
                    $skippedCount++;
                }
            }
            $stmt->reset();
        }

        $stmt->close();
        $msg = "Enrolled the selected student into $enrolledCount courses.";
        if ($skippedCount > 0) {
            $msg .= " $skippedCount courses were already enrolled.";
        }
    } else {
        $msg = "Please select a student for bulk enrollment.";
    }
}

$studentsResult = $conn->query("SELECT id, name, email, reg_no FROM users WHERE role='student' ORDER BY name");
$students = [];
while ($student = $studentsResult->fetch_assoc()) {
    $students[] = $student;
}

$lecturersResult = $conn->query("SELECT id, name, email FROM users WHERE role='lecturer' ORDER BY name");
$lecturers = [];
while ($lecturer = $lecturersResult->fetch_assoc()) {
    $lecturers[] = $lecturer;
}

$coursesResult = $conn->query("SELECT c.id, c.course_code, c.course_name, u.name AS lecturer_name FROM courses c LEFT JOIN users u ON c.lecturer_id = u.id ORDER BY c.course_code");
$courses = [];
while ($course = $coursesResult->fetch_assoc()) {
    $courses[] = $course;
}

$enrollmentSql = "SELECT e.id, s.name AS student_name, s.reg_no, c.course_code, c.course_name
    FROM enrollments e
    JOIN users s ON e.student_id = s.id
    JOIN courses c ON e.course_id = c.id
    WHERE 1=1";
$enrollmentTypes = '';
$enrollmentParams = [];

if ($searchTerm !== '') {
    $searchPattern = '%' . $searchTerm . '%';
    $enrollmentSql .= " AND (s.name LIKE ? OR s.reg_no LIKE ? OR c.course_code LIKE ? OR c.course_name LIKE ?)";
    $enrollmentTypes .= 'ssss';
    $enrollmentParams[] = $searchPattern;
    $enrollmentParams[] = $searchPattern;
    $enrollmentParams[] = $searchPattern;
    $enrollmentParams[] = $searchPattern;
}

if ($selectedCourseId > 0) {
    $enrollmentSql .= " AND c.id = ?";
    $enrollmentTypes .= 'i';
    $enrollmentParams[] = $selectedCourseId;
}

$enrollmentSql .= " ORDER BY s.name, c.course_code";
$enrollmentStmt = $conn->prepare($enrollmentSql);
if (!empty($enrollmentParams)) {
    $bindValues = [$enrollmentTypes];
    foreach ($enrollmentParams as $key => $value) {
        $bindValues[] = &$enrollmentParams[$key];
    }
    call_user_func_array([$enrollmentStmt, 'bind_param'], $bindValues);
}
$enrollmentStmt->execute();
$enrollments = $enrollmentStmt->get_result();

$pageTitle = 'Manage Enrollments';
include '../includes/header.php';
?>
<h2>Manage Enrollments</h2>

<?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="card">
    <h3>Enroll Student to a Course</h3>
    <form method="POST">
        <label>Select Student</label>
        <select name="student_id" required>
            <option value="">-- Select Student --</option>
            <?php foreach ($students as $student): ?>
                <option value="<?php echo intval($student['id']); ?>">
                    <?php echo htmlspecialchars($student['name'] . ' (' . ($student['reg_no'] ?: 'No reg no') . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Select Course</label>
        <select name="course_id" required>
            <option value="">-- Select Course --</option>
            <?php foreach ($courses as $course): ?>
                <option value="<?php echo intval($course['id']); ?>">
                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name'] . ' (' . ($course['lecturer_name'] ?: 'Unassigned') . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="enroll_student" class="btn">Enroll Student</button>
    </form>
</div>

<div class="card">
    <h3>Assign Lecturer to a Course</h3>
    <form method="POST">
        <label>Select Lecturer</label>
        <select name="lecturer_id" required>
            <option value="">-- Select Lecturer --</option>
            <?php foreach ($lecturers as $lecturer): ?>
                <option value="<?php echo intval($lecturer['id']); ?>">
                    <?php echo htmlspecialchars($lecturer['name'] . ' (' . ($lecturer['email'] ?: 'No email') . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Select Course</label>
        <select name="course_id" required>
            <option value="">-- Select Course --</option>
            <?php foreach ($courses as $course): ?>
                <option value="<?php echo intval($course['id']); ?>">
                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="assign_lecturer" class="btn">Assign Lecturer</button>
    </form>
</div>

<div class="card">
    <h3>Quick Bulk Enrollment</h3>
    <form method="POST" style="margin-bottom: 1rem;">
        <label>Enroll all students into one course</label>
        <select name="bulk_course_id" required>
            <option value="">-- Select Course --</option>
            <?php foreach ($courses as $course): ?>
                <option value="<?php echo intval($course['id']); ?>">
                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="enroll_all_students" class="btn">Enroll All Students</button>
    </form>

    <form method="POST">
        <label>Enroll one student into all courses</label>
        <select name="bulk_student_id" required>
            <option value="">-- Select Student --</option>
            <?php foreach ($students as $student): ?>
                <option value="<?php echo intval($student['id']); ?>">
                    <?php echo htmlspecialchars($student['name'] . ' (' . ($student['reg_no'] ?: 'No reg no') . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="enroll_student_all_courses" class="btn">Enroll Student in All Courses</button>
    </form>
</div>

<div class="card">
    <h3>Current Enrollments</h3>
    <form method="GET" class="filters-bar">
        <div class="filter-field">
            <label for="enrollment-search">Search enrollments</label>
            <input
                type="search"
                id="enrollment-search"
                name="search"
                value="<?php echo htmlspecialchars($searchTerm); ?>"
                placeholder="Student name, registration no. or course"
            >
        </div>
        <div class="filter-field">
            <label for="enrollment-course">Filter by course</label>
            <select id="enrollment-course" name="course_id">
                <option value="0">All Courses</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo intval($course['id']); ?>" <?php echo $selectedCourseId === intval($course['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn">Search / Filter</button>
            <?php if ($searchTerm !== '' || $selectedCourseId > 0): ?>
                <a href="manage_enrollments.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </div>
    </form>
    <p class="table-meta">
        <?php echo $enrollments->num_rows; ?> enrollment<?php echo $enrollments->num_rows === 1 ? '' : 's'; ?> found
    </p>
    <table>
        <tr><th>Student</th><th>Registration No.</th><th>Course</th><th>Action</th></tr>
        <?php while ($row = $enrollments->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['student_name']); ?></td>
            <td><?php echo htmlspecialchars($row['reg_no'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($row['course_code'] . ' - ' . $row['course_name']); ?></td>
            <td>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="enrollment_id" value="<?php echo intval($row['id']); ?>">
                    <button type="submit" name="remove_enrollment" class="btn btn-danger" onclick="return confirm('Remove this enrollment?')">Remove</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php if ($enrollments->num_rows === 0): ?>
        <div class="empty-state">
            <?php echo ($searchTerm !== '' || $selectedCourseId > 0)
                ? 'No enrollments match the selected search or filter.'
                : 'No enrollments have been created yet.'; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
