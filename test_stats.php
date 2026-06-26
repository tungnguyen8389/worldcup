<?php
// test_stats.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting stats.php diagnostics...\n";

require_once __DIR__ . '/config/database.php';

// Try to find a user
if (!$pdo) {
    die("Database connection failed ($pdo is null).\n");
}

$user = $pdo->query("SELECT id, username, nickname, real_name FROM users ORDER BY id ASC LIMIT 1")->fetch();
if (!$user) {
    die("No users found in database.\n");
}

$uid = $user['id'];
echo "Testing with User ID: $uid (" . $user['username'] . ")\n";

try {
    // 1. Hồ sơ & tổng điểm
    echo "Querying profile...\n";
    $profile = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $profile->execute([$uid]);
    $me = $profile->fetch();
    var_dump($me);

    echo "Querying stats...\n";
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
    var_dump($s);
    
    $win_rate = $s['played'] > 0 ? round($s['wins']/$s['played']*100) : 0;
    echo "Win rate: $win_rate%\n";

    // 2. Xếp hạng hiện tại
    echo "Querying rank...\n";
    $rank_row = $pdo->prepare("SELECT rank_position, total_points FROM daily_rankings WHERE user_id=? ORDER BY ranking_date DESC LIMIT 1");
    $rank_row->execute([$uid]);
    $rank_info = $rank_row->fetch();
    $my_rank = $rank_info ? $rank_info['rank_position'] : '—';
    echo "My rank: $my_rank\n";

    // 3. Lịch sử điểm tích lũy (chart)
    echo "Querying daily points...\n";
    $daily_pts = $pdo->prepare("
        SELECT ranking_date AS day, total_points AS pts
        FROM daily_rankings WHERE user_id=? ORDER BY ranking_date ASC LIMIT 30
    ");
    $daily_pts->execute([$uid]);
    $daily_pts_rows = $daily_pts->fetchAll();
    echo "Daily pts rows count: " . count($daily_pts_rows) . "\n";

    // 4. Chuỗi thắng liên tiếp gần nhất
    echo "Querying history...\n";
    $history = $pdo->prepare("
        SELECT p.points_awarded, m.match_time
        FROM predictions p JOIN matches m ON p.match_id=m.id
        WHERE p.user_id=? AND p.prediction_status=1
        ORDER BY m.match_time ASC
    ");
    $history->execute([$uid]);
    $hist = $history->fetchAll();
    echo "History count: " . count($hist) . "\n";
    $streak = 0; $best_streak = 0; $tmp = 0;
    foreach (array_reverse($hist) as $h) {
        if ($streak === 0 && $h['points_awarded'] === '1') $streak++; else break;
    }
    foreach ($hist as $h) {
        if ($h['points_awarded'] == 1) { $tmp++; $best_streak = max($best_streak, $tmp); } else $tmp = 0;
    }
    echo "Streak: $streak, Best streak: $best_streak\n";

    // 5. Lịch sử dự đoán đầy đủ
    echo "Querying predictions list...\n";
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
    echo "Pred list count: " . count($pred_list) . "\n";

    // 6. So sánh với nhóm
    echo "Querying group comparison...\n";
    $all_users = $pdo->query("
        SELECT COALESCE(SUM(p.points_awarded),0) AS tp
        FROM users u LEFT JOIN predictions p ON u.id=p.user_id
        WHERE u.role='user' GROUP BY u.id
    ")->fetchAll();
    $all_pts = array_column($all_users,'tp');
    $avg_pts = count($all_pts) > 0 ? round(array_sum($all_pts)/count($all_pts),1) : 0;
    $above_avg = count(array_filter($all_pts, function($x) use ($s) {
        return $x < $s['total_pts'];
    }));
    $percentile = count($all_pts) > 1 ? round($above_avg/(count($all_pts)-1)*100) : 100;
    echo "Group Avg pts: $avg_pts, percentile: $percentile%\n";
    
    echo "ALL TESTS PASSED SUCCESSFULLY WITHOUT ERRORS!\n";

} catch (PDOException $e) {
    echo "PDO EXCEPTION: " . $e->getMessage() . "\n";
} catch (Throwable $t) {
    echo "FATAL ERROR: " . $t->getMessage() . "\n";
}
