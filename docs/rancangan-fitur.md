# Rancangan Fitur Aplikasi Sistem Informasi LSP

Referensi pembanding: sales deck NAS (PT Nusantara Aplikasi Sertimedia), 2023.

Legenda:
- ✅ = sudah ada di NAS (wajib ada juga di aplikasi kita)
- 🆕 = belum ada / tidak disebut di NAS, usulan tambahan sebagai pembeda
- Prioritas: **P1** = MVP, **P2** = rilis kedua, **P3** = pengembangan lanjutan

---

## 0. Arsitektur Multi-LSP (Multi-Tenant): fondasi utama

Satu aplikasi dipakai oleh banyak LSP. Setiap LSP adalah **tenant**. Aturan dasarnya:

> **Setiap user terikat ke satu LSP. User hanya bisa membuka data LSP miliknya sendiri dan tidak bisa melihat data LSP lain, termasuk lewat URL, API, file, laporan, maupun pencarian.**
>
> **Pengecualian: Asesor dan Asesi** boleh terdaftar di lebih dari satu LSP dengan **satu akun**:
> - **Asesor** hanya bisa melihat data uji kompetensi yang **ditugaskan kepadanya** (bagian 0.8).
> - **Asesi** hanya bisa melihat **permohonan, asesmen, dan sertifikat miliknya sendiri** (bagian 0.9).

### 0.1 Hierarki data

```
Platform (Super Admin)
 └── LSP  (tenant, lsp_id)
      ├── User LSP: Admin LSP, Manajer Mutu, Komite, Pleno, Keuangan
      ├── TUK ── Admin TUK            (hanya TUK miliknya, di dalam LSP-nya)
      ├── Asesor (bisa di banyak LSP) (hanya uji kompetensi yang ditugaskan kepadanya)
      ├── Asesi  (bisa di banyak LSP) (hanya permohonan/asesmen/sertifikat miliknya)
      ├── Mitra/Sponsor               (hanya peserta yang ia daftarkan)
      └── Skema, MUK, Jadwal, Asesmen, Sertifikat, Dokumen, Keuangan, Mutu ...
```

Isolasi berlapis: **antar-LSP** (wajib, mutlak), lalu **di dalam LSP** per role (TUK, asesor, asesi, mitra hanya melihat bagiannya).

### 0.2 Data LSP (tabel `lsp`)

| Kelompok | Field |
|---|---|
| Identitas | Nama LSP, singkatan, jenis LSP (P1 / P2 / P3), NPWP, alamat, provinsi/kota, telepon, email, website |
| Lisensi BNSP | Nomor SK lisensi, tanggal terbit, **masa berlaku** (dengan pengingat), ruang lingkup skema, file SK |
| Organisasi | Ketua/Direktur, Manajer Sertifikasi, Manajer Mutu, Manajer Administrasi, struktur organisasi |
| Branding | Logo, warna, **subdomain** (mis. `lsp-abc.aplikasi.id`), custom domain (opsional), kop surat, template sertifikat |
| Pengesahan | Spesimen tanda tangan & stempel (untuk dokumen & sertifikat), nomor urut dokumen/sertifikat per LSP |
| Keuangan | Rekening bank, akun payment gateway milik LSP |
| Langganan | Paket, status (trial / aktif / ditangguhkan / berhenti), masa langganan, kuota (jumlah asesi, storage, user) |
| Pengaturan | Zona waktu, format penomoran, kanal notifikasi, template notifikasi |

### 0.3 Aturan kepemilikan data

1. **Setiap tabel bisnis wajib punya kolom `lsp_id`** (NOT NULL + foreign key ke `lsp`): keanggotaan user, TUK, keanggotaan asesor, asesi, skema, MUK, bank soal, jadwal, asesmen, formulir FR, pleno, sertifikat, banding, keluhan, dokumen mutu, tagihan, notifikasi, log audit.
2. **Akun dan keanggotaan dipisah**: tabel `akun` (identitas login) dan tabel `keanggotaan` (`akun_id`, `lsp_id`, `role`, status). Role internal LSP (Admin LSP, Manajer, Komite, Pleno, Keuangan, Admin TUK) dan Mitra **hanya boleh punya satu keanggotaan**. **Asesor dan Asesi boleh punya banyak keanggotaan** (satu per LSP). Konteks LSP aktif **diambil dari sesi login, tidak pernah dari input/form/URL**.
3. **Keunikan data bersifat per LSP**: email, nomor registrasi, nomor sertifikat, kode skema, kode TUK, dsb. unik di dalam `(lsp_id, ...)`, bukan global.
4. **Asesor dan Asesi di beberapa LSP = satu akun, banyak keanggotaan.** Profil pribadi disimpan sekali (global, milik orangnya). Data operasional (permohonan, penugasan, rekaman asesmen, pembayaran, honor, sertifikat) tetap **milik masing-masing LSP**.
5. **Satu orang = satu akun**: NIK unik secara global pada profil asesor/asesi. Saat mendaftar dengan NIK yang sudah ada, sistem mengarahkan ke login (bukan membuat akun ganda). Kepemilikan akun dibuktikan lewat OTP email/HP; ada alur sengketa jika NIK dipakai orang lain.
6. **Berkas yang diajukan ke LSP dibekukan (snapshot)**: saat asesi mengajukan permohonan atau asesor diverifikasi, salinan dokumen dari profil disimpan di folder LSP tersebut. Jika profil diubah kemudian, bukti audit di LSP tidak ikut berubah.
7. Data LSP tidak pernah dihapus permanen secara langsung: LSP yang berhenti langganan → **ditangguhkan → ekspor data untuk LSP → dihapus** sesuai kebijakan retensi (UU PDP).

### 0.4 Penegakan isolasi (wajib berlapis, bukan hanya di tampilan)

| Lapisan | Mekanisme |
|---|---|
| **Login & sesi** | User LSP: LSP dikenali dari subdomain, login hanya berhasil jika akun punya keanggotaan aktif di LSP tersebut; sesi menyimpan `lsp_id`. Asesor & Asesi: login sekali (dari subdomain LSP mana pun atau portal pusat), sesi menyimpan **daftar LSP tempat ia aktif**; akses per data tetap dicek ke penugasan (asesor) atau kepemilikan (asesi). |
| **Aplikasi (backend)** | Middleware tenant + *global scope* otomatis `WHERE lsp_id = :lsp_id_sesi` di semua query. Developer tidak perlu (dan tidak boleh) menulis filter manual. |
| **Database** | **PostgreSQL Row-Level Security (RLS)** di setiap tabel: koneksi men-set `app.current_lsp_id` (user LSP) `app.current_asesor_id` (asesor), atau `app.current_asesi_id` (asesi), dan DB menolak baris di luar haknya walaupun ada bug di kode aplikasi. Untuk asesor, kebijakan RLS berbasis **tabel penugasan**; untuk asesi, berbasis **pemilik permohonan**, bukan sekadar `lsp_id`. |
| **Penyimpanan file** | Folder/bucket terpisah per LSP (`/lsp/{lsp_id}/...`), akses file hanya lewat *signed URL* berumur pendek yang dibuat setelah cek kepemilikan. Tidak ada URL file publik permanen. |
| **ID data** | Pakai UUID (bukan angka urut) agar ID tidak bisa ditebak. Akses ke ID milik LSP lain dibalas **404 Not Found** (bukan 403), sehingga keberadaan data tidak bocor. |
| **Proses latar belakang** | Antrean job (notifikasi, generate PDF, laporan BNSP, impor Excel) selalu membawa `lsp_id` dan menjalankan query dalam konteks LSP tersebut. |
| **Cache & pencarian** | Kunci cache dan indeks pencarian diberi prefiks/filter `lsp_id`. |
| **Laporan & ekspor** | Semua dashboard, ekspor Excel/PDF, dan laporan BNSP hanya berisi data LSP sesi. |
| **Notifikasi** | Email/WA dikirim atas nama LSP masing-masing; tautan di dalamnya mengarah ke subdomain LSP tersebut. |

