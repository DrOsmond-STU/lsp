<?php
declare(strict_types=1);

/*
 * Mesin formulir asesmen paperless (Buku Kerja 333-25).
 * - Isian per asesi (lingkup permohonan), per jadwal (AK.05/AK.06), per skema (FR.VA) disimpan di `formulir`.
 * - Tanda tangan elektronik di `formulir_ttd`: identitas akun, peran, waktu, IP, dan sidik SHA-256 isi formulir saat ditandatangani.
 * - Perangkat asesmen (MAPA, soal, skenario) per skema disimpan di `muk_perangkat`, berversi, disusun dan divalidasi orang berbeda.
 */

/* =========================== Data acuan skema =========================== */

/** Unit skema beserta kelompok pekerjaan. */
function muk_units(int $skemaId): array
{
    return array_map(function ($u) {
        return ['id' => (int)$u['id'], 'kode' => $u['kode'], 'judul' => $u['judul'], 'kelompok' => (int)($u['kelompok'] ?? 1)];
    }, q('SELECT id, kode, judul, kelompok FROM skema_units WHERE skema_id = ? ORDER BY urut, id', [$skemaId])->fetchAll());
}

/** Elemen dan KUK seluruh unit skema. */
function muk_elemen(int $skemaId): array
{
    $rows = q('SELECT e.id, e.unit_id, e.no, e.judul, e.kuk, u.kode AS unit_kode, u.judul AS unit_judul FROM skema_elemen e
               JOIN skema_units u ON u.id = e.unit_id WHERE u.skema_id = ? ORDER BY u.urut, u.id, e.no', [$skemaId])->fetchAll();
    return array_map(function ($e) {
        return ['id' => (int)$e['id'], 'unit_id' => (int)$e['unit_id'], 'no' => (int)$e['no'], 'judul' => $e['judul'],
            'kuk' => json_decode((string)$e['kuk'], true) ?: [], 'unit_kode' => $e['unit_kode'], 'unit_judul' => $e['unit_judul']];
    }, $rows);
}

/** Perangkat tervalidasi terbaru untuk skema & kode (null bila belum ada). */
function muk_perangkat_valid(int $skemaId, string $kode): ?array
{
    $r = q("SELECT * FROM muk_perangkat WHERE skema_id = ? AND kode = ? AND status = 'valid' ORDER BY versi DESC LIMIT 1", [$skemaId, $kode])->fetch();
    return $r ?: null;
}

/* =========================== Konteks & pihak =========================== */

/** Konteks permohonan untuk formulir: siapa asesi, asesor yang ditugaskan, skema, jadwal. */
function muk_ctx_permohonan(int $pid): array
{
    $r = q(permohonan_sql() . ' WHERE p.id = ?', [$pid])->fetch();
    if (!$r) {
        fail('Permohonan tidak ditemukan.', 404);
    }
    return ['lingkup' => 'permohonan', 'id' => (int)$r['id'], 'lsp_id' => (int)$r['lsp_id'], 'skema_id' => (int)$r['skema_id'],
        'jadwal_id' => (int)$r['jadwal_id'], 'asesi_id' => (int)$r['asesi_id'], 'asesor_id' => (int)$r['jadwal_asesor_id'], 'row' => $r];
}

function muk_ctx_jadwal(int $jid): array
{
    $j = q('SELECT j.*, s.nama AS skema_nama, s.kode AS skema_kode, t.nama AS tuk_nama, t.jenis AS tuk_jenis, a.nama AS asesor_nama, a.no_met
            FROM jadwal j JOIN skema s ON s.id = j.skema_id LEFT JOIN tuk t ON t.id = j.tuk_id LEFT JOIN users a ON a.id = j.asesor_id WHERE j.id = ?', [$jid])->fetch();
    if (!$j) {
        fail('Jadwal tidak ditemukan.', 404);
    }
    return ['lingkup' => 'jadwal', 'id' => (int)$j['id'], 'lsp_id' => (int)$j['lsp_id'], 'skema_id' => (int)$j['skema_id'], 'jadwal_id' => (int)$j['id'],
        'asesi_id' => 0, 'asesor_id' => (int)$j['asesor_id'], 'row' => $j];
}

function muk_ctx_skema(int $sid): array
{
    $s = q('SELECT * FROM skema WHERE id = ?', [$sid])->fetch();
    if (!$s) {
        fail('Skema tidak ditemukan.', 404);
    }
    return ['lingkup' => 'skema', 'id' => (int)$s['id'], 'lsp_id' => (int)$s['lsp_id'], 'skema_id' => (int)$s['id'], 'jadwal_id' => 0,
        'asesi_id' => 0, 'asesor_id' => 0, 'row' => $s];
}

/** Staf LSP (atau Admin Platform) yang boleh melihat seluruh berkas asesmen LSP ini. */
function muk_staf_lsp(int $lspId): bool
{
    $m = active_membership();
    if (!$m) {
        return false;
    }
    if (is_platform($m)) {
        return true;
    }
    if ((int)$m['lsp_id'] !== $lspId) {
        return false;
    }
    foreach (['registration.verify', 'assessment.monitor', 'decision.manage', 'assessment.review', 'appeal.decide'] as $p) {
        if (can($p)) {
            return true;
        }
    }
    return false;
}

function muk_asesor_aktif(int $lspId): bool
{
    return in_array($lspId, asesor_lsps(), true) && can('assessment.conduct');
}

/** Pihak yang boleh diperankan pengguna saat ini pada formulir dalam konteks ini. */
function muk_parties(array $ctx, array $def): array
{
    $me = uid();
    $out = [];
    foreach (array_keys($def['ttd']) as $p) {
        if ($p === 'asesi' && $ctx['asesi_id'] === $me && can('application.own')) {
            $out[] = 'asesi';
        } elseif ($p === 'asesor' && $ctx['asesor_id'] === $me && $ctx['asesi_id'] !== $me && muk_asesor_aktif($ctx['lsp_id'])) {
            $out[] = 'asesor';
        } elseif ($p === 'admin' && can('registration.verify') && muk_staf_lsp($ctx['lsp_id'])) {
            $out[] = 'admin';
        } elseif ($p === 'tim' && can('assessment.review') && (muk_staf_lsp($ctx['lsp_id']) || in_array($ctx['lsp_id'], asesor_lsps(), true))) {
            $out[] = 'tim';
        }
    }
    return $out;
}

/** Boleh melihat formulir? Pihaknya sendiri, staf LSP, atau asesor anggota pleno (tanpa data rahasia). */
function muk_can_view(array $ctx, array $def): bool
{
    if (muk_parties($ctx, $def) || muk_staf_lsp($ctx['lsp_id'])) {
        return true;
    }
    if ($ctx['asesi_id'] === uid()) {
        return isset($def['ttd']['asesi']) || true;
    }
    if ($ctx['lingkup'] === 'permohonan' && can('pleno.participate') && in_array($ctx['lsp_id'], asesor_lsps(), true)
        && $ctx['row']['status'] === 'menunggu_pleno') {
        return true;
    }
    return $ctx['asesor_id'] === uid() && in_array($ctx['lsp_id'], asesor_lsps(), true);
}

/* =========================== Baris tabel =========================== */

/** Baris untuk kolom grid, sesuai sumbernya. Setiap baris: key, label, (opsional) opsi baris & info tambahan. */
function muk_rows(array $f, array $ctx, ?array $perangkat, bool $untukAsesi): array
{
    switch ($f['baris']) {
        case 'unit':
            return array_map(function ($u) {
                return ['key' => (string)$u['id'], 'label' => $u['kode'] . ' — ' . $u['judul'], 'kelompok' => $u['kelompok']];
            }, muk_units($ctx['skema_id']));
        case 'elemen':
            return array_map(function ($e) {
                return ['key' => (string)$e['id'], 'label' => $e['unit_kode'] . ' ' . $e['unit_judul'] . ' — Elemen ' . $e['no'] . ': ' . $e['judul'],
                    'unit' => $e['unit_kode'] . ' — ' . $e['unit_judul'], 'elemen' => $e['no'] . '. ' . $e['judul'],
                    'kuk' => array_map(function ($k, $i) use ($e) { return $e['no'] . '.' . ($i + 1) . ' ' . $k; }, $e['kuk'], array_keys($e['kuk']))];
            }, muk_elemen($ctx['skema_id']));
        case 'kuk':
            $out = [];
            foreach (muk_elemen($ctx['skema_id']) as $e) {
                foreach ($e['kuk'] as $i => $k) {
                    $out[] = ['key' => $e['id'] . '-' . ($i + 1), 'label' => $e['no'] . '.' . ($i + 1) . ' ' . $k,
                        'unit' => $e['unit_kode'] . ' — ' . $e['unit_judul'], 'elemen' => $e['no'] . '. ' . $e['judul']];
                }
            }
            return $out;
        case 'persyaratan':
            $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)q('SELECT persyaratan FROM skema WHERE id = ?', [$ctx['skema_id']])->fetchColumn()))));
            return array_map(function ($l, $i) { return ['key' => (string)($i + 1), 'label' => $l]; }, $lines ?: ['Sesuai persyaratan dasar skema'], array_keys($lines ?: [0]));
        case 'tetap':
            $out = [];
            foreach ($f['rows'] as $k => $v) {
                $out[] = is_array($v) ? ['key' => (string)$k, 'label' => $v[0], 'o' => $v[1]] : ['key' => (string)$k, 'label' => $v];
            }
            return $out;
        case 'item':
            if (!$perangkat) {
                return [];
            }
            $items = json_decode((string)$perangkat['data'], true)['items'] ?? [];
            $pdef = muk_perangkat_defs()[$perangkat['kode']]['item'] ?? [];
            $out = [];
            foreach ($items as $i => $it) {
                $row = ['key' => (string)($i + 1), 'label' => (string)($it['pertanyaan'] ?? $it['skenario'] ?? $it['kriteria'] ?? ''), 'item' => []];
                foreach ($pdef as $c) {
                    if (!empty($c['rahasia']) && $untukAsesi) {
                        continue;
                    }
                    if (isset($it[$c['k']]) && $it[$c['k']] !== '') {
                        $row['item'][$c['k']] = ['l' => $c['l'], 'v' => (string)$it[$c['k']]];
                    }
                }
                $out[] = $row;
            }
            return $out;
        case 'asesi':
            $rows = q("SELECT p.id, u.nama, f.data FROM permohonan p JOIN users u ON u.id = p.asesi_id
                       JOIN formulir f ON f.permohonan_id = p.id AND f.kode = 'AK.02'
                       WHERE p.jadwal_id = ? AND EXISTS (SELECT 1 FROM formulir_ttd t WHERE t.formulir_id = f.id AND t.peran = 'asesor') ORDER BY u.nama", [$ctx['jadwal_id']])->fetchAll();
            return array_map(function ($r) { return ['key' => (string)$r['id'], 'label' => $r['nama']]; }, $rows);
        case 'bebas':
            return [];
    }
    return [];
}

