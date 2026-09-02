<?php
/*
 * helpers.php
 * Small utility helpers used across the app for escaping, URL helpers,
 * environment resolution and simple app settings persistence.
 */

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Branding helpers — every value here is admin-editable (Admin ->
 * Settings -> Branding) and stored in app_settings like every other
 * runtime setting, via the existing generic save_app_settings()/
 * apply_app_settings() mechanism (no per-field plumbing needed there —
 * any key saved flows straight into $config['app'][key]). This is what
 * makes the project "open": the event name, tagline, logo and color
 * scheme are never hardcoded, so this codebase can be reused for a
 * different election by an admin who has never touched PHP.
 */
function site_name(array $config): string
{
    $name = trim((string) ($config['app']['event_name'] ?? ''));
    return $name !== '' ? $name : 'Election';
}

function site_tagline(array $config): string
{
    return trim((string) ($config['app']['event_tagline'] ?? ''));
}

function site_logo_url(array $config): string
{
    $logo = trim((string) ($config['app']['logo_url'] ?? ''));
    return $logo !== '' ? $logo : asset_url('assets/images/Untitled.jpeg', $config);
}

/**
 * Resolves the configured logo to a base64 data: URI for embedding
 * directly into generated PDFs (certificates). Deliberately does NOT
 * fetch remote URLs — certificate generation runs with dompdf's
 * isRemoteEnabled off, since fetching an admin-supplied URL server-side
 * is an SSRF vector (it could point at internal network addresses). Only
 * a local file already on this server's disk (an uploaded logo, or the
 * bundled default) is ever embedded; an external logo_url falls back to
 * the text-only certificate heading instead of a broken/missing image.
 */
