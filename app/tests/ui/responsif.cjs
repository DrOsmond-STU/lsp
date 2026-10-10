// Audit responsif: buka semua halaman publik dan semua menu tiap peran di ukuran HP & tablet,
// lalu laporkan elemen yang keluar dari layar (scroll horizontal) dan error JavaScript.
const { chromium } = require('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const SHOT = process.env.SHOT || '';            // folder screenshot (opsional)
const VIEWPORTS = (process.env.VP || '360x740,390x844,768x1024,1024x768').split(',').map(v => v.split('x').map(Number));
const ROLES = (process.env.ROLES || 'asesi,asesor,admin.tdn,marketing.tdn,keuangan.tdn,tuk.kuningan,superadmin').split(',');
const MEASURE = () => {
  const W = document.documentElement.clientWidth;
  const clipped = el => { for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) { const ox = getComputedStyle(p).overflowX; if (ox === 'auto' || ox === 'scroll' || ox === 'hidden' || ox === 'clip') return true; } return false; };
  const desc = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '') + (el.textContent ? ' "' + el.textContent.trim().replace(/\s+/g, ' ').slice(0, 30) + '"' : '');
  const bad = [];
  for (const el of document.querySelectorAll('#root *')) {
    const cs = getComputedStyle(el); if (cs.position === 'fixed' || cs.visibility === 'hidden') continue;
    const r = el.getBoundingClientRect(); if (!r.width || !r.height || r.right < -1000) continue; // -1000: field jebakan bot yang sengaja di luar layar
    if ((r.right > W + 1 || r.left < -1) && !clipped(el) && !bad.some(b => b.el.contains(el))) bad.push({ el, s: `${desc(el)} [${Math.round(r.left)}..${Math.round(r.right)}]` });
  }
  return { doc: document.documentElement.scrollWidth > W + 1, W, bad: bad.map(b => b.s) };
};
(async () => {
  const b = await chromium.launch();
  const problems = []; let checked = 0;
  const check = async (p, label, vp) => {
    await p.waitForTimeout(250);
    const m = await p.evaluate(MEASURE); checked++;
    if (m.doc || m.bad.length) problems.push(`${vp} ${label}: ${m.doc ? 'SCROLL-HORIZONTAL ' : ''}${m.bad.slice(0, 4).join(' | ')}`);
    if (SHOT) await p.screenshot({ path: `${SHOT}/${vp}-${label.replace(/[^a-z0-9.+-]/gi, '_')}.png`, fullPage: true });
  };
  for (const [w, h] of VIEWPORTS) {
    const vp = `${w}`;
    const ctx = await b.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 1, hasTouch: w < 900, isMobile: w < 900 });
    const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
    await p.goto(BASE, { waitUntil: 'networkidle' }); await p.waitForTimeout(500);
    for (const pg of ['beranda', 'cari', 'jadwal', 'lsp', 'lms', 'blog', 'verif', 'untuk', 'login', 'daftar']) {
      await p.evaluate(x => go(x), pg); await p.waitForTimeout(600); await check(p, 'pub-' + pg, vp);
    }
    await p.evaluate(() => go('cari')); await p.waitForTimeout(400); await p.locator('article.skema').first().click(); await p.waitForTimeout(800); await check(p, 'pub-detail', vp);
    await p.evaluate(() => go('blog', { blogSlug: 'panduan-lengkap-sertifikasi-kompetensi-bnsp-untuk-pemula' })); await p.waitForTimeout(800); await check(p, 'pub-artikel', vp);
    await p.evaluate(() => { S.cekQ = 'TDN7K3P9QX'; go('verif'); }); await p.waitForTimeout(800); await check(p, 'pub-verif-hasil', vp);
    if (w < 980) { await p.evaluate(() => go('beranda')); await p.click('#menuBtn'); await p.waitForTimeout(300); await check(p, 'pub-menu-terbuka', vp); }
    await ctx.close();
    for (const who of ROLES) {
      const c = await b.newContext({ viewport: { width: w, height: h }, hasTouch: w < 900, isMobile: w < 900 });
      const q = await c.newPage(); q.on('pageerror', e => errs.push(who + ': ' + e.message));
      await q.goto(BASE, { waitUntil: 'networkidle' }); await q.waitForTimeout(400);
      await q.evaluate(() => go('login')); await q.fill('#lg-email', who + '@demo.portallsp.id'); await q.fill('#lg-pass', 'Demo-Pass-2026');
      await q.click('#loginForm button[type=submit]'); await q.waitForSelector('.main', { timeout: 15000 }); await q.waitForTimeout(800);
      const keys = await q.evaluate(() => visibleMenu().filter(i => i[0] !== 'g').map(i => i[0]).concat(['notif', 'password']));
      for (const k of keys) {
        await q.evaluate(x => goApp(x), k); await q.waitForTimeout(700); await check(q, `${who}-${k}`, vp);
        if (await q.locator('.content [data-edit="new"]').count()) { await q.locator('.content [data-edit="new"]').first().click(); await q.waitForTimeout(400); await check(q, `${who}-${k}+form`, vp); }
        if (await q.locator('.content [data-sel]').count()) { await q.locator('.content [data-sel]').first().click(); await q.waitForTimeout(500); await check(q, `${who}-${k}+detail`, vp); }
      }
      if (w < 980 && await q.locator('#appMenuBtn').count()) { await q.click('#appMenuBtn'); await q.waitForTimeout(300); await check(q, `${who}-drawer`, vp); }
      await c.close();
    }
    if (errs.length) problems.push(`${vp} JS-ERROR: ${[...new Set(errs)].slice(0, 5).join(' | ')}`);
  }
  await b.close();
  console.log(`${checked} tampilan diperiksa, ${problems.length} bermasalah`);
  for (const x of problems) console.log(' -', x);
  process.exit(problems.length ? 1 : 0);
})();
