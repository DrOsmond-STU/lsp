# Dokumentasi PortalLSP

Dokumen ini menjelaskan aplikasi PortalLSP secara menyeluruh: fungsi, peran pengguna, alur sertifikasi, menu tiap peran, keamanan, data, API, konfigurasi, deploy, dan pengujian.

- **Alamat produksi:** https://lsp.semestateknologiutama.com
- **Situs demo:** https://demo-lsp.semestateknologiutama.com (database MySQL terpisah, lihat bagian 15)
- **Kode sumber:** folder `app/` di repositori ini
- **Rancangan fitur awal:** [`docs/rancangan-fitur.md`](rancangan-fitur.md)
- **Versi terpasang:** v17 di produksi (formulir asesmen paperless sesuai peraturan BNSP); v16 di situs demo
- **Rancangan formulir paperless:** [`docs/rancangan-muk-digital.md`](rancangan-muk-digital.md)

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
    qr.php                 # encoder kode QR (tanpa pustaka luar)
    pdf.php                # penyusun PDF sederhana (font standar, vektor)
    sertifikat.php         # tata letak sertifikat PDF + lampiran unit
    tte.php                # tanda tangan digital: CA PortalLSP, sertifikat per LSP, PSrE (.p12), tanda tangan CMS
    lsp_ops.php            # operasi staf LSP, TUK, platform, dan API publik
    blog.php               # CMS blog: artikel, moderasi, sampul, API blog publik (migrasi 7)
    migrations.php         # migrasi berversi + seed demo dasar
    schema_domain.php      # tabel domain sertifikasi + seed data demo
    seed_lengkap.php       # data demo lengkap (migrasi 10): riwayat 11 bulan, antrean tiap tahap, notifikasi, tiket, CRM
  storage/                 # di luar docroot: database, sesi, unggahan, log, cadangan
  tools/
    smoke.php              # uji asap (CLI); unggah ke server hanya saat dibutuhkan
    notify-worker.php      # pengirim antrean email/WhatsApp (cron)
    fix-header.php         # perbaikan sekali jalan untuk app.js (riwayat; tidak perlu dijalankan lagi)
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
| `manajer_mutu` | Manajer Mutu | Satu LSP | Mutu, validasi asesmen (FR.VA), pemantauan berkas asesmen, alumni, laporan. **Tidak** memutus sertifikasi. |
| `komite_teknis` | Komite Teknis (Pengambil Keputusan) | Satu LSP | Memutus sertifikasi (pleno) dan banding. Tidak boleh asesor yang menguji berkas itu (PBNSP 201). |
| `keuangan` | Keuangan LSP | Satu LSP | Keuangan |
| `marketing` | Marketing LSP | Satu LSP | Etalase, CRM |
| `admin_tuk` | Admin TUK | Satu TUK | Hanya TUK miliknya |
| `asesor` | Asesor | Pribadi | Boleh aktif di banyak LSP |
| `asesi` | Asesi | Pribadi | Boleh aktif di banyak LSP |

Peran internal LSP (`admin_lsp`, `manajer_mutu`, `komite_teknis`, `keuangan`, `marketing`, `admin_tuk`) hanya boleh punya **satu** keanggotaan. Asesor dan asesi boleh terdaftar di banyak LSP dan berpindah konteks lewat pemilih LSP di aplikasi.

### Hak akses (42)

| Kelompok | Kode hak akses |
|---|---|
| Platform | `platform.dashboard`, `listing.review`, `lsp.manage`, `rbac.view` |
| LSP | `lsp.dashboard`, `registration.verify`, `schedule.manage`, `assessment.monitor`, `assessment.review`, `muk.manage`, `decision.manage`, `appeal.decide`, `master.manage`, `alumni.view`, `listing.manage`, `quality.manage`, `finance.manage`, `crm.manage`, `report.bnsp`, `settings.manage`, `user.manage` |
| TUK | `tuk.dashboard`, `tuk.applicants`, `tuk.schedule`, `tuk.facility`, `tuk.chat`, `tuk.alumni` |
| Asesor | `asesor.dashboard`, `preassessment.review`, `assessment.conduct`, `pleno.participate`, `asesor.history`, `asesor.honor` |
| Asesi | `asesi.dashboard`, `application.own`, `payment.own`, `certificate.own`, `class.own`, `profile.own` |
| Umum | `notif.log`, `ai.use`, `blog.manage` |

### Matriks peran → hak akses

| Peran | Hak akses |
|---|---|
| Admin Platform | Semua (42) |
| Admin LSP | Semua hak LSP **kecuali** `decision.manage`, `appeal.decide`, dan `assessment.review`; ditambah `rbac.view`, `notif.log`, `ai.use`, `blog.manage`, dan semua hak TUK |
| Manajer Mutu | `lsp.dashboard`, `quality.manage`, `alumni.view`, `report.bnsp`, `assessment.monitor`, `assessment.review`, `ai.use` |
| Komite Teknis | `lsp.dashboard`, `decision.manage`, `appeal.decide`, `assessment.monitor`, `alumni.view`, `ai.use` |
| Keuangan | `lsp.dashboard`, `finance.manage`, `ai.use` |
| Marketing | `lsp.dashboard`, `listing.manage`, `crm.manage`, `blog.manage`, `ai.use` |
| Admin TUK | Semua hak TUK, `ai.use` |
| Asesor | Semua hak asesor, `muk.manage`, `assessment.review`, `ai.use` |
| Asesi | Semua hak asesi, `ai.use` |

Arti hak akses baru (v17):

- `muk.manage`: menyusun perangkat asesmen (MUK) skema: FR.MAPA.01/02 dan soal instrumen IA.
- `assessment.review`: memvalidasi perangkat asesmen dan mengisi FR.VA (validator tidak boleh penyusunnya sendiri).
- `decision.manage`: memutus sertifikasi (pleno). Sejak v17 hanya Komite Teknis (dan Admin Platform).
- `appeal.decide`: memutus banding FR.AK.04.

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
2. staf LSP tempat asesi punya permohonan yang masih berjalan atau selesai (bukan yang dibatalkan/ditolak);
3. asesor yang ditugaskan pada berkasnya, selama LSP itu aktif dan permohonannya tidak dibatalkan/ditolak;
4. Admin Platform.

Setiap akses oleh selain pemilik tercatat di log audit.

Aturan tambahan yang ikut membatasi data:

- Asesor dari LSP yang dinonaktifkan kehilangan akses ke jadwal, berkas, dan group chat LSP itu.
- Asesor anggota pleno hanya menerima hasil asesmen (unit, rekomendasi, catatan). Data pribadi asesi (email, HP, tanggal lahir, NIK, dokumen) hanya dikirim ke staf pleno LSP (`decision.manage`).
- Admin LSP tidak bisa mengubah masa berlaku lisensi BNSP-nya sendiri; kolom itu hanya diubah Admin Platform.

---

## 5. Alur sertifikasi

### Langkah utama (paperless, sejak v17)

Sejak v17 semua tahap memakai **formulir elektronik** sesuai Buku Kerja Asesmen 333-25 (Perangkat Asesmen Kompetensi BNSP) dan PBNSP 201/202/206/210. Setiap formulir diisi di aplikasi, ditandatangani secara elektronik oleh pihak yang berwenang (nama, peran, waktu, IP, dan sidik jari SHA-256 isi formulir), lalu bisa dicetak atau disimpan sebagai PDF. Rancangan lengkap ada di [`rancangan-muk-digital.md`](rancangan-muk-digital.md).

| No | Tahap | Pelaku | Formulir | Akibat |
|---|---|---|---|---|
| 1 | Daftar | Asesi | **FR.APL.01** (data diri, pendidikan, pekerjaan, tujuan asesmen, bukti persyaratan dasar) dan **FR.APL.02** (asesmen mandiri per elemen dan KUK, bukti) | Keduanya ditandatangani asesi saat mengajukan. Status `diajukan`. |
| 2 | Verifikasi | Admin LSP | FR.APL.01 bagian 3: setiap bukti diberi status **Memenuhi Syarat / Tidak Memenuhi Syarat / Tidak Ada** | Terima hanya bila semua bukti MS → tagihan terbit (`menunggu_bayar`). Bisa juga minta perbaikan (asesi mengubah APL lalu menandatangani ulang) atau tolak. |
| 3 | Bayar | Asesi / Keuangan | Tagihan | `pra_asesmen` |
| 4 | Konsultasi pra-asesmen | Asesor | Tinjauan **FR.APL.02** (dapat/tidak dapat dilanjutkan), **FR.AK.07** (potensi asesi dan penyesuaian wajar), **FR.AK.01** (persetujuan asesmen, pernyataan bebas benturan kepentingan, penjelasan banding) | APL.02 "tidak dapat dilanjutkan" → `tidak_lanjut`. AK.01 hanya bisa ditandatangani bila APL.02 dan AK.07 sudah ditandatangani dan perangkat skema sudah tervalidasi. |
| 5 | Persetujuan | Asesi | Tanda tangan FR.AK.07 dan FR.AK.01 | `siap_uji` |
| 6 | Asesmen (pada/sesudah tanggal uji) | Asesor dan asesi | Instrumen aktif sesuai peta **FR.MAPA.02** untuk potensi asesi (ditambah instrumen dari AK.07): IA.01 observasi, IA.02 tugas praktik, IA.03 pertanyaan pendukung observasi, IA.04 proyek, IA.05 pilihan ganda (dinilai otomatis dari kunci), IA.06 esai, IA.07 lisan, IA.08 verifikasi portofolio, IA.09 wawancara, IA.10 pihak ketiga, IA.11 reviu produk | Untuk IA.05 dan IA.06 asesi menjawab dan menandatangani dulu; kunci jawaban tidak pernah dikirim ke akun asesi. |
| 7 | Keputusan asesor | Asesor | **FR.AK.02** rekaman asesmen per unit (bukti dan K/BK) | Wajib semua instrumen aktif sudah ditandatangani asesor. Status `menunggu_pleno`. Asesi bisa ikut menandatangani AK.02 dan mengisi **FR.AK.03** umpan balik. |
| 8 | Laporan | Asesor | **FR.AK.05** laporan asesmen per jadwal (rekomendasi tiap asesi, aspek negatif/positif, penolakan) dan **FR.AK.06** meninjau proses asesmen | Laporan susulan bisa dibuat bila ada asesi yang selesai belakangan. |
| 9 | Keputusan sertifikasi | **Komite Teknis** | Pleno | Hanya bisa memutus bila AK.02 sudah ditandatangani asesor **dan** FR.AK.05 yang mencakup asesi sudah diserahkan. Komite tidak boleh asesor yang menguji berkas itu. Kompeten → sertifikat terbit. |
| 10 | Banding | Asesi → Komite Teknis | **FR.AK.04** | Diajukan dalam batas hari banding skema (bawaan 7 hari). Diputus oleh orang yang **tidak terlibat** asesmen maupun keputusan awal (bukan asesor, bukan pemutus pleno, bukan asesi). |
| — | Mutu perangkat | Asesor / Admin LSP (penyusun), Manajer Mutu / asesor lain (validator) | **FR.MAPA.01**, **FR.MAPA.02**, soal IA.02–IA.07/IA.11, dan **FR.VA** per skema | Perangkat berversi. Validator wajib berbeda dari penyusun. Mengubah perangkat yang sudah valid membuat versi baru yang harus divalidasi ulang. |

