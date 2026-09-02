# LMS HNDIT — Learning Management System

> ⚠️ **Project Status: Work in Progress (Ongoing Development)**
> This project is currently under active development as part of an HNDIT academic assignment. Features are being added and refined continuously, so parts of the system may be incomplete, unstyled, or subject to change.

A web-based **Learning Management System (LMS)** built with **PHP** and **MySQL**, designed to support three types of users — **Admin**, **Lecturer**, and **Student** — in managing courses, learning materials, assignments, quizzes, and grades.

## 📌 Features

### Admin
- Manage users (students & lecturers)
- Manage courses and enrollments
- Create and manage assignments and quizzes
- Grade student submissions
- View quiz results and submissions
- Mark attendance / absences

### Lecturer
- Dashboard with course overview
- Upload course materials (documents & videos)
- Create assignments and quizzes
- Grade submissions and view results
- Post announcements to students
- Mark student attendance

### Student
- Enroll in available courses
- View enrolled courses and materials
- Submit assignments
- Take quizzes and view results
- View grades

## 🛠️ Tech Stack

- **Backend:** PHP (procedural, using `mysqli`)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML, CSS
- **Local server environment:** XAMPP (Apache + MySQL)

## 📂 Project Structure

```
lms_hndit/
├── admin/          # Admin panel pages
├── lecturer/        # Lecturer panel pages
├── student/          # Student panel pages
├── includes/         # Shared header/footer components
├── assets/           # CSS and images
├── uploads/           # Uploaded materials & submissions
├── config.php         # Database connection & helper functions
├── database.sql       # Database schema
├── index.php           # Landing page
├── login.php / register.php / logout.php
└── setup_admin.php      # Initial admin account setup
```

## 🚀 Getting Started (Local Setup)

1. **Install XAMPP** (or any Apache + MySQL + PHP stack).
2. Clone this repository into your `htdocs` folder:
   ```bash
   git clone https://github.com/Yehan25/lms_hndit.git
   ```
3. Start **Apache** and **MySQL** from the XAMPP control panel.
4. Create a database named `lms_hndit` and import the schema:
   ```bash
   mysql -u root -p lms_hndit < database.sql
   ```
5. Update database credentials in `config.php` if needed (default: `root` / no password).
6. Visit `http://localhost/lms_hndit/setup_admin.php` to create the first admin account.
7. Open `http://localhost/lms_hndit/` in your browser to start using the system.

## 📋 Roadmap / TODO

- [ ] UI/UX improvements and consistent styling across all pages
- [ ] Improve validation and error handling
- [ ] Add proper attendance tracking module
- [ ] Clean up temporary/test files (`tmp_check_materials.php`, `tmp_upload_test.php`, `phpinfo.php`)
- [ ] Add unit tests
- [ ] Improve security (input sanitization, password hashing checks, CSRF protection)

## 🤝 Contributing

This is currently a solo academic project, but suggestions and feedback are welcome via Issues.

## 📄 License

This project is for academic purposes as part of the HNDIT program. License to be decided.

---
*Last updated: September 2026 — this README will be updated as the project progresses.*
