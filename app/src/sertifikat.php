<?php
// Sertifikat kompetensi dalam PDF: halaman depan dengan kode QR verifikasi, lalu lampiran daftar unit kompetensi.
declare(strict_types=1);

const BULAN_ID = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function tanggal_id(?string $ymd): string
{
    $t = $ymd ? strtotime($ymd) : false;
    return $t ? (int)date('j', $t) . ' ' . BULAN_ID[(int)date('n', $t)] . ' ' . date('Y', $t) : '-';
}

/** Keterangan bila sertifikat tidak berlaku lagi (dicabut atau habis masa berlakunya), atau null bila berlaku. */
function sertifikat_peringatan(array $c): ?string
{
    if (($c['status'] ?? 'aktif') !== 'aktif') return 'SERTIFIKAT TIDAK BERLAKU (' . strtoupper((string)$c['status']) . ')';
    if (!empty($c['berlaku']) && $c['berlaku'] < date('Y-m-d')) return 'MASA BERLAKU SERTIFIKAT TELAH HABIS';
    return null;
}

/** PDF sertifikat. Bila $signer diberikan (lihat tte_penanda_tangan), PDF ditandatangani digital; bila penandatanganan gagal,
 *  PDF dibuat ulang tanpa tanda tangan (dan tanpa tulisan "ditandatangani") supaya tidak ada klaim yang tidak benar. */
function sertifikat_pdf(array $c, array $units, string $verifyUrl, ?array $signer = null): string
{
    if ($signer) {
        try {
            return sertifikat_pdf_susun($c, $units, $verifyUrl, $signer);
        } catch (Throwable $e) {
            error_log('PortalLSP TTE: sertifikat ' . ($c['id'] ?? '?') . ' tidak ditandatangani: ' . $e->getMessage());
        }
    }
    return sertifikat_pdf_susun($c, $units, $verifyUrl, null);
}

