// Uji UI lupa password: Masuk → Lupa password? → email → tautan → password baru → masuk.
const { chromium } = require('playwright');
const fs = require('fs'); const path = require('path');
const BASE = (process.env.BASE || 'http://127.0.0.1:8099').replace(/\/$/, '');
const MAIL = process.env.MAIL_LOG || path.join(__dirname, '../../storage/mail.log');
let pass = 0, failN = 0;
const check = (n, c, x = '') => { if (c) { pass++; console.log('  ok  ', n); } else { failN++; console.log('  FAIL', n, x); } };
const uniq = Date.now().toString().slice(-6);
const EMAIL = `ui.reset${uniq}@contoh.id`;
async function daftar() {
  const me = await fetch(BASE + '/api.php?r=auth/me'); const ck = (me.headers.get('set-cookie') || '').match(/lsp_sid=([^;]+)/)[1]; const { csrf } = await me.json();
  const r = await fetch(BASE + '/api.php?r=auth/register', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Cookie: 'lsp_sid=' + ck, Accept: 'application/json' },
    body: JSON.stringify({ nama: 'Penguji UI Reset', nik: '317405520899' + uniq.slice(-4).replace(/^0/, '1'), tanggal_lahir: '1999-08-12', jenis_kelamin: 'P', email: EMAIL, no_hp: '0812345' + uniq.slice(-5),
      password: 'Awal-Pass-2026', consent_privacy: true, consent_marketing: false, website: '' }) });
  if (r.status !== 201) throw new Error('daftar gagal ' + r.status + ' ' + await r.text());
}
(async () => {
  await daftar();
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(BASE + '/', { waitUntil: 'networkidle' });
  await p.click('.topbar [data-go="login"]');
  await p.click('#loginForm button[data-go="lupa"]'); await p.waitForSelector('#lupaForm');
  check('tombol Lupa password membuka formulir', await p.isVisible('#lp-email'));
  await p.fill('#lp-email', EMAIL); await p.click('#lupaForm button[type=submit]'); await p.waitForTimeout(900);
  check('pesan umum tampil', (await p.locator('#lupaForm').innerText()).includes('Bila email itu terdaftar'));
  const blok = fs.readFileSync(MAIL, 'utf8').split('----').filter(x => x.includes('To: ' + EMAIL) && x.includes('Atur ulang password')).pop() || '';
  const link = (blok.match(/https?:\/\/\S+\?reset=[0-9a-f]{64}/) || [])[0];
  check('email berisi tautan atur ulang', !!link);
  await p.goto(BASE + '/?reset=' + 'a'.repeat(64), { waitUntil: 'networkidle' }); await p.waitForTimeout(700);
  check('tautan palsu: pesan "tidak dapat dipakai"', (await p.locator('.content, body').first().innerText()).includes('Tautan tidak dapat dipakai'));
  await p.goto(link.replace(/^https?:\/\/[^/]+/, BASE), { waitUntil: 'networkidle' }); await p.waitForSelector('#resetForm');
  check('formulir password baru tampil, email disamarkan', (await p.locator('#resetForm').innerText()).includes('@contoh.id') && !(await p.locator('#resetForm').innerText()).includes('ui.reset' + uniq));
  check('alamat tidak lagi memuat token', !p.url().includes('reset='));
  await p.fill('#rs-pass', 'Baru-Pass-2026'); await p.fill('#rs-pass2', 'Beda-Pass-2026'); await p.click('#resetForm button[type=submit]'); await p.waitForTimeout(400);
  check('ulangi password berbeda ditolak', (await p.locator('#resetForm').innerText()).includes('Ulangi password baru'));
  await p.fill('#rs-pass', 'pendek'); await p.fill('#rs-pass2', 'pendek'); await p.click('#resetForm button[type=submit]'); await p.waitForTimeout(800);
  check('password lemah ditolak server', (await p.locator('#resetForm').innerText()).includes('Password minimal 10'));
  await p.fill('#rs-pass', 'Baru-Pass-2026'); await p.fill('#rs-pass2', 'Baru-Pass-2026'); await p.click('#resetForm button[type=submit]');
  await p.waitForSelector('#loginForm', { timeout: 5000 });
  check('kembali ke halaman Masuk', await p.isVisible('#lg-email'));
  await p.fill('#lg-email', EMAIL); await p.fill('#lg-pass', 'Baru-Pass-2026'); await p.click('#loginForm button[type=submit]');
  await p.waitForSelector('.side nav', { timeout: 6000 }).catch(() => {});
  check('masuk dengan password baru', await p.isVisible('.side nav'));
  check('tanpa error JavaScript', errs.length === 0, errs.join(' | '));
  await b.close();
  console.log(`${pass} lulus, ${failN} gagal`); process.exit(failN ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
