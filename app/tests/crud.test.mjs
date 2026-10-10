// Uji CRUD semua entitas: buat, baca, ubah, hapus, plus penjaga (data yang masih dirujuk, cakupan LSP, hak akses).
// Jalankan pada database baru (data demo).
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const PW = 'Demo-Pass-2026';
const E = n => `${n}@demo.portallsp.id`;
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, typeof x === 'string' ? x : JSON.stringify(x)); } };
class Client {
  constructor() { this.cookie = ''; this.csrf = ''; }
  async req(route, data) {
    const h = { Accept: 'application/json' }; if (this.cookie) h.Cookie = this.cookie;
    let body; if (data !== undefined) { h['Content-Type'] = 'application/json'; h['X-CSRF-Token'] = this.csrf; body = JSON.stringify(data); }
    const [r, qs] = route.split('?');
    const res = await fetch(`${BASE}/api.php?r=${encodeURIComponent(r)}${qs ? '&' + qs : ''}`, { method: data === undefined ? 'GET' : 'POST', headers: h, body });
    const sc = res.headers.get('set-cookie'); if (sc) { const m = sc.match(/lsp_sid=([^;]+)/); if (m) this.cookie = 'lsp_sid=' + m[1]; }
    let j = {}; try { j = await res.json(); } catch {} if (j.csrf) this.csrf = j.csrf; return { status: res.status, json: j };
  }
}
const login = async email => { const c = new Client(); await c.req('auth/me'); const r = await c.req('auth/login', { email, password: PW }); if (r.status !== 200) throw new Error(email + ' ' + r.status); return c; };
const [sup, tdn, pbi, mkt, tuk, rina] = await Promise.all(['superadmin', 'admin.tdn', 'admin.pbi', 'marketing.tdn', 'tuk.kuningan', 'asesi'].map(n => login(E(n))));
const pad = n => String(n).padStart(2, '0');
const tgl = days => { const d = new Date(Date.now() + days * 86400000); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; };
const uniq = Date.now().toString(36).toUpperCase();
const ok2 = r => r.status === 200 || r.status === 201;