/* =========================== Simpan & validasi =========================== */

function muk_fields(array $def): array
{
    $out = [];
    foreach ($def['bagian'] as $b) {
        foreach ($b['f'] as $f) {
            $out[] = $f;
        }
    }
    return $out;
}

function muk_owner(array $f, array $def): string
{
    return $f['oleh'] ?? $def['pengisi'];
}

/** Opsi kolom pilih/multi sebagai daftar nilai yang sah. */
function muk_opt_keys($o): array
{
    if (!is_array($o)) {
        return [];
    }
    return array_is_list($o) ? array_map('strval', $o) : array_map('strval', array_keys($o));
}

/** Bersihkan satu nilai sesuai jenis kolom. null = kosong. */
function muk_clean_val(array $c, $v, ?array $rowOpts, string $label)
{
    $t = $c['t'];
    if ($v === null || $v === '' || $v === []) {
        return null;
    }
    switch ($t) {
        case 'teks':
        case 'panjang':
            if (!is_string($v) && !is_int($v)) {
                fail("$label tidak valid.", 422);
            }
            $v = trim((string)$v);
            $max = $t === 'teks' ? 500 : 4000;
            if (text_len($v) > $max) {
                fail("$label maksimal $max karakter.", 422);
            }
            return $v === '' ? null : $v;
        case 'tanggal':
            if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) || !strtotime($v)) {
                fail("$label harus berupa tanggal (YYYY-MM-DD).", 422);
            }
            return $v;
        case 'yt':
        case 'kbk':
        case 'pilih':
            $ok = $t === 'yt' ? MUK_YT : ($t === 'kbk' ? MUK_KBK : muk_opt_keys($c['o'] === '@row' ? $rowOpts : $c['o']));
            if (!is_string($v) || !in_array($v, $ok, true)) {
                fail("$label: pilihan tidak valid.", 422);
            }
            return $v;
        case 'multi':
            $ok = muk_opt_keys($c['o'] === '@row' ? $rowOpts : $c['o']);
            if (!is_array($v)) {
                fail("$label: pilihan tidak valid.", 422);
            }
            $out = [];
            foreach ($v as $x) {
                if (!is_string($x) || !in_array($x, $ok, true)) {
                    fail("$label: pilihan tidak valid.", 422);
                }
                $out[] = $x;
            }
            return $out ? array_values(array_unique($out)) : null;
    }
    return null;
}

/**
 * Gabungkan isian pihak $party ke data lama. Hanya kolom milik pihak itu yang diterima; kolom pihak lain diabaikan
 * (tidak bisa ditimpa lewat API).
 */
function muk_merge(array $def, array $data, array $in, string $party, array $ctx, ?array $perangkatMap): array
{
    foreach (muk_fields($def) as $f) {
        if ($f['t'] === 'info' || !array_key_exists($f['k'], $in)) {
            continue;
        }
        $label = $f['l'] ?? $f['k'];
        if ($f['t'] !== 'grid') {
            if (muk_owner($f, $def) === $party) {
                $data[$f['k']] = muk_clean_val($f, $in[$f['k']], null, $label);
            }
            continue;
        }
        $mine = array_values(array_filter($f['kol'], function ($c) use ($f, $def, $party) { return ($c['oleh'] ?? $f['oleh'] ?? $def['pengisi']) === $party; }));
        if (!$mine || !is_array($in[$f['k']])) {
            continue;
        }
        if ($f['baris'] === 'bebas') {
            if (($f['oleh'] ?? $def['pengisi']) !== $party) {
                continue;
            }
            $rows = [];
            foreach (array_slice(array_values($in[$f['k']]), 0, 60) as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $clean = [];
                foreach ($f['kol'] as $c) {
                    $clean[$c['k']] = muk_clean_val($c, $r[$c['k']] ?? null, null, $label . ' / ' . $c['l']);
                }
                if (array_filter($clean, function ($x) { return $x !== null; })) {
                    $rows[] = $clean;
                }
            }
            $data[$f['k']] = $rows;
            continue;
        }
        $rows = muk_rows($f, $ctx, $perangkatMap[$def['perangkat'] ?? ''] ?? null, false);
        $cur = is_array($data[$f['k']] ?? null) ? $data[$f['k']] : [];
        foreach ($rows as $r) {
            if (!isset($in[$f['k']][$r['key']]) || !is_array($in[$f['k']][$r['key']])) {
                continue;
            }
            foreach ($mine as $c) {
                if (array_key_exists($c['k'], $in[$f['k']][$r['key']])) {
                    $cur[$r['key']][$c['k']] = muk_clean_val($c, $in[$f['k']][$r['key']][$c['k']], $r['o'] ?? null, $label . ' / ' . $r['label']);
                }
            }
        }
        $data[$f['k']] = $cur;
    }
    return $data;
}

/** Kolom wajib milik pihak $party yang masih kosong (label untuk pesan kesalahan). */
function muk_missing(array $def, array $data, string $party, array $ctx, ?array $perangkatMap): array
{
    $miss = [];
    foreach (muk_fields($def) as $f) {
        if ($f['t'] === 'info') {
            continue;
        }
        if ($f['t'] !== 'grid') {
            if (!empty($f['wajib']) && muk_owner($f, $def) === $party && ($data[$f['k']] ?? null) === null) {
                $miss[] = $f['l'] ?? $f['k'];
            }
            continue;
        }
        if ($f['baris'] === 'bebas') {
            if (($f['oleh'] ?? $def['pengisi']) === $party) {
                $rows = $data[$f['k']] ?? [];
                if (count($rows) < ($f['min'] ?? 0)) {
                    $miss[] = ($f['l'] ?? $f['k']) . ' (minimal ' . $f['min'] . ' baris)';
                }
                foreach ($rows as $i => $r) {
                    foreach ($f['kol'] as $c) {
                        if (!empty($c['wajib']) && ($r[$c['k']] ?? null) === null) {
                            $miss[] = ($f['l'] ?? 'Baris') . ' ' . ($i + 1) . ': ' . $c['l'];
                        }
                    }
                }
            }
            continue;
        }
        $rows = muk_rows($f, $ctx, $perangkatMap[$def['perangkat'] ?? ''] ?? null, false);
        foreach ($rows as $r) {
            foreach ($f['kol'] as $c) {
                if (!empty($c['wajib']) && ($c['oleh'] ?? $f['oleh'] ?? $def['pengisi']) === $party && ($data[$f['k']][$r['key']][$c['k']] ?? null) === null) {
                    // Keterangan AK.07 hanya wajib bila jawabannya Ya; kolom lain wajib di setiap baris.
                    $miss[] = mb_strimwidth($r['label'], 0, 70, '…') . ': ' . $c['l'];
                }
            }
        }
    }
    return $miss;
}

/* =========================== Muat / simpan =========================== */

function muk_find(array $ctx, string $kode, int $fid = 0): ?array
{
    if ($fid) {
        $r = q('SELECT * FROM formulir WHERE id = ? AND kode = ?', [$fid, $kode])->fetch();
    } elseif ($ctx['lingkup'] === 'permohonan') {
        $r = q('SELECT * FROM formulir WHERE permohonan_id = ? AND kode = ?', [$ctx['id'], $kode])->fetch();
    } elseif ($ctx['lingkup'] === 'jadwal') {
        $r = q('SELECT * FROM formulir WHERE jadwal_id = ? AND permohonan_id IS NULL AND kode = ? ORDER BY id DESC LIMIT 1', [$ctx['id'], $kode])->fetch();
    } else {
        $r = null;
    }
    return $r ?: null;
}

function muk_ttd_of(int $fid): array
{
    $out = [];
    foreach (q('SELECT t.*, u.nama AS user_nama FROM formulir_ttd t JOIN users u ON u.id = t.user_id WHERE t.formulir_id = ? ORDER BY t.id', [$fid])->fetchAll() as $t) {
        $out[$t['peran']] = ['nama' => $t['nama'], 'no_reg' => $t['no_reg'], 'at' => $t['signed_at'], 'hash' => substr($t['hash'], 0, 16), 'user_id' => (int)$t['user_id']];
    }
    return $out;
}

/** Perangkat tervalidasi yang dirujuk formulir (kode → baris), dipilih saat formulir pertama kali dibuat. */
function muk_perangkat_map(array $def, array $ctx, array $data): array
{
    if (empty($def['perangkat'])) {
        return [];
    }
    $pid = (int)($data['_perangkat'] ?? 0);
    $p = $pid ? q('SELECT * FROM muk_perangkat WHERE id = ?', [$pid])->fetch() : muk_perangkat_valid($ctx['skema_id'], $def['perangkat']);
    return [$def['perangkat'] => $p ?: null];
}

function muk_save(array $ctx, string $kode, array $data, ?array $existing): int
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($existing) {
        q('UPDATE formulir SET data = ?, updated_at = ? WHERE id = ?', [$json, now(), $existing['id']]);
        return (int)$existing['id'];
    }
    q('INSERT INTO formulir (lsp_id, permohonan_id, jadwal_id, skema_id, kode, data, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$ctx['lsp_id'], $ctx['lingkup'] === 'permohonan' ? $ctx['id'] : null, $ctx['jadwal_id'] ?: null, $ctx['skema_id'], $kode, $json, 'draf', uid(), now(), now()]);
    return (int)db()->lastInsertId();
}

function muk_no_met(int $uid): ?string
{
    $v = q('SELECT no_met FROM users WHERE id = ?', [$uid])->fetchColumn();
    return $v ? (string)$v : null;
}

