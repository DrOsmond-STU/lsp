<?php
declare(strict_types=1);

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $code = 400): void
{
    json_out(['error' => $message], $code);
}

function body(): array
{
    static $body = null;
    if ($body === null) {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw === false || $raw === '' ? '[]' : $raw, true);
        if (!is_array($body)) {
            $body = [];
        }
    }
    return $body;
}

function text_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/** Ambil string dari body JSON, trim, dan batasi panjang. */
function str_in(string $key, int $max = 255, string $label = ''): string
{
    $v = body()[$key] ?? '';
    if (!is_string($v)) {
        $v = '';
    }
    $v = trim($v);
    if (text_len($v) > $max) {
        fail(($label ?: $key) . ' maksimal ' . $max . ' karakter.', 422);
    }
    return $v;
}

function int_in(string $key): int
{
    $v = body()[$key] ?? null;
    if (is_int($v)) {
        return $v;
    }
    if (is_string($v) && preg_match('/^\d{1,10}$/', $v)) {
        return (int)$v;
    }
    return 0;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        fail('Metode tidak diizinkan.', 405);
    }
}

/** CSRF: semua permintaan yang mengubah data wajib membawa token sesi + Origin yang sama. */
function csrf_check(): void
{
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        return;
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
        if (strtolower((string)parse_url($origin, PHP_URL_HOST)) !== $host) {
            fail('Asal permintaan tidak valid.', 403);
        }
    }
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $token)) {
        fail('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.', 419);
    }
}

function audit(string $action, ?string $target = null, ?int $userId = null, ?int $lspId = null): void
{
    if ($userId === null) {
        $u = current_user();
        $userId = $u ? (int)$u['id'] : null;
    }
    if ($lspId === null) {
        $m = current_user() ? active_membership() : null;
        $lspId = ($m && $m['lsp_id'] !== null) ? (int)$m['lsp_id'] : null;
    }
    q('INSERT INTO audit_logs (user_id, lsp_id, action, target, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, $lspId, $action, $target === null ? null : substr($target, 0, 255), client_ip(), now()]);
}