Setiap asesi, asesor, admin, dan komite membuka formulir dari **Berkas asesmen (FR)**: daftar periksa semua formulir permohonan itu, status isian, dan tanda tangan tiap pihak. Pihak yang belum gilirannya hanya bisa membaca. Kolom milik pihak lain tidak bisa diubah (diperiksa di server).

Selama `payment_simulation` bernilai `true`, asesi bisa menandai lunas sendiri (simulasi). Bila `false`, asesi mentransfer sesuai instruksi LSP dan **Keuangan LSP** mengonfirmasi pelunasan.

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
- Kuota jadwal dicek **atomik** saat mendaftar (cek dan simpan dalam satu perintah), sehingga pendaftaran bersamaan tidak bisa melampaui kuota.
- Asesi yang aksesnya dinonaktifkan oleh suatu LSP tidak bisa mendaftar lagi ke LSP itu.
- Konflik kepentingan:
  - asesor tidak bisa mendaftar sebagai peserta di jadwal yang ia uji;
  - peserta jadwal tidak bisa ditugaskan sebagai asesor jadwal itu;
  - asesor tidak melihat pra-asesmen, asesmen, atau pleno berkasnya sendiri.
- Penugasan asesor dicek bentrok jadwal, termasuk saat tanggal jadwal diubah.
- Ubah jadwal: skema tidak bisa diganti bila sudah ada peserta, kuota tidak boleh di bawah jumlah peserta, tanggal baru tidak boleh di masa lalu, dan status `selesai`/`batal` bersifat final (asesornya pun tidak bisa diubah lagi).
- Dokumen wajib: KTP, ijazah, pas foto (PDF/JPG/PNG, maks. 2 MB). Dokumen disimpan sekali dan dipakai ulang untuk pendaftaran berikutnya. Dokumen **terkunci** (tidak bisa diganti) selama ada permohonan berstatus `menunggu_bayar`, `pra_asesmen`, `siap_uji`, atau `menunggu_pleno`. Bila dokumen diganti setelah pernah dipakai mendaftar, berkas lama **tetap disimpan** sebagai bukti LSP (jejaknya di log audit `dokumen.ganti`).
- **Biaya uji dikunci saat mendaftar** (`permohonan.harga`). Tagihan memakai biaya itu, walaupun biaya skema diubah kemudian.
- **Sertifikat menyimpan salinan** nama skema, kode skema, nama LSP, dan nama pemegang saat terbit, sehingga sertifikat lama tidak ikut berubah bila data master diganti.
- Skema yang sudah dipakai (pendaftaran atau sertifikat): kode, nama, dan level KKNI tidak bisa diubah; unit kompetensinya juga tidak diganti.
- Bila isi publik skema (nama, kode, KKNI, bidang, biaya, deskripsi, persyaratan) diubah, listing etalase yang sedang tayang **otomatis ditarik ke status menunggu** dan Admin Platform diberi notifikasi untuk meninjau ulang.

### Sertifikat PDF dan kode QR

Setiap sertifikat yang terbit bisa diunduh sebagai PDF (A4 melintang). PDF dibuat langsung oleh server saat diminta, tanpa pustaka luar dan tanpa disimpan di disk.

- **Halaman 1:** nama LSP, judul "Sertifikat Kompetensi", nomor, nama pemegang, status KOMPETEN, skema (nama, kode, jumlah unit), tanggal terbit dan masa berlaku, penerbit, serta **kode QR** di kanan bawah beserta kode verifikasi.
- **Halaman 2 dan seterusnya (lampiran):** daftar unit kompetensi (no., kode unit, judul). Bila unit banyak, tabel berlanjut ke halaman berikutnya dengan judul "(lanjutan)".
- **Kode QR** berisi tautan `https://<alamat aplikasi>/?cek=<KODE>`. Memindai QR membuka halaman verifikasi publik yang menampilkan nama, skema, LSP, dan status sertifikat dari database, sehingga sertifikat palsu atau sudah dicabut langsung ketahuan. Halaman cetak HTML juga menampilkan QR yang sama.
- **Peringatan:** bila sertifikat dicabut/dibekukan atau masa berlakunya habis, PDF memuat tulisan merah "SERTIFIKAT TIDAK BERLAKU" atau "MASA BERLAKU SERTIFIKAT TELAH HABIS".
- **Siapa yang bisa mengunduh:** pemegang sertifikat, staf LSP penerbit yang punya hak `decision.manage` atau `alumni.view` (Komite Teknis, Admin LSP, Manajer Mutu), dan Admin Platform. Pengguna lain mendapat 404 (tidak membocorkan bahwa sertifikat itu ada). Unduhan oleh selain pemegang dicatat di log audit (`sertifikat.pdf`).
- **Tombol:** Dompet Sertifikat asesi punya **Unduh PDF**, **Lihat PDF**, dan **Cetak**; menu Pleno & Sertifikat dan Database Alumni punya tombol **PDF** dan **Cetak** di setiap baris.
- **Teknis:** encoder QR (`src/qr.php`, versi 1–10, koreksi galat L/M/Q/H) dan penyusun PDF (`src/pdf.php`, font standar PDF) ditulis sendiri. Matriks QR diuji terhadap dua pembaca QR independen (jsQR dan ZXing), dan PDF hasil render dipindai ulang pada beberapa resolusi.
- **Batasan:** PDF memakai font standar PDF (Helvetica/Times), sehingga huruf Latin termasuk aksen (é, ü, ñ) tampil benar, tetapi aksara non-Latin (mis. Arab, Tionghoa) tampil sebagai `?`.

### Tanda tangan digital

Setiap PDF sertifikat **ditandatangani secara digital oleh LSP penerbit**. Tanda tangan ini kriptografis (bukan gambar): pembaca PDF seperti Adobe Acrobat Reader menampilkan siapa penanda tangannya dan memberi peringatan bila isi PDF diubah walau hanya 1 byte.

- **Cara kerja:**
  - Saat pertama dibutuhkan, server membuat **CA PortalLSP** (`PortalLSP Root CA`, RSA 3072, berlaku 20 tahun).
  - Setiap LSP mendapat **kunci dan sertifikat penanda tangan sendiri** (RSA 2048, berlaku 10 tahun) yang diterbitkan CA itu, dengan nama LSP sebagai nama penanda tangan.
  - Sertifikat LSP diterbitkan ulang otomatis bila nama LSP berubah atau masa berlakunya tinggal kurang dari 30 hari.
  - Setiap unduhan PDF ditandatangani saat itu juga (format PDF `adbe.pkcs7.detached`, SHA-256). Sertifikat penanda tangan dan CA ikut disertakan di dalam PDF.
- **Tampilan:** kotak "Ditandatangani secara digital oleh" di tengah bawah halaman 1 berisi nama penanda tangan (nama LSP saat ini, atau pemilik sertifikat PSrE), waktu penandatanganan, dan sidik jari sertifikat. Kotak ini juga area tanda tangan: klik di pembaca PDF untuk melihat detailnya.
- **Status di Adobe Reader:**
  - Tanpa pengaturan, Adobe menyatakan "dokumen tidak diubah sejak ditandatangani", tetapi identitas penanda tangan "belum diketahui", karena CA PortalLSP tidak ada di daftar tepercaya Adobe.
  - Agar tanda centang hijau muncul, pasang sertifikat CA (unduh dari halaman verifikasi atau `api.php?r=pub/ca`) sebagai sertifikat tepercaya: Adobe → Preferensi → Tanda Tangan → Identitas & Sertifikat Tepercaya → Impor. Lalu bandingkan sidik jari SHA-256 sertifikat itu dengan yang diumumkan LSP atau pengelola platform.
- **Sertifikat dari PSrE (opsional):**
  - Untuk tanda tangan elektronik **tersertifikasi**, LSP bisa memakai sertifikat elektronik dari Penyelenggara Sertifikasi Elektronik (PSrE) berinduk pemerintah. Berkasnya berupa `.p12`.
  - Pasang lewat `tte_p12` di `config.php`; PDF LSP itu lalu ditandatangani dengan sertifikat tersebut beserta rantainya.
  - Bila berkas atau kata sandinya salah, aplikasi kembali memakai sertifikat bawaan dan mencatat galat di log PHP.
  - Bila OpenSSL di server menolak `.p12` lama (enkripsi RC2/3DES), konversi dulu: `openssl pkcs12 -legacy -in lama.p12 -nodes | openssl pkcs12 -export -out baru.p12`.