### 0.5 Super Admin Platform

- Mengelola daftar LSP: **onboarding LSP baru** (buat tenant → buat akun Admin LSP pertama → kirim undangan), paket, penangguhan, pemantauan kuota.
- **Secara default tidak bisa membaca data operasional LSP** (asesi, nilai, dokumen). Hanya melihat statistik agregat (jumlah user, asesmen, storage).
- Akses untuk keperluan *support* hanya lewat fitur **"masuk sebagai" (impersonate) dengan izin Admin LSP**, berbatas waktu, dan **tercatat di log audit yang bisa dilihat oleh LSP tersebut**.

### 0.6 Satu-satunya pengecualian lintas LSP

- **Halaman publik verifikasi sertifikat** (scan QR / cek nomor): hanya menampilkan data minimum (nama pemegang, skema, LSP penerbit, status & masa berlaku). Tidak ada daftar atau pencarian bebas.
- Data agregat anonim untuk Super Admin (tanpa data pribadi).

### 0.7 Pengujian isolasi (wajib sebelum rilis)

- Test otomatis untuk **setiap endpoint**: user LSP A mencoba membaca/mengubah/menghapus data LSP B → harus 404.
- Test asesor: asesor yang anggota LSP A & B mencoba membuka asesmen yang **tidak ditugaskan kepadanya** (di LSP A, LSP B, maupun LSP C) → harus 404.
- Test LSP A tidak bisa melihat penugasan, honor, atau riwayat asesor tersebut di LSP B.
- Test asesi: asesi X mencoba membuka permohonan/asesmen/sertifikat asesi Y (di LSP yang sama maupun berbeda) → harus 404.
- Test LSP A tidak bisa melihat permohonan, nilai, pembayaran, atau sertifikat asesi tersebut di LSP B (kecuali sertifikat yang secara sadar dilampirkan asesi).
- Test unggah/unduh file lintas LSP, test ekspor laporan, test job antrean.
- Penetration test pihak ketiga sebelum go-live dan setiap tahun.

### 0.8 Asesor di banyak LSP

Satu asesor bisa bekerja untuk beberapa LSP. Ia memakai **satu akun**, tetapi **hanya bisa melihat data uji kompetensi yang ia tangani sendiri**.

**Struktur data**

| Tabel | Isi | Pemilik |
|---|---|---|
| `akun` | Email, nomor HP, password, 2FA | Asesor |
| `profil_asesor` (global) | NIK, nama, foto, **No. Reg MET**, sertifikat asesor BNSP & masa berlakunya, sertifikat kompetensi, pendidikan, pengalaman, CV | Asesor (diisi sekali, dipakai di semua LSP) |
| `keanggotaan_asesor` (per LSP) | `lsp_id`, nomor/SK penugasan dari LSP, skema yang boleh diujikan di LSP itu, status (diundang / aktif / nonaktif), **status verifikasi oleh LSP**, rekening & NPWP untuk honor LSP itu, kontrak | LSP |
| `penugasan` (per LSP) | `lsp_id`, `asesor_id`, jadwal asesmen, peran (asesor utama / anggota / pleno / validator) | LSP |

**Yang BISA dilihat asesor**

- Daftar LSP tempat ia aktif, dan **kalender gabungan** semua jadwalnya dari seluruh LSP tersebut.
- Untuk setiap **jadwal yang ditugaskan kepadanya**: data asesi pada jadwal itu, berkas persyaratan & APL, skema & MUK yang dipakai, formulir FR yang ia isi, bukti asesmen, rekaman SJJ, group chat jadwal tersebut.
- **Riwayat** asesmen yang pernah ia tangani (hanya baca setelah keputusan pleno).
- Honor miliknya sendiri di masing-masing LSP.
- Jika ditugaskan sebagai **pleno**: hanya berkas asesmen yang diplenokan kepadanya, dan **sistem menolak** jika ia juga asesor pada asesmen tersebut.

**Yang TIDAK BISA dilihat asesor**

- Asesmen, asesi, dan jadwal yang **tidak ditugaskan kepadanya**, walaupun di LSP yang sama.
- Data asesor lain, data keuangan LSP, dokumen mutu, laporan, dan dashboard LSP.
- Apa pun dari LSP yang keanggotaannya sudah **nonaktif** (riwayat yang ia buat tetap tersimpan di LSP itu).

**Yang bisa dilihat LSP tentang asesor**

- Profil global asesor (karena dibutuhkan untuk verifikasi kelayakan) dan data keanggotaan di LSP-nya sendiri.
- **Tidak bisa** melihat penugasan, honor, asesi, atau riwayat asesor di LSP lain.
- Saat menjadwalkan, sistem hanya memberi tahu **"asesor tidak tersedia pada tanggal ini"** jika bentrok dengan jadwal di LSP lain, tanpa menyebut nama LSP atau detail jadwalnya.

**Alur bergabung ke LSP**

1. Admin LSP mengundang asesor dengan **NIK + email/No. Reg MET yang persis sama** (tidak ada fitur menjelajah/mencari semua asesor di platform, demi privasi).
2. Jika asesor belum punya akun → ia mendaftar dan mengisi profil global. Jika sudah punya → profil yang ada dipakai.
3. Asesor **menyetujui** undangan (persetujuan berbagi profil dengan LSP tersebut, sesuai UU PDP).
4. LSP **memverifikasi** dokumen asesor dan menentukan skema yang boleh ia ujikan → keanggotaan aktif.
5. Jika profil global berubah (mis. sertifikat MET diperpanjang), **semua LSP tempat ia aktif mendapat notifikasi** untuk verifikasi ulang.

**Aturan akses (ringkas)**

```
asesor boleh membuka data X  ⇔
    keanggotaan_asesor(asesor, X.lsp_id).status = aktif
AND ada penugasan(asesor, X.jadwal_id)
```

### 0.9 Asesi di banyak LSP

Satu asesi bisa mengikuti uji kompetensi di beberapa LSP. Ia memakai **satu akun**, tetapi **hanya bisa melihat data miliknya sendiri**, dan **setiap LSP hanya melihat permohonan yang diajukan ke LSP tersebut**.

**Struktur data**

| Tabel | Isi | Pemilik |
|---|---|---|
| `akun` | Email, nomor HP, password, 2FA (opsional) | Asesi |
| `profil_asesi` (global) | NIK, nama sesuai KTP, tempat/tanggal lahir, jenis kelamin, alamat, pendidikan terakhir, pekerjaan/instansi, pas foto, **dokumen pribadi** (KTP, ijazah, transkrip, sertifikat pelatihan, CV) | Asesi (diisi sekali, dipakai ulang saat mendaftar ke LSP mana pun) |
| `keanggotaan_asesi` (per LSP) | `lsp_id`, nomor registrasi asesi di LSP itu, persetujuan berbagi data, status | LSP |
| `permohonan` (per LSP) | `lsp_id`, `asesi_id`, skema, APL.01/APL.02, **snapshot berkas** yang diajukan, pembayaran, jadwal, hasil asesmen, banding, sertifikat | LSP |

**Yang BISA dilihat asesi**

- Daftar LSP tempat ia pernah/sedang mendaftar.
- **Dashboard gabungan**: semua permohonan & status real-time dari seluruh LSP, jadwal uji, tagihan.
- **Dompet sertifikat**: semua sertifikat miliknya dari semua LSP, beserta masa berlaku dan pengingat perpanjangan.
- Per permohonan: berkas yang ia ajukan, formulir yang perlu ia isi, jadwal, asesor & TUK yang ditugaskan, hasil keputusan, umpan balik, banding, invoice/kuitansi, group chat jadwalnya.

**Yang TIDAK BISA dilihat asesi**

- Data asesi lain (termasuk peserta satu jadwal, kecuali nama di daftar hadir/group chat bila LSP mengizinkan).
- Catatan internal asesor/pleno sebelum keputusan diumumkan, bank soal & kunci jawaban, data internal LSP.

