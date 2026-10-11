<?php
// Tanda tangan digital PDF sertifikat. Setiap LSP punya kunci dan sertifikat penanda tangan sendiri, diterbitkan oleh
// CA PortalLSP yang dibuat sekali di storage/tte. Bila LSP punya sertifikat dari PSrE (berkas .p12), pasang lewat
// config 'tte_p12' dan sertifikat itu yang dipakai.
declare(strict_types=1);

const TTE_CA_NAMA = 'PortalLSP Root CA';
const TTE_CA_HARI = 7300;     // 20 tahun
const TTE_LSP_HARI = 3650;    // 10 tahun, diperbarui otomatis 30 hari sebelum habis

function tte_dir(): string
{
    $d = APP_ROOT . '/storage/tte';
    if (!is_dir($d)) {
        mkdir($d, 0700, true);
    }
    return $d;
}

function tte_aktif(): bool
{
    global $CONFIG;
    return ($CONFIG['tte_aktif'] ?? true) === true && function_exists('openssl_cms_sign');
}

/** Kunci privat disimpan terenkripsi; kata sandinya diturunkan dari kunci aplikasi (storage/app.key). */
function tte_sandi(): string
{
    return base64_encode(hash_hmac('sha256', 'tte-kunci-v1', app_key(), true));
}

/** Konfigurasi OpenSSL milik aplikasi, karena openssl.cnf bawaan hosting belum tentu ada atau bisa dibaca. */
function tte_cnf(): array
{
    $f = tte_dir() . '/openssl.cnf';
    $isi = "[req]\ndistinguished_name = dn\nstring_mask = utf8only\nutf8 = yes\n[dn]\n"
        . "[v3_ca]\nbasicConstraints = critical, CA:true, pathlen:0\nkeyUsage = critical, keyCertSign, cRLSign\nsubjectKeyIdentifier = hash\n"
        . "[v3_ttd]\nbasicConstraints = critical, CA:false\nkeyUsage = critical, digitalSignature, nonRepudiation\n"
        . "subjectKeyIdentifier = hash\nauthorityKeyIdentifier = keyid\n";
    if (!is_file($f) || file_get_contents($f) !== $isi) {
        file_put_contents($f, $isi, LOCK_EX);
    }
    return ['config' => $f, 'digest_alg' => 'sha256', 'private_key_type' => OPENSSL_KEYTYPE_RSA];
}

/** Tulis berkas secara atomik dengan izin 0600. */
function tte_tulis(string $file, string $isi): void
{
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, $isi);
    chmod($tmp, 0600);
    rename($tmp, $file);
}

function tte_kunci_baru(int $bits): OpenSSLAsymmetricKey
{
    $k = openssl_pkey_new(tte_cnf() + ['private_key_bits' => $bits]);
    if ($k === false) {
        throw new RuntimeException('Gagal membuat kunci: ' . openssl_error_string());
    }
    return $k;
}

function tte_simpan_kunci(OpenSSLAsymmetricKey $k, string $file): void
{
    if (!openssl_pkey_export($k, $pem, tte_sandi(), tte_cnf())) {
        throw new RuntimeException('Gagal menyimpan kunci.');
    }
    tte_tulis($file, $pem);
}

function tte_baca_kunci(string $file): OpenSSLAsymmetricKey
{
    $k = openssl_pkey_get_private((string)file_get_contents($file), tte_sandi());
    if ($k === false) {
        throw new RuntimeException('Kunci penanda tangan tidak bisa dibuka (storage/app.key berubah?).');
    }
    return $k;
}

