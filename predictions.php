<?php
// predictions.php
$page_title = "Lịch sử dự đoán";
require_once __DIR__ . '/includes/header.php';
require_login();

$user_id = $_SESSION['user_id'];
$reveal_real_names = (int)get_setting('reveal_real_names', 0);

// Lấy danh sách tất cả các trận đấu có trong hệ thống để lọc/xem chi tiết
$sql_matches = "SELECT id, home_team, away_team, match_time, status, home_score, away_score, round FROM matches ORDER BY match_time DESC";
$matches = $pdo->query($sql_matches)->fetchAll();

// Trận đấu đang chọn để xem dự đoán nhóm (mặc định là trận mới nhất hoặc trận gần nhất)
$selected_match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
if ($selected_match_id === 0 && !empty($matches)) {
    // Tìm trận sắp đá gần nhất hoặc trận mới kết thúc
    $selected_match_id = $matches[0]['id'];
    foreach ($matches as $m) {
        if (strtotime($m['match_time']) > time()) {
            $selected_match_id = $m['id'];
        }
    }
}

// Lấy thông tin chi tiết của trận đấu đang chọn
$selected_match = null;
foreach ($matches as $m) {
    if ($m['id'] == $selected_match_id) {
        $selected_match = $m;
        break;
    }
}

// Lấy dự đoán của tất cả thành viên cho trận đấu đang chọn
$group_predictions = [];
if ($selected_match) {
    $is_locked = is_match_locked($selected_match['match_time']);
    
    // Lấy tất cả user (role = user) và JOIN với dự đoán của họ cho trận đấu này
    $sql_group = "SELECT u.id as user_id, u.nickname, u.real_name, 
                         p.predicted_home_score, p.predicted_away_score, p.points_awarded, p.created_at
                  FROM users u
                  LEFT JOIN predictions p ON u.id = p.user_id AND p.match_id = :match_id
                  WHERE u.role = 'user'
                  ORDER BY u.nickname ASC";
    $stmt_group = $pdo->prepare($sql_group);
    $stmt_group->execute(['match_id' => $selected_match_id]);
    $group_predictions = $stmt_group->fetchAll();
}

// Lấy toàn bộ lịch sử dự đoán của cá nhân người đang đăng nhập
$sql_personal = "SELECT m.home_team, m.away_team, m.home_logo, m.away_logo, m.match_time, m.status, m.home_score as actual_home, m.away_score as actual_away, m.round,
                        p.predicted_home_score, p.predicted_away_score, p.points_awarded
                 FROM matches m
                 LEFT JOIN predictions p ON m.id = p.match_id AND p.user_id = :user_id
                 ORDER BY m.match_time DESC";
$stmt_pers = $pdo->prepare($sql_personal);
$stmt_pers->execute(['user_id' => $user_id]);
$personal_history = $stmt_pers->fetchAll();
?>

