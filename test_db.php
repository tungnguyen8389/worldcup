<?php
// test_db.php - Script chẩn đoán lỗi 500 trên VPS
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>Hệ thống chẩn đoán lỗi dự đoán World Cup</h2>";

// 1. Kiểm tra phiên bản PHP
echo "<h3>1. Thông tin PHP:</h3>";
echo "Phiên bản PHP hiện tại: " . PHP_VERSION . "<br>";

// 2. Kiểm tra các Extension cần thiết
echo "<h3>2. Kiểm tra Extension:</h3>";
$required_extensions = ['pdo', 'pdo_mysql', 'session', 'json'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span style='color: green;'>✔</span> Extension <strong>$ext</strong>: Đã bật<br>";
    } else {
        echo "<span style='color: red;'>✘</span> Extension <strong>$ext</strong>: CHƯA BẬT (Có thể gây lỗi 500)<br>";
    }
}

// 3. Kiểm tra file config/database.php
echo "<h3>3. Kiểm tra cấu hình database:</h3>";
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    echo "<span style='color: green;'>✔</span> Tìm thấy file config/database.php<br>";
    
    // Đọc thử nội dung (không include trực tiếp ngay để tránh crash)
    $content = file_get_contents($config_path);
    echo "Nội dung config/database.php:<br><pre style='background:#f4f4f4;padding:10px;border:1px solid #ccc;'>";
    echo htmlspecialchars($content);
    echo "</pre>";
    
    // Thử kết nối database
    echo "<h3>4. Thử kết nối Database:</h3>";
    
    // Đặt tên biến pdo tạm thời để tránh xung đột
    try {
        require_once $config_path;
        
        if (defined('DB_HOST')) {
            echo "DB_HOST: " . DB_HOST . "<br>";
            echo "DB_USER: " . DB_USER . "<br>";
            echo "DB_NAME: " . DB_NAME . "<br>";
            
            echo "Đang thử kết nối database...<br>";
            $test_pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            echo "<span style='color: green; font-weight: bold;'>✔ Kết nối Database THÀNH CÔNG!</span><br>";
            
            // Kiểm tra các bảng trong database
            echo "<h3>5. Kiểm tra các bảng:</h3>";
            $tables = ['users', 'matches', 'predictions', 'system_settings', 'daily_rankings'];
            foreach ($tables as $table) {
                try {
                    $q = $test_pdo->query("SELECT COUNT(*) FROM `$table`");
                    echo "<span style='color: green;'>✔</span> Bảng <strong>$table</strong>: Tồn tại và hoạt động tốt<br>";
                } catch (PDOException $ex) {
                    echo "<span style='color: red;'>✘</span> Bảng <strong>$table</strong>: Lỗi truy vấn - " . $ex->getMessage() . "<br>";
                }
            }
        } else {
            echo "<span style='color: red;'>✘ Không tìm thấy định nghĩa cấu hình database trong file database.php!</span><br>";
        }
    } catch (PDOException $e) {
        echo "<span style='color: red; font-weight: bold;'>✘ Kết nối Database THẤT BẠI!</span><br>";
        echo "Chi tiết lỗi: " . $e->getMessage() . "<br>";
    } catch (Throwable $t) {
        echo "<span style='color: red; font-weight: bold;'>✘ Lỗi hệ thống khi kết nối:</span> " . $t->getMessage() . "<br>";
    }
} else {
    echo "<span style='color: red;'>✘ Không tìm thấy file config/database.php. Vui lòng chạy file install.php trước!</span><br>";
}

// 4. Kiểm tra quyền ghi thư mục config
echo "<h3>6. Kiểm tra quyền ghi thư mục:</h3>";
$config_dir = __DIR__ . '/config';
if (is_writable($config_dir)) {
    echo "<span style='color: green;'>✔</span> Thư mục <strong>/config</strong>: Có quyền ghi (Writable)<br>";
} else {
    echo "<span style='color: red;'>✘</span> Thư mục <strong>/config</strong>: Không có quyền ghi (Không thể lưu file cấu hình mới qua install.php)<br>";
}

// 5. Kiểm tra quyền ghi root (cho install.php xóa chính nó hoặc lưu config)
$root_dir = __DIR__;
if (is_writable($root_dir)) {
    echo "<span style='color: green;'>✔</span> Thư mục <strong>Root</strong>: Có quyền ghi (Writable)<br>";
} else {
    echo "<span style='color: red;'>✘</span> Thư mục <strong>Root</strong>: Không có quyền ghi<br>";
}
?>
