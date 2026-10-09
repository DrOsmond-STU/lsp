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
    // Hash akun demo (password_hash). Kosongkan di produksi.
    'seed_password_hashes' => [],
];
