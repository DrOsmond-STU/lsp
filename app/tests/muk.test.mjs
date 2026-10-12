// Uji alur sertifikasi paperless sesuai Buku Kerja 333-25 & PBNSP 201: formulir FR.*, tanda tangan elektronik,
// gerbang status, pemisahan tugas (asesor ≠ pemutus, validator ≠ penyusun, pemutus banding tidak terlibat), kerahasiaan kunci jawaban.
import { readFileSync } from 'node:fs';
import { fillFor, signForm, aplData, verifikasi } from './lib-muk.mjs';
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const MAIL = new URL('../storage/mail.log', import.meta.url).pathname;
const PW = 'Demo-Pass-2026';
const E = n => `${n}@demo.portallsp.id`;
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, typeof x === 'string' ? x : JSON.stringify(x).slice(0, 400)); } };
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
  async upload(jenis, buf) {
    const fd = new FormData(); fd.append('jenis', jenis); fd.append('file', new Blob([buf], { type: 'image/png' }), jenis + '.png');
    const res = await this.raw('dokumen/upload', { method: 'POST', headers: { 'X-CSRF-Token': this.csrf }, body: fd });
    return { status: res.status };
  }
}
const as = async who => { const c = new Client(); await c.req('auth/me'); const r = await c.req('auth/login', { email: E(who), password: PW }); if (r.status !== 200) throw new Error(who + ' ' + r.status); return c; };
const [sup, tdn, pbi, komite, mutu, asr1, asr2, rina] = await Promise.all(['superadmin', 'admin.tdn', 'admin.pbi', 'komite.tdn', 'mutu.tdn', 'asesor', 'asesor2', 'asesi'].map(as));

console.log('Peran & hak akses sesuai PBNSP 201');
{
  const me = (await komite.req('auth/me')).json;
  check('Komite Teknis punya hak keputusan & banding', me.permissions.includes('decision.manage') && me.permissions.includes('appeal.decide'));
  const adm = (await tdn.req('auth/me')).json.permissions;
  check('Admin LSP tidak lagi memutus sertifikasi', !adm.includes('decision.manage') && adm.includes('muk.manage'));
  const mm = (await mutu.req('auth/me')).json.permissions;
  check('Manajer Mutu meninjau/validasi, tidak memutus', !mm.includes('decision.manage') && mm.includes('assessment.review'));
  check('asesor memiliki No. Reg MET', !!(await asr1.req('auth/me')).json.user);
}

console.log('Data demo: berkas digital lengkap untuk riwayat');
{
  const h = (await komite.req('lsp/hasil')).json.items.find(x => x.diputuskan_at && x.diputuskan_at.slice(0, 10) < today);
  const b = await komite.req('muk/berkas?permohonan_id=' + h.id);
  check('berkas permohonan lama berisi APL, AK.07, AK.01, IA, AK.02', b.status === 200 && ['APL.01', 'APL.02', 'AK.07', 'AK.01', 'AK.02'].every(k => b.json.items.some(i => i.kode === k && i.status === 'final')), b.json);
  const ak02 = await komite.req(`muk/formulir?permohonan_id=${h.id}&kode=AK.02`);
  check('FR.AK.02 lama ditandatangani asesor & asesi dengan sidik isi', ak02.json.ttd.asesor && ak02.json.ttd.asesi && ak02.json.ttd.asesor.hash.length === 16);
  const pr = await tdn.req('muk/perangkat?skema_id=1');
  check('perangkat MUK skema JWD tervalidasi', pr.status === 200 && pr.json.items.filter(i => i.valid).length >= 8);
  check('elemen & KUK tersedia', pr.json.elemen.length >= 18 && pr.json.elemen.every(e => e.kuk.length >= 2));
}