console.log('Etalase (listing)');
{
  const base = { tipe: 'pelatihan', judul: 'Kelas Uji CRUD ' + uniq, bidang: 'TIK', kota: 'Jakarta', format: 'Online', harga: 250000, deskripsi: 'Kelas persiapan uji CRUD yang cukup panjang.', status: 'draf' };
  const c = await mkt.req('listings', base);
  check('buat draf', c.status === 201, c.json);
  const id = c.json.id;
  const find = async () => (await mkt.req('listings')).json.items.find(l => l.id === id);
  check('baca: muncul di daftar', (await find())?.status === 'draf');
  const u = await mkt.req('listings', { ...base, id, judul: 'Kelas Uji CRUD Diubah ' + uniq, harga: 300000 });
  check('ubah draf', u.status === 200 && (await find()).harga === 300000 && (await find()).judul.includes('Diubah'), u.json);
  check('ajukan', (await mkt.req('listings/submit', { id })).status === 200);
  check('ubah saat menunggu ditolak (409)', (await mkt.req('listings', { ...base, id })).status === 409);
  check('hapus saat menunggu ditolak (409)', (await mkt.req('listings/hapus', { id })).status === 409);
  check('Admin Platform minta revisi', (await sup.req('reviews/decide', { id, decision: 'revisi', note: 'Perjelas jadwal kelas dan nama instruktur.' })).status === 200);
  const r2 = await mkt.req('listings', { ...base, id, deskripsi: 'Kelas 4 sesi setiap Sabtu, instruktur bersertifikat.', status: 'draf' });
  check('ubah setelah diminta revisi', r2.status === 200 && (await find()).catatan?.includes('Perjelas'), r2.json);
  const r3 = await mkt.req('listings', { ...base, id, deskripsi: 'Kelas 4 sesi setiap Sabtu, instruktur bersertifikat.', status: 'menunggu', ack: true });
  check('ubah + ajukan ulang sekaligus (catatan lama dibersihkan)', r3.status === 200 && (await find()).status === 'menunggu' && !(await find()).catatan, r3.json);
  check('Admin Platform setujui', (await sup.req('reviews/decide', { id, decision: 'tayang', note: '' })).status === 200);
  const pub = async () => (await new Client().req('catalog')).json.items.some(l => l.id === id);
  check('tayang di katalog publik', await pub());
  check('ubah saat tayang ditolak (409)', (await mkt.req('listings', { ...base, id })).status === 409);
  check('LSP lain tidak bisa menurunkan (404)', (await pbi.req('listings/turunkan', { id })).status === 404);
  check('turunkan dari portal', (await mkt.req('listings/turunkan', { id })).status === 200 && (await find()).status === 'draf');
  check('hilang dari katalog publik', !(await pub()));
  check('LSP lain tidak bisa menghapus (404)', (await pbi.req('listings/hapus', { id })).status === 404);
  check('hapus', (await mkt.req('listings/hapus', { id })).status === 200 && !(await find()));
  const t = await mkt.req('listings', { ...base, judul: 'Kelas Ditolak ' + uniq, status: 'menunggu', ack: true });
  await sup.req('reviews/decide', { id: t.json.id, decision: 'ditolak', note: 'Klaim kelulusan tidak sesuai ketentuan.' });
  check('listing ditolak tidak bisa diubah (final, 409)', (await mkt.req('listings', { ...base, id: t.json.id })).status === 409);
  check('listing ditolak bisa dihapus', (await mkt.req('listings/hapus', { id: t.json.id })).status === 200);
  const kelas = (await new Client().req('catalog')).json.items.find(l => l.tipe === 'pelatihan' && l.lsp_nama === 'LSP Teknologi Digital Nusantara');
  if (kelas) {
    await rina.req('asesi/kelas/daftar', { id: kelas.id });
    await tdn.req('listings/turunkan', { id: kelas.id });
    check('kelas yang sudah punya peserta tidak bisa dihapus (409)', (await tdn.req('listings/hapus', { id: kelas.id })).status === 409);
    await tdn.req('listings/submit', { id: kelas.id }); await sup.req('reviews/decide', { id: kelas.id, decision: 'tayang', note: '' });
  }
}

console.log('Skema');
{
  const units = 'J.62DMI00.001.1 | Memahami data\nJ.62DMI00.002.1 | Membersihkan data';
  const body = { kode: 'SKM-' + uniq, nama: 'Skema Uji CRUD', bidang: 'TIK', kkni: '4', harga: 800000, units_text: units, deskripsi: 'Skema uji.', persyaratan: 'SMA.', status: 'aktif' };
  const c = await tdn.req('lsp/skema', body);
  check('buat', c.status === 201, c.json);
  const id = c.json.id;
  const find = async () => (await tdn.req('lsp/skema')).json.items.find(s => s.id === id);
  check('baca: 2 unit', (await find())?.units.length === 2);
  const u = await tdn.req('lsp/skema', { ...body, id, nama: 'Skema Uji CRUD Diubah', units_text: units + '\nJ.62DMI00.003.1 | Visualisasi data' });
  check('ubah nama dan unit', u.status === 201 && (await find()).nama === 'Skema Uji CRUD Diubah' && (await find()).units.length === 3, u.json);
  check('LSP lain tidak bisa mengubah (404)', (await pbi.req('lsp/skema', { ...body, id })).status === 404);
  check('LSP lain tidak bisa menghapus (404)', (await pbi.req('lsp/skema/hapus', { id })).status === 404);
  check('Marketing tidak punya hak (403)', (await mkt.req('lsp/skema/hapus', { id })).status === 403);
  check('hapus', (await tdn.req('lsp/skema/hapus', { id })).status === 200 && !(await find()));
  const dipakai = (await tdn.req('lsp/skema')).json.items[0];
  const h = await tdn.req('lsp/skema/hapus', { id: dipakai.id });
  check('skema yang sudah dipakai tidak bisa dihapus (409)', h.status === 409 && /nonaktif/.test(h.json.error || ''), h.json);
}

