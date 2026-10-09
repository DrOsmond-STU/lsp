const { chromium } = require('playwright');
const fs = require('fs');
const OUT=require('os').tmpdir()+'/';
const BASE='http://127.0.0.1:8099/';
const PNG=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==','base64');
fs.writeFileSync(OUT+'up.png', PNG);
const ok=(n,c)=>console.log((c?'  ok   ':'  FAIL ')+n);
const today=new Date(); const T=today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-'+String(today.getDate()).padStart(2,'0');
async function login(b, who){ const c=await b.newContext({viewport:{width:1280,height:900}}); const p=await c.newPage(); p.errs=[]; p.on('pageerror',e=>p.errs.push(e.message));
  await p.goto(BASE,{waitUntil:'networkidle'}); await p.click('.topbar [data-go="login"]'); await p.fill('#lg-email',who); await p.fill('#lg-pass','Demo-Pass-2026'); await p.click('#loginForm button[type=submit]'); await p.waitForSelector('.side nav'); return p; }
const nav=async(p,k)=>{ await p.click(`.side nav button[data-go-app="${k}"]`); await p.waitForTimeout(700); };
(async()=>{
  const b=await chromium.launch();
  // 1. Admin membuat jadwal hari ini & menugaskan asesor
  const adm=await login(b,'admin.tdn@demo.portallsp.id');
  await nav(adm,'jadwalA'); await adm.click('[data-edit="new"]'); await adm.waitForSelector('#editor');
  await adm.selectOption('#editor select[name="skema_id"]',{label:'JWD · Junior Web Developer'});
  await adm.selectOption('#editor select[name="tuk_id"]',{label:'TUK Sewaktu Kuningan'});
  await adm.fill('#editor input[name="tanggal"]',T); await adm.fill('#editor input[name="kuota"]','15');
  await adm.click('#editor button[type=submit]'); await adm.waitForTimeout(900);
  const row=adm.locator('tbody tr').filter({hasText:'Junior Web Developer'}).filter({has:adm.locator('select[data-assign]')}).first();
  const sel=adm.locator('tbody tr', {hasText: new Date(T+'T00:00').toLocaleDateString('id-ID',{day:'2-digit',month:'short'})}).filter({hasText:'Junior Web Developer'}).locator('select[data-assign]').first();
  await sel.selectOption({label:'Budi Santoso, S.Kom.'}); await adm.waitForTimeout(900);
  ok('admin buat jadwal hari ini + tugaskan asesor', (await adm.textContent('#toast')).includes('Asesor ditugaskan'));
  // 2. Publik → detail skema → daftar → registrasi
  const cx=await b.newContext({viewport:{width:1280,height:900}}); const pub=await cx.newPage(); pub.errs=[]; pub.on('pageerror',e=>pub.errs.push(e.message));
  await pub.goto(BASE,{waitUntil:'networkidle'}); await pub.waitForTimeout(600);
  ok('beranda menampilkan skema dari server', await pub.locator('article.skema').count()>=4);
  await pub.click('.topbar [data-go="cari"]'); await pub.waitForTimeout(400);
  await pub.locator('article.skema',{hasText:'Junior Web Developer'}).click(); await pub.waitForTimeout(900);
  ok('detail skema: 9 unit tampil', (await pub.locator('table tbody tr').count())>=9);
  const opt=pub.locator('label.sched-opt',{hasText:new Date(T+'T00:00').toLocaleDateString('id-ID',{day:'2-digit',month:'short'})}).first();
  await opt.click(); await pub.waitForTimeout(300);
  await pub.screenshot({path:OUT+'u1-detail.png'});
  await pub.click('button[data-x="apStart"]'); await pub.waitForTimeout(500);
  ok('belum masuk → diarahkan ke login', (await pub.textContent('#root')).includes('Masuk atau daftar sebagai asesi'));
  await pub.click('#loginForm [data-go="daftar"]'); await pub.waitForTimeout(400);
  await pub.fill('#rg-nama','Bayu Pratama'); await pub.fill('#rg-nik','3171011204980001'); await pub.fill('#rg-tgl','1998-04-12'); await pub.check('input[name="rg-jk"][value="L"]');
  await pub.fill('#rg-email','bayu.ui@contoh.id'); await pub.fill('#rg-hp','081277778888'); await pub.fill('#rg-pass','Kompeten-Ui-2026'); await pub.fill('#rg-pass2','Kompeten-Ui-2026'); await pub.check('#rg-privacy');
  await pub.click('#daftarForm button[type=submit]'); await pub.waitForTimeout(1500);
  ok('setelah daftar langsung masuk wizard pendaftaran skema', (await pub.textContent('.content')).includes('Pendaftaran uji kompetensi'));
  // verifikasi email lewat tautan
  const tok=[...fs.readFileSync(require('path').join(__dirname,'../../storage/mail.log'),'utf8').matchAll(/To: bayu\.ui@contoh\.id[\s\S]*?verifikasi=([0-9a-f]{64})/g)].pop()[1];
  await pub.goto(BASE+'?verifikasi='+tok,{waitUntil:'networkidle'}); await pub.waitForTimeout(1200);
  await nav(pub,'skema'); await pub.locator('.card',{hasText:'Junior Web Developer'}).locator('button[data-x="apStart"]').click(); await pub.waitForTimeout(900);
  await pub.locator('label.sched-opt',{hasText:new Date(T+'T00:00').toLocaleDateString('id-ID',{day:'2-digit',month:'short'})}).first().click();
  await pub.click('[data-x="apNext"]'); await pub.waitForTimeout(300);
  ok('langkah data diri: email terverifikasi', (await pub.textContent('.content')).includes('Terverifikasi'));
  await pub.click('[data-x="apNext"]'); await pub.waitForTimeout(300);
  await pub.click('[data-x="apNext"]'); await pub.waitForTimeout(300);
  ok('APL.02 wajib diisi semua unit', (await pub.textContent('#toast')).includes('semua unit'));
  await pub.click('[data-x="apAllK"]'); await pub.screenshot({path:OUT+'u2-apl.png'}); await pub.click('[data-x="apNext"]'); await pub.waitForTimeout(300);
  for (const j of ['ktp','ijazah','foto']) { await pub.setInputFiles('#file-'+j, OUT+'up.png'); await pub.click(`[data-x="docUpload"][data-jenis="${j}"]`); await pub.waitForTimeout(900); }
  ok('3 dokumen terunggah', (await pub.locator('.content').innerText()).match(/up\.png/g).length===3);
  await pub.screenshot({path:OUT+'u3-dok.png'});
  await pub.click('[data-x="apNext"]'); await pub.waitForTimeout(300);
  await pub.check('input[name="ap-consent"]'); await pub.screenshot({path:OUT+'u4-konfirmasi.png'});
  await pub.click('[data-x="apSubmit"]'); await pub.waitForTimeout(1500);
  ok('terkirim → Jadwal saya: menunggu verifikasi', (await pub.textContent('.content')).includes('Menunggu verifikasi'));
  // 3. Admin verifikasi
  await nav(adm,'daftar'); await adm.locator('[data-sel]',{hasText:'Bayu Pratama'}).click(); await adm.waitForTimeout(300);
  ok('admin melihat dokumen asesi', await adm.locator('#detail a.chip').count()===3);
  await adm.click('#detail button[data-p-aksi="terima"]'); await adm.waitForTimeout(1200);
  ok('berkas diterima', (await adm.textContent('#toast')).includes('Tagihan dikirim'));
  // 4. Asesi bayar
  await nav(pub,'bayar'); await pub.click('[data-x="payOpen"]'); await pub.check('input[name="metode"][value="QRIS"]'); await pub.screenshot({path:OUT+'u5-bayar.png'});
  await pub.click('[data-x="payNow"]'); await pub.waitForTimeout(1200);
  await nav(pub,'jadwal'); ok('status → pra-asesmen', (await pub.textContent('.content')).includes('Pra-asesmen'));
  // 5. Asesor pra & asesmen
  const asr=await login(b,'asesor@demo.portallsp.id');
  await nav(asr,'pra'); await asr.locator('[data-sel]',{hasText:'Bayu Pratama'}).click(); await asr.waitForTimeout(300);
  await asr.screenshot({path:OUT+'u6-pra.png'});
  await asr.click('#detail button[type=submit]'); await asr.waitForTimeout(1200);
  await nav(asr,'asesmen'); await asr.locator('[data-sel]',{hasText:'Bayu Pratama'}).click(); await asr.waitForTimeout(300);
  for (const r of await asr.locator('#detail input[type=radio][value="K"]').all()) await r.check();
  await asr.fill('#detail textarea[name="catatan"]','Semua unit kompeten.'); await asr.screenshot({path:OUT+'u7-asesmen.png'});
  await asr.click('#detail button[type=submit]'); await asr.waitForTimeout(1200);
  ok('asesmen tersimpan → pleno', (await asr.textContent('#toast')).includes('pleno'));
  await nav(asr,'pleno'); ok('asesor tidak bisa plenokan asesmennya sendiri', !(await asr.locator('[data-sel]',{hasText:'Bayu Pratama'}).count()) && (await asr.textContent('.content')).includes('Diuji oleh Anda'));
  // 6. Admin pleno
  await nav(adm,'plenoA'); await adm.locator('[data-sel]',{hasText:'Bayu Pratama'}).click(); await adm.waitForTimeout(300);
  await adm.click('#detail button[type=submit]'); await adm.waitForTimeout(1200);
  ok('pleno diputuskan', (await adm.textContent('#toast')).includes('pleno'));
  // 7. Asesi melihat sertifikat & verifikasi publik
  await nav(pub,'sertifikat'); await pub.screenshot({path:OUT+'u8-sertifikat.png'});
  ok('sertifikat di dompet', (await pub.textContent('.content')).includes('LSP-TDN/JWD/'));
  await pub.click('[data-x="cekPublik"]'); await pub.waitForTimeout(1200); await pub.screenshot({path:OUT+'u9-verif.png'});
  ok('verifikasi publik: Bayu Pratama, berlaku', (await pub.textContent('#root')).includes('Sertifikat terdaftar') && (await pub.textContent('#root')).includes('Bayu Pratama'));
  console.log('errors:', [...pub.errs, ...adm.errs, ...asr.errs]);
  await b.close();
})();
