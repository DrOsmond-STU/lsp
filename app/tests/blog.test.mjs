// Uji CMS blog: hak akses, isolasi LSP, terbit/terjadwal, moderasi Admin Platform, gambar sampul, dan API publik.
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const PW = 'Demo-Pass-2026';
const E = n => `${n}@demo.portallsp.id`;
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, x); } };
class Client {
  constructor() { this.cookie = ''; this.csrf = ''; }
  grab(res) { const sc = res.headers.get('set-cookie'); if (sc) { const m = sc.match(/lsp_sid=([^;]+)/); if (m) this.cookie = 'lsp_sid=' + m[1]; } }
  async req(route, data, { csrf = true } = {}) {
    const h = { Accept: 'application/json' }; if (this.cookie) h.Cookie = this.cookie;
    let body; if (data !== undefined) { h['Content-Type'] = 'application/json'; if (csrf) h['X-CSRF-Token'] = this.csrf; body = JSON.stringify(data); }
    const [r, qs] = route.split('?');
    const res = await fetch(`${BASE}/api.php?r=${encodeURIComponent(r)}${qs ? '&' + qs : ''}`, { method: data === undefined ? 'GET' : 'POST', headers: h, body });
    this.grab(res);
    let j = {}; try { j = await res.json(); } catch {} if (j.csrf) this.csrf = j.csrf; return { status: res.status, json: j };
  }
  async upload(fields, file) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(fields)) fd.append(k, String(v));
    if (file) fd.append('file', new Blob([file.bytes], { type: file.type }), file.name);
    const res = await fetch(`${BASE}/api.php?r=blog/cover`, { method: 'POST', headers: { Accept: 'application/json', Cookie: this.cookie, 'X-CSRF-Token': this.csrf }, body: fd });
    this.grab(res);
    let j = {}; try { j = await res.json(); } catch {} return { status: res.status, json: j };
  }
  async raw(path) { const res = await fetch(`${BASE}/${path}`, { headers: this.cookie ? { Cookie: this.cookie } : {} }); this.grab(res); return res; }
}
const as = async who => { const c = new Client(); await c.req('auth/me'); const r = await c.req('auth/login', { email: E(who), password: PW }); if (r.status !== 200) throw new Error(who + ' ' + r.status); return c; };
const anon = async () => { const c = new Client(); await c.req('auth/me'); return c; };
const sup = await as('superadmin'), tdn = await as('admin.tdn'), mkt = await as('marketing.tdn'), keu = await as('keuangan.tdn'),
  pbi = await as('admin.pbi'), asesi = await as('asesi'), tuk = await as('tuk.kuningan');
const pub = await anon();
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
const KONTEN = 'Paragraf pertama artikel uji.\n\n## Subjudul\n\n- poin satu\n- poin dua\n\nTeks **tebal** dan <script>alert(1)</script> harus tetap teks biasa.';
const stamp = Date.now().toString(36);

console.log('Hak akses menu Blog');
{
  for (const [n, c] of [['Admin Platform', sup], ['Admin LSP', tdn], ['Marketing', mkt]]) {
    check(`${n} memegang blog.manage`, (await c.req('auth/me')).json.permissions.includes('blog.manage'));
    check(`${n} bisa membuka daftar artikel`, (await c.req('blog')).status === 200);
  }
  for (const [n, c] of [['Keuangan', keu], ['Asesi', asesi], ['Admin TUK', tuk]]) {
    check(`${n} ditolak (403)`, (await c.req('blog')).status === 403);
  }
  check('tanpa login ditolak (401)', (await pub.req('blog')).status === 401);
  check('tanpa token CSRF ditolak (419)', (await mkt.req('blog', { judul: 'x' }, { csrf: false })).status === 419);
}

console.log('Isolasi daftar artikel');
{
  const a = await tdn.req('blog');
  check('Admin LSP TDN hanya melihat artikel TDN', a.json.items.length > 0 && a.json.items.every(p => p.lsp_id === 1));
  check('Admin LSP bukan moderator', a.json.moderator === false);
  const b = await pbi.req('blog');
  check('Admin LSP PBI hanya melihat artikel PBI', b.json.items.length > 0 && b.json.items.every(p => p.lsp_id === 2));
  const s = await sup.req('blog');
  check('Admin Platform melihat artikel platform dan semua LSP', s.json.items.some(p => p.lsp_id === null) && s.json.items.some(p => p.lsp_id === 1) && s.json.items.some(p => p.lsp_id === 2) && s.json.moderator === true);
  const f = await sup.req('blog?lsp=2');
  check('Admin Platform dengan filter ?lsp=2 hanya PBI', f.json.items.length > 0 && f.json.items.every(p => p.lsp_id === 2));
  check('parameter ?lsp= diabaikan untuk Admin LSP', (await tdn.req('blog?lsp=2')).json.items.every(p => p.lsp_id === 1));
}

