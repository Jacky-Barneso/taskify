<?php
include('../config/db.php');

// Redirect to login page if the user is not logged in (example)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// If logged in, show the dashboard or tasks page (for example)
header('Location: tasks.php');
exit;