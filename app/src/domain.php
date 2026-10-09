<?php
declare(strict_types=1);

/*
 * Proses sertifikasi: permohonan asesi, dokumen, tagihan, pra-asesmen, asesmen, pleno, sertifikat, kelas, chat.
 * Aturan akses: asesi hanya data miliknya; asesor hanya jadwal yang ditugaskan kepadanya; staf LSP hanya LSP-nya
 * (scope_lsp); Admin Platform semua LSP.
 */

const P_STATUS = [
    'diajukan' => 'Menunggu verifikasi', 'perbaikan' => 'Perlu perbaikan', 'ditolak' => 'Ditolak', 'menunggu_bayar' => 'Menunggu pembayaran',
    'pra_asesmen' => 'Pra-asesmen', 'tidak_lanjut' => 'Belum dapat dilanjutkan', 'siap_uji' => 'Siap uji', 'menunggu_pleno' => 'Menunggu pleno',
    'kompeten' => 'Kompeten', 'belum_kompeten' => 'Belum kompeten', 'dibatalkan' => 'Dibatalkan',
];
/** Status yang memakai kuota jadwal. */
const P_AKTIF = ['diajukan', 'perbaikan', 'menunggu_bayar', 'pra_asesmen', 'siap_uji', 'menunggu_pleno', 'kompeten', 'belum_kompeten'];
const DOK_JENIS = ['ktp' => 'KTP', 'ijazah' => 'Ijazah terakhir', 'foto' => 'Pas foto', 'cv' => 'CV / portofolio'];
const DOK_WAJIB = ['ktp', 'ijazah', 'foto'];
const DOK_MAX = 2097152;
const DOK_MIME = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

function today(): string
{
    return date('Y-m-d');
}

function uid(): int
{
    return (int)current_user()['id'];
}

/** Sisa kuota jadwal. */
function jadwal_sisa(int $jadwalId, int $kuota): int
{
    $in = implode(',', array_fill(0, count(P_AKTIF), '?'));
    $n = (int)q("SELECT COUNT(*) FROM permohonan WHERE jadwal_id = ? AND status IN ($in)", array_merge([$jadwalId], P_AKTIF))->fetchColumn();
    return max(0, $kuota - $n);
}

function units_of(int $skemaId): array
{
    return array_map(function ($u) {
        return ['id' => (int)$u['id'], 'kode' => $u['kode'], 'judul' => $u['judul']];
    }, q('SELECT id, kode, judul FROM skema_units WHERE skema_id = ? ORDER BY urut, id', [$skemaId])->fetchAll());
}

/** Peta unit → K/BK dari input; semua unit skema wajib diisi. */
function unit_map_in(string $key, int $skemaId, string $label): string
{
    $in = body()[$key] ?? null;
    if (!is_array($in)) {
        fail("Isi $label untuk setiap unit kompetensi.", 422);
    }
    $out = [];
    foreach (units_of($skemaId) as $u) {
        $v = $in[(string)$u['id']] ?? null;
        if (!in_array($v, ['K', 'BK'], true)) {
            fail("Isi $label untuk setiap unit kompetensi.", 422);
        }
        $out[(string)$u['id']] = $v;
    }
    return json_encode($out);
}

function new_invoice(int $lspId, ?int $permohonanId, ?int $kelasPesertaId, int $userId, string $desc, int $jumlah): int
{
    q('INSERT INTO tagihan (lsp_id, permohonan_id, kelas_peserta_id, user_id, nomor, deskripsi, jumlah, status, jatuh_tempo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$lspId, $permohonanId, $kelasPesertaId, $userId, 'TMP-' . bin2hex(random_bytes(6)), $desc, $jumlah, 'belum', date('Y-m-d', time() + 7 * 86400), now()]);
    $id = (int)db()->lastInsertId();
    q('UPDATE tagihan SET nomor = ? WHERE id = ?', [sprintf('INV/%d/%s/%05d', $lspId, date('Ym'), $id), $id]);
    return $id;
}

function random_code(int $n = 10): string
{
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $n; $i++) {
        $s .= $abc[random_int(0, strlen($abc) - 1)];
    }
    return $s;
}

