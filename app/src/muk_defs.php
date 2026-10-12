<?php
declare(strict_types=1);

/*
 * Definisi formulir Buku Kerja 333-25 (BNSP) untuk asesmen paperless.
 * Teks pernyataan, panduan, dan opsi disalin dari formulir asli. Satu definisi dipakai server (validasi)
 * dan browser (tampilan), sehingga isian selalu sesuai bentuk formulirnya.
 *
 * Jenis kolom: teks, panjang, tanggal, waktu, pilih (satu opsi), multi (beberapa opsi), yt (Ya/Tidak), kbk (K/BK), info (teks tetap).
 * Tabel ('t' => 'grid'): baris dari 'baris' = unit | elemen | kuk | item (butir perangkat) | tetap (daftar 'rows') | bebas (pengisi menambah baris)
 *   | asesi (permohonan pada jadwal), dengan kolom 'kol'. Opsi kolom '@row' = opsi milik baris.
 * 'oleh' = pihak yang mengisi kolom (default: pengisi utama formulir). 'wajib' = harus diisi sebelum pihak itu menandatangani.
 * 'rahasia' = tidak pernah dikirim ke asesi (mis. kunci jawaban).
 */

const MUK_POTENSI = [
    '1' => 'Hasil pelatihan dan / atau pendidikan, dimana Kurikulum dan fasilitas praktek mampu telusur terhadap standar kompetensi.',
    '2' => 'Hasil pelatihan dan / atau pendidikan, dimana kurikulum belum berbasis kompetensi.',
    '3' => 'Pekerja berpengalaman, dimana berasal dari industri/tempat kerja yang dalam operasionalnya mampu telusur dengan standar kompetensi.',
    '4' => 'Pekerja berpengalaman, dimana berasal dari industri/tempat kerja yang dalam operasionalnya belum berbasis kompetensi.',
    '5' => 'Pelatihan / belajar mandiri atau otodidak.',
];

/** Instrumen asesmen (baris FR.MAPA.02) → formulir isian per asesi. */
const MUK_INSTRUMEN = [
    'IA.01' => 'FR.IA.01. CL - Ceklis Observasi Aktivitas Di Tempat Kerja atau Tempat Kerja Simulasi',
    'IA.02' => 'FR.IA.02. TPD - Tugas Praktik Demonstrasi',
    'IA.03' => 'FR.IA.03. PMO - Pertanyaan Untuk Mendukung Observasi',
    'IA.04' => 'FR.IA.04A/B. DIT - Daftar Instruksi Terstruktur dan Penilaian Proyek Singkat atau Kegiatan Terstruktur Lainnya',
    'IA.05' => 'FR.IA.05. DPT - Daftar Pertanyaan Tertulis Pilihan Ganda',
    'IA.06' => 'FR.IA.06. DPT – Daftar Pertanyaan Tertulis Esai',
    'IA.07' => 'FR.IA.07. DPL – Daftar Pertanyaan Lisan',
    'IA.08' => 'FR.IA.08. CVP - Ceklis Verifikasi Portofolio',
    'IA.09' => 'FR.IA.09. PW - Pertanyaan Wawancara',
    'IA.10' => 'FR.IA.10. VPK - Verifikasi Pihak Ketiga',
    'IA.11' => 'FR.IA.11. CRP - Ceklis Reviu Produk',
];

/** Kolom bukti FR.AK.02 dan instrumen yang mengisinya. */
const MUK_AK02_BUKTI = [
    'obs' => ['Observasi Demonstrasi', ['IA.01', 'IA.02', 'IA.03']],
    'porto' => ['Portofolio', ['IA.08']],
    'pk' => ['Pernyataan Pihak Ketiga Pertanyaan Wawancara', ['IA.09', 'IA.10']],
    'lisan' => ['Pertanyaan Lisan', ['IA.07']],
    'tulis' => ['Pertanyaan Tertulis', ['IA.05', 'IA.06']],
    'proyek' => ['Proyek Kerja', ['IA.04']],
    'lain' => ['Lainnya', ['IA.11']],
];

const MUK_YT = ['Ya', 'Tidak'];
const MUK_KBK = ['K', 'BK'];
const MUK_REKOM_IA = ['K' => 'Asesi telah memenuhi pencapaian seluruh kriteria unjuk kerja, direkomendasikan KOMPETEN',
    'BK' => 'Asesi belum memenuhi pencapaian seluruh kriteria unjuk kerja, direkomendasikan BELUM KOMPETEN'];

