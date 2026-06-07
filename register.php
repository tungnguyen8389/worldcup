<?php
// register.php
require_once __DIR__ . '/includes/auth.php';

// Nếu đã đăng nhập, chuyển về trang chủ
if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $real_name = trim($_POST['real_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($nickname) || empty($real_name) || empty($password)) {
        $error = 'Vui lòng điền đầy đủ tất cả các trường thông tin!';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp!';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải chứa ít nhất 6 ký tự!';
    } else {
        try {
            // Kiểm tra username trùng lặp
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Tên đăng nhập này đã được sử dụng!';
            } else {
                // Tạo tài khoản mới
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, nickname, real_name, role) VALUES (?, ?, ?, ?, 'user')");
                $stmt->execute([$username, $hashed_password, $nickname, $real_name]);
                
                $user_id = $pdo->lastInsertId();
                
                // Tự động đăng nhập
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['nickname'] = $nickname;
                $_SESSION['role'] = 'user';
                
                header("Location: index.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Lỗi hệ thống khi đăng ký: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký tài khoản - World Cup Predictor</title>
    <!-- Link FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="auth-container fade-in">
        <div class="auth-header">
            <a href="index.php" class="logo" style="justify-content: center; margin-bottom: 15px;">
                <i class="fa-solid fa-trophy" style="color: var(--primary);"></i>
                <span>WorldCup <span>Predict</span></span>
            </a>
            <p style="color: var(--text-muted); font-size: 14px;">Đăng ký tham gia giải đấu dự đoán</p>
        </div>
        
        <div class="card">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username"><i class="fa-solid fa-user"></i> Tên đăng nhập</label>
                    <input type="text" name="username" id="username" class="form-control" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" placeholder="Dùng để đăng nhập hệ thống" required>
                </div>
                
                <div class="form-group">
                    <label for="nickname"><i class="fa-solid fa-signature"></i> Biệt danh (Nickname)</label>
                    <input type="text" name="nickname" id="nickname" class="form-control" value="<?php echo isset($nickname) ? htmlspecialchars($nickname) : ''; ?>" placeholder="Hiển thị công khai trên Bảng xếp hạng" required>
                </div>
                
                <div class="form-group">
                    <label for="real_name"><i class="fa-solid fa-id-card"></i> Tên thật của bạn</label>
                    <input type="text" name="real_name" id="real_name" class="form-control" value="<?php echo isset($real_name) ? htmlspecialchars($real_name) : ''; ?>" placeholder="Chỉ Ban Tổ Chức nhìn thấy danh tính thật" required>
                    <small style="color: var(--primary); font-size: 11px; margin-top: 5px; display: block;">
                        * Tên thật chỉ công bố công khai sau khi giải đấu kết thúc.
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fa-solid fa-lock"></i> Mật khẩu</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Tối thiểu 6 ký tự" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><i class="fa-solid fa-circle-check"></i> Xác nhận mật khẩu</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Nhập lại mật khẩu" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    Đăng Ký & Tham Gia <i class="fa-solid fa-user-plus"></i>
                </button>
            </form>
        </div>
        
        <div class="auth-footer-text">
            Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a>
        </div>
    </div>
</body>
</html>