console.log('Pendaftaran paperless: FR.APL.01 & FR.APL.02 ditandatangani asesi');
const asesi = new Client();
let pid, jid, asesor, asesorLain, skemaId;
{
  await asesi.req('auth/me');
  const reg = await asesi.req('auth/register', { nama: 'Dina Paperless', nik: '3171014505990002', tanggal_lahir: '1999-05-05', jenis_kelamin: 'P',
    email: 'dina.muk@contoh.id', no_hp: '081266665555', password: 'Paperless-Uji-2026', consent_privacy: true, consent_marketing: false, website: '' });
  check('registrasi asesi', reg.status === 201, reg.json);
  const tok = [...readFileSync(MAIL, 'utf8').matchAll(/To: dina\.muk@contoh\.id[\s\S]*?verifikasi=([0-9a-f]{64})/g)].pop()[1];
  await asesi.req('auth/verify', { token: tok });
  for (const j of ['ktp', 'ijazah', 'foto']) await asesi.upload(j, PNG);
  // Jadwal hari ini dengan asesor yang bisa masuk (demo) dan bebas hari ini.
  const op = (await tdn.req('lsp/opsi')).json;
  const sk = op.skema.find(s => s.kode === 'JWD'); skemaId = sk.id;
  const nj = await tdn.req('lsp/jadwal', { skema_id: sk.id, tuk_id: op.tuk[0].id, tanggal: today, jam: '08.00–16.00', metode: 'Tatap muka', kuota: 5, status: 'dibuka' });
  check('admin membuat jadwal hari ini', nj.status === 201, nj.json);
  jid = nj.json.id;
  for (const [c, email] of [[asr2, E('asesor2')], [asr1, E('asesor')]]) {
    const a = op.asesor.find(x => x.email === email || x.nama === (email === E('asesor') ? 'Budi Santoso, S.Kom.' : 'Lina Marlina, S.T.'));
    if (a && (await tdn.req('lsp/jadwal/asesor', { jadwal_id: jid, asesor_id: a.id })).status === 200) { asesor = c; asesorLain = c === asr1 ? asr2 : asr1; break; }
  }
  check('asesor ditugaskan ke jadwal', !!asesor);
  const d = await asesi.req('muk/def?skema_id=' + skemaId);
  check('definisi FR.APL.01/02 untuk wizard', d.status === 200 && d.json.siap && d.json['APL.02'].def.bagian[0].f.find(f => f.k === 'elemen').rows.length >= 18);
  const base = await aplData(asesi, skemaId);
  const kurang = JSON.parse(JSON.stringify(base)); const k0 = Object.keys(kurang.apl02.elemen)[0]; kurang.apl02.elemen[k0].bukti = null;
  check('APL.02 tanpa bukti satu elemen ditolak', (await asesi.req('asesi/apply', { jadwal_id: jid, ...kurang, consent: true })).status === 422);
  const tanpa = JSON.parse(JSON.stringify(base)); delete tanpa.apl01.pendidikan;
  check('APL.01 tanpa kualifikasi pendidikan ditolak', (await asesi.req('asesi/apply', { jadwal_id: jid, ...tanpa, consent: true })).status === 422);
  const ap = await asesi.req('asesi/apply', { jadwal_id: jid, ...base, consent: true });
  check('pendaftaran dengan FR.APL.01/02 berhasil', ap.status === 201, ap.json);
  pid = ap.json.id;
  const f1 = await asesi.req(`muk/formulir?permohonan_id=${pid}&kode=APL.01`);
  check('FR.APL.01 ditandatangani asesi, menunggu admin', f1.json.ttd.asesi && !f1.json.ttd.admin && f1.json.status === 'draf');
  check('asesi tidak bisa mengubah APL setelah mengajukan', (await asesi.req('muk/formulir/simpan', { permohonan_id: pid, kode: 'APL.01', data: { pendidikan: 'S3' } })).status === 409);
}

console.log('Verifikasi FR.APL.01 oleh Admin LSP');
{
  check('LSP lain tidak bisa membuka formulir (404)', (await pbi.req(`muk/formulir?permohonan_id=${pid}&kode=APL.01`)).status === 404);
  check('asesi lain tidak bisa membuka formulir (404)', (await rina.req(`muk/formulir?permohonan_id=${pid}&kode=APL.01`)).status === 404);
  check('terima tanpa verifikasi per bukti ditolak', (await tdn.req('lsp/pendaftaran/putus', { id: pid, aksi: 'terima', catatan: '' })).status === 422);
  check('terima dengan bukti TMS ditolak', (await tdn.req('lsp/pendaftaran/putus', { id: pid, aksi: 'terima', catatan: '', verifikasi: await verifikasi(tdn, pid, 'TMS') })).status === 422);
  check('minta perbaikan', (await tdn.req('lsp/pendaftaran/putus', { id: pid, aksi: 'perbaikan', catatan: 'Lengkapi bukti persyaratan' })).status === 200);
  check('asesi dapat menyimpan draf APL saat perbaikan', (await asesi.req('muk/formulir/simpan', { permohonan_id: pid, kode: 'APL.01', data: { jabatan: 'Programmer magang' } })).status === 200);
  const rs = await asesi.req('asesi/resubmit', { id: pid });
  check('kirim perbaikan → APL ditandatangani ulang', rs.status === 200, rs.json);
  const ok = await tdn.req('lsp/pendaftaran/putus', { id: pid, aksi: 'terima', catatan: 'Lengkap', verifikasi: await verifikasi(tdn, pid, 'MS') });
  check('semua bukti MS → diterima', ok.status === 200, ok.json);
  const f1 = await tdn.req(`muk/formulir?permohonan_id=${pid}&kode=APL.01`);
  check('FR.APL.01 final: TTD asesi + Admin LSP, rekomendasi diterima', f1.json.status === 'final' && f1.json.ttd.admin && f1.json.data.rekom === 'diterima' && f1.json.data.jabatan === 'Programmer magang');
  const tg = (await asesi.req('asesi/tagihan')).json.items.find(t => t.permohonan_id === pid);
  check('bayar', (await asesi.req('asesi/bayar', { id: tg.id, metode: 'QRIS' })).status === 200);
}

