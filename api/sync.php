<?php
// api/sync.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/FootballAPI.php';

// Chỉ cho phép chạy từ Command Line (Cronjob) hoặc phải đăng nhập dưới quyền Admin
if (php_sapi_name() !== 'cli') {
    require_admin();
}

// Thiết lập header JSON nếu chạy trên trình duyệt
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

$response = [
    'success' => false,
    'message' => '',
    'matches_synced' => 0,
    'predictions_scored' => 0
];

try {
    $apiKey = get_setting('api_key');
    $leagueId = get_setting('league_id', 1);
    $season = get_setting('season', 2026);

    if (empty($apiKey)) {
        throw new Exception("API Key chưa được cấu hình. Vui lòng nhập API Key tại trang quản trị.");
    }

    $api = new FootballAPI($apiKey);
    $fixtures = $api->fetchFixtures($leagueId, $season);

    if (empty($fixtures)) {
        $response['success'] = true;
        $response['message'] = 'Không có trận đấu nào được tìm thấy từ API.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->beginTransaction();

    $syncedCount = 0;
    $affectedDates = []; // Lưu các ngày thi đấu có trận kết thúc để tính lại xếp hạng

    foreach ($fixtures as $item) {
        $fixture = $item['fixture'];
        $teams = $item['teams'];
        $goals = $item['goals'];
        $league = $item['league'];

        $matchId = $fixture['id'];
        $homeTeam = $teams['home']['name'];
        $awayTeam = $teams['away']['name'];
        $homeLogo = $teams['home']['logo'];
        $awayLogo = $teams['away']['logo'];
        
        // Chuyển đổi thời gian từ UTC sang giờ local (đã set timezone Asia/Ho_Chi_Minh ở auth.php)
        $matchTime = date('Y-m-d H:i:s', strtotime($fixture['date']));
        $matchDate = date('Y-m-d', strtotime($fixture['date']));
        
        $statusShort = $fixture['status']['short']; // NS, FT, AET, PEN, PST etc.
        $homeScore = $goals['home'];
        $awayScore = $goals['away'];
        $roundName = $league['round'];

        // Lưu thông tin trận đấu (Hoặc cập nhật nếu đã tồn tại)
        $sql = "INSERT INTO matches (id, home_team, away_team, home_logo, away_logo, match_time, status, home_score, away_score, round)
                VALUES (:id, :home_team, :away_team, :home_logo, :away_logo, :match_time, :status, :home_score, :away_score, :round)
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status), 
                    home_score = VALUES(home_score), 
                    away_score = VALUES(away_score), 
                    match_time = VALUES(match_time),
                    round = VALUES(round)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $matchId,
            'home_team' => $homeTeam,
            'away_team' => $awayTeam,
            'home_logo' => $homeLogo,
            'away_logo' => $awayLogo,
            'match_time' => $matchTime,
            'status' => $statusShort,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'round' => $roundName
        ]);

        $syncedCount++;

        // Nếu trận đấu đã kết thúc (FT, AET, PEN), ghi nhận ngày để cập nhật bảng xếp hạng
        if (in_array($statusShort, ['FT', 'AET', 'PEN'])) {
            $affectedDates[$matchDate] = true;
        }
    }

    $response['matches_synced'] = $syncedCount;

    // 2. Tính điểm cho các dự đoán chưa được chấm điểm của các trận đấu đã kết thúc
    $sql_predictions = "SELECT p.*, m.home_score, m.away_score, m.status, m.match_time
                        FROM predictions p
                        INNER JOIN matches m ON p.match_id = m.id
                        WHERE p.prediction_status = 0 AND m.status IN ('FT', 'AET', 'PEN')";
    
    $stmt_preds = $pdo->prepare($sql_predictions);
    $stmt_preds->execute();
    $pending_predictions = $stmt_preds->fetchAll();

    $scoredCount = 0;
    foreach ($pending_predictions as $pred) {
        $predId = $pred['id'];
        $points = calculate_points(
            $pred['predicted_home_score'],
            $pred['predicted_away_score'],
            $pred['home_score'],
            $pred['away_score']
        );

        if ($points !== null) {
            $stmt_update = $pdo->prepare("UPDATE predictions SET points_awarded = ?, prediction_status = 1 WHERE id = ?");
            $stmt_update->execute([$points, $predId]);
            $scoredCount++;
        }
    }

    $response['predictions_scored'] = $scoredCount;

    $pdo->commit();

    // 3. Cập nhật bảng xếp hạng lịch sử cho các ngày bị ảnh hưởng (sắp xếp tăng dần theo ngày)
    if (!empty($affectedDates)) {
        $dates = array_keys($affectedDates);
        sort($dates);
        foreach ($dates as $date) {
            update_rankings_for_date($date);
        }
    }
    
    // Tự động cập nhật bảng xếp hạng ngày hiện tại sau mỗi lần chạy sync
    update_rankings_for_date(date('Y-m-d'));

    $response['success'] = true;
    $response['message'] = "Đồng bộ dữ liệu thành công! Đã xử lý $syncedCount trận và chấm điểm $scoredCount dự đoán.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

if (php_sapi_name() === 'cli') {
    echo $response['message'] . "\n";
} else {
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
