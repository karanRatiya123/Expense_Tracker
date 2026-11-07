-- ApexSpend single-database installation.
-- This file is the complete schema and seed data for the application.
CREATE DATABASE IF NOT EXISTS apexspend_db;
USE apexspend_db;

-- ============================================================
--  users  — written by auth.php (login + signup)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    email_notifications TINYINT(1) NOT NULL DEFAULT 1,
    welcome_seen TINYINT(1) NOT NULL DEFAULT 0,
    savings_goal DECIMAL(15,2) DEFAULT 50000.00,
    run_rate_target DECIMAL(15,2) DEFAULT 1000000.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('expense', 'income') NOT NULL,
    title VARCHAR(255) NOT NULL,
    note TEXT,
    amount DECIMAL(15,2) NOT NULL,
    category VARCHAR(100) NOT NULL,
    date DATETIME NOT NULL,
    method VARCHAR(100),
    INDEX idx_user_date (user_id, date),
    INDEX idx_user_category (user_id, category),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS budgets (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    period_type VARCHAR(50) NOT NULL DEFAULT 'monthly',
    start_date DATE,
    end_date DATE,
    thresholds JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_category (user_id, category),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
--  login_attempts — brute-force throttle written by auth.php
--  (includes/login_guard.php). Five failures inside a 15-minute
--  window block that email+IP until the window passes.
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    email VARCHAR(100) NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    attempts INT NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    PRIMARY KEY (email, ip)
);

-- ============================================================
--  EXISTING INSTALLS: the transaction/budget indexes above can be
--  added to a live database without touching data:
--    ALTER TABLE transactions ADD INDEX idx_user_date (user_id, date);
--    ALTER TABLE transactions ADD INDEX idx_user_category (user_id, category);
--    ALTER TABLE budgets ADD INDEX idx_user_category (user_id, category);
-- ============================================================

