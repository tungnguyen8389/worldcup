<?php
// index.php
$page_title = "Bảng điều khiển";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/pdf_templates.php';
require_login();

$user_id = $_SESSION['user_id'];
$points_exact = (int)get_setting('point_exact_score', 3);
$reveal_real_names = (int)get_setting('reveal_real_names', 0);

// Lấy tổng số lượng user tham gia (role = 'user')
$total_users_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// 1. Lấy danh sách trận đấu đang và sắp diễn ra cùng số lượng người đã dự đoán
$sql_upcoming = "SELECT m.*, p.predicted_team, p.points_awarded,
                        (SELECT COUNT(*) FROM predictions pr JOIN users u ON pr.user_id = u.id WHERE pr.match_id = m.id AND u.role = 'user') as prediction_count
                 FROM matches m 
                 LEFT JOIN predictions p ON m.id = p.match_id AND p.user_id = :user_id 
                 WHERE m.status NOT IN ('FT', 'AET', 'PEN') 
                 ORDER BY m.match_time ASC 
                 LIMIT 6";
$stmt_up = $pdo->prepare($sql_upcoming);
$stmt_up->execute(['user_id' => $user_id]);
$upcoming_matches = $stmt_up->fetchAll();

// 2. Lấy danh sách trận đấu kết thúc gần đây cùng số lượng người đã dự đoán
$sql_finished = "SELECT m.*, p.predicted_team, p.points_awarded,
                        (SELECT COUNT(*) FROM predictions pr JOIN users u ON pr.user_id = u.id WHERE pr.match_id = m.id AND u.role = 'user') as prediction_count
                 FROM matches m 
                 LEFT JOIN predictions p ON m.id = p.match_id AND p.user_id = :user_id 
                 WHERE m.status IN ('FT', 'AET', 'PEN') 
                 ORDER BY m.match_time DESC 
                 LIMIT 4";
$stmt_fin = $pdo->prepare($sql_finished);
$stmt_fin->execute(['user_id' => $user_id]);
$finished_matches = $stmt_fin->fetchAll();

// 3. Lấy danh sách bảng xếp hạng hiện tại (tính toán realtime để chính xác tuyệt đối)
$sql_leaderboard = "SELECT 
                        u.id as user_id,
                        u.nickname,
                        u.real_name,
                        COALESCE(SUM(p.points_awarded), 0) as total_points,
                        SUM(CASE WHEN p.points_awarded = 1 THEN 1 ELSE 0 END) as win_count,
                        SUM(CASE WHEN p.points_awarded = -1 THEN 1 ELSE 0 END) as loss_count,
                        COALESCE(SUM(UNIX_TIMESTAMP(p.created_at)), 0) as total_pred_time
                    FROM users u
                    LEFT JOIN predictions p ON u.id = p.user_id AND p.points_awarded IS NOT NULL
                    WHERE u.role = 'user'
                    GROUP BY u.id
                    ORDER BY total_points DESC, win_count DESC, total_pred_time ASC, u.nickname ASC";
$stmt_lead = $pdo->prepare($sql_leaderboard);
$stmt_lead->execute();
$leaderboard = $stmt_lead->fetchAll();

// Gán thứ hạng cho bảng xếp hạng hiện tại
$ranked_leaderboard = [];
$rank = 1;
$prev_points = null;
$prev_wins = null;
$prev_pred_time = null;
foreach ($leaderboard as $index => $row) {
    // Nếu có sự khác biệt về điểm số, số trận thắng, hoặc tổng thời gian dự đoán thì tăng hạng
    if ($prev_points !== null && (
        $row['total_points'] < $prev_points || 
        $row['win_count'] < $prev_wins || 
        $row['total_pred_time'] > $prev_pred_time
    )) {
        $rank = $index + 1;
    }
    $row['rank'] = $rank;
    $prev_points = $row['total_points'];
    $prev_wins = $row['win_count'];
    $prev_pred_time = $row['total_pred_time'];
    $ranked_leaderboard[] = $row;
}

