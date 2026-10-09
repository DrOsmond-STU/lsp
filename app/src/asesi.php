<?php
declare(strict_types=1);

/* Pendaftaran asesi mandiri, profil, enkripsi data pribadi, dan verifikasi email. */

const MIN_AGE = 15;
const MAX_REGISTER_PER_IP_HOUR = 5;
const VERIFY_TTL_SEC = 172800; // 48 jam

/** Kunci aplikasi dibuat sekali di storage/ (di luar docroot). Tanpa kunci ini NIK tidak bisa dibaca. */
function app_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $file = APP_ROOT . '/storage/app.key';
    if (!is_file($file)) {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $fh = fopen($file, 'x');
        if ($fh !== false) {
            fwrite($fh, base64_encode(random_bytes(32)));
            fclose($fh);
            chmod($file, 0600);
        }
    }
    $key = base64_decode(trim((string)file_get_contents($file)), true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('Kunci aplikasi tidak valid.');
    }
    return $key;
}

function encrypt_text(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Enkripsi gagal.');
    }
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_text(string $enc): string
{
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 29) {
        throw new RuntimeException('Data terenkripsi rusak.');
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    if ($plain === false) {
        throw new RuntimeException('Dekripsi gagal.');
    }
    return $plain;
}

/** Indeks buta (HMAC) untuk mencari NIK ganda tanpa menyimpan NIK apa adanya. */
function nik_index(string $nik): string
{
    return hash_hmac('sha256', $nik, hash_hmac('sha256', 'nik-index-v1', app_key(), true));
}

/** Validasi struktur NIK: 16 digit, kode provinsi 11–94, tanggal lahir (perempuan +40), bulan 01–12. */
function valid_nik(string $nik): bool
{
    if (!preg_match('/^\d{16}$/', $nik)) {
        return false;
    }
    $prov = (int)substr($nik, 0, 2);
    $day = (int)substr($nik, 6, 2);
    $month = (int)substr($nik, 8, 2);
    if ($prov < 11 || $prov > 94) {
        return false;
    }
    if ($day > 40) {
        $day -= 40;
    }
    return $day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && substr($nik, 12) !== '0000';
}

function mask_nik(string $nik): string
{
    return substr($nik, 0, 4) . str_repeat('•', 8) . substr($nik, 12);
}

function normalize_phone(string $hp): string
{
    $hp = preg_replace('/[\s\-().]/', '', $hp);
    if (strpos($hp, '+62') === 0) {
        $hp = '0' . substr($hp, 3);
    } elseif (strpos($hp, '62') === 0) {
        $hp = '0' . substr($hp, 2);
    }
    return $hp;
}

/** Alamat aplikasi dari config, bukan dari header Host (mencegah host header injection pada tautan email). */
function app_url(): string
{
    global $CONFIG;
    return rtrim((string)($CONFIG['app_url'] ?? 'https://lsp.semestateknologiutama.com'), '/');
}

function send_mail(string $to, string $subject, string $body): bool
{
    global $CONFIG;
    if (!empty($CONFIG['mail_log'])) {
        file_put_contents($CONFIG['mail_log'], "To: $to\nSubject: $subject\n\n$body\n----\n", FILE_APPEND);
        return true;
    }
    $from = (string)($CONFIG['mail_from'] ?? 'no-reply@lsp.semestateknologiutama.com');
    $headers = "From: PortalLSP <$from>\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Mailer: PortalLSP";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . $from);
}

