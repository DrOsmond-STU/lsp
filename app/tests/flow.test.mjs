// Uji alur sertifikasi ujung-ke-ujung + isolasi akses untuk semua menu/fitur baru.
import { readFileSync } from 'node:fs';
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const MAIL = new URL('../storage/mail.log', import.meta.url).pathname;
const PW = 'Demo-Pass-2026';
const E = n => `${n}@demo.portallsp.id`;
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, typeof x === 'string' ? x : JSON.stringify(x).slice(0, 300)); } };
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
const today = new Date(Date.now() + 7 * 3600e3).toISOString().slice(0, 10);

class Client {
  constructor() { this.cookie = ''; this.csrf = ''; }
  async raw(route, init = {}) {
    const [r, qs] = route.split('?');
    const h = { Accept: 'application/json', ...(init.headers || {}) }; if (this.cookie) h.Cookie = this.cookie;
    const res = await fetch(`${BASE}/api.php?r=${encodeURIComponent(r)}${qs ? '&' + qs : ''}`, { ...init, headers: h });
    const sc = res.headers.get('set-cookie'); if (sc) { const m = sc.match(/lsp_sid=([^;]+)/); if (m) this.cookie = 'lsp_sid=' + m[1]; }
    return res;
  }
  async req(route, data) {
    const init = data === undefined ? {} : { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf }, body: JSON.stringify(data) };
    const res = await this.raw(route, init);
    let j = {}; try { j = await res.json(); } catch {} if (j.csrf) this.csrf = j.csrf; return { status: res.status, json: j };
  }
  async upload(jenis, buf, type = 'image/png', name = 'foto.png') {
    const fd = new FormData(); fd.append('jenis', jenis); fd.append('file', new Blob([buf], { type }), name);
    const res = await this.raw('dokumen/upload', { method: 'POST', headers: { 'X-CSRF-Token': this.csrf }, body: fd });
    let j = {}; try { j = await res.json(); } catch {} return { status: res.status, json: j };
  }
}
const as = async who => { const c = new Client(); await c.req('auth/me'); const r = await c.req('auth/login', { email: E(who), password: PW }); if (r.status !== 200) throw new Error(who + ' ' + r.status); return c; };
const [sup, tdn, pbi, mkt, kng, tuk, asr, rina] = await Promise.all(['superadmin', 'admin.tdn', 'admin.pbi', 'marketing.tdn', 'keuangan.tdn', 'tuk.kuningan', 'asesor', 'asesi'].map(as));

console.log('Semua endpoint menu bisa dibuka sesuai peran');
{
  const cases = [
    [rina, ['asesi/permohonan', 'asesi/tagihan', 'asesi/sertifikat', 'asesi/kelas', 'dokumen', 'chat/ruang', 'profile']],
    [asr, ['asesor/jadwal', 'asesor/pra', 'asesor/asesmen', 'pleno', 'asesor/riwayat', 'asesor/honor', 'chat/ruang']],
    [tdn, ['lsp/dashboard', 'lsp/pendaftaran', 'lsp/asesmen', 'lsp/hasil', 'lsp/jadwal', 'lsp/asesor', 'lsp/skema', 'lsp/tuk', 'lsp/opsi', 'lsp/alumni',
      'lsp/keuangan', 'lsp/laporan', 'lsp/crm', 'lsp/mutu', 'lsp/pengaturan', 'tuk/dashboard', 'tuk/pemohon', 'tuk/jadwal', 'tuk/sarpras', 'tuk/alumni', 'pleno', 'tiket', 'chat/ruang']],
    [tuk, ['tuk/dashboard', 'tuk/pemohon', 'tuk/jadwal', 'tuk/sarpras', 'tuk/alumni', 'chat/ruang']],
    [sup, ['platform/dashboard', 'platform/paket', 'skkni', 'tiket', 'audit', 'lsps', 'lsp/dashboard', 'lsp/pendaftaran', 'lsp/jadwal', 'lsp/skema', 'lsp/keuangan', 'tuk/dashboard', 'pleno']],
    [new Client(), ['pub/catalog', 'pub/jadwal', 'pub/lsp']],
  ];
  for (const [c, routes] of cases) {
    const bad = [];
    for (const r of routes) { const x = await c.req(r); if (x.status !== 200) bad.push(r + ':' + x.status + ' ' + (x.json.error || '')); }
    check('GET ' + routes.length + ' endpoint OK', bad.length === 0, bad.join(' | '));
  }
  check('Marketing tidak bisa buka keuangan (403)', (await mkt.req('lsp/keuangan')).status === 403);
  check('Keuangan tidak bisa buka pendaftaran (403)', (await kng.req('lsp/pendaftaran')).status === 403);
  check('Asesi tidak bisa buka data LSP (403)', (await rina.req('lsp/pendaftaran')).status === 403);
}