function issue_certificate(array $p): array
{
    $info = q('SELECT s.kode AS sk, l.kode AS lk, l.id AS lid FROM skema s JOIN lsp l ON l.id = s.lsp_id WHERE s.id = ?', [$p['skema_id']])->fetch();
    $kode = random_code();
    q('INSERT INTO sertifikat (lsp_id, permohonan_id, user_id, skema_id, nomor, kode, terbit, berlaku, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$p['lsp_id'], $p['id'], $p['asesi_id'], $p['skema_id'], 'TMP-' . $kode, $kode, today(), date('Y-m-d', strtotime('+3 years')), 'aktif', now()]);
    $id = (int)db()->lastInsertId();
    $nomor = sprintf('LSP-%s/%s/%s/%05d', $info['lk'] ?: ('L' . $info['lid']), $info['sk'], date('Y'), $id);
    q('UPDATE sertifikat SET nomor = ? WHERE id = ?', [$nomor, $id]);
    return ['id' => $id, 'nomor' => $nomor, 'kode' => $kode];
}

function lsp_name(int $id): string
{
    return (string)q('SELECT nama FROM lsp WHERE id = ?', [$id])->fetchColumn();
}

/** Baris permohonan lengkap untuk tampilan. */
function permohonan_sql(): string
{
    return "SELECT p.*, s.nama AS skema_nama, s.kode AS skema_kode, s.harga, l.nama AS lsp_nama, u.nama AS asesi_nama, u.email AS asesi_email,
                   j.tanggal, j.jam, j.metode, j.asesor_id AS jadwal_asesor_id, j.status AS jadwal_status, t.nama AS tuk_nama, t.id AS tuk_id,
                   a.nama AS asesor_nama, pa.nama AS penguji_nama, po.nama AS pleno_nama
            FROM permohonan p JOIN skema s ON s.id = p.skema_id JOIN lsp l ON l.id = p.lsp_id JOIN users u ON u.id = p.asesi_id
            JOIN jadwal j ON j.id = p.jadwal_id LEFT JOIN tuk t ON t.id = j.tuk_id LEFT JOIN users a ON a.id = j.asesor_id
            LEFT JOIN users pa ON pa.id = p.asesor_id LEFT JOIN users po ON po.id = p.pleno_oleh";
}

function permohonan_row(array $r, bool $detail = false): array
{
    $out = [
        'id' => (int)$r['id'], 'status' => $r['status'], 'status_label' => P_STATUS[$r['status']] ?? $r['status'],
        'lsp_id' => (int)$r['lsp_id'], 'lsp_nama' => $r['lsp_nama'], 'skema_id' => (int)$r['skema_id'], 'skema_nama' => $r['skema_nama'],
        'skema_kode' => $r['skema_kode'], 'harga' => (int)$r['harga'], 'asesi_id' => (int)$r['asesi_id'], 'asesi_nama' => $r['asesi_nama'],
        'jadwal_id' => (int)$r['jadwal_id'], 'tanggal' => $r['tanggal'], 'jam' => $r['jam'], 'metode' => $r['metode'], 'tuk_nama' => $r['tuk_nama'],
        'asesor_nama' => $r['asesor_nama'], 'penguji_nama' => $r['penguji_nama'], 'pleno_nama' => $r['pleno_nama'],
        'tujuan' => $r['tujuan'], 'catatan_admin' => $r['catatan_admin'], 'rekom_pra' => $r['rekom_pra'], 'catatan_pra' => $r['catatan_pra'],
        'rekomendasi' => $r['rekomendasi'], 'catatan_asesor' => $r['catatan_asesor'], 'keputusan' => $r['keputusan'], 'catatan_pleno' => $r['catatan_pleno'],
        'diajukan_at' => $r['diajukan_at'], 'diputuskan_at' => $r['diputuskan_at'], 'updated_at' => $r['updated_at'],
    ];
    if ($detail) {
        $out['units'] = units_of((int)$r['skema_id']);
        $out['apl02'] = json_decode((string)$r['apl02'], true) ?: new stdClass();
        $out['hasil'] = json_decode((string)$r['hasil'], true) ?: new stdClass();
        $out['asesi_email'] = $r['asesi_email'];
        $prof = q('SELECT nik_enc, no_hp, tanggal_lahir, jenis_kelamin FROM asesi_profiles WHERE user_id = ?', [$r['asesi_id']])->fetch();
        $out['asesi_nik'] = $prof ? mask_nik(decrypt_text($prof['nik_enc'])) : null;
        $out['asesi_hp'] = $prof['no_hp'] ?? null;
        $out['asesi_tgl_lahir'] = $prof['tanggal_lahir'] ?? null;
        $out['dokumen'] = array_map('dokumen_row', q('SELECT * FROM dokumen WHERE user_id = ? ORDER BY jenis', [$r['asesi_id']])->fetchAll());
    }
    return $out;
}

/** Ambil permohonan dalam cakupan staf LSP (404 bila di luar cakupan). */
function lsp_permohonan(int $id, array $m): array
{
    $scope = scope_lsp($m);
    $r = q(permohonan_sql() . ' WHERE p.id = ?' . ($scope === null ? '' : ' AND p.lsp_id = ?'), $scope === null ? [$id] : [$id, $scope])->fetch();
    if (!$r) {
        audit('access.denied', 'permohonan:' . $id);
        fail('Permohonan tidak ditemukan.', 404);
    }
    return $r;
}

function set_status(int $id, string $from, string $to, array $extra = []): void
{
    $sets = 'status = ?, updated_at = ?';
    $vals = [$to, now()];
    foreach ($extra as $k => $v) {
        $sets .= ", $k = ?";
        $vals[] = $v;
    }
    $vals[] = $id;
    $vals[] = $from;
    // Kondisi status di WHERE mencegah dua pengguna memproses permohonan yang sama bersamaan.
    if (q("UPDATE permohonan SET $sets WHERE id = ? AND status = ?", $vals)->rowCount() !== 1) {
        fail('Status permohonan sudah berubah. Muat ulang halaman.', 409);
    }
}

/* =========================== Dokumen =========================== */

function dokumen_row(array $d): array
{
    return ['id' => (int)$d['id'], 'jenis' => $d['jenis'], 'label' => DOK_JENIS[$d['jenis']] ?? $d['jenis'], 'nama_file' => $d['nama_file'],
        'mime' => $d['mime'], 'ukuran' => (int)$d['ukuran'], 'created_at' => $d['created_at']];
}

function r_dokumen(): void
{
    require_method('GET');
    $u = require_auth();
    $rows = q('SELECT * FROM dokumen WHERE user_id = ? ORDER BY jenis', [$u['id']])->fetchAll();
    json_out(['items' => array_map('dokumen_row', $rows), 'jenis' => DOK_JENIS, 'wajib' => DOK_WAJIB]);
}

function sniff_mime(string $path): string
{
    $head = (string)file_get_contents($path, false, null, 0, 8);
    if (strncmp($head, '%PDF-', 5) === 0) {
        return 'application/pdf';
    }
    if ($head === "\x89PNG\r\n\x1a\n") {
        return 'image/png';
    }
    if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
        return 'image/jpeg';
    }
    return 'application/octet-stream';
}