console.log('Validasi & membuat artikel');
let draftId, draftSlug;
{
  const base = { judul: 'Artikel Uji ' + stamp, kategori: 'Berita', konten: KONTEN, ringkasan: 'Ringkasan uji.', tags: 'uji, otomatis, uji', status: 'draf' };
  check('judul terlalu pendek ditolak', (await mkt.req('blog', { ...base, judul: 'abc' })).status === 422);
  check('kategori asing ditolak', (await mkt.req('blog', { ...base, kategori: 'Gosip' })).status === 422);
  check('isi terlalu pendek ditolak', (await mkt.req('blog', { ...base, konten: 'pendek' })).status === 422);
  check('slug tidak valid ditolak', (await mkt.req('blog', { ...base, slug: '../etc/passwd' })).status === 422);
  check('status tidak valid ditolak', (await mkt.req('blog', { ...base, status: 'diturunkan' })).status === 422);
  check('waktu terbit tidak valid ditolak', (await mkt.req('blog', { ...base, terbit_at: 'besok' })).status === 422);
  const r = await mkt.req('blog', base);
  check('Marketing membuat draf (201)', r.status === 201 && r.json.baru === true && r.json.status === 'draf', JSON.stringify(r.json));
  draftId = r.json.id; draftSlug = r.json.slug;
  check('slug otomatis dari judul', draftSlug === 'artikel-uji-' + stamp);
  const r2 = await mkt.req('blog', base);
  check('judul sama mendapat slug unik', r2.status === 201 && r2.json.slug === draftSlug + '-2');
  await mkt.req('blog/hapus', { id: r2.json.id });
  const it = (await mkt.req('blog')).json.items.find(p => p.id === draftId);
  check('tag dirapikan (tanpa duplikat)', it && JSON.stringify(it.tags) === '["uji","otomatis"]');
  check('penerbit = LSP sesi, bukan dari input', it && it.lsp_id === 1);
  const forged = await mkt.req('blog', { ...base, judul: 'Coba LSP Lain ' + stamp, lsp_id: 2 });
  check('lsp_id dari klien diabaikan untuk staf LSP', forged.status === 201 && (await pbi.req('blog')).json.items.every(p => p.id !== forged.json.id));
  await mkt.req('blog/hapus', { id: forged.json.id });
  check('draf tidak tampil di publik', (await pub.req('pub/blog/post?slug=' + draftSlug)).status === 404);
  check('slug yang sudah dipakai ditolak (409)', (await mkt.req('blog', { ...base, judul: 'Lain ' + stamp, slug: 'panduan-lengkap-sertifikasi-kompetensi-bnsp-untuk-pemula' })).status === 409);
}

console.log('Isolasi ubah & hapus');
{
  check('Admin LSP PBI tidak bisa mengubah artikel TDN (404)', (await pbi.req('blog', { id: draftId, judul: 'Dibajak', kategori: 'Berita', konten: KONTEN, status: 'terbit' })).status === 404);
  check('Admin LSP PBI tidak bisa menghapus artikel TDN (404)', (await pbi.req('blog/hapus', { id: draftId })).status === 404);
  const platformPost = (await sup.req('blog')).json.items.find(p => p.lsp_id === null);
  check('Admin LSP tidak bisa mengubah artikel platform (404)', (await tdn.req('blog', { id: platformPost.id, judul: 'Dibajak', kategori: 'Berita', konten: KONTEN, status: 'terbit' })).status === 404);
  check('Admin LSP tidak bisa memoderasi (403)', (await tdn.req('blog/moderasi', { id: draftId, aksi: 'turunkan', catatan: 'tes tes' })).status === 403);
}