**Yang bisa dilihat LSP tentang asesi**

- **Hanya** profil asesi yang dibagikan saat mendaftar ke LSP tersebut, beserta permohonan-permohonan ke LSP itu.
- **Tidak bisa** melihat permohonan, nilai, pembayaran, atau sertifikat asesi di LSP lain.
- **Pengecualian dengan persetujuan**: jika skema mensyaratkan sertifikat sebelumnya (mis. jenjang lanjutan, RPL, perpanjangan), asesi dapat **memilih sendiri** sertifikat dari dompetnya untuk dilampirkan. Yang terlampir hanya sertifikat itu, dan keasliannya dicek otomatis lewat verifikasi QR.
- Tidak ada fitur menjelajah/mencari semua asesi di platform.

**Alur pendaftaran**

1. Asesi membuka situs/subdomain LSP → klik daftar.
2. Jika NIK/email sudah terdaftar → diminta login. Jika belum → buat akun & isi profil global.
3. Asesi **menyetujui berbagi profil** dengan LSP tersebut (UU PDP) → keanggotaan dibuat.
4. Asesi memilih skema, mengisi APL.01/APL.02, memilih berkas dari profil atau unggah baru → **berkas dibekukan sebagai snapshot** di folder LSP tersebut.
5. Pendaftaran massal oleh mitra/sponsor: jika peserta sudah punya akun, ia menerima undangan untuk menautkan; data baru terlihat oleh LSP setelah peserta menyetujui.

**Hapus akun & retensi**

- Asesi bisa meminta hapus akun/profil global (hak subjek data UU PDP).
- **Rekaman asesmen & sertifikat yang dipegang LSP tetap disimpan** sesuai kewajiban retensi rekaman sertifikasi, lalu dihapus otomatis setelah masa retensi berakhir.

**Aturan akses (ringkas)**

```
asesi boleh membuka data X  ⇔  X.asesi_id = asesi yang login
LSP  boleh membuka data asesi ⇔  ada keanggotaan_asesi(asesi, LSP) yang disetujui
                                 dan data tersebut milik/diajukan ke LSP itu
```

---

## 1. Peran pengguna (role)

Semua role di bawah **terikat ke satu LSP**, kecuali *Super Admin Platform*, *Publik*, serta **Asesor dan Asesi** (bisa di banyak LSP, lihat bagian 0.8 & 0.9).

| Role | Keterangan | Status |
|---|---|---|
| Super Admin Platform | Pengelola SaaS: onboarding LSP, paket langganan, billing, monitoring (tidak membaca data operasional LSP, lihat bagian 0.5) | 🆕 |
| Admin LSP | Operasional LSP | ✅ |
| Manajer Mutu / Manajer Sertifikasi | Pengendalian mutu, audit internal, kaji ulang manajemen | 🆕 |
| Komite Skema | Penyusunan & kaji ulang skema | 🆕 |
| Komite Ketidakberpihakan | Analisis risiko ketidakberpihakan | 🆕 |
| Tim Pleno / Pengambil Keputusan | Keputusan sertifikasi (harus pihak yang tidak menguji) | ✅ |
| Admin TUK | Operasional TUK | ✅ |
| Asesor | Pelaksana asesmen; satu akun bisa aktif di banyak LSP, hanya melihat uji kompetensi yang ditugaskan kepadanya | ✅ (multi-LSP 🆕) |
| Asesi | Peserta uji; satu akun bisa mendaftar di banyak LSP, hanya melihat permohonan/asesmen/sertifikat miliknya | ✅ (multi-LSP 🆕) |
| Keuangan | Tagihan, pembayaran, honor asesor | 🆕 |
| Marketing / Sales LSP | Mengelola CRM: lead, pipeline mitra, follow-up, kampanye (bagian 14) | 🆕 |
| Mitra / Sponsor (Pemda, BUMN, perusahaan, kampus) | Mendaftarkan peserta massal dan memantau hasilnya | 🆕 |
| Pemberi Kerja / Publik | Verifikasi keaslian sertifikat | 🆕 |

---

## 2. Modul Master Data & Organisasi LSP

| Fitur | Status | Prioritas |
|---|---|---|
| Profil LSP, struktur organisasi, SDM, lisensi BNSP & masa berlakunya | ✅ | P1 |
| Manajemen data TUK (sewaktu, tempat kerja, mandiri) | ✅ | P1 |
| Manajemen data asesor (No. Reg MET, masa berlaku sertifikat asesor, bidang/skema yang dikuasai) | ✅ | P1 |
| Manajemen akun pengguna | ✅ | P1 |
| **Manajemen data pemohon/asesi** (sisi admin): daftar pemohon, cek & verifikasi berkas, terima / tolak / minta perbaikan dengan catatan, riwayat permohonan | ✅ | P1 |
| **Database alumni**: arsip per alumni berisi rekaman dokumen asesmen (APL, FR, bukti, keputusan, sertifikat), bisa dicari & difilter (skema, tahun, TUK, instansi) | ✅ | P1 |
| **Pengingat masa berlaku**: lisensi LSP, sertifikat asesor (MET), sertifikat kompetensi asesor, verifikasi TUK | 🆕 | P1 |
| **Ketersediaan / kalender asesor** (asesor mengisi tanggal tersedia) | 🆕 | P2 |
| **Deklarasi konflik kepentingan** asesor per asesi/jadwal (blok otomatis jika asesor pernah melatih asesi atau satu instansi) | 🆕 | P1 |
| **Inventaris sarana-prasarana TUK** per skema (alat, bahan, ruang) | 🆕 | P2 |
| Rotasi asesor & beban kerja asesor | 🆕 | P3 |
| **Logbook / portofolio asesor**: rekap jumlah & jenis asesmen per tahun dari **semua LSP tempat ia bertugas** (hanya terlihat oleh asesor sendiri), siap diunduh sebagai bukti perpanjangan sertifikat asesor (RCC MET) | 🆕 | P2 |
| **Rapat teknis / kalibrasi asesor** sebelum uji (penyamaan persepsi MUK), notulen & daftar hadir | 🆕 | P2 |
| Evaluasi kinerja asesor oleh LSP (nilai dari umpan balik asesi, ketepatan waktu, kelengkapan dokumen) + catatan kode etik & sanksi | 🆕 | P3 |
| Kartu identitas digital asesor (QR) | 🆕 | P3 |

### 2.1 Hak akses Admin TUK

Admin TUK memakai web yang sama dengan Admin LSP, tetapi menunya terbatas dan **datanya hanya untuk TUK miliknya** (di dalam LSP-nya). Fitur bertanda * di deck NAS:

| Fitur | Admin LSP | Admin TUK |
|---|---|---|
| Manajemen data LSP, asesor, skema, akun pengguna, pleno, laporan BNSP | ✅ | ❌ |
| Manajemen data TUK (profil, sarana-prasarana, dokumen verifikasi) | ✅ semua TUK | ✅ TUK sendiri |
| Manajemen data pemohon | ✅ semua | ✅ pemohon yang mendaftar di TUK-nya |
| Group chat asesmen | ✅ semua jadwal | ✅ jadwal di TUK-nya |
| Tinjau proses sertifikasi | ✅ semua | ✅ asesi di TUK-nya |
| Database alumni | ✅ semua | ✅ alumni yang diuji di TUK-nya |
| Usulan jadwal asesmen (disetujui Admin LSP) | ✅ | ✅ |

### 2.2 Data referensi global & migrasi data

| Fitur | Status | Prioritas |
|---|---|---|
| **Pustaka unit kompetensi global** (SKKNI, SKK Khusus) yang dikelola Super Admin; LSP cukup **mengimpor** unit ke skemanya, tidak mengetik ulang | 🆕 | P2 |
| Data referensi bersama: provinsi/kab/kota/kecamatan, jenjang pendidikan, bidang/sektor, jenjang KKNI, bank | 🆕 | P1 |
| **Migrasi data dari sistem lama / Excel** saat onboarding LSP: asesor, asesi, alumni, sertifikat, skema, TUK (dengan validasi & laporan baris gagal) | 🆕 | P1 |
| Impor data massal kapan saja via template Excel (asesi, asesor, peserta jadwal, soal) | 🆕 | P1 |