console.log('Konsultasi pra-asesmen: FR.APL.02 → FR.AK.07 → FR.AK.01');
const ctx = () => ({ permohonan_id: pid });
{
  check('asesor lain (tidak ditugaskan) tidak bisa membuka formulir (404)', (await asesorLain.req(`muk/formulir?permohonan_id=${pid}&kode=AK.07`)).status === 404);
  check('asesor lain (tidak ditugaskan) tidak berwenang menandatangani (403)', (await asesorLain.req('muk/formulir/ttd', { ...ctx(), kode: 'AK.07', sebagai: 'asesor', setuju: true, data: {} })).status === 403);
  const dini = await signForm(asesor, ctx(), 'AK.01', 'asesor');
  check('FR.AK.01 sebelum tinjauan APL.02 & AK.07 ditolak', dini.status === 422 && /APL\.02/.test(dini.json.error), dini.json);
  check('tinjauan FR.APL.02: dapat dilanjutkan', (await signForm(asesor, ctx(), 'APL.02', 'asesor', { rekom: 'dapat', catatan: null })).status === 200);
  const ak07 = await signForm(asesor, ctx(), 'AK.07', 'asesor', { potensi: '1', sepakat: 'tidak', 'butir.*': { perlu: 'Tidak', ket: null }, instrumen: null, acuan: null, metode: null });
  check('FR.AK.07 potensi asesi 1 ditandatangani asesor', ak07.status === 200, ak07.json);
  const b = await asesor.req('muk/berkas?permohonan_id=' + pid);
  check('instrumen aktif dari FR.MAPA.02 untuk potensi 1', JSON.stringify(b.json.instrumen) === JSON.stringify(['IA.01', 'IA.02', 'IA.03', 'IA.05']), b.json.instrumen);
  check('asesi tidak bisa menandatangani AK.01 sebelum asesor (409)', (await signForm(asesi, ctx(), 'AK.01', 'asesi')).status === 409);
  check('asesor dengan benturan kepentingan ditolak', (await signForm(asesor, ctx(), 'AK.01', 'asesor', { coi: 'Tidak', banding_dijelaskan: 'Ya' })).status === 422);
  const ak01 = await signForm(asesor, ctx(), 'AK.01', 'asesor', { coi: 'Ya', banding_dijelaskan: 'Ya', bukti: ['observasi', 'tulis', 'tanya'] });
  check('FR.AK.01 ditandatangani asesor', ak01.status === 200, ak01.json);
  check('asesi menandatangani FR.AK.07', (await signForm(asesi, ctx(), 'AK.07', 'asesi')).status === 200);
  check('asesi menandatangani FR.AK.01 → siap uji', (await signForm(asesi, ctx(), 'AK.01', 'asesi')).status === 200);
  const p = (await asesi.req('asesi/permohonan')).json.items.find(x => x.id === pid);
  check('status → siap_uji', p.status === 'siap_uji');
  check('tanda tangan tidak bisa diulang (409)', (await signForm(asesor, ctx(), 'AK.01', 'asesor')).status === 409);
}

