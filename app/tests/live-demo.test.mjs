// Uji langsung situs demo (HTTPS, database sungguhan). Tidak meninggalkan data: setiap data uji dibuat, dibaca, diubah, lalu dihapus.
// Jalankan: BASE=https://demo-lsp.example DEMO_PW=... node tests/live-demo.test.mjs
//   atau DEMO_PW_FILE=/jalur/berkas (baris "akun password", akun tanpa @domain) bila tiap akun berbeda password.
// Pengguna, tiket, dan perangkat uji tidak punya aksi hapus di aplikasi: dibersihkan oleh skrip pelaksana (SQL) sesudah uji ini.
import { readFileSync } from 'node:fs';
const BASE = process.env.BASE;
const PWS = {};
if (process.env.DEMO_PW_FILE) for (const l of readFileSync(process.env.DEMO_PW_FILE, 'utf8').split('\n')) { const [a, p] = l.trim().split(/\s+/); if (a && p) PWS[a] = p; }
const pwOf = n => PWS[n] || process.env.DEMO_PW;
if (!BASE || (!process.env.DEMO_PW && !Object.keys(PWS).length)) { console.error('Set BASE dan DEMO_PW atau DEMO_PW_FILE'); process.exit(2); }
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, typeof x === 'string' ? x : JSON.stringify(x).slice(0, 300)); } };
class Client {
  constructor() { this.cookie = ''; this.csrf = ''; }
  async req(route, data) {
    const h = { Accept: 'application/json' }; if (this.cookie) h.Cookie = this.cookie;
    let body; if (data !== undefined) { h['Content-Type'] = 'application/json'; h['X-CSRF-Token'] = this.csrf; h.Origin = BASE; body = JSON.stringify(data); }
    const [r, qs] = route.split('?');
    const res = await fetch(`${BASE}/api.php?r=${encodeURIComponent(r)}${qs ? '&' + qs : ''}`, { method: data === undefined ? 'GET' : 'POST', headers: h, body });
    for (const sc of res.headers.getSetCookie ? res.headers.getSetCookie() : [res.headers.get('set-cookie') || '']) { const m = sc.match(/^([a-z_]*sid)=([^;]+)/); if (m) this.cookie = m[1] + '=' + m[2]; }
    let j = {}; try { j = await res.json(); } catch {} if (j.csrf) this.csrf = j.csrf; return { status: res.status, json: j };
  }
}
const login = async n => { const c = new Client(); await c.req('auth/me'); const r = await c.req('auth/login', { email: n + '@demo.portallsp.id', password: pwOf(n) }); check('masuk sebagai ' + n, r.status === 200, r.json); return c; };
const pad = n => String(n).padStart(2, '0');
const tgl = days => { const d = new Date(Date.now() + days * 86400000); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; };
const TAG = 'UJILIVE' + Date.now().toString(36).toUpperCase();
const ok2 = r => r.status === 200 || r.status === 201;

console.log('Masuk semua akun demo');
const C = {};
for (const n of ['superadmin', 'admin.tdn', 'marketing.tdn', 'keuangan.tdn', 'admin.pbi', 'tuk.kuningan', 'asesor', 'asesor2', 'asesi', 'komite.tdn', 'mutu.tdn']) C[n] = await login(n);