function issue_verification(int $userId, string $email, string $nama): void
{
    $token = bin2hex(random_bytes(32));
    q('UPDATE email_verifications SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $userId]);
    q('INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
        [$userId, hash('sha256', $token), date('Y-m-d H:i:s', time() + VERIFY_TTL_SEC), now()]);
    $link = app_url() . '/?verifikasi=' . $token;
    $ok = send_mail($email, 'Verifikasi email akun PortalLSP',
        "Halo $nama,\n\nTerima kasih telah mendaftar sebagai asesi di PortalLSP.\n" .
        "Buka tautan berikut untuk memverifikasi email Anda (berlaku 48 jam):\n\n$link\n\n" .
        "Abaikan email ini bila Anda tidak merasa mendaftar.\n\nPortalLSP");
    audit($ok ? 'email.verification_sent' : 'email.verification_failed', 'user:' . $userId, $userId, null);
}

function do_register(): void
{
    require_method('POST');
    csrf_check();
    if (current_user()) {
        fail('Anda sudah masuk. Keluar dulu untuk membuat akun baru.', 409);
    }
    $ip = client_ip();
    $hour = date('Y-m-d H:i:s', time() - 3600);
    $recent = (int)q("SELECT COUNT(*) FROM audit_logs WHERE action = 'register.success' AND ip = ? AND created_at >= ?", [$ip, $hour])->fetchColumn();
    if ($recent >= MAX_REGISTER_PER_IP_HOUR) {
        fail('Terlalu banyak pendaftaran dari jaringan ini. Coba lagi dalam satu jam.', 429);
    }
    // Honeypot: kolom tersembunyi yang hanya diisi bot.
    if (str_in('website', 200) !== '') {
        audit('register.bot', null, null, null);
        fail('Pendaftaran tidak dapat diproses.', 400);
    }

    $nama = str_in('nama', 120, 'Nama');
    $nik = preg_replace('/\s+/', '', str_in('nik', 32, 'NIK'));
    $tgl = str_in('tanggal_lahir', 10, 'Tanggal lahir');
    $jk = str_in('jenis_kelamin', 1);
    $email = strtolower(str_in('email', 190, 'Email'));
    $hp = normalize_phone(str_in('no_hp', 20, 'Nomor HP'));
    $pw = body()['password'] ?? '';
    if (!is_string($pw)) {
        $pw = '';
    }
    if (text_len($nama) < 3 || !preg_match("/^[\\p{L} .,'-]+$/u", $nama)) fail('Nama lengkap minimal 3 huruf dan hanya berisi huruf.', 422);
    if (!valid_nik($nik)) fail('NIK harus 16 digit sesuai KTP.', 422);
    $d = DateTime::createFromFormat('!Y-m-d', $tgl);
    if (!$d || $d->format('Y-m-d') !== $tgl) fail('Tanggal lahir tidak valid.', 422);
    $age = (int)$d->diff(new DateTime('today'))->y;
    if ($d > new DateTime('today') || $age < MIN_AGE || $age > 100) fail('Usia minimal ' . MIN_AGE . ' tahun untuk mendaftar.', 422);
    if (!in_array($jk, ['L', 'P'], true)) fail('Pilih jenis kelamin.', 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.', 422);
    if (!preg_match('/^08\d{8,12}$/', $hp)) fail('Nomor HP harus diawali 08 dan berisi 10–14 digit.', 422);
    validate_new_password($pw, $email);
    if ((body()['consent_privacy'] ?? false) !== true) fail('Setujui syarat dan kebijakan privasi untuk mendaftar.', 422);
    $marketing = (body()['consent_marketing'] ?? false) === true ? 1 : 0;

    if (q('SELECT id FROM users WHERE email = ?', [$email])->fetch()) {
        fail('Email ini sudah terdaftar. Silakan masuk, atau hubungi admin bila lupa password.', 409);
    }
    $nikHash = nik_index($nik);
    if (q('SELECT user_id FROM asesi_profiles WHERE nik_hash = ?', [$nikHash])->fetch()) {
        audit('register.nik_conflict', null, null, null);
        fail('NIK ini sudah terdaftar pada akun lain. Hubungi admin bila NIK ini milik Anda.', 409);
    }

    $t = now();
    db()->beginTransaction();
    try {
        q('INSERT INTO users (email, nama, password_hash, status, must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, 0, ?, ?)',
            [$email, $nama, password_hash($pw, PASSWORD_DEFAULT), 'aktif', $t, $t]);
        $uid = (int)db()->lastInsertId();
        q('INSERT INTO asesi_profiles (user_id, nik_enc, nik_hash, tanggal_lahir, jenis_kelamin, no_hp, consent_privacy_at, consent_marketing, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$uid, encrypt_text($nik), $nikHash, $tgl, $jk, $hp, $t, $marketing, $t, $t]);
        // Keanggotaan asesi pribadi (belum terikat LSP). Keanggotaan per LSP dibuat saat asesi mendaftar skema.
        q('INSERT INTO memberships (user_id, lsp_id, role, status, created_at) VALUES (?, NULL, ?, ?, ?)', [$uid, 'asesi', 'aktif', $t]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        if ($e instanceof PDOException && stripos($e->getMessage(), 'unique') !== false) {
            fail('Email atau NIK sudah terdaftar.', 409);
        }
        throw $e;
    }
    audit('register.success', 'user:' . $uid, $uid, null);
    issue_verification($uid, $email, $nama);

    session_regenerate_id(true);
    forget_user_cache();
    $_SESSION = ['uid' => $uid, 'born' => time(), 'last' => time(), 'csrf' => bin2hex(random_bytes(32))];
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [$t, $uid]);
    json_out(me_payload(), 201);
}

function do_verify_email(): void
{
    require_method('POST');
    csrf_check();
    $token = str_in('token', 64);
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        fail('Tautan verifikasi tidak valid.', 422);
    }
    $row = q('SELECT id, user_id, expires_at, used_at FROM email_verifications WHERE token_hash = ?', [hash('sha256', $token)])->fetch();
    if (!$row || $row['used_at'] !== null) {
        fail('Tautan verifikasi tidak valid atau sudah dipakai.', 422);
    }
    if ($row['expires_at'] < now()) {
        fail('Tautan verifikasi sudah kedaluwarsa. Masuk lalu kirim ulang email verifikasi.', 422);
    }
    q('UPDATE email_verifications SET used_at = ? WHERE id = ?', [now(), $row['id']]);
    q('UPDATE users SET email_verified_at = ?, updated_at = ? WHERE id = ? AND email_verified_at IS NULL', [now(), now(), $row['user_id']]);
    audit('email.verified', 'user:' . $row['user_id'], (int)$row['user_id'], null);
    forget_user_cache();
    json_out(['ok' => true] + me_payload());
}

function do_resend_verification(): void
{
    require_method('POST');
    csrf_check();
    $u = require_auth();
    if (!empty($u['email_verified_at'])) {
        fail('Email Anda sudah terverifikasi.', 409);
    }
    $last = q('SELECT MAX(created_at) FROM email_verifications WHERE user_id = ?', [$u['id']])->fetchColumn();
    if ($last && strtotime((string)$last) > time() - 300) {
        fail('Tunggu 5 menit sebelum mengirim ulang email verifikasi.', 429);
    }
    issue_verification((int)$u['id'], $u['email'], $u['nama']);
    json_out(['ok' => true]);
}

/** Profil milik pengguna yang sedang masuk saja; NIK selalu disamarkan. */
function r_profile(): void
{
    require_method('GET');
    $m = require_perm('profile.own');
    $u = current_user();
    $p = q('SELECT nik_enc, tanggal_lahir, jenis_kelamin, no_hp, consent_marketing FROM asesi_profiles WHERE user_id = ?', [$u['id']])->fetch();
    $lspCount = (int)q("SELECT COUNT(*) FROM memberships WHERE user_id = ? AND role = 'asesi' AND lsp_id IS NOT NULL AND status = 'aktif'", [$u['id']])->fetchColumn();
    json_out([
        'nama' => $u['nama'],
        'email' => $u['email'],
        'email_verified' => !empty($u['email_verified_at']),
        'nik' => $p ? mask_nik(decrypt_text($p['nik_enc'])) : null,
        'tanggal_lahir' => $p['tanggal_lahir'] ?? null,
        'jenis_kelamin' => $p['jenis_kelamin'] ?? null,
        'no_hp' => $p['no_hp'] ?? null,
        'consent_marketing' => $p ? (int)$p['consent_marketing'] === 1 : false,
        'lsp_count' => $lspCount,
        'role' => $m['role'],
    ]);
}
