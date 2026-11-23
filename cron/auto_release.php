<?php
// Releases every delivered fulfillment whose 3 days are over (disputed ones are skipped).
// Run it once an hour or once a day.
//
//   Command line (hosts with cron):   php cron/auto_release.php
//   Web (hosts without cron):         https://YOUR-HOST/.../cron/auto_release.php?key=YOUR_SECRET
//
// The web version only works if "cron_key" in config/config.php is set to a long random text.
// Leave cron_key empty in the repository and set it only on the host.
require_once __DIR__ . '/../classes/Fulfillment.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/../config/loader.php';
    $config = kph_config();
    $secret = (string)($config['cron_key'] ?? '');
    $given = $_GET['key'] ?? '';
    if ($secret === '' || !is_string($given) || !hash_equals($secret, $given)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

try {
    $count = (new Fulfillment())->autoReleaseFunds();
    $message = "$count fulfillment(s) auto-released";
    if ($isCli) {
        echo $message . PHP_EOL;
    } else {
        echo json_encode(['message' => $message, 'released' => $count]);
    }
} catch (Throwable $e) {
    error_log('Auto-release job failed: ' . $e->getMessage());
    if ($isCli) {
        echo 'Auto-release failed. See the error log.' . PHP_EOL;
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