- **Penyimpanan kunci:**
  - Lokasi: `storage/tte/` (di luar docroot, folder 0700, berkas 0600).
  - Kunci privat disimpan **terenkripsi** dengan kata sandi yang diturunkan dari `storage/app.key`.
  - **Cadangkan `storage/tte/` bersama `storage/app.key`.** Bila hilang, aplikasi membuat CA dan kunci baru. PDF lama tetap bisa diperiksa, tetapi pengguna yang sudah memasang CA lama perlu memasang CA baru.
- **Gagal aman:** bila penandatanganan gagal karena alasan apa pun, PDF tetap dibuat **tanpa** kotak "ditandatangani" (tidak ada klaim palsu) dan galatnya dicatat di log PHP. Nonaktifkan seluruh fitur dengan `tte_aktif` = `false`.
- **Diuji dengan:** `pdfsig` (Poppler), pyHanko (dengan CA PortalLSP sebagai akar tepercaya: sah, tepercaya, mencakup seluruh berkas), dan `openssl cms -verify`. PDF yang diubah 1 byte terdeteksi tidak sah. Rantai bergaya PSrE (akar → perantara → LSP) juga diuji.
- **CA produksi saat ini** (dibuat 11 Oktober 2026), sidik jari SHA-256:
  `9680040C F2FBB5EF 0519C1A6 EE192AD0 9227EEA0 9F7DEAF2 B52203E3 004888AE`.
  Penerima sertifikat bisa mencocokkan angka ini dengan sertifikat CA yang mereka unduh sebelum memasangnya sebagai sertifikat tepercaya.
- **Batasan:**
  - Belum ada stempel waktu dari TSA (RFC 3161), sehingga waktu tanda tangan berasal dari jam server.
  - Belum ada daftar pencabutan (CRL/OCSP) untuk sertifikat bawaan.
  - Sertifikat bawaan termasuk tanda tangan elektronik **tidak tersertifikasi**. Untuk kekuatan pembuktian penuh, pakai sertifikat PSrE.

---

## 6. Menu dan fitur per peran

### Operasi data (CRUD) per menu

| Data | Buat | Lihat | Ubah | Hapus / nonaktif | Siapa |
|---|---|---|---|---|---|
| Listing etalase | ✓ (draf/ajukan) | ✓ | Draf & perlu revisi | Hapus: draf, perlu revisi, ditolak. Listing tayang: **Turunkan** (kembali draf). Menunggu: **Tarik**. Kelas yang sudah punya peserta tidak bisa dihapus. | Admin LSP, Marketing |
| Skema | ✓ | ✓ | ✓ (kode/nama/KKNI terkunci bila sudah dipakai) | Hapus bila belum dipakai jadwal, pendaftaran, sertifikat, atau etalase; selain itu status nonaktif | Admin LSP |
| TUK | ✓ | ✓ | ✓ | Hapus (beserta sarananya) bila belum punya jadwal atau akun Admin TUK; selain itu nonaktif | Admin LSP |
| Asesor | ✓ (akun baru atau akun terdaftar) | ✓ | — | Aktif/nonaktif langsung dari tab Asesor | Admin LSP |
| Jadwal | ✓ | ✓ | ✓ (tervalidasi) | Hapus bila belum ada pendaftar; selain itu status batal | Admin LSP |
| Pengguna LSP | ✓ | ✓ | Nama, peran (antar-peran staf), password sementara. Tidak untuk akun sendiri, akun asesor/asesi, atau akun yang juga dipakai di LSP lain. Admin LSP aktif terakhir tidak bisa dipindah peran. | Aktif/nonaktif (riwayat tetap tersimpan) | Admin LSP |
| CRM | ✓ | ✓ | ✓ + pindah tahap | Hapus | Admin LSP, Marketing |
| Mutu | ✓ | ✓ | ✓ | Hapus bila belum selesai (item selesai = rekaman mutu) | Admin LSP, Manajer Mutu |
| Sarana & prasarana | ✓ | ✓ | ✓ | Hapus | Admin TUK, Admin LSP |
| Pustaka SKKNI | ✓ | ✓ | ✓ (judul, sektor) | Hapus (skema yang memakainya tidak berubah) | Admin Platform |
| Blog | ✓ | ✓ | ✓ | Hapus (kecuali artikel yang diturunkan, hanya Admin Platform) | Admin LSP, Marketing, Admin Platform |
| Tiket support | ✓ | ✓ | Balas, ubah status (platform) | — (riwayat) | Admin LSP, Admin Platform |
| LSP klien | ✓ (+ Admin LSP) | ✓ | Pengaturan, paket | Aktif/nonaktif | Admin Platform |
| Profil LSP | — | ✓ | ✓ (nama LSP dan lisensi hanya Admin Platform) | — | Admin LSP |
| Profil asesi | Saat daftar (atau dilengkapi di profil bila belum ada) | ✓ | No. HP & persetujuan info kapan saja; nama, tanggal lahir, jenis kelamin hanya sebelum ada pendaftaran berjalan/sertifikat | — | Asesi |
| Dokumen asesi | ✓ | ✓ | Ganti (terkunci saat diproses) | — | Asesi |

Data yang menjadi bukti proses sertifikasi (permohonan, tagihan, sertifikat, log audit) tidak bisa dihapus dari aplikasi.

### Asesi

| Menu | Fungsi |
|---|---|
| Beranda | Ringkasan permohonan aktif, langkah berikutnya, tagihan, sertifikat |
| Daftar Skema Baru | Katalog skema dari semua LSP lalu wizard pendaftaran |
| Jadwal Saya | Daftar permohonan dan statusnya, kirim ulang perbaikan, batalkan |
| Pembayaran | Tagihan dan pembayaran (simulasi bila `payment_simulation` aktif) |
| Dompet Sertifikat | Sertifikat milik sendiri: unduh/lihat PDF dengan kode QR, cetak, tautan verifikasi |
| Kelas Saya | Kelas pelatihan (LMS): daftar, catat progres |
| Profil & Dokumen | Data diri (tombol **Ubah profil**), unggah dan lihat dokumen (terkunci selama permohonan diproses) |

### Asesor

| Menu | Fungsi |
|---|---|
| Beranda | Tugas hari ini, pra-asesmen tertunda, berkas menunggu pleno |
| Kalender Gabungan | Semua jadwal penugasan dari semua LSP tempat ia aktif |
| Tinjau Pra-Asesmen | Daftar asesi pra-asesmen; tombol **Buka berkas** untuk meninjau FR.APL.02 dan menandatangani FR.AK.07 dan FR.AK.01 |
| Asesmen (MUK/FR) | Berkas asesi siap uji: mengisi instrumen IA aktif dan FR.AK.02, hanya pada/sesudah tanggal uji |
| Laporan & Tinjauan | FR.AK.05 laporan asesmen dan FR.AK.06 meninjau proses asesmen per jadwal |
| Pleno | Melihat berkas pleno (keputusan oleh Komite Teknis). Daftar "Diuji oleh Anda" ditampilkan terpisah. |
| Perangkat Asesmen (MUK) | Menyusun FR.MAPA.01/02 dan soal instrumen skema LSP tempat ia aktif; memvalidasi perangkat yang disusun asesor lain |
| Riwayat & Logbook | Riwayat asesmen, unduh logbook CSV |
| Honor | Rekap honor per LSP |

### Admin LSP (dan staf sesuai hak)

| Kelompok | Menu | Fungsi |
|---|---|---|
| Operasional | Dashboard | KPI pendaftaran, jadwal, kelulusan, pendapatan |
| | Pendaftaran | Verifikasi berkas: terima / perbaikan / tolak (tagihan otomatis) |
| | Jadwal & Penugasan | Buat dan ubah jadwal (tervalidasi, lihat bagian 5), tetapkan asesor (cek bentrok dan konflik kepentingan), kuota |
| | Asesmen | Memantau progres asesmen tiap jadwal dan membuka berkas FR tiap asesi |
| | Banding Asesmen | Memantau banding FR.AK.04 (yang memutus Komite Teknis) |
| | Perangkat Asesmen (MUK) | Menyusun perangkat asesmen skema (FR.MAPA.01/02, soal IA) |
| | Validasi Asesmen | Status validasi perangkat dan FR.VA per skema |
| Data | Skema, Asesor, TUK | Master skema: unit kompetensi beserta elemen dan KUK (format `KODE \| Judul \| kelompok`, lalu baris `- Elemen \| KUK 1; KUK 2`), kemasan, masa berlaku, batas hari banding; asesor (dengan nomor registrasi MET); TUK |
| | Database Alumni | Pemegang sertifikat LSP |
| Manajemen | Etalase & Pelatihan | Listing skema/kelas ke portal publik (wajib disetujui Admin Platform). Ubah, hapus, tarik, dan turunkan listing. |
| | Blog (CMS) | Menulis, menjadwalkan, dan mengelola artikel blog LSP (lihat bagian 8) |
| | Mutu (Pedoman 201) | Audit internal, kaji ulang manajemen, tindakan perbaikan |
| | Keuangan | Daftar tagihan, konfirmasi pelunasan manual |
| | CRM | Kanban prospek (baru → dihubungi → proposal → menang/kalah) |
| | Laporan BNSP | Rekap per skema/periode, unduh CSV (dengan pelindung formula injection) |
| | Pengguna & Hak Akses | Tambah staf LSP, aktif/nonaktifkan (termasuk akses asesi ke LSP ini), matriks peran |
| | Log Notifikasi | Riwayat pengiriman email/WA milik LSP (penerima disamarkan) |
| | Profil LSP & Pengaturan | Profil, kontak, honor asesor. Masa berlaku lisensi hanya bisa dilihat; yang mengubah Admin Platform. |
| | Tiket Support | Membuat dan memantau tiket ke tim platform |
| TUK | Dashboard TUK, Pemohon, Jadwal TUK, Sarana & Prasarana, Group Chat, Alumni TUK | Semua TUK milik LSP ini |

