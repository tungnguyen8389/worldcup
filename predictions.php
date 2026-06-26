<?php
// predictions.php
$page_title = "Lịch sử dự đoán";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/pdf_templates.php';
require_login();

$user_id = $_SESSION['user_id'];
$reveal_real_names = (int)get_setting('reveal_real_names', 0);

// Lấy danh sách tất cả các trận đấu có trong hệ thống để lọc/xem chi tiết (bao gồm cả logo)
$sql_matches = "SELECT id, home_team, away_team, home_logo, away_logo, match_time, status, home_score, away_score, round, handicap FROM matches ORDER BY match_time DESC";
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
                         p.predicted_team, p.points_awarded, p.created_at
                  FROM users u
                  LEFT JOIN predictions p ON u.id = p.user_id AND p.match_id = :match_id
                  WHERE u.role = 'user'
                  ORDER BY u.nickname ASC";
    $stmt_group = $pdo->prepare($sql_group);
    $stmt_group->execute(['match_id' => $selected_match_id]);
    $group_predictions = $stmt_group->fetchAll();
}

// Xác định user đang được xem lịch sử dự đoán ở sidebar bên phải (mặc định là chính mình)
$view_user_id = isset($_GET['view_user_id']) ? (int)$_GET['view_user_id'] : $user_id;

// Lấy thông tin user được xem ở sidebar
$stmt_view_user = $pdo->prepare("SELECT nickname, real_name FROM users WHERE id = ? AND role = 'user'");
$stmt_view_user->execute([$view_user_id]);
$view_user_info = $stmt_view_user->fetch();

// Nếu không tìm thấy user, fallback về user hiện tại
if (!$view_user_info) {
    $view_user_id = $user_id;
    $stmt_view_user = $pdo->prepare("SELECT nickname, real_name FROM users WHERE id = ?");
    $stmt_view_user->execute([$user_id]);
    $view_user_info = $stmt_view_user->fetch();
}

// Lấy dự đoán của tất cả thành viên cho trận đấu đang chọn
$group_predictions = [];
if ($selected_match) {
    $is_locked = is_match_locked($selected_match['match_time']);
    
    // Lấy tất cả user (role = user) và JOIN với dự đoán của họ cho trận đấu này
    $sql_group = "SELECT u.id as user_id, u.nickname, u.real_name, 
                         p.predicted_team, p.points_awarded, p.created_at
                  FROM users u
                  LEFT JOIN predictions p ON u.id = p.user_id AND p.match_id = :match_id
                  WHERE u.role = 'user'
                  ORDER BY u.nickname ASC";
    $stmt_group = $pdo->prepare($sql_group);
    $stmt_group->execute(['match_id' => $selected_match_id]);
    $group_predictions = $stmt_group->fetchAll();
}

// Lấy toàn bộ lịch sử dự đoán của user được chọn
$sql_personal = "SELECT m.home_team, m.away_team, m.home_logo, m.away_logo, m.match_time, m.status, m.home_score as actual_home, m.away_score as actual_away, m.round, m.handicap,
                        p.predicted_team, p.points_awarded
                 FROM matches m
                 LEFT JOIN predictions p ON m.id = p.match_id AND p.user_id = :user_id
                 ORDER BY m.match_time DESC";
$stmt_pers = $pdo->prepare($sql_personal);
$stmt_pers->execute(['user_id' => $view_user_id]);
$personal_history = $stmt_pers->fetchAll();
?>

<style>
/* Dashboard Layout */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 24px;
    align-items: start;
    margin-top: 15px;
}

@media (max-width: 992px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

/* Card Improvements */
.card {
    background: #ffffff !important;
    border: 1px solid rgba(0, 0, 0, 0.08) !important;
    border-radius: 16px !important;
    padding: 24px !important;
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.08) !important;
}

.card-title {
    font-size: 17px !important;
    font-weight: 700 !important;
    color: var(--primary) !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
    padding-bottom: 14px !important;
    margin-bottom: 20px !important;
    letter-spacing: 0.5px;
}

