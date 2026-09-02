<?php
require_once '../config.php';
require_role('admin');

$msg = '';
$editUser = null;

// Add lecturer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lecturer'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'lecturer')");
    $stmt->bind_param("sss", $name, $email, $password);
    if ($stmt->execute()) {
        $msg = "Lecturer account created.";
    } else {
        $msg = "Error: email may already be in use.";
    }
    $stmt->close();
}

// Edit an existing user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $reg_no = trim($_POST['reg_no']);
    $role = in_array($_POST['role'], ['lecturer', 'student'], true) ? $_POST['role'] : 'student';

    if ($user_id > 0 && $name !== '' && $email !== '') {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $msg = "Error: email already exists for another user.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, reg_no = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $email, $reg_no, $role, $user_id);
            if ($stmt->execute()) {
                $msg = "User details updated successfully.";
            } else {
                $msg = "Error updating user details.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    } else {
        $msg = "Error: name and email are required.";
    }
}

// Reset a user's password (this is the "Forgot Password" flow -- handled by an admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $target_id = intval($_POST['user_id']);
    $new_password = $_POST['new_password'];

    if (strlen($new_password) < 4) {
        $msg = "Error: new password must be at least 4 characters.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $target_id);
        $stmt->execute();
        $stmt->close();
        $msg = "Password reset successfully. Share the new password with the user securely.";
    }
}

// Delete user
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE id = $id AND role != 'admin'");
    header("Location: manage_users.php");
    exit();
}

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $editUser = $edit_result->fetch_assoc();
    }
    $edit_stmt->close();
}

$users = $conn->query("SELECT * FROM users ORDER BY role, name");

$pageTitle = 'Manage Users';
include '../includes/header.php';
?>
<h2>Manage Users</h2>

<?php if ($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<?php if ($editUser): ?>
<div class="card">
    <h3>Edit User Details</h3>
    <form method="POST">
        <input type="hidden" name="user_id" value="<?php echo intval($editUser['id']); ?>">
        <label>Full Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($editUser['name']); ?>" required>
        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($editUser['email']); ?>" required>
        <label>Registration No.</label>
        <input type="text" name="reg_no" value="<?php echo htmlspecialchars($editUser['reg_no'] ?? ''); ?>">
        <label>Role</label>
        <select name="role">
            <option value="student" <?php echo ($editUser['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
            <option value="lecturer" <?php echo ($editUser['role'] === 'lecturer') ? 'selected' : ''; ?>>Lecturer</option>
        </select>
        <button type="submit" name="edit_user" class="btn">Save Changes</button>
        <a href="manage_users.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php else: ?>
<div class="card">
    <h3>Add Lecturer Account</h3>
    <form method="POST">
        <label>Full Name</label>
        <input type="text" name="name" required>
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit" name="add_lecturer" class="btn">Add Lecturer</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3>All Users</h3>
    <table>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Reg No.</th><th>Reset Password</th><th>Action</th></tr>
        <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($u['name']); ?></td>
            <td><?php echo htmlspecialchars($u['email']); ?></td>
            <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span></td>
            <td><?php echo htmlspecialchars($u['reg_no'] ?? '-'); ?></td>
            <td>
                <!-- Forgot-password flow: admin sets a new password directly for this user -->
                <form method="POST" class="inline-form">
                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                    <input type="password" name="new_password" placeholder="New password" minlength="4" required>
                    <button type="submit" name="reset_password" class="btn btn-outline" onclick="return confirm('Reset password for <?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>?')">Reset</button>
                </form>
            </td>
            <td>
                <?php if ($u['role'] !== 'admin'): ?>
                <a href="?edit=<?php echo $u['id']; ?>" class="btn btn-outline">Edit</a>
                <a href="?delete=<?php echo $u['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this user?')">Delete</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php include '../includes/footer.php'; ?>
