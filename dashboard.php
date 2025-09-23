<?php
// dashboard.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/database.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: index.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

// Determine dashboard content based on role
$dashboard_content = '';
switch ($user_role) {
    case 'admin':
        $dashboard_content = 'pages/admin/dashboard.php';
        break;
    case 'manager':
        $dashboard_content = 'pages/manager/dashboard.php';
        break;
    case 'employee':
        $dashboard_content = 'pages/employee/dashboard.php';
        break;
    default:
        // Redirect to login if role is not recognized
        header('Location: index.php');
        exit();
}

// Get the requested page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Role-based authorization for specific pages
if ($page === 'settings' && $user_role !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

// Set the page title
$page_titles = [
    'dashboard' => 'Dashboard',
    'manage-users' => 'Manage Users',
    'manage-departments' => 'Manage Departments',
    'leave-requests' => 'Leave Requests',
    'request-detail' => 'Request Details',
    'approve-request' => 'Approve Request',
    'reject-request' => 'Reject Request',
    'profile' => 'My Profile',
    'settings' => 'Settings',
    'notifications' => 'Notifications',
    'privacy' => 'Privacy Policy',
    'terms' => 'Terms of Service',
    'accessibility' => 'Accessibility Statement'
];

$page_title = isset($page_titles[$page]) ? $page_titles[$page] : 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ELMS | St. Francis Xavier Hospital</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="main-container">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="main-content">
                <?php 
                // Include the appropriate content based on the page parameter
                if ($page === 'dashboard') {
                    include $dashboard_content;
                } else {
                    // Check if the requested page exists for the current user role
                    $role_page_path = "pages/{$user_role}/{$page}.php";
                    if (file_exists($role_page_path)) {
                        include $role_page_path;
                    } else {
                        // Check if it's a general page
                        $general_page_path = "pages/{$page}.php";
                        if (file_exists($general_page_path)) {
                            include $general_page_path;
                        } else {
                            // Page not found
                            include 'pages/404.php';
                        }
                    }
                }
                ?>
            </main>
        </div>
        
        <?php include 'includes/footer.php'; ?>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>