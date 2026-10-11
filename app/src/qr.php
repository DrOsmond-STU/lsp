<?php
// Encoder QR Code (ISO/IEC 18004, model 2) tanpa library: mode byte, versi 1–10, level koreksi L/M/Q/H.
// Cukup untuk tautan verifikasi sertifikat (±60 karakter). Dipakai untuk PDF dan halaman cetak sertifikat.
declare(strict_types=1);

const QR_EC_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];
// [EC per blok, jumlah blok grup 1, data per blok grup 1, jumlah blok grup 2, data per blok grup 2]
const QR_BLOCKS = [
    1 => ['L' => [7, 1, 19, 0, 0], 'M' => [10, 1, 16, 0, 0], 'Q' => [13, 1, 13, 0, 0], 'H' => [17, 1, 9, 0, 0]],
    2 => ['L' => [10, 1, 34, 0, 0], 'M' => [16, 1, 28, 0, 0], 'Q' => [22, 1, 22, 0, 0], 'H' => [28, 1, 16, 0, 0]],
    3 => ['L' => [15, 1, 55, 0, 0], 'M' => [26, 1, 44, 0, 0], 'Q' => [18, 2, 17, 0, 0], 'H' => [22, 2, 13, 0, 0]],
    4 => ['L' => [20, 1, 80, 0, 0], 'M' => [18, 2, 32, 0, 0], 'Q' => [26, 2, 24, 0, 0], 'H' => [16, 4, 9, 0, 0]],
    5 => ['L' => [26, 1, 108, 0, 0], 'M' => [24, 2, 43, 0, 0], 'Q' => [18, 2, 15, 2, 16], 'H' => [22, 2, 11, 2, 12]],
    6 => ['L' => [18, 2, 68, 0, 0], 'M' => [16, 4, 27, 0, 0], 'Q' => [24, 4, 19, 0, 0], 'H' => [28, 4, 15, 0, 0]],
    7 => ['L' => [20, 2, 78, 0, 0], 'M' => [18, 4, 31, 0, 0], 'Q' => [18, 2, 14, 4, 15], 'H' => [26, 4, 13, 1, 14]],
    8 => ['L' => [24, 2, 97, 0, 0], 'M' => [22, 2, 38, 2, 39], 'Q' => [22, 4, 18, 2, 19], 'H' => [26, 4, 14, 2, 15]],
    9 => ['L' => [30, 2, 116, 0, 0], 'M' => [22, 3, 36, 2, 37], 'Q' => [20, 4, 16, 4, 17], 'H' => [24, 4, 12, 4, 13]],
    10 => ['L' => [18, 2, 68, 2, 69], 'M' => [26, 4, 43, 1, 44], 'Q' => [24, 6, 19, 2, 20], 'H' => [28, 6, 15, 2, 16]],
];
const QR_ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];

/**
 * Hasilkan matriks QR: array baris berisi bool (true = modul gelap), tanpa zona tenang.
 * Versi terkecil yang muat dipilih otomatis; mask terbaik dipilih dengan skor penalti standar.
 */
