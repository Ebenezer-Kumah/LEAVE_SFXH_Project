<?php
// includes/auth.php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function redirect_if_not_logged_in() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }
}

function redirect_based_on_role() {
    if (is_logged_in()) {
        $role = $_SESSION['user_role'];
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit();
    }
}
?>