/* Selector form styling */
.match-selector-form {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.match-selector-form label {
    font-size: 13.5px;
    font-weight: 600;
    color: #333333;
}
.match-selector-form select {
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.15);
    color: #1a1a1a;
    padding: 10px 14px;
    border-radius: 8px;
    outline: none;
    font-size: 13.5px;
    transition: all 0.3s;
    cursor: pointer;
    font-weight: 500;
    flex: 1;
    min-width: 220px;
}
.match-selector-form select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.15);
}

/* Scoreboard Card */
.match-scoreboard-card {
    background: radial-gradient(135deg, rgba(20, 45, 32, 0.4) 0%, rgba(10, 20, 15, 0.65) 100%);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 20px;
    text-align: center;
    position: relative;
    box-shadow: inset 0 0 20px rgba(255,255,255,0.02);
}

.round-badge {
    display: inline-block;
    background: rgba(212, 175, 55, 0.08);
    border: 1px solid rgba(212, 175, 55, 0.15);
    color: var(--primary);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 15px;
}

.scoreboard-vs {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 440px;
    margin: 0 auto 15px auto;
    gap: 12px;
}

.scoreboard-team {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.team-flag-wrapper {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.2);
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
    margin-bottom: 8px;
    transition: transform 0.25s ease;
}

.scoreboard-team:hover .team-flag-wrapper {
    transform: scale(1.08);
}

.team-flag-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.team-name {
    font-size: 13.5px;
    font-weight: 700;
    color: #fff;
    text-align: center;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.scoreboard-score-area {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 110px;
}

.score-display {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 5px;
}

.score-num {
    font-size: 28px;
    font-weight: 900;
    color: #fff;
    background: rgba(255, 255, 255, 0.03);
    padding: 3px 12px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    min-width: 40px;
    text-align: center;
}

.score-divider {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-muted);
}

.score-vs {
    font-size: 22px;
    font-weight: 900;
    font-style: italic;
    color: var(--primary);
    margin-bottom: 5px;
}

.match-status-badge {
    font-size: 9px;
    font-weight: 750;
    padding: 2.5px 6px;
    border-radius: 4px;
    text-transform: uppercase;
}

.status-ft {
    background: rgba(0, 255, 170, 0.08);
    border: 1px solid rgba(0, 255, 170, 0.2);
    color: var(--accent);
}

.status-ns {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: var(--text-muted);
}

.scoreboard-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    padding-top: 12px;
    margin-top: 10px;
    flex-wrap: wrap;
    gap: 8px;
}

.match-time-info, .handicap-badge {
    font-size: 12.5px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 5px;
}

.handicap-badge strong {
    color: var(--accent);
}

.public-disclosure-notice {
    margin-top: 12px;
    background: rgba(0, 255, 170, 0.03);
    border: 1px dashed rgba(0, 255, 170, 0.15);
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 11.5px;
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

/* Predictions Table Styling */
.predictions-table {
    width: 100%;
    border-collapse: collapse;
}

.predictions-table th {
    background: #f1f3f5;
    color: #495057;
    font-weight: 600;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.predictions-table tbody tr {
    background: #ffffff;
    color: #1a1a1a;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    cursor: pointer;
    transition: all 0.2s ease;
}

.predictions-table tbody tr:hover {
    background: #f4f6f5 !important;
}

.predictions-table tr.row-own-user {
    background: #fffcf0;
    border-left: 4px solid var(--primary);
}

.predictions-table tr.row-selected-user {
    background: rgba(212, 175, 55, 0.12) !important;
    box-shadow: inset 3px 0 0 var(--primary);
}

.member-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.member-name {
    font-weight: 700;
    color: #1a1a1a;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13.5px;
}

.member-realname {
    font-size: 11.5px;
    color: #666;
    display: flex;
    align-items: center;
    gap: 4px;
}

.prediction-badge-custom {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 600;
}
.prediction-badge-custom.badge-select {
    background: rgba(0, 255, 170, 0.08);
    color: #008855;
    border: 1px solid rgba(0, 255, 170, 0.25);
}
.prediction-badge-custom.badge-none {
    background: rgba(255, 62, 108, 0.08);
    color: var(--accent-red);
    border: 1px solid rgba(255, 62, 108, 0.25);
    font-weight: normal;
}

.result-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.result-win {
    background: rgba(0, 255, 170, 0.1);
    color: #008855;
    border: 1px solid rgba(0, 255, 170, 0.2);
}

.result-lose {
    background: rgba(255, 62, 108, 0.1);
    color: var(--accent-red);
    border: 1px solid rgba(255, 62, 108, 0.2);
}

.result-draw {
    background: rgba(0, 0, 0, 0.05);
    color: #666;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.points-pill {
    display: inline-block;
    padding: 2.5px 7px;
    border-radius: 4px;
    font-size: 11.5px;
    font-weight: 700;
}

.points-pill.pts-plus {
    background: rgba(0, 255, 170, 0.1);
    color: #008855;
}

.points-pill.pts-minus {
    background: rgba(255, 62, 108, 0.1);
    color: var(--accent-red);
}

.points-pill.pts-zero {
    background: rgba(0, 0, 0, 0.05);
    color: #666;
}

/* Personal History Cards */
.personal-history-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 650px;
    overflow-y: auto;
    padding-right: 6px;
}

.personal-history-list::-webkit-scrollbar {
    width: 4px;
}
.personal-history-list::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.12);
    border-radius: 10px;
}