console.log('Portal publik');
let jwd, jadwalId;
{
  const cat = await new Client().req('pub/catalog');
  jwd = cat.json.items.find(i => i.judul === 'Junior Web Developer');
  check('katalog berisi skema asli + jumlah unit', jwd && jwd.unit === 9 && jwd.skema_id);
  const d = await new Client().req('pub/skema?id=' + jwd.id);
  check('detail skema: unit + jadwal terbuka', d.status === 200 && d.json.skema.units.length === 9 && d.json.jadwal.length >= 2);
  check('detail publik tidak membocorkan asesor', d.json.jadwal.every(j => !('asesor_id' in j) && !('asesor_nama' in j)));
  jadwalId = d.json.jadwal.find(j => j.sisa > 0 && j.tanggal > today).id;
  const lsp = await new Client().req('pub/lsp');
  check('daftar LSP publik', lsp.json.items.length === 4 && lsp.json.items.some(l => l.skema > 0));
  const v = await new Client().req('pub/verify?q=TDN7K3P9QX');
  check('verifikasi sertifikat contoh', v.json.found === true && v.json.status === 'berlaku' && v.json.nama === 'Rina Kartika Sari');
  check('kode salah → tidak ditemukan', (await new Client().req('pub/verify?q=XXXXXXXX')).json.found === false);
}

console.log('Asesi baru: daftar → verifikasi email → dokumen → daftar skema');
const asesi = new Client();
let pid;
{
  await asesi.req('auth/me');
  const reg = await asesi.req('auth/register', { nama: 'Bayu Pratama', nik: '3171011204980001', tanggal_lahir: '1998-04-12', jenis_kelamin: 'L',
    email: 'bayu.alur@contoh.id', no_hp: '081277778888', password: 'Kompeten-Alur-2026', consent_privacy: true, consent_marketing: false, website: '' });
  check('registrasi asesi', reg.status === 201, reg.json);
  const pre = await asesi.req('asesi/apply', { jadwal_id: jadwalId, apl02: {}, consent: true, tujuan: 'Sertifikasi' });
  check('belum verifikasi email → ditolak (403)', pre.status === 403);
  const tok = [...readFileSync(MAIL, 'utf8').matchAll(/To: bayu\.alur@contoh\.id[\s\S]*?verifikasi=([0-9a-f]{64})/g)].pop()[1];
  await asesi.req('auth/verify', { token: tok });
  const noDoc = await asesi.req('asesi/apply', { jadwal_id: jadwalId, apl02: {}, consent: true });
  check('tanpa dokumen wajib → ditolak (422)', noDoc.status === 422 && /KTP/.test(noDoc.json.error));
  check('unggah berkas palsu (teks berekstensi .png) ditolak', (await asesi.upload('ktp', Buffer.from('bukan gambar'), 'image/png', 'ktp.png')).status === 422);
  check('unggah > 2 MB ditolak', (await asesi.upload('ktp', Buffer.alloc(2200000, 1), 'image/png', 'besar.png')).status === 422);
  for (const j of ['ktp', 'ijazah', 'foto']) await asesi.upload(j, PNG);
  const docs = await asesi.req('dokumen');
  check('3 dokumen tersimpan', docs.json.items.length === 3);
  const d = await asesi.req('pub/skema?id=' + jwd.id);
  const allK = Object.fromEntries(d.json.skema.units.map(u => [u.id, 'K']));
  check('APL.02 tidak lengkap ditolak', (await asesi.req('asesi/apply', { jadwal_id: jadwalId, apl02: { [d.json.skema.units[0].id]: 'K' }, consent: true })).status === 422);
  check('tanpa persetujuan berbagi data ditolak', (await asesi.req('asesi/apply', { jadwal_id: jadwalId, apl02: allK, consent: false })).status === 422);
  const ap = await asesi.req('asesi/apply', { jadwal_id: jadwalId, apl02: allK, consent: true, tujuan: 'Sertifikasi' });
  check('pendaftaran skema berhasil', ap.status === 201, ap.json);
  pid = ap.json.id;
  check('tidak bisa daftar skema sama dua kali', (await asesi.req('asesi/apply', { jadwal_id: jadwalId, apl02: allK, consent: true })).status === 409);
  const me = await asesi.req('auth/me');
  check('asesi kini terdaftar di LSP itu', me.json.memberships.some(m => m.lsp_nama === 'LSP Teknologi Digital Nusantara'));
}

