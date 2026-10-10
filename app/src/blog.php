<?php
declare(strict_types=1);

/*
 * CMS blog (migrasi 7).
 * - Admin Platform menulis artikel atas nama platform (lsp_id NULL) atau atas nama LSP mana pun, dan bisa menurunkan artikel.
 * - Staf LSP dengan hak blog.manage (Admin LSP, Marketing) hanya mengelola artikel LSP-nya.
 * - Publik hanya melihat artikel berstatus terbit yang waktu terbitnya sudah lewat.
 * Isi artikel disimpan sebagai teks Markdown sederhana; klien meng-escape semua teks sebelum merender format.
 */

const BLOG_KATEGORI = ['Berita', 'Tips Sertifikasi', 'Regulasi', 'Pengumuman', 'Kisah Alumni', 'Pelatihan'];
const BLOG_STATUS = ['draf', 'terbit'];
const BLOG_COVER_MIME = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
const BLOG_PER_PAGE = 12;

function migrate_blog(): void
{
    $d = ddl();
    $pk = $d['pk'];
    $fk = $d['fk'];
    $e = $d['end'];
    db()->exec("CREATE TABLE blog_posts (id $pk, lsp_id $fk NULL, judul VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE,
        ringkasan VARCHAR(400) NULL, konten TEXT NOT NULL, kategori VARCHAR(40) NOT NULL, tags VARCHAR(255) NULL,
        cover_path VARCHAR(255) NULL, cover_mime VARCHAR(40) NULL, status VARCHAR(20) NOT NULL DEFAULT 'draf', terbit_at DATETIME NULL,
        penulis_id $fk NOT NULL, catatan_moderasi VARCHAR(500) NULL, dibaca INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
        FOREIGN KEY (lsp_id) REFERENCES lsp(id), FOREIGN KEY (penulis_id) REFERENCES users(id))$e");
    db()->exec('CREATE INDEX idx_blog_pub ON blog_posts (status, terbit_at)');
    db()->exec('CREATE INDEX idx_blog_lsp ON blog_posts (lsp_id)');
    seed_rbac_missing();
    seed_blog();
}

/** Ubah judul menjadi slug URL: huruf kecil, angka, dan tanda hubung. */
function blog_slugify(string $s): string
{
    $s = strtolower(trim($s));
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($t) && $t !== '') {
            $s = strtolower($t);
        }
    }
    $s = trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    $s = substr($s, 0, 80);
    return trim($s, '-') ?: 'artikel';
}

function blog_unique_slug(string $base, int $exceptId = 0): string
{
    $slug = $base;
    for ($i = 2; q('SELECT 1 FROM blog_posts WHERE slug = ? AND id <> ?', [$slug, $exceptId])->fetch(); $i++) {
        $slug = substr($base, 0, 80) . '-' . $i;
    }
    return $slug;
}

function blog_menit_baca(string $konten): int
{
    return max(1, (int)ceil(str_word_count(strip_tags($konten)) / 200));
}

function blog_sql(): string
{
    return 'SELECT b.*, s.nama AS lsp_nama, s.status AS lsp_status, u.nama AS penulis_nama FROM blog_posts b
            LEFT JOIN lsp s ON s.id = b.lsp_id JOIN users u ON u.id = b.penulis_id';
}

/** Status tampilan: terjadwal = terbit tetapi waktu terbitnya belum tiba. */
function blog_status_view(array $r): string
{
    if ($r['status'] === 'terbit' && $r['terbit_at'] !== null && $r['terbit_at'] > now()) {
        return 'terjadwal';
    }
    return $r['status'];
}