function muk_defs(): array
{
    static $defs = null;
    if ($defs !== null) {
        return $defs;
    }
    $ttdAA = ['asesor' => 'Asesor', 'asesi' => 'Asesi'];
    $ttdJawab = ['asesi' => 'Asesi', 'asesor' => 'Asesor'];
    $umpan = ['k' => 'umpan_balik', 't' => 'panjang', 'l' => 'Umpan balik untuk asesi'];
    $rekom = ['k' => 'rekomendasi', 't' => 'pilih', 'l' => 'Rekomendasi', 'o' => MUK_REKOM_IA, 'wajib' => true];
    $bkPada = ['k' => 'bk_pada', 't' => 'teks', 'l' => 'Belum kompeten pada (Kelompok Pekerjaan / Unit / Elemen / KUK)'];
    $panduan = function (array $kalimat): array { return ['k' => 'panduan', 't' => 'info', 'l' => 'Panduan bagi asesor', 'v' => $kalimat]; };

    $defs = [
        /* ===================== Pra-asesmen ===================== */
        'APL.01' => [
            'judul' => 'FR.APL.01. Permohonan Sertifikasi Kompetensi', 'urut' => 1, 'lingkup' => 'permohonan', 'tahap' => 'Pendaftaran',
            'pengisi' => 'asesi', 'ttd' => ['asesi' => 'Pemohon/ Kandidat', 'admin' => 'Admin LSP'],
            'bagian' => [
                ['j' => 'Bagian 1 : Rincian Data Pemohon Sertifikasi', 'f' => [
                    ['k' => 'info1', 't' => 'info', 'v' => ['Pada bagian ini, cantumlan data pribadi, data pendidikan formal serta data pekerjaan anda pada saat ini.']],
                    ['k' => 'pendidikan', 't' => 'teks', 'l' => 'Kualifikasi Pendidikan', 'wajib' => true],
                    ['k' => 'institusi', 't' => 'teks', 'l' => 'Nama Institusi /Perusahaan'],
                    ['k' => 'jabatan', 't' => 'teks', 'l' => 'Jabatan'],
                    ['k' => 'alamat_kantor', 't' => 'teks', 'l' => 'Alamat Kantor'],
                    ['k' => 'telp_kantor', 't' => 'teks', 'l' => 'No. Telp/Fax/E-mail Kantor'],
                ]],
                ['j' => 'Bagian 2 : Data Sertifikasi', 'f' => [
                    ['k' => 'info2', 't' => 'info', 'v' => ['Tuliskan Judul dan Nomor Skema Sertifikasi yang anda ajukan berikut Daftar Unit Kompetensi sesuai kemasan pada skema sertifikasi untuk mendapatkan pengakuan sesuai dengan latar belakang pendidikan, pelatihan serta pengalaman kerja yang anda miliki.']],
                    ['k' => 'tujuan', 't' => 'pilih', 'l' => 'Tujuan Asesmen', 'wajib' => true,
                        'o' => ['Sertifikasi', 'Pengakuan Kompetensi Terkini (PKT)', 'Rekognisi Pembelajaran Lampau (RPL)', 'Lainnya']],
                    ['k' => 'units', 't' => 'grid', 'l' => 'Daftar Unit Kompetensi sesuai kemasan', 'baris' => 'unit', 'kol' => []],
                ]],
                ['j' => 'Bagian 3 : Bukti Kelengkapan Pemohon', 'f' => [
                    ['k' => 'dasar', 't' => 'grid', 'l' => 'a. Bukti Persyaratan Dasar Pemohon', 'baris' => 'persyaratan', 'kol' => [
                        ['k' => 'bukti', 't' => 'teks', 'l' => 'Bukti yang dilampirkan', 'oleh' => 'asesi', 'wajib' => true],
                        ['k' => 'status', 't' => 'pilih', 'l' => 'Verifikasi LSP', 'oleh' => 'admin', 'wajib' => true,
                            'o' => ['MS' => 'Memenuhi Syarat', 'TMS' => 'Tidak Memenuhi Syarat', 'TA' => 'Tidak Ada']],
                    ]],
                    ['k' => 'admin_bukti', 't' => 'grid', 'l' => 'b. Bukti Administratif', 'baris' => 'tetap',
                        'rows' => ['ktp' => 'KTP/NIK/Paspor', 'foto' => 'Pas foto', 'ijazah' => 'Ijazah terakhir'], 'kol' => [
                        ['k' => 'status', 't' => 'pilih', 'l' => 'Verifikasi LSP', 'oleh' => 'admin', 'wajib' => true,
                            'o' => ['MS' => 'Memenuhi Syarat', 'TMS' => 'Tidak Memenuhi Syarat', 'TA' => 'Tidak Ada']],
                    ]],
                ]],
                ['j' => 'Rekomendasi (diisi oleh LSP)', 'f' => [
                    ['k' => 'rekom', 't' => 'pilih', 'l' => 'Berdasarkan ketentuan persyaratan dasar, maka pemohon:', 'oleh' => 'admin', 'wajib' => true,
                        'o' => ['diterima' => 'Diterima sebagai peserta sertifikasi', 'tidak' => 'Tidak diterima sebagai peserta sertifikasi']],
                    ['k' => 'catatan', 't' => 'panjang', 'l' => 'Catatan', 'oleh' => 'admin'],
                ]],
            ],
        ],
        'APL.02' => [
            'judul' => 'FR.APL.02. Asesmen Mandiri', 'urut' => 2, 'lingkup' => 'permohonan', 'tahap' => 'Pendaftaran',
            'pengisi' => 'asesi', 'ttd' => ['asesi' => 'Asesi', 'asesor' => 'Ditinjau Oleh Asesor'],
            'bagian' => [
                ['j' => 'Panduan Asesmen Mandiri', 'f' => [
                    ['k' => 'panduan', 't' => 'info', 'v' => ['Baca setiap pertanyaan di kolom sebelah kiri',
                        'Beri tanda centang (√) pada kotak jika Anda yakin dapat melakukan tugas yang dijelaskan.',
                        'Isi kolom di sebelah kanan dengan menuliskan bukti yang relevan anda miliki untuk menunjukkan bahwa anda melakukan pekerjaan.']],
                    ['k' => 'elemen', 't' => 'grid', 'l' => 'Dapatkah Saya ?', 'baris' => 'elemen', 'kol' => [
                        ['k' => 'kbk', 't' => 'kbk', 'l' => 'K / BK', 'oleh' => 'asesi', 'wajib' => true],
                        ['k' => 'bukti', 't' => 'teks', 'l' => 'Bukti yang relevan', 'oleh' => 'asesi', 'wajib' => true],
                    ]],
                ]],
                ['j' => 'Rekomendasi Untuk Asesi', 'f' => [
                    ['k' => 'rekom', 't' => 'pilih', 'l' => 'Asesmen', 'oleh' => 'asesor', 'wajib' => true,
                        'o' => ['dapat' => 'Asesmen dapat dilanjutkan', 'tidak' => 'Asesmen tidak dapat dilanjutkan']],
                    ['k' => 'catatan', 't' => 'panjang', 'l' => 'Catatan asesor', 'oleh' => 'asesor'],
                ]],
            ],
        ],
        'AK.07' => [
            'judul' => 'FR.AK.07. Ceklis Penyesuaian yang Wajar dan Beralasan', 'urut' => 9, 'lingkup' => 'permohonan', 'tahap' => 'Konsultasi pra-asesmen',
            'pengisi' => 'asesor', 'ttd' => ['asesor' => 'Asesor', 'asesi' => 'Asesi'],
            'bagian' => [
                ['j' => 'Panduan bagi asesor', 'f' => [
                    $panduan(['Formulir ini dapat digunakan (sebelum pra asesmen, saat pelaksanaan pra asesmen, setelah pra asesmen) jika ada asesi yang mempunyai keterbatasan sesuai karakteristik yang dimilikinya sehinga diperlukan penyesuaian yang wajar dan beralasan atau jika rencana asesmen dan perangkat asesmen tidak sesuai dengan acuan pembanding, potensi asesi dan konteks asesi atau jika asesi merasa keletihan, sakit, serta jika kondisi alam, listrik padam.',
                        'Jika jawaban Ya, pilih keterangan yang sesuai (boleh lebih dari satu). Jika jawaban Tidak, kolom keterangan tidak perlu diisi.']),
                    ['k' => 'potensi', 't' => 'pilih', 'l' => 'Potensi Asesi', 'o' => MUK_POTENSI, 'wajib' => true],
                ]],
                ['j' => 'Mengidentifikasi Persyaratan Modifikasi dan Kontekstualisasi (karakteristik asesi)', 'f' => [
                    ['k' => 'butir', 't' => 'grid', 'baris' => 'tetap', 'rows' => [
                        '1' => ['Keterbatasan asesi terhadap persyaratan bahasa, literasi, numerasi.', ['Memerlukan dukungan pembaca, penerjemah, pelayan, penulis untuk merekam jawaban asesi.', 'Melakukan asesmen verbal (gunakan pertanyaan lisan/pertanyaan wawancara) dengan dilengkapi gambar diagram dan bentuk-bentuk visual.', 'Menggunakan hasil produksi', 'Menggunakan ceklis observasi/demonstrasi.', 'Menggunakan daftar instruksi terstruktur.']],
                        '2' => ['Penyediaan dukungan pembaca, penerjemah, pelayan, penulis.', ['Menggunakan pertanyaan lisan dengan dilengkapi gambar diagram dan bentuk-bentuk visual.', 'Menggunakan pertanyaan wawancara dengan dilengkapi gambar diagram dan bentuk-bentuk visual.']],
                        '3' => ['Penggunaan teknologi adaptif atau peralatan khusus. (Tidak dapat menggunakan teknologi adaptif (misal: mengoperasikan komputer dan printer, peralatan digital dsb).', ['Ceklis observasi/demonstrasi.', 'Pertanyaan lisan', 'Pertanyaan tertulis.', 'Pertanyaan wawancara.', 'Daftar instruksi terstruktur.', 'Ceklis verifikasi portofolio.', 'Menggunakan dukungan operator komputer.']],
                        '4' => ['Pelaksanaan asesmen secara fleksibel karena alasan keletihan atau keperluan pengobatan.', ['Menggunakan juru tulis.', 'Menggunakan kamera perekam video/audio.', 'Memperbolehkan periode waktu yang lebih panjang untuk menyelesaikan tugas pekerjaan dalam asesmen.', 'Melakukan tugas pekerjaan dalam asesmen dengan waktu lebih pendek.', 'Menggunakan instruksi-instruksi spesifik pada proyek yang dapat dilakukan pada berbagai tingkatan.']],
                        '5' => ['Penyediaan peralatan asesmen berupa braille, audio/video-tape.', ['Menggunakan pertanyaan lisan.', 'Menggunakan pertanyaan wawancara.']],
                        '6' => ['Penyesuaian tempat fisik/lingkungan asesmen', ['Pertanyaan lisan.', 'Pertanyaan tulis.', 'Pertanyaan wawancara.', 'Ceklis verifikasi portofolio.', 'Ceklis reviu produk.', 'Daftar instruksi terstruktur.']],
                        '7' => ['Pertimbangan umur/usia lanjut/gender asesi. (Adanya perbedaan usia dengan asesor yang lebih muda).', ['Menggunakan studi kasus/daftar instruksi terstruktur', 'Menggunakan instrumen asesmen dengan huruf normal jangan terlalu kecil.', 'Menggunakan asesor dengan jenis kelamin yang sama dengan asesi.', 'Menggunakan instrumen asesmen yang sama walaupun berbeda jenis kelamin.']],
                        '8' => ['Pertimbangan budaya/tradisi/agama.', ['Menggunakan studi kasus daftar instruksi terstruktur', 'Menggunakan asesor tanpa pertimbangan budaya/tradisi/agama.', 'Menggunakan instrumen asesmen yang sama walaupun berbeda budaya/tradisi/agama.']],
                    ], 'kol' => [
                        ['k' => 'perlu', 't' => 'yt', 'l' => 'Diperlukan penyesuaian', 'wajib' => true],
                        ['k' => 'ket', 't' => 'multi', 'l' => 'Keterangan', 'o' => '@row'],
                    ]],
                ]],
                ['j' => 'Hasil kesepakatan antara asesi dengan asesor', 'f' => [
                    ['k' => 'sepakat', 't' => 'pilih', 'l' => 'Hasil kesepakatan', 'wajib' => true, 'o' => ['tidak' => 'Tidak diperlukan penyesuaian', 'ya' => 'Diperlukan penyesuaian']],
                    ['k' => 'acuan', 't' => 'teks', 'l' => 'Acuan Pembanding Asesmen'],
                    ['k' => 'metode', 't' => 'teks', 'l' => 'Metode Asesmen'],
                    ['k' => 'instrumen', 't' => 'multi', 'l' => 'Instrumen Asesmen yang disepakati (tambahan)', 'o' => MUK_INSTRUMEN],
                ]],
            ],
        ],
        'AK.01' => [
            'judul' => 'FR.AK.01. Persetujuan Asesmen dan Kerahasiaan', 'urut' => 7, 'lingkup' => 'permohonan', 'tahap' => 'Konsultasi pra-asesmen',
            'pengisi' => 'asesor', 'ttd' => ['asesor' => 'Asesor', 'asesi' => 'Asesi'],
            'bagian' => [
                ['j' => 'Persetujuan Asesmen', 'f' => [
                    ['k' => 'pengantar', 't' => 'info', 'v' => ['Persetujuan Asesmen ini untuk menjamin bahwa Asesi telah diberi arahan secara rinci tentang perencanaan dan proses asesmen']],
                    ['k' => 'bukti', 't' => 'multi', 'l' => 'Bukti yang akan dikumpulkan', 'wajib' => true, 'o' => [
                        'porto' => 'Hasil Verifikasi Portofolio', 'produk' => 'Hasil Reviu Produk', 'observasi' => 'Hasil Observasi Langsung',
                        'terstruktur' => 'Hasil Kegiatan Terstruktur', 'tanya' => 'Hasil Tanya Jawab', 'lisan' => 'Hasil Pertanyaan Lisan',
                        'tulis' => 'Hasil Pertanyaan tulis', 'wawancara' => 'Hasil Pertanyaan Wawancara', 'lain' => 'Lainnya']],
                    ['k' => 'tanggal', 't' => 'tanggal', 'l' => 'Pelaksanaan asesmen disepakati pada: Hari/Tanggal', 'wajib' => true],
                    ['k' => 'waktu', 't' => 'teks', 'l' => 'Waktu', 'wajib' => true],
                    ['k' => 'tuk', 't' => 'teks', 'l' => 'TUK', 'wajib' => true],
                ]],
                ['j' => 'Pernyataan', 'f' => [
                    ['k' => 'p_asesor', 't' => 'info', 'v' => ['Asesor : Menyatakan tidak akan membuka hasil pekerjaan yang saya peroleh karena penugasan saya sebagai Asesor dalam pekerjaan Asesmen kepada siapa pun atau organisasi apapun selain kepada pihak yang berwenang sehubungan dengan kewajiban saya sebagai Asesor yang ditugaskan oleh LSP.']],
                    ['k' => 'coi', 't' => 'yt', 'l' => 'Asesor menyatakan tidak memiliki benturan kepentingan dengan asesi ini (tidak melatih asesi dalam 2 tahun terakhir, tidak ada hubungan keluarga, atasan/bawahan, atau kepentingan lain) — PBNSP 201 §5.2.4e, §6.2.1', 'oleh' => 'asesor', 'wajib' => true],
                    ['k' => 'p_asesi', 't' => 'info', 'v' => ['Asesi : Saya setuju mengikuti asesmen dengan pemahaman bahwa informasi yang dikumpulkan hanya digunakan untuk pengembangan profesional dan hanya dapat diakses oleh orang tertentu saja.']],
                    ['k' => 'banding_dijelaskan', 't' => 'yt', 'l' => 'Proses banding dan kesempatan asesmen ulang telah dijelaskan kepada asesi', 'oleh' => 'asesor', 'wajib' => true],
                ]],
            ],
        ],

        /* ===================== Instrumen asesmen (isian per asesi) ===================== */
        'IA.01' => [
            'judul' => 'FR.IA.01. CL - Ceklis Observasi Aktivitas di Tempat Kerja atau Tempat Kerja Simulasi', 'urut' => 10, 'lingkup' => 'permohonan',
            'tahap' => 'Asesmen', 'pengisi' => 'asesor', 'ttd' => $ttdAA, 'instrumen' => true,
            'bagian' => [
                ['j' => 'Panduan bagi asesor', 'f' => [$panduan(['Lengkapi nama unit kompetensi, elemen, dan kriteria unjuk kerja sesuai kolom dalam tabel.', 'Isilah standar industri atau tempat kerja',
                    'Beri tanda centang (√) pada kolom “YA” jika Anda yakin asesi dapat melakukan/ mendemonstrasikan tugas sesuai KUK, atau centang (√) pada kolom “Tidak” bila sebaliknya.',
                    'Penilaian Lanjut diisi bila hasil belum dapat disimpulkan, untuk itu gunakan metode lain sehingga keputusan dapat dibuat.'])]],
                ['j' => 'Ceklis observasi per Kriteria Unjuk Kerja', 'f' => [
                    ['k' => 'kuk', 't' => 'grid', 'baris' => 'kuk', 'kol' => [
                        ['k' => 'standar', 't' => 'teks', 'l' => 'Standar Industri atau Tempat Kerja'],
                        ['k' => 'capai', 't' => 'yt', 'l' => 'Pencapaian', 'wajib' => true],
                        ['k' => 'lanjut', 't' => 'teks', 'l' => 'Penilaian Lanjut'],
                    ]],
                    $umpan, $rekom, $bkPada,
                ]],
            ],
        ],
        'IA.02' => [
            'judul' => 'FR.IA.02. TPD - Tugas Praktik Demonstrasi', 'urut' => 11, 'lingkup' => 'permohonan', 'tahap' => 'Asesmen', 'pengisi' => 'asesor',
            'ttd' => $ttdAA, 'instrumen' => true, 'perangkat' => 'IA.02',
            'bagian' => [
                ['j' => 'A. Petunjuk', 'f' => [['k' => 'petunjuk', 't' => 'info', 'v' => ['Baca dan pelajari setiap instruksi kerja di bawah ini dengan cermat sebelum melaksanakan praktek',
                    'Klarifikasi kepada asesor kompetensi apabila ada hal-hal yang belum jelas', 'Laksanakan pekerjaan sesuai dengan urutan proses yang sudah ditetapkan',
                    'Seluruh proses kerja mengacu kepada SOP/WI yang dipersyaratkan (Jika Ada)']]]],
                ['j' => 'B. Skenario Tugas Praktik Demonstrasi', 'f' => [
                    ['k' => 'skenario', 't' => 'grid', 'baris' => 'item', 'kol' => [['k' => 'mulai', 't' => 'teks', 'l' => 'Mulai'], ['k' => 'selesai', 't' => 'teks', 'l' => 'Selesai']]],
                    ['k' => 'catatan', 't' => 'panjang', 'l' => 'Catatan pelaksanaan'],
                ]],
            ],
        ],
        'IA.03' => [
            'judul' => 'FR.IA.03. PMO - Pertanyaan untuk Mendukung Observasi', 'urut' => 12, 'lingkup' => 'permohonan', 'tahap' => 'Asesmen', 'pengisi' => 'asesor',
            'ttd' => $ttdAA, 'instrumen' => true, 'perangkat' => 'IA.03',
            'bagian' => [
                ['j' => 'Panduan bagi asesor', 'f' => [$panduan(['Formulir ini di isi oleh asesor kompetensi dapat sebelum, pada saat atau setelah melakukan asesmen dengan metode observasi demonstrasi.',
                    'Pertanyaan dibuat dengan tujuan untuk menggali, dapat berisi pertanyaan yang berkaitan dengan dimensi kompetensi, batasan variabel dan aspek kritis yang relevan dengan skenario tugas dan praktik demonstrasi.',
                    'Jika setelah asesi melakukan praktik demonstrasi terdapat item pertanyaan pendukung observasi telah terpenuhi, maka pertanyaan tersebut tidak perlu ditanyakan lagi dan cukup memberi catatan bahwa sudah terpenuhi pada saat tugas praktek demonstrasi pada kolom tanggapan',
                    'Tanggapan asesi ditulis pada kolom tanggapan.'])]],
                ['j' => 'Pertanyaan', 'f' => [
                    ['k' => 'tanya', 't' => 'grid', 'baris' => 'item', 'kol' => [['k' => 'tanggapan', 't' => 'teks', 'l' => 'Tanggapan', 'wajib' => true], ['k' => 'capai', 't' => 'yt', 'l' => 'Pencapaian', 'wajib' => true]]],
                    $umpan,
                ]],
            ],
        ],
        'IA.04' => [
            'judul' => 'FR.IA.04B. DIT - Penilaian Proyek Singkat atau Kegiatan Terstruktur Lainnya', 'urut' => 13, 'lingkup' => 'permohonan', 'tahap' => 'Asesmen',
            'pengisi' => 'asesor', 'ttd' => $ttdAA, 'instrumen' => true, 'perangkat' => 'IA.04',
            'bagian' => [
                ['j' => 'Panduan bagi asesor', 'f' => [$panduan(['Lakukan penilaian pencapaian hasil proyek singkat atau kegiatan terstruktur lainnya melalui presentasi.',
                    'Penilaian dilakukan sesuai dengan FR IA 04A. DIT - Daftar Instruksi Terstruktur (Penjelasan Proyek Singkat/ Kegiatan Terstruktur Lainnya)',
                    'Pertanyaan disampaikan oleh asesor setelah asesi melakukan presentasi proyek singkat/ kegiatan terstruktur lainnya.',
                    'Pertanyaan yang disampaikan untuk pemenuhan pencapaian 5 dimensi kompetensi.', 'Berikan keputusan pencapaian berdasarkan kesimpulan jawaban asesi.'])]],
                ['j' => 'Instruksi proyek (FR.IA.04A)', 'f' => [['k' => 'proyek', 't' => 'grid', 'baris' => 'item', 'kol' => []]]],
                ['j' => 'Aspek Penilaian', 'f' => [
                    ['k' => 'aspek', 't' => 'grid', 'baris' => 'bebas', 'min' => 1, 'kol' => [
                        ['k' => 'lingkup', 't' => 'teks', 'l' => 'Lingkup Penyajian proyek atau kegiatan terstruktur lainnya', 'wajib' => true],
                        ['k' => 'pertanyaan', 't' => 'teks', 'l' => 'Pertanyaan', 'wajib' => true],
                        ['k' => 'tanggapan', 't' => 'teks', 'l' => 'Tanggapan', 'wajib' => true],
                        ['k' => 'kesesuaian', 't' => 'teks', 'l' => 'Kesesuaian dengan standar kompetensi kerja (unit/elemen/KUK)'],
                        ['k' => 'capai', 't' => 'yt', 'l' => 'Pencapaian', 'wajib' => true],
                    ]],
                    ['k' => 'rekomendasi', 't' => 'kbk', 'l' => 'Rekomendasi Asesor', 'wajib' => true],
                ]],
            ],
        ],
        'IA.05' => [
            'judul' => 'FR.IA.05.C. DPT - Lembar Jawaban Pertanyaan Tertulis Pilihan Ganda', 'urut' => 14, 'lingkup' => 'permohonan', 'tahap' => 'Asesmen',
            'pengisi' => 'asesor', 'ttd' => $ttdJawab, 'instrumen' => true, 'perangkat' => 'IA.05',
            'bagian' => [
                ['j' => 'Jawab semua pertanyaan berikut:', 'f' => [
                    ['k' => 'jawab', 't' => 'grid', 'baris' => 'item', 'kol' => [
                        ['k' => 'jawaban', 't' => 'pilih', 'l' => 'Jawaban', 'o' => ['a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd'], 'oleh' => 'asesi', 'wajib' => true],
                        ['k' => 'capai', 't' => 'yt', 'l' => 'Pencapaian', 'wajib' => true],
                    ]],
                    ['k' => 'umpan_balik', 't' => 'panjang', 'l' => 'Umpan balik untuk asesi: Aspek pengetahuan seluruh unit pada kelompok pekerjaan yang diujikan (tercapai/ belum tercapai). Tuliskan unit/elemen/KUK jika belum tercapai'],
                ]],
            ],
        ],
        'IA.06' => [
            'judul' => 'FR.IA.06.C. DPT - Lembar Jawaban Pertanyaan Tertulis Esai', 'urut' => 15, 'lingkup' => 'permohonan', 'tahap' => 'Asesmen',
            'pengisi' => 'asesor', 'ttd' => $ttdJawab, 'instrumen' => true, 'perangkat' => 'IA.06',
            'bagian' => [
                ['j' => 'Jawablah semua pertanyaan di bawah ini:', 'f' => [
                    ['k' => 'jawab', 't' => 'grid', 'baris' => 'item', 'kol' => [
                        ['k' => 'jawaban', 't' => 'panjang', 'l' => 'Jawaban', 'oleh' => 'asesi', 'wajib' => true],
                        ['k' => 'capai', 't' => 'yt', 'l' => 'Pencapaian', 'wajib' => true],
                    ]],
                    ['k' => 'umpan_balik', 't' => 'panjang', 'l' => 'Umpan balik untuk asesi: Aspek pengetahuan seluruh unit kelompok pekerjaan yang diujikan (tercapai/ belum tercapai). Tuliskan unit/elemen/KUK jika belum tercapai'],
                ]],
            ],
        ],
        'IA.07' => [
            'judul' => 'FR.IA.07. DPL - Daftar Pertanyaan Lisan', 'urut' => 16, 'lingkup' => 'permohonan', 'tahap' => 'Asesmen', 'pengisi' => 'asesor',
            'ttd' => $ttdAA, 'instrumen' => true, 'perangkat' => 'IA.07',
            'bagian' => [
                ['j' => 'Panduan bagi asesor', 'f' => [$panduan(['Pertanyaan lisan merupakan jenis bukti tambahan untuk mendukung bukti-bukti yang sudah ada.',
                    'Ajukan pertanyaan kepada Asesi dari daftar pertanyaan di bawah ini untuk mengonfirmasi pengetahuan, sebagaimana diperlukan.',
                    'Tulis jawaban Asesi secara singkat di tempat yang disediakan dan konfirmasi ulang untuk setiap jawaban.',
                    'Dibutuhkan jastifikasi profesional asesor untuk memutuskan hal ini.'])]],
                ['j' => 'Pertanyaan lisan', 'f' => [
                    ['k' => 'tanya', 't' => 'grid', 'baris' => 'item', 'kol' => [['k' => 'jawaban', 't' => 'teks', 'l' => 'Jawaban Asesi', 'wajib' => true], ['k' => 'capai', 't' => 'yt', 'l' => 'Pencapaian', 'wajib' => true]]],
                    $umpan,
                ]],
            ],
        ],
        'IA.08' => [
            'judul' => 'FR.IA.08. CVP - Ceklis Verifikasi Portofolio', 'urut' => 17, 'lingkup' => 'permohonan', 'tahap' => 'Asesmen', 'pengisi' => 'asesor',
            'ttd' => $ttdAA, 'instrumen' => true,
            'bagian' => [
                ['j' => 'Panduan bagi asesor', 'f' => [$panduan(['Isilah bukti portofolio sesuai ketentuan bukti berkualitas dan relevan dengan standar kompetensi kerja sebagaimana yang telah disepakati pada rekaman asesmen mandiri',
                    'Lakukan verifikasi portofolio berdasarkan aturan bukti.',
                    'Jika hasil verifikasi dokumen portofolio belum memenuhi aturan bukti maka asesor melanjutkan dengan metode tanya jawab pertanyaan wawancara dan/atau verifikasi pihak ketiga.'])]],
                ['j' => 'Bukti Portofolio dan Aturan Bukti', 'f' => [
                    ['k' => 'bukti', 't' => 'grid', 'baris' => 'bebas', 'min' => 1, 'kol' => [
                        ['k' => 'nama', 't' => 'teks', 'l' => 'Bukti Portofolio', 'wajib' => true],
                        ['k' => 'valid', 't' => 'yt', 'l' => 'Valid', 'wajib' => true], ['k' => 'asli', 't' => 'yt', 'l' => 'Asli', 'wajib' => true],
                        ['k' => 'terkini', 't' => 'yt', 'l' => 'Terkini', 'wajib' => true], ['k' => 'memadai', 't' => 'yt', 'l' => 'Memadai', 'wajib' => true],
                    ]],
                    ['k' => 'klarifikasi', 't' => 'grid', 'l' => 'Substansi yang harus diklarifikasi selama wawancara', 'baris' => 'bebas', 'kol' => [
                        ['k' => 'unit', 't' => 'teks', 'l' => 'No. Unit Kompetensi'], ['k' => 'elemen', 't' => 'teks', 'l' => 'No. Elemen'],
                        ['k' => 'materi', 't' => 'teks', 'l' => 'Materi/substansi wawancara /KUK'],
                    ]],
                    ['k' => 'tambahan', 't' => 'panjang', 'l' => 'Bukti tambahan diperlukan pada unit / elemen kompetensi sebagai berikut'],
                    ['k' => 'rekomendasi', 't' => 'pilih', 'l' => 'Rekomendasi Asesor', 'wajib' => true, 'o' => ['K' => MUK_REKOM_IA['K'],
                        'BK' => 'Asesi belum memenuhi pencapaian seluruh kriteria unjuk kerja, direkomendasikan OBSERVASI LANGSUNG/KEGIATAN TERSTRUKTUR']],
                    $bkPada,
                ]],
            ],
        ],
        'IA.09' => [
            'judul' => 'FR.IA.09. PW - Pertanyaan Wawancara', 'urut' => 18, 'lingkup' => 'permohonan', 'tahap' => 'Asesmen', 'pengisi' => 'asesor',
            'ttd' => $ttdAA, 'instrumen' => true,
            'bagian' => [
                ['j' => 'Panduan bagi asesor', 'f' => [$panduan(['Ajukan pertanyaan verifikasi portofolio untuk semua unit/elemen kompetensi yang di checklist pada FR-IA.08',
                    'Jika hasil verifikasi potofolio telah memenuhi aturan bukti maka pertanyaan wawancara tidak perlu dilakukan terhadap bukti tersebut.',
                    'Tuliskan pencapaian atas setiap kesimpulan pertanyaan wawancara dengan cara mencentang (√) “Ya” atau “Tidak”.'])]],
                ['j' => 'Daftar Pertanyaan Wawancara', 'f' => [
                    ['k' => 'tanya', 't' => 'grid', 'baris' => 'bebas', 'min' => 1, 'kol' => [
                        ['k' => 'pertanyaan', 't' => 'teks', 'l' => 'Pertanyaan (Sesuai dengan bukti no. … yang Anda ajukan, …)', 'wajib' => true],
                        ['k' => 'kesimpulan', 't' => 'teks', 'l' => 'Kesimpulan Jawaban Asesi', 'wajib' => true],
                        ['k' => 'capai', 't' => 'yt', 'l' => 'Pencapaian', 'wajib' => true],
                    ]],
                ]],
            ],
        ],
        'IA.10' => [
            'judul' => 'FR.IA.10. VPK - Verifikasi Pihak Ketiga', 'urut' => 19, 'lingkup' => 'permohonan', 'tahap' => 'Asesmen', 'pengisi' => 'asesor',
            'ttd' => ['asesor' => 'Asesor'], 'instrumen' => true,
            'bagian' => [
                ['j' => 'Data pihak ketiga (Informasi Rahasia)', 'f' => [
                    ['k' => 'nama', 't' => 'teks', 'l' => 'Nama Pengawas/penyelia/atasan/orang lain di perusahaan', 'wajib' => true],
                    ['k' => 'tempat', 't' => 'teks', 'l' => 'Tempat kerja', 'wajib' => true], ['k' => 'alamat', 't' => 'teks', 'l' => 'Alamat'],
                    ['k' => 'telepon', 't' => 'teks', 'l' => 'Telepon', 'wajib' => true],
                ]],
                ['j' => 'Pertanyaan kepada pihak ketiga', 'f' => [
                    ['k' => 'tanya', 't' => 'grid', 'baris' => 'tetap', 'rows' => [
                        '1' => 'Apakah asesi bekerja dengan mempertimbangkan Kesehatan, Keamanan dan Keselamatan Kerja?',
                        '2' => 'Apakah asesi berinteraksi dengan harmonis didalam kelompoknya?', '3' => 'Apakah asesi dapat mengelola tugas-tugas secara bersamaan?',
                        '4' => 'Apakah asesi dapat dengan cepat beradaptasi dengan peralatan dan lingkungan yang baru?',
                        '5' => 'Apakah asesi dapat merespon dengan cepat masalah-masalah yang ada di tempat kerjanya?',
                        '6' => 'Apakah Anda bersedia dihubungi jika verifikasi lebih lanjut dari pernyataan ini diperlukan?',
                    ], 'kol' => [['k' => 'jawab', 't' => 'yt', 'l' => 'Ya / Tidak', 'wajib' => true]]],
                    ['k' => 'hubungan', 't' => 'teks', 'l' => 'Apa hubungan Anda dengan asesi?', 'wajib' => true],
                    ['k' => 'lama', 't' => 'teks', 'l' => 'Berapa lama Anda bekerja dengan asesi?', 'wajib' => true],
                    ['k' => 'dekat', 't' => 'teks', 'l' => 'Seberapa dekat Anda bekerja dengan asesi di area yang dinilai?'],
                    ['k' => 'kualifikasi', 't' => 'teks', 'l' => 'Apa pengalaman teknis dan / atau kualifikasi Anda di bidang yang dinilai? (termasuk asesmen atau kualifikasi pelatihan)'],
                    ['k' => 'yakin', 't' => 'panjang', 'l' => 'Secara keseluruhan, apakah Anda yakin asesi melakukan sesuai standar yang diminta oleh unit kompetensi secara konsisten?', 'wajib' => true],
                    ['k' => 'pelatihan', 't' => 'teks', 'l' => 'Identifikasi kebutuhan pelatihan lebih lanjut untuk asesi'],
                    ['k' => 'komentar', 't' => 'teks', 'l' => 'Ada komentar lain'],
                ]],
            ],
        ],
        'IA.11' => [
            'judul' => 'FR.IA.11. CRP - Ceklis Reviu Produk', 'urut' => 20, 'lingkup' => 'permohonan', 'tahap' => 'Asesmen', 'pengisi' => 'asesor',
            'ttd' => $ttdAA, 'instrumen' => true, 'perangkat' => 'IA.11',
            'bagian' => [
                ['j' => 'Data produk', 'f' => [
                    ['k' => 'produk', 't' => 'teks', 'l' => 'Nama produk yang dihasilkan', 'wajib' => true],
                    ['k' => 'standar', 't' => 'teks', 'l' => 'Standar Industri atau tempat kerja'],
                    ['k' => 'spesifikasi', 't' => 'panjang', 'l' => 'Spesifikasi produk secara umum / teknis'],
                    ['k' => 'tgl_pakai', 't' => 'tanggal', 'l' => 'Tanggal pengoperasian/penggunaan'],
                ]],
                ['j' => 'Spesifikasi dan Performa Produk', 'f' => [
                    ['k' => 'kriteria', 't' => 'grid', 'baris' => 'item', 'kol' => [['k' => 'hasil', 't' => 'teks', 'l' => 'Hasil Review Produk', 'wajib' => true], ['k' => 'capai', 't' => 'yt', 'l' => 'Pencapaian', 'wajib' => true]]],
                    ['k' => 'rekomendasi', 't' => 'pilih', 'l' => 'Rekomendasi Asesor', 'wajib' => true, 'o' => ['K' => MUK_REKOM_IA['K'],
                        'BK' => 'Asesi belum memenuhi pencapaian seluruh kriteria unjuk kerja, direkomendasikan OBSERVASI LANGSUNG']],
                    ['k' => 'catatan', 't' => 'panjang', 'l' => 'Catatan: temuan asesmen pencapaian hasil reviu produk, jika belum/tidak terpenuhi'],
                ]],
            ],
        ],

        /* ===================== Keputusan, umpan balik, banding ===================== */
        'AK.02' => [
            'judul' => 'FR.AK.02. Rekaman Asesmen Kompetensi', 'urut' => 21, 'lingkup' => 'permohonan', 'tahap' => 'Keputusan asesmen', 'pengisi' => 'asesor',
            'ttd' => ['asesor' => 'Asesor', 'asesi' => 'Asesi'],
            'bagian' => [
                ['j' => 'Bukti per Unit Kompetensi', 'f' => [
                    ['k' => 'instruksi', 't' => 'info', 'v' => ['Beri tanda centang (√) di kolom yang sesuai untuk mencerminkan bukti yang diperoleh untuk menentukan Kompetensi Asesi untuk setiap Unit Kompetensi.']],
                    ['k' => 'unit', 't' => 'grid', 'baris' => 'unit', 'kol' => [
                        ['k' => 'bukti', 't' => 'multi', 'l' => 'Bukti', 'o' => array_map(function ($x) { return $x[0]; }, MUK_AK02_BUKTI), 'wajib' => true],
                        ['k' => 'hasil', 't' => 'kbk', 'l' => 'Rekomendasi', 'wajib' => true],
                    ]],
                    ['k' => 'tindak_lanjut', 't' => 'panjang', 'l' => 'Tindak lanjut yang dibutuhkan (Masukkan pekerjaan tambahan dan asesmen yang diperlukan untuk mencapai kompetensi)'],
                    ['k' => 'komentar', 't' => 'panjang', 'l' => 'Komentar/ Observasi oleh asesor'],
                ]],
            ],
        ],
        'AK.03' => [
            'judul' => 'FR.AK.03. Umpan Balik dan Catatan Asesmen', 'urut' => 22, 'lingkup' => 'permohonan', 'tahap' => 'Umpan balik', 'pengisi' => 'asesi',
            'ttd' => ['asesi' => 'Asesi'],
            'bagian' => [
                ['j' => 'Umpan balik dari Asesi (diisi oleh Asesi setelah pengambilan keputusan)', 'f' => [
                    ['k' => 'komponen', 't' => 'grid', 'baris' => 'tetap', 'rows' => [
                        '1' => 'Saya mendapatkan penjelasan yang cukup memadai mengenai proses asesmen/uji kompetensi',
                        '2' => 'Saya diberikan kesempatan untuk mempelajari standar kompetensi yang akan diujikan dan menilai diri sendiri terhadap pencapaiannya',
                        '3' => 'Asesor memberikan kesempatan untuk mendiskusikan/menegosiasikan metoda, instrumen dan sumber asesmen serta jadwal asesmen',
                        '4' => 'Asesor berusaha menggali seluruh bukti pendukung yang sesuai dengan latar belakang pelatihan dan pengalaman yang saya miliki',
                        '5' => 'Saya sepenuhnya diberikan kesempatan untuk mendemonstrasikan kompetensi yang saya miliki selama asesmen',
                        '6' => 'Saya mendapatkan penjelasan yang memadai mengenai keputusan asesmen',
                        '7' => 'Asesor memberikan umpan balik yang mendukung setelah asesmen serta tindak lanjutnya',
                        '8' => 'Asesor bersama saya mempelajari semua dokumen asesmen serta menandatanganinya',
                        '9' => 'Saya mendapatkan jaminan kerahasiaan hasil asesmen serta penjelasan penanganan dokumen asesmen',
                        '10' => 'Asesor menggunakan keterampilan komunikasi yang efektif selama asesmen',
                    ], 'kol' => [['k' => 'hasil', 't' => 'yt', 'l' => 'Hasil', 'wajib' => true], ['k' => 'catatan', 't' => 'teks', 'l' => 'Catatan/Komentar Asesi']]],
                    ['k' => 'lainnya', 't' => 'panjang', 'l' => 'Catatan/komentar lainnya (apabila ada)'],
                ]],
            ],
        ],
        'AK.04' => [
            'judul' => 'FR.AK.04. Banding Asesmen', 'urut' => 8, 'lingkup' => 'permohonan', 'tahap' => 'Banding', 'pengisi' => 'asesi', 'ttd' => ['asesi' => 'Asesi'],
            'bagian' => [
                ['j' => 'Jawablah dengan Ya atau Tidak pertanyaan-pertanyaan berikut ini', 'f' => [
                    ['k' => 'tanya', 't' => 'grid', 'baris' => 'tetap', 'rows' => [
                        '1' => 'Apakah Proses Banding telah dijelaskan kepada Anda?', '2' => 'Apakah Anda telah mendiskusikan Banding dengan Asesor?',
                        '3' => 'Apakah Anda mau melibatkan “orang lain” membantu Anda dalam Proses Banding?',
                    ], 'kol' => [['k' => 'jawab', 't' => 'yt', 'l' => 'Ya / Tidak', 'wajib' => true]]],
                    ['k' => 'alasan', 't' => 'panjang', 'l' => 'Banding ini diajukan atas alasan sebagai berikut', 'wajib' => true],
                    ['k' => 'hak', 't' => 'info', 'v' => ['Anda mempunyai hak mengajukan banding jika Anda menilai Proses Asesmen tidak sesuai SOP dan tidak memenuhi Prinsip Asesmen.']],
                ]],
            ],
        ],

        /* ===================== Per jadwal ===================== */
        'AK.05' => [
            'judul' => 'FR.AK.05. Laporan Asesmen', 'urut' => 23, 'lingkup' => 'jadwal', 'tahap' => 'Laporan', 'pengisi' => 'asesor', 'ttd' => ['asesor' => 'Asesor'],
            'bagian' => [
                ['j' => 'Rekomendasi per asesi', 'f' => [
                    ['k' => 'asesi', 't' => 'grid', 'baris' => 'asesi', 'kol' => [['k' => 'rekom', 't' => 'kbk', 'l' => 'Rekomendasi'], ['k' => 'ket', 't' => 'teks', 'l' => 'Keterangan (Kode dan Judul Unit Kompetensi yang dinyatakan BK)']]],
                ]],
                ['j' => 'Aspek Negatif dan Positif dalam Asesmen', 'f' => [
                    ['k' => 'negatif', 't' => 'panjang', 'l' => 'Aspek Negatif', 'wajib' => true], ['k' => 'positif', 't' => 'panjang', 'l' => 'Aspek Positif', 'wajib' => true],
                    ['k' => 'penolakan', 't' => 'teks', 'l' => 'Pencatatan Penolakan Hasil Asesmen: Ada Penolakan', 'wajib' => true],
                    ['k' => 'saran', 't' => 'panjang', 'l' => 'Saran Perbaikan : (Asesor/Personil Terkait)'], ['k' => 'catatan', 't' => 'panjang', 'l' => 'Catatan'],
                ]],
            ],
        ],
        'AK.06' => [
            'judul' => 'FR.AK.06. Meninjau Proses Asesmen', 'urut' => 24, 'lingkup' => 'jadwal', 'tahap' => 'Tinjauan', 'pengisi' => 'asesor',
            'ttd' => ['asesor' => 'Lead Asesor/Asesor'],
            'bagian' => [
                ['j' => 'Penjelasan', 'f' => [['k' => 'penjelasan', 't' => 'info', 'v' => ['Peninjauan dapat dilakukan oleh lead asesor atau asesor yang melaksanakan asesmen.',
                    'Peninjauan dapat dilakukan secara terpadu dalam skema sertifikasi dan / atau peserta kelompok yang homogen.',
                    'Isilah pemenuhan dimensi kompetensi dengan menuliskan jenis bukti dan instrumen yang digunakan pada saat asesmen sebagai bukti terpenuhinya dimensi kompetensi.']]]],
                ['j' => 'Kesesuaian dengan prinsip asesmen', 'f' => [
                    ['k' => 'prinsip', 't' => 'grid', 'baris' => 'tetap', 'rows' => [
                        'rencana' => ['Rencana asesmen', ['V' => 'Validitas', 'R' => 'Reliabel', 'F' => 'Fleksibel', 'A' => 'Adil']],
                        'persiapan' => ['Persiapan asesmen', ['V' => 'Validitas', 'R' => 'Reliabel', 'F' => 'Fleksibel', 'A' => 'Adil']],
                        'implementasi' => ['Implementasi asesmen', ['V' => 'Validitas', 'R' => 'Reliabel', 'F' => 'Fleksibel', 'A' => 'Adil']],
                        'keputusan' => ['Keputusan asesmen', ['V' => 'Validitas', 'R' => 'Reliabel', 'A' => 'Adil']],
                        'umpan' => ['Umpan balik asesmen', ['V' => 'Validitas', 'R' => 'Reliabel', 'A' => 'Adil']],
                    ], 'kol' => [['k' => 'sesuai', 't' => 'multi', 'l' => 'Kesesuaian', 'o' => '@row']]],
                    ['k' => 'rekom1', 't' => 'panjang', 'l' => 'Rekomendasi untuk peningkatan', 'wajib' => true],
                ]],
                ['j' => 'Konsistensi keputusan asesmen (pemenuhan dimensi kompetensi)', 'f' => [
                    ['k' => 'tugas', 't' => 'teks', 'l' => 'Task Skills', 'wajib' => true], ['k' => 'manajemen', 't' => 'teks', 'l' => 'Task Management Skills', 'wajib' => true],
                    ['k' => 'kontinjensi', 't' => 'teks', 'l' => 'Contingency Management Skills', 'wajib' => true], ['k' => 'lingkungan', 't' => 'teks', 'l' => 'Job Role/ Environment Skills', 'wajib' => true],
                    ['k' => 'transfer', 't' => 'teks', 'l' => 'Transfer Skills', 'wajib' => true], ['k' => 'rekom2', 't' => 'panjang', 'l' => 'Rekomendasi untuk peningkatan'],
                    ['k' => 'komentar', 't' => 'panjang', 'l' => 'Komentar'],
                ]],
            ],
        ],

        /* ===================== Per skema: validasi ===================== */
        'VA' => [
            'judul' => 'FR.VA. Memberikan Kontribusi dalam Validasi Asesmen', 'urut' => 25, 'lingkup' => 'skema', 'tahap' => 'Validasi', 'pengisi' => 'tim',
            'ttd' => ['tim' => 'Tim Validasi'], 'banyak' => true,
            'bagian' => [
                ['j' => 'Menyiapkan Proses Validasi', 'f' => [
                    ['k' => 'tim', 't' => 'teks', 'l' => 'Tim Validasi', 'wajib' => true], ['k' => 'tanggal', 't' => 'tanggal', 'l' => 'Hari/Tanggal', 'wajib' => true],
                    ['k' => 'tempat', 't' => 'teks', 'l' => 'Tempat', 'wajib' => true],
                    ['k' => 'periode', 't' => 'pilih', 'l' => 'Periode', 'wajib' => true, 'o' => ['sebelum' => 'Sebelum Asesmen', 'saat' => 'Pada Saat Asesmen', 'setelah' => 'Setelah Asesmen']],
                    ['k' => 'tujuan', 't' => 'multi', 'l' => 'Tujuan dan fokus validasi', 'wajib' => true, 'o' => ['Bagian dari Proses Penjaminan Mutu Organisasi', 'Mengantisipasi Risiko',
                        'Memenuhi Persyaratan BNSP', 'Memastikan Kesesuaian Bukti', 'Meningkatkan Kualitas Asesmen', 'Mengevaluasi Kualitas Perangkat Asesmen']],
                    ['k' => 'konteks', 't' => 'multi', 'l' => 'Konteks validasi', 'wajib' => true, 'o' => ['Internal Organisasi', 'Eksternal Organisasi', 'Proses Lisensi/ Re lisensi',
                        'Dengan Kolega Asesor', 'Kolega dari Organisasi Pelatihan atau Asesmen']],
                    ['k' => 'pendekatan', 't' => 'multi', 'l' => 'Pendekatan validasi', 'wajib' => true, 'o' => ['Panel asesmen', 'Pertemuan moderasi', 'Mengkaji perangkat asesmen',
                        'Acuan pembanding', 'Pengujian lapangan dan uji coba perangkat asesmen', 'Umpan balik dari klien', 'Mengkaji bukti-bukti']],
                    ['k' => 'orang', 't' => 'grid', 'l' => 'Orang yang Relevan', 'baris' => 'bebas', 'min' => 1, 'kol' => [
                        ['k' => 'peran', 't' => 'teks', 'l' => 'Peran', 'wajib' => true], ['k' => 'nama', 't' => 'teks', 'l' => 'Nama', 'wajib' => true],
                        ['k' => 'hasil', 't' => 'teks', 'l' => 'Hasil Konfirmasi/diskusi [tujuan, fokus & konteks]'],
                    ]],
                    ['k' => 'acuan', 't' => 'multi', 'l' => 'Acuan Pembanding', 'wajib' => true, 'o' => ['Standar Kompetensi (SKKNI/SKKK/SKI)', 'Skema Sertifikasi', 'SOP/IK', 'Manual Instruction / Book Manual', 'Standar Kinerja']],
                    ['k' => 'dokumen', 't' => 'multi', 'l' => 'Dokumen Terkait dan Bahan-Bahan', 'wajib' => true, 'o' => ['Perangkat Asesmen', 'Peraturan / Pedoman', 'Bukti-bukti hasil asesmen']],
                ]],
                ['j' => 'Memberikan Kontribusi dalam Proses Validasi', 'f' => [
                    ['k' => 'komunikasi', 't' => 'multi', 'l' => 'Keterampilan komunikasi yang digunakan dalam kegiatan validasi', 'o' => ['PRO AKTIF', 'ACTIVE LISTENING']],
                    ['k' => 'aspek', 't' => 'grid', 'l' => 'Aspek Dalam Kegiatan Validasi (Meninjau, Membandingkan, Mengevaluasi)', 'baris' => 'tetap', 'rows' => [
                        '1' => 'Rencana Asesmen', '2' => 'Interpretasi Standar Kompetensi', '3' => 'Interpretasi Acuan Pembanding lainnya', '4' => 'Proses Asesmen',
                        '5' => 'Penyeleksian dan Penerapan Metode Asesmen', '6' => 'Penyeleksian dan Penerapan Perangkat Asesmen', '7' => 'Bukti-bukti yang Dikumpulkan', '8' => 'Pengambilan Keputusan',
                    ], 'kol' => [
                        ['k' => 'bukti', 't' => 'multi', 'l' => 'Aturan Bukti', 'o' => ['V' => 'Valid', 'A' => 'Asli', 'T' => 'Terkini', 'M' => 'Memadai']],
                        ['k' => 'prinsip', 't' => 'multi', 'l' => 'Prinsip Asesmen', 'o' => ['V' => 'Validitas', 'R' => 'Reliabel', 'F' => 'Fleksibel', 'A' => 'Adil']],
                    ]],
                ]],
                ['j' => 'Memberikan Kontribusi untuk Hasil Asesmen', 'f' => [
                    ['k' => 'temuan', 't' => 'grid', 'baris' => 'bebas', 'min' => 1, 'kol' => [['k' => 'temuan', 't' => 'teks', 'l' => 'Temuan Validasi', 'wajib' => true],
                        ['k' => 'rekom', 't' => 'teks', 'l' => 'Rekomendasi untuk meningkatkan Praktek Asesmen', 'wajib' => true]]],
                    ['k' => 'rencana', 't' => 'grid', 'l' => 'Rencana Implementasi Perubahan / Perbaikan Pelaksanaan Asesmen', 'baris' => 'bebas', 'kol' => [
                        ['k' => 'kegiatan', 't' => 'teks', 'l' => 'Kegiatan perbaikan sesuai rekomendasi'], ['k' => 'waktu', 't' => 'teks', 'l' => 'Waktu Penyelesaian'],
                        ['k' => 'pj', 't' => 'teks', 'l' => 'Penanggung Jawab']]],
                ]],
            ],
        ],
    ];
    return $defs;
}