function qr_matrix(string $data, string $ecl = 'M'): array
{
    if (!isset(QR_EC_BITS[$ecl])) {
        throw new InvalidArgumentException('Level koreksi QR tidak dikenal.');
    }
    $len = strlen($data);
    $version = 0;
    foreach (QR_BLOCKS as $v => $levels) {
        [, $b1, $d1, $b2, $d2] = $levels[$ecl];
        $capacityBits = ($b1 * $d1 + $b2 * $d2) * 8;
        $countBits = $v < 10 ? 8 : 16;
        if (4 + $countBits + $len * 8 <= $capacityBits) {
            $version = $v;
            break;
        }
    }
    if (!$version) {
        throw new InvalidArgumentException('Data terlalu panjang untuk QR versi 1–10.');
    }
    [$ecLen, $b1, $d1, $b2, $d2] = QR_BLOCKS[$version][$ecl];
    $dataCodewords = $b1 * $d1 + $b2 * $d2;

    // Aliran bit: mode byte (0100), panjang, data, terminator, pengisi.
    $bits = [];
    $push = function (int $val, int $n) use (&$bits): void {
        for ($i = $n - 1; $i >= 0; $i--) {
            $bits[] = ($val >> $i) & 1;
        }
    };
    $push(0b0100, 4);
    $push($len, $version < 10 ? 8 : 16);
    for ($i = 0; $i < $len; $i++) {
        $push(ord($data[$i]), 8);
    }
    $cap = $dataCodewords * 8;
    $push(0, min(4, $cap - count($bits)));
    $push(0, (8 - count($bits) % 8) % 8);
    $codewords = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) {
            $byte = ($byte << 1) | $bits[$i + $j];
        }
        $codewords[] = $byte;
    }
    for ($pad = 0xEC; count($codewords) < $dataCodewords; $pad ^= 0xEC ^ 0x11) {
        $codewords[] = $pad;
    }

    // Blok data + koreksi Reed-Solomon, lalu disisipkan berselang-seling.
    $blocks = [];
    $offset = 0;
    foreach ([[$b1, $d1], [$b2, $d2]] as [$count, $size]) {
        for ($i = 0; $i < $count; $i++) {
            $blk = array_slice($codewords, $offset, $size);
            $offset += $size;
            $blocks[] = [$blk, qr_rs_remainder($blk, $ecLen)];
        }
    }
    $final = [];
    $maxData = max($d1, $d2);
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($blocks as [$blk]) {
            if ($i < count($blk)) $final[] = $blk[$i];
        }
    }
    for ($i = 0; $i < $ecLen; $i++) {
        foreach ($blocks as [, $ec]) {
            $final[] = $ec[$i];
        }
    }

    $size = $version * 4 + 17;
    $mod = array_fill(0, $size, array_fill(0, $size, false));
    $fn = array_fill(0, $size, array_fill(0, $size, false));
    $set = function (int $x, int $y, bool $dark) use (&$mod, &$fn): void {
        $mod[$y][$x] = $dark;
        $fn[$y][$x] = true;
    };
    // Pola pewaktu.
    for ($i = 0; $i < $size; $i++) {
        $set(6, $i, $i % 2 === 0);
        $set($i, 6, $i % 2 === 0);
    }
    // Pola pencari + pemisah di tiga sudut.
    foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) continue;
                $d = max(abs($dx), abs($dy));
                $set($x, $y, $d !== 2 && $d !== 4);
            }
        }
    }
    // Pola penyelaras (kecuali yang menimpa pola pencari).
    $al = QR_ALIGN[$version];
    $n = count($al);
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) continue;
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $set($al[$i] + $dx, $al[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                }
            }
        }
    }
    qr_draw_format($set, $size, $ecl, 0); // tandai area format sebagai fungsi
    if ($version >= 7) {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        $vbits = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = (($vbits >> $i) & 1) === 1;
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $set($a, $b, $bit);
            $set($b, $a, $bit);
        }
    }

    // Penempatan zig-zag dari kanan bawah.
    $i = 0;
    $total = count($final) * 8;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) $right = 5;
        for ($vert = 0; $vert < $size; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                $upward = (($right + 1) & 2) === 0;
                $y = $upward ? $size - 1 - $vert : $vert;
                if (!$fn[$y][$x] && $i < $total) {
                    $mod[$y][$x] = (($final[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                    $i++;
                }
            }
        }
    }

    // Pilih mask dengan penalti terkecil.
    $best = null;
    $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $m = $mod;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$fn[$y][$x] && qr_mask_bit($mask, $x, $y)) $m[$y][$x] = !$m[$y][$x];
            }
        }
        $setM = function (int $x, int $y, bool $dark) use (&$m): void { $m[$y][$x] = $dark; };
        qr_draw_format($setM, $size, $ecl, $mask);
        $score = qr_penalty($m, $size);
        if ($score < $bestScore) {
            $bestScore = $score;
            $best = $m;
        }
    }
    return $best;
}