function blog_row(array $r, bool $full = true): array
{
    $o = [
        'id' => (int)$r['id'], 'lsp_id' => $r['lsp_id'] === null ? null : (int)$r['lsp_id'],
        'penerbit' => $r['lsp_id'] === null ? 'PortalLSP' : $r['lsp_nama'], 'judul' => $r['judul'], 'slug' => $r['slug'],
        'ringkasan' => (string)$r['ringkasan'], 'kategori' => $r['kategori'],
        'tags' => array_values(array_filter(array_map('trim', explode(',', (string)$r['tags'])), 'strlen')),
        'cover' => $r['cover_path'] ? 'api.php?r=pub/blog/cover&id=' . (int)$r['id'] . '&v=' . substr(md5((string)$r['cover_path']), 0, 8) : null,
        'terbit_at' => $r['terbit_at'], 'penulis' => $r['penulis_nama'], 'menit_baca' => blog_menit_baca((string)$r['konten']),
    ];
    if ($full) {
        $o += ['konten' => $r['konten'], 'status' => $r['status'], 'status_view' => blog_status_view($r), 'catatan_moderasi' => $r['catatan_moderasi'],
            'dibaca' => (int)$r['dibaca'], 'created_at' => $r['created_at'], 'updated_at' => $r['updated_at']];
    }
    return $o;
}

/** Artikel dalam cakupan pengguna; artikel di luar cakupan diperlakukan seperti tidak ada (404). */
function own_blog_post(int $id, array $m): array
{
    $r = is_platform($m)
        ? q(blog_sql() . ' WHERE b.id = ?', [$id])->fetch()
        : q(blog_sql() . ' WHERE b.id = ? AND b.lsp_id = ?', [$id, (int)$m['lsp_id']])->fetch();
    if (!$r) {
        audit('access.denied', 'blog:' . $id);
        fail('Artikel tidak ditemukan.', 404);
    }
    return $r;
}

/** Waktu terbit dari input datetime-local (YYYY-MM-DDTHH:MM). Kosong = sekarang. */
function blog_parse_time(string $v): ?string
{
    if ($v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $v) ?: DateTime::createFromFormat('Y-m-d H:i:s', $v) ?: DateTime::createFromFormat('Y-m-d H:i', $v);
    if (!$d) {
        fail('Format waktu terbit tidak valid.', 422);
    }
    return $d->format('Y-m-d H:i:00');
}

