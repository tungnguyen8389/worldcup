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
$home_score = isset($_POST['home_score']) ? $_POST['home_score'] : '';
$away_score = isset($_POST['away_score']) ? $_POST['away_score'] : '';

// Kiểm tra tính hợp lệ của điểm số
if ($home_score === '' || $away_score === '') {
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ tỷ số!']);
    exit;
}

$home_score = (int)$home_score;
$away_score = (int)$away_score;

if ($home_score < 0 || $away_score < 0) {
    echo json_encode(['success' => false, 'message' => 'Tỷ số không thể là số âm!']);
    exit;
}

try {
    // Lấy thông tin trận đấu để kiểm tra trạng thái khóa
    $stmt = $pdo->prepare("SELECT match_time, status FROM matches WHERE id = ?");
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
    $sql = "INSERT INTO predictions (user_id, match_id, predicted_home_score, predicted_away_score, created_at)
            VALUES (:user_id, :match_id, :predicted_home_score, :predicted_away_score, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE 
                predicted_home_score = VALUES(predicted_home_score), 
                predicted_away_score = VALUES(predicted_away_score),
                created_at = CURRENT_TIMESTAMP";
    
    $stmt_save = $pdo->prepare($sql);
    $stmt_save->execute([
        'user_id' => $user_id,
        'match_id' => $match_id,
        'predicted_home_score' => $home_score,
        'predicted_away_score' => $away_score
    ]);

    echo json_encode([
        'success' => true, 
        'message' => 'Đã lưu dự đoán của bạn!',
        'home' => $home_score,
        'away' => $away_score
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
