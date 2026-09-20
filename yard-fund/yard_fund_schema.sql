-- Yard Fund Database Schema
-- MySQL/MariaDB
-- Note: This is the schema only. Sample/seed data and real user records
-- have been removed for privacy before publishing.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `yard_fund`
--

-- --------------------------------------------------------

CREATE TABLE `claims_approved` (
  `claim_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `tuition_amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `proof_file` varchar(255) NOT NULL,
  `funding_status` enum('open','funded') DEFAULT 'open',
  `approved_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `claims_denied` (
  `claim_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `tuition_amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `proof_file` varchar(255) NOT NULL,
  `denial_reason` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL,
  `denied_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `claims_history` (
  `history_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `tuition_amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `proof_file` varchar(255) NOT NULL,
  `saved_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `claims_pending` (
  `claim_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `tuition_amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `proof_file` varchar(255) NOT NULL,
  `submitted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `student_updates` (
  `update_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `update_message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `amount_sent` decimal(10,2) NOT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `is_anonymous` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_type` enum('student','donor','admin') NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `major` varchar(100) DEFAULT NULL,
  `classification` varchar(50) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

ALTER TABLE `claims_approved`
  ADD PRIMARY KEY (`claim_id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

ALTER TABLE `claims_denied`
  ADD PRIMARY KEY (`claim_id`),
  ADD KEY `student_id` (`student_id`);

ALTER TABLE `claims_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `student_id` (`student_id`);

ALTER TABLE `claims_pending`
  ADD PRIMARY KEY (`claim_id`),
  ADD KEY `student_id` (`student_id`);

ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `student_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `claim_id` (`claim_id`),
  ADD KEY `student_id` (`student_id`);

ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `claim_id` (`claim_id`),
  ADD KEY `donor_id` (`donor_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

ALTER TABLE `claims_approved`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `claims_denied`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `claims_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `claims_pending`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `student_updates`
  MODIFY `update_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

ALTER TABLE `claims_approved`
  ADD CONSTRAINT `claims_approved_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `claims_denied`
  ADD CONSTRAINT `claims_denied_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `claims_history`
  ADD CONSTRAINT `claims_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `claims_pending`
  ADD CONSTRAINT `claims_pending_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `student_updates`
  ADD CONSTRAINT `student_updates_ibfk_1` FOREIGN KEY (`claim_id`) REFERENCES `claims_approved` (`claim_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_updates_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`claim_id`) REFERENCES `claims_approved` (`claim_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`donor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

COMMIT;
