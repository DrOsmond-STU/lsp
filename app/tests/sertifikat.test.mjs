// Uji sertifikat PDF + kode QR: struktur PDF, isi teks, hak akses, dan regresi matriks QR.
// Matriks acuan di bawah sudah diverifikasi dua decoder independen (jsQR dan ZXing) saat fitur dibuat.
import { execFileSync, spawnSync } from 'child_process';
import fs from 'fs';
import os from 'os';
import path from 'path';
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
  check('berkas PDF utuh (%PDF-1.x … %%EOF)', /^%PDF-1\.\d/.test(raw) && raw.trimEnd().endsWith('%%EOF'));
  const pages = (raw.match(/\/Type \/Page\b/g) || []).length;
  check('2 halaman: depan + lampiran unit', pages === 2, String(pages));
  // Offset xref harus menunjuk ke objek yang benar.
  const xref = Number(raw.match(/startxref\n(\d+)/)[1]);
  check('tabel xref valid', raw.slice(xref, xref + 4) === 'xref');
  const offs = [...raw.slice(xref).matchAll(/^(\d{10}) 00000 n /gm)].map(m => Number(m[1]));
  check('setiap offset objek tepat', offs.length > 5 && offs.every((o, i) => raw.slice(o).startsWith(`${i + 1} 0 obj`)));
  let text = '';
  for (const m of raw.matchAll(/stream\n([\s\S]*?)\nendstream/g)) if (m[1]) text += zlib.inflateSync(Buffer.from(m[1], 'latin1')).toString('latin1');
  check('memuat nama pemegang', text.includes('(Rina Kartika Sari)'));
  check('memuat nomor dan kode verifikasi', text.includes(s.nomor.replace(/[()\\]/g, '')) && text.includes(s.kode));
  check('memuat skema dan status KOMPETEN', text.includes(s.skema_nama) && text.includes('(KOMPETEN)'));
  check('memuat tautan verifikasi', text.includes('?cek=' + s.kode));
  check('kode QR tergambar (ratusan kotak modul)', (text.match(/ re /g) || []).length > 150);
  const nUnit = Number((text.match(/(\d+) unit kompetensi \\\(lihat lampiran/) || [])[1] || 0);
  check('lampiran memuat semua unit kompetensi', text.includes('Judul unit kompetensi') && nUnit > 0 && text.includes(`(${nUnit}) Tj`), String(nUnit));
}

console.log('Tanda tangan digital');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'ttd-'));
const ttdPdf = async cookie => Buffer.from(await (await get('sertifikat/pdf&id=' + s.id, cookie)).arrayBuffer());
// Bagian yang ditandatangani (ByteRange) dan tanda tangan DER dari /Contents.
function bagian(buf) {
  const raw = buf.toString('latin1');
  const br = raw.match(/\/ByteRange \[(\d+) (\d+) (\d+) (\d+)\]/).slice(1).map(Number);
  const hex = raw.slice(br[1] + 1, br[2] - 1);
  let der = Buffer.from(hex, 'hex');
  const n = der[1] & 0x80 ? der.subarray(2, 2 + (der[1] & 0x7f)).reduce((a, b) => a * 256 + b, 0) + 2 + (der[1] & 0x7f) : der[1] + 2;
  der = der.subarray(0, n);
  return { raw, br, data: Buffer.concat([buf.subarray(br[0], br[1]), buf.subarray(br[2], br[2] + br[3])]), der };
}
const verifikasi = (data, der, ca) => {
  fs.writeFileSync(path.join(tmp, 'data.bin'), data); fs.writeFileSync(path.join(tmp, 'sig.der'), der); fs.writeFileSync(path.join(tmp, 'ca.pem'), ca);
  return spawnSync('openssl', ['cms', '-verify', '-binary', '-inform', 'DER', '-in', path.join(tmp, 'sig.der'), '-content', path.join(tmp, 'data.bin'),
    '-CAfile', path.join(tmp, 'ca.pem'), '-purpose', 'any', '-out', os.devNull]).status === 0;
};
{
  const rc = await get('pub/ca');
  const ca = await rc.text();
  check('sertifikat induk (CA) bisa diunduh tanpa login', rc.status === 200 && /x509-ca-cert/.test(rc.headers.get('content-type')) && ca.startsWith('-----BEGIN CERTIFICATE-----'));
  const buf = await ttdPdf(rina);
  const { raw, br, data, der } = bagian(buf);
  check('PDF bertanda tangan PKCS#7 terlepas', /\/Type \/Sig \/Filter \/Adobe\.PPKLite \/SubFilter \/adbe\.pkcs7\.detached/.test(raw) && /\/AcroForm << \/Fields \[\d+ 0 R\] \/SigFlags 3 >>/.test(raw));
  check('area tanda tangan di halaman 1', /\/Subtype \/Widget \/FT \/Sig .*\/Rect \[/.test(raw) && /\/Annots \[\d+ 0 R\]/.test(raw));
  check('ByteRange mencakup seluruh berkas kecuali isi tanda tangan', br[0] === 0 && br[2] + br[3] === buf.length && raw[br[1]] === '<' && raw[br[2] - 1] === '>');
  check('tanda tangan sah terhadap CA PortalLSP', verifikasi(data, der, ca));
  const ubah = Buffer.from(data); ubah[Math.floor(ubah.length / 2)] ^= 1;
  check('PDF yang diubah 1 byte terdeteksi (tanda tangan tidak sah)', !verifikasi(ubah, der, ca));
  const certs = spawnSync('openssl', ['pkcs7', '-inform', 'DER', '-print_certs', '-noout', '-in', path.join(tmp, 'sig.der')]).stdout.toString();
  check('penanda tangan = LSP penerbit, diterbitkan CA PortalLSP', /subject=.*CN ?= ?LSP Teknologi Digital Nusantara/.test(certs) && /issuer=.*CN ?= ?PortalLSP Root CA/.test(certs), certs.slice(0, 300));
  let isi = '';
  for (const m of raw.matchAll(/stream\n([\s\S]*?)\nendstream/g)) if (m[1]) isi += zlib.inflateSync(Buffer.from(m[1], 'latin1')).toString('latin1');
  check('kotak tanda tangan tampil di halaman', isi.includes('(DITANDATANGANI SECARA DIGITAL OLEH)') && isi.includes('Sertifikat: PortalLSP Root CA'));
  const lagi = bagian(await ttdPdf(await login('admin.tdn@demo.portallsp.id')));
  const fp = d => { fs.writeFileSync(path.join(tmp, 'x.der'), d); return spawnSync('openssl', ['pkcs7', '-inform', 'DER', '-print_certs', '-in', path.join(tmp, 'x.der')]).stdout.toString(); };
  check('unduhan berikutnya memakai sertifikat penanda tangan yang sama', fp(lagi.der) === fp(der));
  const dir = new URL('../storage/tte/', import.meta.url).pathname;
  if (fs.existsSync(dir)) {
    const key = path.join(dir, 'lsp-1.key');
    check('kunci privat terenkripsi dan hanya bisa dibaca pemilik (0600)', fs.readFileSync(key, 'utf8').startsWith('-----BEGIN ENCRYPTED PRIVATE KEY-----')
      && (fs.statSync(key).mode & 0o777) === 0o600 && (fs.statSync(dir).mode & 0o777) === 0o700);
  }
}
fs.rmSync(tmp, { recursive: true, force: true });

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
