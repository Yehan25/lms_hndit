<?php
require_once 'config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            header("Location: " . $user['role'] . "/dashboard.php");
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "No account found with that email.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · HNDIT LMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<script>
(function () {
    const storedTheme = localStorage.getItem('lms-theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = storedTheme || (prefersDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
})();
</script>
<link rel="stylesheet" href="/lms_hndit/assets/css/style.css?v=4">
</head>
<body>
<div class="theme-toggle-shell">
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Switch color theme" aria-pressed="false">
        <span class="theme-toggle__icon">🌙</span>
        <span class="theme-toggle__label">Dark mode</span>
    </button>
</div>
<div class="auth-screen auth-screen--v2">
    <span class="auth-orb auth-orb--1" aria-hidden="true"></span>
    <span class="auth-orb auth-orb--2" aria-hidden="true"></span>
    <span class="auth-orb auth-orb--3" aria-hidden="true"></span>

    <div class="auth-hero">
        <div class="auth-hero__brand" aria-label="HNDIT institution branding">
            <img src="/lms_hndit/assets/images/hndit-logo.jpg" alt="HNDIT logo" class="auth-hero__logo">
        </div>
        <span class="eyebrow-tag">★ HNDIT · Higher National Diploma in IT</span>
        <h1>Your diploma, <em>one dashboard</em> away.</h1>
        <p>Course materials, assignments, submissions and grades for every HNDIT module — organized in one place for students, lecturers and administrators.</p>

        <div class="flow-diagram">
            <div class="flow-step"><span class="flow-icon">📘</span><span class="flow-label">Enroll</span></div>
            <span class="flow-arrow">→</span>
            <div class="flow-step"><span class="flow-icon">📂</span><span class="flow-label">Materials</span></div>
            <span class="flow-arrow">→</span>
            <div class="flow-step"><span class="flow-icon">📝</span><span class="flow-label">Submit</span></div>
            <span class="flow-arrow">→</span>
            <div class="flow-step"><span class="flow-icon">🎓</span><span class="flow-label">Grade</span></div>
        </div>
    </div>

    <div class="auth-form-side">
        <div class="auth-box auth-box--glass">
            <div class="logo-row">
                <img src="/lms_hndit/assets/images/hndit-logo.jpg" alt="HNDIT logo" class="auth-box__logo">
                <strong style="font-family:'Sora',sans-serif;font-size:16px;">HNDIT LMS</strong>
            </div>
            <h2>Welcome back</h2>
            <p class="sub">Sign in to access your dashboard.</p>

            <?php if ($error): ?>
                <div class="alert alert-error alert--shake"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <label for="email">Email address</label>
                <div class="input-group">
                    <span class="input-icon" aria-hidden="true">✉️</span>
                    <input type="email" name="email" id="email" placeholder="you@hndit.lk" required>
                </div>

                <label for="password">Password</label>
                <div class="input-group">
                    <span class="input-icon" aria-hidden="true">🔒</span>
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                    <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password" aria-pressed="false" tabindex="-1">👁️</button>
                </div>

                <button type="submit" class="btn btn-amber btn--glow">Sign in</button>
            </form>

            <p class="auth-footnote">No account? <a href="register.php">Register as a student</a></p>
            <div class="demo-note">Forgot your password? Passwords are not shown here for security.
            Please contact your <b>Administrator</b> — they can reset your password from
            Admin&nbsp;&rarr;&nbsp;Manage&nbsp;Users&nbsp;&rarr;&nbsp;Reset&nbsp;Password.</div>
        </div>
    </div>
</div>
<script>
(function () {
    const toggle = document.getElementById('themeToggle');
    const root = document.documentElement;
    const updateButton = (theme) => {
        if (toggle) {
            const icon = toggle.querySelector('.theme-toggle__icon');
            const label = toggle.querySelector('.theme-toggle__label');
            if (icon) icon.textContent = theme === 'dark' ? '☀️' : '🌙';
            if (label) label.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
            toggle.setAttribute('aria-pressed', String(theme === 'dark'));
        }
    };

    const currentTheme = root.getAttribute('data-theme') || 'light';
    updateButton(currentTheme);

    if (toggle) {
        toggle.addEventListener('click', () => {
            const nextTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', nextTheme);
            localStorage.setItem('lms-theme', nextTheme);
            updateButton(nextTheme);
        });
    }
})();

// Purely presentational: toggles the visibility of the password field.
// Does not touch the input's name/id, so backend/session logic is unaffected.
(function () {
    const pwToggle = document.getElementById('passwordToggle');
    const pwInput = document.getElementById('password');
    if (pwToggle && pwInput) {
        pwToggle.addEventListener('click', () => {
            const isHidden = pwInput.getAttribute('type') === 'password';
            pwInput.setAttribute('type', isHidden ? 'text' : 'password');
            pwToggle.textContent = isHidden ? '🙈' : '👁️';
            pwToggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            pwToggle.setAttribute('aria-pressed', String(isHidden));
        });
    }
})();
</script>
</body>
</html>
