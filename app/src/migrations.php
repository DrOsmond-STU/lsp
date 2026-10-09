<?php
declare(strict_types=1);

/** Tipe kolom yang berbeda antara MySQL dan SQLite. */
function ddl(): array
{
    if (driver() === 'mysql') {
        return [
            'pk' => 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'fk' => 'INT UNSIGNED',
            'end' => ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
    }
    return ['pk' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'fk' => 'INTEGER', 'end' => ''];
}

function migrations(): array
{
    return [
        1 => function (): void {
            $d = ddl();
            $pk = $d['pk'];
            $fk = $d['fk'];
            $e = $d['end'];
            $stmts = [
                "CREATE TABLE lsp (id $pk, nama VARCHAR(190) NOT NULL, jenis VARCHAR(5) NOT NULL, kota VARCHAR(100) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'aktif', created_at DATETIME NOT NULL)$e",
                "CREATE TABLE tuk (id $pk, lsp_id $fk NOT NULL, nama VARCHAR(190) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'aktif',
                    FOREIGN KEY (lsp_id) REFERENCES lsp(id))$e",
                "CREATE TABLE users (id $pk, email VARCHAR(190) NOT NULL UNIQUE, nama VARCHAR(190) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'aktif',
                    must_change_password SMALLINT NOT NULL DEFAULT 0, last_login_at DATETIME NULL,
                    created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)$e",
                "CREATE TABLE roles (code VARCHAR(40) NOT NULL PRIMARY KEY, nama VARCHAR(100) NOT NULL, scope VARCHAR(20) NOT NULL,
                    multi_lsp SMALLINT NOT NULL DEFAULT 0)$e",
                "CREATE TABLE permissions (code VARCHAR(60) NOT NULL PRIMARY KEY, deskripsi VARCHAR(190) NOT NULL)$e",
                "CREATE TABLE role_permissions (role_code VARCHAR(40) NOT NULL, perm_code VARCHAR(60) NOT NULL,
                    PRIMARY KEY (role_code, perm_code), FOREIGN KEY (role_code) REFERENCES roles(code),
                    FOREIGN KEY (perm_code) REFERENCES permissions(code))$e",
                "CREATE TABLE memberships (id $pk, user_id $fk NOT NULL, lsp_id $fk NULL, tuk_id $fk NULL, role VARCHAR(40) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'aktif', created_by $fk NULL, created_at DATETIME NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (lsp_id) REFERENCES lsp(id),
                    FOREIGN KEY (tuk_id) REFERENCES tuk(id), FOREIGN KEY (role) REFERENCES roles(code))$e",
                "CREATE INDEX idx_memberships_user ON memberships (user_id)",
                "CREATE INDEX idx_memberships_lsp ON memberships (lsp_id)",
                "CREATE TABLE login_attempts (id $pk, email VARCHAR(190) NOT NULL, ip VARCHAR(45) NOT NULL, success SMALLINT NOT NULL,
                    created_at DATETIME NOT NULL)$e",
                "CREATE INDEX idx_login_email ON login_attempts (email, created_at)",
                "CREATE INDEX idx_login_ip ON login_attempts (ip, created_at)",
                "CREATE TABLE audit_logs (id $pk, user_id $fk NULL, lsp_id $fk NULL, action VARCHAR(60) NOT NULL,
                    target VARCHAR(255) NULL, ip VARCHAR(45) NOT NULL, created_at DATETIME NOT NULL)$e",
                "CREATE INDEX idx_audit_lsp ON audit_logs (lsp_id, created_at)",
                "CREATE TABLE listings (id $pk, lsp_id $fk NOT NULL, tipe VARCHAR(20) NOT NULL, judul VARCHAR(150) NOT NULL,
                    bidang VARCHAR(40) NOT NULL, kota VARCHAR(100) NOT NULL, format VARCHAR(40) NOT NULL, harga INT NOT NULL,
                    deskripsi TEXT NOT NULL, status VARCHAR(20) NOT NULL, catatan VARCHAR(500) NULL,
                    created_by $fk NOT NULL, submitted_at DATETIME NULL, reviewed_by $fk NULL, reviewed_at DATETIME NULL,
                    created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                    FOREIGN KEY (lsp_id) REFERENCES lsp(id), FOREIGN KEY (created_by) REFERENCES users(id),
                    FOREIGN KEY (reviewed_by) REFERENCES users(id))$e",
                "CREATE INDEX idx_listings_lsp ON listings (lsp_id, status)",
                "CREATE INDEX idx_listings_status ON listings (status)",
            ];
            foreach ($stmts as $s) {
                db()->exec($s);
            }
            seed_rbac();
            seed_demo();
        },
        2 => function (): void {
            $d = ddl();
            $pk = $d['pk'];
            $fk = $d['fk'];
            $e = $d['end'];
            db()->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL');
            db()->exec("CREATE TABLE asesi_profiles (user_id $fk NOT NULL PRIMARY KEY, nik_enc VARCHAR(255) NOT NULL,
                nik_hash VARCHAR(64) NOT NULL UNIQUE, tanggal_lahir DATE NOT NULL, jenis_kelamin VARCHAR(1) NOT NULL,
                no_hp VARCHAR(20) NOT NULL, consent_privacy_at DATETIME NOT NULL, consent_marketing SMALLINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id))$e");
            db()->exec("CREATE TABLE email_verifications (id $pk, user_id $fk NOT NULL, token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id))$e");
            db()->exec('CREATE INDEX idx_email_verif_token ON email_verifications (token_hash)');
            db()->exec('CREATE INDEX idx_audit_action_ip ON audit_logs (action, ip, created_at)');
            // Akun demo dianggap sudah terverifikasi.
            q('UPDATE users SET email_verified_at = created_at');
        },
        3 => function (): void {
            $d = ddl();
            $pk = $d['pk'];
            $fk = $d['fk'];
            $e = $d['end'];
            $stmts = [
                "CREATE TABLE notifications (id $pk, user_id $fk NOT NULL, lsp_id $fk NULL, type VARCHAR(60) NOT NULL,
                    title VARCHAR(190) NOT NULL, body TEXT NOT NULL, page VARCHAR(40) NULL, read_at DATETIME NULL,
                    created_at DATETIME NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (lsp_id) REFERENCES lsp(id))$e",
                'CREATE INDEX idx_notif_user ON notifications (user_id, read_at)',
                'CREATE INDEX idx_notif_lsp ON notifications (lsp_id)',
                "CREATE TABLE notification_outbox (id $pk, notification_id $fk NOT NULL, channel VARCHAR(20) NOT NULL,
                    recipient VARCHAR(190) NOT NULL, status VARCHAR(20) NOT NULL, attempts SMALLINT NOT NULL DEFAULT 0,
                    last_error VARCHAR(255) NULL, next_attempt_at DATETIME NOT NULL, sent_at DATETIME NULL, created_at DATETIME NOT NULL,
                    FOREIGN KEY (notification_id) REFERENCES notifications(id))$e",
                'CREATE INDEX idx_outbox_queue ON notification_outbox (status, next_attempt_at)',
                'CREATE INDEX idx_outbox_notif ON notification_outbox (notification_id)',
                "CREATE TABLE notification_settings (user_id $fk NOT NULL PRIMARY KEY, email_on SMALLINT NOT NULL DEFAULT 1,
                    wa_on SMALLINT NOT NULL DEFAULT 0, wa_number VARCHAR(20) NULL, wa_opt_in_at DATETIME NULL, updated_at DATETIME NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id))$e",
                "CREATE TABLE ai_usage (id $pk, user_id $fk NOT NULL, lsp_id $fk NULL, status VARCHAR(10) NOT NULL,
                    input_tokens INT NOT NULL DEFAULT 0, output_tokens INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id))$e",
                'CREATE INDEX idx_ai_usage_user ON ai_usage (user_id, created_at)',
                'CREATE INDEX idx_ai_usage_time ON ai_usage (created_at)',
            ];
            foreach ($stmts as $s) {
                db()->exec($s);
            }
            seed_rbac_missing();
        },
    ];
}

