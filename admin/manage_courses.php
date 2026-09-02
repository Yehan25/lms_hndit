<?php
require_once '../config.php';
require_role('admin');

$msg = '';
$editCourseId = 0;
$editCourse = null;
$selectedSemester = trim($_GET['semester'] ?? '');
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'id';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Add course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $code = trim($_POST['course_code']);
    $name = trim($_POST['course_name']);
    $desc = trim($_POST['description']);
    $semester = trim($_POST['semester']) ?: null;
    $lecturerIds = isset($_POST['lecturer_ids']) && is_array($_POST['lecturer_ids']) ? array_map('intval', $_POST['lecturer_ids']) : [];
    $primaryLecturerId = count($lecturerIds) ? $lecturerIds[0] : null;

    $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, description, semester, lecturer_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $code, $name, $desc, $semester, $primaryLecturerId);
    if ($stmt->execute()) {
        $courseId = $conn->insert_id;
        if (!empty($lecturerIds)) {
            $insertStmt = $conn->prepare("INSERT IGNORE INTO course_lecturers (course_id, lecturer_id) VALUES (?, ?)");
            foreach ($lecturerIds as $lid) {
                $insertStmt->bind_param("ii", $courseId, $lid);
                $insertStmt->execute();
            }
            $insertStmt->close();
        }
        $msg = "Course added successfully.";
    } else {
        $msg = "Error: could not add course (code may already exist). " . $stmt->error;
    }
    $stmt->close();
}

// Update course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    $id = intval($_POST['course_id']);
    $code = trim($_POST['course_code']);
    $name = trim($_POST['course_name']);
    $desc = trim($_POST['description']);
    $semester = trim($_POST['semester']) ?: null;
    $lecturerIds = isset($_POST['lecturer_ids']) && is_array($_POST['lecturer_ids']) ? array_map('intval', $_POST['lecturer_ids']) : [];
    $primaryLecturerId = count($lecturerIds) ? $lecturerIds[0] : null;

    $stmt = $conn->prepare("UPDATE courses SET course_code = ?, course_name = ?, description = ?, semester = ?, lecturer_id = ? WHERE id = ?");
    $stmt->bind_param("ssssii", $code, $name, $desc, $semester, $primaryLecturerId, $id);
    if ($stmt->execute()) {
        $conn->query("DELETE FROM course_lecturers WHERE course_id = $id");
        if (!empty($lecturerIds)) {
            $insertStmt = $conn->prepare("INSERT IGNORE INTO course_lecturers (course_id, lecturer_id) VALUES (?, ?)");
            foreach ($lecturerIds as $lid) {
                $insertStmt->bind_param("ii", $id, $lid);
                $insertStmt->execute();
            }
            $insertStmt->close();
        }
        $msg = "Course updated successfully.";
    } else {
        $msg = "Error: could not update course. " . $stmt->error;
    }
    $stmt->close();
}

// Delete course
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM courses WHERE id = $id");
    header("Location: manage_courses.php");
    exit();
}

// Edit course
if (isset($_GET['edit'])) {
    $editCourseId = intval($_GET['edit']);
    $editResult = $conn->query("SELECT id, course_code, course_name, description, semester, lecturer_id FROM courses WHERE id = $editCourseId");
    if ($editResult && $editResult->num_rows > 0) {
        $editCourse = $editResult->fetch_assoc();
        $lecturerIds = [];
        $lecturerRes = $conn->query("SELECT lecturer_id FROM course_lecturers WHERE course_id = $editCourseId ORDER BY lecturer_id");
        while ($row = $lecturerRes->fetch_assoc()) {
            $lecturerIds[] = $row['lecturer_id'];
        }
        $editCourse['lecturer_ids'] = $lecturerIds;
    }
}

$lecturers = $conn->query("SELECT id, name FROM users WHERE role='lecturer'");

$allowedSortColumns = ['id', 'course_code', 'course_name', 'semester'];
if (!in_array($sortBy, $allowedSortColumns, true)) {
    $sortBy = 'id';
}

$orderByColumn = $sortBy === 'lecturer_name' ? 'u.name' : 'c.' . $sortBy;

$courseSql = "
    SELECT c.* 
    FROM courses c";
if ($selectedSemester !== '') {
    $courseSql .= " WHERE c.semester = ?";
    $courseStmt = $conn->prepare($courseSql . " ORDER BY " . $orderByColumn . " " . $sortOrder);
    $courseStmt->bind_param("s", $selectedSemester);
    $courseStmt->execute();
    $coursesResult = $courseStmt->get_result();
} else {
    $courseSql .= " ORDER BY " . $orderByColumn . " " . $sortOrder;
    $coursesResult = $conn->query($courseSql);
}

