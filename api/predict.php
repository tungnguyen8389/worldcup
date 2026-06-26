<?php
// api/predict.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thực hiện dự đoán!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức yêu cầu không hợp lệ!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$match_id = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
$predicted_team = isset($_POST['predicted_team']) ? trim($_POST['predicted_team']) : '';

// Kiểm tra tính hợp lệ của lựa chọn đội
if ($predicted_team !== 'home' && $predicted_team !== 'away') {
    echo json_encode(['success' => false, 'message' => 'Lựa chọn đội dự đoán không hợp lệ!']);
    exit;
}

try {
    // Lấy thông tin trận đấu để kiểm tra trạng thái khóa
    $stmt = $pdo->prepare("SELECT match_time, status, home_team, away_team FROM matches WHERE id = ?");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch();

    if (!$match) {
        echo json_encode(['success' => false, 'message' => 'Trận đấu không tồn tại!']);
        exit;
    }

    // 1. Kiểm tra trạng thái trận đấu
    if (in_array($match['status'], ['FT', 'AET', 'PEN'])) {
        echo json_encode(['success' => false, 'message' => 'Trận đấu này đã kết thúc, không thể dự đoán!']);
        exit;
    }

    // 2. Kiểm tra giờ khóa (trước giờ đá 15 phút)
    if (is_match_locked($match['match_time'])) {
        echo json_encode(['success' => false, 'message' => 'Đã quá hạn chót dự đoán! Trận đấu đã bị khóa.']);
        exit;
    }

    // 3. Tiến hành lưu dự đoán (Tạo mới hoặc Cập nhật)
    $sql = "INSERT INTO predictions (user_id, match_id, predicted_team, created_at)
            VALUES (:user_id, :match_id, :predicted_team, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE 
                predicted_team = VALUES(predicted_team), 
                created_at = CURRENT_TIMESTAMP";
    
    $stmt_save = $pdo->prepare($sql);
    $stmt_save->execute([
        'user_id' => $user_id,
        'match_id' => $match_id,
        'predicted_team' => $predicted_team
    ]);

    $team_name = ($predicted_team === 'home') ? $match['home_team'] : $match['away_team'];

    echo json_encode([
        'success' => true, 
        'message' => 'Đã lưu lựa chọn đội ' . $team_name . ' của bạn!',
        'predicted_team' => $predicted_team,
        'team_name' => $team_name
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
