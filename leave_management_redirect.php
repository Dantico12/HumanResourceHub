<?php
// Redirect file to maintain backward compatibility
// This handles any existing links to leave_management.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get the tab parameter and redirect to appropriate page
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'apply';

switch ($tab) {
    case 'apply':
        header("Location: apply_leave.php");
        break;
    case 'manage':
        header("Location: manage_leave.php");
        break;
    case 'history':
        header("Location: leave_history.php");
        break;
    case 'holidays':
        header("Location: holidays.php");
        break;
    case 'profile':
        header("Location: leave_profile.php");
        break;
    default:
        header("Location: apply_leave.php");
        break;
}
exit();
?>