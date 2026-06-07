<?php
// admin/dashboard.php
$page_title = "Quản trị hệ thống";
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
require_once __DIR__ . '/../includes/header.php';

$error = '';
$success = '';

// 1. Lưu cấu hình hệ thống
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $api_key = trim($_POST['api_key'] ?? '');
    $league_id = (int)($_POST['league_id'] ?? 1);
    $season = (int)($_POST['season'] ?? 2026);
    $p_exact = (int)($_POST['point_exact_score'] ?? 3);
    $p_diff = (int)($_POST['point_goal_difference'] ?? 2);
    $p_outcome = (int)($_POST['point_correct_outcome'] ?? 1);
    $reveal = isset($_POST['reveal_real_names']) ? 1 : 0;
    
    try {
        save_setting('api_key', $api_key);
        save_setting('league_id', $league_id);
        save_setting('season', $season);
        save_setting('point_exact_score', $p_exact);
        save_setting('point_goal_difference', $p_diff);
        save_setting('point_correct_outcome', $p_outcome);
        save_setting('reveal_real_names', $reveal);
        
        $success = "Lưu cấu hình hệ thống thành công!";
    } catch (Exception $e) {
        $error = "Lỗi khi lưu cấu hình: " . $e->getMessage();
    }
}

// Đọc cấu hình hiện tại
$api_key = get_setting('api_key');
$league_id = get_setting('league_id', 1);
$season = get_setting('season', 2026);
$p_exact = get_setting('point_exact_score', 3);
$p_diff = get_setting('point_goal_difference', 2);
$p_outcome = get_setting('point_correct_outcome', 1);
$reveal = get_setting('reveal_real_names', 0);

// Lấy các số liệu thống kê thực tế cho dashboard admin
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$total_matches = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
$total_predictions = $pdo->query("SELECT COUNT(*) FROM predictions")->fetchColumn();
$finished_matches_count = $pdo->query("SELECT COUNT(*) FROM matches WHERE status IN ('FT', 'AET', 'PEN')")->fetchColumn();
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="color: var(--primary); font-size: 24px; font-weight: 800; text-transform: uppercase;">Quản Trị Hệ Thống</h2>
        <p style="color: var(--text-muted); font-size: 14px;">Quản lý cấu hình, chấm điểm và đối chiếu thông tin thành viên giải dự đoán.</p>
    </div>
    
    <div style="display: flex; gap: 10px;">
        <button id="btn-sync-api" class="btn btn-primary btn-sm"><i class="fa-solid fa-arrows-rotate"></i> Đồng bộ API tự động</button>
    </div>
</div>

<!-- Menu Sub-navigation chung của Admin -->
<div class="card" style="padding: 12px 20px; margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; background: var(--card-bg);">
    <a href="dashboard.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-sliders"></i> Cấu hình & Thống kê</a>
    <a href="matches.php" class="btn btn-secondary btn-sm" style="background: rgba(0,0,0,0.02);"><i class="fa-solid fa-futbol"></i> Quản lý trận đấu</a>
    <a href="users.php" class="btn btn-secondary btn-sm" style="background: rgba(0,0,0,0.02);"><i class="fa-solid fa-users-gear"></i> Quản lý thành viên</a>
</div>

<!-- Grid Thống Kê Tổng Quan -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $total_users; ?></div>
            <div class="stat-label">Thành viên tham gia</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-calendar-days"></i></div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $total_matches; ?></div>
            <div class="stat-label">Tổng số trận đấu</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-circle-question"></i></div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $total_predictions; ?></div>
            <div class="stat-label">Lượt dự đoán đã lưu</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $finished_matches_count; ?></div>
            <div class="stat-label">Trận đã hoàn thành</div>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success; ?></div>
<?php endif; ?>

<!-- Khung Log Đồng bộ API hiển thị bằng JS -->
<div id="sync-log-card" class="card" style="display: none; border: 1px solid var(--accent); background: rgba(0, 136, 85, 0.05);">
    <div class="card-title" style="font-size: 16px; border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
        <i class="fa-solid fa-terminal"></i> Trạng thái Đồng bộ API: <span id="sync-status-text" style="color: var(--accent);">Đang chạy...</span>
    </div>
    <div id="sync-log-message" style="margin-top: 10px; font-family: monospace; font-size: 13.5px; color: var(--text-main); line-height: 1.6;"></div>
</div>