function site_logo_data_uri(array $config): ?string
{
    $logo = trim((string) ($config['app']['logo_url'] ?? ''));
    $relativePath = $logo !== '' ? $logo : 'assets/images/Untitled.jpeg';

    if (preg_match('#^https?://#i', $relativePath)) {
        return null; // external URL — not fetched, see doc comment above
    }

    $fullPath = __DIR__ . '/../' . ltrim($relativePath, '/');
    $realPath = realpath($fullPath);
    $projectRoot = realpath(__DIR__ . '/..');
    if (!$realPath || !$projectRoot || strpos($realPath, $projectRoot) !== 0 || !is_file($realPath)) {
        return null; // missing, or path escapes the project root
    }

    $mime = match (strtolower(pathinfo($realPath, PATHINFO_EXTENSION))) {
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        default => 'image/jpeg',
    };

    $data = @file_get_contents($realPath);
    if ($data === false) {
        return null;
    }

    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

function site_primary_color(array $config): string
{
    $color = trim((string) ($config['app']['theme_primary_color'] ?? ''));
    return $color !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#c8102e';
}

function site_accent_color(array $config): string
{
    $color = trim((string) ($config['app']['theme_accent_color'] ?? ''));
    return $color !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#c9a227';
}

/**
 * Page background color — deliberately separate from primary/accent:
 * those two tint the hero section, buttons, and badges (already
 * low-opacity overlays derived from them), while this controls the flat
 * base color behind the whole page (style.css's `body { background }`).
 * Wanting to change "the background" and wanting to change "the brand
 * colors" are two different asks even though they interact visually.
 */
function site_background_color(array $config): string
{
    $color = trim((string) ($config['app']['theme_background_color'] ?? ''));
    return $color !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#f7f7f7';
}

/** Body text color — separate from background/primary/accent, same reasoning. */
function site_text_color(array $config): string
{
    $color = trim((string) ($config['app']['theme_text_color'] ?? ''));
    return $color !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#121212';
}

/**
 * Contest titles ("Mr UMU Rubaga" / "Mrs UMU Rubaga") — admin-editable so
 * this codebase can be reused for a differently-named contest (e.g.
 * "Mr & Miss Freshers", "King & Queen of the Ball") without touching PHP.
 */
function site_male_title(array $config): string
{
    $title = trim((string) ($config['app']['male_title'] ?? ''));
    return $title !== '' ? $title : 'Mr UMU Rubaga';
}

function site_female_title(array $config): string
{
    $title = trim((string) ($config['app']['female_title'] ?? ''));
    return $title !== '' ? $title : 'Mrs UMU Rubaga';
}

/**
 * Inline <style> block overriding the CSS custom properties style.css is
 * already built on (--umu-red, --umu-gold) with the admin's chosen
 * colors, plus the page's base background color. Kept as a tiny,
 * isolated override rather than editing style.css itself, so the
 * stylesheet stays cacheable and the override is easy to reason about.
 */
function site_theme_style_tag(array $config): string
{
    $primary = site_primary_color($config);
    $accent = site_accent_color($config);
    $background = site_background_color($config);
    $text = site_text_color($config);
    // .hero's own background is a radial-gradient using hardcoded rgba()
    // literals (not var(--umu-red)/var(--umu-gold)), so overriding the
    // CSS variables alone doesn't touch it — it's what actually produces
    // the pink/cream tint visible on the homepage. Override it directly
    // (flat background color, gradient removed) so the admin's chosen
    // background color is what actually shows there, and neutralize the
    // ::after pseudo-element's separate gold overlay for the same reason.
    return '<style>:root{--umu-red:' . h($primary) . ';--umu-gold:' . h($accent) . ';}'
        . 'body{background:' . h($background) . ';color:' . h($text) . ' !important;}'
        . '.hero{background:' . h($background) . ' !important;}'
        . '.hero::after{background:transparent !important;}</style>';
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(array $config): bool
{
    if (empty($_SESSION['user_email'])) {
        return false;
    }

    // Admins are determined by email address listed in the config
    return in_array($_SESSION['user_email'], $config['app']['admin_emails'], true);
}

function base_url(array $config): string
{
    $base = trim($config['app']['base_url'] ?? '');
    if ($base !== '') {
        return rtrim($base, '/');
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($scriptName === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $scriptName);
    if (strpos($normalized, '/admin/') !== false) {
        $basePath = strstr($normalized, '/admin/', true);
        return $basePath === false ? '' : rtrim($basePath, '/');
    }

    $dir = rtrim(str_replace(basename($normalized), '', $normalized), '/');
    return $dir === '' ? '' : $dir;
}

function asset_url(string $path, array $config): string
{
    $base = absolute_base_url($config);
    $cleanPath = ltrim($path, '/');

    if ($base === '') {
        return $cleanPath;
    }

    return $base . '/' . $cleanPath;
}

function asset_url_versioned(string $path, array $config): string
{
    $url = asset_url($path, $config);
    $cleanPath = ltrim($path, '/');
    $fullPath = __DIR__ . '/../' . $cleanPath;

    if (!is_file($fullPath)) {
        return $url;
    }

    $version = (string) filemtime($fullPath);
    $separator = strpos($url, '?') === false ? '?' : '&';

    return $url . $separator . 'v=' . rawurlencode($version);
}

function absolute_base_url(array $config): string
{
    $basePath = base_url($config);
    
    // If base_url is explicitly set in config, use it
    if (!empty($config['app']['base_url'])) {
        return rtrim($config['app']['base_url'], '/');
    }
    
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return $basePath;
    }

    // Force HTTPS for live server
    $isLive = strpos($host, 'umuelections.fwh.is') !== false;
    $isHttps = $isLive || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';

    return $scheme . '://' . $host . $basePath;
}

function resolve_environment(array $config): string
{
    // Determine which environment configuration to use based on host
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $envs = $config['environments'] ?? [];
    foreach ($envs as $envName => $envConfig) {
        $hosts = $envConfig['hosts'] ?? [];
        foreach ($hosts as $envHost) {
            if ($envHost !== '' && strpos($host, strtolower($envHost)) !== false) {
                return $envName;
            }
        }
    }

    return 'local';
}

function runtime_config(array $config): array
{
    // Apply environment-specific overrides (e.g., DB credentials)
    $envName = resolve_environment($config);
    $envs = $config['environments'] ?? [];
    $envConfig = $envs[$envName] ?? [];

    if (!empty($envConfig['db'])) {
        $config['db'] = $envConfig['db'];
    }

    return $config;
}

function ensure_settings_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_settings (\n"
        . "setting_key VARCHAR(64) PRIMARY KEY,\n"
        . "setting_value TEXT NOT NULL\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/**
 * Self-healing schema change, same pattern as ensure_settings_table(): adds
 * votes.mode if it isn't there yet, so an admin who forgets to run
 * database/migrations/2026_08_30_mode_stamping_and_audit.sql doesn't end up
 * with a broken app. Checks information_schema first because MySQL/MariaDB
 * versions in the wild don't reliably support "ADD COLUMN IF NOT EXISTS".
 *
 * This column is what lets historical ballots keep their original
 * interpretation if an admin switches voting_mode mid-election: every vote
 * row remembers which mode it was cast under, instead of results being
 * recalculated under whatever mode happens to be active today.
 */
function ensure_votes_mode_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votes' AND COLUMN_NAME = 'mode'"
    );
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE votes ADD COLUMN mode VARCHAR(16) NOT NULL DEFAULT 'rating' AFTER score");
        $pdo->exec("ALTER TABLE votes ADD INDEX idx_votes_mode (mode)");
    }

    $checked = true;
}

