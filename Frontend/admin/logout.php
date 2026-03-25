<?php
// admin/logout.php
require_once __DIR__ . '/../functions.php';

// Start session if not started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only logout if user is logged in and is admin
if (!empty($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin') {
    logout_user(); // your function that clears session safely
}

// Optionally destroy session completely
session_unset();
session_destroy();

// Redirect to login page
header('Location:login.php');
exit;
