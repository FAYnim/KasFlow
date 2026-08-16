<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                [$k, $v] = explode('=', $line, 2) + [null, null];
                if ($k !== null && !getenv(trim($k))) {
                    putenv(trim($k) . '=' . trim($v ?? ''));
                }
            }
        }
        $host   = getenv('DB_HOST') ?: '127.0.0.1';
        $dbname = getenv('DB_NAME') ?: 'cashflow_kelas';
        $user   = getenv('DB_USER') ?: 'root';
        $pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
        $dsn    = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo    = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
    }
    return $pdo;
}