console.log('Baca semua menu (data dari database)');
const MENU = {
  superadmin: ['platform/dashboard', 'reviews', 'platform/paket', 'skkni', 'tiket', 'audit', 'rbac', 'lsps', 'users', 'notifications/log', 'blog', 'listings'],
  'admin.tdn': ['lsp/dashboard', 'lsp/pendaftaran', 'lsp/jadwal', 'lsp/asesmen', 'lsp/hasil', 'lsp/skema', 'lsp/tuk', 'lsp/asesor', 'lsp/alumni', 'listings', 'lsp/keuangan', 'lsp/crm', 'lsp/mutu', 'lsp/laporan', 'users', 'notifications/log', 'lsp/pengaturan', 'tiket', 'blog', 'tuk/sarpras', 'chat/ruang', 'muk/perangkat', 'banding'],
  'marketing.tdn': ['listings', 'blog', 'lsp/crm'],
  'keuangan.tdn': ['lsp/keuangan'],
  'admin.pbi': ['lsp/dashboard', 'lsp/pendaftaran', 'lsp/alumni'],
  'tuk.kuningan': ['tuk/dashboard', 'tuk/pemohon', 'tuk/jadwal', 'tuk/sarpras', 'tuk/alumni'],
  asesor: ['asesor/jadwal', 'asesor/pra', 'asesor/asesmen', 'pleno', 'asesor/riwayat', 'asesor/honor', 'muk/perangkat'],
  asesor2: ['asesor/jadwal', 'asesor/pra', 'asesor/asesmen', 'asesor/riwayat', 'muk/perangkat'],
  'komite.tdn': ['lsp/dashboard', 'pleno', 'banding', 'lsp/asesmen', 'lsp/hasil', 'lsp/alumni'],
  'mutu.tdn': ['lsp/dashboard', 'lsp/mutu', 'lsp/laporan', 'lsp/asesmen', 'lsp/alumni', 'banding', 'muk/perangkat'],
  asesi: ['asesi/permohonan', 'asesi/tagihan', 'asesi/sertifikat', 'asesi/kelas', 'profile', 'dokumen', 'notifications'],
};
let kosong = [];
for (const [akun, rs] of Object.entries(MENU)) {
  let okAll = true;
  for (const r of rs) {
    const x = await C[akun].req(r);
    if (x.status !== 200) { okAll = false; kosong.push(`${akun}:${r}=${x.status}`); continue; }
    const arr = Array.isArray(x.json.items) ? x.json.items : null;
    if (arr && arr.length === 0) kosong.push(`${akun}:${r}=kosong`);
  }
  check(`${akun}: ${rs.length} menu terbuka (200)`, okAll, kosong.join(' '));
}
console.log('  info: menu tanpa baris data:', kosong.filter(k => k.endsWith('kosong')).join(', ') || '-');

