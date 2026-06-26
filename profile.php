<?php
// profile.php
require_once __DIR__ . '/includes/auth.php';
require_login();

$page_title = "Hồ sơ cá nhân";
require_once __DIR__ . '/includes/header.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Lấy thông tin user hiện tại
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "<div class='alert alert-danger'>Không tìm thấy thông tin thành viên!</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim($_POST['nickname'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($nickname)) {
        $error = 'Biệt danh (nickname) không được để trống!';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Cập nhật biệt danh trước
            $stmt_update = $pdo->prepare("UPDATE users SET nickname = ? WHERE id = ?");
            $stmt_update->execute([$nickname, $user_id]);
            $_SESSION['nickname'] = $nickname; // Cập nhật session
            
            // Xử lý đổi mật khẩu nếu user nhập mật khẩu mới
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $error = 'Vui lòng nhập mật khẩu hiện tại để thay đổi mật khẩu!';
                } elseif (!password_verify($current_password, $user['password'])) {
                    $error = 'Mật khẩu hiện tại không đúng!';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Mật khẩu xác nhận không khớp!';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Mật khẩu mới phải chứa ít nhất 6 ký tự!';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt_pw = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt_pw->execute([$hashed_password, $user_id]);
                }
            }
            
            if (empty($error)) {
                $pdo->commit();
                $success = 'Cập nhật thông tin hồ sơ thành công!';
                // Tải lại thông tin user mới cập nhật
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Lỗi hệ thống khi cập nhật: ' . $e->getMessage();
        }
    }
}
?>

<div style="max-width: 600px; margin: 40px auto;">
    <div style="text-align: center; margin-bottom: 30px;">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 36px; color: #06150e; box-shadow: 0 8px 24px rgba(0, 255, 170, 0.2); margin-bottom: 15px;">
            <i class="fa-solid fa-user-astronaut"></i>
        </div>
        <h2 style="color: var(--text-main); font-family: 'Outfit'; font-weight: 800; font-size: 24px; margin: 0; text-transform: uppercase;">Hồ Sơ Thành Viên</h2>
        <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">Cập nhật biệt danh hiển thị hoặc đổi mật khẩu tài khoản</p>
    </div>
    
    <div class="card" style="background: var(--card-bg); border: 1px solid var(--glass-border); box-shadow: var(--shadow-lg); padding: 30px; border-radius: 12px;">
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">
                <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Username (Read only) -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="color: var(--text-main); font-weight: 600; margin-bottom: 8px; display: block;"><i class="fa-solid fa-user"></i> Tên đăng nhập</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" style="background: rgba(255, 255, 255, 0.05); color: var(--text-muted); border: 1px solid rgba(255, 255, 255, 0.05); cursor: not-allowed;" readonly>
                <small style="color: var(--text-muted); font-size: 11.5px; margin-top: 4px; display: block;">Tên đăng nhập dùng định danh tài khoản và không thể sửa đổi.</small>
            </div>
            
            <!-- Real name (Read only - Not allowed to change) -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="color: var(--text-main); font-weight: 600; margin-bottom: 8px; display: block;"><i class="fa-solid fa-id-card"></i> Họ và tên thật</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['real_name']); ?>" style="background: rgba(255, 255, 255, 0.05); color: var(--text-muted); border: 1px solid rgba(255, 255, 255, 0.05); cursor: not-allowed;" readonly>
                <small style="color: var(--accent-red); font-size: 11.5px; font-weight: 500; margin-top: 4px; display: block;"><i class="fa-solid fa-circle-exclamation"></i> Họ và tên thật cố định đăng ký ban đầu để xác minh giải thưởng, không thể sửa đổi.</small>
            </div>
            
            <!-- Nickname (Editable) -->
            <div class="form-group" style="margin-bottom: 25px;">
                <label for="nickname" style="color: var(--text-main); font-weight: 600; margin-bottom: 8px; display: block;"><i class="fa-solid fa-signature"></i> Biệt danh (Nickname)</label>
                <input type="text" name="nickname" id="nickname" class="form-control" value="<?php echo htmlspecialchars($user['nickname']); ?>" required style="background: rgba(255, 255, 255, 0.02); color: var(--text-main); border: 1px solid var(--glass-border);">
                <small style="color: var(--text-muted); font-size: 11.5px; margin-top: 4px; display: block;">Biệt danh hiển thị công khai trên bảng xếp hạng.</small>
            </div>
            
            <!-- Đổi mật khẩu section -->
            <div style="border-top: 1px solid var(--glass-border); padding-top: 20px; margin-top: 25px; margin-bottom: 25px;">
                <div style="font-weight: 700; font-size: 15px; color: var(--primary); text-transform: uppercase; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-key"></i> Thay đổi mật khẩu
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="current_password" style="color: var(--text-main); font-size: 13.5px; font-weight: 500; margin-bottom: 6px; display: block;">Mật khẩu hiện tại</label>
                    <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Nhập mật khẩu hiện tại" style="background: rgba(255, 255, 255, 0.02); color: var(--text-main); border: 1px solid var(--glass-border);">
                </div>
                
                <div class="grid-2col" style="gap: 15px;">
                    <div class="form-group">
                        <label for="new_password" style="color: var(--text-main); font-size: 13.5px; font-weight: 500; margin-bottom: 6px; display: block;">Mật khẩu mới</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Tối thiểu 6 ký tự" style="background: rgba(255, 255, 255, 0.02); color: var(--text-main); border: 1px solid var(--glass-border);">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" style="color: var(--text-main); font-size: 13.5px; font-weight: 500; margin-bottom: 6px; display: block;">Xác nhận mật khẩu mới</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Nhập lại mật khẩu mới" style="background: rgba(255, 255, 255, 0.02); color: var(--text-main); border: 1px solid var(--glass-border);">
                    </div>
                </div>
                <small style="color: var(--text-muted); font-size: 11.5px; margin-top: 6px; display: block;">Để trống các trường mật khẩu nếu không muốn thay đổi mật khẩu.</small>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <a href="index.php" class="btn btn-secondary" style="border: 1px solid var(--glass-border); padding: 10px 20px;"><i class="fa-solid fa-chevron-left"></i> Quay lại</a>
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Lưu Thay Đổi <i class="fa-solid fa-floppy-disk"></i></button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
