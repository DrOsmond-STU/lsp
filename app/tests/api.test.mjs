// Uji integrasi API: autentikasi, CSRF, RBAC, dan isolasi data antar-LSP.
// Jalankan: php -S 127.0.0.1:8099 -t public  lalu  node tests/api.test.mjs
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const PW = 'Demo-Pass-2026';
let pass = 0, failN = 0;
function check(name, cond, extra='') { if (cond) { pass++; console.log('  ok  ', name); } else { failN++; console.log('  FAIL', name, extra); } }

class Client {
  constructor() { this.cookie = ''; this.csrf = ''; }
  async req(route, data, { csrf = true, origin } = {}) {
    const headers = { Accept: 'application/json' };
    if (this.cookie) headers.Cookie = this.cookie;
    let body;
    if (data !== undefined) { headers['Content-Type'] = 'application/json'; if (csrf) headers['X-CSRF-Token'] = this.csrf; body = JSON.stringify(data); }
    if (origin) headers.Origin = origin;
    const res = await fetch(`${BASE}/api.php?r=${encodeURIComponent(route)}`, { method: data === undefined ? 'GET' : 'POST', headers, body });
    const sc = res.headers.get('set-cookie');
    if (sc) { const m = sc.match(/lsp_sid=([^;]+)/); if (m) this.cookie = 'lsp_sid=' + m[1]; }
    let json = {}; try { json = await res.json(); } catch {}
    if (json.csrf) this.csrf = json.csrf;
    return { status: res.status, json, setCookie: sc };
  }
  async login(email, password = PW) { await this.req('auth/me'); return this.req('auth/login', { email, password }); }
}
const E = n => `${n}@demo.portallsp.id`;

console.log('Autentikasi');
{
  const c = new Client();
  const me = await c.req('auth/me');
  check('tamu: user null, dapat token CSRF', me.status === 200 && me.json.user === null && /^[0-9a-f]{64}$/.test(me.json.csrf));
  const noCsrf = await c.req('auth/login', { email: E('admin.tdn'), password: PW }, { csrf: false });
  check('login tanpa token CSRF ditolak (419)', noCsrf.status === 419);
  const badOrigin = await c.req('auth/login', { email: E('admin.tdn'), password: PW }, { origin: 'https://evil.example' });
  check('login dari Origin lain ditolak (403)', badOrigin.status === 403);
  const wrong = await c.req('auth/login', { email: E('admin.tdn'), password: 'salah-password-1' });
  check('password salah → 401 pesan umum', wrong.status === 401 && wrong.json.error === 'Email atau password salah.');
  const unknown = await c.req('auth/login', { email: 'tidakada@demo.portallsp.id', password: 'apaSaja123' });
  check('email tak terdaftar → pesan sama (tidak bocor)', unknown.status === 401 && unknown.json.error === wrong.json.error);
  const before = c.cookie;
  const ok = await c.req('auth/login', { email: E('admin.tdn'), password: PW });
  check('login benar → 200 + peran admin_lsp', ok.status === 200 && ok.json.active.role === 'admin_lsp');
  check('ID sesi diganti setelah login (anti session fixation)', c.cookie && c.cookie !== before);
  check('cookie HttpOnly + SameSite', /HttpOnly/i.test(ok.setCookie || '') && /SameSite=Lax/i.test(ok.setCookie || ''));
  check('hak akses berasal dari server', ok.json.permissions.includes('user.manage') && !ok.json.permissions.includes('listing.review'));
  const out = await c.req('auth/logout', {});
  check('logout → user null', out.status === 200 && out.json.user === null);
  const after = await c.req('listings');
  check('setelah logout, API terlindungi → 401', after.status === 401);
}

console.log('Penguncian login');
{
  const c = new Client(); await c.req('auth/me');
  let last;
  for (let i = 0; i < 6; i++) last = await c.req('auth/login', { email: E('keuangan.tdn'), password: 'salah-' + i + 'xyz' });
  check('5 gagal → dikunci (429)', last.status === 429);
  const right = await c.req('auth/login', { email: E('keuangan.tdn'), password: PW });
  check('password benar pun ditolak selama terkunci', right.status === 429);
}

