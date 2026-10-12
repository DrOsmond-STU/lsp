<?php
declare(strict_types=1);

/* Migrasi 12: alur paperless Buku Kerja 333-25 (skema, struktur, peran) dan berkas digital untuk data demo yang sudah ada. */

function muk_migrasi_skema(): void
{
    $d = ddl();
    [$pk, $fk, $e] = [$d['pk'], $d['fk'], $d['end']];
    $txt = driver() === 'mysql' ? 'MEDIUMTEXT' : 'TEXT';
    foreach ([
        "ALTER TABLE skema ADD COLUMN kemasan VARCHAR(20) NOT NULL DEFAULT 'KKNI'",
        'ALTER TABLE skema ADD COLUMN masa_berlaku_tahun INT NOT NULL DEFAULT 3',
        'ALTER TABLE skema ADD COLUMN banding_hari INT NOT NULL DEFAULT 7',
        'ALTER TABLE skema_units ADD COLUMN kelompok INT NOT NULL DEFAULT 1',
        'ALTER TABLE users ADD COLUMN no_met VARCHAR(40) NULL',
        "CREATE TABLE skema_elemen (id $pk, unit_id $fk NOT NULL, no INT NOT NULL, judul VARCHAR(255) NOT NULL, kuk TEXT NOT NULL,
            FOREIGN KEY (unit_id) REFERENCES skema_units(id))$e",
        'CREATE INDEX idx_elemen_unit ON skema_elemen (unit_id, no)',
        "CREATE TABLE muk_perangkat (id $pk, lsp_id $fk NOT NULL, skema_id $fk NOT NULL, kode VARCHAR(20) NOT NULL, versi INT NOT NULL, data $txt NOT NULL,
            status VARCHAR(20) NOT NULL, penyusun_id $fk NULL, penyusun_at DATETIME NULL, validator_id $fk NULL, validator_at DATETIME NULL,
            catatan VARCHAR(500) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            FOREIGN KEY (skema_id) REFERENCES skema(id), FOREIGN KEY (lsp_id) REFERENCES lsp(id))$e",
        'CREATE UNIQUE INDEX idx_perangkat_versi ON muk_perangkat (skema_id, kode, versi)',
        "CREATE TABLE formulir (id $pk, lsp_id $fk NOT NULL, permohonan_id $fk NULL, jadwal_id $fk NULL, skema_id $fk NULL, kode VARCHAR(20) NOT NULL,
            data $txt NOT NULL, status VARCHAR(20) NOT NULL, created_by $fk NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            FOREIGN KEY (lsp_id) REFERENCES lsp(id), FOREIGN KEY (permohonan_id) REFERENCES permohonan(id))$e",
        'CREATE UNIQUE INDEX idx_formulir_permohonan ON formulir (permohonan_id, kode)',
        'CREATE INDEX idx_formulir_jadwal ON formulir (jadwal_id, kode)',
        'CREATE INDEX idx_formulir_skema ON formulir (skema_id, kode)',
        "CREATE TABLE formulir_ttd (id $pk, formulir_id $fk NOT NULL, peran VARCHAR(20) NOT NULL, user_id $fk NOT NULL, nama VARCHAR(190) NOT NULL,
            no_reg VARCHAR(40) NULL, hash VARCHAR(64) NOT NULL, ip VARCHAR(45) NOT NULL, signed_at DATETIME NOT NULL,
            FOREIGN KEY (formulir_id) REFERENCES formulir(id), FOREIGN KEY (user_id) REFERENCES users(id))$e",
        'CREATE UNIQUE INDEX idx_ttd_peran ON formulir_ttd (formulir_id, peran)',
        "CREATE TABLE banding (id $pk, lsp_id $fk NOT NULL, permohonan_id $fk NOT NULL, asesi_id $fk NOT NULL, formulir_id $fk NULL, alasan TEXT NOT NULL,
            status VARCHAR(20) NOT NULL, hasil VARCHAR(20) NULL, catatan TEXT NULL, diputus_oleh $fk NULL, diputus_at DATETIME NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (permohonan_id) REFERENCES permohonan(id))$e",
        'CREATE INDEX idx_banding_lsp ON banding (lsp_id, status)',
    ] as $sql) {
        db()->exec($sql);
    }
    // Peran & hak akses baru; keputusan sertifikasi berpindah ke Komite Teknis.
    foreach (ROLE_DEFS as $code => [$nama, $scope, $multi]) {
        if (!q('SELECT 1 FROM roles WHERE code = ?', [$code])->fetch()) {
            q('INSERT INTO roles (code, nama, scope, multi_lsp) VALUES (?, ?, ?, ?)', [$code, $nama, $scope, $multi ? 1 : 0]);
        }
    }
    seed_rbac_missing();
    q("DELETE FROM role_permissions WHERE perm_code = 'decision.manage' AND role_code IN ('admin_lsp','manajer_mutu')");
}