.personal-card {
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 10px;
    padding: 14px;
    transition: all 0.25s ease;
    color: #1a1a1a;
}

.personal-card:hover {
    background: #f4f6f5;
    border-color: rgba(0, 0, 0, 0.1);
    transform: translateY(-1.5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.personal-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.personal-round {
    font-size: 10px;
    font-weight: 700;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.personal-points-indicator {
    font-size: 10.5px;
    font-weight: 800;
    padding: 2.5px 6.5px;
    border-radius: 4px;
}

.personal-points-indicator.pts-plus {
    background: var(--primary);
    color: #0c1a12;
}

.personal-points-indicator.pts-minus {
    background: var(--accent-red);
    color: #fff;
}

.personal-points-indicator.pts-zero {
    background: rgba(0, 0, 0, 0.06);
    color: #666;
}

.personal-matchup {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.personal-team {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
}

.personal-team.home-side {
    justify-content: flex-start;
}

.personal-team.away-side {
    justify-content: flex-end;
}

.ph-flag {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.ph-flag img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ph-team-name {
    font-size: 13px;
    font-weight: 700;
    color: #1a1a1a;
}

.personal-score-box {
    font-size: 14px;
    font-weight: 800;
    color: #1a1a1a;
    padding: 2px 7px;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
    min-width: 36px;
    text-align: center;
    margin: 0 8px;
}

.personal-details-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8faf9;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.personal-choice {
    color: #555;
}
.personal-choice strong.choice-home {
    color: var(--primary);
}
.personal-choice strong.choice-away {
    color: var(--accent);
}

.personal-time {
    font-size: 10.5px;
    color: var(--text-muted);
    margin-bottom: 6px;
}
</style>

<div class="dashboard-grid">
    <!-- CỘT TRÁI: XEM CHI TIẾT DỰ ĐOÁN NHÓM THEO TRẬN -->
    <div>
        <div class="card" id="group-predictions-card">
            <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; width: 100%;">
                <span><i class="fa-solid fa-users text-primary"></i> Dự Đoán Của Cả Nhóm</span>
                <?php if ($selected_match): ?>
                <button type="button" onclick="exportToPDF('pdf-match-predictions-template', 'Du_Doan_Nhom_Tran_<?php echo htmlspecialchars($selected_match['home_team'] . '_vs_' . $selected_match['away_team']); ?>')" class="btn btn-secondary btn-sm" style="padding: 5px 12px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-file-pdf"></i> Xuất PDF
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Bộ lọc trận đấu -->
            <form method="GET" class="match-selector-form">
                <label><i class="fa-solid fa-filter"></i> Chọn trận đấu:</label>
                <select name="match_id" onchange="this.form.submit()">
                    <?php foreach ($matches as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $m['id'] == $selected_match_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['home_team'] . ' vs ' . $m['away_team']); ?> 
                            (<?php echo date('d/m H:i', strtotime($m['match_time'])); ?>) 
                            <?php echo in_array($m['status'], ['FT', 'AET', 'PEN']) ? '- Đã kết thúc' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="view_user_id" value="<?php echo $view_user_id; ?>">
            </form>
            
            <?php if (!$selected_match): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 20px;">Không có trận đấu nào được chọn.</p>
            <?php else: 
                $is_selected_locked = is_match_locked($selected_match['match_time']);
            ?>
                <!-- Khung thông tin trận đấu (Scoreboard) -->
                <div class="match-scoreboard-card">
                    <div class="round-badge"><?php echo htmlspecialchars($selected_match['round']); ?></div>
                    
                    <div class="scoreboard-vs">
                        <!-- Đội nhà -->
                        <div class="scoreboard-team">
                            <div class="team-flag-wrapper">
                                <img src="<?php echo htmlspecialchars($selected_match['home_logo'] ?: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); ?>" alt="<?php echo htmlspecialchars($selected_match['home_team']); ?>" onerror="this.onerror=null; this.src='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';">
                            </div>
                            <div class="team-name"><?php echo htmlspecialchars($selected_match['home_team']); ?></div>
                        </div>
                        
                        <!-- Tỷ số & Trạng thái -->
                        <div class="scoreboard-score-area">
                            <?php if (in_array($selected_match['status'], ['FT', 'AET', 'PEN'])): ?>
                                <div class="score-display">
                                    <span class="score-num"><?php echo $selected_match['home_score']; ?></span>
                                    <span class="score-divider">-</span>
                                    <span class="score-num"><?php echo $selected_match['away_score']; ?></span>
                                </div>
                                <div class="match-status-badge status-ft"><?php echo htmlspecialchars($selected_match['status']); ?></div>
                            <?php else: ?>
                                <div class="score-vs">VS</div>
                                <div class="match-status-badge status-ns">Chưa đá</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Đội khách -->
                        <div class="scoreboard-team">
                            <div class="team-flag-wrapper">
                                <img src="<?php echo htmlspecialchars($selected_match['away_logo'] ?: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); ?>" alt="<?php echo htmlspecialchars($selected_match['away_team']); ?>" onerror="this.onerror=null; this.src='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';">
                            </div>
                            <div class="team-name"><?php echo htmlspecialchars($selected_match['away_team']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Kèo chấp & Thời gian -->
                    <div class="scoreboard-footer">
                        <div class="match-time-info">
                            <i class="fa-solid fa-calendar-days"></i> <?php echo format_match_time($selected_match['match_time']); ?>
                        </div>
                        <div class="handicap-badge">
                            <i class="fa-solid fa-scale-balanced"></i> Tỷ lệ chấp vui: 
                            <strong>
                                <?php 
                                $hc = (float)($selected_match['handicap'] ?? 0.0);
                                if ($hc > 0) {
                                    echo htmlspecialchars($selected_match['home_team']) . ' chấp ' . $hc;
                                } elseif ($hc < 0) {
                                    echo htmlspecialchars($selected_match['away_team']) . ' chấp ' . abs($hc);
                                } else {
                                    echo 'Đồng banh (0.0)';
                                }
                                ?>
                            </strong>
                        </div>
                    </div>
                    
                    <div class="public-disclosure-notice">
                        <i class="fa-solid fa-eye"></i> Dự đoán của cả nhóm được công khai và cập nhật liên tục
                    </div>
                </div>
                
                <!-- Bảng dự đoán của nhóm -->
                <div class="table-responsive">
                    <table class="predictions-table">
                        <thead>
                            <tr>
                                <th>Thành viên</th>
                                <th style="text-align: center;">Dự đoán</th>
                                <th style="text-align: center;">Kết quả</th>
                                <th style="text-align: right;">Điểm thưởng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group_predictions as $gp): 
                                $is_own = ($gp['user_id'] == $user_id);
                                $is_selected = ($gp['user_id'] == $view_user_id);
                                $match_finished = in_array($selected_match['status'], ['FT', 'AET', 'PEN']);
                                $show_pred = $match_finished || $is_own;
                            ?>
                                <tr class="<?php echo $is_own ? 'row-own-user' : ''; ?> <?php echo $is_selected ? 'row-selected-user' : ''; ?>"
                                    onclick="location.href='predictions.php?match_id=<?php echo $selected_match_id; ?>&view_user_id=<?php echo $gp['user_id']; ?>'">
                                    <td>
                                        <div class="member-cell">
                                            <div class="member-name">
                                                <?php echo htmlspecialchars($gp['nickname']); ?>
                                                <?php if ($is_own): ?>
                                                    <span style="font-size: 9px; background: var(--primary); color: #000; padding: 1.5px 4px; border-radius: 3px; font-weight: bold; margin-left: 2px;">BẠN</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($reveal_real_names == 1 || is_admin() || $is_own): ?>
                                                <div class="member-realname">
                                                    <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($gp['real_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (!$show_pred): ?>
                                            <span class="prediction-badge-custom badge-none" style="background: rgba(255,255,255,0.03); color: var(--text-muted); border: 1px solid rgba(255,255,255,0.08); font-weight: normal;">
                                                <i class="fa-solid fa-lock"></i> Đã ẩn
                                            </span>
                                        <?php elseif (empty($gp['predicted_team'])): ?>
                                            <span class="prediction-badge-custom badge-none"><i class="fa-solid fa-circle-minus"></i> Không dự đoán</span>
                                        <?php else: ?>
                                            <span class="prediction-badge-custom badge-select">
                                                <?php echo htmlspecialchars(($gp['predicted_team'] === 'home') ? $selected_match['home_team'] : $selected_match['away_team']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php 
                                        if (!$show_pred) {
                                            echo '<span style="color: var(--text-muted); font-size: 12px;"><i class="fa-solid fa-lock" style="font-size: 10px;"></i> Ẩn</span>';
                                        } elseif ($gp['points_awarded'] !== null) {
                                            if ($gp['points_awarded'] > 0) {
                                                echo '<span class="result-status-badge result-win"><i class="fa-solid fa-circle-check"></i> Tiên tri đúng</span>';
                                            } elseif ($gp['points_awarded'] < 0) {
                                                if (empty($gp['predicted_team'])) {
                                                    echo '<span class="result-status-badge result-lose"><i class="fa-solid fa-ban"></i> Thua (Không dự đoán)</span>';
                                                } else {
                                                    echo '<span class="result-status-badge result-lose"><i class="fa-solid fa-circle-xmark"></i> Tiên tri sai</span>';
                                                }
                                            } else {
                                                echo '<span class="result-status-badge result-draw"><i class="fa-solid fa-circle-minus"></i> Hòa điểm</span>';
                                            }
                                        } elseif ($match_finished) {
                                            echo '<span class="result-status-badge result-lose"><i class="fa-solid fa-circle-xmark"></i> Tiên tri sai</span>';
                                        } else {
                                            echo '<span style="color: var(--text-muted); font-size: 12px;">—</span>';
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align: right; font-weight: bold;">
                                        <?php 
                                        if (!$show_pred) {
                                            echo '<span style="color: var(--text-muted); font-weight: normal; font-size: 11.5px;"><i class="fa-solid fa-lock" style="font-size: 10px;"></i> Ẩn</span>';
                                        } elseif ($gp['points_awarded'] !== null) {
                                            if ($gp['points_awarded'] > 0) {
                                                 echo '<span class="points-pill pts-plus">+' . $gp['points_awarded'] . 'đ</span>';
                                             } elseif ($gp['points_awarded'] < 0) {
                                                 echo '<span class="points-pill pts-minus">' . $gp['points_awarded'] . 'đ</span>';
                                             } else {
                                                 echo '<span class="points-pill pts-zero">0đ</span>';
                                             }
                                        } elseif ($match_finished) {
                                            echo '<span class="points-pill pts-minus">0đ</span>';
                                        } else {
                                            echo '<span style="color: var(--text-muted); font-weight: normal; font-size: 11.5px;">Chờ đấu</span>';
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
            <div class="card-title" style="display: flex; flex-direction: column; gap: 4px;">
                <div>
                    <i class="fa-solid fa-clock-rotate-left text-primary"></i> 
                    Lịch Sử Dự Đoán: <strong style="color: var(--primary);"><?php echo htmlspecialchars($view_user_info['nickname']); ?></strong>
                </div>
                <?php if ($view_user_id == $user_id): ?>
                    <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">(Lịch sử của chính bạn)</span>
                <?php else: ?>
                    <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">(Xem lịch sử thành viên)</span>
                <?php endif; ?>
            </div>
            
            <?php if (empty($personal_history)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 20px;">Thành viên này chưa thực hiện dự đoán nào.</p>
            <?php else: ?>
                <div class="personal-history-list">
                    <?php foreach ($personal_history as $ph): 
                        $ph_locked = is_match_locked($ph['match_time']);
                        $is_finished = in_array($ph['status'], ['FT', 'AET', 'PEN']);
                        $show_personal_pred = $is_finished || ($view_user_id == $user_id);
                    ?>
                        <div class="personal-card">
                            <div class="personal-card-header">
                                <span class="personal-round"><?php echo htmlspecialchars($ph['round']); ?></span>
                                <?php if (!$show_personal_pred): ?>
                                    <span class="personal-points-indicator pts-zero" style="background: rgba(0, 0, 0, 0.04) !important; color: #647b6e !important;">
                                        <i class="fa-solid fa-lock" style="font-size: 9px;"></i> Ẩn
                                    </span>
                                <?php elseif ($ph['points_awarded'] !== null): ?>
                                    <span class="personal-points-indicator <?php echo $ph['points_awarded'] < 0 ? 'pts-minus' : ($ph['points_awarded'] > 0 ? 'pts-plus' : 'pts-zero'); ?>">
                                        <?php echo $ph['points_awarded'] > 0 ? '+' : ''; ?><?php echo $ph['points_awarded']; ?>đ
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="personal-matchup">
                                <!-- Đội nhà -->
                                <div class="personal-team home-side">
                                    <div class="ph-flag">
                                        <img src="<?php echo htmlspecialchars($ph['home_logo'] ?: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); ?>" alt="" onerror="this.onerror=null; this.src='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';">
                                    </div>
                                    <span class="ph-team-name"><?php echo htmlspecialchars($ph['home_team']); ?></span>
                                </div>
                                
                                <!-- Tỷ số hoặc VS -->
                                <div class="personal-score-box">
                                    <?php echo $is_finished ? $ph['actual_home'] . '-' . $ph['actual_away'] : 'VS'; ?>
                                </div>
                                
                                <!-- Đội khách -->
                                <div class="personal-team away-side">
                                    <span class="ph-team-name"><?php echo htmlspecialchars($ph['away_team']); ?></span>
                                    <div class="ph-flag">
                                        <img src="<?php echo htmlspecialchars($ph['away_logo'] ?: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); ?>" alt="" onerror="this.onerror=null; this.src='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="personal-time">
                                <i class="fa-regular fa-clock"></i> <?php echo date('H:i d/m/Y', strtotime($ph['match_time'])); ?>
                            </div>
                            
                            <div class="personal-details-row">
                                <div class="personal-choice">
                                    Dự đoán: 
                                    <?php 
                                    if (!$show_personal_pred) {
                                        echo '<span style="color: var(--text-muted);"><i class="fa-solid fa-lock" style="font-size: 10px;"></i> Đang ẩn</span>';
                                    } elseif (!empty($ph['predicted_team'])) {
                                        $predicted_team_name = ($ph['predicted_team'] === 'home') ? $ph['home_team'] : $ph['away_team'];
                                        $choice_class = ($ph['predicted_team'] === 'home') ? 'choice-home' : 'choice-away';
                                        echo '<strong class="' . $choice_class . '">' . htmlspecialchars($predicted_team_name) . '</strong>';
                                    } else {
                                        echo '<span style="color: var(--accent-red);">Không dự đoán</span>';
                                    }
                                    ?>
                                </div>
                                
                                <div style="color: var(--text-muted);">
                                    Chấp vui: <strong style="color: var(--accent); font-weight: 600;">
                                        <?php 
                                        $hc = (float)($ph['handicap'] ?? 0.0);
                                        if ($hc > 0) {
                                            echo $hc;
                                        } elseif ($hc < 0) {
                                            echo abs($hc);
                                        } else {
                                            echo '0.0';
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
