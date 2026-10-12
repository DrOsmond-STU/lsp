<?php
declare(strict_types=1);

/*
 * Isi materi uji kompetensi untuk skema contoh: elemen & KUK per unit (mengikuti struktur SKKNI),
 * kelompok pekerjaan, dan perangkat asesmen (MAPA, skenario TPD, PMO, soal PG/esai, pertanyaan lisan, DIT).
 */

/** Elemen dan KUK per kode unit. */
function muk_isi_elemen(): array
{
    return [
        // ---------- Junior Web Developer (J.620100)
        'J.620100.004.02' => [['Mengidentifikasi konsep data dan struktur data', ['Konsep data dan struktur data diidentifikasi sesuai dengan konteks permasalahan.', 'Alternatif struktur data dibandingkan kelebihan dan kekurangannya untuk konteks permasalahan yang diselesaikan.']],
            ['Menerapkan struktur data dan akses terhadap struktur data tersebut', ['Struktur data diimplementasikan sesuai dengan bahasa pemrograman yang akan dipergunakan.', 'Akses terhadap data dinyatakan dalam algoritma yang efisien sesuai bahasa pemrograman yang dipakai.']]],
        'J.620100.005.02' => [['Mengidentifikasi rancangan user interface', ['Rancangan user interface diidentifikasi sesuai kebutuhan.', 'Komponen user interface dialog diidentifikasi sesuai konteks rancangan proses.']],
            ['Melakukan implementasi rancangan user interface', ['Menu program sesuai dengan rancangan program diterapkan.', 'Penempatan user interface dialog diatur secara sekuensial.', 'Setting aktif-pasif komponen user interface dialog disesuaikan dengan urutan alur proses.']]],
        'J.620100.011.01' => [['Memilih tools pemrograman yang sesuai dengan kebutuhan', ['Platform (lingkungan) yang akan digunakan untuk menjalankan tools pemrograman diidentifikasi sesuai dengan kebutuhan.', 'Tools bahasa pemrograman dipilih sesuai dengan kebutuhan dan lingkungan pengembangan.']],
            ['Instalasi tools pemrograman', ['Tools pemrograman ter-install sesuai dengan prosedur.', 'Tools pemrograman bisa dijalankan di lingkungan pengembangan yang telah ditetapkan.']]],
        'J.620100.012.01' => [['Melakukan konfigurasi tools untuk pemrograman', ['File konfigurasi tools pemrograman disiapkan sesuai kebutuhan.', 'Konfigurasi tools pemrograman dapat diubah sesuai kebutuhan.']],
            ['Menggunakan tools sesuai kebutuhan pembuatan program', ['Fitur-fitur dasar yang dibutuhkan untuk mendukung pembuatan program diidentifikasi.', 'Fitur-fitur dasar tools untuk pembuatan program diterapkan.']]],
        'J.620100.016.01' => [['Menerapkan coding-guidelines dan best practices dalam penulisan program (kode sumber)', ['Kode sumber dituliskan mengikuti coding-guidelines dan best practices.', 'Struktur program yang sesuai dengan konsep paradigmanya dibuat.', 'Galat/error ditangani.']],
            ['Menggunakan ukuran performansi dalam menuliskan kode sumber', ['Efisiensi penggunaan resources oleh kode dihitung.', 'Kemudahan interaksi selalu diimplementasikan sesuai standar yang berlaku.']]],
        'J.620100.017.02' => [['Menggunakan tipe data dan control program', ['Tipe data yang sesuai standar ditentukan.', 'Syntax program yang dikuasai digunakan sesuai standar.', 'Struktur kontrol program yang dikuasai digunakan sesuai standar.']],
            ['Membuat program sederhana', ['Program baca tulis untuk memasukkan data dari keyboard dan menampilkan ke layar monitor termasuk variasinya sesuai standar masukan/keluaran telah dibuat.', 'Struktur kontrol percabangan dan pengulangan dalam membuat program telah digunakan.']]],
        'J.620100.019.02' => [['Melakukan pemilihan unit-unit reuse yang dapat diaplikasikan', ['Class unit-unit reuse (dari aplikasi lain) yang sesuai dapat diidentifikasi.', 'Keuntungan efisiensi dari pemanfaatan komponen reuse dapat dihitung.', 'Lisensi, hak cipta dan hak paten tidak dilanggar dalam pemanfaatan komponen reuse tersebut.']],
            ['Melakukan integrasi library atau komponen pre-existing dengan source code yang ada', ['Ketergantungan antar unit diidentifikasi.', 'Penggunaan komponen yang sudah obsolete dihindari.', 'Program yang dihubungkan dengan library diterapkan.']]],
        'J.620100.023.02' => [['Melakukan identifikasi kode program', ['Modul program diidentifikasi.', 'Parameter yang dipergunakan diidentifikasi.', 'Algoritma dijelaskan cara kerjanya.']],
            ['Membuat dokumentasi modul program', ['Dokumentasi modul dibuat sesuai dengan identitas untuk memudahkan pelacakan.', 'Identifikasi dokumentasi diterapkan.', 'Kegunaan modul dijelaskan.']]],
        'J.620100.025.02' => [['Mempersiapkan kode program', ['Kode program sesuai spesifikasi disiapkan.', 'Debugging tools untuk melihat proses suatu modul dipersiapkan.']],
            ['Melakukan debugging', ['Kode program dikompilasi sesuai bahasa pemrograman yang digunakan.', 'Kriteria lulus build dianalisis.', 'Kriteria eksekusi aplikasi dianalisis.', 'Kode kesalahan dicatat.']],
            ['Memperbaiki program', ['Perbaikan terhadap kesalahan kompilasi maupun build dirumuskan.', 'Perbaikan dilakukan.']]],
        // ---------- Analis Data Junior (J.62DMI00)
        'J.62DMI00.001.1' => [['Menentukan objektif bisnis', ['Permasalahan bisnis diidentifikasi bersama pemangku kepentingan.', 'Tujuan bisnis dirumuskan secara terukur.']],
            ['Menentukan kebutuhan data', ['Kebutuhan data diidentifikasi berdasarkan objektif bisnis.', 'Kriteria keberhasilan proyek data ditetapkan.']]],
        'J.62DMI00.004.1' => [['Menentukan sumber data', ['Sumber data internal dan eksternal diidentifikasi.', 'Hak akses dan ketentuan penggunaan data dikonfirmasi.']],
            ['Mengambil data', ['Data diambil menggunakan teknik yang sesuai jenis sumber data.', 'Hasil pengambilan data didokumentasikan.']]],
        'J.62DMI00.005.1' => [['Melakukan analisis deskriptif', ['Statistik deskriptif setiap atribut dihitung.', 'Distribusi data divisualisasikan.']],
            ['Menilai kualitas data', ['Data hilang, duplikat dan pencilan diidentifikasi.', 'Laporan kualitas data disusun.']]],
        'J.62DMI00.008.1' => [['Melakukan pembersihan data', ['Nilai hilang ditangani dengan teknik yang sesuai.', 'Data duplikat dan tidak konsisten diperbaiki.']],
            ['Mendokumentasikan pembersihan data', ['Langkah pembersihan dicatat agar dapat diulang.', 'Dampak pembersihan terhadap jumlah data dilaporkan.']]],
        'J.62DMI00.013.1' => [['Menyiapkan parameter model', ['Teknik pemodelan dipilih sesuai tujuan analisis.', 'Data latih dan data uji dipisahkan.']],
            ['Menggunakan tools pemodelan', ['Model dibangun menggunakan tools yang ditetapkan.', 'Parameter model disetel untuk hasil terbaik.']]],
        'J.62DMI00.014.1' => [['Menggunakan kriteria evaluasi', ['Metrik evaluasi dipilih sesuai jenis model.', 'Kinerja model diukur pada data uji.']],
            ['Melakukan evaluasi', ['Hasil evaluasi dibandingkan dengan kriteria keberhasilan.', 'Rekomendasi tindak lanjut model disusun.']]],
        // ---------- Digital Marketing (M.702090)
        'M.702090.001.01' => [['Menganalisis pasar dan pesaing', ['Segmen dan persona target diidentifikasi.', 'Aktivitas pemasaran digital pesaing dianalisis.']],
            ['Menyusun rencana pemasaran digital', ['Tujuan dan KPI kampanye ditetapkan.', 'Kanal dan anggaran dialokasikan sesuai tujuan.']]],
        'M.702090.004.01' => [['Merencanakan konten media sosial', ['Kalender konten disusun sesuai rencana pemasaran.', 'Format konten disesuaikan dengan karakter tiap platform.']],
            ['Mengelola interaksi', ['Komentar dan pesan ditanggapi sesuai pedoman merek.', 'Isu negatif ditangani sesuai prosedur eskalasi.']]],
        'M.702090.007.01' => [['Menyiapkan kampanye iklan', ['Target audiens dan anggaran iklan diatur.', 'Materi iklan disiapkan sesuai ketentuan platform.']],
            ['Mengoptimalkan kampanye', ['Kinerja iklan dipantau secara berkala.', 'Penawaran, audiens atau materi disesuaikan berdasarkan data.']]],
        'M.702090.010.01' => [['Mengumpulkan data kinerja', ['Data kinerja dari setiap kanal dikumpulkan.', 'Data dirangkum terhadap KPI.']],
            ['Menyusun laporan dan rekomendasi', ['Wawasan utama disajikan dalam laporan.', 'Rekomendasi perbaikan kampanye disusun.']]],
        // ---------- Barista (I.56BRS00)
        'I.56BRS00.001.1' => [['Menyiapkan area kerja', ['Area kerja dibersihkan sesuai standar higiene.', 'Bahan dan perlengkapan ditata sesuai urutan kerja.']],
            ['Memeriksa peralatan', ['Mesin espresso dan grinder diperiksa fungsinya.', 'Kalibrasi gilingan dilakukan sesuai resep.']]],
        'I.56BRS00.003.1' => [['Mengekstraksi espresso', ['Dosis dan distribusi kopi diatur sesuai resep.', 'Tamping dilakukan merata.', 'Waktu ekstraksi dan volume dikendalikan.']],
            ['Menyajikan espresso', ['Espresso disajikan dengan crema yang baik.', 'Penyajian dilakukan pada suhu dan wadah yang tepat.']]],
        'I.56BRS00.004.1' => [['Menyiapkan susu', ['Susu di-steam pada suhu dan tekstur yang tepat.', 'Steam wand dibersihkan setelah digunakan.']],
            ['Membuat minuman', ['Minuman dibuat sesuai resep standar.', 'Latte art sederhana diterapkan sesuai pesanan.']]],
        'I.56BRS00.007.1' => [['Menerapkan higiene perorangan', ['Penampilan dan kebersihan diri dijaga sesuai standar.', 'Prosedur cuci tangan diterapkan.']],
            ['Menerapkan sanitasi area', ['Peralatan dibersihkan dan disanitasi sesuai jadwal.', 'Limbah ditangani sesuai prosedur.']]],
        // ---------- Housekeeping Supervisor (I.55HDT00)
        'I.55HDT00.001.1' => [['Membersihkan kamar', ['Urutan pembersihan kamar diterapkan sesuai SOP.', 'Kamar mandi dibersihkan dan disanitasi.']],
            ['Memeriksa kesiapan kamar', ['Perlengkapan kamar dilengkapi sesuai standar.', 'Status kamar diperbarui di sistem.']]],
        'I.55HDT00.006.1' => [['Merencanakan tugas tim', ['Pembagian tugas disusun sesuai tingkat hunian.', 'Briefing tim dilaksanakan setiap awal shift.']],
            ['Memantau kinerja tim', ['Hasil kerja diperiksa sesuai standar.', 'Umpan balik diberikan kepada anggota tim.']]],
        'I.55HDT00.009.1' => [['Mencatat persediaan linen', ['Stok linen dihitung secara berkala.', 'Linen rusak dicatat dan dipisahkan.']],
            ['Mengendalikan sirkulasi linen', ['Par stock dijaga sesuai kebutuhan.', 'Pengiriman dan penerimaan laundry diverifikasi.']]],
        // ---------- Teknisi Instalasi Listrik (F.43TIL01)
        'F.43TIL01.001.1' => [['Mengidentifikasi bahaya', ['Potensi bahaya listrik di area kerja diidentifikasi.', 'APD yang sesuai disiapkan.']],
            ['Menerapkan prosedur K3', ['Prosedur LOTO diterapkan sebelum bekerja.', 'Area kerja diamankan dengan rambu.']]],
        'F.43TIL01.004.1' => [['Menyiapkan pemasangan', ['Gambar instalasi dibaca dan dipahami.', 'Bahan dan alat disiapkan sesuai daftar.']],
            ['Memasang instalasi penerangan', ['Jalur kabel dipasang sesuai gambar.', 'Sambungan dibuat sesuai PUIL.', 'Fitting dan sakelar dipasang dengan benar.']]],
        'F.43TIL01.007.1' => [['Menyiapkan panel', ['Komponen panel diperiksa sesuai spesifikasi.', 'Tata letak komponen direncanakan.']],
            ['Memasang dan merangkai panel', ['Komponen dipasang dan dirangkai sesuai diagram.', 'Penandaan kabel dan komponen diterapkan.']]],
        'F.43TIL01.010.1' => [['Melakukan pengujian', ['Tahanan isolasi diukur.', 'Kontinuitas dan polaritas diuji.']],
            ['Membuat laporan pengujian', ['Hasil uji dicatat pada lembar uji.', 'Rekomendasi perbaikan disampaikan.']]],
        // ---------- Pengelola Administrasi Perkantoran (N.821100)
        'N.821100.001.02' => [['Menata arsip dinamis', ['Arsip dikelompokkan sesuai sistem klasifikasi.', 'Arsip disimpan pada sarana yang sesuai.']],
            ['Menemukan kembali arsip', ['Arsip ditemukan kembali dengan cepat dan tepat.', 'Peminjaman arsip dicatat.']]],
        'N.821100.004.02' => [['Mengumpulkan informasi kegiatan', ['Informasi kegiatan pimpinan dikumpulkan dari pihak terkait.', 'Prioritas kegiatan dikonfirmasi kepada pimpinan.']],
            ['Menyusun agenda', ['Agenda disusun tanpa bentrok waktu.', 'Perubahan agenda dikomunikasikan kepada pihak terkait.']]],
        'N.821100.009.02' => [['Menangani surat masuk', ['Surat masuk diagendakan dan didisposisikan.', 'Surat didistribusikan kepada unit yang dituju.']],
            ['Menangani surat keluar', ['Surat keluar disusun sesuai tata naskah.', 'Surat keluar dicatat dan dikirim.']]],
        'N.821100.012.02' => [['Mengoperasikan pengolah kata dan angka', ['Dokumen dibuat dengan format yang benar.', 'Rumus dan tabel lembar kerja diterapkan.']],
            ['Mengelola berkas digital', ['Berkas disimpan dengan penamaan yang konsisten.', 'Berkas dibagikan sesuai hak akses.']]],
    ];
}

