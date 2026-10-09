<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$cfgFile = APP_ROOT . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Konfigurasi aplikasi belum dibuat.']);
    exit;
}
$CONFIG = require $cfgFile;

date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/db.php';
require __DIR__ . '/http.php';
require __DIR__ . '/rbac.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/asesi.php';
require __DIR__ . '/notify.php';
require __DIR__ . '/ai.php';
require __DIR__ . '/migrations.php';
require __DIR__ . '/routes.php';
