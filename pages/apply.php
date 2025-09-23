<?php
// pages/apply.php - General leave application form for all user roles

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Route to appropriate apply form based on user role
$user_role = $_SESSION['user_role'];

switch ($user_role) {
    case 'employee':
        // Employees use the existing employee apply form
        include 'employee/apply.php';
        break;

    case 'manager':
    case 'admin':
        // Managers and admins use the new manager-admin apply form
        include 'manager-admin/apply.php';
        break;

    default:
        // Unknown role - redirect to unauthorized
        header('Location: unauthorized.php');
        exit();
}
?>