**Komite Teknis** melihat Dashboard, Asesmen (berkas FR), Pleno & Sertifikat (memutus setelah FR.AK.05 diserahkan), Banding Asesmen (memutus FR.AK.04), dan Database Alumni. **Manajer Mutu** melihat Dashboard, Asesmen, Validasi Asesmen, Mutu, Laporan BNSP, dan Database Alumni.

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
| LSP Klien | Onboarding LSP baru (sekaligus membuat Admin LSP), aktif/nonaktif, masa berlaku lisensi |
| Paket & Tagihan | Paket langganan dan kuota per LSP |
| Pustaka SKKNI | Daftar unit kompetensi acuan: tambah, ubah, hapus |
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
- **Jadwal uji:** jadwal mendatang semua LSP beserta sisa kuota. Catatan internal jadwal (mis. tautan rapat SJJ) tidak ditampilkan.
- **Direktori LSP:** profil, skema, TUK.
- **Pelatihan:** kelas yang tayang.
- **Untuk LSP:** paket langganan. Harga dan kuota dibaca dari server (`pub/paket`), sama dengan menu Paket & Tagihan Admin Platform. Tombol berlangganan mengarah ke `kontak_sales` di `config.php`; bila kosong, tombol disembunyikan.
- **Blog:** artikel dari platform dan LSP, dengan pencarian, filter kategori, dan tautan langsung `/?artikel=SLUG` (lihat bagian 8). Beranda menampilkan 3 artikel terbaru.
- **Verifikasi sertifikat:** buka `/?cek=KODE` atau isi formulir verifikasi.
  - Dengan **kode verifikasi** (10 karakter, tercetak di sertifikat): nama pemegang tampil lengkap.
  - Dengan **nomor sertifikat**: keabsahan tampil, tetapi nama disamarkan (mis. `R*** K****** S***`), agar nomor yang berurutan tidak bisa dipakai mengumpulkan nama orang.
  - Dibatasi 30 permintaan per 10 menit per IP (IPv6 dihitung per blok /64).
  - Hasil verifikasi menyertakan tautan unduh **sertifikat CA PortalLSP** (`api.php?r=pub/ca`) untuk memeriksa tanda tangan digital PDF di Adobe Acrobat Reader (lihat bagian 5, "Tanda tangan digital").
- **Pendaftaran asesi mandiri** dengan verifikasi email (`/?verifikasi=TOKEN`). Dibatasi 30 percobaan per IP per jam; kirim ulang email verifikasi maks. 5 kali per 24 jam.

Halaman publik tidak membuat cookie sesi bagi pengunjung yang belum pernah login.

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
- Artikel yang sedang diturunkan tidak bisa dihapus oleh LSP (agar jejak moderasi tetap ada); hanya Admin Platform yang bisa menghapusnya.
- Hitungan pembaca hanya bertambah untuk pengunjung yang punya sesi, sehingga permintaan otomatis tanpa cookie tidak menggelembungkan angka.
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
- Pengiriman dilakukan oleh `tools/notify-worker.php` (cron), dengan percobaan ulang hingga 5 kali. Riwayat notifikasi dan pemakaian AI lebih dari 90 hari dibersihkan otomatis, begitu pula log pembatas laju (cek sertifikat, percobaan pendaftaran) lebih dari 30 hari.
- Log notifikasi: Admin LSP hanya melihat milik LSP-nya, Admin Platform melihat semua. Alamat penerima disamarkan.

---

## 10. Asisten AI

- Tombol asisten tersedia di semua peran yang punya `ai.use`.
- Panggilan ke API Claude dilakukan dari server. Kunci API hanya ada di `config.php`. Bila kunci kosong, fitur nonaktif.
- Konteks yang dikirim ke model hanya ringkasan data milik pengguna/LSP aktifnya. NIK, email, dan nomor HP disamarkan sebelum dikirim.
- Model tidak diberi akses tool, sehingga tidak bisa mengubah data atau membaca data di luar konteks.
- Isi percakapan tidak disimpan. Yang dicatat hanya jumlah token (tabel `ai_usage`).
- Asesi wajib sudah memverifikasi email sebelum memakai asisten.
- Batas pemakaian:
  - 20 pesan per jam per pengguna;
  - 100 pesan per hari per pengguna;
  - batas total harian platform (`ai_daily_limit`).
- Kuota dicatat **sebelum** API dipanggil (baris `ai_usage` berstatus `proses`, lalu `ok` atau `gagal`), sehingga banyak permintaan bersamaan tidak bisa melewati batas.
- Percakapan maks. 12 pesan terakhir; pesan pengguna maks. 2.000 karakter.
- Aturan integritas: asisten menolak membuatkan jawaban uji, isian APL.02, atau bukti portofolio.

---

## 11. Keamanan

| Area | Penerapan |
|---|---|
| Password | `password_hash` (bcrypt) dengan rehash otomatis. Minimal 10 karakter (maks. 72 byte, batas bcrypt), wajib huruf dan angka. Akun yang dibuat admin wajib ganti password saat login pertama. |
| Sesi | Cookie `HttpOnly`, `Secure`, `SameSite=Lax`. ID sesi diganti saat login, ganti konteks, dan ganti password. Ganti password langsung mengakhiri semua sesi lain akun itu (kolom `users.sess_ver`). Idle timeout 30 menit, maksimal 8 jam. File sesi di luar docroot; halaman publik tanpa cookie tidak membuat sesi. |
| CSRF | Token per sesi di header `X-CSRF-Token` + cek `Origin` untuk setiap POST |
| Lupa password | Tautan atur ulang lewat email (`password_resets`, migrasi 11): token acak 256-bit, yang disimpan hanya hash SHA-256-nya, berlaku 1 jam, sekali pakai (dikunci dengan `UPDATE ... WHERE used_at IS NULL`). Jawaban selalu sama untuk email terdaftar maupun tidak. Batas 10 permintaan per IP per jam, satu email per akun per 2 menit, maks. 5 per hari. Setelah password baru disimpan, semua sesi lama keluar (`sess_ver`) dan kunci login karena salah password dibuka. Admin LSP juga bisa mengatur password sementara untuk staf dari menu Pengguna (wajib diganti saat masuk). |
| Brute force | Kunci setelah 5 gagal per email atau 20 per IP dalam 15 menit. Pesan error sama untuk email terdaftar maupun tidak. Pendaftaran asesi maks. 30 percobaan per IP per jam; pesan NIK ganda dibuat umum agar tidak bisa dipakai menebak NIK; kirim ulang email verifikasi maks. 5 per 24 jam. |
| RBAC | Dari tabel `roles` / `permissions` / `role_permissions`, diperiksa di setiap endpoint. Matriks 1.232 kombinasi peran × endpoint diuji otomatis (`tests/rbac-matrix.test.mjs`); rute baru tanpa aturan akses membuat uji gagal. |
| Konflik kepentingan | Asesor tidak bisa menguji, mem-pra-asesmen, atau memutus pleno berkasnya sendiri; tidak bisa ditugaskan pada jadwal yang ia ikuti sebagai peserta; tidak bisa mendaftar sebagai peserta di jadwal yang ia uji. Penguji tidak boleh memutus pleno asesinya. |
| Integritas proses | Status permohonan berpindah dengan kondisi `WHERE status = ...` (aman dari klik ganda). Kuota jadwal dicek atomik saat pendaftaran. Dokumen tidak bisa diganti selama permohonan diproses. Jadwal: skema tidak bisa diganti bila sudah ada peserta, kuota ≥ peserta, status `selesai`/`batal` final, bentrok asesor dicek ulang saat tanggal berubah. Masa berlaku lisensi BNSP dan nama LSP hanya diubah Admin Platform. Minimal satu Admin Platform aktif selalu dijaga. Biaya uji dikunci saat mendaftar; sertifikat menyimpan salinan nama skema/LSP/pemegang; perubahan isi publik skema menarik listing tayang untuk ditinjau ulang. Penghapusan data master dicek terhadap data yang masih merujuknya. |
| Isolasi LSP | `lsp_id` selalu dari sesi; data LSP lain dibalas 404 (lihat bagian 4) |
| Akun nonaktif | Akun dan keanggotaan dibaca ulang dari DB setiap permintaan, sehingga akses langsung hilang. Asesi yang dinonaktifkan LSP tidak bisa mendaftar lagi ke LSP itu; asesor dari LSP nonaktif kehilangan akses berkasnya. |
| Tanda tangan digital | Kunci privat CA dan LSP di `storage/tte/` (di luar docroot, 0700/0600), terenkripsi dengan kata sandi turunan `storage/app.key`. Pembuatan kunci dikunci berkas (`flock`) agar tidak ganda, dan dicatat di log audit (`tte.ca_dibuat`, `tte.sertifikat_lsp`). Hanya sertifikat CA (publik) yang bisa diunduh. |
| Data pribadi | NIK asesi dienkripsi AES-256-GCM dengan kunci `storage/app.key` (kunci ini wajib dicadangkan; tanpa kunci, NIK tidak bisa dibaca) |
| Unggahan | Disimpan di `storage/uploads` (di luar docroot). Jenis berkas dicek dari isi (magic bytes: PDF/PNG/JPEG), maks. 2 MB, nama berkas acak. Disajikan dengan header CSP `sandbox` dan `nosniff`. |
| Ekspor CSV | Sel yang diawali `=`, `+`, `-`, `@` diberi awalan `'` (mencegah formula injection) |
| Frontend | CSP `script-src 'self'`; semua teks dari server di-escape |
| Transport | HTTPS (TLS 1.2 dan 1.3; 1.0/1.1 ditolak) + HSTS 1 tahun termasuk subdomain. Header di `.htaccess`: CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, Cross-Origin-Opener-Policy, Cross-Origin-Resource-Policy, X-Permitted-Cross-Domain-Policies. |
| Audit | Login/logout, akses ditolak, perubahan listing, kurasi, manajemen pengguna, akses dokumen, keputusan pleno |
| Rate limit | IP dihitung per alamat IPv4 atau per blok IPv6 /64. Verifikasi sertifikat 30 per 10 menit per IP (tanpa batas global agar tidak bisa dipakai memblokir semua pengguna); cek dengan nomor sertifikat hanya menampilkan nama tersamar (nama lengkap hanya dengan kode verifikasi). Asisten AI per pengguna dan per hari, kuota dicatat sebelum memanggil API sehingga tidak bisa dilewati dengan permintaan paralel; asesi wajib verifikasi email dulu. |
| Data publik | Catatan internal jadwal (mis. tautan rapat SJJ) tidak ikut di API publik |
| Banjir pesan | Group chat maks. 30 pesan per 5 menit per pengguna; tiket support maks. 5 per jam, balasan maks. 30 per 10 menit |
| Pembayaran | `payment_simulation` (default `true`) mengizinkan asesi menandai lunas sendiri untuk demo. Set `false` saat LSP nyata beroperasi; pelunasan lalu hanya dikonfirmasi Keuangan LSP. |

