# PortalLSP — aplikasi (PHP tanpa framework)

Backend PHP + frontend satu halaman untuk purwarupa PortalLSP, dengan autentikasi dan RBAC multi-LSP.

```
app/
  config.php          # TIDAK di-commit (lihat config.example.php)
  src/                # di luar docroot: bootstrap, db, http, rbac, auth, migrations, routes
  public/             # docroot web: index.html, app.js, api.php, .htaccess
  storage/            # sesi, lock migrasi, database SQLite (tidak di-commit)
  tools/smoke.php     # uji asap via CLI/cron
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

## Menjalankan lokal
```
php tests/make-test-config.php        # config SQLite + akun demo (password: Demo-Pass-2026, hanya lokal)
php -S 127.0.0.1:8099 -t public
node tests/api.test.mjs               # 49 uji autentikasi, CSRF, RBAC, isolasi LSP
```
