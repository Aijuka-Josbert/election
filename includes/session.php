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
| Load DB ($pdo) from includes/db.php, unless a calling script already
| did so.
|---------------------------------------------------------------------------
| BUG FIXED HERE: this used to unconditionally set $pdo = null and then
| require_once db.php "to populate it" — but require_once tracks files by
| resolved path, so if db.php was already required earlier in the same
| request (e.g. certificate.php does config -> db.php -> helpers.php ->
| session.php, in that order), this second require_once is a silent
| no-op. $pdo had already been reset to null on the line above it and
| never got repopulated, so every page that happened to require db.php
| before session.php lost its database connection entirely for the rest
| of the request — with no error, just $pdo silently becoming null. Only
| reset/reload if nothing already gave us a working connection.
*/
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = null;
    $dbPath = __DIR__ . '/db.php';
    if (is_file($dbPath)) {
        try {
            require_once $dbPath; // should populate $pdo (PDO) in your project
        } catch (Throwable $e) {
            $pdo = null;
        }
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
        private ?string $lockName = null;

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
            $this->releaseLock();
            return true;
        }

        /**
         * MySQL advisory lock (GET_LOCK) per session id, held for the
         * duration of the request. Without this, two requests for the same
         * session (a double-click, a second browser tab, a page loading
         * while another is still submitting) can both read the same
         * "before" session data and then both write back their own
         * "after" version — whichever write lands last wins, silently
         * discarding whatever the other request had just set (login
         * state, has_voted, CSRF token, admin flag). This is the same
         * class of race vote.php's per-voter FOR UPDATE lock closes for
         * ballots; this closes it for session state itself.
         */
        private function acquireLock(string $id): void
        {
            $this->lockName = 'election_session_' . $id;
            try {
                $stmt = $this->pdo->prepare('SELECT GET_LOCK(?, ?)');
                $stmt->execute([$this->lockName, $this->lockTimeout]);
            } catch (PDOException $e) {
                // If locking itself fails (e.g. no permission on a
                // restrictive shared host), fall back to unlocked
                // behavior rather than breaking sessions entirely.
                $this->lockName = null;
            }
        }

        private function releaseLock(): void
        {
            if ($this->lockName === null) {
                return;
            }
            try {
                $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
                $stmt->execute([$this->lockName]);
            } catch (PDOException $e) {
                // ignore
            }
            $this->lockName = null;
        }

        public function read($id): string
        {
            $this->acquireLock($id);
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
                $result = (bool)$stmt->execute([$id]);
            } catch (PDOException $e) {
                $result = false;
            }
            $this->releaseLock();
            return $result;
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