/** Kelompok pekerjaan per kode unit (unit yang tidak tercantum = kelompok 1). */
function muk_isi_kelompok(): array
{
    return ['J.620100.011.01' => 1, 'J.620100.012.01' => 1, 'J.620100.004.02' => 2, 'J.620100.005.02' => 2, 'J.620100.016.01' => 2, 'J.620100.017.02' => 2,
        'J.620100.019.02' => 2, 'J.620100.023.02' => 3, 'J.620100.025.02' => 3,
        'J.62DMI00.013.1' => 2, 'J.62DMI00.014.1' => 2, 'M.702090.007.01' => 2, 'M.702090.010.01' => 2,
        'I.55HDT00.006.1' => 2, 'I.55HDT00.009.1' => 2, 'F.43TIL01.007.1' => 2, 'F.43TIL01.010.1' => 2];
}

/** Perangkat asesmen per kode skema. */
function muk_isi_perangkat(): array
{
    $pg = function (string $q, array $o, string $k): array { return ['pertanyaan' => $q, 'a' => $o[0], 'b' => $o[1], 'c' => $o[2], 'd' => $o[3], 'kunci' => $k]; };
    return [
        'JWD' => [
            'standar' => 'SKKNI Kepmenaker No. 282 Tahun 2016 (Pemrograman); spesifikasi aplikasi web tugas praktik',
            'IA.02' => [['kelompok' => 'Kelompok Pekerjaan 1 (Persiapan lingkungan)', 'skenario' => 'Pasang dan konfigurasi lingkungan pengembangan web (editor kode, PHP 8, basis data, Git) pada komputer yang disediakan, lalu jalankan proyek awal yang diberikan.', 'peralatan' => 'Komputer dengan akses internet terbatas, paket instalasi, berkas proyek awal', 'durasi' => '60 menit'],
                ['kelompok' => 'Kelompok Pekerjaan 2 (Pemrograman)', 'skenario' => 'Buat modul daftar tamu: formulir input dengan validasi, simpan ke basis data, tampilkan daftar dengan pencarian. Terapkan struktur data yang tepat, coding guidelines, dan library yang disediakan.', 'peralatan' => 'Lingkungan pengembangan dari Kelompok 1, dokumen spesifikasi', 'durasi' => '180 menit'],
                ['kelompok' => 'Kelompok Pekerjaan 3 (Dokumentasi & debugging)', 'skenario' => 'Perbaiki tiga galat pada modul yang disediakan menggunakan debugger, lalu buat dokumentasi modul (PHPDoc dan README).', 'peralatan' => 'Modul bermasalah, debugger', 'durasi' => '60 menit']],
            'IA.03' => ['Mengapa Anda memilih struktur data array asosiatif untuk menyimpan data tamu?', 'Bagaimana Anda menangani galat ketika koneksi basis data gagal?', 'Bagaimana Anda memastikan library pihak ketiga yang dipakai tidak melanggar lisensi?', 'Apa yang Anda periksa sebelum menyatakan program lulus build?'],
            'IA.04' => [['kelompok' => 'Seluruh unit', 'skenario' => 'Rancang dan buat prototipe aplikasi web pendaftaran kegiatan sederhana sesuai kebutuhan pengguna yang diberikan, lengkap dengan dokumentasi.', 'presentasi' => 'Presentasikan rancangan antarmuka, struktur data, dan hasil uji aplikasi.', 'waktu' => '90 menit persiapan, 20 menit presentasi']],
            'IA.05' => [$pg('Struktur data yang paling tepat untuk mencari data berdasarkan kunci unik dengan cepat adalah…', ['Array terurut biasa', 'Hash map / array asosiatif', 'Linked list', 'Stack'], 'b'),
                $pg('Perintah Git untuk menyimpan perubahan ke repositori lokal adalah…', ['git push', 'git clone', 'git commit', 'git fetch'], 'c'),
                $pg('Prinsip penulisan kode yang menghindari duplikasi logika disebut…', ['KISS', 'DRY', 'YAGNI', 'SOLID-O'], 'b'),
                $pg('Langkah pertama yang tepat saat menemukan galat pada program adalah…', ['Menulis ulang seluruh modul', 'Mereproduksi galat dan membaca pesan kesalahan', 'Menghapus baris yang mencurigakan', 'Mengganti versi bahasa pemrograman'], 'b'),
                $pg('Elemen HTML yang tepat untuk input alamat email adalah…', ['<input type="text">', '<input type="email">', '<textarea>', '<select>'], 'b')],
            'IA.06' => [['pertanyaan' => 'Jelaskan perbedaan validasi di sisi klien dan sisi server serta mengapa keduanya diperlukan.', 'kunci' => 'Validasi klien meningkatkan pengalaman pengguna dan mengurangi permintaan sia-sia; validasi server wajib karena klien dapat dimanipulasi sehingga keamanan dan integritas data dijamin di server.'],
                ['pertanyaan' => 'Uraikan langkah debugging yang sistematis.', 'kunci' => 'Reproduksi galat, isolasi lokasi (log/breakpoint), analisis penyebab, perbaiki, uji ulang, catat kode kesalahan dan perbaikannya.']],
            'IA.07' => [['pertanyaan' => 'Apa manfaat dokumentasi modul program bagi tim?', 'kunci' => 'Memudahkan pemeliharaan, pelacakan, dan pemahaman kegunaan serta parameter modul oleh anggota tim lain.'],
                ['pertanyaan' => 'Bagaimana Anda mengukur efisiensi penggunaan resource oleh kode?', 'kunci' => 'Mengukur waktu eksekusi, penggunaan memori, dan jumlah kueri; membandingkan kompleksitas algoritma.'],
                ['pertanyaan' => 'Kapan komponen pre-existing tidak boleh dipakai?', 'kunci' => 'Bila lisensinya tidak sesuai, sudah obsolete/tidak dipelihara, atau menimbulkan ketergantungan yang tidak perlu.']],
        ],
        'ADJ' => [
            'standar' => 'SKKNI Kepmenaker No. 299 Tahun 2020 (Data Science); studi kasus data penjualan',
            'IA.02' => [['kelompok' => 'Kelompok Pekerjaan 1', 'skenario' => 'Dari studi kasus penjualan ritel, rumuskan objektif bisnis, ambil data dari berkas CSV dan basis data, telaah dan bersihkan data.', 'peralatan' => 'Komputer dengan Python/Jupyter atau spreadsheet, dataset studi kasus', 'durasi' => '150 menit'],
                ['kelompok' => 'Kelompok Pekerjaan 2', 'skenario' => 'Bangun model prediksi penjualan sederhana dan evaluasi hasilnya terhadap kriteria keberhasilan.', 'peralatan' => 'Dataset bersih dari Kelompok 1, pustaka pemodelan', 'durasi' => '120 menit']],
            'IA.03' => ['Bagaimana Anda menentukan teknik penanganan nilai hilang pada atribut harga?', 'Mengapa data latih dan data uji harus dipisahkan?', 'Metrik apa yang Anda pakai untuk model regresi dan mengapa?'],
            'IA.04' => [['kelompok' => 'Seluruh unit', 'skenario' => 'Susun analisis singkat faktor yang memengaruhi penjualan dari dataset yang diberikan.', 'presentasi' => 'Presentasikan temuan, model, dan rekomendasi bisnis.', 'waktu' => '120 menit persiapan, 20 menit presentasi']],
            'IA.05' => [$pg('Nilai yang membagi data terurut menjadi dua bagian sama besar adalah…', ['Rata-rata', 'Median', 'Modus', 'Varians'], 'b'),
                $pg('Teknik yang tepat untuk mendeteksi pencilan pada data numerik adalah…', ['Interquartile range', 'One-hot encoding', 'Normalisasi min-max', 'Sampling acak'], 'a'),
                $pg('Metrik evaluasi untuk model klasifikasi biner yang tidak seimbang adalah…', ['MAE', 'F1-score', 'R2', 'RMSE'], 'b'),
                $pg('Langkah pertama dalam proyek data adalah…', ['Membangun model', 'Menentukan objektif bisnis', 'Membersihkan data', 'Visualisasi'], 'b'),
                $pg('Data duplikat sebaiknya…', ['Selalu dibiarkan', 'Diidentifikasi lalu ditangani sesuai konteks', 'Dihapus tanpa dicatat', 'Digandakan lagi'], 'b')],
            'IA.06' => [['pertanyaan' => 'Jelaskan apa itu overfitting dan cara mencegahnya.', 'kunci' => 'Model terlalu cocok dengan data latih sehingga buruk pada data baru; dicegah dengan validasi silang, regularisasi, data lebih banyak, model lebih sederhana.']],
            'IA.07' => [['pertanyaan' => 'Bagaimana Anda memastikan hak akses data dipenuhi sebelum mengambil data?', 'kunci' => 'Mengonfirmasi izin pemilik data, ketentuan penggunaan dan aturan perlindungan data pribadi.'],
                ['pertanyaan' => 'Apa isi minimal laporan kualitas data?', 'kunci' => 'Kelengkapan, keunikan, konsistensi, pencilan, dan dampaknya terhadap analisis.']],
        ],
        'DM' => [
            'standar' => 'SKKNI Kepmenaker No. 34 Tahun 2018 (Pemasaran Digital); brief kampanye produk UMKM',
            'IA.02' => [['kelompok' => 'Kelompok Pekerjaan 1', 'skenario' => 'Susun strategi pemasaran digital dan kalender konten satu bulan untuk brief produk UMKM yang diberikan.', 'peralatan' => 'Komputer, akses akun demo media sosial, brief', 'durasi' => '120 menit'],
                ['kelompok' => 'Kelompok Pekerjaan 2', 'skenario' => 'Siapkan kampanye iklan berbayar (audiens, anggaran, materi) dan analisis laporan kinerja yang disediakan.', 'peralatan' => 'Akun iklan demo, data kinerja kampanye', 'durasi' => '120 menit']],
            'IA.03' => ['Bagaimana Anda menentukan KPI untuk kampanye kesadaran merek?', 'Apa langkah Anda bila muncul komentar negatif yang viral?', 'Kapan Anda menghentikan materi iklan yang berjalan?'],
            'IA.05' => [$pg('Metrik yang mengukur persentase klik dari tayangan iklan adalah…', ['CPM', 'CTR', 'CPA', 'ROAS'], 'b'),
                $pg('Dokumen yang mengatur jadwal unggahan konten disebut…', ['Brief kreatif', 'Kalender konten', 'Media plan', 'Moodboard'], 'b'),
                $pg('Persona pelanggan disusun berdasarkan…', ['Asumsi tim kreatif', 'Data dan riset audiens', 'Pesaing saja', 'Anggaran'], 'b'),
                $pg('ROAS dihitung dengan…', ['Pendapatan iklan dibagi biaya iklan', 'Klik dibagi tayangan', 'Biaya dibagi konversi', 'Tayangan dibagi jangkauan'], 'a'),
                $pg('Uji A/B dipakai untuk…', ['Membandingkan dua variasi materi', 'Menghapus akun', 'Menaikkan anggaran', 'Memblokir komentar'], 'a')],
            'IA.06' => [['pertanyaan' => 'Uraikan langkah optimasi kampanye yang biaya per akuisisinya terlalu tinggi.', 'kunci' => 'Analisis segmen dan materi, persempit/ubah audiens, uji materi baru, sesuaikan penawaran dan halaman tujuan, realokasi anggaran.']],
            'IA.07' => [['pertanyaan' => 'Bagaimana menyesuaikan satu konten untuk Instagram dan LinkedIn?', 'kunci' => 'Menyesuaikan format, nada bahasa, panjang teks dan ajakan bertindak dengan karakter audiens tiap platform.']],
        ],
        'BRS' => [
            'standar' => 'SKKNI Kepmenaker No. 213 Tahun 2016 (Barista); SOP bar TUK',
            'IA.02' => [['kelompok' => 'Kelompok Pekerjaan 1', 'skenario' => 'Siapkan area dan peralatan bar, kalibrasi grinder, lalu sajikan 2 espresso dan 2 cappuccino dalam waktu yang ditentukan dengan menerapkan higiene dan sanitasi.', 'peralatan' => 'Mesin espresso, grinder, biji kopi, susu, cangkir, alat kebersihan', 'durasi' => '45 menit']],
            'IA.03' => ['Berapa waktu ekstraksi espresso yang Anda targetkan dan mengapa?', 'Apa yang Anda lakukan bila espresso terasa terlalu pahit?', 'Bagaimana prosedur membersihkan steam wand?'],
            'IA.05' => [$pg('Waktu ekstraksi espresso tunggal yang umum adalah…', ['5–10 detik', '25–30 detik', '60 detik', '2 menit'], 'b'),
                $pg('Suhu ideal susu untuk cappuccino sekitar…', ['30 °C', '60–65 °C', '90 °C', '100 °C'], 'b'),
                $pg('Gilingan terlalu kasar menyebabkan ekstraksi…', ['Lambat', 'Cepat dan encer', 'Tidak berubah', 'Terlalu pahit'], 'b'),
                $pg('Peralatan bar disanitasi…', ['Seminggu sekali', 'Sesuai jadwal dan setelah digunakan', 'Hanya saat inspeksi', 'Tidak perlu'], 'b'),
                $pg('Tamping yang tidak rata menyebabkan…', ['Channeling', 'Crema tebal', 'Suhu turun', 'Susu pecah'], 'a')],
            'IA.07' => [['pertanyaan' => 'Bagaimana Anda menangani limbah ampas kopi?', 'kunci' => 'Dibuang ke wadah tertutup terpisah sesuai prosedur limbah, area dibersihkan.']],
        ],
        'HKS' => [
            'standar' => 'SKKNI Kepmenaker No. 145 Tahun 2015 (Housekeeping); SOP hotel TUK',
            'IA.02' => [['kelompok' => 'Kelompok Pekerjaan 1', 'skenario' => 'Siapkan satu kamar check-out menjadi siap jual sesuai SOP dan periksa kesiapannya.', 'peralatan' => 'Kamar praktik, trolley, linen, amenities', 'durasi' => '45 menit'],
                ['kelompok' => 'Kelompok Pekerjaan 2', 'skenario' => 'Pimpin briefing tim, susun pembagian tugas untuk hunian 80%, dan lakukan penghitungan par stock linen.', 'peralatan' => 'Data hunian, formulir tugas, data stok linen', 'durasi' => '60 menit']],
            'IA.03' => ['Bagaimana urutan pembersihan kamar yang Anda terapkan?', 'Apa yang Anda lakukan bila anggota tim tidak mencapai standar?', 'Bagaimana menghitung kebutuhan par stock linen?'],
            'IA.05' => [$pg('Status kamar yang siap dijual disebut…', ['OD', 'VR', 'OOO', 'VD'], 'b'),
                $pg('Par stock linen hotel umumnya…', ['1 par', '3–5 par', '10 par', 'Tidak ditentukan'], 'b'),
                $pg('Briefing tim dilakukan…', ['Akhir bulan', 'Setiap awal shift', 'Saat ada keluhan', 'Tahunan'], 'b'),
                $pg('Linen rusak harus…', ['Dipakai sampai habis', 'Dicatat dan dipisahkan', 'Dibuang tanpa dicatat', 'Dijual'], 'b'),
                $pg('Kamar mandi dibersihkan dengan urutan…', ['Lantai lalu cermin', 'Dari atas ke bawah, kering ke basah', 'Acak', 'Hanya bila kotor'], 'b')],
            'IA.07' => [['pertanyaan' => 'Bagaimana memberi umpan balik kepada anggota tim?', 'kunci' => 'Spesifik, segera, berbasis standar, disertai contoh dan tindak lanjut.']],
        ],
        'TIL' => [
            'standar' => 'SKKNI Kepmenaker No. 2 Tahun 2018 (Instalasi Listrik); PUIL 2011',
            'IA.02' => [['kelompok' => 'Kelompok Pekerjaan 1', 'skenario' => 'Pasang instalasi penerangan satu ruangan (2 titik lampu, 1 sakelar ganda, 1 stop kontak) sesuai gambar dengan menerapkan K3.', 'peralatan' => 'Panel latih, kabel NYM, pipa, fitting, sakelar, APD, alat ukur', 'durasi' => '120 menit'],
                ['kelompok' => 'Kelompok Pekerjaan 2', 'skenario' => 'Rangkai panel hubung bagi 1 fase dan uji instalasi (tahanan isolasi, kontinuitas, polaritas), lalu isi lembar uji.', 'peralatan' => 'Box panel, MCB, terminal, megger, multimeter', 'durasi' => '120 menit']],
            'IA.03' => ['Apa langkah LOTO sebelum Anda bekerja?', 'Berapa nilai minimum tahanan isolasi yang Anda terima?', 'Mengapa penandaan kabel diperlukan?'],
            'IA.05' => [$pg('Alat untuk mengukur tahanan isolasi adalah…', ['Tang ampere', 'Megger', 'Lux meter', 'Tespen'], 'b'),
                $pg('Warna kabel netral menurut PUIL adalah…', ['Hitam', 'Biru', 'Kuning-hijau', 'Merah'], 'b'),
                $pg('Kabel pentanahan berwarna…', ['Biru', 'Kuning-hijau', 'Coklat', 'Abu-abu'], 'b'),
                $pg('Prosedur penguncian sumber energi disebut…', ['LOTO', 'SOP', 'JSA', 'APD'], 'a'),
                $pg('MCB berfungsi sebagai…', ['Pengaman arus lebih', 'Pengukur daya', 'Penstabil tegangan', 'Penerang'], 'a')],
            'IA.07' => [['pertanyaan' => 'Apa yang Anda lakukan bila hasil uji polaritas terbalik?', 'kunci' => 'Hentikan pemberian tegangan, periksa dan perbaiki sambungan fasa-netral, uji ulang dan catat.']],
        ],
        'APK' => [
            'standar' => 'SKKNI Kepmenaker No. 183 Tahun 2016 (Administrasi Perkantoran); tata naskah dinas TUK',
            'IA.02' => [['kelompok' => 'Kelompok Pekerjaan 1', 'skenario' => 'Tata 20 arsip sesuai klasifikasi, agendakan dan disposisikan 3 surat masuk, susun 1 surat keluar dan agenda pimpinan satu minggu menggunakan aplikasi perkantoran.', 'peralatan' => 'Komputer, aplikasi perkantoran, berkas arsip contoh, buku agenda', 'durasi' => '120 menit']],
            'IA.03' => ['Sistem klasifikasi arsip apa yang Anda gunakan?', 'Bagaimana Anda menangani agenda pimpinan yang bentrok?', 'Bagaimana penamaan berkas digital yang konsisten?'],
            'IA.05' => [$pg('Arsip yang masih digunakan langsung dalam kegiatan disebut arsip…', ['Statis', 'Dinamis aktif', 'Vital', 'Inaktif'], 'b'),
                $pg('Disposisi surat berisi…', ['Tanggal lahir pengirim', 'Instruksi pimpinan atas surat', 'Daftar hadir', 'Nomor rekening'], 'b'),
                $pg('Rumus untuk menjumlahkan sel A1 sampai A10 adalah…', ['=COUNT(A1:A10)', '=SUM(A1:A10)', '=AVG(A1:A10)', '=ADD(A1:A10)'], 'b'),
                $pg('Agenda pimpinan disusun agar…', ['Tidak ada kegiatan bentrok', 'Pimpinan sibuk', 'Surat cepat dikirim', 'Arsip rapi'], 'a'),
                $pg('Peminjaman arsip harus…', ['Dicatat', 'Tidak boleh', 'Lisan saja', 'Tanpa batas waktu'], 'a')],
            'IA.07' => [['pertanyaan' => 'Bagaimana memastikan berkas digital dibagikan sesuai hak akses?', 'kunci' => 'Menggunakan folder/izin per peran, memeriksa daftar penerima, dan mencatat pembagian.']],
        ],
    ];
}

/** Peta instrumen (FR.MAPA.02) bawaan: instrumen → potensi asesi yang memakainya. */
function muk_isi_peta(string $kodeSkema): array
{
    $proyek = in_array($kodeSkema, ['JWD', 'ADJ'], true);
    return [
        'IA.01' => ['potensi' => ['1', '2', '4', '5']], 'IA.02' => ['potensi' => ['1', '2', '4', '5']], 'IA.03' => ['potensi' => ['1', '2', '4', '5']],
        'IA.04' => ['potensi' => $proyek ? ['3'] : null], 'IA.05' => ['potensi' => ['1', '2', '3', '4', '5']],
        'IA.06' => ['potensi' => in_array($kodeSkema, ['JWD', 'ADJ', 'DM'], true) ? ['2', '5'] : null],
        'IA.07' => ['potensi' => ['3', '4']], 'IA.08' => ['potensi' => ['3', '4']], 'IA.09' => ['potensi' => ['3']], 'IA.10' => ['potensi' => ['3']],
        'IA.11' => ['potensi' => null],
    ];
}
