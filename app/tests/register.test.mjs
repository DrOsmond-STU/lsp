// Uji pendaftaran asesi mandiri: validasi, duplikasi, enkripsi NIK, verifikasi email, privasi, pembatasan.
import { readFileSync, existsSync } from 'node:fs';
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const MAIL = process.env.MAIL_LOG || new URL('../storage/mail.log', import.meta.url).pathname;
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, x); } };
class Client {
  constructor() { this.cookie = ''; this.csrf = ''; }
  async req(route, data, { csrf = true } = {}) {
    const h = { Accept: 'application/json' }; if (this.cookie) h.Cookie = this.cookie;
    let body; if (data !== undefined) { h['Content-Type'] = 'application/json'; if (csrf) h['X-CSRF-Token'] = this.csrf; body = JSON.stringify(data); }
    const r = await fetch(`${BASE}/api.php?r=${encodeURIComponent(route)}`, { method: data === undefined ? 'GET' : 'POST', headers: h, body });
    const sc = r.headers.get('set-cookie'); if (sc) { const m = sc.match(/lsp_sid=([^;]+)/); if (m) this.cookie = 'lsp_sid=' + m[1]; }
    let j = {}; try { j = await r.json(); } catch {} if (j.csrf) this.csrf = j.csrf; return { status: r.status, json: j };
  }
}
const base = (o = {}) => ({ nama: 'Siti Nurhaliza', nik: '3174055208990003', tanggal_lahir: '1999-08-12', jenis_kelamin: 'P',
  email: 'siti.asesi@contoh.id', no_hp: '0812-3456-7890', password: 'Kompeten-2026x', consent_privacy: true, consent_marketing: false, website: '', ...o });
async function fresh() { const c = new Client(); await c.req('auth/me'); return c; }

console.log('Validasi pendaftaran');
{
  const c = await fresh();
  check('tanpa CSRF ditolak (419)', (await c.req('auth/register', base(), { csrf: false })).status === 419);
  check('NIK salah format ditolak (422)', (await c.req('auth/register', base({ nik: '12345' }))).status === 422);
  check('NIK kode provinsi tidak valid ditolak', (await c.req('auth/register', base({ nik: '9974055208990003' }))).status === 422);
  check('umur di bawah 15 tahun ditolak', (await c.req('auth/register', base({ tanggal_lahir: '2020-01-01' }))).status === 422);
  check('nomor HP salah ditolak', (await c.req('auth/register', base({ no_hp: '12345' }))).status === 422);
  check('password lemah ditolak', (await c.req('auth/register', base({ password: 'pendek' }))).status === 422);
  check('tanpa persetujuan privasi ditolak', (await c.req('auth/register', base({ consent_privacy: false }))).status === 422);
  check('honeypot terisi (bot) ditolak', (await c.req('auth/register', base({ website: 'http://spam' }))).status === 400);
  check('email demo yang sudah ada ditolak (409)', (await c.req('auth/register', base({ email: 'asesi@demo.portallsp.id' }))).status === 409);
}

console.log('Pendaftaran berhasil');
const asesi = await fresh();
{
  const r = await asesi.req('auth/register', base());
  check('daftar → 201 dan langsung masuk', r.status === 201 && r.json.user && r.json.user.email === 'siti.asesi@contoh.id');
  check('peran asesi, belum terikat LSP', r.json.active.role === 'asesi' && r.json.active.lsp_id === null);
  check('email belum terverifikasi', r.json.user.email_verified === false);
  check('hak akses hanya milik asesi', r.json.permissions.includes('profile.own') && !r.json.permissions.includes('listing.manage'));
  const prof = await asesi.req('profile');
  check('profil sendiri: NIK disamarkan', prof.status === 200 && prof.json.nik === '3174••••••••0003');
  check('nomor HP dinormalisasi', prof.json.no_hp === '081234567890');
  check('asesi tidak bisa akses etalase/pengguna', (await asesi.req('listings')).status === 403 && (await asesi.req('users')).status === 403);
  check('daftar lagi saat sudah masuk ditolak (409)', (await asesi.req('auth/register', base({ email: 'lain@contoh.id', nik: '3174055208990004' }))).status === 409);
  const dupNik = await (await fresh()).req('auth/register', base({ email: 'beda@contoh.id' }));
  check('NIK sama di akun lain ditolak (409)', dupNik.status === 409 && /NIK/.test(dupNik.json.error));
  const dupEmail = await (await fresh()).req('auth/register', base({ nik: '3174055208990009' }));
  check('email sama ditolak (409)', dupEmail.status === 409);
}

console.log('Privasi antar-LSP');
{
  const tdn = await fresh(); await tdn.req('auth/login', { email: 'admin.tdn@demo.portallsp.id', password: 'Demo-Pass-2026' });
  const u = await tdn.req('users');
  check('asesi baru tidak terlihat oleh LSP mana pun', u.status === 200 && !u.json.items.some(x => x.email === 'siti.asesi@contoh.id'));
}

console.log('Verifikasi email');
{
  check('email verifikasi tercatat', existsSync(MAIL));
  const log = readFileSync(MAIL, 'utf8');
  const m = [...log.matchAll(/To: siti\.asesi@contoh\.id[\s\S]*?\?verifikasi=([0-9a-f]{64})/g)].pop();
  check('tautan memakai app_url dari config', /http:\/\/127\.0\.0\.1:8099\/\?verifikasi=/.test(log));
  const anon = await fresh();
  check('token palsu ditolak', (await anon.req('auth/verify', { token: 'a'.repeat(64) })).status === 422);
  const v = await anon.req('auth/verify', { token: m && m[1] });
  check('token benar → terverifikasi', v.status === 200);
  check('token tidak bisa dipakai dua kali', (await anon.req('auth/verify', { token: m && m[1] })).status === 422);
  const me = await asesi.req('auth/me');
  check('status akun berubah menjadi terverifikasi', me.json.user.email_verified === true);
  check('kirim ulang saat sudah terverifikasi ditolak', (await asesi.req('auth/resend-verification', {})).status === 409);
}

console.log('Masuk ulang & pembatasan');
{
  await asesi.req('auth/logout', {});
  const again = await fresh();
  const lg = await again.req('auth/login', { email: 'siti.asesi@contoh.id', password: 'Kompeten-2026x' });
  check('bisa login dengan akun baru', lg.status === 200 && lg.json.active.role === 'asesi');
  let last;
  for (let i = 0; i < 6; i++) {
    last = await (await fresh()).req('auth/register', base({ email: `bulk${i}@contoh.id`, nik: `31740552089900${10 + i}` }));
    if (last.status === 429) break;
  }
  check('pendaftaran massal dari satu IP dibatasi (429)', last.status === 429);
}
console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
