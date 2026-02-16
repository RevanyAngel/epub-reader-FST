<?php
require_once 'config/database.php';

// Redirect ke dashboard jika sudah login
if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
} else {
    // Redirect ke login jika belum login
    header('Location: login.php');
    exit();
}
?>