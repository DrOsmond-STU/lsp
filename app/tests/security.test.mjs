// Uji regresi keamanan: memastikan temuan audit RBAC & keamanan tidak muncul lagi.
// Jalankan pada database baru (data demo), sesudah suite lain atau sendirian.
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const PW = 'Demo-Pass-2026';
const E = n => `${n}@demo.portallsp.id`;
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, typeof x === 'string' ? x : JSON.stringify(x)); } };
class Client {
  constructor() { this.cookie = ''; this.csrf = ''; }
  grab(res) { const sc = res.headers.get('set-cookie'); if (sc) { const m = sc.match(/lsp_sid=([^;]+)/); if (m) this.cookie = 'lsp_sid=' + m[1]; } }
  async req(route, data) {
    const h = { Accept: 'application/json' }; if (this.cookie) h.Cookie = this.cookie;
    let body; if (data !== undefined) { h['Content-Type'] = 'application/json'; h['X-CSRF-Token'] = this.csrf; body = JSON.stringify(data); }
    const [r, qs] = route.split('?');
    const res = await fetch(`${BASE}/api.php?r=${encodeURIComponent(r)}${qs ? '&' + qs : ''}`, { method: data === undefined ? 'GET' : 'POST', headers: h, body });
    this.grab(res);
    let j = {}; try { j = await res.json(); } catch {} if (j.csrf) this.csrf = j.csrf; return { status: res.status, json: j };
  }
  async upload(route, fields, file) {
    const fd = new FormData(); for (const [k, v] of Object.entries(fields)) fd.append(k, String(v));
    fd.append('file', new Blob([file.bytes], { type: file.type }), file.name);
    const res = await fetch(`${BASE}/api.php?r=${route}`, { method: 'POST', headers: { Accept: 'application/json', Cookie: this.cookie, 'X-CSRF-Token': this.csrf }, body: fd });
    let j = {}; try { j = await res.json(); } catch {} return { status: res.status, json: j };
  }
}
const login = async (email, pw = PW) => { const c = new Client(); await c.req('auth/me'); const r = await c.req('auth/login', { email, password: pw }); if (r.status !== 200) throw new Error(email + ' ' + r.status); return c; };
const sup = await login(E('superadmin')), tdn = await login(E('admin.tdn')), asr = await login(E('asesor')), rina = await login(E('asesi'));
const rinaId = (await rina.req('auth/me')).json.user.id;
const pad = n => String(n).padStart(2, '0');
const d = new Date(); const today = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

console.log('Catatan jadwal (tautan SJJ) tidak bocor ke publik');
const jadwalTdn = (await tdn.req('lsp/jadwal')).json.items;
{
  const j = jadwalTdn.find(x => x.status === 'dibuka' && x.tanggal >= today);
  const up = await tdn.req('lsp/jadwal', { id: j.id, skema_id: j.skema_id, tuk_id: j.tuk_id, tanggal: j.tanggal, jam: j.jam, metode: j.metode, kuota: j.kuota, status: j.status, catatan: 'https://meet.example/rahasia-sjj' });
  check('admin menyimpan catatan jadwal', up.status === 201, up.json);
  const pj = JSON.stringify((await new Client().req('pub/jadwal')).json);
  check('pub/jadwal tanpa catatan internal', !pj.includes('rahasia-sjj') && !pj.includes('"catatan"'));
  const cat = (await new Client().req('pub/catalog')).json.items.filter(c => c.skema_id === j.skema_id);
  const ps = cat.length ? JSON.stringify((await new Client().req('pub/skema?id=' + cat[0].id)).json) : '';
  check('pub/skema tanpa catatan internal', !ps.includes('rahasia-sjj'));
}