function seed_rbac(): void
{
    foreach (ROLE_DEFS as $code => [$nama, $scope, $multi]) {
        q('INSERT INTO roles (code, nama, scope, multi_lsp) VALUES (?, ?, ?, ?)', [$code, $nama, $scope, $multi ? 1 : 0]);
    }
    foreach (PERM_DEFS as $code => $desc) {
        q('INSERT INTO permissions (code, deskripsi) VALUES (?, ?)', [$code, $desc]);
    }
    foreach (ROLE_PERMS as $role => $perms) {
        foreach ($perms as $p) {
            q('INSERT INTO role_permissions (role_code, perm_code) VALUES (?, ?)', [$role, $p]);
        }
    }
}

/** Tambahkan hak akses baru dari konstanta ke database yang sudah berjalan, tanpa menduplikasi. */
function seed_rbac_missing(): void
{
    foreach (PERM_DEFS as $code => $desc) {
        if (!q('SELECT 1 FROM permissions WHERE code = ?', [$code])->fetch()) {
            q('INSERT INTO permissions (code, deskripsi) VALUES (?, ?)', [$code, $desc]);
        }
    }
    foreach (ROLE_PERMS as $role => $perms) {
        foreach ($perms as $p) {
            if (!q('SELECT 1 FROM role_permissions WHERE role_code = ? AND perm_code = ?', [$role, $p])->fetch()) {
                q('INSERT INTO role_permissions (role_code, perm_code) VALUES (?, ?)', [$role, $p]);
            }
        }
    }
}

