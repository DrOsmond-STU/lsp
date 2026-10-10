<?php
// Worker antrean notifikasi (email + WhatsApp). Jalankan dari cron tiap 1–2 menit. Hanya CLI.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';
$lockDir = APP_ROOT . '/storage';
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0700, true);
}
$lock = fopen($lockDir . '/notify-worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // worker sebelumnya masih berjalan
}
migrate();
$stats = process_outbox(50);
// Bersihkan riwayat lama (90 hari) sesekali.
if (random_int(1, 30) === 1) {
    $old = date('Y-m-d H:i:s', time() - 90 * 86400);
    q("DELETE FROM notification_outbox WHERE created_at < ? AND status <> 'antri'", [$old]);
    q('DELETE FROM ai_usage WHERE created_at < ?', [$old]);
    // Log pencarian publik & percobaan daftar hanya dipakai untuk pembatasan laju; simpan 30 hari saja.
    q("DELETE FROM audit_logs WHERE action IN ('sertifikat.cek','register.attempt') AND created_at < ?", [date('Y-m-d H:i:s', time() - 30 * 86400)]);
}
if (array_sum($stats) > 0) {
    echo date('c'), ' ', json_encode($stats), "\n"; // log hanya bila ada yang diproses
}
