// Matriks RBAC: setiap endpoint dipanggil oleh setiap peran (dan pengunjung anonim) lewat GET dan POST.
// Peran yang TIDAK memegang hak akses endpoint wajib ditolak (401/403/405); peran yang memegang tidak boleh mendapat 401/403.
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const PW = 'Demo-Pass-2026';
let pass = 0, failN = 0; const fails = [];
const check = (n, c, x = '') => { if (c) pass++; else { failN++; fails.push(n + (x ? ' — ' + x : '')); } };
class Client {
  constructor() { this.cookie = ''; this.csrf = ''; }
  async req(route, data) {
    const h = { Accept: 'application/json' }; if (this.cookie) h.Cookie = this.cookie;
    let body; if (data !== undefined) { h['Content-Type'] = 'application/json'; h['X-CSRF-Token'] = this.csrf; body = JSON.stringify(data); }
    const res = await fetch(`${BASE}/api.php?r=${encodeURIComponent(route)}`, { method: data === undefined ? 'GET' : 'POST', headers: h, body, redirect: 'manual' });
    const sc = res.headers.get('set-cookie'); if (sc) { const m = sc.match(/lsp_sid=([^;]+)/); if (m) this.cookie = 'lsp_sid=' + m[1]; }
    let j = {}; try { j = await res.json(); } catch {} if (j.csrf) this.csrf = j.csrf; return { status: res.status, json: j };
  }
}
// Hak akses yang dibutuhkan tiap endpoint (salah satu cukup). 'auth' = cukup login; 'public' = tanpa login.
const NEED = {
  'auth/switch': 'auth', 'auth/password': 'auth', 'auth/resend-verification': 'auth',
  'catalog': 'public', 'listings': ['listing.manage'], 'listings/submit': ['listing.manage'], 'listings/withdraw': ['listing.manage'],
  'reviews': ['listing.review'], 'reviews/decide': ['listing.review'], 'users': ['user.manage'], 'users/status': ['user.manage'], 'rbac': ['rbac.view'],
  'profile': ['profile.own'], 'notifications': 'auth', 'notifications/count': 'auth', 'notifications/read': 'auth', 'notifications/settings': 'auth',
  'notifications/test': 'auth', 'notifications/log': ['notif.log'], 'ai/chat': ['ai.use'], 'lsps': ['lsp.manage'],
  'dokumen': 'auth', 'dokumen/upload': ['profile.own'], 'dokumen/file': 'auth',
  'asesi/apply': ['application.own'], 'asesi/permohonan': ['application.own'], 'asesi/resubmit': ['application.own'], 'asesi/cancel': ['application.own'],
  'asesi/tagihan': ['payment.own'], 'asesi/bayar': ['payment.own'], 'asesi/sertifikat': ['certificate.own'], 'asesi/kelas': ['class.own'],
  'asesi/kelas/daftar': ['class.own'], 'asesi/kelas/progres': ['class.own'], 'sertifikat/cetak': 'auth',
  'asesor/jadwal': ['asesor.dashboard'], 'asesor/pra': ['preassessment.review'], 'asesor/pra/putus': ['preassessment.review'],
  'asesor/asesmen': ['assessment.conduct'], 'asesor/asesmen/simpan': ['assessment.conduct'], 'asesor/riwayat': ['asesor.history'], 'asesor/honor': ['asesor.honor'],
  'pleno': ['decision.manage', 'pleno.participate'], 'pleno/putus': ['decision.manage', 'pleno.participate'],
  'chat': 'auth', 'chat/kirim': 'auth', 'chat/ruang': 'auth', // isi dibatasi: asesor/asesi hanya ruang jadwalnya, staf hanya LSP-nya
  'lsp/dashboard': ['lsp.dashboard'], 'lsp/pendaftaran': ['registration.verify'], 'lsp/pendaftaran/putus': ['registration.verify'],
  'lsp/asesmen': ['assessment.monitor'], 'lsp/hasil': ['decision.manage'], 'lsp/jadwal': ['schedule.manage'], 'lsp/jadwal/asesor': ['schedule.manage'],
  'lsp/asesor': ['master.manage'], 'lsp/skema': ['master.manage'], 'lsp/tuk': ['master.manage'], 'lsp/opsi': ['schedule.manage', 'listing.manage', 'master.manage'],
  'lsp/alumni': ['alumni.view'], 'lsp/keuangan': ['finance.manage'], 'lsp/keuangan/lunas': ['finance.manage'], 'lsp/laporan': ['report.bnsp'],
  'lsp/crm': ['crm.manage'], 'lsp/crm/tahap': ['crm.manage'], 'lsp/mutu': ['quality.manage'], 'lsp/pengaturan': ['settings.manage'],
  'tuk/dashboard': ['tuk.dashboard'], 'tuk/pemohon': ['tuk.applicants'], 'tuk/jadwal': ['tuk.schedule'], 'tuk/sarpras': ['tuk.facility'], 'tuk/alumni': ['tuk.alumni'],
  'platform/dashboard': ['platform.dashboard'], 'platform/lsp': ['lsp.manage'], 'platform/lsp/status': ['lsp.manage'], 'platform/paket': ['lsp.manage'],
  'skkni': ['lsp.manage', 'master.manage'], 'tiket': ['settings.manage', 'lsp.manage'], 'tiket/balas': ['settings.manage', 'lsp.manage'], 'audit': ['lsp.manage'],
  'listings/hapus': ['listing.manage'], 'listings/turunkan': ['listing.manage'], 'lsp/mutu/hapus': ['quality.manage'], 'lsp/crm/hapus': ['crm.manage'],
  'lsp/skema/hapus': ['master.manage'], 'lsp/tuk/hapus': ['master.manage'], 'lsp/jadwal/hapus': ['schedule.manage'], 'tuk/sarpras/hapus': ['tuk.facility'],
  'skkni/hapus': ['lsp.manage'],
  'blog': ['blog.manage'], 'blog/hapus': ['blog.manage'], 'blog/moderasi': ['lsp.manage'], 'blog/cover': ['blog.manage'],
  'pub/catalog': 'public', 'pub/skema': 'public', 'pub/jadwal': 'public', 'pub/lsp': 'public', 'pub/verify': 'public',
  'pub/blog': 'public', 'pub/blog/post': 'public', 'pub/blog/cover': 'public',
};
const ROLES = ['superadmin', 'admin.tdn', 'marketing.tdn', 'keuangan.tdn', 'admin.pbi', 'tuk.kuningan', 'asesor', 'asesi'];
const DENY = new Set([401, 403, 404, 405]); // 404: data di luar cakupan diperlakukan seperti tidak ada
const login = async who => { const c = new Client(); await c.req('auth/me'); const r = await c.req('auth/login', { email: `${who}@demo.portallsp.id`, password: PW }); if (r.status !== 200) throw new Error(who + ' login ' + r.status); const me = await c.req('auth/me'); c.perms = me.json.permissions || []; return c; };

