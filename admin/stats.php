<?php
// admin/stats.php — Báo cáo thống kê tổng quan (Admin)
$page_title = "Báo cáo thống kê";
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
require_once __DIR__ . '/../includes/header.php';

// ============================================================
// TRUY VẤN DỮ LIỆU
// ============================================================

// 1. Tổng quan nhanh
$total_users    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$total_matches  = (int)$pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
$finished       = (int)$pdo->query("SELECT COUNT(*) FROM matches WHERE status IN ('FT','AET','PEN')")->fetchColumn();
$upcoming       = (int)$pdo->query("SELECT COUNT(*) FROM matches WHERE status='NS'")->fetchColumn();
$total_preds    = (int)$pdo->query("SELECT COUNT(*) FROM predictions")->fetchColumn();
$scored_preds   = (int)$pdo->query("SELECT COUNT(*) FROM predictions WHERE prediction_status=1")->fetchColumn();
$participation  = $total_users > 0 && $total_matches > 0 ? round($total_preds / ($total_users * max($total_matches, 1)) * 100) : 0;

// 2. Bảng xếp hạng đầy đủ
$leaderboard = $pdo->query("
    SELECT u.id, u.nickname, u.real_name,
           COALESCE(SUM(p.points_awarded),0)                              AS total_points,
           SUM(CASE WHEN p.points_awarded=1  THEN 1 ELSE 0 END)          AS wins,
           SUM(CASE WHEN p.points_awarded=-1 THEN 1 ELSE 0 END)          AS losses,
           SUM(CASE WHEN p.points_awarded=0  AND p.prediction_status=1 THEN 1 ELSE 0 END) AS draws,
           COUNT(CASE WHEN p.prediction_status=1 THEN 1 END)             AS played,
           COUNT(p.id)                                                    AS total_pred
    FROM users u
    LEFT JOIN predictions p ON u.id=p.user_id
    WHERE u.role='user'
    GROUP BY u.id, u.nickname, u.real_name
    ORDER BY total_points DESC, wins DESC
")->fetchAll();

// 3. Thống kê từng trận đấu (tỷ lệ chọn home/away)
$match_stats = $pdo->query("
    SELECT m.id, m.home_team, m.away_team, m.round, m.status,
           m.home_score, m.away_score, m.handicap, m.match_time,
           COUNT(p.id)                                               AS total_pred,
           SUM(CASE WHEN p.predicted_team='home' THEN 1 ELSE 0 END) AS home_picks,
           SUM(CASE WHEN p.predicted_team='away' THEN 1 ELSE 0 END) AS away_picks,
           SUM(CASE WHEN p.points_awarded=1  THEN 1 ELSE 0 END)     AS winners,
           SUM(CASE WHEN p.points_awarded=-1 THEN 1 ELSE 0 END)     AS losers
    FROM matches m
    LEFT JOIN predictions p ON m.id=p.match_id
    GROUP BY m.id
    ORDER BY m.match_time DESC
")->fetchAll();

// 3b. Chi tiết dự đoán của thành viên cho trận đấu đang chọn (Admin)
$selected_match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
if ($selected_match_id === 0 && !empty($match_stats)) {
    $selected_match_id = $match_stats[0]['id'];
    foreach ($match_stats as $m) {
        if (strtotime($m['match_time']) > time()) {
            $selected_match_id = $m['id'];
        }
    }
}
$selected_match = null;
foreach ($match_stats as $m) {
    if ($m['id'] == $selected_match_id) {
        $selected_match = $m;
        break;
    }
}
$admin_predictions = [];
if ($selected_match) {
    $sql_admin_group = "SELECT u.id as user_id, u.nickname, u.real_name, 
                                 p.predicted_team, p.points_awarded, p.created_at
                          FROM users u
                          LEFT JOIN predictions p ON u.id = p.user_id AND p.match_id = :match_id
                          WHERE u.role = 'user'
                          ORDER BY u.nickname ASC";
    $stmt_admin_group = $pdo->prepare($sql_admin_group);
    $stmt_admin_group->execute(['match_id' => $selected_match_id]);
    $admin_predictions = $stmt_admin_group->fetchAll();
}

// 4. Dữ liệu Chart: điểm tích lũy theo ngày (top 6 người)
$top6 = array_slice($leaderboard, 0, 6);
$top6_ids = array_column($top6, 'id');
$chart_data = [];
$chart_labels = [];
if (!empty($top6_ids)) {
    $in = implode(',', $top6_ids);
    $daily = $pdo->query("
        SELECT dr.user_id, u.nickname, dr.ranking_date, dr.total_points
        FROM daily_rankings dr
        JOIN users u ON dr.user_id=u.id
        WHERE dr.user_id IN ($in)
        ORDER BY dr.ranking_date ASC
    ")->fetchAll();
    $dates = array_unique(array_column($daily, 'ranking_date'));
    sort($dates);
    $chart_labels = array_values(array_slice($dates, -14)); // 14 ngày gần nhất
    foreach ($top6 as $u) {
        $pts_by_date = [];
        foreach ($daily as $row) {
            if ($row['user_id'] == $u['id']) $pts_by_date[$row['ranking_date']] = $row['total_points'];
        }
        $series = [];
        foreach ($chart_labels as $d) { $series[] = $pts_by_date[$d] ?? null; }
        $chart_data[] = ['label' => $u['nickname'], 'data' => $series];
    }
}

// 5. Phân bổ điểm số (histogram)
$score_dist = $pdo->query("
    SELECT total_points, COUNT(*) AS cnt
    FROM (
        SELECT u.id, COALESCE(SUM(p.points_awarded),0) AS total_points
        FROM users u LEFT JOIN predictions p ON u.id=p.user_id WHERE u.role='user' GROUP BY u.id
    ) t GROUP BY total_points ORDER BY total_points
")->fetchAll();

// 6. Hoạt động theo ngày (số dự đoán được gửi mỗi ngày)
$activity = $pdo->query("
    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
    FROM predictions GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 14
")->fetchAll();
$activity = array_reverse($activity);

// Màu sắc cho chart
$palette = ['#d4af37','#00cc77','#ff6b6b','#4facfe','#f857a6','#ff9f43'];
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h2 style="color:var(--primary); font-size:24px; font-weight:800; text-transform:uppercase;">Báo Cáo Thống Kê</h2>
        <p style="color:var(--text-muted); font-size:14px;">Tổng quan hiệu suất & hoạt động của toàn bộ người chơi</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-sliders"></i> Dashboard</a>
        <a href="matches.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-futbol"></i> Trận đấu</a>
        <a href="users.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-users"></i> Thành viên</a>
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa-solid fa-print"></i> In báo cáo</button>
    </div>
</div>

<!-- ── KPI CARDS ─────────────────────────────────── -->
<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:28px;">
<?php
$kpis = [
    ['icon'=>'fa-users',          'color'=>'#d4af37', 'val'=>$total_users,   'label'=>'Người chơi'],
    ['icon'=>'fa-futbol',         'color'=>'#4facfe', 'val'=>$total_matches, 'label'=>'Tổng trận đấu'],
    ['icon'=>'fa-circle-check',   'color'=>'#00cc77', 'val'=>$finished,      'label'=>'Đã kết thúc'],
    ['icon'=>'fa-clock',          'color'=>'#ff9f43', 'val'=>$upcoming,      'label'=>'Sắp diễn ra'],
    ['icon'=>'fa-bullseye',       'color'=>'#f857a6', 'val'=>$total_preds,   'label'=>'Lượt dự đoán'],
    ['icon'=>'fa-percent',        'color'=>'#a29bfe', 'val'=>$participation.'%','label'=>'Tỷ lệ tham gia'],
];
foreach ($kpis as $k): ?>
    <div style="background:var(--card-bg); border:1px solid var(--glass-border); border-radius:14px; padding:20px 16px; display:flex; flex-direction:column; gap:8px; box-shadow:var(--shadow); transition:var(--transition);"
         onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
        <div style="width:42px; height:42px; border-radius:10px; background:<?= $k['color'] ?>22; display:flex; align-items:center; justify-content:center;">
            <i class="fa-solid <?= $k['icon'] ?>" style="color:<?= $k['color'] ?>; font-size:18px;"></i>
        </div>
        <div style="font-size:28px; font-weight:800; color:var(--text-main); line-height:1;"><?= $k['val'] ?></div>
        <div style="font-size:13px; color:var(--text-muted); font-weight:500;"><?= $k['label'] ?></div>
    </div>
<?php endforeach; ?>
</div>

<!-- ── CHARTS ROW ─────────────────────────────────── -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:28px;">

    <!-- Chart: Điểm tích lũy theo ngày -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-title"><i class="fa-solid fa-chart-line"></i> Điểm Tích Lũy Theo Ngày (Top <?= count($top6) ?> người)</div>
        <canvas id="chartTrend" style="max-height:280px;"></canvas>
    </div>

    <!-- Chart: Phân bổ điểm -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-title"><i class="fa-solid fa-chart-bar"></i> Phân Bổ Điểm Số</div>
        <canvas id="chartDist" style="max-height:280px;"></canvas>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px;">
    <!-- Chart: Hoạt động theo ngày -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-title"><i class="fa-solid fa-wave-square"></i> Lượt Dự Đoán Theo Ngày</div>
        <canvas id="chartActivity" style="max-height:200px;"></canvas>
    </div>

    <!-- Top 3 nổi bật -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-title"><i class="fa-solid fa-trophy"></i> Top 3 Dẫn Đầu</div>
        <?php foreach (array_slice($leaderboard, 0, 3) as $i => $p):
            $medals = ['🥇','🥈','🥉'];
            $colors = ['#d4af37','#a8a9ad','#cd7f32'];
            $rate = $p['played'] > 0 ? round($p['wins']/$p['played']*100) : 0;
        ?>
        <div style="display:flex; align-items:center; gap:14px; padding:14px 0; border-bottom:1px solid var(--glass-border);">
            <div style="font-size:28px;"><?= $medals[$i] ?></div>
            <div style="flex:1;">
                <div style="font-weight:700; font-size:16px; color:var(--text-main);"><?= htmlspecialchars($p['nickname']) ?></div>
                <div style="font-size:12.5px; color:var(--text-muted);"><?= $p['wins'] ?>W / <?= $p['losses'] ?>L / <?= $p['draws'] ?>H &nbsp;|&nbsp; Tỷ lệ thắng: <?= $rate ?>%</div>
            </div>
            <div style="font-size:26px; font-weight:800; color:<?= $colors[$i] ?>;"><?= $p['total_points'] ?>đ</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── BẢNG XẾP HẠNG ĐẦY ĐỦ ─────────────────────── -->
<div class="card">
    <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; width: 100%;">
        <span><i class="fa-solid fa-ranking-star"></i> Bảng Xếp Hạng Toàn Bộ</span>
        <button type="button" onclick="exportToPDF('pdf-leaderboard-template', 'Bang_Xep_Hang_Toan_Bo')" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-file-pdf"></i> Xuất PDF
        </button>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr>
                <th style="width:50px;">#</th>
                <th>Người chơi</th>
                <th style="text-align:center;">Đã chơi</th>
                <th style="text-align:center; color:#00cc77;">Thắng</th>
                <th style="text-align:center; color:#ff6b6b;">Thua</th>
                <th style="text-align:center; color:#ff9f43;">Hòa</th>
                <th style="text-align:center;">Tỷ lệ thắng</th>
                <th style="text-align:center;">Tổng điểm</th>
                <th style="text-align:right;">Hiệu suất</th>
            </tr></thead>
            <tbody>
            <?php foreach ($leaderboard as $i => $p):
                $rank = $i + 1;
                $rate = $p['played'] > 0 ? round($p['wins']/$p['played']*100) : 0;
                $pts  = (int)$p['total_points'];
                $ptColor = $pts > 0 ? '#00cc77' : ($pts < 0 ? '#ff6b6b' : 'var(--text-muted)');
            ?>
            <tr>
                <td>
                    <?php if ($rank<=3): ?>
                        <span style="font-size:20px;"><?= ['🥇','🥈','🥉'][$rank-1] ?></span>
                    <?php else: ?>
                        <span style="font-weight:700; color:var(--text-muted);"><?= $rank ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($p['nickname']) ?></div>
                    <div style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($p['real_name']) ?></div>
                </td>
                <td style="text-align:center; font-weight:600;"><?= $p['played'] ?></td>
                <td style="text-align:center;">
                    <span style="background:rgba(0,204,119,.12); color:#00cc77; padding:3px 10px; border-radius:20px; font-weight:700;"><?= $p['wins'] ?></span>
                </td>
                <td style="text-align:center;">
                    <span style="background:rgba(255,107,107,.12); color:#ff6b6b; padding:3px 10px; border-radius:20px; font-weight:700;"><?= $p['losses'] ?></span>
                </td>
                <td style="text-align:center;">
                    <span style="background:rgba(255,159,67,.12); color:#ff9f43; padding:3px 10px; border-radius:20px; font-weight:700;"><?= $p['draws'] ?></span>
                </td>
                <td style="text-align:center;">
                    <div style="display:flex; align-items:center; gap:8px; justify-content:center;">
                        <div style="flex:1; max-width:80px; height:6px; background:rgba(0,0,0,.08); border-radius:3px; overflow:hidden;">
                            <div style="height:100%; width:<?= $rate ?>%; background:linear-gradient(90deg,#d4af37,#00cc77); border-radius:3px;"></div>
                        </div>
                        <span style="font-size:13px; font-weight:600;"><?= $rate ?>%</span>
                    </div>
                </td>
                <td style="text-align:center; font-size:22px; font-weight:800; color:<?= $ptColor ?>;">
                    <?= $pts > 0 ? '+' : '' ?><?= $pts ?>
                </td>
                <td style="text-align:right;">
                    <?php
                    $all_pts = array_column($leaderboard,'total_points');
                    $max_pts = max(array_map('abs', $all_pts)) ?: 1;
                    $bar_w = $p['played'] > 0 ? min(100, round(abs($pts)/$max_pts*100)) : 0;
                    $bar_c = $pts >= 0 ? 'linear-gradient(90deg,#d4af37,#aa8414)' : 'linear-gradient(90deg,#ff6b6b,#d9383a)';
                    ?>
                    <div style="height:8px; width:<?= $bar_w ?>%; background:<?= $bar_c ?>; border-radius:4px; min-width:4px; margin-left:auto;"></div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── THỐNG KÊ TỪNG TRẬN ─────────────────────────── -->
<div class="card">
    <div class="card-title"><i class="fa-solid fa-futbol"></i> Phân Tích Từng Trận Đấu</div>
    <div class="table-responsive">
        <table>
            <thead><tr>
                <th>Trận đấu</th>
                <th style="text-align:center;">Kèo</th>
                <th style="text-align:center;">Kết quả</th>
                <th style="text-align:center;">Dự đoán</th>
                <th style="text-align:center;">Tỷ lệ chọn</th>
                <th style="text-align:center; color:#00cc77;">Thắng</th>
                <th style="text-align:center; color:#ff6b6b;">Thua</th>
            </tr></thead>
            <tbody>
            <?php foreach ($match_stats as $m):
                $total = (int)$m['total_pred'];
                $home_pct = $total > 0 ? round($m['home_picks']/$total*100) : 0;
                $away_pct = 100 - $home_pct;
                $is_done = in_array($m['status'],['FT','AET','PEN']);
                $hc = (float)$m['handicap'];
                $hc_str = $hc == 0 ? 'Đồng banh' : ($hc > 0 ? htmlspecialchars($m['home_team']).' chấp '.$hc : htmlspecialchars($m['away_team']).' chấp '.abs($hc));
            ?>
            <tr>
                <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($m['home_team']) ?> vs <?= htmlspecialchars($m['away_team']) ?></div>
                    <div style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($m['round']) ?></div>
                </td>
                <td style="text-align:center; font-size:12.5px; color:var(--primary);"><?= $hc_str ?></td>
                <td style="text-align:center;">
                    <?php if ($is_done): ?>
                        <span style="font-weight:800; font-size:16px; color:var(--text-main);"><?= $m['home_score'] ?> – <?= $m['away_score'] ?></span>
                    <?php else: ?>
                        <span style="font-size:12px; color:var(--text-muted);">Chưa diễn ra</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center; font-weight:600;"><?= $total ?> lượt</td>
                <td style="min-width:180px;">
                    <?php if ($total > 0): ?>
                    <div style="display:flex; align-items:center; gap:6px; font-size:12px;">
                        <span style="color:var(--primary); font-weight:600; min-width:32px;"><?= $home_pct ?>%</span>
                        <div style="flex:1; height:10px; border-radius:5px; overflow:hidden; background:rgba(0,0,0,.06);">
                            <div style="height:100%; width:<?= $home_pct ?>%; background:linear-gradient(90deg,#d4af37,#aa8414);"></div>
                        </div>
                        <span style="color:#4facfe; font-weight:600; min-width:32px; text-align:right;"><?= $away_pct ?>%</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:11px; color:var(--text-muted); margin-top:2px;">
                        <span><?= htmlspecialchars($m['home_team']) ?> (<?= $m['home_picks'] ?>)</span>
                        <span><?= htmlspecialchars($m['away_team']) ?> (<?= $m['away_picks'] ?>)</span>
                    </div>
                    <?php else: ?>
                    <span style="color:var(--text-muted); font-size:12px;">Chưa có dự đoán</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if ($is_done): ?>
                    <span style="background:rgba(0,204,119,.12); color:#00cc77; padding:3px 10px; border-radius:20px; font-weight:700;"><?= $m['winners'] ?></span>
                    <?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if ($is_done): ?>
                    <span style="background:rgba(255,107,107,.12); color:#ff6b6b; padding:3px 10px; border-radius:20px; font-weight:700;"><?= $m['losers'] ?></span>
                    <?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── DỰ ĐOÁN CHI TIẾT TỪNG THÀNH VIÊN (PDF Export) ── -->
<div class="card" id="admin-match-predictions-card">
    <div class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; width: 100%;">
        <span><i class="fa-solid fa-file-invoice text-primary"></i> Chi tiết Dự đoán & Kết quả thành viên</span>
        <?php if ($selected_match): ?>
        <button type="button" onclick="exportToPDF('pdf-match-predictions-template', 'Danh_Sach_Thanh_Vien_Du_Doan_<?php echo htmlspecialchars($selected_match['home_team'] . '_vs_' . $selected_match['away_team']); ?>')" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-file-pdf"></i> Xuất PDF
        </button>
        <?php endif; ?>
    </div>
    
    <!-- Bộ lọc trận đấu -->
    <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <label style="white-space: nowrap; font-weight: 500; color: var(--text-muted);">Chọn trận đấu:</label>
        <select name="match_id" class="form-control" onchange="this.form.submit()" style="flex: 1; min-width: 200px;">
            <?php foreach ($match_stats as $m): ?>
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
    <?php else: ?>
        <!-- Khung thông tin trận đấu -->
        <div style="background: rgba(0, 0, 0, 0.02); border: 1px solid var(--glass-border); padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
            <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">
                <?php echo htmlspecialchars($selected_match['round']); ?>
            </div>
            <div style="font-size: 18px; font-weight: 700; color: var(--text-main); margin-bottom: 5px;">
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
            <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 5px;">
                <i class="fa-solid fa-scale-balanced"></i> Kèo chấp: 
                <strong style="color: var(--primary);">
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
        
        <!-- Bảng dự đoán của nhóm -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Thành viên</th>
                        <th style="text-align: center;">Dự đoán</th>
                        <th style="text-align: center;">Kết quả</th>
                        <th style="text-align: right;">Điểm thưởng</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admin_predictions as $gp): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: var(--text-main);">
                                    <?php echo htmlspecialchars($gp['nickname']); ?>
                                </div>
                                <div style="font-size: 11px; color: var(--text-muted);">
                                    <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($gp['real_name']); ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if (empty($gp['predicted_team'])): ?>
                                    <span style="color: var(--accent-red); font-size: 14px;"><i class="fa-solid fa-circle-minus"></i> Không dự đoán</span>
                                <?php else: ?>
                                    <strong style="font-size: 16px; color: var(--primary);">
                                        <?php echo htmlspecialchars(($gp['predicted_team'] === 'home') ? $selected_match['home_team'] : $selected_match['away_team']); ?>
                                    </strong>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php 
                                if ($gp['points_awarded'] !== null) {
                                    if ($gp['points_awarded'] > 0) {
                                        echo '<span style="background: rgba(0,204,119,0.12); color: var(--accent); padding: 3px 8px; border-radius: 4px; font-size: 11.5px; font-weight: bold;"><i class="fa-solid fa-circle-check"></i> Thắng kèo</span>';
                                    } elseif ($gp['points_awarded'] < 0) {
                                        if (empty($gp['predicted_team'])) {
                                            echo '<span style="background: rgba(255,107,107,0.12); color: var(--accent-red); padding: 3px 8px; border-radius: 4px; font-size: 11.5px; font-weight: bold;"><i class="fa-solid fa-circle-xmark"></i> Thua (Không dự đoán)</span>';
                                        } else {
                                            echo '<span style="background: rgba(255,107,107,0.12); color: var(--accent-red); padding: 3px 8px; border-radius: 4px; font-size: 11.5px; font-weight: bold;"><i class="fa-solid fa-circle-xmark"></i> Thua kèo</span>';
                                        }
                                    } else {
                                        echo '<span style="background: rgba(0,0,0,0.05); color: var(--text-muted); padding: 3px 8px; border-radius: 4px; font-size: 11.5px;"><i class="fa-solid fa-circle-minus"></i> Hòa kèo</span>';
                                    }
                                } elseif (in_array($selected_match['status'], ['FT', 'AET', 'PEN'])) {
                                    echo '<span style="background: rgba(255,107,107,0.12); color: var(--accent-red); padding: 3px 8px; border-radius: 4px; font-size: 11.5px; font-weight: bold;"><i class="fa-solid fa-circle-xmark"></i> Thua (Không dự đoán)</span>';
                                } else {
                                    echo '<span style="color: var(--text-muted); font-size: 12px;">—</span>';
                                }
                                ?>
                            </td>
                            <td style="text-align: right; font-weight: bold;">
                                <?php 
                                if ($gp['points_awarded'] !== null) {
                                    if ($gp['points_awarded'] > 0) {
                                         echo '<span style="color: var(--accent);">+' . $gp['points_awarded'] . 'đ</span>';
                                     } elseif ($gp['points_awarded'] < 0) {
                                         echo '<span style="color: var(--accent-red);">' . $gp['points_awarded'] . 'đ</span>';
                                     } else {
                                         echo '<span style="color: var(--text-muted);">0đ</span>';
                                     }
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

<!-- ── CHART.JS ───────────────────────────────────── -->
<script>
const PALETTE = <?= json_encode($palette) ?>;

// Chart 1: Trend line
(function(){
    const labels = <?= json_encode($chart_labels) ?>;
    const datasets = <?= json_encode($chart_data) ?>.map((s,i)=>({
        label: s.label,
        data: s.data,
        borderColor: PALETTE[i % PALETTE.length],
        backgroundColor: PALETTE[i % PALETTE.length] + '22',
        borderWidth: 2.5,
        pointRadius: 4,
        pointHoverRadius: 6,
        tension: 0.4,
        fill: false,
        spanGaps: true
    }));
    new Chart(document.getElementById('chartTrend'), {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { position:'bottom', labels:{ boxWidth:12, font:{size:12} } } },
            scales: {
                x: { grid:{color:'rgba(0,0,0,.05)'}, ticks:{font:{size:11}} },
                y: { grid:{color:'rgba(0,0,0,.05)'}, ticks:{font:{size:11}} }
            }
        }
    });
})();

// Chart 2: Score distribution bar
(function(){
    const dist = <?= json_encode(array_values($score_dist)) ?>;
    const labels = dist.map(d => d.total_points + ' điểm');
    const counts  = dist.map(d => d.cnt);
    new Chart(document.getElementById('chartDist'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Số người',
                data: counts,
                backgroundColor: labels.map((_,i) => PALETTE[i % PALETTE.length] + 'cc'),
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend:{ display:false } },
            scales: {
                x: { grid:{display:false}, ticks:{font:{size:11}} },
                y: { grid:{color:'rgba(0,0,0,.05)'}, ticks:{stepSize:1, font:{size:11}} }
            }
        }
    });
})();

// Chart 3: Daily activity
(function(){
    const rows = <?= json_encode(array_values($activity)) ?>;
    const labels = rows.map(r => r.day);
    const data   = rows.map(r => r.cnt);
    new Chart(document.getElementById('chartActivity'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Lượt dự đoán',
                data,
                backgroundColor: '#d4af3766',
                borderColor: '#d4af37',
                borderWidth: 2,
                borderRadius: 5,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend:{ display:false } },
            scales: {
                x: { grid:{display:false}, ticks:{font:{size:10}, maxRotation:45} },
                y: { grid:{color:'rgba(0,0,0,.05)'}, ticks:{stepSize:1, font:{size:11}} }
            }
        }
    });
})();
</script>

<style>
@media print {
    header, .btn, button { display: none !important; }
    body { background: #fff !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
