<?php
// admin/matches.php
$page_title = "Quản lý trận đấu";
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$error = '';
$success = $_GET['success'] ?? '';

// 1. Xử lý thêm hoặc cập nhật trận đấu mới thủ công
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_match') {
    $is_edit = isset($_POST['is_edit']) && $_POST['is_edit'] == '1';
    $original_id = (int)($_POST['original_id'] ?? 0);
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        $id = $is_edit ? $original_id : rand(100000, 999999);
    }
    
    $home_team = trim($_POST['home_team'] ?? '');
    $away_team = trim($_POST['away_team'] ?? '');
    $home_logo = trim($_POST['home_logo'] ?? '');
    $away_logo = trim($_POST['away_logo'] ?? '');
    $match_time = $_POST['match_time'] ?? '';
    $round = trim($_POST['round'] ?? 'Vòng bảng');
    $handicap = isset($_POST['handicap']) && $_POST['handicap'] !== '' ? (float)$_POST['handicap'] : 0.0;
    
    if (empty($home_team) || empty($away_team) || empty($match_time)) {
        $error = "Vui lòng điền đầy đủ các thông tin trận đấu bắt buộc!";
    } else {
        try {
            if ($is_edit) {
                // Cập nhật trận đấu hiện tại
                $sql = "UPDATE matches SET id = ?, home_team = ?, away_team = ?, home_logo = ?, away_logo = ?, match_time = ?, round = ?, handicap = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id, $home_team, $away_team, $home_logo, $away_logo, $match_time, $round, $handicap, $original_id]);
                
                // Nếu ID thay đổi, cập nhật dự đoán liên quan
                if ($id !== $original_id) {
                    $stmt_up_preds = $pdo->prepare("UPDATE predictions SET match_id = ? WHERE match_id = ?");
                    $stmt_up_preds->execute([$id, $original_id]);
                }
                
                // Tự động chấm điểm lại nếu trận đấu đã kết thúc
                $stmt_check = $pdo->prepare("SELECT status, home_score, away_score FROM matches WHERE id = ?");
                $stmt_check->execute([$id]);
                $m_check = $stmt_check->fetch();
                if ($m_check && in_array($m_check['status'], ['FT', 'AET', 'PEN']) && $m_check['home_score'] !== null && $m_check['away_score'] !== null) {
                    score_match_predictions($id, $m_check['home_score'], $m_check['away_score'], $handicap);
                    
                    $match_date = date('Y-m-d', strtotime($match_time));
                    update_rankings_for_date($match_date);
                    update_rankings_for_date(date('Y-m-d'));
                }
                
                $success = "Cập nhật trận đấu thành công!";
            } else {
                // Thêm mới
                $sql = "INSERT INTO matches (id, home_team, away_team, home_logo, away_logo, match_time, status, round, handicap) 
                        VALUES (?, ?, ?, ?, ?, ?, 'NS', ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id, $home_team, $away_team, $home_logo, $away_logo, $match_time, $round, $handicap]);
                $success = "Thêm trận đấu thành công!";
            }
            header("Location: matches.php?success=" . urlencode($success));
            exit;
        } catch (PDOException $e) {
            $error = "Lỗi khi lưu trận đấu: " . $e->getMessage();
        }
    }
}

// 2. Xử lý cập nhật tỷ số thủ công
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_score') {
    $match_id = (int)$_POST['match_id'];
    $home_score = $_POST['home_score'] === '' ? null : (int)$_POST['home_score'];
    $away_score = $_POST['away_score'] === '' ? null : (int)$_POST['away_score'];
    $status = (isset($_POST['quick_ft']) && $_POST['quick_ft'] == '1') ? 'FT' : ($_POST['status'] ?? 'NS');
    if ($home_score !== null && $away_score !== null && $status === 'NS') {
        $status = 'FT';
    }
    
    try {
        $pdo->beginTransaction();
        
        // Cập nhật trận đấu
        $sql = "UPDATE matches SET home_score = ?, away_score = ?, status = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$home_score, $away_score, $status, $match_id]);
        
        // Nếu chuyển sang trạng thái đã kết thúc thì chấm điểm các dự đoán liên quan
        $predictions_scored = 0;
        if (in_array($status, ['FT', 'AET', 'PEN']) && $home_score !== null && $away_score !== null) {
            // Lấy ngày thi đấu và tỷ lệ chấp để tính bảng xếp hạng sau đó
            $stmt_match_info = $pdo->prepare("SELECT match_time, handicap FROM matches WHERE id = ?");
            $stmt_match_info->execute([$match_id]);
            $match_info = $stmt_match_info->fetch();
            $match_date = date('Y-m-d', strtotime($match_info['match_time']));
            $handicap = (float)($match_info['handicap'] ?? 0.0);
            
            // Chấm điểm các dự đoán và xử thua cho thành viên không dự đoán
            $predictions_scored = score_match_predictions($match_id, $home_score, $away_score, $handicap);
            
            $pdo->commit();
            
            // Cập nhật bảng xếp hạng ngày diễn ra trận đấu và ngày hiện tại
            update_rankings_for_date($match_date);
            update_rankings_for_date(date('Y-m-d'));
            
            $success = "Đã cập nhật tỷ số trận đấu và tự động chấm điểm cho $predictions_scored lượt dự đoán!";
        } else {
            // Nếu không kết thúc thì chỉ lưu tỷ số và commit
            $pdo->commit();
            $success = "Cập nhật thông tin trận đấu thành công!";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Lỗi khi cập nhật tỷ số: " . $e->getMessage();
    }
}