console.log('RBAC & isolasi LSP');
const tdn = new Client(); await tdn.login(E('admin.tdn'));
const pbi = new Client(); await pbi.login(E('admin.pbi'));
const mkt = new Client(); await mkt.login(E('marketing.tdn'));
const sup = new Client(); await sup.login(E('superadmin'));
const asesi = new Client(); await asesi.login(E('asesi'));
const asesor = new Client(); await asesor.login(E('asesor'));
const tuk = new Client(); await tuk.login(E('tuk.kuningan'));
{
  const a = await tdn.req('listings'); const b = await pbi.req('listings');
  const ta = a.json.items.map(i => i.lsp_nama), tb = b.json.items.map(i => i.lsp_nama);
  check('Admin LSP A hanya melihat listing LSP A', a.status === 200 && ta.length > 0 && ta.every(n => n === 'LSP Teknologi Digital Nusantara'));
  check('Admin LSP B hanya melihat listing LSP B', b.status === 200 && tb.length > 0 && tb.every(n => n === 'LSP Pariwisata Bahari Indonesia'));
  const pbiId = b.json.items.find(i => i.status === 'menunggu').id;
  const steal = await tdn.req('listings/withdraw', { id: pbiId });
  check('LSP A tidak bisa mengubah listing LSP B (404)', steal.status === 404);
  const m = await mkt.req('listings');
  check('Marketing boleh kelola etalase', m.status === 200);
  const mu = await mkt.req('users');
  check('Marketing tidak boleh kelola pengguna (403)', mu.status === 403);
  const sa = await asesi.req('listings');
  check('Asesi tidak boleh akses etalase LSP (403)', sa.status === 403);
  const tr = await tdn.req('reviews');
  check('Admin LSP tidak boleh menyetujui listing (403)', tr.status === 403);
  const tdec = await tdn.req('reviews/decide', { id: pbiId, decision: 'tayang', note: '' });
  check('Admin LSP tidak bisa memaksa approve lewat API (403)', tdec.status === 403);
  const tk = await tuk.req('listings');
  check('Admin TUK tidak boleh akses etalase (403)', tk.status === 403);
  const sr = await sup.req('rbac');
  check('Admin Platform melihat semua peran termasuk platform', sr.status === 200 && sr.json.roles.some(r => r.code === 'platform_admin'));
  const tr2 = await tdn.req('rbac');
  check('Admin LSP tidak melihat peran platform', tr2.status === 200 && !tr2.json.roles.some(r => r.code === 'platform_admin'));
  const ar = await asesor.req('auth/me');
  check('Asesor punya 3 keanggotaan LSP', ar.json.memberships.length >= 3 && ar.json.active.role === 'asesor');
  const sw = await asesor.req('auth/switch', { membership_id: 1 });
  check('Asesor tidak bisa beralih ke keanggotaan orang lain (404)', sw.status === 404);
}

console.log('Alur listing & persetujuan');
{
  const draftBad = await mkt.req('listings', { tipe: 'pelatihan', judul: 'Kelas Uji Coba Pelatihan', bidang: 'TIK', kota: 'Jakarta', format: 'Online', harga: 100000, deskripsi: 'Kelas persiapan untuk uji kompetensi data.', status: 'menunggu', ack: false });
  check('Pelatihan tanpa pernyataan ditolak (422)', draftBad.status === 422);
  const xss = '<img src=x onerror=alert(1)> Skema Uji Keamanan';
  const created = await mkt.req('listings', { tipe: 'skema', judul: xss, bidang: 'TIK', kota: 'Jakarta', format: 'SJJ', harga: 500000, deskripsi: 'Deskripsi skema uji untuk pengujian otomatis.', status: 'menunggu' });
  check('Marketing mengajukan listing (201)', created.status === 201);
  const id = created.json.id;
  const cat0 = await new Client().req('catalog');
  check('Belum disetujui → tidak tampil di katalog publik', !cat0.json.items.some(i => i.id === id));
  const q = await sup.req('reviews');
  check('Masuk antrean Admin Platform', q.json.items.some(i => i.id === id));
  const noNote = await sup.req('reviews/decide', { id, decision: 'revisi', note: '' });
  check('Revisi tanpa catatan ditolak (422)', noNote.status === 422);
  const okDec = await sup.req('reviews/decide', { id, decision: 'tayang', note: '' });
  check('Admin Platform menyetujui (200)', okDec.status === 200);
  const twice = await sup.req('reviews/decide', { id, decision: 'ditolak', note: 'coba ulang keputusan' });
  check('Keputusan ganda ditolak (409)', twice.status === 409);
  const cat1 = await new Client().req('catalog');
  const item = cat1.json.items.find(i => i.id === id);
  check('Setelah disetujui → tampil di katalog publik', !!item);
  check('Katalog publik tidak memuat catatan/status internal', item && !('catatan' in item) && !('status' in item));
  check('Teks berbahaya disimpan apa adanya (di-escape di klien)', item && item.judul === xss);
}