<div class="dashboard-grid">
    <!-- CỘT TRÁI: XEM CHI TIẾT DỰ ĐOÁN NHÓM THEO TRẬN -->
    <div>
        <div class="card">
            <div class="card-title">
                <i class="fa-solid fa-users text-primary"></i> Dự Đoán Của Cả Nhóm
            </div>
            
            <!-- Bộ lọc trận đấu -->
            <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
                <label style="white-space: nowrap; font-weight: 500; color: var(--text-muted);">Chọn trận đấu:</label>
                <select name="match_id" class="form-control" onchange="this.form.submit()" style="flex: 1;">
                    <?php foreach ($matches as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $m['id'] == $selected_match_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['home_team'] . ' vs ' . $m['away_team']); ?> 
                            (<?php echo date('d/m H:i', strtotime($m['match_time'])); ?>) 
                            <?php echo in_array($m['status'], ['FT', 'AET', 'PEN']) ? '- Đã kết thúc' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <?php if (!$selected_match): ?>
                <p style="text-align: center; color: var(--text-muted);">Không có trận đấu nào được chọn.</p>
            <?php else: 
                $is_selected_locked = is_match_locked($selected_match['match_time']);
            ?>
                <!-- Khung thông tin trận đấu -->
                <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php echo htmlspecialchars($selected_match['round']); ?>
                    </div>
                    <div style="font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 5px;">
                        <?php echo htmlspecialchars($selected_match['home_team']); ?> 
                        <span style="color: var(--primary); margin: 0 10px;">
                            <?php 
                            if (in_array($selected_match['status'], ['FT', 'AET', 'PEN'])) {
                                echo $selected_match['home_score'] . ' - ' . $selected_match['away_score'];
                            } else {
                                echo 'VS';
                            }
                            ?>
                        </span>
                        <?php echo htmlspecialchars($selected_match['away_team']); ?>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted);">
                        <i class="fa-solid fa-calendar-days"></i> <?php echo format_match_time($selected_match['match_time']); ?>
                    </div>
                    <?php if (!$is_selected_locked): ?>
                        <div style="margin-top: 10px; display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: rgba(0, 255, 170, 0.1); border: 1px solid var(--accent); border-radius: 20px; font-size: 12px; color: var(--accent);">
                            <i class="fa-solid fa-eye-slash"></i> Tỷ số dự đoán của nhóm đang được ẩn để giữ bí mật
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 10px; display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); border-radius: 20px; font-size: 12px; color: var(--text-muted);">
                            <i class="fa-solid fa-eye"></i> Trận đấu đã khóa - Đã công khai dự đoán của cả nhóm
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Bảng dự đoán của nhóm -->
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Thành viên</th>
                                <th style="text-align: center;">Dự đoán</th>
                                <th style="text-align: right;">Điểm thưởng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group_predictions as $gp): 
                                $is_own = ($gp['user_id'] == $user_id);
                            ?>
                                <tr style="<?php echo $is_own ? 'background: rgba(212, 175, 55, 0.05);' : ''; ?>">
                                    <td>
                                        <div style="font-weight: 600;">
                                            <?php echo htmlspecialchars($gp['nickname']); ?>
                                            <?php if ($is_own): ?>
                                                <span style="font-size: 10px; background: var(--primary); color: #000; padding: 1px 4px; border-radius: 3px; font-weight: bold; margin-left: 5px;">BẠN</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($reveal_real_names == 1 || is_admin() || $is_own): ?>
                                            <div style="font-size: 11px; color: var(--text-muted);">
                                                <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($gp['real_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($gp['predicted_home_score'] === null): ?>
                                            <span style="color: var(--accent-red); font-size: 14px;"><i class="fa-solid fa-circle-minus"></i> Không dự đoán</span>
                                        <?php else: ?>
                                            <?php if ($is_selected_locked || $is_own || is_admin()): ?>
                                                <strong style="font-size: 16px; color: var(--accent);">
                                                    <?php echo $gp['predicted_home_score'] . ' - ' . $gp['predicted_away_score']; ?>
                                                </strong>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 13px;">
                                                    <i class="fa-solid fa-eye-slash" style="font-size: 11px;"></i> Đã đoán (đang ẩn)
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-weight: bold;">
                                        <?php 
                                        if ($gp['points_awarded'] !== null) {
                                            echo '<span style="color: var(--primary);">+' . $gp['points_awarded'] . 'đ</span>';
                                        } elseif (in_array($selected_match['status'], ['FT', 'AET', 'PEN'])) {
                                            echo '<span style="color: var(--accent-red);">0đ</span>';
                                        } else {
                                            echo '<span style="color: var(--text-muted); font-weight: normal;">Chờ đấu</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- CỘT PHẢI: LỊCH SỬ DỰ ĐOÁN CÁ NHÂN -->
    <div>
        <div class="card">
            <div class="card-title">
                <i class="fa-solid fa-clock-rotate-left text-primary"></i> Lịch Sử Dự Đoán Của Bạn
            </div>
            
            <?php if (empty($personal_history)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 20px;">Bạn chưa thực hiện dự đoán nào.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px; max-height: 600px; overflow-y: auto; padding-right: 5px;">
                    <?php foreach ($personal_history as $ph): 
                        $ph_locked = is_match_locked($ph['match_time']);
                        $is_finished = in_array($ph['status'], ['FT', 'AET', 'PEN']);
                    ?>
                        <div style="background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); border-radius: 10px; padding: 12px; position: relative;">
                            <!-- Points label -->
                            <?php if ($ph['points_awarded'] !== null): ?>
                                <span style="position: absolute; top: 12px; right: 12px; background: var(--primary-grad); color: #000; font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px;">
                                    +<?php echo $ph['points_awarded']; ?>đ
                                </span>
                            <?php endif; ?>
                            
                            <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 6px;">
                                <?php echo htmlspecialchars($ph['round']); ?>
                            </div>
                            
                            <div style="font-weight: 600; font-size: 14px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; padding-right: 50px;">
                                <span><?php echo htmlspecialchars($ph['home_team'] . ' - ' . $ph['away_team']); ?></span>
                            </div>
                            
                            <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 8px;">
                                <?php echo date('H:i d/m/Y', strtotime($ph['match_time'])); ?>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02); padding: 8px; border-radius: 6px; font-size: 12px;">
                                <div>
                                    Dự đoán: 
                                    <strong>
                                        <?php 
                                        if ($ph['predicted_home_score'] !== null) {
                                            echo $ph['predicted_home_score'] . ' - ' . $ph['predicted_away_score'];
                                        } else {
                                            echo '<span style="color: var(--accent-red);">Chưa đoán</span>';
                                        }
                                        ?>
                                    </strong>
                                </div>
                                
                                <div>
                                    Tỷ số thật: 
                                    <strong style="color: var(--primary);">
                                        <?php 
                                        if ($is_finished) {
                                            echo $ph['actual_home'] . ' - ' . $ph['actual_away'];
                                        } else {
                                            echo 'Chưa đá';
                                        }
                                        ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