console.log('Asesor yang juga asesi tidak bisa menangani/memutus berkasnya sendiri');
let pid, rinaJadwal;
{
  const mine = (await rina.req('asesi/permohonan')).json.items.find(p => p.status === 'pra_asesmen' && p.lsp_nama === 'LSP Teknologi Digital Nusantara');
  check('data demo: Rina punya permohonan pra-asesmen di TDN', !!mine);
  pid = mine.id; rinaJadwal = mine.jadwal_id;
  const add = await tdn.req('lsp/asesor', { email: E('asesi') });
  check('admin menambahkan Rina sebagai asesor TDN', add.status === 201, add.json);
  const conflict = await tdn.req('lsp/jadwal/asesor', { jadwal_id: rinaJadwal, asesor_id: rinaId });
  check('menugaskan peserta sebagai asesor jadwalnya sendiri ditolak (409)', conflict.status === 409, conflict.json);
  // Asesor lain menyelesaikan pra-asesmen & asesmen agar berkas masuk pleno.
  let jd = (await tdn.req('lsp/jadwal')).json.items.find(x => x.id === rinaJadwal);
  if (jd.asesor_id !== (await asr.req('auth/me')).json.user.id) {
    await tdn.req('lsp/jadwal/asesor', { jadwal_id: jd.id, asesor_id: (await asr.req('auth/me')).json.user.id });
  }
  check('asesor penguji meloloskan pra-asesmen', (await asr.req('asesor/pra/putus', { id: pid, rekom: 'lanjut', catatan: 'ok' })).status === 200);
  jd = (await tdn.req('lsp/jadwal')).json.items.find(x => x.id === rinaJadwal);
  const budiId = (await asr.req('auth/me')).json.user.id;
  for (const x of (await tdn.req('lsp/jadwal')).json.items.filter(x => x.tanggal === today && x.asesor_id === budiId && x.id !== jd.id && !['selesai', 'batal'].includes(x.status)))
    await tdn.req('lsp/jadwal/asesor', { jadwal_id: x.id, asesor_id: 0 });
  if (jd.tanggal > today) { const mv = await tdn.req('lsp/jadwal', { id: jd.id, skema_id: jd.skema_id, tuk_id: jd.tuk_id, tanggal: today, jam: jd.jam, metode: jd.metode, kuota: jd.kuota, status: jd.status }); check('jadwal dimajukan ke hari ini', mv.status === 201, mv.json); }
  const units = (await asr.req('asesor/asesmen')).json.items.find(x => x.id === pid).units;
  const sim = await asr.req('asesor/asesmen/simpan', { id: pid, hasil: Object.fromEntries(units.map(u => [u.id, 'K'])), catatan: 'ok' });
  check('asesmen tersimpan → menunggu pleno', sim.status === 200, sim.json);
  const asesorMid = (await rina.req('auth/me')).json.memberships.find(m => m.role === 'asesor').id;
  check('Rina berpindah ke konteks asesor', (await rina.req('auth/switch', { membership_id: asesorMid })).status === 200);
  const pl = await rina.req('pleno');
  check('daftar pleno tidak memuat permohonannya sendiri', pl.status === 200 && !pl.json.items.some(x => x.id === pid));
  const putus = await rina.req('pleno/putus', { id: pid, keputusan: 'K', catatan: '' });
  check('memutus pleno permohonan sendiri ditolak (403)', putus.status === 403, putus.json);
  check('daftar pra-asesmen tidak memuat permohonannya sendiri', !(await rina.req('asesor/pra')).json.items.some(x => x.asesi_id === rinaId));
  check('staf pleno (bukan pemilik) tetap bisa memutus', (await tdn.req('pleno/putus', { id: pid, keputusan: 'K', catatan: 'Lengkap' })).status === 200);
}