<div class="card">
    <div class="card-title">
        <i class="fa-solid fa-sliders text-primary"></i> Cấu Hình Giải Đấu & API
    </div>
    
    <form method="POST">
        <input type="hidden" name="action" value="save_settings">
        
        <div class="form-group">
            <label for="api_key"><i class="fa-solid fa-key"></i> API-Football Key (api-sports.io)</label>
            <input type="text" name="api_key" id="api_key" class="form-control" value="<?php echo htmlspecialchars($api_key); ?>" placeholder="Nhập API Key đăng ký tại api-sports.io">
        </div>
        
        <div class="grid-2col">
            <div class="form-group">
                <label for="league_id">League ID (World Cup = 1)</label>
                <input type="number" name="league_id" id="league_id" class="form-control" value="<?php echo htmlspecialchars($league_id); ?>" required>
            </div>
            <div class="form-group">
                <label for="season">Mùa giải (Season)</label>
                <input type="number" name="season" id="season" class="form-control" value="<?php echo htmlspecialchars($season); ?>" required>
            </div>
        </div>
        
        <div class="admin-meta-info" style="margin-top: 15px; background: rgba(170, 132, 20, 0.05); border-left: 3px solid var(--primary); padding: 12px; border-radius: 4px; font-size: 13.5px; color: var(--text-muted);">
            <i class="fa-solid fa-circle-info" style="color: var(--primary);"></i> Gợi ý: League ID mặc định của World Cup trong API-Football là <strong>1</strong>. Mùa giải sắp tới sẽ là năm diễn ra (ví dụ: 2026).
        </div>
        
        <div style="font-weight: 600; font-size: 15px; color: var(--primary); margin: 25px 0 15px 0; border-bottom: 1px solid var(--glass-border); padding-bottom: 5px;">
            Cấu Hình Điểm Số
        </div>
        
        <div class="grid-3col">
            <div class="form-group">
                <label>Đúng tỷ số</label>
                <input type="number" name="point_exact_score" class="form-control" value="<?php echo htmlspecialchars($p_exact); ?>" required>
            </div>
            <div class="form-group">
                <label>Đúng hiệu số</label>
                <input type="number" name="point_goal_difference" class="form-control" value="<?php echo htmlspecialchars($p_diff); ?>" required>
            </div>
            <div class="form-group">
                <label>Đúng kết quả</label>
                <input type="number" name="point_correct_outcome" class="form-control" value="<?php echo htmlspecialchars($p_outcome); ?>" required>
            </div>
        </div>
        
        <div style="font-weight: 600; font-size: 15px; color: var(--primary); margin: 25px 0 15px 0; border-bottom: 1px solid var(--glass-border); padding-bottom: 5px;">
            Chế Độ Riêng Tư
        </div>
        
        <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
            <input type="checkbox" name="reveal_real_names" id="reveal_real_names" value="1" style="width: 20px; height: 20px; accent-color: var(--primary);" <?php echo $reveal == 1 ? 'checked' : ''; ?>>
            <label for="reveal_real_names" style="margin-bottom: 0; cursor: pointer; font-size: 15px; font-weight: 600; color: var(--text-main);">
                Công khai Tên Thật của tất cả người chơi
            </label>
        </div>
        <div style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 20px; padding-left: 30px;">
            Tích chọn ô này sau khi kết thúc giải đấu hoặc khi bạn muốn tiết lộ danh tính thật của toàn bộ các Nickname trên bảng xếp hạng.
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%;">Lưu Cấu Hình Hệ Thống <i class="fa-solid fa-floppy-disk"></i></button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnSync = document.getElementById('btn-sync-api');
    const syncCard = document.getElementById('sync-log-card');
    const syncStatus = document.getElementById('sync-status-text');
    const syncLog = document.getElementById('sync-log-message');
    
    if (btnSync) {
        btnSync.addEventListener('click', () => {
            btnSync.disabled = true;
            btnSync.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang đồng bộ...';
            
            syncCard.style.display = 'block';
            syncStatus.innerHTML = 'Đang gọi API...';
            syncStatus.style.color = 'var(--text-main)';
            syncLog.innerHTML = 'Bắt đầu gửi cURL request đến api-sports.io...';
            
            fetch('../api/sync.php')
            .then(res => res.json())
            .then(data => {
                btnSync.disabled = false;
                btnSync.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Đồng bộ API tự động';
                
                if (data.success) {
                    syncStatus.innerHTML = 'Thành công! <i class="fa-solid fa-circle-check"></i>';
                    syncStatus.style.color = 'var(--accent)';
                    syncLog.innerHTML = `<strong>Kết quả:</strong> ${data.message}<br>` +
                                      `- Trận đã đồng bộ: ${data.matches_synced}<br>` +
                                      `- Dự đoán đã chấm điểm: ${data.predictions_scored}`;
                    
                    // Reload trang sau 3 giây để cập nhật bảng xếp hạng mới
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    syncStatus.innerHTML = 'Lỗi! <i class="fa-solid fa-circle-xmark"></i>';
                    syncStatus.style.color = 'var(--accent-red)';
                    syncLog.innerHTML = `<span style="color: var(--accent-red);">Lỗi chi tiết: ${data.message}</span>`;
                }
            })
            .catch(err => {
                btnSync.disabled = false;
                btnSync.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Đồng bộ API tự động';
                
                syncStatus.innerHTML = 'Lỗi kết nối!';
                syncStatus.style.color = 'var(--accent-red)';
                syncLog.innerHTML = '<span style="color: var(--accent-red);">Không thể kết nối đến script api/sync.php</span>';
            });
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
