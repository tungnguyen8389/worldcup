<?php
// seed_data.php — Script tạo dữ liệu mẫu (chỉ dùng để development/testing)
// XÓA FILE NÀY SAU KHI SỬ DỤNG!
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (!$pdo) {
    die('Không thể kết nối CSDL!');
}

// Bảo vệ: chỉ chạy 1 lần
$existingUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
if ($existingUsers >= 10) {
    die('<pre>⚠️  Đã có ' . $existingUsers . ' user trong CSDL. Không tạo lại để tránh trùng lặp. Xóa dữ liệu cũ trước nếu muốn chạy lại.</pre>');
}

$pdo->beginTransaction();

try {
    // ==========================
    // 1. TẠO NGƯỜI CHƠI MẪU
    // ==========================
    $players = [
        ['username' => 'minhtu',   'nickname' => 'Minh Tú',    'real_name' => 'Nguyễn Minh Tú'],
        ['username' => 'hoangan',  'nickname' => 'Hồng Ân',    'real_name' => 'Lê Thị Hồng Ân'],
        ['username' => 'quocbao',  'nickname' => 'Quốc Bảo',   'real_name' => 'Trần Quốc Bảo'],
        ['username' => 'thuyhang', 'nickname' => 'Thuỳ Hằng',  'real_name' => 'Phạm Thuỳ Hằng'],
        ['username' => 'ducminh',  'nickname' => 'Đức Minh',   'real_name' => 'Võ Đức Minh'],
        ['username' => 'thanhlan', 'nickname' => 'Thanh Lan',  'real_name' => 'Đỗ Thanh Lan'],
        ['username' => 'phuocle',  'nickname' => 'Phước Lê',   'real_name' => 'Lê Văn Phước'],
        ['username' => 'ngocmai',  'nickname' => 'Ngọc Mai',   'real_name' => 'Nguyễn Thị Ngọc Mai'],
        ['username' => 'tuankhoa', 'nickname' => 'Tuấn Khoa',  'real_name' => 'Dương Tuấn Khoa'],
        ['username' => 'kimchi',   'nickname' => 'Kim Chi',    'real_name' => 'Bùi Thị Kim Chi'],
        ['username' => 'vietanh',  'nickname' => 'Việt Anh',   'real_name' => 'Vũ Việt Anh'],
        ['username' => 'mylinh',   'nickname' => 'Mỹ Linh',    'real_name' => 'Trịnh Thị Mỹ Linh'],
    ];

    $userIds = [];
    $password = password_hash('123456', PASSWORD_BCRYPT);
    $stmtUser = $pdo->prepare("INSERT IGNORE INTO users (username, password, nickname, real_name, role) VALUES (?, ?, ?, ?, 'user')");
    foreach ($players as $p) {
        $stmtUser->execute([$p['username'], $password, $p['nickname'], $p['real_name']]);
        $userIds[$p['username']] = $pdo->lastInsertId() ?: (int)$pdo->query("SELECT id FROM users WHERE username = '{$p['username']}'")->fetchColumn();
    }

    // ==========================
    // 2. TẠO TRẬN ĐẤU MẪU
    // ==========================
    // Trận đã kết thúc (để tính điểm)
    $finishedMatches = [
        ['id' => 90001, 'home' => 'Brazil',    'away' => 'Argentina', 'home_logo' => 'https://media.api-sports.io/football/teams/6.png',   'away_logo' => 'https://media.api-sports.io/football/teams/26.png', 'time' => '-8 days', 'status' => 'FT', 'home_score' => 2, 'away_score' => 1, 'round' => 'Vòng 1/8', 'handicap' => -0.5],
        ['id' => 90002, 'home' => 'Pháp',      'away' => 'Đức',       'home_logo' => 'https://media.api-sports.io/football/teams/2.png',   'away_logo' => 'https://media.api-sports.io/football/teams/25.png', 'time' => '-7 days', 'status' => 'FT', 'home_score' => 1, 'away_score' => 1, 'round' => 'Vòng 1/8', 'handicap' => 0.0],
        ['id' => 90003, 'home' => 'Anh',       'away' => 'Tây Ban Nha','home_logo' => 'https://media.api-sports.io/football/teams/10.png', 'away_logo' => 'https://media.api-sports.io/football/teams/9.png',  'time' => '-6 days', 'status' => 'FT', 'home_score' => 0, 'away_score' => 2, 'round' => 'Tứ kết', 'handicap' => 0.5],
        ['id' => 90004, 'home' => 'Hà Lan',    'away' => 'Bồ Đào Nha','home_logo' => 'https://media.api-sports.io/football/teams/1118.png','away_logo' => 'https://media.api-sports.io/football/teams/38.png', 'time' => '-5 days', 'status' => 'FT', 'home_score' => 3, 'away_score' => 1, 'round' => 'Tứ kết', 'handicap' => -1.0],
        ['id' => 90005, 'home' => 'Uruguay',   'away' => 'Bỉ',        'home_logo' => 'https://media.api-sports.io/football/teams/23.png',  'away_logo' => 'https://media.api-sports.io/football/teams/1.png',  'time' => '-4 days', 'status' => 'FT', 'home_score' => 2, 'away_score' => 2, 'round' => 'Vòng bảng', 'handicap' => 0.0],
        ['id' => 90006, 'home' => 'Croatia',   'away' => 'Nhật Bản',  'home_logo' => 'https://media.api-sports.io/football/teams/3.png',  'away_logo' => 'https://media.api-sports.io/football/teams/35.png', 'time' => '-3 days', 'status' => 'FT', 'home_score' => 1, 'away_score' => 1, 'round' => 'Vòng 1/8', 'handicap' => -0.5],
        ['id' => 90007, 'home' => 'Morocco',   'away' => 'Canada',    'home_logo' => 'https://media.api-sports.io/football/teams/32.png', 'away_logo' => 'https://media.api-sports.io/football/teams/101.png','time' => '-2 days', 'status' => 'FT', 'home_score' => 2, 'away_score' => 0, 'round' => 'Vòng bảng', 'handicap' => -0.5],
        ['id' => 90008, 'home' => 'Senegal',   'away' => 'Ecuador',   'home_logo' => 'https://media.api-sports.io/football/teams/796.png','away_logo' => 'https://media.api-sports.io/football/teams/22.png', 'time' => '-1 day',  'status' => 'FT', 'home_score' => 0, 'away_score' => 1, 'round' => 'Vòng bảng', 'handicap' => 0.25],
    ];

    // Trận sắp diễn ra
    $upcomingMatches = [
        ['id' => 90009, 'home' => 'Brazil',    'away' => 'Pháp',     'home_logo' => 'https://media.api-sports.io/football/teams/6.png',   'away_logo' => 'https://media.api-sports.io/football/teams/2.png',  'time' => '+1 day',  'status' => 'NS', 'home_score' => null, 'away_score' => null, 'round' => 'Bán kết', 'handicap' => -0.25],
        ['id' => 90010, 'home' => 'Hà Lan',   'away' => 'Tây Ban Nha','home_logo' => 'https://media.api-sports.io/football/teams/1118.png','away_logo' => 'https://media.api-sports.io/football/teams/9.png',  'time' => '+2 days', 'status' => 'NS', 'home_score' => null, 'away_score' => null, 'round' => 'Bán kết', 'handicap' => 0.5],
        ['id' => 90011, 'home' => 'Morocco',  'away' => 'Croatia',   'home_logo' => 'https://media.api-sports.io/football/teams/32.png',  'away_logo' => 'https://media.api-sports.io/football/teams/3.png',  'time' => '+4 days', 'status' => 'NS', 'home_score' => null, 'away_score' => null, 'round' => 'Tranh hạng 3', 'handicap' => 0.0],
        ['id' => 90012, 'home' => 'Brazil',   'away' => 'Hà Lan',   'home_logo' => 'https://media.api-sports.io/football/teams/6.png',   'away_logo' => 'https://media.api-sports.io/football/teams/1118.png','time' => '+7 days', 'status' => 'NS', 'home_score' => null, 'away_score' => null, 'round' => 'Chung kết', 'handicap' => -0.5],
    ];

    $allMatches = array_merge($finishedMatches, $upcomingMatches);

    $stmtMatch = $pdo->prepare("INSERT INTO matches (id, home_team, away_team, home_logo, away_logo, match_time, status, home_score, away_score, round, handicap) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), home_score=VALUES(home_score), away_score=VALUES(away_score), handicap=VALUES(handicap)");
    foreach ($allMatches as $m) {
        $matchTime = date('Y-m-d H:i:s', strtotime($m['time']));
        $stmtMatch->execute([$m['id'], $m['home'], $m['away'], $m['home_logo'], $m['away_logo'], $matchTime, $m['status'], $m['home_score'], $m['away_score'], $m['round'], $m['handicap']]);
    }

    // ==========================
    // 3. TẠO DỰ ĐOÁN MẪU (có logic thực tế)
    // ==========================
    // Kịch bản dự đoán: mỗi user sẽ chọn đội, một số đúng một số sai tạo nên bảng xếp hạng thực tế
    // Định nghĩa kết quả kèo cho từng trận đã kết thúc
    // adjusted_home = home_score + handicap => nếu > away_score thì home win kèo
    $matchKeoResult = [
        90001 => 'home',  // Brazil 2-1 Argentina, kèo -0.5 => adjusted=1.5 > 1 => home win
        90002 => 'away',  // Pháp 1-1 Đức, kèo 0.0 => hòa => không bên nào thắng
        90003 => 'away',  // Anh 0-2 TBN, kèo +0.5 => adjusted=0.5 < 2 => away win
        90004 => 'home',  // Hà Lan 3-1 BĐN, kèo -1.0 => adjusted=2 > 1 => home win
        90005 => 'away',  // Uruguay 2-2 Bỉ, kèo 0.0 => hòa
        90006 => 'away',  // Croatia 1-1 NB, kèo -0.5 => adjusted=0.5 < 1 => away win
        90007 => 'home',  // Morocco 2-0 Canada, kèo -0.5 => adjusted=1.5 > 0 => home win
        90008 => 'away',  // Senegal 0-1 Ecuador, kèo +0.25 => adjusted=0.25 < 1 => away win
    ];

    // Kịch bản đặt kèo của từng người (mix đúng/sai để tạo phân bổ điểm thực tế)
    $userPredictions = [
        'minhtu'   => [90001=>'home', 90002=>'home', 90003=>'away', 90004=>'home', 90005=>'home', 90006=>'home', 90007=>'home', 90008=>'away', 90009=>'home', 90010=>'home'],
        'hoangan'  => [90001=>'home', 90002=>'away', 90003=>'away', 90004=>'home', 90005=>'away', 90006=>'away', 90007=>'home', 90008=>'away', 90009=>'away', 90011=>'away'],
        'quocbao'  => [90001=>'away', 90002=>'home', 90003=>'home', 90004=>'home', 90005=>'home', 90006=>'away', 90007=>'home', 90008=>'home', 90010=>'away', 90012=>'home'],
        'thuyhang' => [90001=>'home', 90002=>'away', 90003=>'away', 90004=>'away', 90005=>'away', 90006=>'home', 90007=>'away', 90008=>'away', 90009=>'home', 90010=>'away'],
        'ducminh'  => [90001=>'home', 90002=>'home', 90003=>'away', 90004=>'home', 90005=>'home', 90006=>'away', 90007=>'home', 90008=>'away', 90009=>'away', 90012=>'home'],
        'thanhlan' => [90001=>'away', 90002=>'away', 90003=>'away', 90004=>'away', 90005=>'away', 90006=>'away', 90007=>'away', 90008=>'away', 90011=>'home', 90012=>'away'],
        'phuocle'  => [90001=>'home', 90002=>'home', 90003=>'home', 90004=>'home', 90005=>'home', 90006=>'home', 90007=>'home', 90008=>'home', 90009=>'home', 90010=>'home'],
        'ngocmai'  => [90001=>'home', 90002=>'away', 90003=>'away', 90004=>'home', 90005=>'away', 90006=>'away', 90007=>'home', 90008=>'away'],
        'tuankhoa' => [90001=>'away', 90002=>'away', 90003=>'away', 90004=>'home', 90005=>'home', 90006=>'home', 90007=>'home', 90008=>'away', 90009=>'away'],
        'kimchi'   => [90001=>'home', 90002=>'home', 90003=>'home', 90004=>'home', 90005=>'away', 90006=>'away', 90007=>'home', 90008=>'home'],
        'vietanh'  => [90001=>'home', 90002=>'away', 90003=>'away', 90004=>'home', 90005=>'home', 90006=>'away', 90007=>'home', 90008=>'away', 90010=>'away'],
        'mylinh'   => [90001=>'away', 90002=>'home', 90003=>'away', 90004=>'away', 90005=>'away', 90006=>'home', 90007=>'away', 90008=>'home'],
    ];

    // Thời gian tạo dự đoán (rải ngẫu nhiên trong ngày)
    $stmtPred = $pdo->prepare("INSERT IGNORE INTO predictions (user_id, match_id, predicted_team, points_awarded, prediction_status, created_at) VALUES (?,?,?,?,?,?)");

    foreach ($userPredictions as $username => $preds) {
        if (!isset($userIds[$username])) continue;
        $uid = $userIds[$username];

        foreach ($preds as $matchId => $teamChoice) {
            // Tìm match time để tạo thời gian dự đoán trước đó
            $matchInfo = null;
            foreach ($allMatches as $m) {
                if ($m['id'] == $matchId) { $matchInfo = $m; break; }
            }
            if (!$matchInfo) continue;

            $matchTime = strtotime($matchInfo['time']);
            // Dự đoán trước trận 1-24 giờ
            $predTime = date('Y-m-d H:i:s', $matchTime - rand(3600, 86400));

            // Tính điểm nếu trận đã xong
            $points = null;
            $status = 0;
            if ($matchInfo['status'] === 'FT' && $matchInfo['home_score'] !== null) {
                // Dùng hàm calculate_points
                $points = calculate_points($teamChoice, $matchInfo['home_score'], $matchInfo['away_score'], $matchInfo['handicap']);
                $status = 1;
            }

            $stmtPred->execute([$uid, $matchId, $teamChoice, $points, $status, $predTime]);
        }
    }

    $pdo->commit();

    // ==========================
    // 4. CẬP NHẬT BẢNG XẾP HẠNG (sau commit, hàm tự quản lý transaction)
    // ==========================
    for ($i = 10; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        update_rankings_for_date($date);
    }

    echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Seed Data - World Cup Predict</title>
    <style>
        body { font-family: Segoe UI, sans-serif; background: #0c1a12; color: #d4af37; padding: 30px; }
        .box { background: rgba(255,255,255,0.05); border: 1px solid rgba(212,175,55,0.2); border-radius: 12px; padding: 25px; max-width: 600px; margin: 0 auto; }
        h2 { color: #d4af37; margin-bottom: 20px; }
        .ok { color: #00cc77; font-weight: bold; }
        .link { display: inline-block; margin-top: 20px; padding: 12px 24px; background: linear-gradient(135deg,#d4af37,#aa8414); color: #000; border-radius: 8px; text-decoration: none; font-weight: bold; margin-right: 10px; }
        ul { line-height: 2.2; list-style: none; padding: 0; }
        li::before { content: "✅ "; }
        .warn { margin-top: 20px; padding: 12px; background: rgba(255,100,100,0.1); border: 1px solid #ff6b6b; border-radius: 8px; color: #ff9999; font-size: 14px; }
    </style>
</head>
<body>
<div class="box">
    <h2>🏆 Tạo Dữ Liệu Mẫu Thành Công!</h2>
    <ul>
        <li>' . count($players) . ' tài khoản người chơi (mật khẩu: <strong>123456</strong>)</li>
        <li>' . count($finishedMatches) . ' trận đấu đã kết thúc (có kết quả & điểm)</li>
        <li>' . count($upcomingMatches) . ' trận đấu sắp diễn ra</li>
        <li>Dự đoán với kèo chấp đã được tính điểm</li>
        <li>Bảng xếp hạng đã được cập nhật</li>
    </ul>
    <a class="link" href="index.php">🏠 Trang chủ</a>
    <a class="link" href="admin/dashboard.php">⚙️ Admin Panel</a>
    <div class="warn">
        ⚠️ <strong>Quan trọng:</strong> Hãy xóa file <code>seed_data.php</code> sau khi kiểm tra xong để đảm bảo bảo mật!
    </div>
</div>
</body>
</html>';

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo '<pre style="color:red; background:#fff; padding:20px;">Lỗi: ' . $e->getMessage() . "\n\n" . $e->getTraceAsString() . '</pre>';
}
