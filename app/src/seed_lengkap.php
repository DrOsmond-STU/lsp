<?php
declare(strict_types=1);

/*
 * Data demo lengkap (migrasi 10): riwayat sertifikasi 11 bulan di keempat LSP, antrean di setiap tahap proses,
 * notifikasi, tiket, CRM, mutu, sarana TUK, group chat, kelas, artikel, dan profil asesi. Dengan data ini setiap menu
 * menampilkan alur dan fungsinya. Hanya dibuat bila akun demo ada; instalasi bersih (tanpa akun demo) tidak tersentuh.
 * Akun tambahan memakai domain contoh.portallsp.id dengan password acak, jadi tidak bisa dipakai masuk.
 * Notifikasi dicatat langsung dengan status akhir (terkirim/gagal) sehingga tidak ada email yang benar-benar dikirim.
 */

/** Tanggal-jam relatif terhadap sekarang (hari, jam opsional). */
function sd_at(int $days, string $jam = '09:00:00'): string
{
    return date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days')) . ' ' . $jam;
}

function sd_pick(array $a)
{
    return $a[mt_rand(0, count($a) - 1)];
}

/** NIK fiktif yang lolos validasi struktur (provinsi 31, Jakarta). */
function sd_nik(int $i, string $lahir, string $jk): string
{
    $dd = (int)substr($lahir, 8, 2) + ($jk === 'P' ? 40 : 0);
    return '317405' . sprintf('%02d', $dd) . substr($lahir, 5, 2) . substr($lahir, 2, 2) . sprintf('%04d', 9000 + $i);
}

