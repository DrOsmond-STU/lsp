<?php
// Generator PDF kecil tanpa library: halaman, teks dengan 14 font standar PDF (WinAnsi), garis, kotak, dan kode QR vektor.
// Koordinat memakai titik (pt) dengan titik nol di kiri atas halaman.
declare(strict_types=1);

const PDF_FONTS = ['H' => 'Helvetica', 'HB' => 'Helvetica-Bold', 'T' => 'Times-Roman', 'TB' => 'Times-Bold', 'TI' => 'Times-Italic'];

/** Lebar glyf (per 1000 unit) untuk karakter 32–126, dari metrik AFM standar Adobe. */
function pdf_widths(string $font): array
{
    static $cache = [];
    if (isset($cache[$font])) return $cache[$font];
    $t = [
        'H' => '278 278 355 556 556 889 667 191 333 333 389 584 278 333 278 278 556 556 556 556 556 556 556 556 556 556 278 278 584 584 584 556 1015 667 667 722 722 667 611 778 722 278 500 667 556 833 722 778 667 778 722 667 611 722 667 944 667 667 611 278 278 278 469 556 333 556 556 500 556 556 278 556 556 222 222 500 222 833 556 556 556 556 333 500 278 556 500 722 500 500 500 334 260 334 584',
        'HB' => '278 333 474 556 556 889 722 238 333 333 389 584 278 333 278 278 556 556 556 556 556 556 556 556 556 556 333 333 584 584 584 611 975 722 722 722 722 667 611 778 722 278 556 722 611 833 722 778 667 778 722 667 611 722 667 944 667 667 611 333 278 333 584 556 333 556 611 556 611 556 333 611 611 278 278 556 278 889 611 611 611 611 389 556 333 611 556 778 556 556 500 389 280 389 584',
        'T' => '250 333 408 500 500 833 778 180 333 333 500 564 250 333 250 278 500 500 500 500 500 500 500 500 500 500 278 278 564 564 564 444 921 722 667 667 722 611 556 722 722 333 389 722 611 889 722 722 556 722 667 556 611 722 722 944 722 722 611 333 278 333 469 500 333 444 500 444 500 444 333 500 500 278 278 500 278 778 500 500 500 500 333 389 278 500 500 722 500 500 444 480 200 480 541',
        'TB' => '250 333 555 500 500 1000 833 278 333 333 500 570 250 333 250 278 500 500 500 500 500 500 500 500 500 500 333 333 570 570 570 500 930 722 667 722 722 667 611 778 778 389 500 778 667 944 722 778 611 778 722 556 667 722 722 1000 722 722 667 333 278 333 581 500 333 500 556 444 556 444 333 500 556 278 333 556 278 833 556 500 556 556 444 389 333 556 500 722 500 500 444 394 220 394 520',
        'TI' => '250 333 420 500 500 833 778 214 333 333 500 675 250 333 250 278 500 500 500 500 500 500 500 500 500 500 333 333 675 675 675 500 920 611 611 667 722 611 611 722 722 333 444 667 556 833 667 722 611 722 611 500 556 722 611 833 611 556 556 389 278 389 422 500 333 500 500 444 500 444 278 500 500 278 278 444 278 722 500 500 500 500 389 389 278 500 444 667 444 444 389 400 275 400 541',
    ];
    return $cache[$font] = array_map('intval', explode(' ', $t[$font]));
}