/** Data contoh. Akun hanya dibuat bila hash password-nya ada di config.php (tidak pernah di repositori). */
function seed_demo(): void
{
    global $CONFIG;
    $t = now();
    $lsps = [
        [1, 'LSP Teknologi Digital Nusantara', 'P3', 'Jakarta'],
        [2, 'LSP Pariwisata Bahari Indonesia', 'P3', 'Denpasar'],
        [3, 'LSP Konstruksi Mandiri', 'P2', 'Surabaya'],
        [4, 'LSP Manajemen Profesional', 'P3', 'Jakarta'],
    ];
    foreach ($lsps as [$id, $nama, $jenis, $kota]) {
        q('INSERT INTO lsp (id, nama, jenis, kota, status, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, $nama, $jenis, $kota, 'aktif', $t]);
    }
    q('INSERT INTO tuk (id, lsp_id, nama) VALUES (1, 1, ?)', ['TUK Sewaktu Kuningan']);
    q('INSERT INTO tuk (id, lsp_id, nama) VALUES (2, 2, ?)', ['TUK Hotel Sanur']);

    $hashes = $CONFIG['seed_password_hashes'] ?? [];
    $users = [
        ['superadmin@demo.portallsp.id', 'Tim Platform', [[null, 'platform_admin', null]]],
        ['admin.tdn@demo.portallsp.id', 'Dewi Lestari', [[1, 'admin_lsp', null]]],
        ['marketing.tdn@demo.portallsp.id', 'Yusuf Hidayat', [[1, 'marketing', null]]],
        ['keuangan.tdn@demo.portallsp.id', 'Ratna Sari', [[1, 'keuangan', null]]],
        ['admin.pbi@demo.portallsp.id', 'Made Wirawan', [[2, 'admin_lsp', null]]],
        ['tuk.kuningan@demo.portallsp.id', 'Agus Pratama', [[1, 'admin_tuk', 1]]],
        ['asesor@demo.portallsp.id', 'Budi Santoso, S.Kom.', [[1, 'asesor', null], [3, 'asesor', null], [4, 'asesor', null]]],
        ['asesi@demo.portallsp.id', 'Rina Kartika Sari', [[1, 'asesi', null], [2, 'asesi', null]]],
    ];
    $ids = [];
    foreach ($users as [$email, $nama, $ms]) {
        if (empty($hashes[$email])) {
            continue;
        }
        q('INSERT INTO users (email, nama, password_hash, status, must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, 0, ?, ?)',
            [$email, $nama, $hashes[$email], 'aktif', $t, $t]);
        $uid = (int)db()->lastInsertId();
        $ids[$email] = $uid;
        foreach ($ms as [$lspId, $role, $tukId]) {
            q('INSERT INTO memberships (user_id, lsp_id, tuk_id, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$uid, $lspId, $tukId, $role, 'aktif', $t]);
        }
    }

    $by = $ids['admin.tdn@demo.portallsp.id'] ?? null;
    $byPbi = $ids['admin.pbi@demo.portallsp.id'] ?? null;
    if ($by && $byPbi) {
        $rows = [
            [1, 'skema', 'Pemrogram Junior (Junior Coder)', 'TIK', 'Jakarta', 'Tatap muka & SJJ', 650000,
                'Skema untuk lulusan SMK RPL. Uji praktik membuat program sederhana dan wawancara.', 'menunggu', null, $by],
            [1, 'pelatihan', 'Bootcamp Laravel 5 Hari', 'TIK', 'Jakarta', 'Tatap muka', 1200000,
                'Belajar Laravel dari nol sampai deploy. Dijamin kompeten saat uji.', 'revisi',
                'Hapus kalimat "dijamin kompeten saat uji" di deskripsi. Cantumkan nama instruktur.', $by],
            [1, 'skema', 'Teknisi Jaringan (paket korporat)', 'TIK', 'Jakarta', 'Tatap muka', 0, 'Draf paket untuk mitra korporat.', 'draf', null, $by],
            [2, 'skema', 'Barista Kopi Nusantara (jadwal Nov)', 'Pariwisata', 'Denpasar', 'Tatap muka', 600000,
                'Skema barista dengan fokus kopi nusantara. TUK Hotel Sanur.', 'menunggu', null, $byPbi],
            [2, 'pelatihan', 'Kelas Persiapan Housekeeping Supervisor', 'Pariwisata', 'Denpasar', 'Webinar', 450000,
                'Persiapan uji untuk supervisor housekeeping. Tidak wajib untuk mendaftar uji.', 'menunggu', null, $byPbi],
        ];
        foreach ($rows as [$lsp, $tipe, $judul, $bidang, $kota, $format, $harga, $desc, $status, $cat, $creator]) {
            q('INSERT INTO listings (lsp_id, tipe, judul, bidang, kota, format, harga, deskripsi, status, catatan, created_by, submitted_at, created_at, updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$lsp, $tipe, $judul, $bidang, $kota, $format, $harga, $desc, $status, $cat, $creator, $status === 'draf' ? null : $t, $t, $t]);
        }
    }
}

/** Menjalankan migrasi yang belum dijalankan. Dikunci dengan file lock agar tidak berjalan ganda. */
function migrate(): void
{
    $all = migrations();
    $latest = max(array_keys($all));
    try {
        $current = (int)db()->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn();
    } catch (PDOException $e) {
        $current = -1;
    }
    if ($current >= $latest) {
        return;
    }
    $lockDir = APP_ROOT . '/storage';
    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0700, true);
    }
    $lock = fopen($lockDir . '/migrate.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        try {
            $current = (int)db()->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn();
        } catch (PDOException $e) {
            db()->exec('CREATE TABLE schema_migrations (version INT NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL)' . ddl()['end']);
            $current = 0;
        }
        foreach ($all as $v => $fn) {
            if ($v <= $current) {
                continue;
            }
            $fn();
            q('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)', [$v, now()]);
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