## 3. Modul Skema Sertifikasi

| Fitur | Status | Prioritas |
|---|---|---|
| Upload skema: unit kompetensi (kode SKKNI/SKK Khusus/Internasional), elemen, KUK, persyaratan dasar | ✅ | P1 |
| Upload MUK (Materi Uji Kompetensi) per skema | ✅ | P1 |
| Status lisensi skema (termasuk ruang lingkup lisensi BNSP atau belum) & masa berlakunya | ✅ | P1 |
| **Workflow penyusunan skema**: draft → kaji komite skema → validasi → pengajuan ke BNSP → aktif → kaji ulang berkala | 🆕 | P2 |
| **Versi skema & MUK** (riwayat perubahan; asesmen lama tetap merujuk versi yang dipakai saat itu) | 🆕 | P1 |
| **Biaya per skema** & per jenis asesmen (baru / RPL / perpanjangan) | 🆕 | P1 |
| **Validasi instrumen asesmen (FR.VA)** sebelum dipakai | 🆕 | P2 |
| Pemetaan skema ke jenjang KKNI & jabatan | 🆕 | P3 |

## 4. Modul Pendaftaran & Pra-Asesmen (Asesi)

| Fitur | Status | Prioritas |
|---|---|---|
| Registrasi & pengajuan skema (FR.APL.01) | ✅ | P1 |
| Upload berkas persyaratan & pendukung | ✅ | P1 |
| Asesmen mandiri (FR.APL.02) | ✅ | P1 |
| Status asesmen real-time | ✅ | P1 |
| **Tinjau pra-asesmen oleh asesor**: asesor melihat daftar peserta uji di jadwalnya, meninjau APL.01/APL.02 & bukti, lalu memberi rekomendasi (asesmen dapat dilanjutkan / tidak / perlu bukti tambahan) | ✅ | P1 |
| **Tinjau proses sertifikasi** (sisi admin): timeline tahap tiap asesi (daftar → verifikasi berkas → bayar → pra-asesmen → asesmen → pleno → sertifikat), dengan penanda yang tertahan terlalu lama | ✅ | P1 |
| **Verifikasi identitas**: OCR KTP/ijazah, cek NIK (format & duplikasi), swafoto | 🆕 | P2 |
| **Cek kelengkapan berkas otomatis** (berkas kurang/buram/kedaluwarsa ditandai sebelum ke admin) | 🆕 | P2 |
| **Pendaftaran massal** via template Excel / tautan undangan untuk mitra (program Pemda, CSR, BUMN, kampus) | 🆕 | P1 |
| **Kuota & kode voucher kerja sama** (peserta dibiayai sponsor) | 🆕 | P2 |
| **Pembayaran online** (payment gateway: VA, QRIS, e-wallet), invoice & kuitansi otomatis | 🆕 | P1 |
| Penyesuaian yang beralasan untuk asesi berkebutuhan khusus (FR.AK.07) | 🆕 | P2 |
| **Jalur RPL (Rekognisi Pembelajaran Lampau)**: asesi memilih jalur portofolio, sistem menampilkan bukti yang dibutuhkan per unit, asesor memverifikasi tanpa uji praktik penuh | 🆕 | P2 |
| Simpan draf pendaftaran & lanjutkan nanti | 🆕 | P1 |

## 5. Modul Penjadwalan

| Fitur | Status | Prioritas |
|---|---|---|
| Create jadwal asesmen (TUK, asesor, peserta) | ✅ | P1 |
| Jadwal pleno & jadwal banding | ✅ | P1 |
| **Saran asesor otomatis** (berdasarkan skema, lokasi, ketersediaan, bebas konflik kepentingan) | 🆕 | P2 |
| **Validasi bentrok jadwal** asesor/TUK/ruang | 🆕 | P1 |
| **Rasio asesor : asesi** sesuai ketentuan, sistem menolak jika terlampaui | 🆕 | P1 |
| Sinkronisasi ke Google Calendar / Outlook (file .ics) | 🆕 | P3 |
| **Jadwal uji terbuka & pilih jadwal sendiri**: asesi memilih jadwal yang tersedia saat mendaftar (tampil juga di website LSP) | 🆕 | P1 |
| **Kuota peserta per jadwal** + daftar tunggu (waiting list) otomatis naik bila ada yang batal | 🆕 | P2 |
| **Reschedule & pembatalan** oleh asesi/LSP dengan aturan batas waktu, tercatat riwayatnya | 🆕 | P1 |
| **Kartu peserta uji** (PDF/QR) untuk check-in di TUK | 🆕 | P1 |
| Kapasitas ruang & jadwal pemakaian ruang TUK | 🆕 | P2 |

## 6. Modul Verifikasi TUK

| Fitur | Status | Prioritas |
|---|---|---|
| Verifikasi TUK | ✅ | P1 |
| **Checklist verifikasi digital** + foto ber-geotag & timestamp sebagai bukti | 🆕 | P1 |
| Status TUK (terverifikasi / perlu perbaikan / ditolak) dengan masa berlaku | 🆕 | P2 |

## 7. Modul Pelaksanaan Asesmen

Mendukung tiga metode: **paper-based, paperless, dan Sertifikasi Jarak Jauh (SJJ)** ✅

| Fitur | Status | Prioritas |
|---|---|---|
| Asesor membaca & mengisi dokumen MUK | ✅ | P1 |
| Metode uji observasi demonstrasi, tanya jawab, verifikasi portofolio, wawancara | ✅ | P1 |
| Integrasi virtual meeting & rekaman | ✅ | P1 |
| **Rekaman virtual meeting tersimpan di database/cloud LSP** dan **otomatis terlampir** di berkas asesi sebagai kelengkapan pengajuan jadwal pleno (wajib untuk SJJ) | ✅ | P1 |
| Group chat asesmen | ✅ | P2 |
| Asesmen ulang | ✅ | P1 |
| **Paket formulir lengkap**: FR.APL.01–02, FR.MAPA.01–02, FR.AK.01–07, FR.IA.01–11 | 🆕 (NAS hanya menyebut "MUK") | P1 |
| **CBT / ujian tertulis online**: bank soal, acak soal & opsi, timer, auto-scoring pilihan ganda | 🆕 | P1 |
| **Analisis butir soal** (tingkat kesukaran, daya beda) | 🆕 | P3 |
| **Proctoring SJJ**: verifikasi wajah sebelum mulai, deteksi pindah tab, snapshot kamera berkala, log kecurangan | 🆕 | P2 |
| **Mode offline** di aplikasi asesor (TUK di lokasi tanpa sinyal, sinkron setelah online) | 🆕 | P2 |
| **Upload bukti multimedia** (foto/video hasil kerja praktik) langsung dari HP asesor | 🆕 | P1 |
| **Tanda tangan elektronik** asesor, asesi, dan pleno (TTE tersertifikasi via PSrE, mis. Privy, Peruri, VIDA) | 🆕 | P1 |
| Umpan balik asesi (FR.AK.03) & penilaian asesor oleh asesi | 🆕 | P2 |
| Daftar hadir digital (QR code / geotag) | 🆕 | P2 |

## 8. Modul Keputusan, Sertifikat & Pelaporan BNSP

