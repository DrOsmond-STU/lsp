// Pindai semua halaman publik dan semua menu tiap peran: cari sisa purwarupa (teks, label "contoh", tombol data-toast tanpa aksi).
const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099/';
const POLA = /purwarupa|prototype|data contoh|harga contoh|lorem ipsum|segera hadir|coming soon|dibangun pada tahap berikutnya/i;
async function periksa(p, nama, temuan) {
  const r = await p.evaluate((src) => {
    const re = new RegExp(src, 'i');
    const root = document.querySelector('.content') || document.body;
    const teks = root.innerText;
    const cocok = (teks.match(new RegExp('.{0,40}(' + src + ').{0,40}', 'ig')) || []).slice(0, 3);
    const sample = [...root.querySelectorAll('.sample')].map(e => e.textContent.trim()).slice(0, 3);
    const toast = [...root.querySelectorAll('[data-toast]')].map(e => (e.textContent.trim() || e.getAttribute('aria-label') || '?') + ' → ' + e.dataset.toast).slice(0, 5);
    return { cocok, sample, toast };
  }, POLA.source);
  if (r.cocok.length || r.sample.length || r.toast.length) temuan.push({ nama, ...r });
}
(async () => {
  const b = await chromium.launch(); const temuan = []; let n = 0;
  const pub = await b.newPage({ viewport: { width: 1280, height: 900 } });
  await pub.goto(BASE, { waitUntil: 'networkidle' });
  const pubKeys = await pub.$$eval('.topbar [data-go]', bs => [...new Set(bs.map(x => x.dataset.go))]);
  for (const k of [...new Set([...pubKeys, 'beranda', 'cari', 'jadwal', 'lsp', 'lms', 'verif', 'untuk', 'blog', 'login', 'daftar'])]) {
    const el = await pub.$(`[data-go="${k}"]`);
    if (el) { await el.click().catch(() => {}); } else { continue; }
    await pub.waitForTimeout(700); n++;
    await periksa(pub, 'publik/' + k, temuan);
  }
  // detail skema
  const kartu = await pub.$('[data-go="cari"]'); if (kartu) { await kartu.click(); await pub.waitForTimeout(700); const d = await pub.$('[data-skema], [data-detail], .skema-card'); if (d) { await d.click().catch(() => {}); await pub.waitForTimeout(700); n++; await periksa(pub, 'publik/detail', temuan); } }
  await pub.close();
  for (const who of ['asesi', 'asesor', 'admin.tdn', 'admin.pbi', 'marketing.tdn', 'keuangan.tdn', 'tuk.kuningan', 'komite.tdn', 'mutu.tdn', 'superadmin']) {
    const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
    await p.goto(BASE, { waitUntil: 'networkidle' });
    await p.click('.topbar [data-go="login"]'); await p.fill('#lg-email', who + '@demo.portallsp.id'); await p.fill('#lg-pass', 'Demo-Pass-2026');
    await p.click('#loginForm button[type=submit]'); await p.waitForTimeout(1300);
    const keys = await p.$$eval('.side nav button[data-go-app]', bs => bs.map(x => x.dataset.goApp));
    for (const k of keys) { await p.click(`.side nav button[data-go-app="${k}"]`); await p.waitForTimeout(650); n++; await periksa(p, who + '/' + k, temuan); }
    await p.close();
  }
  await b.close();
  console.log('halaman diperiksa:', n);
  console.log(temuan.length ? JSON.stringify(temuan, null, 1) : 'TIDAK ADA sisa purwarupa');
  process.exit(temuan.length ? 1 : 0);
})();
