-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Mar 12, 2026 alle 23:11
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `TradeMarketAi`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `condition_type` enum('above','below') NOT NULL,
  `threshold` decimal(15,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `market_data`
--

CREATE TABLE `market_data` (
  `id` int(11) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `change_pct` decimal(5,2) DEFAULT NULL,
  `volume` bigint(20) DEFAULT NULL,
  `market_cap` bigint(20) DEFAULT NULL,
  `fetched_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `market_data`
--

INSERT INTO `market_data` (`id`, `symbol`, `name`, `price`, `change_pct`, `volume`, `market_cap`, `fetched_at`) VALUES
(1, 'AAPL', 'Apple Inc.', 227.50, 1.23, 54000000, 3400000000000, '2026-03-12 22:09:34'),
(2, 'MSFT', 'Microsoft Corp.', 415.20, 0.87, 22000000, 3100000000000, '2026-03-12 22:09:34'),
(3, 'GOOGL', 'Alphabet Inc.', 175.80, -0.45, 18000000, 2200000000000, '2026-03-12 22:09:34'),
(4, 'AMZN', 'Amazon.com Inc.', 205.60, 2.10, 31000000, 2100000000000, '2026-03-12 22:09:34'),
(5, 'TSLA', 'Tesla Inc.', 245.30, -1.80, 89000000, 780000000000, '2026-03-12 22:09:34'),
(6, 'NVDA', 'NVIDIA Corp.', 875.40, 3.50, 42000000, 2150000000000, '2026-03-12 22:09:34'),
(7, 'META', 'Meta Platforms Inc.', 525.10, 1.15, 16000000, 1350000000000, '2026-03-12 22:09:34'),
(8, 'BRK.B', 'Berkshire Hathaway', 390.20, 0.30, 4000000, 855000000000, '2026-03-12 22:09:34'),
(9, 'JPM', 'JPMorgan Chase', 215.80, 0.65, 11000000, 620000000000, '2026-03-12 22:09:34'),
(10, 'V', 'Visa Inc.', 280.40, 0.42, 7000000, 575000000000, '2026-03-12 22:09:34');

-- --------------------------------------------------------

--
-- Struttura della tabella `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'view_dashboard', 'Accesso alla dashboard', '2026-02-26 18:28:34'),
(2, 'view_profile', 'Visualizzare il proprio profilo', '2026-02-26 18:28:34'),
(3, 'edit_profile', 'Modificare il proprio profilo', '2026-02-26 18:28:34'),
(4, 'view_free_content', 'Accesso ai contenuti gratuiti', '2026-02-26 18:28:34'),
(5, 'view_premium_content', 'Accesso ai contenuti premium', '2026-02-26 18:28:34'),
(6, 'download_files', 'Scaricare file e risorse', '2026-02-26 18:28:34'),
(7, 'manage_users', 'Gestione degli utenti (admin)', '2026-02-26 18:28:34'),
(8, 'manage_roles', 'Gestione dei ruoli (admin)', '2026-02-26 18:28:34'),
(9, 'view_reports', 'Visualizzare report e statistiche', '2026-02-26 18:28:34'),
(10, 'manage_content', 'Creare/modificare contenuti (admin)', '2026-02-26 18:28:34'),
(11, 'manage_permissions', 'CRUD sui permessi degli utenti via API REST', '2026-02-26 19:36:09'),
(12, 'view_market_data', 'Visualizzare dati di mercato base', '2026-03-12 22:09:12'),
(13, 'view_market_advanced', 'Indicatori avanzati e storico esteso', '2026-03-12 22:09:12'),
(14, 'view_ai_analysis', 'Analisi predittiva AI (solo Premium)', '2026-03-12 22:09:12'),
(15, 'run_simulation', 'Simulazioni Monte Carlo e backtesting', '2026-03-12 22:09:12'),
(16, 'set_basic_alerts', 'Alert su soglie di prezzo (Free)', '2026-03-12 22:09:12'),
(17, 'set_advanced_alerts', 'Alert avanzati e segnali AI (Premium)', '2026-03-12 22:09:12'),
(18, 'manage_portfolio', 'Gestione portafoglio virtuale', '2026-03-12 22:09:12'),
(19, 'manage_multi_portfolio', 'Portafogli multipli (Premium)', '2026-03-12 22:09:12');

-- --------------------------------------------------------

--
-- Struttura della tabella `portfolios`
--

CREATE TABLE `portfolios` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT 'Portafoglio principale',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `portfolio_items`
--

CREATE TABLE `portfolio_items` (
  `id` int(11) NOT NULL,
  `portfolio_id` int(11) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `quantity` int(11) NOT NULL,
  `purchase_price` decimal(15,2) NOT NULL,
  `purchased_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `refresh_tokens`
--

CREATE TABLE `refresh_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked` tinyint(1) DEFAULT 0,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `refresh_tokens`
--

INSERT INTO `refresh_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `revoked`, `user_agent`, `ip_address`, `created_at`) VALUES
(1, 3, '85c942672158ec9e40e8f4c7a35fdf3d9a4f2b5232e6e9c6ee2886619876395e', '2026-03-05 20:51:53', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-26 19:51:53');

-- --------------------------------------------------------

--
-- Struttura della tabella `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'free', 'Utente con accesso base gratuito', '2026-02-26 18:28:34'),
(2, 'premium', 'Utente con accesso completo premium', '2026-02-26 18:28:34'),
(3, 'admin', 'Amministratore del sistema', '2026-02-26 18:28:34');

-- --------------------------------------------------------

--
-- Struttura della tabella `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 12),
(1, 16),
(1, 18),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 12),
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(2, 18),
(2, 19),
(3, 1),
(3, 2),
(3, 3),
(3, 4),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(3, 9),
(3, 10),
(3, 11),
(3, 12),
(3, 13),
(3, 14),
(3, 15),
(3, 16),
(3, 17),
(3, 18),
(3, 19);