// 4. Tính toán xu hướng tăng giảm hạng (so với ngày hôm trước có dữ liệu hạng)
$prev_ranks = [];
try {
    // Tìm ngày xếp hạng gần nhất trước ngày hôm nay
    $stmt_prev_date = $pdo->query("SELECT MAX(ranking_date) FROM daily_rankings WHERE ranking_date < CURRENT_DATE()");
    $prev_date = $stmt_prev_date->fetchColumn();
    
    if ($prev_date) {
        $stmt_prev_ranks = $pdo->prepare("SELECT user_id, rank_position FROM daily_rankings WHERE ranking_date = ?");
        $stmt_prev_ranks->execute([$prev_date]);
        $prev_ranks = $stmt_prev_ranks->fetchAll(PDO::FETCH_KEY_PAIR);
    }
} catch (PDOException $e) {
    // Bỏ qua nếu bảng trống
}

// 5. Chuẩn bị dữ liệu biểu đồ đường đua Chart.js cho Top 5
$chart_json = '';
$top_5_ids = [];
$top_5_names = [];
for ($i = 0; $i < min(5, count($ranked_leaderboard)); $i++) {
    $top_5_ids[] = $ranked_leaderboard[$i]['user_id'];
    $top_5_names[$ranked_leaderboard[$i]['user_id']] = $ranked_leaderboard[$i]['nickname'];
}

if (!empty($top_5_ids)) {
    try {
        // Lấy tất cả các ngày đã có xếp hạng trong daily_rankings
        $stmt_dates = $pdo->query("SELECT DISTINCT ranking_date FROM daily_rankings ORDER BY ranking_date ASC");
        $dates = $stmt_dates->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($dates) > 0) {
            // Lấy điểm số của top 5 qua các ngày
            $in_clause = implode(',', array_fill(0, count($top_5_ids), '?'));
            $stmt_history = $pdo->prepare("SELECT user_id, ranking_date, total_points FROM daily_rankings 
                                           WHERE user_id IN ($in_clause) 
                                           ORDER BY ranking_date ASC");
            $stmt_history->execute($top_5_ids);
            $history_data = $stmt_history->fetchAll();
            
            // Xây dựng cấu trúc dataset cho Chart.js
            $datasets = [];
            $colors = [
                '#d4af37', // Vàng FIFA
                '#00ffaa', // Xanh Neon
                '#ff3e6c', // Hồng Neon
                '#00d2ff', // Xanh Cyan
                '#ff9f43'  // Cam Neon
            ];
            
            // Khởi tạo mảng điểm rỗng cho từng user trên các ngày
            $user_points_by_date = [];
            foreach ($top_5_ids as $uid) {
                $user_points_by_date[$uid] = array_fill(0, count($dates), 0);
            }
            
            // Điền dữ liệu điểm thực tế
            foreach ($history_data as $h) {
                $date_index = array_search($h['ranking_date'], $dates);
                if ($date_index !== false) {
                    $user_points_by_date[$h['user_id']][$date_index] = (int)$h['total_points'];
                }
            }
            
            $dataset_index = 0;
            foreach ($top_5_ids as $uid) {
                $formatted_dates = array_map(function($d) { return date('d/m', strtotime($d)); }, $dates);
                
                $datasets[] = [
                    'label' => $top_5_names[$uid],
                    'data' => $user_points_by_date[$uid],
                    'borderColor' => $colors[$dataset_index % count($colors)],
                    'backgroundColor' => 'transparent',
                    'borderWidth' => 3,
                    'pointBackgroundColor' => $colors[$dataset_index % count($colors)],
                    'pointRadius' => 4,
                    'tension' => 0.2
                ];
                $dataset_index++;
            }
            
            $chart_json = json_encode([
                'labels' => $formatted_dates,
                'datasets' => $datasets
            ]);
        }
    } catch (PDOException $e) {
        // Biểu đồ trống
    }
}
?>