console.log('Manajemen pengguna & ganti password wajib');
{
  const weak = await tdn.req('users', { nama: 'Staf Baru', email: 'staf.baru@demo.portallsp.id', role: 'keuangan', password: 'pendek1' });
  check('Password lemah ditolak (422)', weak.status === 422);
  const plat = await tdn.req('users', { nama: 'Penyusup', email: 'penyusup@demo.portallsp.id', role: 'platform_admin', password: 'Rahasia-Kuat-123' });
  check('Admin LSP tidak bisa memberi peran platform (403)', plat.status === 403);
  const dup = await tdn.req('users', { nama: 'Dobel', email: E('admin.pbi'), role: 'keuangan', password: 'Rahasia-Kuat-123' });
  check('Email yang sudah terdaftar ditolak (409)', dup.status === 409);
  const ok = await tdn.req('users', { nama: 'Staf Baru', email: 'staf.baru@demo.portallsp.id', role: 'keuangan', password: 'Awal-Sementara-77' });
  check('Admin LSP menambah pengguna (201)', ok.status === 201);
  const pbiUsers = await pbi.req('users');
  check('Pengguna baru tidak terlihat oleh LSP lain', !pbiUsers.json.items.some(u => u.email === 'staf.baru@demo.portallsp.id'));
  const nu = new Client(); const lg = await nu.login('staf.baru@demo.portallsp.id', 'Awal-Sementara-77');
  check('Pengguna baru wajib ganti password', lg.status === 200 && lg.json.user.must_change_password === true);
  const blocked = await nu.req('users');
  check('Sebelum ganti password, API lain diblokir (403)', blocked.status === 403);
  const cp = await nu.req('auth/password', { current: 'Awal-Sementara-77', new: 'Pribadi-Baru-2026' });
  check('Ganti password berhasil', cp.status === 200 && cp.json.user.must_change_password === false);
  const me2 = await nu.req('auth/me');
  check('Setelah ganti password, akses normal', me2.json.user.must_change_password === false && me2.json.active.role === 'keuangan');
  const tdnUsers = await tdn.req('users');
  const target = tdnUsers.json.items.find(u => u.email === 'staf.baru@demo.portallsp.id');
  const pbiDeact = await pbi.req('users/status', { membership_id: target.membership_id, status: 'nonaktif' });
  check('LSP lain tidak bisa menonaktifkan pengguna LSP A (404)', pbiDeact.status === 404);
  const self = tdnUsers.json.items.find(u => u.is_me);
  const selfDeact = await tdn.req('users/status', { membership_id: self.membership_id, status: 'nonaktif' });
  check('Admin tidak bisa menonaktifkan dirinya sendiri (409)', selfDeact.status === 409);
  const deact = await tdn.req('users/status', { membership_id: target.membership_id, status: 'nonaktif' });
  check('Admin LSP menonaktifkan pengguna', deact.status === 200);
  const after = await nu.req('auth/me');
  check('Pengguna nonaktif langsung kehilangan akses', after.json.user === null || after.json.active === null || after.status !== 200 || (await nu.req('listings')).status !== 200);
}

console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
