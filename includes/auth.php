<?php
// includes/auth.php
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Tự động phân giải đường dẫn tương đối dựa trên thư mục chạy thực tế
$auth_root_dir = realpath(dirname(__DIR__));
$auth_script_dir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
$auth_path_prefix = '';

if ($auth_script_dir && $auth_root_dir) {
    $auth_root_dir = str_replace('\\', '/', $auth_root_dir);
    $auth_script_dir = str_replace('\\', '/', $auth_script_dir);
    
    $auth_root_dir_lower = strtolower($auth_root_dir);
    $auth_script_dir_lower = strtolower($auth_script_dir);
    if ($auth_script_dir_lower !== $auth_root_dir_lower) {
        $diff = str_ireplace($auth_root_dir_lower, '', $auth_script_dir_lower);
        $diff = trim($diff, '/');
        if (!empty($diff)) {
            $parts = explode('/', $diff);
            $auth_path_prefix = str_repeat('../', count($parts));
        }
    }
}

// Kiểm tra xem database đã được cài đặt chưa
global $pdo;
$current_page = basename($_SERVER['PHP_SELF']);
if (!$pdo && $current_page !== 'install.php') {
    header("Location: " . $auth_path_prefix . "install.php");
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_login() {
    global $auth_path_prefix;
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . $auth_path_prefix . "login.php");
        exit;
    }
}

function require_admin() {
    global $auth_path_prefix;
    require_login();
    if (!is_admin()) {
        header("Location: " . $auth_path_prefix . "index.php");
        exit;
    }
}