-- --------------------------------------------------------

--
-- Struttura della tabella `stocks`
--

CREATE TABLE `stocks` (
  `id` int(11) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `change_pct` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('transactions_enabled', '1', current_timestamp()),
('cursor_tracking_enabled', '1', current_timestamp());

-- --------------------------------------------------------

--
-- Struttura della tabella `subscription_transactions`
--

CREATE TABLE `subscription_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `from_role_id` int(11) NOT NULL,
  `to_role_id` int(11) NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'completed',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 1, '2026-02-26 18:28:34', '2026-02-26 18:28:34'),
(2, 'gatto@gmail.com', 'gatto@gmail.com', '$2y$12$7JkKXjZcG1V6CnZAZ/g3ce/vBSpKcKhmzY5IpWBX/dPWC9wJ.s.o6', 1, 1, '2026-02-26 18:50:27', '2026-02-26 18:50:27'),
(3, 'banano@gmail.com', 'banano@gmail.com', '$2y$12$u9qvhWVM2z3bKZfIjUhyh.HPFM7VmkOlxpYg/wpWeKP.3D88HJyHy', 1, 1, '2026-02-26 18:52:43', '2026-02-26 18:52:43'),
(4, 'criceto', 'criceto@criceto.com', '$2y$12$b3Pl7rfPhWq7X9SDghIzdOgIhLDpiMl41Bd4IV4oxAFOj78MwwYmO', 1, 1, '2026-03-12 21:54:45', '2026-03-12 21:54:45');

-- --------------------------------------------------------

--
-- Struttura della tabella `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `user_portfolios`
--

CREATE TABLE `user_portfolios` (
  `user_id` int(11) DEFAULT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `purchase_price` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `view_active_sessions`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `view_active_sessions` (
`username` varchar(50)
,`ip_address` varchar(45)
,`user_agent` varchar(255)
,`expires_at` datetime
,`login_time` timestamp
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `view_portfolio_valuation`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `view_portfolio_valuation` (
`username` varchar(50)
,`email` varchar(150)
,`total_wealth` decimal(47,2)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `view_premium_market_analysis`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `view_premium_market_analysis` (
`username` varchar(50)
,`symbol` varchar(10)
,`price` decimal(15,2)
,`ai_insight` varchar(28)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `view_user_access_control`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `view_user_access_control` (
`user_id` int(11)
,`username` varchar(50)
,`email` varchar(150)
,`role_name` varchar(50)
,`permission_name` varchar(100)
,`permission_info` varchar(255)
);

-- --------------------------------------------------------

--
-- Struttura per vista `view_active_sessions`
--
DROP TABLE IF EXISTS `view_active_sessions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_active_sessions`  AS SELECT `u`.`username` AS `username`, `rt`.`ip_address` AS `ip_address`, `rt`.`user_agent` AS `user_agent`, `rt`.`expires_at` AS `expires_at`, `rt`.`created_at` AS `login_time` FROM (`refresh_tokens` `rt` join `users` `u` on(`rt`.`user_id` = `u`.`id`)) WHERE `rt`.`revoked` = 0 AND `rt`.`expires_at` > current_timestamp() ;

-- --------------------------------------------------------

--
-- Struttura per vista `view_portfolio_valuation`
--
DROP TABLE IF EXISTS `view_portfolio_valuation`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_portfolio_valuation`  AS SELECT `u`.`username` AS `username`, `u`.`email` AS `email`, sum(`up`.`quantity` * `s`.`price`) AS `total_wealth` FROM ((`users` `u` join `user_portfolios` `up` on(`u`.`id` = `up`.`user_id`)) join `stocks` `s` on(`up`.`symbol` = `s`.`symbol`)) GROUP BY `u`.`id` ;

-- --------------------------------------------------------

--
-- Struttura per vista `view_premium_market_analysis`
--
DROP TABLE IF EXISTS `view_premium_market_analysis`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_premium_market_analysis`  AS SELECT `u`.`username` AS `username`, `s`.`symbol` AS `symbol`, `s`.`price` AS `price`, 'Analisi Predittiva Riservata' AS `ai_insight` FROM (`users` `u` join `stocks` `s`) WHERE `u`.`role_id` in (2,3) ;

-- --------------------------------------------------------

--
-- Struttura per vista `view_user_access_control`
--
DROP TABLE IF EXISTS `view_user_access_control`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_user_access_control`  AS SELECT `u`.`id` AS `user_id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `r`.`name` AS `role_name`, `p`.`name` AS `permission_name`, `p`.`description` AS `permission_info` FROM (((`users` `u` join `roles` `r` on(`u`.`role_id` = `r`.`id`)) join `role_permissions` `rp` on(`r`.`id` = `rp`.`role_id`)) join `permissions` `p` on(`rp`.`permission_id` = `p`.`id`)) WHERE `u`.`is_active` = 1 ;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indici per le tabelle `market_data`
--
ALTER TABLE `market_data`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indici per le tabelle `portfolios`
--
ALTER TABLE `portfolios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indici per le tabelle `portfolio_items`
--
ALTER TABLE `portfolio_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `portfolio_id` (`portfolio_id`);

--
-- Indici per le tabelle `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_token_hash` (`token_hash`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indici per le tabelle `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indici per le tabelle `subscription_transactions`
--
ALTER TABLE `subscription_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subscription_transactions_user_id` (`user_id`),
  ADD KEY `idx_subscription_transactions_created_at` (`created_at`),
  ADD KEY `idx_subscription_transactions_from_role` (`from_role_id`),
  ADD KEY `idx_subscription_transactions_to_role` (`to_role_id`);

--
-- Indici per le tabelle `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indici per le tabelle `stocks`
--
ALTER TABLE `stocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `symbol` (`symbol`);

--
-- Indici per le tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Indici per le tabelle `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indici per le tabelle `user_portfolios`
--
ALTER TABLE `user_portfolios`
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `market_data`
--
ALTER TABLE `market_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT per la tabella `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT per la tabella `portfolios`
--
ALTER TABLE `portfolios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `portfolio_items`
--
ALTER TABLE `portfolio_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT per la tabella `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `subscription_transactions`
--
ALTER TABLE `subscription_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `stocks`
--
ALTER TABLE `stocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `portfolios`
--
ALTER TABLE `portfolios`
  ADD CONSTRAINT `portfolios_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `portfolio_items`
--
ALTER TABLE `portfolio_items`
  ADD CONSTRAINT `portfolio_items_ibfk_1` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `refresh_tokens`
--
ALTER TABLE `refresh_tokens`
  ADD CONSTRAINT `refresh_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `subscription_transactions`
--
ALTER TABLE `subscription_transactions`
  ADD CONSTRAINT `fk_subscription_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subscription_transactions_from_role` FOREIGN KEY (`from_role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_subscription_transactions_to_role` FOREIGN KEY (`to_role_id`) REFERENCES `roles` (`id`);

--
-- Limiti per la tabella `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Limiti per la tabella `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `user_portfolios`
--
ALTER TABLE `user_portfolios`
  ADD CONSTRAINT `user_portfolios_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Trigger per bloccare inserimenti transazioni quando disattivate
--
DROP TRIGGER IF EXISTS `trg_subscription_transactions_guard`;

DELIMITER $$
CREATE TRIGGER `trg_subscription_transactions_guard`
BEFORE INSERT ON `subscription_transactions`
FOR EACH ROW
BEGIN
  DECLARE v_enabled varchar(255);

  SELECT setting_value
    INTO v_enabled
    FROM system_settings
   WHERE setting_key = 'transactions_enabled'
   LIMIT 1;

  IF COALESCE(v_enabled, '0') <> '1' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'TRANSAZIONI_DISATTIVATE_DB';
  END IF;
END$$
DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