/**
 * Perangkat asesmen (MUK) per skema: disusun penyusun, divalidasi validator yang berbeda, berversi.
 * 'item' = definisi kolom butir (soal/pertanyaan/kriteria); 'rahasia' = kolom yang tidak boleh dilihat asesi.
 */
function muk_perangkat_defs(): array
{
    return [
        'MAPA.01' => ['judul' => 'FR.MAPA.01. Merencanakan Aktivitas dan Proses Asesmen', 'urut' => 4],
        'MAPA.02' => ['judul' => 'FR.MAPA.02. Peta Instrumen Asesmen Hasil Pendekatan Asesmen dan Perencanaan Asesmen', 'urut' => 6],
        'IA.02' => ['judul' => 'FR.IA.02. TPD - Tugas Praktik Demonstrasi (skenario)', 'urut' => 11, 'item' => [
            ['k' => 'kelompok', 't' => 'teks', 'l' => 'Kelompok Pekerjaan'], ['k' => 'skenario', 't' => 'panjang', 'l' => 'Skenario Tugas Praktik Demonstrasi', 'wajib' => true],
            ['k' => 'peralatan', 't' => 'panjang', 'l' => 'Perlengkapan dan Peralatan', 'wajib' => true], ['k' => 'durasi', 't' => 'teks', 'l' => 'Durasi Waktu', 'wajib' => true]]],
        'IA.03' => ['judul' => 'FR.IA.03. PMO - Pertanyaan untuk Mendukung Observasi', 'urut' => 12, 'item' => [
            ['k' => 'kelompok', 't' => 'teks', 'l' => 'Kelompok Pekerjaan'], ['k' => 'pertanyaan', 't' => 'panjang', 'l' => 'Pertanyaan', 'wajib' => true]]],
        'IA.04' => ['judul' => 'FR.IA.04A. DIT - Daftar Instruksi Terstruktur (Penjelasan Proyek Singkat/ Kegiatan Terstruktur Lainnya)', 'urut' => 13, 'item' => [
            ['k' => 'kelompok', 't' => 'teks', 'l' => 'Kelompok Pekerjaan'],
            ['k' => 'skenario', 't' => 'panjang', 'l' => 'Hal yang harus disiapkan atau dilakukan atau dihasilkan untuk suatu proyek singkat/ kegiatan terstruktur', 'wajib' => true],
            ['k' => 'presentasi', 't' => 'panjang', 'l' => 'Hal yang perlu didemonstrasikan/dipresentasikan', 'wajib' => true], ['k' => 'waktu', 't' => 'teks', 'l' => 'Waktu', 'wajib' => true]]],
        'IA.05' => ['judul' => 'FR.IA.05.A/B. DPT - Pertanyaan Tertulis Pilihan Ganda dan Kunci Jawaban', 'urut' => 14, 'item' => [
            ['k' => 'pertanyaan', 't' => 'panjang', 'l' => 'Pertanyaan', 'wajib' => true], ['k' => 'a', 't' => 'teks', 'l' => 'a.', 'wajib' => true],
            ['k' => 'b', 't' => 'teks', 'l' => 'b.', 'wajib' => true], ['k' => 'c', 't' => 'teks', 'l' => 'c.', 'wajib' => true], ['k' => 'd', 't' => 'teks', 'l' => 'd.', 'wajib' => true],
            ['k' => 'kunci', 't' => 'pilih', 'l' => 'Kunci Jawaban (FR.IA.05.B)', 'o' => ['a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd'], 'wajib' => true, 'rahasia' => true]]],
        'IA.06' => ['judul' => 'FR.IA.06.A/B. DPT - Pertanyaan Tertulis Esai dan Kunci Jawaban', 'urut' => 15, 'item' => [
            ['k' => 'pertanyaan', 't' => 'panjang', 'l' => 'Pertanyaan', 'wajib' => true],
            ['k' => 'kunci', 't' => 'panjang', 'l' => 'Kunci Jawaban (FR.IA.06.B)', 'wajib' => true, 'rahasia' => true]]],
        'IA.07' => ['judul' => 'FR.IA.07. DPL - Daftar Pertanyaan Lisan', 'urut' => 16, 'item' => [
            ['k' => 'pertanyaan', 't' => 'panjang', 'l' => 'Pertanyaan', 'wajib' => true], ['k' => 'kunci', 't' => 'panjang', 'l' => 'Kunci Jawaban', 'wajib' => true, 'rahasia' => true]]],
        'IA.11' => ['judul' => 'FR.IA.11. CRP - Ceklis Reviu Produk (kriteria)', 'urut' => 20, 'item' => [
            ['k' => 'aspek', 't' => 'pilih', 'l' => 'Aspek', 'o' => ['Spesifikasi produk', 'Performa produk atau Karakteristik Produk'], 'wajib' => true],
            ['k' => 'kriteria', 't' => 'teks', 'l' => 'Kriteria', 'wajib' => true]]],
    ];
}

