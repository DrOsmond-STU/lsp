<?php
declare(strict_types=1);

const LISTING_TYPES = ['skema', 'pelatihan'];
const LISTING_BIDANG = ['TIK', 'Pariwisata', 'Konstruksi', 'Bisnis', 'Kesehatan'];
const LISTING_FORMATS = ['Tatap muka', 'SJJ', 'Tatap muka & SJJ', 'Online', 'Webinar'];
const REVIEW_DECISIONS = ['tayang', 'revisi', 'ditolak'];

function dispatch(string $route): void
{
    $routes = [
        'auth/me' => 'r_me',
        'auth/login' => 'do_login',
        'auth/logout' => 'do_logout',
        'auth/switch' => 'do_switch_context',
        'auth/password' => 'do_change_password',
        'catalog' => 'r_catalog',
        'listings' => 'r_listings',
        'listings/submit' => 'r_listing_submit',
        'listings/withdraw' => 'r_listing_withdraw',
        'reviews' => 'r_reviews',
        'reviews/decide' => 'r_review_decide',
        'users' => 'r_users',
        'users/status' => 'r_user_status',
        'rbac' => 'r_rbac',
        'auth/register' => 'do_register',
        'auth/verify' => 'do_verify_email',
        'auth/resend-verification' => 'do_resend_verification',
        'profile' => 'r_profile',
        'notifications' => 'r_notifications',
        'notifications/count' => 'r_notif_count',
        'notifications/read' => 'r_notif_read',
        'notifications/settings' => 'r_notif_settings',
        'notifications/test' => 'r_notif_test',
        'notifications/log' => 'r_notif_log',
        'ai/chat' => 'r_ai_chat',
        'lsps' => 'r_lsps',
        // Proses sertifikasi
        'dokumen' => 'r_dokumen', 'dokumen/upload' => 'r_dokumen_upload', 'dokumen/file' => 'r_dokumen_file',
        'asesi/apply' => 'r_asesi_apply', 'asesi/permohonan' => 'r_asesi_permohonan', 'asesi/resubmit' => 'r_asesi_resubmit',
        'asesi/cancel' => 'r_asesi_cancel', 'asesi/tagihan' => 'r_asesi_tagihan', 'asesi/bayar' => 'r_asesi_bayar',
        'asesi/sertifikat' => 'r_asesi_sertifikat', 'asesi/kelas' => 'r_asesi_kelas', 'asesi/kelas/daftar' => 'r_asesi_kelas_daftar',
        'asesi/kelas/progres' => 'r_asesi_kelas_progres', 'sertifikat/cetak' => 'r_sertifikat_cetak',
        'asesor/jadwal' => 'r_asesor_jadwal', 'asesor/pra' => 'r_asesor_pra', 'asesor/pra/putus' => 'r_asesor_pra_putus',
        'asesor/asesmen' => 'r_asesor_asesmen', 'asesor/asesmen/simpan' => 'r_asesor_asesmen_simpan', 'asesor/riwayat' => 'r_asesor_riwayat',
        'asesor/honor' => 'r_asesor_honor', 'pleno' => 'r_pleno', 'pleno/putus' => 'r_pleno_putus',
        'chat' => 'r_chat', 'chat/kirim' => 'r_chat_kirim', 'chat/ruang' => 'r_chat_ruang',
        'lsp/dashboard' => 'r_lsp_dashboard', 'lsp/pendaftaran' => 'r_lsp_pendaftaran', 'lsp/pendaftaran/putus' => 'r_lsp_pendaftaran_putus',
        'lsp/asesmen' => 'r_lsp_asesmen', 'lsp/hasil' => 'r_lsp_hasil', 'lsp/jadwal' => 'r_lsp_jadwal', 'lsp/jadwal/asesor' => 'r_lsp_jadwal_asesor',
        'lsp/asesor' => 'r_lsp_asesor', 'lsp/skema' => 'r_lsp_skema', 'lsp/tuk' => 'r_lsp_tuk', 'lsp/opsi' => 'r_lsp_opsi',
        'lsp/alumni' => 'r_lsp_alumni', 'lsp/keuangan' => 'r_lsp_keuangan', 'lsp/keuangan/lunas' => 'r_lsp_keuangan_lunas',
        'lsp/laporan' => 'r_lsp_laporan', 'lsp/crm' => 'r_lsp_crm', 'lsp/crm/tahap' => 'r_lsp_crm_tahap', 'lsp/mutu' => 'r_lsp_mutu',
        'lsp/pengaturan' => 'r_lsp_pengaturan',
        'tuk/dashboard' => 'r_tuk_dashboard', 'tuk/pemohon' => 'r_tuk_pemohon', 'tuk/jadwal' => 'r_tuk_jadwal', 'tuk/sarpras' => 'r_tuk_sarpras',
        'tuk/alumni' => 'r_tuk_alumni',
        'platform/dashboard' => 'r_platform_dashboard', 'platform/lsp' => 'r_platform_lsp', 'platform/lsp/status' => 'r_platform_lsp_status',
        'platform/paket' => 'r_platform_paket', 'skkni' => 'r_skkni', 'tiket' => 'r_tiket', 'tiket/balas' => 'r_tiket_balas', 'audit' => 'r_audit',
        'pub/catalog' => 'r_pub_catalog', 'pub/skema' => 'r_pub_skema', 'pub/jadwal' => 'r_pub_jadwal', 'pub/lsp' => 'r_pub_lsp', 'pub/verify' => 'r_pub_verify',
        // CMS blog
        'blog' => 'r_blog', 'blog/hapus' => 'r_blog_hapus', 'blog/moderasi' => 'r_blog_moderasi', 'blog/cover' => 'r_blog_cover',
        'pub/blog' => 'r_pub_blog', 'pub/blog/post' => 'r_pub_blog_post', 'pub/blog/cover' => 'r_pub_blog_cover',
    ];
    if (!isset($routes[$route])) {
        fail('Alamat API tidak ditemukan.', 404);
    }
    ($routes[$route])();
}