console.log('Siklus buat → baca → ubah → hapus');
const tdnC = C['admin.tdn'], mktC = C['marketing.tdn'], tukC = C['tuk.kuningan'], supC = C.superadmin;
{ // Skema
  const units = 'J.62UJI00.001.1 | Unit uji satu\n- Elemen uji satu | KUK 1.1; KUK 1.2\nJ.62UJI00.002.1 | Unit uji dua';
  const body = { kode: TAG, nama: 'Skema ' + TAG, bidang: 'TIK', kkni: '4', harga: 800000, units_text: units, deskripsi: 'Skema uji langsung.', persyaratan: 'SMA.', status: 'aktif' };
  const c = await tdnC.req('lsp/skema', body); const id = c.json.id;
  const find = async () => (await tdnC.req('lsp/skema')).json.items.find(s => s.id === id);
  check('Skema: buat', c.status === 201, c.json);
  { const sk = await find(); check('Skema: baca (2 unit, elemen & KUK tersimpan)', sk?.units.length === 2 && /- Elemen uji satu \| KUK 1\.1; KUK 1\.2/.test(sk.units_text || ''), sk && { units: sk.units, units_text: sk.units_text }); }
  check('Skema: ubah', ok2(await tdnC.req('lsp/skema', { ...body, id, nama: 'Skema ' + TAG + ' diubah' })) && (await find())?.nama.endsWith('diubah'));
  check('Skema: hapus', (await tdnC.req('lsp/skema/hapus', { id })).status === 200 && !(await find()));
}
{ // TUK
  const body = { nama: 'TUK ' + TAG, jenis: 'Sewaktu', alamat: 'Jl. Uji 1', kapasitas: 20, verif_sampai: tgl(300), status: 'aktif' };
  const c = await tdnC.req('lsp/tuk', body); const id = c.json.id;
  const find = async () => (await tdnC.req('lsp/tuk')).json.items.find(t => t.id === id);
  check('TUK: buat', c.status === 201, c.json);
  check('TUK: baca', (await find())?.kapasitas === 20);
  check('TUK: ubah', ok2(await tdnC.req('lsp/tuk', { ...body, id, kapasitas: 30 })) && (await find())?.kapasitas === 30);
  check('TUK: hapus', (await tdnC.req('lsp/tuk/hapus', { id })).status === 200 && !(await find()));
}
{ // Jadwal
  const opsi = (await tdnC.req('lsp/opsi')).json;
  const body = { skema_id: opsi.skema[0].id, tuk_id: opsi.tuk[0].id, tanggal: tgl(75), jam: '08.00–16.00', metode: 'Tatap muka', kuota: 10, status: 'dibuka', catatan: TAG };
  const c = await tdnC.req('lsp/jadwal', body); const id = c.json.id;
  const find = async () => (await tdnC.req('lsp/jadwal')).json.items.find(j => j.id === id);
  check('Jadwal: buat', c.status === 201, c.json);
  check('Jadwal: baca', (await find())?.kuota === 10);
  check('Jadwal: ubah', ok2(await tdnC.req('lsp/jadwal', { ...body, id, kuota: 12 })) && (await find())?.kuota === 12);
  check('Jadwal: hapus', (await tdnC.req('lsp/jadwal/hapus', { id })).status === 200 && !(await find()));
}
{ // CRM
  const body = { nama: 'Prospek Uji', organisasi: 'PT ' + TAG, kontak: '0812', sumber: 'Pameran', tahap: 'baru', nilai: 5000000, catatan: '', followup: tgl(7) };
  const c = await mktC.req('lsp/crm', body); const id = c.json.id;
  const find = async () => (await mktC.req('lsp/crm')).json.items.find(x => x.id === id);
  check('CRM: buat', c.status === 201, c.json);
  check('CRM: ubah + pindah tahap', ok2(await mktC.req('lsp/crm', { ...body, id, nilai: 6000000 })) && (await mktC.req('lsp/crm/tahap', { id, tahap: 'proposal' })).status === 200 && (await find())?.tahap === 'proposal');
  check('CRM: hapus', (await mktC.req('lsp/crm/hapus', { id })).status === 200 && !(await find()));
}
{ // Mutu
  const body = { jenis: 'Audit internal', judul: 'Audit ' + TAG, deskripsi: '', status: 'terbuka', pic: 'Manajer Mutu', tenggat: tgl(30) };
  const c = await tdnC.req('lsp/mutu', body); const id = c.json.id;
  const find = async () => (await tdnC.req('lsp/mutu')).json.items.find(x => x.id === id);
  check('Mutu: buat', c.status === 201, c.json);
  check('Mutu: ubah', ok2(await tdnC.req('lsp/mutu', { ...body, id, status: 'proses' })) && (await find())?.status === 'proses');
  check('Mutu: hapus', (await tdnC.req('lsp/mutu/hapus', { id })).status === 200 && !(await find()));
}
{ // Sarana & prasarana
  const mine = (await tukC.req('tuk/sarpras')).json.items; const tukId = mine[0]?.tuk_id;
  const c = await tukC.req('tuk/sarpras', { tuk_id: tukId, nama: 'Alat ' + TAG, jumlah: 5, kondisi: 'baik', catatan: '' }); const id = c.json.id;
  const find = async () => (await tukC.req('tuk/sarpras')).json.items.find(x => x.id === id);
  check('Sarpras: buat', c.status === 201, c.json);
  check('Sarpras: ubah', ok2(await tukC.req('tuk/sarpras', { id, nama: 'Alat ' + TAG, jumlah: 4, kondisi: 'perlu perbaikan', catatan: 'uji' })) && (await find())?.jumlah === 4);
  check('Sarpras: hapus', (await tukC.req('tuk/sarpras/hapus', { id })).status === 200 && !(await find()));
}
{ // Pustaka SKKNI
  const kode = 'UJI.' + TAG;
  const find = async () => (await supC.req('skkni?q=' + encodeURIComponent(kode))).json.items.find(u => u.kode === kode);
  check('SKKNI: buat', (await supC.req('skkni', { kode, judul: 'Unit uji langsung', sektor: 'TIK' })).status === 201);
  check('SKKNI: ubah', (await supC.req('skkni', { kode, judul: 'Unit uji langsung diubah', sektor: 'Data', ubah: true })).status === 200 && (await find())?.sektor === 'Data');
  check('SKKNI: hapus', (await supC.req('skkni/hapus', { kode })).status === 200 && !(await find()));
}
{ // Etalase (listing draf)
  const base = { tipe: 'pelatihan', judul: 'Kelas ' + TAG, bidang: 'TIK', kota: 'Jakarta', format: 'Online', harga: 250000, deskripsi: 'Kelas uji langsung yang cukup panjang deskripsinya.', status: 'draf' };
  const c = await mktC.req('listings', base); const id = c.json.id;
  const find = async () => (await mktC.req('listings')).json.items.find(l => l.id === id);
  check('Listing: buat draf', c.status === 201, c.json);
  check('Listing: ubah', ok2(await mktC.req('listings', { ...base, id, harga: 300000 })) && (await find())?.harga === 300000);
  check('Listing: hapus', (await mktC.req('listings/hapus', { id })).status === 200 && !(await find()));
}
{ // Blog (draf)
  const konten = 'Artikel uji langsung untuk memastikan CMS blog bekerja di situs demo. '.repeat(6);
  const c = await mktC.req('blog', { judul: 'Artikel ' + TAG, kategori: 'Berita', konten, ringkasan: 'Ringkasan uji.', tags: 'uji', status: 'draf' }); const id = c.json.id;
  const find = async () => (await mktC.req('blog')).json.items.find(p => p.id === id);
  check('Blog: buat draf', c.status === 201, c.json);
  check('Blog: ubah', ok2(await mktC.req('blog', { id, judul: 'Artikel ' + TAG + ' diubah', kategori: 'Berita', konten, status: 'draf' })) && (await find())?.judul.endsWith('diubah'));
  check('Blog: hapus', (await mktC.req('blog/hapus', { id })).status === 200 && !(await find()));
}
{ // Profil asesi (ubah lalu kembalikan)
  const r = C.asesi; const p = (await r.req('profile')).json;
  const u = await r.req('profile', { nama: p.nama, tanggal_lahir: p.tanggal_lahir, jenis_kelamin: p.jenis_kelamin, no_hp: '081299900011', consent_marketing: !!p.consent_marketing });
  check('Profil asesi: ubah nomor HP', u.status === 200 && (await r.req('profile')).json.no_hp === '081299900011', u.json);
  const b = await r.req('profile', { nama: p.nama, tanggal_lahir: p.tanggal_lahir, jenis_kelamin: p.jenis_kelamin, no_hp: p.no_hp, consent_marketing: !!p.consent_marketing });
  check('Profil asesi: dikembalikan', b.status === 200 && (await r.req('profile')).json.no_hp === p.no_hp, b.json);
}