function r_blog(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $m = require_perm('blog.manage');
        $scope = scope_lsp($m);
        $rows = $scope === null
            ? q(blog_sql() . ' ORDER BY COALESCE(b.terbit_at, b.updated_at) DESC, b.id DESC LIMIT 300')->fetchAll()
            : q(blog_sql() . ' WHERE b.lsp_id = ? ORDER BY COALESCE(b.terbit_at, b.updated_at) DESC, b.id DESC LIMIT 300', [$scope])->fetchAll();
        json_out(['items' => array_map('blog_row', $rows), 'kategori' => BLOG_KATEGORI, 'moderator' => is_platform($m)]);
    }
    require_method('POST');
    csrf_check();
    $m = require_perm('blog.manage');
    $u = current_user();
    $id = int_in('id');
    $old = $id ? own_blog_post($id, $m) : null;

    $judul = str_in('judul', 150, 'Judul');
    if (text_len($judul) < 5) {
        fail('Judul minimal 5 karakter.', 422);
    }
    $konten = str_in('konten', 50000, 'Isi artikel');
    if (text_len($konten) < 20) {
        fail('Isi artikel minimal 20 karakter.', 422);
    }
    $ringkasan = str_in('ringkasan', 300, 'Ringkasan');
    $kategori = str_in('kategori', 40);
    if (!in_array($kategori, BLOG_KATEGORI, true)) {
        fail('Pilih kategori.', 422);
    }
    $tags = implode(', ', array_slice(array_values(array_unique(array_filter(array_map(function ($t) {
        return cut(trim($t), 30);
    }, explode(',', str_in('tags', 200, 'Tag'))), 'strlen'))), 0, 8));
    $status = str_in('status', 10);
    if (!in_array($status, BLOG_STATUS, true)) {
        fail('Status tidak valid.', 422);
    }
    $terbit = blog_parse_time(str_in('terbit_at', 20));
    if ($status === 'terbit' && $terbit === null) {
        $terbit = ($old && $old['terbit_at'] !== null && $old['terbit_at'] <= now()) ? $old['terbit_at'] : now();
    }

    $slugIn = strtolower(str_in('slug', 90, 'Slug'));
    if ($slugIn !== '') {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugIn)) {
            fail('Slug hanya boleh huruf kecil, angka, dan tanda hubung.', 422);
        }
        if (q('SELECT 1 FROM blog_posts WHERE slug = ? AND id <> ?', [$slugIn, $id])->fetch()) {
            fail('Slug sudah dipakai artikel lain.', 409);
        }
        $slug = $slugIn;
    } else {
        $slug = $old ? $old['slug'] : blog_unique_slug(blog_slugify($judul));
    }

    // Artikel yang diturunkan Admin Platform tetap diturunkan sampai dipulihkan oleh Admin Platform.
    $newStatus = ($old && $old['status'] === 'diturunkan' && !is_platform($m)) ? 'diturunkan' : $status;

    if ($old) {
        q('UPDATE blog_posts SET judul = ?, slug = ?, ringkasan = ?, konten = ?, kategori = ?, tags = ?, status = ?, terbit_at = ?, updated_at = ? WHERE id = ?',
            [$judul, $slug, $ringkasan, $konten, $kategori, $tags, $newStatus, $terbit, now(), $id]);
        audit('blog.ubah', 'blog:' . $id);
    } else {
        if (is_platform($m)) {
            $lspId = int_in('lsp_id') ?: null;
            if ($lspId !== null && !q("SELECT 1 FROM lsp WHERE id = ? AND status = 'aktif'", [$lspId])->fetch()) {
                fail('LSP penerbit tidak ditemukan.', 422);
            }
        } else {
            $lspId = scope_lsp($m);
        }
        q('INSERT INTO blog_posts (lsp_id, judul, slug, ringkasan, konten, kategori, tags, status, terbit_at, penulis_id, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$lspId, $judul, $slug, $ringkasan, $konten, $kategori, $tags, $newStatus, $terbit, (int)$u['id'], now(), now()]);
        $id = (int)db()->lastInsertId();
        audit('blog.buat', 'blog:' . $id);
    }
    $r = q(blog_sql() . ' WHERE b.id = ?', [$id])->fetch();
    json_out(['ok' => true, 'id' => $id, 'baru' => !$old, 'slug' => $slug, 'status' => blog_status_view($r),
        'catatan' => $newStatus === 'diturunkan' ? 'Artikel masih diturunkan. Hubungi Admin Platform untuk menayangkannya kembali.' : null], $old ? 200 : 201);
}

function blog_delete_cover(?string $path): void
{
    if ($path && strpos($path, 'uploads/blog/') === 0) {
        @unlink(APP_ROOT . '/storage/' . $path);
    }
}

function r_blog_hapus(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('blog.manage');
    $r = own_blog_post(int_in('id'), $m);
    if ($r['status'] === 'diturunkan' && !is_platform($m)) {
        fail('Artikel yang diturunkan Admin Platform tidak bisa dihapus oleh LSP. Perbaiki isinya lalu minta ditayangkan kembali.', 409);
    }
    q('DELETE FROM blog_posts WHERE id = ?', [$r['id']]);
    blog_delete_cover($r['cover_path']);
    audit('blog.hapus', 'blog:' . $r['id'] . ' ' . cut($r['judul'], 80));
    json_out(['ok' => true]);
}