// 3. Xử lý xóa trận đấu
if (isset($_GET['delete_match'])) {
    $del_id = (int)$_GET['delete_match'];
    try {
        $stmt = $pdo->prepare("DELETE FROM matches WHERE id = ?");
        $stmt->execute([$del_id]);
        $success = "Đã xóa trận đấu thành công!";
        header("Location: matches.php?success=" . urlencode($success));
        exit;
    } catch (PDOException $e) {
        $error = "Lỗi khi xóa trận đấu: " . $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';

// 4. Lấy thông tin trận đấu đang được chỉnh sửa (nếu có)
$edit_match = null;
if (isset($_GET['edit_match'])) {
    $edit_id = (int)$_GET['edit_match'];
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_match = $stmt->fetch();
}

// Lấy danh sách trận đấu hiện tại trong DB
$sql_matches = "SELECT * FROM matches ORDER BY match_time DESC";
$matches = $pdo->query($sql_matches)->fetchAll();
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="color: var(--primary); font-size: 24px; font-weight: 800; text-transform: uppercase;">Quản Lý Trận Đấu</h2>
        <p style="color: var(--text-muted); font-size: 14px;">Thêm mới, xóa trận đấu hoặc cập nhật kết quả thủ công.</p>
    </div>
    
    <div>
        <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Quay lại Admin Panel</a>
    </div>
</div>

<!-- Menu Sub-navigation chung của Admin -->
<div class="card" style="padding: 12px 20px; margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; background: var(--card-bg);">
    <a href="dashboard.php" class="btn btn-secondary btn-sm" style="background: rgba(0,0,0,0.02);"><i class="fa-solid fa-sliders"></i> Cấu hình & Thống kê</a>
    <a href="matches.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-futbol"></i> Quản lý trận đấu</a>
    <a href="users.php" class="btn btn-secondary btn-sm" style="background: rgba(0,0,0,0.02);"><i class="fa-solid fa-users-gear"></i> Quản lý thành viên</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- CỘT TRÁI: DANH SÁCH TRẬN ĐẤU & FORM CẬP NHẬT TỶ SỐ -->
    <div>
        <div class="card" id="matches-list-card">
            <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
                <span><i class="fa-solid fa-futbol text-primary"></i> Lịch Thi Đấu & Kết Quả</span>
                <button onclick="exportToPDF('matches-list-card', 'Danh_Sach_Ket_Qua_Tran_Dau')" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 12px; margin-left: auto;">
                    <i class="fa-solid fa-file-pdf"></i> Xuất PDF
                </button>
            </div>
            
            <?php if (empty($matches)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 30px;">Không có trận đấu nào trong cơ sở dữ liệu.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Thông tin trận đấu</th>
                                <th style="text-align: center; width: 220px;">Tỷ số & Trạng thái</th>
                                <th class="pdf-exclude" style="text-align: center; width: 100px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $match): ?>
                                <tr>
                                    <td>
                                        <div style="font-size: 11px; color: var(--primary); font-weight: bold; text-transform: uppercase; margin-bottom: 4px;">
                                            <?php echo htmlspecialchars($match['round']); ?> (ID: <?php echo $match['id']; ?>)
                                        </div>
                                        <div style="font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 10px; margin-bottom: 6px; flex-wrap: wrap;">
                                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                                <img src="<?php echo htmlspecialchars($match['home_logo'] ?: '../assets/images/team_placeholder.png'); ?>" style="width: 24px; height: 16px; object-fit: cover; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); display: inline-block; vertical-align: middle;" alt="">
                                                <span><?php echo htmlspecialchars($match['home_team']); ?></span>
                                            </div>
                                            <span style="color: var(--text-muted); font-weight: normal; font-size: 12px;">vs</span>
                                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                                <img src="<?php echo htmlspecialchars($match['away_logo'] ?: '../assets/images/team_placeholder.png'); ?>" style="width: 24px; height: 16px; object-fit: cover; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); display: inline-block; vertical-align: middle;" alt="">
                                                <span><?php echo htmlspecialchars($match['away_team']); ?></span>
                                            </div>
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-muted);">
                                            <i class="fa-solid fa-clock"></i> <?php echo format_match_time($match['match_time']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                            <i class="fa-solid fa-scale-balanced"></i> Kèo chấp: 
                                            <strong style="color: var(--accent);">
                                                <?php 
                                                $hc = (float)($match['handicap'] ?? 0.0);
                                                if ($hc > 0) {
                                                    echo htmlspecialchars($match['home_team']) . ' chấp ' . $hc;
                                                } elseif ($hc < 0) {
                                                    echo htmlspecialchars($match['away_team']) . ' chấp ' . abs($hc);
                                                } else {
                                                    echo 'Đồng banh (0.0)';
                                                }
                                                ?>
                                            </strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="pdf-only" style="display: none; font-size: 16px; font-weight: 800; text-align: center; color: var(--text-main);">
                                            <?php echo ($match['home_score'] !== null && $match['away_score'] !== null) ? ($match['home_score'] . ' - ' . $match['away_score'] . ' (' . $match['status'] . ')') : 'Chưa diễn ra'; ?>
                                        </div>
                                        <form method="POST" style="display: flex; flex-direction: column; gap: 6px;">
                                            <input type="hidden" name="action" value="update_score">
                                            <input type="hidden" name="match_id" value="<?php echo $match['id']; ?>">
                                            
                                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                                <input type="number" name="home_score" class="form-control" style="width: 50px; text-align: center; padding: 6px; <?php echo in_array($match['status'], ['FT', 'AET', 'PEN']) ? 'opacity: 0.6;' : ''; ?>" value="<?php echo $match['home_score']; ?>" min="0" <?php echo in_array($match['status'], ['FT', 'AET', 'PEN']) ? 'disabled' : ''; ?>>
                                                <span style="color: var(--text-muted); font-weight: bold;">-</span>
                                                <input type="number" name="away_score" class="form-control" style="width: 50px; text-align: center; padding: 6px; <?php echo in_array($match['status'], ['FT', 'AET', 'PEN']) ? 'opacity: 0.6;' : ''; ?>" value="<?php echo $match['away_score']; ?>" min="0" <?php echo in_array($match['status'], ['FT', 'AET', 'PEN']) ? 'disabled' : ''; ?>>
                                            </div>
                                            
                                            <?php if (in_array($match['status'], ['FT', 'AET', 'PEN'])): ?>
                                                <div style="font-size: 10px; text-align: center; background: rgba(0, 136, 85, 0.08); border: 1px solid var(--accent); color: var(--accent); padding: 4px; border-radius: 4px; font-weight: bold; margin-bottom: 4px;">
                                                    <i class="fa-solid fa-circle-check"></i> Đã kết thúc (<?php echo $match['status']; ?>)
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div style="display: flex; gap: 5px; margin-top: 4px;">
                                                <select name="status" class="form-control" style="padding: 6px; font-size: 11px; height: auto; flex: 1; <?php echo in_array($match['status'], ['FT', 'AET', 'PEN']) ? 'opacity: 0.6;' : ''; ?>" <?php echo in_array($match['status'], ['FT', 'AET', 'PEN']) ? 'disabled' : ''; ?>>
                                                    <option value="NS" <?php echo $match['status'] === 'NS' ? 'selected' : ''; ?>>Chưa đá (NS)</option>
                                                    <option value="FT" <?php echo $match['status'] === 'FT' ? 'selected' : ''; ?>>Hết giờ (FT)</option>
                                                    <option value="AET" <?php echo $match['status'] === 'AET' ? 'selected' : ''; ?>>Hiệp phụ (AET)</option>
                                                    <option value="PEN" <?php echo $match['status'] === 'PEN' ? 'selected' : ''; ?>>Penalty (PEN)</option>
                                                </select>
                                                
                                                <?php if (in_array($match['status'], ['FT', 'AET', 'PEN'])): ?>
                                                    <button type="button" class="btn btn-secondary btn-sm edit-btn" style="padding: 6px 12px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: var(--text-main);" title="Chỉnh sửa tỷ số & trạng thái" onclick="enableMatchEditing(this)">
                                                    
                                                        <i class="fa-solid fa-pen-to-square"></i> Chỉnh sửa
                                                    </button>
                                                     <button type="submit" class="btn btn-primary btn-sm save-btn" style="padding: 6px 12px; font-size: 11px; display: none; align-items: center; gap: 4px;" title="Lưu tỷ số & trạng thái"><i class="fa-solid fa-floppy-disk"></i> Lưu</button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 6px 12px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" title="Cập nhật tỷ số & trạng thái">
                                                        <i class="fa-solid fa-check"></i> Cập nhật
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </td>
                                    <td class="pdf-exclude" style="text-align: center;">
                                        <div style="display: flex; gap: 5px; justify-content: center;">
                                            <a href="?edit_match=<?php echo $match['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 6px 10px; color: var(--primary);" title="Sửa thông tin trận đấu">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="?delete_match=<?php echo $match['id']; ?>" class="btn btn-danger btn-sm" style="padding: 6px 10px;" onclick="return confirm('Bạn có chắc chắn muốn xóa trận đấu này? Tất cả dự đoán liên quan cũng sẽ bị xóa!')" title="Xóa trận đấu">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- CỘT PHẢI: FORM THÊM / CẬP NHẬT TRẬN ĐẤU THỦ CÔNG -->
    <div>
        <div class="card" id="match-form">
            <div class="card-title">
                <i class="fa-solid <?php echo $edit_match ? 'fa-pen-to-square' : 'fa-circle-plus'; ?> text-primary"></i> 
                <?php echo $edit_match ? 'Cập Nhật Trận Đấu (ID: ' . $edit_match['id'] . ')' : 'Thêm Trận Đấu Mới'; ?>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_match">
                <?php if ($edit_match): ?>
                    <input type="hidden" name="is_edit" value="1">
                    <input type="hidden" name="original_id" value="<?php echo $edit_match['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="id">API ID Trận đấu <?php echo $edit_match ? '(Có thể sửa)' : '(Tự động tạo nếu để trống)'; ?></label>
                    <input type="number" name="id" id="id" class="form-control" value="<?php echo $edit_match ? htmlspecialchars($edit_match['id']) : ''; ?>" placeholder="Ví dụ: 102945">
                </div>
                
                <div class="form-group">
                    <label for="round">Vòng đấu (Round)</label>
                    <input type="text" name="round" id="round" class="form-control" value="<?php echo $edit_match ? htmlspecialchars($edit_match['round']) : 'Group Stage - 1'; ?>" placeholder="Ví dụ: Group Stage, Round of 16..." required>
                </div>
                
                <div class="grid-2col">
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <label for="home_team" style="margin-bottom: 0;">Đội Nhà (Home Team) *</label>
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 11px;" onclick="openFlagPicker('home')">
                                <i class="fa-solid fa-flag"></i> Chọn cờ
                            </button>
                        </div>
                        <input type="text" name="home_team" id="home_team" class="form-control" value="<?php echo $edit_match ? htmlspecialchars($edit_match['home_team']) : ''; ?>" placeholder="Ví dụ: Qatar" required>
                    </div>
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <label for="away_team" style="margin-bottom: 0;">Đội Khách (Away Team) *</label>
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 11px;" onclick="openFlagPicker('away')">
                                <i class="fa-solid fa-flag"></i> Chọn cờ
                            </button>
                        </div>
                        <input type="text" name="away_team" id="away_team" class="form-control" value="<?php echo $edit_match ? htmlspecialchars($edit_match['away_team']) : ''; ?>" placeholder="Ví dụ: Ecuador" required>
                    </div>
                </div>
                
                <div class="grid-2col">
                    <div class="form-group">
                        <label for="home_logo">Logo Đội Nhà (URL)</label>
                        <input type="text" name="home_logo" id="home_logo" class="form-control" value="<?php echo $edit_match ? htmlspecialchars($edit_match['home_logo']) : ''; ?>" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label for="away_logo">Logo Đội Khách (URL)</label>
                        <input type="text" name="away_logo" id="away_logo" class="form-control" value="<?php echo $edit_match ? htmlspecialchars($edit_match['away_logo']) : ''; ?>" placeholder="https://...">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="match_time">Thời gian bắt đầu (Giờ Việt Nam) *</label>
                    <input type="datetime-local" name="match_time" id="match_time" class="form-control" value="<?php echo $edit_match ? date('Y-m-d\TH:i', strtotime($edit_match['match_time'])) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="handicap">Kèo Chấp (Đội Nhà chấp Đội Khách) *</label>
                    <input type="number" name="handicap" id="handicap" class="form-control" step="0.25" value="<?php echo $edit_match ? htmlspecialchars($edit_match['handicap']) : '0'; ?>" required>
                    <small style="color: var(--text-muted); font-size: 11.5px; margin-top: 5px; display: block;">
                        * Nhập số dương (ví dụ: <code>1.5</code>) nếu Đội nhà chấp. Nhập số âm (ví dụ: <code>-0.5</code>) nếu Đội khách chấp. Nhập <code>0</code> nếu đồng banh.
                    </small>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <?php echo $edit_match ? 'Cập Nhật <i class="fa-solid fa-check"></i>' : 'Thêm Trận Đấu <i class="fa-solid fa-plus"></i>'; ?>
                    </button>
                    <?php if ($edit_match): ?>
                        <a href="matches.php" class="btn btn-secondary" style="text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center;">Hủy bỏ</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal chọn cờ quốc gia -->
<div id="flagPickerModal" class="flag-picker-overlay" onclick="closeFlagPicker(event)">
    <div class="flag-picker-modal" onclick="event.stopPropagation()">
        <div class="flag-picker-header">
            <h3><i class="fa-solid fa-flag"></i> Chọn Cờ Quốc Gia</h3>
            <button type="button" class="flag-picker-close" onclick="toggleFlagModal(false)">&times;</button>
        </div>
        <div class="flag-picker-body">
            <input type="text" id="flagSearchInput" class="flag-search" placeholder="Nhập tên quốc gia cần tìm... (ví dụ: Pháp, Brazil, qa)" oninput="filterFlags()">
            <div id="flagGrid" class="flag-grid">
                <!-- JS render flag items -->
            </div>
        </div>
    </div>
</div>

<script>
const flagCountries = [
    { name: "Qatar", code: "qa", search: "qatar qa" },
    { name: "Ecuador", code: "ec", search: "ecuador ec" },
    { name: "Senegal", code: "sn", search: "senegal sn" },
    { name: "Hà Lan", code: "nl", search: "ha lan netherlands holland nl" },
    { name: "Anh", code: "gb-eng", search: "anh england gb-eng" },
    { name: "Iran", code: "ir", search: "iran ir" },
    { name: "Mỹ", code: "us", search: "my usa united states us" },
    { name: "Xứ Wales", code: "gb-wls", search: "xu wales wales gb-wls" },
    { name: "Argentina", code: "ar", search: "argentina ar" },
    { name: "Ả Rập Xê Út", code: "sa", search: "a rap xe ut saudi arabia sa" },
    { name: "Mexico", code: "mx", search: "mexico mx" },
    { name: "Ba Lan", code: "pl", search: "ba lan poland pl" },
    { name: "Pháp", code: "fr", search: "phap france fr" },
    { name: "Úc", code: "au", search: "uc australia au" },
    { name: "Đan Mạch", code: "dk", search: "dan mach denmark dk" },
    { name: "Tunisia", code: "tn", search: "tunisia tn" },
    { name: "Tây Ban Nha", code: "es", search: "tay ban nha spain es" },
    { name: "Costa Rica", code: "cr", search: "costa rica cr" },
    { name: "Đức", code: "de", search: "duc germany de" },
    { name: "Nhật Bản", code: "jp", search: "nhat ban japan jp" },
    { name: "Bỉ", code: "be", search: "bi belgium be" },
    { name: "Canada", code: "ca", search: "canada ca" },
    { name: "Maroc", code: "ma", search: "maroc morocco ma" },
    { name: "Croatia", code: "hr", search: "croatia hr" },
    { name: "Brazil", code: "br", search: "brazil br" },
    { name: "Serbia", code: "rs", search: "serbia rs" },
    { name: "Thụy Sĩ", code: "ch", search: "thuy si switzerland ch" },
    { name: "Cameroon", code: "cm", search: "cameroon cm" },
    { name: "Bồ Đào Nha", code: "pt", search: "bo dao nha portugal pt" },
    { name: "Ghana", code: "gh", search: "ghana gh" },
    { name: "Uruguay", code: "uy", search: "uruguay uy" },
    { name: "Hàn Quốc", code: "kr", search: "han quoc south korea kr" },
    { name: "Việt Nam", code: "vn", search: "viet nam vietnam vn" },
    { name: "Ý", code: "it", search: "y italy it" },
    { name: "Thụy Điển", code: "se", search: "thuy dien sweden se" },
    { name: "Na Uy", code: "no", search: "na uy norway no" },
    { name: "Thổ Nhĩ Kỳ", code: "tr", search: "tho nhi ky turkey tr" },
    { name: "Ukraine", code: "ua", search: "ukraine ua" },
    { name: "Ai Cập", code: "eg", search: "ai cap egypt eg" },
    { name: "Colombia", code: "co", search: "colombia co" },
    { name: "Chile", code: "cl", search: "chile cl" },
    { name: "Peru", code: "pe", search: "peru pe" },
    { name: "Nigeria", code: "ng", search: "nigeria ng" },
    { name: "Algeria", code: "dz", search: "algeria dz" },
    { name: "Nga", code: "ru", search: "nga russia ru" },
    { name: "Trung Quốc", code: "cn", search: "trung quoc china cn" }
];

let targetTeamType = 'home';

function initFlagGrid() {
    const grid = document.getElementById('flagGrid');
    if (!grid) return;
    grid.innerHTML = '';
    
    flagCountries.forEach(country => {
        const item = document.createElement('div');
        item.className = 'flag-item';
        item.setAttribute('data-search', country.search.toLowerCase());
        item.onclick = () => selectFlag(country.name, country.code);
        
        const img = document.createElement('img');
        img.src = `https://flagcdn.com/w160/${country.code}.png`;
        img.alt = country.name;
        
        const span = document.createElement('span');
        span.innerText = country.name;
        
        item.appendChild(img);
        item.appendChild(span);
        grid.appendChild(item);
    });
}

function openFlagPicker(type) {
    targetTeamType = type;
    toggleFlagModal(true);
    document.getElementById('flagSearchInput').value = '';
    filterFlags();
    setTimeout(() => {
        document.getElementById('flagSearchInput').focus();
    }, 100);
}

function toggleFlagModal(show) {
    const modal = document.getElementById('flagPickerModal');
    if (!modal) return;
    if (show) {
        modal.classList.add('active');
    } else {
        modal.classList.remove('active');
    }
}

function closeFlagPicker(event) {
    if (event.target.id === 'flagPickerModal') {
        toggleFlagModal(false);
    }
}

function filterFlags() {
    const query = document.getElementById('flagSearchInput').value.toLowerCase().trim();
    const items = document.querySelectorAll('#flagGrid .flag-item');
    
    items.forEach(item => {
        const searchAttr = item.getAttribute('data-search');
        if (!query || searchAttr.includes(query)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function selectFlag(name, code) {
    const flagUrl = `https://flagcdn.com/w160/${code}.png`;
    if (targetTeamType === 'home') {
        document.getElementById('home_team').value = name;
        document.getElementById('home_logo').value = flagUrl;
    } else {
        document.getElementById('away_team').value = name;
        document.getElementById('away_logo').value = flagUrl;
    }
    toggleFlagModal(false);
}

function setupAutoFlagMatch() {
    const homeInput = document.getElementById('home_team');
    const awayInput = document.getElementById('away_team');
    
    function matchAndSet(inputEl, logoElId) {
        const value = inputEl.value.trim().toLowerCase();
        if (value.length < 2) return;
        
        const match = flagCountries.find(c => 
            c.name.toLowerCase() === value || 
            c.search.split(' ').includes(value)
        );
        
        if (match) {
            const logoInput = document.getElementById(logoElId);
            logoInput.value = `https://flagcdn.com/w160/${match.code}.png`;
        }
    }
    
    if (homeInput) {
        homeInput.addEventListener('input', () => matchAndSet(homeInput, 'home_logo'));
    }
    if (awayInput) {
        awayInput.addEventListener('input', () => matchAndSet(awayInput, 'away_logo'));
    }
}

function enableMatchEditing(button) {
    const form = button.closest('form');
    const inputs = form.querySelectorAll('input[type="number"], select[name="status"]');
    for (let i = 0; i < inputs.length; i++) {
        inputs[i].disabled = false;
        inputs[i].removeAttribute('disabled');
        inputs[i].style.opacity = '1';
    }
    button.style.display = 'none';
    const saveBtn = form.querySelector('.save-btn');
    if (saveBtn) {
        saveBtn.style.display = 'inline-flex';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initFlagGrid();
    setupAutoFlagMatch();
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
