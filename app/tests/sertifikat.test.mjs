// Uji sertifikat PDF + kode QR: struktur PDF, isi teks, hak akses, dan regresi matriks QR.
// Matriks acuan di bawah sudah diverifikasi dua decoder independen (jsQR dan ZXing) saat fitur dibuat.
import { execFileSync } from 'child_process';
import zlib from 'zlib';
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const PW = 'Demo-Pass-2026';
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, typeof x === 'string' ? x : JSON.stringify(x)); } };
async function login(email) {
  let r = await fetch(BASE + '/api.php?r=auth/me');
  let cookie = r.headers.get('set-cookie').match(/lsp_sid=[^;]+/)[0];
  const { csrf } = await r.json();
  r = await fetch(BASE + '/api.php?r=auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Cookie: cookie }, body: JSON.stringify({ email, password: PW }) });
  const sc = r.headers.get('set-cookie'); if (sc) cookie = sc.match(/lsp_sid=[^;]+/)[0];
  return cookie;
}
const get = (path, cookie) => fetch(BASE + '/api.php?r=' + path, { headers: cookie ? { Cookie: cookie } : {} });

console.log('Regresi encoder QR');
{
  const php = `require "src/qr.php"; foreach ([["https://lsp.semestateknologiutama.com/?cek=TDN7K3P9QX","M"],[str_repeat("PortalLSP-",10),"H"],[str_repeat("z",150),"L"]] as [$d,$e]) { $m = qr_matrix($d, $e); echo count($m), " ", hash("sha256", json_encode($m)), "\\n"; }`;
  const out = execFileSync('php', ['-r', php], { cwd: new URL('..', import.meta.url).pathname }).toString().trim().split('\n');
  check('versi 4-M (tautan verifikasi) sama dengan matriks terverifikasi', out[0] === '33 03fece5205d30364c8f7f4c0eec5cf7d430b25167ada79541cea12f8571e079e', out[0]);
  check('versi 10-H (4 blok + info versi) sama', out[1] === '57 5a407635cf6f117897edcfc041870caf035a4e0f0a1e3c07eacbe9675c5e3364', out[1]);
  check('versi 7-L sama', out[2] === '45 91549339d4452cef99d8e6b10f6955607fc71091da34d758efaa134db9167529', out[2]);
}

console.log('PDF sertifikat');
const rina = await login('asesi@demo.portallsp.id');
const s = (await (await get('asesi/sertifikat', rina)).json()).items[0];
{
  const r = await get('sertifikat/pdf&id=' + s.id + '&unduh=1', rina);
  const buf = Buffer.from(await r.arrayBuffer());
  check('pemilik mengunduh PDF (200, application/pdf)', r.status === 200 && r.headers.get('content-type') === 'application/pdf');
  check('nama berkas aman dan sebagai unduhan', /^attachment; filename="sertifikat-[A-Za-z0-9-]+\.pdf"$/.test(r.headers.get('content-disposition') || ''), r.headers.get('content-disposition'));
  check('tidak disimpan di cache', /no-store/.test(r.headers.get('cache-control') || ''));
  const raw = buf.toString('latin1');
  check('berkas PDF utuh (%PDF-1.4 … %%EOF)', raw.startsWith('%PDF-1.4') && raw.trimEnd().endsWith('%%EOF'));
  const pages = (raw.match(/\/Type \/Page\b/g) || []).length;
  check('2 halaman: depan + lampiran unit', pages === 2, String(pages));
  // Offset xref harus menunjuk ke objek yang benar.
  const xref = Number(raw.match(/startxref\n(\d+)/)[1]);
  check('tabel xref valid', raw.slice(xref, xref + 4) === 'xref');
  const offs = [...raw.slice(xref).matchAll(/^(\d{10}) 00000 n /gm)].map(m => Number(m[1]));
  check('setiap offset objek tepat', offs.length > 5 && offs.every((o, i) => raw.slice(o).startsWith(`${i + 1} 0 obj`)));
  let text = '';
  for (const m of raw.matchAll(/stream\n([\s\S]*?)\nendstream/g)) text += zlib.inflateSync(Buffer.from(m[1], 'latin1')).toString('latin1');
  check('memuat nama pemegang', text.includes('(Rina Kartika Sari)'));
  check('memuat nomor dan kode verifikasi', text.includes(s.nomor.replace(/[()\\]/g, '')) && text.includes(s.kode));
  check('memuat skema dan status KOMPETEN', text.includes(s.skema_nama) && text.includes('(KOMPETEN)'));
  check('memuat tautan verifikasi', text.includes('?cek=' + s.kode));
  check('kode QR tergambar (ratusan kotak modul)', (text.match(/ re /g) || []).length > 150);
  const nUnit = Number((text.match(/(\d+) unit kompetensi \\\(lihat lampiran/) || [])[1] || 0);
  check('lampiran memuat semua unit kompetensi', text.includes('Judul unit kompetensi') && nUnit > 0 && text.includes(`(${nUnit}) Tj`), String(nUnit));
}

console.log('Hak akses');
{
  const id = s.id;
  check('tanpa login ditolak (401)', (await get('sertifikat/pdf&id=' + id)).status === 401);
  check('Admin LSP penerbit boleh (200)', (await get('sertifikat/pdf&id=' + id, await login('admin.tdn@demo.portallsp.id'))).status === 200);
  check('Admin Platform boleh (200)', (await get('sertifikat/pdf&id=' + id, await login('superadmin@demo.portallsp.id'))).status === 200);
  check('Admin LSP lain ditolak (404)', (await get('sertifikat/pdf&id=' + id, await login('admin.pbi@demo.portallsp.id'))).status === 404);
  check('asesor ditolak (404)', (await get('sertifikat/pdf&id=' + id, await login('asesor@demo.portallsp.id'))).status === 404);
  check('Marketing LSP penerbit ditolak (404)', (await get('sertifikat/pdf&id=' + id, await login('marketing.tdn@demo.portallsp.id'))).status === 404);
  check('id tidak ada → 404', (await get('sertifikat/pdf&id=999999', rina)).status === 404);
  check('id bukan angka → 404', (await get('sertifikat/pdf&id=1%27', rina)).status === 404);
}

console.log('Halaman cetak HTML');
{
  const html = await (await get('sertifikat/cetak&id=' + s.id, rina)).text();
  check('memuat kode QR SVG', html.includes('<svg') && html.includes('aria-label="Kode QR verifikasi"'));
  check('memuat tautan verifikasi', html.includes('?cek=' + s.kode));
}

console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