| Fitur | Status | Prioritas |
|---|---|---|
| Penentuan komite/tim pleno | ✅ | P1 |
| Tinjau & beri status keputusan (pleno) | ✅ | P1 |
| Auto-generate laporan BNSP / BAPS | ✅ | P1 |
| Pengajuan blanko ke BNSP | ✅ | P1 |
| **Validasi independensi pleno** (anggota pleno tidak boleh asesor dari asesmen yang sama) | 🆕 | P1 |
| **Penerbitan sertifikat digital + QR code verifikasi** | 🆕 | P1 |
| **Halaman publik verifikasi sertifikat** (cek nomor/QR, tanpa membuka data pribadi berlebih) | 🆕 | P1 |
| Pelacakan blanko (diajukan → dicetak → diterima → dikirim ke asesi, dengan nomor resi) | 🆕 | P2 |
| Badge digital yang bisa dibagikan ke LinkedIn | 🆕 | P3 |
| **Impor nomor registrasi & nomor blanko dari BNSP** ke data sertifikat asesi | 🆕 | P1 |
| **Cetak sertifikat di blanko BNSP** dengan kalibrasi posisi cetak (geser margin per printer), cetak massal | 🆕 | P1 |
| Asesi mengunduh sertifikat digital & dokumen hasil asesmen dari aplikasi | 🆕 | P1 |
| Sertifikat dwibahasa (Indonesia–Inggris) | 🆕 | P3 |

### 8.1 Persuratan & dokumen otomatis

| Fitur | Status | Prioritas |
|---|---|---|
| Generate otomatis: **surat tugas asesor**, **SK tim pleno**, undangan asesi, berita acara asesmen, daftar hadir, **BAPS**, surat keterangan sedang proses sertifikasi | 🆕 | P1 |
| **Penomoran surat otomatis** sesuai format LSP (nomor/kode/bulan romawi/tahun) | 🆕 | P1 |
| Template surat bisa diubah per LSP (kop, isi, penanda tangan) + TTE | 🆕 | P2 |
| Arsip surat masuk & surat keluar | 🆕 | P2 |

## 9. Modul Banding, Keluhan, Surveilans & Re-sertifikasi

| Fitur | Status | Prioritas |
|---|---|---|
| Pengajuan & rekaman dokumen banding (FR.AK.04) | ✅ | P1 |
| Surveillance asesi & re-lisensi | ✅ | P2 |
| Notifikasi hingga masa berlaku sertifikat habis | ✅ | P1 |
| **Manajemen keluhan** (keluhan publik/asesi/pemberi kerja, SLA, tindak lanjut) | 🆕 | P1 |
| **Formulir surveilans pemegang sertifikat** (logbook, bukti kerja, survei ke pemberi kerja) | 🆕 | P2 |
| **Re-sertifikasi via RCC** (Recognition of Current Competency) dengan alur lebih ringkas | 🆕 | P2 |
| Pencabutan / pembekuan sertifikat dan tampil di halaman verifikasi | 🆕 | P2 |

## 10. Modul Sistem Manajemen Mutu (Pedoman BNSP 201) — **pembeda utama**

NAS hanya menyebut "dokumen manajemen". Untuk LSP, modul mutu inilah yang paling dicari saat **surveilans/witness BNSP**.

| Fitur | Status | Prioritas |
|---|---|---|
| Pengendalian dokumen (nomor, versi, pengesahan, distribusi, dokumen kedaluwarsa) | ✅ (sebagian) | P1 |
| Kode penyimpanan dokumen fisik | ✅ | P2 |
| **Audit internal**: rencana, checklist, temuan, ketidaksesuaian | 🆕 | P2 |
| **Tindakan perbaikan & pencegahan (CAPA)** dengan tenggat & PIC | 🆕 | P2 |
| **Kaji ulang manajemen**: agenda, notulen, data otomatis (jumlah asesmen, keluhan, banding, temuan) | 🆕 | P2 |
| **Analisis risiko ketidakberpihakan** & notulen komite ketidakberpihakan | 🆕 | P2 |
| Pakta kerahasiaan & ketidakberpihakan seluruh personel (tanda tangan digital) | 🆕 | P1 |
| Monitoring kinerja asesor (witness internal, konsistensi keputusan) | 🆕 | P3 |
| **Checklist kesiapan surveilans BNSP** (dokumen apa yang sudah/belum lengkap) | 🆕 | P2 |
| **Jadwal surveilans/witness BNSP & tindak lanjut temuan BNSP** (temuan → CAPA → bukti penutupan) | 🆕 | P2 |
| Notulen rapat (komite skema, ketidakberpihakan, rapat manajemen) dengan daftar hadir & TTE | 🆕 | P2 |
| **Jadwal retensi arsip**: masa simpan per jenis rekaman, pemusnahan terkontrol dengan berita acara | 🆕 | P3 |
| Sasaran mutu & indikator kinerja LSP (target vs realisasi) | 🆕 | P3 |

## 11. Modul Keuangan

| Fitur | Status | Prioritas |
|---|---|---|
| Tagihan asesi & mitra (perorangan / kolektif) | 🆕 | P1 |
| Payment gateway + rekonsiliasi otomatis | 🆕 | P1 |
| **Honor asesor** (per asesi / per jadwal), slip honor, rekap PPh 21 | 🆕 | P2 |
| Biaya blanko BNSP & biaya sewa TUK | 🆕 | P2 |
| Laporan pendapatan per skema/TUK/periode, ekspor ke akuntansi | 🆕 | P2 |
| E-Faktur / PPN bila diperlukan | 🆕 | P3 |
| **Pembatalan & refund** sesuai kebijakan LSP (penuh / sebagian / jadi saldo untuk jadwal lain) | 🆕 | P2 |
| Cicilan / pembayaran bertahap untuk tagihan kolektif mitra | 🆕 | P3 |
| Integrasi software akuntansi (mis. Jurnal, Accurate) | 🆕 | P3 |

## 12. Dashboard & Analitik

| Fitur | Status | Prioritas |
|---|---|---|
| Peta sebaran alumni nasional | ✅ | P2 |
| Jumlah asesmen, asesi, skema, TUK | ✅ | P1 |
| **Tingkat kelulusan (K/BK)** per skema, TUK, asesor | 🆕 | P1 |
| **Waktu proses** (daftar → sertifikat terbit) untuk deteksi hambatan | 🆕 | P2 |
| Sertifikat yang akan kedaluwarsa 30/60/90 hari (peluang re-sertifikasi) | 🆕 | P1 |
| Laporan untuk mitra/sponsor (hasil program yang mereka danai) | 🆕 | P2 |
| Ekspor Excel/PDF untuk semua laporan | 🆕 | P1 |

## 13. Komunikasi & Notifikasi

| Fitur | Status | Prioritas |
|---|---|---|
| Notifikasi proses asesmen | ✅ | P1 |
| Group chat asesmen | ✅ | P2 |
| **Multi-kanal**: WhatsApp (API resmi), email, push notification, SMS | 🆕 | P1 |
| **Template notifikasi** yang bisa diubah admin LSP | 🆕 | P2 |
| Pengumuman / broadcast ke segmen tertentu | 🆕 | P2 |
| **Helpdesk / tiket dukungan** di dalam aplikasi (bukan hanya telepon jam kerja) | 🆕 | P2 |
| Chatbot FAQ asesi 24 jam | 🆕 | P3 |
| **Kotak notifikasi di dalam aplikasi** (lonceng, tanda belum dibaca) | 🆕 | P1 |
| **Pengingat jadwal** H-3 & H-1 ke asesi, asesor, TUK + konfirmasi kehadiran | 🆕 | P1 |
| Preferensi notifikasi per user (kanal & jenis yang ingin diterima) | 🆕 | P2 |

## 14. CRM (Customer Relationship Management)

NAS hanya menyebut sistemnya "dapat digunakan sebagai CRM" ✅, tanpa rincian. Di sini CRM dibuat sebagai **modul tersendiri** dengan dua sasaran: **asesi perorangan (B2C)** dan **mitra/korporat (B2B)**. Seluruh data CRM **milik masing-masing LSP** (`lsp_id`) dan mengikuti aturan isolasi di bagian 0.

### 14.1 Kontak & organisasi

