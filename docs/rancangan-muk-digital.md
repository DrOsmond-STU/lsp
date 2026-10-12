# Rancangan: Alur Sertifikasi Paperless sesuai Buku Kerja 333-25 dan Pedoman BNSP

Acuan: folder *Form Buku Kerja 333-25* (32 form FR.APL, FR.MAPA, FR.AK, FR.IA, FR.VA) dan *Bahan Pelatihan ASKOM Jan 2026*
(PBNSP 201/2014, 202/2014, 206/2014, 210/2017, SKKNI Asesor MAPA/MA/MKVA, Modul ASKOM 2023, Skema Asesor 2024).

## 1. Prinsip

- **Paperless**: setiap form FR.* diisi di aplikasi, ditandatangani elektronik (identitas akun + waktu + IP + sidik isi SHA-256),
  dan dapat dicetak/arsip ke PDF dari tampilan cetak. Tidak ada form kertas yang wajib.
- **Satu mesin form**: definisi tiap form (bagian, kolom, opsi, siapa mengisi, siapa menandatangani) disimpan sekali di server
  (`src/muk.php`). Browser merender dari definisi yang sama; server memvalidasi dengan definisi yang sama.
- **Perangkat vs isian**: perangkat asesmen (MUK) disusun dan divalidasi sekali per skema (blok *Penyusun dan Validator*);
  isian per asesi dibuat saat asesmen dan merujuk versi perangkat yang dipakai.
- **Pemisahan tugas** (P201 §9.4.4, §9.8.4): pengambil keputusan ≠ asesor penguji; pemutus banding ≠ pengambil keputusan
  dan ≠ asesor; validator perangkat ≠ penyusun.

## 2. Peran

| Peran | Kode | Tugas pada alur |
|---|---|---|
| Asesi | `asesi` | APL.01, APL.02, tanda tangan AK.01/AK.07, menjawab IA.05/IA.06, tanda tangan AK.02, umpan balik AK.03, banding AK.04 |
| Asesor Kompetensi (No. Reg MET) | `asesor` | Tinjau APL.02, MAPA.01/02 (penyusun), AK.07, AK.01, instrumen IA, AK.02, AK.05, AK.06, validasi perangkat & FR.VA (sebagai validator bila bukan penyusun) |
| Admin LSP / Bagian Sertifikasi | `admin_lsp` | Verifikasi APL.01 (bukti persyaratan dasar & administratif, rekomendasi diterima/tidak), jadwal, penugasan asesor & TUK |
| Komite Teknis (Pengambil Keputusan) | `komite_teknis` **baru** | Keputusan sertifikasi (berita acara pleno) dan keputusan banding; tidak boleh untuk asesmen yang ia uji/putuskan sendiri |
| Manajer Mutu | `manajer_mutu` | Pemantauan, tinjauan AK.06, validasi FR.VA, audit internal; tidak lagi memutus sertifikasi |
| Admin TUK | `admin_tuk` | Jadwal & sarpras TUK |
| Keuangan, Marketing | tetap | tidak berubah |

## 3. Alur dan form per tahap

| # | Tahap | Form | Pengisi → penanda tangan | Gerbang status |
|---|---|---|---|---|
| 1 | Pendaftaran | **FR.APL.01** bagian 1–3 | Asesi → TTD asesi | `diajukan` |
| 2 | Asesmen mandiri | **FR.APL.02** per elemen (K/BK + bukti) | Asesi → TTD asesi | (bersama APL.01) |
| 3 | Verifikasi persyaratan | **FR.APL.01** rekomendasi LSP: tiap bukti Memenuhi/Tidak Memenuhi/Tidak Ada; Diterima/Tidak diterima | Admin LSP → TTD admin | `menunggu_bayar` / `perbaikan` / `ditolak` |
| 4 | Pembayaran | tagihan | Asesi | `pra_asesmen` |
| 5 | Perencanaan (per skema) | **FR.MAPA.01**, **FR.MAPA.02** (instrumen × potensi asesi) | Asesor penyusun → validator | perangkat skema harus **divalidasi** sebelum AK.01 |
| 6 | Konsultasi pra-asesmen | Tinjauan **APL.02** (dapat/tidak dilanjutkan), **FR.AK.07** penyesuaian wajar, **FR.AK.01** persetujuan & kerahasiaan + pernyataan bebas benturan kepentingan | Asesor → TTD asesor; asesi → TTD asesi | `siap_uji` setelah AK.01 ditandatangani **kedua** pihak |
| 7 | Asesmen | **FR.IA.01–IA.11** sesuai MAPA.02 untuk potensi asesi (dan AK.07) | Asesor (IA.05C/IA.06C dijawab asesi online) | — |
| 8 | Keputusan asesmen | **FR.AK.02** rekaman per unit K/BK, tindak lanjut | Asesor → TTD asesor (+ asesi) | `menunggu_pleno` |
| 9 | Umpan balik | **FR.AK.03** 10 butir | Asesi | — |
| 10 | Laporan | **FR.AK.05** per jadwal | Asesor | pleno hanya untuk berkas yang tercakup laporan |
| 11 | Keputusan sertifikasi | berita acara pleno | Komite Teknis (≠ asesor penguji) | `kompeten` (sertifikat terbit) / `belum_kompeten` |
| 12 | Banding | **FR.AK.04** | Asesi, dalam batas hari skema → diputus Komite lain | rekaman banding |
| 13 | Tinjauan | **FR.AK.06** per jadwal | Asesor / lead asesor | — |
| 14 | Validasi | **FR.VA** per skema/periode | Tim validasi (asesor/manajer mutu) | — |

## 4. Data baru (migrasi 12)

- `skema`: `kemasan` (KKNI/Okupasi/Klaster), `masa_berlaku_tahun`, `banding_hari`.
- `skema_units.kelompok` (Kelompok Pekerjaan); `skema_elemen` (elemen + daftar KUK per unit).
- `users.no_met` (No. Reg asesor).
- `muk_perangkat` (perangkat per skema & kode, berversi, penyusun/validator).
- `formulir` + `formulir_ttd` (isian per permohonan/jadwal/skema dan tanda tangan elektroniknya).
- `banding`.
- Peran `komite_teknis`; hak akses baru `muk.manage`, `appeal.decide`, `assessment.review`.
