<?php
// =====================================================
// ONE-TIME ADMIN SETUP
// Visit this file once in your browser: 
//   http://localhost/lms_hndit/setup_admin.php
// It safely (re)sets the admin password using YOUR server's
// own password_hash(), so login is guaranteed to work.
// Delete this file afterwards (or leave it, it's safe to re-run).
// =====================================================
require_once 'config.php';

$default_password = 'admin123';
$hash = password_hash($default_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = 'admin@hndit.lk' AND role = 'admin'");
$stmt->bind_param("s", $hash);
$stmt->execute();

$message = '';
if ($stmt->affected_rows > 0) {
    $message = "Admin password set successfully.";
} else {
    // Admin row didn't exist yet (e.g. deleted) - create it
    $name = 'System Admin';
    $email = 'admin@hndit.lk';
    $stmt2 = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt2->bind_param("sss", $name, $email, $hash);
    $stmt2->execute();
    $message = "Admin account created successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Setup · HNDIT LMS</title>
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
<div class="auth-form-side" style="min-height: calc(100vh - 64px);">
    <div class="auth-box">
        <div class="logo-row">
            <div class="brand-mark" style="width:34px;height:34px;font-size:13px;">HD</div>
            <strong style="font-family:'Sora',sans-serif;font-size:16px;">HNDIT LMS</strong>
        </div>
        <h2>Admin Setup</h2>
        <p class="sub">One-time admin account initialization.</p>

        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>

        <p style="font-size:13.5px; color: var(--text-muted); line-height:1.6;">
            Login with:<br>
            <b style="color: var(--text);">Email:</b> admin@hndit.lk<br>
            <b style="color: var(--text);">Password:</b> admin123
        </p>

        <a href="login.php" class="btn btn-amber" style="width:100%; justify-content:center; margin-top:6px;">Go to Login</a>

        <div class="demo-note">For security, delete <code class="mono">setup_admin.php</code> once you've logged in and changed your password.</div>
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