console.log('Terbit, terjadwal, dan API publik');
{
  const up = await tdn.req('blog', { id: draftId, judul: 'Artikel Uji ' + stamp, kategori: 'Regulasi', konten: KONTEN, ringkasan: 'Ringkasan uji.', tags: 'uji, otomatis', status: 'terbit' });
  check('Admin LSP menerbitkan artikel Marketing', up.status === 200 && up.json.status === 'terbit');
  const p = await pub.req('pub/blog/post?slug=' + draftSlug);
  check('artikel terbit bisa dibaca publik', p.status === 200 && p.json.post.judul === 'Artikel Uji ' + stamp && p.json.post.penerbit === 'LSP Teknologi Digital Nusantara');
  check('isi dikirim apa adanya (di-escape di klien)', p.json.post && p.json.post.konten === KONTEN);
  check('data internal tidak bocor ke publik', p.json.post && !('status' in p.json.post) && !('catatan_moderasi' in p.json.post) && !('lsp_status' in p.json.post));
  const v1 = p.json.post.dibaca, v2 = (await pub.req('pub/blog/post?slug=' + draftSlug)).json.post.dibaca;
  check('pembaca dihitung sekali per sesi', v1 === 1 && v2 === 1, `${v1} ${v2}`);
  const other = await anon();
  check('sesi lain menambah hitungan', (await other.req('pub/blog/post?slug=' + draftSlug)).json.post.dibaca === 2);
  const l = await pub.req('pub/blog?kategori=Regulasi');
  check('filter kategori', l.status === 200 && l.json.items.some(i => i.slug === draftSlug) && l.json.items.every(i => i.kategori === 'Regulasi'));
  check('daftar publik tanpa isi lengkap', l.json.items.every(i => !('konten' in i)));
  check('pencarian judul', (await pub.req('pub/blog?q=' + encodeURIComponent('uji ' + stamp))).json.items.some(i => i.slug === draftSlug));
  check('pencarian dengan karakter khusus aman', (await pub.req('pub/blog?q=' + encodeURIComponent("%_' OR 1=1 --"))).json.total === 0);
  check('pencarian tag', (await pub.req('pub/blog?q=otomatis')).json.items.some(i => i.slug === draftSlug));
  check('filter LSP', (await pub.req('pub/blog?lsp=2')).json.items.every(i => i.lsp_id === 2));
  const all = await pub.req('pub/blog');
  check('hitungan kategori tersedia', Array.isArray(all.json.kategori) && all.json.kategori.length === 6);
  check('artikel terjadwal (seed) belum tampil', !all.json.items.some(i => i.judul.startsWith('Jadwal Uji Analis Data')));
  const later = new Date(Date.now() + 3 * 86400000); const pad = n => String(n).padStart(2, '0');
  const dt = `${later.getFullYear()}-${pad(later.getMonth() + 1)}-${pad(later.getDate())}T10:00`;
  const sch = await mkt.req('blog', { judul: 'Terjadwal ' + stamp, kategori: 'Pengumuman', konten: KONTEN, status: 'terbit', terbit_at: dt });
  check('menjadwalkan artikel (status terjadwal)', sch.status === 201 && sch.json.status === 'terjadwal');
  check('artikel terjadwal tidak bisa dibaca publik', (await pub.req('pub/blog/post?slug=' + sch.json.slug)).status === 404);
  await mkt.req('blog/hapus', { id: sch.json.id });
}

console.log('Moderasi Admin Platform');
{
  check('menurunkan wajib alasan', (await sup.req('blog/moderasi', { id: draftId, aksi: 'turunkan', catatan: '' })).status === 422);
  check('aksi tidak dikenal ditolak', (await sup.req('blog/moderasi', { id: draftId, aksi: 'hapus' })).status === 422);
  check('memulihkan artikel yang tidak diturunkan ditolak', (await sup.req('blog/moderasi', { id: draftId, aksi: 'pulihkan' })).status === 409);
  const t = await sup.req('blog/moderasi', { id: draftId, aksi: 'turunkan', catatan: 'Hapus klaim pasti lulus.' });
  check('Admin Platform menurunkan artikel LSP', t.status === 200);
  check('artikel diturunkan hilang dari publik', (await pub.req('pub/blog/post?slug=' + draftSlug)).status === 404);
  const n = await mkt.req('notifications');
  check('LSP mendapat notifikasi penurunan', (n.json.items || []).some(x => x.title === 'Artikel blog diturunkan'));
  const e = await mkt.req('blog', { id: draftId, judul: 'Artikel Uji ' + stamp, kategori: 'Regulasi', konten: KONTEN + ' Sudah diperbaiki.', status: 'terbit' });
  check('LSP tidak bisa menayangkan sendiri artikel yang diturunkan', e.status === 200 && e.json.status === 'diturunkan' && !!e.json.catatan);
  check('masih tidak tampil di publik', (await pub.req('pub/blog/post?slug=' + draftSlug)).status === 404);
  const it = (await mkt.req('blog')).json.items.find(p => p.id === draftId);
  check('alasan penurunan terlihat oleh LSP', it && it.catatan_moderasi === 'Hapus klaim pasti lulus.');
  check('Admin Platform memulihkan artikel', (await sup.req('blog/moderasi', { id: draftId, aksi: 'pulihkan' })).status === 200);
  check('artikel tampil lagi di publik', (await pub.req('pub/blog/post?slug=' + draftSlug)).status === 200);
}