<!-- Bảng Quỹ Liên Hoan Hài Hước -->
<div class="card party-fund-card" style="margin-bottom: 25px; background: linear-gradient(135deg, rgba(20, 45, 30, 0.45) 0%, rgba(12, 18, 14, 0.7) 100%); border: 1px solid rgba(212, 175, 55, 0.2); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="hotpot-icon-wrapper" style="font-size: 28px; color: #ff6b6b; animation: float 3s ease-in-out infinite;">
                <i class="fa-solid fa-fire-burner"></i>
            </div>
            <div>
                <h3 style="color: var(--primary); font-family: 'Outfit'; font-weight: 800; font-size: 17px; margin: 0; text-transform: uppercase; letter-spacing: 0.5px;">NỒI LẨU TIÊN TRI - QUỸ LIÊN HOAN NHÓM</h3>
                <p style="color: var(--text-muted); font-size: 12.5px; margin: 2px 0 0 0;">Góp sức cho nồi lẩu World Cup thêm tôm thịt!</p>
            </div>
        </div>
        <div style="text-align: right;">
            <span style="font-size: 12px; color: var(--text-muted);">Tổng Quỹ Hiện Tại:</span>
            <div style="font-size: 20px; font-weight: 800; color: var(--accent); font-family: 'Outfit';">
                <?php echo number_format((int)get_setting('party_fund_total', 1500000)); ?> VNĐ
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <?php 
        $fund_total = (int)get_setting('party_fund_total', 1500000);
        $fund_target = (int)get_setting('party_fund_target', 3000000);
        $percent = min(100, round(($fund_total / $fund_target) * 100));
    ?>
    <div style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: var(--text-muted); margin-bottom: 6px;">
            <span>Tiến trình nồi lẩu: <strong><?php echo $percent; ?>%</strong></span>
            <span>Mục tiêu lẩu đầy đủ: <strong><?php echo number_format($fund_target); ?> VNĐ</strong></span>
        </div>
        <div class="progress-bar-container" style="width: 100%; height: 10px; background: rgba(255, 255, 255, 0.03); border-radius: 10px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05);">
            <div class="progress-bar-fill" style="width: <?php echo $percent; ?>%; height: 100%; background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%); border-radius: 10px; box-shadow: 0 0 10px rgba(0, 255, 170, 0.3);"></div>
        </div>
    </div>

    <!-- Danh sách Mạnh Thường Quân -->
    <div style="border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 15px;">
        <div style="font-size: 12px; font-weight: bold; color: var(--primary); text-transform: uppercase; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; letter-spacing: 0.5px;">
            <i class="fa-solid fa-crown" style="color: #ff9f43;"></i> Bảng Danh Dự Mạnh Thường Quân (Đóng Góp Nồi Lẩu)
        </div>
        <div class="sponsors-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px;">
            <?php 
                $sponsors_raw = get_setting('party_fund_sponsors', "Anh Tuấn - 500,000 (Đại Gia Lẩu Bò)\nChị Thảo - 300,000 (Nữ Hoàng Tôm Sú)\nĐức Huy - 200,000 (Chúa Tể Rau Muống)\nMinh Quân - 200,000 (Thần Cồn)");
                $sponsors_lines = explode("\n", str_replace("\r", "", trim($sponsors_raw)));
                $rank_badges = ['🥇', '🥈', '🥉', '🏅'];
                $idx = 0;
                foreach ($sponsors_lines as $line) {
                    if (empty(trim($line))) continue;
                    $parts = explode('-', $line);
                    $name = trim($parts[0] ?? 'Ẩn danh');
                    $rest = trim($parts[1] ?? '0');
                    
                    $amount_str = '';
                    $title = '';
                    if (preg_match('/([\d,]+)\s*(?:\((.*)\))?/', $rest, $matches)) {
                        $amount_str = trim($matches[1]);
                        $title = trim($matches[2] ?? '');
                    } else {
                        $amount_str = $rest;
                    }
                    
                    $badge = $rank_badges[min(3, $idx)];
                    $idx++;
            ?>
                <div class="sponsor-card" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255,255,255,0.03); padding: 10px 12px; border-radius: 8px; display: flex; flex-direction: column; justify-content: center; position: relative;">
                    <div style="font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 6px; color: var(--text-main);">
                        <span><?php echo $badge; ?></span> <?php echo htmlspecialchars($name); ?>
                    </div>
                    <div style="font-size: 12px; color: var(--accent); font-weight: bold; margin-top: 2px;">
                        +<?php echo htmlspecialchars($amount_str); ?> VNĐ
                    </div>
                    <?php if ($title): ?>
                        <div style="font-size: 10.5px; color: #ff9f43; font-style: italic; margin-top: 5px; background: rgba(255, 159, 67, 0.08); padding: 2px 6px; border-radius: 4px; width: fit-content; border: 1px solid rgba(255, 159, 67, 0.15);">
                            <?php echo htmlspecialchars($title); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<style>
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}
</style>

