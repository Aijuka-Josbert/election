<?php
/*
 * includes/session.php
 * DB-backed sessions with automatic fallback to file sessions.
 */

declare(strict_types=1);

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    return false;
}

/*
|---------------------------------------------------------------------------
| Secure session cookie settings
|---------------------------------------------------------------------------
*/
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $cookieParams['path'] ?? '/',
    'domain'   => $cookieParams['domain'] ?? '',
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

/*
|---------------------------------------------------------------------------
| Session GC
|---------------------------------------------------------------------------
| Keep sessions for 1.5 hours (90 minutes) before deletion.
*/
ini_set('session.gc_maxlifetime', (string)(60 * 90)); // 1.5 hours
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');

/*
|---------------------------------------------------------------------------
| Try to load DB ($pdo) from includes/db.php
|---------------------------------------------------------------------------
*/
$pdo = null;
$dbPath = __DIR__ . '/db.php';
if (is_file($dbPath)) {
    try {
        require_once $dbPath; // should populate $pdo (PDO) in your project
    } catch (Throwable $e) {
        $pdo = null;
    }
}

/*
|---------------------------------------------------------------------------
| If we have a PDO, ensure sessions table exists and register handler
|---------------------------------------------------------------------------
*/
if (!empty($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                access INT(11) NOT NULL,
                data MEDIUMTEXT,
                INDEX (access)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        // table create failed — we'll fallback to file sessions below
        $pdo = null;
    }
}

if (!empty($pdo) && $pdo instanceof PDO) {

    class PdoSessionHandler implements SessionHandlerInterface
    {
        private PDO $pdo;
        private string $table = 'sessions';
        private int $lockTimeout = 10;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        public function open($savePath, $sessionName): bool
        {
            return true;
        }

        public function close(): bool
        {
            return true;
        }

        public function read($id): string
        {
            try {
                $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ? (string)$row['data'] : '';
            } catch (PDOException $e) {
                return '';
            }
        }

        public function write($id, $data): bool
        {
            try {
                $access = time();
                $stmt = $this->pdo->prepare("
                    REPLACE INTO {$this->table} (id, access, data) VALUES (?, ?, ?)
                ");
                return (bool)$stmt->execute([$id, $access, $data]);
            } catch (PDOException $e) {
                return false;
            }
        }

        public function destroy($id): bool
        {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
                return (bool)$stmt->execute([$id]);
            } catch (PDOException $e) {
                return false;
            }
        }

        public function gc(int $maxlifetime): int
        {
            try {
                $old = time() - $maxlifetime;
                $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE access < ?");
                $stmt->execute([$old]);
                return $stmt->rowCount();
            } catch (PDOException $e) {
                return 0;
            }
        }
    }

    try {
        $handler = new PdoSessionHandler($pdo);
        session_set_save_handler($handler, true);
    } catch (Throwable $e) {
        // fallback to native file sessions
    }
}

/*
|---------------------------------------------------------------------------
| Start session (fallback to file sessions if DB handler not available)
|---------------------------------------------------------------------------
*/
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}