// Uji browser tombol CRUD: ubah/hapus/turunkan listing, hapus skema, nonaktifkan asesor, ubah profil asesi, unduh sertifikat PDF.
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

  console.log('Portal publik: harga paket dan footer');
  {
    const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
    p.on('pageerror', e => errs.push(e.message));
    await p.goto(BASE + '/', { waitUntil: 'networkidle' });
    check('footer tanpa teks purwarupa', !/purwarupa/i.test(await p.locator('footer').innerText()));
    check('footer tanpa label PSE yang belum dikonfirmasi', !/PSE|Server di Indonesia/.test(await p.locator('footer').innerText()));
    await p.click('.topbar [data-go="untuk"]'); await p.waitForTimeout(800);
    const paket = await p.evaluate(async () => (await (await fetch('api.php?r=pub/paket')).json()).paket);
    const teks = await p.locator('.plans').innerText();
    check('harga semua paket sama dengan data server', paket.length === 3 && paket.every(x => teks.includes('Rp' + x.biaya.toLocaleString('id-ID')) && teks.includes(x.kuota.toLocaleString('id-ID') + ' asesi/tahun')), teks.slice(0, 200));
    check('tanpa tombol demo palsu bila kontak belum diatur', await p.locator('.plans [data-toast]').count() === 0 && /hubungi pengelola PortalLSP/.test(await p.locator('main, .block').first().innerText()));
    check('halaman Untuk LSP tanpa teks purwarupa', !/purwarupa|harga contoh/i.test(await p.locator('body').innerText()));
    await p.screenshot({ path: process.env.SHOT || '/dev/null', fullPage: true }).catch(() => {});
    await p.close();
  }

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
  await r.click('#appMenuBtn').catch(() => {}); await r.waitForTimeout(300);
  await menu(r, 'sertifikat');
  const unduh = r.locator('a', { hasText: 'Unduh PDF' }).first();
  check('dompet sertifikat punya tombol Unduh PDF', await unduh.count() === 1);
  const pdf = await r.evaluate(async h => { const x = await fetch(h); const b = new Uint8Array(await x.arrayBuffer()); return { ct: x.headers.get('content-type'), head: String.fromCharCode(...b.slice(0, 5)) }; }, await unduh.getAttribute('href'));
  check('tautan Unduh PDF menghasilkan berkas PDF', pdf.ct === 'application/pdf' && pdf.head === '%PDF-', JSON.stringify(pdf));
  check('tanpa scroll horizontal di ponsel', await r.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));

  console.log('Pengguna: ubah nama, peran, password sementara');
  {
    const p = await open('admin.tdn@demo.portallsp.id');
    const email = `ui.staf.${tag}@contoh.id`;
    await api(p, 'users', { email, nama: 'Staf UI Uji', role: 'keuangan', password: 'Staf-Awal-2026' });
    await menu(p, 'users');
    const baris = p.locator('tr', { hasText: email });
    check('tombol Ubah tampil untuk staf', await baris.locator('[data-uedit]').count() === 1);
    check('tidak ada tombol Ubah untuk akun sendiri', await p.locator('tr', { hasText: 'admin.tdn@demo.portallsp.id' }).locator('[data-uedit]').count() === 0);
    await baris.locator('[data-uedit]').click(); await p.waitForSelector('#userEditForm');
    await p.fill('#ue-nama', 'Staf UI Diubah'); await p.selectOption('#ue-role', 'marketing');
    await p.click('#userEditForm button[type=submit]'); await p.waitForTimeout(1200);
    const isi = await p.locator('tr', { hasText: email }).innerText();
    check('nama dan peran tersimpan', isi.includes('Staf UI Diubah') && /Marketing/i.test(isi), isi);
    await p.locator('tr', { hasText: email }).locator('[data-uedit]').click(); await p.waitForSelector('#userEditForm');
    await p.fill('#ue-pass', 'pendek'); await p.click('#userEditForm button[type=submit]'); await p.waitForTimeout(800);
    check('password sementara lemah ditolak dengan pesan', /Password minimal 10/.test(await p.locator('#userEditForm').innerText()));
    await p.fill('#ue-pass', 'Sementara-2026x'); await p.click('#userEditForm button[type=submit]'); await p.waitForTimeout(1200);
    check('password sementara disimpan', await p.locator('#userEditForm').count() === 0);
    await p.close();
  }

  check('tanpa error JavaScript', errs.length === 0, errs.slice(0, 3).join(' | '));
  console.log(`\n${pass} lulus, ${fail} gagal`);
  await b.close();
  process.exit(fail ? 1 : 0);
})();
