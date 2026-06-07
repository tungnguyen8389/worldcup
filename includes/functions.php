<?php
// includes/functions.php
require_once __DIR__ . '/../config/database.php';

/**
 * Lấy giá trị cấu hình hệ thống từ DB
 */
function get_setting($key, $default = '') {
    global $pdo;
    if (!$pdo) return $default;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetchColumn();
        return $res !== false ? $res : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Lưu/Cập nhật cấu hình hệ thống
 */
function save_setting($key, $value) {
    global $pdo;
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        return $stmt->execute([$key, $value]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Kiểm tra xem một trận đấu đã bị khóa dự đoán chưa (trước giờ thi đấu 15 phút)
 */
function is_match_locked($match_time_str) {
    $match_time = strtotime($match_time_str);
    $current_time = time();
    // Khóa trước 15 phút = 15 * 60 = 900 giây
    return ($match_time - $current_time) <= 900;
}

/**
 * Tính toán điểm nhận được từ một dự đoán so với tỷ số thực tế
 */
function calculate_points($pred_home, $pred_away, $actual_home, $actual_away) {
    if ($actual_home === null || $actual_away === null) {
        return null;
    }
    
    $pred_home = (int)$pred_home;
    $pred_away = (int)$pred_away;
    $actual_home = (int)$actual_home;
    $actual_away = (int)$actual_away;
    
    $points_exact = (int)get_setting('point_exact_score', 3);
    $points_diff = (int)get_setting('point_goal_difference', 2);
    $points_outcome = (int)get_setting('point_correct_outcome', 1);
    
    // 1. Đúng tỷ số chính xác
    if ($pred_home === $actual_home && $pred_away === $actual_away) {
        return $points_exact;
    }
    
    // 2. Đúng kết quả & đúng hiệu số (chỉ dành cho các trận thắng/thua, không tính hòa vì hòa khác tỷ số là chỉ đúng kết quả)
    $pred_diff = $pred_home - $pred_away;
    $actual_diff = $actual_home - $actual_away;
    
    if ($pred_diff === $actual_diff && $pred_diff !== 0) {
        return $points_diff;
    }
    
    // 3. Đúng kết quả (Thắng / Thua / Hòa)
    if (
        ($pred_home > $pred_away && $actual_home > $actual_away) || // Đúng đội nhà thắng
        ($pred_home < $pred_away && $actual_home < $actual_away) || // Đúng đội khách thắng
        ($pred_home === $pred_away && $actual_home === $actual_away) // Đúng kết quả hòa
    ) {
        return $points_outcome;
    }
    
    // 4. Sai hoàn toàn
    return 0;
}

/**
 * Định dạng thời gian thi đấu hiển thị tiếng Việt
 */
function format_match_time($datetime_str) {
    $time = strtotime($datetime_str);
    $days = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
    $day_of_week = $days[date('w', $time)];
    return $day_of_week . ', ' . date('d/m/Y H:i', $time);
}

/**
 * Thống kê thứ hạng người chơi tại ngày hiện tại
 */
function update_rankings_for_date($date) {
    global $pdo;
    if (!$pdo) return;
    
    try {
        $points_exact = (int)get_setting('point_exact_score', 3);
        
        // Lấy danh sách điểm số lũy kế của user đến hết ngày $date cùng số trận trúng tuyệt đối và tổng thời gian dự đoán
        $sql = "SELECT u.id as user_id, 
                       COALESCE(SUM(p.points_awarded), 0) as total_points,
                       SUM(CASE WHEN p.points_awarded = :points_exact THEN 1 ELSE 0 END) as exact_count,
                       COALESCE(SUM(UNIX_TIMESTAMP(p.created_at)), 0) as total_pred_time
                FROM users u
                LEFT JOIN predictions p ON u.id = p.user_id AND p.points_awarded IS NOT NULL
                LEFT JOIN matches m ON p.match_id = m.id AND DATE(m.match_time) <= :date
                WHERE u.role = 'user'
                GROUP BY u.id
                ORDER BY total_points DESC, exact_count DESC, total_pred_time ASC, u.nickname ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['date' => $date, 'points_exact' => $points_exact]);
        $ranks = $stmt->fetchAll();
        
        // Lưu thứ hạng vào daily_rankings
        $rank_pos = 1;
        $prev_points = null;
        $prev_exact = null;
        $prev_pred_time = null;
        $actual_rank = 1;
        
        $pdo->beginTransaction();
        
        foreach ($ranks as $index => $row) {
            $user_id = $row['user_id'];
            $total_points = $row['total_points'];
            $exact_count = $row['exact_count'];
            $total_pred_time = $row['total_pred_time'];
            
            if ($prev_points !== null && (
                $total_points < $prev_points || 
                $exact_count < $prev_exact || 
                $total_pred_time > $prev_pred_time
            )) {
                $actual_rank = $index + 1;
            }
            $prev_points = $total_points;
            $prev_exact = $exact_count;
            $prev_pred_time = $total_pred_time;
            
            $stmt_insert = $pdo->prepare("INSERT INTO daily_rankings (user_id, ranking_date, total_points, rank_position) 
                                          VALUES (?, ?, ?, ?)
                                          ON DUPLICATE KEY UPDATE total_points = VALUES(total_points), rank_position = VALUES(rank_position)");
            $stmt_insert->execute([$user_id, $date, $total_points, $actual_rank]);
        }
        
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