console.log('Isolasi data permohonan & dokumen');
{
  const pb = await pbi.req('lsp/pendaftaran');
  check('LSP lain tidak melihat pendaftaran ini', !pb.json.items.some(p => p.id === pid));
  check('LSP lain tidak bisa memprosesnya (404)', (await pbi.req('lsp/pendaftaran/putus', { id: pid, aksi: 'terima', catatan: '' })).status === 404);
  const docId = (await asesi.req('dokumen')).json.items[0].id;
  check('pemilik bisa unduh dokumennya', (await asesi.raw('dokumen/file?id=' + docId)).status === 200);
  check('Admin LSP tujuan bisa melihat dokumen', (await tdn.raw('dokumen/file?id=' + docId)).status === 200);
  check('Admin LSP lain tidak bisa (404)', (await pbi.raw('dokumen/file?id=' + docId)).status === 404);
  check('asesi lain tidak bisa (404)', (await rina.raw('dokumen/file?id=' + docId)).status === 404);
  check('tanpa login → 401', (await new Client().raw('dokumen/file?id=' + docId)).status === 401);
  const f = await asesi.raw('dokumen/file?id=' + docId);
  check('berkas dikirim dengan CSP sandbox', /sandbox/.test(f.headers.get('content-security-policy') || ''));
}

console.log('Verifikasi berkas → bayar');
{
  check('minta perbaikan tanpa catatan ditolak', (await tdn.req('lsp/pendaftaran/putus', { id: pid, aksi: 'perbaikan', catatan: '' })).status === 422);
  check('minta perbaikan', (await tdn.req('lsp/pendaftaran/putus', { id: pid, aksi: 'perbaikan', catatan: 'Ijazah kurang jelas' })).status === 200);
  check('asesi kirim perbaikan', (await asesi.req('asesi/resubmit', { id: pid })).status === 200);
  check('berkas diterima', (await tdn.req('lsp/pendaftaran/putus', { id: pid, aksi: 'terima', catatan: '' })).status === 200);
  check('diproses dua kali → 409', (await tdn.req('lsp/pendaftaran/putus', { id: pid, aksi: 'terima', catatan: '' })).status === 409);
  const tg = (await asesi.req('asesi/tagihan')).json.items.find(t => t.permohonan_id === pid);
  check('tagihan terbit sesuai biaya skema', tg && tg.jumlah === 750000 && tg.status === 'belum');
  check('asesi lain tidak bisa membayar tagihan ini', (await rina.req('asesi/bayar', { id: tg.id, metode: 'QRIS' })).status === 404);
  check('bayar', (await asesi.req('asesi/bayar', { id: tg.id, metode: 'QRIS' })).status === 200);
  check('bayar dua kali → 409', (await asesi.req('asesi/bayar', { id: tg.id, metode: 'QRIS' })).status === 409);
  const p = (await asesi.req('asesi/permohonan')).json.items.find(x => x.id === pid);
  check('status → pra-asesmen', p.status === 'pra_asesmen');
}

