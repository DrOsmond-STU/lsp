// Uji akses penuh Admin Platform ke semua LSP, sementara peran LSP tetap terisolasi.
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const PW = 'Demo-Pass-2026';
const E = n => `${n}@demo.portallsp.id`;
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, x); } };
class Client {
  constructor() { this.cookie = ''; this.csrf = ''; }
  async req(route, data, { csrf = true } = {}) {
    const h = { Accept: 'application/json' }; if (this.cookie) h.Cookie = this.cookie;
    let body; if (data !== undefined) { h['Content-Type'] = 'application/json'; if (csrf) h['X-CSRF-Token'] = this.csrf; body = JSON.stringify(data); }
    const [r, qs] = route.split('?');
    const res = await fetch(`${BASE}/api.php?r=${encodeURIComponent(r)}${qs ? '&' + qs : ''}`, { method: data === undefined ? 'GET' : 'POST', headers: h, body });
    const sc = res.headers.get('set-cookie'); if (sc) { const m = sc.match(/lsp_sid=([^;]+)/); if (m) this.cookie = 'lsp_sid=' + m[1]; }
    let j = {}; try { j = await res.json(); } catch {} if (j.csrf) this.csrf = j.csrf; return { status: res.status, json: j };
  }
}
const as = async who => { const c = new Client(); await c.req('auth/me'); const r = await c.req('auth/login', { email: E(who), password: PW }); if (r.status !== 200) throw new Error(who + ' ' + r.status); return c; };
const sup = await as('superadmin'), tdn = await as('admin.tdn'), pbi = await as('admin.pbi');

console.log('Hak akses Admin Platform');
{
  const me = await sup.req('auth/me');
  const perms = me.json.permissions;
  check('memegang semua hak akses', ['listing.manage', 'user.manage', 'finance.manage', 'crm.manage', 'tuk.dashboard', 'schedule.manage', 'lsp.manage', 'listing.review'].every(p => perms.includes(p)) && perms.length >= 38);
  const r = await sup.req('rbac');
  const pa = r.json.roles.find(x => x.code === 'platform_admin');
  check('matriks RBAC menunjukkan semua hak akses', pa && pa.permissions.length === r.json.permissions.length);
}

console.log('Melihat semua LSP tanpa filter');
let tdnListing, pbiListing;
{
  const l = await sup.req('listings');
  const lsps = new Set(l.json.items.map(i => i.lsp_nama));
  check('etalase: listing dari semua LSP', l.status === 200 && lsps.has('LSP Teknologi Digital Nusantara') && lsps.has('LSP Pariwisata Bahari Indonesia'));
  tdnListing = l.json.items.find(i => i.lsp_nama === 'LSP Teknologi Digital Nusantara' && i.status === 'draf');
  pbiListing = l.json.items.find(i => i.lsp_nama === 'LSP Pariwisata Bahari Indonesia' && i.status === 'menunggu');
  const f = await sup.req('listings?lsp=2');
  check('filter ?lsp=2 hanya LSP itu', f.status === 200 && f.json.items.length > 0 && f.json.items.every(i => i.lsp_nama === 'LSP Pariwisata Bahari Indonesia'));
  const u = await sup.req('users');
  const names = new Set(u.json.items.map(i => i.lsp_nama));
  check('pengguna: semua LSP + akun platform/pribadi', u.status === 200 && names.has('LSP Teknologi Digital Nusantara') && names.has('LSP Pariwisata Bahari Indonesia') && u.json.items.some(i => i.role === 'platform_admin'));
  const ls = await sup.req('lsps');
  check('daftar LSP dengan ringkasan angka', ls.status === 200 && ls.json.items.length >= 4 && ls.json.items.every(i => typeof i.pengguna === 'number'));
  check('log notifikasi bisa disaring per LSP', (await sup.req('notifications/log?lsp=1')).status === 200);
}

