# PortalLSP — aplikasi (PHP tanpa framework)

Backend PHP + frontend satu halaman untuk purwarupa PortalLSP, dengan autentikasi dan RBAC multi-LSP.

```
app/
  config.php          # TIDAK di-commit (lihat config.example.php)
  src/                # di luar docroot: bootstrap, db, http, rbac, auth, migrations, routes
  public/             # docroot web: index.html, app.js, api.php, .htaccess
  storage/            # sesi, lock migrasi, database SQLite (tidak di-commit)
  tools/smoke.php     # uji asap via CLI/cron
  tools/notify-worker.php  # pengirim antrean email/WhatsApp (cron)
  tests/              # uji integrasi API
```

## Keamanan yang diterapkan
- Password di-hash `password_hash` (bcrypt) dan di-rehash otomatis; kebijakan min. 10 karakter, huruf + angka.
- Sesi: cookie `HttpOnly`, `Secure`, `SameSite=Lax`; ID sesi diganti saat login/ganti konteks/ganti password;
  idle timeout 30 menit, maksimal 8 jam; file sesi di luar docroot.
- CSRF: token per sesi wajib di header `X-CSRF-Token` + cek `Origin` untuk setiap POST.
- Penguncian login: 5 gagal per email atau 20 per IP dalam 15 menit; pesan error sama untuk email terdaftar/tidak.
- Akun baru dari Admin LSP wajib ganti password saat login pertama; API lain diblokir sampai diganti.
- RBAC dari tabel `roles`, `permissions`, `role_permissions`; diperiksa di server pada setiap endpoint.
- Isolasi LSP: semua query listing/pengguna memakai `lsp_id` dari keanggotaan aktif di sesi (bukan dari input);
  data LSP lain dibalas 404. Peran internal LSP hanya boleh satu keanggotaan; asesor/asesi boleh banyak.
- Akun dibaca ulang dari DB tiap request, jadi akun/keanggotaan nonaktif langsung kehilangan akses.
- Log audit untuk login, logout, akses ditolak, perubahan listing, keputusan kurasi, dan manajemen pengguna.
- Header keamanan & CSP ketat (`script-src 'self'`), HTTPS + HSTS, semua teks dari server di-escape di klien.

## Notifikasi (aplikasi, email, WhatsApp)
- Setiap peristiwa penting membuat notifikasi di kotak masuk aplikasi (ikon lonceng) lalu diantrekan ke email/WhatsApp
  sesuai pengaturan pengguna. Pengiriman dilakukan `tools/notify-worker.php` lewat cron (tiap 2 menit), dengan
  percobaan ulang hingga 5 kali.
- Peristiwa: listing diajukan (ke Admin Platform), listing disetujui/revisi/ditolak (ke staf LSP yang mengelola etalase),
  akun dibuat dan akses diaktifkan/dinonaktifkan (ke pengguna), password diganti (selalu ke email, tidak bisa dimatikan).
- WhatsApp hanya dikirim bila pengguna mengisi nomor dan mencentang persetujuan (opt-in); waktunya dicatat.
- Password sementara tidak pernah dikirim lewat email/WA.
- Log pengiriman: Admin LSP hanya melihat notifikasi LSP-nya, Admin Platform melihat semua; penerima disamarkan.
- Driver WA: `fonnte` atau `meta` (WhatsApp Cloud API, wajib template). Kosong = kanal WA dilewati.

## Asisten AI
- Panggilan ke API Claude dilakukan dari server; kunci hanya di `config.php`. Kosong = fitur nonaktif.
- Konteks yang dikirim ke model hanya data milik pengguna/LSP aktifnya (ringkasan peran, hak akses, status listing).
  Email, NIK, dan nomor HP tidak dikirim; NIK/email/nomor HP yang diketik pengguna disamarkan sebelum dikirim.
- Model tidak punya akses tool, jadi tidak bisa mengubah data atau membaca data di luar konteks.
- Isi percakapan tidak disimpan di server; yang dicatat hanya jumlah token (tabel `ai_usage`).
- Batas: 20 pesan/jam dan 100 pesan/hari per pengguna, plus batas harian platform (`ai_daily_limit`).
- Aturan integritas: asisten tidak membuatkan jawaban uji, isian APL.02, atau bukti portofolio.

## Menjalankan lokal
```
php tests/make-test-config.php        # config SQLite + akun demo (password: Demo-Pass-2026, hanya lokal)
php -S 127.0.0.1:8099 -t public
node tests/api.test.mjs               # 49 uji autentikasi, CSRF, RBAC, isolasi LSP
node tests/register.test.mjs          # 29 uji pendaftaran asesi
node tests/notify-ai.test.mjs         # 47 uji notifikasi & asisten AI (server tiruan di port 8098)
```
