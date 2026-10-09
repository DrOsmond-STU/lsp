<?php
// Sekali jalan (CLI): ganti elemen header pada app.js agar editor cPanel tidak menyisipkan meta charset.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$f = dirname(__DIR__) . '/public/app.js';
$s = file_get_contents($f);
$open = '<' . 'hea' . 'der class="topbar">';
$meta = '<' . 'meta charset="utf-8">';
$s = str_replace($open . $meta, '<div class="topbar" role="banner">', $s);
$s = str_replace($open, '<div class="topbar" role="banner">', $s);
$s = str_replace('</div></' . 'hea' . 'der>', '</div></div>', $s);
file_put_contents($f, $s);
echo 'app.js ' . strlen($s) . " byte\n";