console.log('Bertindak atas nama LSP mana pun');
{
  const noLsp = await sup.req('listings', { tipe: 'skema', judul: 'Skema Tanpa LSP', bidang: 'TIK', kota: 'Jakarta', format: 'SJJ', harga: 1000, deskripsi: 'x', status: 'draf' });
  check('membuat listing wajib memilih LSP', noLsp.status === 422);
  const mk = await sup.req('listings', { lsp_id: 2, tipe: 'skema', judul: 'Skema Dibuat Platform', bidang: 'Pariwisata', kota: 'Denpasar', format: 'Tatap muka', harga: 1000, deskripsi: 'Dibuat oleh Admin Platform untuk LSP ini.', status: 'draf' });
  check('membuat listing untuk LSP lain', mk.status === 201);
  check('listing itu muncul di etalase LSP tujuan', (await pbi.req('listings')).json.items.some(i => i.judul === 'Skema Dibuat Platform'));
  check('mengajukan listing LSP mana pun', (await sup.req('listings/submit', { id: tdnListing.id })).status !== 404);
  check('menarik pengajuan LSP mana pun', (await sup.req('listings/withdraw', { id: pbiListing.id })).status === 200);
  const nu = await sup.req('users', { lsp_id: 2, nama: 'Staf Dibuat Platform', email: 'staf.platform@demo.portallsp.id', role: 'keuangan', password: 'Awal-Platform-2026' });
  check('menambah pengguna ke LSP lain', nu.status === 201);
  const pu = await pbi.req('users');
  const m = pu.json.items.find(i => i.email === 'staf.platform@demo.portallsp.id');
  check('pengguna baru terlihat oleh Admin LSP tujuan', !!m);
  check('menonaktifkan pengguna di LSP mana pun', (await sup.req('users/status', { membership_id: m.membership_id, status: 'nonaktif' })).status === 200);
  const supMe = (await sup.req('users')).json.items.find(i => i.is_me);
  check('tidak bisa menonaktifkan diri sendiri', (await sup.req('users/status', { membership_id: supMe.membership_id, status: 'nonaktif' })).status === 409);
}

console.log('Admin LSP tetap terisolasi');
{
  const lspPerms = ['lsp.dashboard', 'registration.verify', 'schedule.manage', 'assessment.monitor', 'decision.manage', 'master.manage', 'alumni.view',
    'listing.manage', 'quality.manage', 'finance.manage', 'crm.manage', 'report.bnsp', 'settings.manage', 'user.manage', 'notif.log',
    'tuk.dashboard', 'tuk.applicants', 'tuk.schedule', 'tuk.facility', 'tuk.chat', 'tuk.alumni'];
  const tp = (await tdn.req('auth/me')).json.permissions;
  check('Admin LSP memegang semua hak akses tingkat LSP (termasuk TUK)', lspPerms.every(p => tp.includes(p)));
  check('Admin LSP tidak memegang hak akses platform', !['listing.review', 'lsp.manage', 'platform.dashboard'].some(p => tp.includes(p)));
  check('Admin LSP tidak bisa daftar LSP (403)', (await tdn.req('lsps')).status === 403);
  const l = await tdn.req('listings?lsp=2');
  check('parameter ?lsp diabaikan untuk Admin LSP', l.status === 200 && l.json.items.every(i => i.lsp_nama === 'LSP Teknologi Digital Nusantara'));
  const u = await tdn.req('users?lsp=2');
  check('pengguna LSP lain tetap tidak terlihat', u.json.items.every(i => i.lsp_nama === 'LSP Teknologi Digital Nusantara'));
  const other = (await sup.req('listings?lsp=2')).json.items[0];
  check('listing LSP lain tetap 404', (await tdn.req('listings/withdraw', { id: other.id })).status === 404);
  const bad = await tdn.req('users', { lsp_id: 2, nama: 'Coba Pindah', email: 'coba.pindah@demo.portallsp.id', role: 'keuangan', password: 'Awal-Pindah-2026' });
  const pbiSees = (await pbi.req('users')).json.items.some(i => i.email === 'coba.pindah@demo.portallsp.id');
  check('lsp_id dari Admin LSP diabaikan (pengguna masuk ke LSP-nya sendiri)', bad.status === 201 && !pbiSees);
}
console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