/** Elemen & KUK, kelompok pekerjaan, parameter skema, No. Reg MET asesor. */
function muk_migrasi_acuan(): void
{
    $isi = muk_isi_elemen();
    $kel = muk_isi_kelompok();
    foreach (q('SELECT id, kode FROM skema_units')->fetchAll() as $u) {
        q('UPDATE skema_units SET kelompok = ? WHERE id = ?', [$kel[$u['kode']] ?? 1, $u['id']]);
        foreach ($isi[$u['kode']] ?? [] as $i => [$judul, $kuk]) {
            q('INSERT INTO skema_elemen (unit_id, no, judul, kuk) VALUES (?, ?, ?, ?)', [$u['id'], $i + 1, $judul, json_encode($kuk, JSON_UNESCAPED_UNICODE)]);
        }
    }
    q("UPDATE skema SET kemasan = 'Okupasi' WHERE kode IN ('BRS','HKS')");
    q("UPDATE skema SET kemasan = 'Klaster' WHERE kode IN ('DM')");
    $n = 3412;
    foreach (q("SELECT DISTINCT u.id FROM users u JOIN memberships m ON m.user_id = u.id AND m.role = 'asesor' ORDER BY u.id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        q('UPDATE users SET no_met = ? WHERE id = ? AND no_met IS NULL', [sprintf('MET.000.%06d.2019', $n++), $id]);
    }
}

/** Akun demo untuk peran baru (Komite Teknis, Manajer Mutu, asesor kedua/validator). */
function muk_migrasi_akun(): void
{
    global $CONFIG;
    $hashes = $CONFIG['seed_password_hashes'] ?? [];
    if (!q('SELECT 1 FROM users WHERE email = ?', ['admin.tdn@demo.portallsp.id'])->fetch()) {
        return; // instalasi bersih tanpa data demo
    }
    // Akun contoh yang sudah ada diberi alamat demo agar bisa dipakai masuk (password diatur dari konfigurasi/alat deploy).
    foreach (['fitri.h@contoh.portallsp.id' => 'mutu.tdn@demo.portallsp.id', 'lina.marlina@contoh.portallsp.id' => 'asesor2@demo.portallsp.id'] as $lama => $baru) {
        if (!q('SELECT 1 FROM users WHERE email = ?', [$baru])->fetch()) {
            q('UPDATE users SET email = ? WHERE email = ?', [$baru, $lama]);
        }
    }
    seed_user('komite.tdn@demo.portallsp.id', 'Dr. Hendro Wibowo, M.T.', [[1, 'komite_teknis']]);
    foreach ([[2, 'gede.s@contoh.portallsp.id', 'I Gede Suarjana, S.ST.Par.'], [3, 'hadi.p@contoh.portallsp.id', 'Ir. Hadi Purnomo'], [4, 'yanto.s@contoh.portallsp.id', 'Drs. Yanto Sukmana']] as [$lsp, $email, $nama]) {
        seed_user($email, $nama, [[$lsp, 'komite_teknis']]);
    }
    foreach (['komite.tdn@demo.portallsp.id', 'mutu.tdn@demo.portallsp.id', 'asesor2@demo.portallsp.id'] as $email) {
        if (!empty($hashes[$email])) {
            q('UPDATE users SET password_hash = ? WHERE email = ?', [$hashes[$email], $email]);
        }
    }
}

/** Perangkat asesmen tervalidasi untuk setiap skema (penyusun dan validator berbeda). */
function muk_migrasi_perangkat(): array
{
    $isi = muk_isi_perangkat();
    $out = [];
    foreach (q('SELECT id, lsp_id, kode, nama, created_at FROM skema ORDER BY id')->fetchAll() as $s) {
        $as = q("SELECT u.id FROM users u JOIN memberships m ON m.user_id = u.id AND m.role = 'asesor' AND m.status = 'aktif' WHERE m.lsp_id = ? ORDER BY u.id", [$s['lsp_id']])->fetchAll(PDO::FETCH_COLUMN);
        if (count($as) < 2) {
            continue;
        }
        [$penyusun, $validator] = [(int)$as[0], (int)$as[1]];
        $c = $isi[$s['kode']] ?? null;
        if (!$c) {
            continue;
        }
        $at = date('Y-m-d H:i:s', strtotime($s['created_at'] . ' +2 days'));
        $units = muk_units((int)$s['id']);
        $rencana = [];
        foreach ($units as $u) {
            $rencana[(string)$u['id']] = ['bukti' => 'Hasil demonstrasi/observasi kinerja sesuai KUK unit ' . $u['kode'] . ', portofolio pekerjaan, dan jawaban pertanyaan pengetahuan',
                'jenis' => ['L', 'TL', 'T'], 'metode' => ['obs', 'tanya', 'cvp']];
        }
        $peta = muk_isi_peta($s['kode']);
        $data = [
            'MAPA.01' => ['asesi' => ['1', '2', '3', '4', '5'], 'tujuan' => ['Sertifikasi', 'Pengakuan Kompetensi Terkini (PKT)'], 'lingkungan' => ['Tempat kerja simulasi'],
                'peluang' => 'Tersedia', 'hubungan' => ['Bukti untuk mendukung asesmen', 'Aktivitas kerja di tempat kerja Asesi'], 'pelaku' => 'Lembaga Sertifikasi',
                'konfirmasi' => ['Manajer sertifikasi LSP', 'Master Asesor / Master Trainer / Lead Asesor Kompetensi'], 'standar' => $c['standar'], 'rencana' => $rencana,
                'karakteristik' => 'Tidak ada karakteristik khusus asesi pada kelompok homogen; bila ada dicatat pada FR.AK.07.', 'kontekstual' => 'Tidak ada',
                'saran' => 'Tidak ada', 'penyesuaian' => 'Tidak ada', 'integrasi' => 'Observasi dan pertanyaan pendukung observasi dilaksanakan terintegrasi per kelompok pekerjaan.'],
            'MAPA.02' => ['peta' => $peta],
            'IA.02' => ['items' => $c['IA.02']],
            'IA.03' => ['items' => array_map(function ($q) { return ['kelompok' => 'Seluruh kelompok pekerjaan', 'pertanyaan' => $q]; }, $c['IA.03'])],
            'IA.05' => ['items' => $c['IA.05']],
            'IA.07' => ['items' => $c['IA.07']],
            'IA.11' => muk_perangkat_awal('IA.11', (int)$s['id']),
        ];
        if (!empty($c['IA.04'])) {
            $data['IA.04'] = ['items' => $c['IA.04']];
        }
        if (!empty($c['IA.06'])) {
            $data['IA.06'] = ['items' => $c['IA.06']];
        }
        foreach ($data as $kode => $d) {
            q('INSERT INTO muk_perangkat (lsp_id, skema_id, kode, versi, data, status, penyusun_id, penyusun_at, validator_id, validator_at, created_at, updated_at)
               VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)', [$s['lsp_id'], $s['id'], $kode, json_encode($d, JSON_UNESCAPED_UNICODE), 'valid', $penyusun, $at, $validator,
                date('Y-m-d H:i:s', strtotime($at . ' +1 day')), $at, $at]);
        }
        $out[(int)$s['id']] = [$penyusun, $validator];
    }
    return $out;
}

