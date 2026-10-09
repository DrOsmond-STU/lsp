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
> **Pengecualian: Asesor** boleh terdaftar di lebih dari satu LSP dengan **satu akun**, tetapi **hanya bisa melihat data uji kompetensi yang ia tangani sendiri** di masing-masing LSP (lihat bagian 0.8).

### 0.1 Hierarki data

```
Platform (Super Admin)
 └── LSP  (tenant, lsp_id)
      ├── User LSP: Admin LSP, Manajer Mutu, Komite, Pleno, Keuangan
      ├── TUK ── Admin TUK            (hanya TUK miliknya, di dalam LSP-nya)
      ├── Asesor (bisa di banyak LSP) (hanya uji kompetensi yang ditugaskan kepadanya)
      ├── Asesi                       (hanya data dirinya sendiri)
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
2. **Akun dan keanggotaan dipisah**: tabel `akun` (identitas login) dan tabel `keanggotaan` (`akun_id`, `lsp_id`, `role`, status). Admin LSP, Manajer, Pleno, Keuangan, Admin TUK, Asesi, Mitra **hanya boleh punya satu keanggotaan**. **Asesor boleh punya banyak keanggotaan** (satu per LSP). Konteks LSP aktif **diambil dari sesi login, tidak pernah dari input/form/URL**.
3. **Keunikan data bersifat per LSP**: email, nomor registrasi, nomor sertifikat, kode skema, kode TUK, dsb. unik di dalam `(lsp_id, ...)`, bukan global.
4. **Asesor yang bekerja di beberapa LSP = satu akun, banyak keanggotaan.** Data operasional (penugasan, rekaman asesmen, honor) tetap milik masing-masing LSP. **Asesi** untuk saat ini tetap satu akun per LSP (bisa memakai pola keanggotaan yang sama di tahap berikutnya bila dibutuhkan).
5. Data LSP tidak pernah dihapus permanen secara langsung: LSP yang berhenti langganan → **ditangguhkan → ekspor data untuk LSP → dihapus** sesuai kebijakan retensi (UU PDP).

### 0.4 Penegakan isolasi (wajib berlapis, bukan hanya di tampilan)

| Lapisan | Mekanisme |
|---|---|
| **Login & sesi** | User LSP: LSP dikenali dari subdomain, login hanya berhasil jika akun punya keanggotaan aktif di LSP tersebut; sesi menyimpan `lsp_id`. Asesor: login sekali, sesi menyimpan **daftar LSP tempat ia aktif** + daftar penugasannya (bagian 0.8). |
| **Aplikasi (backend)** | Middleware tenant + *global scope* otomatis `WHERE lsp_id = :lsp_id_sesi` di semua query. Developer tidak perlu (dan tidak boleh) menulis filter manual. |
| **Database** | **PostgreSQL Row-Level Security (RLS)** di setiap tabel: koneksi men-set `app.current_lsp_id` (user LSP) atau `app.current_asesor_id` (asesor), dan DB menolak baris di luar haknya walaupun ada bug di kode aplikasi. Untuk asesor, kebijakan RLS berbasis **tabel penugasan**, bukan sekadar `lsp_id`. |
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

---

## 1. Peran pengguna (role)

Semua role di bawah **terikat ke satu LSP**, kecuali *Super Admin Platform*, *Publik*, dan **Asesor** (bisa di banyak LSP, lihat bagian 0.8).

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
| Asesi | Peserta uji | ✅ |
| Keuangan | Tagihan, pembayaran, honor asesor | 🆕 |
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
| **Pengingat masa berlaku**: lisensi LSP, sertifikat asesor (MET), sertifikat kompetensi asesor, verifikasi TUK | 🆕 | P1 |
| **Ketersediaan / kalender asesor** (asesor mengisi tanggal tersedia) | 🆕 | P2 |
| **Deklarasi konflik kepentingan** asesor per asesi/jadwal (blok otomatis jika asesor pernah melatih asesi atau satu instansi) | 🆕 | P1 |
| **Inventaris sarana-prasarana TUK** per skema (alat, bahan, ruang) | 🆕 | P2 |
| Rotasi asesor & beban kerja asesor | 🆕 | P3 |

## 3. Modul Skema Sertifikasi

| Fitur | Status | Prioritas |
|---|---|---|
| Upload skema: unit kompetensi (kode SKKNI/SKK Khusus/Internasional), elemen, KUK, persyaratan dasar | ✅ | P1 |
| Upload MUK (Materi Uji Kompetensi) per skema | ✅ | P1 |
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
| **Verifikasi identitas**: OCR KTP/ijazah, cek NIK (format & duplikasi), swafoto | 🆕 | P2 |
| **Cek kelengkapan berkas otomatis** (berkas kurang/buram/kedaluwarsa ditandai sebelum ke admin) | 🆕 | P2 |
| **Pendaftaran massal** via template Excel / tautan undangan untuk mitra (program Pemda, CSR, BUMN, kampus) | 🆕 | P1 |
| **Kuota & kode voucher kerja sama** (peserta dibiayai sponsor) | 🆕 | P2 |
| **Pembayaran online** (payment gateway: VA, QRIS, e-wallet), invoice & kuitansi otomatis | 🆕 | P1 |
| Penyesuaian yang beralasan untuk asesi berkebutuhan khusus (FR.AK.07) | 🆕 | P2 |

## 5. Modul Penjadwalan

| Fitur | Status | Prioritas |
|---|---|---|
| Create jadwal asesmen (TUK, asesor, peserta) | ✅ | P1 |
| Jadwal pleno & jadwal banding | ✅ | P1 |
| **Saran asesor otomatis** (berdasarkan skema, lokasi, ketersediaan, bebas konflik kepentingan) | 🆕 | P2 |
| **Validasi bentrok jadwal** asesor/TUK/ruang | 🆕 | P1 |
| **Rasio asesor : asesi** sesuai ketentuan, sistem menolak jika terlampaui | 🆕 | P1 |
| Sinkronisasi ke Google Calendar / Outlook (file .ics) | 🆕 | P3 |

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

## 11. Modul Keuangan

| Fitur | Status | Prioritas |
|---|---|---|
| Tagihan asesi & mitra (perorangan / kolektif) | 🆕 | P1 |
| Payment gateway + rekonsiliasi otomatis | 🆕 | P1 |
| **Honor asesor** (per asesi / per jadwal), slip honor, rekap PPh 21 | 🆕 | P2 |
| Biaya blanko BNSP & biaya sewa TUK | 🆕 | P2 |
| Laporan pendapatan per skema/TUK/periode, ekspor ke akuntansi | 🆕 | P2 |
| E-Faktur / PPN bila diperlukan | 🆕 | P3 |

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

## 14. Ekosistem

| Fitur | Status | Prioritas |
|---|---|---|
| Website LSP | ✅ | P2 |
| E-commerce (jual skema/paket sertifikasi) | ✅ | P3 |
| LMS Bimtek | ✅ | P3 |
| Kerja sama B2B (Pemda, BUMN, kampus) | ✅ | P3 |
| **Portal mitra/korporat**: daftar karyawan massal, lihat progres & hasil, unduh sertifikat | 🆕 | P2 |
| **Tracer study alumni** (status kerja setelah sertifikasi) | 🆕 | P3 |
| **Job board / talent pool** pemegang sertifikat (dengan persetujuan asesi) | 🆕 | P3 |
| **API publik & webhook** untuk integrasi HRIS/LMS mitra | 🆕 | P3 |

## 15. Keamanan, Kepatuhan & Infrastruktur

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

## 16. Fitur AI (pembeda modern)

| Fitur | Prioritas |
|---|---|
| OCR & validasi otomatis dokumen persyaratan (KTP, ijazah, sertifikat pelatihan) | P2 |
| Asisten asesor: ringkasan bukti portofolio & draf rekomendasi (keputusan tetap di asesor) | P3 |
| Generator draf soal dari unit kompetensi/KUK (wajib divalidasi manusia) | P3 |
| Deteksi kecurangan SJJ (wajah berbeda, suara orang lain, jawaban identik antarpeserta) | P3 |
| Chatbot asesi berbasis dokumen skema & FAQ LSP | P3 |

---

## 17. Usulan tahapan rilis

**Fase 1 — MVP (±3–4 bulan)**: **multi-LSP (tenant) dengan isolasi data berlapis (bagian 0)** & RBAC, master data, skema & MUK berversi, pendaftaran + pembayaran, penjadwalan, verifikasi TUK, asesmen paperless + CBT, formulir FR lengkap, TTE, pleno, sertifikat ber-QR + halaman verifikasi, laporan BNSP, notifikasi WA/email, audit trail, kepatuhan PDP.

**Fase 2 — Mutu & skala (±3 bulan)**: SJJ dengan proctoring, mode offline asesor, modul mutu (audit internal, CAPA, kaji ulang manajemen, ketidakberpihakan), keuangan & honor asesor, surveilans & RCC, portal mitra, helpdesk, dashboard analitik lanjutan.

**Fase 3 — Ekosistem**: website LSP builder, e-commerce, LMS bimtek, tracer study, talent pool, API publik, fitur AI.

## 18. Usulan teknologi (garis besar)

- **Backend**: Laravel (PHP) atau NestJS (TypeScript), PostgreSQL, Redis (antrean notifikasi & cache)
- **Web**: Next.js / React (admin LSP, TUK, asesor, asesi, portal mitra)
- **Mobile**: Flutter atau React Native (asesor & asesi, dengan penyimpanan offline)
- **Storage**: object storage di region Indonesia (AWS Jakarta / GCP Jakarta / penyedia lokal), terpisah per tenant
- **Integrasi**: payment gateway (Midtrans/Xendit), WhatsApp Business API, Zoom/Google Meet/Jitsi, penyedia TTE (PSrE)
- **PDF**: generator template FR & sertifikat sisi server

> Catatan: nomor pedoman dan kode formulir BNSP perlu dicek ulang terhadap versi terbaru yang berlaku (BNSP rutin memperbarui pedoman & format MUK).
