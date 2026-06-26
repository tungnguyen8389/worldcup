<?php
// includes/header.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Tự động phân giải đường dẫn tương đối dựa trên thư mục chạy thực tế
$root_dir = realpath(dirname(__DIR__));
$script_dir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
$path_prefix = '';

if ($script_dir && $root_dir) {
    // Chuẩn hóa dấu gạch chéo xuôi/ngược để đồng bộ trên Windows & Linux
    $root_dir = str_replace('\\', '/', $root_dir);
    $script_dir = str_replace('\\', '/', $script_dir);
    
    $root_dir_lower = strtolower($root_dir);
    $script_dir_lower = strtolower($script_dir);
    if ($script_dir_lower !== $root_dir_lower) {
        $diff = str_ireplace($root_dir_lower, '', $script_dir_lower);
        $diff = trim($diff, '/');
        if (!empty($diff)) {
            $parts = explode('/', $diff);
            $path_prefix = str_repeat('../', count($parts));
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - " . strip_tags(get_setting('site_logo_text', 'WorldCup Predict')) : strip_tags(get_setting('site_logo_text', 'WorldCup Predict')); ?></title>
    <!-- Google Fonts Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Link FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Link Chart.js (for leaderboards) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- CSS Stylesheets -->
    <?php if (stripos($_SERVER['SCRIPT_NAME'], '/admin/') !== false): ?>
        <link rel="stylesheet" href="<?php echo $path_prefix; ?>admin/admin.css?v=1.0.6">
    <?php else: ?>
        <link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/style.css?v=1.0.6">
    <?php endif; ?>
</head>
<body data-path-prefix="<?php echo $path_prefix; ?>">
    <header>
        <div class="nav-container">
            <a href="<?php echo $path_prefix; ?>index.php" class="logo">
                <i class="fa-solid fa-trophy text-primary" style="color: var(--primary);"></i>
                <span><?php echo get_setting('site_logo_text', 'WorldCup <span>Predict</span>'); ?></span>
            </a>
            
            <?php if (is_logged_in()): ?>
                <!-- Hamburger Button for Mobile -->
                <button class="nav-toggle" id="navToggle" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
                <div class="nav-menu" id="navMenu">
                    <ul class="nav-links">
                        <li class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                            <a href="<?php echo $path_prefix; ?>index.php"><i class="fa-solid fa-house-chimney"></i> Trang chủ</a>
                        </li>
                        <li class="<?php echo $current_page == 'predictions.php' ? 'active' : ''; ?>">
                            <a href="<?php echo $path_prefix; ?>predictions.php"><i class="fa-solid fa-chart-line"></i> Lịch sử dự đoán</a>
                        </li>
                        <li class="<?php echo $current_page == 'stats.php' ? 'active' : ''; ?>">
                            <a href="<?php echo $path_prefix; ?>stats.php"><i class="fa-solid fa-chart-bar"></i> Thống kê</a>
                        </li>
                        <li class="<?php echo $current_page == 'rules.php' ? 'active' : ''; ?>">
                            <a href="<?php echo $path_prefix; ?>rules.php"><i class="fa-solid fa-book-open"></i> Luật chơi</a>
                        </li>
                        <?php if (is_admin()): ?>
                            <li class="<?php echo strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? 'active' : ''; ?>">
                                <a href="<?php echo $path_prefix; ?>admin/dashboard.php" class="btn-admin"><i class="fa-solid fa-user-gear"></i> Admin Panel</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <div class="user-menu">
                        <div class="user-info">
                            Xin chào, <strong><?php echo htmlspecialchars($_SESSION['nickname']); ?></strong>
                            <?php if (is_admin()): ?>
                                <span class="badge" style="background: var(--primary); color: #06150e; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 5px;">Admin</span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo $path_prefix; ?>logout.php" class="btn btn-secondary btn-sm" style="padding: 6px 12px;"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="user-menu">
                    <a href="<?php echo $path_prefix; ?>login.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập</a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');
        
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                navMenu.classList.toggle('active');
                
                const icon = navToggle.querySelector('i');
                if (icon.classList.contains('fa-bars')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-xmark');
                } else {
                    icon.classList.remove('fa-xmark');
                    icon.classList.add('fa-bars');
                }
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!navMenu.contains(e.target) && !navToggle.contains(e.target)) {
                    if (navMenu.classList.contains('active')) {
                        navMenu.classList.remove('active');
                        const icon = navToggle.querySelector('i');
                        icon.classList.remove('fa-xmark');
                        icon.classList.add('fa-bars');
                    }
                }
            });
        }
    });
    </script>
    <main class="container fade-in">
