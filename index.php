<?php
require_once 'config.php';
if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "/dashboard.php");
} else {
    header("Location: login.php");
}
exit();
?>