console.log('TUK');
{
  const body = { nama: 'TUK Uji ' + uniq, jenis: 'Sewaktu', alamat: 'Jl. Uji 1', kapasitas: 20, verif_sampai: tgl(300), status: 'aktif' };
  const c = await tdn.req('lsp/tuk', body);
  check('buat', c.status === 201, c.json);
  const id = c.json.id;
  const find = async () => (await tdn.req('lsp/tuk')).json.items.find(t => t.id === id);
  check('baca', (await find())?.kapasitas === 20);
  check('ubah', (await tdn.req('lsp/tuk', { ...body, id, kapasitas: 35, status: 'nonaktif' })).status === 201 && (await find()).kapasitas === 35 && (await find()).status === 'nonaktif');
  const s = await tdn.req('tuk/sarpras', { tuk_id: id, nama: 'Proyektor', jumlah: 1, kondisi: 'baik', catatan: '' });
  check('TUK baru bisa diisi sarana', s.status === 201, s.json);
  check('LSP lain tidak bisa menghapus (404)', (await pbi.req('lsp/tuk/hapus', { id })).status === 404);
  check('hapus (beserta sarananya)', (await tdn.req('lsp/tuk/hapus', { id })).status === 200 && !(await find()));
  const kuningan = (await tdn.req('lsp/tuk')).json.items.find(t => /Kuningan/.test(t.nama));
  check('TUK yang punya jadwal/Admin TUK tidak bisa dihapus (409)', (await tdn.req('lsp/tuk/hapus', { id: kuningan.id })).status === 409);
}

console.log('Jadwal');
{
  const opsi = (await tdn.req('lsp/opsi')).json;
  const sk = opsi.skema.find(s => s.lsp_id === 1), tk = opsi.tuk.find(t => t.lsp_id === 1);
  const body = { skema_id: sk.id, tuk_id: tk.id, tanggal: tgl(70), jam: '08.00–16.00', metode: 'Tatap muka', kuota: 10, status: 'dibuka', catatan: '' };
  const c = await tdn.req('lsp/jadwal', body);
  check('buat', c.status === 201, c.json);
  const id = c.json.id;
  const find = async () => (await tdn.req('lsp/jadwal')).json.items.find(j => j.id === id);
  check('baca', (await find())?.kuota === 10);
  check('ubah', (await tdn.req('lsp/jadwal', { ...body, id, kuota: 15, tanggal: tgl(71) })).status === 201 && (await find()).kuota === 15 && (await find()).tanggal === tgl(71));
  check('LSP lain tidak bisa menghapus (404)', (await pbi.req('lsp/jadwal/hapus', { id })).status === 404);
  check('hapus', (await tdn.req('lsp/jadwal/hapus', { id })).status === 200 && !(await find()));
  const isi = (await tdn.req('lsp/jadwal')).json.items.find(j => j.peserta > 0);
  check('jadwal yang punya pendaftar tidak bisa dihapus (409)', (await tdn.req('lsp/jadwal/hapus', { id: isi.id })).status === 409);
}

console.log('CRM');
{
  const body = { nama: 'Budi Prospek', organisasi: 'PT Uji ' + uniq, kontak: '0812', sumber: 'Pameran', tahap: 'baru', nilai: 5000000, catatan: '', followup: tgl(7) };
  const c = await mkt.req('lsp/crm', body);
  check('buat', c.status === 201, c.json);
  const id = c.json.id;
  const find = async () => (await mkt.req('lsp/crm')).json.items.find(x => x.id === id);
  check('baca', (await find())?.nilai === 5000000);
  check('ubah', (await mkt.req('lsp/crm', { ...body, id, nilai: 7500000 })).status === 201 && (await find()).nilai === 7500000);
  check('pindah tahap', (await mkt.req('lsp/crm/tahap', { id, tahap: 'proposal' })).status === 200 && (await find()).tahap === 'proposal');
  check('LSP lain tidak bisa menghapus (404)', (await pbi.req('lsp/crm/hapus', { id })).status === 404);
  check('hapus', (await mkt.req('lsp/crm/hapus', { id })).status === 200 && !(await find()));
}

