<?php
declare(strict_types=1);

/* Skema data proses sertifikasi (migrasi 6) dan data contoh untuk akun demo. */

function migrate_domain(): void
{
    $d = ddl();
    $pk = $d['pk'];
    $fk = $d['fk'];
    $e = $d['end'];
    foreach (['alamat VARCHAR(255) NULL', 'telepon VARCHAR(30) NULL', 'email VARCHAR(190) NULL', 'website VARCHAR(190) NULL',
        'deskripsi TEXT NULL', 'lisensi_sampai DATE NULL', 'honor_per_asesi INT NOT NULL DEFAULT 150000',
        "paket VARCHAR(20) NOT NULL DEFAULT 'Pro'", 'kuota_asesi INT NOT NULL DEFAULT 5000', 'kode VARCHAR(20) NULL'] as $c) {
        db()->exec("ALTER TABLE lsp ADD COLUMN $c");
    }
    foreach (['alamat VARCHAR(255) NULL', "jenis VARCHAR(30) NOT NULL DEFAULT 'Sewaktu'", 'kapasitas INT NOT NULL DEFAULT 20', 'verif_sampai DATE NULL'] as $c) {
        db()->exec("ALTER TABLE tuk ADD COLUMN $c");
    }
    db()->exec("ALTER TABLE listings ADD COLUMN skema_id $fk NULL");
    $stmts = [
        "CREATE TABLE skema (id $pk, lsp_id $fk NOT NULL, kode VARCHAR(40) NOT NULL, nama VARCHAR(190) NOT NULL, bidang VARCHAR(40) NOT NULL,
            kkni VARCHAR(20) NOT NULL, harga INT NOT NULL, deskripsi TEXT NULL, persyaratan TEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'aktif',
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (lsp_id) REFERENCES lsp(id))$e",
        'CREATE INDEX idx_skema_lsp ON skema (lsp_id)',
        "CREATE TABLE skema_units (id $pk, skema_id $fk NOT NULL, kode VARCHAR(40) NOT NULL, judul VARCHAR(255) NOT NULL, urut INT NOT NULL DEFAULT 0,
            FOREIGN KEY (skema_id) REFERENCES skema(id))$e",
        'CREATE INDEX idx_units_skema ON skema_units (skema_id)',
        "CREATE TABLE jadwal (id $pk, lsp_id $fk NOT NULL, skema_id $fk NOT NULL, tuk_id $fk NULL, tanggal DATE NOT NULL, jam VARCHAR(20) NOT NULL,
            metode VARCHAR(20) NOT NULL, kuota INT NOT NULL, asesor_id $fk NULL, status VARCHAR(20) NOT NULL DEFAULT 'dibuka', catatan VARCHAR(255) NULL,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (lsp_id) REFERENCES lsp(id), FOREIGN KEY (skema_id) REFERENCES skema(id),
            FOREIGN KEY (tuk_id) REFERENCES tuk(id), FOREIGN KEY (asesor_id) REFERENCES users(id))$e",
        'CREATE INDEX idx_jadwal_lsp ON jadwal (lsp_id, tanggal)',
        'CREATE INDEX idx_jadwal_asesor ON jadwal (asesor_id)',
        "CREATE TABLE permohonan (id $pk, lsp_id $fk NOT NULL, asesi_id $fk NOT NULL, skema_id $fk NOT NULL, jadwal_id $fk NOT NULL,
            status VARCHAR(30) NOT NULL, tujuan VARCHAR(80) NULL, apl02 TEXT NULL, catatan_admin VARCHAR(500) NULL, rekom_pra VARCHAR(10) NULL,
            catatan_pra VARCHAR(500) NULL, hasil TEXT NULL, rekomendasi VARCHAR(5) NULL, catatan_asesor VARCHAR(1000) NULL, asesor_id $fk NULL,
            keputusan VARCHAR(5) NULL, catatan_pleno VARCHAR(500) NULL, pleno_oleh $fk NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            diajukan_at DATETIME NULL, diverifikasi_at DATETIME NULL, dibayar_at DATETIME NULL, diuji_at DATETIME NULL, diputuskan_at DATETIME NULL,
            FOREIGN KEY (lsp_id) REFERENCES lsp(id), FOREIGN KEY (asesi_id) REFERENCES users(id), FOREIGN KEY (skema_id) REFERENCES skema(id),
            FOREIGN KEY (jadwal_id) REFERENCES jadwal(id))$e",
        'CREATE INDEX idx_permohonan_lsp ON permohonan (lsp_id, status)',
        'CREATE INDEX idx_permohonan_asesi ON permohonan (asesi_id)',
        'CREATE INDEX idx_permohonan_jadwal ON permohonan (jadwal_id)',
        "CREATE TABLE dokumen (id $pk, user_id $fk NOT NULL, jenis VARCHAR(30) NOT NULL, nama_file VARCHAR(190) NOT NULL, path VARCHAR(255) NOT NULL,
            mime VARCHAR(60) NOT NULL, ukuran INT NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id))$e",
        'CREATE UNIQUE INDEX idx_dokumen_user_jenis ON dokumen (user_id, jenis)',
        "CREATE TABLE kelas_peserta (id $pk, listing_id $fk NOT NULL, user_id $fk NOT NULL, status VARCHAR(20) NOT NULL, progres INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL, FOREIGN KEY (listing_id) REFERENCES listings(id), FOREIGN KEY (user_id) REFERENCES users(id))$e",
        'CREATE UNIQUE INDEX idx_kelas_peserta ON kelas_peserta (listing_id, user_id)',
        "CREATE TABLE tagihan (id $pk, lsp_id $fk NOT NULL, permohonan_id $fk NULL, kelas_peserta_id $fk NULL, user_id $fk NOT NULL, nomor VARCHAR(40) NOT NULL UNIQUE,
            deskripsi VARCHAR(255) NOT NULL, jumlah INT NOT NULL, status VARCHAR(20) NOT NULL, metode VARCHAR(40) NULL, dibayar_at DATETIME NULL,
            jatuh_tempo DATE NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY (lsp_id) REFERENCES lsp(id), FOREIGN KEY (user_id) REFERENCES users(id))$e",
        'CREATE INDEX idx_tagihan_lsp ON tagihan (lsp_id, status)',
        'CREATE INDEX idx_tagihan_user ON tagihan (user_id)',
        "CREATE TABLE sertifikat (id $pk, lsp_id $fk NOT NULL, permohonan_id $fk NULL, user_id $fk NOT NULL, skema_id $fk NOT NULL, nomor VARCHAR(60) NOT NULL UNIQUE,
            kode VARCHAR(20) NOT NULL UNIQUE, terbit DATE NOT NULL, berlaku DATE NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'aktif', created_at DATETIME NOT NULL,
            FOREIGN KEY (lsp_id) REFERENCES lsp(id), FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (skema_id) REFERENCES skema(id))$e",
        'CREATE INDEX idx_sertifikat_lsp ON sertifikat (lsp_id)',
        'CREATE INDEX idx_sertifikat_user ON sertifikat (user_id)',
        "CREATE TABLE crm_leads (id $pk, lsp_id $fk NOT NULL, nama VARCHAR(150) NOT NULL, organisasi VARCHAR(150) NULL, kontak VARCHAR(150) NULL,
            sumber VARCHAR(40) NOT NULL, tahap VARCHAR(20) NOT NULL, nilai INT NOT NULL DEFAULT 0, catatan TEXT NULL, followup DATE NULL,
            created_by $fk NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (lsp_id) REFERENCES lsp(id))$e",
        'CREATE INDEX idx_crm_lsp ON crm_leads (lsp_id)',
        "CREATE TABLE mutu (id $pk, lsp_id $fk NOT NULL, jenis VARCHAR(40) NOT NULL, judul VARCHAR(190) NOT NULL, deskripsi TEXT NULL,
            status VARCHAR(20) NOT NULL, pic VARCHAR(120) NULL, tenggat DATE NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            FOREIGN KEY (lsp_id) REFERENCES lsp(id))$e",
        'CREATE INDEX idx_mutu_lsp ON mutu (lsp_id)',
        "CREATE TABLE sarpras (id $pk, lsp_id $fk NOT NULL, tuk_id $fk NOT NULL, nama VARCHAR(150) NOT NULL, jumlah INT NOT NULL, kondisi VARCHAR(20) NOT NULL,
            catatan VARCHAR(255) NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (tuk_id) REFERENCES tuk(id))$e",
        'CREATE INDEX idx_sarpras_tuk ON sarpras (tuk_id)',
        "CREATE TABLE chat_pesan (id $pk, lsp_id $fk NOT NULL, jadwal_id $fk NOT NULL, user_id $fk NOT NULL, isi TEXT NOT NULL, created_at DATETIME NOT NULL,
            FOREIGN KEY (jadwal_id) REFERENCES jadwal(id), FOREIGN KEY (user_id) REFERENCES users(id))$e",
        'CREATE INDEX idx_chat_jadwal ON chat_pesan (jadwal_id, id)',
        "CREATE TABLE skkni (id $pk, kode VARCHAR(40) NOT NULL UNIQUE, judul VARCHAR(255) NOT NULL, sektor VARCHAR(60) NOT NULL)$e",
        "CREATE TABLE tiket (id $pk, lsp_id $fk NULL, user_id $fk NOT NULL, judul VARCHAR(190) NOT NULL, isi TEXT NOT NULL, prioritas VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id))$e",
        "CREATE TABLE tiket_balasan (id $pk, tiket_id $fk NOT NULL, user_id $fk NOT NULL, isi TEXT NOT NULL, created_at DATETIME NOT NULL,
            FOREIGN KEY (tiket_id) REFERENCES tiket(id))$e",
    ];
    foreach ($stmts as $s) {
        db()->exec($s);
    }
    seed_domain();
}

