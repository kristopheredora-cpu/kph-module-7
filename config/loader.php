<?php
// Returns the module's settings.
//  - On a computer that has config/config.php (local XAMPP), that file is used as before.
//  - On Azure App Service config.php does not exist (it is not in GitHub), so the
//    settings come from the app's environment variables (Application settings) instead.
function kph_config(): array {
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $file = __DIR__ . '/config.php';
    if (is_file($file)) {
        $cfg = require $file;
        return $cfg;
    }

    $env = function (string $key, string $default = ''): string {
        $v = getenv($key);
        return ($v !== false && $v !== '') ? $v : $default;
    };

    $cfg = [
        'host'     => $env('DB_HOST'),
        'port'     => (int)$env('DB_PORT', '3306'),
        'dbname'   => $env('DB_NAME', 'kph-database'),
        'user'     => $env('DB_USER'),
        'pass'     => $env('DB_PASS'),
        'charset'  => 'utf8mb4',
        'cron_key' => $env('CRON_KEY'),
        'modules'  => [
            'orders'   => $env('MODULE6_API'),
            'sellers'  => $env('MODULE2_API'),
            'catalog'  => $env('MODULE3_API'),
            'shipping' => $env('MODULE8_API'),
        ],
    ];
    return $cfg;
}