console.log('Mutu');
{
  const body = { jenis: 'Audit internal', judul: 'Audit uji ' + uniq, deskripsi: '', status: 'terbuka', pic: 'Manajer Mutu', tenggat: tgl(30) };
  const c = await tdn.req('lsp/mutu', body);
  check('buat', c.status === 201, c.json);
  const id = c.json.id;
  const find = async () => (await tdn.req('lsp/mutu')).json.items.find(x => x.id === id);
  check('ubah', (await tdn.req('lsp/mutu', { ...body, id, status: 'proses' })).status === 201 && (await find()).status === 'proses');
  check('hapus item yang belum selesai', (await tdn.req('lsp/mutu/hapus', { id })).status === 200 && !(await find()));
  const c2 = await tdn.req('lsp/mutu', { ...body, status: 'selesai' });
  check('item selesai tidak bisa dihapus (rekaman mutu, 409)', (await tdn.req('lsp/mutu/hapus', { id: c2.json.id })).status === 409);
}

console.log('Sarana & prasarana (Admin TUK)');
{
  const mine = (await tuk.req('tuk/sarpras')).json.items;
  const tukId = mine[0]?.tuk_id || (await tuk.req('tuk/dashboard')).json.tuk?.id;
  const c = await tuk.req('tuk/sarpras', { tuk_id: tukId, nama: 'Laptop uji ' + uniq, jumlah: 5, kondisi: 'baik', catatan: '' });
  check('buat', c.status === 201, c.json);
  const id = c.json.id;
  const find = async () => (await tuk.req('tuk/sarpras')).json.items.find(x => x.id === id);
  check('ubah', (await tuk.req('tuk/sarpras', { id, nama: 'Laptop uji ' + uniq, jumlah: 4, kondisi: 'perlu perbaikan', catatan: '1 rusak' })).status === 201 && (await find()).jumlah === 4);
  const lain = (await tdn.req('tuk/sarpras')).json.items.find(x => x.tuk_id !== tukId);
  if (lain) check('sarana TUK lain tidak bisa dihapus Admin TUK (404)', (await tuk.req('tuk/sarpras/hapus', { id: lain.id })).status === 404);
  check('LSP lain tidak bisa menghapus (404)', (await pbi.req('tuk/sarpras/hapus', { id })).status === 404);
  check('hapus', (await tuk.req('tuk/sarpras/hapus', { id })).status === 200 && !(await find()));
}

console.log('Pustaka SKKNI (Admin Platform)');
{
  const kode = 'UJI.' + uniq;
  check('buat', (await sup.req('skkni', { kode, judul: 'Unit uji pustaka', sektor: 'TIK' })).status === 201);
  const find = async () => (await sup.req('skkni?q=' + encodeURIComponent(kode))).json.items.find(u => u.kode === kode);
  check('baca', (await find())?.judul === 'Unit uji pustaka');
  check('kode ganda ditolak (409)', (await sup.req('skkni', { kode, judul: 'Unit uji pustaka', sektor: 'TIK' })).status === 409);
  check('ubah', (await sup.req('skkni', { kode, judul: 'Unit uji pustaka diubah', sektor: 'Data', ubah: true })).status === 200 && (await find()).sektor === 'Data');
  check('Admin LSP tidak bisa menghapus (403)', (await tdn.req('skkni/hapus', { kode })).status === 403);
  check('hapus', (await sup.req('skkni/hapus', { kode })).status === 200 && !(await find()));
}

