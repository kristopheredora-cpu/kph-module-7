<?php
// One shared PDO connection for the whole module.
//  - Database::connect()             is what the class's shared helpers.php calls.
//  - (new Database())->getConnection() is what our own classes already use, so nothing else changes.
class Database {
    private static ?PDO $pdo = null;

    public static function connect(): PDO {
        if (self::$pdo === null) {
            require_once __DIR__ . '/loader.php';
            $c = kph_config();
            $port = $c['port'] ?? 3306;
            $charset = $c['charset'] ?? 'utf8mb4';
            $dsn = "mysql:host={$c['host']};port={$port};dbname={$c['dbname']};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 10,
            ];
            // Azure MySQL needs an encrypted connection. Optional: set "ssl_ca" in config.php
            // to the path of Azure's CA certificate if the connection is refused.
            if (!empty($c['ssl_ca']) && file_exists($c['ssl_ca'])) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $c['ssl_ca'];
            } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], $options);
        }
        return self::$pdo;
    }

    // Kept so our existing classes keep working without changes.
    public function getConnection(): PDO {
        return self::connect();
    }
}
