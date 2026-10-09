<?php
// Uji asap dari CLI (cron): jalankan migrasi lalu cek isi tabel inti. Tidak menampilkan rahasia.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';
$out = ['php' => PHP_VERSION, 'driver' => null, 'ok' => false];
try {
    $out['driver'] = driver();
    migrate();
    foreach (['lsp', 'users', 'memberships', 'roles', 'permissions', 'role_permissions', 'listings'] as $t) {
        $out['count'][$t] = (int)db()->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    }
    $out['ok'] = true;
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}
echo json_encode($out, JSON_PRETTY_PRINT), "\n";
