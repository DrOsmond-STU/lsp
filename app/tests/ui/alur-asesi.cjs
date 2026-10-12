// Uji browser alur sertifikasi paperless (Buku Kerja 333-25) ujung-ke-ujung:
// admin → asesi (FR.APL.01/02) → verifikasi → bayar → asesor (APL.02, AK.07, AK.01) → asesi tanda tangan → instrumen IA → AK.02 → AK.05 → Komite Teknis → sertifikat.
const { chromium } = require('playwright');
const fs = require('fs');
const OUT = require('os').tmpdir() + '/';
const BASE = process.env.BASE || 'http://127.0.0.1:8099/';
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
fs.writeFileSync(OUT + 'up.png', PNG);
let pass = 0, fail = 0;
const ok = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { fail++; console.log('  FAIL', n, x); } };
const today = new Date(); const T = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
const tglLabel = new Date(T + 'T00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
async function login(b, who) {
  const c = await b.newContext({ viewport: { width: 1280, height: 900 } }); const p = await c.newPage(); PAGES.push(p); p.errs = []; p.on('pageerror', e => p.errs.push(e.message)); p.on('dialog', d => d.accept());
  await p.goto(BASE, { waitUntil: 'networkidle' }); await p.click('.topbar [data-go="login"]'); await p.fill('#lg-email', who); await p.fill('#lg-pass', 'Demo-Pass-2026');
  await p.click('#loginForm button[type=submit]'); await p.waitForSelector('.side nav'); return p;
}
const nav = async (p, k) => { await p.click(`.side nav button[data-go-app="${k}"]`); await p.waitForTimeout(800); };
const toast = async p => (await p.textContent('#toast')) || '';
/** Buka formulir dari daftar periksa berkas, jalankan pengisian, lalu tandatangani. */
async function isiTtd(p, kode, isi) {
  await p.click(`button[data-x="mkForm"][data-kode="${kode}"]`); await p.waitForSelector('#mkDoc');
  if (isi) await isi(p);
  await p.check('#mk-setuju'); await p.click('button[data-x="mkSign"]'); await p.waitForTimeout(1100);
  const t = await toast(p);
  await p.click('button[data-x="mkBack"]'); await p.waitForTimeout(800);
  return t;
}
const pilihSemua = async (p, sel) => { for (const r of await p.locator(sel).all()) await r.check(); };
const isiSemua = async (p, sel, v) => { for (const r of await p.locator(sel).all()) await r.fill(v); };

const PAGES = [];
(async () => {
  const b = await chromium.launch();
  try {
  // 1. Admin membuat jadwal hari ini & menugaskan asesor
  const adm = await login(b, 'admin.tdn@demo.portallsp.id');
  await nav(adm, 'jadwalA'); await adm.click('[data-edit="new"]'); await adm.waitForSelector('#editor');
  await adm.selectOption('#editor select[name="skema_id"]', { label: 'JWD · Junior Web Developer' });
  await adm.selectOption('#editor select[name="tuk_id"]', { label: 'TUK Sewaktu Kuningan' });
  await adm.fill('#editor input[name="tanggal"]', T); await adm.fill('#editor input[name="kuota"]', '15');
  await adm.click('#editor button[type=submit]'); await adm.waitForTimeout(900);
  const sel = adm.locator('tbody tr', { hasText: tglLabel }).filter({ hasText: 'Junior Web Developer' }).locator('select[data-assign]').first();
  await sel.selectOption({ label: 'Budi Santoso, S.Kom.' }); await adm.waitForTimeout(900);
  ok('admin buat jadwal hari ini + tugaskan asesor', (await toast(adm)).includes('Asesor ditugaskan'));

  // 2. Registrasi asesi → wizard FR.APL.01 / FR.APL.02
  const cx = await b.newContext({ viewport: { width: 1280, height: 900 } }); const pub = await cx.newPage(); PAGES.push(pub); pub.errs = []; pub.on('pageerror', e => pub.errs.push(e.message)); pub.on('dialog', d => d.accept());
  await pub.goto(BASE, { waitUntil: 'networkidle' }); await pub.waitForTimeout(500);
  await pub.click('.topbar [data-go="cari"]'); await pub.waitForTimeout(400);
  await pub.locator('article.skema', { hasText: 'Junior Web Developer' }).click(); await pub.waitForTimeout(900);
  await pub.locator('label.sched-opt', { hasText: tglLabel }).first().click(); await pub.waitForTimeout(300);
  await pub.click('button[data-x="apStart"]'); await pub.waitForTimeout(500);
  await pub.click('#loginForm [data-go="daftar"]'); await pub.waitForTimeout(400);
  await pub.fill('#rg-nama', 'Bayu Pratama'); await pub.fill('#rg-nik', '3171011204980001'); await pub.fill('#rg-tgl', '1998-04-12'); await pub.check('input[name="rg-jk"][value="L"]');
  await pub.fill('#rg-email', 'bayu.ui@contoh.id'); await pub.fill('#rg-hp', '081277778888'); await pub.fill('#rg-pass', 'Kompeten-Ui-2026'); await pub.fill('#rg-pass2', 'Kompeten-Ui-2026'); await pub.check('#rg-privacy');
  await pub.click('#daftarForm button[type=submit]'); await pub.waitForTimeout(1500);
  const tok = [...fs.readFileSync(require('path').join(__dirname, '../../storage/mail.log'), 'utf8').matchAll(/To: bayu\.ui@contoh\.id[\s\S]*?verifikasi=([0-9a-f]{64})/g)].pop()[1];
  await pub.goto(BASE + '?verifikasi=' + tok, { waitUntil: 'networkidle' }); await pub.waitForTimeout(1200);
  await nav(pub, 'skema'); await pub.locator('.card', { hasText: 'Junior Web Developer' }).locator('button[data-x="apStart"]').click(); await pub.waitForTimeout(900);
  await pub.locator('label.sched-opt', { hasText: tglLabel }).first().click();
  await pub.click('[data-x="apNext"]'); await pub.waitForSelector('#apMk');
  ok('langkah 2 menampilkan FR.APL.01 (bagian 1–3)', (await pub.textContent('#apMk')).includes('Bagian 3 : Bukti Kelengkapan Pemohon'));
  await pub.click('[data-x="apNext"]'); await pub.waitForTimeout(300);
  ok('FR.APL.01 wajib diisi (pendidikan)', (await toast(pub)).includes('pendidikan'));
  await pub.fill('#apMk input[data-f="pendidikan"]', 'SMK Rekayasa Perangkat Lunak');
  await pub.fill('#apMk input[data-f="institusi"]', 'PT Solusi Kode');
  await pub.check('#apMk input[data-f="tujuan"][value="Sertifikasi"]');
  await isiSemua(pub, '#apMk input[data-f="dasar"][data-c="bukti"]', 'Ijazah SMK RPL dan KTP terlampir');
  await pub.screenshot({ path: OUT + 'p1-apl01.png', fullPage: true });
  await pub.click('[data-x="apNext"]'); await pub.waitForSelector('#apMk');
  ok('langkah 3 menampilkan FR.APL.02 per elemen & KUK', (await pub.textContent('#apMk')).includes('Kriteria Unjuk Kerja') || (await pub.locator('#apMk .muk-kuk').count()) > 10);
  await pub.click('[data-x="apNext"]'); await pub.waitForTimeout(300);
  ok('FR.APL.02 wajib diisi semua elemen', (await toast(pub)).includes('setiap elemen'));
  await pub.click('[data-x="apAllK"]'); await pub.waitForTimeout(300);
  await isiSemua(pub, '#apMk input[data-f="elemen"][data-c="bukti"]', 'Portofolio proyek web sekolah');
  await pub.screenshot({ path: OUT + 'p2-apl02.png' });
  await pub.click('[data-x="apNext"]'); await pub.waitForTimeout(400);
  for (const j of ['ktp', 'ijazah', 'foto']) { await pub.setInputFiles('#file-' + j, OUT + 'up.png'); await pub.click(`[data-x="docUpload"][data-jenis="${j}"]`); await pub.waitForTimeout(900); }
  await pub.click('[data-x="apNext"]'); await pub.waitForTimeout(400);
  ok('konfirmasi merangkum tujuan & elemen APL.02', (await pub.textContent('.content')).includes('elemen K'));
  await pub.check('input[name="ap-consent"]'); await pub.click('[data-x="apSubmit"]'); await pub.waitForTimeout(1600);
  ok('terkirim: FR.APL.01/02 ditandatangani', (await pub.textContent('.content')).includes('Menunggu verifikasi'));

  // 3. Admin memverifikasi bukti FR.APL.01
  await nav(adm, 'daftar'); await adm.locator('[data-sel]', { hasText: 'Bayu Pratama' }).click(); await adm.waitForSelector('#pdMk');
  ok('admin melihat FR.APL.01 dengan isian asesi', (await adm.textContent('#pdMk')).includes('SMK Rekayasa Perangkat Lunak'));
  await adm.click('#detail button[data-aksi="terima"]'); await adm.waitForTimeout(900);
  ok('terima tanpa verifikasi bukti ditolak', /Memenuhi Syarat|belum lengkap/.test(await toast(adm)));
  await pilihSemua(adm, '#pdMk input[data-c="status"][value="MS"]');
  await adm.screenshot({ path: OUT + 'p3-verifikasi.png', fullPage: true });
  await adm.click('#detail button[data-aksi="terima"]'); await adm.waitForTimeout(1300);
  ok('berkas diterima & tagihan terbit', (await toast(adm)).includes('tagihan'));

  // 4. Asesi membayar
  await nav(pub, 'bayar'); await pub.click('[data-x="payOpen"]'); await pub.check('input[name="metode"][value="QRIS"]');
  await pub.click('[data-x="payNow"]'); await pub.waitForTimeout(1200);
  await nav(pub, 'jadwal'); ok('status → pra-asesmen', (await pub.textContent('.content')).includes('Pra-asesmen'));

  // 5. Asesor: konsultasi pra-asesmen
  const asr = await login(b, 'asesor@demo.portallsp.id');
  await nav(asr, 'pra'); await asr.locator('tr', { hasText: 'Bayu Pratama' }).locator('button[data-x="mkOpen"]').click(); await asr.waitForTimeout(1000);
  ok('berkas asesmen: daftar periksa tampil', (await asr.textContent('.content')).includes('Daftar periksa kelengkapan dokumen'));
  let t = await isiTtd(asr, 'APL.02', async p => { await p.check('#mkDoc input[data-f="rekom"][value="dapat"]'); });
  ok('asesor meninjau & menandatangani FR.APL.02', t.includes('ditandatangani'), t);
  t = await isiTtd(asr, 'AK.07', async p => {
    await p.check('#mkDoc input[data-f="potensi"][value="1"]');
    await pilihSemua(p, '#mkDoc input[data-f="butir"][data-c="perlu"][value="Tidak"]');
    await p.check('#mkDoc input[data-f="sepakat"][value="tidak"]');
  });
  ok('asesor menandatangani FR.AK.07', t.includes('ditandatangani'), t);
  t = await isiTtd(asr, 'AK.01', async p => {
    await p.screenshot({ path: OUT + 'p4-ak01.png', fullPage: true });
    await p.check('#mkDoc input[data-f="coi"][value="Ya"]'); await p.check('#mkDoc input[data-f="banding_dijelaskan"][value="Ya"]');
  });
  ok('asesor menandatangani FR.AK.01 (bebas benturan kepentingan)', t.includes('ditandatangani'), t);

  // 6. Asesi menandatangani AK.07 & AK.01
  await nav(pub, 'jadwal');
  ok('asesi melihat pengingat tindakan', (await pub.textContent('.content')).includes('Perlu tindakan Anda'));
  await pub.locator('button[data-x="mkOpen"]').first().click(); await pub.waitForTimeout(900);
  ok('asesi: AK.07 ditandatangani', (await isiTtd(pub, 'AK.07')).includes('ditandatangani'));
  ok('asesi: AK.01 ditandatangani', (await isiTtd(pub, 'AK.01')).includes('ditandatangani'));
  ok('status → siap uji', (await pub.textContent('.content')).includes('Siap uji'));
  // Asesi menjawab soal pilihan ganda (FR.IA.05) di akunnya.
  await pub.click('button[data-x="mkForm"][data-kode="IA.05"]'); await pub.waitForSelector('#mkDoc');
  ok('lembar jawaban asesi tanpa kunci jawaban', !(await pub.textContent('#mkDoc')).includes('Kunci Jawaban'));
  await pilihSemua(pub, '#mkDoc input[data-c="jawaban"][value="b"]');
  await pub.check('#mk-setuju'); await pub.click('button[data-x="mkSign"]'); await pub.waitForTimeout(1100);
  ok('asesi menandatangani lembar jawaban FR.IA.05', (await toast(pub)).includes('ditandatangani'));

  // 7. Asesor: instrumen & FR.AK.02
  await nav(asr, 'asesmen'); await asr.locator('tr', { hasText: 'Bayu Pratama' }).locator('button[data-x="mkOpen"]').click(); await asr.waitForTimeout(1000);
  t = await isiTtd(asr, 'IA.01', async p => { await pilihSemua(p, '#mkDoc input[data-c="capai"][value="Ya"]'); await p.check('#mkDoc input[data-f="rekomendasi"][value="K"]'); });
  ok('FR.IA.01 ceklis observasi per KUK ditandatangani', t.includes('ditandatangani'), t);
  ok('FR.IA.02 ditandatangani', (await isiTtd(asr, 'IA.02')).includes('ditandatangani'));
  t = await isiTtd(asr, 'IA.03', async p => { await isiSemua(p, '#mkDoc input[data-c="tanggapan"]', 'Dijawab sesuai SOP'); await pilihSemua(p, '#mkDoc input[data-c="capai"][value="Ya"]'); });
  ok('FR.IA.03 ditandatangani', t.includes('ditandatangani'), t);
  t = await isiTtd(asr, 'IA.05', async p => { ok('asesor melihat kunci & penilaian otomatis', (await p.textContent('#mkDoc')).includes('Kunci Jawaban')); });
  ok('FR.IA.05 dinilai & ditandatangani asesor', t.includes('ditandatangani'), t);
  // Asesi ikut menandatangani instrumen yang diisi asesor (FR.IA.01–03) sebelum rekaman FR.AK.02.
  await nav(pub, 'jadwal'); await pub.locator('button[data-x="mkOpen"]').first().click(); await pub.waitForTimeout(900);
  for (const k of ['IA.01', 'IA.02', 'IA.03']) ok('asesi menandatangani ' + k, (await isiTtd(pub, k)).includes('ditandatangani'));
  t = await isiTtd(asr, 'AK.02', async p => { await pilihSemua(p, '#mkDoc input[data-c="hasil"][value="K"]'); await p.screenshot({ path: OUT + 'p5-ak02.png', fullPage: true }); });
  ok('FR.AK.02 ditandatangani → menunggu pleno', t.includes('ditandatangani'), t);

  // 8. Laporan FR.AK.05
  await nav(asr, 'laporanA');
  await asr.locator('tr', { hasText: tglLabel }).filter({ hasText: 'Junior Web Developer' }).locator('button[data-kode="AK.05"]').first().click(); await asr.waitForSelector('#mkDoc');
  ok('FR.AK.05 memuat Bayu dengan rekomendasi K', (await asr.textContent('#mkDoc')).includes('Bayu Pratama'));
  await asr.fill('#mkDoc textarea[data-f="negatif"]', 'Tidak ada'); await asr.fill('#mkDoc textarea[data-f="positif"]', 'Asesi siap dan teliti');
  await asr.check('#mk-setuju'); await asr.click('button[data-x="mkSign"]'); await asr.waitForTimeout(1100);
  ok('FR.AK.05 diserahkan', (await toast(asr)).includes('ditandatangani'));

  // 9. Komite Teknis memutuskan
  const kom = await login(b, 'komite.tdn@demo.portallsp.id');
  ok('menu Komite: Pleno & Banding', (await kom.textContent('.side nav')).includes('Pleno & Sertifikat') && (await kom.textContent('.side nav')).includes('Banding Asesmen'));
  await nav(kom, 'plenoA'); await kom.locator('[data-sel]', { hasText: 'Bayu Pratama' }).click(); await kom.waitForTimeout(400);
  ok('Komite melihat FR.AK.05 sudah diserahkan', (await kom.textContent('#detail')).includes('Sudah diserahkan'));
  await kom.click('#detail button[type=submit]'); await kom.waitForTimeout(1300);
  ok('Komite memutus → sertifikat terbit', (await toast(kom)).includes('pleno'));
  ok('Admin LSP tidak punya menu pleno (keputusan oleh Komite)', await adm.locator('.side nav button[data-go-app="plenoA"]').count() === 0);

  // 10. Sertifikat & cetak berkas
  await nav(pub, 'sertifikat'); ok('sertifikat di dompet', (await pub.textContent('.content')).includes('LSP-TDN/JWD/'));
  await nav(pub, 'jadwal'); await pub.locator('button[data-x="mkOpen"]').first().click(); await pub.waitForTimeout(900);
  await pub.click('button[data-x="mkForm"][data-kode="AK.02"]'); await pub.waitForSelector('#mkDoc');
  ok('berkas memiliki tombol Cetak / PDF', await pub.locator('button[data-x="mkPrint"]').count() === 1);
  await pub.check('#mk-setuju'); await pub.click('button[data-x="mkSign"]'); await pub.waitForTimeout(1000);
  ok('asesi menandatangani FR.AK.02', (await toast(pub)).includes('ditandatangani'));

  // 11. Perangkat & validasi
  await nav(adm, 'muk'); await adm.locator('tr', { hasText: 'JWD' }).locator('button[data-x="mukSkema"]').click(); await adm.waitForTimeout(900);
  ok('perangkat MUK skema tampil', (await adm.textContent('.content')).includes('FR.MAPA.02'));
  await adm.locator('tr', { hasText: 'FR.IA.05' }).locator('button[data-x="mukOpen"]').click(); await adm.waitForSelector('#mkDoc');
  ok('perangkat memuat penyusun & validator', (await adm.textContent('#mkDoc')).includes('Validator'));
  const mm = await login(b, 'mutu.tdn@demo.portallsp.id');
  ok('Manajer Mutu punya menu Validasi Asesmen', (await mm.textContent('.side nav')).includes('Validasi Asesmen'));
  await nav(mm, 'mukV'); ok('halaman validasi memuat skema', (await mm.textContent('.content')).includes('Junior Web Developer'));

  } catch (e) {
    fail++; console.log('  FAIL', e.message.split('\n')[0]);
    for (const [i, p] of PAGES.entries()) { await p.screenshot({ path: OUT + 'gagal-' + i + '.png', fullPage: true }).catch(() => {}); console.log('  [' + i + '] toast:', await p.textContent('#toast').catch(() => '')); }
  }
  const errs = PAGES.flatMap(p => p.errs);
  ok('tanpa error JavaScript', errs.length === 0, errs.slice(0, 3).join(' | '));
  console.log(`\n${pass} lulus, ${fail} gagal`);
  await b.close();
  process.exit(fail ? 1 : 0);
})();