function sertifikat_pdf_susun(array $c, array $units, string $verifyUrl, ?array $signer): string
{
    $navy = [14, 35, 92];
    $blue = [29, 78, 216];
    $teal = [13, 148, 136];
    $ink = [17, 24, 39];
    $grey = [91, 104, 137];
    $red = [185, 28, 28];
    $pdf = new MiniPdf(842, 595);
    $W = $pdf->width();
    $cx = $W / 2;

    // ---------- Halaman depan ----------
    $pdf->addPage();
    $pdf->rect(0, 0, $W, 595, [250, 251, 255]);
    $pdf->rect(20, 20, $W - 40, 555, null, $navy, 3);
    $pdf->rect(28, 28, $W - 56, 539, null, $teal, 0.8);
    foreach ([[28, 28], [$W - 40, 28], [28, 555], [$W - 40, 555]] as [$x, $y]) {
        $pdf->rect($x, $y, 12, 12, $teal);
    }
    $pdf->rect(28, 28, $W - 56, 6, $navy);

    $pdf->text($cx, 78, mb_upper((string)$c['lsp_nama']), 'HB', 13, $navy, 'C', 1.2);
    $pdf->text($cx, 96, 'Lembaga Sertifikasi Profesi', 'H', 9.5, $grey, 'C');
    $pdf->text($cx, 146, 'SERTIFIKAT KOMPETENSI', 'TB', 34, $navy, 'C', 3);
    $pdf->line($cx - 150, 160, $cx + 150, 160, $teal, 1.2);
    $pdf->text($cx, 180, 'Nomor: ' . $c['nomor'], 'H', 11, $grey, 'C');
    $warn = sertifikat_peringatan($c);
    if ($warn) {
        $pdf->text($cx, 199, $warn, 'HB', 11, $red, 'C', 0.5);
    }

    $pdf->text($cx, 228, 'Diberikan kepada', 'TI', 15, $ink, 'C');
    // Nama: dikecilkan bila panjang; bila masih tidak muat, dibagi menjadi dua baris.
    $nameSize = 30;
    while ($nameSize > 20 && $pdf->textWidth((string)$c['nama'], 'TB', $nameSize) > 640) $nameSize -= 1;
    if ($pdf->textWidth((string)$c['nama'], 'TB', $nameSize) <= 640) {
        $pdf->text($cx, 266, (string)$c['nama'], 'TB', $nameSize, $blue, 'C');
    } else {
        $nameLines = $pdf->wrap((string)$c['nama'], 'TB', 18, 640);
        if (count($nameLines) > 2) {
            $nameLines = [$nameLines[0], implode(' ', array_slice($nameLines, 1))];
        }
        foreach ($nameLines as $i => $ln) {
            $size = 18;
            while ($size > 10 && $pdf->textWidth($ln, 'TB', $size) > 690) $size -= 0.5;
            $pdf->text($cx, 250 + $i * 21, $ln, 'TB', $size, $blue, 'C');
        }
    }
    $underY = isset($nameLines) ? 281 : 276;
    $pdf->line($cx - 220, $underY, $cx + 220, $underY, [203, 213, 225], 0.6);

    $pdf->textRunsCentered($cx, 302, [['telah dinyatakan ', 'T', $ink], ['KOMPETEN', 'TB', $navy], [' pada skema sertifikasi', 'T', $ink]], 14);

    $skemaLines = array_slice($pdf->wrap((string)$c['skema_nama'], 'TB', 21, 620), 0, 2);
    $y = 334;
    foreach ($skemaLines as $ln) {
        $pdf->text($cx, $y, $ln, 'TB', 21, $navy, 'C');
        $y += 25;
    }
    if (!empty($c['skema_kode'])) {
        $pdf->text($cx, $y - 4, 'Kode skema: ' . $c['skema_kode'] . '  ·  ' . count($units) . ' unit kompetensi (lihat lampiran)', 'H', 9.5, $grey, 'C');
    }

    // Kiri bawah: tanggal.
    $pdf->text(70, 452, 'Tanggal terbit', 'H', 9, $grey);
    $pdf->text(70, 467, tanggal_id($c['terbit']), 'HB', 12, $ink);
    $pdf->text(70, 492, 'Berlaku sampai', 'H', 9, $grey);
    $pdf->text(70, 507, tanggal_id($c['berlaku']), 'HB', 12, $ink);

    // Tengah bawah: penerbit, dengan kotak tanda tangan digital bila PDF ditandatangani.
    $ttdRect = null;
    if ($signer) {
        $top = 432;
        $pdf->text($cx, $top + 14, 'DITANDATANGANI SECARA DIGITAL OLEH', 'HB', 7.5, $teal, 'C', 0.8);
        $y = $top + 31;
        // Nama penanda tangan sesuai sertifikat yang dipakai (nama LSP saat ini, atau pemilik sertifikat PSrE).
        foreach (array_slice($pdf->wrap((string)(($signer['tampil'] ?? '') ?: $signer['nama']), 'HB', 11.5, 280), 0, 2) as $ln) {
            $pdf->text($cx, $y, $ln, 'HB', 11.5, $ink, 'C');
            $y += 14;
        }
        $pdf->text($cx, $y + 1, 'Waktu: ' . tanggal_id(date('Y-m-d')) . ', ' . date('H.i') . ' ' . date('T'), 'H', 8, $grey, 'C');
        $fp = substr(tte_sidik_jari($signer['cert']), 0, 16);
        $pdf->text($cx, $y + 12, 'Sertifikat: ' . ($signer['penerbit'] ?: $signer['nama']) . '  ·  ' . implode(' ', str_split($fp, 4)), 'H', 7, $grey, 'C');
        $ttdRect = [$cx - 150, $top, 300, $y + 19 - $top];
        $pdf->rect($ttdRect[0], $ttdRect[1], $ttdRect[2], $ttdRect[3], null, $teal, 0.8);
        $pdf->text($cx, 527, 'Periksa tanda tangan di pembaca PDF, atau pindai kode QR', 'H', 7, $grey, 'C');
    } else {
        $pdf->text($cx, 452, 'Dikeluarkan oleh', 'H', 9, $grey, 'C');
        foreach (array_slice($pdf->wrap((string)$c['lsp_nama'], 'HB', 12, 300), 0, 2) as $i => $ln) {
            $pdf->text($cx, 469 + $i * 15, $ln, 'HB', 12, $ink, 'C');
        }
        $pdf->line($cx - 120, 510, $cx + 120, 510, [148, 163, 184], 0.6);
        $pdf->text($cx, 523, 'Diterbitkan melalui PortalLSP · verifikasi dengan kode QR', 'H', 7.5, $grey, 'C');
    }

    // Kanan bawah: QR verifikasi.
    $qrSize = 104;
    $qx = $W - 70 - $qrSize;
    $pdf->qr(qr_matrix($verifyUrl, 'M'), $qx, 414, $qrSize);
    $pdf->text($qx + $qrSize / 2, 528, 'Pindai untuk memeriksa keaslian', 'H', 7.5, $grey, 'C');
    $pdf->text($qx + $qrSize / 2, 540, 'Kode verifikasi: ' . $c['kode'], 'HB', 8.5, $ink, 'C');

    $pdf->text($cx, 560, 'Keaslian sertifikat dapat diperiksa di ' . $verifyUrl, 'H', 7.5, $grey, 'C');

    // ---------- Lampiran: daftar unit ----------
    $top = function (bool $lanjut) use ($pdf, $c, $W, $navy, $grey, $ink, $teal): float {
        $pdf->addPage();
        $pdf->rect(20, 20, $W - 40, 555, null, $navy, 1.5);
        $pdf->text(48, 62, 'LAMPIRAN SERTIFIKAT KOMPETENSI' . ($lanjut ? ' (lanjutan)' : ''), 'HB', 13, $navy, 'L', 0.6);
        $pdf->text($W - 48, 62, 'Nomor: ' . $c['nomor'], 'H', 9.5, $grey, 'R');
        $pdf->line(48, 72, $W - 48, 72, $teal, 1);
        $y = 92;
        foreach ([['Nama', (string)$c['nama']], ['Skema', (string)$c['skema_nama'] . (!empty($c['skema_kode']) ? ' (' . $c['skema_kode'] . ')' : '')]] as [$label, $val]) {
            $pdf->text(48, $y, $label, 'H', 9.5, $grey);
            foreach (array_slice($pdf->wrap($val, 'HB', 10.5, $W - 48 - 150), 0, 2) as $ln) {
                $pdf->text(150, $y, $ln, 'HB', 10.5, $ink);
                $y += 14;
            }
            $y += 2;
        }
        $y += 8;
        $pdf->rect(48, $y, $W - 96, 22, [238, 242, 255]);
        $pdf->text(58, $y + 15, 'No.', 'HB', 9.5, $navy);
        $pdf->text(96, $y + 15, 'Kode unit', 'HB', 9.5, $navy);
        $pdf->text(262, $y + 15, 'Judul unit kompetensi', 'HB', 9.5, $navy);
        return $y + 22;
    };
    $y = $top(false);
    $no = 0;
    $titleW = $W - 48 - 262 - 10;
    foreach ($units as $u) {
        $no++;
        $lines = $pdf->wrap((string)$u['judul'], 'H', 10, $titleW);
        $rowH = 10 + 13 * count($lines);
        if ($y + $rowH > 535) {
            $y = $top(true);
        }
        $pdf->text(58, $y + 16, (string)$no, 'H', 10, $ink);
        $pdf->text(96, $y + 16, (string)$u['kode'], 'H', 9.5, $ink);
        foreach ($lines as $i => $ln) {
            $pdf->text(262, $y + 16 + $i * 13, $ln, 'H', 10, $ink);
        }
        $y += $rowH;
        $pdf->line(48, $y, $W - 48, $y, [226, 232, 240], 0.5);
    }
    $pdf->text(48, 552, 'Asesi dinyatakan kompeten pada seluruh unit di atas. Keaslian: ' . $verifyUrl, 'H', 7.5, $grey);

    return $pdf->output([
        'Title' => 'Sertifikat Kompetensi ' . $c['nomor'],
        'Author' => (string)$c['lsp_nama'],
        'Subject' => $c['nama'] . ' - ' . $c['skema_nama'],
    ], $signer ? [
        'signer' => $signer, 'page' => 0, 'rect' => $ttdRect, 'nama' => $signer['nama'],
        'alasan' => 'Penerbitan sertifikat kompetensi ' . $c['nomor'], 'lokasi' => 'PortalLSP', 'kontak' => $verifyUrl,
    ] : null);
}

/** Huruf kapital yang aman untuk UTF-8. */
function mb_upper(string $s): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
}
