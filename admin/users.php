<?php
// admin/users.php
$page_title = "Quản lý thành viên";
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
require_once __DIR__ . '/../includes/header.php';

$error = '';
$success = '';

// 1. Thêm hoặc cập nhật thành viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_user') {
    $uid = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $username = trim($_POST['username'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $real_name = trim($_POST['real_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    
    if ($uid === 0) {
        // Thêm mới
        if (empty($username) || empty($nickname) || empty($real_name) || empty($password)) {
            $error = "Vui lòng điền đầy đủ thông tin khi thêm thành viên mới!";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = "Tên đăng nhập này đã tồn tại!";
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, nickname, real_name, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed, $nickname, $real_name, $role]);
                    $success = "Đã thêm thành viên mới thành công!";
                }
            } catch (PDOException $e) {
                $error = "Lỗi khi thêm thành viên: " . $e->getMessage();
            }
        }
    } else {
        // Cập nhật thông tin
        if (empty($nickname) || empty($real_name)) {
            $error = "Vui lòng điền đầy đủ Nickname và Tên thật!";
        } else {
            try {
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET nickname = ?, real_name = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->execute([$nickname, $real_name, $hashed, $role, $uid]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET nickname = ?, real_name = ?, role = ? WHERE id = ?");
                    $stmt->execute([$nickname, $real_name, $role, $uid]);
                }
                
                // Cập nhật session nếu tự edit chính mình
                if ($uid === (int)$_SESSION['user_id']) {
                    $_SESSION['nickname'] = $nickname;
                    $_SESSION['role'] = $role;
                }
                
                $success = "Đã cập nhật thông tin thành viên thành công!";
            } catch (PDOException $e) {
                $error = "Lỗi khi cập nhật thành viên: " . $e->getMessage();
            }
        }
    }
}

// 2. Reset mật khẩu về mặc định 123456
if (isset($_GET['reset_password'])) {
    $reset_id = (int)$_GET['reset_password'];
    try {
        $default_pass = '123456';
        $hashed = password_hash($default_pass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $reset_id]);
        $success = "Mật khẩu của thành viên đã được reset về mặc định: <strong>$default_pass</strong>";
    } catch (PDOException $e) {
        $error = "Lỗi khi reset mật khẩu: " . $e->getMessage();
    }
}

// 3. Xóa thành viên
if (isset($_GET['delete_user'])) {
    $del_id = (int)$_GET['delete_user'];
    if ($del_id !== (int)$_SESSION['user_id']) { // Không cho tự xóa chính mình
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$del_id]);
            $success = "Đã xóa thành viên thành công!";
        } catch (PDOException $e) {
            $error = "Lỗi khi xóa thành viên: " . $e->getMessage();
        }
    } else {
        $error = "Bạn không thể tự xóa tài khoản của chính mình!";
    }
}