function seed_skkni(): void
{
    $units = [
        ['J.620100.004.02', 'Menggunakan struktur data', 'TIK'], ['J.620100.005.02', 'Mengimplementasikan user interface', 'TIK'],
        ['J.620100.011.01', 'Melakukan instalasi software tools pemrograman', 'TIK'], ['J.620100.012.01', 'Melakukan pengaturan software tools pemrograman', 'TIK'],
        ['J.620100.016.01', 'Menulis kode dengan prinsip sesuai guidelines dan best practices', 'TIK'], ['J.620100.017.02', 'Mengimplementasikan pemrograman terstruktur', 'TIK'],
        ['J.620100.019.02', 'Menggunakan library atau komponen pre-existing', 'TIK'], ['J.620100.023.02', 'Membuat dokumen kode program', 'TIK'],
        ['J.620100.025.02', 'Melakukan debugging', 'TIK'],
        ['J.62DMI00.001.1', 'Menentukan objektif bisnis dan kebutuhan data', 'TIK'], ['J.62DMI00.004.1', 'Mengumpulkan data', 'TIK'],
        ['J.62DMI00.005.1', 'Menelaah data', 'TIK'], ['J.62DMI00.008.1', 'Membersihkan data', 'TIK'], ['J.62DMI00.013.1', 'Membangun model', 'TIK'],
        ['J.62DMI00.014.1', 'Mengevaluasi hasil pemodelan', 'TIK'],
        ['M.702090.001.01', 'Menyusun strategi pemasaran digital', 'TIK'], ['M.702090.004.01', 'Mengelola media sosial', 'TIK'],
        ['M.702090.007.01', 'Mengelola iklan digital berbayar', 'TIK'], ['M.702090.010.01', 'Menganalisis kinerja kampanye digital', 'TIK'],
        ['I.56BRS00.001.1', 'Menyiapkan peralatan kopi', 'Pariwisata'], ['I.56BRS00.003.1', 'Menyajikan espresso', 'Pariwisata'],
        ['I.56BRS00.004.1', 'Membuat minuman berbasis espresso', 'Pariwisata'], ['I.56BRS00.007.1', 'Menerapkan higiene dan sanitasi', 'Pariwisata'],
        ['I.55HDT00.001.1', 'Menyiapkan kamar untuk tamu', 'Pariwisata'], ['I.55HDT00.006.1', 'Memimpin tim housekeeping', 'Pariwisata'],
        ['I.55HDT00.009.1', 'Mengendalikan persediaan linen', 'Pariwisata'],
        ['F.43TIL01.001.1', 'Menerapkan K3 instalasi listrik', 'Konstruksi'], ['F.43TIL01.004.1', 'Memasang instalasi penerangan', 'Konstruksi'],
        ['F.43TIL01.007.1', 'Memasang panel hubung bagi', 'Konstruksi'], ['F.43TIL01.010.1', 'Menguji instalasi listrik', 'Konstruksi'],
        ['N.821100.001.02', 'Mengelola arsip dinamis', 'Bisnis'], ['N.821100.004.02', 'Menyusun agenda kegiatan pimpinan', 'Bisnis'],
        ['N.821100.009.02', 'Menangani surat masuk dan keluar', 'Bisnis'], ['N.821100.012.02', 'Mengoperasikan aplikasi perkantoran', 'Bisnis'],
    ];
    foreach ($units as [$k, $j, $s]) {
        q('INSERT INTO skkni (kode, judul, sektor) VALUES (?, ?, ?)', [$k, $j, $s]);
    }
}