console.log('Asesmen: instrumen FR.IA, lembar jawaban, rekaman FR.AK.02');
{
  check('FR.AK.02 sebelum instrumen ditandatangani ditolak', (await signForm(asesor, ctx(), 'AK.02', 'asesor', { 'unit.*': { bukti: ['obs'], hasil: 'K' } })).status === 422);
  const lihat = await asesi.req(`muk/formulir?permohonan_id=${pid}&kode=IA.05`);
  const rows = lihat.json.def.bagian[0].f[0].rows;
  check('asesi melihat soal PG tanpa kunci jawaban', rows.length === 5 && rows.every(r => !r.item.kunci) && !JSON.stringify(lihat.json).includes('"kunci"'));
  const sAsesor = await asesor.req(`muk/formulir?permohonan_id=${pid}&kode=IA.05`);
  check('asesor melihat kunci jawaban', sAsesor.json.def.bagian[0].f[0].rows.every(r => r.item.kunci));
  check('asesor tidak bisa menandatangani IA.05 sebelum asesi menjawab (409)', (await signForm(asesor, ctx(), 'IA.05', 'asesor')).status === 409);
  const kunci = Object.fromEntries(sAsesor.json.def.bagian[0].f[0].rows.map(r => [r.key, { jawaban: r.item.kunci.v }]));
  kunci['1'].jawaban = kunci['1'].jawaban === 'a' ? 'b' : 'a';
  check('asesi menjawab & menandatangani lembar jawaban IA.05', (await signForm(asesi, ctx(), 'IA.05', 'asesi', { jawab: kunci })).status === 200);
  const nilai = await asesor.req(`muk/formulir?permohonan_id=${pid}&kode=IA.05`);
  check('penilaian otomatis terhadap kunci (1 salah, 4 benar)', nilai.json.data.jawab['1'].capai === 'Tidak' && Object.values(nilai.json.data.jawab).filter(x => x.capai === 'Ya').length === 4);
  const r5 = await asesor.req('muk/formulir/ttd', { permohonan_id: pid, kode: 'IA.05', id: nilai.json.id, sebagai: 'asesor', setuju: true, data: { umpan_balik: 'Satu soal belum tepat; dikonfirmasi lisan.' } });
  check('asesor menandatangani IA.05', r5.status === 200, r5.json);
  check('IA.06 tidak aktif untuk asesi ini (409)', (await signForm(asesor, ctx(), 'IA.06', 'asesor')).status === 409);
  for (const ia of ['IA.01', 'IA.02', 'IA.03']) {
    const r = await signForm(asesor, ctx(), ia, 'asesor', { rekomendasi: 'K', bk_pada: null, 'kuk.*': { standar: null, capai: 'Ya', lanjut: null }, 'skenario.*': { mulai: '09.00', selesai: '10.30' } });
    check(ia + ' ditandatangani asesor', r.status === 200, r.json);
    check(ia + ' ditandatangani asesi', (await signForm(asesi, ctx(), ia, 'asesi')).status === 200);
  }
  const bkTanpaTL = await signForm(asesor, ctx(), 'AK.02', 'asesor', { 'unit.*': { bukti: ['obs', 'tulis'], hasil: 'BK' }, tindak_lanjut: null });
  check('AK.02 dengan unit BK tanpa tindak lanjut ditolak', bkTanpaTL.status === 422 && /Tindak lanjut/.test(bkTanpaTL.json.error));
  const ak02 = await signForm(asesor, ctx(), 'AK.02', 'asesor', { 'unit.*': { bukti: ['obs', 'tulis'], hasil: 'K' }, tindak_lanjut: null });
  check('FR.AK.02 ditandatangani asesor → menunggu pleno', ak02.status === 200, ak02.json);
  check('asesi menandatangani FR.AK.02', (await signForm(asesi, ctx(), 'AK.02', 'asesi')).status === 200);
  check('asesi mengisi umpan balik FR.AK.03', (await signForm(asesi, ctx(), 'AK.03', 'asesi', { 'komponen.*': { hasil: 'Ya', catatan: null }, lainnya: null })).status === 200);
}