/**
 * Widens categories.gender from ENUM('male','female') to
 * ENUM('male','female','all') if it isn't already — same self-healing
 * pattern as the other ensure_*() helpers.
 *
 * This is a real, confirmed schema bug, not a guess: the whole app (this
 * file, vote.php, results.php, and admin/categories.php's own "All"
 * dropdown option) has always treated 'all' as a valid category gender
 * meaning "applies to both genders" — but the column was never widened to
 * actually store it. On a strict-mode MySQL server that INSERT would
 * throw; on a non-strict server (common on shared hosting) it silently
 * truncates to an empty string instead. Either way, any category meant
 * to include both genders ends up with a gender value that matches
 * neither 'male' nor 'female' nor 'all' in every gender-matching check
 * across the app — the category effectively shows no contestants of
 * either gender rather than both.
 */
function ensure_category_gender_enum(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $stmt = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'gender'"
    );
    $columnType = (string) $stmt->fetchColumn();
    if (stripos($columnType, "'all'") === false) {
        $pdo->exec("ALTER TABLE categories MODIFY gender ENUM('male','female','all') NOT NULL");
    }

    $checked = true;
}

/**
 * Normalizes a category's stored gender to one of 'male' | 'female' |
 * 'all'. Any other value (empty string from the enum-truncation bug
 * above, NULL, unexpected data) is treated as 'all' — inclusive by
 * default — rather than as excluding every contestant, which is what
 * happened before this function existed. Use this everywhere a
 * category's gender is read for display/validation, instead of comparing
 * the raw column value directly.
 */
function normalize_category_gender(?string $value): string
{
    $v = strtolower(trim((string) $value));
    if ($v === 'male' || $v === 'female') {
        return $v;
    }
    return 'all';
}

/**
 * Resizes and re-compresses an uploaded image in place — applied to both
 * contestant photos and the site logo. Every upload through this app was
 * previously stored at whatever size/quality the voter's phone produced
 * (often several MB per photo), which adds up fast across dozens of
 * contestants and slows the site down for every visitor on every page
 * that shows a photo. This caps the longest side at a sane maximum and
 * re-encodes at a quality level that's visually close to lossless for a
 * headshot-style photo while cutting file size dramatically.
 *
 * Requires the GD extension (ext-gd) — near-universal on shared PHP
 * hosting. If GD isn't available, or the image is corrupt/unreadable,
 * this fails closed by leaving the original upload untouched rather than
 * blocking the upload entirely — a slightly-large photo is a much better
 * outcome than "you can't add contestants because an image extension is
 * missing."
 */
