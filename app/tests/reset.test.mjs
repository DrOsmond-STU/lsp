// Uji lupa password: tautan lewat email, satu kali pakai, tidak membocorkan email terdaftar, sesi lama keluar, kunci login dibuka.
import { readFileSync, existsSync } from 'node:fs';
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const MAIL = process.env.MAIL_LOG || new URL('../storage/mail.log', import.meta.url).pathname;
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, typeof x === 'string' ? x : JSON.stringify(x)); } };
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
const fresh = async () => { const c = new Client(); await c.req('auth/me'); return c; };
const mailLog = () => (existsSync(MAIL) ? readFileSync(MAIL, 'utf8') : '');
const tokensFor = email => [...mailLog().split('----').filter(b => b.includes('To: ' + email) && b.includes('Atur ulang password')).join('\n').matchAll(/\?reset=([0-9a-f]{64})/g)].map(m => m[1]);
const uniq = Date.now().toString().slice(-6);
// Dua akun asesi baru, supaya password akun demo yang dipakai suite lain tidak berubah.
async function daftar(email, nikAkhir) {
  const c = await fresh();
  const r = await c.req('auth/register', { nama: 'Penguji Reset', nik: '31740552089' + nikAkhir, tanggal_lahir: '1999-08-12', jenis_kelamin: 'P',
    email, no_hp: '081234567' + nikAkhir.slice(-3), password: 'Awal-Pass-2026', consent_privacy: true, consent_marketing: false, website: '' });
  if (r.status !== 201) throw new Error('daftar ' + email + ' ' + JSON.stringify(r.json));
  return c;
}
const A = `reset.a${uniq}@contoh.id`, B = `reset.b${uniq}@contoh.id`;
const sesiA = await daftar(A, '9' + uniq.slice(-4));
await daftar(B, '8' + uniq.slice(-4));

console.log('Permintaan tautan');
const MSG = 'Bila email itu terdaftar';
{
  const c = await fresh();
  check('tanpa CSRF ditolak (419)', (await c.req('auth/forgot', { email: A }, { csrf: false })).status === 419);
  check('format email salah ditolak (422)', (await c.req('auth/forgot', { email: 'bukan-email' })).status === 422);
  const before = mailLog().length;
  const r0 = await c.req('auth/forgot', { email: `tidak.ada${uniq}@contoh.id` });
  check('email tidak terdaftar: jawaban umum 200', r0.status === 200 && r0.json.message?.includes(MSG), r0.json);
  check('email tidak terdaftar: tidak ada email terkirim', mailLog().length === before);
  const r1 = await c.req('auth/forgot', { email: A.toUpperCase() });
  check('email terdaftar: jawaban sama persis', r1.status === 200 && r1.json.message === r0.json.message, r1.json);
  check('tautan terkirim ke email akun', tokensFor(A).length === 1);
  check('tautan memakai app_url dari config', mailLog().includes(BASE + '/?reset='));
  await c.req('auth/forgot', { email: A });
  check('permintaan beruntun tidak mengirim email kedua', tokensFor(A).length === 1);
}

console.log('Cek dan pakai tautan');
{
  const tok = tokensFor(A)[0];
  const c = await fresh();
  check('token salah format ditolak', (await c.req('auth/reset-check', { token: 'abc' })).status === 422);
  check('token tidak dikenal ditolak', (await c.req('auth/reset-check', { token: 'f'.repeat(64) })).status === 422);
  const ck = await c.req('auth/reset-check', { token: tok });
  check('token sah: email disamarkan', ck.status === 200 && ck.json.email && !ck.json.email.includes(A.split('@')[0]) && ck.json.email.endsWith('@contoh.id'), ck.json);
  check('password lemah ditolak', (await c.req('auth/reset', { token: tok, password: 'pendek' })).status === 422);
  check('password memuat nama email ditolak', (await c.req('auth/reset', { token: tok, password: A.split('@')[0] + '123' })).status === 422);
  check('tautan masih berlaku setelah percobaan gagal', (await c.req('auth/reset-check', { token: tok })).status === 200);
  check('sesi lama masih masuk sebelum reset', (await sesiA.req('auth/me')).json.user?.email === A);
  const r = await c.req('auth/reset', { token: tok, password: 'Baru-Pass-2026' });
  check('password baru disimpan', r.status === 200 && r.json.ok, r.json);
  check('tautan tidak bisa dipakai lagi', (await c.req('auth/reset', { token: tok, password: 'Lain-Pass-2026' })).status === 422);
  check('sesi lama otomatis keluar', !(await sesiA.req('auth/me')).json.user);
  const l1 = await fresh();
  check('password lama tidak berlaku', (await l1.req('auth/login', { email: A, password: 'Awal-Pass-2026' })).status === 401);
  const l2 = await fresh();
  check('masuk dengan password baru', (await l2.req('auth/login', { email: A, password: 'Baru-Pass-2026' })).status === 200);
}

console.log('Kunci login dibuka setelah reset');
{
  for (let i = 0; i < 5; i++) { const c = await fresh(); await c.req('auth/login', { email: B, password: 'Salah-Pass-' + i }); }
  const c = await fresh();
  check('akun terkunci setelah 5 kali salah', (await c.req('auth/login', { email: B, password: 'Awal-Pass-2026' })).status === 429);
  await (await fresh()).req('auth/forgot', { email: B });
  const tok = tokensFor(B)[0];
  check('tautan untuk akun terkunci terkirim', !!tok);
  check('reset berhasil', (await (await fresh()).req('auth/reset', { token: tok, password: 'Pulih-Pass-2026' })).status === 200);
  check('langsung bisa masuk setelah reset', (await (await fresh()).req('auth/login', { email: B, password: 'Pulih-Pass-2026' })).status === 200);
}

console.log('Pembatasan per jaringan');
{
  let kode = 0;
  for (let i = 0; i < 12; i++) { kode = (await (await fresh()).req('auth/forgot', { email: `banjir${i}.${uniq}@contoh.id` })).status; if (kode === 429) break; }
  check('permintaan berlebihan dari satu IP dibatasi (429)', kode === 429);
}

console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
