-- 举报证据表迁移脚本
-- 在执行过 migration_add_reports.sql 的基础上执行此 SQL，
-- 为举报功能增加“证据补充”能力所需的表结构。
--
-- 说明：举报撤回采用物理删除 reports 记录的方式实现（不残留旧状态，
-- 撤回后同一访客可对同一留言重新举报），因此本表通过外键 ON DELETE CASCADE
-- 在举报撤回（或留言删除）时自动清理证据记录。

USE `community_board`;

CREATE TABLE IF NOT EXISTS `report_evidences` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` INT UNSIGNED NOT NULL COMMENT '所属举报ID',
    `image` VARCHAR(255) NOT NULL COMMENT '证据图片相对路径',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '补充时间',
    INDEX `idx_report_id` (`report_id`),
    CONSTRAINT `fk_evidence_report` FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='举报证据表';

-- 执行完成后，可以通过以下命令验证：
-- SHOW TABLES LIKE 'report_evidences';
-- DESCRIBE report_evidences;