console.log('Pra-asesmen → asesmen → pleno → sertifikat');
let kode;
{
  const pra = await asr.req('asesor/pra');
  check('asesor yang ditugaskan melihat berkas', pra.json.items.some(x => x.id === pid));
  check('asesor bisa melihat dokumen asesi yang ditugaskan', (await asr.raw('dokumen/file?id=' + pra.json.items.find(x => x.id === pid).dokumen[0].id)).status === 200);
  check('pra "tidak" tanpa alasan ditolak', (await asr.req('asesor/pra/putus', { id: pid, rekom: 'tidak', catatan: '' })).status === 422);
  check('pra lanjut', (await asr.req('asesor/pra/putus', { id: pid, rekom: 'lanjut', catatan: 'Bukti cukup' })).status === 200);
  const units = (await asr.req('asesor/asesmen')).json.items.find(x => x.id === pid).units;
  const hasil = Object.fromEntries(units.map(u => [u.id, 'K']));
  check('asesmen sebelum tanggal uji ditolak (409)', (await asr.req('asesor/asesmen/simpan', { id: pid, hasil, catatan: '' })).status === 409);
  const jd = (await tdn.req('lsp/jadwal')).json.items.find(j => j.id === jadwalId);
  const up = await tdn.req('lsp/jadwal', { id: jd.id, skema_id: jd.skema_id, tuk_id: jd.tuk_id, tanggal: today, jam: jd.jam, metode: jd.metode, kuota: jd.kuota, status: 'dibuka' });
  check('admin memindah jadwal ke hari ini', up.status === 201, up.json);
  check('asesmen tersimpan', (await asr.req('asesor/asesmen/simpan', { id: pid, hasil, catatan: 'Semua unit kompeten' })).json.rekomendasi === 'K');
  check('asesor penguji tidak bisa memutuskan pleno sendiri (403)', (await asr.req('pleno/putus', { id: pid, keputusan: 'K', catatan: '' })).status === 403);
  check('berkas sendiri tidak muncul di antrean pleno asesor', !(await asr.req('pleno')).json.items.some(x => x.id === pid));
  check('LSP lain tidak bisa memutuskan (404)', (await pbi.req('pleno/putus', { id: pid, keputusan: 'K', catatan: '' })).status === 404);
  const pl = await tdn.req('pleno/putus', { id: pid, keputusan: 'K', catatan: 'Sesuai rekomendasi' });
  check('pleno oleh Admin LSP → sertifikat terbit', pl.status === 200 && pl.json.sertifikat && pl.json.sertifikat.nomor.startsWith('LSP-TDN/JWD/'));
  kode = pl.json.sertifikat.kode;
  check('sertifikat ada di dompet asesi', (await asesi.req('asesi/sertifikat')).json.items.some(s => s.kode === kode));
  check('verifikasi publik sertifikat baru', (await new Client().req('pub/verify?q=' + kode)).json.nama === 'Bayu Pratama');
  check('cetak sertifikat oleh pemilik', (await asesi.raw('sertifikat/cetak?id=' + (await asesi.req('asesi/sertifikat')).json.items[0].id)).status === 200);
  check('alumni LSP berisi asesi ini', (await tdn.req('lsp/alumni?q=Bayu')).json.items.length === 1);
  check('alumni tidak terlihat oleh LSP lain', (await pbi.req('lsp/alumni?q=Bayu')).json.items.length === 0);
  check('riwayat asesor mencatat', (await asr.req('asesor/riwayat')).json.items.some(x => x.id === pid));
  check('honor asesor dihitung', (await asr.req('asesor/honor')).json.total >= 150000);
}

console.log('Pleno oleh asesor lain (bukan penguji)');
{
  const q = await asr.req('pleno');
  const lain = q.json.items[0];
  check('antrean pleno asesor berisi berkas penguji lain', lain && lain.penguji_nama === 'Lina Marlina, S.T.');
  check('asesor memutuskan pleno berkas penguji lain', (await asr.req('pleno/putus', { id: lain.id, keputusan: 'K', catatan: '' })).status === 200);
  check('daftar "diuji sendiri" ditampilkan terpisah', q.json.diuji_sendiri.length >= 1);
}

