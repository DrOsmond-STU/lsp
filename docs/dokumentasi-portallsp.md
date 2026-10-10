# Dokumentasi PortalLSP

Dokumen ini menjelaskan aplikasi PortalLSP secara menyeluruh: fungsi, peran pengguna, alur sertifikasi, menu tiap peran, keamanan, data, API, konfigurasi, deploy, dan pengujian.

- **Alamat produksi:** https://lsp.semestateknologiutama.com
- **Kode sumber:** folder `app/` di repositori ini
- **Rancangan fitur awal:** [`docs/rancangan-fitur.md`](rancangan-fitur.md)

---

## Daftar isi

1. [Ringkasan](#1-ringkasan)
2. [Arsitektur](#2-arsitektur)
3. [Peran dan hak akses](#3-peran-dan-hak-akses)
4. [Isolasi data antar-LSP](#4-isolasi-data-antar-lsp)
5. [Alur sertifikasi](#5-alur-sertifikasi)
6. [Menu dan fitur per peran](#6-menu-dan-fitur-per-peran)
7. [Portal publik](#7-portal-publik)
8. [Blog (CMS)](#8-blog-cms)
9. [Notifikasi (aplikasi, email, WhatsApp)](#9-notifikasi-aplikasi-email-whatsapp)
10. [Asisten AI](#10-asisten-ai)
11. [Keamanan](#11-keamanan)
12. [Model data](#12-model-data)
13. [Daftar endpoint API](#13-daftar-endpoint-api)
14. [Konfigurasi](#14-konfigurasi)
15. [Instalasi, deploy, dan cron](#15-instalasi-deploy-dan-cron)
16. [Pengujian](#16-pengujian)
17. [Data dan akun demo](#17-data-dan-akun-demo)
18. [Batasan dan langkah berikutnya](#18-batasan-dan-langkah-berikutnya)
19. [Panduan singkat pemakaian](#19-panduan-singkat-pemakaian)

---

## 1. Ringkasan

PortalLSP adalah platform multi-LSP (Lembaga Sertifikasi Profesi) untuk mengelola sertifikasi kompetensi dari awal sampai akhir:

- **Publik:** mencari skema, jadwal uji, direktori LSP, kelas pelatihan, membaca blog, dan memverifikasi sertifikat.
- **Asesi:** mendaftar mandiri, mengunggah dokumen, mengisi asesmen mandiri (APL.02), membayar, mengikuti uji, menerima sertifikat digital, dan mengikuti kelas.
- **LSP:** memverifikasi pendaftaran, mengatur jadwal dan asesor, memantau asesmen, menggelar pleno, menerbitkan sertifikat, serta mengelola master data, keuangan, CRM, mutu, laporan BNSP, etalase, blog, dan pengguna.
- **TUK:** melihat pemohon dan jadwal, mengelola sarana-prasarana, membuka group chat asesmen, dan melihat alumni.
- **Asesor:** melihat kalender gabungan lintas LSP, meninjau pra-asesmen, mengisi hasil asesmen per unit, mengikuti pleno, membuka riwayat/logbook, dan melihat honor.
- **Admin Platform:** mengelola LSP klien, paket, pustaka SKKNI, kurasi listing, blog platform (termasuk moderasi artikel LSP), tiket support, dan audit. Admin Platform juga bisa membuka **semua** menu LSP untuk semua LSP.

Pendukung lintas fitur: notifikasi (aplikasi, email, WhatsApp) dan asisten AI.

---

## 2. Arsitektur

| Lapisan | Teknologi |
|---|---|
| Backend | PHP 8.3 tanpa framework |
| Database | SQLite (produksi). MySQL juga didukung lewat `ddl()` di migrasi. |
| Frontend | Aplikasi satu halaman (SPA) dengan JavaScript murni (tanpa framework/bundler) |
| Kebijakan skrip | CSP ketat `script-src 'self'`; tidak ada skrip inline atau CDN |
| Email | `mail()` PHP lewat antrean (`notification_outbox`) |
| WhatsApp | Driver `fonnte` atau `meta` (WhatsApp Cloud API) |
| AI | Claude Messages API, dipanggil dari server |

### Struktur folder

```
app/
  config.php               # TIDAK di-commit; salin dari config.example.php
  config.example.php       # contoh konfigurasi tanpa rahasia
  public/                  # docroot web
    index.html             # kerangka SPA
    app.js                 # inti SPA: router, login, menu, halaman dasar
    fitur.js               # semua halaman fitur, termasuk CMS & blog publik (dimuat app.js sebelum render pertama)
    fitur.css              # gaya tambahan (panel AI, notifikasi, list-detail, blog)
    api.php                # satu pintu masuk API: api.php?r=<rute>
    .htaccess              # header keamanan, HTTPS
  src/                     # di luar docroot
    bootstrap.php          # memuat semua modul
    db.php                 # koneksi PDO + helper q()
    http.php               # respons JSON, CSRF, header, rate limit
    auth.php               # login, sesi, ganti konteks, password
    rbac.php               # peran, hak akses, require_perm()
    asesi.php              # pendaftaran mandiri & verifikasi email
    routes.php             # tabel rute + endpoint dasar (listing, pengguna, LSP)
    notify.php             # notifikasi, outbox, driver WhatsApp
    ai.php                 # proxy asisten AI
    domain.php             # proses sertifikasi (permohonan, dokumen, asesor, pleno, chat)
    lsp_ops.php            # operasi staf LSP, TUK, platform, dan API publik
    blog.php               # CMS blog: artikel, moderasi, sampul, API blog publik (migrasi 7)
    migrations.php         # migrasi berversi + seed demo dasar
    schema_domain.php      # tabel domain sertifikasi + seed data demo
  storage/                 # di luar docroot: database, sesi, unggahan, log, cadangan
  tools/
    smoke.php              # uji asap (CLI)
    notify-worker.php      # pengirim antrean email/WhatsApp (cron)
  tests/                   # uji API (Node) + uji browser (Playwright)
```

### Cara kerja frontend

- `app.js` memuat `fitur.js` saat boot. `fitur.js` mengganti beberapa fungsi `app.js` (mis. dashboard per peran, pemuat data halaman) dan menambah halaman baru.
- Formulir memakai atribut deklaratif. `data-api` mengirim formulir ke rute API, `data-act` menjalankan aksi tombol, dan `data-tab`, `data-sel`, `data-edit` mengatur tab, pilihan baris, dan mode sunting. Hasilnya, satu penangan umum melayani semua menu.
- Data per halaman dimuat lewat peta `PAGE_LOAD` lalu disimpan di `S.d[halaman]`.
- Fungsi `api()` otomatis memperbarui token CSRF dan mengulang satu kali bila server membalas 419.

### Tampilan responsif (HP & tablet)

| Lebar layar | Perilaku |
|---|---|
| > 980 px (laptop/desktop) | Sidebar menu tetap di kiri |
| ≤ 980 px (tablet, HP) | Sidebar menjadi **laci menu** (tombol ☰ di kiri atas; tutup dengan ✕, ketuk area gelap, atau tombol Esc). Header aplikasi satu baris. |
| ≤ 640 px (HP) | Nama pengguna disembunyikan dari header (avatar tetap ada); input memakai huruf 16 px agar iOS tidak memperbesar layar; ruang bawah untuk tombol asisten AI |
| ≤ 420 px (HP kecil) | Header publik dan kartu KPI dipadatkan |

- **Tabel adaptif.** Tabel yang tidak muat di wadahnya otomatis tampil sebagai **kartu**: setiap nilai diberi label dari judul kolomnya. Ini berlaku di HP, tablet, dan laptop kecil dengan sidebar. Tabel yang muat tetap berupa tabel. Pengecekan diulang saat ukuran layar berubah (mis. tablet diputar).
- Matriks peran & hak akses tetap berupa tabel yang bisa digeser ke samping (kelas `no-stack`).
- Grid 3 kolom menjadi 2 kolom di tablet dan 1 kolom di HP.
- Di perangkat sentuh, tombol kecil minimal setinggi 40 px.

### Cara kerja backend

- Semua permintaan masuk ke `public/api.php?r=<rute>`. `dispatch()` di `routes.php` memetakan rute ke fungsi `r_*`.
- Setiap endpoint memanggil `require_perm('<hak>')` atau `require_auth()`. Konteks LSP diambil dari keanggotaan aktif di sesi, bukan dari input klien.
- Migrasi berjalan otomatis (dengan kunci file) saat permintaan pertama setelah deploy. Versi tercatat di `schema_migrations`.

---

## 3. Peran dan hak akses

### Peran

| Kode | Nama | Cakupan | Catatan |
|---|---|---|---|
| `platform_admin` | Admin Platform | Platform | Memegang **semua** hak akses untuk **semua** LSP |
| `admin_lsp` | Admin LSP | Satu LSP | Semua data LSP-nya, termasuk semua TUK di LSP itu |
| `manajer_mutu` | Manajer Mutu | Satu LSP | Mutu, pleno, alumni, laporan |
| `keuangan` | Keuangan LSP | Satu LSP | Keuangan |
| `marketing` | Marketing LSP | Satu LSP | Etalase, CRM |
| `admin_tuk` | Admin TUK | Satu TUK | Hanya TUK miliknya |
| `asesor` | Asesor | Pribadi | Boleh aktif di banyak LSP |
| `asesi` | Asesi | Pribadi | Boleh aktif di banyak LSP |

Peran internal LSP (`admin_lsp`, `manajer_mutu`, `keuangan`, `marketing`, `admin_tuk`) hanya boleh punya **satu** keanggotaan. Asesor dan asesi boleh terdaftar di banyak LSP dan berpindah konteks lewat pemilih LSP di aplikasi.

### Hak akses (39)

| Kelompok | Kode hak akses |
|---|---|
| Platform | `platform.dashboard`, `listing.review`, `lsp.manage`, `rbac.view` |
| LSP | `lsp.dashboard`, `registration.verify`, `schedule.manage`, `assessment.monitor`, `decision.manage`, `master.manage`, `alumni.view`, `listing.manage`, `quality.manage`, `finance.manage`, `crm.manage`, `report.bnsp`, `settings.manage`, `user.manage` |
| TUK | `tuk.dashboard`, `tuk.applicants`, `tuk.schedule`, `tuk.facility`, `tuk.chat`, `tuk.alumni` |
| Asesor | `asesor.dashboard`, `preassessment.review`, `assessment.conduct`, `pleno.participate`, `asesor.history`, `asesor.honor` |
| Asesi | `asesi.dashboard`, `application.own`, `payment.own`, `certificate.own`, `class.own`, `profile.own` |
| Umum | `notif.log`, `ai.use`, `blog.manage` |

### Matriks peran → hak akses

| Peran | Hak akses |
|---|---|
| Admin Platform | Semua (39) |
| Admin LSP | Semua hak LSP, `rbac.view`, `notif.log`, `ai.use`, `blog.manage`, dan semua hak TUK |
| Manajer Mutu | `lsp.dashboard`, `quality.manage`, `alumni.view`, `report.bnsp`, `decision.manage`, `ai.use` |
| Keuangan | `lsp.dashboard`, `finance.manage`, `ai.use` |
| Marketing | `lsp.dashboard`, `listing.manage`, `crm.manage`, `blog.manage`, `ai.use` |
| Admin TUK | Semua hak TUK, `ai.use` |
| Asesor | Semua hak asesor, `ai.use` |
| Asesi | Semua hak asesi, `ai.use` |

Hak akses disimpan di tabel `role_permissions` dan diperiksa di server pada setiap permintaan. Menu di frontend hanya menyembunyikan tautan; penegakan akses selalu terjadi di server.

---

## 4. Isolasi data antar-LSP

| Peran | Data yang terlihat |
|---|---|
| Admin Platform | Semua LSP. Filter opsional `?lsp=ID` (pemilih "Semua LSP / LSP tertentu" di aplikasi). |
| Admin LSP dan staf LSP | Hanya LSP tempat ia menjadi anggota. Parameter `?lsp=` diabaikan. Data LSP lain dibalas 404. |
| Admin TUK | Hanya TUK miliknya (`tuk_where`): pemohon, jadwal, sarpras, chat, alumni |
| Asesor | Hanya jadwal dan berkas yang ditugaskan kepadanya, di LSP tempat ia aktif (`asesor_lsps()`) |
| Asesi | Hanya permohonan, tagihan, sertifikat, dokumen, dan kelas miliknya sendiri (`uid()`) |

Fungsi kunci di server:

- `scope_lsp($m)` mengembalikan `null` (semua LSP) untuk Admin Platform tanpa filter. Untuk peran lain, nilainya selalu `lsp_id` dari sesi.
- `target_lsp()` menentukan LSP tujuan saat Admin Platform membuat data (mis. listing, pengguna) atas nama LSP tertentu.

Dokumen asesi hanya bisa dibuka oleh:

1. pemiliknya;
2. staf LSP tempat asesi mendaftar;
3. asesor yang ditugaskan pada berkasnya;
4. Admin Platform.

Setiap akses oleh selain pemilik tercatat di log audit.

---

## 5. Alur sertifikasi

### Langkah utama

1. **Asesi** memilih skema di portal publik → masuk/daftar → wizard pendaftaran:
   1. pilih jadwal;
   2. isi data diri;
   3. isi APL.02 (asesmen mandiri per unit kompetensi);
   4. unggah KTP, ijazah, dan pas foto;
   5. setujui berbagi data ke LSP;
   6. ajukan.
2. **Admin LSP** memverifikasi berkas dengan salah satu keputusan:
   - **terima:** tagihan dibuat otomatis;
   - **minta perbaikan:** dengan catatan;
   - **tolak.**
3. **Asesi** membayar tagihan. Pembayaran saat ini masih simulasi. Keuangan LSP juga bisa mengonfirmasi pelunasan manual.
4. **Asesor** meninjau pra-asesmen dengan rekomendasi *lanjut* atau *tidak lanjut*.
5. **Asesor** mengisi hasil asesmen per unit (K/BK). Pengisian hanya bisa dilakukan **pada atau sesudah tanggal uji**.
6. **Pleno** (Admin LSP / Manajer Mutu) memutuskan *Kompeten* atau *Belum Kompeten*. **Asesor yang menguji tidak boleh memutus pleno berkasnya sendiri.**
7. Bila kompeten, **sertifikat terbit otomatis** dengan kode verifikasi 10 karakter. Asesi bisa mencetak atau mengunduhnya, dan publik bisa memverifikasinya.

### Mesin status permohonan

```
diajukan ──► perbaikan ──(kirim ulang)──► diajukan
   │
   ├──► ditolak
   │
   └──► menunggu_bayar ──(lunas)──► pra_asesmen ──► siap_uji ──(asesmen)──► menunggu_pleno ──► kompeten (sertifikat terbit)
                                          │                                            └──► belum_kompeten
                                          └──► tidak_lanjut

(asesi dapat membatalkan sebelum uji → dibatalkan)
```

| Status | Label |
|---|---|
| `diajukan` | Menunggu verifikasi |
| `perbaikan` | Perlu perbaikan |
| `ditolak` | Ditolak |
| `menunggu_bayar` | Menunggu pembayaran |
| `pra_asesmen` | Pra-asesmen |
| `tidak_lanjut` | Belum dapat dilanjutkan |
| `siap_uji` | Siap uji |
| `menunggu_pleno` | Menunggu pleno |
| `kompeten` | Kompeten |
| `belum_kompeten` | Belum kompeten |
| `dibatalkan` | Dibatalkan |

Aturan yang ditegakkan server:

- Perpindahan status memakai `set_status()` dengan `UPDATE … WHERE status = <status asal>`, sehingga dua klik bersamaan tidak bisa memproses berkas dua kali.
- Satu asesi tidak bisa punya dua permohonan aktif untuk skema yang sama.
- Kuota jadwal dicek saat mendaftar.
- Penugasan asesor dicek bentrok jadwal.
- Dokumen wajib: KTP, ijazah, pas foto (PDF/JPG/PNG, maks. 2 MB). Dokumen disimpan sekali dan dipakai ulang untuk pendaftaran berikutnya.

---

## 6. Menu dan fitur per peran

### Asesi

| Menu | Fungsi |
|---|---|
| Beranda | Ringkasan permohonan aktif, langkah berikutnya, tagihan, sertifikat |
| Daftar Skema Baru | Katalog skema dari semua LSP lalu wizard pendaftaran |
| Jadwal Saya | Daftar permohonan dan statusnya, kirim ulang perbaikan, batalkan |
| Pembayaran | Tagihan dan pembayaran (simulasi) |
| Dompet Sertifikat | Sertifikat milik sendiri: cetak/unduh, tautan verifikasi |
| Kelas Saya | Kelas pelatihan (LMS): daftar, catat progres |
| Profil & Dokumen | Data diri, unggah dan lihat dokumen |

### Asesor

| Menu | Fungsi |
|---|---|
| Beranda | Tugas hari ini, pra-asesmen tertunda, berkas menunggu pleno |
| Kalender Gabungan | Semua jadwal penugasan dari semua LSP tempat ia aktif |
| Tinjau Pra-Asesmen | Melihat APL.02 dan dokumen asesi, lalu memberi rekomendasi lanjut/tidak lanjut |
| Asesmen (MUK/FR) | Mengisi hasil per unit (K/BK) dan catatan, hanya pada/sesudah tanggal uji |
| Pleno | Ikut memutus pleno (kecuali berkas yang ia uji sendiri) |
| Riwayat & Logbook | Riwayat asesmen, unduh logbook CSV |
| Honor | Rekap honor per LSP |

### Admin LSP (dan staf sesuai hak)

| Kelompok | Menu | Fungsi |
|---|---|---|
| Operasional | Dashboard | KPI pendaftaran, jadwal, kelulusan, pendapatan |
| | Pendaftaran | Verifikasi berkas: terima / perbaikan / tolak (tagihan otomatis) |
| | Jadwal & Penugasan | Buat jadwal, tetapkan asesor (cek bentrok), kuota |
| | Asesmen | Memantau progres asesmen tiap jadwal |
| | Pleno & Sertifikat | Memutus pleno dan menerbitkan sertifikat |
| Data | Skema, Asesor, TUK | Master skema dan unit kompetensi (format `KODE \| Judul` per baris), asesor, TUK |
| | Database Alumni | Pemegang sertifikat LSP |
| Manajemen | Etalase & Pelatihan | Listing skema/kelas ke portal publik (wajib disetujui Admin Platform) |
| | Blog (CMS) | Menulis, menjadwalkan, dan mengelola artikel blog LSP (lihat bagian 8) |
| | Mutu (Pedoman 201) | Audit internal, kaji ulang manajemen, tindakan perbaikan |
| | Keuangan | Daftar tagihan, konfirmasi pelunasan manual |
| | CRM | Kanban prospek (baru → dihubungi → proposal → menang/kalah) |
| | Laporan BNSP | Rekap per skema/periode, unduh CSV (dengan pelindung formula injection) |
| | Pengguna & Hak Akses | Tambah staf LSP, aktif/nonaktifkan, matriks peran |
| | Log Notifikasi | Riwayat pengiriman email/WA milik LSP (penerima disamarkan) |
| | Profil LSP & Pengaturan | Profil, kontak, lisensi |
| | Tiket Support | Membuat dan memantau tiket ke tim platform |
| TUK | Dashboard TUK, Pemohon, Jadwal TUK, Sarana & Prasarana, Group Chat, Alumni TUK | Semua TUK milik LSP ini |

Staf non-admin hanya melihat menu yang sesuai haknya. Contoh: Keuangan hanya melihat Dashboard dan Keuangan, sedangkan Marketing melihat Dashboard, Etalase, Blog (CMS), dan CRM.

### Admin TUK

| Menu | Fungsi |
|---|---|
| Dashboard TUK | Jadwal mendatang, jumlah pemohon, status verifikasi TUK |
| Pemohon | Asesi yang terjadwal di TUK ini |
| Jadwal | Jadwal uji di TUK ini |
| Sarana & Prasarana | Inventaris alat dan kondisi |
| Group Chat | Ruang chat per jadwal (asesor, asesi, admin) |
| Alumni TUK | Pemegang sertifikat yang diuji di TUK ini |

### Admin Platform

| Menu | Fungsi |
|---|---|
| Ringkasan Platform | KPI semua LSP |
| Persetujuan Listing | Kurasi listing (setujui / minta revisi / tolak) dengan checklist |
| Blog (CMS) | Artikel platform dan semua LSP; menulis atas nama platform/LSP mana pun; menurunkan dan memulihkan artikel |
| LSP Klien | Onboarding LSP baru (sekaligus membuat Admin LSP), aktif/nonaktif |
| Paket & Tagihan | Paket langganan dan kuota per LSP |
| Pustaka SKKNI | Daftar unit kompetensi acuan |
| Tiket Support | Membalas tiket dari LSP |
| Log Akses Support | Log audit (login, akses ditolak, perubahan data, akses dokumen) |
| Peran & Hak Akses | Matriks RBAC |
| Log Notifikasi | Semua pengiriman notifikasi |
| Semua menu LSP dan TUK | Menu Admin LSP untuk semua LSP, dengan filter LSP opsional |

---

## 7. Portal publik

Bisa diakses tanpa login:

- **Beranda dan katalog skema:** filter bidang, kota, harga. Data diambil dari listing yang sudah tayang.
- **Detail skema:** unit kompetensi, persyaratan, jadwal terdekat, tombol daftar. Pengunjung yang belum login diarahkan ke login/daftar, lalu kembali ke wizard.
- **Jadwal uji:** jadwal mendatang semua LSP beserta sisa kuota.
- **Direktori LSP:** profil, skema, TUK.
- **Pelatihan:** kelas yang tayang.
- **Blog:** artikel dari platform dan LSP, dengan pencarian, filter kategori, dan tautan langsung `/?artikel=SLUG` (lihat bagian 8). Beranda menampilkan 3 artikel terbaru.
- **Verifikasi sertifikat:** buka `/?cek=KODE` atau isi formulir verifikasi. Dibatasi 30 permintaan per 10 menit per IP.
- **Pendaftaran asesi mandiri** dengan verifikasi email (`/?verifikasi=TOKEN`).

---

## 8. Blog (CMS)

CMS blog dipakai untuk menerbitkan artikel: tips persiapan uji, regulasi BNSP, pengumuman jadwal, dan kisah alumni. Artikel tampil di menu **Blog** portal publik dan di beranda.

### Siapa yang bisa menulis

| Peran | Cakupan |
|---|---|
| Admin Platform | Semua artikel. Bisa menulis atas nama **PortalLSP** (platform) atau atas nama LSP mana pun, serta menurunkan/memulihkan artikel. |
| Admin LSP, Marketing LSP | Hanya artikel LSP-nya. Artikel LSP lain dan artikel platform dibalas 404. |
| Peran lain | Tidak punya akses (403) |

Hak aksesnya adalah `blog.manage`. Admin LSP bisa memberi peran Marketing kepada staf yang mengelola konten.

### Fitur editor

- **Judul, kategori, tag** (maks. 8, dipisah koma), **ringkasan** (maks. 300 karakter), dan **slug URL**. Slug dibuat otomatis dari judul dan selalu unik; bisa diubah manual.
- **Kategori:** Berita, Tips Sertifikasi, Regulasi, Pengumuman, Kisah Alumni, Pelatihan.
- **Isi artikel** ditulis dengan Markdown sederhana. Toolbar menyediakan tombol tebal, miring, subjudul, daftar, kutipan, dan tautan, serta tombol **Pratinjau**.

  | Tulis | Hasil |
  |---|---|
  | `## Subjudul` / `### Sub-subjudul` | Subjudul |
  | `**tebal**`, `_miring_`, `` `kode` `` | Format teks |
  | `- poin` / `1. poin` | Daftar berpoin / bernomor |
  | `> kutipan` | Kutipan |
  | `[teks](https://alamat)` | Tautan (dibuka di tab baru, `rel="nofollow"`) |
  | `---` | Garis pemisah |

- **Status:** *Draf* atau *Terbitkan*. Bila waktu terbit diisi tanggal mendatang, artikel berstatus **Terjadwal** dan otomatis tayang pada waktunya (tanpa cron).
- **Gambar sampul:** JPG/PNG maks. 2 MB, rasio 16:9 disarankan. Tanpa sampul, kartu artikel memakai warna kategori.
- **Statistik:** jumlah pembaca per artikel (dihitung sekali per sesi pengunjung) dan total pembaca.

### Moderasi

- Artikel LSP langsung tayang tanpa antrean persetujuan, agar pengumuman jadwal tidak tertunda.
- Admin Platform dapat **menurunkan** artikel yang melanggar ketentuan (mis. klaim "pasti lulus"). Alasan wajib diisi dan dikirim sebagai notifikasi ke staf blog LSP tersebut.
- Artikel yang diturunkan hanya bisa ditayangkan kembali oleh Admin Platform (**Pulihkan**). LSP tetap bisa memperbaiki isinya.

### Halaman publik

- `Blog`: artikel utama terbaru, kartu artikel, pencarian (judul, ringkasan, tag), filter kategori, dan halaman (12 artikel per halaman).
- Halaman artikel: penerbit, penulis, tanggal, perkiraan waktu baca, jumlah pembaca, isi, tag (klik untuk mencari), tombol bagikan (salin tautan, WhatsApp), ajakan mencari skema, dan 3 artikel terkait.
- Tautan langsung `https://lsp.semestateknologiutama.com/?artikel=SLUG`. Pengunjung yang sudah login tetap diarahkan ke artikel, bukan ke dashboard.

### Keamanan

- Isi artikel disimpan sebagai teks. Semua teks di-escape di browser **sebelum** format Markdown diterapkan, sehingga HTML/skrip yang diketik penulis tampil sebagai teks biasa dan tidak dijalankan.
- Tautan hanya boleh `http(s)://` atau alamat relatif. `javascript:` dan sejenisnya tidak pernah menjadi tautan.
- Sampul dicek dari isi berkas (magic bytes), disimpan di luar docroot dengan nama acak, dan disajikan dengan `nosniff` + CSP `sandbox`.
  - Sampul artikel yang sudah terbit bersifat publik (cache 1 hari).
  - Sampul draf hanya bisa dilihat pengelola artikel itu.
- Publik hanya melihat artikel berstatus terbit yang waktu terbitnya sudah lewat, dari LSP yang aktif.
- Semua tindakan (buat, ubah, hapus, sampul, turunkan, pulihkan) tercatat di log audit.

---

## 9. Notifikasi (aplikasi, email, WhatsApp)

Setiap peristiwa penting membuat notifikasi di kotak masuk aplikasi (ikon lonceng), lalu diantrekan ke email/WhatsApp sesuai pengaturan pengguna (menu **Notifikasi** di profil).

| Peristiwa | Penerima |
|---|---|
| Pendaftaran asesi baru / perbaikan dikirim | Staf dengan `registration.verify` di LSP itu |
| Pendaftaran diterima / perlu perbaikan / ditolak | Asesi |
| Pembayaran diterima | Asesi |
| Penugasan asesmen baru | Asesor |
| Pra-asesmen baru | Asesor yang ditugaskan |
| Hasil pra-asesmen | Asesi |
| Berkas menunggu pleno | Staf dengan `decision.manage` |
| Hasil pleno (kompeten / belum kompeten) | Asesi |
| Listing diajukan / diputus | Admin Platform / staf etalase LSP |
| Akun dibuat, akses aktif/nonaktif, LSP baru aktif | Pengguna terkait |
| Password diganti | Selalu dikirim ke email (tidak bisa dimatikan) |
| Tiket support baru / dibalas | Admin Platform / pembuat tiket |
| Artikel blog diturunkan / dipulihkan | Staf LSP dengan `blog.manage` |

Aturan pengiriman:

- WhatsApp hanya dikirim bila pengguna mengisi nomor **dan** mencentang persetujuan (opt-in). Waktu persetujuan dicatat.
- Password tidak pernah dikirim lewat email/WA.
- Pengiriman dilakukan oleh `tools/notify-worker.php` (cron), dengan percobaan ulang hingga 5 kali. Riwayat lebih dari 90 hari dibersihkan otomatis.
- Log notifikasi: Admin LSP hanya melihat milik LSP-nya, Admin Platform melihat semua. Alamat penerima disamarkan.

---

## 10. Asisten AI

- Tombol asisten tersedia di semua peran yang punya `ai.use`.
- Panggilan ke API Claude dilakukan dari server. Kunci API hanya ada di `config.php`. Bila kunci kosong, fitur nonaktif.
- Konteks yang dikirim ke model hanya ringkasan data milik pengguna/LSP aktifnya. NIK, email, dan nomor HP disamarkan sebelum dikirim.
- Model tidak diberi akses tool, sehingga tidak bisa mengubah data atau membaca data di luar konteks.
- Isi percakapan tidak disimpan. Yang dicatat hanya jumlah token (tabel `ai_usage`).
- Batas pemakaian:
  - 20 pesan per jam per pengguna;
  - 100 pesan per hari per pengguna;
  - batas total harian platform (`ai_daily_limit`).
- Aturan integritas: asisten menolak membuatkan jawaban uji, isian APL.02, atau bukti portofolio.

---

## 11. Keamanan

| Area | Penerapan |
|---|---|
| Password | `password_hash` (bcrypt) dengan rehash otomatis. Minimal 10 karakter, wajib huruf dan angka. Akun yang dibuat admin wajib ganti password saat login pertama. |
| Sesi | Cookie `HttpOnly`, `Secure`, `SameSite=Lax`. ID sesi diganti saat login, ganti konteks, dan ganti password. Idle timeout 30 menit, maksimal 8 jam. File sesi di luar docroot. |
| CSRF | Token per sesi di header `X-CSRF-Token` + cek `Origin` untuk setiap POST |
| Brute force | Kunci setelah 5 gagal per email atau 20 per IP dalam 15 menit. Pesan error sama untuk email terdaftar maupun tidak. |
| RBAC | Dari tabel `roles` / `permissions` / `role_permissions`, diperiksa di setiap endpoint |
| Isolasi LSP | `lsp_id` selalu dari sesi; data LSP lain dibalas 404 (lihat bagian 4) |
| Akun nonaktif | Akun dan keanggotaan dibaca ulang dari DB setiap permintaan, sehingga akses langsung hilang |
| Data pribadi | NIK asesi dienkripsi AES-256-GCM dengan kunci `storage/app.key` (kunci ini wajib dicadangkan; tanpa kunci, NIK tidak bisa dibaca) |
| Unggahan | Disimpan di `storage/uploads` (di luar docroot). Jenis berkas dicek dari isi (magic bytes: PDF/PNG/JPEG), maks. 2 MB, nama berkas acak. Disajikan dengan header CSP `sandbox` dan `nosniff`. |
| Ekspor CSV | Sel yang diawali `=`, `+`, `-`, `@` diberi awalan `'` (mencegah formula injection) |
| Frontend | CSP `script-src 'self'`; semua teks dari server di-escape |
| Transport | HTTPS + HSTS, header keamanan di `.htaccess` |
| Audit | Login/logout, akses ditolak, perubahan listing, kurasi, manajemen pengguna, akses dokumen, keputusan pleno |
| Rate limit publik | Verifikasi sertifikat 30 per 10 menit per IP, asisten AI per pengguna |

---

## 12. Model data

### Tabel inti (akun dan akses)

| Tabel | Isi |
|---|---|
| `users` | Akun (email, nama, hash password, status, wajib ganti password) |
| `memberships` | Keanggotaan pengguna ke LSP/TUK dengan peran tertentu |
| `roles`, `permissions`, `role_permissions` | RBAC |
| `lsp`, `tuk` | LSP klien dan TUK |
| `asesi_profiles` | Data diri asesi (NIK, alamat, pendidikan, dsb.) |
| `email_verifications` | Token verifikasi email pendaftaran mandiri |
| `login_attempts` | Catatan gagal login (penguncian) |
| `audit_logs` | Log audit |
| `schema_migrations` | Versi migrasi |

### Tabel sertifikasi

| Tabel | Isi |
|---|---|
| `skema`, `skema_units` | Skema sertifikasi dan unit kompetensinya |
| `skkni` | Pustaka unit SKKNI acuan |
| `jadwal` | Jadwal uji (skema, TUK, tanggal, kuota, asesor) |
| `permohonan` | Pendaftaran asesi: status, APL.02, rekomendasi pra-asesmen, hasil per unit, keputusan pleno |
| `dokumen` | Dokumen asesi (KTP, ijazah, foto, CV) |
| `tagihan` | Tagihan per permohonan (nomor invoice, status bayar) |
| `sertifikat` | Sertifikat terbit (nomor, kode verifikasi, masa berlaku) |
| `listings` | Etalase skema/pelatihan di portal publik (dengan status kurasi) |
| `kelas_peserta` | Peserta kelas pelatihan dan progresnya |

### Tabel operasional

| Tabel | Isi |
|---|---|
| `crm_leads` | Prospek CRM |
| `mutu` | Kegiatan mutu (audit internal, kaji ulang, tindakan perbaikan) |
| `sarpras` | Sarana-prasarana TUK |
| `chat_pesan` | Pesan group chat per jadwal |
| `tiket`, `tiket_balasan` | Tiket support |
| `notifications`, `notification_settings`, `notification_outbox` | Notifikasi, preferensi, dan antrean kirim |
| `ai_usage` | Pemakaian token asisten AI |
| `blog_posts` | Artikel blog: penerbit (LSP/platform), slug, isi, kategori, tag, sampul, status, waktu terbit, catatan moderasi, jumlah pembaca |

---

## 13. Daftar endpoint API

Semua endpoint: `api.php?r=<rute>`.

- GET dipakai untuk membaca, POST untuk mengubah. POST wajib membawa header `X-CSRF-Token`.
- Respons berupa JSON `{ ok: true, ... }` atau `{ ok: false, error: "..." }` dengan kode HTTP yang sesuai (400/401/403/404/409/419/429).
- Admin Platform dapat menambahkan `&lsp=ID` untuk memfilter satu LSP.

### Autentikasi dan akun

| Rute | Fungsi |
|---|---|
| `auth/me` | Profil sesi, keanggotaan, hak akses, token CSRF |
| `auth/login`, `auth/logout` | Masuk / keluar |
| `auth/switch` | Ganti konteks (LSP / peran) |
| `auth/password` | Ganti password |
| `auth/register`, `auth/verify`, `auth/resend-verification` | Pendaftaran asesi mandiri dan verifikasi email |
| `profile` | Profil asesi |

### Listing, pengguna, platform

| Rute | Hak | Fungsi |
|---|---|---|
| `catalog` | publik | Katalog listing tayang |
| `listings`, `listings/submit`, `listings/withdraw` | `listing.manage` | Etalase LSP |
| `reviews`, `reviews/decide` | `listing.review` | Kurasi listing |
| `users`, `users/status` | `user.manage` | Pengguna LSP |
| `rbac` | `rbac.view` | Matriks peran |
| `lsps` | login | Daftar LSP (platform: semua) |
| `platform/dashboard` | `platform.dashboard` | Ringkasan platform |
| `platform/lsp`, `platform/lsp/status` | `lsp.manage` | LSP klien |
| `platform/paket` | `lsp.manage` | Paket dan kuota |
| `skkni` | `lsp.manage` | Pustaka SKKNI |
| `tiket`, `tiket/balas` | login | Tiket support |
| `audit` | `lsp.manage` | Log audit |

### Notifikasi dan AI

| Rute | Fungsi |
|---|---|
| `notifications`, `notifications/count`, `notifications/read` | Kotak masuk notifikasi |
| `notifications/settings` | Preferensi email/WA dan persetujuan WA |
| `notifications/test` | Kirim notifikasi uji ke diri sendiri |
| `notifications/log` | Log pengiriman (`notif.log`) |
| `ai/chat` | Asisten AI (`ai.use`) |

### Asesi

| Rute | Fungsi |
|---|---|
| `dokumen`, `dokumen/upload`, `dokumen/file` | Daftar, unggah, dan buka dokumen |
| `asesi/apply` | Ajukan pendaftaran |
| `asesi/permohonan` | Daftar permohonan sendiri |
| `asesi/resubmit` | Kirim ulang perbaikan |
| `asesi/cancel` | Batalkan |
| `asesi/tagihan`, `asesi/bayar` | Tagihan dan pembayaran (simulasi) |
| `asesi/sertifikat` | Sertifikat sendiri |
| `sertifikat/cetak` | Halaman cetak sertifikat (HTML) |
| `asesi/kelas`, `asesi/kelas/daftar`, `asesi/kelas/progres` | Kelas pelatihan |

### Asesor dan pleno

| Rute | Fungsi |
|---|---|
| `asesor/jadwal` | Kalender gabungan |
| `asesor/pra`, `asesor/pra/putus` | Pra-asesmen |
| `asesor/asesmen`, `asesor/asesmen/simpan` | Hasil asesmen per unit |
| `asesor/riwayat` | Riwayat dan logbook (CSV) |
| `asesor/honor` | Honor |
| `pleno`, `pleno/putus` | Pleno dan penerbitan sertifikat |
| `chat`, `chat/kirim`, `chat/ruang` | Group chat per jadwal |

### Staf LSP

| Rute | Hak |
|---|---|
| `lsp/dashboard` | `lsp.dashboard` |
| `lsp/pendaftaran`, `lsp/pendaftaran/putus` | `registration.verify` |
| `lsp/jadwal`, `lsp/jadwal/asesor` | `schedule.manage` |
| `lsp/asesmen` | `assessment.monitor` |
| `lsp/hasil` | `decision.manage` |
| `lsp/skema`, `lsp/asesor`, `lsp/tuk`, `lsp/opsi` | `master.manage` |
| `lsp/alumni` | `alumni.view` |
| `lsp/keuangan`, `lsp/keuangan/lunas` | `finance.manage` |
| `lsp/laporan` (+ `&format=csv`) | `report.bnsp` |
| `lsp/crm`, `lsp/crm/tahap` | `crm.manage` |
| `lsp/mutu` | `quality.manage` |
| `lsp/pengaturan` | `settings.manage` |

### TUK

| Rute | Hak |
|---|---|
| `tuk/dashboard` | `tuk.dashboard` |
| `tuk/pemohon` | `tuk.applicants` |
| `tuk/jadwal` | `tuk.schedule` |
| `tuk/sarpras` | `tuk.facility` |
| `tuk/alumni` | `tuk.alumni` |

### Blog (CMS)

| Rute | Hak | Fungsi |
|---|---|---|
| `blog` (GET) | `blog.manage` | Daftar artikel dalam cakupan (platform: semua, `&lsp=ID` untuk satu LSP) |
| `blog` (POST) | `blog.manage` | Buat (tanpa `id`) atau ubah artikel |
| `blog/hapus` | `blog.manage` | Hapus artikel beserta sampulnya |
| `blog/cover` (multipart) | `blog.manage` | Unggah sampul (`id`, `file`) atau hapus (`hapus=1`) |
| `blog/moderasi` | `lsp.manage` | `aksi=turunkan` (dengan `catatan`) atau `aksi=pulihkan` |

### Publik (tanpa login)

| Rute | Fungsi |
|---|---|
| `pub/catalog` | Katalog skema dan kelas |
| `pub/skema` | Detail skema |
| `pub/jadwal` | Jadwal mendatang |
| `pub/lsp` | Direktori LSP |
| `pub/verify` | Verifikasi sertifikat (dibatasi per IP) |
| `pub/blog` | Daftar artikel terbit (`q`, `kategori`, `lsp`, `page`) + jumlah per kategori |
| `pub/blog/post` | Satu artikel (`slug`) + artikel terkait; menambah hitungan pembaca |
| `pub/blog/cover` | Gambar sampul (`id`) |

---

## 14. Konfigurasi

File `app/config.php` (di luar docroot, **tidak di-commit**) berisi array berikut. Contoh lengkapnya ada di `config.example.php`.

| Kunci | Fungsi |
|---|---|
| `db.dsn`, `db.user`, `db.pass` | Koneksi database (`sqlite:/path/portal.sqlite` atau `mysql:...`) |
| `session_name` | Nama cookie sesi |
| `idle_timeout`, `absolute_timeout` | Batas sesi (detik) |
| `cookie_secure` | `true` di produksi (HTTPS) |
| `app_url` | URL aplikasi untuk tautan di email |
| `mail_from` | Alamat pengirim email |
| `ai_api_key` | Kunci API Claude. Kosong = asisten AI nonaktif. |
| `ai_model` | Model AI (default `claude-opus-5-5`; bisa diganti model yang lebih hemat) |
| `ai_daily_limit` | Batas pesan AI per hari untuk seluruh platform |
| `wa_driver` | `''` (nonaktif), `fonnte`, atau `meta` |
| `wa_token` | Token Fonnte / token WhatsApp Cloud API |
| `wa_phone_number_id`, `wa_meta_template` | Khusus driver `meta` (template wajib disetujui Meta; `{{1}}` judul, `{{2}}` isi) |
| `seed_password_hashes` | Hash password akun demo. Kosongkan di produksi nyata. |

> **Penting:**
> - Kunci API dan token WhatsApp diisi sendiri oleh pemilik server langsung di `config.php`, jangan dikirim lewat chat atau di-commit.
> - Cadangkan `storage/app.key` dan database `storage/*.sqlite` secara berkala.

---

## 15. Instalasi, deploy, dan cron

### Instalasi baru

1. Salin folder `app/` ke server, misalnya `/home/<akun>/lsp-app`.
2. Arahkan docroot subdomain ke `lsp-app/public`. Folder `src/`, `storage/`, `tools/`, dan `config.php` harus tetap di luar docroot.
3. Salin `config.example.php` menjadi `config.php`, lalu isi.
4. Pastikan `storage/` bisa ditulis PHP (izin 0700/0750).
5. Buka situs. Migrasi berjalan otomatis saat permintaan pertama.
6. Jalankan `php tools/smoke.php` untuk memeriksa: versi PHP, driver DB, dan jumlah data.

### Cron

| Jadwal | Perintah | Fungsi |
|---|---|---|
| Tiap 2–6 menit | `/opt/alt/php83/usr/bin/php /home/<akun>/lsp-app/tools/notify-worker.php >> .../storage/notify-worker.log 2>&1` | Mengirim antrean email/WhatsApp |

### Pembaruan versi (cara yang dipakai di produksi)

1. Buat paket patch PHP yang memverifikasi hash setiap berkas lama sebelum menulis berkas baru.
2. Kompres paket (gzip), lalu unggah ke `lsp-app/tools/`.
3. Jalankan sekali lewat cron sementara. Patch menulis cadangan berkas lama ke `storage/backup-<tanggal>`, lalu menjalankan `smoke.php`.
4. Baca keluaran, lalu hapus cron sementara.

Riwayat versi di produksi:

| Patch | Isi |
|---|---|
| v3 | Asisten AI + notifikasi email/WhatsApp |
| v4 | Admin Platform akses penuh semua menu dan semua LSP |
| v5 | Admin LSP akses penuh semua data LSP-nya (termasuk TUK), isolasi antar-LSP |
| v6 | Semua menu dan fitur berfungsi: proses sertifikasi lengkap, portal publik dengan data nyata, data demo. Dideploy 9 Okt 2026, smoke test lulus. |
| v7 | CMS blog (migrasi 7): menu Blog (CMS), halaman Blog publik, moderasi Admin Platform, 6 artikel contoh. |
| v8 | Responsif HP & tablet: laci menu, header ringkas, tabel adaptif menjadi kartu, grid tablet, target sentuh. |

### Rollback

Setiap patch menyimpan berkas lama di `storage/backup-<tanggal-jam>/`. Salin kembali berkas dari folder itu untuk mengembalikan versi sebelumnya.

Perubahan skema database tidak dibatalkan otomatis. Bila perlu, pulihkan dari cadangan database.

---

## 16. Pengujian

Uji lokal:

```bash
cd app
php tests/make-test-config.php      # config SQLite + akun demo khusus lokal
php -S 127.0.0.1:8099 -t public

node tests/api.test.mjs             # 49 uji: autentikasi, CSRF, RBAC, isolasi LSP
node tests/register.test.mjs        # 29 uji: pendaftaran asesi mandiri
node tests/notify-ai.test.mjs       # 47 uji: notifikasi & asisten AI (server tiruan port 8098)
node tests/platform.test.mjs        # 23 uji: akses penuh Admin Platform + isolasi Admin LSP
node tests/flow.test.mjs            # 108 uji: alur sertifikasi ujung-ke-ujung + isolasi
node tests/blog.test.mjs            # 83 uji: CMS blog (akses, isolasi, terjadwal, moderasi, sampul, API publik)

NODE_PATH=$(npm root -g) node tests/ui/alur-asesi.cjs  # uji browser: daftar asesi s.d. sertifikat terverifikasi
NODE_PATH=$(npm root -g) node tests/ui/semua-menu.cjs  # uji browser: buka semua menu untuk 7 peran
NODE_PATH=$(npm root -g) node tests/ui/blog.cjs        # uji browser: tulis, pratinjau, terbitkan, sampul, baca, moderasi
NODE_PATH=$(npm root -g) node tests/ui/responsif.cjs   # audit responsif: semua halaman × 7 peran × 4 ukuran layar
```

Catatan: jalankan tiap suite pada database baru (`php tests/make-test-config.php` lalu hapus `storage/test.sqlite`). Suite berbagi data (mis. NIK contoh) dan batas login per IP, sehingga menjalankan semuanya berturut-turut pada satu database bisa memicu kegagalan palsu. `tests/ui/alur-asesi.cjs` membuat jadwal "hari ini", jadi jalankan dengan `TZ=Asia/Jakarta`.

Hasil terakhir:

- Semua 339 uji API lulus pada database baru (termasuk 83 uji blog), plus 24 uji browser blog.
- Uji browser membuka semua menu di 7 peran tanpa error JavaScript dan tanpa halaman kosong/placeholder.
- Audit responsif: 548 tampilan (halaman publik + semua menu 7 peran, termasuk formulir dan panel detail) di lebar 360, 390, 768, dan 1024 px; tidak ada elemen yang keluar layar dan tidak ada scroll horizontal.

---

## 17. Data dan akun demo

Server produksi saat ini berisi data demo agar semua menu bisa dicoba.

**LSP:**

| Kode | Nama | Kota |
|---|---|---|
| TDN | LSP Teknologi Digital Nusantara | Jakarta |
| PBI | LSP Pariwisata Bahari Indonesia | Denpasar |
| KMD | LSP Konstruksi Mandiri | Surabaya |
| LMP | LSP Manajemen Profesional | Jakarta |

**Skema (7):**

| Skema | LSP |
|---|---|
| Junior Web Developer | TDN |
| Analis Data Junior | TDN |
| Digital Marketing | TDN |
| Barista | PBI |
| Housekeeping Supervisor | PBI |
| Teknisi Instalasi Listrik Bangunan | KMD |
| Pengelola Administrasi Perkantoran | LMP |

Setiap skema punya unit SKKNI dan jadwal di masa lalu dan mendatang.

**TUK:** Kuningan, Daring (SJJ) TDN, Hotel Sanur, SMK Negeri 5 Surabaya, Graha Manajemen.

**Akun demo (bisa login):**

| Email | Peran |
|---|---|
| `superadmin@demo.portallsp.id` | Admin Platform |
| `admin.tdn@demo.portallsp.id` | Admin LSP — TDN |
| `marketing.tdn@demo.portallsp.id` | Marketing — TDN |
| `keuangan.tdn@demo.portallsp.id` | Keuangan — TDN |
| `admin.pbi@demo.portallsp.id` | Admin LSP — PBI |
| `tuk.kuningan@demo.portallsp.id` | Admin TUK Kuningan |
| `asesor@demo.portallsp.id` | Asesor (TDN, KMD, LMP) |
| `asesi@demo.portallsp.id` | Asesi Rina (TDN, PBI) — punya permohonan di berbagai tahap dan satu sertifikat |

Password akun demo **tidak** dicantumkan di dokumen ini. Password ada di berkas kredensial terpisah yang sudah dikirim ke pemilik.

**Blog:** 6 artikel contoh. Dua dari platform, empat dari LSP: tiga TDN (satu terjadwal, satu draf) dan satu PBI.

Akun contoh tambahan (asesor Lina Marlina dan 6 asesi `@contoh.portallsp.id`) tidak punya password dan tidak bisa dipakai login. Akun-akun ini hanya mengisi data.

Contoh verifikasi sertifikat: buka `https://lsp.semestateknologiutama.com/?cek=TDN7K3P9QX`.

> Sebelum dipakai untuk LSP sungguhan, hapus/nonaktifkan akun demo dan kosongkan `seed_password_hashes`.

---

## 18. Batasan dan langkah berikutnya

| Hal | Kondisi saat ini | Usulan |
|---|---|---|
| Pembayaran | Simulasi; konfirmasi manual oleh Keuangan | Integrasi payment gateway (mis. Midtrans/Xendit) + webhook |
| Email | `mail()` server | SMTP/layanan transaksional dengan SPF/DKIM |
| WhatsApp | Siap, tetapi token belum diisi | Isi `wa_driver` + `wa_token` di `config.php` |
| Asisten AI | Siap, tetapi kunci belum diisi | Isi `ai_api_key` di `config.php` |
| Sertifikat | Halaman cetak HTML + kode verifikasi | PDF bertanda tangan digital, QR code |
| Formulir MUK | Hasil per unit (K/BK) + catatan | Formulir FR.IA lengkap per metode uji |
| Laporan BNSP | Rekap + CSV | Format sesuai template terbaru BNSP |
| LMS | Daftar kelas + progres | Materi, kuis, video |
| Database | SQLite | Pindah ke MySQL bila beban tinggi (sudah didukung) |
| Cadangan | Cadangan berkas saat patch | Cadangan DB terjadwal ke penyimpanan terpisah |

---

## 19. Panduan singkat pemakaian

### Asesi

1. Buka portal → pilih skema → **Daftar**.
2. Bila belum punya akun, daftar dengan email lalu klik tautan verifikasi di email.
3. Ikuti wizard: jadwal → data diri → APL.02 → unggah dokumen → persetujuan → **Ajukan**.
4. Pantau status di **Jadwal Saya**. Bila diminta perbaikan, perbaiki lalu kirim ulang.
5. Setelah diterima, bayar di **Pembayaran**.
6. Ikuti uji sesuai jadwal. Setelah pleno, sertifikat muncul di **Dompet Sertifikat**.

### Admin LSP

1. **Pendaftaran:** buka berkas → periksa dokumen dan APL.02 → Terima / Perbaikan / Tolak.
2. **Jadwal & Penugasan:** buat jadwal → tetapkan asesor.
3. **Asesmen:** pantau progres.
4. **Pleno & Sertifikat:** putuskan K/BK. Sertifikat terbit otomatis.
5. **Skema, Asesor, TUK:** kelola master data.
6. **Etalase:** tambah listing → ajukan ke Admin Platform.
7. **Blog (CMS):** Tulis artikel → isi judul, kategori, isi (pakai Pratinjau) → pilih *Terbitkan* (atau isi waktu terbit untuk menjadwalkan) → Simpan → unggah gambar sampul.

### Asesor

1. **Kalender Gabungan:** lihat tugas.
2. **Tinjau Pra-Asesmen:** periksa berkas → beri rekomendasi.
3. **Asesmen:** pada hari uji, isi K/BK per unit → simpan.
4. **Pleno:** ikut memutus berkas yang tidak Anda uji sendiri.

### Admin Platform

1. **LSP Klien:** onboarding LSP baru beserta Admin LSP-nya.
2. **Persetujuan Listing:** tinjau dan tayangkan listing.
3. **Blog (CMS):** tulis artikel atas nama platform atau LSP; tombol **Turunkan** (dengan alasan) untuk artikel bermasalah, **Pulihkan** setelah diperbaiki.
4. Pilih LSP di pemilih konteks untuk membuka data satu LSP, atau "Semua LSP" untuk melihat semuanya.