### Audit keamanan (Oktober 2026)

Audit RBAC, keamanan aplikasi, dan hardening server menemukan dan memperbaiki: asesor yang juga asesi bisa memplenokan berkasnya sendiri, asesi nonaktif masih bisa mendaftar, kebocoran catatan jadwal di API publik, pendaftaran melewati kuota saat bersamaan, nomor sertifikat membuka nama lengkap, pesan NIK ganda bisa dipakai menebak NIK, sesi lain tetap hidup setelah ganti password, batas AI bisa dilewati dengan permintaan paralel, dan validasi ubah jadwal yang kurang. Semua dibuktikan oleh `tests/security.test.mjs`.

Audit putaran 2 (review independen seluruh endpoint) tidak menemukan IDOR, SQL injection, XSS, atau celah CSRF. Yang diperbaiki: biaya tagihan dan isi publik skema bisa diubah setelah disetujui, sertifikat ikut berubah bila nama skema/LSP diganti, Admin LSP bisa mengganti nama LSP sendiri, dokumen lama terhapus saat diganti, pembatas laju bisa dilewati dengan rotasi IPv6 dan batas global verifikasi bisa memblokir semua pengguna, serta tidak ada batas pesan chat/tiket. Listing yang ditolak Admin Platform kini final (hanya bisa dihapus).

Hardening server (diperiksa ulang 10 Okt 2026): HTTP dialihkan ke HTTPS, TLS 1.0/1.1 ditolak, sertifikat Let's Encrypt berlaku s.d. Jan 2027, semua header keamanan aktif, `X-Powered-By` tidak tampil, berkas sensitif (`config.php`, database, `storage/`, `src/`, `.git`) tidak bisa diakses dari web, metode TRACE/PUT/DELETE/OPTIONS ditolak, izin berkas `config.php` dan database 600, folder `storage` 700.

---

## 12. Model data

### Tabel inti (akun dan akses)

| Tabel | Isi |
|---|---|
| `users` | Akun (email, nama, hash password, status, wajib ganti password, `sess_ver` untuk mengakhiri sesi lain saat ganti password) |
| `memberships` | Keanggotaan pengguna ke LSP/TUK dengan peran tertentu |
| `roles`, `permissions`, `role_permissions` | RBAC |
| `lsp`, `tuk` | LSP klien dan TUK |
| `asesi_profiles` | Data diri asesi (NIK, alamat, pendidikan, dsb.) |
| `email_verifications` | Token verifikasi email pendaftaran mandiri |
| `login_attempts` | Catatan gagal login (penguncian) |
| `audit_logs` | Log audit; juga dipakai sebagai catatan pembatas laju (cek sertifikat, percobaan pendaftaran) |
| `schema_migrations` | Versi migrasi (terbaru: 9) |

### Tabel sertifikasi

| Tabel | Isi |
|---|---|
| `skema`, `skema_units`, `skema_elemen` | Skema sertifikasi (termasuk kemasan, masa berlaku, batas hari banding), unit kompetensi (dengan kelompok pekerjaan), elemen dan KUK |
| `muk_perangkat` | Perangkat asesmen per skema dan kode (FR.MAPA.01/02, soal IA): versi, isi, penyusun, validator, status validasi |
| `formulir`, `formulir_ttd` | Formulir FR elektronik per permohonan, jadwal, atau skema (isi JSON, status) dan tanda tangan tiap pihak (peran, waktu, IP, sidik jari SHA-256 isi) |
| `banding` | Banding FR.AK.04: alasan, status, keputusan, pemutus |
| `skkni` | Pustaka unit SKKNI acuan |
| `jadwal` | Jadwal uji (skema, TUK, tanggal, kuota, asesor) |
| `permohonan` | Pendaftaran asesi: status, APL.02, rekomendasi pra-asesmen, hasil per unit, keputusan pleno, biaya uji saat mendaftar (`harga`) |
| `dokumen` | Dokumen asesi (KTP, ijazah, foto, CV) |
| `tagihan` | Tagihan per permohonan (nomor invoice, status bayar) |
| `sertifikat` | Sertifikat terbit (nomor, kode verifikasi, masa berlaku) + salinan `skema_nama`, `skema_kode`, `lsp_nama`, `nama_pemegang` saat terbit |
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
| `ai_usage` | Pemakaian asisten AI per permintaan (status `proses`/`ok`/`gagal`, jumlah token) |
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
| `auth/forgot`, `auth/reset-check`, `auth/reset` | Lupa password: kirim tautan atur ulang ke email, cek tautan, simpan password baru (publik, tanpa login) |
| `profile` | Profil asesi (GET), ubah profil / lengkapi NIK (POST) |

### Listing, pengguna, platform

| Rute | Hak | Fungsi |
|---|---|---|
| `catalog` | publik | Katalog listing tayang |
| `listings` (GET/POST; `id` = ubah), `listings/submit`, `listings/withdraw`, `listings/hapus`, `listings/turunkan` | `listing.manage` | Etalase LSP |
| `reviews`, `reviews/decide` | `listing.review` | Kurasi listing |
| `users`, `users/status`, `users/ubah` | `user.manage` | Pengguna LSP: tambah, aktif/nonaktif, ubah nama/peran/password sementara |
| `rbac` | `rbac.view` | Matriks peran |
| `lsps` | login | Daftar LSP (platform: semua) |
| `platform/dashboard` | `platform.dashboard` | Ringkasan platform |
| `platform/lsp`, `platform/lsp/status` | `lsp.manage` | LSP klien |
| `platform/paket` | `lsp.manage` | Paket dan kuota |
| `skkni` (POST; `ubah: true` = ubah), `skkni/hapus` | `lsp.manage` | Pustaka SKKNI |
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
| `asesi/tagihan`, `asesi/bayar` | Tagihan dan pembayaran simulasi (`asesi/bayar` dibalas 403 bila `payment_simulation` = `false`) |
| `asesi/sertifikat` | Sertifikat sendiri |
| `sertifikat/pdf` | Sertifikat PDF dengan kode QR (`&id=`; tambah `&unduh=1` untuk mengunduh). Pemilik, staf LSP penerbit dengan `decision.manage`, atau Admin Platform |
| `sertifikat/cetak` | Halaman cetak sertifikat (HTML) dengan kode QR |
| `asesi/kelas`, `asesi/kelas/daftar`, `asesi/kelas/progres` | Kelas pelatihan |

### Asesor dan pleno

| Rute | Fungsi |
|---|---|
| `asesor/jadwal` | Kalender gabungan |
| `asesor/pra` | Daftar pra-asesmen (keputusan lewat formulir FR) |
| `asesor/asesmen` | Daftar asesi siap uji (penilaian lewat formulir FR) |
| `asesor/laporan` | Jadwal yang perlu FR.AK.05/AK.06 |
| `asesor/riwayat` | Riwayat dan logbook (CSV) |
| `asesor/honor` | Honor |
| `pleno`, `pleno/putus` | Pleno dan penerbitan sertifikat (hak `decision.manage`, Komite Teknis). `pleno` juga mengembalikan `diuji_sendiri` dan status FR.AK.05; `pleno/putus` dibalas 403 untuk berkas yang diuji atau milik sendiri, dan 409 bila AK.02 atau AK.05 belum ada. |
| `muk/def` | Definisi FR.APL.01/02 untuk wizard pendaftaran |
| `muk/formulir`, `muk/formulir/simpan`, `muk/formulir/ttd` | Ambil, simpan draf, dan tanda tangani formulir FR (konteks `permohonan_id`, `jadwal_id`, atau `skema_id`). Tanda tangan butuh `setuju: true` dan mengikuti urutan pihak. |
| `muk/berkas`, `muk/jadwal` | Daftar periksa formulir satu permohonan / satu jadwal |
| `muk/perangkat`, `muk/perangkat/detail`, `muk/perangkat/simpan`, `muk/perangkat/ttd` | Perangkat asesmen skema: lihat, susun, ajukan, validasi atau kembalikan |
| `banding`, `banding/putus` | Daftar dan keputusan banding FR.AK.04 (hak `appeal.decide`) |
| `chat`, `chat/kirim`, `chat/ruang` | Group chat per jadwal |

### Staf LSP

