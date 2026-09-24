-- 举报证据表迁移脚本
-- 在已执行 migration_add_reports.sql 的基础上执行本文件
-- 用于支持举报人在管理员处理前补充证据图片

USE `community_board';

CREATE TABLE IF NOT EXISTS `report_evidence` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` INT UNSIGNED NOT NULL COMMENT '所属举报ID',
    `image` VARCHAR(255) NOT NULL COMMENT '证据图片路径',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
    INDEX `idx_report_id` (`report_id`),
    CONSTRAINT `fk_evidence_report` FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='举报证据图片表';

-- 执行完成后，可以通过以下命令验证：
-- SHOW TABLES LIKE 'report_evidence';
-- DESCRIBE report_evidence;