/** Moderasi oleh Admin Platform: turunkan (wajib alasan) atau pulihkan. */
function r_blog_moderasi(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('lsp.manage');
    $r = own_blog_post(int_in('id'), $m);
    $aksi = str_in('aksi', 10);
    if ($aksi === 'turunkan') {
        $cat = str_in('catatan', 500, 'Alasan');
        if (text_len($cat) < 5) {
            fail('Tulis alasan penurunan (minimal 5 karakter).', 422);
        }
        q("UPDATE blog_posts SET status = 'diturunkan', catatan_moderasi = ?, updated_at = ? WHERE id = ?", [$cat, now(), $r['id']]);
        if ($r['lsp_id'] !== null) {
            notify(users_with_perm('blog.manage', (int)$r['lsp_id']), (int)$r['lsp_id'], 'blog.diturunkan', 'Artikel blog diturunkan',
                "\"{$r['judul']}\" diturunkan oleh Admin Platform: $cat", 'blog');
        }
    } elseif ($aksi === 'pulihkan') {
        if ($r['status'] !== 'diturunkan') {
            fail('Artikel ini tidak sedang diturunkan.', 409);
        }
        q("UPDATE blog_posts SET status = 'terbit', catatan_moderasi = NULL, terbit_at = COALESCE(terbit_at, ?), updated_at = ? WHERE id = ?", [now(), now(), $r['id']]);
        if ($r['lsp_id'] !== null) {
            notify(users_with_perm('blog.manage', (int)$r['lsp_id']), (int)$r['lsp_id'], 'blog.dipulihkan', 'Artikel blog tayang kembali',
                "\"{$r['judul']}\" sudah ditayangkan kembali oleh Admin Platform.", 'blog');
        }
    } else {
        fail('Aksi tidak valid.', 422);
    }
    audit('blog.' . $aksi, 'blog:' . $r['id']);
    json_out(['ok' => true]);
}

/** Unggah atau hapus gambar sampul (PNG/JPG, maks 2 MB). */
function r_blog_cover(): void
{
    require_method('POST');
    csrf_check();
    $m = require_perm('blog.manage');
    $id = isset($_POST['id']) && is_string($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : 0;
    $r = own_blog_post($id, $m);
    if (($_POST['hapus'] ?? '') === '1') {
        q('UPDATE blog_posts SET cover_path = NULL, cover_mime = NULL, updated_at = ? WHERE id = ?', [now(), $id]);
        blog_delete_cover($r['cover_path']);
        json_out(['ok' => true]);
    }
    $f = $_FILES['file'] ?? null;
    if (is_array($f) && in_array($f['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        fail('Ukuran gambar maksimal 2 MB.', 422);
    }
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        fail('Pilih gambar untuk diunggah.', 422);
    }
    if ($f['size'] > DOK_MAX) {
        fail('Ukuran gambar maksimal 2 MB.', 422);
    }
    $mime = sniff_mime($f['tmp_name']);
    if (!isset(BLOG_COVER_MIME[$mime])) {
        fail('Gambar sampul harus JPG atau PNG.', 422);
    }
    $dir = APP_ROOT . '/storage/uploads/blog';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $rel = 'uploads/blog/' . bin2hex(random_bytes(16)) . '.' . BLOG_COVER_MIME[$mime];
    if (!move_uploaded_file($f['tmp_name'], APP_ROOT . '/storage/' . $rel)) {
        fail('Gambar gagal disimpan. Coba lagi.', 500);
    }
    chmod(APP_ROOT . '/storage/' . $rel, 0600);
    q('UPDATE blog_posts SET cover_path = ?, cover_mime = ?, updated_at = ? WHERE id = ?', [$rel, $mime, now(), $id]);
    blog_delete_cover($r['cover_path']);
    audit('blog.sampul', 'blog:' . $id);
    json_out(['ok' => true], 201);
}

/* =========================== Publik =========================== */

function blog_public_where(): string
{
    return "b.status = 'terbit' AND b.terbit_at <= ? AND (b.lsp_id IS NULL OR s.status = 'aktif')";
}

function r_pub_blog(): void
{
    require_method('GET');
    $where = blog_public_where();
    $args = [now()];
    $kat = isset($_GET['kategori']) && is_string($_GET['kategori']) && in_array($_GET['kategori'], BLOG_KATEGORI, true) ? $_GET['kategori'] : '';
    if ($kat !== '') {
        $where .= ' AND b.kategori = ?';
        $args[] = $kat;
    }
    if (isset($_GET['lsp']) && is_string($_GET['lsp']) && ctype_digit($_GET['lsp'])) {
        $where .= ' AND b.lsp_id = ?';
        $args[] = (int)$_GET['lsp'];
    }
    $qq = isset($_GET['q']) && is_string($_GET['q']) ? trim(cut($_GET['q'], 80)) : '';
    if ($qq !== '') {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], strtolower($qq)) . '%';
        $where .= " AND (LOWER(b.judul) LIKE ? ESCAPE '!' OR LOWER(b.ringkasan) LIKE ? ESCAPE '!' OR LOWER(b.tags) LIKE ? ESCAPE '!')";
        array_push($args, $like, $like, $like);
    }
    $page = isset($_GET['page']) && is_string($_GET['page']) && ctype_digit($_GET['page']) ? max(1, min(500, (int)$_GET['page'])) : 1;
    $total = (int)q('SELECT COUNT(*) FROM blog_posts b LEFT JOIN lsp s ON s.id = b.lsp_id WHERE ' . $where, $args)->fetchColumn();
    $rows = q(blog_sql() . ' WHERE ' . $where . ' ORDER BY b.terbit_at DESC, b.id DESC LIMIT ' . BLOG_PER_PAGE . ' OFFSET ' . (($page - 1) * BLOG_PER_PAGE), $args)->fetchAll();
    $kats = q('SELECT b.kategori, COUNT(*) AS n FROM blog_posts b LEFT JOIN lsp s ON s.id = b.lsp_id WHERE ' . blog_public_where() . ' GROUP BY b.kategori', [now()])->fetchAll();
    json_out([
        'items' => array_map(function ($r) { return blog_row($r, false); }, $rows),
        'total' => $total, 'page' => $page, 'per_page' => BLOG_PER_PAGE,
        'kategori' => array_map(function ($k) use ($kats) {
            foreach ($kats as $x) {
                if ($x['kategori'] === $k) return ['nama' => $k, 'n' => (int)$x['n']];
            }
            return ['nama' => $k, 'n' => 0];
        }, BLOG_KATEGORI),
    ]);
}

