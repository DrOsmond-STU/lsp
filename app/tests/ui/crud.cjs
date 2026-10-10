// Uji browser tombol CRUD: ubah/hapus/turunkan listing, hapus skema, nonaktifkan asesor, ubah profil asesi.
const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const PW = 'Demo-Pass-2026';
let pass = 0, fail = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { fail++; console.log('  FAIL', n, x); } };
const wait = ms => new Promise(r => setTimeout(r, ms));
(async () => {
  const b = await chromium.launch();
  const errs = [];
  const open = async (email, vp = { width: 1280, height: 900 }) => {
    const p = await b.newPage({ viewport: vp });
    p.on('pageerror', e => errs.push(e.message));
    p.on('dialog', d => d.accept());
    await p.goto(BASE + '/', { waitUntil: 'networkidle' });
    await p.click('.topbar [data-go="login"]'); await p.fill('#lg-email', email); await p.fill('#lg-pass', PW);
    await p.click('#loginForm button[type=submit]'); await p.waitForTimeout(1500);
    return p;
  };
  const api = (p, route, data) => p.evaluate(async ([r, d]) => api(r, d), [route, data]);
  const menu = async (p, page) => { await p.click(`.side nav button[data-go-app="${page}"]`); await p.waitForTimeout(900); };
  const tag = Date.now().toString(36);

  console.log('Etalase: ubah, turunkan, hapus');
  const m = await open('marketing.tdn@demo.portallsp.id');
  const judul = 'Kelas UI ' + tag;
  await api(m, 'listings', { tipe: 'pelatihan', judul, bidang: 'TIK', kota: 'Jakarta', format: 'Online', harga: 100000, deskripsi: 'Deskripsi kelas uji browser yang cukup.', status: 'draf' });
  await menu(m, 'etalase');
  let row = m.locator('tr', { hasText: judul });
  check('listing draf punya tombol Ubah dan Hapus', await row.locator('[data-ledit]').count() === 1 && await row.locator('[data-lhapus]').count() === 1);
  await row.locator('[data-ledit]').click(); await m.waitForTimeout(400);
  check('form ubah terisi data lama', (await m.inputValue('#lf-judul')) === judul && /Ubah listing/.test(await m.locator('#listingForm h3').innerText()));
  await m.fill('#lf-judul', judul + ' diubah'); await m.fill('#lf-harga', '175000');
  await m.click('[data-savedraft]'); await m.waitForTimeout(1000);
  row = m.locator('tr', { hasText: judul + ' diubah' });
  check('perubahan tersimpan di tabel', await row.count() === 1 && /175/.test(await row.innerText()));
  await row.locator('[data-lhapus]').click(); await m.waitForTimeout(1000);
  check('listing terhapus dari tabel', await m.locator('tr', { hasText: judul }).count() === 0);
  const tayang = m.locator('tr', { hasText: 'Tayang' }).first();
  check('listing tayang punya tombol Turunkan', await tayang.locator('[data-lturun]').count() === 1);

  console.log('Master: hapus skema, nonaktifkan asesor');
  const a = await open('admin.tdn@demo.portallsp.id');
  await api(a, 'lsp/skema', { kode: 'UI-' + tag.toUpperCase(), nama: 'Skema UI ' + tag, bidang: 'TIK', kkni: '3', harga: 500000, units_text: 'U.001 | Unit satu', deskripsi: '', persyaratan: '', status: 'aktif' });
  await menu(a, 'master');
  const sr = a.locator('tr', { hasText: 'Skema UI ' + tag });
  check('skema baru tampil dengan tombol Hapus', await sr.locator('[data-act="lsp/skema/hapus"]').count() === 1);
  await sr.locator('[data-act="lsp/skema/hapus"]').click(); await a.waitForTimeout(1000);
  check('skema terhapus', await a.locator('tr', { hasText: 'Skema UI ' + tag }).count() === 0);
  await a.locator('[data-tab="master"][data-v="asesor"]').click(); await a.waitForTimeout(500);
  check('tab asesor punya tombol status', await a.locator('[data-act="users/status"]').count() >= 1);

  console.log('Profil asesi');
  const r = await open('asesi@demo.portallsp.id', { width: 390, height: 844 });
  await r.click('#appMenuBtn').catch(() => {}); await r.waitForTimeout(300);
  await menu(r, 'profil');
  await r.click('[data-edit="profil"]'); await r.waitForTimeout(400);
  check('form ubah profil tampil', await r.locator('#editor form[data-api="profile"]').count() === 1);
  if (await r.locator('#editor input[name="nik"]').count()) {
    await r.fill('#editor input[name="nik"]', '3174054107940011');
    await r.fill('#editor input[name="tanggal_lahir"]', '1994-07-01');
    await r.check('#editor input[name="consent_privacy"]');
  }
  await r.fill('#editor input[name="no_hp"]', '081277766655');
  await r.click('#editor button[type="submit"]'); await r.waitForTimeout(1200);
  check('nomor HP baru tampil di profil', /081277766655/.test(await r.locator('.content').innerText()));
  check('tanpa scroll horizontal di ponsel', await r.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));

  check('tanpa error JavaScript', errs.length === 0, errs.slice(0, 3).join(' | '));
  console.log(`\n${pass} lulus, ${fail} gagal`);
  await b.close();
  process.exit(fail ? 1 : 0);
})();
