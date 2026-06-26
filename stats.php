<?php
// stats.php — Thống kê cá nhân người chơi
require_once __DIR__ . '/includes/auth.php';
require_login();

$page_title = "Thống kê của tôi";
require_once __DIR__ . '/includes/header.php';

$uid = $_SESSION['user_id'];

// ============================================================
// TRUY VẤN DỮ LIỆU
// ============================================================

// 1. Hồ sơ & tổng điểm
$profile = $pdo->prepare("SELECT * FROM users WHERE id=?");
$profile->execute([$uid]);
$me = $profile->fetch();

if (!$me) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$stats = $pdo->prepare("
    SELECT
        COUNT(CASE WHEN p.prediction_status=1 THEN 1 END)             AS played,
        COALESCE(SUM(p.points_awarded),0)                              AS total_pts,
        SUM(CASE WHEN p.points_awarded=1  THEN 1 ELSE 0 END)          AS wins,
        SUM(CASE WHEN p.points_awarded=-1 THEN 1 ELSE 0 END)          AS losses,
        SUM(CASE WHEN p.points_awarded=0 AND p.prediction_status=1 THEN 1 ELSE 0 END) AS draws,
        COUNT(p.id)                                                    AS total_pred
    FROM predictions p WHERE p.user_id=?
");
$stats->execute([$uid]);
$s = $stats->fetch();

// Cast stats values to integers/floats for safety and type consistency
$s['total_pts'] = (int)($s['total_pts'] ?? 0);
$s['played'] = (int)($s['played'] ?? 0);
$s['wins'] = (int)($s['wins'] ?? 0);
$s['losses'] = (int)($s['losses'] ?? 0);
$s['draws'] = (int)($s['draws'] ?? 0);
$s['total_pred'] = (int)($s['total_pred'] ?? 0);

$win_rate = $s['played'] > 0 ? round($s['wins']/$s['played']*100) : 0;

// 2. Xếp hạng hiện tại
$rank_row = $pdo->prepare("SELECT rank_position, total_points FROM daily_rankings WHERE user_id=? ORDER BY ranking_date DESC LIMIT 1");
$rank_row->execute([$uid]);
$rank_info = $rank_row->fetch();
$my_rank = $rank_info ? $rank_info['rank_position'] : '—';

// 3. Lịch sử điểm tích lũy (chart)
$daily_pts = $pdo->prepare("
    SELECT ranking_date AS day, total_points AS pts
    FROM daily_rankings WHERE user_id=? ORDER BY ranking_date ASC LIMIT 30
");
$daily_pts->execute([$uid]);
$daily_pts_rows = $daily_pts->fetchAll();

// 4. Chuỗi thắng liên tiếp gần nhất
$history = $pdo->prepare("
    SELECT p.points_awarded, m.match_time
    FROM predictions p JOIN matches m ON p.match_id=m.id
    WHERE p.user_id=? AND p.prediction_status=1
    ORDER BY m.match_time ASC
");
$history->execute([$uid]);
$hist = $history->fetchAll();
$streak = 0; $best_streak = 0; $tmp = 0;
foreach (array_reverse($hist) as $h) {
    if ($streak === 0 && $h['points_awarded'] === '1') $streak++; else break;
}
foreach ($hist as $h) {
    if ($h['points_awarded'] == 1) { $tmp++; $best_streak = max($best_streak, $tmp); } else $tmp = 0;
}

// 5. Lịch sử dự đoán đầy đủ
$pred_list = $pdo->prepare("
    SELECT m.home_team, m.away_team, m.home_score, m.away_score, m.round,
           m.match_time, m.status, m.handicap,
           p.predicted_team, p.points_awarded, p.prediction_status, p.created_at
    FROM predictions p JOIN matches m ON p.match_id=m.id
    WHERE p.user_id=?
    ORDER BY m.match_time DESC
");
$pred_list->execute([$uid]);
$pred_list = $pred_list->fetchAll();

// 6. So sánh với nhóm
$all_users = $pdo->query("
    SELECT COALESCE(SUM(p.points_awarded),0) AS tp
    FROM users u LEFT JOIN predictions p ON u.id=p.user_id
    WHERE u.role='user' GROUP BY u.id
")->fetchAll();
$all_pts = array_map('intval', array_column($all_users,'tp'));
$avg_pts = count($all_pts) > 0 ? round(array_sum($all_pts)/count($all_pts),1) : 0;
$above_avg = count(array_filter($all_pts, function($x) use ($s) {
    return $x < $s['total_pts'];
}));
$percentile = count($all_pts) > 1 ? round($above_avg/(count($all_pts)-1)*100) : 100;
?>

<!-- ── HEADER ────────────────────────────────────── -->
<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h2 style="font-size:24px; font-weight:800; color:var(--primary); text-transform:uppercase;">Thống Kê Của Tôi</h2>
        <p style="color:var(--text-muted); font-size:14px;">Hiệu suất & lịch sử dự đoán cá nhân</p>
    </div>
    <a href="predictions.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-clock-rotate-left"></i> Lịch sử dự đoán</a>
</div>

<!-- ── PROFILE CARD ──────────────────────────────── -->
<div class="card" style="background:linear-gradient(135deg, rgba(212,175,55,.08), rgba(0,136,85,.05)); margin-bottom:20px;">
    <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
        <div style="width:70px; height:70px; border-radius:50%; background:var(--primary-grad); display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:800; color:#000; flex-shrink:0;">
            <?php 
            $fc = function_exists('mb_substr') ? mb_substr($me['nickname'],0,1) : substr($me['nickname'],0,1);
            echo htmlspecialchars(function_exists('mb_strtoupper') ? mb_strtoupper($fc) : strtoupper($fc)); 
            ?>
        </div>
        <div style="flex:1;">
            <div style="font-size:22px; font-weight:800; color:var(--text-main);"><?= htmlspecialchars($me['nickname']) ?></div>
            <div style="font-size:14px; color:var(--text-muted);"><?= htmlspecialchars($me['real_name']) ?></div>
            <div style="font-size:13px; color:var(--text-muted); margin-top:4px;">
                Tham gia từ <?= date('d/m/Y', strtotime($me['created_at'])) ?>
            </div>
        </div>
        <div style="display:flex; gap:24px; flex-wrap:wrap; align-items:center;">
            <div style="text-align:center;">
                <div style="font-size:36px; font-weight:800; color:<?= $s['total_pts'] >= 0 ? '#00cc77' : '#ff6b6b' ?>;"><?= $s['total_pts'] > 0 ? '+' : '' ?><?= $s['total_pts'] ?></div>
                <div style="font-size:13px; color:var(--text-muted);">Tổng điểm</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:36px; font-weight:800; color:var(--primary);"><?= $my_rank !== '—' ? '#'.$my_rank : '—' ?></div>
                <div style="font-size:13px; color:var(--text-muted);">Xếp hạng</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:36px; font-weight:800; color:var(--text-main);"><?= $win_rate ?>%</div>
                <div style="font-size:13px; color:var(--text-muted);">Tỷ lệ thắng</div>
            </div>
        </div>
    </div>
</div>

<!-- ── KPI ROW ───────────────────────────────────── -->
<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:24px;">
<?php
$kpis = [
    ['icon'=>'fa-gamepad',         'color'=>'#4facfe', 'val'=>$s['total_pred'],   'label'=>'Đã dự đoán'],
    ['icon'=>'fa-circle-check',    'color'=>'#00cc77', 'val'=>$s['wins'],         'label'=>'Trận thắng'],
    ['icon'=>'fa-circle-xmark',    'color'=>'#ff6b6b', 'val'=>$s['losses'],       'label'=>'Trận thua'],
    ['icon'=>'fa-handshake',       'color'=>'#ff9f43', 'val'=>$s['draws'],        'label'=>'Trận hòa điểm'],
    ['icon'=>'fa-fire-flame-curved','color'=>'#f857a6', 'val'=>$streak,           'label'=>'Chuỗi thắng hiện tại'],
    ['icon'=>'fa-star',            'color'=>'#d4af37', 'val'=>$best_streak,       'label'=>'Chuỗi thắng dài nhất'],
];
foreach ($kpis as $k): ?>
<div style="background:var(--card-bg); border:1px solid var(--glass-border); border-radius:13px; padding:18px 14px; text-align:center; box-shadow:var(--shadow);">
    <i class="fa-solid <?= $k['icon'] ?>" style="color:<?= $k['color'] ?>; font-size:22px; margin-bottom:8px; display:block;"></i>
    <div style="font-size:26px; font-weight:800; color:var(--text-main);"><?= $k['val'] ?></div>
    <div style="font-size:12px; color:var(--text-muted); margin-top:2px;"><?= $k['label'] ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- ── CHARTS ROW ───────────────────────────────── -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:18px; margin-bottom:24px;">

    <!-- Biểu đồ điểm tích lũy -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-title"><i class="fa-solid fa-chart-area"></i> Điểm Tích Lũy Theo Thời Gian</div>
        <?php if (count($daily_pts_rows) > 0): ?>
        <canvas id="chartMyTrend" style="max-height:220px;"></canvas>
        <?php else: ?>
        <p style="text-align:center; color:var(--text-muted); padding:40px 0;">Chưa có đủ dữ liệu lịch sử.</p>
        <?php endif; ?>
    </div>

    <!-- Biểu đồ Win/Loss/Draw (Doughnut) -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-title"><i class="fa-solid fa-chart-pie"></i> Tỷ Lệ W/L/H</div>
        <?php if ($s['played'] > 0): ?>
        <canvas id="chartPie" style="max-height:220px;"></canvas>
        <?php else: ?>
        <p style="text-align:center; color:var(--text-muted); padding:40px 0;">Chưa có trận nào kết thúc.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ── SO SÁNH VỚI NHÓM ─────────────────────────── -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-title"><i class="fa-solid fa-users"></i> So Sánh Với Nhóm</div>
    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; text-align:center;">
        <div>
            <div style="font-size:13px; color:var(--text-muted); margin-bottom:6px;">Điểm của bạn</div>
            <div style="font-size:32px; font-weight:800; color:<?= $s['total_pts'] >= 0 ? '#00cc77' : '#ff6b6b' ?>;"><?= $s['total_pts'] > 0 ? '+' : '' ?><?= $s['total_pts'] ?></div>
        </div>
        <div>
            <div style="font-size:13px; color:var(--text-muted); margin-bottom:6px;">Điểm TB nhóm</div>
            <div style="font-size:32px; font-weight:800; color:var(--primary);"><?= $avg_pts > 0 ? '+' : '' ?><?= $avg_pts ?></div>
        </div>
        <div>
            <div style="font-size:13px; color:var(--text-muted); margin-bottom:6px;">Top <?= 100-$percentile ?>% người chơi</div>
            <div style="font-size:32px; font-weight:800; color:var(--text-main);"><?= $percentile ?>%</div>
            <div style="font-size:12px; color:var(--text-muted);">vượt qua nhóm</div>
        </div>
    </div>
    <!-- Progress bar điểm so TB -->
    <?php
    $max_cmp = max(abs((float)$s['total_pts']), abs((float)$avg_pts), 1);
    $my_pct  = min(100, round(abs($s['total_pts'])/$max_cmp*80));
    $avg_pct = min(100, round(abs($avg_pts)/$max_cmp*80));
    ?>
    <div style="margin-top:20px;">
        <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text-muted); margin-bottom:6px;">
            <span><?= htmlspecialchars($me['nickname']) ?> (<?= $s['total_pts'] ?>đ)</span>
            <span>Trung bình (<?= $avg_pts ?>đ)</span>
        </div>
        <div style="height:10px; background:rgba(0,0,0,.07); border-radius:5px; overflow:hidden; position:relative;">
            <div style="height:100%; width:<?= $my_pct ?>%; background:linear-gradient(90deg,#d4af37,#aa8414); border-radius:5px; transition:width .8s;"></div>
        </div>
        <div style="height:10px; background:rgba(0,0,0,.07); border-radius:5px; overflow:hidden; margin-top:6px;">
            <div style="height:100%; width:<?= $avg_pct ?>%; background:linear-gradient(90deg,#4facfe,#00cc77); border-radius:5px; transition:width .8s;"></div>
        </div>
    </div>
</div>

<!-- ── LỊCH SỬ DỰ ĐOÁN ──────────────────────────── -->
<div class="card">
    <div class="card-title"><i class="fa-solid fa-list-check"></i> Lịch Sử Dự Đoán Chi Tiết</div>
    <div class="table-responsive">
        <table>
            <thead><tr>
                <th>Trận đấu</th>
                <th style="text-align:center;">Điểm chấp vui</th>
                <th style="text-align:center;">Tỷ số</th>
                <th style="text-align:center;">Đội chọn</th>
                <th style="text-align:center;">Thời gian đặt</th>
                <th style="text-align:center;">Kết quả</th>
            </tr></thead>
            <tbody>
            <?php foreach ($pred_list as $p):
                $is_done = in_array($p['status'],['FT','AET','PEN']);
                $hc = (float)$p['handicap'];
                $hc_str = $hc == 0 ? '0.0' : ($hc > 0 ? $p['home_team'].' -'.$hc : $p['away_team'].' -'.abs($hc));
                $team_name = $p['predicted_team'] === 'home' ? $p['home_team'] : ($p['predicted_team'] === 'away' ? $p['away_team'] : 'Không dự đoán');
                $pts = $p['points_awarded'];
                if ($pts === null)     { $badge = '<span style="background:rgba(0,0,0,.07);color:var(--text-muted);padding:3px 10px;border-radius:20px;font-size:12px;">Chờ kết quả</span>'; }
                elseif ($pts == 1)     { $badge = '<span style="background:rgba(0,204,119,.15);color:#00cc77;padding:3px 10px;border-radius:20px;font-weight:700;font-size:13px;">+1 Thắng ✓</span>'; }
                elseif ($pts == -1)    { 
                    if (empty($p['predicted_team'])) {
                        $badge = '<span style="background:rgba(255,107,107,.15);color:#ff6b6b;padding:3px 10px;border-radius:20px;font-weight:700;font-size:13px;">-1 Thua (Không dự đoán) ✗</span>';
                    } else {
                        $badge = '<span style="background:rgba(255,107,107,.15);color:#ff6b6b;padding:3px 10px;border-radius:20px;font-weight:700;font-size:13px;">-1 Thua ✗</span>';
                    }
                }
                else                   { $badge = '<span style="background:rgba(255,159,67,.15);color:#ff9f43;padding:3px 10px;border-radius:20px;font-weight:700;font-size:13px;">0 Hòa điểm</span>'; }
            ?>
            <tr>
                <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($p['home_team']) ?> vs <?= htmlspecialchars($p['away_team']) ?></div>
                    <div style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($p['round']) ?> — <?= date('d/m/Y H:i', strtotime($p['match_time'])) ?></div>
                </td>
                <td style="text-align:center; font-size:12.5px; color:var(--primary); font-weight:600;"><?= htmlspecialchars($hc_str) ?></td>
                <td style="text-align:center; font-weight:800; font-size:16px;">
                    <?= $is_done ? $p['home_score'].' – '.$p['away_score'] : '<span style="color:var(--text-muted);font-size:13px;">Chưa diễn ra</span>' ?>
                </td>
                <td style="text-align:center; font-weight:600; color:var(--primary);"><?= htmlspecialchars($team_name) ?></td>
                <td style="text-align:center; font-size:12.5px; color:var(--text-muted);"><?= date('d/m H:i', strtotime($p['created_at'])) ?></td>
                <td style="text-align:center;"><?= $badge ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pred_list)): ?>
            <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">Bạn chưa có lượt dự đoán nào.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── SCRIPTS ───────────────────────────────────── -->
<script>
<?php if (count($daily_pts_rows) > 0): ?>
(function(){
    const rows  = <?= json_encode(array_values($daily_pts_rows)) ?>;
    const labels = rows.map(r=>r.day);
    const data   = rows.map(r=>Number(r.pts));
    new Chart(document.getElementById('chartMyTrend'),{
        type:'line',
        data:{labels, datasets:[{
            label:'Điểm tích lũy',
            data, fill:true,
            borderColor:'#d4af37',
            backgroundColor:'rgba(212,175,55,.12)',
            borderWidth:2.5,
            pointRadius:4,
            pointHoverRadius:7,
            tension:0.4
        }]},
        options:{
            responsive:true, maintainAspectRatio:true,
            plugins:{legend:{display:false}},
            scales:{
                x:{grid:{color:'rgba(0,0,0,.05)'}, ticks:{font:{size:11}, maxRotation:45}},
                y:{grid:{color:'rgba(0,0,0,.05)'}, ticks:{font:{size:11}}}
            }
        }
    });
})();
<?php endif; ?>
<?php if ($s['played'] > 0): ?>
(function(){
    new Chart(document.getElementById('chartPie'),{
        type:'doughnut',
        data:{
            labels:['Thắng','Thua','Hòa điểm'],
            datasets:[{
                data:[<?= $s['wins'] ?>,<?= $s['losses'] ?>,<?= $s['draws'] ?>],
                backgroundColor:['#00cc7788','#ff6b6b88','#ff9f4388'],
                borderColor:['#00cc77','#ff6b6b','#ff9f43'],
                borderWidth:2,
                hoverOffset:8
            }]
        },
        options:{
            responsive:true, maintainAspectRatio:true, cutout:'65%',
            plugins:{
                legend:{position:'bottom', labels:{boxWidth:12, font:{size:12}}},
                tooltip:{callbacks:{label:ctx=>' '+ctx.label+': '+ctx.parsed+' trận'}}
            }
        }
    });
})();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