function r_pub_blog_post(): void
{
    require_method('GET');
    $slug = isset($_GET['slug']) && is_string($_GET['slug']) ? strtolower(cut($_GET['slug'], 190)) : '';
    $r = q(blog_sql() . ' WHERE b.slug = ? AND ' . blog_public_where(), [$slug, now()])->fetch();
    if (!$r) {
        fail('Artikel tidak ditemukan atau belum terbit.', 404);
    }
    // Hitung pembaca sekali per sesi; permintaan tanpa sesi (bot tanpa cookie) tidak dihitung.
    $seen = $_SESSION['blog_seen'] ?? [];
    if (session_status() === PHP_SESSION_ACTIVE && !in_array((int)$r['id'], $seen, true)) {
        q('UPDATE blog_posts SET dibaca = dibaca + 1 WHERE id = ?', [$r['id']]);
        $seen[] = (int)$r['id'];
        $_SESSION['blog_seen'] = array_slice($seen, -200);
        $r['dibaca'] = (int)$r['dibaca'] + 1;
    }
    $sameLsp = $r['lsp_id'] === null ? 'b.lsp_id IS NULL' : 'b.lsp_id = ' . (int)$r['lsp_id'];
    $related = q(blog_sql() . ' WHERE ' . blog_public_where() . " AND b.id <> ? AND (b.kategori = ? OR $sameLsp) ORDER BY b.terbit_at DESC LIMIT 3",
        [now(), $r['id'], $r['kategori']])->fetchAll();
    $o = blog_row($r, true);
    unset($o['status'], $o['catatan_moderasi'], $o['created_at']);
    json_out(['post' => $o, 'terkait' => array_map(function ($x) { return blog_row($x, false); }, $related)]);
}

