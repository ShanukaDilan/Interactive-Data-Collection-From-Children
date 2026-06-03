<?php
/**
 * db.php — Database connection + schema initialisation.
 *
 * Supports Railway MySQL and PostgreSQL plugins.
 * Env vars checked (in order):
 *   MySQL URL  : MYSQL_URL  (mysql://user:pass@host:port/db)
 *   PgSQL URL  : DATABASE_URL (postgresql://... or postgres://...)
 *   MySQL vars : MYSQLHOST / MYSQL_HOST, MYSQLUSER / MYSQL_USER,
 *                MYSQLPASSWORD / MYSQL_PASSWORD,
 *                MYSQLDATABASE / MYSQL_DATABASE, MYSQLPORT / MYSQL_PORT
 *   PgSQL vars : PGHOST, PGUSER, PGPASSWORD, PGDATABASE, PGPORT
 *
 * Returns null if no DB is configured (app falls back to flat file).
 */

function getDb(): ?PDO
{
    static $pdo = false;   // false = not yet attempted; null = tried and failed
    if ($pdo !== false) return $pdo;

    $pdo = _connectDb();
    if ($pdo) {
        try {
            _initSchema($pdo);
        } catch (Throwable $e) {
            error_log("DB _initSchema error: " . $e->getMessage());
            // Connection still works even if schema init had a problem
        }
    }
    return $pdo;
}

function _connectDb(): ?PDO
{
    // ── 1. URL-style env vars ─────────────────────────────────────
    foreach (['MYSQL_URL', 'DATABASE_URL'] as $key) {
        $url = getenv($key);
        if (!$url) continue;

        $p = parse_url($url);
        if (empty($p['host'])) continue;

        $scheme = strtolower($p['scheme'] ?? '');
        if (str_contains($scheme, 'mysql') || str_contains($scheme, 'mariadb')) {
            $driver = 'mysql';
        } elseif (str_contains($scheme, 'postgres') || str_contains($scheme, 'pgsql')) {
            $driver = 'pgsql';
        } else {
            continue;
        }

        $host   = $p['host'];
        $port   = $p['port'] ?? ($driver === 'mysql' ? 3306 : 5432);
        $dbname = ltrim($p['path'] ?? '', '/');
        $user   = urldecode($p['user'] ?? '');
        $pass   = urldecode($p['pass'] ?? '');

        $dsn = $driver === 'mysql'
            ? "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4"
            : "pgsql:host=$host;port=$port;dbname=$dbname";

        try {
            return new PDO($dsn, $user, $pass, _pdoOptions());
        } catch (Throwable $e) {
            error_log("DB[$key]: " . $e->getMessage());
        }
    }

    // ── 2. Individual MySQL env vars ──────────────────────────────
    $host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST');
    if ($host) {
        $port   = (int)(getenv('MYSQLPORT')     ?: getenv('MYSQL_PORT')     ?: 3306);
        $dbname = getenv('MYSQLDATABASE')        ?: getenv('MYSQL_DATABASE') ?: '';
        $user   = getenv('MYSQLUSER')            ?: getenv('MYSQL_USER')     ?: '';
        $pass   = getenv('MYSQLPASSWORD')        ?: getenv('MYSQL_PASSWORD') ?: '';
        try {
            return new PDO(
                "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
                $user, $pass, _pdoOptions()
            );
        } catch (Throwable $e) {
            error_log("DB[MYSQL vars]: " . $e->getMessage());
        }
    }

    // ── 3. Individual PostgreSQL env vars ─────────────────────────
    $pghost = getenv('PGHOST');
    if ($pghost) {
        $port   = (int)(getenv('PGPORT')     ?: 5432);
        $dbname = getenv('PGDATABASE') ?: '';
        $user   = getenv('PGUSER')     ?: '';
        $pass   = getenv('PGPASSWORD') ?: '';
        try {
            return new PDO(
                "pgsql:host=$pghost;port=$port;dbname=$dbname",
                $user, $pass, _pdoOptions()
            );
        } catch (Throwable $e) {
            error_log("DB[PG vars]: " . $e->getMessage());
        }
    }

    return null;
}

function _pdoOptions(): array
{
    return [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
}

function _initSchema(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        // Separate table creation from index creation for maximum compatibility
        $pdo->exec("CREATE TABLE IF NOT EXISTS responses (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            session_id       VARCHAR(64),
            child_age        TINYINT,
            child_id         VARCHAR(60),
            quiz_mode        VARCHAR(20)  DEFAULT 'correct',
            question_id      VARCHAR(20),
            question_text    VARCHAR(200),
            category         VARCHAR(60),
            correct_label    VARCHAR(100),
            selected_label   VARCHAR(100),
            is_correct       TINYINT      DEFAULT 0,
            attempts         TINYINT      DEFAULT 1,
            response_time_ms INT          DEFAULT 0,
            created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add indexes separately — silently skip if they already exist
        foreach ([
            "CREATE INDEX idx_resp_session  ON responses (session_id)",
            "CREATE INDEX idx_resp_category ON responses (category)",
            "CREATE INDEX idx_resp_age      ON responses (child_age)",
        ] as $sql) {
            try { $pdo->exec($sql); } catch (Throwable $e) { /* already exists */ }
        }
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS responses (
            id               SERIAL PRIMARY KEY,
            session_id       VARCHAR(64),
            child_age        SMALLINT,
            child_id         VARCHAR(60),
            quiz_mode        VARCHAR(20)  DEFAULT 'correct',
            question_id      VARCHAR(20),
            question_text    VARCHAR(200),
            category         VARCHAR(60),
            correct_label    VARCHAR(100),
            selected_label   VARCHAR(100),
            is_correct       SMALLINT     DEFAULT 0,
            attempts         SMALLINT     DEFAULT 1,
            response_time_ms INT          DEFAULT 0,
            created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )");
        foreach ([
            "CREATE INDEX IF NOT EXISTS idx_resp_session  ON responses (session_id)",
            "CREATE INDEX IF NOT EXISTS idx_resp_category ON responses (category)",
            "CREATE INDEX IF NOT EXISTS idx_resp_age      ON responses (child_age)",
        ] as $sql) {
            try { $pdo->exec($sql); } catch (Throwable $e) { /* already exists */ }
        }
    }
}