console.log('Gambar sampul');
{
  check('berkas bukan gambar ditolak', (await mkt.upload({ id: draftId }, { bytes: Buffer.from('<?php echo 1; ?>'), type: 'image/png', name: 'x.png' })).status === 422);
  check('artikel LSP lain ditolak (404)', (await pbi.upload({ id: draftId }, { bytes: PNG, type: 'image/png', name: 'a.png' })).status === 404);
  const u = await mkt.upload({ id: draftId }, { bytes: PNG, type: 'image/png', name: 'sampul.png' });
  check('Marketing mengunggah sampul PNG', u.status === 201, JSON.stringify(u.json));
  const p = await pub.req('pub/blog/post?slug=' + draftSlug);
  check('URL sampul tersedia di API publik', p.json.post && /^api\.php\?r=pub\/blog\/cover&id=\d+/.test(p.json.post.cover));
  const img = await pub.raw(p.json.post.cover);
  const bytes = Buffer.from(await img.arrayBuffer());
  check('sampul artikel terbit bisa dilihat publik', img.status === 200 && img.headers.get('content-type') === 'image/png' && bytes.equals(PNG));
  check('sampul memakai header nosniff', img.headers.get('x-content-type-options') === 'nosniff');
  const d = await mkt.req('blog', { judul: 'Draf Bersampul ' + stamp, kategori: 'Berita', konten: KONTEN, status: 'draf' });
  await mkt.upload({ id: d.json.id }, { bytes: PNG, type: 'image/png', name: 's.png' });
  const path = `api.php?r=pub/blog/cover&id=${d.json.id}`;
  check('sampul draf tertutup untuk publik', (await (await anon()).raw(path)).status === 404);
  check('sampul draf terbuka untuk pengelola LSP-nya', (await mkt.raw(path)).status === 200);
  check('sampul draf tertutup untuk LSP lain', (await pbi.raw(path)).status === 404);
  check('sampul draf terbuka untuk Admin Platform', (await sup.raw(path)).status === 200);
  check('menghapus sampul', (await mkt.upload({ id: d.json.id, hapus: '1' })).status === 200 && (await mkt.raw(path)).status === 404);
  await mkt.req('blog/hapus', { id: d.json.id });
}

console.log('Artikel platform & atas nama LSP');
{
  const a = await sup.req('blog', { lsp_id: 0, judul: 'Kabar Platform ' + stamp, kategori: 'Berita', konten: KONTEN, status: 'terbit' });
  check('Admin Platform menulis atas nama platform', a.status === 201);
  const p = await pub.req('pub/blog/post?slug=' + a.json.slug);
  check('penerbit = PortalLSP', p.json.post && p.json.post.penerbit === 'PortalLSP' && p.json.post.lsp_id === null);
  const b = await sup.req('blog', { lsp_id: 2, judul: 'Atas Nama PBI ' + stamp, kategori: 'Pelatihan', konten: KONTEN, status: 'draf' });
  check('Admin Platform menulis atas nama LSP lain', b.status === 201 && (await pbi.req('blog')).json.items.some(x => x.id === b.json.id));
  check('LSP tujuan harus aktif', (await sup.req('blog', { lsp_id: 999, judul: 'LSP Hilang ' + stamp, kategori: 'Berita', konten: KONTEN, status: 'draf' })).status === 422);
  check('Admin LSP PBI bisa menghapus artikel LSP-nya', (await pbi.req('blog/hapus', { id: b.json.id })).status === 200);
  check('Admin Platform menghapus artikel platform', (await sup.req('blog/hapus', { id: a.json.id })).status === 200);
}

console.log('Hapus');
{
  check('Marketing menghapus artikelnya', (await mkt.req('blog/hapus', { id: draftId })).status === 200);
  check('artikel terhapus tidak bisa dibaca', (await pub.req('pub/blog/post?slug=' + draftSlug)).status === 404);
  check('hapus ulang = 404', (await mkt.req('blog/hapus', { id: draftId })).status === 404);
  const audit = await sup.req('audit');
  check('tindakan blog tercatat di audit', (audit.json.items || []).some(a => String(a.action).startsWith('blog.')));
}

console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