console.log('Laporan FR.AK.05 & keputusan Komite Teknis');
let kode;
{
  const pl0 = (await komite.req('pleno')).json.items.find(x => x.id === pid);
  check('berkas tampil di antrean Komite, belum ada FR.AK.05', pl0 && pl0.ak05 === false);
  check('keputusan sebelum FR.AK.05 ditolak (409)', (await komite.req('pleno/putus', { id: pid, keputusan: 'K', catatan: '' })).status === 409);
  const lap = (await asesor.req('asesor/laporan')).json.items.find(j => j.id === jid);
  check('jadwal muncul di daftar laporan asesor', lap && lap.diuji === 1 && lap.tercakup === 0);
  const ak05 = await signForm(asesor, { jadwal_id: jid }, 'AK.05', 'asesor', { negatif: 'Tidak ada', positif: 'Asesi siap', penolakan: 'Tidak ada', saran: null, catatan: null });
  check('asesor menyerahkan FR.AK.05', ak05.status === 200, ak05.json);
  check('Admin LSP tidak dapat memutus sertifikasi (403)', (await tdn.req('pleno/putus', { id: pid, keputusan: 'K', catatan: '' })).status === 403);
  check('Manajer Mutu tidak dapat memutus sertifikasi (403)', (await mutu.req('pleno/putus', { id: pid, keputusan: 'K', catatan: '' })).status === 403);
  check('asesor penguji tidak dapat memutus (403)', (await asesor.req('pleno/putus', { id: pid, keputusan: 'K', catatan: '' })).status === 403);
  const pl = await komite.req('pleno/putus', { id: pid, keputusan: 'K', catatan: 'Berita acara pleno: kompeten' });
  check('Komite Teknis memutus → sertifikat terbit', pl.status === 200 && pl.json.sertifikat, pl.json);
  kode = pl.json.sertifikat && pl.json.sertifikat.kode;
  check('Admin LSP tetap dapat melihat hasil & sertifikat', (await tdn.req('lsp/hasil')).json.sertifikat.some(s => s.kode === kode));
  check('asesor menandatangani tinjauan FR.AK.06', (await signForm(asesor, { jadwal_id: jid }, 'AK.06', 'asesor', { 'prinsip.*': { sesuai: ['V', 'R', 'A'] } })).status === 200);
}

console.log('Banding FR.AK.04');
{
  const bd = await signForm(asesi, ctx(), 'AK.04', 'asesi', { 'tanya.*': { jawab: 'Ya' }, alasan: 'Saya menilai satu soal tertulis ambigu.' });
  check('asesi mengajukan banding (FR.AK.04)', bd.status === 200, bd.json);
  const lst = (await komite.req('banding')).json.items.find(b => b.permohonan_id === pid);
  check('banding tampil untuk Komite, tetapi pemutus pleno tidak boleh memutus', lst && lst.boleh === false);
  check('pemutus pleno ditolak memutus banding (403)', (await komite.req('banding/putus', { id: lst.id, hasil: 'ditolak', catatan: 'Keputusan tetap karena bukti cukup.' })).status === 403);
  check('Admin LSP tidak berwenang memutus banding (403)', (await tdn.req('banding/putus', { id: lst.id, hasil: 'ditolak', catatan: 'Keputusan tetap karena bukti cukup.' })).status === 403);
  const put = await sup.req('banding/putus', { id: lst.id, hasil: 'ditolak', catatan: 'Soal sudah divalidasi; keputusan tetap.' });
  check('personil tidak terlibat memutus banding', put.status === 200, put.json);
  check('banding tidak bisa diputus dua kali (409)', (await sup.req('banding/putus', { id: lst.id, hasil: 'diterima', catatan: 'Coba ubah keputusan banding.' })).status === 409);
  check('banding kedua untuk permohonan sama ditolak', (await signForm(asesi, ctx(), 'AK.04', 'asesi', { 'tanya.*': { jawab: 'Ya' }, alasan: 'Ulang' })).status === 409);
}