function seed_uid(string $email): ?int
{
    $id = q('SELECT id FROM users WHERE email = ?', [$email])->fetchColumn();
    return $id ? (int)$id : null;
}

function seed_user(string $email, string $nama, array $memberships): int
{
    $id = seed_uid($email);
    if (!$id) {
        // Akun contoh tanpa password yang bisa dipakai masuk.
        q('INSERT INTO users (email, nama, password_hash, status, must_change_password, created_at, updated_at, email_verified_at) VALUES (?, ?, ?, ?, 0, ?, ?, ?)',
            [$email, $nama, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), 'aktif', now(), now(), now()]);
        $id = (int)db()->lastInsertId();
    }
    foreach ($memberships as [$lsp, $role]) {
        if (!q('SELECT 1 FROM memberships WHERE user_id = ? AND lsp_id = ? AND role = ?', [$id, $lsp, $role])->fetch()) {
            q('INSERT INTO memberships (user_id, lsp_id, role, status, created_at) VALUES (?, ?, ?, ?, ?)', [$id, $lsp, $role, 'aktif', now()]);
        }
    }
    return $id;
}

function rel_date(int $days): string
{
    return date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days'));
}

/** Data contoh proses sertifikasi. Hanya dibuat bila akun demo ada (tidak pernah di instalasi bersih). */
function seed_domain(): void
{
    seed_skkni();
    $adminTdn = seed_uid('admin.tdn@demo.portallsp.id');
    $asesor = seed_uid('asesor@demo.portallsp.id');
    $asesi = seed_uid('asesi@demo.portallsp.id');
    if (!$adminTdn || !$asesor || !$asesi) {
        return;
    }
    $t = now();
    $lspInfo = [
        1 => ['TDN', 'Jl. HR Rasuna Said Kav. 5, Kuningan, Jakarta Selatan', '021-5550101', 'info@lsp-tdn.example', 'Sertifikasi kompetensi bidang teknologi informasi dan ekonomi digital.', rel_date(900)],
        2 => ['PBI', 'Jl. Danau Tamblingan 88, Sanur, Denpasar', '0361-288800', 'halo@lsp-pbi.example', 'Sertifikasi kompetensi pariwisata, perhotelan, dan kuliner.', rel_date(500)],
        3 => ['KMD', 'Jl. Ahmad Yani 120, Surabaya', '031-8280012', 'admin@lsp-kmd.example', 'Sertifikasi tenaga kerja konstruksi dan kelistrikan.', rel_date(300)],
        4 => ['LMP', 'Jl. Sudirman 45, Jakarta Pusat', '021-5703344', 'cs@lsp-lmp.example', 'Sertifikasi manajemen, administrasi, dan bisnis.', rel_date(620)],
    ];
    foreach ($lspInfo as $id => [$kode, $alamat, $tel, $mail, $desk, $lis]) {
        q('UPDATE lsp SET kode = ?, alamat = ?, telepon = ?, email = ?, deskripsi = ?, lisensi_sampai = ? WHERE id = ?', [$kode, $alamat, $tel, $mail, $desk, $lis, $id]);
    }
    q("UPDATE tuk SET alamat = ?, jenis = 'Sewaktu', kapasitas = 24, verif_sampai = ? WHERE id = 1", ['Gedung Kuningan Center Lt. 3, Jakarta Selatan', rel_date(150)]);
    q("UPDATE tuk SET alamat = ?, jenis = 'Tempat Kerja', kapasitas = 16, verif_sampai = ? WHERE id = 2", ['Hotel Sanur Beach, Denpasar', rel_date(40)]);
    $tukBaru = [[1, 'Daring (SJJ) TDN', 'Sewaktu', 'Zoom / Google Meet', 30], [3, 'TUK SMK Negeri 5 Surabaya', 'Sewaktu', 'Jl. Mayjen Prof. Moestopo, Surabaya', 20],
        [4, 'TUK Graha Manajemen', 'Sewaktu', 'Jl. Sudirman 45, Jakarta Pusat', 25]];
    $tukId = [1 => 1, 2 => 2];
    foreach ($tukBaru as [$lsp, $nama, $jenis, $alamat, $kap]) {
        q('INSERT INTO tuk (lsp_id, nama, status, alamat, jenis, kapasitas, verif_sampai) VALUES (?, ?, ?, ?, ?, ?, ?)', [$lsp, $nama, 'aktif', $alamat, $jenis, $kap, rel_date(200)]);
        $tukId[$nama] = (int)db()->lastInsertId();
    }

    $skemaDef = [
        ['jwd', 1, 'JWD', 'Junior Web Developer', 'TIK', 'Level 3', 750000, 'J.620100', 1, 'Tatap muka'],
        ['ad', 1, 'ADJ', 'Analis Data Junior', 'TIK', 'Level 4', 950000, 'J.62DMI00', $tukId['Daring (SJJ) TDN'], 'SJJ'],
        ['dm', 1, 'DM', 'Digital Marketing', 'TIK', 'Level 4', 850000, 'M.702090', $tukId['Daring (SJJ) TDN'], 'SJJ'],
        ['brs', 2, 'BRS', 'Barista', 'Pariwisata', 'Level 2', 600000, 'I.56BRS00', 2, 'Tatap muka'],
        ['hk', 2, 'HKS', 'Housekeeping Supervisor', 'Pariwisata', 'Level 4', 900000, 'I.55HDT00', 2, 'Tatap muka'],
        ['til', 3, 'TIL', 'Teknisi Instalasi Listrik Bangunan', 'Konstruksi', 'Level 3', 1100000, 'F.43TIL01', $tukId['TUK SMK Negeri 5 Surabaya'], 'Tatap muka'],
        ['apk', 4, 'APK', 'Pengelola Administrasi Perkantoran', 'Bisnis', 'Level 3', 700000, 'N.821100', $tukId['TUK Graha Manajemen'], 'Tatap muka'],
    ];
    $sk = [];
    $jd = [];
    $asesorLsp = [1 => $asesor, 3 => $asesor, 4 => $asesor];
    foreach ($skemaDef as [$key, $lsp, $kode, $nama, $bidang, $kkni, $harga, $prefix, $tuk, $metode]) {
        q('INSERT INTO skema (lsp_id, kode, nama, bidang, kkni, harga, deskripsi, persyaratan, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$lsp, $kode, $nama, $bidang, $kkni, $harga, "Skema sertifikasi $nama sesuai SKKNI. Uji praktik, observasi, dan wawancara oleh asesor kompetensi.",
                "Minimal lulusan SMK/D3 bidang terkait, atau pengalaman kerja minimal 1 tahun.\nKTP dan pas foto berlatar merah.\nIjazah terakhir.", 'aktif', $t, $t]);
        $sid = (int)db()->lastInsertId();
        $sk[$key] = $sid;
        $units = q('SELECT kode, judul FROM skkni WHERE kode LIKE ? ORDER BY kode', [$prefix . '%'])->fetchAll();
        foreach ($units as $i => $u) {
            q('INSERT INTO skema_units (skema_id, kode, judul, urut) VALUES (?, ?, ?, ?)', [$sid, $u['kode'], $u['judul'], $i + 1]);
        }
        q("INSERT INTO listings (lsp_id, tipe, judul, bidang, kota, format, harga, deskripsi, status, created_by, submitted_at, reviewed_at, created_at, updated_at, skema_id)
           VALUES (?, 'skema', ?, ?, ?, ?, ?, ?, 'tayang', ?, ?, ?, ?, ?, ?)",
            [$lsp, $nama, $bidang, $lsp === 2 ? 'Denpasar' : ($lsp === 3 ? 'Surabaya' : 'Jakarta'), $metode, $harga,
                "Uji kompetensi $nama. Jadwal rutin setiap bulan, hasil pleno maksimal 7 hari kerja.", $adminTdn, $t, $t, $t, $t, $sid]);
        foreach ([-20 => 'selesai', -2 => 'dibuka', 7 => 'dibuka', 21 => 'dibuka'] as $off => $st) {
            q('INSERT INTO jadwal (lsp_id, skema_id, tuk_id, tanggal, jam, metode, kuota, asesor_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$lsp, $sid, $tuk, rel_date($off), '08.00–16.00', $metode, $off === 7 ? 12 : 20, $asesorLsp[$lsp] ?? null, $st, $t, $t]);
            $jd[$key][$off] = (int)db()->lastInsertId();
        }
    }

    // Asesi & asesor contoh tambahan (tanpa password, tidak bisa dipakai masuk).
    $asesor2 = seed_user('lina.marlina@contoh.portallsp.id', 'Lina Marlina, S.T.', [[1, 'asesor']]);
    $peserta = [];
    foreach ([['hendra.w', 'Hendra Wijaya'], ['maya.a', 'Maya Anggraini'], ['dimas.p', 'Dimas Prasetyo'], ['sari.n', 'Sari Nurhayati'],
        ['agung.s', 'Agung Setiawan'], ['putri.l', 'Putri Larasati']] as [$u, $n]) {
        $peserta[$u] = seed_user("$u@contoh.portallsp.id", $n, [[1, 'asesi']]);
    }
    seed_user('asesi@demo.portallsp.id', 'Rina Kartika Sari', [[1, 'asesi'], [2, 'asesi']]);

    // Berkas contoh (gambar kecil) agar tautan dokumen bisa dibuka.
    $dir = APP_ROOT . '/storage/uploads/contoh';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    file_put_contents($dir . '/contoh.png', $png);
    foreach (array_merge([$asesi], array_values($peserta)) as $uid) {
        foreach (['ktp' => 'KTP', 'ijazah' => 'Ijazah', 'foto' => 'Pas foto'] as $j => $label) {
            q('INSERT INTO dokumen (user_id, jenis, nama_file, path, mime, ukuran, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$uid, $j, "$label (contoh).png", 'uploads/contoh/contoh.png', 'image/png', strlen($png), $t]);
        }
    }

    $apl = function (int $skemaId, string $val = 'K'): string {
        $o = [];
        foreach (q('SELECT id FROM skema_units WHERE skema_id = ?', [$skemaId])->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $o[(string)$id] = $val;
        }
        return json_encode($o);
    };
    $mk = function (array $p) use ($t): int {
        $p += ['tujuan' => 'Sertifikasi', 'catatan_admin' => null, 'rekom_pra' => null, 'catatan_pra' => null, 'hasil' => null, 'rekomendasi' => null,
            'catatan_asesor' => null, 'asesor_id' => null, 'keputusan' => null, 'catatan_pleno' => null, 'pleno_oleh' => null,
            'diverifikasi_at' => null, 'dibayar_at' => null, 'diuji_at' => null, 'diputuskan_at' => null];
        q('INSERT INTO permohonan (lsp_id, asesi_id, skema_id, jadwal_id, status, tujuan, apl02, catatan_admin, rekom_pra, catatan_pra, hasil, rekomendasi,
             catatan_asesor, asesor_id, keputusan, catatan_pleno, pleno_oleh, created_at, updated_at, diajukan_at, diverifikasi_at, dibayar_at, diuji_at, diputuskan_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$p['lsp_id'], $p['asesi_id'], $p['skema_id'], $p['jadwal_id'], $p['status'], $p['tujuan'], $p['apl02'], $p['catatan_admin'], $p['rekom_pra'],
                $p['catatan_pra'], $p['hasil'], $p['rekomendasi'], $p['catatan_asesor'], $p['asesor_id'], $p['keputusan'], $p['catatan_pleno'], $p['pleno_oleh'],
                $t, $t, $t, $p['diverifikasi_at'], $p['dibayar_at'], $p['diuji_at'], $p['diputuskan_at']]);
        return (int)db()->lastInsertId();
    };
    $bill = function (int $lsp, ?int $pid, int $uid, string $desc, int $jumlah, string $status) use ($t): void {
        q('INSERT INTO tagihan (lsp_id, permohonan_id, user_id, nomor, deskripsi, jumlah, status, metode, dibayar_at, jatuh_tempo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$lsp, $pid, $uid, 'TMP-' . bin2hex(random_bytes(6)), $desc, $jumlah, $status, $status === 'lunas' ? 'QRIS' : null, $status === 'lunas' ? $t : null, rel_date(7), $t]);
        $id = (int)db()->lastInsertId();
        q('UPDATE tagihan SET nomor = ? WHERE id = ?', [sprintf('INV/%d/%s/%05d', $lsp, date('Ym'), $id), $id]);
    };
    $sert = function (int $lsp, int $pid, int $uid, int $skemaId, string $terbit, string $kode) use ($t): void {
        $s = q('SELECT s.kode AS sk, l.kode AS lk FROM skema s JOIN lsp l ON l.id = s.lsp_id WHERE s.id = ?', [$skemaId])->fetch();
        q('INSERT INTO sertifikat (lsp_id, permohonan_id, user_id, skema_id, nomor, kode, terbit, berlaku, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$lsp, $pid, $uid, $skemaId, '', $kode, $terbit, date('Y-m-d', strtotime($terbit . ' +3 years')), 'aktif', $t]);
        $id = (int)db()->lastInsertId();
        q('UPDATE sertifikat SET nomor = ? WHERE id = ?', [sprintf('LSP-%s/%s/%s/%05d', $s['lk'], $s['sk'], substr($terbit, 0, 4), $id), $id]);
    };

    // Rina (asesi demo): satu di tiap tahap utama.
    $p = $mk(['lsp_id' => 1, 'asesi_id' => $asesi, 'skema_id' => $sk['jwd'], 'jadwal_id' => $jd['jwd'][7], 'status' => 'pra_asesmen', 'apl02' => $apl($sk['jwd']),
        'diverifikasi_at' => $t, 'dibayar_at' => $t]);
    $bill(1, $p, $asesi, 'Uji kompetensi Junior Web Developer', 750000, 'lunas');
    $p = $mk(['lsp_id' => 1, 'asesi_id' => $asesi, 'skema_id' => $sk['dm'], 'jadwal_id' => $jd['dm'][21], 'status' => 'menunggu_bayar', 'apl02' => $apl($sk['dm']), 'diverifikasi_at' => $t]);
    $bill(1, $p, $asesi, 'Uji kompetensi Digital Marketing', 850000, 'belum');
    $mk(['lsp_id' => 2, 'asesi_id' => $asesi, 'skema_id' => $sk['brs'], 'jadwal_id' => $jd['brs'][21], 'status' => 'perbaikan', 'apl02' => $apl($sk['brs']),
        'catatan_admin' => 'Pas foto kurang jelas. Unggah ulang pas foto berlatar merah.']);
    $p = $mk(['lsp_id' => 1, 'asesi_id' => $asesi, 'skema_id' => $sk['ad'], 'jadwal_id' => $jd['ad'][-20], 'status' => 'kompeten', 'apl02' => $apl($sk['ad']),
        'hasil' => $apl($sk['ad']), 'rekomendasi' => 'K', 'asesor_id' => $asesor2, 'keputusan' => 'K', 'pleno_oleh' => $adminTdn, 'rekom_pra' => 'lanjut',
        'diverifikasi_at' => $t, 'dibayar_at' => $t, 'diuji_at' => $t, 'diputuskan_at' => $t, 'catatan_asesor' => 'Semua unit terpenuhi.']);
    $bill(1, $p, $asesi, 'Uji kompetensi Analis Data Junior', 950000, 'lunas');
    $sert(1, $p, $asesi, $sk['ad'], rel_date(-15), 'TDN7K3P9QX');

    // Peserta lain di LSP TDN untuk antrean admin & asesor.
    $mk(['lsp_id' => 1, 'asesi_id' => $peserta['hendra.w'], 'skema_id' => $sk['jwd'], 'jadwal_id' => $jd['jwd'][21], 'status' => 'diajukan', 'apl02' => $apl($sk['jwd'])]);
    $mk(['lsp_id' => 1, 'asesi_id' => $peserta['maya.a'], 'skema_id' => $sk['dm'], 'jadwal_id' => $jd['dm'][7], 'status' => 'diajukan', 'apl02' => $apl($sk['dm'])]);
    $p = $mk(['lsp_id' => 1, 'asesi_id' => $peserta['dimas.p'], 'skema_id' => $sk['jwd'], 'jadwal_id' => $jd['jwd'][-2], 'status' => 'siap_uji', 'apl02' => $apl($sk['jwd']),
        'rekom_pra' => 'lanjut', 'catatan_pra' => 'Bukti portofolio cukup.', 'diverifikasi_at' => $t, 'dibayar_at' => $t]);
    $bill(1, $p, $peserta['dimas.p'], 'Uji kompetensi Junior Web Developer', 750000, 'lunas');
    // Diuji oleh asesor lain → Budi boleh menjadi anggota pleno.
    $p = $mk(['lsp_id' => 1, 'asesi_id' => $peserta['sari.n'], 'skema_id' => $sk['jwd'], 'jadwal_id' => $jd['jwd'][-2], 'status' => 'menunggu_pleno', 'apl02' => $apl($sk['jwd']),
        'hasil' => $apl($sk['jwd']), 'rekomendasi' => 'K', 'asesor_id' => $asesor2, 'rekom_pra' => 'lanjut', 'catatan_asesor' => 'Kompeten pada semua unit.',
        'diverifikasi_at' => $t, 'dibayar_at' => $t, 'diuji_at' => $t]);
    $bill(1, $p, $peserta['sari.n'], 'Uji kompetensi Junior Web Developer', 750000, 'lunas');
    // Diuji oleh Budi → Budi tidak boleh memutuskan pleno-nya sendiri.
    $p = $mk(['lsp_id' => 1, 'asesi_id' => $peserta['agung.s'], 'skema_id' => $sk['jwd'], 'jadwal_id' => $jd['jwd'][-20], 'status' => 'menunggu_pleno', 'apl02' => $apl($sk['jwd']),
        'hasil' => $apl($sk['jwd'], 'K'), 'rekomendasi' => 'K', 'asesor_id' => $asesor, 'rekom_pra' => 'lanjut', 'catatan_asesor' => 'Kompeten.',
        'diverifikasi_at' => $t, 'dibayar_at' => $t, 'diuji_at' => $t]);
    $bill(1, $p, $peserta['agung.s'], 'Uji kompetensi Junior Web Developer', 750000, 'lunas');
    foreach ([['putri.l', -400, 'TDNPL4X2M8'], ['hendra.w', -1010, 'TDNHW9R5T1']] as [$u, $ago, $kode]) {
        $p = $mk(['lsp_id' => 1, 'asesi_id' => $peserta[$u], 'skema_id' => $sk['jwd'], 'jadwal_id' => $jd['jwd'][-20], 'status' => 'kompeten', 'apl02' => $apl($sk['jwd']),
            'hasil' => $apl($sk['jwd']), 'rekomendasi' => 'K', 'asesor_id' => $asesor, 'keputusan' => 'K', 'pleno_oleh' => $asesor2, 'rekom_pra' => 'lanjut',
            'diverifikasi_at' => $t, 'dibayar_at' => $t, 'diuji_at' => $t, 'diputuskan_at' => $t]);
        $bill(1, $p, $peserta[$u], 'Uji kompetensi Junior Web Developer', 750000, 'lunas');
        $sert(1, $p, $peserta[$u], $sk['jwd'], rel_date($ago), $kode);
    }

    // Kelas yang diikuti asesi demo.
    foreach ([[1, 'Kelas Persiapan Uji Junior Web Developer', 'TIK', 'Online', 350000, 'Online · 12 modul · 8 jam. Instruktur: Andi Saputra, S.Kom.'],
        [2, 'Teknik Latte Art untuk Barista', 'Pariwisata', 'Tatap muka', 500000, 'Tatap muka 2 hari di Denpasar. Instruktur: Kadek Ari.'],
        [3, 'Dasar K3 Kelistrikan', 'Konstruksi', 'Webinar', 0, 'Webinar 2 sesi, gratis. Instruktur: Ir. Bambang S.']] as $i => [$lsp, $judul, $bid, $fmt, $hrg, $desk]) {
        q("INSERT INTO listings (lsp_id, tipe, judul, bidang, kota, format, harga, deskripsi, status, created_by, submitted_at, reviewed_at, created_at, updated_at)
           VALUES (?, 'pelatihan', ?, ?, ?, ?, ?, ?, 'tayang', ?, ?, ?, ?, ?)",
            [$lsp, $judul, $bid, $lsp === 2 ? 'Denpasar' : ($lsp === 3 ? 'Surabaya' : 'Jakarta'), $fmt, $hrg, $desk . ' Tidak wajib untuk mendaftar uji.', $adminTdn, $t, $t, $t, $t]);
        if ($i === 0) {
            q('INSERT INTO kelas_peserta (listing_id, user_id, status, progres, created_at) VALUES (?, ?, ?, ?, ?)', [(int)db()->lastInsertId(), $asesi, 'aktif', 40, $t]);
        }
    }

    foreach ([['PT Data Prima', 'Bu Ratna (HRD)', 'ratna@dataprima.example', 'Referral', 'proposal', 37500000, 25, 'Kirim proposal 25 karyawan skema JWD.'],
        ['SMK Negeri 12 Jakarta', 'Pak Joko (Waka Hubin)', '0812-1111-2222', 'Pameran', 'dihubungi', 60000000, 80, 'Uji kompetensi siswa kelas XII.'],
        ['Universitas Nusantara', 'Career Center', 'cdc@unus.example', 'Website', 'baru', 0, 0, null],
        ['Bank Sejahtera', 'Divisi L&D', 'ld@banksejahtera.example', 'LinkedIn', 'menang', 42500000, 50, 'Kontrak ditandatangani.'],
        ['Startup Kopi Kita', 'Founder', '0813-5555-0000', 'Instagram', 'kalah', 0, 0, 'Belum ada anggaran tahun ini.']] as [$org, $nm, $kt, $src, $th, $nilai, $n, $cat]) {
        q('INSERT INTO crm_leads (lsp_id, nama, organisasi, kontak, sumber, tahap, nilai, catatan, followup, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [1, $nm, $org, $kt, $src, $th, $nilai, $cat, in_array($th, ['baru', 'dihubungi', 'proposal'], true) ? rel_date(3) : null, $adminTdn, $t, $t]);
    }
    foreach ([['Audit internal', 'Audit internal semester 2', 'proses', 'Manajer Mutu', 20], ['Ketidaksesuaian', 'Rekaman asesmen SJJ belum diarsipkan lengkap', 'terbuka', 'Admin LSP', 7],
        ['Tindakan perbaikan (CAPA)', 'Prosedur arsip rekaman SJJ', 'terbuka', 'Admin LSP', 14], ['Kaji ulang manajemen', 'Kaji ulang manajemen tahunan', 'selesai', 'Direktur', -10],
        ['Analisis ketidakberpihakan', 'Identifikasi risiko ketidakberpihakan pelatihan', 'proses', 'Manajer Mutu', 30]] as [$j, $judul, $st, $pic, $due]) {
        q('INSERT INTO mutu (lsp_id, jenis, judul, deskripsi, status, pic, tenggat, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [1, $j, $judul, null, $st, $pic, rel_date($due), $t, $t]);
    }
    foreach ([['Komputer peserta (i5, 16 GB)', 24, 'baik'], ['Proyektor', 2, 'baik'], ['Jaringan internet 100 Mbps', 1, 'baik'], ['UPS', 4, 'perlu perbaikan'],
        ['Kamera pengawas ruang uji', 3, 'baik']] as [$n, $j, $k]) {
        q('INSERT INTO sarpras (lsp_id, tuk_id, nama, jumlah, kondisi, catatan, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [1, 1, $n, $j, $k, null, $t]);
    }
    foreach ([['Mesin espresso 2 group', 3, 'baik'], ['Grinder kopi', 3, 'baik'], ['Troli housekeeping', 4, 'rusak']] as [$n, $j, $k]) {
        q('INSERT INTO sarpras (lsp_id, tuk_id, nama, jumlah, kondisi, catatan, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [2, 2, $n, $j, $k, null, $t]);
    }
    $tukAdmin = seed_uid('tuk.kuningan@demo.portallsp.id');
    foreach ([[$tukAdmin, 'Ruang uji lantai 3 sudah disiapkan. Peserta hadir pukul 07.30 untuk registrasi.'],
        [$asesor, 'Peserta mohon membawa laptop cadangan dan KTP asli.'], [$asesi, 'Baik, terima kasih informasinya.']] as [$u, $isi]) {
        if ($u) {
            q('INSERT INTO chat_pesan (lsp_id, jadwal_id, user_id, isi, created_at) VALUES (?, ?, ?, ?, ?)', [1, $jd['jwd'][7], $u, $isi, $t]);
        }
    }
    q('INSERT INTO tiket (lsp_id, user_id, judul, isi, prioritas, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [1, $adminTdn, 'Template sertifikat perlu logo baru', 'Mohon bantuan mengganti logo pada template sertifikat digital.', 'normal', 'terbuka', $t, $t]);
}
