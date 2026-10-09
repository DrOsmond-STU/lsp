'use strict';
/* PortalLSP: frontend. Data contoh ditandai di UI; data etalase, persetujuan, pengguna dan sesi berasal dari server. */
/* ===================== Data contoh ===================== */
const ICON = {
  search:'<path d="M11 4a7 7 0 1 0 4.4 12.4l4.1 4.1 1.4-1.4-4.1-4.1A7 7 0 0 0 11 4Zm0 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10Z"/>',
  home:'<path d="M12 3 3 10v11h6v-6h6v6h6V10l-9-7Z"/>',
  doc:'<path d="M6 2h8l6 6v14H6V2Zm7 1.5V9h5.5L13 3.5ZM8 12h8v2H8v-2Zm0 4h8v2H8v-2Z"/>',
  cal:'<path d="M7 2h2v2h6V2h2v2h3v18H4V4h3V2Zm-1 8v10h12V10H6Z"/>',
  cert:'<path d="M4 4h16v12h-5l1 6-4-2-4 2 1-6H4V4Zm4 4v2h8V8H8Zm0 4v2h5v-2H8Z"/>',
  wallet:'<path d="M3 6h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3V6Zm13 6a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3ZM5 3h12v2H5V3Z"/>',
  users:'<path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0v1H2v-1Zm15-9a3 3 0 1 0 0-6v6Zm1 2h-1.2A8.9 8.9 0 0 1 19 20v1h3v-1a6 6 0 0 0-4-7Z"/>',
  check:'<path d="m9 16.2-4.2-4.2-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z"/>',
  chat:'<path d="M4 4h16v12H8l-4 4V4Z"/>',
  chart:'<path d="M4 20V10h3v10H4Zm6.5 0V4h3v16h-3ZM17 20v-7h3v7h-3Z"/>',
  money:'<path d="M2 6h20v12H2V6Zm10 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM5 9v6h1V9H5Zm13 0v6h1V9h-1Z"/>',
  shield:'<path d="M12 2 4 5v6c0 5 3.4 9.5 8 11 4.6-1.5 8-6 8-11V5l-8-3Zm-1 13-3.5-3.5 1.4-1.4 2.1 2.1 4.6-4.6 1.4 1.4L11 15Z"/>',
  build:'<path d="M3 21V7l6-4 6 4v2h6v12H3Zm2-2h4v-3H5v3Zm0-5h4v-3H5v3Zm6 5h4v-3h-4v3Zm0-5h4v-3h-4v3Zm6 5h2v-3h-2v3Z"/>',
  gear:'<path d="m19.4 13 .1-1-.1-1 2.1-1.6-2-3.5-2.5 1a7 7 0 0 0-1.7-1L15 3h-4l-.4 2.6a7 7 0 0 0-1.7 1l-2.5-1-2 3.5L6.6 11l-.1 1 .1 1-2.1 1.6 2 3.5 2.5-1a7 7 0 0 0 1.7 1L11 21h4l.4-2.6a7 7 0 0 0 1.7-1l2.5 1 2-3.5-2.2-1.9ZM13 15a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/>',
  bell:'<path d="M12 22a2.5 2.5 0 0 0 2.4-2h-4.8a2.5 2.5 0 0 0 2.4 2Zm7-6V11a7 7 0 0 0-5-6.7V3h-4v1.3A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z"/>',
  book:'<path d="M4 3h7a3 3 0 0 1 3 3v15a2 2 0 0 0-2-2H4V3Zm16 0h-4a3 3 0 0 0-1 .2V20a3 3 0 0 1 1-1h4V3Z"/>',
  pin:'<path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/>',
  menu:'<path d="M3 6h18v2H3V6Zm0 5h18v2H3v-2Zm0 5h18v2H3v-2Z"/>',
  qr:'<path d="M3 3h8v8H3V3Zm2 2v4h4V5H5Zm8-2h8v8h-8V3Zm2 2v4h4V5h-4ZM3 13h8v8H3v-8Zm2 2v4h4v-4H5Zm8-2h2v2h-2v-2Zm4 0h4v2h-2v2h-2v-4Zm-4 4h2v4h-2v-4Zm4 2h4v2h-4v-2Z"/>',
  star:'<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/>',
  logout:'<path d="M10 3H4v18h6v-2H6V5h4V3Zm6.6 4.6L15.2 9l2 2H9v2h8.2l-2 2 1.4 1.4L21 12l-4.4-4.4Z"/>'
};
const ic = (n,c='ico') => `<svg class="${c}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">${ICON[n]}</svg>`;
const rp = n => 'Rp' + n.toLocaleString('id-ID');
const GRAD = {blue:'var(--g-blue)',green:'var(--g-green)',orange:'var(--g-orange)',pink:'var(--g-pink)',purple:'var(--g-purple)',teal:'var(--g-teal)',red:'var(--g-red)'};

const SKEMA = [
  {id:'jwd',kode:'JWD',nama:'Junior Web Developer',bidang:'TIK',kkni:'Level 3',lsp:'LSP Teknologi Digital Nusantara',kota:'Jakarta',metode:['Tatap muka','SJJ'],harga:750000,unit:9,warna:'blue',rating:4.8},
  {id:'dm',kode:'DM',nama:'Digital Marketing',bidang:'TIK',kkni:'Level 4',lsp:'LSP Teknologi Digital Nusantara',kota:'Bandung',metode:['SJJ'],harga:850000,unit:7,warna:'purple',rating:4.7},
  {id:'brs',kode:'BRS',nama:'Barista',bidang:'Pariwisata',kkni:'Level 2',lsp:'LSP Pariwisata Bahari Indonesia',kota:'Denpasar',metode:['Tatap muka'],harga:600000,unit:8,warna:'orange',rating:4.9},
  {id:'hk',kode:'HK',nama:'Housekeeping Supervisor',bidang:'Pariwisata',kkni:'Level 4',lsp:'LSP Pariwisata Bahari Indonesia',kota:'Yogyakarta',metode:['Tatap muka'],harga:900000,unit:10,warna:'pink',rating:4.6},
  {id:'til',kode:'TIL',nama:'Teknisi Instalasi Listrik Bangunan',bidang:'Konstruksi',kkni:'Level 3',lsp:'LSP Konstruksi Mandiri',kota:'Surabaya',metode:['Tatap muka'],harga:1100000,unit:11,warna:'teal',rating:4.7},
  {id:'adm',kode:'APK',nama:'Pengelola Administrasi Perkantoran',bidang:'Bisnis',kkni:'Level 3',lsp:'LSP Manajemen Profesional',kota:'Jakarta',metode:['Tatap muka','SJJ'],harga:700000,unit:8,warna:'green',rating:4.5},
  {id:'ad',kode:'AD',nama:'Analis Data Junior',bidang:'TIK',kkni:'Level 4',lsp:'LSP Teknologi Digital Nusantara',kota:'Jakarta',metode:['SJJ'],harga:950000,unit:9,warna:'red',rating:4.8},
  {id:'las',kode:'LAS',nama:'Juru Las 3G SMAW',bidang:'Konstruksi',kkni:'Level 2',lsp:'LSP Konstruksi Mandiri',kota:'Batam',metode:['Tatap muka'],harga:1350000,unit:6,warna:'orange',rating:4.6}
];
const UNIT_JWD = [
  ['J.620100.004.02','Menggunakan struktur data'],
  ['J.620100.005.02','Mengimplementasikan user interface'],
  ['J.620100.011.01','Melakukan instalasi software tools pemrograman'],
  ['J.620100.012.01','Melakukan pengaturan software tools pemrograman'],
  ['J.620100.016.01','Menulis kode dengan prinsip sesuai guidelines dan best practices'],
  ['J.620100.017.02','Mengimplementasikan pemrograman terstruktur'],
  ['J.620100.019.02','Menggunakan library atau komponen pre-existing'],
  ['J.620100.023.02','Membuat dokumen kode program'],
  ['J.620100.025.02','Melakukan debugging']
];
const JADWAL = [
  {tgl:'Sab, 24 Okt 2026',jam:'08.00–16.00 WIB',tuk:'TUK Sewaktu Kuningan',kota:'Jakarta Selatan',metode:'Tatap muka',sisa:6,kuota:20},
  {tgl:'Sab, 31 Okt 2026',jam:'08.00–15.00 WIB',tuk:'Daring (SJJ) via Zoom',kota:'Seluruh Indonesia',metode:'SJJ',sisa:3,kuota:10},
  {tgl:'Sab, 14 Nov 2026',jam:'08.00–16.00 WIB',tuk:'TUK Tempat Kerja PT Data Prima',kota:'Jakarta Barat',metode:'Tatap muka',sisa:0,kuota:15}
];
const LSPS = [
  {nama:'LSP Teknologi Digital Nusantara',jenis:'P3',kota:'Jakarta',skema:42,tuk:18,warna:'blue',lisensi:'Berlaku s.d. Mar 2029'},
  {nama:'LSP Pariwisata Bahari Indonesia',jenis:'P3',kota:'Denpasar',skema:27,tuk:11,warna:'orange',lisensi:'Berlaku s.d. Jan 2028'},
  {nama:'LSP Konstruksi Mandiri',jenis:'P2',kota:'Surabaya',skema:19,tuk:9,warna:'teal',lisensi:'Berlaku s.d. Agu 2027'},
  {nama:'LSP Manajemen Profesional',jenis:'P3',kota:'Jakarta',skema:33,tuk:14,warna:'green',lisensi:'Berlaku s.d. Jun 2028'},
  {nama:'LSP Politeknik Negeri Maritim',jenis:'P1',kota:'Semarang',skema:15,tuk:4,warna:'purple',lisensi:'Berlaku s.d. Des 2027'},
  {nama:'LSP Kesehatan Nusa Husada',jenis:'P3',kota:'Medan',skema:21,tuk:7,warna:'pink',lisensi:'Berlaku s.d. Okt 2028'}
];

const KELAS = [
  {judul:'Persiapan Uji Junior Web Developer',info:'Online · 12 modul · 8 jam',warna:'blue',harga:350000,lsp:'LSP Teknologi Digital Nusantara'},
  {judul:'Teknik Latte Art untuk Barista',info:'Tatap muka · Denpasar',warna:'orange',harga:500000,lsp:'LSP Pariwisata Bahari Indonesia'},
  {judul:'Dasar K3 Kelistrikan',info:'Webinar · 2 sesi',warna:'teal',harga:0,lsp:'LSP Konstruksi Mandiri'}
];


/* ===================== Sesi, API & RBAC (klien) =====================
   Klien hanya menyembunyikan menu yang tidak diizinkan. Semua izin
   ditegakkan ulang di server (api.php); jangan mengandalkan klien. */
let ME = null;          // {user, memberships, active, permissions}
let CSRF = '';
let CATALOG = [];       // listing tayang dari server
const S = {role:'publik', page:'beranda', skema:'jwd', filter:'Semua', q:'', jadwal:0, step:0, appPage:'dashboard', lspCtx:'all',
  navOpen:false, verif:false, etab:'semua', form:null, pick:null, busy:false, loginErr:'', loginEmail:'', pwErr:'', userForm:false, userErr:'', formErr:'',
  listings:[], reviews:[], reviewStats:{approved_month:0,rejected_month:0}, users:[], assignable:[], rbac:null, loading:false};

const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
/* Semua teks dari server di-escape sekali saat diterima, sehingga aman dipakai di template HTML. */
function clean(v){
  if(Array.isArray(v)) return v.map(clean);
  if(v && typeof v==='object'){ const o={}; for(const k in v) o[k]=clean(v[k]); return o; }
  return typeof v==='string' ? esc(v) : v;
}
class ApiError extends Error { constructor(msg,status){ super(msg); this.status=status; } }
async function api(path, data){
  const opt={method:data===undefined?'GET':'POST',credentials:'same-origin',headers:{'Accept':'application/json'}};
  if(data!==undefined){ opt.headers['Content-Type']='application/json'; opt.headers['X-CSRF-Token']=CSRF; opt.body=JSON.stringify(data); }
  let res;
  try{ res=await fetch('api.php?r='+encodeURIComponent(path),opt); }
  catch(e){ throw new ApiError('Tidak bisa terhubung ke server. Periksa koneksi Anda.',0); }
  let body={};
  try{ body=await res.json(); }catch(e){}
  if(!res.ok){
    if(res.status===401 && ME){ ME=null; S.role='publik'; S.page='login'; S.loginErr='Sesi Anda berakhir. Silakan masuk lagi.'; render(); }
    if(res.status===419){ await loadMe(); }
    throw new ApiError(body.error || ('Permintaan gagal ('+res.status+')'), res.status);
  }
  return clean(body);
}
const UI_ROLE = {platform_admin:'super',admin_lsp:'admin',manajer_mutu:'admin',keuangan:'admin',marketing:'admin',admin_tuk:'tuk',asesor:'asesor',asesi:'asesi'};
const can = p => !!ME && Array.isArray(ME.permissions) && ME.permissions.includes(p);

