<?php
// Salin menjadi config.php (di LUAR folder public) lalu isi. Jangan commit config.php.
return [
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=NAMA_DB;charset=utf8mb4',
        'user' => 'USER_DB',
        'pass' => 'PASSWORD_DB',
    ],
    'session_name' => 'lsp_sid',
    'idle_timeout' => 1800,      // 30 menit tanpa aktivitas
    'absolute_timeout' => 28800, // maksimal 8 jam per sesi
    'cookie_secure' => true,     // wajib true di produksi (HTTPS)
    'app_url' => 'https://lsp.example.id',          // dipakai untuk tautan di email
    'mail_from' => 'no-reply@lsp.example.id',
    // Asisten AI (Claude). Kosong = fitur nonaktif. Kunci dari console.anthropic.com; jangan pernah di-commit.
    'ai_api_key' => '',
    'ai_model' => 'claude-opus-5-5',  // bisa diganti model yang lebih hemat, mis. claude-haiku-5-5
    'ai_daily_limit' => 2000,         // batas total pesan per hari untuk seluruh platform
    // WhatsApp: '' (nonaktif), 'fonnte' (wa_token = token perangkat Fonnte),
    // atau 'meta' (WhatsApp Cloud API: wa_token + wa_phone_number_id + template yang disetujui, {{1}} judul, {{2}} isi).
    'wa_driver' => '',
    'wa_token' => '',
    'wa_phone_number_id' => '',
    'wa_meta_template' => 'notifikasi_portallsp',
    // true = asesi bisa menandai tagihan lunas sendiri (simulasi/demo). Set false saat LSP nyata beroperasi.
    'payment_simulation' => true,
    // Hash akun demo (password_hash). Kosongkan di produksi.
    'seed_password_hashes' => [],
];