/** Bagian FR.MAPA.01 (perangkat per skema). */
function muk_mapa01_def(): array
{
    return [
        ['j' => '1. Menentukan Pendekatan Asesmen', 'f' => [
            ['k' => 'asesi', 't' => 'multi', 'l' => '1.1 Asesi', 'o' => MUK_POTENSI, 'wajib' => true],
            ['k' => 'tujuan', 't' => 'multi', 'l' => 'Tujuan Asesmen', 'o' => ['Sertifikasi', 'Pengakuan Kompetensi Terkini (PKT)', 'Rekognisi Pembelajaran Lampau', 'Lainnya'], 'wajib' => true],
            ['k' => 'lingkungan', 't' => 'multi', 'l' => 'Konteks Asesmen: Lingkungan', 'o' => ['Tempat kerja nyata', 'Tempat kerja simulasi'], 'wajib' => true],
            ['k' => 'peluang', 't' => 'pilih', 'l' => 'Peluang untuk mengumpulkan bukti dalam sejumlah situasi', 'o' => ['Tersedia', 'Terbatas'], 'wajib' => true],
            ['k' => 'hubungan', 't' => 'multi', 'l' => 'Hubungan antara standar kompetensi dan', 'o' => ['Bukti untuk mendukung asesmen', 'Aktivitas kerja di tempat kerja Asesi', 'Kegiatan Pembelajaran'], 'wajib' => true],
            ['k' => 'pelaku', 't' => 'pilih', 'l' => 'Siapa yang melakukan asesmen/ RPL', 'o' => ['Lembaga Sertifikasi', 'Organisasi Pelatihan', 'Asesor Perusahaan'], 'wajib' => true],
            ['k' => 'konfirmasi', 't' => 'multi', 'l' => 'Konfirmasi dengan orang yang relevan', 'o' => ['Manajer sertifikasi LSP', 'Master Asesor / Master Trainer / Lead Asesor Kompetensi',
                'Manajer Pelatihan Lembaga Training terakreditasi / Lembaga Training terdaftar', 'Manajer atau supervisor ditempat kerja'], 'wajib' => true],
            ['k' => 'standar', 't' => 'panjang', 'l' => '1.2 Standar Industri atau Tempat Kerja (SKKNI KEPMENNAKER No/thn, kriteria kurikulum, spesifikasi kinerja/produk, pedoman khusus)', 'wajib' => true],
        ]],
        ['j' => '2. Mempersiapkan Rencana Asesmen (per Unit Kompetensi)', 'f' => [
            ['k' => 'info', 't' => 'info', 'v' => ['Metode dan Perangkat Asesmen: CL (Ceklis Observasi), DIT (Daftar Instruksi Terstruktur), DPL (Daftar Pertanyaan Lisan), DPT (Daftar Pertanyaan Tertulis), VPK (Verifikasi Pihak Ketiga), CVP (Ceklis Verifikasi Portofolio), CRP (Ceklis Reviu Produk), PW (Pertanyaan Wawancara)']],
            ['k' => 'rencana', 't' => 'grid', 'baris' => 'unit', 'kol' => [
                ['k' => 'bukti', 't' => 'teks', 'l' => 'Bukti-Bukti (Kinerja, Produk, Portofolio, dan / atau Pengetahuan) diidentifikasi berdasarkan Kriteria Unjuk Kerja dan Pendekatan Asesmen', 'wajib' => true],
                ['k' => 'jenis', 't' => 'multi', 'l' => 'Jenis Bukti', 'o' => ['L' => 'L (Langsung)', 'TL' => 'TL (Tidak Langsung)', 'T' => 'T (Tambahan)'], 'wajib' => true],
                ['k' => 'metode', 't' => 'multi', 'l' => 'Metode dan Perangkat Asesmen', 'wajib' => true, 'o' => [
                    'obs' => 'Observasi langsung (CL)', 'dit' => 'Kegiatan Terstruktur (DIT)', 'tanya' => 'Tanya Jawab (DPT/DPL/PW)', 'cvp' => 'Verifikasi Portfolio (CVP)',
                    'crp' => 'Reviu Produk (CRP)', 'vpk' => 'Verifikasi Pihak Ketiga (VPK)']],
            ]],
        ]],
        ['j' => '3. Mengidentifikasi Persyaratan Modifikasi dan Kontekstualisasi', 'f' => [
            ['k' => 'karakteristik', 't' => 'teks', 'l' => '3.1 a. Karakteristik Asesi (Ada / tidak ada karakteristik khusus Asesi; jika ada, tuliskan)'],
            ['k' => 'kontekstual', 't' => 'teks', 'l' => '3.1 b. Kebutuhan kontekstualisasi terkait tempat kerja'],
            ['k' => 'saran', 't' => 'teks', 'l' => '3.2 Saran yang diberikan oleh paket pelatihan atau pengembang pelatihan'],
            ['k' => 'penyesuaian', 't' => 'teks', 'l' => '3.3 Penyesuaian perangkat asesmen terkait kebutuhan kontekstualisasi'],
            ['k' => 'integrasi', 't' => 'teks', 'l' => '3.4 Peluang untuk kegiatan asesmen terintegrasi dan mencatat setiap perubahan yang diperlukan untuk alat asesmen'],
        ]],
    ];
}

/** Bagian FR.MAPA.02: matriks instrumen × potensi asesi. */
function muk_mapa02_def(): array
{
    return [
        ['j' => 'Peta Instrumen Asesmen', 'f' => [
            ['k' => 'info', 't' => 'info', 'v' => ['Diisi berdasarkan hasil penentuan pendekatan asesmen dan perencanaan asesmen. Centang instrumen yang dipakai untuk setiap potensi asesi.']],
            ['k' => 'peta', 't' => 'grid', 'baris' => 'tetap', 'rows' => MUK_INSTRUMEN, 'kol' => [['k' => 'potensi', 't' => 'multi', 'l' => 'Potensi Asesi 1–5', 'o' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5']]]],
            ['k' => 'keterangan', 't' => 'info', 'v' => array_map(function ($k, $v) { return "$k = $v"; }, array_keys(MUK_POTENSI), MUK_POTENSI)],
        ]],
    ];
}