function r_me(): void
{
    require_method('GET');
    json_out(me_payload());
}

function listing_row(array $r, bool $internal): array
{
    $out = [
        'id' => (int)$r['id'],
        'tipe' => $r['tipe'],
        'judul' => $r['judul'],
        'bidang' => $r['bidang'],
        'kota' => $r['kota'],
        'format' => $r['format'],
        'harga' => (int)$r['harga'],
        'deskripsi' => $r['deskripsi'],
        'lsp_nama' => $r['lsp_nama'],
    ];
    if ($internal) {
        $out += ['skema_id' => isset($r['skema_id']) && $r['skema_id'] !== null ? (int)$r['skema_id'] : null, 'status' => $r['status'], 'catatan' => $r['catatan'], 'submitted_at' => $r['submitted_at'], 'reviewed_at' => $r['reviewed_at']];
    }
    return $out;
}

/** Publik: hanya listing berstatus tayang dari LSP aktif. */
function r_catalog(): void
{
    require_method('GET');
    $rows = q("SELECT l.*, s.nama AS lsp_nama FROM listings l JOIN lsp s ON s.id = l.lsp_id
               WHERE l.status = 'tayang' AND s.status = 'aktif' ORDER BY l.reviewed_at DESC LIMIT 200")->fetchAll();
    json_out(['items' => array_map(function ($r) { return listing_row($r, false); }, $rows)]);
}

/**
 * Cakupan LSP untuk endpoint data LSP.
 * Admin Platform: semua LSP (null), atau satu LSP bila memilih filter ?lsp=ID.
 * Peran lain: selalu LSP aktif di sesi; tidak pernah dari input klien.
 */
function scope_lsp(array $m): ?int
{
    if (is_platform($m)) {
        $f = isset($_GET['lsp']) && is_string($_GET['lsp']) && ctype_digit($_GET['lsp']) ? (int)$_GET['lsp'] : 0;
        return $f > 0 ? $f : null;
    }
    if ($m['lsp_id'] === null) {
        fail('Pilih konteks LSP terlebih dahulu.', 403);
    }
    return (int)$m['lsp_id'];
}

/** LSP tujuan saat Admin Platform membuat data atas nama sebuah LSP. */
function target_lsp(): array
{
    $l = q("SELECT id, nama FROM lsp WHERE id = ? AND status = 'aktif'", [int_in('lsp_id')])->fetch();
    if (!$l) {
        fail('Pilih LSP tujuan.', 422);
    }
    return $l;
}

/** Ambil listing dalam cakupan; listing LSP lain diperlakukan seperti tidak ada (404). Admin Platform: semua. */
function own_listing(int $id, array $m): array
{
    $row = is_platform($m)
        ? q('SELECT l.*, s.nama AS lsp_nama FROM listings l JOIN lsp s ON s.id = l.lsp_id WHERE l.id = ?', [$id])->fetch()
        : q('SELECT l.*, s.nama AS lsp_nama FROM listings l JOIN lsp s ON s.id = l.lsp_id WHERE l.id = ? AND l.lsp_id = ?', [$id, (int)$m['lsp_id']])->fetch();
    if (!$row) {
        audit('access.denied', 'listing:' . $id);
        fail('Listing tidak ditemukan.', 404);
    }
    return $row;
}

function r_listings(): void
{
    $m = require_perm('listing.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $scope = scope_lsp($m);
        $rows = q('SELECT l.*, s.nama AS lsp_nama FROM listings l JOIN lsp s ON s.id = l.lsp_id'
            . ($scope === null ? '' : ' WHERE l.lsp_id = ?') . ' ORDER BY l.updated_at DESC, l.id DESC', $scope === null ? [] : [$scope])->fetchAll();
        json_out(['items' => array_map(function ($r) { return listing_row($r, true); }, $rows)]);
    }
    require_method('POST');
    csrf_check();
    $lspId = is_platform($m) ? (int)target_lsp()['id'] : (int)scope_lsp($m);
    $tipe = str_in('tipe', 20);
    $judul = str_in('judul', 150, 'Judul');
    $bidang = str_in('bidang', 40);
    $kota = str_in('kota', 100, 'Kota');
    $format = str_in('format', 40);
    $desc = str_in('deskripsi', 2000, 'Deskripsi');
    $harga = int_in('harga');
    $status = str_in('status', 20);
    if (!in_array($tipe, LISTING_TYPES, true)) fail('Jenis listing tidak valid.', 422);
    if (text_len($judul) < 5) fail('Judul minimal 5 karakter.', 422);
    if (!in_array($bidang, LISTING_BIDANG, true)) fail('Bidang tidak valid.', 422);
    if (!in_array($format, LISTING_FORMATS, true)) fail('Metode/format tidak valid.', 422);
    if ($kota === '') fail('Kota wajib diisi.', 422);
    if ($harga < 0 || $harga > 100000000) fail('Harga tidak valid.', 422);
    if (!in_array($status, ['draf', 'menunggu'], true)) fail('Status awal hanya boleh draf atau diajukan.', 422);
    if ($status === 'menunggu' && text_len($desc) < 20) fail('Deskripsi minimal 20 karakter sebelum diajukan.', 422);
    if ($tipe === 'pelatihan' && $status === 'menunggu' && (body()['ack'] ?? false) !== true) {
        fail('Centang pernyataan bahwa pelatihan bukan syarat wajib uji.', 422);
    }
    // Listing skema bisa ditautkan ke skema master LSP itu agar asesi bisa langsung mendaftar jadwalnya.
    $skemaId = $tipe === 'skema' ? int_in('skema_id') : 0;
    if ($skemaId && !q('SELECT 1 FROM skema WHERE id = ? AND lsp_id = ?', [$skemaId, $lspId])->fetch()) {
        fail('Skema master tidak ditemukan di LSP ini.', 422);
    }
    $t = now();
    q('INSERT INTO listings (lsp_id, tipe, judul, bidang, kota, format, harga, deskripsi, status, created_by, submitted_at, created_at, updated_at, skema_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$lspId, $tipe, $judul, $bidang, $kota, $format, $harga, $desc, $status, (int)current_user()['id'], $status === 'menunggu' ? $t : null, $t, $t, $skemaId ?: null]);
    $id = (int)db()->lastInsertId();
    audit($status === 'menunggu' ? 'listing.submitted' : 'listing.drafted', 'listing:' . $id);
    if ($status === 'menunggu') {
        notify_listing_submitted($id);
    }
    json_out(['id' => $id], 201);
}

function r_listing_submit(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('listing.manage');
    $row = own_listing(int_in('id'), $m);
    if (!in_array($row['status'], ['draf', 'revisi'], true)) fail('Listing ini tidak bisa diajukan pada status sekarang.', 409);
    if (text_len($row['deskripsi']) < 20) fail('Lengkapi deskripsi (minimal 20 karakter) sebelum diajukan.', 422);
    q("UPDATE listings SET status = 'menunggu', catatan = NULL, submitted_at = ?, updated_at = ? WHERE id = ? AND lsp_id = ?",
        [now(), now(), $row['id'], $row['lsp_id']]);
    audit('listing.submitted', 'listing:' . $row['id']);
    notify_listing_submitted((int)$row['id']);
    json_out(['ok' => true]);
}

function r_listing_withdraw(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('listing.manage');
    $row = own_listing(int_in('id'), $m);
    if ($row['status'] !== 'menunggu') fail('Hanya pengajuan yang sedang menunggu yang bisa ditarik.', 409);
    q("UPDATE listings SET status = 'draf', updated_at = ? WHERE id = ? AND lsp_id = ?", [now(), $row['id'], $row['lsp_id']]);
    audit('listing.withdrawn', 'listing:' . $row['id']);
    json_out(['ok' => true]);
}

function r_reviews(): void
{
    require_method('GET');
    require_perm('listing.review');
    $rows = q("SELECT l.*, s.nama AS lsp_nama FROM listings l JOIN lsp s ON s.id = l.lsp_id WHERE l.status = 'menunggu' ORDER BY l.submitted_at, l.id")->fetchAll();
    $month = date('Y-m-01 00:00:00');
    $stats = [
        'approved_month' => (int)q("SELECT COUNT(*) FROM listings WHERE status = 'tayang' AND reviewed_at >= ?", [$month])->fetchColumn(),
        'rejected_month' => (int)q("SELECT COUNT(*) FROM listings WHERE status IN ('revisi','ditolak') AND reviewed_at >= ?", [$month])->fetchColumn(),
    ];
    json_out(['items' => array_map(function ($r) { return listing_row($r, true); }, $rows), 'stats' => $stats]);
}

function r_review_decide(): void
{
    require_method('POST');
    csrf_check();
    require_perm('listing.review');
    $id = int_in('id');
    $decision = str_in('decision', 20);
    $note = str_in('note', 500, 'Catatan');
    if (!in_array($decision, REVIEW_DECISIONS, true)) fail('Keputusan tidak valid.', 422);
    if ($decision !== 'tayang' && text_len($note) < 5) fail('Tulis catatan untuk LSP (minimal 5 karakter).', 422);
    $row = q('SELECT id, status FROM listings WHERE id = ?', [$id])->fetch();
    if (!$row) fail('Listing tidak ditemukan.', 404);
    if ($row['status'] !== 'menunggu') fail('Listing ini sudah diputuskan oleh peninjau lain.', 409);
    // Kondisi status di WHERE mencegah dua peninjau memutuskan bersamaan.
    $st = q("UPDATE listings SET status = ?, catatan = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE id = ? AND status = 'menunggu'",
        [$decision, $note === '' ? null : $note, (int)current_user()['id'], now(), now(), $id]);
    if ($st->rowCount() !== 1) fail('Listing ini sudah diputuskan oleh peninjau lain.', 409);
    audit('listing.' . $decision, 'listing:' . $id);
    notify_listing_decided($id, $decision, $note);
    json_out(['ok' => true]);
}

function r_users(): void
{
    $m = require_perm('user.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Admin Platform: semua keanggotaan di semua LSP (termasuk akun platform & asesi pribadi) bila tanpa filter.
        $scope = scope_lsp($m);
        $rows = q('SELECT m.id, m.role, m.status, u.nama, u.email, u.last_login_at, t.nama AS tuk_nama, l.nama AS lsp_nama
                   FROM memberships m JOIN users u ON u.id = m.user_id LEFT JOIN tuk t ON t.id = m.tuk_id LEFT JOIN lsp l ON l.id = m.lsp_id'
                   . ($scope === null ? '' : ' WHERE m.lsp_id = ?') . ' ORDER BY l.nama, m.status, m.role, u.nama', $scope === null ? [] : [$scope])->fetchAll();
        $items = array_map(function ($r) use ($m) {
            return ['membership_id' => (int)$r['id'], 'nama' => $r['nama'], 'email' => $r['email'], 'role' => $r['role'],
                'role_nama' => role_label($r['role']), 'status' => $r['status'], 'tuk_nama' => $r['tuk_nama'], 'lsp_nama' => $r['lsp_nama'],
                'last_login_at' => $r['last_login_at'], 'is_me' => (int)$r['id'] === (int)$m['id']];
        }, $rows);
        $roles = array_map(function ($c) { return ['code' => $c, 'nama' => role_label($c)]; }, LSP_ASSIGNABLE_ROLES);
        json_out(['items' => $items, 'assignable_roles' => $roles]);
    }
    require_method('POST');
    csrf_check();
    $target = is_platform($m) ? target_lsp() : ['id' => (int)scope_lsp($m), 'nama' => $m['lsp_nama']];
    $lspId = (int)$target['id'];
    $nama = str_in('nama', 120, 'Nama');
    $email = strtolower(str_in('email', 190, 'Email'));
    $role = str_in('role', 40);
    $pw = body()['password'] ?? '';
    if (!is_string($pw)) $pw = '';
    if (text_len($nama) < 3) fail('Nama minimal 3 karakter.', 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.', 422);
    if (!in_array($role, LSP_ASSIGNABLE_ROLES, true)) fail('Peran ini tidak boleh diberikan oleh Admin LSP.', 403);
    validate_new_password($pw, $email);
    if (q('SELECT id FROM users WHERE email = ?', [$email])->fetch()) {
        // Peran internal LSP hanya boleh satu keanggotaan; pesan umum agar tidak membocorkan data LSP lain.
        fail('Email ini sudah terdaftar. Gunakan email lain untuk staf LSP.', 409);
    }
    $t = now();
    db()->beginTransaction();
    try {
        q('INSERT INTO users (email, nama, password_hash, status, must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)',
            [$email, $nama, password_hash($pw, PASSWORD_DEFAULT), 'aktif', $t, $t]);
        $uid = (int)db()->lastInsertId();
        q('INSERT INTO memberships (user_id, lsp_id, role, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$uid, $lspId, $role, 'aktif', (int)current_user()['id'], $t]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    audit('user.created', $email . ' as ' . $role);
    // Password sementara tidak pernah dikirim lewat email/WA; diserahkan langsung oleh admin.
    notify([$uid], $lspId, 'akun.dibuat', 'Akun PortalLSP Anda sudah dibuat',
        'Anda ditambahkan sebagai ' . role_label($role) . ' di ' . $target['nama'] . '. Minta password sementara kepada Admin LSP Anda, lalu ganti saat pertama masuk.');
    json_out(['ok' => true], 201);
}

function r_user_status(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('user.manage');
    $mid = int_in('membership_id');
    $status = str_in('status', 20);
    if (!in_array($status, ['aktif', 'nonaktif'], true)) fail('Status tidak valid.', 422);
    $row = is_platform($m)
        ? q('SELECT m.id, m.user_id, m.role, m.status, m.lsp_id, l.nama AS lsp_nama FROM memberships m LEFT JOIN lsp l ON l.id = m.lsp_id WHERE m.id = ?', [$mid])->fetch()
        : q('SELECT m.id, m.user_id, m.role, m.status, m.lsp_id, l.nama AS lsp_nama FROM memberships m LEFT JOIN lsp l ON l.id = m.lsp_id WHERE m.id = ? AND m.lsp_id = ?', [$mid, (int)scope_lsp($m)])->fetch();
    if (!$row) {
        audit('access.denied', 'membership:' . $mid);
        fail('Pengguna tidak ditemukan.', 404);
    }
    if ((int)$row['id'] === (int)$m['id']) fail('Anda tidak bisa menonaktifkan akses Anda sendiri.', 409);
    if ($status === 'nonaktif' && $row['role'] === 'admin_lsp') {
        $admins = (int)q("SELECT COUNT(*) FROM memberships WHERE lsp_id = ? AND role = 'admin_lsp' AND status = 'aktif'", [$row['lsp_id']])->fetchColumn();
        if ($admins <= 1) fail('LSP harus punya minimal satu Admin LSP aktif.', 409);
    }
    if ($status === 'nonaktif' && $row['role'] === 'platform_admin') {
        $admins = (int)q("SELECT COUNT(*) FROM memberships WHERE role = 'platform_admin' AND status = 'aktif'")->fetchColumn();
        if ($admins <= 1) fail('Platform harus punya minimal satu Admin Platform aktif.', 409);
    }
    // Syarat "minimal satu admin aktif" diperiksa lagi di dalam UPDATE yang sama agar dua admin yang saling menonaktifkan bersamaan tidak lolos.
    $guard = '';
    $gp = [];
    if ($status === 'nonaktif' && $row['role'] === 'admin_lsp') {
        $guard = " AND (SELECT n FROM (SELECT COUNT(*) AS n FROM memberships WHERE lsp_id = ? AND role = 'admin_lsp' AND status = 'aktif') x) > 1";
        $gp = [(int)$row['lsp_id']];
    } elseif ($status === 'nonaktif' && $row['role'] === 'platform_admin') {
        $guard = " AND (SELECT n FROM (SELECT COUNT(*) AS n FROM memberships WHERE role = 'platform_admin' AND status = 'aktif') x) > 1";
    }
    $st = q('UPDATE memberships SET status = ? WHERE id = ?' . $guard, array_merge([$status, $mid], $gp));
    if ($guard !== '' && $st->rowCount() === 0 && $row['status'] !== $status) {
        fail('Harus tetap ada minimal satu admin aktif.', 409);
    }
    audit('membership.' . $status, 'membership:' . $mid);
    if ($row['status'] !== $status) {
        notify([(int)$row['user_id']], $row['lsp_id'] === null ? null : (int)$row['lsp_id'], 'akun.akses_' . $status,
            $status === 'aktif' ? 'Akses Anda diaktifkan' : 'Akses Anda dinonaktifkan',
            'Akses ' . role_label($row['role']) . ' Anda di ' . ($row['lsp_nama'] ?? 'PortalLSP') . ($status === 'aktif' ? ' sudah aktif kembali.' : ' dinonaktifkan oleh admin. Hubungi admin bila ini keliru.'));
    }
    json_out(['ok' => true]);
}

function r_rbac(): void
{
    require_method('GET');
    $m = require_perm('rbac.view');
    $roles = q('SELECT code, nama, scope, multi_lsp FROM roles ORDER BY scope, code')->fetchAll();
    if ($m['role'] !== 'platform_admin') {
        $roles = array_values(array_filter($roles, function ($r) { return $r['scope'] !== 'platform'; }));
    }
    $perms = q('SELECT code, deskripsi FROM permissions ORDER BY code')->fetchAll();
    $map = [];
    foreach (q('SELECT role_code, perm_code FROM role_permissions')->fetchAll() as $rp) {
        $map[$rp['role_code']][] = $rp['perm_code'];
    }
    json_out(['roles' => array_map(function ($r) use ($map) {
        return ['code' => $r['code'], 'nama' => $r['nama'], 'scope' => $r['scope'], 'multi_lsp' => (int)$r['multi_lsp'] === 1, 'permissions' => $map[$r['code']] ?? []];
    }, $roles), 'permissions' => $perms]);
}

/** Daftar LSP beserta ringkasan angka (Admin Platform). */
function r_lsps(): void
{
    require_method('GET');
    require_perm('lsp.manage');
    $rows = q("SELECT l.id, l.nama, l.jenis, l.kota, l.status, l.created_at,
                 (SELECT COUNT(*) FROM memberships m WHERE m.lsp_id = l.id AND m.status = 'aktif') AS pengguna,
                 (SELECT COUNT(*) FROM memberships m WHERE m.lsp_id = l.id AND m.status = 'aktif' AND m.role = 'asesor') AS asesor,
                 (SELECT COUNT(*) FROM memberships m WHERE m.lsp_id = l.id AND m.status = 'aktif' AND m.role = 'asesi') AS asesi,
                 (SELECT COUNT(*) FROM tuk t WHERE t.lsp_id = l.id) AS tuk,
                 (SELECT COUNT(*) FROM listings s WHERE s.lsp_id = l.id) AS listing,
                 (SELECT COUNT(*) FROM listings s WHERE s.lsp_id = l.id AND s.status = 'tayang') AS tayang,
                 (SELECT COUNT(*) FROM listings s WHERE s.lsp_id = l.id AND s.status = 'menunggu') AS menunggu
               FROM lsp l ORDER BY l.nama")->fetchAll();
    json_out(['items' => array_map(function ($r) {
        $out = ['id' => (int)$r['id'], 'nama' => $r['nama'], 'jenis' => $r['jenis'], 'kota' => $r['kota'], 'status' => $r['status'], 'created_at' => $r['created_at']];
        foreach (['pengguna', 'asesor', 'asesi', 'tuk', 'listing', 'tayang', 'menunggu'] as $k) {
            $out[$k] = (int)$r[$k];
        }
        return $out;
    }, $rows)]);
}
