<?php
// install.php
error_reporting(E_ALL);
ini_set('display_errors', 1);


$installed = false;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    $host = $_POST['host'] ?? 'localhost';
    $user = $_POST['user'] ?? 'root';
    $pass = $_POST['pass'] ?? '';
    $dbname = $_POST['dbname'] ?? 'worldcup';
    
    $admin_user = $_POST['admin_user'] ?? 'admin';
    $admin_nick = $_POST['admin_nick'] ?? 'Admin';
    $admin_real = $_POST['admin_real'] ?? 'Administrator';
    $admin_pass = $_POST['admin_pass'] ?? '';
    
    if (empty($admin_pass)) {
        $error = "Vui lòng nhập mật khẩu tài khoản Admin!";
    } else {
        try {
            // Connect without DB first
            $conn = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Create database
            $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->exec("USE `$dbname`");
            
            // Read sql file
            $sqlFile = __DIR__ . '/worldcup.sql';
            if (!file_exists($sqlFile)) {
                // If sql file is missing, create tables directly
                throw new Exception("Không tìm thấy file worldcup.sql!");
            }
            
            $sql = file_get_contents($sqlFile);
            // Remove comments and execute
            $conn->exec($sql);
            
            // Check if admin user already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$admin_user]);
            if ($stmt->fetch()) {
                $conn->exec("DELETE FROM users WHERE username = '$admin_user'");
            }
            
            // Insert admin user
            $hashed_password = password_hash($admin_pass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, nickname, real_name, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->execute([$admin_user, $hashed_password, $admin_nick, $admin_real]);
            
            // Write database.php config
            $configContent = "<?php\n" .
                             "// config/database.php\n" .
                             "if (session_status() == PHP_SESSION_NONE) {\n" .
                             "    session_start();\n" .
                             "}\n\n" .
                             "define('DB_HOST', '$host');\n" .
                             "define('DB_USER', '$user');\n" .
                             "define('DB_PASS', '$pass');\n" .
                             "define('DB_NAME', '$dbname');\n\n" .
                             "try {\n" .
                             "    if (class_exists('PDO')) {\n" .
                             "        \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\", DB_USER, DB_PASS, [\n" .
                             "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n" .
                             "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n" .
                             "            PDO::ATTR_EMULATE_PREPARES => false,\n" .
                             "        ]);\n" .
                             "    } else {\n" .
                             "        \$pdo = null;\n" .
                             "    }\n" .
                             "} catch (PDOException \$e) {\n" .
                             "    \$pdo = null;\n" .
                             "} catch (Throwable \$e) {\n" .
                             "    \$pdo = null;\n" .
                             "}\n";
            
            file_put_contents(__DIR__ . '/config/database.php', $configContent);
            $success = "Cài đặt cơ sở dữ liệu và tạo tài khoản Admin thành công! Vui lòng xóa file install.php trước khi sử dụng website.";
            $installed = true;
        } catch (Exception $e) {
            $error = "Lỗi cài đặt: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt Website Dự đoán World Cup</title>
    <style>
        :root {
            --bg-color: #0c1a12;
            --card-bg: rgba(20, 40, 30, 0.4);
            --primary: #d4af37;
            --text-color: #ffffff;
            --input-bg: rgba(255, 255, 255, 0.05);
            --border: rgba(255, 255, 255, 0.1);
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            background-image: radial-gradient(circle at 50% 50%, #163825 0%, #0c1a12 100%);
            color: var(--text-color);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 500px;
            padding: 30px;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid var(--border);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }
        h2 {
            text-align: center;
            color: var(--primary);
            margin-bottom: 30px;
            font-weight: 600;
            letter-spacing: 1px;
            text-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #ccc;
        }
        input {
            width: 100%;
            padding: 12px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: white;
            font-size: 14px;
            box-sizing: border-box;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(212, 175, 55, 0.5);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #d4af37 0%, #aa8414 100%);
            border: none;
            border-radius: 8px;
            color: #0c1a12;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid #dc3545;
            color: #ea868f;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid #28a745;
            color: #75ec88;
        }
        .divider {
            height: 1px;
            background: var(--border);
            margin: 25px 0;
        }
        .subtitle {
            font-size: 15px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>CÀI ĐẶT HỆ THỐNG</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <br><br>
                <a href="login.php" class="btn" style="display: block; text-align: center; text-decoration: none; box-sizing: border-box;">Đăng nhập ngay</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="install">
                
                <div class="subtitle">Cấu hình Database (MySQL)</div>
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>Database User</label>
                    <input type="text" name="user" value="root" required>
                </div>
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="pass" value="" placeholder="Để trống nếu là XAMPP mặc định">
                </div>
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="dbname" value="worldcup" required>
                </div>
                
                <div class="divider"></div>
                
                <div class="subtitle">Tạo tài khoản Quản trị (Admin)</div>
                <div class="form-group">
                    <label>Tên đăng nhập Admin</label>
                    <input type="text" name="admin_user" value="admin" required>
                </div>
                <div class="form-group">
                    <label>Nickname hiển thị</label>
                    <input type="text" name="admin_nick" value="BTC_WorldCup" required>
                </div>
                <div class="form-group">
                    <label>Tên thật (Chỉ admin xem được)</label>
                    <input type="text" name="admin_real" value="Ban Tổ Chức" required>
                </div>
                <div class="form-group">
                    <label>Mật khẩu Admin</label>
                    <input type="password" name="admin_pass" placeholder="Nhập mật khẩu Admin" required>
                </div>
                
                <button type="submit" class="btn">Tiến hành Cài đặt</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