console.log('Jadwal, master, CRM, mutu, keuangan, laporan, pengaturan');
{
  const op = (await tdn.req('lsp/opsi')).json;
  const nj = await tdn.req('lsp/jadwal', { skema_id: op.skema[0].id, tuk_id: op.tuk[0].id, tanggal: '2099-01-10', jam: '08.00–15.00', metode: 'Tatap muka', kuota: 10, status: 'dibuka' });
  check('buat jadwal', nj.status === 201);
  check('jadwal masa lalu ditolak', (await tdn.req('lsp/jadwal', { skema_id: op.skema[0].id, tanggal: '2001-01-01', metode: 'SJJ', kuota: 5 })).status === 422);
  check('tidak bisa pakai skema LSP lain', (await pbi.req('lsp/jadwal', { skema_id: op.skema[0].id, tanggal: '2099-01-10', metode: 'SJJ', kuota: 5 })).status === 422);
  const budi = op.asesor.find(a => a.nama.startsWith('Budi'));
  check('tugaskan asesor', (await tdn.req('lsp/jadwal/asesor', { jadwal_id: nj.json.id, asesor_id: budi.id })).status === 200);
  const nj2 = await tdn.req('lsp/jadwal', { skema_id: op.skema[1].id, tanggal: '2099-01-10', metode: 'SJJ', kuota: 5, status: 'dibuka' });
  check('bentrok jadwal asesor di tanggal sama ditolak (409)', (await tdn.req('lsp/jadwal/asesor', { jadwal_id: nj2.json.id, asesor_id: budi.id })).status === 409);
  const sk = await tdn.req('lsp/skema', { kode: 'UXJ', nama: 'UI/UX Designer Junior', bidang: 'TIK', kkni: 'Level 3', harga: 800000, status: 'aktif',
    units_text: 'J.62UXD00.001.1 | Melakukan riset pengguna\nJ.62UXD00.005.1 | Membuat prototipe antarmuka' });
  check('buat skema + unit', sk.status === 201);
  check('format unit salah ditolak', (await tdn.req('lsp/skema', { kode: 'X', nama: 'Skema X', bidang: 'TIK', kkni: '3', harga: 1, units_text: 'tanpa pemisah' })).status === 422);
  check('buat TUK', (await tdn.req('lsp/tuk', { nama: 'TUK Tempat Kerja PT Data Prima', jenis: 'Tempat Kerja', alamat: 'Jakarta Barat', kapasitas: 15, status: 'aktif' })).status === 201);
  const asr2 = await tdn.req('lsp/asesor', { email: 'asesor.baru@contoh.id', nama: 'Yogi Pradana', password: 'Asesor-Baru-2026' });
  check('tambah asesor baru', asr2.status === 201);
  check('tambah asesor yang sudah ada di LSP lain (multi-LSP)', (await pbi.req('lsp/asesor', { email: E('asesor') })).status === 201);
  check('akun staf tidak bisa dijadikan asesor', (await tdn.req('lsp/asesor', { email: E('keuangan.tdn') })).status === 409);
  const lead = await mkt.req('lsp/crm', { nama: 'Pak Andi', organisasi: 'PT Contoh', kontak: '0812', sumber: 'Website', tahap: 'baru', nilai: 1000000 });
  check('Marketing tambah lead', lead.status === 201);
  check('ubah tahap lead', (await mkt.req('lsp/crm/tahap', { id: lead.json.id, tahap: 'proposal' })).status === 200);
  check('lead LSP lain tidak bisa diubah (404)', (await pbi.req('lsp/crm/tahap', { id: lead.json.id, tahap: 'kalah' })).status === 404);
  check('catat temuan mutu', (await tdn.req('lsp/mutu', { jenis: 'Ketidaksesuaian', judul: 'Formulir FR.IA.01 belum ditandatangani', status: 'terbuka', pic: 'Asesor', tenggat: '2099-02-01' })).status === 201);
  const keu = await kng.req('lsp/keuangan');
  check('keuangan: ringkasan & tagihan', keu.status === 200 && keu.json.ringkasan.lunas_total > 0 && keu.json.items.length > 0);
  const belum = keu.json.items.find(t => t.status === 'belum');
  check('keuangan konfirmasi lunas manual', (await kng.req('lsp/keuangan/lunas', { id: belum.id })).status === 200);
  const lap = await tdn.req('lsp/laporan');
  check('laporan per skema', lap.json.items.some(i => i.kode === 'JWD' && i.kompeten >= 1));
  const csv = await tdn.raw('lsp/laporan?format=csv');
  check('unduh CSV laporan', csv.status === 200 && /text\/csv/.test(csv.headers.get('content-type')) && (await csv.text()).includes('Junior Web Developer'));
  const set = await tdn.req('lsp/pengaturan', { nama: 'LSP Teknologi Digital Nusantara', kota: 'Jakarta', alamat: 'Jl. Kuningan', telepon: '021', email: 'info@tdn.example', website: 'https://tdn.example', deskripsi: 'x', honor_per_asesi: 175000, lisensi_sampai: '2029-03-01' });
  check('ubah pengaturan LSP', set.status === 200 && set.json.lsp.honor_per_asesi === 175000);
  check('Admin Platform harus pilih LSP untuk pengaturan', (await sup.req('lsp/pengaturan')).status === 422);
  check('Admin Platform dengan filter LSP', (await sup.req('lsp/pengaturan?lsp=2')).json.lsp.nama === 'LSP Pariwisata Bahari Indonesia');
}

