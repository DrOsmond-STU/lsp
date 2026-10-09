<?php
declare(strict_types=1);

/*
 * Notifikasi: kotak masuk di aplikasi + antrean kirim ke email dan WhatsApp.
 * Pengiriman keluar dilakukan tools/notify-worker.php (cron), bukan di dalam request pengguna.
 */

const NOTIF_MAX_ATTEMPTS = 5;
const NOTIF_BACKOFF_MIN = [1, 5, 15, 60];
/** Notifikasi keamanan selalu dikirim ke email, apa pun pengaturan pengguna. */
const NOTIF_FORCE_EMAIL = ['akun.password_diubah', 'akun.dibuat'];
const NOTIF_TEST_PER_HOUR = 3;

function cut(string $s, int $n): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n);
}

/** Permintaan HTTP keluar (API WhatsApp / AI). Tidak mengikuti redirect. */
function http_request(string $method, string $url, array $headers, ?string $body, int $timeout = 30): array
{
    if (!function_exists('curl_init')) {
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers),
            'content' => $body ?? '', 'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0]]);
        $resp = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m)) {
                $code = (int)$m[1];
            }
        }
        return [$code, $resp === false ? '' : $resp];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    if ($resp === false) {
        return [0, $err];
    }
    return [$code, (string)$resp];
}

function mask_email(string $email): string
{
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return cut($name, 1) . '***@' . $domain;
}

function mask_phone(string $hp): string
{
    return strlen($hp) > 8 ? substr($hp, 0, 4) . str_repeat('•', strlen($hp) - 8) . substr($hp, -4) : '••••';
}

/* ---------------- Pengaturan per pengguna ---------------- */

function notif_settings(int $uid): array
{
    $row = q('SELECT email_on, wa_on, wa_number, wa_opt_in_at FROM notification_settings WHERE user_id = ?', [$uid])->fetch();
    if ($row) {
        return [
            'email_on' => (int)$row['email_on'] === 1,
            'wa_on' => (int)$row['wa_on'] === 1 && $row['wa_opt_in_at'] !== null && (string)$row['wa_number'] !== '',
            'wa_number' => $row['wa_number'],
        ];
    }
    // Belum pernah diatur: email aktif, WhatsApp mati sampai pengguna menyetujui (opt-in).
    $hp = q('SELECT no_hp FROM asesi_profiles WHERE user_id = ?', [$uid])->fetchColumn();
    return ['email_on' => true, 'wa_on' => false, 'wa_number' => $hp ?: null];
}

