-- Migration: 2026_08_30_mode_stamping_and_audit
--
-- Purpose:
--   1. Stamp every vote row with the ballot workflow ('rating' or 'simple')
--      it was actually cast under, so switching the admin's voting_mode
--      setting later can never reinterpret or blend older ballots into the
--      new mode's results.
--   2. Add a minimal admin audit log for sensitive settings changes
--      (voting mode, voting window, results visibility).
--
-- Safe to run more than once: uses IF NOT EXISTS / information_schema
-- checks throughout. The application (includes/helpers.php) also applies
-- these changes defensively on first use if this file was never run
-- manually, so running it is optional but recommended for visibility and
-- for environments where the DB user only has DDL rights via migrations.

-- 1. Per-ballot mode stamping -------------------------------------------------

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votes' AND COLUMN_NAME = 'mode'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE votes ADD COLUMN mode VARCHAR(16) NOT NULL DEFAULT ''rating'' AFTER score',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votes' AND INDEX_NAME = 'idx_votes_mode'
);

SET @ddl := IF(
    @idx_exists = 0,
    'ALTER TABLE votes ADD INDEX idx_votes_mode (mode)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Existing rows default to 'rating' via the column default above, which is
-- correct: every vote cast before this migration was cast under the
-- rating/star workflow (the simple one-click mode did not exist yet).

-- 2. Admin audit log -----------------------------------------------------------

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT UNSIGNED NULL,
    admin_email VARCHAR(191) NULL,
    action VARCHAR(64) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
