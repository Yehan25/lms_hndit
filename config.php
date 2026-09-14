<?php
// =====================================================
// DATABASE CONNECTION CONFIG
// Update these if your XAMPP MySQL settings differ
// =====================================================
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');        // default XAMPP MySQL password is empty
define('DB_NAME', 'lms_hndit');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$semesterCheck = $conn->query("SHOW COLUMNS FROM courses LIKE 'semester'");
if ($semesterCheck && $semesterCheck->num_rows === 0) {
    $conn->query("ALTER TABLE courses ADD COLUMN semester VARCHAR(50) NULL AFTER description");
}

$assignmentFileCol = $conn->query("SHOW COLUMNS FROM assignments LIKE 'file_path'");
if ($assignmentFileCol && $assignmentFileCol->num_rows === 0) {
    $conn->query("ALTER TABLE assignments ADD COLUMN file_path VARCHAR(255) NULL AFTER due_date");
}

// App limit for video uploads (50 MB)
define('MAX_VIDEO_UPLOAD_BYTES', 50 * 1024 * 1024);

define('MAX_ASSIGNMENT_FILE_BYTES', 20 * 1024 * 1024);

function php_size_to_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $num = (int)$val;
    switch($last) {
        case 'g': $num *= 1024;
        case 'm': $num *= 1024;
        case 'k': $num *= 1024;
    }
    return $num;
}

$conn->query("CREATE TABLE IF NOT EXISTS course_lecturers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_course_lecturer (course_id, lecturer_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
)");

function get_course_lecturer_names($conn, $course_id) {
    $stmt = $conn->prepare("SELECT u.name FROM course_lecturers cl JOIN users u ON u.id = cl.lecturer_id WHERE cl.course_id = ? ORDER BY u.name");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $names = [];
    while ($row = $result->fetch_assoc()) {
        $names[] = $row['name'];
    }
    $stmt->close();

    if (empty($names)) {
        $fallback = $conn->query("SELECT lecturer_id FROM courses WHERE id = " . intval($course_id))->fetch_assoc()['lecturer_id'] ?? null;
        if ($fallback) {
            $nameResult = $conn->query("SELECT name FROM users WHERE id = " . intval($fallback) . " LIMIT 1");
            if ($nameResult && $nameResult->num_rows > 0) {
                $nameRow = $nameResult->fetch_assoc();
                $names[] = $nameRow['name'];
            }
        }
    }

    return $names;
}

// Helper: redirect if not logged in
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /lms_hndit/login.php");
        exit();
    }
}

// Helper: restrict page to a specific role
function require_role($role) {
    require_login();
    if ($_SESSION['role'] !== $role) {
        header("Location: /lms_hndit/login.php");
        exit();
    }
}

// Helper: restrict page to a set of roles, e.g. require_any_role(['admin','lecturer'])
function require_any_role($roles) {
    require_login();
    if (!in_array($_SESSION['role'], $roles)) {
        header("Location: /lms_hndit/login.php");
        exit();
    }
}

// Helper: guess whether an uploaded file is a video or a regular document,
// based on its extension, so it can be embedded with a <video> player.
function detect_resource_type($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $videoExts = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
    return in_array($ext, $videoExts) ? 'video' : 'document';
}

// Helper: make sure an upload directory exists before we try to save into it.
// This is the #1 cause of "upload failed" errors on a fresh XAMPP install,
// since empty folders are sometimes not copied/extracted properly.
function ensure_upload_dir($path) {
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function delete_submission_file($file_path) {
    $relative_path = ltrim((string)$file_path, '/\\');
    if ($relative_path === '' || strpos($relative_path, 'uploads/submissions/') !== 0) {
        return;
    }

    $physical_path = __DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
    if (is_file($physical_path)) {
        unlink($physical_path);
    }
}

function sanitize_upload_filename($name) {
    $name = pathinfo($name, PATHINFO_FILENAME);
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    return $name ?: 'file';
}

function build_public_file_url($file_path) {
    $normalized = ltrim((string)$file_path, '/');
    if ($normalized === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $normalized)) {
        return $normalized;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/lms_hndit/' . $normalized;
}

function assignment_file_preview_url($file_path) {
    $publicUrl = build_public_file_url($file_path);
    if ($publicUrl === '') {
        return '';
    }
    return 'https://docs.google.com/gview?embedded=true&url=' . urlencode($publicUrl);
}

function assignment_supports_inline_preview($file_path) {
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($ext, ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'], true);
}
?>