function r_dokumen_upload(): void
{
    require_method('POST');
    csrf_check();
    $u = require_perm('profile.own');
    $uid = uid();
    $jenis = is_string($_POST['jenis'] ?? null) ? $_POST['jenis'] : '';
    if (!isset(DOK_JENIS[$jenis])) {
        fail('Jenis dokumen tidak valid.', 422);
    }
    $f = $_FILES['file'] ?? null;
    if (is_array($f) && in_array($f['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        fail('Ukuran berkas maksimal 2 MB.', 422);
    }
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        fail('Pilih berkas untuk diunggah.', 422);
    }
    if ($f['size'] > DOK_MAX) {
        fail('Ukuran berkas maksimal 2 MB.', 422);
    }
    // Jenis berkas ditentukan dari isi berkas (magic bytes), bukan dari nama atau header kiriman.
    $mime = sniff_mime($f['tmp_name']);
    if (!isset(DOK_MIME[$mime])) {
        fail('Format berkas harus PDF, JPG, atau PNG.', 422);
    }
    $dir = APP_ROOT . '/storage/uploads/' . $uid;
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $rel = 'uploads/' . $uid . '/' . bin2hex(random_bytes(16)) . '.' . DOK_MIME[$mime];
    if (!move_uploaded_file($f['tmp_name'], APP_ROOT . '/storage/' . $rel)) {
        fail('Berkas gagal disimpan. Coba lagi.', 500);
    }
    chmod(APP_ROOT . '/storage/' . $rel, 0600);
    $nama = cut(preg_replace('/[^\pL\pN ._()-]/u', '_', basename((string)$f['name'])), 150);
    $old = q('SELECT id, path FROM dokumen WHERE user_id = ? AND jenis = ?', [$uid, $jenis])->fetch();
    if ($old) {
        q('UPDATE dokumen SET nama_file = ?, path = ?, mime = ?, ukuran = ?, created_at = ? WHERE id = ?', [$nama, $rel, $mime, (int)$f['size'], now(), $old['id']]);
        if (strpos($old['path'], 'uploads/contoh/') !== 0) {
            @unlink(APP_ROOT . '/storage/' . $old['path']);
        }
    } else {
        q('INSERT INTO dokumen (user_id, jenis, nama_file, path, mime, ukuran, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$uid, $jenis, $nama, $rel, $mime, (int)$f['size'], now()]);
    }
    audit('dokumen.upload', $jenis);
    json_out(['ok' => true], 201);
}

/** Boleh melihat dokumen: pemilik, staf LSP tempat asesi mengajukan permohonan, atau asesor yang ditugaskan. */
function can_view_docs_of(int $ownerId): bool
{
    $me = uid();
    if ($me === $ownerId) {
        return true;
    }
    $m = active_membership();
    if (is_platform($m)) {
        return true;
    }
    if ($m && $m['lsp_id'] !== null && (can('registration.verify') || can('assessment.monitor'))) {
        if (q('SELECT 1 FROM permohonan WHERE asesi_id = ? AND lsp_id = ?', [$ownerId, $m['lsp_id']])->fetch()) {
            return true;
        }
    }
    return (bool)q("SELECT 1 FROM permohonan p JOIN jadwal j ON j.id = p.jadwal_id JOIN memberships m ON m.user_id = j.asesor_id AND m.lsp_id = p.lsp_id
                    AND m.role = 'asesor' AND m.status = 'aktif' WHERE p.asesi_id = ? AND j.asesor_id = ?", [$ownerId, $me])->fetch();
}

function r_dokumen_file(): void
{
    require_method('GET');
    require_auth();
    $id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
    $d = q('SELECT * FROM dokumen WHERE id = ?', [$id])->fetch();
    if (!$d || !can_view_docs_of((int)$d['user_id'])) {
        audit('access.denied', 'dokumen:' . $id);
        fail('Dokumen tidak ditemukan.', 404);
    }
    $path = APP_ROOT . '/storage/' . $d['path'];
    if (!is_file($path)) {
        fail('Berkas tidak ditemukan.', 404);
    }
    if ((int)$d['user_id'] !== uid()) {
        audit('dokumen.lihat', 'dokumen:' . $id);
    }
    header('Content-Type: ' . $d['mime']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $d['nama_file']) . '"');
    header('Cache-Control: private, no-store');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    readfile($path);
    exit;
}

/* =========================== Asesi =========================== */

function r_asesi_apply(): void
{
    require_method('POST');
    csrf_check();
    require_perm('application.own');
    $u = current_user();
    $uid = uid();
    if (empty($u['email_verified_at'])) {
        fail('Verifikasi email Anda terlebih dahulu sebelum mendaftar uji kompetensi.', 403);
    }
    $j = q("SELECT j.*, s.nama AS skema_nama, s.harga, s.status AS skema_status, l.status AS lsp_status
            FROM jadwal j JOIN skema s ON s.id = j.skema_id JOIN lsp l ON l.id = j.lsp_id WHERE j.id = ?", [int_in('jadwal_id')])->fetch();
    if (!$j || $j['status'] !== 'dibuka' || $j['skema_status'] !== 'aktif' || $j['lsp_status'] !== 'aktif' || $j['tanggal'] < today()) {
        fail('Jadwal tidak tersedia. Pilih jadwal lain.', 422);
    }
    if (!q("SELECT 1 FROM listings WHERE skema_id = ? AND status = 'tayang'", [$j['skema_id']])->fetch()) {
        fail('Skema ini belum dibuka untuk pendaftaran publik.', 422);
    }
    if (jadwal_sisa((int)$j['id'], (int)$j['kuota']) < 1) {
        fail('Kuota jadwal ini sudah penuh. Pilih jadwal lain.', 409);
    }
    $open = q("SELECT 1 FROM permohonan WHERE asesi_id = ? AND skema_id = ? AND status IN ('diajukan','perbaikan','menunggu_bayar','pra_asesmen','siap_uji','menunggu_pleno')",
        [$uid, $j['skema_id']])->fetch();
    if ($open) {
        fail('Anda masih punya permohonan aktif untuk skema ini.', 409);
    }
    $have = q('SELECT jenis FROM dokumen WHERE user_id = ?', [$uid])->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_diff(DOK_WAJIB, $have);
    if ($missing) {
        fail('Lengkapi dokumen: ' . implode(', ', array_map(function ($k) { return DOK_JENIS[$k]; }, $missing)) . '.', 422);
    }
    $apl = unit_map_in('apl02', (int)$j['skema_id'], 'asesmen mandiri (APL.02)');
    if ((body()['consent'] ?? false) !== true) {
        fail('Setujui pembagian data diri dan dokumen Anda ke LSP ini.', 422);
    }
    $tujuan = str_in('tujuan', 80, 'Tujuan asesmen');
    if (!in_array($tujuan, ['Sertifikasi', 'Sertifikasi ulang', 'Pengakuan Kompetensi Terkini (PKT)', 'Rekognisi Pembelajaran Lampau (RPL)', 'Lainnya'], true)) {
        $tujuan = 'Sertifikasi';
    }
    $t = now();
    db()->beginTransaction();
    try {
        // Data asesi baru dibagikan ke LSP saat ia mendaftar skema di LSP itu (keanggotaan asesi per LSP).
        $mem = q("SELECT id, status FROM memberships WHERE user_id = ? AND lsp_id = ? AND role = 'asesi'", [$uid, $j['lsp_id']])->fetch();
        if (!$mem) {
            q('INSERT INTO memberships (user_id, lsp_id, role, status, created_at) VALUES (?, ?, ?, ?, ?)', [$uid, $j['lsp_id'], 'asesi', 'aktif', $t]);
        }
        q("INSERT INTO permohonan (lsp_id, asesi_id, skema_id, jadwal_id, status, tujuan, apl02, created_at, updated_at, diajukan_at)
           VALUES (?, ?, ?, ?, 'diajukan', ?, ?, ?, ?, ?)", [$j['lsp_id'], $uid, $j['skema_id'], $j['id'], $tujuan, $apl, $t, $t, $t]);
        $pid = (int)db()->lastInsertId();
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    audit('permohonan.diajukan', 'permohonan:' . $pid, $uid, (int)$j['lsp_id']);
    notify(users_with_perm('registration.verify', (int)$j['lsp_id']), (int)$j['lsp_id'], 'permohonan.baru', 'Pendaftaran asesi baru',
        $u['nama'] . ' mendaftar skema ' . $j['skema_nama'] . ' untuk jadwal ' . $j['tanggal'] . '.', 'daftar');
    notify([$uid], (int)$j['lsp_id'], 'permohonan.diajukan', 'Pendaftaran diterima',
        'Pendaftaran skema ' . $j['skema_nama'] . ' di ' . lsp_name((int)$j['lsp_id']) . ' sedang diverifikasi LSP.', 'jadwal');
    json_out(['id' => $pid], 201);
}

function own_permohonan(int $id): array
{
    $r = q(permohonan_sql() . ' WHERE p.id = ? AND p.asesi_id = ?', [$id, uid()])->fetch();
    if (!$r) {
        fail('Permohonan tidak ditemukan.', 404);
    }
    return $r;
}

function r_asesi_permohonan(): void
{
    require_method('GET');
    require_perm('application.own');
    $rows = q(permohonan_sql() . ' WHERE p.asesi_id = ? ORDER BY p.id DESC', [uid()])->fetchAll();
    $items = [];
    foreach ($rows as $r) {
        $x = permohonan_row($r, true);
        $tg = q('SELECT id, nomor, jumlah, status FROM tagihan WHERE permohonan_id = ? ORDER BY id DESC', [$r['id']])->fetch();
        $x['tagihan'] = $tg ? ['id' => (int)$tg['id'], 'nomor' => $tg['nomor'], 'jumlah' => (int)$tg['jumlah'], 'status' => $tg['status']] : null;
        $sc = q('SELECT id, nomor, kode FROM sertifikat WHERE permohonan_id = ?', [$r['id']])->fetch();
        $x['sertifikat'] = $sc ? ['id' => (int)$sc['id'], 'nomor' => $sc['nomor'], 'kode' => $sc['kode']] : null;
        unset($x['dokumen']);
        $items[] = $x;
    }
    json_out(['items' => $items]);
}

function r_asesi_resubmit(): void
{
    require_method('POST');
    csrf_check();
    require_perm('application.own');
    $p = own_permohonan(int_in('id'));
    if ($p['status'] !== 'perbaikan') {
        fail('Permohonan ini tidak sedang menunggu perbaikan.', 409);
    }
    $extra = ['diajukan_at' => now()];
    if (isset(body()['apl02'])) {
        $extra['apl02'] = unit_map_in('apl02', (int)$p['skema_id'], 'asesmen mandiri (APL.02)');
    }
    set_status((int)$p['id'], 'perbaikan', 'diajukan', $extra);
    audit('permohonan.diajukan_ulang', 'permohonan:' . $p['id']);
    notify(users_with_perm('registration.verify', (int)$p['lsp_id']), (int)$p['lsp_id'], 'permohonan.perbaikan_dikirim', 'Perbaikan berkas dikirim',
        $p['asesi_nama'] . ' mengirim perbaikan untuk skema ' . $p['skema_nama'] . '.', 'daftar');
    json_out(['ok' => true]);
}

function r_asesi_cancel(): void
{
    require_method('POST');
    csrf_check();
    require_perm('application.own');
    $p = own_permohonan(int_in('id'));
    if (!in_array($p['status'], ['diajukan', 'perbaikan', 'menunggu_bayar'], true)) {
        fail('Permohonan yang sudah dibayar tidak bisa dibatalkan di sini. Hubungi LSP untuk pengembalian dana.', 409);
    }
    set_status((int)$p['id'], $p['status'], 'dibatalkan');
    q("UPDATE tagihan SET status = 'batal' WHERE permohonan_id = ? AND status = 'belum'", [$p['id']]);
    audit('permohonan.dibatalkan', 'permohonan:' . $p['id']);
    json_out(['ok' => true]);
}

function tagihan_row(array $t): array
{
    return ['id' => (int)$t['id'], 'nomor' => $t['nomor'], 'deskripsi' => $t['deskripsi'], 'jumlah' => (int)$t['jumlah'], 'status' => $t['status'],
        'metode' => $t['metode'], 'dibayar_at' => $t['dibayar_at'], 'jatuh_tempo' => $t['jatuh_tempo'], 'created_at' => $t['created_at'],
        'lsp_nama' => $t['lsp_nama'] ?? null, 'nama' => $t['nama'] ?? null, 'permohonan_id' => $t['permohonan_id'] === null ? null : (int)$t['permohonan_id']];
}

function r_asesi_tagihan(): void
{
    require_method('GET');
    require_perm('payment.own');
    $rows = q('SELECT t.*, l.nama AS lsp_nama FROM tagihan t JOIN lsp l ON l.id = t.lsp_id WHERE t.user_id = ? ORDER BY t.id DESC', [uid()])->fetchAll();
    json_out(['items' => array_map('tagihan_row', $rows)]);
}

/** Pelunasan tagihan (dipakai pembayaran asesi dan konfirmasi manual oleh keuangan LSP). */
function settle_invoice(array $t, string $metode): void
{
    if (q("UPDATE tagihan SET status = 'lunas', metode = ?, dibayar_at = ? WHERE id = ? AND status = 'belum'", [$metode, now(), $t['id']])->rowCount() !== 1) {
        fail('Tagihan ini sudah dibayar atau dibatalkan.', 409);
    }
    audit('tagihan.lunas', 'tagihan:' . $t['id'], null, (int)$t['lsp_id']);
    if ($t['permohonan_id']) {
        $p = q(permohonan_sql() . ' WHERE p.id = ?', [$t['permohonan_id']])->fetch();
        if ($p && $p['status'] === 'menunggu_bayar') {
            set_status((int)$p['id'], 'menunggu_bayar', 'pra_asesmen', ['dibayar_at' => now()]);
            notify([(int)$p['asesi_id']], (int)$p['lsp_id'], 'tagihan.lunas', 'Pembayaran diterima',
                'Pembayaran ' . $t['nomor'] . ' lunas. Permohonan ' . $p['skema_nama'] . ' masuk tahap pra-asesmen.', 'jadwal');
            if ($p['jadwal_asesor_id']) {
                notify([(int)$p['jadwal_asesor_id']], (int)$p['lsp_id'], 'pra.baru', 'Pra-asesmen baru',
                    $p['asesi_nama'] . ' (' . $p['skema_nama'] . ', ' . $p['tanggal'] . ') siap ditinjau.', 'pra');
            }
        }
    }
    if ($t['kelas_peserta_id']) {
        q("UPDATE kelas_peserta SET status = 'aktif' WHERE id = ?", [$t['kelas_peserta_id']]);
    }
}

function r_asesi_bayar(): void
{
    require_method('POST');
    csrf_check();
    require_perm('payment.own');
    $t = q('SELECT * FROM tagihan WHERE id = ? AND user_id = ?', [int_in('id'), uid()])->fetch();
    if (!$t) {
        fail('Tagihan tidak ditemukan.', 404);
    }
    $metode = str_in('metode', 40);
    if (!in_array($metode, ['Virtual Account BCA', 'Virtual Account BRI', 'Virtual Account Mandiri', 'QRIS', 'E-wallet'], true)) {
        fail('Pilih metode pembayaran.', 422);
    }
    // Purwarupa: pembayaran langsung dianggap berhasil (belum terhubung payment gateway).
    settle_invoice($t, $metode);
    json_out(['ok' => true]);
}

function sertifikat_row(array $s): array
{
    return ['id' => (int)$s['id'], 'nomor' => $s['nomor'], 'kode' => $s['kode'], 'terbit' => $s['terbit'], 'berlaku' => $s['berlaku'], 'status' => $s['status'],
        'skema_nama' => $s['skema_nama'], 'lsp_nama' => $s['lsp_nama'], 'nama' => $s['nama'] ?? null, 'url' => app_url() . '/?cek=' . $s['kode']];
}

function sertifikat_sql(): string
{
    return 'SELECT c.*, s.nama AS skema_nama, l.nama AS lsp_nama, u.nama AS nama FROM sertifikat c JOIN skema s ON s.id = c.skema_id
            JOIN lsp l ON l.id = c.lsp_id JOIN users u ON u.id = c.user_id';
}

function r_asesi_sertifikat(): void
{
    require_method('GET');
    require_perm('certificate.own');
    json_out(['items' => array_map('sertifikat_row', q(sertifikat_sql() . ' WHERE c.user_id = ? ORDER BY c.terbit DESC', [uid()])->fetchAll())]);
}

/** Lembar sertifikat siap cetak (pemilik, staf LSP penerbit, atau Admin Platform). */
function r_sertifikat_cetak(): void
{
    require_method('GET');
    require_auth();
    $id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
    $c = q(sertifikat_sql() . ' WHERE c.id = ?', [$id])->fetch();
    $m = active_membership();
    $ok = $c && ((int)$c['user_id'] === uid() || is_platform($m) || ($m['lsp_id'] !== null && (int)$m['lsp_id'] === (int)$c['lsp_id'] && can('decision.manage')));
    if (!$ok) {
        fail('Sertifikat tidak ditemukan.', 404);
    }
    $e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
    $units = units_of((int)$c['skema_id']);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    $rows = '';
    foreach ($units as $u) {
        $rows .= '<tr><td>' . $e($u['kode']) . '</td><td>' . $e($u['judul']) . '</td></tr>';
    }
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Sertifikat ' . $e($c['nomor']) . '</title>'
        . '<style>body{font-family:Georgia,serif;margin:0;padding:40px;color:#0e1a3a}.s{border:10px double #1d4ed8;padding:40px;max-width:820px;margin:auto;text-align:center}'
        . 'h1{font-size:30px;letter-spacing:.08em;margin:.2em 0}h2{font-size:26px;margin:.4em 0}table{width:100%;border-collapse:collapse;margin-top:18px;font:13px system-ui,sans-serif;text-align:left}'
        . 'td{border-top:1px solid #dbe4f3;padding:6px}.m{font:13px system-ui,sans-serif;color:#5b6889}@media print{body{padding:0}}</style>'
        . '<div class="s"><p class="m">' . $e($c['lsp_nama']) . '</p><h1>SERTIFIKAT KOMPETENSI</h1><p class="m">Nomor ' . $e($c['nomor']) . '</p>'
        . '<p>Diberikan kepada</p><h2>' . $e($c['nama']) . '</h2><p>dinyatakan <b>KOMPETEN</b> pada skema</p><h2>' . $e($c['skema_nama']) . '</h2>'
        . '<p class="m">Terbit ' . $e($c['terbit']) . ' · Berlaku sampai ' . $e($c['berlaku']) . ' · Status ' . $e($c['status']) . '</p>'
        . '<table>' . $rows . '</table><p class="m" style="margin-top:20px">Verifikasi keaslian: ' . $e(app_url() . '/?cek=' . $c['kode']) . '</p></div></html>';
    exit;
}

function r_asesi_kelas(): void
{
    require_method('GET');
    require_perm('class.own');
    $rows = q("SELECT k.id, k.status, k.progres, k.created_at, l.id AS listing_id, l.judul, l.format, l.kota, l.harga, l.deskripsi, s.nama AS lsp_nama
               FROM kelas_peserta k JOIN listings l ON l.id = k.listing_id JOIN lsp s ON s.id = l.lsp_id WHERE k.user_id = ? ORDER BY k.id DESC", [uid()])->fetchAll();
    json_out(['items' => array_map(function ($r) {
        return ['id' => (int)$r['id'], 'status' => $r['status'], 'progres' => (int)$r['progres'], 'listing_id' => (int)$r['listing_id'], 'judul' => $r['judul'],
            'format' => $r['format'], 'kota' => $r['kota'], 'harga' => (int)$r['harga'], 'deskripsi' => $r['deskripsi'], 'lsp_nama' => $r['lsp_nama']];
    }, $rows)]);
}

function r_asesi_kelas_daftar(): void
{
    require_method('POST');
    csrf_check();
    require_perm('class.own');
    $l = q("SELECT * FROM listings WHERE id = ? AND tipe = 'pelatihan' AND status = 'tayang'", [int_in('listing_id')])->fetch();
    if (!$l) {
        fail('Kelas tidak ditemukan.', 404);
    }
    if (q('SELECT 1 FROM kelas_peserta WHERE listing_id = ? AND user_id = ?', [$l['id'], uid()])->fetch()) {
        fail('Anda sudah terdaftar di kelas ini.', 409);
    }
    $gratis = (int)$l['harga'] === 0;
    q('INSERT INTO kelas_peserta (listing_id, user_id, status, progres, created_at) VALUES (?, ?, ?, 0, ?)', [$l['id'], uid(), $gratis ? 'aktif' : 'menunggu_bayar', now()]);
    $kid = (int)db()->lastInsertId();
    if (!$gratis) {
        new_invoice((int)$l['lsp_id'], null, $kid, uid(), 'Kelas: ' . $l['judul'], (int)$l['harga']);
    }
    audit('kelas.daftar', 'listing:' . $l['id']);
    json_out(['ok' => true, 'perlu_bayar' => !$gratis], 201);
}

function r_asesi_kelas_progres(): void
{
    require_method('POST');
    csrf_check();
    require_perm('class.own');
    $p = max(0, min(100, int_in('progres')));
    $n = q("UPDATE kelas_peserta SET progres = ?, status = ? WHERE id = ? AND user_id = ? AND status IN ('aktif','selesai')",
        [$p, $p >= 100 ? 'selesai' : 'aktif', int_in('id'), uid()])->rowCount();
    if ($n !== 1) {
        fail('Kelas belum aktif. Selesaikan pembayaran dulu.', 409);
    }
    json_out(['ok' => true]);
}

/* =========================== Asesor =========================== */

/** LSP tempat pengguna aktif sebagai asesor. */
function asesor_lsps(): array
{
    return array_map('intval', q("SELECT lsp_id FROM memberships WHERE user_id = ? AND role = 'asesor' AND status = 'aktif' AND lsp_id IS NOT NULL", [uid()])->fetchAll(PDO::FETCH_COLUMN));
}

function in_list_sql(array $ids): string
{
    return $ids ? implode(',', array_fill(0, count($ids), '?')) : 'NULL';
}

function r_asesor_jadwal(): void
{
    require_method('GET');
    require_perm('asesor.dashboard');
    $l = asesor_lsps();
    $rows = $l ? q('SELECT j.*, s.nama AS skema_nama, ls.nama AS lsp_nama, t.nama AS tuk_nama FROM jadwal j JOIN skema s ON s.id = j.skema_id
                    JOIN lsp ls ON ls.id = j.lsp_id LEFT JOIN tuk t ON t.id = j.tuk_id
                    WHERE j.asesor_id = ? AND j.lsp_id IN (' . in_list_sql($l) . ') ORDER BY j.tanggal', array_merge([uid()], $l))->fetchAll() : [];
    json_out(['items' => array_map('jadwal_row', $rows)]);
}

/** Permohonan pada jadwal yang ditugaskan ke asesor ini, dengan status tertentu. */
function asesor_permohonan(array $statuses, int $id = 0): array
{
    $l = asesor_lsps();
    if (!$l) {
        return [];
    }
    $sql = permohonan_sql() . ' WHERE j.asesor_id = ? AND p.lsp_id IN (' . in_list_sql($l) . ') AND p.status IN (' . in_list_sql($statuses) . ')';
    $params = array_merge([uid()], $l, $statuses);
    if ($id) {
        $sql .= ' AND p.id = ?';
        $params[] = $id;
    }
    return q($sql . ' ORDER BY j.tanggal, p.id', $params)->fetchAll();
}

function r_asesor_pra(): void
{
    require_method('GET');
    require_perm('preassessment.review');
    json_out(['items' => array_map(function ($r) { return permohonan_row($r, true); }, asesor_permohonan(['pra_asesmen']))]);
}

function r_asesor_pra_putus(): void
{
    require_method('POST');
    csrf_check();
    require_perm('preassessment.review');
    $rows = asesor_permohonan(['pra_asesmen'], int_in('id'));
    if (!$rows) {
        fail('Permohonan tidak ditemukan di daftar tinjauan Anda.', 404);
    }
    $p = $rows[0];
    $rekom = str_in('rekom', 10);
    $cat = str_in('catatan', 500, 'Catatan');
    if (!in_array($rekom, ['lanjut', 'tidak'], true)) {
        fail('Pilih rekomendasi.', 422);
    }
    if ($rekom === 'tidak' && text_len($cat) < 5) {
        fail('Tulis alasan untuk asesi (minimal 5 karakter).', 422);
    }
    set_status((int)$p['id'], 'pra_asesmen', $rekom === 'lanjut' ? 'siap_uji' : 'tidak_lanjut', ['rekom_pra' => $rekom, 'catatan_pra' => $cat ?: null]);
    audit('pra.' . $rekom, 'permohonan:' . $p['id'], null, (int)$p['lsp_id']);
    notify([(int)$p['asesi_id']], (int)$p['lsp_id'], 'pra.' . $rekom, $rekom === 'lanjut' ? 'Pra-asesmen selesai: siap uji' : 'Pra-asesmen: belum dapat dilanjutkan',
        $rekom === 'lanjut' ? "Anda siap mengikuti uji {$p['skema_nama']} pada {$p['tanggal']}" . ($p['tuk_nama'] ? " di {$p['tuk_nama']}." : '.') : "Catatan asesor: $cat", 'jadwal');
    json_out(['ok' => true]);
}

function r_asesor_asesmen(): void
{
    require_method('GET');
    require_perm('assessment.conduct');
    json_out(['items' => array_map(function ($r) { return permohonan_row($r, true); }, asesor_permohonan(['siap_uji']))]);
}

function r_asesor_asesmen_simpan(): void
{
    require_method('POST');
    csrf_check();
    require_perm('assessment.conduct');
    $rows = asesor_permohonan(['siap_uji'], int_in('id'));
    if (!$rows) {
        fail('Permohonan tidak ditemukan di daftar asesmen Anda.', 404);
    }
    $p = $rows[0];
    if ($p['tanggal'] > today()) {
        fail('Asesmen baru bisa diisi pada atau setelah tanggal uji (' . $p['tanggal'] . ').', 409);
    }
    $hasil = unit_map_in('hasil', (int)$p['skema_id'], 'hasil asesmen');
    $cat = str_in('catatan', 1000, 'Catatan');
    $rekom = in_array('BK', json_decode($hasil, true), true) ? 'BK' : 'K';
    set_status((int)$p['id'], 'siap_uji', 'menunggu_pleno', ['hasil' => $hasil, 'rekomendasi' => $rekom, 'catatan_asesor' => $cat ?: null,
        'asesor_id' => uid(), 'diuji_at' => now()]);
    audit('asesmen.' . $rekom, 'permohonan:' . $p['id'], null, (int)$p['lsp_id']);
    notify(users_with_perm('decision.manage', (int)$p['lsp_id']), (int)$p['lsp_id'], 'pleno.baru', 'Berkas menunggu pleno',
        "Asesmen {$p['asesi_nama']} ({$p['skema_nama']}) selesai dengan rekomendasi $rekom.", 'plenoA');
    json_out(['ok' => true, 'rekomendasi' => $rekom]);
}

/** Berkas pleno: asesor (bukan penguji berkas itu) di LSP tempat ia aktif; atau staf dengan decision.manage dalam cakupan. */
function pleno_rows(int $id = 0): array
{
    $m = active_membership();
    $me = uid();
    $where = ["p.status = 'menunggu_pleno'", '(p.asesor_id IS NULL OR p.asesor_id <> ?)'];
    $params = [$me];
    if (can('decision.manage')) {
        $scope = scope_lsp($m);
        if ($scope !== null) {
            $where[] = 'p.lsp_id = ?';
            $params[] = $scope;
        }
    } else {
        $l = asesor_lsps();
        if (!$l) {
            return [];
        }
        $where[] = 'p.lsp_id IN (' . in_list_sql($l) . ')';
        $params = array_merge($params, $l);
    }
    if ($id) {
        $where[] = 'p.id = ?';
        $params[] = $id;
    }
    return q(permohonan_sql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY p.diuji_at, p.id', $params)->fetchAll();
}

function r_pleno(): void
{
    require_method('GET');
    if (!can('decision.manage') && !can('pleno.participate')) {
        require_perm('decision.manage');
    }
    require_auth();
    $items = array_map(function ($r) { return permohonan_row($r, true); }, pleno_rows());
    // Berkas yang diuji sendiri ditampilkan terpisah agar jelas mengapa tidak bisa diputuskan.
    $own = q(permohonan_sql() . " WHERE p.status = 'menunggu_pleno' AND p.asesor_id = ?", [uid()])->fetchAll();
    json_out(['items' => $items, 'diuji_sendiri' => array_map(function ($r) { return permohonan_row($r); }, $own)]);
}

function r_pleno_putus(): void
{
    require_method('POST');
    csrf_check();
    if (!can('decision.manage') && !can('pleno.participate')) {
        require_perm('decision.manage');
    }
    require_auth();
    $id = int_in('id');
    $chk = q('SELECT asesor_id FROM permohonan WHERE id = ?', [$id])->fetch();
    if ($chk && (int)$chk['asesor_id'] === uid()) {
        audit('pleno.ditolak_independensi', 'permohonan:' . $id);
        fail('Anda tidak boleh memutuskan pleno untuk asesmen yang Anda uji sendiri.', 403);
    }
    $rows = pleno_rows($id);
    if (!$rows) {
        fail('Berkas pleno tidak ditemukan.', 404);
    }
    $p = $rows[0];
    $k = str_in('keputusan', 5);
    $cat = str_in('catatan', 500, 'Catatan');
    if (!in_array($k, ['K', 'BK'], true)) {
        fail('Pilih keputusan Kompeten (K) atau Belum Kompeten (BK).', 422);
    }
    db()->beginTransaction();
    try {
        set_status((int)$p['id'], 'menunggu_pleno', $k === 'K' ? 'kompeten' : 'belum_kompeten',
            ['keputusan' => $k, 'catatan_pleno' => $cat ?: null, 'pleno_oleh' => uid(), 'diputuskan_at' => now()]);
        $cert = $k === 'K' ? issue_certificate($p) : null;
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    audit('pleno.' . $k, 'permohonan:' . $p['id'], null, (int)$p['lsp_id']);
    notify([(int)$p['asesi_id']], (int)$p['lsp_id'], 'pleno.' . $k, $k === 'K' ? 'Selamat, Anda kompeten' : 'Hasil uji: belum kompeten',
        $k === 'K' ? "Sertifikat {$p['skema_nama']} nomor {$cert['nomor']} sudah terbit di dompet sertifikat Anda."
            : "Hasil pleno skema {$p['skema_nama']}: belum kompeten." . ($cat ? " Catatan: $cat" : '') . ' Anda dapat mengajukan banding ke LSP.', $k === 'K' ? 'sertifikat' : 'jadwal');
    json_out(['ok' => true, 'sertifikat' => $cert]);
}

function r_asesor_riwayat(): void
{
    require_method('GET');
    require_perm('asesor.history');
    $rows = q(permohonan_sql() . ' WHERE p.asesor_id = ? ORDER BY p.diuji_at DESC', [uid()])->fetchAll();
    json_out(['items' => array_map(function ($r) { return permohonan_row($r) + ['diuji_at' => $r['diuji_at']]; }, $rows)]);
}

function r_asesor_honor(): void
{
    require_method('GET');
    require_perm('asesor.honor');
    $rows = q('SELECT p.lsp_id, l.nama AS lsp_nama, l.honor_per_asesi, p.diuji_at FROM permohonan p JOIN lsp l ON l.id = p.lsp_id
               WHERE p.asesor_id = ? AND p.diuji_at IS NOT NULL ORDER BY p.diuji_at DESC', [uid()])->fetchAll();
    $agg = [];
    foreach ($rows as $r) {
        $bulan = substr($r['diuji_at'], 0, 7);
        $k = $r['lsp_id'] . '|' . $bulan;
        $agg[$k] = $agg[$k] ?? ['lsp_nama' => $r['lsp_nama'], 'bulan' => $bulan, 'asesi' => 0, 'tarif' => (int)$r['honor_per_asesi']];
        $agg[$k]['asesi']++;
    }
    $items = array_values(array_map(function ($a) {
        $a['total'] = $a['asesi'] * $a['tarif'];
        $a['status'] = $a['bulan'] < date('Y-m') ? 'dibayar' : 'diproses';
        return $a;
    }, $agg));
    json_out(['items' => $items, 'total' => array_sum(array_column($items, 'total'))]);
}

/* =========================== Jadwal & chat =========================== */

function jadwal_row(array $j): array
{
    $peserta = (int)q('SELECT COUNT(*) FROM permohonan WHERE jadwal_id = ? AND status IN (' . in_list_sql(P_AKTIF) . ')', array_merge([$j['id']], P_AKTIF))->fetchColumn();
    return ['id' => (int)$j['id'], 'lsp_id' => (int)$j['lsp_id'], 'lsp_nama' => $j['lsp_nama'] ?? null, 'skema_id' => (int)$j['skema_id'], 'skema_nama' => $j['skema_nama'] ?? null,
        'tuk_id' => $j['tuk_id'] === null ? null : (int)$j['tuk_id'], 'tuk_nama' => $j['tuk_nama'] ?? null, 'tanggal' => $j['tanggal'], 'jam' => $j['jam'],
        'metode' => $j['metode'], 'kuota' => (int)$j['kuota'], 'peserta' => $peserta, 'sisa' => max(0, (int)$j['kuota'] - $peserta),
        'asesor_id' => $j['asesor_id'] === null ? null : (int)$j['asesor_id'], 'asesor_nama' => $j['asesor_nama'] ?? null, 'status' => $j['status'], 'catatan' => $j['catatan']];
}

/** Boleh ikut chat jadwal: asesor yang ditugaskan, asesi peserta, staf LSP/TUK terkait, atau Admin Platform. */
function can_chat(array $j): bool
{
    $me = uid();
    $m = active_membership();
    if (is_platform($m) || (int)$j['asesor_id'] === $me) {
        return true;
    }
    if (q("SELECT 1 FROM permohonan WHERE jadwal_id = ? AND asesi_id = ? AND status IN ('pra_asesmen','siap_uji','menunggu_pleno','kompeten','belum_kompeten')", [$j['id'], $me])->fetch()) {
        return true;
    }
    if ($m && $m['lsp_id'] !== null && (int)$m['lsp_id'] === (int)$j['lsp_id']) {
        if ($m['role'] === 'admin_tuk') {
            return (int)$m['tuk_id'] === (int)$j['tuk_id'];
        }
        return can('tuk.chat') || can('schedule.manage');
    }
    return false;
}

function chat_jadwal(int $id): array
{
    $j = q('SELECT * FROM jadwal WHERE id = ?', [$id])->fetch();
    if (!$j || !can_chat($j)) {
        fail('Ruang chat tidak ditemukan.', 404);
    }
    return $j;
}

function r_chat(): void
{
    require_method('GET');
    require_auth();
    $j = chat_jadwal(isset($_GET['jadwal_id']) && ctype_digit((string)$_GET['jadwal_id']) ? (int)$_GET['jadwal_id'] : 0);
    $rows = q('SELECT c.id, c.isi, c.created_at, c.user_id, u.nama FROM chat_pesan c JOIN users u ON u.id = c.user_id WHERE c.jadwal_id = ? ORDER BY c.id DESC LIMIT 100', [$j['id']])->fetchAll();
    json_out(['items' => array_reverse(array_map(function ($r) {
        return ['id' => (int)$r['id'], 'isi' => $r['isi'], 'nama' => $r['nama'], 'created_at' => $r['created_at'], 'saya' => (int)$r['user_id'] === uid()];
    }, $rows))]);
}

function r_chat_kirim(): void
{
    require_method('POST');
    csrf_check();
    require_auth();
    $j = chat_jadwal(int_in('jadwal_id'));
    $isi = str_in('isi', 1000, 'Pesan');
    if ($isi === '') {
        fail('Tulis pesan.', 422);
    }
    q('INSERT INTO chat_pesan (lsp_id, jadwal_id, user_id, isi, created_at) VALUES (?, ?, ?, ?, ?)', [$j['lsp_id'], $j['id'], uid(), $isi, now()]);
    json_out(['ok' => true], 201);
}

/** Jadwal yang bisa dibuka chat-nya oleh pengguna (untuk daftar ruang chat). */
function r_chat_ruang(): void
{
    require_method('GET');
    require_auth();
    $m = active_membership();
    $me = uid();
    $sql = 'SELECT DISTINCT j.*, s.nama AS skema_nama, ls.nama AS lsp_nama, t.nama AS tuk_nama, a.nama AS asesor_nama FROM jadwal j JOIN skema s ON s.id = j.skema_id
            JOIN lsp ls ON ls.id = j.lsp_id LEFT JOIN tuk t ON t.id = j.tuk_id LEFT JOIN users a ON a.id = j.asesor_id';
    if ($m['role'] === 'admin_tuk') {
        $rows = q("$sql WHERE j.tuk_id = ? ORDER BY j.tanggal DESC LIMIT 50", [(int)$m['tuk_id']])->fetchAll();
    } elseif (can('tuk.chat') || can('schedule.manage')) {
        $scope = scope_lsp($m);
        $rows = q("$sql" . ($scope === null ? '' : ' WHERE j.lsp_id = ?') . ' ORDER BY j.tanggal DESC LIMIT 50', $scope === null ? [] : [$scope])->fetchAll();
    } else {
        $rows = q("$sql LEFT JOIN permohonan p ON p.jadwal_id = j.id WHERE j.asesor_id = ? OR (p.asesi_id = ? AND p.status IN ('pra_asesmen','siap_uji','menunggu_pleno','kompeten','belum_kompeten'))
                   ORDER BY j.tanggal DESC LIMIT 50", [$me, $me])->fetchAll();
    }
    json_out(['items' => array_map('jadwal_row', $rows)]);
}
