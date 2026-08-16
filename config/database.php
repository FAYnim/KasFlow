<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Load .env file if present (supports quoted values and #-comments)
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip comment lines
                if ($line === '' || $line[0] === '#') continue;

                if (strpos($line, '=') === false) continue;
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val ?? '');

                // Strip surrounding single or double quotes from value
                if (strlen($val) >= 2
                    && (($val[0] === '"' && substr($val, -1) === '"')
                        || ($val[0] === "'" && substr($val, -1) === "'"))
                ) {
                    $val = substr($val, 1, -1);
                }

                // Only set if not already defined in the environment
                if ($key !== '' && getenv($key) === false) {
                    putenv("$key=$val");
                    $_ENV[$key]    = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }

        $host   = getenv('DB_HOST') ?: '127.0.0.1';
        $port   = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'cashflow_kelas';
        $user   = getenv('DB_USER') ?: 'root';
        $pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
    }
    return $pdo;
}
