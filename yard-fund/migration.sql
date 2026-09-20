-- ============================================================
-- Yard Fund — Full App Migration
-- Run in phpMyAdmin > yard_fund_demo > SQL tab
-- WARNING: Drops and rebuilds all tables
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS student_updates;
DROP TABLE IF EXISTS claims_pending;
DROP TABLE IF EXISTS claims_approved;
DROP TABLE IF EXISTS claims_denied;
DROP TABLE IF EXISTS claims_requests;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ── Users ─────────────────────────────────────────────
CREATE TABLE users (
    user_id        INT AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(150) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    user_type      ENUM('student','donor','admin') NOT NULL,
    profile_photo  VARCHAR(255) DEFAULT NULL,
    major          VARCHAR(100) DEFAULT NULL,
    classification VARCHAR(50)  DEFAULT NULL,
    gpa            DECIMAL(3,2) DEFAULT NULL,
    bio            TEXT         DEFAULT NULL,
    is_active      TINYINT(1)   DEFAULT 1,
    created_at     DATETIME     DEFAULT NOW()
);

-- Fixed admin account — set a real password via a secure script (not committed) after import
-- Do not hardcode credentials in a script kept in version control
INSERT INTO users (full_name, email, password_hash, user_type)
VALUES ('Site Admin', 'admin@yardfund.com', 'PLACEHOLDER', 'admin');

-- ── Password Resets ───────────────────────────────────
CREATE TABLE password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    token      VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used       TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ── Claims: Pending ───────────────────────────────────
CREATE TABLE claims_pending (
    claim_id       INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT NOT NULL,
    tuition_amount DECIMAL(10,2) NOT NULL,
    description    TEXT NOT NULL,
    proof_file     VARCHAR(255) NOT NULL,
    submitted_at   DATETIME DEFAULT NOW(),
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ── Claims: Approved ──────────────────────────────────
CREATE TABLE claims_approved (
    claim_id       INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT NOT NULL UNIQUE,
    tuition_amount DECIMAL(10,2) NOT NULL,
    description    TEXT NOT NULL,
    proof_file     VARCHAR(255) NOT NULL,
    funding_status ENUM('open','funded') DEFAULT 'open',
    approved_at    DATETIME DEFAULT NOW(),
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ── Claims: Denied ────────────────────────────────────
CREATE TABLE claims_denied (
    claim_id       INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT NOT NULL,
    tuition_amount DECIMAL(10,2) NOT NULL,
    description    TEXT NOT NULL,
    proof_file     VARCHAR(255) NOT NULL,
    denial_reason  TEXT DEFAULT NULL,
    submitted_at   DATETIME NOT NULL,
    denied_at      DATETIME DEFAULT NOW(),
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ── Transactions ──────────────────────────────────────
CREATE TABLE transactions (
    transaction_id   INT AUTO_INCREMENT PRIMARY KEY,
    claim_id         INT NOT NULL,
    donor_id         INT NOT NULL,
    amount_sent      DECIMAL(10,2) NOT NULL,
    transaction_date DATETIME DEFAULT NOW(),
    is_anonymous     TINYINT(1) DEFAULT 0,
    FOREIGN KEY (claim_id) REFERENCES claims_approved(claim_id) ON DELETE CASCADE,
    FOREIGN KEY (donor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ── Student Updates ───────────────────────────────────
CREATE TABLE student_updates (
    update_id      INT AUTO_INCREMENT PRIMARY KEY,
    claim_id       INT NOT NULL,
    student_id     INT NOT NULL,
    update_message TEXT NOT NULL,
    created_at     DATETIME DEFAULT NOW(),
    FOREIGN KEY (claim_id)   REFERENCES claims_approved(claim_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
);