| Rute | Hak |
|---|---|
| `lsp/dashboard` | `lsp.dashboard` |
| `lsp/pendaftaran`, `lsp/pendaftaran/putus` | `registration.verify` |
| `lsp/jadwal`, `lsp/jadwal/asesor`, `lsp/jadwal/hapus` | `schedule.manage` |
| `lsp/asesmen` | `assessment.monitor` |
| `lsp/hasil` | `decision.manage` |
| `lsp/skema`, `lsp/skema/hapus`, `lsp/asesor`, `lsp/tuk`, `lsp/tuk/hapus`, `lsp/opsi` | `master.manage` |
| `lsp/alumni` | `alumni.view` |
| `lsp/keuangan`, `lsp/keuangan/lunas` | `finance.manage` |
| `lsp/laporan` (+ `&format=csv`) | `report.bnsp` |
| `lsp/crm`, `lsp/crm/tahap`, `lsp/crm/hapus` | `crm.manage` |
| `lsp/mutu`, `lsp/mutu/hapus` | `quality.manage` |
| `lsp/pengaturan` | `settings.manage` |

### TUK

| Rute | Hak |
|---|---|
| `tuk/dashboard` | `tuk.dashboard` |
| `tuk/pemohon` | `tuk.applicants` |
| `tuk/jadwal` | `tuk.schedule` |
| `tuk/sarpras`, `tuk/sarpras/hapus` | `tuk.facility` |
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
| `pub/jadwal` | Jadwal mendatang (tanpa catatan internal) |
| `pub/lsp` | Direktori LSP |
| `pub/verify` | Verifikasi sertifikat (`q` = kode atau nomor). Dengan nomor, respons berisi `nama_disamarkan: true`. Dibatasi per IP dan total. |
| `pub/blog` | Daftar artikel terbit (`q`, `kategori`, `lsp`, `page`) + jumlah per kategori |
| `pub/blog/post` | Satu artikel (`slug`) + artikel terkait; menambah hitungan pembaca |
| `pub/blog/cover` | Gambar sampul (`id`) |
| `pub/ca` | Sertifikat CA PortalLSP (`.crt`) untuk memeriksa tanda tangan digital PDF sertifikat |
| `pub/paket` | Harga dan kuota paket langganan (dari konstanta `PAKET`, sumber yang sama dengan Paket & Tagihan) + kontak penjualan |

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
| `payment_simulation` | `true` (default) = tombol "Bayar" simulasi untuk asesi. Set `false` di operasi nyata. |
| `kontak_sales` | Kontak penjualan di halaman Untuk LSP: email atau tautan `https://` (mis. WhatsApp). Kosong = tombol disembunyikan dan halaman meminta pengunjung menghubungi pengelola. |
| `seed_password_hashes` | Hash password akun demo. Kosongkan di produksi nyata. |
| `tte_aktif` | `true` (default) = PDF sertifikat ditandatangani digital. `false` = PDF tanpa tanda tangan. |
| `tte_p12` | Opsional: sertifikat PSrE per LSP, mis. `['3' => ['file' => '/path/lsp-3.p12', 'password' => '...']]`; kunci `'*'` berlaku untuk semua LSP. |

> **Penting:**
> - Kunci API dan token WhatsApp diisi sendiri oleh pemilik server langsung di `config.php`, jangan dikirim lewat chat atau di-commit.
> - Cadangkan `storage/app.key`, `storage/tte/` (kunci tanda tangan digital), dan database `storage/*.sqlite` secara berkala.

---

## 15. Instalasi, deploy, dan cron

### Instalasi baru

1. Salin folder `app/` ke server, misalnya `/home/<akun>/lsp-app`.
2. Arahkan docroot subdomain ke `lsp-app/public`. Folder `src/`, `storage/`, `tools/`, dan `config.php` harus tetap di luar docroot.
3. Salin `config.example.php` menjadi `config.php`, lalu isi.
4. Pastikan `storage/` bisa ditulis PHP (izin 0700/0750).
5. Buka situs. Migrasi berjalan otomatis saat permintaan pertama.
6. Jalankan `php tools/smoke.php` untuk memeriksa: versi PHP, driver DB, dan jumlah data. Hapus lagi dari server setelah dipakai.
7. Kunci enkripsi `storage/app.key` dibuat otomatis (izin 600) saat NIK pertama disimpan. Segera cadangkan.

### Cron

| Jadwal | Perintah | Fungsi |
|---|---|---|
| Tiap 6 menit (`*/6`; boleh lebih sering) | `/opt/alt/php83/usr/bin/php /home/<akun>/lsp-app/tools/notify-worker.php >> .../storage/notify-worker.log 2>&1` | Mengirim antrean email/WhatsApp |

### Pembaruan versi (cara yang dipakai di produksi)

1. Buat paket patch PHP yang memverifikasi hash setiap berkas lama sebelum menulis berkas baru.
2. Kompres paket (gzip), lalu unggah ke `lsp-app/tools/`.
3. Jalankan sekali lewat cron sementara dengan berkas penanda (mis. `storage/patch9.txt`) agar tidak berjalan dua kali. Bila ada satu saja berkas yang hash-nya tidak cocok, patch dibatalkan tanpa mengubah apa pun. Bila cocok, berkas lama dicadangkan ke `storage/backup-<tanggal-jam>` lalu berkas baru ditulis.
4. Migrasi database berjalan otomatis pada permintaan berikutnya.
5. Baca keluaran, uji asap dari server (header, API publik, verifikasi), lalu hapus cron sementara dan berkas patch.

> Cache aset: proxy hosting menyimpan berkas JS/CSS sebagai `public, immutable` selama 30 hari. Karena itu `index.html` memuat `app.js?v=<versi>`, dan `app.js` memuat `fitur.js` serta `fitur.css` dengan versi yang sama. **Setiap rilis yang mengubah JS/CSS wajib menaikkan angka `?v=` di `index.html`**, supaya browser pengguna mengambil berkas baru.

> Catatan cron cPanel: tanda `%` di perintah cron dianggap baris baru. Untuk perintah yang memakai `%` (mis. `curl -w '%{http_code}'`), taruh di skrip `.sh` lalu panggil skripnya dari cron.

Riwayat versi di produksi:

| Patch | Isi |
|---|---|
| v3 | Asisten AI + notifikasi email/WhatsApp |
| v4 | Admin Platform akses penuh semua menu dan semua LSP |
| v5 | Admin LSP akses penuh semua data LSP-nya (termasuk TUK), isolasi antar-LSP |
| v6 | Semua menu dan fitur berfungsi: proses sertifikasi lengkap, portal publik dengan data nyata, data demo. Dideploy 9 Okt 2026, smoke test lulus. |
| v7 | CMS blog (migrasi 7): menu Blog (CMS), halaman Blog publik, moderasi Admin Platform, 6 artikel contoh. |
| v8 | Responsif HP & tablet: laci menu, header ringkas, tabel adaptif menjadi kartu, grid tablet, target sentuh. |
| v9 | Audit RBAC, keamanan, dan hardening (migrasi 8: `users.sess_ver`). Dideploy 10 Okt 2026, uji asap produksi lulus. Lihat bagian 11. |
| v10 | CRUD lengkap (ubah/hapus/turunkan listing, hapus skema/TUK/jadwal/CRM/mutu/sarpras, ubah/hapus SKKNI, ubah profil asesi, nonaktifkan asesor dari menu Master), audit keamanan putaran 2, header isolasi lintas-origin, versi aset `?v=11` untuk menembus cache proxy (migrasi 9: `permohonan.harga`, salinan data di `sertifikat`). Dideploy 10 Okt 2026, cadangan database `storage/backup-db-pra-v10.sqlite`. |
| v11 | Sertifikat PDF dengan kode QR verifikasi (unduh dari Dompet Sertifikat, Pleno & Sertifikat, Database Alumni), QR juga di halaman cetak, versi aset `?v=12`. Tanpa migrasi database. Dideploy 11 Okt 2026. |
| v12 | Tanda tangan digital PDF sertifikat: CA PortalLSP, sertifikat penanda tangan per LSP, dukungan `.p12` PSrE, kotak tanda tangan di halaman 1, unduh CA (`pub/ca`) dari halaman verifikasi, versi aset `?v=13`. Tanpa migrasi database. Dideploy 11 Okt 2026. |
| v13 | Halaman Untuk LSP membaca harga dan kuota dari server (`pub/paket`), sehingga selalu sama dengan Paket & Tagihan; tombol demo palsu diganti tautan `kontak_sales`; footer "Purwarupa UI/UX" diganti deskripsi produk; versi aset `?v=14`. Tanpa migrasi database. Dideploy 11 Okt 2026. |
| v14 | Label "Terdaftar PSE · Server di Indonesia" dihapus dari footer portal publik dan aplikasi, karena status PSE belum dikonfirmasi; versi aset `?v=15`. Tanpa migrasi database. Dideploy 11 Okt 2026. |
| v15 | Data demo lengkap (migrasi 10, `src/seed_lengkap.php`): riwayat sertifikasi 11 bulan di empat LSP, permohonan di setiap tahap proses, sertifikat akan habis/kedaluwarsa/dicabut, notifikasi dan log pengiriman, tiket dengan balasan, CRM, mutu, sarana TUK, group chat, kelas, profil asesi. Stempel waktu data contoh lama dirapikan dan jadwal asesor demo yang bentrok dialihkan. Hanya berjalan bila akun demo ada. Kartu KPI sempit dengan angka panjang (mis. nominal rupiah di layar 1024 px) kini menaruh ikon di atas angka agar tidak menimbulkan scroll horizontal; versi aset `?v=16`. Dideploy 11 Okt 2026, cadangan database `storage/backup-db-pra-v15.sqlite`. Karena data migrasi 6 di produksi dibuat 2 hari lebih awal, lima jadwal contoh sempat bentrok dengan jadwal asesor yang sama; sudah digeser (cadangan `storage/backup-db-pra-v15b.sqlite`) dan seed kini memilih tanggal kosong otomatis. |
| v16 | Fitur **Lupa password** (migrasi 11, tabel `password_resets`): tautan atur ulang lewat email, halaman Lupa password dan Buat password baru. **Ubah pengguna** di menu Pengguna (nama, peran, password sementara). Kode tampilan purwarupa lama yang sudah tidak dipakai (dashboard, halaman publik, dan data contoh statis di `app.js`) dihapus; pemindai otomatis memastikan tidak ada teks purwarupa, label "contoh", atau tombol tanpa aksi di 112 halaman. Perbaikan seed riwayat jadwal asesor lintas LSP. Versi aset `?v=17`. Situs demo `demo-lsp` (MySQL) dibuat 12 Okt 2026. |
| v17 | **Formulir asesmen paperless** sesuai Buku Kerja 333-25 dan PBNSP 201/202/206/210 (migrasi 12; `src/muk*.php`, `public/muk.js`): FR.APL.01/02, AK.01–AK.07, IA.01–IA.11, MAPA.01/02, VA dengan tanda tangan elektronik berurutan. Peran baru **Komite Teknis** (pemutus sertifikasi dan banding); Admin LSP dan Manajer Mutu tidak lagi memutus pleno. Elemen/KUK skema, perangkat asesmen berversi dengan validator ≠ penyusun, banding FR.AK.04, laporan AK.05/AK.06 per jadwal, nomor MET asesor. Akun demo baru: `komite.tdn`, `mutu.tdn`, `asesor2`. Versi aset `?v=18`. Dideploy ke produksi 12 Okt 2026 (berkas diambil dari commit di GitHub dengan pemeriksaan SHA-256 dasar dan hasil); cadangan database `storage/backup-db-pra-v17.sqlite`, cadangan kode `storage/backup-20261012-135412-v17`. Migrasi 12 di produksi: 1,1 detik, 2.473 formulir dan 4.468 tanda tangan. Semua 11 akun demo diberi password baru dan diuji masuk lewat HTTPS (11/11). |