/** Gambar sampul: publik untuk artikel yang sudah terbit; selain itu hanya pengelola artikel. */
function r_pub_blog_cover(): void
{
    require_method('GET');
    $id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
    $r = q(blog_sql() . ' WHERE b.id = ?', [$id])->fetch();
    $public = $r && $r['cover_path'] && q('SELECT 1 FROM blog_posts b LEFT JOIN lsp s ON s.id = b.lsp_id WHERE b.id = ? AND ' . blog_public_where(), [$id, now()])->fetch();
    if ($r && $r['cover_path'] && !$public) {
        $m = current_user() ? active_membership() : null;
        $ok = $m && can('blog.manage') && (is_platform($m) || ((int)$m['lsp_id'] === (int)$r['lsp_id'] && $r['lsp_id'] !== null));
        if (!$ok) {
            $r = null;
        }
    }
    $path = $r && $r['cover_path'] ? APP_ROOT . '/storage/' . $r['cover_path'] : '';
    if ($path === '' || !is_file($path)) {
        fail('Gambar tidak ditemukan.', 404);
    }
    header('Content-Type: ' . $r['cover_mime']);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: ' . ($public ? 'public, max-age=86400' : 'private, no-store'));
    header("Content-Security-Policy: default-src 'none'; sandbox");
    readfile($path);
    exit;
}

/* =========================== Data contoh =========================== */

