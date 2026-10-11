# PortalLSP: aplikasi (PHP tanpa framework)

Aplikasi sertifikasi kompetensi multi-LSP: portal publik, pendaftaran asesi, proses asesmen sampai pleno, sertifikat PDF dengan kode QR dan tanda tangan digital, serta menu operasional LSP, TUK, asesor, dan Admin Platform.

- **Produksi:** https://lsp.semestateknologiutama.com (versi terpasang **v12**, 11 Oktober 2026)
- **Dokumentasi lengkap:** [`../docs/dokumentasi-portallsp.md`](../docs/dokumentasi-portallsp.md). Berisi peran, alur, menu, keamanan, model data, semua endpoint, konfigurasi, deploy, dan riwayat versi.
- **Rancangan fitur awal:** [`../docs/rancangan-fitur.md`](../docs/rancangan-fitur.md)

README ini hanya ringkasan untuk pengembang. Bila ada perbedaan, yang berlaku adalah dokumentasi lengkap.

## Teknologi

| Bagian | Pilihan |
|---|---|
| Backend | PHP 8.3 tanpa framework, satu pintu masuk `public/api.php?r=<rute>` |
| Database | SQLite (produksi saat ini) atau MySQL, migrasi berversi otomatis |
| Frontend | SPA vanilla JS (`app.js` + `fitur.js`), CSP ketat `script-src 'self'` |
| PDF dan QR | Penyusun PDF dan encoder QR ditulis sendiri (`src/pdf.php`, `src/qr.php`), tanpa pustaka luar |
| Tanda tangan digital | OpenSSL (CMS/PKCS#7 terlepas): CA PortalLSP dan satu sertifikat per LSP, atau `.p12` dari PSrE |
| Notifikasi | Email (`mail()`), WhatsApp (Fonnte / WhatsApp Cloud API), antrean lewat cron |
| AI | Claude Messages API, dipanggil dari server |

## Struktur folder

```
app/
  config.php            # TIDAK di-commit; salin dari config.example.php
  config.example.php    # contoh konfigurasi tanpa rahasia
  public/               # docroot: index.html, app.js, fitur.js, fitur.css, api.php, .htaccess
  src/                  # di luar docroot: semua logika server (lihat dokumentasi bagian 2)
  storage/              # di luar docroot, tidak di-commit: database, sesi, unggahan, app.key, tte/ (kunci tanda tangan), log, cadangan
  tools/
    smoke.php           # uji asap CLI (unggah ke server hanya saat dibutuhkan)
    notify-worker.php   # pengirim antrean email/WhatsApp (cron)
    fix-header.php      # perbaikan sekali jalan untuk app.js (riwayat; tidak perlu dijalankan lagi)
  tests/                # uji API (Node) dan uji browser (Playwright)
```

## Menjalankan lokal

```bash
cd app
php tests/make-test-config.php      # config SQLite + akun demo khusus lokal (password: Demo-Pass-2026)
php -S 127.0.0.1:8099 -t public     # buka http://127.0.0.1:8099
```

Akun demo dan data contoh dijelaskan di dokumentasi bagian 17. Contoh verifikasi sertifikat: `/?cek=TDN7K3P9QX`.

## Pengujian

Jalankan setiap suite pada database baru: hapus `storage/test.sqlite` sebelum suite berikutnya. Suite saling berbagi data contoh dan batas login per IP.

```bash
node tests/api.test.mjs             # 49 uji: autentikasi, CSRF, RBAC, isolasi LSP
node tests/register.test.mjs        # 29 uji: pendaftaran asesi mandiri
node tests/notify-ai.test.mjs       # 47 uji: notifikasi & asisten AI (server tiruan port 8098)
node tests/platform.test.mjs        # 23 uji: akses penuh Admin Platform + isolasi Admin LSP
node tests/flow.test.mjs            # 108 uji: alur sertifikasi ujung-ke-ujung + isolasi
node tests/blog.test.mjs            # 83 uji: CMS blog
node tests/rbac-matrix.test.mjs     # 1.211 uji: setiap peran × setiap endpoint
node tests/crud.test.mjs            # 89 uji: buat/baca/ubah/hapus semua data
node tests/security.test.mjs        # 49 uji: regresi temuan audit keamanan
node tests/sertifikat.test.mjs      # 36 uji: PDF sertifikat, kode QR, tanda tangan digital, hak unduh

TZ=Asia/Jakarta NODE_PATH=$(npm root -g) node tests/ui/alur-asesi.cjs  # daftar asesi s.d. sertifikat terverifikasi
NODE_PATH=$(npm root -g) node tests/ui/semua-menu.cjs                  # buka semua menu untuk 7 peran
NODE_PATH=$(npm root -g) node tests/ui/blog.cjs                        # CMS blog s.d. halaman artikel publik
NODE_PATH=$(npm root -g) node tests/ui/crud.cjs                        # tombol ubah/hapus, profil, unduh PDF
NODE_PATH=$(npm root -g) node tests/ui/responsif.cjs                   # 548 tampilan di lebar 360/390/768/1024 px
```

Hasil terakhir (11 Oktober 2026, v12): semua 1.724 uji API lulus, semua uji browser lulus, dan audit responsif tanpa masalah.

## Ringkasan keamanan

Rinciannya ada di dokumentasi bagian 4 dan 11.

- **Password:** bcrypt dengan rehash otomatis. Akun buatan admin wajib ganti password saat login pertama.
- **Sesi:** cookie `HttpOnly`/`Secure`/`SameSite=Lax`, ID sesi diganti saat login, batas idle 30 menit, maksimal 8 jam. Ganti password mengakhiri sesi lain.
- **CSRF:** token per sesi + cek `Origin`.
- **Brute force:** penguncian login per email dan per IP (IPv6 dihitung per /64).
- **RBAC:** dari database, dicek di setiap endpoint. `lsp_id` selalu diambil dari sesi; data LSP lain dibalas 404.
- **Data pribadi:** NIK terenkripsi AES-256-GCM. Unggahan dicek dari isi berkas dan disimpan di luar docroot.
- **Kunci tanda tangan digital:** di `storage/tte/`, terenkripsi, izin 0600.
- **Header:** CSP ketat, HSTS, X-Frame-Options, isolasi lintas-origin.

## Deploy

Produksi diperbarui dengan **patch PHP sekali jalan** yang memeriksa SHA-256 setiap berkas sebelum menulis dan menyimpan cadangan di `storage/backup-<waktu>/`. Langkahnya ada di dokumentasi bagian 15.

**Sebelum dipakai LSP sungguhan,** baca dulu daftar periksanya di bagian yang sama:
- matikan `payment_simulation`;
- hapus akun demo;
- cadangkan `storage/app.key` dan `storage/tte/`;
- umumkan sidik jari CA, atau pasang sertifikat PSrE.

Setiap rilis yang mengubah JS/CSS wajib menaikkan `?v=` di `public/index.html`, karena proxy hosting menyimpan berkas statis sebagai *immutable* selama 30 hari.
