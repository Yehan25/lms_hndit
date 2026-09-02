<?php
require_once 'config.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $reg_no = trim($_POST['reg_no']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "An account with this email already exists.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt2 = $conn->prepare("INSERT INTO users (name, email, password, role, reg_no) VALUES (?, ?, ?, 'student', ?)");
        $stmt2->bind_param("ssss", $name, $email, $hashed, $reg_no);
        if ($stmt2->execute()) {
            $success = "Registration successful! You can now log in.";
        } else {
            $error = "Something went wrong. Please try again.";
        }
        $stmt2->close();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register · HNDIT LMS</title>
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
<div class="auth-screen">
    <div class="auth-hero">
        <span class="eyebrow-tag">★ Student Registration</span>
        <h1>Join your <em>HNDIT</em> cohort.</h1>
        <p>Register with your diploma registration number to unlock course enrollment, materials, assignment submission and grade tracking.</p>

        <div class="flow-diagram">
            <div class="flow-step"><span class="flow-icon">🧾</span><span class="flow-label">Register</span></div>
            <span class="flow-arrow">→</span>
            <div class="flow-step"><span class="flow-icon">📘</span><span class="flow-label">Enroll</span></div>
            <span class="flow-arrow">→</span>
            <div class="flow-step"><span class="flow-icon">🎓</span><span class="flow-label">Graduate</span></div>
        </div>
    </div>

    <div class="auth-form-side">
        <div class="auth-box">
            <div class="logo-row">
                <div class="brand-mark" style="width:34px;height:34px;font-size:13px;">HD</div>
                <strong style="font-family:'Sora',sans-serif;font-size:16px;">HNDIT LMS</strong>
            </div>
            <h2>Create your account</h2>
            <p class="sub">Student registration only. Lecturer accounts are created by an administrator.</p>

            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

            <form method="POST">
                <label>Full name</label>
                <input type="text" name="name" placeholder="e.g. Nimal Perera" required>
                <label>HNDIT registration no.</label>
                <input type="text" name="reg_no" placeholder="e.g. HNDIT/24/045" required>
                <label>Email address</label>
                <input type="email" name="email" placeholder="you@hndit.lk" required>
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
                <button type="submit" class="btn btn-amber">Create account</button>
            </form>

            <p class="auth-footnote">Already registered? <a href="login.php">Sign in</a></p>
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
</script>
</body>
</html>
