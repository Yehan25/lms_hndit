-- =====================================================
-- LMS FOR HNDIT STUDENTS - DATABASE SCHEMA (v3)
-- Import this file in MySQL Workbench or phpMyAdmin
-- (Use this for a FRESH install. If you already have the
--  database, use update_lms_hndit.sql + update_lms_hndit_v2.sql instead.)
-- =====================================================

CREATE DATABASE IF NOT EXISTS lms_hndit;
USE lms_hndit;

-- ---------------------------------------------------
-- USERS TABLE (Admin, Lecturer, Student)
-- ---------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','lecturer','student') NOT NULL DEFAULT 'student',
    reg_no VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------
-- COURSES / MODULES TABLE
-- ---------------------------------------------------
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(150) NOT NULL,
    description TEXT,
    semester VARCHAR(50) NULL,
    lecturer_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------
-- ENROLLMENTS (Student <-> Course)
-- ---------------------------------------------------
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id)
);

-- ---------------------------------------------------
-- COURSE MATERIALS
-- Both admin and lecturer can upload. material_type lets
-- videos be shown in an inline player instead of a plain link.
-- ---------------------------------------------------
CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    uploaded_by INT NULL,
    uploaded_by_role ENUM('admin','lecturer') NULL DEFAULT 'lecturer',
    title VARCHAR(150) NOT NULL,
    material_type ENUM('document','video') NOT NULL DEFAULT 'document',
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- ASSIGNMENTS
-- Both admin and lecturer can create.
-- ---------------------------------------------------
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    created_by INT NULL,
    created_by_role ENUM('admin','lecturer') DEFAULT 'lecturer',
    title VARCHAR(150) NOT NULL,
    description TEXT,
    due_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- SUBMISSIONS (Student submits work for an assignment)
-- status: 'submitted' -> student uploaded a file
--         'absent'    -> lecturer/admin marked the student absent
-- ---------------------------------------------------
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    status ENUM('submitted','absent') NOT NULL DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    grade VARCHAR(10) DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission (assignment_id, student_id)
);

-- ---------------------------------------------------
-- ANNOUNCEMENTS
-- ---------------------------------------------------
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- QUIZZES (belong to a course, created by admin or lecturer)
-- ---------------------------------------------------
CREATE TABLE quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    due_date DATETIME NOT NULL,
    created_by INT NULL,
    created_by_role ENUM('admin','lecturer') DEFAULT 'lecturer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- QUIZ QUESTIONS (multiple choice, A/B/C/D)
-- ---------------------------------------------------
CREATE TABLE quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('A','B','C','D') NOT NULL,
    marks INT NOT NULL DEFAULT 1,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- QUIZ ATTEMPTS (one per student per quiz, auto-graded on submit)
-- ---------------------------------------------------
CREATE TABLE quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    total_marks INT NOT NULL DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt (quiz_id, student_id)
);

-- ---------------------------------------------------
-- QUIZ ANSWERS
-- ---------------------------------------------------
CREATE TABLE quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option ENUM('A','B','C','D') NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- DEFAULT ADMIN ACCOUNT
-- After importing, open setup_admin.php once in your browser
-- to create the admin login using your own server's password_hash().
-- ---------------------------------------------------
INSERT INTO users (name, email, password, role) VALUES
('System Admin', 'admin@hndit.lk', 'CHANGE_ME_VIA_SETUP_SCRIPT', 'admin');

-- ---------------------------------------------------
-- NOTE ON "FORGOT PASSWORD"
-- Users who forget their password should contact an Administrator,
-- who can reset it from Admin > Manage Users > Reset Password.
-- ---------------------------------------------------
