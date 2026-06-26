<?php
// includes/pdf_templates.php
// File chứa template HTML/CSS dùng để xuất PDF danh sách dự đoán và bảng xếp hạng nhóm
?>
<style>
.pdf-only {
    display: none !important;
}
.pdf-exporting.pdf-only {
    display: block !important;
}
</style>
<?php

/**
 * Render template PDF cho danh sách dự đoán theo từng trận
 * 
 * @param array $selected_match Thông tin trận đấu đã chọn
 * @param array $group_predictions Danh sách dự đoán của các thành viên trong nhóm
 * @param int $user_id ID của user hiện tại để đánh dấu dòng nổi bật
 * @param int $reveal_real_names Cấu hình hiển thị tên thật (0 = ẩn, 1 = hiện)
 */
function render_match_predictions_pdf($selected_match, $group_predictions, $user_id, $reveal_real_names) {
    if (!$selected_match) return;
    $home_team = htmlspecialchars($selected_match['home_team']);
    $away_team = htmlspecialchars($selected_match['away_team']);
    $round = htmlspecialchars($selected_match['round']);
    $site_title = strip_tags(get_setting('site_logo_text', 'WorldCup Predict'));
    $export_date = date('H:i d/m/Y');
    
    // Tính toán thống kê dự đoán
    $home_votes = 0;
    $away_votes = 0;
    $no_votes = 0;
    foreach ($group_predictions as $gp) {
        if (empty($gp['predicted_team'])) {
            $no_votes++;
        } elseif ($gp['predicted_team'] === 'home') {
            $home_votes++;
        } else {
            $away_votes++;
        }
    }
    $total_users = count($group_predictions);
    $home_percent = $total_users > 0 ? round(($home_votes / $total_users) * 100) : 0;
    $away_percent = $total_users > 0 ? round(($away_votes / $total_users) * 100) : 0;
    $no_percent = $total_users > 0 ? round(($no_votes / $total_users) * 100) : 0;
    ?>
    <div id="pdf-match-predictions-template" class="pdf-only pdf-export-container">
        <!-- CSS Styles dành riêng cho bản in A4 PDF -->
        <style>
            .pdf-export-container {
                background: #ffffff !important;
                color: #1a1a1a !important;
                width: 800px !important;
                padding: 35px !important;
                font-family: 'Be Vietnam Pro', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
                box-sizing: border-box !important;
            }
            .pdf-header {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                border-bottom: 3px double #d4af37 !important;
                padding-bottom: 12px !important;
                margin-bottom: 20px !important;
            }
            .pdf-logo {
                font-size: 20px !important;
                font-weight: 800 !important;
                color: #0c1a12 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
            }
            .pdf-logo span {
                color: #d4af37 !important;
            }
            .pdf-meta {
                text-align: right !important;
                font-size: 11px !important;
                color: #647b6e !important;
                font-weight: 500 !important;
            }
            
            .pdf-title-block {
                text-align: center !important;
                margin-bottom: 20px !important;
            }
            .pdf-title-block h1 {
                font-size: 21px !important;
                font-weight: 850 !important;
                color: #0c1a12 !important;
                margin: 0 0 6px 0 !important;
                letter-spacing: 0.5px !important;
            }
            .pdf-title-block p {
                font-size: 13px !important;
                color: #647b6e !important;
                margin: 0 !important;
                font-weight: 500 !important;
            }
            
            /* Scoreboard Card */
            .pdf-scoreboard {
                background: linear-gradient(135deg, #0c1a12 0%, #163020 100%) !important;
                color: #ffffff !important;
                border-radius: 12px !important;
                padding: 18px 20px !important;
                margin-bottom: 22px !important;
                border: 1px solid #d4af37 !important;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05) !important;
                text-align: center !important;
            }
            .pdf-scoreboard-round {
                font-size: 10px !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 1.2px !important;
                color: #d4af37 !important;
                margin-bottom: 8px !important;
            }
            .pdf-scoreboard-main {
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                gap: 25px !important;
                margin: 0 auto 12px auto !important;
            }
            .pdf-team {
                width: 200px !important;
                font-size: 16px !important;
                font-weight: 750 !important;
                color: #ffffff !important;
            }
            .pdf-team-home { text-align: right !important; }
            .pdf-team-away { text-align: left !important; }
            
            .pdf-score-box {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                min-width: 120px !important;
            }
            .pdf-score-val {
                font-size: 28px !important;
                font-weight: 800 !important;
                color: #ffffff !important;
                background: rgba(255, 255, 255, 0.1) !important;
                padding: 4px 14px !important;
                border-radius: 6px !important;
                border: 1px solid rgba(255, 255, 255, 0.2) !important;
                letter-spacing: 3px !important;
                text-indent: 3px !important;
            }
            .pdf-score-vs {
                font-size: 18px !important;
                font-weight: 800 !important;
                color: #d4af37 !important;
            }
            .pdf-status-lbl {
                font-size: 9px !important;
                font-weight: 750 !important;
                text-transform: uppercase !important;
                padding: 2.5px 7px !important;
                border-radius: 4px !important;
                margin-top: 6px !important;
                display: inline-block !important;
            }
            .pdf-status-ft {
                background: rgba(0, 136, 85, 0.2) !important;
                color: #79f2b8 !important;
                border: 1px solid rgba(0, 136, 85, 0.3) !important;
            }
            .pdf-status-ns {
                background: rgba(255, 255, 255, 0.15) !important;
                color: #e1e7e4 !important;
                border: 1px solid rgba(255, 255, 255, 0.2) !important;
            }
            .pdf-scoreboard-footer {
                border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
                padding-top: 10px !important;
                margin-top: 10px !important;
                display: flex !important;
                justify-content: space-around !important;
                font-size: 12px !important;
                color: #cccccc !important;
            }
            .pdf-scoreboard-footer strong {
                color: #ffffff !important;
            }
            
            /* Statistics Row */
            .pdf-stats-row {
                display: flex !important;
                justify-content: space-between !important;
                gap: 12px !important;
                margin-bottom: 22px !important;
            }
            .pdf-stat-card {
                flex: 1 !important;
                background: #f8faf9 !important;
                border: 1px solid #e1e7e4 !important;
                border-radius: 8px !important;
                padding: 10px 12px !important;
                text-align: center !important;
            }
            .pdf-stat-val {
                font-size: 18px !important;
                font-weight: 800 !important;
                color: #0c1a12 !important;
                margin-bottom: 2px !important;
            }
            .pdf-stat-label {
                font-size: 10px !important;
                color: #647b6e !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.3px !important;
            }
            
            /* Table Styling */
            .pdf-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 10px !important;
            }
            .pdf-table tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .pdf-table th,
            .pdf-table td {
                box-sizing: border-box !important;
            }
            .pdf-table th {
                background: #0c1a12 !important;
                color: #ffffff !important;
                font-weight: 700 !important;
                font-size: 11.5px !important;
                padding: 10px 8px !important;
                text-transform: uppercase !important;
                border: 1px solid #0c1a12 !important;
            }
            .pdf-table td {
                padding: 10px 8px !important;
                border-bottom: 1px solid #e1e7e4 !important;
                border-left: 1px solid #e1e7e4 !important;
                border-right: 1px solid #e1e7e4 !important;
                font-size: 11.5px !important;
                color: #1a1a1a !important;
                background: #ffffff !important;
            }
            .pdf-table tr:nth-child(even) td {
                background: #f8faf9 !important;
            }
            .pdf-table tr.pdf-row-own td {
                background: #fffcf0 !important;
            }
            .pdf-table tr.pdf-row-own td:first-child {
                border-left: 3px solid #d4af37 !important;
            }
            
            /* Column Widths */
            .pdf-table th:nth-child(1), .pdf-table td:nth-child(1) { width: 8% !important; text-align: center !important; }
            .pdf-table th:nth-child(2), .pdf-table td:nth-child(2) { width: 30% !important; text-align: left !important; }
            .pdf-table th:nth-child(3), .pdf-table td:nth-child(3) { width: 22% !important; text-align: center !important; }
            .pdf-table th:nth-child(4), .pdf-table td:nth-child(4) { width: 22% !important; text-align: center !important; }
            .pdf-table th:nth-child(5), .pdf-table td:nth-child(5) { width: 18% !important; text-align: right !important; }
            
            .pdf-member-name {
                font-weight: 700 !important;
            }
            .pdf-member-realname {
                font-size: 9.5px !important;
                color: #647b6e !important;
                margin-top: 1.5px !important;
            }
            
            /* Badge choice */
            .pdf-badge-choice {
                display: inline-block !important;
                padding: 3px 8px !important;
                border-radius: 4px !important;
                font-size: 10.5px !important;
                font-weight: 700 !important;
            }
            .pdf-badge-choice.home-team-choice {
                background: rgba(12, 26, 18, 0.06) !important;
                color: #0c1a12 !important;
                border: 1px solid rgba(12, 26, 18, 0.15) !important;
            }
            .pdf-badge-choice.away-team-choice {
                background: rgba(212, 175, 55, 0.08) !important;
                color: #aa8414 !important;
                border: 1px solid rgba(212, 175, 55, 0.25) !important;
            }
            .pdf-badge-choice.none-choice {
                background: rgba(217, 56, 58, 0.05) !important;
                color: #d9383a !important;
                border: 1px solid rgba(217, 56, 58, 0.15) !important;
            }
            .pdf-points {
                font-weight: 800 !important;
                font-size: 12px !important;
            }
            .pdf-points.plus { color: #008855 !important; }
            .pdf-points.minus { color: #d9383a !important; }
            .pdf-points.zero { color: #647b6e !important; }
        </style>

        <!-- Header -->
        <div class="pdf-header">
            <div class="pdf-logo">
                🏆 WorldCup <span>Predict</span>
            </div>
            <div class="pdf-meta">
                Ngày xuất: <?php echo $export_date; ?>
            </div>
        </div>

        <div class="pdf-title-block">
            <h1>BẢNG TỔNG HỢP CHI TIẾT DỰ ĐOÁN</h1>
            <p>Giải đấu: World Cup 2026 | Hạng mục: Dự đoán thành viên theo trận</p>
        </div>

        <!-- Scoreboard -->
        <div class="pdf-scoreboard">
            <div class="pdf-scoreboard-round"><?php echo $round; ?></div>
            
            <div class="pdf-scoreboard-main">
                <div class="pdf-team pdf-team-home"><?php echo $home_team; ?></div>
                
                <div class="pdf-score-box">
                    <?php if (in_array($selected_match['status'], ['FT', 'AET', 'PEN'])): ?>
                        <div class="pdf-score-display">
                            <span class="pdf-score-val"><?php echo $selected_match['home_score']; ?>-<?php echo $selected_match['away_score']; ?></span>
                        </div>
                        <span class="pdf-match-status pdf-status-ft"><?php echo htmlspecialchars($selected_match['status']); ?></span>
                    <?php else: ?>
                        <div class="pdf-score-vs">VS</div>
                        <span class="pdf-match-status pdf-status-ns">Chưa diễn ra</span>
                    <?php endif; ?>
                </div>
                
                <div class="pdf-team pdf-team-away"><?php echo $away_team; ?></div>
            </div>
            
            <div class="pdf-scoreboard-footer">
                <div>Thời gian: <strong><?php echo date('H:i d/m/Y', strtotime($selected_match['match_time'])); ?></strong></div>
                <div>
                    Kèo chấp: <strong>
                        <?php 
                        $hc = (float)($selected_match['handicap'] ?? 0.0);
                        if ($hc > 0) {
                            echo $home_team . ' chấp ' . $hc;
                        } elseif ($hc < 0) {
                            echo $away_team . ' chấp ' . abs($hc);
                        } else {
                            echo 'Đồng banh (0.0)';
                        }
                        ?>
                    </strong>
                </div>
            </div>
        </div>

        <!-- Statistics Row -->
        <div class="pdf-stats-row">
            <div class="pdf-stat-card">
                <div class="pdf-stat-val" style="color: #0c1a12;"><?php echo $home_votes; ?> <span style="font-size: 11px; font-weight: normal;">(<?php echo $home_percent; ?>%)</span></div>
                <div class="pdf-stat-label">Chọn <?php echo $home_team; ?></div>
            </div>
            <div class="pdf-stat-card">
                <div class="pdf-stat-val" style="color: #aa8414;"><?php echo $away_votes; ?> <span style="font-size: 11px; font-weight: normal;">(<?php echo $away_percent; ?>%)</span></div>
                <div class="pdf-stat-label">Chọn <?php echo $away_team; ?></div>
            </div>
            <div class="pdf-stat-card">
                <div class="pdf-stat-val" style="color: #d9383a;"><?php echo $no_votes; ?> <span style="font-size: 11px; font-weight: normal;">(<?php echo $no_percent; ?>%)</span></div>
                <div class="pdf-stat-label">Không dự đoán</div>
            </div>
        </div>

        <!-- Table -->
        <table class="pdf-table">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Thành viên</th>
                    <th>Thời gian cược</th>
                    <th>Đội chọn</th>
                    <th>Điểm nhận</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $stt = 1;
                foreach ($group_predictions as $gp): 
                    $is_own = ($gp['user_id'] == $user_id);
                    $match_finished = in_array($selected_match['status'], ['FT', 'AET', 'PEN']);
                    $show_pred = $match_finished || $is_own;
                ?>
                    <tr class="<?php echo $is_own ? 'pdf-row-own' : ''; ?>">
                        <td style="text-align: center; font-weight: bold; color: #647b6e;">
                            <?php echo $stt++; ?>
                        </td>
                        <td>
                            <div class="pdf-member-name">
                                <?php echo htmlspecialchars($gp['nickname']); ?>
                                <?php if ($is_own): ?>
                                    <span style="font-size: 9px; background: #aa8414; color: #fff; padding: 1px 4px; border-radius: 3px; font-weight: bold; margin-left: 4px;">BẠN</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($reveal_real_names == 1 || is_admin() || $is_own): ?>
                                <div class="pdf-member-realname">
                                    👤 <?php echo htmlspecialchars($gp['real_name']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; color: #647b6e; font-size: 11px;">
                            <?php 
                            if (!$show_pred) {
                                echo '🔒 Ẩn';
                            } elseif (empty($gp['created_at'])) {
                                echo '—';
                            } else {
                                echo date('H:i d/m/Y', strtotime($gp['created_at']));
                            }
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if (!$show_pred): ?>
                                <span class="pdf-badge-choice none" style="background: rgba(0,0,0,0.03) !important; color: #647b6e !important; border: 1px solid rgba(0,0,0,0.08) !important;">🔒 Đã ẩn</span>
                            <?php elseif (empty($gp['predicted_team'])): ?>
                                <span class="pdf-badge-choice none-choice">Không dự đoán</span>
                            <?php else: ?>
                                <span class="pdf-badge-choice <?php echo $gp['predicted_team'] === 'home' ? 'home-team-choice' : 'away-team-choice'; ?>">
                                    <?php echo htmlspecialchars(($gp['predicted_team'] === 'home') ? $selected_match['home_team'] : $selected_match['away_team']); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; font-weight: bold;">
                            <?php 
                            if (!$show_pred) {
                                echo '<span class="pdf-points zero">🔒 Ẩn</span>';
                            } elseif ($gp['points_awarded'] !== null) {
                                $pts = $gp['points_awarded'];
                                if ($pts > 0) {
                                    echo '<span class="pdf-points plus">+' . $pts . 'đ</span>';
                                } elseif ($pts < 0) {
                                    echo '<span class="pdf-points minus">' . $pts . 'đ</span>';
                                } else {
                                    echo '<span class="pdf-points zero">0đ</span>';
                                }
                            } elseif ($match_finished) {
                                echo '<span class="pdf-points minus">0đ</span>';
                            } else {
                                echo '<span style="color: #647b6e; font-weight: normal; font-size: 11px;">Chờ đấu</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Render template PDF cho bảng xếp hạng nhóm (Danh sách tổng)
 * 
 * @param array $ranked_leaderboard Danh sách thành viên đã xếp hạng
 * @param int $user_id ID của user hiện tại để đánh dấu dòng nổi bật
 * @param int $reveal_real_names Cấu hình hiển thị tên thật (0 = ẩn, 1 = hiện)
 * @param array $prev_ranks Mảng xếp hạng của ngày hôm trước
 */
function render_leaderboard_pdf($ranked_leaderboard, $user_id, $reveal_real_names, $prev_ranks) {
    $site_title = strip_tags(get_setting('site_logo_text', 'WorldCup Predict'));
    $export_date = date('H:i d/m/Y');
    ?>
    <div id="pdf-leaderboard-template" class="pdf-only pdf-export-container">
        <!-- CSS Styles dành riêng cho bản in A4 PDF của bảng xếp hạng nhóm -->
        <style>
            .pdf-export-container {
                background: #ffffff !important;
                color: #142b1e !important;
                width: 800px !important;
                padding: 30px !important;
                font-family: 'Be Vietnam Pro', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
                box-sizing: border-box !important;
            }
            .pdf-header-container {
                border-bottom: 2px solid #aa8414 !important;
                padding-bottom: 15px !important;
                margin-bottom: 25px !important;
            }
            .pdf-header-top {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                margin-bottom: 12px !important;
            }
            .pdf-brand {
                font-size: 16px !important;
                font-weight: 800 !important;
                color: #aa8414 !important;
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                text-transform: uppercase !important;
            }
            .pdf-meta-date {
                font-size: 11px !important;
                color: #647b6e !important;
                font-weight: 500 !important;
            }
            .pdf-header-title {
                font-size: 20px !important;
                font-weight: 850 !important;
                color: #142b1e !important;
                text-align: center !important;
                margin-top: 10px !important;
                margin-bottom: 6px !important;
                letter-spacing: 0.5px !important;
            }
            .pdf-header-subtitle {
                font-size: 13.5px !important;
                font-weight: 600 !important;
                color: #647b6e !important;
                text-align: center !important;
                margin-bottom: 10px !important;
            }
            
            /* Table Styling */
            .pdf-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 15px !important;
            }
            .pdf-table tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .pdf-table th,
            .pdf-table td {
                box-sizing: border-box !important;
            }
            .pdf-table th {
                background: #f1f4f2 !important;
                color: #142b1e !important;
                font-weight: 700 !important;
                font-size: 11px !important;
                padding: 10px 12px !important;
                border-bottom: 2px solid #d1dcd6 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
            }
            .pdf-table td {
                padding: 10px 12px !important;
                border-bottom: 1px solid #e1e7e4 !important;
                font-size: 12px !important;
                color: #142b1e !important;
                background: #ffffff !important;
            }
            
            /* Cố định tỷ lệ cột bảng xếp hạng */
            .pdf-table th:nth-child(1), .pdf-table td:nth-child(1) { width: 15% !important; text-align: center !important; }
            .pdf-table th:nth-child(2), .pdf-table td:nth-child(2) { width: 45% !important; text-align: left !important; }
            .pdf-table th:nth-child(3), .pdf-table td:nth-child(3) { width: 20% !important; text-align: center !important; }
            .pdf-table th:nth-child(4), .pdf-table td:nth-child(4) { width: 20% !important; text-align: right !important; }
            
            .pdf-table tr.pdf-row-own td {
                background: #fffcf0 !important;
            }
            .pdf-table tr.pdf-row-own td:first-child {
                border-left: 3px solid #aa8414 !important;
            }
            .pdf-rank-badge {
                display: inline-block !important;
                width: 24px !important;
                height: 24px !important;
                line-height: 24px !important;
                border-radius: 50% !important;
                text-align: center !important;
                font-weight: 800 !important;
                font-size: 11px !important;
            }
            .pdf-rank-1 { background: #d4af37 !important; color: #fff !important; }
            .pdf-rank-2 { background: #c0c0c0 !important; color: #fff !important; }
            .pdf-rank-3 { background: #cd7f32 !important; color: #fff !important; }
            .pdf-rank-other { background: #f1f4f2 !important; color: #647b6e !important; }
            
            .pdf-member-name {
                font-weight: 700 !important;
            }
            .pdf-member-realname {
                font-size: 10px !important;
                color: #647b6e !important;
                margin-top: 2px !important;
            }
            .pdf-trend-indicator {
                font-size: 10px !important;
                font-weight: bold !important;
                margin-left: 5px !important;
            }
            .pdf-trend-up { color: #008855 !important; }
            .pdf-trend-down { color: #d9383a !important; }
            .pdf-trend-same { color: #647b6e !important; }
            .pdf-total-points {
                font-weight: 800 !important;
                font-size: 15px !important;
                color: #aa8414 !important;
            }
            .pdf-win-loss {
                font-weight: 600 !important;
            }
        </style>

        <!-- Brand/Title Header -->
        <div class="pdf-header-container">
            <div class="pdf-header-top">
                <div class="pdf-brand">
                    <span style="font-size: 18px; margin-right: 5px;">🏆</span>
                    <span><?php echo $site_title; ?></span>
                </div>
                <div class="pdf-meta-date">
                    Ngày xuất: <?php echo $export_date; ?>
                </div>
            </div>
            <div class="pdf-header-title">
                BẢNG XẾP HẠNG THÀNH VIÊN NHÓM
            </div>
            <div class="pdf-header-subtitle">
                Giải đấu: World Cup 2026 | Hạng mục: Tổng hợp điểm số dự đoán
            </div>
        </div>

        <!-- Leaderboard Table -->
        <table class="pdf-table">
            <thead>
                <tr>
                    <th>Hạng</th>
                    <th>Thành viên</th>
                    <th>Thắng / Thua</th>
                    <th>Tổng điểm</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranked_leaderboard as $user): 
                    $uid = $user['user_id'];
                    $rank_pos = $user['rank'];
                    $prev_pos = isset($prev_ranks[$uid]) ? $prev_ranks[$uid] : null;
                    $is_own = ($uid == $user_id);
                    
                    // Lớp CSS cho thứ hạng
                    $badge_class = 'pdf-rank-other';
                    if ($rank_pos == 1) $badge_class = 'pdf-rank-1';
                    elseif ($rank_pos == 2) $badge_class = 'pdf-rank-2';
                    elseif ($rank_pos == 3) $badge_class = 'pdf-rank-3';
                    
                    // Ký hiệu xu hướng tăng giảm
                    $trend_text = '';
                    if ($prev_pos !== null) {
                        if ($rank_pos < $prev_pos) {
                            $trend_text = '<span class="pdf-trend-indicator pdf-trend-up">▲ ' . ($prev_pos - $rank_pos) . '</span>';
                        } elseif ($rank_pos > $prev_pos) {
                            $trend_text = '<span class="pdf-trend-indicator pdf-trend-down">▼ ' . ($rank_pos - $prev_pos) . '</span>';
                        } else {
                            $trend_text = '<span class="pdf-trend-indicator pdf-trend-same">―</span>';
                        }
                    } else {
                        $trend_text = '<span class="pdf-trend-indicator pdf-trend-same">―</span>';
                    }
                ?>
                    <tr class="<?php echo $is_own ? 'pdf-row-own' : ''; ?>">
                        <td style="text-align: center;">
                            <span class="pdf-rank-badge <?php echo $badge_class; ?>"><?php echo $rank_pos; ?></span>
                        </td>
                        <td>
                            <div class="pdf-member-name">
                                <?php echo htmlspecialchars($user['nickname']); ?>
                                <?php echo $trend_text; ?>
                                <?php if ($is_own): ?>
                                    <span style="font-size: 9px; background: #aa8414; color: #fff; padding: 1px 4px; border-radius: 3px; font-weight: bold; margin-left: 4px;">BẠN</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($reveal_real_names == 1 || is_admin() || $is_own): ?>
                                <div class="pdf-member-realname">
                                    👤 <?php echo htmlspecialchars($user['real_name']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; color: #647b6e;">
                            <span class="pdf-win-loss" style="color: #008855;"><?php echo $user['win_count']; ?></span>
                            /
                            <span class="pdf-win-loss" style="color: #d9383a;"><?php echo $user['loss_count']; ?></span>
                        </td>
                        <td style="text-align: right;">
                            <span class="pdf-total-points"><?php echo $user['total_points']; ?>đ</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