function seed_demo_lengkap(): void
{
    $demo = [];
    foreach (['superadmin', 'admin.tdn', 'admin.pbi', 'marketing.tdn', 'keuangan.tdn', 'tuk.kuningan', 'asesor', 'asesi'] as $k) {
        $demo[$k] = seed_uid("$k@demo.portallsp.id");
        if (!$demo[$k]) {
            return;
        }
    }
    if (!q('SELECT 1 FROM skema LIMIT 1')->fetch()) {
        return;
    }
    mt_srand(20261011);
    db()->beginTransaction();
    try {
        sd_isi($demo);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

/**
 * Data contoh migrasi 6 memakai satu stempel waktu untuk semua tahap. Ratakan agar runtut terhadap tanggal jadwal
 * (atau tanggal terbit sertifikat). Hanya baris yang semua stempelnya sama persis, yaitu yang belum pernah diproses.
 */
function sd_rapikan_lama(): void
{
    $rows = q('SELECT p.id, p.status, p.created_at, p.diajukan_at, p.diverifikasi_at, p.dibayar_at, p.diuji_at, p.diputuskan_at, j.tanggal,
                      (SELECT c.terbit FROM sertifikat c WHERE c.permohonan_id = p.id) AS terbit
               FROM permohonan p JOIN jadwal j ON j.id = p.jadwal_id WHERE p.diajukan_at = p.created_at')->fetchAll();
    $now = time();
    foreach ($rows as $r) {
        $stamps = array_filter([$r['diverifikasi_at'], $r['dibayar_at'], $r['diuji_at'], $r['diputuskan_at']]);
        if (array_diff(array_unique($stamps), [$r['diajukan_at']])) {
            continue;
        }
        // Hari uji = tanggal jadwal, kecuali sertifikat contoh sengaja terbit jauh sebelumnya (uji 2 hari sebelum terbit).
        $acuan = strtotime($r['terbit'] && $r['terbit'] < $r['tanggal'] ? $r['terbit'] . ' -2 days' : $r['tanggal']);
        $put = $r['terbit'] ? strtotime($r['terbit'] . ' 10:00:00') : min($acuan + 2 * 86400 + 36000, $now - 3600);
        $uji = min($acuan + 36000, $now - 7200);
        $daftar = min($acuan - 10 * 86400, $now - 3 * 86400) + 32400;
        $f = function (int $ts): string { return date('Y-m-d H:i:s', $ts); };
        $set = ['diajukan_at' => $f($daftar), 'created_at' => $f($daftar)];
        if ($r['diverifikasi_at']) $set['diverifikasi_at'] = $f($daftar + 86400);
        if ($r['dibayar_at']) $set['dibayar_at'] = $f($daftar + 2 * 86400);
        if ($r['diuji_at']) $set['diuji_at'] = $f($uji);
        if ($r['diputuskan_at']) $set['diputuskan_at'] = $f($put);
        $set['updated_at'] = end($set);
        q('UPDATE permohonan SET ' . implode(', ', array_map(function ($k) { return "$k = ?"; }, array_keys($set))) . ' WHERE id = ?',
            array_merge(array_values($set), [$r['id']]));
        if ($r['dibayar_at']) {
            q("UPDATE tagihan SET created_at = ?, dibayar_at = ? WHERE permohonan_id = ? AND status = 'lunas'", [$set['diverifikasi_at'], $set['dibayar_at'], $r['id']]);
        } elseif ($r['diverifikasi_at']) {
            q("UPDATE tagihan SET created_at = ? WHERE permohonan_id = ?", [$set['diverifikasi_at'], $r['id']]);
        }
    }
}

function sd_isi(array $demo): void
{
    $t = now();
    sd_rapikan_lama();

    // ---- Profil LSP: paket dan honor berbeda agar menu Paket & Tagihan dan Honor bervariasi.
    foreach ([1 => ['Enterprise', 50000, 150000], 2 => ['Basic', 1000, 125000], 3 => ['Pro', 5000, 175000], 4 => ['Basic', 1000, 150000]] as $id => [$pk, $kuota, $honor]) {
        q('UPDATE lsp SET paket = ?, kuota_asesi = ?, honor_per_asesi = ?, website = ? WHERE id = ?',
            [$pk, $kuota, $honor, 'https://lsp-' . strtolower((string)q('SELECT kode FROM lsp WHERE id = ?', [$id])->fetchColumn()) . '.example', $id]);
    }

    // ---- Staf dan asesor tambahan.
    $staf = [
        'fitri.h' => ['Fitri Handayani, S.T.', [[1, 'manajer_mutu']]],
        'komang.j' => ['Komang Juliani', [[2, 'keuangan']]],
        'rudi.h' => ['Rudi Hartono', [[3, 'admin_lsp']]],
        'siti.r' => ['Siti Rahmawati, S.E.', [[4, 'admin_lsp']]],
        'andi.s' => ['Andi Saputra, S.Kom.', [[1, 'asesor']]],
        'kadek.a' => ['Kadek Ari Wibawa', [[2, 'asesor']]],
        'niluh.p' => ['Ni Luh Putu Ayu, S.Par.', [[2, 'asesor']]],
        'bambang.s' => ['Ir. Bambang Sutrisno', [[3, 'asesor']]],
        'diana.p' => ['Diana Puspita, S.E.', [[4, 'asesor']]],
    ];
    $u = [];
    foreach ($staf as $key => [$nama, $ms]) {
        $u[$key] = seed_user("$key@contoh.portallsp.id", $nama, $ms);
    }
    $u['lina'] = seed_uid('lina.marlina@contoh.portallsp.id') ?: seed_user('lina.marlina@contoh.portallsp.id', 'Lina Marlina, S.T.', [[1, 'asesor']]);
    $asesorLsp = [
        1 => [$demo['asesor'], $u['lina'], $u['andi.s']],
        2 => [$u['kadek.a'], $u['niluh.p']],
        3 => [$demo['asesor'], $u['bambang.s']],
        4 => [$demo['asesor'], $u['diana.p']],
    ];
    $adminLsp = [1 => $demo['admin.tdn'], 2 => $demo['admin.pbi'], 3 => $u['rudi.h'], 4 => $u['siti.r']];

    // Jadwal bawaan migrasi 6 menugaskan asesor demo di semua skema LSP 1/3/4 pada tanggal yang sama (bentrok dengan aturan
    // satu jadwal per hari). Skema JWD tetap dipegang asesor demo (dipakai skenario uji coba); skema lain dialihkan.
    $pengganti = ['ADJ' => $u['andi.s'], 'DM' => $u['lina'], 'TIL' => $u['bambang.s'], 'APK' => $u['diana.p']];
    $awal = q('SELECT MIN(created_at) FROM jadwal')->fetchColumn();
    foreach ($pengganti as $kode => $aid) {
        q('UPDATE jadwal SET asesor_id = ? WHERE created_at = ? AND asesor_id = ? AND skema_id IN (SELECT id FROM skema WHERE kode = ?)', [$aid, $awal, $demo['asesor'], $kode]);
    }

    // ---- Asesi contoh dengan profil (NIK terenkripsi) dan dokumen. Nama disusun dari kombinasi nama depan/belakang per daerah.
    // Nama depan yang dipakai skenario uji (Bayu, Rina, Dewi, Sari, Maya, Dimas, Hendra, Agung, Putri) sengaja dihindari.
    $daerah = [
        1 => [45, ['Fajar' => 'L', 'Anisa' => 'P', 'Bagas' => 'L', 'Citra' => 'P', 'Dedi' => 'L', 'Eka' => 'P', 'Galih' => 'L', 'Hana' => 'P', 'Irfan' => 'L',
            'Jihan' => 'P', 'Kevin' => 'L', 'Laras' => 'P', 'Rizky' => 'L', 'Nadia' => 'P', 'Oki' => 'L', 'Prisca' => 'P', 'Raka' => 'L', 'Salsabila' => 'P',
            'Teguh' => 'L', 'Vina' => 'P', 'Wahyu' => 'L', 'Yoga' => 'L', 'Aditya' => 'L', 'Bella' => 'P'],
            ['Nugroho', 'Saputra', 'Kurniawan', 'Permana', 'Safitri', 'Maulana', 'Wijaya', 'Ayuningtyas', 'Paramita', 'Setiadi', 'Amelia', 'Hidayat', 'Pratama',
                'Santoso', 'Oktaviani', 'Rahmawati', 'Hakim', 'Siregar', 'Utami', 'Nasution']],
        2 => [30, ['I Gede' => 'L', 'Ni Made' => 'P', 'Ketut' => 'L', 'Luh Putu' => 'P', 'Wayan' => 'L', 'Desak Nyoman' => 'P', 'I Putu' => 'L', 'Ni Kadek' => 'P',
            'Nyoman' => 'L', 'Ni Komang' => 'P', 'I Made' => 'L', 'Ni Wayan' => 'P'],
            ['Mahendra', 'Lestari', 'Sudarsana', 'Ariani', 'Arta', 'Wirawan', 'Yanti', 'Suarjana', 'Wulandari', 'Santika', 'Astawa', 'Suardana', 'Antari', 'Wiguna']],
        3 => [25, ['Slamet' => 'L', 'Bambang' => 'L', 'Joko' => 'L', 'Heru' => 'L', 'Sugeng' => 'L', 'Wawan' => 'L', 'Andik' => 'L', 'Yudi' => 'L', 'Eko' => 'L',
            'Arif' => 'L', 'Ninik' => 'P', 'Endah' => 'P'],
            ['Riyadi', 'Wibowo', 'Susilo', 'Setiawan', 'Prasetyo', 'Raharjo', 'Gunawan', 'Firmansyah', 'Purnomo', 'Utomo', 'Kusuma', 'Hartanto']],
        4 => [25, ['Fitria' => 'P', 'Lestari' => 'P', 'Ratih' => 'P', 'Yuliana' => 'P', 'Nur' => 'P', 'Mega' => 'P', 'Indah' => 'P', 'Ayu' => 'P', 'Dian' => 'P',
            'Novi' => 'P', 'Reza' => 'L', 'Ilham' => 'L'],
            ['Kartika', 'Mulyani', 'Ningsih', 'Handayani', 'Purnamasari', 'Aini', 'Puspita', 'Permatasari', 'Anggraeni', 'Safira', 'Maharani', 'Ramadhan']],
    ];
    $png = 'uploads/contoh/contoh.png';
    $pngFile = APP_ROOT . '/storage/' . $png;
    if (!is_file($pngFile)) {
        if (!is_dir(dirname($pngFile))) {
            mkdir(dirname($pngFile), 0700, true);
        }
        file_put_contents($pngFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }
    $pngSize = (int)filesize($pngFile);
    // Hash murah untuk password acak akun contoh: akun ini memang tidak bisa dipakai masuk.
    $hashContoh = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT, ['cost' => 4]);
    $pool = [];
    $i = 0;
    $terpakai = [];
    foreach ($daerah as $lsp => [$jumlah, $depan, $belakang]) {
        $fn = array_keys($depan);
        for ($k = 0, $dibuat = 0; $dibuat < $jumlah && $k < 500; $k++) {
            $first = $fn[$k % count($fn)];
            $last = $belakang[(int)($k * 7 + intdiv($k, count($fn))) % count($belakang)];
            $nama = "$first $last";
            $email = trim(strtolower((string)preg_replace('/[^a-z]+/i', '.', $nama)), '.') . '@contoh.portallsp.id';
            if (isset($terpakai[$email]) || $first === $last) {
                continue;
            }
            $terpakai[$email] = true;
            $dibuat++;
            $i++;
            $uid = seed_uid($email);
            if (!$uid) {
                q('INSERT INTO users (email, nama, password_hash, status, must_change_password, created_at, updated_at, email_verified_at) VALUES (?, ?, ?, ?, 0, ?, ?, ?)',
                    [$email, $nama, $hashContoh, 'aktif', $t, $t, $t]);
                $uid = (int)db()->lastInsertId();
                q('INSERT INTO memberships (user_id, lsp_id, role, status, created_at) VALUES (?, ?, ?, ?, ?)', [$uid, $lsp, 'asesi', 'aktif', $t]);
            }
            $pool[$lsp][] = $uid;
            foreach (['ktp' => 'KTP', 'ijazah' => 'Ijazah', 'foto' => 'Pas foto'] as $j => $label) {
                if (!q('SELECT 1 FROM dokumen WHERE user_id = ? AND jenis = ?', [$uid, $j])->fetch()) {
                    q('INSERT INTO dokumen (user_id, jenis, nama_file, path, mime, ukuran, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$uid, $j, "$label (contoh).png", $png, 'image/png', $pngSize, $t]);
                }
            }
            sd_profil($uid, $i, sprintf('%04d-%02d-%02d', mt_rand(1985, 2004), mt_rand(1, 12), mt_rand(1, 28)), $depan[$first], sprintf('0812%08d', 30000000 + $i * 7919));
        }
    }
    // Profil asesi demo (bila belum diisi saat uji coba).
    sd_profil($demo['asesi'], 0, '1998-04-17', 'P', '081290001234');

    // ---- Skema dan TUK bawaan tiap skema.
    $skema = q('SELECT s.id, s.lsp_id, s.kode, s.nama, s.harga, l.kode AS lsp_kode, l.nama AS lsp_nama FROM skema s JOIN lsp l ON l.id = s.lsp_id ORDER BY s.id')->fetchAll();
    foreach ($skema as &$s) {
        $j = q('SELECT tuk_id, metode FROM jadwal WHERE skema_id = ? ORDER BY id LIMIT 1', [$s['id']])->fetch();
        $s['tuk_id'] = $j ? $j['tuk_id'] : null;
        $s['metode'] = $j ? $j['metode'] : 'Tatap muka';
        $s['units'] = q('SELECT id FROM skema_units WHERE skema_id = ? ORDER BY urut', [$s['id']])->fetchAll(PDO::FETCH_COLUMN);
    }
    unset($s);
    $byKode = [];
    foreach ($skema as $s) {
        $byKode[$s['kode']] = $s;
    }

    $ctx = ['t' => $t, 'admin' => $adminLsp, 'notif' => []];
    $jadwal = function (array $s, string $tanggal, ?int $asesor, string $status, int $kuota = 20, ?string $catatan = null) use ($t): int {
        q('INSERT INTO jadwal (lsp_id, skema_id, tuk_id, tanggal, jam, metode, kuota, asesor_id, status, catatan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$s['lsp_id'], $s['id'], $s['tuk_id'], $tanggal, '08.00–16.00', $s['metode'], $kuota, $asesor, $status, $catatan, $t, $t]);
        return (int)db()->lastInsertId();
    };

    // ---- Riwayat 11 bulan: jadwal selesai tiap bulan per skema, peserta dengan hasil akhir.
    // Asesi yang sudah kompeten di satu skema tidak diuji lagi di skema itu; yang belum kompeten boleh uji ulang.
    $sudahK = [];
    foreach ($skema as $s) {
        $lsp = (int)$s['lsp_id'];
        for ($m = 11; $m >= 1; $m--) {
            $tgl = date('Y-m-d', strtotime('-' . (30 * $m + mt_rand(-4, 4)) . ' days'));
            $asesor = $asesorLsp[$lsp][($m + (int)$s['id']) % count($asesorLsp[$lsp])];
            $jid = $jadwal($s, $tgl, $asesor, 'selesai');
            $n = $lsp === 1 ? mt_rand(2, 5) : mt_rand(2, 3);
            $peserta = array_values(array_filter($pool[$lsp], function ($a) use ($sudahK, $s) { return empty($sudahK[$s['id']][$a]); }));
            shuffle($peserta);
            foreach (array_slice($peserta, 0, $n) as $aid) {
                $r = mt_rand(1, 100);
                $akhir = $r <= 80 ? 'kompeten' : ($r <= 90 ? 'belum_kompeten' : ($r <= 94 ? ($m <= 2 ? 'tidak_lanjut' : 'belum_kompeten') : ($r <= 97 ? 'ditolak' : 'dibatalkan')));
                if ($akhir === 'kompeten') {
                    $sudahK[$s['id']][$aid] = true;
                }
                sd_permohonan($ctx, $s, $jid, $tgl, $aid, $asesor, $akhir, $asesorLsp[$lsp]);
            }
        }
    }

    // ---- Sertifikat lama (impor): sebagian akan habis dalam 90 hari, sebagian sudah kedaluwarsa, satu dicabut.
    foreach ([[1, 'JWD', -1040, 'aktif'], [1, 'ADJ', -1025, 'aktif'], [1, 'DM', -1015, 'aktif'], [1, 'JWD', -1160, 'aktif'], [1, 'DM', -700, 'dicabut'],
        [2, 'BRS', -1030, 'aktif'], [2, 'HKS', -1200, 'aktif'], [3, 'TIL', -1020, 'aktif'], [4, 'APK', -1050, 'aktif']] as $k => [$lsp, $kode, $ago, $st]) {
        $aid = $pool[$lsp][count($pool[$lsp]) - 1 - $k % 3];
        sd_sertifikat($byKode[$kode], null, $aid, date('Y-m-d', strtotime("$ago days")), $st, date('Y-m-d H:i:s', strtotime("$ago days")));
    }

    // ---- Antrean berjalan: jadwal mendatang/hari ini dan permohonan di setiap tahap.
    // Tanggal slot per LSP dibuat berbeda agar asesor demo (bertugas di LSP 1, 3, 4) tidak punya dua jadwal di hari yang sama,
    // dan hari ini tetap kosong untuk skenario uji coba "buat jadwal hari ini".
    $slotHari = ['uji' => [1 => -1, 2 => -1, 3 => -3, 4 => -5], 'lalu' => [1 => -4, 2 => -4, 3 => -6, 4 => -7],
        'dekat' => [1 => 4, 2 => 4, 3 => 5, 4 => 6], 'nanti' => [1 => 11, 2 => 11, 3 => 12, 4 => 13], 'jauh' => [1 => 25, 2 => 25, 3 => 26, 4 => 27]];
    $b = $demo['asesor'];
    // Asesor per skema per slot: [uji, lalu, dekat, nanti/jauh].
    $slotAsesor = [
        'JWD' => [$b, $u['lina'], $b, $u['andi.s']], 'ADJ' => [$u['andi.s'], $u['andi.s'], $u['lina'], $u['lina']], 'DM' => [$u['lina'], $b, $u['andi.s'], $u['andi.s']],
        'BRS' => [$u['kadek.a'], $u['niluh.p'], $u['niluh.p'], $u['kadek.a']], 'HKS' => [$u['niluh.p'], $u['kadek.a'], $u['kadek.a'], $u['niluh.p']],
        'TIL' => [$b, $u['bambang.s'], $b, $u['bambang.s']], 'APK' => [$u['diana.p'], $b, $b, $u['diana.p']],
    ];
    $up = [];
    $tglJadwal = [];
    foreach ($skema as $s) {
        $lsp = (int)$s['lsp_id'];
        [$aUji, $aLalu, $aDekat, $aNanti] = $slotAsesor[$s['kode']] ?? array_fill(0, 4, $asesorLsp[$lsp][0]);
        $tg = function (string $slot) use ($slotHari, $lsp): string { return rel_date($slotHari[$slot][$lsp]); };
        $up[$s['kode']] = [
            'hari_ini' => $jadwal($s, $tg('uji'), $aUji, 'ditutup'),
            'lalu' => $jadwal($s, $tg('lalu'), $aLalu, 'ditutup'),
            'dekat' => $jadwal($s, $tg('dekat'), $aDekat, 'dibuka', 15),
            'nanti' => $jadwal($s, $tg('nanti'), $aNanti, 'dibuka'),
            'jauh' => $jadwal($s, $tg('jauh'), $s['kode'] === 'DM' ? null : $aNanti, 'dibuka', 25, $s['kode'] === 'DM' ? 'Menunggu konfirmasi asesor' : null),
        ];
        foreach (['hari_ini' => 'uji', 'lalu' => 'lalu', 'dekat' => 'dekat', 'nanti' => 'nanti', 'jauh' => 'jauh'] as $k => $slot) {
            $tglJadwal[$s['kode']][$k] = $tg($slot);
        }
    }
    $alur = [
        // [kode skema, jadwal, status, jumlah]
        ['JWD', 'nanti', 'diajukan', 2], ['ADJ', 'jauh', 'diajukan', 1], ['DM', 'nanti', 'perbaikan', 1], ['JWD', 'jauh', 'ditolak', 1],
        ['ADJ', 'nanti', 'menunggu_bayar', 1], ['DM', 'jauh', 'menunggu_bayar', 1], ['JWD', 'dekat', 'pra_asesmen', 2], ['ADJ', 'dekat', 'pra_asesmen', 1],
        ['DM', 'dekat', 'tidak_lanjut', 1], ['JWD', 'hari_ini', 'siap_uji', 2], ['ADJ', 'hari_ini', 'siap_uji', 1], ['JWD', 'lalu', 'menunggu_pleno', 1],
        ['DM', 'lalu', 'menunggu_pleno', 1], ['JWD', 'lalu', 'kompeten', 2], ['ADJ', 'lalu', 'kompeten', 1], ['DM', 'lalu', 'belum_kompeten', 1],
        ['JWD', 'nanti', 'dibatalkan', 1],
        ['BRS', 'nanti', 'diajukan', 1], ['HKS', 'jauh', 'diajukan', 1], ['BRS', 'nanti', 'menunggu_bayar', 1], ['HKS', 'dekat', 'pra_asesmen', 2],
        ['BRS', 'hari_ini', 'siap_uji', 1], ['BRS', 'lalu', 'menunggu_pleno', 2], ['HKS', 'lalu', 'kompeten', 2],
        ['TIL', 'nanti', 'diajukan', 1], ['TIL', 'jauh', 'menunggu_bayar', 1], ['TIL', 'dekat', 'pra_asesmen', 2], ['TIL', 'hari_ini', 'siap_uji', 2],
        ['TIL', 'lalu', 'menunggu_pleno', 1], ['TIL', 'lalu', 'kompeten', 1],
        ['APK', 'nanti', 'diajukan', 2], ['APK', 'dekat', 'pra_asesmen', 1], ['APK', 'hari_ini', 'siap_uji', 1], ['APK', 'lalu', 'menunggu_pleno', 1],
        ['APK', 'lalu', 'kompeten', 1],
    ];
    $dipakai = [];
    foreach ($alur as [$kode, $slot, $status, $jumlah]) {
        $s = $byKode[$kode];
        $lsp = (int)$s['lsp_id'];
        $jid = $up[$kode][$slot];
        $asesor = (int)q('SELECT asesor_id FROM jadwal WHERE id = ?', [$jid])->fetchColumn() ?: null;
        for ($k = 0; $k < $jumlah; $k++) {
            // Peserta berbeda untuk setiap permohonan berjalan.
            $kandidat = array_values(array_filter(array_diff($pool[$lsp], $dipakai), function ($a) use ($sudahK, $s) { return empty($sudahK[$s['id']][$a]); }));
            if (!$kandidat) {
                if ($status === 'kompeten') {
                    continue; // jangan terbitkan sertifikat kedua untuk skema yang sama
                }
                $kandidat = array_values(array_diff($pool[$lsp], $dipakai)); // semua sudah kompeten: anggap sertifikasi ulang
            }
            $aid = $kandidat[mt_rand(0, count($kandidat) - 1)];
            $dipakai[] = $aid;
            if ($status === 'kompeten') {
                $sudahK[$s['id']][$aid] = true;
            }
            sd_permohonan($ctx, $s, $jid, $tglJadwal[$kode][$slot], $aid, $asesor, $status, $asesorLsp[$lsp], true);
        }
    }

    // ---- Kelas: pelatihan tambahan dan pesertanya.
    $kelasBaru = [
        [3, 'Persiapan Uji Teknisi Instalasi Listrik', 'Konstruksi', 'Surabaya', 'Tatap muka', 400000, 'Tatap muka 2 hari di TUK SMK Negeri 5 Surabaya. Instruktur: Ir. Bambang Sutrisno.'],
        [4, 'Workshop Arsip Digital Perkantoran', 'Bisnis', 'Jakarta', 'Online', 250000, 'Online 3 sesi. Instruktur: Diana Puspita, S.E.'],
    ];
    foreach ($kelasBaru as [$lsp, $judul, $bid, $kota, $fmt, $hrg, $desk]) {
        q("INSERT INTO listings (lsp_id, tipe, judul, bidang, kota, format, harga, deskripsi, status, created_by, submitted_at, reviewed_by, reviewed_at, created_at, updated_at)
           VALUES (?, 'pelatihan', ?, ?, ?, ?, ?, ?, 'tayang', ?, ?, ?, ?, ?, ?)",
            [$lsp, $judul, $bid, $kota, $fmt, $hrg, $desk . ' Tidak wajib untuk mendaftar uji.', $adminLsp[$lsp], sd_at(-20), $demo['superadmin'], sd_at(-18), sd_at(-21), sd_at(-18)]);
    }
    q("INSERT INTO listings (lsp_id, tipe, judul, bidang, kota, format, harga, deskripsi, status, catatan, created_by, submitted_at, reviewed_by, reviewed_at, created_at, updated_at)
       VALUES (1, 'pelatihan', ?, 'TIK', 'Jakarta', 'Online', 999000, ?, 'ditolak', ?, ?, ?, ?, ?, ?, ?)",
        ['Sertifikasi Kilat 1 Hari Pasti Lulus', 'Ikut kelas 1 hari, langsung dapat sertifikat kompetensi.',
            'Ditolak: sertifikat kompetensi hanya terbit lewat uji oleh asesor. Klaim "pasti lulus" menyesatkan.', $demo['marketing.tdn'], sd_at(-9), $demo['superadmin'], sd_at(-8), sd_at(-10), sd_at(-8)]);
    $kelas = q("SELECT id, lsp_id, judul, harga FROM listings WHERE tipe = 'pelatihan' AND status = 'tayang' ORDER BY id")->fetchAll();
    foreach ($kelas as $kl) {
        $lsp = (int)$kl['lsp_id'];
        $peserta = $pool[$lsp];
        shuffle($peserta);
        foreach (array_slice($peserta, 0, mt_rand(4, 8)) as $aid) {
            if (q('SELECT 1 FROM kelas_peserta WHERE listing_id = ? AND user_id = ?', [$kl['id'], $aid])->fetch()) {
                continue;
            }
            $r = mt_rand(1, 10);
            $st = $r <= 4 ? 'selesai' : ($r <= 9 ? 'aktif' : ((int)$kl['harga'] > 0 ? 'menunggu_bayar' : 'aktif'));
            $mulai = sd_at(-mt_rand(5, 120));
            q('INSERT INTO kelas_peserta (listing_id, user_id, status, progres, created_at) VALUES (?, ?, ?, ?, ?)',
                [$kl['id'], $aid, $st, $st === 'selesai' ? 100 : ($st === 'aktif' ? mt_rand(10, 90) : 0), $mulai]);
            $kid = (int)db()->lastInsertId();
            if ((int)$kl['harga'] > 0) {
                sd_tagihan($lsp, null, $kid, $aid, 'Kelas: ' . $kl['judul'], (int)$kl['harga'], $st === 'menunggu_bayar' ? 'belum' : 'lunas', $mulai);
            }
        }
    }
    // Asesi demo: satu kelas berbayar sudah selesai. Kelas gratis dan kelas TDN/PBI dibiarkan untuk skenario uji coba "daftar kelas".
    $arsip = q("SELECT id, lsp_id, judul, harga FROM listings WHERE tipe = 'pelatihan' AND status = 'tayang' AND judul = 'Workshop Arsip Digital Perkantoran'")->fetch();
    if ($arsip && !q('SELECT 1 FROM kelas_peserta WHERE listing_id = ? AND user_id = ?', [$arsip['id'], $demo['asesi']])->fetch()) {
        q('INSERT INTO kelas_peserta (listing_id, user_id, status, progres, created_at) VALUES (?, ?, ?, ?, ?)', [$arsip['id'], $demo['asesi'], 'selesai', 100, sd_at(-40)]);
        sd_tagihan((int)$arsip['lsp_id'], null, (int)db()->lastInsertId(), $demo['asesi'], 'Kelas: ' . $arsip['judul'], (int)$arsip['harga'], 'lunas', sd_at(-40));
    }

    // ---- Group chat di jadwal yang sedang berjalan.
    $chat = [
        ['JWD', 'dekat', [[$demo['tuk.kuningan'], 'Selamat pagi. Ruang uji lantai 3 TUK Kuningan sudah disiapkan untuk jadwal ini.', -3],
            [$demo['asesor'], 'Terima kasih. Peserta mohon membawa laptop sendiri dan KTP asli.', -3], ['asesi', 'Baik Pak, apakah boleh membawa charger cadangan?', -2],
            [$demo['asesor'], 'Boleh. Stop kontak tersedia di setiap meja.', -2], [$demo['tuk.kuningan'], 'Registrasi dibuka pukul 07.30. Parkir di basement B1.', -1]]],
        ['JWD', 'hari_ini', [[$demo['tuk.kuningan'], 'Peserta sudah registrasi lengkap. Uji dimulai pukul 08.15.', -1], [$demo['asesor'], 'Siap, saya menuju ruang uji.', -1]]],
        ['TIL', 'hari_ini', [[$demo['asesor'], 'Peserta wajib memakai APD lengkap: helm, sarung tangan, dan sepatu safety.', -3], ['asesi', 'Siap Pak, APD sudah kami siapkan.', -3]]],
        ['HKS', 'dekat', [[$u['kadek.a'], 'Uji praktik dilakukan di kamar 301–305 Hotel Sanur. Mohon datang 30 menit lebih awal.', -2], ['asesi', 'Baik, terima kasih Pak Kadek.', -2]]],
    ];
    foreach ($chat as [$kode, $slot, $pesan]) {
        $jid = $up[$kode][$slot];
        $lsp = (int)$byKode[$kode]['lsp_id'];
        $asesiJadwal = q('SELECT asesi_id FROM permohonan WHERE jadwal_id = ? ORDER BY id LIMIT 1', [$jid])->fetchColumn();
        foreach ($pesan as $n => [$by, $isi, $hari]) {
            $by = $by === 'asesi' ? (int)$asesiJadwal : $by;
            if ($by) {
                q('INSERT INTO chat_pesan (lsp_id, jadwal_id, user_id, isi, created_at) VALUES (?, ?, ?, ?, ?)', [$lsp, $jid, $by, $isi, sd_at($hari, sprintf('%02d:%02d:00', 8 + $n, 10 + $n * 7))]);
            }
        }
    }

    // ---- CRM per LSP.
    $crm = [
        1 => [['PT Logistik Andalan', 'Pak Hari (HR Manager)', 'hari@logistikandalan.example', 'Referral', 'dihubungi', 30000000, 'Butuh sertifikasi Analis Data untuk 30 staf.', 2],
            ['Politeknik Digital Jakarta', 'Bu Wulan (Kaprodi)', 'wulan@polidigi.example', 'Pameran', 'proposal', 45000000, 'Uji kompetensi mahasiswa semester akhir.', 5],
            ['Kominfo Kota Bekasi', 'Seksi SDM', '021-8800123', 'Website', 'baru', 0, null, 1],
            ['PT Retail Maju', 'Bu Sinta (L&D)', 'sinta@retailmaju.example', 'LinkedIn', 'menang', 25500000, 'PO diterima untuk 30 peserta Digital Marketing.', null]],
        2 => [['Hotel Nusa Dua Resort', 'Executive Housekeeper', 'hk@nusaduaresort.example', 'Kunjungan', 'proposal', 27000000, '30 staf housekeeping.', 4],
            ['Kafe Kopi Bali', 'Owner', '0812-3800-1111', 'Instagram', 'dihubungi', 6000000, 'Sertifikasi 10 barista.', 3],
            ['SMK Pariwisata Denpasar', 'Waka Hubin', '0361-222333', 'Pameran', 'menang', 18000000, 'Uji siswa kelas XII.', null]],
        3 => [['PT Kontraktor Jaya', 'Manajer Proyek', 'proyek@kontraktorjaya.example', 'Referral', 'proposal', 55000000, 'Syarat tender: teknisi bersertifikat.', 6],
            ['PLN UP3 Surabaya Selatan', 'Bidang SDM', 'sdm.up3@pln.example', 'Website', 'baru', 0, null, 2]],
        4 => [['Kantor Notaris Wijaya', 'Office Manager', 'office@notariswijaya.example', 'Referral', 'dihubungi', 7000000, 'Sertifikasi 10 staf administrasi.', 3],
            ['Universitas Bisnis Jakarta', 'Career Center', 'cdc@ubj.example', 'Pameran', 'kalah', 0, 'Memilih LSP lain karena jadwal.', null]],
    ];
    foreach ($crm as $lsp => $rows) {
        foreach ($rows as [$org, $nm, $kt, $src, $th, $nilai, $cat, $fu]) {
            q('INSERT INTO crm_leads (lsp_id, nama, organisasi, kontak, sumber, tahap, nilai, catatan, followup, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$lsp, $nm, $org, $kt, $src, $th, $nilai, $cat, $fu === null ? null : rel_date($fu), $lsp === 1 ? $demo['marketing.tdn'] : $adminLsp[$lsp], sd_at(-mt_rand(5, 60)), sd_at(-mt_rand(0, 4))]);
        }
    }

    // ---- Mutu (Pedoman 201) per LSP.
    $mutu = [
        1 => [['Banding & keluhan', 'Banding hasil uji DM a.n. peserta Agustus', 'Peserta mengajukan banding atas unit "Mengelola iklan digital berbayar". Panel banding dibentuk.', 'proses', 'Manajer Mutu', 6],
            ['Banding & keluhan', 'Keluhan keterlambatan sertifikat', 'Sertifikat terbit 12 hari kerja setelah pleno. Akar masalah: antrean tanda tangan.', 'selesai', 'Admin LSP', -12]],
        2 => [['Audit internal', 'Audit internal TUK Hotel Sanur', null, 'proses', 'Manajer Mutu PBI', 15], ['Ketidaksesuaian', 'Masa verifikasi TUK Hotel Sanur hampir habis', null, 'terbuka', 'Admin LSP', 30],
            ['Kaji ulang manajemen', 'Kaji ulang manajemen semester 1', null, 'selesai', 'Direktur', -40]],
        3 => [['Analisis ketidakberpihakan', 'Asesor yang juga instruktur kelas persiapan', 'Asesor tidak boleh menguji peserta kelas yang ia ajar.', 'proses', 'Manajer Mutu', 20],
            ['Tindakan perbaikan (CAPA)', 'Kalibrasi megger di TUK SMK 5', null, 'terbuka', 'Admin TUK', 10]],
        4 => [['Audit internal', 'Audit internal tahunan', null, 'terbuka', 'Manajer Mutu', 45], ['Ketidaksesuaian', 'Formulir APL.02 versi lama masih dipakai', null, 'selesai', 'Admin LSP', -5]],
    ];
    foreach ($mutu as $lsp => $rows) {
        foreach ($rows as [$jenis, $judul, $desk, $st, $pic, $due]) {
            q('INSERT INTO mutu (lsp_id, jenis, judul, deskripsi, status, pic, tenggat, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$lsp, $jenis, $judul, $desk, $st, $pic, rel_date($due), sd_at(-mt_rand(10, 60)), sd_at(-mt_rand(0, 9))]);
        }
    }

    // ---- Sarana & prasarana untuk TUK yang belum punya data.
    $sarpras = [
        'Daring (SJJ) TDN' => [['Lisensi Zoom Pro', 2, 'baik'], ['Laptop proktor', 3, 'baik'], ['Kamera dokumen', 2, 'baik'], ['Headset', 6, 'perlu perbaikan']],
        'TUK SMK Negeri 5 Surabaya' => [['Panel hubung bagi latihan', 6, 'baik'], ['Multimeter digital', 10, 'baik'], ['APD (helm, sarung tangan, sepatu)', 20, 'baik'], ['Megger 1 kV', 2, 'perlu perbaikan']],
        'TUK Graha Manajemen' => [['Komputer peserta', 20, 'baik'], ['Printer', 2, 'baik'], ['Lemari arsip', 4, 'baik'], ['Mesin fotokopi', 1, 'rusak']],
    ];
    foreach ($sarpras as $nama => $rows) {
        $tuk = q('SELECT id, lsp_id FROM tuk WHERE nama = ?', [$nama])->fetch();
        if (!$tuk || q('SELECT 1 FROM sarpras WHERE tuk_id = ?', [$tuk['id']])->fetch()) {
            continue;
        }
        foreach ($rows as [$n, $j, $k]) {
            q('INSERT INTO sarpras (lsp_id, tuk_id, nama, jumlah, kondisi, catatan, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$tuk['lsp_id'], $tuk['id'], $n, $j, $k, $k === 'baik' ? null : 'Dijadwalkan servis bulan ini.', sd_at(-mt_rand(1, 30))]);
        }
    }

    // ---- Tiket support dengan percakapan.
    $tiket = [
        [2, $demo['admin.pbi'], 'Kuota paket Basic hampir habis', 'Pendaftar kami naik. Bagaimana cara pindah ke paket Pro di tengah tahun?', 'normal', 'selesai', -20,
            [[$demo['superadmin'], 'Bisa kapan saja. Kami ubah paket Anda mulai bulan depan; tagihan disesuaikan prorata.', -19], [$demo['admin.pbi'], 'Terima kasih, kami setuju.', -19]]],
        [1, $demo['marketing.tdn'], 'Cara merevisi listing yang diminta revisi', 'Listing Bootcamp Laravel diminta revisi. Di mana tombol ubahnya?', 'rendah', 'proses', -6,
            [[$demo['superadmin'], 'Buka menu Etalase & Pelatihan, klik Ubah pada listing berstatus Perlu revisi, perbaiki deskripsi, lalu Ajukan.', -5]]],
        [3, $u['rudi.h'], 'Gagal mengunggah logo LSP', 'Muncul pesan "berkas terlalu besar" padahal ukurannya 1,5 MB.', 'tinggi', 'terbuka', -1, []],
        [1, $demo['keuangan.tdn'], 'Ekspor data pembayaran ke Excel', 'Apakah ada fitur unduh daftar tagihan untuk rekonsiliasi bank?', 'rendah', 'selesai', -30,
            [[$demo['superadmin'], 'Saat ini tersedia di Laporan BNSP (CSV). Ekspor tagihan masuk rencana pengembangan berikutnya.', -29]]],
    ];
    foreach ($tiket as [$lsp, $by, $judul, $isi, $prio, $st, $hari, $balas]) {
        q('INSERT INTO tiket (lsp_id, user_id, judul, isi, prioritas, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$lsp, $by, $judul, $isi, $prio, $st, sd_at($hari, '10:15:00'), sd_at($balas ? end($balas)[2] : $hari, '14:30:00')]);
        $tid = (int)db()->lastInsertId();
        foreach ($balas as $n => [$ub, $isiB, $hb]) {
            q('INSERT INTO tiket_balasan (tiket_id, user_id, isi, created_at) VALUES (?, ?, ?, ?)', [$tid, $ub, $isiB, sd_at($hb, sprintf('%02d:00:00', 13 + $n))]);
        }
    }

    // ---- Artikel tambahan.
    if (function_exists('blog_unique_slug')) {
        foreach ([[3, $u['rudi.h'], 'Pentingnya K3 dalam Uji Kompetensi Instalasi Listrik', 'Tips Sertifikasi', 'Mengapa APD dan prosedur K3 menjadi bagian penilaian asesor.',
            "Uji kompetensi **Teknisi Instalasi Listrik Bangunan** selalu dimulai dari unit *Menerapkan K3 instalasi listrik*.\n\n## Yang diperiksa asesor\n\n- APD lengkap dan layak pakai\n- Prosedur *lock out tag out* sebelum bekerja\n- Pengukuran tegangan sebelum menyentuh penghantar\n\nPeserta yang mengabaikan K3 tidak dapat melanjutkan uji praktik.", 'k3, listrik, konstruksi', -6],
            [4, $u['siti.r'], 'Mengenal Skema Pengelola Administrasi Perkantoran', 'Pengumuman', 'Siapa yang cocok mengikuti skema ini dan apa saja unit yang diujikan.',
            "Skema **Pengelola Administrasi Perkantoran** ditujukan bagi staf administrasi, sekretaris, dan admin kantor.\n\n## Unit yang diujikan\n\n1. Mengelola arsip dinamis\n2. Menyusun agenda kegiatan pimpinan\n3. Menangani surat masuk dan keluar\n4. Mengoperasikan aplikasi perkantoran\n\nJadwal uji tersedia setiap bulan di TUK Graha Manajemen.", 'administrasi, perkantoran', -2]] as [$lsp, $by, $judul, $kat, $ring, $isi, $tags, $hari]) {
            q('INSERT INTO blog_posts (lsp_id, judul, slug, ringkasan, konten, kategori, tags, status, terbit_at, penulis_id, dibaca, created_at, updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$lsp, $judul, blog_unique_slug(blog_slugify($judul)), $ring, $isi, $kat, $tags, 'terbit', sd_at($hari), $by, 35 * -$hari + 11, sd_at($hari), sd_at($hari)]);
        }
    }

    // ---- Notifikasi untuk akun demo (bel dan Log Notifikasi).
    $notif = [
        [$demo['asesi'], 1, 'permohonan.diterima', 'Berkas lengkap, silakan bayar', 'Skema Digital Marketing: tagihan sudah terbit. Bayar sebelum jatuh tempo.', 'bayar', -2, false],
        [$demo['asesi'], 2, 'permohonan.perbaikan', 'Berkas perlu perbaikan', 'Skema Barista: Pas foto kurang jelas. Unggah ulang pas foto berlatar merah.', 'jadwal', -1, false],
        [$demo['asesi'], 1, 'tagihan.lunas', 'Pembayaran diterima', 'Pembayaran uji Junior Web Developer sudah kami terima. Tahap berikutnya: pra-asesmen.', 'bayar', -6, true],
        [$demo['asesi'], 1, 'pleno.K', 'Selamat, Anda kompeten', 'Sertifikat Analis Data Junior sudah terbit dan bisa diunduh di Dompet Sertifikat.', 'sertifikat', -15, true],
        [$demo['asesor'], 1, 'jadwal.penugasan', 'Penugasan asesmen baru', 'Anda ditugaskan sebagai asesor Junior Web Developer pada ' . rel_date(4) . ' di LSP Teknologi Digital Nusantara.', 'kalender', -5, true],
        [$demo['asesor'], 3, 'jadwal.penugasan', 'Penugasan asesmen baru', 'Anda ditugaskan sebagai asesor Teknisi Instalasi Listrik Bangunan pada ' . rel_date(-2) . ' di LSP Konstruksi Mandiri.', 'kalender', -7, true],
        [$demo['asesor'], 1, 'pra.baru', 'Pra-asesmen baru', 'Ada berkas Junior Web Developer yang menunggu tinjauan Anda.', 'pra', -1, false],
        [$demo['asesor'], 4, 'pra.baru', 'Pra-asesmen baru', 'Ada berkas Pengelola Administrasi Perkantoran yang menunggu tinjauan Anda.', 'pra', 0, false],
        [$demo['admin.tdn'], 1, 'listing.revisi', 'Listing perlu revisi', '"Bootcamp Laravel 5 Hari" belum bisa ditayangkan. Catatan peninjau: Hapus kalimat "dijamin kompeten saat uji" di deskripsi.', 'etalase', -4, true],
        [$demo['marketing.tdn'], 1, 'listing.ditolak', 'Listing ditolak', '"Sertifikasi Kilat 1 Hari Pasti Lulus" belum bisa ditayangkan. Catatan peninjau: klaim "pasti lulus" menyesatkan.', 'etalase', -8, false],
        [$demo['marketing.tdn'], 1, 'tiket.balasan', 'Tiket support dibalas', 'Cara merevisi listing yang diminta revisi', 'support', -5, false],
        [$demo['keuangan.tdn'], 1, 'tiket.balasan', 'Tiket support dibalas', 'Ekspor data pembayaran ke Excel', 'support', -29, true],
        [$demo['admin.pbi'], 2, 'tiket.balasan', 'Tiket support dibalas', 'Kuota paket Basic hampir habis', 'support', -19, true],
        // Notifikasi peninjau platform tanpa lsp_id (sama dengan notify_listing_submitted) agar tidak muncul di log LSP.
        [$demo['superadmin'], null, 'listing.diajukan', 'Listing baru menunggu persetujuan', 'Skema "Pemrogram Junior (Junior Coder)" dari LSP Teknologi Digital Nusantara menunggu kurasi.', 'approval', -3, false],
        [$demo['superadmin'], null, 'listing.diajukan', 'Listing baru menunggu persetujuan', 'Skema "Barista Kopi Nusantara (jadwal Nov)" dari LSP Pariwisata Bahari Indonesia menunggu kurasi.', 'approval', -2, false],
    ];
    foreach ($notif as [$uid, $lsp, $type, $title, $body, $page, $hari, $dibaca]) {
        $ctx['notif'][] = [$uid, $lsp, $type, $title, $body, $page, sd_at($hari, '10:0' . mt_rand(0, 9) . ':00'), $dibaca];
    }
    foreach ($ctx['notif'] as $n => [$uid, $lsp, $type, $title, $body, $page, $at, $dibaca]) {
        q('INSERT INTO notifications (user_id, lsp_id, type, title, body, page, read_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$uid, $lsp, $type, $title, $body, $page, $dibaca ? $at : null, $at]);
        $nid = (int)db()->lastInsertId();
        $email = (string)q('SELECT email FROM users WHERE id = ?', [$uid])->fetchColumn();
        $gagal = $n % 17 === 5;
        q('INSERT INTO notification_outbox (notification_id, channel, recipient, status, attempts, last_error, next_attempt_at, sent_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$nid, 'email', $email, $gagal ? 'gagal' : 'terkirim', $gagal ? 5 : 1, $gagal ? 'SMTP: connection timed out' : null, $at, $gagal ? null : date('Y-m-d H:i:s', strtotime($at) + 40), $at]);
    }
}

function sd_profil(int $uid, int $i, string $lahir, string $jk, string $hp): void
{
    if (q('SELECT 1 FROM asesi_profiles WHERE user_id = ?', [$uid])->fetch()) {
        return;
    }
    $nik = sd_nik($i, $lahir, $jk);
    $hash = nik_index($nik);
    if (q('SELECT 1 FROM asesi_profiles WHERE nik_hash = ?', [$hash])->fetch()) {
        return;
    }
    q('INSERT INTO asesi_profiles (user_id, nik_enc, nik_hash, tanggal_lahir, jenis_kelamin, no_hp, consent_privacy_at, consent_marketing, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$uid, encrypt_text($nik), $hash, $lahir, $jk, $hp, now(), $i % 3 === 0 ? 1 : 0, now(), now()]);
}

function sd_tagihan(int $lsp, ?int $pid, ?int $kid, int $uid, string $desc, int $jumlah, string $status, string $dibuat, ?string $dibayar = null): void
{
    $dibayar = $status === 'lunas' ? ($dibayar ?? date('Y-m-d H:i:s', strtotime($dibuat) + 86400)) : null;
    q('INSERT INTO tagihan (lsp_id, permohonan_id, kelas_peserta_id, user_id, nomor, deskripsi, jumlah, status, metode, dibayar_at, jatuh_tempo, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$lsp, $pid, $kid, $uid, 'TMP-' . bin2hex(random_bytes(6)), $desc, $jumlah, $status, $status === 'lunas' ? sd_pick(['QRIS', 'Transfer BCA', 'VA Mandiri', 'Transfer BRI']) : null,
            $dibayar, date('Y-m-d', strtotime($dibuat) + 7 * 86400), $dibuat]);
    $id = (int)db()->lastInsertId();
    q('UPDATE tagihan SET nomor = ? WHERE id = ?', [sprintf('INV/%d/%s/%05d', $lsp, date('Ym', strtotime($dibuat)), $id), $id]);
}

function sd_sertifikat(array $s, ?int $pid, int $uid, string $terbit, string $status, string $created): void
{
    $kode = random_code();
    while (q('SELECT 1 FROM sertifikat WHERE kode = ?', [$kode])->fetch()) {
        $kode = random_code();
    }
    $nama = (string)q('SELECT nama FROM users WHERE id = ?', [$uid])->fetchColumn();
    q('INSERT INTO sertifikat (lsp_id, permohonan_id, user_id, skema_id, nomor, kode, terbit, berlaku, status, created_at, skema_nama, skema_kode, lsp_nama, nama_pemegang)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$s['lsp_id'], $pid, $uid, $s['id'], 'TMP-' . $kode, $kode, $terbit, date('Y-m-d', strtotime($terbit . ' +3 years')), $status, $created,
            $s['nama'], $s['kode'], $s['lsp_nama'], $nama]);
    $id = (int)db()->lastInsertId();
    q('UPDATE sertifikat SET nomor = ? WHERE id = ?', [sprintf('LSP-%s/%s/%s/%05d', $s['lsp_kode'], $s['kode'], substr($terbit, 0, 4), $id), $id]);
}

/**
 * Satu permohonan sampai status akhirnya, dengan stempel waktu yang konsisten terhadap tanggal jadwal.
 * $berjalan = true: permohonan antrean saat ini (tanggal dibatasi agar tidak melewati sekarang/awal bulan).
 */
function sd_permohonan(array &$ctx, array $s, int $jid, string $tgl, int $aid, ?int $asesor, string $akhir, array $asesorLsp, bool $berjalan = false): void
{
    $lsp = (int)$s['lsp_id'];
    $tglTs = strtotime($tgl);
    $now = time();
    $fmt = function (int $ts, string $jam = '') use ($now): string {
        $ts = min($ts, $now - 600);
        return $jam === '' ? date('Y-m-d H:i:s', $ts) : date('Y-m-d', $ts) . ' ' . $jam;
    };
    // Pendaftaran 8–14 hari sebelum uji; antrean berjalan mendaftar 1–8 hari terakhir dan selalu minimal 2 hari sebelum uji.
    $daftarTs = $berjalan ? min($tglTs - 2 * 86400, $now - 86400) - mt_rand(0, 6) * 86400 : $tglTs - mt_rand(8, 14) * 86400;
    $daftarTs = strtotime(date('Y-m-d', $daftarTs) . ' 00:00:00');
    // Verifikasi dan bayar selesai paling lambat 40 jam setelah daftar, jadi selalu sebelum hari uji.
    $diajukan = $fmt($daftarTs + mt_rand(8, 11) * 3600 + mt_rand(0, 59) * 60);
    $verif = $fmt(strtotime($diajukan) + mt_rand(3, 20) * 3600);
    $bayar = $fmt(strtotime($verif) + mt_rand(2, 20) * 3600);
    $uji = $fmt($tglTs + mt_rand(9, 14) * 3600);
    $putus = $fmt($tglTs + mt_rand(1, 3) * 86400 + mt_rand(9, 15) * 3600);
    $units = $s['units'];
    $apl = json_encode(array_fill_keys(array_map('strval', $units), 'K'));
    $bk = [];
    foreach ($units as $k => $uidUnit) {
        $bk[(string)$uidUnit] = $k === count($units) - 1 || ($k === 1 && mt_rand(0, 1)) ? 'BK' : 'K';
    }
    $f = ['tujuan' => sd_pick(['Sertifikasi', 'Sertifikasi', 'Sertifikasi', 'Sertifikasi ulang (RCC)', 'Syarat kerja/proyek']),
        'catatan_admin' => null, 'rekom_pra' => null, 'catatan_pra' => null, 'hasil' => null, 'rekomendasi' => null, 'catatan_asesor' => null,
        'asesor_id' => null, 'keputusan' => null, 'catatan_pleno' => null, 'pleno_oleh' => null,
        'diverifikasi_at' => null, 'dibayar_at' => null, 'diuji_at' => null, 'diputuskan_at' => null];
    $urut = ['diajukan' => 0, 'perbaikan' => 0, 'ditolak' => 0, 'dibatalkan' => 0, 'menunggu_bayar' => 1, 'pra_asesmen' => 2, 'tidak_lanjut' => 2,
        'siap_uji' => 3, 'menunggu_pleno' => 4, 'kompeten' => 5, 'belum_kompeten' => 5];
    $tahap = $urut[$akhir];
    if ($akhir === 'perbaikan') {
        $f['catatan_admin'] = sd_pick(['Scan ijazah buram. Unggah ulang dengan resolusi lebih tinggi.', 'KTP sudah habis masa berlaku. Unggah KTP terbaru.']);
    }
    if ($akhir === 'ditolak') {
        $f['catatan_admin'] = sd_pick(['Ijazah tidak memenuhi persyaratan pendidikan minimal skema.', 'Pengalaman kerja belum memenuhi syarat minimal 1 tahun.']);
        $f['diverifikasi_at'] = $verif;
    }
    if ($tahap >= 1) {
        $f['diverifikasi_at'] = $verif;
    }
    if ($tahap >= 2) {
        $f['dibayar_at'] = $bayar;
    }
    if ($tahap >= 3 || $akhir === 'tidak_lanjut') {
        $f['rekom_pra'] = $akhir === 'tidak_lanjut' ? 'tidak' : 'lanjut';
        $f['catatan_pra'] = $akhir === 'tidak_lanjut' ? 'Bukti pengalaman belum relevan dengan unit kompetensi. Disarankan ikut kelas persiapan.'
            : sd_pick(['Bukti portofolio cukup.', 'Dokumen lengkap, siap uji.', null]);
    }
    if ($tahap >= 4) {
        $f['asesor_id'] = $asesor;
        $f['diuji_at'] = $uji;
        $lulus = $akhir !== 'belum_kompeten' && ($akhir !== 'menunggu_pleno' || mt_rand(1, 10) > 2);
        $f['hasil'] = $lulus ? $apl : json_encode($bk);
        $f['rekomendasi'] = $lulus ? 'K' : 'BK';
        $f['catatan_asesor'] = $lulus ? sd_pick(['Kompeten pada semua unit.', 'Unjuk kerja baik, bukti portofolio lengkap.', 'Semua kriteria unjuk kerja terpenuhi.'])
            : 'Belum menunjukkan kompetensi pada unit terakhir. Disarankan uji ulang unit tersebut.';
    }
    if ($tahap >= 5) {
        $f['keputusan'] = $akhir === 'kompeten' ? 'K' : 'BK';
        $f['diputuskan_at'] = $putus;
        $f['catatan_pleno'] = $akhir === 'kompeten' ? 'Sesuai rekomendasi asesor.' : 'Uji ulang dapat diajukan setelah 1 bulan.';
        $lain = array_values(array_filter($asesorLsp, function ($a) use ($asesor) { return $a !== $asesor; }));
        $f['pleno_oleh'] = mt_rand(0, 1) && $lain ? $lain[0] : $ctx['admin'][$lsp];
    }
    $last = $f['diputuskan_at'] ?? $f['diuji_at'] ?? $f['dibayar_at'] ?? $f['diverifikasi_at'] ?? $diajukan;
    q('INSERT INTO permohonan (lsp_id, asesi_id, skema_id, jadwal_id, status, tujuan, apl02, catatan_admin, rekom_pra, catatan_pra, hasil, rekomendasi,
         catatan_asesor, asesor_id, keputusan, catatan_pleno, pleno_oleh, created_at, updated_at, diajukan_at, diverifikasi_at, dibayar_at, diuji_at, diputuskan_at, harga)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$lsp, $aid, $s['id'], $jid, $akhir, $f['tujuan'], $apl, $f['catatan_admin'], $f['rekom_pra'], $f['catatan_pra'], $f['hasil'], $f['rekomendasi'],
            $f['catatan_asesor'], $f['asesor_id'], $f['keputusan'], $f['catatan_pleno'], $f['pleno_oleh'], $diajukan, $last, $diajukan,
            $f['diverifikasi_at'], $f['dibayar_at'], $f['diuji_at'], $f['diputuskan_at'], (int)$s['harga']]);
    $pid = (int)db()->lastInsertId();
    $desc = 'Uji kompetensi ' . $s['nama'];
    if ($tahap >= 2) {
        sd_tagihan($lsp, $pid, null, $aid, $desc, (int)$s['harga'], 'lunas', $verif, $bayar);
    } elseif ($akhir === 'menunggu_bayar') {
        sd_tagihan($lsp, $pid, null, $aid, $desc, (int)$s['harga'], 'belum', $verif);
    } elseif ($akhir === 'dibatalkan' && mt_rand(0, 1)) {
        sd_tagihan($lsp, $pid, null, $aid, $desc, (int)$s['harga'], 'batal', $verif);
    }
    if ($akhir === 'kompeten') {
        sd_sertifikat($s, $pid, $aid, substr($putus, 0, 10), 'aktif', $putus);
    }
    // Notifikasi ke asesi untuk kejadian 30 hari terakhir (isi Log Notifikasi).
    if (strtotime($last) > time() - 30 * 86400) {
        $map = ['kompeten' => ['pleno.K', 'Selamat, Anda kompeten', 'sertifikat'], 'belum_kompeten' => ['pleno.BK', 'Hasil uji: belum kompeten', 'jadwal'],
            'menunggu_bayar' => ['permohonan.diterima', 'Berkas lengkap, silakan bayar', 'bayar'], 'perbaikan' => ['permohonan.perbaikan', 'Berkas perlu perbaikan', 'jadwal'],
            'pra_asesmen' => ['tagihan.lunas', 'Pembayaran diterima', 'bayar'], 'siap_uji' => ['pra.lanjut', 'Pra-asesmen selesai: siap uji', 'jadwal'],
            'diajukan' => ['permohonan.diajukan', 'Pendaftaran diterima', 'jadwal'], 'ditolak' => ['permohonan.ditolak', 'Pendaftaran ditolak', 'jadwal']];
        if (isset($map[$akhir])) {
            [$type, $title, $page] = $map[$akhir];
            $ctx['notif'][] = [$aid, $lsp, $type, $title, 'Skema ' . $s['nama'] . '.', $page, $last, mt_rand(0, 1) === 1];
        }
    }
}
