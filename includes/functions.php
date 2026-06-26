<?php
// includes/functions.php
require_once __DIR__ . '/../config/database.php';

// Tự động kiểm tra và nâng cấp cấu trúc bảng predictions cho luật chơi mới
if ($pdo) {
    try {
        // Kiểm tra xem cột predicted_team đã tồn tại chưa
        $check = $pdo->query("SHOW COLUMNS FROM `predictions` LIKE 'predicted_team'")->fetch();
        if (!$check) {
            // Thêm cột predicted_team
            $pdo->exec("ALTER TABLE `predictions` ADD COLUMN `predicted_team` VARCHAR(10) DEFAULT NULL AFTER `match_id`");
            // Cho phép các cột điểm số cũ nullable
            $pdo->exec("ALTER TABLE `predictions` MODIFY `predicted_home_score` INT NULL");
            $pdo->exec("ALTER TABLE `predictions` MODIFY `predicted_away_score` INT NULL");
        }
        
        // Kiểm tra xem cột handicap đã tồn tại trong bảng matches chưa
        $check_handicap = $pdo->query("SHOW COLUMNS FROM `matches` LIKE 'handicap'")->fetch();
        if (!$check_handicap) {
            $pdo->exec("ALTER TABLE `matches` ADD COLUMN `handicap` FLOAT DEFAULT 0.0 AFTER `away_logo`");
        }
    } catch (PDOException $e) {
        // Bỏ qua lỗi
    }
}

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
 * Tính toán điểm nhận được từ một dự đoán chọn đội so với tỷ số thực tế
 * Luật mới: Chọn đội thắng được +1 điểm (1 trận thắng), chọn đội thua bị -1 điểm (bị trừ 1 điểm), hòa được 0 điểm.
 * Quy ước Handicap mới:
 * - Handicap dương (> 0): Đội nhà chấp Đội khách (trừ vào điểm Đội nhà)
 * - Handicap âm (< 0): Đội khách chấp Đội nhà (cộng vào điểm Đội nhà / trừ vào Đội khách)
 */
function calculate_points($predicted_team, $actual_home, $actual_away, $handicap = 0.0) {
    if ($actual_home === null || $actual_away === null || empty($predicted_team)) {
        return null;
    }
    
    $actual_home = (float)$actual_home;
    $actual_away = (float)$actual_away;
    $handicap = (float)$handicap;
    
    // Điểm số điều chỉnh cho Đội Nhà sau khi áp dụng kèo chấp
    // Nếu Đội Nhà chấp (handicap > 0), ta trừ đi handicap từ tỷ số Đội Nhà
    $adjusted_home = $actual_home - $handicap;
    
    if ($predicted_team === 'home') {
        if ($adjusted_home > $actual_away) {
            return 1; // Chọn đội nhà thắng kèo: +1 điểm
        } elseif ($adjusted_home < $actual_away) {
            return -1; // Chọn đội nhà thua kèo: -1 điểm
        } else {
            return 0; // Hòa kèo: 0 điểm
        }
    } elseif ($predicted_team === 'away') {
        if ($adjusted_home < $actual_away) {
            return 1; // Chọn đội khách thắng kèo: +1 điểm
        } elseif ($adjusted_home > $actual_away) {
            return -1; // Chọn đội khách thua kèo: -1 điểm
        } else {
            return 0; // Hòa kèo: 0 điểm
        }
    }
    
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
        // Lấy danh sách điểm số lũy kế của user đến hết ngày $date cùng số trận thắng và tổng thời gian dự đoán
        $sql = "SELECT u.id as user_id, 
                       COALESCE(SUM(p.points_awarded), 0) as total_points,
                       SUM(CASE WHEN p.points_awarded = 1 THEN 1 ELSE 0 END) as win_count,
                       COALESCE(SUM(UNIX_TIMESTAMP(p.created_at)), 0) as total_pred_time
                FROM users u
                LEFT JOIN predictions p ON u.id = p.user_id AND p.points_awarded IS NOT NULL
                LEFT JOIN matches m ON p.match_id = m.id AND DATE(m.match_time) <= :date
                WHERE u.role = 'user'
                GROUP BY u.id
                ORDER BY total_points DESC, win_count DESC, total_pred_time ASC, u.nickname ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['date' => $date]);
        $ranks = $stmt->fetchAll();
        
        // Lưu thứ hạng vào daily_rankings
        $prev_points = null;
        $prev_wins = null;
        $prev_pred_time = null;
        $actual_rank = 1;
        
        $pdo->beginTransaction();
        
        foreach ($ranks as $index => $row) {
            $user_id = $row['user_id'];
            $total_points = $row['total_points'];
            $win_count = $row['win_count'];
            $total_pred_time = $row['total_pred_time'];
            
            if ($prev_points !== null && (
                $total_points < $prev_points || 
                $win_count < $prev_wins || 
                $total_pred_time > $prev_pred_time
            )) {
                $actual_rank = $index + 1;
            }
            $prev_points = $total_points;
            $prev_wins = $win_count;
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

/**
 * Chấm điểm tất cả dự đoán cho trận đấu và xử thua (-1đ) cho những thành viên không dự đoán
 */
function score_match_predictions($match_id, $home_score, $away_score, $handicap = 0.0) {
    global $pdo;
    if (!$pdo) return 0;
    
    $scored_count = 0;
    
    // 1. Chấm điểm cho các dự đoán đã có của trận đấu này
    $stmt_preds = $pdo->prepare("SELECT * FROM predictions WHERE match_id = ?");
    $stmt_preds->execute([$match_id]);
    $preds = $stmt_preds->fetchAll();
    
    foreach ($preds as $pred) {
        $points = calculate_points(
            $pred['predicted_team'],
            $home_score,
            $away_score,
            $handicap
        );
        
        // Nếu dự đoán rỗng thì phạt -1đ
        if (empty($pred['predicted_team'])) {
            $points = -1;
        }
        
        $stmt_up_pred = $pdo->prepare("UPDATE predictions SET points_awarded = ?, prediction_status = 1 WHERE id = ?");
        $stmt_up_pred->execute([$points, $pred['id']]);
        $scored_count++;
    }
    
    // 2. Tìm tất cả các thành viên (role = 'user') chưa có dự đoán cho trận đấu này
    $stmt_users = $pdo->prepare("
        SELECT id FROM users 
        WHERE role = 'user' 
          AND id NOT IN (SELECT user_id FROM predictions WHERE match_id = ?)
    ");
    $stmt_users->execute([$match_id]);
    $no_pred_users = $stmt_users->fetchAll(PDO::FETCH_COLUMN);
    
    // Thêm bản ghi dự đoán rỗng với điểm phạt -1 cho họ
    $stmt_insert = $pdo->prepare("
        INSERT INTO predictions (user_id, match_id, predicted_team, points_awarded, prediction_status) 
        VALUES (?, ?, NULL, -1, 1)
        ON DUPLICATE KEY UPDATE points_awarded = -1, prediction_status = 1
    ");
    foreach ($no_pred_users as $user_id) {
        $stmt_insert->execute([$user_id, $match_id]);
        $scored_count++;
    }
    
    return $scored_count;
}