| Fitur | Status | Prioritas |
|---|---|---|
| Database kontak: calon asesi (lead), asesi, alumni, PIC mitra | 🆕 | P1 |
| Database organisasi: perusahaan, BUMN, instansi pemerintah, kampus, asosiasi (beserta PIC-nya) | 🆕 | P1 |
| **Timeline interaksi per kontak**: telepon, WA, email, meeting, catatan, riwayat permohonan & sertifikat di LSP ini | 🆕 | P1 |
| Tag & segmentasi (mis. skema minat, instansi, kota, sumber lead, status) | 🆕 | P1 |
| Deteksi & gabung kontak ganda (NIK/email/HP sama) | 🆕 | P2 |

### 14.2 Akuisisi calon asesi (lead)

| Fitur | Status | Prioritas |
|---|---|---|
| Form minat di website LSP / landing page per skema → otomatis masuk sebagai lead | 🆕 | P1 |
| **QR code event** (job fair, seminar, kampus): scan → isi data singkat → masuk lead | 🆕 | P2 |
| Tombol WhatsApp → percakapan tercatat ke kontak | 🆕 | P2 |
| **Pelacakan sumber** (UTM: Instagram, Google, event, referral, mitra) untuk mengukur kanal paling efektif | 🆕 | P2 |
| Impor lead dari Excel | 🆕 | P1 |

### 14.3 Pipeline (Kanban)

**Pipeline asesi (B2C)**

```
Lead → Dihubungi → Tertarik → Daftar (APL) → Bayar → Asesmen → Kompeten → Alumni → Re-sertifikasi
                                   ↘ berkas tidak lengkap / belum bayar  → follow-up otomatis
```

**Pipeline mitra (B2B)**

```
Prospek → Presentasi/Demo → Penawaran → Negosiasi → MoU/PKS → Program berjalan → Selesai → Repeat order
```

| Fitur | Status | Prioritas |
|---|---|---|
| Papan Kanban yang bisa di-drag, tahapan bisa diatur per LSP | 🆕 | P2 |
| Penugasan lead/prospek ke staf marketing tertentu | 🆕 | P2 |
| **Penawaran (quotation) otomatis** PDF: skema, jumlah peserta, harga, diskon, masa berlaku | 🆕 | P2 |
| Penyimpanan MoU/PKS + pengingat masa berlaku kerja sama | 🆕 | P2 |
| Nilai deal & **forecast pendapatan** dari pipeline B2B | 🆕 | P3 |
| Penawaran yang disetujui → langsung menjadi **program/kuota** di portal mitra & tagihan kolektif | 🆕 | P2 |

### 14.4 Follow-up & retensi otomatis

| Fitur | Status | Prioritas |
|---|---|---|
| **Pendaftar belum menyelesaikan proses** (berkas kurang / belum bayar / belum isi APL.02) → pengingat otomatis H+1, H+3, H+7 | 🆕 | P1 |
| **Pengingat re-sertifikasi** 6, 3, dan 1 bulan sebelum sertifikat habis, dengan tautan langsung daftar perpanjangan | ✅ (pengingat) / 🆕 (tautan & pelacakan konversi) | P1 |
| **Rekomendasi skema lanjutan** (jenjang berikutnya / skema terkait) untuk alumni | 🆕 | P2 |
| Asesi **Belum Kompeten** → ajakan asesmen ulang / bimtek | 🆕 | P2 |
| Tugas & pengingat follow-up untuk staf marketing (telepon hari ini, kirim penawaran, dsb.) | 🆕 | P1 |
| Workflow otomatis sederhana: *jika [kejadian] maka [kirim pesan / buat tugas / ubah tahap]* | 🆕 | P3 |

### 14.5 Kampanye & broadcast

| Fitur | Status | Prioritas |
|---|---|---|
| Broadcast WA/email ke segmen (mis. "alumni skema X yang sertifikatnya habis ≤ 90 hari") | 🆕 | P2 |
| Template pesan dengan variabel (nama, skema, tanggal habis, tautan) | 🆕 | P2 |
| Penjadwalan kirim, batas kirim per hari (agar nomor WA tidak diblokir) | 🆕 | P2 |
| Statistik: terkirim, dibaca, diklik, mendaftar (konversi) | 🆕 | P2 |
| **Promo & kode diskon** per kampanye / periode | 🆕 | P2 |
| **Program referral**: alumni/mitra pemasaran mendapat kode referral, komisi tercatat | 🆕 | P3 |

### 14.6 Layanan pelanggan

| Fitur | Status | Prioritas |
|---|---|---|
| **Kotak masuk terpadu (omnichannel)**: WA, email, chat web dalam satu layar, terhubung ke profil kontak | 🆕 | P3 |
| Tiket helpdesk & keluhan terhubung ke kontak (lihat juga modul keluhan, bagian 9) | 🆕 | P2 |
| **Survei kepuasan / NPS** otomatis setelah asesmen & setelah sertifikat terbit | 🆕 | P2 |
| Balasan cepat (quick reply) & FAQ | 🆕 | P2 |

### 14.7 Laporan CRM

| Fitur | Status | Prioritas |
|---|---|---|
| **Funnel konversi**: lead → daftar → bayar → kompeten | 🆕 | P2 |
| Efektivitas sumber lead & kampanye (biaya vs pendaftar) | 🆕 | P3 |
| Tingkat re-sertifikasi (retensi) per skema | 🆕 | P2 |
| Pendapatan per mitra & per staf marketing | 🆕 | P2 |
| Skor kepuasan (NPS) per skema, TUK, asesor | 🆕 | P2 |

### 14.8 Aturan privasi CRM (penting karena asesi multi-LSP)

- CRM LSP hanya berisi **kontak milik LSP itu sendiri**: lead yang ia kumpulkan sendiri, atau asesi yang pernah mendaftar ke LSP itu. **LSP tidak bisa menarget asesi berdasarkan data di LSP lain** (mis. "orang yang pernah ikut skema X di LSP lain").
- Profil global asesi **tidak bisa dicari** dari CRM. Data asesi baru masuk CRM sebuah LSP setelah asesi mendaftar/menyetujui berbagi data dengan LSP tersebut.
- **Persetujuan pemasaran (opt-in) terpisah** dari notifikasi layanan (UU PDP): notifikasi proses asesmen tetap terkirim, tetapi promosi hanya ke kontak yang setuju. Setiap pesan promosi punya opsi **berhenti berlangganan**.
- Ekspor data kontak dibatasi role (mis. hanya Admin LSP) dan tercatat di log audit.

### 14.9 CRM Platform (untuk Super Admin, opsional)

Untuk pengelola aplikasi menjual langganan ke LSP:

| Fitur | Prioritas |
|---|---|
| Pipeline calon LSP klien: prospek → demo → trial → berlangganan | P3 |
| Pengingat perpanjangan langganan & tagihan LSP | P2 |
| **Sinyal churn**: LSP yang aktivitasnya menurun, kuota hampir habis, tiket support banyak | P3 |
| Statistik penggunaan per LSP (agregat, tanpa data pribadi asesi) | P2 |

## 15. Ekosistem

| Fitur | Status | Prioritas |
|---|---|---|
| Website LSP (halaman depan: beranda, profil, skema, jadwal, verifikasi sertifikat), lihat 15.1 | ✅ | P1 (dasar) / P2 (page builder) |
| E-commerce: katalog skema → pilih jadwal → keranjang → bayar, lihat 15.1 | ✅ | P1 (katalog + checkout) / P2 (promo, bundling) |
| LMS Bimtek, lihat 15.1 | ✅ | P3 |
| Kerja sama B2B (Pemda, BUMN, kampus) | ✅ | P3 |
| **Portal mitra/korporat**: daftar karyawan massal, lihat progres & hasil, unduh sertifikat | 🆕 | P2 |
| **Tracer study alumni** (status kerja setelah sertifikasi) | 🆕 | P3 |
| **Job board / talent pool** pemegang sertifikat (dengan persetujuan asesi) | 🆕 | P3 |
| **API publik & webhook** untuk integrasi HRIS/LMS mitra | 🆕 | P3 |