/* ---------- Pengisian berkas untuk permohonan yang sudah ada ---------- */

function muk_seed_form(array $ctx, string $kode, array $data, array $ttd, ?int $jadwalOnly = null): int
{
    $def = muk_defs()[$kode];
    $pmap = muk_perangkat_map($def, $ctx, $data);
    if (!empty($def['perangkat']) && !empty($pmap[$def['perangkat']])) {
        $data['_perangkat'] = (int)$pmap[$def['perangkat']]['id'];
    }
    $first = $ttd ? min(array_map(function ($x) { return $x[1]; }, $ttd)) : now();
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $st = array_diff(array_keys($def['ttd']), array_keys($ttd)) ? 'draf' : 'final';
    q('INSERT INTO formulir (lsp_id, permohonan_id, jadwal_id, skema_id, kode, data, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$ctx['lsp_id'], $ctx['lingkup'] === 'permohonan' ? $ctx['id'] : null, $ctx['jadwal_id'] ?: null, $ctx['skema_id'], $kode, $json, $st, null, $first, $first]);
    $fid = (int)db()->lastInsertId();
    foreach ($ttd as $peran => [$uid, $at]) {
        $u = q('SELECT nama, no_met FROM users WHERE id = ?', [$uid])->fetch();
        q('INSERT INTO formulir_ttd (formulir_id, peran, user_id, nama, no_reg, hash, ip, signed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$fid, $peran, $uid, $u['nama'], $peran === 'asesor' ? $u['no_met'] : null, hash('sha256', $fid . '|' . $peran . '|' . $json), '127.0.0.1', $at]);
    }
    return $fid;
}

function muk_at(?string $base, string $shift = '+0 hours'): string
{
    return date('Y-m-d H:i:s', strtotime(($base ?: now()) . ' ' . $shift));
}

/** Isi berkas sesuai tahap setiap permohonan contoh. */
function muk_migrasi_berkas(): void
{
    $perms = q(permohonan_sql() . ' ORDER BY p.id')->fetchAll();
    if (!$perms) {
        return;
    }
    $admin = [];
    foreach (q("SELECT m.lsp_id, MIN(m.user_id) AS uid FROM memberships m WHERE m.role = 'admin_lsp' AND m.status = 'aktif' GROUP BY m.lsp_id")->fetchAll() as $r) {
        $admin[(int)$r['lsp_id']] = (int)$r['uid'];
    }
    $pendidikan = ['JWD' => 'SMK Rekayasa Perangkat Lunak', 'ADJ' => 'S1 Statistika', 'DM' => 'D3 Manajemen Pemasaran', 'BRS' => 'SMK Tata Boga',
        'HKS' => 'D3 Perhotelan', 'TIL' => 'SMK Teknik Instalasi Tenaga Listrik', 'APK' => 'D3 Administrasi Perkantoran'];
    $tempat = [['PT Nusantara Solusi Digital', 'Staf'], ['CV Karya Mandiri', 'Pelaksana'], ['Belum bekerja / fresh graduate', '-'], ['PT Sinar Jaya Abadi', 'Koordinator']];
    $buktiPool = ['Sertifikat pelatihan berbasis kompetensi', 'Portofolio hasil kerja', 'Surat keterangan pengalaman kerja', 'Laporan praktik kerja lapangan', 'Ijazah dan transkrip nilai'];
    $jadwalSelesai = [];
    foreach ($perms as $i => $p) {
        $ctx = muk_ctx_permohonan((int)$p['id']);
        $st = $p['status'];
        $asesi = (int)$p['asesi_id'];
        $asesor = (int)($p['asesor_id'] ?: $p['jadwal_asesor_id']);
        $adm = $admin[(int)$p['lsp_id']] ?? null;
        $tp = $tempat[$i % count($tempat)];
        $diajukan = $p['diajukan_at'] ?: $p['created_at'];
        $verified = !in_array($st, ['diajukan', 'perbaikan', 'dibatalkan'], true) || $p['diverifikasi_at'];
        $verifAt = $p['diverifikasi_at'] ?: muk_at($diajukan, '+1 day');
        // FR.APL.01
        $dasar = [];
        foreach (muk_rows(['baris' => 'persyaratan'], $ctx, null, false) as $r) {
            $dasar[$r['key']] = ['bukti' => $r['key'] === '1' ? 'Ijazah ' . ($pendidikan[$p['skema_kode']] ?? 'terakhir') : 'Terlampir di profil (dokumen asesi)',
                'status' => $verified && $st !== 'dibatalkan' ? ($st === 'ditolak' && $r['key'] === '1' ? 'TMS' : 'MS') : null];
        }
        $admB = [];
        foreach (['ktp', 'foto', 'ijazah'] as $k) {
            $admB[$k] = ['status' => $verified && $st !== 'dibatalkan' ? 'MS' : null];
        }
        $apl1 = ['pendidikan' => $pendidikan[$p['skema_kode']] ?? 'SMA/SMK', 'institusi' => $tp[0], 'jabatan' => $tp[1], 'alamat_kantor' => null, 'telp_kantor' => null,
            'tujuan' => $p['tujuan'] ?: 'Sertifikasi', 'dasar' => $dasar, 'admin_bukti' => $admB];
        $ttd1 = $st === 'perbaikan' ? [] : ['asesi' => [$asesi, $diajukan]];
        if ($verified && $adm && !in_array($st, ['diajukan', 'perbaikan', 'dibatalkan'], true)) {
            $apl1['rekom'] = $st === 'ditolak' ? 'tidak' : 'diterima';
            $apl1['catatan'] = $p['catatan_admin'];
            $ttd1['admin'] = [$adm, $verifAt];
        }
        muk_seed_form($ctx, 'APL.01', $apl1, $ttd1);
        // FR.APL.02
        $el = [];
        foreach (muk_elemen((int)$p['skema_id']) as $j => $e) {
            $el[(string)$e['id']] = ['kbk' => 'K', 'bukti' => $buktiPool[($i + $j) % count($buktiPool)]];
        }
        $apl2 = ['elemen' => $el];
        $ttd2 = $st === 'perbaikan' ? [] : ['asesi' => [$asesi, $diajukan]];
        $praAt = muk_at($p['dibayar_at'] ?: $verifAt, '+1 day');
        $lewatPra = in_array($st, ['siap_uji', 'menunggu_pleno', 'kompeten', 'belum_kompeten', 'tidak_lanjut'], true);
        if ($lewatPra && $asesor) {
            $apl2['rekom'] = $st === 'tidak_lanjut' ? 'tidak' : 'dapat';
            $apl2['catatan'] = $st === 'tidak_lanjut' ? $p['catatan_pra'] : null;
            $ttd2['asesor'] = [$asesor, $praAt];
        }
        muk_seed_form($ctx, 'APL.02', $apl2, $ttd2);
        if (!$lewatPra || $st === 'tidak_lanjut' || !$asesor) {
            continue;
        }
        // FR.AK.07 & FR.AK.01
        $pot = ((int)$p['id'] % 4 === 0) ? '3' : '1';
        $butir = [];
        foreach (range(1, 8) as $n) {
            $butir[(string)$n] = ['perlu' => 'Tidak', 'ket' => null];
        }
        muk_seed_form($ctx, 'AK.07', ['potensi' => $pot, 'butir' => $butir, 'sepakat' => 'tidak'], ['asesor' => [$asesor, $praAt], 'asesi' => [$asesi, muk_at($praAt, '+2 hours')]]);
        $ak01 = muk_prefill('AK.01', $ctx) + ['coi' => 'Ya', 'banding_dijelaskan' => 'Ya'];
        muk_seed_form($ctx, 'AK.01', $ak01, ['asesor' => [$asesor, muk_at($praAt, '+1 hours')], 'asesi' => [$asesi, muk_at($praAt, '+3 hours')]]);
        if ($st === 'siap_uji') {
            continue;
        }
        // Instrumen & rekaman asesmen
        $hasil = json_decode((string)$p['hasil'], true) ?: [];
        $ujiAt = $p['diuji_at'] ?: muk_at($p['tanggal'] . ' 15:00:00');
        $bkUnits = array_keys(array_filter($hasil, function ($v) { return $v === 'BK'; }));
        foreach (muk_instrumen_aktif($ctx) as $ia) {
            $data = muk_seed_instrumen($ia, $ctx, $bkUnits, $tp);
            $ttd = muk_defs()[$ia]['ttd'];
            $sig = [];
            foreach (array_keys($ttd) as $k => $peran) {
                $sig[$peran] = [$peran === 'asesor' ? $asesor : $asesi, muk_at($ujiAt, '-' . (3 - $k) . ' hours')];
            }
            muk_seed_form($ctx, $ia, $data, $sig);
        }
        $ak02 = muk_prefill('AK.02', $ctx);
        foreach ($ak02['unit'] as $uid => &$r) {
            $r['hasil'] = $hasil[$uid] ?? 'K';
        }
        unset($r);
        $ak02['tindak_lanjut'] = $bkUnits ? 'Asesi perlu latihan tambahan pada unit yang belum kompeten dan dapat mengikuti asesmen ulang untuk unit tersebut.' : null;
        $ak02['komentar'] = $p['catatan_asesor'] ?: ($bkUnits ? 'Kinerja belum konsisten pada sebagian KUK.' : 'Asesi menunjukkan kinerja konsisten sesuai KUK.');
        muk_seed_form($ctx, 'AK.02', $ak02, ['asesor' => [$asesor, $ujiAt], 'asesi' => [$asesi, muk_at($ujiAt, '+10 minutes')]]);
        if ($st !== 'menunggu_pleno' || (int)$p['id'] % 3 !== 0) {
            $komp = [];
            foreach (range(1, 10) as $n) {
                $komp[(string)$n] = ['hasil' => 'Ya', 'catatan' => null];
            }
            muk_seed_form($ctx, 'AK.03', ['komponen' => $komp, 'lainnya' => null], ['asesi' => [$asesi, muk_at($ujiAt, '+30 minutes')]]);
        }
        $jadwalSelesai[(int)$p['jadwal_id']] = [$asesor, max($jadwalSelesai[(int)$p['jadwal_id']][1] ?? $ujiAt, $ujiAt)];
    }
    // FR.AK.05 per jadwal (laporan ke LSP) dan FR.AK.06 untuk jadwal yang sudah diputuskan.
    foreach ($jadwalSelesai as $jid => [$asesor, $at]) {
        $ctx = muk_ctx_jadwal($jid);
        $ak05 = muk_prefill('AK.05', $ctx) + ['negatif' => 'Sebagian asesi kurang teliti membaca instruksi kerja.', 'positif' => 'Asesi kooperatif, sarana TUK lengkap dan berfungsi baik.',
            'saran' => 'Tambahkan simulasi singkat sebelum uji praktik.', 'catatan' => null];
        muk_seed_form($ctx, 'AK.05', $ak05, ['asesor' => [$asesor, muk_at($at, '+1 hours')]]);
        $selesai = !q("SELECT 1 FROM permohonan WHERE jadwal_id = ? AND status = 'menunggu_pleno'", [$jid])->fetch();
        if ($selesai && $jid % 2 === 0) {
            $pr = [];
            foreach (['rencana' => ['V', 'R', 'F', 'A'], 'persiapan' => ['V', 'R', 'F', 'A'], 'implementasi' => ['V', 'R', 'F', 'A'], 'keputusan' => ['V', 'R', 'A'], 'umpan' => ['V', 'R', 'A']] as $k => $v) {
                $pr[$k] = ['sesuai' => $v];
            }
            muk_seed_form($ctx, 'AK.06', ['prinsip' => $pr, 'rekom1' => 'Pertahankan konsistensi penerapan instrumen; perbarui skenario TPD setiap tahun.',
                'tugas' => 'IA.01 CL, IA.02 TPD', 'manajemen' => 'IA.02 TPD (urutan kerja)', 'kontinjensi' => 'IA.03 PMO', 'lingkungan' => 'IA.01 CL, IA.07 DPL',
                'transfer' => 'IA.05 DPT', 'rekom2' => null, 'komentar' => null], ['asesor' => [$asesor, muk_at($at, '+2 days')]]);
        }
    }
}

/** Isian instrumen yang konsisten dengan hasil asesmen tersimpan (unit BK → sebagian KUK tidak tercapai). */
function muk_seed_instrumen(string $ia, array $ctx, array $bkUnits, array $tempat): array
{
    $def = muk_defs()[$ia];
    $pmap = muk_perangkat_map($def, $ctx, []);
    $items = $pmap ? (json_decode((string)($pmap[$def['perangkat']]['data'] ?? '{}'), true)['items'] ?? []) : [];
    $bk = (bool)$bkUnits;
    switch ($ia) {
        case 'IA.01':
            $rows = [];
            $unitOf = [];
            foreach (muk_elemen($ctx['skema_id']) as $e) {
                foreach ($e['kuk'] as $k => $_) {
                    $tidak = in_array((string)$e['unit_id'], $bkUnits, true) && $k === 0;
                    $rows[$e['id'] . '-' . ($k + 1)] = ['standar' => null, 'capai' => $tidak ? 'Tidak' : 'Ya', 'lanjut' => $tidak ? 'Dikonfirmasi dengan pertanyaan lisan' : null];
                    if ($tidak) {
                        $unitOf[] = $e['unit_kode'] . ' elemen ' . $e['no'] . ' KUK ' . $e['no'] . '.1';
                    }
                }
            }
            return ['kuk' => $rows, 'umpan_balik' => $bk ? 'Perlu latihan pada KUK yang belum tercapai.' : 'Seluruh KUK didemonstrasikan dengan baik.',
                'rekomendasi' => $bk ? 'BK' : 'K', 'bk_pada' => $unitOf ? implode('; ', $unitOf) : null];
        case 'IA.02':
            $r = [];
            foreach ($items as $n => $_) {
                $r[(string)($n + 1)] = ['mulai' => sprintf('%02d.00', 9 + $n * 2), 'selesai' => sprintf('%02d.45', 9 + $n * 2)];
            }
            return ['skenario' => $r, 'catatan' => 'Tugas dikerjakan sesuai urutan dan waktu yang ditetapkan.'];
        case 'IA.03':
            $r = [];
            foreach ($items as $n => $_) {
                $r[(string)($n + 1)] = ['tanggapan' => 'Asesi menjelaskan alasan dan langkah kerjanya sesuai SOP.', 'capai' => 'Ya'];
            }
            return ['tanya' => $r, 'umpan_balik' => 'Jawaban mendukung hasil observasi.'];
        case 'IA.04':
            return ['aspek' => [['lingkup' => 'Rancangan dan hasil proyek', 'pertanyaan' => 'Jelaskan keputusan rancangan utama Anda.', 'tanggapan' => 'Asesi menjelaskan rancangan dengan runtut.',
                'kesesuaian' => 'Seluruh unit', 'capai' => $bk ? 'Tidak' : 'Ya']], 'rekomendasi' => $bk ? 'BK' : 'K'];
        case 'IA.05':
            $r = [];
            foreach ($items as $n => $it) {
                $salah = $bk && $n === 0;
                $jw = $salah ? ($it['kunci'] === 'a' ? 'b' : 'a') : $it['kunci'];
                $r[(string)($n + 1)] = ['jawaban' => $jw, 'capai' => $jw === $it['kunci'] ? 'Ya' : 'Tidak'];
            }
            return ['jawab' => $r, 'umpan_balik' => $bk ? 'Aspek pengetahuan belum tercapai seluruhnya.' : 'Aspek pengetahuan seluruh unit tercapai.'];
        case 'IA.06':
            $r = [];
            foreach ($items as $n => $it) {
                $r[(string)($n + 1)] = ['jawaban' => 'Menurut saya, ' . lcfirst((string)$it['kunci']), 'capai' => 'Ya'];
            }
            return ['jawab' => $r, 'umpan_balik' => 'Aspek pengetahuan tercapai.'];
        case 'IA.07':
            $r = [];
            foreach ($items as $n => $it) {
                $r[(string)($n + 1)] = ['jawaban' => 'Asesi menjawab: ' . lcfirst((string)$it['kunci']), 'capai' => 'Ya'];
            }
            return ['tanya' => $r, 'umpan_balik' => 'Pengetahuan pendukung memadai.'];
        case 'IA.08':
            return ['bukti' => [['nama' => 'Surat keterangan pengalaman kerja', 'valid' => 'Ya', 'asli' => 'Ya', 'terkini' => 'Ya', 'memadai' => 'Ya'],
                ['nama' => 'Portofolio hasil kerja', 'valid' => 'Ya', 'asli' => 'Ya', 'terkini' => 'Ya', 'memadai' => $bk ? 'Tidak' : 'Ya']],
                'klarifikasi' => [['unit' => '1', 'elemen' => '1', 'materi' => 'Konfirmasi peran asesi dalam portofolio']], 'tambahan' => null,
                'rekomendasi' => $bk ? 'BK' : 'K', 'bk_pada' => $bk ? 'Unit dengan bukti portofolio belum memadai' : null];
        case 'IA.09':
            return ['tanya' => [['pertanyaan' => 'Sesuai dengan bukti no. 2 yang Anda ajukan, apa peran Anda dalam pekerjaan tersebut?', 'kesimpulan' => 'Asesi menjelaskan perannya secara rinci.', 'capai' => 'Ya']]];
        case 'IA.10':
            return ['nama' => 'Atasan langsung asesi', 'tempat' => $tempat[0], 'alamat' => null, 'telepon' => '021-5550199',
                'tanya' => array_combine(array_map('strval', range(1, 6)), array_fill(0, 6, ['jawab' => 'Ya'])), 'hubungan' => 'Atasan langsung', 'lama' => '2 tahun',
                'dekat' => 'Setiap hari dalam satu tim', 'kualifikasi' => 'Supervisor bidang terkait', 'yakin' => 'Ya, asesi bekerja sesuai standar secara konsisten.',
                'pelatihan' => null, 'komentar' => null];
        case 'IA.11':
            $r = [];
            foreach ($items as $n => $_) {
                $r[(string)($n + 1)] = ['hasil' => 'Sesuai', 'capai' => 'Ya'];
            }
            return ['produk' => 'Produk hasil kerja asesi', 'standar' => null, 'spesifikasi' => null, 'tgl_pakai' => null, 'kriteria' => $r, 'rekomendasi' => 'K', 'catatan' => null];
    }
    return [];
}

/** Contoh validasi asesmen (FR.VA) per skema dan contoh banding. */
function muk_migrasi_validasi(array $penyusunValidator): void
{
    foreach ($penyusunValidator as $sid => [$penyusun, $validator]) {
        $ctx = muk_ctx_skema($sid);
        $at = muk_at(date('Y-m-d', strtotime('-30 days')) . ' 10:00:00');
        $tim = q('SELECT nama FROM users WHERE id IN (?, ?)', [$penyusun, $validator])->fetchAll(PDO::FETCH_COLUMN);
        $aspek = [];
        foreach (range(1, 8) as $n) {
            $aspek[(string)$n] = ['bukti' => ['V', 'A', 'T', 'M'], 'prinsip' => ['V', 'R', 'F', 'A']];
        }
        muk_seed_form($ctx, 'VA', ['tim' => implode(', ', $tim), 'tanggal' => substr($at, 0, 10), 'tempat' => 'Kantor LSP', 'periode' => 'setelah',
            'tujuan' => ['Bagian dari Proses Penjaminan Mutu Organisasi', 'Mengevaluasi Kualitas Perangkat Asesmen'], 'konteks' => ['Internal Organisasi', 'Dengan Kolega Asesor'],
            'pendekatan' => ['Mengkaji perangkat asesmen', 'Mengkaji bukti-bukti'], 'orang' => array_map(function ($n) { return ['peran' => 'Asesor Kompetensi', 'nama' => $n, 'hasil' => 'Sepakat tujuan, fokus dan konteks validasi']; }, $tim),
            'acuan' => ['Standar Kompetensi (SKKNI/SKKK/SKI)', 'Skema Sertifikasi'], 'dokumen' => ['Perangkat Asesmen', 'Bukti-bukti hasil asesmen'],
            'komunikasi' => ['PRO AKTIF', 'ACTIVE LISTENING'], 'aspek' => $aspek,
            'temuan' => [['temuan' => 'Instruksi skenario TPD perlu memuat kriteria waktu per tahap.', 'rekom' => 'Tambahkan batas waktu per tahap pada FR.IA.02.']],
            'rencana' => [['kegiatan' => 'Revisi skenario FR.IA.02', 'waktu' => '1 bulan', 'pj' => $tim[0] ?? 'Penyusun']]], ['tim' => [$validator, $at]]);
    }
    // Banding contoh: satu sudah diputus, satu menunggu Komite Teknis (asesi demo bila ada).
    $bk = q("SELECT p.id, p.lsp_id, p.asesi_id, p.diputuskan_at FROM permohonan p WHERE p.status = 'belum_kompeten' AND p.lsp_id = 1 ORDER BY p.diputuskan_at DESC LIMIT 2")->fetchAll();
    $komite = (int)q("SELECT user_id FROM memberships WHERE role = 'komite_teknis' AND lsp_id = 1 ORDER BY id LIMIT 1")->fetchColumn();
    foreach ($bk as $n => $b) {
        $ctx = muk_ctx_permohonan((int)$b['id']);
        $alasan = $n === 0 ? 'Saya menilai waktu praktik terpotong karena gangguan listrik di TUK sehingga saya tidak sempat menyelesaikan seluruh tugas.'
            : 'Saya merasa pertanyaan lisan tidak berkaitan dengan unit yang diujikan.';
        $at = muk_at($b['diputuskan_at'], '+4 hours');
        $fid = muk_seed_form($ctx, 'AK.04', ['tanya' => ['1' => ['jawab' => 'Ya'], '2' => ['jawab' => 'Ya'], '3' => ['jawab' => 'Tidak']], 'alasan' => $alasan], ['asesi' => [(int)$b['asesi_id'], $at]]);
        $diputus = $n === 1 && $komite;
        q('INSERT INTO banding (lsp_id, permohonan_id, asesi_id, formulir_id, alasan, status, hasil, catatan, diputus_oleh, diputus_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$b['lsp_id'], $b['id'], $b['asesi_id'], $fid, $alasan, $diputus ? 'diputus' : 'diajukan', $diputus ? 'ditolak' : null,
                $diputus ? 'Rekaman FR.IA.07 menunjukkan seluruh pertanyaan lisan mengacu pada KUK unit yang diujikan; keputusan tetap.' : null,
                $diputus ? $komite : null, $diputus ? muk_at($at, '+3 days') : null, $at, $at]);
    }
}

function muk_migrasi_12(): void
{
    muk_migrasi_skema(); // DDL di luar transaksi (MySQL melakukan commit implisit untuk DDL)
    db()->beginTransaction();
    try {
        muk_migrasi_acuan();
        muk_migrasi_akun();
        if (q('SELECT 1 FROM users WHERE email = ?', ['admin.tdn@demo.portallsp.id'])->fetch()) {
            $pv = muk_migrasi_perangkat();
            muk_migrasi_berkas();
            muk_migrasi_validasi($pv);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}
