<?php
declare(strict_types=1);

/* Endpoint staf LSP, TUK, Admin Platform, dan portal publik. Semua kueri LSP memakai scope_lsp(). */

const JADWAL_METODE = ['Tatap muka', 'SJJ'];
const CRM_TAHAP = ['baru', 'dihubungi', 'proposal', 'menang', 'kalah'];
const MUTU_JENIS = ['Audit internal', 'Ketidaksesuaian', 'Tindakan perbaikan (CAPA)', 'Kaji ulang manajemen', 'Analisis ketidakberpihakan', 'Banding & keluhan'];
const PAKET = ['Basic' => [1500000, 1000], 'Pro' => [4000000, 5000], 'Enterprise' => [10000000, 50000]];

/** Klausa WHERE untuk kolom lsp_id sesuai cakupan; mengembalikan [sql, params]. */
function scope_where(array $m, string $col, bool $and = false): array
{
    $s = scope_lsp($m);
    if ($s === null) {
        return ['', []];
    }
    return [($and ? ' AND ' : ' WHERE ') . "$col = ?", [$s]];
}

function date_in(string $key, string $label, bool $required = true): ?string
{
    $v = str_in($key, 10, $label);
    if ($v === '' && !$required) {
        return null;
    }
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        fail("$label tidak valid.", 422);
    }
    return $v;
}

/** LSP tujuan untuk data baru: LSP aktif di sesi; Admin Platform wajib memilih (lsp_id) atau memakai filter. */
function write_lsp(array $m): int
{
    if (is_platform($m)) {
        $id = int_in('lsp_id') ?: (int)(scope_lsp($m) ?? 0);
        if (!q('SELECT 1 FROM lsp WHERE id = ?', [$id])->fetch()) {
            fail('Pilih LSP tujuan.', 422);
        }
        return $id;
    }
    return (int)scope_lsp($m);
}

/** Baris milik LSP dalam cakupan (404 bila di luar). */
function scoped_row(string $table, int $id, array $m): array
{
    $s = scope_lsp($m);
    $r = q("SELECT * FROM $table WHERE id = ?" . ($s === null ? '' : ' AND lsp_id = ?'), $s === null ? [$id] : [$id, $s])->fetch();
    if (!$r) {
        audit('access.denied', "$table:$id");
        fail('Data tidak ditemukan.', 404);
    }
    return $r;
}

/* =========================== Dashboard LSP =========================== */