console.log('Asesor tidak bisa mendaftar di jadwal yang ia uji; asesi nonaktif tidak bisa mendaftar');
{
  const asesiMid = (await rina.req('auth/me')).json.memberships.find(m => m.role === 'asesi').id;
  await rina.req('auth/switch', { membership_id: asesiMid });
  const listed = new Set((await new Client().req('pub/catalog')).json.items.filter(c => c.lsp_id === 1).map(c => c.skema_id));
  const opsi = (await tdn.req('lsp/opsi')).json;
  const sk = opsi.skema.find(s => s.lsp_id === 1 && listed.has(s.id));
  check('data demo: ada skema TDN terbuka', !!sk);
  const fut = new Date(Date.now() + 45 * 86400000); const tgl = `${fut.getFullYear()}-${pad(fut.getMonth() + 1)}-${pad(fut.getDate())}`;
  const mk = async () => (await tdn.req('lsp/jadwal', { skema_id: sk.id, tuk_id: opsi.tuk.find(t => t.lsp_id === 1).id, tanggal: tgl, jam: '08.00–16.00', metode: 'Tatap muka', kuota: 5, status: 'dibuka' })).json.id;
  const open = [{ id: await mk() }, { id: await mk() }];
  const j2 = open[0], j3 = open[1] || open[0];
  const as = await tdn.req('lsp/jadwal/asesor', { jadwal_id: j2.id, asesor_id: rinaId });
  check('Rina ditugaskan sebagai asesor jadwal lain', as.status === 200, as.json);
  const ap = await rina.req('asesi/apply', { jadwal_id: j2.id, apl02: {}, tujuan: 'Sertifikasi', consent: true });
  check('asesor mendaftar di jadwal yang ia uji ditolak (409)', ap.status === 409 && /asesor/i.test(ap.json.error || ''), ap.json);
  await tdn.req('lsp/jadwal/asesor', { jadwal_id: j2.id, asesor_id: 0 });
  const mem = (await tdn.req('users')).json.items.find(u => u.email === E('asesi') && u.role === 'asesi');
  check('admin menonaktifkan akses asesi Rina di TDN', (await tdn.req('users/status', { membership_id: mem.membership_id, status: 'nonaktif' })).status === 200);
  const ap2 = await rina.req('asesi/apply', { jadwal_id: j3.id, apl02: {}, tujuan: 'Sertifikasi', consent: true });
  check('asesi nonaktif mendaftar ulang ke LSP itu ditolak (403)', ap2.status === 403, ap2.json);
  await tdn.req('users/status', { membership_id: mem.membership_id, status: 'aktif' });
}

console.log('Ubah jadwal tervalidasi');
{
  const jd = (await tdn.req('lsp/jadwal')).json.items.find(x => x.peserta > 0 && x.status === 'dibuka');
  const other = (await tdn.req('lsp/opsi')).json.skema.find(s => s.id !== jd.skema_id && s.lsp_id === 1);
  const base = { id: jd.id, skema_id: jd.skema_id, tuk_id: jd.tuk_id, tanggal: jd.tanggal, jam: jd.jam, metode: jd.metode, kuota: jd.kuota, status: jd.status };
  check('ganti skema saat sudah ada peserta ditolak (409)', (await tdn.req('lsp/jadwal', { ...base, skema_id: other.id })).status === 409);
  check('kuota di bawah jumlah peserta ditolak (422)', jd.peserta < 2 || (await tdn.req('lsp/jadwal', { ...base, kuota: jd.peserta - 1 })).status === 422);
  const later = new Date(Date.now() + 60 * 86400000); const tg = `${later.getFullYear()}-${pad(later.getMonth() + 1)}-${pad(later.getDate())}`;
  const nj = await tdn.req('lsp/jadwal', { skema_id: jd.skema_id, tuk_id: jd.tuk_id, tanggal: tg, jam: '08.00–16.00', metode: jd.metode, kuota: 5, status: 'dibuka' });
  await tdn.req('lsp/jadwal', { id: nj.json.id, skema_id: jd.skema_id, tuk_id: jd.tuk_id, tanggal: tg, jam: '08.00–16.00', metode: jd.metode, kuota: 5, status: 'batal' });
  check('jadwal batal tidak bisa dibuka lagi (409)', (await tdn.req('lsp/jadwal', { id: nj.json.id, skema_id: jd.skema_id, tuk_id: jd.tuk_id, tanggal: tg, jam: '08.00–16.00', metode: jd.metode, kuota: 5, status: 'dibuka' })).status === 409);
  check('asesor jadwal batal tidak bisa diubah (409)', (await tdn.req('lsp/jadwal/asesor', { jadwal_id: nj.json.id, asesor_id: 0 })).status === 409);
}