function qr_mask_bit(int $mask, int $x, int $y): bool
{
    switch ($mask) {
        case 0: return ($x + $y) % 2 === 0;
        case 1: return $y % 2 === 0;
        case 2: return $x % 3 === 0;
        case 3: return ($x + $y) % 3 === 0;
        case 4: return (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0;
        case 5: return ($x * $y) % 2 + ($x * $y) % 3 === 0;
        case 6: return (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0;
        default: return (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0;
    }
}

function qr_draw_format(callable $set, int $size, string $ecl, int $mask): void
{
    $data = (QR_EC_BITS[$ecl] << 3) | $mask;
    $rem = $data;
    for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
    $bits = (($data << 10) | $rem) ^ 0x5412;
    $bit = function (int $i) use ($bits): bool { return (($bits >> $i) & 1) === 1; };
    for ($i = 0; $i <= 5; $i++) $set(8, $i, $bit($i));
    $set(8, 7, $bit(6));
    $set(8, 8, $bit(7));
    $set(7, 8, $bit(8));
    for ($i = 9; $i < 15; $i++) $set(14 - $i, 8, $bit($i));
    for ($i = 0; $i < 8; $i++) $set($size - 1 - $i, 8, $bit($i));
    for ($i = 8; $i < 15; $i++) $set(8, $size - 15 + $i, $bit($i));
    $set(8, $size - 8, true); // modul gelap tetap
}

/** Sisa pembagian polinomial Reed-Solomon di GF(256) dengan polinom 0x11D. */
function qr_rs_remainder(array $data, int $degree): array
{
    static $exp = null, $log = null;
    if ($exp === null) {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    }
    $mul = function (int $a, int $b) use (&$exp, &$log): int {
        return ($a === 0 || $b === 0) ? 0 : $exp[$log[$a] + $log[$b]];
    };
    // Polinom generator: (x - a^0)(x - a^1)...(x - a^(degree-1)), koefisien dari pangkat tertinggi (tanpa koefisien 1 di depan).
    $gen = array_fill(0, $degree, 0);
    $gen[$degree - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        for ($j = 0; $j < $degree; $j++) {
            $gen[$j] = $mul($gen[$j], $root);
            if ($j + 1 < $degree) $gen[$j] ^= $gen[$j + 1];
        }
        $root = $mul($root, 0x02);
    }
    $res = array_fill(0, $degree, 0);
    foreach ($data as $b) {
        $factor = $b ^ array_shift($res);
        $res[] = 0;
        for ($i = 0; $i < $degree; $i++) $res[$i] ^= $mul($gen[$i], $factor);
    }
    return $res;
}

function qr_penalty(array $m, int $size): int
{
    $score = 0;
    // Aturan 1: deret 5+ modul sewarna (baris dan kolom). Aturan 3: pola mirip pola pencari.
    for ($pass = 0; $pass < 2; $pass++) {
        for ($a = 0; $a < $size; $a++) {
            $run = 1;
            $line = [];
            for ($b = 0; $b < $size; $b++) {
                $line[] = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
            }
            for ($b = 1; $b < $size; $b++) {
                if ($line[$b] === $line[$b - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) $score += $run - 2;
                    $run = 1;
                }
            }
            if ($run >= 5) $score += $run - 2;
            $s = '';
            foreach ($line as $v) $s .= $v ? '1' : '0';
            $score += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
        }
    }
    // Aturan 2: blok 2x2 sewarna.
    $dark = 0;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if ($m[$y][$x]) $dark++;
            if ($x < $size - 1 && $y < $size - 1) {
                $c = $m[$y][$x];
                if ($m[$y][$x + 1] === $c && $m[$y + 1][$x] === $c && $m[$y + 1][$x + 1] === $c) $score += 3;
            }
        }
    }
    // Aturan 4: proporsi modul gelap mendekati 50%.
    $total = $size * $size;
    $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
    return $score + max(0, $k) * 10;
}

/** QR sebagai SVG (untuk halaman cetak HTML). */
function qr_svg(string $data, int $px = 120): string
{
    $m = qr_matrix($data, 'M');
    $n = count($m);
    $q = 4; // zona tenang
    $path = '';
    foreach ($m as $y => $row) {
        foreach ($row as $x => $dark) {
            if ($dark) $path .= 'M' . ($x + $q) . ' ' . ($y + $q) . 'h1v1h-1z';
        }
    }
    $v = $n + 2 * $q;
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $px . '" height="' . $px . '" viewBox="0 0 ' . $v . ' ' . $v . '" shape-rendering="crispEdges" role="img" aria-label="Kode QR verifikasi">'
        . '<rect width="' . $v . '" height="' . $v . '" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
}