function applyMe(data){
  CSRF = data.csrf || CSRF;
  ME = data.user ? {user:data.user, memberships:data.memberships||[], active:data.active, permissions:data.permissions||[]} : null;
  if(ME && ME.active){ S.role = UI_ROLE[ME.active.role] || 'publik'; }
  else { S.role = 'publik'; }
  return data;
}
async function loadMe(){ const d=await api('auth/me'); applyMe(d); return d; }
async function loadCatalog(){ try{ CATALOG=(await api('catalog')).items; }catch(e){ CATALOG=[]; } }
async function loadListings(){ if(can('listing.manage')) S.listings=(await api('listings')).items; }
async function loadReviews(){ if(can('listing.review')){ const d=await api('reviews'); S.reviews=d.items; S.reviewStats=d.stats; } }
async function loadUsers(){ if(can('user.manage')){ const d=await api('users'); S.users=d.items; S.assignable=d.assignable_roles; } }
async function loadRbac(){ if(can('rbac.view')) S.rbac=await api('rbac'); }
async function loadForPage(p){
  try{
    if(p==='etalase'||p==='dashboard') await loadListings();
    if(p==='approval'||p==='dashboard') await loadReviews();
    if(p==='users'){ await loadUsers(); await loadRbac(); }
    if(p==='rbac') await loadRbac();
  }catch(e){ toast(e.message); }
}