console.log('Lisensi BNSP hanya diubah Admin Platform');
{
  const before = (await tdn.req('lsp/pengaturan')).json.lsp;
  const body = { nama: before.nama, kota: before.kota, alamat: before.alamat || '', telepon: before.telepon || '', email: before.email || '', website: before.website || '', deskripsi: before.deskripsi || '', honor_per_asesi: before.honor_per_asesi, lisensi_sampai: '2099-12-31' };
  check('Admin LSP menyimpan pengaturan', (await tdn.req('lsp/pengaturan', body)).status === 200);
  check('masa berlaku lisensi tidak berubah oleh Admin LSP', (await tdn.req('lsp/pengaturan')).json.lsp.lisensi_sampai === before.lisensi_sampai);
}

console.log('Artikel yang diturunkan tidak bisa dihapus LSP');
{
  const a = await tdn.req('blog', { judul: 'Uji Moderasi Hapus ' + Date.now(), kategori: 'Berita', konten: 'Isi artikel uji moderasi yang cukup panjang.', status: 'terbit' });
  await sup.req('blog/moderasi', { id: a.json.id, aksi: 'turunkan', catatan: 'Klaim menyesatkan.' });
  check('LSP menghapus artikel diturunkan ditolak (409)', (await tdn.req('blog/hapus', { id: a.json.id })).status === 409);
  check('Admin Platform tetap bisa menghapus', (await sup.req('blog/hapus', { id: a.json.id })).status === 200);
}

console.log('Verifikasi sertifikat: nomor tidak membuka nama lengkap');
{
  const v1 = await new Client().req('pub/verify?q=TDN7K3P9QX');
  check('kode verifikasi menampilkan nama lengkap', v1.json.found && v1.json.nama === 'Rina Kartika Sari' && !v1.json.nama_disamarkan, v1.json);
  const v2 = await new Client().req('pub/verify?q=' + encodeURIComponent(v1.json.nomor));
  check('nomor sertifikat: sah, nama disamarkan', v2.json.found && v2.json.nama_disamarkan === true && !v2.json.nama.includes('Kartika'), v2.json);
}

console.log('Sesi');
{
  const res = await fetch(`${BASE}/api.php?r=pub/catalog`);
  check('endpoint publik tanpa cookie tidak membuat sesi baru', !res.headers.get('set-cookie'));
  const a = await login(E('marketing.tdn')), b = await login(E('marketing.tdn'));
  check('panjang password > 72 ditolak (422)', (await a.req('auth/password', { current: PW, new: 'Aa1' + 'x'.repeat(80) })).status === 422);
  check('ganti password', (await a.req('auth/password', { current: PW, new: 'Ganti-Sesi-2026' })).status === 200);
  check('sesi yang mengganti password tetap masuk', (await a.req('lsp/dashboard')).status === 200);
  check('sesi lain pengguna yang sama langsung berakhir (401)', (await b.req('lsp/dashboard')).status === 401);
  check('kembalikan password', (await a.req('auth/password', { current: 'Ganti-Sesi-2026', new: PW })).status === 200);
}

console.log('Dokumen terkunci saat permohonan diproses');
{
  const pbiPra = (await rina.req('asesi/permohonan')).json.items.some(p => ['menunggu_bayar', 'pra_asesmen', 'siap_uji', 'menunggu_pleno'].includes(p.status));
  check('data demo: Rina punya permohonan yang sedang diproses', pbiPra);
  const up = await rina.upload('dokumen/upload', { jenis: 'ktp' }, { bytes: PNG, type: 'image/png', name: 'ktp.png' });
  check('mengganti KTP saat diproses ditolak (409)', up.status === 409, up.json);
}

console.log('Asisten AI: asesi wajib verifikasi email');
{
  const c = new Client(); await c.req('auth/me');
  const n = String(Date.now()).slice(-4);
  const reg = await c.req('auth/register', { nama: 'Uji Keamanan', nik: `31740552089${n.padStart(4, '0')}`.slice(0, 12) + n, tanggal_lahir: '1999-08-12', jenis_kelamin: 'P', email: `uji.ai.${Date.now()}@contoh.id`, no_hp: '081234567890', password: 'Kuat-Sekali-2026', consent_privacy: true });
  check('akun asesi baru (belum verifikasi email)', reg.status === 201, reg.json);
  check('AI ditolak sebelum verifikasi email (403)', (await c.req('ai/chat', { messages: [{ role: 'user', content: 'halo' }] })).status === 403);
}

console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