$semesterGroups = [];
if ($coursesResult) {
    while ($course = $coursesResult->fetch_assoc()) {
        $semesterKey = trim((string)($course['semester'] ?? '')) !== '' ? $course['semester'] : 'Unassigned';
        $semesterGroups[$semesterKey][] = $course;
    }
}

$pageTitle = 'Manage Courses';
include '../includes/header.php';
?>
<h2>Manage Courses</h2>

<?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="card">
    <h3><?php echo $editCourse ? 'Edit Course' : 'Add New Course'; ?></h3>
    <form method="POST">
        <?php if ($editCourse): ?>
            <input type="hidden" name="course_id" value="<?php echo intval($editCourse['id']); ?>">
        <?php endif; ?>
        <label>Course Code</label>
        <input type="text" name="course_code" placeholder="e.g. IT3205" value="<?php echo htmlspecialchars($editCourse['course_code'] ?? ''); ?>" required>
        <label>Course Name</label>
        <input type="text" name="course_name" placeholder="e.g. Web Application Development" value="<?php echo htmlspecialchars($editCourse['course_name'] ?? ''); ?>" required>
        <label>Description</label>
        <textarea name="description" rows="3"><?php echo htmlspecialchars($editCourse['description'] ?? ''); ?></textarea>
        <label>Semester</label>
        <input type="text" name="semester" list="semester-options" placeholder="e.g. Semester 1" value="<?php echo htmlspecialchars($editCourse['semester'] ?? ''); ?>">
        <datalist id="semester-options">
            <option value="Semester 1"></option>
            <option value="Semester 2"></option>
            <option value="Semester 3"></option>
            <option value="Semester 4"></option>
            <option value="Semester 5"></option>
            <option value="Semester 6"></option>
            <option value="Semester 7"></option>
            <option value="Semester 8"></option>
            <option value="Year 1 Semester 1"></option>
            <option value="Year 1 Semester 2"></option>
            <option value="Year 2 Semester 1"></option>
            <option value="Year 2 Semester 2"></option>
        </datalist>
        <label>Assign Lecturer(s)</label>
        <select name="lecturer_ids[]" multiple size="4" style="min-height: 140px;">
            <?php while ($l = $lecturers->fetch_assoc()): ?>
                <option value="<?php echo $l['id']; ?>" <?php echo in_array($l['id'], $editCourse['lecturer_ids'] ?? [], true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($l['name']); ?></option>
            <?php endwhile; ?>
        </select>
        <small style="display:block; margin-top:8px; color:var(--text-muted);">Hold Ctrl/Cmd to select multiple lecturers.</small>
        <?php if ($editCourse): ?>
            <button type="submit" name="update_course" class="btn">Update Course</button>
            <a href="manage_courses.php" class="btn btn-outline">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add_course" class="btn">Add Course</button>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h3>All Courses</h3>
    <form method="GET" class="filters">
        <label>Filter by Semester</label>
        <input type="text" name="semester" list="semester-options" placeholder="All Semesters" value="<?php echo htmlspecialchars($selectedSemester); ?>">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortBy); ?>">
        <input type="hidden" name="order" value="<?php echo htmlspecialchars($sortOrder === 'ASC' ? 'asc' : 'desc'); ?>">
        <button type="submit" class="btn">Filter</button>
        <a href="manage_courses.php" class="btn btn-outline">Reset</a>
    </form>

    <div class="semester-card-grid">
        <?php if (!empty($semesterGroups)): ?>
            <?php foreach ($semesterGroups as $semesterName => $coursesForSemester): ?>
                <div class="semester-card">
                    <div class="semester-card__header">
                        <span class="semester-chip">Semester</span>
                        <h4><?php echo htmlspecialchars($semesterName); ?></h4>
                        <span class="semester-count"><?php echo count($coursesForSemester); ?> course<?php echo count($coursesForSemester) === 1 ? '' : 's'; ?></span>
                    </div>

                    <div class="semester-course-list">
                        <?php foreach ($coursesForSemester as $course): ?>
                            <?php $lecturerNames = get_course_lecturer_names($conn, $course['id']); $lecturerText = !empty($lecturerNames) ? implode(', ', $lecturerNames) : ($course['lecturer_id'] ? 'Assigned' : 'Unassigned'); ?>
                            <div class="semester-course-item">
                                <div class="semester-course-main">
                                    <div class="course-code-pill"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($course['course_name']); ?></strong>
                                        <div class="semester-course-meta"><?php echo htmlspecialchars($lecturerText); ?></div>
                                    </div>
                                </div>
                                <div class="semester-course-actions">
                                    <a href="?edit=<?php echo $course['id']; ?>" class="btn btn-outline">Edit</a>
                                    <a href="?delete=<?php echo $course['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this course?')">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">No courses found for this semester.</div>
        <?php endif; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