const WARNA = ['blue','purple','teal','pink','green','orange','red'];
const initials = t => (String(t).replace(/&[a-z#0-9]+;/g,'').match(/\b[A-Z]/g)||['N','E','W']).join('').slice(0,3);
function toSkema(l){ return {id:'n'+l.id,kode:initials(l.judul),nama:l.judul,bidang:l.bidang,kkni:'—',lsp:l.lsp_nama,kota:l.kota,metode:String(l.format).split(' &amp; '),harga:l.harga,unit:'—',warna:WARNA[l.id%WARNA.length],rating:5,baru:true}; }
function toKelas(l){ return {judul:l.judul,info:l.format+' · '+l.kota,warna:WARNA[l.id%WARNA.length],harga:l.harga,lsp:l.lsp_nama,baru:true}; }
const allSkema = () => SKEMA.concat(CATALOG.filter(l=>l.tipe==='skema').map(toSkema));
const allKelas = () => KELAS.concat(CATALOG.filter(l=>l.tipe==='pelatihan').map(toKelas));
const ST = {draf:['plain','Draf'],menunggu:['warn','Menunggu persetujuan'],revisi:['bad','Perlu revisi'],tayang:['ok','Tayang'],ditolak:['bad','Ditolak']};
const fmtTgl = s => s ? new Date(String(s).replace(' ','T')).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) : '—';

function toast(msg){const t=$('#toast');t.textContent=msg;t.hidden=false;clearTimeout(toast.h);toast.h=setTimeout(()=>t.hidden=true,3200)}
const $ = s => document.querySelector(s);
function go(page,extra={}){Object.assign(S,{page,navOpen:false},extra);render();window.scrollTo(0,0)}
async function goApp(p){S.appPage=p;render();window.scrollTo(0,0);await loadForPage(p);render()}

/* ===================== Portal publik ===================== */
const PUB_NAV = [['beranda','Beranda'],['cari','Cari Skema'],['jadwal','Jadwal Uji'],['lsp','Daftar LSP'],['lms','Pelatihan'],['verif','Verifikasi Sertifikat'],['untuk','Untuk LSP']];
function topbar(){
  return `<div class="topbar" role="banner"><div class="wrap" style="position:relative">
    <button class="logo" data-go="beranda"><span class="logo-mark">${ic('shield')}</span>PortalLSP</button>
    <nav class="nav ${S.navOpen?'open':''}" aria-label="Menu utama">${PUB_NAV.map(([k,l])=>`<button data-go="${k}" class="${S.page===k||(k==='cari'&&S.page==='detail')?'on':''}">${l}</button>`).join('')}</nav>
    <div class="row auth">${ME?`<button class="btn sm" data-enter="1">${ic('home')}Dashboard</button>`:`<button class="btn ghost sm" data-go="login">Masuk</button><button class="btn sm" data-toast="Pendaftaran akun asesi mandiri dibuat pada tahap berikutnya.">Daftar</button>`}</div>
    <button class="btn ghost sm menu-btn" id="menuBtn" aria-label="Buka menu">${ic('menu')}</button>
  </div></div>`;
}
function skemaCard(s){
  return `<article class="card skema" data-skema="${s.id}" tabindex="0">
    <div class="spread"><span class="badge" style="background:${GRAD[s.warna]}">${s.kode}</span>${s.baru?'<span class="chip ok">Baru</span>':`<span class="chip plain">${ic('star')}${s.rating}</span>`}</div>
    <div><h3>${s.nama}</h3><p class="muted" style="font-size:.85rem;margin-top:.2rem">${s.lsp}</p></div>
    <div class="meta"><span class="chip info">${s.bidang}</span><span class="chip plain">KKNI ${s.kkni}</span>${s.metode.map(m=>`<span class="chip ${m==='SJJ'?'ok':'plain'}">${m}</span>`).join('')}</div>
    <div class="spread" style="margin-top:auto"><div><span class="eyebrow">Mulai</span><div class="price">${rp(s.harga)}</div></div><span class="muted row" style="gap:.25rem;font-size:.84rem">${ic('pin')}${s.kota}</span></div>
  </article>`;
}
function pBeranda(){
  return `<section class="hero"><div class="wrap">
    <div>
      <span class="chip" style="background:rgba(255,255,255,.18);color:#fff">Satu portal untuk ${LSPS.length*28}+ LSP di Indonesia</span>
      <h1 style="margin-top:1rem">Cari, daftar, dan raih sertifikat kompetensi BNSP dalam satu tempat</h1>
      <p class="lead">Bandingkan skema dari berbagai LSP, pilih jadwal dan TUK terdekat, bayar online, lalu pantau proses asesmen sampai sertifikat terbit.</p>
      <form class="search" id="heroSearch">
        <input id="hq" placeholder="Cari skema, mis. Web Developer, Barista" aria-label="Cari skema">
        <select id="hk" aria-label="Lokasi"><option>Semua lokasi</option><option>Jakarta</option><option>Bandung</option><option>Surabaya</option><option>Denpasar</option></select>
        <button class="btn" type="submit">${ic('search')}Cari Skema</button>
      </form>
      <div class="quick">
        <button class="btn sm green" data-filter="TIK">Teknologi Informasi</button>
        <button class="btn sm orange" data-filter="Pariwisata">Pariwisata</button>
        <button class="btn sm teal" data-filter="Konstruksi">Konstruksi</button>
        <button class="btn sm pink" data-filter="Bisnis">Bisnis</button>
      </div>
    </div>
    <div class="hero-card">
      <p class="eyebrow" style="color:rgba(255,255,255,.8)">Status pendaftaran Anda</p>
      <div class="cert" style="margin-top:.7rem">
        <div class="spread"><b>Junior Web Developer</b><span class="chip info">Pra-asesmen</span></div>
        <p class="muted" style="font-size:.82rem">LSP Teknologi Digital Nusantara · 24 Okt 2026</p>
        <div class="track">${[1,1,1,2,0,0,0].map(v=>`<div class="${v===1?'d':v===2?'n':''}"></div>`).join('')}</div>
        <div class="track-l"><span>Daftar</span><span>Berkas</span><span>Bayar</span><span>Pra</span><span>Uji</span><span>Pleno</span><span>Sertifikat</span></div>
      </div>
      <div class="row" style="margin-top:.9rem;justify-content:space-between"><span style="font-size:.85rem;opacity:.9">Asesor sedang meninjau APL.02 Anda</span><button class="btn sm white" data-go="login">Lihat</button></div>
    </div>
  </div></section>
  <div class="wrap"><div class="stats">
    ${[['168','LSP terdaftar'],['2.940','skema aktif'],['1.210','TUK terhubung'],['84.500','asesi tersertifikasi']].map(([b,l])=>`<div class="stat"><b>${b}</b><span class="muted" style="font-size:.85rem">${l}</span></div>`).join('')}
  </div><p class="muted" style="font-size:.75rem;margin-top:.5rem"><span class="sample">Data contoh</span> Angka, nama LSP, dan skema di purwarupa ini fiktif.</p></div>

  <section class="block"><div class="wrap">
    <div class="sec-head"><div><p class="eyebrow">Paling banyak dicari</p><h2>Skema populer bulan ini</h2></div><button class="btn sm purple" data-go="cari">Lihat semua skema</button></div>
    <div class="grid g4">${SKEMA.slice(0,4).map(skemaCard).join('')}</div>
  </div></section>

  <section class="block" style="padding-top:0"><div class="wrap layout-2">
    <div class="card">
      <div class="sec-head" style="margin-bottom:.6rem"><h3>Jadwal uji terdekat</h3><button class="btn sm teal" data-go="jadwal">Semua jadwal</button></div>
      <div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Skema</th><th>TUK</th><th>Sisa kuota</th></tr></thead><tbody>
        ${[['24 Okt','Junior Web Developer','TUK Sewaktu Kuningan, Jakarta',6],['25 Okt','Barista','TUK Hotel Sanur, Denpasar',2],['31 Okt','Digital Marketing','Daring (SJJ)',3],['01 Nov','Teknisi Instalasi Listrik','TUK SMK Negeri 5 Surabaya',0]].map(r=>`<tr><td class="num"><b>${r[0]}</b></td><td>${r[1]}</td><td class="muted">${r[2]}</td><td>${r[3]?`<span class="chip ${r[3]<=3?'warn':'ok'}">${r[3]} kursi</span>`:'<span class="chip bad">Penuh</span>'}</td></tr>`).join('')}
      </tbody></table></div>
    </div>
    <div class="card" style="background:var(--grad);color:#fff;border:0">
      <p class="eyebrow" style="color:rgba(255,255,255,.8)">Verifikasi sertifikat</p>
      <h3 style="margin-top:.4rem;font-size:1.25rem">Pastikan sertifikat kompetensi asli</h3>
      <p style="opacity:.9;font-size:.9rem;margin-top:.4rem">Masukkan nomor sertifikat atau pindai QR di sertifikat.</p>
      <div class="search" style="margin-top:1rem"><input placeholder="Nomor sertifikat" value="62090 2433 1 0012345 2026" aria-label="Nomor sertifikat"><button class="btn green" data-go="verif" data-verif="1">${ic('check')}Cek</button></div>
    </div>
  </div></section>

  <section class="block" style="padding-top:0"><div class="wrap">
    <div class="sec-head"><div><p class="eyebrow">Alur sertifikasi</p><h2>Empat langkah sampai sertifikat terbit</h2></div></div>
    <div class="steps">
      ${[['blue','Pilih skema & jadwal','Bandingkan skema dari semua LSP, lalu pilih TUK dan tanggal uji.'],['purple','Isi APL & unggah berkas','Isi FR.APL.01 dan asesmen mandiri FR.APL.02. Berkas dari profil bisa dipakai ulang.'],['orange','Bayar & ikuti asesmen','Bayar lewat VA, QRIS, atau e-wallet. Ikuti uji tatap muka atau jarak jauh (SJJ).'],['green','Terima sertifikat','Keputusan pleno tampil langsung. Sertifikat tersimpan di dompet sertifikat Anda.']].map((s,i)=>`<div class="card"><div class="step-n" style="background:${GRAD[s[0]]};box-shadow:0 10px 20px -8px var(--s-${s[0]})">${i+1}</div><h3>${s[1]}</h3><p class="muted" style="font-size:.88rem;margin-top:.35rem">${s[2]}</p></div>`).join('')}
    </div>
  </div></section>

  <section class="block" style="padding-top:0"><div class="wrap"><div class="cta">
    <div style="max-width:60ch"><p class="eyebrow" style="color:rgba(255,255,255,.8)">Untuk Lembaga Sertifikasi Profesi</p><h2 style="margin-top:.35rem">Kelola asesmen, mutu, dan sertifikat LSP Anda tanpa tumpukan berkas</h2><p style="opacity:.9;margin-top:.5rem">Paperless, SJJ, pleno, laporan BNSP, dan modul mutu Pedoman 201 dalam satu sistem.</p></div>
    <div class="row"><button class="btn lg white" data-go="untuk">Lihat paket</button><button class="btn lg orange" data-go="untuk">Minta demo</button></div>
  </div></div></section>`;
}
function pCari(){
  const bidang=['Semua','TIK','Pariwisata','Konstruksi','Bisnis'];
  const list=allSkema().filter(s=>(S.filter==='Semua'||s.bidang===S.filter)&&(!S.q||s.nama.toLowerCase().includes(S.q.toLowerCase())));
  return `<section class="block"><div class="wrap stack">
    <div><p class="eyebrow">Marketplace skema</p><h2>Cari skema dari semua LSP</h2></div>
    <div class="card stack" style="gap:.8rem">
      <div class="row"><input id="cq" placeholder="Cari nama skema" value="${S.q}" style="flex:1 1 260px" aria-label="Cari nama skema"><select style="flex:0 1 200px" aria-label="Lokasi"><option>Semua lokasi</option><option>Jakarta</option><option>Surabaya</option></select><select style="flex:0 1 200px" aria-label="Metode"><option>Semua metode</option><option>Tatap muka</option><option>SJJ (jarak jauh)</option></select></div>
      <div class="filters">${bidang.map(b=>`<button class="fchip ${S.filter===b?'on':''}" data-filter="${b}">${b}</button>`).join('')}</div>
    </div>
    <div class="spread"><p class="muted"><b class="num" style="color:var(--fg)">${list.length}</b> skema ditemukan · diurutkan berdasarkan relevansi</p><span class="chip plain">Hanya skema berlisensi BNSP aktif</span></div>
    <div class="grid g4">${list.map(skemaCard).join('')||'<div class="card empty" style="grid-column:1/-1"><h3>Belum ada skema yang cocok</h3><p class="muted">Coba kata kunci lain atau pilih bidang "Semua".</p></div>'}</div>
  </div></section>`;
}
function pDetail(){
  const s=allSkema().find(x=>x.id===S.skema)||SKEMA[0];
  const j=JADWAL[S.jadwal];
  const steps=['Pilih jadwal','Data diri','APL.01 & APL.02','Unggah berkas','Pembayaran'];
  let body='';
  if(S.step===0){
    body=`<div class="stack" style="gap:.6rem">${JADWAL.map((x,i)=>`<label class="sched-opt ${S.jadwal===i?'on':''}" ${x.sisa?'':'style="opacity:.55"'}><input type="radio" name="jd" value="${i}" ${S.jadwal===i?'checked':''} ${x.sisa?'':'disabled'} style="width:auto">
      <div style="flex:1;min-width:0"><b>${x.tgl}</b> · <span class="muted">${x.jam}</span><div class="muted" style="font-size:.85rem">${x.tuk} · ${x.kota}</div></div>
      ${x.sisa?`<span class="chip ${x.sisa<=3?'warn':'ok'}">Sisa ${x.sisa}/${x.kuota}</span>`:'<span class="chip bad">Penuh · daftar tunggu</span>'}</label>`).join('')}</div>`;
  } else if(S.step===1){
    body=`<div class="grid g2"><label class="f">Nama sesuai KTP<input id="f-nama" value="Rina Kartika Sari"></label><label class="f">NIK<input id="f-nik" class="mono" value="3174 0123 4567 0001"></label><label class="f">Email<input id="f-mail" value="rina.kartika@contoh.id"></label><label class="f">No. WhatsApp<input id="f-wa" value="0812 3456 7890"></label></div><p class="alert info" style="margin-top:1rem">${ic('check')}Data diambil dari profil Anda. Perubahan di sini tidak mengubah berkas yang sudah diajukan ke LSP lain.</p>`;
  } else if(S.step===2){
    body=`<p class="muted" style="margin-bottom:.8rem">Asesmen mandiri (FR.APL.02): nilai diri Anda untuk tiap unit kompetensi.</p><div class="table-wrap"><table><thead><tr><th>Unit</th><th>Judul</th><th>Kompeten?</th></tr></thead><tbody>${UNIT_JWD.slice(0,4).map((u,i)=>`<tr><td class="mono">${u[0]}</td><td>${u[1]}</td><td><span class="chip ${i===3?'warn':'ok'}">${i===3?'Belum yakin':'Ya'}</span></td></tr>`).join('')}</tbody></table></div><p class="muted" style="font-size:.82rem;margin-top:.6rem">+${UNIT_JWD.length-4} unit lainnya</p>`;
  } else if(S.step===3){
    body=`<div class="stack" style="gap:.55rem">${[['KTP','Dari profil','ok'],['Ijazah terakhir (SMK/D3/S1)','Dari profil','ok'],['Pas foto latar merah','Dari profil','ok'],['Sertifikat pelatihan pemrograman web','Belum diunggah','warn'],['Portofolio / bukti kerja','Opsional','plain']].map(r=>`<div class="spread card" style="padding:.75rem 1rem;box-shadow:none"><span class="row" style="gap:.6rem">${ic('doc')}<b style="font-size:.9rem">${r[0]}</b></span><span class="row"><span class="chip ${r[2]}">${r[1]}</span>${r[2]!=='ok'?'<button class="btn sm teal" data-toast="Berkas diunggah">Unggah</button>':''}</span></div>`).join('')}</div>`;
  } else {
    body=`<div class="pay">${[['Virtual Account BCA','blue'],['QRIS','green'],['GoPay / OVO','purple'],['Virtual Account BRI','teal'],['Virtual Account Mandiri','orange'],['Kode voucher mitra','pink']].map((p,i)=>`<label><input type="radio" name="pay" ${i===1?'checked':''}><span class="dot" style="background:${GRAD[p[1]]}"></span>${p[0]}</label>`).join('')}</div><p class="muted" style="font-size:.82rem;margin-top:.8rem">Pembayaran masuk langsung ke rekening ${s.lsp}.</p>`;
  }
  return `<section class="block"><div class="wrap stack">
    <button class="btn ghost sm" data-go="cari" style="align-self:flex-start">← Kembali ke pencarian</button>
    <div class="layout-2">
      <div class="stack">
        <div class="card" style="background:var(--grad);color:#fff;border:0">
          <div class="row"><span class="chip" style="background:rgba(255,255,255,.2);color:#fff">${s.bidang}</span><span class="chip" style="background:rgba(255,255,255,.2);color:#fff">KKNI ${s.kkni}</span><span class="chip" style="background:rgba(255,255,255,.2);color:#fff">${s.unit} unit kompetensi</span></div>
          <h1 style="font-size:clamp(1.6rem,3.4vw,2.3rem);margin-top:.8rem">${s.nama}</h1>
          <p style="opacity:.9;margin-top:.4rem">${s.lsp} · Lisensi BNSP berlaku s.d. Mar 2029</p>
        </div>
        <div class="card">
          <div class="stepper">${steps.map((t,i)=>`<span class="${i<S.step?'done':i===S.step?'now':''}">${i+1}. ${t}</span>`).join('')}</div>
          <h3 style="margin-bottom:.9rem">${steps[S.step]}</h3>
          ${body}
          <div class="spread" style="margin-top:1.2rem">
            <button class="btn ghost" data-step="-1" ${S.step===0?'hidden':''}>Kembali</button>
            ${S.step<4?`<button class="btn ${['blue','purple','orange','teal'][S.step]}" data-step="1" style="margin-left:auto">Lanjut ke ${steps[S.step+1]}</button>`:`<button class="btn green lg" id="bayar" style="margin-left:auto">${ic('wallet')}Bayar ${rp(s.harga)}</button>`}
          </div>
        </div>
        <div class="card">
          <h3>Unit kompetensi</h3><p class="muted" style="font-size:.85rem">Mengacu SKKNI · <span class="sample">contoh</span></p>
          <div class="table-wrap" style="margin-top:.7rem"><table><thead><tr><th>Kode unit</th><th>Judul unit</th></tr></thead><tbody>${UNIT_JWD.map(u=>`<tr><td class="mono">${u[0]}</td><td>${u[1]}</td></tr>`).join('')}</tbody></table></div>
        </div>
      </div>
      <aside class="stack" style="position:sticky;top:80px">
        <div class="card">
          <p class="eyebrow">Ringkasan</p>
          <div class="stack" style="gap:.55rem;margin-top:.7rem;font-size:.9rem">
            <div class="spread"><span class="muted">Skema</span><b>${s.nama}</b></div>
            <div class="spread"><span class="muted">Jadwal</span><b>${j.tgl}</b></div>
            <div class="spread"><span class="muted">TUK</span><b style="text-align:right">${j.tuk}</b></div>
            <div class="spread"><span class="muted">Metode</span><span class="chip ${j.metode==='SJJ'?'ok':'plain'}">${j.metode}</span></div>
            <hr style="border:0;border-top:1px dashed var(--line);width:100%">
            <div class="spread"><span class="muted">Biaya uji</span><span class="num">${rp(s.harga)}</span></div>
            <div class="spread"><span class="muted">Biaya layanan</span><span class="num">Rp0</span></div>
            <div class="spread"><b>Total</b><span class="price">${rp(s.harga)}</span></div>
          </div>
        </div>
        <div class="card"><h3>Persyaratan dasar</h3><ul style="margin:.6rem 0 0;padding-left:1.1rem;display:grid;gap:.3rem;font-size:.9rem"><li>Minimal lulusan SMK/D3 bidang TIK, atau</li><li>Pengalaman kerja pemrograman web minimal 1 tahun</li><li>KTP dan pas foto berlatar merah</li></ul></div>
        <div class="card row" style="justify-content:space-between"><div><b>${s.lsp}</b><p class="muted" style="font-size:.82rem">Jakarta · 42 skema · 18 TUK</p></div><button class="btn sm purple" data-go="lsp">Profil LSP</button></div>
      </aside>
    </div>
  </div></section>`;
}
function pJadwal(){
  const rows=[['24 Okt 2026','Junior Web Developer','LSP Teknologi Digital Nusantara','TUK Sewaktu Kuningan, Jakarta','Tatap muka',6],['25 Okt 2026','Barista','LSP Pariwisata Bahari Indonesia','TUK Hotel Sanur, Denpasar','Tatap muka',2],['31 Okt 2026','Digital Marketing','LSP Teknologi Digital Nusantara','Daring','SJJ',3],['01 Nov 2026','Teknisi Instalasi Listrik Bangunan','LSP Konstruksi Mandiri','TUK SMK Negeri 5 Surabaya','Tatap muka',0],['07 Nov 2026','Pengelola Administrasi Perkantoran','LSP Manajemen Profesional','TUK Graha Mandala, Jakarta','Tatap muka',12],['08 Nov 2026','Analis Data Junior','LSP Teknologi Digital Nusantara','Daring','SJJ',8]];
  return `<section class="block"><div class="wrap stack"><div><p class="eyebrow">Kalender uji</p><h2>Jadwal uji kompetensi terbuka</h2></div>
  <div class="card"><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Skema</th><th>LSP</th><th>TUK</th><th>Metode</th><th>Kuota</th><th></th></tr></thead><tbody>
  ${rows.map(r=>`<tr><td class="num"><b>${r[0]}</b></td><td>${r[1]}</td><td class="muted">${r[2]}</td><td class="muted">${r[3]}</td><td><span class="chip ${r[4]==='SJJ'?'ok':'plain'}">${r[4]}</span></td><td>${r[5]?`<span class="chip ${r[5]<=3?'warn':'ok'}">Sisa ${r[5]}</span>`:'<span class="chip bad">Penuh</span>'}</td><td>${r[5]?'<button class="btn sm" data-skema="jwd">Daftar</button>':'<button class="btn sm orange" data-toast="Anda masuk daftar tunggu">Daftar tunggu</button>'}</td></tr>`).join('')}
  </tbody></table></div></div></div></section>`;
}
function pLsp(){
  return `<section class="block"><div class="wrap stack"><div><p class="eyebrow">Direktori</p><h2>Daftar LSP</h2><p class="muted" style="margin-top:.3rem">Setiap LSP punya halaman profil di portal ini. Hanya LSP dengan lisensi BNSP aktif yang tampil.</p></div>
  <div class="grid g3">${LSPS.map(l=>`<article class="card stack" style="gap:.8rem">
    <div class="row"><span class="logo-mark" style="width:48px;height:48px;background:${GRAD[l.warna]};box-shadow:0 10px 20px -8px var(--s-${l.warna});color:#fff">${ic('build')}</span><div style="min-width:0"><h3>${l.nama}</h3><p class="muted" style="font-size:.84rem">LSP ${l.jenis} · ${l.kota}</p></div></div>
    <div class="row"><span class="chip ok">${ic('check')}${l.lisensi}</span></div>
    <div class="spread"><span class="muted" style="font-size:.88rem"><b class="num" style="color:var(--fg)">${l.skema}</b> skema · <b class="num" style="color:var(--fg)">${l.tuk}</b> TUK</span><button class="btn sm ${l.warna}" data-toast="Membuka profil ${l.nama}">Lihat profil</button></div>
  </article>`).join('')}</div></div></section>`;
}
function pLms(){
  const k=allKelas();
  return `<section class="block"><div class="wrap stack"><div><p class="eyebrow">Pelatihan (LMS)</p><h2>Kelas persiapan & bimtek</h2></div>
  <p class="alert warn">${ic('shield')}<span>Pelatihan tidak wajib untuk ikut uji kompetensi. Instruktur kelas tidak boleh menjadi asesor peserta yang ia ajar.</span></p>
  <div class="grid g3">${k.map(kelasCard).join('')}</div></div></section>`;
}
function kelasCard(x){
  return `<article class="card stack" style="gap:.7rem"><div style="height:120px;border-radius:16px;background:${GRAD[x.warna]};display:grid;place-items:center;color:#fff">${ic('book','ico" style="width:42px;height:42px')}</div><div class="spread"><h3>${x.judul}</h3>${x.baru?'<span class="chip ok">Baru</span>':''}</div><p class="muted" style="font-size:.86rem">${x.info} · ${x.lsp}</p><div class="spread"><span class="price">${x.harga?rp(x.harga):'Gratis'}</span><button class="btn sm ${x.warna}" data-toast="Kelas ditambahkan">Ikuti kelas</button></div></article>`;
}
function pVerif(){
  return `<section class="block"><div class="wrap stack" style="max-width:760px"><div><p class="eyebrow">Verifikasi publik</p><h2>Verifikasi sertifikat kompetensi</h2></div>
  <form class="card row" id="verifForm"><input id="vnum" class="mono" style="flex:1 1 260px" value="62090 2433 1 0012345 2026" aria-label="Nomor sertifikat"><button class="btn green" type="submit">${ic('check')}Verifikasi</button><button class="btn purple" type="button" data-toast="Kamera tidak tersedia di purwarupa">${ic('qr')}Pindai QR</button></form>
  ${S.verif?`<div class="card" style="border:2px solid var(--ok)"><div class="spread"><span class="chip ok" style="font-size:.85rem">${ic('check')}Sertifikat asli & berlaku</span><span class="sample">contoh</span></div>
  <div class="grid g2" style="margin-top:1rem;font-size:.92rem">${[['Nama pemegang','Rina Kartika Sari'],['Skema','Junior Web Developer'],['LSP penerbit','LSP Teknologi Digital Nusantara'],['Nomor registrasi','62090 2433 1 0012345 2026'],['Tanggal terbit','12 Mei 2026'],['Berlaku sampai','11 Mei 2029']].map(r=>`<div><p class="eyebrow">${r[0]}</p><b>${r[1]}</b></div>`).join('')}</div>
  <p class="muted" style="font-size:.8rem;margin-top:1rem">Hanya data minimum yang ditampilkan untuk melindungi data pribadi pemegang sertifikat.</p></div>`:''}
  </div></section>`;
}
function pUntuk(){
  const plans=[['Basic','Rp1.500.000','/bulan','teal',['Asesmen paperless & paper-based','Hingga 1.000 asesi/tahun','5 TUK, 30 asesor','Laporan BNSP & sertifikat ber-QR','Support 08.00–17.00 WIB']],['Pro','Rp3.500.000','/bulan','blue',['Semua fitur Basic','SJJ dengan proctoring','Modul mutu Pedoman 201','CRM & kampanye WA','Hingga 5.000 asesi/tahun']],['Enterprise','Hubungi kami','','purple',['Semua fitur Pro','Asesi & TUK tanpa batas','LMS & e-commerce lanjutan','API & integrasi HRIS','Support prioritas 24 jam']]];
  return `<section class="block"><div class="wrap stack" style="gap:1.6rem"><div style="text-align:center"><p class="eyebrow">Untuk LSP</p><h2>Pilih paket sesuai skala LSP Anda</h2><p class="muted" style="margin-top:.3rem"><span class="sample">Harga contoh</span></p></div>
  <div class="plans">${plans.map((p,i)=>`<div class="card plan ${i===1?'feat':''}"><div class="spread"><h3>${p[0]}</h3>${i===1?'<span class="chip info">Paling dipilih</span>':''}</div><p style="margin-top:.6rem"><span class="price" style="font-size:1.6rem">${p[1]}</span><span class="muted">${p[2]}</span></p><ul>${p[4].map(f=>`<li>${f}</li>`).join('')}</ul><button class="btn ${p[3]}" style="width:100%;margin-top:1.1rem" data-toast="Permintaan demo terkirim (purwarupa)">${i===2?'Hubungi sales':'Coba gratis 14 hari'}</button></div>`).join('')}</div></div></section>`;
}
function publik(){
  const pages={beranda:pBeranda,cari:pCari,detail:pDetail,jadwal:pJadwal,lsp:pLsp,lms:pLms,verif:pVerif,untuk:pUntuk,login:pLogin};
  return topbar()+(pages[S.page]||pBeranda)()+`<footer><div class="wrap spread"><span class="row">${ic('shield')}<b>PortalLSP</b><span class="muted">Purwarupa UI/UX · nama produk sementara</span></span><span class="muted">Terdaftar PSE · Server di Indonesia</span></div></footer>`;
}



const MODUL = {
  skema:['Daftar skema baru','Cari skema di semua LSP, pilih jadwal, dan pakai ulang berkas dari profil.',['Pencarian marketplace','Pakai ulang dokumen profil','Simpan draf pendaftaran']],
  jadwal:['Jadwal saya','Semua jadwal uji Anda dari seluruh LSP.',['Kartu peserta ber-QR','Reschedule & pembatalan','Pengingat H-3 dan H-1']],
  bayar:['Pembayaran','Tagihan dan kuitansi dari setiap LSP.',['VA, QRIS, e-wallet','Kuitansi otomatis','Refund sesuai kebijakan LSP']],
  sertifikat:['Dompet sertifikat','Semua sertifikat Anda dari semua LSP.',['Pengingat masa berlaku','Lampirkan ke pendaftaran skema lanjutan','Bagikan tautan verifikasi']],
  kelas:['Kelas saya','Kelas persiapan & bimtek yang Anda ikuti.',['Video & materi','Kuis','Sertifikat pelatihan']],
  profil:['Profil & dokumen','Profil global yang dipakai di semua LSP.',['KTP, ijazah, pas foto, CV','Persetujuan berbagi data per LSP','Hapus akun (UU PDP)']],
  kalender:['Kalender gabungan','Jadwal Anda dari semua LSP dalam satu tampilan.',['Warna per LSP','Tandai tanggal tidak tersedia','Ekspor ke Google Calendar']],
  pra:['Tinjau pra-asesmen','APL.01 dan APL.02 peserta yang ditugaskan kepada Anda.',['Lihat bukti','Rekomendasi lanjut / tidak','Minta bukti tambahan']],
  asesmen:['Asesmen','Isi formulir FR.IA dan FR.AK selama uji.',['Mode offline','Unggah foto/video bukti','Tanda tangan elektronik']],
  pleno:['Pleno','Berkas yang diplenokan kepada Anda.',['Sistem menolak jika Anda asesornya','Keputusan K/BK','TTE berita acara']],
  riwayat:['Riwayat & logbook','Rekap asesmen dari semua LSP tempat Anda bertugas.',['Unduh logbook untuk RCC MET','Filter per tahun','Hanya terlihat oleh Anda']],
  honor:['Honor','Honor dari tiap LSP, terpisah per LSP.',['Slip honor','Rekap PPh 21','Status pembayaran']],
  daftar:['Pendaftaran','Verifikasi berkas pemohon.',['Terima / tolak / minta perbaikan','Cek kelengkapan otomatis','Pendaftaran massal mitra']],
  jadwalA:['Jadwal & penugasan','Buat jadwal dan tugaskan asesor.',['Saran asesor otomatis','Cek bentrok & konflik kepentingan','Rasio asesor : asesi']],
  asesmenA:['Asesmen','Pantau asesmen berjalan.',['Paperless, paper-based, SJJ','Rekaman SJJ','Proctoring']],
  plenoA:['Pleno & sertifikat','Keputusan dan penerbitan sertifikat.',['Validasi independensi pleno','Cetak di blanko BNSP','Sertifikat digital ber-QR']],
  master:['Skema, asesor, TUK','Data master LSP.',['Skema & MUK berversi','Undang asesor via NIK','Verifikasi TUK']],
  alumni:['Database alumni','Arsip rekaman asesmen per alumni.',['Pencarian & filter','Peta sebaran','Sertifikat akan kedaluwarsa']],
  mutu:['Sistem manajemen mutu','Pedoman BNSP 201.',['Audit internal & CAPA','Kaji ulang manajemen','Analisis ketidakberpihakan']],
  keuangan:['Keuangan','Tagihan, pembayaran, dan honor.',['Rekonsiliasi otomatis','Honor asesor & PPh 21','Laporan pendapatan']],
  crm:['CRM','Lead, mitra, dan kampanye.',['Pipeline Kanban','Follow-up otomatis','Broadcast WA dengan opt-in']],
  laporan:['Laporan BNSP','Laporan otomatis sesuai format BNSP.',['Generate BAPS','Pengajuan blanko','Ekspor Excel/PDF']],
  setting:['Profil LSP & pengaturan','Halaman profil di portal, branding dokumen, dan pengguna.',['Halaman profil LSP','Template surat & sertifikat','Akun & hak akses']],
  pemohon:['Pemohon','Pemohon yang mendaftar di TUK ini.',['Verifikasi berkas','Daftar hadir QR','Riwayat']],
  jadwalT:['Jadwal TUK','Jadwal uji di TUK ini.',['Usulan jadwal ke LSP','Kapasitas ruang','Bentrok ruang']],
  sarpras:['Sarana & prasarana','Inventaris alat per skema.',['Checklist verifikasi','Foto ber-geotag','Masa berlaku verifikasi']],
  chat:['Group chat','Chat per jadwal asesmen.',['Asesor, asesi, TUK','Lampiran berkas','Arsip otomatis']],
  alumniT:['Alumni TUK','Alumni yang diuji di TUK ini.',['Hanya data TUK ini','Ekspor','Pencarian']],
  lspList:['LSP klien','Daftar LSP yang berlangganan.',['Onboarding LSP baru','Penangguhan','Kuota']],
  paket:['Paket & tagihan','Paket langganan & feature flag.',['Basic / Pro / Enterprise','Tagihan otomatis','Trial 14 hari']],
  pustaka:['Pustaka SKKNI','Unit kompetensi global untuk diimpor LSP.',['Impor ke skema','Versi SKKNI','Pencarian kode unit']],
  support:['Tiket support','Tiket dari LSP.',['SLA per paket','Prioritas','Riwayat']],
  audit:['Log akses support','Akses "masuk sebagai" yang diizinkan LSP.',['Izin Admin LSP','Batas waktu','Terlihat oleh LSP']]
};
function kpi(ico,warna,val,label,sub=''){return `<div class="card kpi"><span class="k-ico" style="background:${GRAD[warna]};box-shadow:0 10px 20px -8px var(--s-${warna})">${ic(ico)}</span><div style="min-width:0"><b>${val}</b><span class="muted" style="font-size:.84rem">${label}</span>${sub?`<div style="font-size:.75rem;margin-top:.15rem">${sub}</div>`:''}</div></div>`}
const TRACK_L = '<div class="track-l"><span>Daftar</span><span>Berkas</span><span>Bayar</span><span>Pra</span><span>Uji</span><span>Pleno</span><span>Sertifikat</span></div>';
const track = n => `<div class="track">${[0,1,2,3,4,5,6].map(i=>`<div class="${i<n?'d':i===n?'n':''}"></div>`).join('')}</div>`+TRACK_L;

function dAsesi(){
  const per=[{s:'Junior Web Developer',lsp:'LSP Teknologi Digital Nusantara',w:'blue',n:3,st:['info','Pra-asesmen'],ket:'Asesor Budi Santoso meninjau APL.02. Jadwal uji Sab, 24 Okt 2026.',btn:['Lihat kartu peserta','blue']},{s:'Digital Marketing',lsp:'LSP Teknologi Digital Nusantara',w:'purple',n:2,st:['warn','Menunggu bayar'],ket:'Bayar sebelum Kam, 15 Okt 2026 pukul 23.59 WIB.',btn:['Bayar Rp850.000','orange']},{s:'Barista',lsp:'LSP Pariwisata Bahari Indonesia',w:'orange',n:1,st:['bad','Berkas kurang'],ket:'LSP meminta pas foto latar merah yang lebih jelas.',btn:['Unggah ulang','pink']}];
  const ctx=S.lspCtx;const list=per.filter(p=>ctx==='all'||p.lsp===ctx);
  return `<div class="spread"><div><p class="eyebrow">Jumat, 9 Oktober 2026</p><h2>Halo, ${ME.user.nama.split(' ')[0]}</h2></div><button class="btn purple" data-go-public="cari">${ic('search')}Daftar skema baru</button></div>
  <div class="grid g4">${kpi('doc','blue','3','Permohonan aktif')}${kpi('cal','teal','1','Jadwal uji minggu depan')}${kpi('wallet','orange','1','Tagihan belum dibayar')}${kpi('cert','green','2','Sertifikat dimiliki')}</div>
  <div class="layout-2">
    <div class="stack">
      <div class="spread"><h3>Status permohonan</h3><span class="chip plain">Dari ${ctx==='all'?'semua LSP':ctx}</span></div>
      ${list.map(p=>`<div class="card"><div class="spread"><div class="row" style="gap:.7rem;min-width:0"><span class="dot" style="width:12px;height:12px;background:${GRAD[p.w]}"></span><div style="min-width:0"><h3>${p.s}</h3><p class="muted" style="font-size:.84rem">${p.lsp}</p></div></div><span class="chip ${p.st[0]}">${p.st[1]}</span></div>${track(p.n)}<div class="spread" style="margin-top:.9rem"><p class="muted" style="font-size:.88rem;flex:1 1 240px">${p.ket}</p><button class="btn sm ${p.btn[1]}" data-toast="${p.btn[0]} (purwarupa)">${p.btn[0]}</button></div></div>`).join('')||'<div class="card empty"><h3>Tidak ada permohonan di LSP ini</h3></div>'}
    </div>
    <div class="stack">
      <h3>Dompet sertifikat</h3>
      <div class="cert-card" style="background:var(--g-teal);--s:var(--s-teal)"><p class="eyebrow" style="color:rgba(255,255,255,.85)">LSP Manajemen Profesional</p><h3 style="margin-top:.3rem">Pengelola Administrasi Perkantoran</h3><p class="mono" style="opacity:.9;margin-top:.5rem">62090 1120 3 0004411 2023</p><div class="spread" style="margin-top:.8rem"><span class="chip" style="background:rgba(255,255,255,.22);color:#fff">Habis 3 Feb 2027</span><button class="btn sm white" data-toast="Perpanjangan dibuka">Perpanjang</button></div></div>
      <div class="cert-card" style="background:var(--g-blue);--s:var(--s-blue)"><p class="eyebrow" style="color:rgba(255,255,255,.85)">LSP Teknologi Digital Nusantara</p><h3 style="margin-top:.3rem">Operator Komputer Muda</h3><p class="mono" style="opacity:.9;margin-top:.5rem">62090 2433 1 0009876 2025</p><div class="spread" style="margin-top:.8rem"><span class="chip" style="background:rgba(255,255,255,.22);color:#fff">Berlaku s.d. Jun 2028</span><button class="btn sm glass" data-toast="Tautan verifikasi disalin">${ic('qr')}Bagikan</button></div></div>
      <div class="alert warn">${ic('bell')}<span>Sertifikat Administrasi Perkantoran habis dalam 117 hari. Daftar RCC sekarang agar tidak terputus.</span></div>
    </div>
  </div>`;
}
function dAsesor(){
  const L={'LSP Teknologi Digital Nusantara':'#1d4ed8','LSP Konstruksi Mandiri':'#0d9488','LSP Manajemen Profesional':'#059669'};
  const ev=[['Sab 10 Okt',[['08.00','Uji JWD · 6 asesi','LSP Teknologi Digital Nusantara','TUK Sewaktu Kuningan']]],['Sen 12 Okt',[['13.00','Pleno Administrasi Perkantoran · 8 berkas','LSP Manajemen Profesional','Daring']]],['Rab 14 Okt',[['09.00','Rapat teknis asesor JWD','LSP Teknologi Digital Nusantara','Daring'],['14.00','Verifikasi TUK SMK 5','LSP Konstruksi Mandiri','Surabaya']]],['Sab 17 Okt',[['08.00','Uji Teknisi Listrik · 5 asesi','LSP Konstruksi Mandiri','TUK SMK Negeri 5 Surabaya']]]].map(d=>[d[0],d[1].filter(e=>S.lspCtx==='all'||e[2]===S.lspCtx)]).filter(d=>d[1].length);
  return `<div class="spread"><div><p class="eyebrow">Jumat, 9 Oktober 2026</p><h2>Selamat datang, ${ME.user.nama.split(',')[0]}</h2></div><span class="chip ok">${ic('check')}Sertifikat MET berlaku s.d. Agu 2027</span></div>
  <div class="grid g4">${kpi('cal','blue','4','Jadwal 7 hari ke depan')}${kpi('doc','orange','7','APL.02 menunggu tinjauan')}${kpi('shield','purple','8','Berkas pleno')}${kpi('chart','green','63','Asesmen tahun ini','<span class="muted">dari 3 LSP</span>')}</div>
  <div class="layout-2">
    <div class="card"><div class="spread" style="margin-bottom:.4rem"><h3>Kalender gabungan</h3><div class="row" style="gap:.6rem;font-size:.75rem">${Object.entries(L).map(([n,c])=>`<span class="row" style="gap:.3rem"><span class="dot" style="background:${c}"></span>${n.replace('LSP ','')}</span>`).join('')}</div></div>
      ${ev.map(d=>`<div class="day"><b style="font-size:.85rem">${d[0]}</b><div>${d[1].map(e=>`<div class="ev" style="--c:${L[e[2]]}"><div class="spread"><b style="font-size:.9rem">${e[1]}</b><span class="mono muted">${e[0]}</span></div><span class="muted" style="font-size:.8rem">${e[2]} · ${e[3]}</span></div>`).join('')}</div></div>`).join('')||'<p class="muted">Tidak ada jadwal di LSP ini.</p>'}
    </div>
    <div class="stack">
      <div class="card"><h3>Tinjau pra-asesmen</h3><p class="muted" style="font-size:.82rem">Uji JWD, Sab 10 Okt · hanya peserta yang ditugaskan kepada Anda</p>
        <div class="stack" style="gap:.6rem;margin-top:.8rem">${[['Rina Kartika Sari','9/9 unit Ya'],['Fajar Nugroho','7/9 unit Ya'],['Sinta Maharani','9/9 unit Ya']].map(r=>`<div class="spread" style="border-bottom:1px solid var(--line);padding-bottom:.6rem"><div><b style="font-size:.9rem">${r[0]}</b><p class="muted" style="font-size:.78rem">APL.02 · ${r[1]}</p></div><div class="row" style="gap:.4rem"><button class="btn sm green" data-toast="Direkomendasikan lanjut">Lanjut</button><button class="btn sm orange" data-toast="Permintaan bukti terkirim">Minta bukti</button></div></div>`).join('')}</div>
      </div>
      <div class="alert info">${ic('shield')}<span>Anda hanya melihat asesi pada jadwal yang ditugaskan. Data LSP lain dan asesor lain tidak tampil.</span></div>
    </div>
  </div>`;
}
function dAdmin(){
  return `<div class="spread"><div><p class="eyebrow">${ME.active.lsp_nama} · ${ME.active.role_nama}</p><h2>Dashboard LSP</h2></div><div class="row">${can('schedule.manage')?`<button class="btn teal" data-toast="Jadwal baru dibuat (purwarupa)">${ic('cal')}Buat jadwal</button>`:''}${can('report.bnsp')?`<button class="btn pink" data-toast="Laporan BNSP dibuat">${ic('chart')}Laporan BNSP</button>`:''}</div></div>
  <div class="grid g4">${kpi('users','blue','214','Pendaftar bulan ini','<span class="chip ok">+18% dari Sep</span>')}${kpi('check','teal','37','Asesmen berjalan')}${kpi('shield','purple','52','Menunggu pleno')}${kpi('cert','orange','129','Sertifikat habis ≤ 90 hari','<span class="chip warn">Peluang RCC</span>')}</div>
  <div class="layout-2">
    <div class="card"><div class="spread" style="margin-bottom:.6rem"><h3>Pendaftaran perlu verifikasi</h3><span class="chip warn">12 menunggu</span></div>
      <div class="table-wrap"><table><thead><tr><th>Pemohon</th><th>Skema</th><th>Berkas</th><th>Bayar</th><th>Aksi</th></tr></thead><tbody>
      ${[['Rina Kartika Sari','Junior Web Developer','ok','Lengkap','ok','Lunas'],['Hendra Wijaya','Analis Data Junior','warn','Ijazah buram','ok','Lunas'],['PT Data Prima (25 org)','Junior Web Developer','ok','Lengkap','info','Kolektif'],['Maya Anggraini','Digital Marketing','ok','Lengkap','warn','Belum']].map(r=>`<tr><td><b>${r[0]}</b></td><td class="muted">${r[1]}</td><td><span class="chip ${r[2]}">${r[3]}</span></td><td><span class="chip ${r[4]}">${r[5]}</span></td><td>${can('registration.verify')?`<div class="row" style="gap:.35rem;flex-wrap:nowrap"><button class="btn sm green" data-toast="Permohonan diterima">Terima</button><button class="btn sm orange" data-toast="Permintaan perbaikan dikirim">Perbaiki</button></div>`:'<span class="muted" style="font-size:.8rem">Tidak ada akses</span>'}</td></tr>`).join('')}
      </tbody></table></div>
    </div>
    <div class="stack">
      <div class="card"><h3>Funnel bulan ini</h3><div class="funnel" style="margin-top:.8rem">${[['Lead',420,'blue'],['Daftar',214,'purple'],['Bayar',176,'orange'],['Diuji',131,'teal'],['Kompeten',118,'green']].map(f=>`<div class="row" style="gap:.6rem;flex-wrap:nowrap"><span class="muted" style="width:68px;font-size:.8rem;flex:none">${f[0]}</span><div style="flex:1;min-width:0"><div class="bar" style="width:${Math.round(f[1]/420*100)}%;background:${GRAD[f[2]]}"><span class="num">${f[1]}</span></div></div></div>`).join('')}</div></div>
      <div class="stack" style="gap:.6rem">
        <div class="alert bad">${ic('bell')}<span><b>Sertifikat MET 3 asesor</b> habis dalam 30 hari. Asesor tidak bisa ditugaskan setelah tanggal itu.</span></div>
        <div class="alert warn">${ic('shield')}<span><b>Surveilans BNSP</b> 18 Nov 2026. Checklist kesiapan 82% lengkap.</span></div>
        <div class="alert info">${ic('build')}<span><b>TUK SMK 7 Jakarta</b> verifikasi berakhir 30 Okt 2026.</span></div>
        ${can('listing.manage')&&S.listings.some(l=>l.status==='revisi')?`<div class="alert bad">${ic('wallet')}<span><b>Etalase:</b> ${S.listings.filter(l=>l.status==='revisi').length} listing perlu revisi dari Admin Platform. <button class="btn sm red" data-go-app="etalase" style="margin-top:.4rem">Buka etalase</button></span></div>`:''}
      </div>
    </div>
  </div>
  <div class="card"><div class="spread" style="margin-bottom:.6rem"><h3>Jadwal minggu ini</h3><button class="btn sm purple" data-go-app="jadwalA">Kelola jadwal</button></div>
    <div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Skema</th><th>TUK</th><th>Asesor</th><th>Peserta</th><th>Status</th></tr></thead><tbody>
    ${[['Sab 10 Okt','Junior Web Developer','TUK Sewaktu Kuningan','Budi Santoso, Lina M.','12/20','ok','Siap'],['Sab 10 Okt','Analis Data Junior','Daring (SJJ)','Yogi Pradana','8/10','warn','Link meeting belum dibuat'],['Min 11 Okt','Digital Marketing','Daring (SJJ)','—','6/10','bad','Asesor belum ditugaskan']].map(r=>`<tr><td class="num"><b>${r[0]}</b></td><td>${r[1]}</td><td class="muted">${r[2]}</td><td>${r[3]}</td><td class="num">${r[4]}</td><td><span class="chip ${r[5]}">${r[6]}</span></td></tr>`).join('')}
    </tbody></table></div>
  </div>`;
}
function dTuk(){
  return `<div class="spread"><div><p class="eyebrow">${ME.active.lsp_nama}</p><h2>${ME.active.tuk_nama||'TUK'}</h2></div><button class="btn orange" data-toast="Usulan jadwal dikirim ke Admin LSP">${ic('cal')}Usulkan jadwal</button></div>
  <div class="grid g4">${kpi('users','blue','34','Pemohon di TUK ini')}${kpi('cal','teal','3','Jadwal bulan ini')}${kpi('build','purple','96%','Sarana siap')}${kpi('shield','green','Mar 2027','Verifikasi berlaku s.d.')}</div>
  <div class="card"><h3>Pemohon jadwal Sab, 10 Okt</h3><div class="table-wrap" style="margin-top:.6rem"><table><thead><tr><th>Nama</th><th>Skema</th><th>Kehadiran</th><th></th></tr></thead><tbody>${[['Rina Kartika Sari','Junior Web Developer','ok','Terkonfirmasi'],['Fajar Nugroho','Junior Web Developer','warn','Belum konfirmasi'],['Sinta Maharani','Junior Web Developer','ok','Terkonfirmasi']].map(r=>`<tr><td><b>${r[0]}</b></td><td class="muted">${r[1]}</td><td><span class="chip ${r[2]}">${r[3]}</span></td><td><button class="btn sm teal" data-toast="Pengingat terkirim via WhatsApp">Ingatkan</button></td></tr>`).join('')}</tbody></table></div></div>
  <div class="alert info">${ic('shield')}<span>Admin TUK hanya melihat data TUK ini. Data TUK lain dan data asesor tidak tampil.</span></div>`;
}
function dSuper(){
  return `<div class="spread"><div><p class="eyebrow">Platform</p><h2>Ringkasan platform</h2></div><button class="btn green" data-toast="Undangan onboarding dikirim">${ic('build')}Tambah LSP klien</button></div>
  <div class="grid g4">${kpi('check','orange',String(S.reviews.length),'Listing menunggu persetujuan',S.reviews.length?'<button class="btn sm orange" data-go-app="approval" style="margin-top:.3rem">Tinjau sekarang</button>':'')}${kpi('build','blue','168','LSP aktif')}${kpi('money','green','Rp412 jt','MRR')}${kpi('bell','red','6','Sinyal churn')}</div>
  <div class="card"><div class="spread" style="margin-bottom:.6rem"><h3>LSP klien</h3><span class="chip plain">Data agregat, tanpa data pribadi asesi</span></div><div class="table-wrap"><table><thead><tr><th>LSP</th><th>Paket</th><th>Asesi/tahun</th><th>Storage</th><th>Status</th></tr></thead><tbody>
  ${[['LSP Teknologi Digital Nusantara','Pro','3.912 / 5.000','64%','ok','Aktif'],['LSP Pariwisata Bahari Indonesia','Basic','980 / 1.000','91%','warn','Kuota hampir habis'],['LSP Konstruksi Mandiri','Pro','1.204 / 5.000','22%','ok','Aktif'],['LSP Kesehatan Nusa Husada','Basic','112 / 1.000','8%','bad','Aktivitas turun 60%'],['LSP Politeknik Negeri Maritim','Trial','40 / 200','3%','info','Trial s.d. 20 Okt']].map(r=>`<tr><td><b>${r[0]}</b></td><td><span class="chip plain">${r[1]}</span></td><td class="num">${r[2]}</td><td class="num">${r[3]}</td><td><span class="chip ${r[4]}">${r[5]}</span></td></tr>`).join('')}
  </tbody></table></div></div>
  <div class="alert warn">${ic('shield')}<span>Super Admin tidak bisa membuka data operasional LSP. Akses support hanya lewat "masuk sebagai" dengan izin Admin LSP dan tercatat di log.</span></div>`;
}

/* ===================== Halaman login & password ===================== */
function pLogin(){
  return `<section class="block"><div class="wrap" style="max-width:480px">
    <form class="card stack" id="loginForm" novalidate>
      <div><p class="eyebrow">Masuk</p><h2>Masuk ke PortalLSP</h2><p class="muted" style="margin-top:.3rem">Satu akun untuk asesi, asesor, dan pengelola LSP.</p></div>
      ${S.loginErr?`<p class="alert bad" role="alert">${esc(S.loginErr)}</p>`:''}
      <label class="f">Email<input id="lg-email" type="email" autocomplete="username" required maxlength="190" value="${esc(S.loginEmail)}"></label>
      <label class="f">Password<input id="lg-pass" type="password" autocomplete="current-password" required maxlength="128"></label>
      <button class="btn lg" type="submit" ${S.busy?'disabled':''}>${S.busy?'Memeriksa…':'Masuk'}</button>
      <button type="button" class="btn ghost sm" data-toast="Hubungi Admin LSP Anda untuk mengatur ulang password.">Lupa password?</button>
      <p class="muted" style="font-size:.8rem">Setelah 5 kali gagal, login dikunci 15 menit. Sesi berakhir otomatis setelah 30 menit tidak aktif.</p>
    </form></div></section>`;
}
function pChangePassword(forced){
  return `<div class="wrap" style="max-width:520px;padding-block:2.5rem">
    <form class="card stack" id="pwForm" novalidate>
      <div><p class="eyebrow">${forced?'Wajib sebelum melanjutkan':'Keamanan akun'}</p><h2>Ganti password</h2>
      ${forced?'<p class="muted" style="margin-top:.3rem">Akun Anda dibuat oleh Admin LSP. Buat password pribadi sebelum mulai bekerja.</p>':''}</div>
      ${S.pwErr?`<p class="alert bad" role="alert">${esc(S.pwErr)}</p>`:''}
      <input type="email" autocomplete="username" value="${ME.user.email}" hidden>
      <label class="f">Password saat ini<input id="pw-cur" type="password" autocomplete="current-password" required></label>
      <label class="f">Password baru<input id="pw-new" type="password" autocomplete="new-password" required minlength="10" maxlength="128"></label>
      <label class="f">Ulangi password baru<input id="pw-new2" type="password" autocomplete="new-password" required></label>
      <p class="muted" style="font-size:.82rem">Minimal 10 karakter, berisi huruf dan angka, dan tidak memuat nama email Anda.</p>
      <div class="row" style="justify-content:flex-end">${forced?'<button type="button" class="btn ghost" data-logout="1">Keluar</button>':'<button type="button" class="btn ghost" data-go-app="dashboard">Batal</button>'}<button class="btn green" type="submit" ${S.busy?'disabled':''}>${S.busy?'Menyimpan…':'Simpan password'}</button></div>
    </form></div>`;
}
function pDenied(){
  return `<div class="card empty"><span class="logo-mark" style="width:60px;height:60px;margin:0 auto 1rem;color:#fff;background:var(--g-red)">${ic('shield','ico" style="width:28px;height:28px')}</span><h3>Akses ditolak</h3><p class="muted" style="margin-top:.4rem">Peran ${ME.active.role_nama} tidak punya hak akses ke halaman ini. Hubungi Admin LSP bila Anda memerlukannya.</p><div style="margin-top:1.2rem"><button class="btn" data-go-app="dashboard">Kembali ke beranda</button></div></div>`;
}

/* ===================== Menu per peran (dengan hak akses) ===================== */
const MENU = {
  asesi:[['dashboard','Beranda','home','asesi.dashboard'],['skema','Daftar Skema Baru','search','application.own'],['jadwal','Jadwal Saya','cal','application.own'],['bayar','Pembayaran','wallet','payment.own'],['sertifikat','Dompet Sertifikat','cert','certificate.own'],['kelas','Kelas Saya','book','class.own'],['profil','Profil & Dokumen','users','profile.own']],
  asesor:[['dashboard','Beranda','home','asesor.dashboard'],['kalender','Kalender Gabungan','cal','asesor.dashboard'],['pra','Tinjau Pra-Asesmen','doc','preassessment.review'],['asesmen','Asesmen (MUK/FR)','check','assessment.conduct'],['pleno','Pleno','shield','pleno.participate'],['riwayat','Riwayat & Logbook','book','asesor.history'],['honor','Honor','money','asesor.honor']],
  admin:[['g','Operasional'],['dashboard','Dashboard','home','lsp.dashboard'],['daftar','Pendaftaran','doc','registration.verify'],['jadwalA','Jadwal & Penugasan','cal','schedule.manage'],['asesmenA','Asesmen','check','assessment.monitor'],['plenoA','Pleno & Sertifikat','cert','decision.manage'],['g','Data'],['master','Skema, Asesor, TUK','build','master.manage'],['alumni','Database Alumni','users','alumni.view'],['g','Manajemen'],['etalase','Etalase & Pelatihan','wallet','listing.manage'],['mutu','Mutu (Pedoman 201)','shield','quality.manage'],['keuangan','Keuangan','money','finance.manage'],['crm','CRM','chat','crm.manage'],['laporan','Laporan BNSP','chart','report.bnsp'],['users','Pengguna & Hak Akses','users','user.manage'],['setting','Profil LSP & Pengaturan','gear','settings.manage']],
  tuk:[['dashboard','Dashboard TUK','home','tuk.dashboard'],['pemohon','Pemohon','doc','tuk.applicants'],['jadwalT','Jadwal','cal','tuk.schedule'],['sarpras','Sarana & Prasarana','build','tuk.facility'],['chat','Group Chat','chat','tuk.chat'],['alumniT','Alumni TUK','users','tuk.alumni']],
  super:[['dashboard','Ringkasan Platform','home','platform.dashboard'],['approval','Persetujuan Listing','check','listing.review'],['lspList','LSP Klien','build','lsp.manage'],['paket','Paket & Tagihan','money','lsp.manage'],['pustaka','Pustaka SKKNI','book','lsp.manage'],['support','Tiket Support','chat','lsp.manage'],['audit','Log Akses Support','shield','lsp.manage'],['rbac','Peran & Hak Akses','gear','rbac.view']]
};
function visibleMenu(){
  const items=(MENU[S.role]||[]).filter(it=>it[0]==='g'||can(it[3]));
  return items.filter((it,i)=>it[0]!=='g'||(items[i+1]&&items[i+1][0]!=='g'));
}
const allowedPage = p => p==='password' || visibleMenu().some(it=>it[0]===p);

/* ===================== Etalase LSP (dari API) ===================== */
function pEtalase(){
  const mine=S.listings;
  const tabs=[['semua','Semua'],['tayang','Tayang'],['menunggu','Menunggu'],['revisi','Perlu revisi'],['draf','Draf'],['ditolak','Ditolak']];
  const list=mine.filter(l=>S.etab==='semua'||l.status===S.etab);
  const count=k=>k==='semua'?mine.length:mine.filter(l=>l.status===k).length;
  const isSkema=S.form==='skema';
  const fd=S.formDraft||{};
  const form=S.form?`<form class="card stack" id="listingForm" style="gap:.9rem;border:2px solid var(--brand-b)" novalidate>
      <div class="spread"><h3>${isSkema?'Tambah skema ke etalase':'Tambah kelas pelatihan'}</h3><button type="button" class="btn ghost sm" data-closeform="1">Batal</button></div>
      ${S.formErr?`<p class="alert bad" role="alert">${esc(S.formErr)}</p>`:''}
      <div class="grid g2">
        <label class="f">Judul<input id="lf-judul" required maxlength="150" value="${esc(fd.judul??(isSkema?'Analis Data Junior (jadwal Desember)':'Kelas Persiapan Analis Data'))}"></label>
        <label class="f">Bidang<select id="lf-bidang">${['TIK','Pariwisata','Konstruksi','Bisnis','Kesehatan'].map(b=>`<option ${fd.bidang===b?'selected':''}>${b}</option>`).join('')}</select></label>
        <label class="f">Harga (Rp)<input id="lf-harga" type="number" min="0" max="100000000" step="1000" value="${esc(fd.harga??(isSkema?950000:400000))}"></label>
        <label class="f">${isSkema?'Metode':'Format'}<select id="lf-format">${(isSkema?['Tatap muka','SJJ','Tatap muka & SJJ']:['Online','Webinar','Tatap muka']).map(f=>`<option ${fd.format===f?'selected':''}>${esc(f)}</option>`).join('')}</select></label>
        <label class="f">Kota<input id="lf-kota" maxlength="100" value="${esc(fd.kota??'Jakarta')}"></label>
      </div>
      <label class="f">Deskripsi<textarea id="lf-desc" rows="3" maxlength="2000">${esc(fd.deskripsi??(isSkema?'Uji kompetensi analis data junior. TUK Sewaktu Kuningan atau daring.':'Kelas persiapan 4 sesi. Tidak wajib untuk mendaftar uji kompetensi.'))}</textarea></label>
      ${S.form==='pelatihan'?`<label class="row" style="gap:.5rem;font-size:.88rem"><input type="checkbox" id="lf-ack" checked style="width:auto">Saya menyatakan kelas ini bukan syarat wajib uji, dan instruktur tidak akan menjadi asesor pesertanya.</label>`:''}
      <div class="row" style="justify-content:flex-end"><button type="button" class="btn ghost" data-savedraft="1" ${S.busy?'disabled':''}>Simpan draf</button><button type="submit" class="btn green" ${S.busy?'disabled':''}>${ic('check')}Ajukan persetujuan</button></div>
    </form>`:'';
  return `<div class="spread"><div><p class="eyebrow">${ME.active.lsp_nama}</p><h2>Etalase & Pelatihan</h2></div><div class="row"><button class="btn" data-newform="skema">${ic('cert')}Tambah skema</button><button class="btn purple" data-newform="pelatihan">${ic('book')}Tambah pelatihan</button></div></div>
  <div class="alert info">${ic('shield')}<span>Listing baru dan setiap perubahan baru tampil di portal publik setelah <b>disetujui Admin Platform</b>.</span></div>
  <div class="flow"><span>Draf</span><i>→</i><span>Diajukan</span><i>→</i><span>Ditinjau Admin Platform</span><i>→</i><span style="background:var(--ok-bg);color:var(--ok)">Tayang</span><i>/</i><span style="background:var(--bad-bg);color:var(--bad)">Perlu revisi</span></div>
  ${form}
  <div class="card">
    <div class="tabs" style="margin-bottom:.8rem">${tabs.map(([k,l])=>`<button class="fchip ${S.etab===k?'on':''}" data-etab="${k}">${l} <span class="num">(${count(k)})</span></button>`).join('')}</div>
    <div class="table-wrap"><table><thead><tr><th>Judul</th><th>Jenis</th><th>Harga</th><th>Status</th><th>Catatan Admin Platform</th><th>Aksi</th></tr></thead><tbody>
    ${list.map(l=>`<tr><td><b>${l.judul}</b><div class="muted" style="font-size:.76rem">${l.submitted_at?'Diajukan '+fmtTgl(l.submitted_at):'Belum diajukan'}</div></td><td><span class="chip ${l.tipe==='skema'?'info':'plain'}">${l.tipe==='skema'?'Skema uji':'Pelatihan'}</span></td><td class="num">${l.harga?rp(l.harga):'—'}</td><td><span class="chip ${ST[l.status][0]}">${ST[l.status][1]}</span></td><td style="max-width:280px;font-size:.84rem" class="${l.catatan?'':'muted'}">${l.catatan||'—'}</td><td>${
      l.status==='draf'||l.status==='revisi'?`<button class="btn sm green" data-submit="${l.id}">Ajukan</button>`:
      l.status==='menunggu'?`<button class="btn sm ghost" data-withdraw="${l.id}">Tarik</button>`:'—'}</td></tr>`).join('')||'<tr><td colspan="6" class="muted">Belum ada listing dengan status ini.</td></tr>'}
    </tbody></table></div>
  </div>`;
}

/* ===================== Persetujuan listing (Admin Platform) ===================== */
function pApproval(){
  const q=S.reviews;
  if(!S.pick||!q.find(l=>l.id===S.pick))S.pick=q[0]?q[0].id:null;
  const cur=q.find(l=>l.id===S.pick);
  const preview=cur?(cur.tipe==='skema'?skemaCard(toSkema(cur)):kelasCard(toKelas(cur))):'';
  return `<div class="spread"><div><p class="eyebrow">Kurasi e-commerce & LMS</p><h2>Persetujuan listing</h2></div><span class="chip ${q.length?'warn':'ok'}">${q.length} menunggu · SLA 2 hari kerja</span></div>
  <div class="grid g4">${kpi('check','orange',String(q.length),'Menunggu')}${kpi('star','green',String(S.reviewStats.approved_month),'Disetujui bulan ini')}${kpi('bell','red',String(S.reviewStats.rejected_month),'Ditolak / revisi bulan ini')}${kpi('cal','blue','2 hari','Target waktu tinjau')}</div>
  ${cur?`<div class="layout-2" style="grid-template-columns:minmax(0,1fr) minmax(0,1.5fr)">
    <div class="stack" style="gap:.6rem">${q.map(l=>`<button class="qitem ${l.id===S.pick?'on':''}" data-pick="${l.id}"><span class="spread" style="width:100%"><b>${l.judul}</b><span class="chip ${l.tipe==='skema'?'info':'plain'}">${l.tipe==='skema'?'Skema':'Pelatihan'}</span></span><span class="muted" style="font-size:.8rem">${l.lsp_nama} · diajukan ${fmtTgl(l.submitted_at)}</span></button>`).join('')}</div>
    <div class="card stack">
      <div class="spread"><div><p class="eyebrow">Pratinjau tampilan publik</p><h3>${cur.judul}</h3><p class="muted" style="font-size:.84rem">${cur.lsp_nama} · ${cur.harga?rp(cur.harga):'Gratis'}</p></div><span class="chip warn">Belum tayang</span></div>
      <div style="max-width:340px">${preview}</div>
      <div><p class="eyebrow">Deskripsi dari LSP</p><p style="margin-top:.3rem">${cur.deskripsi||'—'}</p></div>
      <div><p class="eyebrow" style="margin-bottom:.5rem">Checklist kurasi</p><ul class="checklist">${(cur.tipe==='skema'?['Skema masuk ruang lingkup lisensi BNSP LSP & lisensi masih berlaku','Harga, biaya tambahan, dan kebijakan refund jelas','Tidak ada klaim menyesatkan ("pasti lulus", "dijamin kompeten")','Lokasi TUK & jadwal benar']:['Tidak dinyatakan sebagai syarat wajib uji','Instruktur tercatat (untuk cek konflik kepentingan asesor)','Harga & kebijakan refund jelas','Tidak ada klaim menyesatkan']).map((c,i)=>`<li><label><input type="checkbox" id="ck-${cur.id}-${i}">${c}</label></li>`).join('')}</ul></div>
      <label class="f">Catatan untuk LSP (wajib untuk revisi / tolak)<textarea id="ap-note" rows="2" maxlength="500" placeholder="Mis. hapus klaim 'dijamin kompeten' di deskripsi"></textarea></label>
      <div class="row" style="justify-content:flex-end"><button class="btn red" data-decide="ditolak" ${S.busy?'disabled':''}>Tolak</button><button class="btn orange" data-decide="revisi" ${S.busy?'disabled':''}>Minta revisi</button><button class="btn green" data-decide="tayang" ${S.busy?'disabled':''}>${ic('check')}Setujui & tayangkan</button></div>
    </div>
  </div>`:`<div class="card empty"><h3>Antrean kosong</h3><p class="muted">Semua listing sudah ditinjau.</p></div>`}`;
}

/* ===================== Pengguna & peran (Admin LSP) ===================== */
function rbacMatrix(){
  if(!S.rbac) return '<p class="muted">Memuat…</p>';
  const roles=S.rbac.roles, perms=S.rbac.permissions;
  return `<div class="table-wrap"><table><thead><tr><th>Hak akses</th>${roles.map(r=>`<th style="text-align:center">${r.nama}</th>`).join('')}</tr></thead><tbody>
    ${perms.filter(p=>roles.some(r=>r.permissions.includes(p.code))).map(p=>`<tr><td><span class="mono">${p.code}</span><div class="muted" style="font-size:.78rem">${p.deskripsi}</div></td>${roles.map(r=>`<td style="text-align:center">${r.permissions.includes(p.code)?`<span class="chip ok" aria-label="diizinkan">${ic('check')}</span>`:'<span class="muted" aria-label="tidak">·</span>'}</td>`).join('')}</tr>`).join('')}
  </tbody></table></div>`;
}
function pUsers(){
  const ud=S.userDraft||{};
  const form=S.userForm?`<form class="card stack" id="userForm" style="border:2px solid var(--brand-b)" novalidate autocomplete="off">
      <div class="spread"><h3>Tambah pengguna LSP</h3><button type="button" class="btn ghost sm" data-userform="0">Batal</button></div>
      ${S.userErr?`<p class="alert bad" role="alert">${esc(S.userErr)}</p>`:''}
      <div class="grid g2">
        <label class="f">Nama lengkap<input id="uf-nama" required maxlength="120" value="${esc(ud.nama??'')}"></label>
        <label class="f">Email<input id="uf-email" type="email" required maxlength="190" value="${esc(ud.email??'')}"></label>
        <label class="f">Peran<select id="uf-role">${S.assignable.map(r=>`<option value="${r.code}" ${ud.role===r.code?'selected':''}>${r.nama}</option>`).join('')}</select></label>
        <label class="f">Password awal<input id="uf-pass" type="password" autocomplete="new-password" required minlength="10" maxlength="128"></label>
      </div>
      <p class="muted" style="font-size:.82rem">Pengguna wajib mengganti password awal saat pertama kali masuk. Asesor dan asesi tidak ditambahkan di sini; mereka bergabung lewat undangan dan pendaftaran.</p>
      <div class="row" style="justify-content:flex-end"><button class="btn green" type="submit" ${S.busy?'disabled':''}>${ic('check')}Simpan pengguna</button></div>
    </form>`:'';
  return `<div class="spread"><div><p class="eyebrow">${ME.active.lsp_nama}</p><h2>Pengguna & Hak Akses</h2></div><button class="btn" data-userform="1">${ic('users')}Tambah pengguna</button></div>
  <div class="alert info">${ic('shield')}<span>Daftar ini hanya berisi pengguna LSP Anda. Pengguna dari LSP lain tidak pernah tampil di sini.</span></div>
  ${form}
  <div class="card"><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Login terakhir</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    ${S.users.map(u=>`<tr><td><b>${u.nama}</b>${u.is_me?' <span class="chip info">Anda</span>':''}</td><td class="muted">${u.email}</td><td><span class="chip plain">${u.role_nama}${u.tuk_nama?' · '+u.tuk_nama:''}</span></td><td class="muted num">${fmtTgl(u.last_login_at)}</td><td><span class="chip ${u.status==='aktif'?'ok':'bad'}">${u.status==='aktif'?'Aktif':'Nonaktif'}</span></td><td>${u.is_me?'—':`<button class="btn sm ${u.status==='aktif'?'red':'green'}" data-ustatus="${u.membership_id}" data-to="${u.status==='aktif'?'nonaktif':'aktif'}">${u.status==='aktif'?'Nonaktifkan':'Aktifkan'}</button>`}</td></tr>`).join('')||'<tr><td colspan="6" class="muted">Memuat…</td></tr>'}
  </tbody></table></div></div>
  <div class="card"><h3>Matriks peran & hak akses</h3><p class="muted" style="font-size:.85rem;margin:.2rem 0 .8rem">Ditegakkan di server pada setiap permintaan.</p>${rbacMatrix()}</div>`;
}
function pRbac(){
  return `<div><p class="eyebrow">Platform</p><h2>Peran & hak akses</h2></div>
  <div class="alert info">${ic('shield')}<span>Peran internal LSP hanya boleh punya satu keanggotaan. Asesor dan asesi boleh aktif di banyak LSP, tetapi hanya melihat data miliknya.</span></div>
  <div class="card">${rbacMatrix()}</div>`;
}

/* ===================== Kerangka aplikasi ===================== */
function app(){
  const r=S.role;
  if(ME.user.must_change_password) return `<div class="main">${pChangePassword(true)}</div>`;
  const dash={asesi:dAsesi,asesor:dAsesor,admin:dAdmin,tuk:dTuk,super:dSuper}[r];
  const menu=visibleMenu();
  const multi=ME.memberships.length>1 && ME.memberships.every(m=>m.role===ME.active.role);
  const otherCtx=ME.memberships.length>1 && !multi;
  const lspOpts=ME.memberships.map(m=>m.lsp_nama).filter(Boolean);
  const p=S.appPage;
  const pages={etalase:pEtalase,approval:pApproval,users:pUsers,rbac:pRbac};
  const content=p==='password'?pChangePassword(false):!allowedPage(p)?pDenied():p==='dashboard'?dash():pages[p]?pages[p]():modul(p);
  const navItems=menu.map(it=>it[0]==='g'?`<div class="grp">${it[1]}</div>`:`<button data-go-app="${it[0]}" class="${p===it[0]?'on':''}">${ic(it[2])}${it[1]}${it[0]==='approval'&&S.reviews.length?`<span class="navbadge">${S.reviews.length}</span>`:''}</button>`).join('');
  const ctxLabel=ME.active.lsp_nama?(ME.active.tuk_nama?ME.active.tuk_nama:ME.active.lsp_nama):'Platform';
  return `<div class="app">
    <aside class="side">
      <button class="logo" data-go-public="beranda"><span class="logo-mark">${ic('shield')}</span>PortalLSP</button>
      <div class="tenant"><span style="opacity:.75">${multi?'Akun Anda aktif di':'Masuk ke'}</span><b>${multi?ME.memberships.length+' LSP':ctxLabel}</b></div>
      <nav aria-label="Menu aplikasi">${navItems}</nav>
      <div class="stack" style="gap:.4rem;margin-top:auto"><button class="btn glass sm" data-go-app="password">${ic('gear')}Ganti password</button><button class="btn glass sm" data-logout="1">${ic('logout')}Keluar</button></div>
    </aside>
    <div class="main">
      <div class="apptop">
        ${multi?`<label class="row" style="gap:.4rem;font-size:.82rem;font-weight:600" for="ctx"><span class="muted">Tampilkan</span><select id="ctx"><option value="all">Semua LSP</option>${lspOpts.map(l=>`<option ${S.lspCtx===l?'selected':''}>${l}</option>`).join('')}</select></label>`:
          otherCtx?`<label class="row" style="gap:.4rem;font-size:.82rem;font-weight:600" for="switch"><span class="muted">Konteks</span><select id="switch">${ME.memberships.map(m=>`<option value="${m.id}" ${m.id===ME.active.id?'selected':''}>${m.lsp_nama||'Platform'} · ${m.role_nama}</option>`).join('')}</select></label>`:
          `<span class="chip info">${ME.active.role_nama}</span>`}
        <div class="row" style="margin-left:auto;gap:.6rem"><span class="avatar">${ME.user.nama.split(' ').map(w=>w[0]).join('').slice(0,2)}</span><div style="line-height:1.2"><b style="font-size:.86rem">${ME.user.nama}</b><div class="muted" style="font-size:.74rem">${ME.active.role_nama}${ME.active.lsp_nama?' · '+ME.active.lsp_nama:''}</div></div><button class="btn ghost sm" data-logout="1" aria-label="Keluar">${ic('logout')}</button></div>
      </div>
      <div class="menu-mobile">${menu.filter(i=>i[0]!=='g').map(it=>`<button data-go-app="${it[0]}" class="${p===it[0]?'on':''}">${it[1]}</button>`).join('')}<button data-go-app="password">Ganti password</button><button data-logout="1">Keluar</button></div>
      <div class="content">${content}</div>
    </div>
  </div>`;
}

/* ===================== Render & event ===================== */
function render(){
  $('#root').innerHTML = (ME && S.role!=='publik' && S.inApp) ? app() : publik();
}
async function enterApp(){
  S.inApp=true; S.appPage='dashboard'; S.lspCtx='all';
  render(); window.scrollTo(0,0);
  await loadForPage('dashboard'); render();
}
async function logout(){
  try{ applyMe(await api('auth/logout',{})); }catch(e){}
  ME=null; S.inApp=false; S.role='publik'; S.listings=[]; S.reviews=[]; S.users=[]; S.rbac=null;
  go('beranda'); toast('Anda sudah keluar.');
}
/* Saat menunggu server: matikan tombol tanpa render ulang, agar isian form tidak hilang. */
function busy(on){
  S.busy=on;
  if(on){ document.querySelectorAll('#root button').forEach(b=>{ b.dataset.wasDisabled=b.disabled?'1':''; b.disabled=true; }); }
  else render();
}

document.addEventListener('click',async e=>{
  const t=e.target.closest('button,[data-skema]');if(!t||t.disabled)return;
  const d=t.dataset;
  try{
    if(t.id==='menuBtn'){S.navOpen=!S.navOpen;render();return}
    if(d.logout){await logout();return}
    if(d.enter){await enterApp();return}
    if(d.goPublic){S.inApp=false;go(d.goPublic);return}
    if(d.goApp){await goApp(d.goApp);return}
    if(d.filter){S.filter=d.filter;if(S.page!=='cari')go('cari');else render();return}
    if(d.skema){go('detail',{skema:d.skema,step:0,jadwal:0});return}
    if(d.step){S.step=Math.max(0,Math.min(4,S.step+Number(d.step)));render();return}
    if(t.id==='bayar'){
      if(!ME){toast('Masuk sebagai asesi untuk melanjutkan pembayaran.');go('login');return}
      if(ME.active.role!=='asesi'){toast('Pembayaran hanya untuk akun asesi.');return}
      toast('Pembayaran berhasil (simulasi). Permohonan masuk ke LSP.');await enterApp();return}
    if(d.go){S.inApp=false;go(d.go,d.verif?{verif:true}:{loginErr:''});return}
    if(d.etab){S.etab=d.etab;render();return}
    if(d.newform){S.form=d.newform;S.formErr='';S.formDraft=null;render();return}
    if(d.closeform){S.form=null;render();return}
    if(d.savedraft){await saveListing('draf');return}
    if(d.submit){busy(true);try{await api('listings/submit',{id:Number(d.submit)});toast('Diajukan ke Admin Platform untuk ditinjau.');await loadListings();}finally{busy(false)}return}
    if(d.withdraw){busy(true);try{await api('listings/withdraw',{id:Number(d.withdraw)});toast('Pengajuan ditarik, kembali menjadi draf.');await loadListings();}finally{busy(false)}return}
    if(d.pick){S.pick=Number(d.pick);render();return}
    if(d.decide){
      const note=($('#ap-note')?.value||'').trim();
      if(d.decide==='tayang'&&[...document.querySelectorAll('.checklist input')].some(c=>!c.checked)){toast('Centang semua checklist kurasi sebelum menyetujui.');return}
      if(d.decide!=='tayang'&&note.length<5){toast('Tulis catatan untuk LSP dulu (minimal 5 karakter).');$('#ap-note').focus();return}
      busy(true);
      try{
        await api('reviews/decide',{id:S.pick,decision:d.decide,note});
        S.pick=null; await Promise.all([loadReviews(),loadCatalog()]);
        toast(d.decide==='tayang'?'Disetujui. Listing sudah tayang di portal publik.':d.decide==='revisi'?'Permintaan revisi dikirim ke LSP.':'Listing ditolak. LSP sudah diberi tahu.');
      }finally{busy(false)}
      return}
    if(d.userform!==undefined){S.userForm=d.userform==='1';S.userErr='';S.userDraft=null;render();return}
    if(d.ustatus){busy(true);try{await api('users/status',{membership_id:Number(d.ustatus),status:d.to});toast(d.to==='aktif'?'Akses pengguna diaktifkan.':'Akses pengguna dinonaktifkan.');await loadUsers();}finally{busy(false)}return}
    if(d.toast){toast(d.toast)}
  }catch(err){ toast(err.message); render(); }
});
async function saveListing(status){
  const f=S.form;
  const payload={tipe:f,judul:$('#lf-judul').value,bidang:$('#lf-bidang').value,harga:Number($('#lf-harga').value)||0,format:$('#lf-format').value,kota:$('#lf-kota').value,deskripsi:$('#lf-desc').value,status,ack:f==='pelatihan'?$('#lf-ack').checked:false};
  busy(true);
  try{
    await api('listings',payload);
    S.form=null;S.formErr='';S.formDraft=null;S.etab='semua';await loadListings();
    toast(status==='draf'?'Disimpan sebagai draf.':'Diajukan. Tampil di portal setelah disetujui Admin Platform.');
  }catch(err){ S.formErr=err.message; S.formDraft=payload; }
  finally{ busy(false); }
}
document.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target.matches('article[data-skema]'))e.target.click()});
document.addEventListener('change',async e=>{
  if(e.target.name==='jd'){S.jadwal=Number(e.target.value);render()}
  if(e.target.id==='ctx'){S.lspCtx=e.target.value;render()}
  if(e.target.id==='switch'){
    try{ applyMe(await api('auth/switch',{membership_id:Number(e.target.value)})); await enterApp(); toast('Konteks diganti.'); }
    catch(err){ toast(err.message); render(); }
  }
});
document.addEventListener('input',e=>{if(e.target.id==='cq'){S.q=e.target.value;const pos=e.target.selectionStart;render();const n=$('#cq');n.focus();n.setSelectionRange(pos,pos)}});
document.addEventListener('submit',async e=>{
  e.preventDefault();
  const id=e.target.id;
  if(id==='heroSearch'){S.q=$('#hq').value;S.filter='Semua';go('cari')}
  if(id==='verifForm'){S.verif=true;render();toast('Sertifikat ditemukan')}
  if(id==='listingForm'){await saveListing('menunggu')}
  if(id==='loginForm'){
    const email=$('#lg-email').value.trim(), password=$('#lg-pass').value;
    S.loginEmail=email;
    if(!email||!password){S.loginErr='Email dan password wajib diisi.';render();return}
    S.loginErr=''; busy(true);
    try{ applyMe(await api('auth/login',{email,password})); S.busy=false; await enterApp(); toast('Selamat datang, '+ME.user.nama+'.'); }
    catch(err){ S.loginErr=err.message; busy(false); }
  }
  if(id==='pwForm'){
    const cur=$('#pw-cur').value, nw=$('#pw-new').value, nw2=$('#pw-new2').value;
    if(nw!==nw2){S.pwErr='Ulangi password baru dengan benar.';render();return}
    S.pwErr=''; busy(true);
    try{ applyMe(await api('auth/password',{current:cur,new:nw})); S.busy=false; S.appPage='dashboard'; await loadForPage('dashboard'); render(); toast('Password berhasil diganti.'); }
    catch(err){ S.pwErr=err.message; busy(false); }
  }
  if(id==='userForm'){
    const payload={nama:$('#uf-nama').value,email:$('#uf-email').value,role:$('#uf-role').value,password:$('#uf-pass').value};
    busy(true);
    try{ await api('users',payload); S.userForm=false; S.userErr=''; S.userDraft=null; await loadUsers(); toast('Pengguna ditambahkan. Ia wajib mengganti password saat pertama masuk.'); }
    catch(err){ S.userErr=err.message; S.userDraft={nama:payload.nama,email:payload.email,role:payload.role}; }
    finally{ busy(false); }
  }
});

/* ===================== Mulai ===================== */
(async function boot(){
  render();
  try{
    const d=await loadMe();
    await loadCatalog();
    if(ME){ await enterApp(); }
    else { if(d.expired) { S.loginErr='Sesi Anda berakhir. Silakan masuk lagi.'; } render(); }
  }catch(e){ toast(e.message); render(); }
})();
