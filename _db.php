<?php

function respondDbInitError($message, $statusCode = 500)
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }
    echo json_encode([
        'result' => 'Error',
        'message' => $message
    ]);
    exit;
}

// Prefer MySQL if reachable, otherwise use SQLite.
// Only cache positive MySQL probe to avoid sticky fallback to SQLite.
$cacheFile = __DIR__ . '/.db_driver_cache.json';
$mysqlSchemaMarker = __DIR__ . '/.db_mysql_schema_v1';
$mysqlReachable = false;
$now = time();
$hasFreshCache = false;

if (is_file($mysqlSchemaMarker)) {
    $mysqlReachable = true;
    $hasFreshCache = true;
} elseif (is_file($cacheFile)) {
    $cacheRaw = @file_get_contents($cacheFile);
    $cache = $cacheRaw ? json_decode($cacheRaw, true) : null;
    if (
        is_array($cache) &&
        isset($cache['driver'], $cache['expires_at']) &&
        $cache['driver'] === 'mysql' &&
        intval($cache['expires_at']) > $now
    ) {
        $hasFreshCache = true;
        $mysqlReachable = true;
    }
}

if (!$hasFreshCache) {
    $socket = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 0.2);
    if ($socket) {
        $mysqlReachable = true;
        @fclose($socket);
    }

    if ($mysqlReachable) {
        @file_put_contents($cacheFile, json_encode([
            'driver' => 'mysql',
            'expires_at' => $now + 30
        ]));
    } else {
        @unlink($cacheFile);
    }
}

try {
    if ($mysqlReachable) {
        require_once '_db_mysql.php';
    } else {
        require_once '_db_sqlite.php';
    }
} catch (Throwable $e) {
    respondDbInitError('Không thể kết nối cơ sở dữ liệu.');
}

require_once '_tenant.php';
require_once '_reservation_data.php';
require_once '_trip_data.php';