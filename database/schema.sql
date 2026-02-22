-- ╔══════════════════════════════════════════════════════════╗
-- ║  Contract Tracker — MySQL 5.7+ / MariaDB 10.3+ Schema  ║
-- ║  Charset: utf8mb4_unicode_ci · Engine: InnoDB           ║
-- ╚══════════════════════════════════════════════════════════╝

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ═══ USERS ═════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login`         VARCHAR(64)  NOT NULL,
  `email`         VARCHAR(255) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name`     VARCHAR(255) NOT NULL DEFAULT '',
  `role`          ENUM('admin','manager','viewer') NOT NULL DEFAULT 'viewer',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_login` (`login`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══ CONTRACTS ═════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `contracts` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`          VARCHAR(100) NOT NULL,
  `subject`         TEXT         NOT NULL,
  `law_type`        ENUM('223','44') NOT NULL,
  `contractor_name` VARCHAR(500) NOT NULL,
  `contractor_inn`  VARCHAR(12)  DEFAULT NULL,
  `total_amount`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `nmck_amount`     DECIMAL(15,2) DEFAULT NULL COMMENT 'НМЦК для 44-ФЗ',
  `currency`        VARCHAR(3)   NOT NULL DEFAULT 'RUB',
  `status`          ENUM('draft','active','executed','terminated','cancelled') NOT NULL DEFAULT 'draft',
  `signed_at`       DATE         DEFAULT NULL,
  `expires_at`      DATE         DEFAULT NULL,
  `notes`           TEXT         DEFAULT NULL,
  `created_by`      INT UNSIGNED DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_law` (`law_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `fk_contract_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══ PROCUREMENTS (Закупки) ═══════════════════════════
CREATE TABLE IF NOT EXISTS `procurements` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`        VARCHAR(100) NOT NULL,
  `subject`       TEXT         NOT NULL,
  `law_type`      ENUM('223','44') NOT NULL,
  `status`        ENUM('draft','rfq','evaluation','awarded','cancelled') NOT NULL DEFAULT 'draft',
  `nmck_amount`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `deadline_at`   DATE         DEFAULT NULL,
  `notes`         TEXT         DEFAULT NULL,
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pr_law` (`law_type`),
  KEY `idx_pr_status` (`status`),
  KEY `idx_pr_deadline` (`deadline_at`),
  KEY `idx_pr_created_by` (`created_by`),
  CONSTRAINT `fk_procurement_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══ PROCUREMENT PROPOSALS (КП) ═══════════════════════
CREATE TABLE IF NOT EXISTS `procurement_proposals` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `procurement_id` INT UNSIGNED NOT NULL,
  `supplier_name`  VARCHAR(255) NOT NULL,
  `supplier_inn`   VARCHAR(12)  DEFAULT NULL,
  `amount`         DECIMAL(15,2) NOT NULL,
  `currency`       VARCHAR(3)   NOT NULL DEFAULT 'RUB',
  `submitted_at`   DATE         DEFAULT NULL,
  `comment`        TEXT         DEFAULT NULL,
  `is_winner`      TINYINT(1)   NOT NULL DEFAULT 0,
  `created_by`     INT UNSIGNED DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pp_procurement` (`procurement_id`),
  KEY `idx_pp_amount` (`amount`),
  KEY `idx_pp_winner` (`is_winner`),
  CONSTRAINT `fk_pp_procurement` FOREIGN KEY (`procurement_id`) REFERENCES `procurements`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pp_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══ DOCUMENTS ═════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `documents` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_id`    INT UNSIGNED NOT NULL,
  `original_name`  VARCHAR(500) NOT NULL,
  `safe_name`      VARCHAR(100) NOT NULL,
  `relative_path`  VARCHAR(500) NOT NULL,
  `mime_type`      VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
  `size_bytes`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `sha256`         CHAR(64)     NOT NULL,
  `doc_type`       ENUM('contract','supplement','act','invoice','other') NOT NULL DEFAULT 'other',
  `uploaded_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_contract` (`contract_id`),
  KEY `idx_doc_type` (`doc_type`),
  CONSTRAINT `fk_doc_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doc_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══ PAYMENTS ══════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `payments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_id`     INT UNSIGNED NOT NULL,
  `amount`          DECIMAL(15,2) NOT NULL,
  `status`          ENUM('planned','in_progress','paid','canceled') NOT NULL DEFAULT 'planned',
  `payment_date`    DATE         DEFAULT NULL,
  `purpose`         VARCHAR(500) DEFAULT NULL,
  `invoice_number`  VARCHAR(100) DEFAULT NULL,
  `created_by`      INT UNSIGNED DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pay_contract` (`contract_id`),
  KEY `idx_pay_status` (`status`),
  CONSTRAINT `fk_pay_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══ CONTRACT STAGES ════════════════════════════════
CREATE TABLE IF NOT EXISTS `contract_stages` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_id`   INT UNSIGNED NOT NULL,
  `title`         VARCHAR(255) NOT NULL,
  `status`        ENUM('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  `planned_date`  DATE         DEFAULT NULL,
  `actual_date`   DATE         DEFAULT NULL,
  `sort_order`    INT UNSIGNED NOT NULL DEFAULT 0,
  `description`   TEXT         DEFAULT NULL,
  `created_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stage_contract` (`contract_id`),
  KEY `idx_stage_status` (`status`),
  KEY `idx_stage_plan` (`planned_date`),
  CONSTRAINT `fk_stage_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stage_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══ AUDIT LOG ═════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED DEFAULT NULL,
  `action`       VARCHAR(100) NOT NULL,
  `entity_type`  VARCHAR(50)  DEFAULT NULL,
  `entity_id`    INT UNSIGNED DEFAULT NULL,
  `details`      JSON         DEFAULT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL DEFAULT '',
  `user_agent`   VARCHAR(500) DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;

-- ═══ SEED: admin / admin123 — СМЕНИТЬ ПАРОЛЬ! ═════════
-- Password hash: bcrypt('admin123')
INSERT INTO `users` (`login`, `email`, `password_hash`, `full_name`, `role`, `is_active`)
VALUES ('admin', 'admin@example.com',
  '$2y$12$kw60IV7gCqxklWS23c3Qr.OlCshVRqlWT/J0DA8pUYJ/l7ivY3UCq',
  'Администратор', 'admin', 1)
ON DUPLICATE KEY UPDATE `login`=`login`;