### 15.1 Halaman depan publik: Website, E-commerce & LMS

Ada **dua tingkat halaman depan**:

| Tingkat | Alamat (contoh) | Untuk siapa | Isi |
|---|---|---|---|
| **Portal pusat (platform)** | `aplikasi.id` | Calon LSP klien & masyarakat umum | Profil produk, harga paket, demo, **direktori LSP & pencarian skema lintas LSP** (hanya data publik yang LSP setujui tampil), verifikasi sertifikat, login |
| **Website tiap LSP** | `lsp-abc.aplikasi.id` atau domain sendiri `lsp-abc.or.id` | Calon asesi, mitra, publik | Website lengkap LSP dengan branding LSP sendiri: beranda, e-commerce skema, LMS, login |

#### Menu website LSP (sebelum login)

```
[Logo LSP]  Beranda | Profil | Skema Sertifikasi | Jadwal Uji | Pelatihan (LMS) | Verifikasi Sertifikat | Berita | Kontak    [Masuk] [Daftar]
```

| Menu | Isi | Prioritas |
|---|---|---|
| **Beranda** | Banner, keunggulan LSP, skema populer, jadwal terdekat, statistik (jumlah asesi, skema, TUK), testimoni, logo mitra | P1 |
| **Profil** | Tentang LSP, visi-misi, struktur organisasi, nomor lisensi BNSP, daftar TUK (peta), daftar asesor (opsional, data terbatas) | P1 |
| **Skema Sertifikasi** (e-commerce) | Katalog skema: unit kompetensi, persyaratan, biaya, jenjang; filter bidang/harga/lokasi; tombol **Daftar Sekarang** | P1 |
| **Jadwal Uji** | Kalender jadwal terbuka per skema & TUK, sisa kuota, tombol daftar | P1 |
| **Pelatihan (LMS)** | Katalog kelas bimtek/persiapan uji, gratis/berbayar | P3 |
| **Verifikasi Sertifikat** | Cek nomor / scan QR | P1 |
| **Berita / Galeri / Pengumuman** | Artikel, foto kegiatan, pengumuman | P2 |
| **Kontak** | Alamat, peta, WA, form minat (masuk ke CRM sebagai lead) | P1 |
| **Masuk / Daftar** | Satu pintu login untuk semua role; setelah login diarahkan ke dashboard sesuai role | P1 |

Website dikelola Admin LSP lewat **CMS sederhana** (ubah banner, teks, berita, galeri, warna). *Page builder* drag-and-drop di P2. Halaman otomatis SEO-friendly (judul, deskripsi, sitemap) agar skema LSP muncul di Google.

#### E-commerce (alur beli skema)

```
Katalog skema → Detail skema → Pilih jadwal & TUK → Keranjang → Login/Daftar → Isi APL.01/02 & unggah berkas → Bayar (VA/QRIS/e-wallet) → Masuk pipeline asesmen
```

| Fitur | Prioritas |
|---|---|
| Katalog skema + halaman detail + checkout satu skema | P1 |
| Pembayaran payment gateway, invoice & kuitansi otomatis (terhubung modul Keuangan) | P1 |
| Kode promo / voucher, harga khusus mitra | P2 |
| Paket bundling (mis. kelas persiapan + uji kompetensi) | P2 |
| Keranjang beberapa item, pembelian untuk orang lain (perusahaan membelikan karyawan) | P2 |
| Ulasan & rating skema/TUK dari alumni | P3 |

E-commerce ini **bukan modul terpisah**: begitu dibayar, pesanan otomatis menjadi permohonan sertifikasi (bagian 4) dan tagihan (bagian 11).

#### LMS Bimtek

| Fitur | Prioritas |
|---|---|
| Kelas online: video, materi PDF, kuis, tugas, progres belajar | P3 |
| Kelas tatap muka/webinar: jadwal, link meeting, presensi | P3 |
| Sertifikat pelatihan (berbeda dengan sertifikat kompetensi BNSP) | P3 |
| Instruktur/pengajar sebagai role tersendiri | P3 |
| Penjualan kelas melalui e-commerce yang sama | P3 |

> **Penting: aturan ketidakberpihakan.** LSP yang juga menyelenggarakan pelatihan berisiko konflik kepentingan. Sistem wajib menegakkan:
> - **Instruktur yang mengajar seorang peserta tidak boleh menjadi asesornya** (sistem memblokir penugasan otomatis, lihat deklarasi konflik kepentingan di bagian 2).
> - **Mengikuti pelatihan tidak boleh menjadi syarat wajib** untuk mendaftar uji kompetensi, dan tidak boleh dijanjikan "pasti kompeten".
> - Risiko ini tercatat di analisis risiko ketidakberpihakan (bagian 10).
> - Opsi: LMS dioperasikan oleh **lembaga pelatihan (LPK) mitra** sebagai pihak terpisah, sementara LSP hanya menautkan.

#### Setelah login: dashboard per role

| Role | Menu utama |
|---|---|
| Asesi | Dashboard (status semua permohonan di semua LSP), Daftar Skema, Jadwal Saya, Pembayaran, Kelas Saya (LMS), Dompet Sertifikat, Profil & Dokumen |
| Asesor | Kalender gabungan, Penugasan, Pra-asesmen, Asesmen (MUK/FR), Pleno, Riwayat, Logbook, Honor |
| Admin LSP | Dashboard, Pendaftaran, Jadwal, Asesmen, Pleno & Sertifikat, Data Master, Mutu, Keuangan, CRM, Website/CMS, Laporan, Pengaturan |
| Admin TUK | Dashboard TUK, Pemohon, Jadwal, Sarana-prasarana, Chat |
| Mitra | Program/kuota, Peserta, Progres & hasil, Tagihan |

Menu yang tampil **mengikuti paket langganan LSP** (feature flag, bagian 15.2): misalnya LSP paket Basic tidak menampilkan menu Pelatihan (LMS).

### 15.2 Paket langganan & layanan support

Deck NAS menyebut "*untuk paket Basic*", artinya fitur dibedakan per paket. Aplikasi perlu mendukung ini sejak awal.

| Fitur | Status | Prioritas |
|---|---|---|
| **Paket langganan** (contoh: Basic / Pro / Enterprise) dengan batas kuota (asesi per tahun, storage, jumlah TUK, jumlah user) | ✅ (tersirat) | P1 |
| **Feature flag per paket**: modul (mis. SJJ, CRM lanjutan, modul mutu, website, e-commerce, LMS) bisa dinyalakan/dimatikan per LSP sesuai paket | 🆕 | P1 |
| Upgrade/downgrade paket, masa trial, tagihan langganan otomatis | 🆕 | P2 |
| **Tim support** dengan jam layanan per paket (mis. Basic 08.00–17.00 WIB setiap hari; paket lebih tinggi bisa 24 jam / prioritas) | ✅ | P1 (operasional) |
| SLA respons tiket support per paket, tercatat di helpdesk | 🆕 | P2 |
| Pusat bantuan: panduan, video tutorial per role, FAQ | 🆕 | P2 |

## 16. Keamanan, Kepatuhan & Infrastruktur