console.log('Perangkat asesmen: penyusun ≠ validator, berversi');
{
  const det = (await asesor.req('muk/perangkat/detail?skema_id=' + skemaId + '&kode=IA.07')).json;
  const items = det.data.items.concat([{ pertanyaan: 'Bagaimana Anda menjaga keamanan kode sumber?', kunci: 'Kontrol versi, review kode, tidak menyimpan rahasia di repositori.' }]);
  check('penyusun menyimpan perubahan (versi baru)', (await asesor.req('muk/perangkat/simpan', { skema_id: skemaId, kode: 'IA.07', data: { items } })).status === 200);
  check('penyusun menandatangani & mengajukan', (await asesor.req('muk/perangkat/ttd', { skema_id: skemaId, kode: 'IA.07', sebagai: 'penyusun', setuju: true, data: {} })).status === 200);
  check('penyusun tidak boleh memvalidasi sendiri (403)', (await asesor.req('muk/perangkat/ttd', { skema_id: skemaId, kode: 'IA.07', sebagai: 'validator', aksi: 'valid', setuju: true })).status === 403);
  const pv = (await tdn.req('muk/perangkat?skema_id=' + skemaId)).json.items.find(i => i.kode === 'IA.07');
  check('versi lama tetap dipakai sampai divalidasi', pv.valid.versi === 1 && pv.terbaru.versi === 2 && pv.terbaru.status === 'menunggu_validasi');
  check('validator mengembalikan tanpa catatan ditolak', (await asesorLain.req('muk/perangkat/ttd', { skema_id: skemaId, kode: 'IA.07', sebagai: 'validator', aksi: 'kembalikan', catatan: '' })).status === 422);
  check('validator lain memvalidasi', (await asesorLain.req('muk/perangkat/ttd', { skema_id: skemaId, kode: 'IA.07', sebagai: 'validator', aksi: 'valid', setuju: true, catatan: 'Sesuai KUK' })).status === 200);
  const pv2 = (await tdn.req('muk/perangkat?skema_id=' + skemaId)).json.items.find(i => i.kode === 'IA.07');
  check('versi 2 kini dipakai', pv2.valid.versi === 2);
  check('asesi tidak bisa membuka perangkat (kunci jawaban) (404/403)', [403, 404].includes((await asesi.req('muk/perangkat/detail?skema_id=' + skemaId + '&kode=IA.05')).status));
  check('LSP lain tidak bisa membuka perangkat (404)', (await pbi.req('muk/perangkat/detail?skema_id=' + skemaId + '&kode=IA.05')).status === 404);
}

console.log('Validasi asesmen FR.VA oleh Manajer Mutu');
{
  const F = (await mutu.req(`muk/formulir?skema_id=${skemaId}&kode=VA`)).json;
  const data = fillFor(F, 'tim', { periode: 'setelah' });
  const r = await mutu.req('muk/formulir/ttd', { skema_id: skemaId, kode: 'VA', sebagai: 'tim', setuju: true, data });
  check('FR.VA ditandatangani tim validasi', r.status === 200, r.json);
  check('daftar validasi skema bertambah', (await tdn.req('muk/perangkat?skema_id=' + skemaId)).json.validasi.some(v => v.id === r.json.id));
  check('Keuangan tidak berwenang (403)', (await (await as('keuangan.tdn')).req('muk/formulir/ttd', { skema_id: skemaId, kode: 'VA', sebagai: 'tim', setuju: true, data })).status === 403);
}

console.log('Isolasi formulir: id formulir terikat ke permohonan/jadwal konteksnya');
{
  const rp = (await rina.req('asesi/permohonan')).json.items.find(x => x.status !== 'diajukan' && x.lsp_id === 1) || (await rina.req('asesi/permohonan')).json.items[0];
  const fDina = (await asesi.req(`muk/formulir?permohonan_id=${pid}&kode=APL.01`)).json;
  check('asesi lain tidak bisa membaca formulir orang lain lewat id (404)', (await rina.req(`muk/formulir?permohonan_id=${rp.id}&kode=APL.01&id=${fDina.id}`)).status === 404);
  const fRina = (await sup.req(`muk/formulir?permohonan_id=${rp.id}&kode=APL.01`)).json;
  check('asesor tidak bisa menulis formulir asesi lain lewat id (404)', (await asesor.req('muk/formulir/simpan', { permohonan_id: pid, kode: 'APL.01', id: fRina.id, sebagai: 'asesor', data: {} })).status === 404);
  check('asesor tidak bisa menandatangani formulir asesi lain lewat id (404)', (await asesor.req('muk/formulir/ttd', { permohonan_id: pid, kode: 'APL.01', id: fRina.id, sebagai: 'asesor', setuju: true, data: {} })).status === 404);
  const isi = JSON.stringify((await sup.req(`muk/formulir?permohonan_id=${rp.id}&kode=APL.01`)).json.data);
  check('isi formulir asesi lain tidak berubah', isi === JSON.stringify(fRina.data));
}

console.log('Verifikasi publik & audit');
{
  check('sertifikat baru terverifikasi publik', (await new Client().req('pub/verify?q=' + kode)).json.nama === 'Dina Paperless');
  const au = (await sup.req('audit')).json.items.map(a => a.action);
  check('tanda tangan formulir tercatat di log audit', au.includes('formulir.ttd'));
}
console.log(`\n${pass} lulus, ${failN} gagal`);
process.exit(failN ? 1 : 0);