function compress_uploaded_image(string $filePath, int $maxDimension = 1600, int $jpegQuality = 82): bool
{
    if (!function_exists('imagecreatefromstring')) {
        return false;
    }

    $raw = @file_get_contents($filePath);
    if ($raw === false) {
        return false;
    }

    $image = @imagecreatefromstring($raw);
    if ($image === false) {
        return false;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $longestSide = max($width, $height);

    if ($longestSide > $maxDimension) {
        $scale = $maxDimension / $longestSide;
        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        // Preserve transparency for PNG/GIF/WebP instead of flattening
        // it to black.
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $ok = match ($extension) {
        'png' => imagepng($image, $filePath, 6), // 0 (none) - 9 (max); 6 is a good size/speed balance
        'gif' => imagegif($image, $filePath),
        'webp' => function_exists('imagewebp') ? imagewebp($image, $filePath, $jpegQuality) : false,
        default => imagejpeg($image, $filePath, $jpegQuality),
    };

    imagedestroy($image);
    return (bool) $ok;
}

/**
 * Adds an `active` (soft-delete) flag to categories/contestants if it
 * isn't there yet — same self-healing pattern as the other ensure_*()
 * helpers. This exists because categories/contestants currently have
 * `votes ... ON DELETE CASCADE` foreign keys: hard-deleting a category or
 * contestant that already has votes silently destroys those historical
 * ballots with no warning. See safe_delete_category()/safe_delete_contestant().
 */
function ensure_active_column(PDO $pdo, string $table): void
{
    static $checked = [];
    if (!empty($checked[$table])) {
        return;
    }

    // Defensive whitelist even though every current call site passes a
    // hardcoded literal — this function interpolates $table directly into
    // DDL, so if a future caller ever passes anything derived from user
    // input, this is what stops it from becoming a SQL injection vector.
    $allowedTables = ['categories', 'contestants'];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Invalid table for ensure_active_column()');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'active'"
    );
    $stmt->execute([$table]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
    }

    $checked[$table] = true;
}

/**
 * True if this category/contestant already has at least one vote recorded
 * against it — the deciding factor for whether a delete request is safe to
 * perform as a hard DELETE or must be downgraded to an archive (active=0).
 */
function has_votes_for(PDO $pdo, string $column, int $id): bool
{
    $allowed = ['category_id', 'contestant_id'];
    if (!in_array($column, $allowed, true)) {
        throw new InvalidArgumentException('Invalid column for has_votes_for()');
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE $column = ?");
    $stmt->execute([$id]);
    return ((int) $stmt->fetchColumn()) > 0;
}

/**
 * Minimal admin audit log — who changed what, when. Covers the sensitive
 * admin actions this app actually has today (voting mode, voting window,
 * results visibility); extend the $action set as more admin actions need
 * covering rather than logging everything indiscriminately.
 */
function ensure_audit_log_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_audit_log (\n"
        . "id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n"
        . "admin_user_id INT UNSIGNED NULL,\n"
        . "admin_email VARCHAR(191) NULL,\n"
        . "action VARCHAR(64) NOT NULL,\n"
        . "details TEXT NULL,\n"
        . "ip_address VARCHAR(45) NULL,\n"
        . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "INDEX idx_audit_action (action),\n"
        . "INDEX idx_audit_created (created_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function log_admin_action(PDO $pdo, string $action, string $details = ''): void
{
    try {
        ensure_audit_log_table($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_user_id, admin_email, action, details, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['user_email'] ?? null,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (PDOException $e) {
        // Audit logging must never block the admin action it's logging.
    }
}

function fetch_app_settings(PDO $pdo): array
{
    // Read app-level settings stored in DB and return as key => value array
    try {
        ensure_settings_table($pdo);
        $rows = $pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return $settings;
}

function apply_app_settings(array $config, PDO $pdo): array
{
    $settings = fetch_app_settings($pdo);
    if (!$settings) {
        return $config;
    }
    // Map persisted settings (strings) into proper types and merge into config
    $app = $config['app'] ?? [];
    foreach ($settings as $key => $value) {
        switch ($key) {
            case 'voting_open':
            case 'results_public':
                $app[$key] = (bool) (int) $value;
                break;
            case 'category_limit':
                $app[$key] = (int) $value;
                break;
            default:
                $app[$key] = $value;
                break;
        }
    }

    $config['app'] = $app;
    return $config;
}

function save_app_settings(PDO $pdo, array $settings): bool
{
    // Persist a set of application settings into the DB
    try {
        ensure_settings_table($pdo);
        $stmt = $pdo->prepare('REPLACE INTO app_settings (setting_key, setting_value) VALUES (?, ?)');
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, (string) $value]);
        }
    } catch (PDOException $e) {
        return false;
    }

    return true;
}

function get_voting_window(array $config): array
{
    $tzName = $config['app']['timezone'] ?? 'UTC';
    $tz = new DateTimeZone($tzName);
    $startStr = $config['app']['voting_start'] ?? '';
    $endStr = $config['app']['voting_end'] ?? '';

    $start = $startStr !== '' ? new DateTime($startStr, $tz) : null;
    $end = $endStr !== '' ? new DateTime($endStr, $tz) : null;

    if ($start && $end && $end <= $start) {
        $end = (clone $end)->modify('+1 day');
    }

    return [
        'start' => $start,
        'end' => $end,
        'enabled' => !empty($config['app']['voting_open']),
        'timezone' => $tzName,
    ];
}

function is_voting_open_config(array $config): bool
{
    $window = get_voting_window($config);
    if (!$window['enabled']) {
        return false;
    }

    $tz = new DateTimeZone($window['timezone']);
    $now = new DateTime('now', $tz);

    $start = $window['start'];
    $end = $window['end'];

    if ($start && $end) {
        return $now >= $start && $now <= $end;
    } elseif ($start) {
        return $now >= $start;
    } elseif ($end) {
        return $now <= $end;
    }

    return (bool) $window['enabled'];
}

function voting_timestamps_for_client(array $config): array
{
    $window = get_voting_window($config);
    $utc = new DateTimeZone('UTC');

    $startUtc = $window['start'] ? (clone $window['start'])->setTimezone($utc)->format(DateTime::ATOM) : '';
    $endUtc = $window['end'] ? (clone $window['end'])->setTimezone($utc)->format(DateTime::ATOM) : '';

    return [
        'start' => $startUtc,
        'end' => $endUtc,
        'enabled' => $window['enabled'] ? 1 : 0,
    ];
}

/**
 * Single source of truth for "is voting open right now, and what should the
 * banner say". Previously this exact logic was duplicated (and drifting)
 * across vote.php, admin/settings.php and results.php, which is what let a
 * disabled/expired voting window still appear open on some pages.
 */
function voting_status_message(array $config): array
{
    $window = get_voting_window($config);
    $tz = new DateTimeZone($window['timezone']);
    $now = new DateTime('now', $tz);
    $start = $window['start'];
    $end = $window['end'];

    if (!$window['enabled']) {
        return ['open' => false, 'message' => 'Voting is disabled by the admin.'];
    }

    if (!$start && !$end) {
        return ['open' => true, 'message' => 'Voting is open now.'];
    }

    $open = true;
    if ($start && $end) {
        $open = $now >= $start && $now <= $end;
    } elseif ($start) {
        $open = $now >= $start;
    } elseif ($end) {
        $open = $now <= $end;
    }

    if ($open) {
        return ['open' => true, 'message' => 'Voting is open now.'];
    }

    if ($start && $now < $start) {
        return ['open' => false, 'message' => 'Voting opens on ' . $start->format('M d, Y H:i') . ' (' . $window['timezone'] . ').'];
    }

    if ($end && $now > $end) {
        return ['open' => false, 'message' => 'Voting closed on ' . $end->format('M d, Y H:i') . ' (' . $window['timezone'] . ').'];
    }

    return ['open' => false, 'message' => 'Voting is currently closed.'];
}

/**
 * Which ballot workflow is active: 'rating' (the existing 1-5 rate-every-
 * contestant flow) or 'simple' (pick one contestant per category, one
 * "Vote Now" submit — a normal single-choice ballot). Defaults to 'rating'
 * so existing elections are unaffected until an admin opts in.
 */
function get_voting_mode(array $config): string
{
    $mode = strtolower(trim((string) ($config['app']['voting_mode'] ?? 'rating')));
    return $mode === 'simple' ? 'simple' : 'rating';
}

/**
 * Mode-aware leaderboard: ranks by AVG(score) in rating mode and by
 * vote COUNT in simple mode (AVG is meaningless once every vote is worth
 * the same 1 point). Used by vote.php, results.php and certificate.php so
 * there is one ranking implementation instead of three copies that can
 * disagree with each other.
 */
function get_leaderboard(PDO $pdo, string $mode): array
{
    ensure_votes_mode_column($pdo);

    // Filtered by v.mode, not just by which mode is active in settings
    // today — a vote row keeps the mode it was actually cast under, so
    // switching modes mid-election can't reinterpret (or silently blend)
    // older ballots into the new mode's results.
    $metricSql = $mode === 'simple' ? 'COUNT(v.id)' : 'AVG(v.score)';

    $categoryRows = $pdo->prepare(
        "SELECT c.id AS category_id, c.name AS category_name, c.gender,
                con.id AS contestant_id, con.name AS contestant_name, con.gender AS contestant_gender, con.photo,
                $metricSql AS metric
         FROM categories c
         JOIN votes v ON v.category_id = c.id AND v.mode = :mode
         JOIN contestants con ON con.id = v.contestant_id
            AND (c.gender = con.gender OR c.gender NOT IN (\"male\", \"female\"))
         GROUP BY c.id, con.id
         ORDER BY c.id, con.gender, metric DESC, con.id ASC"
    );
    $categoryRows->execute(['mode' => $mode]);
    $categoryRows = $categoryRows->fetchAll();

    // Ties: without an explicit tiebreaker, two contestants with the same
    // metric have no defined order — which one a query returns "first"
    // (and therefore which one this app calls "the winner") is not
    // guaranteed to be consistent, i.e. effectively a coin flip picked by
    // MySQL's internal storage order rather than by the app. `con.id ASC`
    // above makes that pick deterministic (same input always produces the
    // same output) — but deterministic isn't the same as fair, so ties
    // are also tracked explicitly below (tied_with) instead of silently
    // hidden behind a confident-looking single "winner".
    $categoryLeaders = [];
    $categoryTieGroups = []; // key => [contestant_name, ...] all sharing the top metric
    foreach ($categoryRows as $row) {
        $contestantGender = $row['contestant_gender'] ?? 'male';
        $categoryGenderNormalized = normalize_category_gender($row['gender'] ?? null);
        $key = $categoryGenderNormalized === 'all' ? $row['category_id'] . '_' . $contestantGender : $row['category_id'];
        if (!isset($categoryLeaders[$key]) || $row['metric'] > $categoryLeaders[$key]['metric']) {
            $categoryLeaders[$key] = $row;
            $categoryTieGroups[$key] = [$row['contestant_name']];
        } elseif ((float) $row['metric'] === (float) $categoryLeaders[$key]['metric']) {
            $categoryTieGroups[$key][] = $row['contestant_name'];
        }
    }
    foreach ($categoryLeaders as $key => $row) {
        $others = array_diff($categoryTieGroups[$key], [$row['contestant_name']]);
        $categoryLeaders[$key]['tied_with'] = array_values($others);
    }

    $overallRows = $pdo->prepare(
        "SELECT con.id AS contestant_id, con.name AS contestant_name, con.gender, con.photo,
                $metricSql AS metric
         FROM contestants con
         JOIN votes v ON v.contestant_id = con.id AND v.mode = :mode
         JOIN categories c ON c.id = v.category_id
         WHERE c.gender = con.gender OR c.gender NOT IN (\"male\", \"female\")
         GROUP BY con.id, con.gender
         ORDER BY con.gender, metric DESC, con.id ASC"
    );
    $overallRows->execute(['mode' => $mode]);
    $overallRows = $overallRows->fetchAll();

    // Female first, then male — matches the announcement order requested for
    // the results/winners screens.
    $overallWinners = ['female' => null, 'male' => null];
    $overallAll = ['female' => [], 'male' => []];
    $overallTieGroups = ['female' => [], 'male' => []];
    foreach ($overallRows as $row) {
        $gender = $row['gender'] ?? '';
        if ($gender !== 'male' && $gender !== 'female') {
            continue;
        }
        $overallAll[$gender][] = $row;
        if ($overallWinners[$gender] === null) {
            $overallWinners[$gender] = $row;
            $overallTieGroups[$gender] = [$row['contestant_name']];
        } elseif ((float) $row['metric'] === (float) $overallWinners[$gender]['metric']) {
            $overallTieGroups[$gender][] = $row['contestant_name'];
        }
    }
    foreach (['female', 'male'] as $gender) {
        if ($overallWinners[$gender] !== null) {
            $others = array_diff($overallTieGroups[$gender], [$overallWinners[$gender]['contestant_name']]);
            $overallWinners[$gender]['tied_with'] = array_values($others);
        }
    }

    return [
        'mode' => $mode,
        'category_leaders' => $categoryLeaders,
        'overall_winners' => $overallWinners,
        // Full gender-sorted rankings (not just the #1 leader) — used by
        // admin/stats.php's "Overall Rankings" table.
        'overall_all' => $overallAll,
    ];
}

/**
 * "Winner Name" or "Winner Name (tied with Other, Another)" — call this
 * instead of reading contestant_name directly wherever a winner is
 * announced (results, certificates, the post-vote screen), so a genuine
 * tie is never presented as an unqualified single winner.
 */
function leaderboard_winner_label(?array $row): string
{
    if (!$row) {
        return '';
    }
    $name = (string) $row['contestant_name'];
    $tiedWith = $row['tied_with'] ?? [];
    if (!$tiedWith) {
        return $name;
    }
    return $name . ' (tied with ' . implode(', ', $tiedWith) . ')';
}

function format_leaderboard_metric(?array $row, string $mode): string
{
    if (!$row) {
        return '';
    }
    $value = (float) $row['metric'];
    if ($mode === 'simple') {
        $count = (int) round($value);
        return number_format($count) . ' vote' . ($count === 1 ? '' : 's');
    }
    return 'Avg ' . number_format($value, 2) . '/5';
}

/**
 * Counts of already-cast votes grouped by the mode they were cast under
 * (e.g. ['rating' => 40, 'simple' => 12]) — used to warn an admin, before
 * they switch voting_mode, that the election already has ballots recorded
 * under a different mode (those ballots stay intact and are excluded from
 * the currently-active mode's results, per get_leaderboard()'s v.mode
 * filter, but an admin should know they exist).
 */
function vote_counts_by_mode(PDO $pdo): array
{
    ensure_votes_mode_column($pdo);
    try {
        $rows = $pdo->query('SELECT mode, COUNT(*) AS cnt FROM votes GROUP BY mode')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['mode']] = (int) $row['cnt'];
    }
    return $counts;
}

/**
 * Minimal session-bound CSRF protection for the vote and admin-settings
 * forms, neither of which had any token before.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && $token !== '' && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Fixed-window rate limiter backed by a MySQL upsert (ON DUPLICATE KEY
 * UPDATE is atomic), so it works correctly across multiple PHP-FPM workers
 * without needing shared memory, APCu or Redis. Fails OPEN on any DB
 * error — a rate-limiter outage must never be the reason a legitimate vote
 * gets rejected.
 *
 * Deliberately NOT purely IP-based for authenticated actions: voters here
 * are university students who are often behind the same campus/hostel
 * WiFi NAT, so a strict per-IP limit could lock out an entire dorm's worth
 * of legitimate voters. Callers should bucket by user id wherever the
 * action is already authenticated (see rate_limit_client_bucket()).
 */
function ensure_rate_limits_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS rate_limits (\n"
        . "rl_key VARCHAR(191) PRIMARY KEY,\n"
        . "hit_count INT UNSIGNED NOT NULL DEFAULT 0,\n"
        . "expires_at DATETIME NOT NULL,\n"
        . "INDEX idx_rate_limits_expires (expires_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function rate_limit_allow(PDO $pdo, string $bucket, int $maxHits, int $windowSeconds): bool
{
    try {
        ensure_rate_limits_table($pdo);
        $windowId = intdiv(time(), $windowSeconds);
        $key = $bucket . ':' . $windowId;
        $expiresAt = date('Y-m-d H:i:s', ($windowId + 1) * $windowSeconds);

        $stmt = $pdo->prepare(
            'INSERT INTO rate_limits (rl_key, hit_count, expires_at) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE hit_count = hit_count + 1'
        );
        $stmt->execute([$key, $expiresAt]);

        $check = $pdo->prepare('SELECT hit_count FROM rate_limits WHERE rl_key = ?');
        $check->execute([$key]);
        $hitCount = (int) $check->fetchColumn();

        // Opportunistic cleanup instead of a cron job — cheap, and only
        // needs to happen occasionally.
        if (random_int(1, 50) === 1) {
            $pdo->exec('DELETE FROM rate_limits WHERE expires_at < NOW()');
        }
    } catch (PDOException $e) {
        return true;
    }

    if ($hitCount > $maxHits) {
        log_admin_action($pdo, 'rate_limit_blocked', "bucket={$bucket} hits={$hitCount}");
        return false;
    }

    return true;
}

/** Bucket a rate limit by authenticated user id when available, else by IP. */
function rate_limit_client_bucket(string $prefix): string
{
    if (!empty($_SESSION['user_id'])) {
        return $prefix . ':user:' . $_SESSION['user_id'];
    }
    return $prefix . ':ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/**
 * Ballot secrecy: votes.user_id starts out NOT NULL because the one-vote-
 * per-user guarantee (the unique key + the FOR UPDATE lock in vote.php)
 * needs it while voting is open. Once voting has closed, that dedup need
 * is gone — nothing about correct tallying requires knowing WHO cast a
 * given vote, only THAT it was cast. anonymize_ballots() severs that link
 * (SET user_id = NULL) without touching scores/contestants/categories, so
 * results stay byte-for-byte identical while the voter↔choice mapping
 * that a raw DB query could otherwise reconstruct is gone for good.
 *
 * Participation (users.has_voted) is untouched by this — an admin can
 * still see WHO voted, just not WHAT they chose, matching the "don't
 * casually expose voter -> choice" principle without pretending this
 * project can offer cryptographic ballot secrecy.
 */
function ensure_votes_user_id_nullable(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $stmt = $pdo->query(
        "SELECT IS_NULLABLE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votes' AND COLUMN_NAME = 'user_id'"
    );
    if ($stmt->fetchColumn() === 'NO') {
        $pdo->exec('ALTER TABLE votes MODIFY user_id INT NULL');
    }

    $checked = true;
}

/**
 * Irreversibly severs votes.user_id for every already-cast vote. Caller is
 * responsible for confirming with the admin and for only allowing this
 * once voting is closed (see admin/settings.php). Returns the number of
 * rows anonymized.
 */
function anonymize_ballots(PDO $pdo): int
{
    ensure_votes_user_id_nullable($pdo);
    $stmt = $pdo->prepare('UPDATE votes SET user_id = NULL WHERE user_id IS NOT NULL');
    $stmt->execute();
    return $stmt->rowCount();
}
