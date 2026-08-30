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

-- 3. Rate limiting ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS rate_limits (
    rl_key VARCHAR(191) PRIMARY KEY,
    hit_count INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    INDEX idx_rate_limits_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Soft-delete (active) flag for categories/contestants ------------------
--
-- categories/contestants have `votes ... ON DELETE CASCADE` foreign keys,
-- so hard-deleting either once votes exist would silently destroy those
-- historical ballots. admin/categories.php and admin/contestants.php now
-- archive (active = 0) instead of hard-deleting whenever votes already
-- reference the row.

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'active'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE categories ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contestants' AND COLUMN_NAME = 'active'
);
SET @ddl := IF(@col_exists = 0, 'ALTER TABLE contestants ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Ballot secrecy: allow votes.user_id to be nulled out after voting ----
--    closes (see admin/settings.php "Anonymize ballots"). Dedup no longer
--    needs this column once voting is closed, and nulling it severs the
--    voter -> choice link without touching any score/contestant/category.

SET @is_nullable := (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votes' AND COLUMN_NAME = 'user_id'
);
SET @ddl := IF(@is_nullable = 'NO', 'ALTER TABLE votes MODIFY user_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Fix categories.gender to actually support 'all' -----------------------
--
-- categories.gender was ENUM('male','female') NOT NULL, but the entire
-- app (vote.php, results.php, admin/categories.php's own "All" dropdown
-- option) has always treated 'all' as a valid value meaning "applies to
-- both genders". On a non-strict MySQL server (common on shared hosting)
-- inserting 'all' silently truncated to an empty string instead of
-- erroring — and a category with gender = '' matched neither 'male' nor
-- 'female' nor 'all' anywhere in the app, so it showed NO contestants of
-- either gender rather than both. This is the most likely root cause of
-- "female contestants are being ignored" reports: any category meant to
-- include everyone was actually silently empty for everyone.
--
-- Widening the column fixes it going forward. Any category already stuck
-- with a corrupted gender value needs a one-time fix via
-- Admin -> Categories -> Edit (re-select the correct gender and save) —
-- this migration can't safely guess which categories were meant to be
-- 'all' vs originally something else, so it doesn't attempt to rewrite
-- existing rows. The app treats any unrecognized value as inclusive
-- ('all') in the meantime, so nothing stays silently broken while you fix
-- it — see normalize_category_gender() in includes/helpers.php.

SET @column_type := (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'gender'
);
SET @ddl := IF(
    LOCATE("'all'", @column_type) = 0,
    "ALTER TABLE categories MODIFY gender ENUM('male','female','all') NOT NULL",
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
