<?php
// Bắt đầu đệm đầu ra để thu giữ bất kỳ mã HTML/CSS rác nào xuất ra khi require file
ob_start();

// api/get_pdf_template.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pdf_templates.php';

// Xóa bỏ toàn bộ nội dung CSS/HTML thừa vừa được xuất ra trong quá trình require
ob_clean();

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thực hiện hành động này!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

try {
    ob_start();
    
    if ($type === 'match_predictions') {
        $match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
        
        if ($match_id === 0) {
            $sql_matches = "SELECT id, match_time FROM matches ORDER BY match_time DESC";
            $matches = $pdo->query($sql_matches)->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($matches)) {
                $match_id = $matches[0]['id'];
                foreach ($matches as $m) {
                    if (strtotime($m['match_time']) > time()) {
                        $match_id = $m['id'];
                    }
                }
            }
        }
        
        // Lấy thông tin trận đấu
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$match_id]);
        $selected_match = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$selected_match) {
            echo json_encode(['success' => false, 'message' => 'Trận đấu không tồn tại!']);
            exit;
        }
        
        // Lấy danh sách dự đoán
        $sql_group = "SELECT u.id as user_id, u.nickname, u.real_name, 
                             p.predicted_team, p.points_awarded, p.created_at
                      FROM users u
                      LEFT JOIN predictions p ON u.id = p.user_id AND p.match_id = :match_id
                      WHERE u.role = 'user'
                      ORDER BY u.nickname ASC";
        $stmt_group = $pdo->prepare($sql_group);
        $stmt_group->execute(['match_id' => $match_id]);
        $group_predictions = $stmt_group->fetchAll(PDO::FETCH_ASSOC);
        
        $reveal_real_names = (int)get_setting('reveal_real_names', 0);
        
        render_match_predictions_pdf($selected_match, $group_predictions, $user_id, $reveal_real_names);
        
    } elseif ($type === 'leaderboard') {
        // Lấy bảng xếp hạng tổng hợp (tính toán realtime)
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
        $leaderboard = $stmt_lead->fetchAll(PDO::FETCH_ASSOC);
        
        // Gán thứ hạng
        $ranked_leaderboard = [];
        $rank = 1;
        $prev_points = null;
        $prev_wins = null;
        $prev_pred_time = null;
        foreach ($leaderboard as $index => $row) {
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
        
        // Tính toán xu hướng tăng giảm hạng
        $prev_ranks = [];
        try {
            $stmt_prev_date = $pdo->query("SELECT MAX(ranking_date) FROM daily_rankings WHERE ranking_date < CURRENT_DATE()");
            $prev_date = $stmt_prev_date->fetchColumn();
            
            if ($prev_date) {
                $stmt_prev_ranks = $pdo->prepare("SELECT user_id, rank_position FROM daily_rankings WHERE ranking_date = ?");
                $stmt_prev_ranks->execute([$prev_date]);
                $prev_ranks = $stmt_prev_ranks->fetchAll(PDO::FETCH_KEY_PAIR);
            }
        } catch (PDOException $e) {
            // Bỏ qua
        }
        
        $reveal_real_names = (int)get_setting('reveal_real_names', 0);
        
        render_leaderboard_pdf($ranked_leaderboard, $user_id, $reveal_real_names, $prev_ranks);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Loại báo cáo không hợp lệ!']);
        exit;
    }
    
    $html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}