### Situs demo (demo-lsp)

Situs demo terpisah untuk presentasi dan uji coba, supaya data produksi tidak tercampur data percobaan.

| Hal | Situs demo |
|---|---|
| Alamat | https://demo-lsp.semestateknologiutama.com (SSL Let's Encrypt lewat AutoSSL) |
| Folder | `/home/<akun>/demo-lsp-app` (docroot `demo-lsp-app/public`) |
| Database | MySQL/MariaDB `semestat_demolsp`, pengguna `semestat_demolsp` dengan hak penuh hanya pada database itu |
| Kode | Sama dengan produksi (v15 + commit perbaikan seed riwayat jadwal) |
| `config.php` | `dsn` MySQL, `app_url` demo, `session_name` = `lspdemo_sid`, `payment_simulation` = `true`, `seed_password_hashes` berisi 8 akun demo dengan password yang **berbeda** dari produksi. AI, WhatsApp, dan `kontak_sales` kosong. |
| Kunci | `storage/app.key` dan CA tanda tangan `storage/tte/` dibuat sendiri, terpisah dari produksi. Sertifikat demo tidak bisa diverifikasi di situs produksi, dan sebaliknya. |
| Cron | `notify-worker.php` milik demo, tiap 6 menit |

Aplikasi sudah diuji penuh di MariaDB sebelum dipasang: semua suite API (1.700+ uji) dan uji UI (semua menu tiap peran, CRUD, blog, alur asesi) lulus. Satu-satunya perbedaan adalah uji `register` yang memeriksa port 8099 pada tautan email, karena salinan uji MySQL berjalan di port lain.

Membuat ulang data demo dari awal: hapus semua tabel di `semestat_demolsp` (atau buat database baru), lalu buka situs. Migrasi 1–10 membuat ulang skema dan seluruh data contoh relatif terhadap tanggal hari itu. Berkas `storage/app.key` boleh tetap dipakai.

### Rollback

Setiap patch menyimpan berkas lama di `storage/backup-<tanggal-jam>/`. Salin kembali berkas dari folder itu untuk mengembalikan versi sebelumnya.

Perubahan skema database tidak dibatalkan otomatis. Bila perlu, pulihkan dari cadangan database.

### Daftar periksa sebelum dipakai LSP sungguhan

1. Set `payment_simulation` ke `false` di `config.php`.
2. Nonaktifkan atau hapus akun demo, lalu kosongkan `seed_password_hashes`.
3. Ganti password Admin Platform dengan password baru yang kuat.
4. Isi `ai_api_key` dan pengaturan WhatsApp bila fitur itu dipakai.
5. Cadangkan `storage/app.key`, `storage/tte/`, dan `storage/*.sqlite` ke tempat terpisah, dan jadwalkan cadangan database rutin.
6. Pastikan cron `notify-worker.php` aktif dan email dari server tidak masuk spam (SPF/DKIM).
7. Umumkan sidik jari SHA-256 CA PortalLSP (atau pasang sertifikat PSrE lewat `tte_p12`) supaya penerima bisa memastikan tanda tangan digital sertifikat.

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
node tests/platform.test.mjs        # 28 uji: akses penuh Admin Platform + isolasi Admin LSP + harga paket publik
node tests/flow.test.mjs            # 115 uji: alur sertifikasi ujung-ke-ujung + isolasi
node tests/blog.test.mjs            # 83 uji: CMS blog (akses, isolasi, terjadwal, moderasi, sampul, API publik)
node tests/rbac-matrix.test.mjs     # 1.731 uji: setiap peran × setiap endpoint sesuai hak akses
node tests/crud.test.mjs            # 103 uji: buat/baca/ubah/hapus semua data (termasuk ubah pengguna) + penjaga rujukan, cakupan LSP, hak akses
node tests/security.test.mjs        # 50 uji: regresi temuan audit keamanan (putaran 1 dan 2)
node tests/reset.test.mjs           # 25 uji: lupa password (tanpa bocor email, sekali pakai, sesi lama keluar, kunci login dibuka, batas IP)
node tests/sertifikat.test.mjs      # 36 uji: PDF sertifikat (struktur, isi, lampiran), tanda tangan digital (sah, deteksi perubahan, penanda tangan, kunci), hak unduh, QR, regresi encoder QR
node tests/muk.test.mjs             # 88 uji: formulir paperless (peran, APL.01/02, verifikasi bukti, pra-asesmen, instrumen, kunci jawaban rahasia, AK.02, AK.05, Komite, banding, validasi perangkat, FR.VA, jejak audit)

