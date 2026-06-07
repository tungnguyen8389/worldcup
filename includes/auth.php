<?php
// includes/auth.php
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Kiểm tra xem database đã được cài đặt chưa
global $pdo;
$current_page = basename($_SERVER['PHP_SELF']);
if (!$pdo && $current_page !== 'install.php') {
    header("Location: install.php");
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        header("Location: index.php");
        exit;
    }
}