/** Tanda tangan elektronik satu pihak; formulir final bila semua pihak sudah menandatangani. */
function muk_sign_row(int $fid, string $party, array $data): void
{
    $u = current_user();
    $hash = hash('sha256', $fid . '|' . $party . '|' . json_encode($data, JSON_UNESCAPED_UNICODE));
    q('INSERT INTO formulir_ttd (formulir_id, peran, user_id, nama, no_reg, hash, ip, signed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$fid, $party, $u['id'], $u['nama'], $party === 'asesor' ? muk_no_met((int)$u['id']) : null, $hash, client_ip(), now()]);
}

function muk_refresh_status(int $fid, array $def): string
{
    $have = array_keys(muk_ttd_of($fid));
    $st = array_diff(array_keys($def['ttd']), $have) ? 'draf' : 'final';
    q('UPDATE formulir SET status = ? WHERE id = ?', [$st, $fid]);
    return $st;
}

/* =========================== Prefill =========================== */

/** Isian awal yang diturunkan dari data lain (bukan rekaan): jadwal, APL.02, instrumen yang sudah ditandatangani. */
function muk_prefill(string $kode, array $ctx): array
{
    $d = [];
    if ($ctx['lingkup'] === 'permohonan') {
        $p = $ctx['row'];
        if ($kode === 'AK.01') {
            $map = ['IA.08' => 'porto', 'IA.11' => 'produk', 'IA.01' => 'observasi', 'IA.02' => 'observasi', 'IA.04' => 'terstruktur', 'IA.03' => 'tanya',
                'IA.07' => 'lisan', 'IA.05' => 'tulis', 'IA.06' => 'tulis', 'IA.09' => 'wawancara', 'IA.10' => 'lain'];
            $b = [];
            foreach (muk_instrumen_aktif($ctx) as $ia) {
                $b[] = $map[$ia];
            }
            $d = ['bukti' => array_values(array_unique($b)) ?: null, 'tanggal' => $p['tanggal'], 'waktu' => $p['jam'], 'tuk' => $p['tuk_nama'] ?: $p['metode']];
        } elseif ($kode === 'IA.08') {
            $apl = muk_find($ctx, 'APL.02');
            $rows = [];
            foreach (json_decode((string)($apl['data'] ?? ''), true)['elemen'] ?? [] as $r) {
                if (!empty($r['bukti']) && !in_array($r['bukti'], array_column($rows, 'nama'), true)) {
                    $rows[] = ['nama' => $r['bukti'], 'valid' => null, 'asli' => null, 'terkini' => null, 'memadai' => null];
                }
            }
            $d = ['bukti' => array_slice($rows, 0, 20)];
        } elseif ($kode === 'AK.02') {
            $aktif = muk_instrumen_aktif($ctx);
            $bukti = [];
            foreach (MUK_AK02_BUKTI as $k => [$label, $ias]) {
                if (array_intersect($ias, $aktif)) {
                    $bukti[] = $k;
                }
            }
            $unit = [];
            foreach (muk_units($ctx['skema_id']) as $u) {
                $unit[(string)$u['id']] = ['bukti' => $bukti ?: null, 'hasil' => null];
            }
            $d = ['unit' => $unit];
        } elseif ($kode === 'APL.01') {
            $d = ['tujuan' => $p['tujuan'] ?: 'Sertifikasi'];
        }
    } elseif ($ctx['lingkup'] === 'jadwal' && $kode === 'AK.05') {
        $rows = [];
        $units = [];
        foreach (muk_units($ctx['skema_id']) as $u) {
            $units[(string)$u['id']] = $u['kode'] . ' ' . $u['judul'];
        }
        foreach (muk_rows(['baris' => 'asesi'], $ctx, null, false) as $r) {
            $ak = json_decode((string)q("SELECT data FROM formulir WHERE permohonan_id = ? AND kode = 'AK.02'", [(int)$r['key']])->fetchColumn(), true) ?: [];
            $bk = [];
            foreach ($ak['unit'] ?? [] as $uid => $x) {
                if (($x['hasil'] ?? '') === 'BK') {
                    $bk[] = $units[$uid] ?? $uid;
                }
            }
            $rows[$r['key']] = ['rekom' => $bk ? 'BK' : 'K', 'ket' => $bk ? implode('; ', $bk) : null];
        }
        $banding = (int)q('SELECT COUNT(*) FROM banding b JOIN permohonan p ON p.id = b.permohonan_id WHERE p.jadwal_id = ?', [$ctx['id']])->fetchColumn();
        $d = ['asesi' => $rows, 'penolakan' => $banding ? "Ada ($banding banding)" : 'Tidak ada'];
    }
    return $d;
}

/* =========================== Instrumen aktif (MAPA.02 + AK.07) =========================== */

/** Potensi asesi dari FR.AK.07 (null bila belum ditentukan). */
function muk_potensi(array $ctx): ?string
{
    $f = muk_find($ctx, 'AK.07');
    $d = $f ? json_decode((string)$f['data'], true) : [];
    return $d['potensi'] ?? null;
}

/** Instrumen yang dipakai untuk asesi: peta FR.MAPA.02 skema untuk potensi asesinya, ditambah kesepakatan FR.AK.07. */
function muk_instrumen_aktif(array $ctx): array
{
    $pot = muk_potensi($ctx);
    $mapa = muk_perangkat_valid($ctx['skema_id'], 'MAPA.02');
    if (!$pot || !$mapa) {
        return [];
    }
    $peta = json_decode((string)$mapa['data'], true)['peta'] ?? [];
    $out = [];
    foreach (MUK_INSTRUMEN as $kode => $_) {
        if (in_array($pot, $peta[$kode]['potensi'] ?? [], true)) {
            $out[] = $kode;
        }
    }
    $ak07 = json_decode((string)(muk_find($ctx, 'AK.07')['data'] ?? ''), true) ?: [];
    if (($ak07['sepakat'] ?? '') === 'ya') {
        foreach ($ak07['instrumen'] ?? [] as $kode) {
            $out[] = $kode;
        }
    }
    $out = array_values(array_unique($out));
    usort($out, function ($a, $b) { return strcmp($a, $b); });
    return $out;
}

/* =========================== Payload =========================== */

/** Definisi siap kirim ke browser: baris grid sudah diisi; kolom/baris rahasia dibuang bila untuk asesi. */
function muk_def_payload(array $def, array $ctx, array $perangkatMap, bool $untukAsesi): array
{
    $out = $def;
    foreach ($out['bagian'] as &$b) {
        foreach ($b['f'] as &$f) {
            if ($f['t'] === 'grid') {
                $f['rows'] = muk_rows($f, $ctx, $perangkatMap[$def['perangkat'] ?? ''] ?? null, $untukAsesi);
            }
        }
    }
    return $out;
}

function muk_header(array $ctx): array
{
    if ($ctx['lingkup'] === 'permohonan') {
        $r = $ctx['row'];
        $t = $r['tuk_id'] ? q('SELECT jenis FROM tuk WHERE id = ?', [$r['tuk_id']])->fetchColumn() : null;
        $a = $r['jadwal_asesor_id'] ? q('SELECT nama, no_met FROM users WHERE id = ?', [$r['jadwal_asesor_id']])->fetch() : null;
        return ['skema_judul' => $r['skema_nama'], 'skema_nomor' => $r['skema_kode'], 'lsp' => $r['lsp_nama'], 'tuk' => trim(($t ?: 'Sewaktu') . ' — ' . ($r['tuk_nama'] ?: $r['metode'])),
            'asesor' => $a['nama'] ?? null, 'no_reg' => $a['no_met'] ?? null, 'asesi' => $r['asesi_nama'], 'tanggal' => $r['tanggal'], 'waktu' => $r['jam'], 'status' => $r['status']];
    }
    if ($ctx['lingkup'] === 'jadwal') {
        $j = $ctx['row'];
        return ['skema_judul' => $j['skema_nama'], 'skema_nomor' => $j['skema_kode'], 'tuk' => trim(($j['tuk_jenis'] ?: 'Sewaktu') . ' — ' . ($j['tuk_nama'] ?: $j['metode'])),
            'asesor' => $j['asesor_nama'], 'no_reg' => $j['no_met'], 'tanggal' => $j['tanggal'], 'waktu' => $j['jam']];
    }
    return ['skema_judul' => $ctx['row']['nama'], 'skema_nomor' => $ctx['row']['kode']];
}

/** Hapus jawaban rahasia & penilaian yang belum ditandatangani asesor dari tampilan asesi. */
function muk_for_asesi(array $def, array $data, array $ttd): array
{
    if (isset($ttd['asesor'])) {
        return $data;
    }
    foreach (muk_fields($def) as $f) {
        if (muk_owner($f, $def) === 'asesor' && $f['t'] !== 'grid') {
            unset($data[$f['k']]);
        }
        if ($f['t'] === 'grid' && $f['baris'] !== 'bebas' && is_array($data[$f['k']] ?? null)) {
            foreach ($data[$f['k']] as $rk => $r) {
                foreach ($f['kol'] as $c) {
                    if (($c['oleh'] ?? $f['oleh'] ?? $def['pengisi']) === 'asesor') {
                        unset($data[$f['k']][$rk][$c['k']]);
                    }
                }
            }
        }
    }
    return $data;
}

function muk_ctx_from_input(array $src): array
{
    $pid = (int)($src['permohonan_id'] ?? 0);
    $jid = (int)($src['jadwal_id'] ?? 0);
    $sid = (int)($src['skema_id'] ?? 0);
    if ($pid) {
        return muk_ctx_permohonan($pid);
    }
    if ($jid) {
        return muk_ctx_jadwal($jid);
    }
    if ($sid) {
        return muk_ctx_skema($sid);
    }
    fail('Konteks formulir tidak lengkap.', 422);
}

function muk_def_or_404(string $kode, array $ctx): array
{
    $def = muk_defs()[$kode] ?? null;
    if (!$def || $def['lingkup'] !== $ctx['lingkup']) {
        fail('Formulir tidak dikenal.', 404);
    }
    return $def;
}

/* =========================== Endpoint formulir =========================== */

function r_muk_formulir(): void
{
    require_method('GET');
    require_auth();
    $ctx = muk_ctx_from_input($_GET);
    $kode = is_string($_GET['kode'] ?? null) ? $_GET['kode'] : '';
    $def = muk_def_or_404($kode, $ctx);
    if (!muk_can_view($ctx, $def)) {
        audit('access.denied', 'formulir:' . $kode);
        fail('Formulir tidak ditemukan.', 404);
    }
    $fid = (int)($_GET['id'] ?? 0);
    // Laporan susulan (FR.AK.05 baru untuk asesi yang belum tercakup laporan sebelumnya).
    $baru = $ctx['lingkup'] === 'jadwal' && $kode === 'AK.05' && ($_GET['baru'] ?? '') === '1';
    $f = $baru ? null : muk_find($ctx, $kode, $fid);
    if ($f && ((int)$f['lsp_id'] !== $ctx['lsp_id'] || ($ctx['lingkup'] === 'skema' && (int)$f['skema_id'] !== $ctx['id']))) {
        fail('Formulir tidak ditemukan.', 404);
    }
    $data = $f ? (json_decode((string)$f['data'], true) ?: []) : muk_prefill($kode, $ctx);
    $ttd = $f ? muk_ttd_of((int)$f['id']) : [];
    $pmap = muk_perangkat_map($def, $ctx, $data);
    $parties = muk_parties($ctx, $def);
    $untukAsesi = $ctx['asesi_id'] === uid() && !muk_staf_lsp($ctx['lsp_id']);
    if ($kode === 'IA.05' && in_array('asesor', $parties, true)) {
        $data = muk_ia05_autoscore($data, $pmap['IA.05'] ?? null);
    }
    if ($untukAsesi) {
        $data = muk_for_asesi($def, $data, $ttd);
    }
    unset($data['_perangkat']);
    json_out([
        'kode' => $kode, 'id' => $f ? (int)$f['id'] : null, 'status' => $f['status'] ?? 'baru', 'def' => muk_def_payload($def, $ctx, $pmap, $untukAsesi),
        'header' => muk_header($ctx), 'data' => (object)$data, 'ttd' => (object)$ttd,
        'pihak' => array_values(array_filter($parties, function ($p) use ($ttd) { return !isset($ttd[$p]); })),
        'perangkat' => $pmap ? array_map(function ($p) { return $p ? ['versi' => (int)$p['versi'], 'status' => $p['status']] : null; }, $pmap) : null,
        'catatan' => muk_blocker($kode, $ctx, $ttd),
    ]);
}

/** Pesan bila formulir belum boleh diisi/ditandatangani pada tahap ini (null bila boleh). */
function muk_blocker(string $kode, array $ctx, array $ttd): ?string
{
    if ($ctx['lingkup'] !== 'permohonan') {
        return null;
    }
    $st = $ctx['row']['status'];
    $def = muk_defs()[$kode];
    if (in_array($kode, ['AK.07', 'AK.01'], true) && $st !== 'pra_asesmen' && count($ttd) < count($def['ttd'])) {
        return 'Formulir ini diisi pada tahap pra-asesmen.';
    }
    if (!empty($def['instrumen'])) {
        if ($st !== 'siap_uji') {
            return $st === 'pra_asesmen' ? 'Instrumen asesmen diisi setelah FR.AK.01 ditandatangani asesor dan asesi.' : null;
        }
        if ($ctx['row']['tanggal'] > today()) {
            return 'Instrumen asesmen baru dapat diisi pada atau setelah tanggal uji (' . $ctx['row']['tanggal'] . ').';
        }
        if (!in_array($kode, muk_instrumen_aktif($ctx), true)) {
            return 'Instrumen ini tidak dipakai untuk asesi ini menurut FR.MAPA.02 dan FR.AK.07.';
        }
    }
    if ($kode === 'AK.02' && $st !== 'siap_uji' && !isset($ttd['asesor'])) {
        return 'FR.AK.02 diisi setelah asesmen dilaksanakan.';
    }
    if ($kode === 'AK.03' && !q("SELECT 1 FROM formulir f JOIN formulir_ttd t ON t.formulir_id = f.id AND t.peran = 'asesor' WHERE f.permohonan_id = ? AND f.kode = 'AK.02'", [$ctx['id']])->fetch()) {
        return 'Umpan balik diisi setelah asesor menandatangani rekaman asesmen (FR.AK.02).';
    }
    if ($kode === 'AK.04') {
        return muk_banding_blocker($ctx);
    }
    return null;
}

function r_muk_simpan(): void
{
    require_method('POST');
    csrf_check();
    require_auth();
    [$ctx, $def, $f, $party, $data] = muk_write_prep();
    $id = muk_save($ctx, $def['kode'], $data, $f);
    audit('formulir.simpan', $def['kode'] . ':' . $id, null, $ctx['lsp_id']);
    json_out(['ok' => true, 'id' => $id]);
}

function r_muk_ttd(): void
{
    require_method('POST');
    csrf_check();
    require_auth();
    if ((body()['setuju'] ?? false) !== true) {
        fail('Centang pernyataan tanda tangan elektronik terlebih dahulu.', 422);
    }
    [$ctx, $def, $f, $party, $data] = muk_write_prep();
    $pmap = muk_perangkat_map($def, $ctx, $data);
    if ($def['kode'] === 'IA.05' && $party === 'asesor') {
        $data = muk_ia05_autoscore($data, $pmap['IA.05'] ?? null);
    }
    $miss = muk_missing($def, $data, $party, $ctx, $pmap);
    $miss = array_merge($miss, muk_rules($def['kode'], $data, $party, $ctx));
    if ($miss) {
        fail('Lengkapi dulu: ' . implode('; ', array_slice($miss, 0, 6)) . (count($miss) > 6 ? ' (dan ' . (count($miss) - 6) . ' lainnya)' : '') . '.', 422);
    }
    // Urutan tanda tangan mengikuti formulir: pihak sebelumnya harus sudah menandatangani.
    $ttd = $f ? muk_ttd_of((int)$f['id']) : [];
    foreach (array_keys($def['ttd']) as $p) {
        if ($p === $party) {
            break;
        }
        if (!isset($ttd[$p])) {
            fail('Formulir ini harus ditandatangani ' . $def['ttd'][$p] . ' terlebih dahulu.', 409);
        }
    }
    if ($party === 'asesor' && !muk_no_met(uid())) {
        fail('Nomor registrasi asesor (No. Reg MET) Anda belum tercatat. Minta Admin LSP melengkapinya di data asesor.', 409);
    }
    db()->beginTransaction();
    try {
        $id = muk_save($ctx, $def['kode'], $data, $f);
        muk_sign_row($id, $party, $data);
        $st = muk_refresh_status($id, $def);
        muk_after_sign($def['kode'], $party, $ctx, $data, $id, $st);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    audit('formulir.ttd', $def['kode'] . ':' . $id . ':' . $party, null, $ctx['lsp_id']);
    json_out(['ok' => true, 'id' => $id, 'status' => $st]);
}

/** Persiapan bersama simpan/tanda tangan: cek pihak, tahap, lalu gabungkan isian. */
function muk_write_prep(): array
{
    $b = body();
    $ctx = muk_ctx_from_input($b);
    $kode = is_string($b['kode'] ?? null) ? $b['kode'] : '';
    $def = muk_def_or_404($kode, $ctx);
    $def['kode'] = $kode;
    $f = muk_find($ctx, $kode, (int)($b['id'] ?? 0));
    if ($f && $ctx['lingkup'] === 'jadwal' && $kode === 'AK.05' && $f['status'] === 'final' && ($b['baru'] ?? false) === true) {
        $f = null;
    }
    if ($f && (int)$f['lsp_id'] !== $ctx['lsp_id']) {
        fail('Formulir tidak ditemukan.', 404);
    }
    $parties = muk_parties($ctx, $def);
    $ttd = $f ? muk_ttd_of((int)$f['id']) : [];
    $party = is_string($b['sebagai'] ?? null) ? $b['sebagai'] : ($parties[0] ?? '');
    if (!in_array($party, $parties, true)) {
        audit('access.denied', 'formulir:' . $kode);
        fail('Anda tidak berwenang mengisi formulir ini.', 403);
    }
    if (isset($ttd[$party])) {
        fail('Anda sudah menandatangani formulir ini; isian tidak dapat diubah lagi.', 409);
    }
    if ($kode === 'APL.01' || $kode === 'APL.02') {
        if ($party === 'asesi' && !in_array($ctx['row']['status'], ['perbaikan'], true)) {
            fail('FR.APL.01 dan FR.APL.02 diisi saat mendaftar atau saat perbaikan berkas.', 409);
        }
        if ($party === 'admin') {
            fail('Verifikasi FR.APL.01 dilakukan dari menu Pendaftaran.', 409);
        }
        if ($party === 'asesor' && $ctx['row']['status'] !== 'pra_asesmen') {
            fail('Tinjauan FR.APL.02 dilakukan pada tahap pra-asesmen.', 409);
        }
    } else {
        $blk = muk_blocker($kode, $ctx, $ttd);
        if ($blk) {
            fail($blk, 409);
        }
    }
    $data = $f ? (json_decode((string)$f['data'], true) ?: []) : muk_prefill($kode, $ctx);
    $pmap = muk_perangkat_map($def, $ctx, $data);
    if (!empty($def['perangkat'])) {
        if (empty($pmap[$def['perangkat']])) {
            fail('Perangkat ' . $def['perangkat'] . ' untuk skema ini belum divalidasi. Hubungi Admin LSP.', 409);
        }
        $data['_perangkat'] = (int)$pmap[$def['perangkat']]['id'];
    }
    $in = is_array($b['data'] ?? null) ? $b['data'] : [];
    $data = muk_merge($def, $data, $in, $party, $ctx, $pmap);
    return [$ctx, $def, $f, $party, $data];
}

/** Penilaian otomatis lembar jawaban pilihan ganda terhadap kunci (FR.IA.05.B); asesor tetap dapat mengubahnya. */
function muk_ia05_autoscore(array $data, ?array $perangkat): array
{
    if (!$perangkat) {
        return $data;
    }
    $items = json_decode((string)$perangkat['data'], true)['items'] ?? [];
    foreach ($items as $i => $it) {
        $k = (string)($i + 1);
        $jw = $data['jawab'][$k]['jawaban'] ?? null;
        if ($jw !== null && ($data['jawab'][$k]['capai'] ?? null) === null) {
            $data['jawab'][$k]['capai'] = $jw === ($it['kunci'] ?? '') ? 'Ya' : 'Tidak';
        }
    }
    return $data;
}

/** Aturan isian tambahan per formulir (di luar kolom wajib). */
function muk_rules(string $kode, array $data, string $party, array $ctx): array
{
    $e = [];
    if ($kode === 'APL.02' && $party === 'asesor' && ($data['rekom'] ?? '') === 'tidak' && text_len((string)($data['catatan'] ?? '')) < 5) {
        $e[] = 'Tuliskan alasan untuk asesi bila asesmen tidak dapat dilanjutkan (minimal 5 karakter)';
    }
    if ($kode === 'AK.07' && $party === 'asesor') {
        foreach ($data['butir'] ?? [] as $no => $r) {
            if (($r['perlu'] ?? '') === 'Ya' && empty($r['ket'])) {
                $e[] = "Butir $no: pilih keterangan penyesuaian";
            }
        }
        if (($data['sepakat'] ?? '') === 'ya' && empty($data['metode']) && empty($data['instrumen'])) {
            $e[] = 'Tuliskan metode atau instrumen asesmen yang disepakati';
        }
    }
    if ($kode === 'AK.01' && $party === 'asesor') {
        if (($data['coi'] ?? '') !== 'Ya') {
            $e[] = 'Asesor yang memiliki benturan kepentingan tidak boleh mengases asesi ini; minta Admin LSP mengganti asesor';
        }
        if (($data['banding_dijelaskan'] ?? '') !== 'Ya') {
            $e[] = 'Jelaskan proses banding kepada asesi sebelum menandatangani';
        }
        $apl = muk_find($ctx, 'APL.02');
        $aplTtd = $apl ? muk_ttd_of((int)$apl['id']) : [];
        if (!isset($aplTtd['asesor']) || (json_decode((string)$apl['data'], true)['rekom'] ?? '') !== 'dapat') {
            $e[] = 'Tinjau dan tandatangani FR.APL.02 dengan rekomendasi "Asesmen dapat dilanjutkan"';
        }
        $ak07 = muk_find($ctx, 'AK.07');
        if (!$ak07 || !isset(muk_ttd_of((int)$ak07['id'])['asesor'])) {
            $e[] = 'Isi dan tandatangani FR.AK.07 (potensi asesi dan penyesuaian wajar)';
        }
        if (!muk_perangkat_valid($ctx['skema_id'], 'MAPA.01') || !muk_perangkat_valid($ctx['skema_id'], 'MAPA.02')) {
            $e[] = 'FR.MAPA.01 dan FR.MAPA.02 skema ini belum divalidasi';
        }
        foreach (muk_instrumen_aktif($ctx) as $ia) {
            $pk = muk_defs()[$ia]['perangkat'] ?? null;
            if ($pk && !muk_perangkat_valid($ctx['skema_id'], $pk)) {
                $e[] = "Perangkat $pk belum divalidasi";
            }
        }
        if (!muk_instrumen_aktif($ctx)) {
            $e[] = 'Belum ada instrumen asesmen untuk potensi asesi ini di FR.MAPA.02';
        }
    }
    if ($kode === 'AK.02' && $party === 'asesor') {
        $bk = false;
        foreach ($data['unit'] ?? [] as $r) {
            $bk = $bk || ($r['hasil'] ?? '') === 'BK';
        }
        if ($bk && empty($data['tindak_lanjut'])) {
            $e[] = 'Tindak lanjut wajib diisi bila ada unit Belum Kompeten';
        }
        foreach (muk_instrumen_aktif($ctx) as $ia) {
            $f = muk_find($ctx, $ia);
            if (!$f || !isset(muk_ttd_of((int)$f['id'])['asesor'])) {
                $e[] = MUK_INSTRUMEN[$ia] . ' belum ditandatangani asesor';
            }
        }
    }
    if (in_array($kode, ['IA.01', 'IA.08', 'IA.11'], true) && $party === 'asesor' && ($data['rekomendasi'] ?? '') === 'BK' && empty($data['bk_pada']) && $kode !== 'IA.11') {
        $e[] = 'Tuliskan kelompok pekerjaan/unit/elemen/KUK yang belum kompeten';
    }
    return $e;
}

/** Perubahan status permohonan dan tindakan lain setelah tanda tangan. */
function muk_after_sign(string $kode, string $party, array $ctx, array $data, int $fid, string $st): void
{
    if ($ctx['lingkup'] !== 'permohonan') {
        if ($kode === 'AK.05') {
            notify(users_with_perm('decision.manage', $ctx['lsp_id']), $ctx['lsp_id'], 'laporan.asesmen', 'Laporan asesmen (FR.AK.05) masuk',
                'Asesor menyerahkan laporan asesmen jadwal ' . $ctx['row']['tanggal'] . ' (' . $ctx['row']['skema_nama'] . '). Berkas siap diputuskan.', 'plenoA');
        }
        return;
    }
    $p = $ctx['row'];
    if ($kode === 'APL.02' && $party === 'asesor' && ($data['rekom'] ?? '') === 'tidak') {
        set_status($ctx['id'], 'pra_asesmen', 'tidak_lanjut', ['rekom_pra' => 'tidak', 'catatan_pra' => $data['catatan'] ?? 'Asesmen tidak dapat dilanjutkan (FR.APL.02).']);
        notify([$ctx['asesi_id']], $ctx['lsp_id'], 'pra.tidak', 'Pra-asesmen: belum dapat dilanjutkan', 'Catatan asesor: ' . ($data['catatan'] ?? '-'), 'jadwal');
    }
    if ($kode === 'AK.01' && $party === 'asesor') {
        notify([$ctx['asesi_id']], $ctx['lsp_id'], 'ak01.ttd', 'Tandatangani persetujuan asesmen',
            "Asesor sudah menandatangani FR.AK.01 untuk {$p['skema_nama']}. Tinjau dan tandatangani agar asesmen dapat dilaksanakan.", 'jadwal');
    }
    if ($kode === 'AK.01' && $st === 'final') {
        set_status($ctx['id'], 'pra_asesmen', 'siap_uji', ['rekom_pra' => 'lanjut', 'catatan_pra' => null]);
        notify([$ctx['asesi_id'], $ctx['asesor_id']], $ctx['lsp_id'], 'pra.lanjut', 'Persetujuan asesmen lengkap: siap uji',
            "Asesmen {$p['skema_nama']} untuk {$p['asesi_nama']} dilaksanakan {$data['tanggal']} ({$data['waktu']}) di {$data['tuk']}.", 'jadwal');
    }
    if ($kode === 'AK.02' && $party === 'asesor') {
        $hasil = [];
        foreach ($data['unit'] ?? [] as $u => $r) {
            $hasil[(string)$u] = $r['hasil'];
        }
        $rekom = in_array('BK', $hasil, true) ? 'BK' : 'K';
        set_status($ctx['id'], 'siap_uji', 'menunggu_pleno', ['hasil' => json_encode($hasil), 'rekomendasi' => $rekom,
            'catatan_asesor' => $data['komentar'] ?? ($data['tindak_lanjut'] ?? null), 'asesor_id' => uid(), 'diuji_at' => now()]);
        notify([$ctx['asesi_id']], $ctx['lsp_id'], 'ak02.ttd', 'Rekaman asesmen siap ditandatangani',
            "Asesor sudah merekam hasil asesmen {$p['skema_nama']}. Tandatangani FR.AK.02 dan isi umpan balik FR.AK.03.", 'jadwal');
    }
    if ($kode === 'AK.04') {
        q('INSERT INTO banding (lsp_id, permohonan_id, asesi_id, formulir_id, alasan, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$ctx['lsp_id'], $ctx['id'], $ctx['asesi_id'], $fid, $data['alasan'], 'diajukan', now(), now()]);
        notify(users_with_perm('appeal.decide', $ctx['lsp_id']), $ctx['lsp_id'], 'banding.baru', 'Banding asesmen diajukan',
            "{$p['asesi_nama']} mengajukan banding atas keputusan {$p['skema_nama']}.", 'banding');
    }
}

/* =========================== Berkas per permohonan =========================== */

/** Daftar periksa kelengkapan berkas asesmen satu permohonan (urutan Buku Kerja). */
function muk_berkas(array $ctx): array
{
    $aktif = muk_instrumen_aktif($ctx);
    $list = ['APL.01', 'APL.02', 'AK.07', 'AK.01'];
    foreach ($aktif as $ia) {
        $list[] = $ia;
    }
    $list[] = 'AK.02';
    $list[] = 'AK.03';
    if (q('SELECT 1 FROM banding WHERE permohonan_id = ?', [$ctx['id']])->fetch() || in_array($ctx['row']['status'], ['kompeten', 'belum_kompeten'], true)) {
        $list[] = 'AK.04';
    }
    $defs = muk_defs();
    $out = [];
    foreach ($list as $kode) {
        $f = muk_find($ctx, $kode);
        $ttd = $f ? muk_ttd_of((int)$f['id']) : [];
        $out[] = ['kode' => $kode, 'judul' => $defs[$kode]['judul'], 'tahap' => $defs[$kode]['tahap'], 'status' => $f['status'] ?? 'belum',
            'ttd' => array_map(function ($p, $l) use ($ttd) { return ['peran' => $p, 'label' => $l, 'sudah' => isset($ttd[$p])]; }, array_keys($defs[$kode]['ttd']), $defs[$kode]['ttd']),
            'pihak' => array_values(array_filter(muk_parties($ctx, $defs[$kode]), function ($p) use ($ttd) { return !isset($ttd[$p]); })),
            'catatan' => muk_blocker($kode, $ctx, $ttd)];
    }
    return $out;
}

function r_muk_berkas(): void
{
    require_method('GET');
    require_auth();
    $ctx = muk_ctx_permohonan((int)($_GET['permohonan_id'] ?? 0));
    if (!muk_can_view($ctx, muk_defs()['APL.01'])) {
        fail('Permohonan tidak ditemukan.', 404);
    }
    $p = $ctx['row'];
    json_out(['permohonan' => ['id' => $ctx['id'], 'status' => $p['status'], 'status_label' => P_STATUS[$p['status']] ?? $p['status'], 'skema_nama' => $p['skema_nama'],
        'asesi_nama' => $p['asesi_nama'], 'tanggal' => $p['tanggal'], 'asesor_nama' => $p['asesor_nama']],
        'potensi' => muk_potensi($ctx), 'instrumen' => muk_instrumen_aktif($ctx), 'items' => muk_berkas($ctx),
        'banding' => muk_banding_rows(['b.permohonan_id = ?'], [$ctx['id']])]);
}

/** Formulir per jadwal untuk asesor (AK.05, AK.06) dan pemantauan staf. */
function r_muk_jadwal(): void
{
    require_method('GET');
    require_auth();
    $ctx = muk_ctx_jadwal((int)($_GET['jadwal_id'] ?? 0));
    $def = muk_defs()['AK.05'];
    if (!muk_can_view($ctx, $def)) {
        fail('Jadwal tidak ditemukan.', 404);
    }
    $out = [];
    foreach (['AK.05', 'AK.06'] as $kode) {
        $f = muk_find($ctx, $kode);
        $out[] = ['kode' => $kode, 'judul' => muk_defs()[$kode]['judul'], 'status' => $f['status'] ?? 'belum', 'id' => $f ? (int)$f['id'] : null,
            'boleh' => (bool)muk_parties($ctx, muk_defs()[$kode]) && (!$f || $f['status'] !== 'final')];
    }
    $rows = q('SELECT p.id, p.status, u.nama FROM permohonan p JOIN users u ON u.id = p.asesi_id WHERE p.jadwal_id = ? ORDER BY u.nama', [$ctx['id']])->fetchAll();
    json_out(['header' => muk_header($ctx), 'items' => $out, 'asesi' => array_map(function ($r) {
        return ['id' => (int)$r['id'], 'nama' => $r['nama'], 'status' => $r['status'], 'status_label' => P_STATUS[$r['status']] ?? $r['status']];
    }, $rows)]);
}

/** Permohonan yang tercakup laporan asesmen (FR.AK.05) yang sudah ditandatangani. */
function muk_ak05_mencakup(int $pid, int $jadwalId): bool
{
    foreach (q("SELECT f.data FROM formulir f WHERE f.jadwal_id = ? AND f.kode = 'AK.05' AND f.status = 'final'", [$jadwalId])->fetchAll(PDO::FETCH_COLUMN) as $d) {
        if (isset((json_decode((string)$d, true) ?: [])['asesi'][(string)$pid])) {
            return true;
        }
    }
    return false;
}

/* =========================== Perangkat asesmen (MUK) =========================== */

function muk_perangkat_row(array $p): array
{
    $u = function ($id) { return $id ? q('SELECT nama, no_met FROM users WHERE id = ?', [$id])->fetch() ?: null : null; };
    $pe = $u($p['penyusun_id']);
    $va = $u($p['validator_id']);
    return ['id' => (int)$p['id'], 'kode' => $p['kode'], 'judul' => muk_perangkat_defs()[$p['kode']]['judul'] ?? $p['kode'], 'versi' => (int)$p['versi'],
        'status' => $p['status'], 'penyusun' => $pe ? ['nama' => $pe['nama'], 'no_met' => $pe['no_met'], 'at' => $p['penyusun_at']] : null,
        'validator' => $va ? ['nama' => $va['nama'], 'no_met' => $va['no_met'], 'at' => $p['validator_at']] : null, 'catatan' => $p['catatan'], 'updated_at' => $p['updated_at']];
}

/** Skema dalam cakupan pengguna untuk perangkat: staf LSP atau asesor aktif di LSP skema itu. */
function muk_skema_scope(int $sid, bool $tulis): array
{
    $s = q('SELECT * FROM skema WHERE id = ?', [$sid])->fetch();
    if (!$s) {
        fail('Skema tidak ditemukan.', 404);
    }
    $lsp = (int)$s['lsp_id'];
    $ok = muk_staf_lsp($lsp) || in_array($lsp, asesor_lsps(), true);
    if ($tulis) {
        $ok = can('muk.manage') && $ok;
    }
    if (!$ok) {
        audit('access.denied', 'perangkat:' . $sid);
        fail('Skema tidak ditemukan.', 404);
    }
    return $s;
}

function r_muk_perangkat(): void
{
    require_method('GET');
    require_auth();
    $sid = (int)($_GET['skema_id'] ?? 0);
    if (!$sid) {
        // Daftar skema yang perangkatnya dapat dikelola pengguna.
        $m = active_membership();
        $lsps = is_platform($m) ? null : array_values(array_unique(array_merge(muk_staf_lsp((int)$m['lsp_id']) ? [(int)$m['lsp_id']] : [], asesor_lsps())));
        $f = isset($_GET['lsp']) && ctype_digit((string)$_GET['lsp']) && is_platform($m) ? [(int)$_GET['lsp']] : $lsps;
        $rows = q('SELECT s.id, s.kode, s.nama, l.nama AS lsp_nama FROM skema s JOIN lsp l ON l.id = s.lsp_id WHERE s.status = \'aktif\''
            . ($f === null ? '' : ' AND s.lsp_id IN (' . in_list_sql($f) . ')') . ' ORDER BY l.id, s.id', $f ?? [])->fetchAll();
        json_out(['items' => array_map(function ($s) {
            $n = (int)q("SELECT COUNT(DISTINCT kode) FROM muk_perangkat WHERE skema_id = ? AND status = 'valid'", [$s['id']])->fetchColumn();
            return ['id' => (int)$s['id'], 'kode' => $s['kode'], 'nama' => $s['nama'], 'lsp_nama' => $s['lsp_nama'], 'valid' => $n, 'total' => count(muk_perangkat_defs())];
        }, $rows), 'boleh' => can('muk.manage')]);
    }
    $s = muk_skema_scope($sid, false);
    $items = [];
    foreach (muk_perangkat_defs() as $kode => $d) {
        $all = q('SELECT * FROM muk_perangkat WHERE skema_id = ? AND kode = ? ORDER BY versi DESC', [$sid, $kode])->fetchAll();
        $items[] = ['kode' => $kode, 'judul' => $d['judul'], 'urut' => $d['urut'], 'terbaru' => $all ? muk_perangkat_row($all[0]) : null,
            'valid' => ($v = array_values(array_filter($all, function ($x) { return $x['status'] === 'valid'; }))) ? muk_perangkat_row($v[0]) : null];
    }
    $va = q("SELECT id, status, data, updated_at FROM formulir WHERE skema_id = ? AND kode = 'VA' AND permohonan_id IS NULL AND jadwal_id IS NULL ORDER BY id DESC", [$sid])->fetchAll();
    json_out(['skema' => ['id' => (int)$s['id'], 'kode' => $s['kode'], 'nama' => $s['nama'], 'kemasan' => $s['kemasan'] ?? null],
        'units' => muk_units($sid), 'elemen' => muk_elemen($sid), 'items' => $items, 'boleh' => can('muk.manage'),
        'validasi' => array_map(function ($f) {
            $d = json_decode((string)$f['data'], true) ?: [];
            return ['id' => (int)$f['id'], 'status' => $f['status'], 'tanggal' => $d['tanggal'] ?? null, 'periode' => $d['periode'] ?? null, 'updated_at' => $f['updated_at']];
        }, $va), 'boleh_va' => can('assessment.review')]);
}

/** Definisi bagian perangkat (MAPA memakai bagian, perangkat lain memakai daftar butir). */
function muk_perangkat_bagian(string $kode): array
{
    if ($kode === 'MAPA.01') {
        return muk_mapa01_def();
    }
    if ($kode === 'MAPA.02') {
        return muk_mapa02_def();
    }
    $d = muk_perangkat_defs()[$kode];
    return [['j' => 'Butir perangkat', 'f' => [['k' => 'items', 't' => 'grid', 'baris' => 'bebas', 'min' => 1, 'kol' => $d['item']]]]];
}

function r_muk_perangkat_detail(): void
{
    require_method('GET');
    require_auth();
    $sid = (int)($_GET['skema_id'] ?? 0);
    $kode = is_string($_GET['kode'] ?? null) ? $_GET['kode'] : '';
    if (!isset(muk_perangkat_defs()[$kode])) {
        fail('Perangkat tidak dikenal.', 404);
    }
    $s = muk_skema_scope($sid, false);
    $p = q('SELECT * FROM muk_perangkat WHERE skema_id = ? AND kode = ? ORDER BY versi DESC LIMIT 1', [$sid, $kode])->fetch();
    $ctx = muk_ctx_skema($sid);
    $def = ['kode' => $kode, 'judul' => muk_perangkat_defs()[$kode]['judul'], 'pengisi' => 'penyusun', 'ttd' => ['penyusun' => 'Penyusun', 'validator' => 'Validator'],
        'bagian' => muk_perangkat_bagian($kode)];
    $me = uid();
    $pihak = [];
    if (can('muk.manage')) {
        if (!$p || $p['status'] === 'draf' || $p['status'] === 'valid') {
            $pihak[] = 'penyusun';
        }
        if ($p && $p['status'] === 'menunggu_validasi' && (int)$p['penyusun_id'] !== $me) {
            $pihak[] = 'validator';
        }
    }
    json_out(['kode' => $kode, 'def' => muk_def_payload($def, $ctx, [], false), 'header' => muk_header($ctx),
        'perangkat' => $p ? muk_perangkat_row($p) : null, 'data' => (object)($p ? (json_decode((string)$p['data'], true) ?: []) : muk_perangkat_awal($kode, $sid)),
        'pihak' => $pihak, 'skema_lsp' => (int)$s['lsp_id']]);
}

/** Isian awal perangkat baru: kriteria reviu produk dari formulir asli; rencana per unit kosong. */
function muk_perangkat_awal(string $kode, int $sid): array
{
    if ($kode === 'IA.11') {
        $items = [];
        foreach (['Ukuran produk sesuai rencana atau gambar kerja', 'Estetika/penampilan produk', 'Kebersihan dan kerapian permukaan produk'] as $k) {
            $items[] = ['aspek' => 'Spesifikasi produk', 'kriteria' => $k];
        }
        foreach (['Kesesuaian ukuran (dimensi dan/atau berat)', 'Kesesuaian dengan gambar kerja atau bentuk', 'Kualitas produk sesuai dengan rujukan'] as $k) {
            $items[] = ['aspek' => 'Performa produk atau Karakteristik Produk', 'kriteria' => $k];
        }
        return ['items' => $items];
    }
    return [];
}

function r_muk_perangkat_simpan(): void
{
    require_method('POST');
    csrf_check();
    require_perm('muk.manage');
    [$s, $kode, $p, $data] = muk_perangkat_prep();
    $t = now();
    if (!$p || $p['status'] === 'valid') {
        // Perubahan atas perangkat tervalidasi menjadi versi baru; versi lama tetap dipakai sampai versi baru divalidasi.
        $versi = $p ? (int)$p['versi'] + 1 : 1;
        q('INSERT INTO muk_perangkat (lsp_id, skema_id, kode, versi, data, status, penyusun_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$s['lsp_id'], $s['id'], $kode, $versi, json_encode($data, JSON_UNESCAPED_UNICODE), 'draf', uid(), $t, $t]);
        $id = (int)db()->lastInsertId();
    } elseif ($p['status'] === 'draf') {
        q('UPDATE muk_perangkat SET data = ?, penyusun_id = ?, updated_at = ? WHERE id = ?', [json_encode($data, JSON_UNESCAPED_UNICODE), uid(), $t, $p['id']]);
        $id = (int)$p['id'];
    } else {
        fail('Perangkat sedang menunggu validasi; tunggu hasil validasi atau minta validator mengembalikannya.', 409);
    }
    audit('perangkat.simpan', "$kode:$id", null, (int)$s['lsp_id']);
    json_out(['ok' => true, 'id' => $id]);
}

function r_muk_perangkat_ttd(): void
{
    require_method('POST');
    csrf_check();
    require_perm('muk.manage');
    $b = body();
    $sebagai = is_string($b['sebagai'] ?? null) ? $b['sebagai'] : '';
    if ($sebagai === 'validator') {
        $s = muk_skema_scope(int_in('skema_id'), true);
        $kode = str_in('kode', 20);
        $p = q("SELECT * FROM muk_perangkat WHERE skema_id = ? AND kode = ? ORDER BY versi DESC LIMIT 1", [$s['id'], $kode])->fetch();
        if (!$p || $p['status'] !== 'menunggu_validasi') {
            fail('Perangkat ini tidak sedang menunggu validasi.', 409);
        }
        if ((int)$p['penyusun_id'] === uid()) {
            audit('perangkat.ditolak_independensi', "$kode:" . $p['id']);
            fail('Validator harus orang yang berbeda dari penyusun perangkat.', 403);
        }
        if (!muk_no_met(uid()) && !can('decision.manage') && !can('quality.manage') && !can('registration.verify')) {
            fail('Nomor registrasi (No. Reg MET) Anda belum tercatat.', 409);
        }
        $aksi = str_in('aksi', 20);
        $cat = str_in('catatan', 500, 'Catatan');
        if ($aksi === 'kembalikan') {
            if (text_len($cat) < 5) {
                fail('Tulis catatan perbaikan untuk penyusun (minimal 5 karakter).', 422);
            }
            q("UPDATE muk_perangkat SET status = 'draf', penyusun_at = NULL, catatan = ?, updated_at = ? WHERE id = ?", [$cat, now(), $p['id']]);
            notify([(int)$p['penyusun_id']], (int)$s['lsp_id'], 'perangkat.kembali', 'Perangkat asesmen perlu perbaikan', "$kode skema {$s['nama']}: $cat", 'muk');
        } else {
            if ((body()['setuju'] ?? false) !== true) {
                fail('Centang pernyataan tanda tangan elektronik terlebih dahulu.', 422);
            }
            q("UPDATE muk_perangkat SET status = 'valid', validator_id = ?, validator_at = ?, catatan = ?, updated_at = ? WHERE id = ?", [uid(), now(), $cat ?: null, now(), $p['id']]);
            notify([(int)$p['penyusun_id']], (int)$s['lsp_id'], 'perangkat.valid', 'Perangkat asesmen tervalidasi', "$kode skema {$s['nama']} versi {$p['versi']} sudah divalidasi.", 'muk');
        }
        audit('perangkat.validasi.' . ($aksi === 'kembalikan' ? 'kembali' : 'valid'), "$kode:" . $p['id'], null, (int)$s['lsp_id']);
        json_out(['ok' => true]);
    }
    if ((body()['setuju'] ?? false) !== true) {
        fail('Centang pernyataan tanda tangan elektronik terlebih dahulu.', 422);
    }
    [$s, $kode, $p, $data] = muk_perangkat_prep();
    $def = ['kode' => $kode, 'pengisi' => 'penyusun', 'ttd' => ['penyusun' => 'Penyusun'], 'bagian' => muk_perangkat_bagian($kode)];
    $miss = muk_missing($def, $data, 'penyusun', muk_ctx_skema((int)$s['id']), []);
    if ($kode === 'MAPA.02') {
        $any = false;
        foreach ($data['peta'] ?? [] as $r) {
            $any = $any || !empty($r['potensi']);
        }
        if (!$any) {
            $miss[] = 'Pilih minimal satu instrumen untuk potensi asesi';
        }
    }
    if ($miss) {
        fail('Lengkapi dulu: ' . implode('; ', array_slice($miss, 0, 6)) . '.', 422);
    }
    $t = now();
    if (!$p || $p['status'] === 'valid') {
        $versi = $p ? (int)$p['versi'] + 1 : 1;
        q('INSERT INTO muk_perangkat (lsp_id, skema_id, kode, versi, data, status, penyusun_id, penyusun_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$s['lsp_id'], $s['id'], $kode, $versi, json_encode($data, JSON_UNESCAPED_UNICODE), 'menunggu_validasi', uid(), $t, $t, $t]);
    } elseif ($p['status'] === 'draf') {
        q("UPDATE muk_perangkat SET data = ?, status = 'menunggu_validasi', penyusun_id = ?, penyusun_at = ?, catatan = NULL, updated_at = ? WHERE id = ?",
            [json_encode($data, JSON_UNESCAPED_UNICODE), uid(), $t, $t, $p['id']]);
    } else {
        fail('Perangkat sudah menunggu validasi.', 409);
    }
    notify(array_values(array_diff(users_with_perm('muk.manage', (int)$s['lsp_id']), [uid()])), (int)$s['lsp_id'], 'perangkat.validasi', 'Perangkat menunggu validasi',
        "$kode skema {$s['nama']} disusun " . current_user()['nama'] . ' dan menunggu validasi.', 'muk');
    audit('perangkat.ttd_penyusun', $kode, null, (int)$s['lsp_id']);
    json_out(['ok' => true]);
}

function muk_perangkat_prep(): array
{
    $s = muk_skema_scope(int_in('skema_id'), true);
    $kode = str_in('kode', 20);
    if (!isset(muk_perangkat_defs()[$kode])) {
        fail('Perangkat tidak dikenal.', 404);
    }
    $p = q('SELECT * FROM muk_perangkat WHERE skema_id = ? AND kode = ? ORDER BY versi DESC LIMIT 1', [$s['id'], $kode])->fetch() ?: null;
    $def = ['kode' => $kode, 'pengisi' => 'penyusun', 'ttd' => ['penyusun' => 'Penyusun'], 'bagian' => muk_perangkat_bagian($kode)];
    $data = $p ? (json_decode((string)$p['data'], true) ?: []) : muk_perangkat_awal($kode, (int)$s['id']);
    $in = is_array(body()['data'] ?? null) ? body()['data'] : [];
    $data = muk_merge($def, $data, $in, 'penyusun', muk_ctx_skema((int)$s['id']), []);
    return [$s, $kode, $p, $data];
}

/* =========================== Banding (FR.AK.04) =========================== */

function muk_banding_blocker(array $ctx): ?string
{
    $p = $ctx['row'];
    if (!in_array($p['status'], ['kompeten', 'belum_kompeten'], true) || !$p['diputuskan_at']) {
        return 'Banding dapat diajukan setelah keputusan sertifikasi ditetapkan.';
    }
    $hari = (int)(q('SELECT banding_hari FROM skema WHERE id = ?', [$ctx['skema_id']])->fetchColumn() ?: 7);
    if (strtotime($p['diputuskan_at']) + $hari * 86400 < time()) {
        return "Batas waktu pengajuan banding ($hari hari sejak keputusan) sudah lewat.";
    }
    return null;
}

function muk_banding_rows(array $where, array $params): array
{
    $rows = q('SELECT b.*, u.nama AS asesi_nama, s.nama AS skema_nama, p.keputusan, p.pleno_oleh, j.asesor_id, d.nama AS pemutus_nama, j.tanggal
               FROM banding b JOIN permohonan p ON p.id = b.permohonan_id JOIN users u ON u.id = b.asesi_id JOIN skema s ON s.id = p.skema_id
               JOIN jadwal j ON j.id = p.jadwal_id LEFT JOIN users d ON d.id = b.diputus_oleh WHERE ' . implode(' AND ', $where) . ' ORDER BY b.id DESC', $params)->fetchAll();
    return array_map(function ($b) {
        return ['id' => (int)$b['id'], 'permohonan_id' => (int)$b['permohonan_id'], 'asesi_nama' => $b['asesi_nama'], 'skema_nama' => $b['skema_nama'],
            'tanggal_uji' => $b['tanggal'], 'keputusan_awal' => $b['keputusan'], 'alasan' => $b['alasan'], 'status' => $b['status'], 'hasil' => $b['hasil'],
            'catatan' => $b['catatan'], 'pemutus_nama' => $b['pemutus_nama'], 'created_at' => $b['created_at'], 'diputus_at' => $b['diputus_at'],
            'boleh' => $b['status'] === 'diajukan' && can('appeal.decide') && !in_array(uid(), [(int)$b['pleno_oleh'], (int)$b['asesor_id'], (int)$b['asesi_id']], true)];
    }, $rows);
}

function r_banding(): void
{
    require_method('GET');
    $m = require_perm('appeal.decide');
    $scope = scope_lsp($m);
    json_out(['items' => muk_banding_rows($scope === null ? ['1=1'] : ['b.lsp_id = ?'], $scope === null ? [] : [$scope])]);
}

function r_banding_putus(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('appeal.decide');
    $scope = scope_lsp($m);
    $b = q('SELECT b.*, p.pleno_oleh, j.asesor_id, s.nama AS skema_nama FROM banding b JOIN permohonan p ON p.id = b.permohonan_id JOIN jadwal j ON j.id = p.jadwal_id
            JOIN skema s ON s.id = p.skema_id WHERE b.id = ?' . ($scope === null ? '' : ' AND b.lsp_id = ?'), $scope === null ? [int_in('id')] : [int_in('id'), $scope])->fetch();
    if (!$b) {
        fail('Banding tidak ditemukan.', 404);
    }
    if (in_array(uid(), [(int)$b['pleno_oleh'], (int)$b['asesor_id'], (int)$b['asesi_id']], true)) {
        audit('banding.ditolak_independensi', 'banding:' . $b['id']);
        fail('Banding harus diputus oleh personil yang tidak terlibat dalam asesmen maupun keputusan yang dibanding (PBNSP 201 §9.8.4).', 403);
    }
    $hasil = str_in('hasil', 20);
    $cat = str_in('catatan', 1000, 'Catatan');
    if (!in_array($hasil, ['diterima', 'ditolak'], true)) {
        fail('Pilih hasil banding.', 422);
    }
    if (text_len($cat) < 10) {
        fail('Tuliskan dasar keputusan banding (minimal 10 karakter).', 422);
    }
    if (q("UPDATE banding SET status = 'diputus', hasil = ?, catatan = ?, diputus_oleh = ?, diputus_at = ?, updated_at = ? WHERE id = ? AND status = 'diajukan'",
        [$hasil, $cat, uid(), now(), now(), $b['id']])->rowCount() !== 1) {
        fail('Banding ini sudah diputus.', 409);
    }
    audit('banding.' . $hasil, 'banding:' . $b['id'], null, (int)$b['lsp_id']);
    notify([(int)$b['asesi_id']], (int)$b['lsp_id'], 'banding.' . $hasil, $hasil === 'diterima' ? 'Banding diterima' : 'Banding ditolak',
        "Banding skema {$b['skema_nama']}: $cat", 'jadwal');
    if ($hasil === 'diterima') {
        notify(users_with_perm('schedule.manage', (int)$b['lsp_id']), (int)$b['lsp_id'], 'banding.diterima', 'Banding diterima: jadwalkan asesmen ulang',
            "Banding asesi pada skema {$b['skema_nama']} diterima. Tindak lanjut: $cat", 'jadwalA');
    }
    json_out(['ok' => true]);
}

/* =========================== Pendaftaran (APL.01/APL.02) & verifikasi =========================== */

/**
 * Isi & tandatangani FR.APL.01 dan FR.APL.02 atas nama asesi (saat mendaftar atau mengirim perbaikan).
 * Mengembalikan peta unit → K/BK yang diturunkan dari asesmen mandiri per elemen.
 */
function muk_isi_pendaftaran(array $ctx): string
{
    $b = body();
    $a1 = is_array($b['apl01'] ?? null) ? $b['apl01'] : [];
    $a2 = is_array($b['apl02'] ?? null) ? $b['apl02'] : [];
    if (!isset($a1['tujuan']) && isset($b['tujuan'])) {
        $a1['tujuan'] = $b['tujuan'];
    }
    if (!muk_elemen($ctx['skema_id'])) {
        fail('Skema ini belum memiliki elemen dan KUK, sehingga asesmen mandiri (FR.APL.02) belum bisa diisi. Hubungi LSP.', 409);
    }
    $out = [];
    foreach (['APL.01' => $a1, 'APL.02' => $a2] as $kode => $in) {
        $def = muk_defs()[$kode] + ['kode' => $kode];
        $f = muk_find($ctx, $kode);
        $data = $f ? (json_decode((string)$f['data'], true) ?: []) : muk_prefill($kode, $ctx);
        $data = muk_merge($def, $data, $in, 'asesi', $ctx, []);
        $miss = muk_missing($def, $data, 'asesi', $ctx, []);
        if ($miss) {
            fail(($kode === 'APL.01' ? 'FR.APL.01' : 'FR.APL.02 (asesmen mandiri)') . ' belum lengkap: ' . implode('; ', array_slice($miss, 0, 5))
                . (count($miss) > 5 ? ' (dan ' . (count($miss) - 5) . ' lainnya)' : '') . '.', 422);
        }
        $id = muk_save($ctx, $kode, $data, $f);
        q("DELETE FROM formulir_ttd WHERE formulir_id = ? AND peran = 'asesi'", [$id]);
        muk_sign_row($id, 'asesi', $data);
        muk_refresh_status($id, $def);
        $out[$kode] = $data;
    }
    $unit = [];
    foreach (muk_elemen($ctx['skema_id']) as $e) {
        $v = $out['APL.02']['elemen'][(string)$e['id']]['kbk'] ?? 'K';
        $unit[(string)$e['unit_id']] = ($unit[(string)$e['unit_id']] ?? 'K') === 'BK' || $v === 'BK' ? 'BK' : 'K';
    }
    return json_encode($unit);
}

/** Verifikasi FR.APL.01 oleh Admin LSP: status tiap bukti dan rekomendasi diterima/tidak, lalu tanda tangan admin. */
function muk_verifikasi_apl01(array $ctx, string $aksi, string $catatan): void
{
    $def = muk_defs()['APL.01'] + ['kode' => 'APL.01'];
    $f = muk_find($ctx, 'APL.01');
    $data = $f ? (json_decode((string)$f['data'], true) ?: []) : muk_prefill('APL.01', $ctx);
    $in = is_array(body()['verifikasi'] ?? null) ? body()['verifikasi'] : [];
    $in['rekom'] = $aksi === 'terima' ? 'diterima' : 'tidak';
    $in['catatan'] = $catatan !== '' ? $catatan : null;
    $data = muk_merge($def, $data, $in, 'admin', $ctx, []);
    $miss = muk_missing($def, $data, 'admin', $ctx, []);
    if ($miss) {
        fail('Verifikasi FR.APL.01 belum lengkap: ' . implode('; ', array_slice($miss, 0, 5)) . '.', 422);
    }
    if ($aksi === 'terima') {
        foreach (['dasar', 'admin_bukti'] as $g) {
            foreach ($data[$g] ?? [] as $r) {
                if (($r['status'] ?? '') !== 'MS') {
                    fail('Pemohon hanya dapat diterima bila semua bukti persyaratan dasar dan administratif Memenuhi Syarat.', 422);
                }
            }
        }
    }
    $id = muk_save($ctx, 'APL.01', $data, $f);
    muk_sign_row($id, 'admin', $data);
    muk_refresh_status($id, $def);
}

/** Saat berkas dikembalikan untuk perbaikan: buka kunci isian asesi pada FR.APL.01/02. */
function muk_buka_perbaikan(array $ctx): void
{
    foreach (['APL.01', 'APL.02'] as $kode) {
        $f = muk_find($ctx, $kode);
        if ($f) {
            q("DELETE FROM formulir_ttd WHERE formulir_id = ? AND peran = 'asesi'", [$f['id']]);
            q("UPDATE formulir SET status = 'draf' WHERE id = ?", [$f['id']]);
        }
    }
}

/** Jadwal asesor yang perlu laporan (FR.AK.05) / tinjauan (FR.AK.06). */
function r_asesor_laporan(): void
{
    require_method('GET');
    require_perm('assessment.conduct');
    $l = asesor_lsps();
    if (!$l) {
        json_out(['items' => []]);
    }
    $rows = q("SELECT j.id, j.tanggal, s.nama AS skema_nama, ls.nama AS lsp_nama,
                  (SELECT COUNT(*) FROM permohonan p WHERE p.jadwal_id = j.id AND p.status IN ('menunggu_pleno','kompeten','belum_kompeten')) AS diuji,
                  (SELECT COUNT(*) FROM permohonan p WHERE p.jadwal_id = j.id AND p.status = 'siap_uji') AS belum
               FROM jadwal j JOIN skema s ON s.id = j.skema_id JOIN lsp ls ON ls.id = j.lsp_id
               WHERE j.asesor_id = ? AND j.lsp_id IN (" . in_list_sql($l) . ") AND j.tanggal <= ? ORDER BY j.tanggal DESC LIMIT 60", array_merge([uid()], $l, [today()]))->fetchAll();
    json_out(['items' => array_values(array_filter(array_map(function ($j) {
        $st = [];
        foreach (['AK.05', 'AK.06'] as $k) {
            $st[$k] = q('SELECT status FROM formulir WHERE jadwal_id = ? AND permohonan_id IS NULL AND kode = ? ORDER BY id DESC LIMIT 1', [$j['id'], $k])->fetchColumn() ?: 'belum';
        }
        $tercakup = 0;
        foreach (q("SELECT p.id FROM permohonan p WHERE p.jadwal_id = ? AND p.status IN ('menunggu_pleno','kompeten','belum_kompeten')", [$j['id']])->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $tercakup += muk_ak05_mencakup((int)$pid, (int)$j['id']) ? 1 : 0;
        }
        return (int)$j['diuji'] ? ['id' => (int)$j['id'], 'tanggal' => $j['tanggal'], 'skema_nama' => $j['skema_nama'], 'lsp_nama' => $j['lsp_nama'],
            'diuji' => (int)$j['diuji'], 'belum' => (int)$j['belum'], 'tercakup' => $tercakup, 'ak05' => $st['AK.05'], 'ak06' => $st['AK.06']] : null;
    }, $rows)))]);
}

/** Definisi FR.APL.01/02 untuk wizard pendaftaran (sebelum permohonan dibuat). */
function r_muk_def(): void
{
    require_method('GET');
    require_perm('application.own');
    $sid = (int)($_GET['skema_id'] ?? 0);
    $s = q("SELECT s.*, l.nama AS lsp_nama FROM skema s JOIN lsp l ON l.id = s.lsp_id WHERE s.id = ? AND s.status = 'aktif'", [$sid])->fetch();
    if (!$s) {
        fail('Skema tidak ditemukan.', 404);
    }
    $ctx = ['lingkup' => 'permohonan', 'id' => 0, 'lsp_id' => (int)$s['lsp_id'], 'skema_id' => $sid, 'jadwal_id' => 0, 'asesi_id' => uid(), 'asesor_id' => 0, 'row' => ['tujuan' => 'Sertifikasi']];
    $out = [];
    foreach (['APL.01', 'APL.02'] as $kode) {
        $out[$kode] = ['kode' => $kode, 'def' => muk_def_payload(muk_defs()[$kode], $ctx, [], true), 'data' => (object)muk_prefill($kode, $ctx), 'ttd' => (object)[], 'pihak' => ['asesi'],
            'header' => ['skema_judul' => $s['nama'], 'skema_nomor' => $s['kode'], 'lsp' => $s['lsp_nama'], 'asesi' => current_user()['nama']]];
    }
    $out['siap'] = (bool)muk_elemen($sid);
    json_out($out);
}

/** Formulir yang menunggu tindakan asesi (untuk pengingat di Jadwal Saya). Banding tidak dihitung sebagai tugas. */
function muk_tugas_asesi(int $pid): ?string
{
    $ctx = muk_ctx_permohonan($pid);
    $out = [];
    foreach (muk_berkas($ctx) as $it) {
        if ($it['kode'] !== 'AK.04' && in_array('asesi', $it['pihak'], true) && !$it['catatan'] && !in_array($it['kode'], ['APL.01', 'APL.02'], true)) {
            $out[] = $it['judul'];
        }
    }
    return $out ? implode('; ', $out) : null;
}