NODE_PATH=$(npm root -g) node tests/ui/alur-asesi.cjs  # uji browser alur paperless: APL.01/02 → verifikasi bukti → bayar → AK.07/AK.01 → IA → AK.02 → AK.05 → Komite → sertifikat
NODE_PATH=$(npm root -g) node tests/ui/semua-menu.cjs  # uji browser: buka semua menu untuk 9 peran
NODE_PATH=$(npm root -g) node tests/ui/blog.cjs        # uji browser: tulis, pratinjau, terbitkan, sampul, baca, moderasi
NODE_PATH=$(npm root -g) node tests/ui/responsif.cjs   # audit responsif: semua halaman, berkas, dan formulir FR × 9 peran × 4 ukuran layar
NODE_PATH=$(npm root -g) node tests/ui/crud.cjs        # uji browser tombol ubah/hapus/turunkan, nonaktif asesor, ubah pengguna, ubah profil, unduh PDF
NODE_PATH=$(npm root -g) node tests/ui/reset.cjs       # uji browser lupa password: minta tautan, tautan palsu, password baru, masuk
NODE_PATH=$(npm root -g) node tests/ui/pindai-purwarupa.cjs  # pindai semua halaman publik + semua menu 8 akun: tidak boleh ada teks purwarupa, label "contoh", atau tombol tanpa aksi
```

Semua suite bisa dijalankan terhadap MySQL/MariaDB dengan mengganti `dsn` di `config.php` dan mengatur `BASE` ke alamat server uji.

Catatan: jalankan tiap suite pada database baru (`php tests/make-test-config.php` lalu hapus `storage/test.sqlite`). Suite berbagi data (mis. NIK contoh) dan batas login per IP, sehingga menjalankan semuanya berturut-turut pada satu database bisa memicu kegagalan palsu. `tests/ui/alur-asesi.cjs` membuat jadwal "hari ini", jadi jalankan dengan `TZ=Asia/Jakarta`.

Hasil terakhir (12 Oktober 2026, v17, formulir paperless), SQLite dan MariaDB (MySQL):

- Semua 2.384 uji API lulus: 49 api, 29 pendaftaran, 47 notifikasi/AI, 28 platform, 115 alur, 83 blog, 1.731 matriks RBAC, 103 CRUD, 50 keamanan, 25 lupa password, 36 sertifikat PDF, 88 formulir paperless.
- Uji browser lulus: alur paperless lengkap 41 cek (APL.01/02 di wizard, verifikasi bukti per baris, AK.07/AK.01 asesor lalu asesi, IA.05 dijawab asesi tanpa kunci, IA.01/02/03, AK.02, AK.05, Komite memutus, sertifikat, cetak berkas, perangkat dan validasi), CRUD 24, blog 24, lupa password 11, semua menu 9 peran tanpa error JavaScript, pemindai purwarupa 127 halaman.
- Audit responsif mencakup halaman berkas FR dan formulir: tidak ada scroll horizontal di 360–1024 px.

Hasil sebelumnya (12 Oktober 2026, v16), dijalankan dua kali: SQLite dan MariaDB (MySQL):

- Semua 1.813 uji API lulus di kedua database: 49 api, 29 pendaftaran, 47 notifikasi/AI, 27 platform, 108 alur, 83 blog, 1.232 matriks RBAC, 103 CRUD, 49 keamanan, 25 lupa password, 36 sertifikat PDF dan tanda tangan digital.
- Uji browser lulus di kedua database: CRUD 24, blog 24, lupa password 11, alur asesi lengkap (daftar → verifikasi → bayar → pra-asesmen → asesmen → pleno → sertifikat → verifikasi publik), dan semua menu di 7 peran tanpa error JavaScript.
- Pemindai purwarupa: 112 halaman (publik + semua menu 8 akun) tanpa teks purwarupa, label "contoh", atau tombol yang hanya memunculkan notifikasi.
- Kode QR di PDF hasil render terbaca oleh jsQR dan ZXing pada 72, 110, dan 200 dpi.
- Audit responsif: 548 tampilan di lebar 360, 390, 768, dan 1024 px; tidak ada elemen yang keluar layar dan tidak ada scroll horizontal.

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
| `admin.tdn@demo.portallsp.id` | Admin LSP — TDN (verifikasi, jadwal, perangkat MUK; **tidak** memutus sertifikasi) |
| `komite.tdn@demo.portallsp.id` | Komite Teknis — TDN (Dr. Hendro Wibowo): memutus pleno dan banding |
| `mutu.tdn@demo.portallsp.id` | Manajer Mutu — TDN (Fitri Handayani): validasi perangkat dan FR.VA |
| `marketing.tdn@demo.portallsp.id` | Marketing — TDN |
| `keuangan.tdn@demo.portallsp.id` | Keuangan — TDN |
| `admin.pbi@demo.portallsp.id` | Admin LSP — PBI |
| `tuk.kuningan@demo.portallsp.id` | Admin TUK Kuningan |
| `asesor@demo.portallsp.id` | Asesor Budi (TDN, KMD, LMP) — penyusun perangkat MUK |
| `asesor2@demo.portallsp.id` | Asesor Lina Marlina (TDN) — validator perangkat yang disusun Budi |
| `asesi@demo.portallsp.id` | Asesi Rina (TDN, PBI) — punya permohonan di berbagai tahap dan satu sertifikat |

Password akun demo **tidak** dicantumkan di dokumen ini. Password ada di berkas kredensial terpisah yang sudah dikirim ke pemilik.

**Blog:** 8 artikel contoh. Dua dari platform, enam dari LSP: tiga TDN (satu terjadwal, satu draf), satu PBI, satu KMD, satu LMP.

### Isi data demo per menu (v15)

Data demo dibuat agar setiap menu menunjukkan alur dan fungsinya. Semua tanggal dihitung relatif terhadap saat migrasi dijalankan, jadi ringkasan "bulan ini", kalender, dan pengingat tetap berisi.

| Bagian | Isi |
|---|---|
| Riwayat sertifikasi | Jadwal uji bulanan selama 11 bulan untuk tiap skema. Sekitar 280 permohonan (TDN ±150, PBI ±65, KMD ±35, LMP ±30) dengan hasil kompeten, belum kompeten, ditolak, dibatalkan, dan tidak dilanjutkan. Peserta yang sudah kompeten tidak diuji ulang pada skema yang sama. |
| Antrean berjalan | Di keempat LSP ada permohonan di setiap tahap: menunggu verifikasi, perlu perbaikan, menunggu bayar (satu lewat jatuh tempo), pra-asesmen, tidak dilanjutkan, siap uji, menunggu pleno, dan baru kompeten bulan ini. |
| Jadwal | Jadwal yang sudah lewat, kemarin (siap dinilai asesor), minggu ini, dan bulan depan; satu jadwal belum punya asesor. Hari ini sengaja dibiarkan kosong untuk skenario uji coba. |
| Sertifikat | ±190 sertifikat. Beberapa akan habis dalam 90 hari (muncul di dashboard dan Database Alumni), dua sudah kedaluwarsa, satu dicabut (bisa dicoba di Verifikasi Sertifikat). |
| Keuangan | Tagihan lunas dengan berbagai metode bayar, tagihan belum dibayar, tagihan kelas. Honor asesor terhitung per bulan per LSP. |
| Asesor demo (Budi) | Bertugas di TDN, KMD, dan LMP: kalender gabungan, pra-asesmen yang menunggu, asesi siap dinilai, berkas pleno dari penguji lain dan berkas yang ia uji sendiri, riwayat dan honor 11 bulan. |
| Asesi demo (Rina) | Permohonan di beberapa tahap, satu sertifikat, satu kelas aktif dan satu kelas selesai, profil lengkap (NIK terenkripsi). Kelas gratis dan kelas TDN/PBI lain dibiarkan untuk dicoba daftar. |
| Kelas | Lima pelatihan tayang dengan 4–8 peserta (aktif, selesai, menunggu bayar), dan satu listing pelatihan ditolak beserta alasannya. |
| Notifikasi | Notifikasi di bel tiap akun demo (sebagian belum dibaca) dan Log Notifikasi berisi pengiriman terkirim dan gagal. Semua dicatat langsung dengan status akhir, tidak ada email yang benar-benar dikirim. |
| Operasional LSP | CRM 16 prospek di empat LSP, 14 catatan mutu (termasuk banding dan keluhan), sarana prasarana di kelima TUK, group chat di jadwal yang berjalan, 5 tiket support dengan balasan. |
| Platform | Paket berbeda tiap LSP (Enterprise, Pro, Basic) dengan pemakaian kuota, tarif honor berbeda, statistik platform terisi. |

Akun contoh tambahan (`@contoh.portallsp.id`: ±130 asesi, 6 asesor, Komite Teknis PBI/KMD/LMP, Keuangan PBI, Admin LSP KMD dan LMP) memakai password acak dan tidak bisa dipakai login. Akun-akun ini hanya mengisi data. NIK mereka fiktif.

**Berkas FR demo (v17, migrasi 12):** setiap permohonan contoh punya formulir FR sesuai tahapnya (sekitar 2.500 formulir dan 4.600 tanda tangan elektronik). Contoh: permohonan `pra_asesmen` sudah punya FR.APL.01/02 yang diverifikasi; permohonan `menunggu_pleno` sudah punya AK.01, instrumen IA, dan AK.02; jadwal yang sudah selesai punya FR.AK.05 dan FR.AK.06. Setiap skema punya perangkat asesmen tervalidasi (penyusun dan validator berbeda) dan FR.VA. Ada dua banding contoh: satu menunggu keputusan Komite, satu sudah diputus. Elemen dan KUK ketujuh skema diisi dari SKKNI acuannya; setiap asesor punya nomor registrasi contoh berformat `MET.000.xxxxxx.2019`.

Log Akses Support (audit) sengaja tidak diisi data contoh: log ini catatan keamanan, jadi hanya berisi aktivitas sungguhan.

Contoh verifikasi sertifikat: buka `https://lsp.semestateknologiutama.com/?cek=TDN7K3P9QX`.

> Sebelum dipakai untuk LSP sungguhan, hapus/nonaktifkan akun demo dan kosongkan `seed_password_hashes`.

---

## 18. Batasan dan langkah berikutnya

| Hal | Kondisi saat ini | Usulan |
|---|---|---|
| Pembayaran | Simulasi (`payment_simulation`); konfirmasi manual oleh Keuangan | Integrasi payment gateway (mis. Midtrans/Xendit) + webhook |
| Email | `mail()` server | SMTP/layanan transaksional dengan SPF/DKIM |
| WhatsApp | Siap, tetapi token belum diisi | Isi `wa_driver` + `wa_token` di `config.php` |
| Asisten AI | Siap, tetapi kunci belum diisi | Isi `ai_api_key` di `config.php` |
| Sertifikat | PDF bertanda tangan digital (CA PortalLSP atau `.p12` PSrE) + kode QR; font standar (aksara non-Latin tampil `?`) | Stempel waktu TSA, CRL/OCSP, PAdES-LTV, font tertanam untuk aksara non-Latin |
| Formulir MUK | Hasil per unit (K/BK) + catatan | Formulir FR.IA lengkap per metode uji |
| Laporan BNSP | Rekap + CSV | Format sesuai template terbaru BNSP |
| LMS | Daftar kelas + progres | Materi, kuis, video |
| Database | SQLite | Pindah ke MySQL bila beban tinggi (sudah didukung) |
| Cadangan | Cadangan berkas saat patch | Cadangan DB terjadwal ke penyimpanan terpisah |
| Verifikasi nomor HP | Nomor WA tidak diverifikasi | OTP WhatsApp sebelum notifikasi dikirim |
| Tambah asesor | Admin LSP langsung menambahkan akun terdaftar | Undangan yang harus diterima asesor |
| Kunci login per email | 5 gagal mengunci email 15 menit (bisa dipakai orang lain untuk mengganggu) | CAPTCHA setelah beberapa kegagalan |
| Progres kelas | Diisi sendiri oleh asesi | Dihitung dari materi/kuis LMS |

---

## 19. Panduan singkat pemakaian

### Asesi

1. Buka portal → pilih skema → **Daftar**.
2. Bila belum punya akun, daftar dengan email lalu klik tautan verifikasi di email.
3. Ikuti wizard: jadwal → data diri → APL.02 → unggah dokumen → persetujuan → **Ajukan**.
4. Pantau status di **Jadwal Saya**. Bila diminta perbaikan, perbaiki lalu kirim ulang.
5. Setelah diterima, bayar di **Pembayaran**.
6. Ikuti uji sesuai jadwal. Setelah pleno, sertifikat muncul di **Dompet Sertifikat**.
7. Di Dompet Sertifikat: **Unduh PDF** (sertifikat bertanda tangan digital dengan kode QR), **Lihat PDF**, **Cetak**, atau **Salin tautan verifikasi** untuk dibagikan ke pemberi kerja.

### Admin LSP

1. **Pendaftaran:** buka berkas → periksa dokumen dan APL.02 → Terima / Perbaikan / Tolak.
2. **Jadwal & Penugasan:** buat jadwal → tetapkan asesor.
3. **Asesmen:** pantau progres.
4. **Pleno & Sertifikat:** putuskan K/BK. Sertifikat terbit otomatis; tombol **PDF** dan **Cetak** tersedia di daftar sertifikat dan di **Database Alumni**.
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