/** Jalankan $fn dengan kunci berkas agar dua permintaan bersamaan tidak membuat kunci ganda. */
function tte_terkunci(callable $fn)
{
    $h = fopen(tte_dir() . '/.lock', 'c');
    flock($h, LOCK_EX);
    try {
        return $fn();
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

/** Sertifikat CA PortalLSP (PEM). Dibuat sekali; sertifikat ini yang dipasang pengguna sebagai sertifikat tepercaya. */
function tte_ca_pem(): string
{
    $crt = tte_dir() . '/ca.crt';
    if (!is_file($crt)) {
        tte_terkunci(function () use ($crt) {
            if (is_file($crt)) return;
            $cfg = tte_cnf();
            $k = tte_kunci_baru(3072);
            $csr = openssl_csr_new(['countryName' => 'ID', 'organizationName' => 'PortalLSP', 'commonName' => TTE_CA_NAMA], $k, $cfg);
            $x = openssl_csr_sign($csr, null, $k, TTE_CA_HARI, $cfg + ['x509_extensions' => 'v3_ca'], random_int(1, PHP_INT_MAX));
            if ($x === false || !openssl_x509_export($x, $pem)) {
                throw new RuntimeException('Gagal membuat CA: ' . openssl_error_string());
            }
            tte_simpan_kunci($k, tte_dir() . '/ca.key');
            tte_tulis($crt, $pem); // ditulis terakhir: tanda CA sudah lengkap
            audit('tte.ca_dibuat', 'ca', null, null);
        });
    }
    return (string)file_get_contents($crt);
}

function tte_sidik_jari(string $pem): string
{
    return strtoupper((string)openssl_x509_fingerprint($pem, 'sha256'));
}

/** Potong teks UTF-8 untuk kolom nama sertifikat (maks. 64 karakter). */
function tte_potong(string $s): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    return function_exists('mb_substr') ? mb_substr($s, 0, 64, 'UTF-8') : substr($s, 0, 64);
}

/** Sertifikat dari PSrE yang dipasang di config: 'tte_p12' => ['<lsp_id>' atau '*' => ['file' => ..., 'password' => ...]]. */
function tte_dari_p12(int $lspId): ?array
{
    global $CONFIG;
    $map = $CONFIG['tte_p12'] ?? [];
    $e = is_array($map) ? ($map[(string)$lspId] ?? $map['*'] ?? null) : null;
    if (!is_array($e) || empty($e['file'])) {
        return null;
    }
    $raw = is_readable((string)$e['file']) ? (string)file_get_contents((string)$e['file']) : '';
    if ($raw === '' || !openssl_pkcs12_read($raw, $o, (string)($e['password'] ?? ''))) {
        error_log('PortalLSP TTE: berkas .p12 untuk LSP ' . $lspId . ' tidak bisa dibaca; memakai sertifikat bawaan PortalLSP.');
        return null;
    }
    $info = openssl_x509_parse($o['cert']) ?: [];
    return ['cert' => $o['cert'], 'key' => $o['pkey'], 'chain' => array_values($o['extracerts'] ?? []),
        'nama' => (string)($info['subject']['CN'] ?? ''), 'tampil' => (string)($info['subject']['CN'] ?? ''),
        'penerbit' => (string)($info['issuer']['CN'] ?? ''), 'sumber' => 'p12'];
}

/** Penanda tangan untuk satu LSP. Sertifikat bawaan diterbitkan ulang bila belum ada, hampir habis, atau nama LSP berubah. */
function tte_penanda_tangan(int $lspId, string $lspNama): ?array
{
    if (!tte_aktif()) {
        return null;
    }
    try {
        if ($p = tte_dari_p12($lspId)) {
            return $p;
        }
        $caPem = tte_ca_pem();
        $crt = tte_dir() . '/lsp-' . $lspId . '.crt';
        $key = tte_dir() . '/lsp-' . $lspId . '.key';
        $cn = tte_potong($lspNama);
        $perlu = function () use ($crt, $cn, $caPem): bool {
            if (!is_file($crt)) return true;
            $pem = (string)file_get_contents($crt);
            $i = openssl_x509_parse($pem);
            // Terbitkan ulang juga bila CA diganti (sertifikat lama tidak lagi berinduk ke CA yang sekarang).
            return !$i || ($i['subject']['CN'] ?? '') !== $cn || $i['validTo_time_t'] < time() + 30 * 86400
                || openssl_x509_verify($pem, $caPem) !== 1;
        };
        if ($perlu()) {
            tte_terkunci(function () use ($perlu, $crt, $key, $cn, $caPem, $lspId) {
                if (!$perlu()) return;
                $cfg = tte_cnf();
                $k = tte_kunci_baru(2048);
                $csr = openssl_csr_new(['countryName' => 'ID', 'organizationName' => $cn, 'organizationalUnitName' => 'Penerbit Sertifikat Kompetensi',
                    'commonName' => $cn], $k, $cfg);
                $x = openssl_csr_sign($csr, $caPem, tte_baca_kunci(tte_dir() . '/ca.key'), TTE_LSP_HARI, $cfg + ['x509_extensions' => 'v3_ttd'],
                    random_int(1, PHP_INT_MAX));
                if ($x === false || !openssl_x509_export($x, $pem)) {
                    throw new RuntimeException('Gagal menerbitkan sertifikat LSP: ' . openssl_error_string());
                }
                tte_simpan_kunci($k, $key);
                tte_tulis($crt, $pem);
                audit('tte.sertifikat_lsp', 'lsp:' . $lspId, null, $lspId);
            });
        }
        return ['cert' => (string)file_get_contents($crt), 'key' => tte_baca_kunci($key), 'chain' => [$caPem],
            'nama' => $cn, 'tampil' => trim($lspNama), 'penerbit' => TTE_CA_NAMA, 'sumber' => 'portal'];
    } catch (Throwable $e) {
        error_log('PortalLSP TTE: ' . $e->getMessage());
        return null;
    }
}

/** Tanda tangan CMS/PKCS#7 terlepas (detached, DER) atas $data. */
function tte_tanda_tangani(string $data, array $signer): string
{
    $dir = tte_dir();
    $in = tempnam($dir, 'in');
    $out = tempnam($dir, 'out');
    $chain = $signer['chain'] ? tempnam($dir, 'ch') : null;
    try {
        file_put_contents($in, $data);
        if ($chain) {
            file_put_contents($chain, implode("\n", $signer['chain']));
        }
        if (!@openssl_cms_sign($in, $out, $signer['cert'], $signer['key'], [], OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER, $chain)) {
            throw new RuntimeException('Gagal menandatangani: ' . openssl_error_string());
        }
        return (string)file_get_contents($out);
    } finally {
        foreach ([$in, $out, $chain] as $f) {
            if ($f && is_file($f)) unlink($f);
        }
    }
}
