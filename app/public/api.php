<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

try {
    migrate();
    $route = isset($_GET['r']) && is_string($_GET['r']) ? $_GET['r'] : '';
    // Endpoint publik tanpa cookie sesi tidak membuat sesi baru (mencegah banjir file sesi dari bot).
    $cookie = (string)($CONFIG['session_name'] ?? 'lsp_sid');
    if (strncmp($route, 'pub/', 4) !== 0 || isset($_COOKIE[$cookie])) {
        // Polling jumlah notifikasi tidak dihitung sebagai aktivitas, jadi batas idle 30 menit tetap berlaku.
        start_session($route !== 'notifications/count');
    }
    dispatch($route);
} catch (Throwable $e) {
    error_log('[lsp-api] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_out(['error' => 'Terjadi kesalahan di server. Coba lagi beberapa saat lagi.'], 500);
}