<div class="dashboard-grid">
    <!-- CỘT TRÁI: DỰ ĐOÁN VÀ KẾT QUẢ -->
    <div>
        <!-- Trận đấu sắp diễn ra -->
        <div class="card">
            <div class="card-title">
                <i class="fa-solid fa-gamepad text-primary"></i> Trận Đấu Đang & Sắp Diễn Ra
            </div>
            
            <?php if (empty($upcoming_matches)): ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <i class="fa-solid fa-calendar-xmark" style="font-size: 48px; margin-bottom: 15px; color: var(--glass-border-focus);"></i>
                    <p>Hiện không có trận đấu nào sắp diễn ra hoặc chưa đoán.</p>
                    <?php if (is_admin()): ?>
                        <a href="admin/dashboard.php" class="btn btn-primary btn-sm" style="margin-top: 15px;">
                            <i class="fa-solid fa-arrows-rotate"></i> Đồng bộ trận đấu ngay
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="matches-grid">
                    <?php foreach ($upcoming_matches as $match): 
                        $is_locked = is_match_locked($match['match_time']);
                        $has_predicted = !empty($match['predicted_team']);
                    ?>
                        <div class="match-card <?php echo $is_locked ? 'locked' : ''; ?>">
                            <!-- Thông tin giải đấu / Trạng thái khóa -->
                            <div class="match-meta">
                                <span><?php echo htmlspecialchars($match['round']); ?></span>
                                <span class="countdown-timer" data-time="<?php echo $match['match_time']; ?>">
                                    <?php if ($is_locked): ?>
                                        <span class="text-danger"><i class="fa-solid fa-lock"></i> Đã khóa</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <!-- Form dự đoán chọn đội -->
                            <form class="prediction-form" data-match-id="<?php echo $match['id']; ?>">
                                <!-- Kèo chấp -->
                                <div style="text-align: center; margin-bottom: 12px;">
                                    <span style="font-size: 11px; background: rgba(212, 175, 55, 0.1); color: var(--primary); padding: 4px 10px; border-radius: 20px; font-weight: 600; border: 1px solid rgba(212, 175, 55, 0.2); display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-scale-balanced" style="font-size: 10px;"></i> Tỷ lệ chấp vui: 
                                        <strong>
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
                                    </span>
                                </div>
                                
                                <div class="match-teams-selector">
                                    <button type="button" class="team-select-btn <?php echo ($match['predicted_team'] === 'home') ? 'selected' : ''; ?>" data-team="home" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                        <img src="<?php echo htmlspecialchars($match['home_logo'] ?: 'assets/images/team_placeholder.png'); ?>" class="team-logo" alt="">
                                        <span class="team-name" title="<?php echo htmlspecialchars($match['home_team']); ?>"><?php echo htmlspecialchars($match['home_team']); ?></span>
                                        <span class="select-badge"><i class="fa-solid fa-check"></i> Chọn</span>
                                    </button>
                                    
                                    <div class="match-vs-selector">
                                        <span class="vs-text">VS</span>
                                        <span class="vs-time"><?php echo date('H:i d/m', strtotime($match['match_time'])); ?></span>
                                    </div>
                                    
                                    <button type="button" class="team-select-btn <?php echo ($match['predicted_team'] === 'away') ? 'selected' : ''; ?>" data-team="away" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                        <img src="<?php echo htmlspecialchars($match['away_logo'] ?: 'assets/images/team_placeholder.png'); ?>" class="team-logo" alt="">
                                        <span class="team-name" title="<?php echo htmlspecialchars($match['away_team']); ?>"><?php echo htmlspecialchars($match['away_team']); ?></span>
                                        <span class="select-badge"><i class="fa-solid fa-check"></i> Chọn</span>
                                    </button>
                                </div>
                                
                                <input type="hidden" name="predicted_team" class="predicted-team-input" value="<?php echo htmlspecialchars($match['predicted_team'] ?: ''); ?>">
                                
                                <!-- Thống kê số người dự đoán -->
                                <div style="font-size: 11.5px; color: var(--text-muted); text-align: center; margin: 10px 0; background: rgba(0, 0, 0, 0.02); padding: 4px 8px; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 6px; box-sizing: border-box;">
                                    <i class="fa-solid fa-chart-simple" style="color: var(--primary);"></i>
                                    <span>Đã dự đoán: <strong><?php echo $match['prediction_count']; ?></strong>/<strong><?php echo $total_users_count; ?></strong> thành viên</span>
                                </div>
                                
                                <div class="pred-score-text pred-status-text" style="<?php echo $has_predicted ? 'border-color: rgba(0, 255, 170, 0.3); border-style: solid;' : ''; ?>">
                                    <?php if ($has_predicted): 
                                        $selected_team_name = ($match['predicted_team'] === 'home') ? $match['home_team'] : $match['away_team'];
                                    ?>
                                        Bạn đã chọn: <strong style="color: var(--accent); font-size: 15px;"><?php echo htmlspecialchars($selected_team_name); ?></strong>
                                    <?php else: ?>
                                        Chưa có dự đoán
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!$is_locked): ?>
                                    <button type="submit" class="btn btn-primary btn-sm btn-predict" style="width: 100%; margin-top: 10px;" <?php echo !$has_predicted ? 'disabled' : ''; ?>>
                                        <?php echo $has_predicted ? 'Cập nhật <i class="fa-solid fa-check"></i>' : 'Lưu dự đoán <i class="fa-solid fa-floppy-disk"></i>'; ?>
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Trận đấu đã kết thúc -->
        <div class="card">
            <div class="card-title">
                <i class="fa-solid fa-circle-check text-primary"></i> Kết Quả Các Trận Gần Đây
            </div>
            
            <?php if (empty($finished_matches)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 20px;">Chưa có trận đấu nào kết thúc.</p>
            <?php else: ?>
                <div class="matches-grid">
                    <?php foreach ($finished_matches as $match): 
                        $points = $match['points_awarded'];
                    ?>
                        <div class="match-card locked" style="border-color: rgba(255, 255, 255, 0.04);">
                            <!-- Điểm được thưởng -->
                            <?php if ($points !== null): ?>
                                <span class="pred-points-badge <?php echo $points == 0 ? 'zero' : ($points < 0 ? 'negative' : ''); ?>" style="<?php echo $points < 0 ? 'background: linear-gradient(135deg, #ff6b8b 0%, var(--accent-red) 100%) !important; box-shadow: 0 4px 10px rgba(217, 56, 58, 0.25) !important;' : ''; ?>">
                                    <?php echo $points > 0 ? '+' : ''; ?><?php echo $points; ?> điểm
                                </span>
                            <?php endif; ?>
                            
                            <div class="match-meta">
                                <span><?php echo htmlspecialchars($match['round']); ?></span>
                                <span class="match-status status-ft">KẾT THÚC</span>
                            </div>
                            
                            <div class="match-teams">
                                <div class="team-box">
                                    <img src="<?php echo htmlspecialchars($match['home_logo']); ?>" class="team-logo" alt="">
                                    <span class="team-name"><?php echo htmlspecialchars($match['home_team']); ?></span>
                                </div>
                                
                                <div class="match-vs">
                                    <span class="actual-score"><?php echo $match['home_score'] . '-' . $match['away_score']; ?></span>
                                    <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">
                                        <?php echo date('d/m H:i', strtotime($match['match_time'])); ?>
                                    </span>
                                </div>
                                
                                <div class="team-box">
                                    <img src="<?php echo htmlspecialchars($match['away_logo']); ?>" class="team-logo" alt="">
                                    <span class="team-name"><?php echo htmlspecialchars($match['away_team']); ?></span>
                                </div>
                            </div>
                            
                            <!-- Kèo chấp -->
                            <div style="text-align: center; margin-top: 8px; font-size: 11px; color: var(--text-muted);">
                                Tỷ lệ chấp vui: 
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
                            
                            <!-- Thống kê số người dự đoán -->
                            <div style="font-size: 11.5px; color: var(--text-muted); text-align: center; margin: 8px 0; background: rgba(0, 0, 0, 0.02); padding: 4px 8px; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 6px; box-sizing: border-box;">
                                <i class="fa-solid fa-chart-simple" style="color: var(--primary);"></i>
                                <span>Đã dự đoán: <strong><?php echo $match['prediction_count']; ?></strong>/<strong><?php echo $total_users_count; ?></strong> thành viên</span>
                            </div>
                            
                            <div class="pred-score-text" style="background: rgba(255,255,255,0.01);">
                                <?php if (!empty($match['predicted_team'])): 
                                    $sel_team = $match['predicted_team'];
                                    $sel_team_name = ($sel_team === 'home') ? $match['home_team'] : $match['away_team'];
                                ?>
                                    Bạn chọn: <strong><?php echo htmlspecialchars($sel_team_name); ?></strong> 
                                    (<?php 
                                        if ($points == 1) {
                                            echo '<span style="color: var(--accent); font-weight: bold;">Thắng +1đ</span>';
                                        } elseif ($points == -1) {
                                            echo '<span style="color: var(--accent-red); font-weight: bold;">Thua -1đ</span>';
                                        } else {
                                            echo '<span style="color: var(--text-muted); font-weight: bold;">Hòa 0đ</span>';
                                        }
                                    ?>)
                                <?php else: ?>
                                    <span style="color: var(--accent-red);">Bạn không dự đoán trận này</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CỘT PHẢI: BẢNG XẾP HẠNG & BIỂU ĐỒ ĐƯỜNG ĐUA -->
    <div>
        <!-- Bảng xếp hạng -->
        <div class="card" id="leaderboard-card">
            <div class="card-title" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                <span><i class="fa-solid fa-ranking-star text-primary"></i> Bảng Xếp Hạng Vui</span>
                <button onclick="exportToPDF('pdf-leaderboard-template', 'Bang_Xep_Hang_WorldCup')" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 12px; margin-left: auto;">
                    <i class="fa-solid fa-file-pdf"></i> Xuất PDF
                </button>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">Hạng</th>
                            <th>Thành viên</th>
                            <th style="text-align: center; width: 95px;">Đúng/Sai</th>
                            <th style="text-align: right; width: 95px;">Tổng Điểm</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ranked_leaderboard)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted);">Chưa có thành viên nào đăng ký tham gia.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ranked_leaderboard as $user): 
                                $uid = $user['user_id'];
                                $rank_pos = $user['rank'];
                                $prev_pos = isset($prev_ranks[$uid]) ? $prev_ranks[$uid] : null;
                                
                                // Xác định icon xu hướng tăng giảm hạng
                                $trend_icon = '';
                                if ($prev_pos !== null) {
                                    if ($rank_pos < $prev_pos) {
                                        $trend_icon = '<span class="trend-up" title="Tăng ' . ($prev_pos - $rank_pos) . ' hạng"><i class="fa-solid fa-caret-up"></i></span>';
                                    } elseif ($rank_pos > $prev_pos) {
                                        $trend_icon = '<span class="trend-down" title="Giảm ' . ($rank_pos - $prev_pos) . ' hạng"><i class="fa-solid fa-caret-down"></i></span>';
                                    } else {
                                        $trend_icon = '<span class="trend-same" title="Giữ nguyên hạng"><i class="fa-solid fa-minus" style="font-size: 10px;"></i></span>';
                                    }
                                } else {
                                    $trend_icon = '<span class="trend-same"><i class="fa-solid fa-minus" style="font-size: 10px;"></i></span>';
                                }
                                
                                // Style cho huy hiệu Top 3
                                $badge_class = 'rank-other';
                                if ($rank_pos == 1) $badge_class = 'rank-1';
                                elseif ($rank_pos == 2) $badge_class = 'rank-2';
                                elseif ($rank_pos == 3) $badge_class = 'rank-3';
                            ?>
                                <tr style="<?php echo $uid == $user_id ? 'background: rgba(212, 175, 55, 0.08); border-left: 3px solid var(--primary);' : ''; ?>">
                                    <td style="text-align: center;">
                                        <span class="rank-badge <?php echo $badge_class; ?>"><?php echo $rank_pos; ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                            <?php echo htmlspecialchars($user['nickname']); ?>
                                            <?php echo $trend_icon; ?>
                                        </div>
                                        <?php if ($reveal_real_names == 1 || is_admin() || $uid == $user_id): ?>
                                            <div style="font-size: 12px; color: var(--text-muted); font-weight: normal; margin-top: 2px;">
                                                <i class="fa-solid fa-id-card-clip" style="font-size: 10px;"></i> <?php echo htmlspecialchars($user['real_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; font-weight: 500; color: var(--text-muted);">
                                        <span style="color: var(--accent); font-weight: 600;"><?php echo $user['win_count']; ?></span>
                                        /
                                        <span style="color: var(--accent-red); font-weight: 600;"><?php echo $user['loss_count']; ?></span>
                                    </td>
                                    <td style="text-align: right; font-weight: 800; font-size: 16px; color: var(--primary);">
                                        <?php echo $user['total_points']; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Biểu đồ Đường đua điểm số -->
        <div class="card">
            <div class="card-title">
                <i class="fa-solid fa-chart-line text-primary"></i> Đường Đua Top 5
            </div>
            
            <?php if (empty($chart_json)): ?>
                <div style="text-align: center; padding: 30px 10px; color: var(--text-muted); font-size: 14px;">
                    <i class="fa-solid fa-chart-area" style="font-size: 36px; margin-bottom: 10px; opacity: 0.3;"></i>
                    <p>Biểu đồ đường đua sẽ tự động vẽ sau khi lượt trận đấu đầu tiên được đồng bộ kết quả.</p>
                </div>
            <?php else: ?>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="raceChart"></canvas>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const ctx = document.getElementById('raceChart').getContext('2d');
                        const chartData = <?php echo $chart_json; ?>;
                        
                        new Chart(ctx, {
                            type: 'line',
                            data: chartData,
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            color: '#a0b2a6',
                                            font: {
                                                family: 'Outfit',
                                                size: 11
                                            },
                                            boxWidth: 10,
                                            boxHeight: 10,
                                            padding: 10
                                        }
                                    },
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                        backgroundColor: '#0c1a12',
                                        borderColor: 'rgba(255,255,255,0.1)',
                                        borderWidth: 1,
                                        titleFont: { family: 'Outfit', weight: 'bold' },
                                        bodyFont: { family: 'Outfit' }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: {
                                            color: 'rgba(255,255,255,0.03)'
                                        },
                                        ticks: {
                                            color: '#a0b2a6',
                                            font: { family: 'Outfit', size: 10 }
                                        }
                                    },
                                    y: {
                                        grid: {
                                            color: 'rgba(255,255,255,0.03)'
                                        },
                                        ticks: {
                                            color: '#a0b2a6',
                                            font: { family: 'Outfit', size: 10 },
                                            precision: 0
                                        }
                                    }
                                }
                            }
                        });
                    });
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>



<?php
require_once __DIR__ . '/includes/footer.php';
?>