console.log('Pengguna & asesor');
{
  const email = `staf.${uniq.toLowerCase()}@contoh.id`;
  const c = await tdn.req('users', { email, nama: 'Staf Uji CRUD', role: 'keuangan', password: 'Staf-Awal-2026' });
  check('buat staf', c.status === 201, c.json);
  const find = async () => (await tdn.req('users')).json.items.find(u => u.email === email);
  check('baca', (await find())?.role === 'keuangan');
  const mid = (await find()).membership_id;
  check('nonaktifkan', (await tdn.req('users/status', { membership_id: mid, status: 'nonaktif' })).status === 200 && (await find()).status === 'nonaktif');
  check('aktifkan', (await tdn.req('users/status', { membership_id: mid, status: 'aktif' })).status === 200 && (await find()).status === 'aktif');
  check('LSP lain tidak bisa mengubah (404)', (await pbi.req('users/status', { membership_id: mid, status: 'nonaktif' })).status === 404);
  check('peran platform tidak bisa diberikan Admin LSP (422/403)', [403, 422].includes((await tdn.req('users', { email: 'x.' + email, nama: 'Coba Platform', role: 'platform_admin', password: 'Staf-Awal-2026' })).status));
  const ae = `asesor.${uniq.toLowerCase()}@contoh.id`;
  check('tambah asesor baru', (await tdn.req('lsp/asesor', { email: ae, nama: 'Asesor Uji CRUD', password: 'Asesor-Awal-2026' })).status === 201);
  const a = (await tdn.req('lsp/asesor')).json.items.find(x => x.email === ae);
  check('asesor bisa dinonaktifkan dari menu Master', (await tdn.req('users/status', { membership_id: a.membership_id, status: 'nonaktif' })).status === 200
    && (await tdn.req('lsp/asesor')).json.items.find(x => x.email === ae).status === 'nonaktif');
}

console.log('LSP klien & paket (Admin Platform)');
{
  const kode = 'U' + uniq.slice(-4);
  const c = await sup.req('platform/lsp', { nama: 'LSP Uji CRUD ' + uniq, kode, jenis: 'P3', kota: 'Bandung', paket: 'Basic', admin_email: `admin.${uniq.toLowerCase()}@contoh.id`, admin_nama: 'Admin Uji', admin_password: 'Admin-Awal-2026' });
  check('buat LSP + Admin LSP', c.status === 201, c.json);
  const id = c.json.id;
  const find = async () => (await sup.req('lsps')).json.items.find(l => l.id === id);
  check('baca', (await find())?.status === 'aktif');
  check('ubah paket', (await sup.req('platform/paket', { id, paket: 'Pro', kuota_asesi: 5000 })).status === 200);
  check('nonaktifkan', (await sup.req('platform/lsp/status', { id, status: 'nonaktif' })).status === 200 && (await find()).status === 'nonaktif');
  check('Admin LSP tidak bisa membuat LSP (403)', (await tdn.req('platform/lsp', { nama: 'X', kode: 'XX' })).status === 403);
}

console.log('Pengaturan LSP');
{
  const cur = (await tdn.req('lsp/pengaturan')).json.lsp;
  const body = { nama: cur.nama, kota: cur.kota, alamat: 'Jl. Diubah Uji 9', telepon: cur.telepon || '', email: cur.email || '', website: cur.website || '', deskripsi: cur.deskripsi || '', honor_per_asesi: cur.honor_per_asesi };
  check('ubah dan baca kembali', (await tdn.req('lsp/pengaturan', body)).status === 200 && (await tdn.req('lsp/pengaturan')).json.lsp.alamat === 'Jl. Diubah Uji 9');
  await tdn.req('lsp/pengaturan', { ...body, alamat: cur.alamat || '' });
}