| Fitur | Status | Prioritas |
|---|---|---|
| Penyimpanan cloud di Indonesia, folder/storage terpisah per LSP | ✅ | P1 |
| Double backup dokumen | ✅ | P1 |
| Terdaftar PSE Kominfo/Komdigi | ✅ | P1 (non-teknis) |
| **Kepatuhan UU PDP (UU 27/2022)**: persetujuan (consent), hak akses & hapus data, kebijakan retensi, prosedur kebocoran data | 🆕 | P1 |
| **Audit trail** (siapa mengubah apa & kapan), penting saat ditelusuri BNSP | 🆕 | P1 |
| **RBAC** granular per role & per TUK | 🆕 | P1 |
| **2FA** untuk admin, asesor, pleno | 🆕 | P1 |
| Enkripsi data sensitif (NIK, dokumen identitas) | 🆕 | P1 |
| **Multi-tenant + white-label** (subdomain/custom domain & logo per LSP) | 🆕 | P1 |
| Disaster recovery plan, uji restore backup berkala | 🆕 | P2 |
| Status page & SLA uptime | 🆕 | P3 |
| Login alternatif: **OTP WhatsApp/email**, login dengan Google; lupa password mandiri | 🆕 | P1 |
| Manajemen sesi & perangkat (lihat perangkat yang login, keluar dari semua perangkat), batas waktu sesi | 🆕 | P2 |
| Kebijakan password, pembatasan percobaan login, CAPTCHA | 🆕 | P1 |
| Rate limiting, WAF, proteksi DDoS | 🆕 | P1 |
| Monitoring aplikasi & error, log terpusat, peringatan otomatis ke tim teknis | 🆕 | P1 |
| Verifikasi NIK via Dukcapil (memerlukan kerja sama/PKS) | 🆕 | P3 |
| **Multi-bahasa** antarmuka (Indonesia / Inggris) | 🆕 | P3 |
| **Aksesibilitas** (kontras, ukuran huruf, pembaca layar) untuk asesi difabel | 🆕 | P2 |
| Aplikasi web responsif + PWA (bisa dipasang di HP tanpa app store) | 🆕 | P1 |

## 17. Fitur AI (pembeda modern)

| Fitur | Prioritas |
|---|---|
| OCR & validasi otomatis dokumen persyaratan (KTP, ijazah, sertifikat pelatihan) | P2 |
| Asisten asesor: ringkasan bukti portofolio & draf rekomendasi (keputusan tetap di asesor) | P3 |
| Generator draf soal dari unit kompetensi/KUK (wajib divalidasi manusia) | P3 |
| Deteksi kecurangan SJJ (wajah berbeda, suara orang lain, jawaban identik antarpeserta) | P3 |
| Chatbot asesi berbasis dokumen skema & FAQ LSP | P3 |

---

## 18. Usulan tahapan rilis

**Fase 1 — MVP (±3–4 bulan)**: **multi-LSP (tenant) dengan isolasi data berlapis (bagian 0)** & RBAC, master data, skema & MUK berversi, pendaftaran + pembayaran, penjadwalan, verifikasi TUK, asesmen paperless + CBT, formulir FR lengkap, TTE, pleno, sertifikat ber-QR + halaman verifikasi, laporan BNSP, notifikasi WA/email, **CRM dasar** (kontak, lead, follow-up pendaftar belum selesai, pengingat re-sertifikasi), **migrasi data dari Excel**, **persuratan otomatis** (surat tugas, SK pleno, BAPS), **website LSP dasar + katalog skema & checkout (e-commerce dasar)**, jadwal terbuka + kartu peserta, cetak sertifikat di blanko, paket langganan & feature flag, audit trail, kepatuhan PDP.

**Fase 2 — Mutu & skala (±3 bulan)**: SJJ dengan proctoring, mode offline asesor, modul mutu (audit internal, CAPA, kaji ulang manajemen, ketidakberpihakan), keuangan & honor asesor, surveilans & RCC, portal mitra, **CRM lanjutan** (pipeline B2B + penawaran, kampanye broadcast, survei NPS, laporan funnel), helpdesk, dashboard analitik lanjutan, jalur RPL, logbook asesor, pustaka SKKNI global, refund & waiting list, tindak lanjut temuan BNSP.

**Fase 3 — Ekosistem**: website LSP builder, e-commerce, LMS bimtek, tracer study, talent pool, API publik, fitur AI, **CRM omnichannel, workflow otomatis & referral**.

## 19. Usulan teknologi (garis besar)

- **Backend**: Laravel (PHP) atau NestJS (TypeScript), PostgreSQL, Redis (antrean notifikasi & cache)
- **Web**: Next.js / React (admin LSP, TUK, asesor, asesi, portal mitra)
- **Mobile**: Flutter atau React Native (asesor & asesi, dengan penyimpanan offline)
- **Storage**: object storage di region Indonesia (AWS Jakarta / GCP Jakarta / penyedia lokal), terpisah per tenant
- **Integrasi**: payment gateway (Midtrans/Xendit), WhatsApp Business API, Zoom/Google Meet/Jitsi, penyedia TTE (PSrE)
- **PDF**: generator template FR & sertifikat sisi server

> Catatan: nomor pedoman dan kode formulir BNSP perlu dicek ulang terhadap versi terbaru yang berlaku (BNSP rutin memperbarui pedoman & format MUK).

---

## Lampiran A. Pemetaan fitur NAS → dokumen ini

Semua fitur di slide "Fitur Sistem" dan "Ekosistem NAS" sudah tercakup.

### Web Admin LSP & TUK

| Fitur NAS | Bagian |
|---|---|
| Manajemen Data LSP (Organisasi, SDM, Dokumentasi, Jadwal) | 0.2, 2, 10 |
| Manajemen Data TUK* | 2, 2.1, 6 |
| Manajemen Data Asesor | 2, 0.8 |
| Manajemen Data Pemohon* | 2, 2.1 |
| Manajemen Akun Pengguna | 0.3, 2 |
| Upload data Skema (Unit Kompetensi, MUK, Lisensi) | 3 |
| Create Jadwal Asesmen | 5 |
| Group Chat Asesmen* | 7, 13 |
| Penentuan Komite Pleno | 8 |
| Create Jadwal & Perekaman Dokumen Banding | 5, 9 |
| Tinjau Proses Sertifikasi* | 4, 2.1 |
| Database Alumni (Rekaman Dokumen Asesmen)* | 2, 2.1 |
| Integrasi Database (Rekaman Virtual Meeting) | 7 |
| Auto Generate Template Report BNSP | 8 |

### Mobile Apps & Web Asesor

| Fitur NAS | Bagian |
|---|---|
| Register Data Asesor | 0.8 |
| Jadwal Asesmen, Pleno, dan Banding | 0.8, 5 |
| Daftar Peserta Uji (Tinjau Pra-Asesmen) | 4 |
| Membaca & Mengisi dokumen MUK | 7 |
| Tinjau & Pemberian Status Keputusan Hasil Uji (Pleno) | 8 |
| Group chat Assessment | 7, 13 |
| Integrasi Virtual Meeting | 7 |
| Riwayat Asesmen | 0.8 |
| Asesmen Ulang | 7 |

### Mobile Apps & Web Asesi

| Fitur NAS | Bagian |
|---|---|
| Pengajuan Skema | 4, 0.9 |
| Upload Berkas Persyaratan & Pendukung | 4, 0.9 |
| Mengisi Dokumen MUK | 4, 7 |
| Real-Time Status Asesmen | 4, 0.9 |
| Notifikasi Proses Asesmen s.d. Habis Masa Berlaku Sertifikat | 9, 13 |
| Group chat Assessment | 7, 13 |
| Integrasi Virtual Meeting | 7 |
| Riwayat Asesmen | 0.9 |
| Asesmen Ulang | 7 |
| Informasi Surveillance Asesi & Re-Lisensi | 9 |

### Ekosistem NAS

| Fitur NAS | Bagian |
|---|---|
| Kerja sama B2B Pemerintah (Program Pemda, CSR) | 14.3, 15 |
| Kerja sama B2B BUMN, Perusahaan Swasta, Kampus | 14.3, 15 |
| Event calon asesi (Job Fair, Seminar) | 14.2 |
| Proses Uji Kompetensi (APL01 – Generate BAPS) | 4–8 |
| Website LSP | 15, 15.1 |
| E-Commerce | 15, 15.1 |
| LMS Bimtek | 15, 15.1 |
| Calon Asesi | 14.2 |
| Asesor & TUK terhubung ke LSP | 0.8, 2, 6 |
| Tim Support 08.00–17.00 WIB setiap hari | 15.2 |
| Paket Basic | 15.2 |