/** UTF-8 ke Windows-1252 (encoding WinAnsi PDF). Karakter di luar jangkauan diganti "?". */
function pdf_ansi(string $s): string
{
    static $special = [0x20AC => 0x80, 0x201A => 0x82, 0x0192 => 0x83, 0x201E => 0x84, 0x2026 => 0x85, 0x2020 => 0x86, 0x2021 => 0x87, 0x02C6 => 0x88,
        0x2030 => 0x89, 0x0160 => 0x8A, 0x2039 => 0x8B, 0x0152 => 0x8C, 0x017D => 0x8E, 0x2018 => 0x91, 0x2019 => 0x92, 0x201C => 0x93, 0x201D => 0x94,
        0x2022 => 0x95, 0x2013 => 0x96, 0x2014 => 0x97, 0x02DC => 0x98, 0x2122 => 0x99, 0x0161 => 0x9A, 0x203A => 0x9B, 0x0153 => 0x9C, 0x017E => 0x9E, 0x0178 => 0x9F];
    $out = '';
    $n = strlen($s);
    for ($i = 0; $i < $n;) {
        $b = ord($s[$i]);
        if ($b < 0x80) { $cp = $b; $len = 1; }
        elseif (($b & 0xE0) === 0xC0) { $cp = $b & 0x1F; $len = 2; }
        elseif (($b & 0xF0) === 0xE0) { $cp = $b & 0x0F; $len = 3; }
        elseif (($b & 0xF8) === 0xF0) { $cp = $b & 0x07; $len = 4; }
        else { $out .= '?'; $i++; continue; }
        for ($k = 1; $k < $len; $k++) {
            $cp = ($cp << 6) | (ord($s[$i + $k] ?? "\0") & 0x3F);
        }
        $i += $len;
        if ($cp < 0x20) $out .= ' ';
        elseif ($cp < 0x7F || ($cp >= 0xA0 && $cp <= 0xFF)) $out .= chr($cp);
        elseif (isset($special[$cp])) $out .= chr($special[$cp]);
        else $out .= '?';
    }
    return $out;
}

final class MiniPdf
{
    private array $pages = [];
    private int $cur = -1;

    public function __construct(private float $w = 842.0, private float $h = 595.0)
    {
    }

