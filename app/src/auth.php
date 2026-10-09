<?php
declare(strict_types=1);

const MAX_FAIL_PER_EMAIL = 5;
const MAX_FAIL_PER_IP = 20;
const LOCK_WINDOW_SEC = 900;

function start_session(): void
{
    global $CONFIG;
    $dir = APP_ROOT . '/storage/sessions';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    session_save_path($dir);
    session_name($CONFIG['session_name'] ?? 'lsp_sid');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)($CONFIG['absolute_timeout'] ?? 28800));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (bool)($CONFIG['cookie_secure'] ?? true),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $t = time();
    if (!empty($_SESSION['uid'])) {
        $idle = $t - (int)($_SESSION['last'] ?? 0);
        $age = $t - (int)($_SESSION['born'] ?? 0);
        if ($idle > (int)($CONFIG['idle_timeout'] ?? 1800) || $age > (int)($CONFIG['absolute_timeout'] ?? 28800)) {
            clear_session();
            $_SESSION['expired'] = 1;
        } else {
            $_SESSION['last'] = $t;
        }
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function clear_session(): void
{
    forget_user_cache();
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function forget_user_cache(): void
{
    unset($GLOBALS['__lsp_user']);
}

/** Pengguna dibaca ulang dari DB setiap request, sehingga akun nonaktif langsung kehilangan akses. */
function current_user(): ?array
{
    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid === 0) {
        return null;
    }
    if (isset($GLOBALS['__lsp_user']) && (int)$GLOBALS['__lsp_user']['id'] === $uid) {
        return $GLOBALS['__lsp_user'];
    }
    $row = q('SELECT id, email, nama, status, must_change_password FROM users WHERE id = ?', [$uid])->fetch();
    if (!$row || $row['status'] !== 'aktif') {
        clear_session();
        return null;
    }
    $GLOBALS['__lsp_user'] = $row;
    return $row;
}

function memberships(int $userId): array
{
    return q("SELECT m.id, m.lsp_id, m.tuk_id, m.role, l.nama AS lsp_nama, t.nama AS tuk_nama
              FROM memberships m
              LEFT JOIN lsp l ON l.id = m.lsp_id
              LEFT JOIN tuk t ON t.id = m.tuk_id
              WHERE m.user_id = ? AND m.status = 'aktif' AND (m.lsp_id IS NULL OR l.status = 'aktif')
              ORDER BY m.id", [$userId])->fetchAll();
}

/** Konteks aktif selalu divalidasi ulang terhadap keanggotaan di DB, tidak pernah dari input klien. */
function active_membership(): ?array
{
    static $cacheKey = null, $cached = null;
    $u = current_user();
    if (!$u) {
        return null;
    }
    $key = $u['id'] . ':' . ($_SESSION['mid'] ?? 0);
    if ($cacheKey === $key) {
        return $cached;
    }
    $ms = memberships((int)$u['id']);
    $found = null;
    foreach ($ms as $m) {
        if ((int)$m['id'] === (int)($_SESSION['mid'] ?? 0)) {
            $found = $m;
        }
    }
    if (!$found && $ms) {
        $found = $ms[0];
        $_SESSION['mid'] = (int)$found['id'];
    }
    $cacheKey = $u['id'] . ':' . ($_SESSION['mid'] ?? 0);
    $cached = $found;
    return $found;
}

function membership_public(array $m): array
{
    return [
        'id' => (int)$m['id'],
        'lsp_id' => $m['lsp_id'] === null ? null : (int)$m['lsp_id'],
        'lsp_nama' => $m['lsp_nama'],
        'tuk_nama' => $m['tuk_nama'],
        'role' => $m['role'],
        'role_nama' => role_label($m['role']),
    ];
}

function me_payload(): array
{
    $out = ['csrf' => $_SESSION['csrf'], 'user' => null];
    if (!empty($_SESSION['expired'])) {
        $out['expired'] = true;
        unset($_SESSION['expired']);
    }
    $u = current_user();
    if (!$u) {
        return $out;
    }
    $act = active_membership();
    $out['user'] = [
        'id' => (int)$u['id'],
        'nama' => $u['nama'],
        'email' => $u['email'],
        'must_change_password' => (int)$u['must_change_password'] === 1,
    ];
    $out['memberships'] = array_map('membership_public', memberships((int)$u['id']));
    $out['active'] = $act ? membership_public($act) : null;
    $out['permissions'] = permissions_for($act);
    return $out;
}

function validate_new_password(string $pw, string $email = ''): void
{
    if (strlen($pw) < 10) {
        fail('Password minimal 10 karakter.', 422);
    }
    if (strlen($pw) > 128) {
        fail('Password maksimal 128 karakter.', 422);
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        fail('Password harus berisi huruf dan angka.', 422);
    }
    if ($email !== '' && stripos($pw, explode('@', $email)[0]) !== false) {
        fail('Password tidak boleh memuat nama email Anda.', 422);
    }
}

function do_login(): void
{
    require_method('POST');
    csrf_check();
    $email = strtolower(str_in('email', 190, 'Email'));
    $pass = body()['password'] ?? '';
    if (!is_string($pass)) {
        $pass = '';
    }
    if ($email === '' || $pass === '') {
        fail('Email dan password wajib diisi.', 422);
    }
    if (strlen($pass) > 128) {
        fail('Email atau password salah.', 401);
    }

    $ip = client_ip();
    $since = date('Y-m-d H:i:s', time() - LOCK_WINDOW_SEC);
    $failEmail = (int)q('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND email = ? AND created_at >= ?', [$email, $since])->fetchColumn();
    $failIp = (int)q('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND ip = ? AND created_at >= ?', [$ip, $since])->fetchColumn();
    if ($failEmail >= MAX_FAIL_PER_EMAIL || $failIp >= MAX_FAIL_PER_IP) {
        audit('login.locked', $email, null, null);
        fail('Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.', 429);
    }

    $u = q('SELECT id, email, password_hash, status FROM users WHERE email = ?', [$email])->fetch();
    // Selalu jalankan password_verify agar waktu respons tidak membocorkan email terdaftar.
    $hash = $u ? $u['password_hash'] : '$2y$10$4wS7doSVkSIhYfrOJsYKYerM0/ZbzDaCp9Ek0se0qd1mgmOZ57VUK';
    $ok = password_verify($pass, $hash) && $u && $u['status'] === 'aktif';
    $hasAccess = $ok && count(memberships((int)$u['id'])) > 0;

    q('INSERT INTO login_attempts (email, ip, success, created_at) VALUES (?, ?, ?, ?)', [$email, $ip, $hasAccess ? 1 : 0, now()]);
    if (random_int(1, 50) === 1) {
        q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
    if (!$ok) {
        audit('login.failed', $email, $u ? (int)$u['id'] : null, null);
        fail('Email atau password salah.', 401);
    }
    if (!$hasAccess) {
        audit('login.no_access', $email, (int)$u['id'], null);
        fail('Akun Anda belum punya akses aktif ke LSP mana pun. Hubungi Admin LSP.', 403);
    }

    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
    }
    // Cegah session fixation: ID sesi baru setelah login.
    session_regenerate_id(true);
    $_SESSION = [
        'uid' => (int)$u['id'],
        'born' => time(),
        'last' => time(),
        'csrf' => bin2hex(random_bytes(32)),
    ];
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
    audit('login.success', $email, (int)$u['id']);
    json_out(me_payload());
}

function do_logout(): void
{
    require_method('POST');
    csrf_check();
    if (current_user()) {
        audit('logout');
    }
    clear_session();
    json_out(me_payload());
}

function do_switch_context(): void
{
    require_method('POST');
    csrf_check();
    $u = require_auth();
    $mid = int_in('membership_id');
    foreach (memberships((int)$u['id']) as $m) {
        if ((int)$m['id'] === $mid) {
            session_regenerate_id(true);
            $_SESSION['mid'] = $mid;
            audit('context.switch', (string)$mid);
            json_out(me_payload());
        }
    }
    audit('access.denied', 'context:' . $mid);
    fail('Konteks tidak ditemukan.', 404);
}

function do_change_password(): void
{
    require_method('POST');
    csrf_check();
    $u = require_auth(true);
    $current = body()['current'] ?? '';
    $new = body()['new'] ?? '';
    if (!is_string($current) || !is_string($new)) {
        fail('Isian tidak valid.', 422);
    }
    $row = q('SELECT password_hash FROM users WHERE id = ?', [$u['id']])->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) {
        audit('password.change_failed');
        fail('Password saat ini salah.', 422);
    }
    if (hash_equals($current, $new)) {
        fail('Password baru harus berbeda dari password lama.', 422);
    }
    validate_new_password($new, $u['email']);
    q('UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = ? WHERE id = ?',
        [password_hash($new, PASSWORD_DEFAULT), now(), $u['id']]);
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    audit('password.changed');
    forget_user_cache();
    json_out(['ok' => true] + me_payload());
}
