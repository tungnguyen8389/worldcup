-- World Cup Prediction Database Schema
CREATE DATABASE IF NOT EXISTS `worldcup` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `worldcup`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nickname` VARCHAR(50) NOT NULL,
    `real_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'user') DEFAULT 'user',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Matches Table (Sử dụng API Match ID làm PK)
CREATE TABLE IF NOT EXISTS `matches` (
    `id` INT PRIMARY KEY,
    `home_team` VARCHAR(100) NOT NULL,
    `away_team` VARCHAR(100) NOT NULL,
    `home_logo` VARCHAR(255) DEFAULT NULL,
    `away_logo` VARCHAR(255) DEFAULT NULL,
    `match_time` DATETIME NOT NULL,
    `status` VARCHAR(50) DEFAULT 'NS', -- NS: Not Started, FT: Full Time, etc.
    `home_score` INT DEFAULT NULL,
    `away_score` INT DEFAULT NULL,
    `round` VARCHAR(50) DEFAULT NULL, -- Vòng bảng, Vòng 16, Tứ kết...
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Predictions Table
CREATE TABLE IF NOT EXISTS `predictions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `match_id` INT NOT NULL,
    `predicted_home_score` INT NOT NULL,
    `predicted_away_score` INT NOT NULL,
    `points_awarded` INT DEFAULT NULL,
    `prediction_status` TINYINT DEFAULT 0, -- 0: Chưa tính, 1: Đã tính điểm
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `user_match` (`user_id`, `match_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`match_id`) REFERENCES `matches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. System Settings Table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Daily Rankings Table (Lưu lịch sử xếp hạng hàng ngày để vẽ biểu đồ)
CREATE TABLE IF NOT EXISTS `daily_rankings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `ranking_date` DATE NOT NULL,
    `total_points` INT NOT NULL DEFAULT 0,
    `rank_position` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `user_date` (`user_id`, `ranking_date`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES 
('reveal_real_names', '0'),
('point_exact_score', '3'),
('point_goal_difference', '2'),
('point_correct_outcome', '1'),
('api_key', ''),
('league_id', '1'), -- World Cup League ID in API-Football
('season', '2026')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