    public function width(): float { return $this->w; }
    public function height(): float { return $this->h; }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->cur = count($this->pages) - 1;
    }

    private function put(string $ops): void
    {
        $this->pages[$this->cur] .= $ops . "\n";
    }

    private static function n(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.2F', $v), '0'), '.');
        return $s === '-0' ? '0' : $s;
    }

    private static function rgb(array $c): string
    {
        return implode(' ', array_map(function ($v) { return self::n($v / 255); }, $c));
    }

    /** Kotak: $fill/$stroke berupa warna [r,g,b] atau null. */
    public function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $lw = 1.0): void
    {
        $ops = 'q ';
        if ($fill) $ops .= self::rgb($fill) . ' rg ';
        if ($stroke) $ops .= self::rgb($stroke) . ' RG ' . self::n($lw) . ' w ';
        $ops .= self::n($x) . ' ' . self::n($this->h - $y - $h) . ' ' . self::n($w) . ' ' . self::n($h) . ' re ' . ($fill && $stroke ? 'B' : ($fill ? 'f' : 'S')) . ' Q';
        $this->put($ops);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $color, float $lw = 1.0): void
    {
        $this->put('q ' . self::rgb($color) . ' RG ' . self::n($lw) . ' w ' . self::n($x1) . ' ' . self::n($this->h - $y1) . ' m '
            . self::n($x2) . ' ' . self::n($this->h - $y2) . ' l S Q');
    }

    public function textWidth(string $utf8, string $font, float $size, float $spacing = 0.0): float
    {
        $s = pdf_ansi($utf8);
        $wd = pdf_widths($font);
        $sum = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($s[$i]);
            $sum += ($c >= 32 && $c <= 126) ? $wd[$c - 32] : ($font[0] === 'H' ? 556 : 500);
        }
        return $sum * $size / 1000 + $spacing * max(0, $len - 1);
    }

    /** Teks pada garis dasar $y. $align: L (x = kiri), C (x = tengah), R (x = kanan). */
    public function text(float $x, float $y, string $utf8, string $font, float $size, array $color, string $align = 'L', float $spacing = 0.0): void
    {
        $wdt = $this->textWidth($utf8, $font, $size, $spacing);
        if ($align === 'C') $x -= $wdt / 2;
        elseif ($align === 'R') $x -= $wdt;
        $s = strtr(pdf_ansi($utf8), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $fid = array_search($font, array_keys(PDF_FONTS), true) + 1;
        $this->put('BT ' . self::rgb($color) . ' rg /F' . $fid . ' ' . self::n($size) . ' Tf ' . self::n($spacing) . ' Tc '
            . self::n($x) . ' ' . self::n($this->h - $y) . ' Td (' . $s . ') Tj ET');
    }

    /** Beberapa potongan teks (font/warna berbeda) dalam satu baris, ditengahkan pada $cx. Potongan: [teks, font, warna]. */
    public function textRunsCentered(float $cx, float $y, array $runs, float $size): void
    {
        $total = 0;
        foreach ($runs as [$t, $f]) $total += $this->textWidth($t, $f, $size);
        $x = $cx - $total / 2;
        foreach ($runs as [$t, $f, $c]) {
            $this->text($x, $y, $t, $f, $size, $c);
            $x += $this->textWidth($t, $f, $size);
        }
    }

    /** Pecah teks menjadi baris dengan lebar maksimum $maxW. */
    public function wrap(string $utf8, string $font, float $size, float $maxW): array
    {
        $words = preg_split('/\s+/u', trim($utf8)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $wd) {
            $try = $line === '' ? $wd : $line . ' ' . $wd;
            if ($line !== '' && $this->textWidth($try, $font, $size) > $maxW) {
                $lines[] = $line;
                $line = $wd;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') $lines[] = $line;
        return $lines ?: [''];
    }

    /** Kode QR vektor: matriks dari qr_matrix(), sudut kiri atas ($x,$y), sisi $size termasuk zona tenang 4 modul. */
    public function qr(array $matrix, float $x, float $y, float $size): void
    {
        $n = count($matrix);
        $mod = $size / ($n + 8);
        $this->rect($x, $y, $size, $size, [255, 255, 255]);
        $ops = 'q 0 0 0 rg ';
        foreach ($matrix as $r => $row) {
            $c = 0;
            while ($c < $n) {
                if (!$row[$c]) { $c++; continue; }
                $start = $c;
                while ($c < $n && $row[$c]) $c++;
                // Satu kotak per deret modul gelap dalam satu baris, sedikit dilebarkan agar tidak ada celah tipis saat dirender.
                $ops .= self::n($x + ($start + 4) * $mod) . ' ' . self::n($this->h - ($y + ($r + 5) * $mod) - 0.02) . ' '
                    . self::n(($c - $start) * $mod) . ' ' . self::n($mod + 0.04) . ' re ';
            }
        }
        $this->put($ops . 'f Q');
    }

    /** Susun berkas PDF. $info: Title, Author, Subject (UTF-8). */
    public function output(array $info = []): string
    {
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $fontIds = [];
        $next = 3;
        foreach (PDF_FONTS as $key => $base) {
            $fontIds[$key] = $next;
            $objs[$next++] = '<< /Type /Font /Subtype /Type1 /BaseFont /' . $base . ' /Encoding /WinAnsiEncoding >>';
        }
        $fontRes = '';
        $i = 1;
        foreach ($fontIds as $id) $fontRes .= '/F' . $i++ . ' ' . $id . ' 0 R ';
        $kids = [];
        foreach ($this->pages as $content) {
            $data = $content;
            $filter = '';
            if (function_exists('gzcompress')) {
                $data = gzcompress($content, 6);
                $filter = ' /Filter /FlateDecode';
            }
            $cid = $next++;
            $objs[$cid] = "<< /Length " . strlen($data) . $filter . " >>\nstream\n" . $data . "\nendstream";
            $pid = $next++;
            $objs[$pid] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::n($this->w) . ' ' . self::n($this->h) . '] /Resources << /Font << '
                . $fontRes . '>> >> /Contents ' . $cid . ' 0 R >>';
            $kids[] = $pid . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $infoId = $next++;
        $str = function (string $v): string {
            // Teks metadata sebagai UTF-16BE dengan BOM, dalam bentuk heksadesimal.
            $u = function_exists('mb_convert_encoding') ? mb_convert_encoding($v, 'UTF-16BE', 'UTF-8') : pdf_ansi($v);
            return '<' . (function_exists('mb_convert_encoding') ? 'FEFF' : '') . strtoupper(bin2hex($u)) . '>';
        };
        $dict = '<< /Producer ' . $str('PortalLSP') . ' /CreationDate (D:' . date('YmdHis') . ')';
        foreach (['Title', 'Author', 'Subject'] as $k) {
            if (!empty($info[$k])) $dict .= ' /' . $k . ' ' . $str((string)$info[$k]);
        }
        $objs[$infoId] = $dict . ' >>';
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $max = max(array_keys($objs));
        $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $max; $id++) {
            $out .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0) . "\n";
        }
        $out .= "trailer\n<< /Size " . ($max + 1) . ' /Root 1 0 R /Info ' . $infoId . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $out;
    }
}