{ // Pengguna (staf LSP): buat → baca → ubah nama/peran → nonaktif/aktif. Baris dibersihkan skrip pelaksana.
  const email = `staf.${TAG.toLowerCase()}@contoh.id`;
  const c = await tdnC.req('users', { email, nama: 'Staf Uji Langsung', role: 'keuangan', password: 'Staf-Awal-' + TAG });
  const find = async () => (await tdnC.req('users')).json.items.find(u => u.email === email);
  check('Pengguna: buat staf', c.status === 201, c.json);
  const u0 = await find(); const mid = u0?.membership_id;
  check('Pengguna: baca', u0?.role === 'keuangan' && u0.bisa_ubah === true, u0);
  check('Pengguna: ubah nama & peran', (await tdnC.req('users/ubah', { membership_id: mid, nama: 'Staf Uji Diubah', role: 'marketing' })).status === 200 && (await find())?.nama === 'Staf Uji Diubah' && (await find())?.role === 'marketing');
  check('Pengguna: nonaktifkan lalu aktifkan', (await tdnC.req('users/status', { membership_id: mid, status: 'nonaktif' })).status === 200 && (await find())?.status === 'nonaktif'
    && (await tdnC.req('users/status', { membership_id: mid, status: 'aktif' })).status === 200 && (await find())?.status === 'aktif');
  check('Pengguna: LSP lain tidak bisa mengubah (404)', (await C['admin.pbi'].req('users/ubah', { membership_id: mid, nama: 'Coba Dibajak' })).status === 404);
  const s2 = new Client(); await s2.req('auth/me');
  check('Pengguna: staf baru bisa masuk', (await s2.req('auth/login', { email, password: 'Staf-Awal-' + TAG })).status === 200);
  await s2.req('auth/logout', {});
}
{ // Tiket support: buat → balas → tutup. Baris dibersihkan skrip pelaksana.
  const c = await tdnC.req('tiket', { judul: 'Tiket ' + TAG, isi: 'Mohon bantuan uji langsung.', prioritas: 'normal' }); const id = c.json.id;
  const find = async cl => (await cl.req('tiket')).json.items.find(t => t.id === id);
  check('Tiket: buat', c.status === 201, c.json);
  check('Tiket: Admin Platform membalas → proses', (await supC.req('tiket/balas', { id, isi: 'Sedang kami cek.' })).status === 200 && (await find(supC))?.status === 'proses');
  check('Tiket: tutup → selesai', (await supC.req('tiket/balas', { id, isi: '', status: 'selesai' })).status === 200 && (await find(tdnC))?.status === 'selesai');
}
{ // Pengaturan LSP: ubah lalu kembalikan.
  const cur = (await tdnC.req('lsp/pengaturan')).json.lsp;
  const body = { nama: cur.nama, kota: cur.kota, alamat: 'Jl. Uji ' + TAG, telepon: cur.telepon || '', email: cur.email || '', website: cur.website || '', deskripsi: cur.deskripsi || '', honor_per_asesi: cur.honor_per_asesi };
  check('Pengaturan LSP: ubah alamat', (await tdnC.req('lsp/pengaturan', body)).status === 200 && (await tdnC.req('lsp/pengaturan')).json.lsp.alamat === 'Jl. Uji ' + TAG);
  check('Pengaturan LSP: dikembalikan', (await tdnC.req('lsp/pengaturan', { ...body, alamat: cur.alamat || '' })).status === 200 && (await tdnC.req('lsp/pengaturan')).json.lsp.alamat === (cur.alamat || ''));
}
{ // Perangkat asesmen (MUK): draf baru disimpan asesor → dibaca → diubah. Draf dibersihkan skrip pelaksana.
  const daftar = (await C.asesor.req('muk/perangkat')).json.items || [];
  let target = null;
  for (const s of daftar) { const d = (await C.asesor.req('muk/perangkat?skema_id=' + s.id)).json; const it = (d.items || []).find(x => x.kode === 'IA.02'); if (it && (!it.terbaru || it.terbaru.status === 'valid')) { target = s; break; } }
  if (!target) console.log('  info: semua skema sudah punya draf IA.02, uji perangkat dilewati');
  else {
    const c = await C.asesor.req('muk/perangkat/simpan', { skema_id: target.id, kode: 'IA.02', data: { items: [{ kelompok: 'Kelompok ' + TAG, skenario: 'Skenario uji langsung ' + TAG, peralatan: 'Komputer', durasi: '60 menit' }] } });
    check('Perangkat: simpan draf IA.02', c.status === 200 && c.json.id > 0, c.json);
    const d = (await C.asesor.req('muk/perangkat/detail?skema_id=' + target.id + '&kode=IA.02')).json;
    check('Perangkat: baca draf', d.perangkat?.status === 'draf' && d.data?.items?.[0]?.kelompok === 'Kelompok ' + TAG, d.perangkat);
    const u = await C.asesor.req('muk/perangkat/simpan', { skema_id: target.id, kode: 'IA.02', data: { items: [{ kelompok: 'Kelompok ' + TAG + ' diubah', skenario: 'Skenario uji langsung ' + TAG, peralatan: 'Komputer', durasi: '60 menit' }] } });
    const d2 = (await C.asesor.req('muk/perangkat/detail?skema_id=' + target.id + '&kode=IA.02')).json;
    check('Perangkat: ubah draf (id tetap)', u.status === 200 && u.json.id === c.json.id && (d2.data?.items?.[0]?.kelompok || '').endsWith('diubah'), u.json);
  }
}
{ // Formulir paperless: berkas asesi dan formulir dibaca dari database.
  const pm = (await C.asesi.req('asesi/permohonan')).json.items || [];
  const p = pm.find(x => ['siap_uji', 'kompeten', 'belum_kompeten', 'pra_asesmen', 'menunggu_pleno'].includes(x.status)) || pm[0];
  const b = p ? await C.asesi.req('muk/berkas?permohonan_id=' + p.id) : null;
  check('Formulir: berkas asesi terbaca', !!b && b.status === 200 && (b.json.items || []).length > 0, b?.json);
  const f = b && (b.json.items || []).find(x => x.kode === 'APL.01');
  const r = f ? await C.asesi.req('muk/formulir?permohonan_id=' + p.id + '&kode=APL.01') : null;
  check('Formulir: FR.APL.01 terbaca dengan tanda tangan', !!r && r.status === 200 && r.json.ttd && typeof r.json.ttd === 'object' && Object.keys(r.json.ttd).length > 0, r?.json && { status: r.json.status, ttd: r.json.ttd });
  const pl = await C['komite.tdn'].req('pleno');
  check('Pleno: Komite melihat berkas menunggu keputusan', pl.status === 200 && Array.isArray(pl.json.items), pl.json);
  const bd = await C['mutu.tdn'].req('banding');
  check('Banding: Manajer Mutu melihat daftar', bd.status === 200 && Array.isArray(bd.json.items), bd.json);
}
{ // Notifikasi: preferensi diubah lalu dikembalikan.
  const r = C.asesi; const s0 = (await r.req('notifications/settings')).json;
  check('Notifikasi: ubah preferensi email', (await r.req('notifications/settings', { email_on: !s0.email_on, wa_on: false, wa_number: '', wa_consent: false })).status === 200 && (await r.req('notifications/settings')).json.email_on === !s0.email_on);
  check('Notifikasi: dikembalikan', (await r.req('notifications/settings', { email_on: s0.email_on, wa_on: false, wa_number: '', wa_consent: false })).status === 200 && (await r.req('notifications/settings')).json.email_on === s0.email_on);
}