console.log('TUK & chat');
{
  const pm = await tuk.req('tuk/pemohon');
  check('Admin TUK hanya melihat pemohon TUK-nya', pm.json.items.length > 0 && pm.json.items.every(p => p.tuk_nama === 'TUK Sewaktu Kuningan'));
  const sp = await tuk.req('tuk/sarpras', { nama: 'Printer laser', jumlah: 1, kondisi: 'baik' });
  check('Admin TUK tambah sarpras', sp.status === 201);
  const other = (await tdn.req('tuk/sarpras')).json.items.find(s => s.tuk_nama !== 'TUK Sewaktu Kuningan');
  check('Admin TUK tidak bisa ubah sarpras TUK lain', !other || (await tuk.req('tuk/sarpras', { id: other.id, nama: 'x', jumlah: 1, kondisi: 'baik' })).status === 404);
  const room = (await rina.req('chat/ruang')).json.items.find(r => r.tuk_nama === 'TUK Sewaktu Kuningan');
  check('asesi peserta punya ruang chat', !!room);
  check('asesi kirim pesan', (await rina.req('chat/kirim', { jadwal_id: room.id, isi: 'Apakah boleh membawa laptop sendiri?' })).status === 201);
  check('Admin TUK membaca pesan', (await tuk.req('chat?jadwal_id=' + room.id)).json.items.some(m => m.isi.includes('laptop sendiri')));
  check('LSP lain tidak bisa membaca chat (404)', (await pbi.req('chat?jadwal_id=' + room.id)).status === 404);
}

console.log('Kelas & platform');
{
  const cat = (await new Client().req('pub/catalog')).json.items.filter(i => i.tipe === 'pelatihan');
  const gratis = cat.find(k => k.harga === 0), bayar = cat.find(k => k.harga > 0 && k.judul !== 'Kelas Persiapan Uji Junior Web Developer');
  check('daftar kelas gratis langsung aktif', (await rina.req('asesi/kelas/daftar', { listing_id: gratis.id })).json.perlu_bayar === false);
  check('daftar kelas berbayar → tagihan', (await rina.req('asesi/kelas/daftar', { listing_id: bayar.id })).json.perlu_bayar === true);
  const kelas = (await rina.req('asesi/kelas')).json.items;
  check('progres kelas aktif tersimpan', (await rina.req('asesi/kelas/progres', { id: kelas.find(k => k.status === 'aktif').id, progres: 100 })).status === 200);
  check('kelas belum dibayar tidak bisa progres', (await rina.req('asesi/kelas/progres', { id: kelas.find(k => k.status === 'menunggu_bayar').id, progres: 10 })).status === 409);
  const nl = await sup.req('platform/lsp', { nama: 'LSP Kesehatan Nusa Husada', jenis: 'P3', kota: 'Medan', kode: 'KNH', paket: 'Basic', admin_nama: 'Admin KNH', admin_email: 'admin@knh.example', admin_password: 'Awal-Knh-2026' });
  check('Admin Platform tambah LSP + Admin LSP', nl.status === 201);
  check('ubah paket LSP', (await sup.req('platform/paket', { id: nl.json.id, paket: 'Pro' })).json.items.find(i => i.id === nl.json.id).paket === 'Pro');
  check('nonaktifkan LSP', (await sup.req('platform/lsp/status', { id: nl.json.id, status: 'nonaktif' })).status === 200);
  check('Admin LSP tidak bisa tambah LSP (403)', (await tdn.req('platform/lsp', {})).status === 403);
  check('tambah unit SKKNI', (await sup.req('skkni', { kode: 'J.62UXD00.009.1', judul: 'Melakukan uji kegunaan', sektor: 'TIK' })).status === 201);
  check('cari SKKNI', (await tdn.req('skkni?q=kegunaan')).json.items.length === 1);
  const tk = await tdn.req('tiket', { judul: 'Tidak bisa unduh laporan', isi: 'Tombol unduh laporan BNSP tidak merespons.', prioritas: 'tinggi' });
  check('Admin LSP buat tiket', tk.status === 201);
  check('Admin Platform balas & proses tiket', (await sup.req('tiket/balas', { id: tk.json.id, isi: 'Sedang kami cek.', status: 'proses' })).status === 200);
  check('tiket LSP lain tidak terlihat', !(await pbi.req('tiket')).json.items.some(t => t.id === tk.json.id));
  check('log audit berisi aktivitas', (await sup.req('audit')).json.items.some(a => a.action === 'pleno.K'));
}
console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