-- ============================================================
--  Family Space — added later, additive only (no change to
--  users / transactions / budgets, existing data preserved)
-- ============================================================
CREATE TABLE IF NOT EXISTS families (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    invite_code VARCHAR(10) NOT NULL UNIQUE,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS family_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin','member') NOT NULL DEFAULT 'member',
    status ENUM('active','pending','rejected') NOT NULL DEFAULT 'active',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_family_user (family_id, user_id),
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
--  categories — user-defined categories, added later, additive
--  only. transactions.category stays VARCHAR(100); built-in
--  seeds match today's names exactly, so no backfill is needed.
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(60) NOT NULL DEFAULT 'fa-tag',
    color VARCHAR(9) NOT NULL DEFAULT '#f59e0b',
    is_builtin TINYINT(1) NOT NULL DEFAULT 0,
    position INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_category (user_id, name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT IGNORE INTO categories (id, user_id, name, icon, color, is_builtin, position)
SELECT 'cat_seed_food',    u.id, 'Food & Dining',       'fa-utensils',          '#f59e0b', 1, 1 FROM users u ORDER BY u.id LIMIT 1;
INSERT IGNORE INTO categories (id, user_id, name, icon, color, is_builtin, position)
SELECT 'cat_seed_house',   u.id, 'Housing & Utilities', 'fa-bolt',              '#3b82f6', 1, 2 FROM users u ORDER BY u.id LIMIT 1;
INSERT IGNORE INTO categories (id, user_id, name, icon, color, is_builtin, position)
SELECT 'cat_seed_shop',    u.id, 'Shopping & Retail',   'fa-bag-shopping',      '#a855f7', 1, 3 FROM users u ORDER BY u.id LIMIT 1;
INSERT IGNORE INTO categories (id, user_id, name, icon, color, is_builtin, position)
SELECT 'cat_seed_trans',   u.id, 'Transportation',      'fa-car',               '#4ade80', 1, 4 FROM users u ORDER BY u.id LIMIT 1;
INSERT IGNORE INTO categories (id, user_id, name, icon, color, is_builtin, position)
SELECT 'cat_seed_ent',     u.id, 'Entertainment',       'fa-film',              '#ec4899', 1, 5 FROM users u ORDER BY u.id LIMIT 1;
INSERT IGNORE INTO categories (id, user_id, name, icon, color, is_builtin, position)
SELECT 'cat_seed_health',  u.id, 'Health & Medical',    'fa-briefcase-medical', '#22d3ee', 1, 6 FROM users u ORDER BY u.id LIMIT 1;
INSERT IGNORE INTO categories (id, user_id, name, icon, color, is_builtin, position)
SELECT 'cat_seed_income',  u.id, 'Income',              'fa-money-bill-wave',   '#8a8a8a', 1, 7 FROM users u ORDER BY u.id LIMIT 1;

-- Insert demo user (demo@apexspend.com / password123)
INSERT IGNORE INTO users (id, name, email, password, created_at) VALUES 
(1, 'Alex Morgan', 'demo@apexspend.com', '$2y$10$CbxJ6QzJeq5X2pixzmgNveQZYBsL4RmkjZeYsKPv2wsrtp0WwU4uW', '2026-07-28 10:00:00');

-- Insert demo transactions
INSERT IGNORE INTO transactions (id, user_id, type, title, note, amount, category, date, method) VALUES 
('tx_201', 1, 'expense', 'Chai Point', 'Evening chai & samosa', 180.00, 'Food & Dining', '2026-07-28 15:45:00', 'UPI ··4182'),
('tx_202', 1, 'expense', 'BigBasket', 'Weekly groceries', 11800.50, 'Food & Dining', '2026-07-28 14:30:00', 'UPI ··4182'),
('tx_203', 1, 'income', 'Tech Corp Payroll', 'Salary, July', 450000.00, 'Income', '2026-07-25 09:00:00', 'NEFT ··0019'),
('tx_204', 1, 'expense', 'Tata Power', 'Electric bill, July', 8240.00, 'Housing & Utilities', '2026-07-22 10:14:00', 'NEFT ··0019'),
('tx_205', 1, 'expense', 'Netflix', 'Standard plan, monthly', 649.00, 'Entertainment', '2026-07-20 18:12:00', 'UPI ··4182'),
('tx_206', 1, 'expense', 'Rapido', 'Ride to Kempegowda Airport, T2', 1420.00, 'Transportation', '2026-07-18 11:30:00', 'UPI'),
('tx_207', 1, 'expense', 'Apollo Pharmacy', 'Vitamins & first-aid', 2150.00, 'Health & Medical', '2026-07-15 19:45:00', 'RuPay ··2207'),
('tx_208', 1, 'expense', 'H&M', 'Summer wardrobe', 6480.00, 'Shopping & Retail', '2026-07-12 16:22:00', 'RuPay ··2207'),
('tx_209', 1, 'expense', 'BESCOM', 'Electricity, June', 6450.00, 'Housing & Utilities', '2026-07-08 10:00:00', 'NEFT ··0019'),
('tx_210', 1, 'expense', 'Indigo', 'Flight BOM → BLR', 8950.00, 'Transportation', '2026-07-05 07:15:00', 'RuPay ··2207'),
('tx_211', 1, 'expense', 'Toit Brewery', 'Dinner with friends', 4200.00, 'Food & Dining', '2026-07-03 20:30:00', 'UPI ··4182'),
('tx_212', 1, 'expense', 'BookMyShow', 'Inox, 4 tickets', 1800.00, 'Entertainment', '2026-07-01 19:00:00', 'UPI ··4182'),
('tx_213', 1, 'expense', 'Airtel Broadband', 'Monthly plan', 999.00, 'Housing & Utilities', '2026-06-28 08:00:00', 'NEFT ··0019'),
('tx_214', 1, 'expense', 'Decathlon', 'Running shoes', 5999.00, 'Shopping & Retail', '2026-06-25 14:10:00', 'RuPay ··2207'),
('tx_215', 1, 'income', 'Freelance Project', 'UI design — milestone 2', 75000.00, 'Income', '2026-06-20 11:00:00', 'IMPS'),
('tx_216', 1, 'expense', 'Shell Petrol', 'Fuel', 3500.00, 'Transportation', '2026-06-18 09:20:00', 'RuPay ··2207'),
('tx_217', 1, 'expense', 'IKEA', 'Desk lamp & organizers', 4150.00, 'Shopping & Retail', '2026-06-15 15:40:00', 'UPI ··4182'),
('tx_218', 1, 'expense', 'Cult Fitness', 'Monthly membership', 2500.00, 'Health & Medical', '2026-06-10 07:30:00', 'NEFT ··0019'),
('tx_219', 1, 'expense', 'Spotify', 'Premium, family plan', 299.00, 'Entertainment', '2026-06-05 09:00:00', 'RuPay ··2207'),
('tx_220', 1, 'expense', 'Manipal Hospital', 'Annual health check-up', 7800.00, 'Health & Medical', '2026-06-02 11:30:00', 'RuPay ··2207');