console.log('Tidak ada data uji yang tertinggal');
{
  const sisa = [];
  const cek = async (c, r, f) => { const it = ((await c.req(r)).json.items || []).filter(f); if (it.length) sisa.push(r + ':' + it.length); };
  await cek(tdnC, 'lsp/skema', s => (s.kode || '').includes(TAG)); await cek(tdnC, 'lsp/tuk', t => t.nama.includes(TAG)); await cek(tdnC, 'lsp/jadwal', j => (j.catatan || '').includes(TAG));
  await cek(mktC, 'lsp/crm', x => (x.organisasi || '').includes(TAG)); await cek(tdnC, 'lsp/mutu', x => x.judul.includes(TAG)); await cek(tukC, 'tuk/sarpras', x => x.nama.includes(TAG));
  await cek(mktC, 'listings', l => l.judul.includes(TAG)); await cek(mktC, 'blog', p => p.judul.includes(TAG));
  check('data uji yang punya aksi hapus sudah terhapus', sisa.length === 0, sisa.join(', '));
  console.log('  info: pengguna/tiket/perangkat uji (tanpa aksi hapus di aplikasi) dibersihkan skrip pelaksana, tanda: ' + TAG);
  console.log('TAG=' + TAG);
}
for (const c of Object.values(C)) await c.req('auth/logout', {});
console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
