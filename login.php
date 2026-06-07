<?php
// login.php
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
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Đăng nhập thành công, thiết lập session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nickname'] = $user['nickname'];
                $_SESSION['role'] = $user['role'];
                
                // Chuyển hướng về trang trước đó (nếu có) hoặc trang chủ
                $redirect = $_SESSION['redirect_url'] ?? 'index.php';
                unset($_SESSION['redirect_url']);
                header("Location: " . $redirect);
                exit;
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không chính xác!';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi hệ thống khi đăng nhập: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - World Cup Predictor</title>
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
            <p style="color: var(--text-muted); font-size: 14px;">Đăng nhập để tham gia dự đoán & xem bảng xếp hạng</p>
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
                    <input type="text" name="username" id="username" class="form-control" placeholder="Nhập tài khoản đăng ký" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fa-solid fa-lock"></i> Mật khẩu</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Nhập mật khẩu" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    Đăng Nhập <i class="fa-solid fa-right-to-bracket"></i>
                </button>
            </form>
        </div>
        
        <div class="auth-footer-text">
            Chưa có tài khoản? <a href="register.php">Đăng ký tham gia ngay</a>
        </div>
    </div>
</body>
</html>