function r_lsp_dashboard(): void
{
    require_method('GET');
    $m = require_perm('lsp.dashboard');
    [$w, $p] = scope_where($m, 'lsp_id', true);
    $cnt = function (string $cond, array $params = []) use ($w, $p): int {
        return (int)q("SELECT COUNT(*) FROM permohonan WHERE $cond$w", array_merge($params, $p))->fetchColumn();
    };
    $bulan = date('Y-m-01 00:00:00');
    $funnel = [
        ['Daftar', $cnt('diajukan_at >= ?', [$bulan])],
        ['Bayar', $cnt('dibayar_at >= ?', [$bulan])],
        ['Diuji', $cnt('diuji_at >= ?', [$bulan])],
        ['Kompeten', $cnt("keputusan = 'K' AND diputuskan_at >= ?", [$bulan])],
    ];
    [$w2, $p2] = scope_where($m, 'p.lsp_id', true);
    $verif = q(permohonan_sql() . " WHERE p.status IN ('diajukan')$w2 ORDER BY p.diajukan_at LIMIT 6", $p2)->fetchAll();
    [$w3, $p3] = scope_where($m, 'j.lsp_id', true);
    $jadwal = q('SELECT j.*, s.nama AS skema_nama, ls.nama AS lsp_nama, t.nama AS tuk_nama, a.nama AS asesor_nama FROM jadwal j JOIN skema s ON s.id = j.skema_id
                 JOIN lsp ls ON ls.id = j.lsp_id LEFT JOIN tuk t ON t.id = j.tuk_id LEFT JOIN users a ON a.id = j.asesor_id
                 WHERE j.tanggal >= ? AND j.tanggal <= ? AND j.status <> ?' . $w3 . ' ORDER BY j.tanggal LIMIT 8', array_merge([today(), date('Y-m-d', strtotime('+30 days')), 'batal'], $p3))->fetchAll();
    [$w4, $p4] = scope_where($m, 'lsp_id', true);
    $habis = (int)q("SELECT COUNT(*) FROM sertifikat WHERE status = 'aktif' AND berlaku <= ?$w4", array_merge([date('Y-m-d', strtotime('+90 days'))], $p4))->fetchColumn();
    $pendapatan = (int)q("SELECT COALESCE(SUM(jumlah),0) FROM tagihan WHERE status = 'lunas' AND dibayar_at >= ?$w4", array_merge([$bulan], $p4))->fetchColumn();
    $tanpaAsesor = (int)q("SELECT COUNT(*) FROM jadwal WHERE asesor_id IS NULL AND status = 'dibuka' AND tanggal >= ?$w4", array_merge([today()], $p4))->fetchColumn();
    json_out([
        'kpi' => ['pendaftar_bulan' => $cnt('diajukan_at >= ?', [$bulan]), 'perlu_verifikasi' => $cnt("status = 'diajukan'"),
            'berjalan' => $cnt("status IN ('pra_asesmen','siap_uji')"), 'menunggu_pleno' => $cnt("status = 'menunggu_pleno'"),
            'sertifikat_habis' => $habis, 'pendapatan_bulan' => $pendapatan, 'jadwal_tanpa_asesor' => $tanpaAsesor],
        'funnel' => $funnel,
        'verifikasi' => array_map('permohonan_row', $verif),
        'jadwal' => array_map('jadwal_row', $jadwal),
    ]);
}

/* =========================== Pendaftaran & asesmen =========================== */

function r_lsp_pendaftaran(): void
{
    require_method('GET');
    $m = require_perm('registration.verify');
    [$w, $p] = scope_where($m, 'p.lsp_id', true);
    $st = isset($_GET['status']) && is_string($_GET['status']) && isset(P_STATUS[$_GET['status']]) ? $_GET['status'] : '';
    $rows = q(permohonan_sql() . ' WHERE 1=1' . ($st ? ' AND p.status = ?' : '') . $w . ' ORDER BY p.id DESC LIMIT 300', array_merge($st ? [$st] : [], $p))->fetchAll();
    json_out(['items' => array_map(function ($r) { return permohonan_row($r, true); }, $rows)]);
}

function r_lsp_pendaftaran_putus(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('registration.verify');
    $p = lsp_permohonan(int_in('id'), $m);
    $aksi = str_in('aksi', 20);
    $cat = str_in('catatan', 500, 'Catatan');
    if ($p['status'] !== 'diajukan') {
        fail('Permohonan ini sudah diproses.', 409);
    }
    if ($aksi !== 'terima' && text_len($cat) < 5) {
        fail('Tulis catatan untuk asesi (minimal 5 karakter).', 422);
    }
    if ($aksi === 'terima') {
        db()->beginTransaction();
        try {
            set_status((int)$p['id'], 'diajukan', 'menunggu_bayar', ['diverifikasi_at' => now(), 'catatan_admin' => $cat ?: null]);
            $tid = new_invoice((int)$p['lsp_id'], (int)$p['id'], null, (int)$p['asesi_id'], 'Uji kompetensi ' . $p['skema_nama'], (int)$p['harga']);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
        notify([(int)$p['asesi_id']], (int)$p['lsp_id'], 'permohonan.diterima', 'Berkas lengkap, silakan bayar',
            'Berkas ' . $p['skema_nama'] . ' sudah diverifikasi. Bayar Rp' . number_format((int)$p['harga'], 0, ',', '.') . ' dalam 7 hari.', 'bayar');
    } elseif ($aksi === 'perbaikan') {
        set_status((int)$p['id'], 'diajukan', 'perbaikan', ['catatan_admin' => $cat]);
        notify([(int)$p['asesi_id']], (int)$p['lsp_id'], 'permohonan.perbaikan', 'Berkas perlu perbaikan', "Skema {$p['skema_nama']}: $cat", 'jadwal');
    } elseif ($aksi === 'tolak') {
        set_status((int)$p['id'], 'diajukan', 'ditolak', ['catatan_admin' => $cat]);
        notify([(int)$p['asesi_id']], (int)$p['lsp_id'], 'permohonan.ditolak', 'Pendaftaran ditolak', "Skema {$p['skema_nama']}: $cat", 'jadwal');
    } else {
        fail('Aksi tidak valid.', 422);
    }
    audit('permohonan.' . $aksi, 'permohonan:' . $p['id']);
    json_out(['ok' => true]);
}

function r_lsp_asesmen(): void
{
    require_method('GET');
    $m = require_perm('assessment.monitor');
    [$w, $p] = scope_where($m, 'p.lsp_id', true);
    $rows = q(permohonan_sql() . " WHERE p.status IN ('pra_asesmen','siap_uji','menunggu_pleno','tidak_lanjut')$w ORDER BY j.tanggal, p.id", $p)->fetchAll();
    json_out(['items' => array_map('permohonan_row', $rows)]);
}

function r_lsp_hasil(): void
{
    require_method('GET');
    $m = require_perm('decision.manage');
    [$w, $p] = scope_where($m, 'p.lsp_id', true);
    $rows = q(permohonan_sql() . " WHERE p.status IN ('kompeten','belum_kompeten')$w ORDER BY p.diputuskan_at DESC LIMIT 200", $p)->fetchAll();
    [$w2, $p2] = scope_where($m, 'c.lsp_id', true);
    $sert = q(sertifikat_sql() . ' WHERE 1=1' . $w2 . ' ORDER BY c.terbit DESC LIMIT 200', $p2)->fetchAll();
    json_out(['items' => array_map('permohonan_row', $rows), 'sertifikat' => array_map('sertifikat_row', $sert)]);
}

/* =========================== Jadwal & penugasan =========================== */

function r_lsp_jadwal(): void
{
    $m = require_perm('schedule.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        [$w, $p] = scope_where($m, 'j.lsp_id');
        $rows = q('SELECT j.*, s.nama AS skema_nama, ls.nama AS lsp_nama, t.nama AS tuk_nama, a.nama AS asesor_nama FROM jadwal j JOIN skema s ON s.id = j.skema_id
                   JOIN lsp ls ON ls.id = j.lsp_id LEFT JOIN tuk t ON t.id = j.tuk_id LEFT JOIN users a ON a.id = j.asesor_id' . $w . ' ORDER BY j.tanggal DESC, j.id DESC LIMIT 300', $p)->fetchAll();
        json_out(['items' => array_map('jadwal_row', $rows)]);
    }
    require_method('POST');
    csrf_check();
    $id = int_in('id');
    $lsp = $id ? (int)scoped_row('jadwal', $id, $m)['lsp_id'] : write_lsp($m);
    $skema = q("SELECT id FROM skema WHERE id = ? AND lsp_id = ? AND status = 'aktif'", [int_in('skema_id'), $lsp])->fetchColumn();
    if (!$skema) {
        fail('Pilih skema aktif milik LSP ini.', 422);
    }
    $tuk = int_in('tuk_id');
    if ($tuk && !q('SELECT 1 FROM tuk WHERE id = ? AND lsp_id = ?', [$tuk, $lsp])->fetch()) {
        fail('TUK tidak valid untuk LSP ini.', 422);
    }
    $tgl = date_in('tanggal', 'Tanggal');
    $jam = str_in('jam', 20, 'Jam');
    $metode = str_in('metode', 20);
    $kuota = int_in('kuota');
    $status = str_in('status', 20) ?: 'dibuka';
    if (!$id && $tgl < today()) fail('Tanggal jadwal baru tidak boleh di masa lalu.', 422);
    if (!in_array($metode, JADWAL_METODE, true)) fail('Metode tidak valid.', 422);
    if ($kuota < 1 || $kuota > 500) fail('Kuota 1–500 peserta.', 422);
    if (!in_array($status, ['dibuka', 'ditutup', 'selesai', 'batal'], true)) fail('Status tidak valid.', 422);
    if ($jam === '') $jam = '08.00–16.00';
    $catatan = str_in('catatan', 255, 'Catatan');
    if ($id) {
        q('UPDATE jadwal SET skema_id = ?, tuk_id = ?, tanggal = ?, jam = ?, metode = ?, kuota = ?, status = ?, catatan = ?, updated_at = ? WHERE id = ?',
            [$skema, $tuk ?: null, $tgl, $jam, $metode, $kuota, $status, $catatan ?: null, now(), $id]);
    } else {
        q('INSERT INTO jadwal (lsp_id, skema_id, tuk_id, tanggal, jam, metode, kuota, status, catatan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$lsp, $skema, $tuk ?: null, $tgl, $jam, $metode, $kuota, $status, $catatan ?: null, now(), now()]);
        $id = (int)db()->lastInsertId();
    }
    audit('jadwal.simpan', 'jadwal:' . $id, null, $lsp);
    json_out(['id' => $id], 201);
}

function r_lsp_jadwal_asesor(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('schedule.manage');
    $j = scoped_row('jadwal', int_in('jadwal_id'), $m);
    $aid = int_in('asesor_id');
    if ($aid === 0) {
        q('UPDATE jadwal SET asesor_id = NULL, updated_at = ? WHERE id = ?', [now(), $j['id']]);
        json_out(['ok' => true]);
    }
    if (!q("SELECT 1 FROM memberships WHERE user_id = ? AND lsp_id = ? AND role = 'asesor' AND status = 'aktif'", [$aid, $j['lsp_id']])->fetch()) {
        fail('Asesor tidak terdaftar aktif di LSP ini.', 422);
    }
    // Cek bentrok: asesor yang sama di tanggal yang sama (di LSP mana pun).
    if (q("SELECT 1 FROM jadwal WHERE asesor_id = ? AND tanggal = ? AND id <> ? AND status <> 'batal'", [$aid, $j['tanggal'], $j['id']])->fetch()) {
        fail('Asesor ini sudah bertugas di jadwal lain pada tanggal yang sama.', 409);
    }
    q('UPDATE jadwal SET asesor_id = ?, updated_at = ? WHERE id = ?', [$aid, now(), $j['id']]);
    audit('jadwal.asesor', 'jadwal:' . $j['id'] . ' asesor:' . $aid, null, (int)$j['lsp_id']);
    $sk = q('SELECT nama FROM skema WHERE id = ?', [$j['skema_id']])->fetchColumn();
    notify([$aid], (int)$j['lsp_id'], 'jadwal.penugasan', 'Penugasan asesmen baru', "Anda ditugaskan sebagai asesor $sk pada {$j['tanggal']} di " . lsp_name((int)$j['lsp_id']) . '.', 'kalender');
    json_out(['ok' => true]);
}

function r_lsp_asesor(): void
{
    $m = require_perm('master.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        [$w, $p] = scope_where($m, 'm.lsp_id', true);
        $rows = q("SELECT m.id AS membership_id, m.status, m.lsp_id, u.id, u.nama, u.email, l.nama AS lsp_nama,
                     (SELECT COUNT(*) FROM jadwal j WHERE j.asesor_id = u.id AND j.lsp_id = m.lsp_id AND j.tanggal >= ?) AS jadwal_mendatang,
                     (SELECT COUNT(*) FROM permohonan p WHERE p.asesor_id = u.id AND p.lsp_id = m.lsp_id) AS diuji
                   FROM memberships m JOIN users u ON u.id = m.user_id JOIN lsp l ON l.id = m.lsp_id WHERE m.role = 'asesor'$w ORDER BY u.nama", array_merge([today()], $p))->fetchAll();
        json_out(['items' => array_map(function ($r) {
            return ['id' => (int)$r['id'], 'membership_id' => (int)$r['membership_id'], 'nama' => $r['nama'], 'email' => $r['email'], 'status' => $r['status'],
                'lsp_id' => (int)$r['lsp_id'], 'lsp_nama' => $r['lsp_nama'], 'jadwal_mendatang' => (int)$r['jadwal_mendatang'], 'diuji' => (int)$r['diuji']];
        }, $rows)]);
    }
    require_method('POST');
    csrf_check();
    $lsp = write_lsp($m);
    $email = strtolower(str_in('email', 190, 'Email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.', 422);
    $u = q('SELECT id, nama FROM users WHERE email = ?', [$email])->fetch();
    if ($u) {
        // Asesor boleh aktif di banyak LSP; tambahkan keanggotaan di LSP ini.
        $internal = q("SELECT 1 FROM memberships WHERE user_id = ? AND role IN ('platform_admin','admin_lsp','manajer_mutu','keuangan','marketing','admin_tuk')", [$u['id']])->fetch();
        if ($internal) fail('Akun ini adalah akun staf. Gunakan email pribadi asesor.', 409);
        if (q("SELECT 1 FROM memberships WHERE user_id = ? AND lsp_id = ? AND role = 'asesor'", [$u['id'], $lsp])->fetch()) fail('Asesor ini sudah terdaftar di LSP ini.', 409);
        $uid = (int)$u['id'];
        $nama = $u['nama'];
    } else {
        $nama = str_in('nama', 120, 'Nama');
        $pw = body()['password'] ?? '';
        if (text_len($nama) < 3) fail('Email belum terdaftar. Isi nama lengkap dan password awal untuk membuat akun asesor.', 422);
        validate_new_password(is_string($pw) ? $pw : '', $email);
        q('INSERT INTO users (email, nama, password_hash, status, must_change_password, created_at, updated_at, email_verified_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?)',
            [$email, $nama, password_hash($pw, PASSWORD_DEFAULT), 'aktif', now(), now(), now()]);
        $uid = (int)db()->lastInsertId();
    }
    q('INSERT INTO memberships (user_id, lsp_id, role, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$uid, $lsp, 'asesor', 'aktif', uid(), now()]);
    audit('asesor.ditambah', $email, null, $lsp);
    notify([$uid], $lsp, 'akun.dibuat', 'Anda terdaftar sebagai asesor', "Anda ditambahkan sebagai asesor di " . lsp_name($lsp) . '.' . ($u ? '' : ' Minta password awal ke Admin LSP.'), 'kalender');
    json_out(['ok' => true], 201);
}

/* =========================== Master: skema & TUK =========================== */

function r_lsp_skema(): void
{
    $m = require_perm('master.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        [$w, $p] = scope_where($m, 's.lsp_id');
        $rows = q("SELECT s.*, l.nama AS lsp_nama, (SELECT COUNT(*) FROM listings x WHERE x.skema_id = s.id AND x.status = 'tayang') AS tayang
                   FROM skema s JOIN lsp l ON l.id = s.lsp_id$w ORDER BY s.nama", $p)->fetchAll();
        json_out(['items' => array_map(function ($r) {
            return ['id' => (int)$r['id'], 'lsp_id' => (int)$r['lsp_id'], 'lsp_nama' => $r['lsp_nama'], 'kode' => $r['kode'], 'nama' => $r['nama'], 'bidang' => $r['bidang'],
                'kkni' => $r['kkni'], 'harga' => (int)$r['harga'], 'deskripsi' => $r['deskripsi'], 'persyaratan' => $r['persyaratan'], 'status' => $r['status'],
                'tayang' => (int)$r['tayang'] > 0, 'units' => units_of((int)$r['id'])];
        }, $rows)]);
    }
    require_method('POST');
    csrf_check();
    $id = int_in('id');
    $lsp = $id ? (int)scoped_row('skema', $id, $m)['lsp_id'] : write_lsp($m);
    $kode = strtoupper(str_in('kode', 40, 'Kode'));
    $nama = str_in('nama', 190, 'Nama skema');
    $bidang = str_in('bidang', 40);
    $kkni = str_in('kkni', 20, 'KKNI');
    $harga = int_in('harga');
    $status = str_in('status', 20) ?: 'aktif';
    if ($kode === '' || text_len($nama) < 3) fail('Isi kode dan nama skema.', 422);
    if (!in_array($bidang, LISTING_BIDANG, true)) fail('Bidang tidak valid.', 422);
    if ($harga < 0 || $harga > 100000000) fail('Biaya uji tidak valid.', 422);
    if (!in_array($status, ['aktif', 'nonaktif'], true)) fail('Status tidak valid.', 422);
    $units = [];
    foreach (preg_split('/\R/', str_in('units_text', 20000, 'Unit kompetensi')) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = preg_split('/\s*[|\t]\s*|\s+[-–]\s+/u', $line, 2);
        if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') fail("Format unit salah: \"$line\". Gunakan KODE | Judul unit.", 422);
        $units[] = [cut($parts[0], 40), cut($parts[1], 255)];
    }
    if (!$units) fail('Isi minimal satu unit kompetensi.', 422);
    $desk = str_in('deskripsi', 2000, 'Deskripsi');
    $syarat = str_in('persyaratan', 2000, 'Persyaratan');
    db()->beginTransaction();
    try {
        if ($id) {
            q('UPDATE skema SET kode = ?, nama = ?, bidang = ?, kkni = ?, harga = ?, deskripsi = ?, persyaratan = ?, status = ?, updated_at = ? WHERE id = ?',
                [$kode, $nama, $bidang, $kkni, $harga, $desk, $syarat, $status, now(), $id]);
            // Unit hanya diganti bila belum ada permohonan (rekaman asesmen lama merujuk unit lama).
            if (!q('SELECT 1 FROM permohonan WHERE skema_id = ?', [$id])->fetch()) {
                q('DELETE FROM skema_units WHERE skema_id = ?', [$id]);
                foreach ($units as $i => [$k, $j]) q('INSERT INTO skema_units (skema_id, kode, judul, urut) VALUES (?, ?, ?, ?)', [$id, $k, $j, $i + 1]);
            }
        } else {
            q('INSERT INTO skema (lsp_id, kode, nama, bidang, kkni, harga, deskripsi, persyaratan, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$lsp, $kode, $nama, $bidang, $kkni, $harga, $desk, $syarat, $status, now(), now()]);
            $id = (int)db()->lastInsertId();
            foreach ($units as $i => [$k, $j]) q('INSERT INTO skema_units (skema_id, kode, judul, urut) VALUES (?, ?, ?, ?)', [$id, $k, $j, $i + 1]);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    audit('skema.simpan', 'skema:' . $id, null, $lsp);
    json_out(['id' => $id], 201);
}

function r_lsp_tuk(): void
{
    $m = require_perm('master.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        [$w, $p] = scope_where($m, 't.lsp_id');
        $rows = q("SELECT t.*, l.nama AS lsp_nama, (SELECT COUNT(*) FROM jadwal j WHERE j.tuk_id = t.id AND j.tanggal >= ?) AS jadwal_mendatang
                   FROM tuk t JOIN lsp l ON l.id = t.lsp_id$w ORDER BY t.nama", array_merge([today()], $p))->fetchAll();
        json_out(['items' => array_map('tuk_row', $rows)]);
    }
    require_method('POST');
    csrf_check();
    $id = int_in('id');
    $lsp = $id ? (int)scoped_row('tuk', $id, $m)['lsp_id'] : write_lsp($m);
    $nama = str_in('nama', 190, 'Nama TUK');
    $jenis = str_in('jenis', 30);
    $alamat = str_in('alamat', 255, 'Alamat');
    $kap = int_in('kapasitas');
    $verif = date_in('verif_sampai', 'Masa berlaku verifikasi', false);
    $status = str_in('status', 20) ?: 'aktif';
    if (text_len($nama) < 3) fail('Isi nama TUK.', 422);
    if (!in_array($jenis, ['Sewaktu', 'Tempat Kerja', 'Mandiri'], true)) fail('Jenis TUK tidak valid.', 422);
    if ($kap < 1 || $kap > 1000) fail('Kapasitas 1–1000.', 422);
    if (!in_array($status, ['aktif', 'nonaktif'], true)) fail('Status tidak valid.', 422);
    if ($id) {
        q('UPDATE tuk SET nama = ?, jenis = ?, alamat = ?, kapasitas = ?, verif_sampai = ?, status = ? WHERE id = ?', [$nama, $jenis, $alamat, $kap, $verif, $status, $id]);
    } else {
        q('INSERT INTO tuk (lsp_id, nama, status, alamat, jenis, kapasitas, verif_sampai) VALUES (?, ?, ?, ?, ?, ?, ?)', [$lsp, $nama, $status, $alamat, $jenis, $kap, $verif]);
        $id = (int)db()->lastInsertId();
    }
    audit('tuk.simpan', 'tuk:' . $id, null, $lsp);
    json_out(['id' => $id], 201);
}

function tuk_row(array $t): array
{
    return ['id' => (int)$t['id'], 'lsp_id' => (int)$t['lsp_id'], 'lsp_nama' => $t['lsp_nama'] ?? null, 'nama' => $t['nama'], 'jenis' => $t['jenis'], 'alamat' => $t['alamat'],
        'kapasitas' => (int)$t['kapasitas'], 'verif_sampai' => $t['verif_sampai'], 'status' => $t['status'], 'jadwal_mendatang' => (int)($t['jadwal_mendatang'] ?? 0)];
}

/** Opsi pilihan (skema, TUK, asesor) untuk form jadwal/etalase. */
function r_lsp_opsi(): void
{
    require_method('GET');
    $m = require_auth() ? active_membership() : null;
    if (!can('schedule.manage') && !can('listing.manage') && !can('master.manage')) {
        require_perm('schedule.manage');
    }
    [$w, $p] = scope_where($m, 'lsp_id');
    $sk = q("SELECT id, lsp_id, kode, nama FROM skema$w ORDER BY nama", $p)->fetchAll();
    $tk = q("SELECT id, lsp_id, nama FROM tuk$w ORDER BY nama", $p)->fetchAll();
    [$w2, $p2] = scope_where($m, 'm.lsp_id', true);
    $as = q("SELECT u.id, u.nama, m.lsp_id FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.role = 'asesor' AND m.status = 'aktif'$w2 ORDER BY u.nama", $p2)->fetchAll();
    $ints = function ($rows) {
        return array_map(function ($r) { foreach (['id', 'lsp_id'] as $k) $r[$k] = (int)$r[$k]; return $r; }, $rows);
    };
    json_out(['skema' => $ints($sk), 'tuk' => $ints($tk), 'asesor' => $ints($as)]);
}

/* =========================== Alumni, keuangan, laporan =========================== */

function r_lsp_alumni(): void
{
    require_method('GET');
    $m = require_perm('alumni.view');
    [$w, $p] = scope_where($m, 'c.lsp_id', true);
    $qq = isset($_GET['q']) && is_string($_GET['q']) ? trim(cut($_GET['q'], 60)) : '';
    if ($qq !== '') {
        $w .= ' AND (u.nama LIKE ? OR c.nomor LIKE ? OR s.nama LIKE ?)';
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $qq) . '%';
        array_push($p, $like, $like, $like);
    }
    $rows = q(sertifikat_sql() . ' WHERE 1=1' . $w . ' ORDER BY c.terbit DESC LIMIT 500', $p)->fetchAll();
    json_out(['items' => array_map('sertifikat_row', $rows)]);
}

function r_lsp_keuangan(): void
{
    require_method('GET');
    $m = require_perm('finance.manage');
    [$w, $p] = scope_where($m, 't.lsp_id', true);
    $rows = q('SELECT t.*, l.nama AS lsp_nama, u.nama FROM tagihan t JOIN lsp l ON l.id = t.lsp_id JOIN users u ON u.id = t.user_id WHERE 1=1' . $w . ' ORDER BY t.id DESC LIMIT 300', $p)->fetchAll();
    $bulan = date('Y-m-01 00:00:00');
    $sum = function (string $cond, array $params = []) use ($w, $p): int {
        return (int)q("SELECT COALESCE(SUM(t.jumlah),0) FROM tagihan t WHERE $cond$w", array_merge($params, $p))->fetchColumn();
    };
    [$w2, $p2] = scope_where($m, 'p.lsp_id', true);
    $honor = q("SELECT u.nama, l.nama AS lsp_nama, l.honor_per_asesi, COUNT(*) AS asesi FROM permohonan p JOIN users u ON u.id = p.asesor_id JOIN lsp l ON l.id = p.lsp_id
                WHERE p.diuji_at >= ?$w2 GROUP BY u.nama, l.nama, l.honor_per_asesi ORDER BY u.nama", array_merge([$bulan], $p2))->fetchAll();
    json_out([
        'items' => array_map('tagihan_row', $rows),
        'ringkasan' => ['lunas_bulan' => $sum("t.status = 'lunas' AND t.dibayar_at >= ?", [$bulan]), 'belum' => $sum("t.status = 'belum'"),
            'lunas_total' => $sum("t.status = 'lunas'")],
        'honor' => array_map(function ($h) {
            return ['nama' => $h['nama'], 'lsp_nama' => $h['lsp_nama'], 'asesi' => (int)$h['asesi'], 'total' => (int)$h['asesi'] * (int)$h['honor_per_asesi']];
        }, $honor),
    ]);
}

function r_lsp_keuangan_lunas(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('finance.manage');
    $t = scoped_row('tagihan', int_in('id'), $m);
    settle_invoice($t, 'Transfer (konfirmasi manual)');
    json_out(['ok' => true]);
}

function r_lsp_laporan(): void
{
    require_method('GET');
    $m = require_perm('report.bnsp');
    $tahun = isset($_GET['tahun']) && ctype_digit((string)$_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
    $from = "$tahun-01-01 00:00:00";
    $to = ($tahun + 1) . '-01-01 00:00:00';
    [$w, $p] = scope_where($m, 's.lsp_id', true);
    $rows = q("SELECT s.id, s.kode, s.nama, l.nama AS lsp_nama,
                 SUM(CASE WHEN p.diajukan_at >= ? AND p.diajukan_at < ? THEN 1 ELSE 0 END) AS pendaftar,
                 SUM(CASE WHEN p.diuji_at >= ? AND p.diuji_at < ? THEN 1 ELSE 0 END) AS diuji,
                 SUM(CASE WHEN p.keputusan = 'K' AND p.diputuskan_at >= ? AND p.diputuskan_at < ? THEN 1 ELSE 0 END) AS kompeten,
                 SUM(CASE WHEN p.keputusan = 'BK' AND p.diputuskan_at >= ? AND p.diputuskan_at < ? THEN 1 ELSE 0 END) AS belum_kompeten
               FROM skema s JOIN lsp l ON l.id = s.lsp_id LEFT JOIN permohonan p ON p.skema_id = s.id WHERE 1=1$w GROUP BY s.id, s.kode, s.nama, l.nama ORDER BY l.nama, s.nama",
        array_merge([$from, $to, $from, $to, $from, $to, $from, $to], $p))->fetchAll();
    $items = array_map(function ($r) {
        return ['kode' => $r['kode'], 'nama' => $r['nama'], 'lsp_nama' => $r['lsp_nama'], 'pendaftar' => (int)$r['pendaftar'], 'diuji' => (int)$r['diuji'],
            'kompeten' => (int)$r['kompeten'], 'belum_kompeten' => (int)$r['belum_kompeten']];
    }, $rows);
    if (($_GET['format'] ?? '') === 'csv') {
        audit('laporan.unduh', 'tahun:' . $tahun);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="laporan-bnsp-' . $tahun . '.csv"');
        header('Cache-Control: private, no-store');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['LSP', 'Kode skema', 'Nama skema', 'Pendaftar', 'Diuji', 'Kompeten', 'Belum kompeten']);
        foreach ($items as $i) {
            // Cegah formula injection di spreadsheet.
            $cells = array_map(function ($v) { return is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v; }, [$i['lsp_nama'], $i['kode'], $i['nama']]);
            fputcsv($out, array_merge($cells, [$i['pendaftar'], $i['diuji'], $i['kompeten'], $i['belum_kompeten']]));
        }
        fclose($out);
        exit;
    }
    json_out(['tahun' => $tahun, 'items' => $items]);
}

/* =========================== CRM & mutu =========================== */

function r_lsp_crm(): void
{
    $m = require_perm('crm.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        [$w, $p] = scope_where($m, 'c.lsp_id');
        $rows = q("SELECT c.*, l.nama AS lsp_nama FROM crm_leads c JOIN lsp l ON l.id = c.lsp_id$w ORDER BY c.updated_at DESC", $p)->fetchAll();
        json_out(['items' => array_map(function ($r) {
            return ['id' => (int)$r['id'], 'lsp_nama' => $r['lsp_nama'], 'nama' => $r['nama'], 'organisasi' => $r['organisasi'], 'kontak' => $r['kontak'],
                'sumber' => $r['sumber'], 'tahap' => $r['tahap'], 'nilai' => (int)$r['nilai'], 'catatan' => $r['catatan'], 'followup' => $r['followup']];
        }, $rows), 'tahap' => CRM_TAHAP]);
    }
    require_method('POST');
    csrf_check();
    $id = int_in('id');
    $lsp = $id ? (int)scoped_row('crm_leads', $id, $m)['lsp_id'] : write_lsp($m);
    $nama = str_in('nama', 150, 'Nama kontak');
    $tahap = str_in('tahap', 20) ?: 'baru';
    if (text_len($nama) < 2) fail('Isi nama kontak.', 422);
    if (!in_array($tahap, CRM_TAHAP, true)) fail('Tahap tidak valid.', 422);
    $vals = [$nama, str_in('organisasi', 150, 'Organisasi'), str_in('kontak', 150, 'Kontak'), str_in('sumber', 40, 'Sumber') ?: 'Lainnya', $tahap,
        max(0, int_in('nilai')), str_in('catatan', 2000, 'Catatan'), date_in('followup', 'Tanggal follow-up', false), now()];
    if ($id) {
        q('UPDATE crm_leads SET nama = ?, organisasi = ?, kontak = ?, sumber = ?, tahap = ?, nilai = ?, catatan = ?, followup = ?, updated_at = ? WHERE id = ?', array_merge($vals, [$id]));
    } else {
        q('INSERT INTO crm_leads (nama, organisasi, kontak, sumber, tahap, nilai, catatan, followup, updated_at, lsp_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge($vals, [$lsp, uid(), now()]));
        $id = (int)db()->lastInsertId();
    }
    audit('crm.simpan', 'lead:' . $id, null, $lsp);
    json_out(['id' => $id], 201);
}

function r_lsp_crm_tahap(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('crm.manage');
    $r = scoped_row('crm_leads', int_in('id'), $m);
    $tahap = str_in('tahap', 20);
    if (!in_array($tahap, CRM_TAHAP, true)) fail('Tahap tidak valid.', 422);
    q('UPDATE crm_leads SET tahap = ?, updated_at = ? WHERE id = ?', [$tahap, now(), $r['id']]);
    json_out(['ok' => true]);
}

function r_lsp_mutu(): void
{
    $m = require_perm('quality.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        [$w, $p] = scope_where($m, 'x.lsp_id');
        $rows = q("SELECT x.*, l.nama AS lsp_nama FROM mutu x JOIN lsp l ON l.id = x.lsp_id$w ORDER BY CASE x.status WHEN 'selesai' THEN 1 ELSE 0 END, x.tenggat", $p)->fetchAll();
        json_out(['items' => array_map(function ($r) {
            return ['id' => (int)$r['id'], 'lsp_nama' => $r['lsp_nama'], 'jenis' => $r['jenis'], 'judul' => $r['judul'], 'deskripsi' => $r['deskripsi'],
                'status' => $r['status'], 'pic' => $r['pic'], 'tenggat' => $r['tenggat']];
        }, $rows), 'jenis' => MUTU_JENIS]);
    }
    require_method('POST');
    csrf_check();
    $id = int_in('id');
    $lsp = $id ? (int)scoped_row('mutu', $id, $m)['lsp_id'] : write_lsp($m);
    $jenis = str_in('jenis', 40);
    $judul = str_in('judul', 190, 'Judul');
    $status = str_in('status', 20) ?: 'terbuka';
    if (!in_array($jenis, MUTU_JENIS, true)) fail('Jenis tidak valid.', 422);
    if (text_len($judul) < 3) fail('Isi judul.', 422);
    if (!in_array($status, ['terbuka', 'proses', 'selesai'], true)) fail('Status tidak valid.', 422);
    $vals = [$jenis, $judul, str_in('deskripsi', 2000, 'Deskripsi'), $status, str_in('pic', 120, 'PIC'), date_in('tenggat', 'Tenggat', false), now()];
    if ($id) {
        q('UPDATE mutu SET jenis = ?, judul = ?, deskripsi = ?, status = ?, pic = ?, tenggat = ?, updated_at = ? WHERE id = ?', array_merge($vals, [$id]));
    } else {
        q('INSERT INTO mutu (jenis, judul, deskripsi, status, pic, tenggat, updated_at, lsp_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($vals, [$lsp, now()]));
        $id = (int)db()->lastInsertId();
    }
    audit('mutu.simpan', 'mutu:' . $id, null, $lsp);
    json_out(['id' => $id], 201);
}

/* =========================== Pengaturan LSP =========================== */

function r_lsp_pengaturan(): void
{
    $m = require_perm('settings.manage');
    $id = is_platform($m) ? (int)(scope_lsp($m) ?? int_in('lsp_id')) : (int)$m['lsp_id'];
    if (!$id) {
        fail('Pilih LSP di bagian atas untuk mengubah pengaturannya.', 422);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $nama = str_in('nama', 190, 'Nama LSP');
        if (text_len($nama) < 5) fail('Nama LSP minimal 5 karakter.', 422);
        $email = str_in('email', 190, 'Email');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.', 422);
        $web = str_in('website', 190, 'Website');
        if ($web !== '' && !preg_match('#^https?://#i', $web)) fail('Website harus diawali http:// atau https://', 422);
        $honor = int_in('honor_per_asesi');
        if ($honor < 0 || $honor > 10000000) fail('Honor tidak valid.', 422);
        q('UPDATE lsp SET nama = ?, kota = ?, alamat = ?, telepon = ?, email = ?, website = ?, deskripsi = ?, honor_per_asesi = ?, lisensi_sampai = ? WHERE id = ?',
            [$nama, str_in('kota', 100, 'Kota'), str_in('alamat', 255, 'Alamat'), str_in('telepon', 30, 'Telepon'), $email, $web,
                str_in('deskripsi', 2000, 'Deskripsi'), $honor, date_in('lisensi_sampai', 'Masa berlaku lisensi', false), $id]);
        audit('lsp.pengaturan', 'lsp:' . $id, null, $id);
    }
    $l = q('SELECT * FROM lsp WHERE id = ?', [$id])->fetch();
    json_out(['lsp' => ['id' => (int)$l['id'], 'nama' => $l['nama'], 'jenis' => $l['jenis'], 'kota' => $l['kota'], 'alamat' => $l['alamat'], 'telepon' => $l['telepon'],
        'email' => $l['email'], 'website' => $l['website'], 'deskripsi' => $l['deskripsi'], 'honor_per_asesi' => (int)$l['honor_per_asesi'],
        'lisensi_sampai' => $l['lisensi_sampai'], 'paket' => $l['paket'], 'status' => $l['status']]]);
}

/* =========================== TUK =========================== */

/** Cakupan TUK: Admin TUK hanya TUK-nya; Admin LSP semua TUK di LSP-nya; Admin Platform semua. */
function tuk_where(array $m, string $tukCol, string $lspCol): array
{
    if ($m['role'] === 'admin_tuk') {
        return [" AND $tukCol = ?", [(int)$m['tuk_id']]];
    }
    $s = scope_lsp($m);
    return $s === null ? ['', []] : [" AND $lspCol = ?", [$s]];
}

function r_tuk_dashboard(): void
{
    require_method('GET');
    $m = require_perm('tuk.dashboard');
    [$w, $p] = tuk_where($m, 't.id', 't.lsp_id');
    $tuks = q("SELECT t.*, l.nama AS lsp_nama FROM tuk t JOIN lsp l ON l.id = t.lsp_id WHERE 1=1$w ORDER BY t.nama", $p)->fetchAll();
    $items = array_map(function ($t) {
        $r = tuk_row($t);
        $r['pemohon'] = (int)q("SELECT COUNT(*) FROM permohonan p JOIN jadwal j ON j.id = p.jadwal_id WHERE j.tuk_id = ? AND p.status IN ('pra_asesmen','siap_uji') ", [$t['id']])->fetchColumn();
        $r['jadwal_bulan'] = (int)q('SELECT COUNT(*) FROM jadwal WHERE tuk_id = ? AND tanggal >= ? AND tanggal < ?', [$t['id'], date('Y-m-01'), date('Y-m-01', strtotime('first day of next month'))])->fetchColumn();
        $s = q("SELECT COUNT(*) AS n, SUM(CASE WHEN kondisi = 'baik' THEN 1 ELSE 0 END) AS baik FROM sarpras WHERE tuk_id = ?", [$t['id']])->fetch();
        $r['sarpras_siap'] = (int)$s['n'] ? (int)round(100 * (int)$s['baik'] / (int)$s['n']) : null;
        return $r;
    }, $tuks);
    json_out(['items' => $items]);
}

function r_tuk_pemohon(): void
{
    require_method('GET');
    $m = require_perm('tuk.applicants');
    [$w, $p] = tuk_where($m, 'j.tuk_id', 'p.lsp_id');
    $rows = q(permohonan_sql() . " WHERE p.status IN ('pra_asesmen','siap_uji','menunggu_pleno','kompeten','belum_kompeten')$w ORDER BY j.tanggal DESC LIMIT 300", $p)->fetchAll();
    json_out(['items' => array_map('permohonan_row', $rows)]);
}

function r_tuk_jadwal(): void
{
    require_method('GET');
    $m = require_perm('tuk.schedule');
    [$w, $p] = tuk_where($m, 'j.tuk_id', 'j.lsp_id');
    $rows = q('SELECT j.*, s.nama AS skema_nama, ls.nama AS lsp_nama, t.nama AS tuk_nama, a.nama AS asesor_nama FROM jadwal j JOIN skema s ON s.id = j.skema_id
               JOIN lsp ls ON ls.id = j.lsp_id LEFT JOIN tuk t ON t.id = j.tuk_id LEFT JOIN users a ON a.id = j.asesor_id WHERE j.tuk_id IS NOT NULL' . $w . ' ORDER BY j.tanggal DESC LIMIT 200', $p)->fetchAll();
    json_out(['items' => array_map('jadwal_row', $rows)]);
}

function r_tuk_sarpras(): void
{
    $m = require_perm('tuk.facility');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        [$w, $p] = tuk_where($m, 's.tuk_id', 's.lsp_id');
        $rows = q("SELECT s.*, t.nama AS tuk_nama FROM sarpras s JOIN tuk t ON t.id = s.tuk_id WHERE 1=1$w ORDER BY t.nama, s.nama", $p)->fetchAll();
        json_out(['items' => array_map(function ($r) {
            return ['id' => (int)$r['id'], 'tuk_id' => (int)$r['tuk_id'], 'tuk_nama' => $r['tuk_nama'], 'nama' => $r['nama'], 'jumlah' => (int)$r['jumlah'],
                'kondisi' => $r['kondisi'], 'catatan' => $r['catatan'], 'updated_at' => $r['updated_at']];
        }, $rows)]);
    }
    require_method('POST');
    csrf_check();
    $id = int_in('id');
    if ($id) {
        $row = q('SELECT * FROM sarpras WHERE id = ?', [$id])->fetch();
        $tukId = $row ? (int)$row['tuk_id'] : 0;
    } else {
        $tukId = $m['role'] === 'admin_tuk' ? (int)$m['tuk_id'] : int_in('tuk_id');
    }
    [$w, $p] = tuk_where($m, 't.id', 't.lsp_id');
    $tuk = q("SELECT * FROM tuk t WHERE t.id = ?$w", array_merge([$tukId], $p))->fetch();
    if (!$tuk) {
        fail('TUK tidak ditemukan.', 404);
    }
    $nama = str_in('nama', 150, 'Nama alat');
    $kondisi = str_in('kondisi', 20);
    $jumlah = int_in('jumlah');
    if (text_len($nama) < 2) fail('Isi nama alat.', 422);
    if (!in_array($kondisi, ['baik', 'perlu perbaikan', 'rusak'], true)) fail('Kondisi tidak valid.', 422);
    if ($jumlah < 0 || $jumlah > 10000) fail('Jumlah tidak valid.', 422);
    $cat = str_in('catatan', 255, 'Catatan');
    if ($id) {
        q('UPDATE sarpras SET nama = ?, jumlah = ?, kondisi = ?, catatan = ?, updated_at = ? WHERE id = ?', [$nama, $jumlah, $kondisi, $cat ?: null, now(), $id]);
    } else {
        q('INSERT INTO sarpras (lsp_id, tuk_id, nama, jumlah, kondisi, catatan, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$tuk['lsp_id'], $tuk['id'], $nama, $jumlah, $kondisi, $cat ?: null, now()]);
        $id = (int)db()->lastInsertId();
    }
    audit('sarpras.simpan', 'sarpras:' . $id, null, (int)$tuk['lsp_id']);
    json_out(['id' => $id], 201);
}

function r_tuk_alumni(): void
{
    require_method('GET');
    $m = require_perm('tuk.alumni');
    [$w, $p] = tuk_where($m, 'j.tuk_id', 'c.lsp_id');
    $rows = q('SELECT c.*, s.nama AS skema_nama, l.nama AS lsp_nama, u.nama AS nama FROM sertifikat c JOIN skema s ON s.id = c.skema_id JOIN lsp l ON l.id = c.lsp_id
               JOIN users u ON u.id = c.user_id JOIN permohonan p ON p.id = c.permohonan_id JOIN jadwal j ON j.id = p.jadwal_id WHERE 1=1' . $w . ' ORDER BY c.terbit DESC', $p)->fetchAll();
    json_out(['items' => array_map('sertifikat_row', $rows)]);
}

/* =========================== Platform =========================== */

function r_platform_dashboard(): void
{
    require_method('GET');
    require_perm('platform.dashboard');
    $one = function (string $sql, array $p = []): int { return (int)q($sql, $p)->fetchColumn(); };
    json_out(['kpi' => [
        'lsp_aktif' => $one("SELECT COUNT(*) FROM lsp WHERE status = 'aktif'"),
        'listing_menunggu' => $one("SELECT COUNT(*) FROM listings WHERE status = 'menunggu'"),
        'tiket_terbuka' => $one("SELECT COUNT(*) FROM tiket WHERE status <> 'selesai'"),
        'asesi' => $one("SELECT COUNT(DISTINCT user_id) FROM memberships WHERE role = 'asesi' AND status = 'aktif'"),
        'sertifikat_tahun' => $one('SELECT COUNT(*) FROM sertifikat WHERE terbit >= ?', [date('Y-01-01')]),
        'transaksi_bulan' => $one("SELECT COALESCE(SUM(jumlah),0) FROM tagihan WHERE status = 'lunas' AND dibayar_at >= ?", [date('Y-m-01 00:00:00')]),
    ]]);
}

function r_platform_lsp(): void
{
    require_method('POST');
    csrf_check();
    require_perm('lsp.manage');
    $nama = str_in('nama', 190, 'Nama LSP');
    $jenis = str_in('jenis', 5);
    $kota = str_in('kota', 100, 'Kota');
    $kode = strtoupper(str_in('kode', 20, 'Kode'));
    $paket = str_in('paket', 20);
    $email = strtolower(str_in('admin_email', 190, 'Email admin'));
    $anama = str_in('admin_nama', 120, 'Nama admin');
    $pw = body()['admin_password'] ?? '';
    if (text_len($nama) < 5) fail('Nama LSP minimal 5 karakter.', 422);
    if (!in_array($jenis, ['P1', 'P2', 'P3'], true)) fail('Jenis LSP tidak valid.', 422);
    if (!isset(PAKET[$paket])) fail('Paket tidak valid.', 422);
    if (!preg_match('/^[A-Z0-9]{2,10}$/', $kode)) fail('Kode LSP 2–10 huruf/angka.', 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || text_len($anama) < 3) fail('Isi nama dan email Admin LSP.', 422);
    validate_new_password(is_string($pw) ? $pw : '', $email);
    if (q('SELECT 1 FROM users WHERE email = ?', [$email])->fetch()) fail('Email admin sudah terdaftar.', 409);
    db()->beginTransaction();
    try {
        q('INSERT INTO lsp (nama, jenis, kota, status, created_at, kode, paket, kuota_asesi) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$nama, $jenis, $kota, 'aktif', now(), $kode, $paket, PAKET[$paket][1]]);
        $lsp = (int)db()->lastInsertId();
        q('INSERT INTO users (email, nama, password_hash, status, must_change_password, created_at, updated_at, email_verified_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?)',
            [$email, $anama, password_hash($pw, PASSWORD_DEFAULT), 'aktif', now(), now(), now()]);
        $uid = (int)db()->lastInsertId();
        q('INSERT INTO memberships (user_id, lsp_id, role, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$uid, $lsp, 'admin_lsp', 'aktif', uid(), now()]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    audit('lsp.dibuat', 'lsp:' . $lsp);
    notify([$uid], $lsp, 'akun.dibuat', 'LSP Anda aktif di PortalLSP', "$nama sudah aktif. Anda adalah Admin LSP. Minta password awal ke tim platform dan ganti saat pertama masuk.");
    json_out(['id' => $lsp], 201);
}

function r_platform_lsp_status(): void
{
    require_method('POST');
    csrf_check();
    require_perm('lsp.manage');
    $status = str_in('status', 20);
    if (!in_array($status, ['aktif', 'nonaktif'], true)) fail('Status tidak valid.', 422);
    if (q('UPDATE lsp SET status = ? WHERE id = ?', [$status, int_in('id')])->rowCount() !== 1) fail('LSP tidak ditemukan.', 404);
    audit('lsp.' . $status, 'lsp:' . int_in('id'));
    json_out(['ok' => true]);
}

function r_platform_paket(): void
{
    $m = require_perm('lsp.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $paket = str_in('paket', 20);
        if (!isset(PAKET[$paket])) fail('Paket tidak valid.', 422);
        $kuota = int_in('kuota_asesi') ?: PAKET[$paket][1];
        if (q('UPDATE lsp SET paket = ?, kuota_asesi = ? WHERE id = ?', [$paket, $kuota, int_in('id')])->rowCount() < 1 && !q('SELECT 1 FROM lsp WHERE id = ?', [int_in('id')])->fetch()) {
            fail('LSP tidak ditemukan.', 404);
        }
        audit('lsp.paket', 'lsp:' . int_in('id') . ' ' . $paket);
    }
    $rows = q('SELECT l.*, (SELECT COUNT(*) FROM permohonan p WHERE p.lsp_id = l.id AND p.diajukan_at >= ?) AS terpakai FROM lsp l ORDER BY l.nama', [date('Y-01-01')])->fetchAll();
    json_out(['items' => array_map(function ($l) {
        return ['id' => (int)$l['id'], 'nama' => $l['nama'], 'paket' => $l['paket'], 'kuota_asesi' => (int)$l['kuota_asesi'], 'terpakai' => (int)$l['terpakai'],
            'biaya_bulan' => PAKET[$l['paket']][0] ?? 0, 'status' => $l['status']];
    }, $rows), 'paket' => array_map(function ($k, $v) { return ['nama' => $k, 'biaya' => $v[0], 'kuota' => $v[1]]; }, array_keys(PAKET), PAKET)]);
}

function r_skkni(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        require_perm('lsp.manage');
        $kode = strtoupper(str_in('kode', 40, 'Kode unit'));
        $judul = str_in('judul', 255, 'Judul unit');
        $sektor = str_in('sektor', 60, 'Sektor');
        if ($kode === '' || text_len($judul) < 5) fail('Isi kode dan judul unit.', 422);
        if (q('SELECT 1 FROM skkni WHERE kode = ?', [$kode])->fetch()) fail('Kode unit sudah ada.', 409);
        q('INSERT INTO skkni (kode, judul, sektor) VALUES (?, ?, ?)', [$kode, $judul, $sektor ?: 'Lainnya']);
        audit('skkni.tambah', $kode);
        json_out(['ok' => true], 201);
    }
    require_method('GET');
    require_auth();
    if (!can('master.manage') && !can('lsp.manage')) require_perm('master.manage');
    $qq = isset($_GET['q']) && is_string($_GET['q']) ? trim(cut($_GET['q'], 60)) : '';
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $qq) . '%';
    $rows = q('SELECT kode, judul, sektor FROM skkni WHERE kode LIKE ? OR judul LIKE ? OR sektor LIKE ? ORDER BY kode LIMIT 200', [$like, $like, $like])->fetchAll();
    json_out(['items' => $rows]);
}

function r_tiket(): void
{
    $m = require_auth() ? active_membership() : null;
    $platform = is_platform($m);
    if (!$platform && !can('settings.manage')) require_perm('settings.manage');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $judul = str_in('judul', 190, 'Judul');
        $isi = str_in('isi', 4000, 'Isi');
        $prio = str_in('prioritas', 20) ?: 'normal';
        if (text_len($judul) < 5 || text_len($isi) < 10) fail('Isi judul (min. 5) dan uraian (min. 10 karakter).', 422);
        if (!in_array($prio, ['rendah', 'normal', 'tinggi'], true)) fail('Prioritas tidak valid.', 422);
        $lsp = $platform ? scope_lsp($m) : (int)$m['lsp_id'];
        q('INSERT INTO tiket (lsp_id, user_id, judul, isi, prioritas, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$lsp, uid(), $judul, $isi, $prio, 'terbuka', now(), now()]);
        $id = (int)db()->lastInsertId();
        audit('tiket.buat', 'tiket:' . $id);
        notify(users_with_perm('lsp.manage', null), null, 'tiket.baru', 'Tiket support baru', "$judul (" . ($lsp ? lsp_name((int)$lsp) : 'Platform') . ')', 'support');
        json_out(['id' => $id], 201);
    }
    require_method('GET');
    [$w, $p] = $platform ? (scope_lsp($m) === null ? ['', []] : [' WHERE t.lsp_id = ?', [scope_lsp($m)]]) : [' WHERE t.lsp_id = ?', [(int)$m['lsp_id']]];
    $rows = q("SELECT t.*, u.nama, l.nama AS lsp_nama FROM tiket t JOIN users u ON u.id = t.user_id LEFT JOIN lsp l ON l.id = t.lsp_id$w
               ORDER BY CASE t.status WHEN 'selesai' THEN 1 ELSE 0 END, t.updated_at DESC", $p)->fetchAll();
    json_out(['items' => array_map(function ($t) {
        $bal = q('SELECT b.isi, b.created_at, u.nama FROM tiket_balasan b JOIN users u ON u.id = b.user_id WHERE b.tiket_id = ? ORDER BY b.id', [$t['id']])->fetchAll();
        return ['id' => (int)$t['id'], 'judul' => $t['judul'], 'isi' => $t['isi'], 'prioritas' => $t['prioritas'], 'status' => $t['status'], 'nama' => $t['nama'],
            'lsp_nama' => $t['lsp_nama'], 'created_at' => $t['created_at'], 'updated_at' => $t['updated_at'], 'balasan' => $bal];
    }, $rows)]);
}

function r_tiket_balas(): void
{
    require_method('POST');
    csrf_check();
    $m = require_auth() ? active_membership() : null;
    $t = q('SELECT * FROM tiket WHERE id = ?', [int_in('id')])->fetch();
    $platform = is_platform($m);
    if (!$t || (!$platform && (!can('settings.manage') || (int)$t['lsp_id'] !== (int)$m['lsp_id']))) {
        fail('Tiket tidak ditemukan.', 404);
    }
    $isi = str_in('isi', 4000, 'Balasan');
    $status = str_in('status', 20);
    if ($isi === '' && $status === '') fail('Tulis balasan.', 422);
    if ($isi !== '') {
        q('INSERT INTO tiket_balasan (tiket_id, user_id, isi, created_at) VALUES (?, ?, ?, ?)', [$t['id'], uid(), $isi, now()]);
    }
    $new = $status !== '' && $platform && in_array($status, ['terbuka', 'proses', 'selesai'], true) ? $status : ($platform && $isi !== '' ? 'proses' : $t['status']);
    q('UPDATE tiket SET status = ?, updated_at = ? WHERE id = ?', [$new, now(), $t['id']]);
    if ($platform && (int)$t['user_id'] !== uid()) {
        notify([(int)$t['user_id']], $t['lsp_id'] === null ? null : (int)$t['lsp_id'], 'tiket.balasan', 'Tiket support dibalas', $t['judul'], 'support');
    }
    json_out(['ok' => true]);
}

function r_audit(): void
{
    require_method('GET');
    $m = require_perm('lsp.manage');
    [$w, $p] = scope_where($m, 'a.lsp_id');
    $rows = q("SELECT a.*, u.email, l.nama AS lsp_nama FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN lsp l ON l.id = a.lsp_id$w ORDER BY a.id DESC LIMIT 300", $p)->fetchAll();
    json_out(['items' => array_map(function ($r) {
        return ['id' => (int)$r['id'], 'email' => $r['email'], 'lsp_nama' => $r['lsp_nama'], 'action' => $r['action'], 'target' => $r['target'], 'ip' => $r['ip'], 'created_at' => $r['created_at']];
    }, $rows)]);
}

/* =========================== Portal publik =========================== */

function r_pub_catalog(): void
{
    require_method('GET');
    $rows = q("SELECT l.*, s.nama AS lsp_nama, k.kkni, k.kode AS skema_kode, k.persyaratan,
                 (SELECT COUNT(*) FROM skema_units u WHERE u.skema_id = l.skema_id) AS unit,
                 (SELECT MIN(j.tanggal) FROM jadwal j WHERE j.skema_id = l.skema_id AND j.status = 'dibuka' AND j.tanggal >= ?) AS jadwal_terdekat
               FROM listings l JOIN lsp s ON s.id = l.lsp_id LEFT JOIN skema k ON k.id = l.skema_id
               WHERE l.status = 'tayang' AND s.status = 'aktif' ORDER BY l.reviewed_at DESC, l.id DESC LIMIT 300", [today()])->fetchAll();
    json_out(['items' => array_map(function ($r) {
        return listing_row($r, false) + ['lsp_id' => (int)$r['lsp_id'], 'skema_id' => $r['skema_id'] === null ? null : (int)$r['skema_id'], 'kkni' => $r['kkni'],
            'kode' => $r['skema_kode'], 'unit' => (int)$r['unit'], 'jadwal_terdekat' => $r['jadwal_terdekat']];
    }, $rows)]);
}

function r_pub_skema(): void
{
    require_method('GET');
    $id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
    $l = q("SELECT l.*, s.nama AS lsp_nama, s.kota AS lsp_kota, s.lisensi_sampai, s.id AS lsp_id2 FROM listings l JOIN lsp s ON s.id = l.lsp_id
            WHERE l.id = ? AND l.status = 'tayang' AND s.status = 'aktif'", [$id])->fetch();
    if (!$l) {
        fail('Skema tidak ditemukan.', 404);
    }
    $sk = $l['skema_id'] ? q('SELECT * FROM skema WHERE id = ?', [$l['skema_id']])->fetch() : null;
    $jadwal = $sk ? q("SELECT j.*, t.nama AS tuk_nama, t.alamat AS tuk_alamat FROM jadwal j LEFT JOIN tuk t ON t.id = j.tuk_id
                       WHERE j.skema_id = ? AND j.status = 'dibuka' AND j.tanggal >= ? ORDER BY j.tanggal", [$sk['id'], today()])->fetchAll() : [];
    json_out([
        'listing' => listing_row($l, false) + ['lsp_id' => (int)$l['lsp_id'], 'lsp_kota' => $l['lsp_kota'], 'lisensi_sampai' => $l['lisensi_sampai']],
        'skema' => $sk ? ['id' => (int)$sk['id'], 'kode' => $sk['kode'], 'nama' => $sk['nama'], 'kkni' => $sk['kkni'], 'harga' => (int)$sk['harga'],
            'deskripsi' => $sk['deskripsi'], 'persyaratan' => $sk['persyaratan'], 'units' => units_of((int)$sk['id'])] : null,
        'jadwal' => array_map(function ($j) {
            $r = jadwal_row($j);
            unset($r['asesor_id'], $r['asesor_nama'], $r['peserta']);
            $r['tuk_alamat'] = $j['tuk_alamat'];
            return $r;
        }, $jadwal),
    ]);
}

function r_pub_jadwal(): void
{
    require_method('GET');
    $rows = q("SELECT * FROM (SELECT j.*, s.nama AS skema_nama, ls.nama AS lsp_nama, t.nama AS tuk_nama, t.alamat AS tuk_alamat,
                 (SELECT MIN(x.id) FROM listings x WHERE x.skema_id = j.skema_id AND x.status = 'tayang') AS listing_id
               FROM jadwal j JOIN skema s ON s.id = j.skema_id JOIN lsp ls ON ls.id = j.lsp_id LEFT JOIN tuk t ON t.id = j.tuk_id
               WHERE j.status = 'dibuka' AND j.tanggal >= ? AND ls.status = 'aktif') z WHERE z.listing_id IS NOT NULL ORDER BY z.tanggal LIMIT 200", [today()])->fetchAll();
    json_out(['items' => array_map(function ($j) {
        $r = jadwal_row($j);
        unset($r['asesor_id'], $r['asesor_nama'], $r['peserta']);
        return $r + ['listing_id' => (int)$j['listing_id'], 'tuk_alamat' => $j['tuk_alamat']];
    }, $rows)]);
}

function r_pub_lsp(): void
{
    require_method('GET');
    $rows = q("SELECT l.id, l.nama, l.jenis, l.kota, l.alamat, l.telepon, l.email, l.website, l.deskripsi, l.lisensi_sampai,
                 (SELECT COUNT(*) FROM listings x WHERE x.lsp_id = l.id AND x.status = 'tayang' AND x.tipe = 'skema') AS skema,
                 (SELECT COUNT(*) FROM tuk t WHERE t.lsp_id = l.id AND t.status = 'aktif') AS tuk,
                 (SELECT COUNT(*) FROM sertifikat c WHERE c.lsp_id = l.id) AS alumni
               FROM lsp l WHERE l.status = 'aktif' ORDER BY l.nama")->fetchAll();
    json_out(['items' => array_map(function ($r) {
        foreach (['id', 'skema', 'tuk', 'alumni'] as $k) $r[$k] = (int)$r[$k];
        return $r;
    }, $rows)]);
}

function r_pub_verify(): void
{
    require_method('GET');
    $qq = isset($_GET['q']) && is_string($_GET['q']) ? strtoupper(trim(cut($_GET['q'], 60))) : '';
    if (strlen($qq) < 6) {
        fail('Masukkan nomor sertifikat atau kode verifikasi.', 422);
    }
    // Batasi percobaan per IP agar nomor sertifikat tidak bisa ditebak massal.
    $n = (int)q("SELECT COUNT(*) FROM audit_logs WHERE action = 'sertifikat.cek' AND ip = ? AND created_at >= ?", [client_ip(), date('Y-m-d H:i:s', time() - 600)])->fetchColumn();
    if ($n >= 30) {
        fail('Terlalu banyak pencarian. Coba lagi dalam 10 menit.', 429);
    }
    audit('sertifikat.cek', cut($qq, 60), null, null);
    $c = q(sertifikat_sql() . ' WHERE c.kode = ? OR UPPER(c.nomor) = ?', [$qq, $qq])->fetch();
    if (!$c) {
        json_out(['found' => false]);
    }
    $status = $c['status'] !== 'aktif' ? 'dicabut' : ($c['berlaku'] < today() ? 'kedaluwarsa' : 'berlaku');
    json_out(['found' => true, 'nomor' => $c['nomor'], 'nama' => $c['nama'], 'skema' => $c['skema_nama'], 'lsp' => $c['lsp_nama'],
        'terbit' => $c['terbit'], 'berlaku' => $c['berlaku'], 'status' => $status]);
}
