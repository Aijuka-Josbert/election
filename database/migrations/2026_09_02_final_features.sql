-- Migration: 2026_09_02_final_features
--
-- Adds:
--   1. tie_break_log — records an admin's manual decision when two or
--      more contestants are exactly tied. See record_tie_break() /
--      get_tie_break_winner() in includes/helpers.php.
--   2. New app_settings keys (no schema change needed — app_settings is
--      a plain key-value table): voting_mode_locked, theme_font,
--      allow_any_email. Listed here for documentation; each is written
--      on first use via save_app_settings() the same as every other
--      runtime setting, so no explicit INSERT is required.
--
-- Safe to run more than once (CREATE TABLE IF NOT EXISTS). The app also
-- applies this table defensively on first use via ensure_tie_break_table()
-- in includes/helpers.php, same self-healing pattern as every other
-- ensure_*() helper — running this migration by hand is optional but
-- recommended for visibility.

CREATE TABLE IF NOT EXISTS tie_break_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    gender VARCHAR(16) NOT NULL,
    winner_contestant_id INT NOT NULL,
    admin_user_id INT UNSIGNED NULL,
    admin_email VARCHAR(191) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tie_break_lookup (category_id, gender)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- app_settings reference (documentation only, nothing to run):
--   voting_mode_locked  '1' once any vote has been cast; blocks further
--                        voting_mode changes from admin/settings.php.
--   theme_font           One of the values in site_font_options()
--                        (includes/helpers.php) — defaults to 'Manrope'.
--   allow_any_email      '1' to skip the allowed_domain check in
--                        google-callback.php. Defaults to off.