// Semua rute di dispatch() harus terdaftar di NEED (rute baru tanpa aturan = gagal).
import { readFileSync } from 'node:fs';
const routesSrc = readFileSync(new URL('../src/routes.php', import.meta.url), 'utf8');
const all = [...routesSrc.matchAll(/'([a-z0-9/_-]+)'\s*=>\s*'(?:r|do)_[a-z0-9_]+'/g)].map(m => m[1]).filter(r => !['auth/me', 'auth/login', 'auth/logout', 'auth/register', 'auth/verify'].includes(r));
for (const r of all) check(`aturan akses terdaftar untuk ${r}`, r in NEED);

const clients = { anonim: await (async () => { const c = new Client(); await c.req('auth/me'); c.perms = []; c.anon = true; return c; })() };
for (const who of ROLES) clients[who] = await login(who);
for (const [who, c] of Object.entries(clients)) {
  for (const route of all) {
    const need = NEED[route]; if (!need) continue;
    const g = await c.req(route), p = await c.req(route, {});
    const allowed = need === 'public' || (need === 'auth' ? !c.anon : need.some(x => c.perms.includes(x)));
    if (need === 'public') { check(`${who} ${route} publik`, g.status !== 401 && g.status !== 403, `GET ${g.status}`); continue; }
    if (allowed) check(`${who} boleh ${route}`, !(g.status === 401 || g.status === 403) || !(p.status === 401 || p.status === 403), `GET ${g.status} POST ${p.status}`);
    else check(`${who} DITOLAK ${route}`, DENY.has(g.status) && DENY.has(p.status), `GET ${g.status} POST ${p.status}`);
    if (c.anon) check(`anonim ${route} → 401`, [401, 405].includes(g.status) && [401, 405].includes(p.status), `GET ${g.status} POST ${p.status}`);
  }
}
// Tidak ada respons API yang membocorkan hash password atau token.
for (const [who, c] of Object.entries(clients)) {
  for (const route of ['users', 'lsp/asesor', 'lsp/pendaftaran', 'lsp/alumni', 'tuk/pemohon', 'pleno', 'asesor/pra', 'audit', 'notifications/log', 'blog', 'chat/ruang']) {
    const r = await c.req(route); const t = JSON.stringify(r.json);
    check(`${who} ${route} tanpa hash/token`, !/password_hash|\$2y\$|nik_enc|nik_hash|"token"|csrf_secret/.test(t));
  }
}
console.log(`${pass} lulus, ${failN} gagal`);
for (const f of fails.slice(0, 60)) console.log('  FAIL', f);
process.exit(failN ? 1 : 0);
