<?php
// Membuat config.php untuk pengujian lokal (SQLite). Jangan dipakai di produksi.
$dir = dirname(__DIR__);
$db = $argv[1] ?? ($dir . '/storage/test.sqlite');
$pw = 'Demo-Pass-2026';
$emails = ['superadmin', 'admin.tdn', 'marketing.tdn', 'keuangan.tdn', 'admin.pbi', 'tuk.kuningan', 'asesor', 'asesi'];
$hashes = [];
foreach ($emails as $e) {
    $hashes[$e . '@demo.portallsp.id'] = password_hash($pw, PASSWORD_DEFAULT);
}
$cfg = [
    'db' => ['dsn' => 'sqlite:' . $db, 'user' => null, 'pass' => null],
    'session_name' => 'lsp_sid',
    'idle_timeout' => 1800,
    'absolute_timeout' => 28800,
    'cookie_secure' => false,
    'seed_password_hashes' => $hashes,
    'app_url' => 'http://127.0.0.1:8099',
    'mail_log' => $dir . '/storage/mail.log',
];
file_put_contents($dir . '/config.php', "<?php\nreturn " . var_export($cfg, true) . ";\n");