// Lấy thông tin thành viên đang được chỉnh sửa (nếu có)
$edit_user = null;
if (isset($_GET['edit_user'])) {
    $edit_id = (int)$_GET['edit_user'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch();
}

// Lấy danh sách toàn bộ người chơi (đối chiếu tên thật)
$sql_users = "SELECT id, username, nickname, real_name, role, created_at FROM users ORDER BY role ASC, nickname ASC";
$users = $pdo->query($sql_users)->fetchAll();
?>

<!-- Header tiêu đề trang -->
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="color: var(--primary); font-size: 24px; font-weight: 800; text-transform: uppercase;">Quản Lý Thành Viên</h2>
        <p style="color: var(--text-muted); font-size: 14px;">Quản lý tài khoản, phân quyền và đối chiếu danh tính người chơi trong hệ thống.</p>
    </div>
    <div>
        <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Quay lại Admin Panel</a>
    </div>
</div>

<!-- Menu Sub-navigation chung của Admin -->
<div class="card" style="padding: 12px 20px; margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; background: var(--card-bg);">
    <a href="dashboard.php" class="btn btn-secondary btn-sm" style="background: rgba(0,0,0,0.02);"><i class="fa-solid fa-sliders"></i> Cấu hình & Thống kê</a>
    <a href="matches.php" class="btn btn-secondary btn-sm" style="background: rgba(0,0,0,0.02);"><i class="fa-solid fa-futbol"></i> Quản lý trận đấu</a>
    <a href="users.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-users-gear"></i> Quản lý thành viên</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success; ?></div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- CỘT TRÁI: DANH SÁCH THÀNH VIÊN -->
    <div>
        <div class="card" id="users-list-card">
            <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
                <span><i class="fa-solid fa-users text-primary"></i> Danh Sách Thành Viên (<?php echo count($users); ?>)</span>
                <button onclick="exportToPDF('users-list-card', 'Danh_Sach_Thanh_Vien')" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 12px; margin-left: auto;">
                    <i class="fa-solid fa-file-pdf"></i> Xuất PDF
                </button>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nickname / Acc</th>
                            <th>Tên thật</th>
                            <th class="pdf-exclude" style="text-align: center; width: 140px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $is_current = ($u['id'] === (int)$_SESSION['user_id']);
                        ?>
                            <tr style="<?php echo $is_current ? 'background: rgba(170, 132, 20, 0.05);' : ''; ?>">
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($u['nickname']); ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($u['username']); ?></div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($u['real_name']); ?></strong>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span style="font-size: 10px; background: var(--accent-red); color: white; padding: 1px 4px; border-radius: 3px; font-weight: bold; margin-left: 5px;">Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pdf-exclude" style="text-align: center;">
                                    <div style="display: flex; gap: 5px; justify-content: center;">
                                        <!-- Sửa thông tin -->
                                        <a href="?edit_user=<?php echo $u['id']; ?>#user-form" class="btn btn-secondary btn-sm" style="padding: 6px 10px;" title="Chỉnh sửa">
                                            <i class="fa-solid fa-user-pen"></i>
                                        </a>
                                        <!-- Reset mật khẩu -->
                                        <a href="?reset_password=<?php echo $u['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px; color: var(--primary);" title="Reset mật khẩu về 123456" onclick="return confirm('Bạn có chắc chắn muốn reset mật khẩu của thành viên này về mặc định: 123456?')">
                                            <i class="fa-solid fa-key"></i>
                                        </a>
                                        <!-- Xóa -->
                                        <?php if (!$is_current): ?>
                                            <a href="?delete_user=<?php echo $u['id']; ?>" class="btn btn-danger btn-sm" style="padding: 6px 10px;" onclick="return confirm('Bạn có chắc chắn muốn xóa thành viên này? Hành động này sẽ xóa tất cả dự đoán của họ và không thể khôi phục!')" title="Xóa tài khoản">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" style="padding: 6px 10px; opacity: 0.5;" disabled title="Không thể tự xóa chính mình">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- CỘT PHẢI: FORM THÊM / CẬP NHẬT THÀNH VIÊN -->
    <div>
        <div class="card" id="user-form">
            <div class="card-title">
                <i class="fa-solid <?php echo $edit_user ? 'fa-user-pen' : 'fa-user-plus'; ?> text-primary"></i> 
                <?php echo $edit_user ? 'Cập Nhật Thành Viên: ' . htmlspecialchars($edit_user['nickname']) : 'Thêm Thành Viên Mới'; ?>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_user">
                <?php if ($edit_user): ?>
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username">Tên đăng nhập (Username) *</label>
                    <input type="text" name="username" id="username" class="form-control" value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>" <?php echo $edit_user ? 'disabled' : 'required'; ?> placeholder="Dùng để đăng nhập">
                </div>
                
                <div class="grid-2col">
                    <div class="form-group">
                        <label for="nickname">Biệt danh (Nickname) *</label>
                        <input type="text" name="nickname" id="nickname" class="form-control" value="<?php echo $edit_user ? htmlspecialchars($edit_user['nickname']) : ''; ?>" placeholder="Hiển thị công khai" required>
                    </div>
                    <div class="form-group">
                        <label for="real_name">Tên thật (Real name) *</label>
                        <input type="text" name="real_name" id="real_name" class="form-control" value="<?php echo $edit_user ? htmlspecialchars($edit_user['real_name']) : ''; ?>" placeholder="Ban tổ chức đối chiếu" required>
                    </div>
                </div>
                
                <div class="grid-2col">
                    <div class="form-group">
                        <label for="password">Mật khẩu <?php echo $edit_user ? '(Để trống nếu không đổi)' : '*'; ?></label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Tối thiểu 6 ký tự" <?php echo $edit_user ? '' : 'required'; ?>>
                    </div>
                    <div class="form-group">
                        <label for="role">Vai trò (Role)</label>
                        <select name="role" id="role" class="form-control">
                            <option value="user" <?php echo ($edit_user && $edit_user['role'] === 'user') ? 'selected' : ''; ?>>Thành viên (user)</option>
                            <option value="admin" <?php echo ($edit_user && $edit_user['role'] === 'admin') ? 'selected' : ''; ?>>Quản trị viên (admin)</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <?php echo $edit_user ? 'Cập Nhật <i class="fa-solid fa-check"></i>' : 'Thêm Mới <i class="fa-solid fa-plus"></i>'; ?>
                    </button>
                    
                    <?php if ($edit_user): ?>
                        <a href="users.php" class="btn btn-secondary" style="text-align: center; text-decoration: none;">Hủy bỏ</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