/** Artikel contoh. Hanya dibuat bila akun demo ada (tidak pernah di instalasi bersih). */
function seed_blog(): void
{
    $super = seed_uid('superadmin@demo.portallsp.id');
    $mkt = seed_uid('marketing.tdn@demo.portallsp.id');
    $pbi = seed_uid('admin.pbi@demo.portallsp.id');
    if (!$super || !$mkt || !$pbi) {
        return;
    }
    $ago = function (int $days): string {
        return date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days')) . ' 09:00:00';
    };
    $posts = [
        [null, $super, 'Panduan Lengkap Sertifikasi Kompetensi BNSP untuk Pemula', 'Tips Sertifikasi',
            'Apa itu sertifikasi kompetensi, siapa yang membutuhkan, dan bagaimana alurnya dari daftar sampai sertifikat terbit.',
            "Sertifikat kompetensi adalah bukti pengakuan bahwa seseorang **mampu melakukan pekerjaan** sesuai standar (SKKNI). Sertifikat diterbitkan oleh LSP berlisensi BNSP.\n\n## Siapa yang membutuhkan?\n\n- Lulusan SMK, D3, atau S1 yang ingin menambah nilai jual saat melamar kerja\n- Pekerja yang perlu memenuhi syarat proyek atau tender\n- Profesional yang ingin pengakuan resmi atas pengalamannya\n\n## Alur sertifikasi di PortalLSP\n\n1. Cari skema dan pilih jadwal uji\n2. Isi asesmen mandiri (APL.02) dan unggah berkas\n3. Bayar setelah berkas diverifikasi LSP\n4. Ikuti pra-asesmen dan uji kompetensi\n5. Sertifikat terbit setelah rapat pleno\n\n> Berkas yang sudah diunggah di profil dipakai ulang untuk pendaftaran berikutnya, jadi Anda tidak perlu mengunggah ulang.\n\nSiap mulai? Buka menu **Cari Skema** di bagian atas halaman.",
            'sertifikasi, bnsp, pemula', -12, 'terbit'],
        [null, $super, 'Cara Memverifikasi Keaslian Sertifikat Kompetensi', 'Pengumuman',
            'Perusahaan dan HRD kini bisa memeriksa keaslian sertifikat dalam hitungan detik.',
            "Setiap sertifikat yang terbit lewat PortalLSP memiliki **kode verifikasi** 10 karakter.\n\n## Langkah verifikasi\n\n1. Buka menu *Verifikasi Sertifikat*\n2. Masukkan nomor sertifikat atau kode verifikasi\n3. Sistem menampilkan nama pemegang, skema, LSP penerbit, dan masa berlaku\n\nStatus yang mungkin muncul: **berlaku**, **kedaluwarsa**, atau **dicabut**.\n\nUntuk mencegah penyalahgunaan, pencarian dibatasi 30 kali per 10 menit untuk setiap alamat IP.",
            'verifikasi, hrd', -5, 'terbit'],
        [1, $mkt, '5 Tips Lulus Uji Kompetensi Junior Web Developer', 'Tips Sertifikasi',
            'Persiapan praktis dari asesor kami agar Anda percaya diri saat uji praktik.',
            "Uji kompetensi Junior Web Developer menilai kemampuan Anda membangun aplikasi web sederhana sesuai unit SKKNI.\n\n## 1. Pahami unit kompetensinya\n\nBaca daftar unit di halaman skema. Setiap unit punya kriteria unjuk kerja yang akan diamati asesor.\n\n## 2. Siapkan portofolio\n\nKumpulkan contoh proyek, potongan kode, dan dokumentasi. Portofolio yang rapi memudahkan asesor menilai bukti.\n\n## 3. Latihan debugging\n\nUnit *Melakukan debugging* sering menjadi tantangan. Biasakan membaca pesan galat dan menelusuri alur program.\n\n## 4. Tulis kode yang rapi\n\nGunakan penamaan yang jelas dan komentar seperlunya.\n\n## 5. Datang lebih awal\n\nPastikan perangkat dan koneksi siap, terutama untuk uji jarak jauh (SJJ).\n\nJadwal terdekat bisa dilihat di [halaman jadwal uji](https://lsp.semestateknologiutama.com/).",
            'web developer, tips, tik', -3, 'terbit'],
        [1, $mkt, 'Jadwal Uji Analis Data Junior Bulan Depan Dibuka', 'Pengumuman',
            'LSP Teknologi Digital Nusantara membuka kuota uji Analis Data Junior secara daring.',
            "Kami membuka jadwal uji **Analis Data Junior** melalui TUK Daring (SJJ). Kuota terbatas.\n\n- Metode: SJJ (jarak jauh)\n- Biaya: sesuai halaman skema\n- Persyaratan: KTP, ijazah terakhir, dan pas foto\n\nDaftar sekarang lewat menu *Cari Skema*.",
            'analis data, sjj', 2, 'terbit'],
        [1, $mkt, 'Kisah Alumni: Dari Kasir Menjadi Digital Marketer', 'Kisah Alumni',
            'Draf cerita alumni yang sedang disiapkan tim marketing.',
            "Draf. Wawancara dengan alumni skema Digital Marketing akan ditambahkan setelah mendapat persetujuan narasumber.",
            'alumni, digital marketing', 0, 'draf'],
        [2, $pbi, 'Standar Higiene dan Sanitasi untuk Barista', 'Pelatihan',
            'Mengapa unit higiene dan sanitasi wajib dikuasai sebelum uji Barista.',
            "Unit **Menerapkan higiene dan sanitasi** adalah salah satu unit inti skema Barista.\n\n## Yang dinilai asesor\n\n- Kebersihan diri dan seragam\n- Kebersihan peralatan kopi\n- Penyimpanan bahan baku sesuai suhu\n\nIkuti kelas persiapan kami di menu *Pelatihan* untuk latihan langsung.",
            'barista, higiene, pariwisata', -8, 'terbit'],
    ];
    foreach ($posts as [$lsp, $by, $judul, $kat, $ring, $isi, $tags, $hari, $status]) {
        $t = $ago($hari);
        q('INSERT INTO blog_posts (lsp_id, judul, slug, ringkasan, konten, kategori, tags, status, terbit_at, penulis_id, dibaca, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$lsp, $judul, blog_unique_slug(blog_slugify($judul)), $ring, $isi, $kat, $tags, $status, $status === 'terbit' ? $t : null, $by,
                $hari < 0 ? 40 * -$hari + 17 : 0, $t, $t]);
    }
}