function save_notif_settings(int $uid, bool $emailOn, bool $waOn, ?string $waNumber): void
{
    $t = now();
    $old = q('SELECT wa_on, wa_number, wa_opt_in_at FROM notification_settings WHERE user_id = ?', [$uid])->fetch();
    // Waktu persetujuan dicatat ulang bila WA baru diaktifkan atau nomornya berganti.
    $optIn = null;
    if ($waOn) {
        $same = $old && (int)$old['wa_on'] === 1 && $old['wa_number'] === $waNumber && $old['wa_opt_in_at'] !== null;
        $optIn = $same ? $old['wa_opt_in_at'] : $t;
    }
    if ($old) {
        q('UPDATE notification_settings SET email_on = ?, wa_on = ?, wa_number = ?, wa_opt_in_at = ?, updated_at = ? WHERE user_id = ?',
            [$emailOn ? 1 : 0, $waOn ? 1 : 0, $waNumber, $optIn, $t, $uid]);
    } else {
        q('INSERT INTO notification_settings (user_id, email_on, wa_on, wa_number, wa_opt_in_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$uid, $emailOn ? 1 : 0, $waOn ? 1 : 0, $waNumber, $optIn, $t]);
    }
}

/* ---------------- Membuat notifikasi ---------------- */

/** Pengguna aktif yang punya hak akses tertentu di satu LSP (atau di platform bila $lspId null). */
function users_with_perm(string $perm, ?int $lspId): array
{
    $sql = "SELECT DISTINCT m.user_id FROM memberships m
            JOIN role_permissions rp ON rp.role_code = m.role
            JOIN users u ON u.id = m.user_id
            WHERE rp.perm_code = ? AND m.status = 'aktif' AND u.status = 'aktif' AND ";
    $sql .= $lspId === null ? 'm.lsp_id IS NULL' : 'm.lsp_id = ?';
    return array_map('intval', q($sql, $lspId === null ? [$perm] : [$perm, $lspId])->fetchAll(PDO::FETCH_COLUMN));
}

function queue_delivery(int $notifId, string $channel, string $recipient): void
{
    $t = now();
    q("INSERT INTO notification_outbox (notification_id, channel, recipient, status, attempts, next_attempt_at, created_at)
       VALUES (?, ?, ?, 'antri', 0, ?, ?)", [$notifId, $channel, $recipient, $t, $t]);
}

/**
 * Simpan notifikasi untuk tiap penerima dan antrekan email/WA sesuai pengaturan mereka.
 * Kegagalan di sini dicatat saja dan tidak menggagalkan aksi utama.
 */
function notify(array $userIds, ?int $lspId, string $type, string $title, string $body, string $page = ''): void
{
    try {
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            $u = q('SELECT id, email, status FROM users WHERE id = ?', [$uid])->fetch();
            if (!$u || $u['status'] !== 'aktif') {
                continue;
            }
            q('INSERT INTO notifications (user_id, lsp_id, type, title, body, page, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$uid, $lspId, $type, cut($title, 190), cut($body, 1000), $page === '' ? null : $page, now()]);
            $nid = (int)db()->lastInsertId();
            $s = notif_settings($uid);
            if ($s['email_on'] || in_array($type, NOTIF_FORCE_EMAIL, true)) {
                queue_delivery($nid, 'email', $u['email']);
            }
            if ($s['wa_on']) {
                queue_delivery($nid, 'whatsapp', (string)$s['wa_number']);
            }
        }
    } catch (Throwable $e) {
        error_log('[lsp-notify] ' . $e->getMessage());
    }
}

function listing_brief(int $id): ?array
{
    $r = q('SELECT l.id, l.lsp_id, l.tipe, l.judul, s.nama AS lsp_nama FROM listings l JOIN lsp s ON s.id = l.lsp_id WHERE l.id = ?', [$id])->fetch();
    return $r ?: null;
}

function notify_listing_submitted(int $id): void
{
    $l = listing_brief($id);
    if (!$l) {
        return;
    }
    $jenis = $l['tipe'] === 'skema' ? 'Skema' : 'Pelatihan';
    // Notifikasi untuk peninjau platform tidak memakai lsp_id agar tidak muncul di log LSP.
    notify(users_with_perm('listing.review', null), null, 'listing.diajukan', 'Listing baru menunggu persetujuan',
        "$jenis \"{$l['judul']}\" dari {$l['lsp_nama']} menunggu kurasi.", 'approval');
}

function notify_listing_decided(int $id, string $decision, string $note): void
{
    $l = listing_brief($id);
    if (!$l) {
        return;
    }
    $titles = ['tayang' => 'Listing disetujui dan sudah tayang', 'revisi' => 'Listing perlu revisi', 'ditolak' => 'Listing ditolak'];
    $body = "\"{$l['judul']}\" " . ($decision === 'tayang' ? 'sudah tampil di portal publik.' : 'belum bisa ditayangkan.');
    if ($note !== '') {
        $body .= " Catatan peninjau: $note";
    }
    notify(users_with_perm('listing.manage', (int)$l['lsp_id']), (int)$l['lsp_id'], 'listing.' . $decision, $titles[$decision] ?? 'Keputusan listing', $body, 'etalase');
}

/* ---------------- Kanal pengiriman ---------------- */

function wa_driver(): string
{
    global $CONFIG;
    $d = (string)($CONFIG['wa_driver'] ?? '');
    if ($d === 'log' && !empty($CONFIG['wa_log'])) {
        return 'log';
    }
    if ($d === 'fonnte' && !empty($CONFIG['wa_token'])) {
        return 'fonnte';
    }
    if ($d === 'meta' && !empty($CONFIG['wa_token']) && !empty($CONFIG['wa_phone_number_id'])) {
        return 'meta';
    }
    return '';
}

/** Kirim WhatsApp. Mengembalikan [berhasil, pesan galat]. Token tidak pernah ikut dalam pesan galat. */
function wa_send(string $to, string $title, string $body): array
{
    global $CONFIG;
    $driver = wa_driver();
    $text = "*PortalLSP* | $title\n\n$body\n\n" . app_url() . '/';
    if ($driver === 'log') {
        file_put_contents($CONFIG['wa_log'], "To: $to\n$text\n----\n", FILE_APPEND);
        return [true, ''];
    }
    if ($driver === 'fonnte') {
        [$code, $resp] = http_request('POST', (string)($CONFIG['wa_fonnte_url'] ?? 'https://api.fonnte.com/send'),
            ['Authorization: ' . $CONFIG['wa_token']], http_build_query(['target' => $to, 'message' => $text, 'countryCode' => '62']));
        $j = json_decode($resp, true);
        if ($code === 200 && is_array($j) && ($j['status'] ?? false) === true) {
            return [true, ''];
        }
        $why = is_array($j) ? (string)($j['reason'] ?? $j['detail'] ?? '') : '';
        return [false, 'Fonnte: ' . cut($why !== '' ? $why : 'HTTP ' . $code, 200)];
    }
    if ($driver === 'meta') {
        // Pesan yang dimulai bisnis wajib memakai template yang sudah disetujui Meta: {{1}} = judul, {{2}} = isi.
        $param = function (string $s): array {
            return ['type' => 'text', 'text' => cut(trim((string)preg_replace('/\s+/u', ' ', $s)), 900)];
        };
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => '62' . substr($to, 1),
            'type' => 'template',
            'template' => [
                'name' => (string)($CONFIG['wa_meta_template'] ?? 'notifikasi_portallsp'),
                'language' => ['code' => (string)($CONFIG['wa_meta_lang'] ?? 'id')],
                'components' => [['type' => 'body', 'parameters' => [$param($title), $param($body)]]],
            ],
        ];
        $url = rtrim((string)($CONFIG['wa_meta_base'] ?? 'https://graph.facebook.com/v21.0'), '/') . '/' . rawurlencode((string)$CONFIG['wa_phone_number_id']) . '/messages';
        [$code, $resp] = http_request('POST', $url, ['Authorization: Bearer ' . $CONFIG['wa_token'], 'Content-Type: application/json'],
            json_encode($payload, JSON_UNESCAPED_UNICODE));
        if ($code >= 200 && $code < 300) {
            return [true, ''];
        }
        $j = json_decode($resp, true);
        return [false, 'Meta: ' . cut((string)($j['error']['message'] ?? 'HTTP ' . $code), 200)];
    }
    return [false, 'WhatsApp belum dikonfigurasi'];
}

function email_send(string $to, string $type, string $title, string $body): array
{
    $footer = in_array($type, NOTIF_FORCE_EMAIL, true)
        ? 'Email keamanan ini selalu dikirim dan tidak bisa dimatikan.'
        : 'Atur kanal notifikasi di menu Notifikasi pada aplikasi.';
    $ok = send_mail($to, 'PortalLSP: ' . $title, "$title\n\n$body\n\nBuka PortalLSP: " . app_url() . "/\n\n--\n$footer");
    return [$ok, $ok ? '' : 'Server email menolak pesan'];
}

/** Proses antrean (dipanggil worker cron). Mengembalikan ringkasan jumlah per hasil. */
function process_outbox(int $limit = 50): array
{
    $stats = ['terkirim' => 0, 'diulang' => 0, 'gagal' => 0, 'dilewati' => 0];
    $rows = q("SELECT o.id, o.channel, o.recipient, o.attempts, n.type, n.title, n.body
               FROM notification_outbox o JOIN notifications n ON n.id = o.notification_id
               WHERE o.status = 'antri' AND o.next_attempt_at <= ? ORDER BY o.id LIMIT " . max(1, min(200, $limit)), [now()])->fetchAll();
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        if ($r['channel'] === 'whatsapp' && wa_driver() === '') {
            q("UPDATE notification_outbox SET status = 'dilewati', last_error = ? WHERE id = ?", ['WhatsApp belum dikonfigurasi', $id]);
            $stats['dilewati']++;
            continue;
        }
        try {
            [$ok, $err] = $r['channel'] === 'email'
                ? email_send($r['recipient'], $r['type'], $r['title'], $r['body'])
                : wa_send($r['recipient'], $r['title'], $r['body']);
        } catch (Throwable $e) {
            [$ok, $err] = [false, 'Galat: ' . cut($e->getMessage(), 150)];
        }
        $attempts = (int)$r['attempts'] + 1;
        if ($ok) {
            q("UPDATE notification_outbox SET status = 'terkirim', attempts = ?, last_error = NULL, sent_at = ? WHERE id = ?", [$attempts, now(), $id]);
            $stats['terkirim']++;
        } elseif ($attempts >= NOTIF_MAX_ATTEMPTS) {
            q("UPDATE notification_outbox SET status = 'gagal', attempts = ?, last_error = ? WHERE id = ?", [$attempts, cut($err, 255), $id]);
            $stats['gagal']++;
        } else {
            $wait = NOTIF_BACKOFF_MIN[min($attempts - 1, count(NOTIF_BACKOFF_MIN) - 1)] * 60;
            q('UPDATE notification_outbox SET attempts = ?, last_error = ?, next_attempt_at = ? WHERE id = ?',
                [$attempts, cut($err, 255), date('Y-m-d H:i:s', time() + $wait), $id]);
            $stats['diulang']++;
        }
    }
    return $stats;
}

/* ---------------- API ---------------- */

function notif_row(array $r): array
{
    return ['id' => (int)$r['id'], 'type' => $r['type'], 'title' => $r['title'], 'body' => $r['body'], 'page' => $r['page'],
        'lsp_nama' => $r['lsp_nama'], 'read' => $r['read_at'] !== null, 'created_at' => $r['created_at']];
}

function unread_count(int $uid): int
{
    return (int)q('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$uid])->fetchColumn();
}

/** Hanya notifikasi milik pengguna yang sedang masuk. */
function r_notifications(): void
{
    require_method('GET');
    $u = require_auth();
    $rows = q('SELECT n.id, n.type, n.title, n.body, n.page, n.read_at, n.created_at, l.nama AS lsp_nama
               FROM notifications n LEFT JOIN lsp l ON l.id = n.lsp_id
               WHERE n.user_id = ? ORDER BY n.id DESC LIMIT 30', [$u['id']])->fetchAll();
    json_out(['items' => array_map('notif_row', $rows), 'unread' => unread_count((int)$u['id'])]);
}

/** Dipanggil berkala oleh klien; tidak memperpanjang sesi (lihat api.php). */
function r_notif_count(): void
{
    require_method('GET');
    $u = require_auth();
    json_out(['unread' => unread_count((int)$u['id'])]);
}

function r_notif_read(): void
{
    require_method('POST');
    csrf_check();
    $u = require_auth();
    if ((body()['all'] ?? false) === true) {
        q('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [now(), $u['id']]);
    } else {
        q('UPDATE notifications SET read_at = ? WHERE id = ? AND user_id = ? AND read_at IS NULL', [now(), int_in('id'), $u['id']]);
    }
    json_out(['ok' => true, 'unread' => unread_count((int)$u['id'])]);
}

function r_notif_settings(): void
{
    $u = require_auth();
    $uid = (int)$u['id'];
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        require_method('POST');
        csrf_check();
        $emailOn = (body()['email_on'] ?? true) === true;
        $waOn = (body()['wa_on'] ?? false) === true;
        $num = normalize_phone(str_in('wa_number', 20, 'Nomor WhatsApp'));
        if ($num !== '' && !preg_match('/^08\d{8,12}$/', $num)) {
            fail('Nomor WhatsApp harus diawali 08 dan berisi 10–14 digit.', 422);
        }
        if ($waOn && $num === '') {
            fail('Isi nomor WhatsApp untuk mengaktifkan notifikasi WhatsApp.', 422);
        }
        if ($waOn && (body()['wa_consent'] ?? false) !== true) {
            fail('Centang persetujuan menerima notifikasi lewat WhatsApp.', 422);
        }
        save_notif_settings($uid, $emailOn, $waOn, $num === '' ? null : $num);
        audit('notif.settings', 'email:' . ($emailOn ? 1 : 0) . ' wa:' . ($waOn ? 1 : 0));
    }
    $s = notif_settings($uid);
    json_out(['email_on' => $s['email_on'], 'wa_on' => $s['wa_on'], 'wa_number' => $s['wa_number'], 'email' => $u['email'],
        'wa_available' => wa_driver() !== '', 'forced' => ['Password diganti', 'Akun dibuat oleh admin']]);
}

function r_notif_test(): void
{
    require_method('POST');
    csrf_check();
    $u = require_auth();
    $since = date('Y-m-d H:i:s', time() - 3600);
    $n = (int)q("SELECT COUNT(*) FROM audit_logs WHERE action = 'notif.test' AND user_id = ? AND created_at >= ?", [$u['id'], $since])->fetchColumn();
    if ($n >= NOTIF_TEST_PER_HOUR) {
        fail('Uji coba maksimal ' . NOTIF_TEST_PER_HOUR . ' kali per jam.', 429);
    }
    audit('notif.test');
    $s = notif_settings((int)$u['id']);
    notify([(int)$u['id']], null, 'uji.coba', 'Uji coba notifikasi',
        'Ini pesan uji coba dari PortalLSP. Bila Anda menerimanya, kanal notifikasi Anda berfungsi.', 'notif');
    json_out(['ok' => true, 'channels' => array_values(array_filter(['email' => $s['email_on'] ? 'email' : null, 'wa' => $s['wa_on'] ? 'whatsapp' : null]))]);
}

/** Log pengiriman: Admin LSP hanya melihat notifikasi LSP-nya; Admin Platform melihat semuanya. Penerima disamarkan. */
function r_notif_log(): void
{
    require_method('GET');
    $m = require_perm('notif.log');
    $platform = $m['lsp_id'] === null;
    $where = $platform ? '' : 'WHERE n.lsp_id = ?';
    $params = $platform ? [] : [(int)$m['lsp_id']];
    $rows = q("SELECT o.id, o.channel, o.recipient, o.status, o.attempts, o.last_error, o.created_at, o.sent_at, n.type, n.title
               FROM notification_outbox o JOIN notifications n ON n.id = o.notification_id $where
               ORDER BY o.id DESC LIMIT 100", $params)->fetchAll();
    $sum = [];
    foreach (q("SELECT o.status, COUNT(*) AS c FROM notification_outbox o JOIN notifications n ON n.id = o.notification_id $where GROUP BY o.status", $params)->fetchAll() as $r) {
        $sum[$r['status']] = (int)$r['c'];
    }
    json_out([
        'items' => array_map(function ($r) {
            return ['id' => (int)$r['id'], 'channel' => $r['channel'], 'status' => $r['status'], 'attempts' => (int)$r['attempts'],
                'recipient' => $r['channel'] === 'email' ? mask_email($r['recipient']) : mask_phone($r['recipient']),
                'error' => $r['last_error'], 'type' => $r['type'], 'title' => $r['title'], 'created_at' => $r['created_at'], 'sent_at' => $r['sent_at']];
        }, $rows),
        'summary' => $sum,
        'channels' => ['email' => true, 'whatsapp' => wa_driver() !== '', 'wa_driver' => $platform ? wa_driver() : null],
    ]);
}