console.log('Tiket support');
{
  const c = await tdn.req('tiket', { judul: 'Tiket uji ' + uniq, isi: 'Mohon bantuan uji CRUD.', prioritas: 'normal' });
  check('buat', c.status === 201, c.json);
  const id = c.json.id;
  const find = async cl => (await cl.req('tiket')).json.items.find(t => t.id === id);
  check('Admin Platform membalas', (await sup.req('tiket/balas', { id, isi: 'Sedang kami cek.' })).status === 200 && (await find(sup)).status === 'proses');
  check('LSP membalas', (await tdn.req('tiket/balas', { id, isi: 'Terima kasih.' })).status === 200);
  check('Admin Platform menutup', (await sup.req('tiket/balas', { id, isi: '', status: 'selesai' })).status === 200 && (await find(tdn)).status === 'selesai');
  check('LSP lain tidak bisa membalas (404)', (await pbi.req('tiket/balas', { id, isi: 'x' })).status === 404);
}

console.log('Profil asesi');
{
  const p0 = (await rina.req('profile')).json;
  if (!p0.nik) {
    const l = await rina.req('profile', { nama: p0.nama, nik: '3174054107940009', tanggal_lahir: '1994-07-01', jenis_kelamin: 'P', no_hp: '081211112222', consent_privacy: true });
    check('akun tanpa profil melengkapi NIK & data diri', l.status === 200 && !!(await rina.req('profile')).json.nik, l.json);
  }
  const p = (await rina.req('profile')).json;
  const hp = await rina.req('profile', { nama: p.nama, tanggal_lahir: p.tanggal_lahir, jenis_kelamin: p.jenis_kelamin, no_hp: '081299988877', consent_marketing: true });
  check('ubah nomor HP dan persetujuan info', hp.status === 200 && (await rina.req('profile')).json.no_hp === '081299988877', hp.json);
  const n = await rina.req('profile', { nama: 'Rina Diubah', tanggal_lahir: p.tanggal_lahir, jenis_kelamin: p.jenis_kelamin, no_hp: '081299988877' });
  check('nama tidak bisa diubah setelah punya sertifikat/pendaftaran (409)', n.status === 409, n.json);
  check('HP tidak valid ditolak (422)', (await rina.req('profile', { ...p, no_hp: '12' })).status === 422);
  const baru = new Client(); await baru.req('auth/me');
  const n4 = String(Date.now()).slice(-6);
  const reg = await baru.req('auth/register', { nama: 'Asesi Profil Baru', nik: '3174055208' + n4, tanggal_lahir: '1998-05-05', jenis_kelamin: 'P', email: `profil.${n4}@contoh.id`, no_hp: '081234500000', password: 'Kuat-Sekali-2026', consent_privacy: true });
  check('akun asesi baru', reg.status === 201, reg.json);
  check('asesi baru bisa memperbaiki nama dan tanggal lahir', (await baru.req('profile', { nama: 'Asesi Profil Diperbaiki', tanggal_lahir: '1998-06-06', jenis_kelamin: 'P', no_hp: '081234500000' })).status === 200
    && (await baru.req('profile')).json.nama === 'Asesi Profil Diperbaiki' && (await baru.req('auth/me')).json.user.nama === 'Asesi Profil Diperbaiki');
  check('staf tidak punya profil asesi (403)', (await tdn.req('profile', { nama: 'x' })).status === 403);
}

console.log('Notifikasi');
{
  const s = (await rina.req('notifications/settings')).json;
  check('ubah preferensi email', (await rina.req('notifications/settings', { email_on: !s.email_on, wa_on: false, wa_number: '', wa_consent: false })).status === 200
    && (await rina.req('notifications/settings')).json.email_on === !s.email_on);
  await rina.req('notifications/settings', { email_on: s.email_on, wa_on: false, wa_number: '', wa_consent: false });
  check('tandai semua dibaca', (await rina.req('notifications/read', { all: true })).status === 200 && (await rina.req('notifications/count')).json.unread === 0);
}

console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
