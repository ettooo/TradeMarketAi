-- ============================================================
-- multi_tenancy_migration.sql
-- Migrazione incrementale per abilitare il multi-tenancy
-- su un database TradeMarketAi già esistente.
--
-- Applicare UNA SOLA VOLTA sull'istanza di produzione/staging.
-- Sicuro da rieseguire: usa IF NOT EXISTS / IGNORE dove possibile.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ─── 1. TABELLA TENANTS ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS `tenants` (
  `id`         int(11) NOT NULL AUTO_INCREMENT,
  `slug`       varchar(63) NOT NULL COMMENT 'Identificatore URL-safe (es. sottodominio)',
  `name`       varchar(100) NOT NULL,
  `plan`       enum('basic','professional','enterprise') NOT NULL DEFAULT 'basic',
  `is_active`  tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tenant predefinito (contiene tutti gli utenti esistenti)
INSERT IGNORE INTO `tenants` (`id`, `slug`, `name`, `plan`, `is_active`)
VALUES (1, 'default', 'Default Organization', 'enterprise', 1);

-- ─── 2. COLONNA tenant_id NELLA TABELLA users ────────────────

-- Aggiunge tenant_id solo se non esiste già.
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'tenant_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `tenant_id` int(11) NOT NULL DEFAULT 1 AFTER `id`',
    'SELECT ''tenant_id already exists'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Assegna tutti gli utenti esistenti al tenant predefinito (id=1).
UPDATE `users` SET `tenant_id` = 1 WHERE `tenant_id` = 0 OR `tenant_id` IS NULL;

-- ─── 3. AGGIORNA VINCOLI DI UNICITÀ (per-tenant) ─────────────

-- Rimuovi i vecchi indici globali (ignorando errori se non esistono).
ALTER TABLE `users`
  DROP INDEX IF EXISTS `username`,
  DROP INDEX IF EXISTS `email`;

-- Aggiunge nuovi indici unici per tenant (ignora se esistono già).
ALTER TABLE `users`
  ADD UNIQUE KEY IF NOT EXISTS `unique_username_per_tenant` (`tenant_id`, `username`),
  ADD UNIQUE KEY IF NOT EXISTS `unique_email_per_tenant`    (`tenant_id`, `email`),
  ADD KEY        IF NOT EXISTS `idx_users_tenant_id`        (`tenant_id`);

-- ─── 4. FOREIGN KEY users → tenants ──────────────────────────

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA        = DATABASE()
      AND TABLE_NAME          = 'users'
      AND CONSTRAINT_NAME     = 'fk_users_tenant'
);

SET @sql2 = IF(@fk_exists = 0,
    'ALTER TABLE `users` ADD CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)',
    'SELECT ''fk_users_tenant already exists'''
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- ─── 5. RUOLO super_admin ─────────────────────────────────────

INSERT IGNORE INTO `roles` (`id`, `name`, `description`)
VALUES (4, 'super_admin', 'Super Amministratore della piattaforma (cross-tenant)');

-- ─── 6. PERMESSO manage_tenants ──────────────────────────────

INSERT IGNORE INTO `permissions` (`name`, `description`)
VALUES ('manage_tenants', 'Creazione e gestione dei tenant della piattaforma (super_admin)');

-- ─── 7. ASSEGNA TUTTI I PERMESSI AL RUOLO super_admin ────────

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, `id` FROM `permissions`;

-- ─── 8. AGGIORNA LE VISTE SQL (aggiunge tenant_id) ───────────

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER
VIEW `view_active_sessions` AS
SELECT
    `u`.`tenant_id`   AS `tenant_id`,
    `u`.`username`    AS `username`,
    `rt`.`ip_address` AS `ip_address`,
    `rt`.`user_agent` AS `user_agent`,
    `rt`.`expires_at` AS `expires_at`,
    `rt`.`created_at` AS `login_time`
FROM `refresh_tokens` `rt`
JOIN `users` `u` ON `rt`.`user_id` = `u`.`id`
WHERE `rt`.`revoked` = 0
  AND `rt`.`expires_at` > current_timestamp();

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER
VIEW `view_user_access_control` AS
SELECT
    `u`.`tenant_id`       AS `tenant_id`,
    `u`.`id`              AS `user_id`,
    `u`.`username`        AS `username`,
    `u`.`email`           AS `email`,
    `r`.`name`            AS `role_name`,
    `p`.`name`            AS `permission_name`,
    `p`.`description`     AS `permission_info`
FROM `users` `u`
JOIN `roles`            `r`  ON `u`.`role_id`       = `r`.`id`
JOIN `role_permissions` `rp` ON `r`.`id`             = `rp`.`role_id`
JOIN `permissions`      `p`  ON `rp`.`permission_id` = `p`.`id`
WHERE `u`.`is_active` = 1;

COMMIT;
