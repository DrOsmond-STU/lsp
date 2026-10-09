const { chromium } = require('playwright');
const OUT=require('os').tmpdir()+'/';
(async () => {
  const b = await chromium.launch();
  for (const who of ['asesi','asesor','admin.tdn','marketing.tdn','keuangan.tdn','tuk.kuningan','superadmin']) {
    const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
    const errs=[]; p.on('pageerror', e=>errs.push(e.message)); p.on('console', m=>{ if(m.type()==='error') errs.push('console: '+m.text()); });
    await p.goto('http://127.0.0.1:8099/', {waitUntil:'networkidle'});
    await p.click('.topbar [data-go="login"]'); await p.fill('#lg-email',who+'@demo.portallsp.id'); await p.fill('#lg-pass','Demo-Pass-2026');
    await p.click('#loginForm button[type=submit]'); await p.waitForTimeout(1300);
    const keys = await p.$$eval('.side nav button[data-go-app]', bs=>bs.map(b=>b.dataset.goApp));
    const bad=[], placeholder=[];
    for (const k of keys) {
      await p.click(`.side nav button[data-go-app="${k}"]`); await p.waitForTimeout(700);
      const txt = await p.locator('.content').innerText();
      if (/Memuat…/.test(txt) && txt.length < 200) bad.push(k+'(memuat)');
      if (/Akses ditolak/.test(txt)) bad.push(k+'(ditolak)');
      if (/dibangun pada tahap berikutnya/.test(txt)) placeholder.push(k);
      
    }
    console.log(who.padEnd(14), '| menu:', keys.length, '| gagal:', bad.join(',')||'-', '| placeholder:', placeholder.join(',')||'-', '| error:', errs.length?errs.slice(0,3):'-');
    await p.close();
  }
  await b.close();
})();
