'use strict';
/* PortalLSP: layar kerja semua menu (dimuat oleh app.js sebelum aplikasi dimulai).
   Semua teks dari server sudah di-escape oleh api()/clean(); input pengguna di-escape dengan esc(). */

/* ===================== Utilitas tampilan ===================== */
const fmtRp = n => 'Rp' + Number(n || 0).toLocaleString('id-ID');
const fmtHari = s => s ? new Date(String(s).slice(0, 10) + 'T00:00:00').toLocaleDateString('id-ID', {weekday:'short', day:'2-digit', month:'short', year:'numeric'}) : '—';
const todayStr = () => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
const daysTo = s => Math.round((new Date(String(s).slice(0, 10) + 'T00:00:00') - new Date(todayStr() + 'T00:00:00')) / 86400000);
const PST = {diajukan:'warn', perbaikan:'bad', ditolak:'bad', menunggu_bayar:'warn', pra_asesmen:'info', tidak_lanjut:'bad', siap_uji:'info', menunggu_pleno:'info', kompeten:'ok', belum_kompeten:'bad', dibatalkan:'plain'};
const TRACK_N = {diajukan:1, perbaikan:1, ditolak:1, menunggu_bayar:2, pra_asesmen:3, tidak_lanjut:3, siap_uji:4, menunggu_pleno:5, kompeten:7, belum_kompeten:6, dibatalkan:0};
const AKTIF_ST = ['diajukan', 'perbaikan', 'menunggu_bayar', 'pra_asesmen', 'siap_uji', 'menunggu_pleno'];
const stChip = p => `<span class="chip ${PST[p.status] || 'plain'}">${p.status_label}</span>`;
const loading = () => '<div class="card"><p class="muted">Memuat…</p></div>';
const tr = cells => `<tr>${cells.map(c => `<td>${c ?? '—'}</td>`).join('')}</tr>`;
function table(headers, rows, empty = 'Belum ada data.') {
  return `<div class="table-wrap"><table><thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.length ? rows.join('') : `<tr><td colspan="${headers.length}" class="muted">${empty}</td></tr>`}</tbody></table></div>`;
}
function phead(eyebrow, title, actions = '') {
  return `<div class="spread"><div><p class="eyebrow">${eyebrow}</p><h2>${title}</h2></div>${actions ? `<div class="row">${actions}</div>` : ''}</div>`;
}
function tabs(key, list) {
  const cur = (S.tab[key] ?? list[0][0]);
  return `<div class="tabs">${list.map(([k, l, n]) => `<button class="fchip ${cur === k ? 'on' : ''}" data-tab="${key}" data-v="${k}">${l}${n !== undefined ? ` <span class="num">(${n})</span>` : ''}</button>`).join('')}</div>`;
}
const curTab = (key, def) => S.tab[key] ?? def;
const fld = (label, inner, full) => `<label class="f"${full ? ' style="grid-column:1/-1"' : ''}>${label}${inner}</label>`;
const inp = (name, val = '', attrs = '') => `<input name="${name}" value="${val ?? ''}" ${attrs}>`;
const sel = (name, opts, val, attrs = '') => `<select name="${name}" ${attrs}>${opts.map(o => { const [v, l] = Array.isArray(o) ? o : [o, o]; return `<option value="${v}" ${String(v) === String(val ?? '') ? 'selected' : ''}>${l}</option>`; }).join('')}</select>`;
const txa = (name, val = '', attrs = '') => `<textarea name="${name}" ${attrs}>${val ?? ''}</textarea>`;
const hid = (name, val) => `<input type="hidden" name="${name}" value="${val}">`;
function form(route, inner, o = {}) {
  return `<form class="stack" data-api="${route}" data-ok="${o.ok || 'Tersimpan.'}" ${o.after ? `data-after="${o.after}"` : ''} novalidate style="gap:.9rem">
    <p class="alert bad ferr" role="alert" hidden></p>${inner}
    <div class="row" style="justify-content:flex-end">${o.cancel ? `<button type="button" class="btn ghost" data-close="1">Batal</button>` : ''}<button class="btn ${o.cls || 'green'}" type="submit">${ic('check')}${o.submit || 'Simpan'}</button></div></form>`;
}
const btnAct = (route, params, label, cls = 'sm', extra = '') => `<button class="btn ${cls}" data-act="${route}" ${Object.entries(params).map(([k, v]) => `data-p-${k.replace(/_/g, '-')}="${v}"`).join(' ')} ${extra}>${label}</button>`;
const docLink = d => `<a class="chip info" href="api.php?r=dokumen/file&id=${d.id}" target="_blank" rel="noopener">${ic('doc')}${d.label}</a>`;
const lspOk = item => S.lspCtx === 'all' || !item.lsp_nama || item.lsp_nama === S.lspCtx;
const needLspPick = () => isPlatform() && !S.lspFilter;
const pickLspNote = what => `<div class="alert info">${ic('build')}<span>Pilih LSP di bagian atas untuk ${what}.</span></div>`;
function unitTable(units, map, opts = {}) {
  return table(['Kode unit', 'Judul unit', opts.label || 'Hasil'], units.map(u => {
    const v = map ? map[u.id] : undefined;
    const cell = opts.input
      ? `<div class="row" style="gap:.6rem;flex-wrap:nowrap">${['K', 'BK'].map(x => `<label class="row" style="gap:.25rem;font-weight:700"><input type="radio" name="${opts.input}-${u.id}" data-map="${opts.input}" data-key="${u.id}" value="${x}" ${(opts.cur || {})[u.id] === x ? 'checked' : ''} style="width:auto">${x}</label>`).join('')}</div>`
      : (v ? `<span class="chip ${v === 'K' ? 'ok' : 'bad'}">${v === 'K' ? 'Kompeten' : 'Belum kompeten'}</span>` : '—');
    return tr([`<span class="mono">${u.kode}</span>`, u.judul, cell]);
  }));
}
function infoGrid(rows) {
  return `<div class="grid g2" style="font-size:.92rem;gap:.8rem">${rows.filter(Boolean).map(([k, v]) => `<div style="min-width:0"><p class="eyebrow">${k}</p><div style="margin-top:.15rem;overflow-wrap:anywhere">${v ?? '—'}</div></div>`).join('')}</div>`;
}

/* ===================== Data per halaman ===================== */
S.d = {}; S.tab = {}; S.sel = {}; S.edit = null; S.ap = null; S.chat = null; S.payId = null; S.afterLogin = null;
const PUB = {};
function lazy(key, route) {
  if (!(key in PUB)) {
    PUB[key] = null;
    api(route).then(v => { PUB[key] = v; render(); }).catch(e => { PUB[key] = {error: e.message}; render(); });
  }
  return PUB[key];
}
const clearPub = () => { for (const k in PUB) delete PUB[k]; };
const g = route => api(withLsp(route));
const PAGE_LOAD = {
  dashboard: async () => {
    if (S.role === 'asesi') { const [p, s, t] = await Promise.all([api('asesi/permohonan'), api('asesi/sertifikat'), api('asesi/tagihan')]); return {p: p.items, s: s.items, t: t.items}; }
    if (S.role === 'asesor') { const [j, p, a, pl] = await Promise.all([api('asesor/jadwal'), api('asesor/pra'), api('asesor/asesmen'), api('pleno')]); return {j: j.items, p: p.items, a: a.items, pl: pl.items}; }
    if (S.role === 'admin') return g('lsp/dashboard');
    if (S.role === 'tuk') return g('tuk/dashboard');
    if (S.role === 'super') return api('platform/dashboard');
  },
  dashLsp: () => g('lsp/dashboard'),
  dashTuk: () => g('tuk/dashboard'),
  skema: async () => { const [c, d] = await Promise.all([api('pub/catalog'), api('dokumen')]); return {cat: c.items, dok: d, det: S.ap ? await api('pub/skema?id=' + S.ap.id) : null}; },
  jadwal: () => api('asesi/permohonan'),
  bayar: () => api('asesi/tagihan'),
  sertifikat: () => api('asesi/sertifikat'),
  kelas: async () => { const [k, c] = await Promise.all([api('asesi/kelas'), api('pub/catalog')]); return {mine: k.items, cat: c.items.filter(x => x.tipe === 'pelatihan')}; },
  profil: () => api('dokumen'),
  kalender: () => api('asesor/jadwal'),
  pra: () => api('asesor/pra'),
  asesmen: () => api('asesor/asesmen'),
  pleno: () => api('pleno'),
  riwayat: () => api('asesor/riwayat'),
  honor: () => api('asesor/honor'),
  daftar: () => g('lsp/pendaftaran'),
  jadwalA: async () => { const [j, o] = await Promise.all([g('lsp/jadwal'), g('lsp/opsi')]); return {j: j.items, o}; },
  asesmenA: () => g('lsp/asesmen'),
  plenoA: async () => { const [p, h] = await Promise.all([g('pleno'), g('lsp/hasil')]); return {p, h}; },
  master: async () => { const [s, t, a] = await Promise.all([g('lsp/skema'), g('lsp/tuk'), g('lsp/asesor')]); return {s: s.items, t: t.items, a: a.items}; },
  alumni: () => g('lsp/alumni' + (S.alumniQ ? '?q=' + encodeURIComponent(S.alumniQ) : '')),
  mutu: () => g('lsp/mutu'),
  keuangan: () => g('lsp/keuangan'),
  crm: () => g('lsp/crm'),
  laporan: () => g('lsp/laporan?tahun=' + (S.lapTahun || new Date().getFullYear())),
  setting: () => needLspPick() ? null : g('lsp/pengaturan'),
  support: () => g('tiket'),
  pemohon: () => g('tuk/pemohon'),
  jadwalT: () => g('tuk/jadwal'),
  sarpras: async () => { const [s, d] = await Promise.all([g('tuk/sarpras'), g('tuk/dashboard')]); return {s: s.items, tuk: d.items}; },
  chat: () => g('chat/ruang'),
  alumniT: () => g('tuk/alumni'),
  paket: () => api('platform/paket'),
  pustaka: () => api('skkni' + (S.skkniQ ? '?q=' + encodeURIComponent(S.skkniQ) : '')),
  audit: () => g('audit'),
  etalase: () => can('listing.manage') ? g('lsp/opsi') : null,
};
async function loadForPage(p) {
  try {
    if (isPlatform() && !S.lsps.length) await loadLsps();
    if (p === 'lspList') await loadLsps();
    if (p === 'etalase' || p === 'dashboard' || p === 'dashLsp') await loadListings();
    if (p === 'approval' || p === 'dashboard') await loadReviews();
    if (p === 'users') { await loadUsers(); await loadRbac(); }
    if (p === 'rbac') await loadRbac();
    if (p === 'dashboard' || p === 'profil') await loadProfile();
    if (p === 'notif') await loadNotifs();
    if (p === 'notiflog') await loadNotifLog();
    if (PAGE_LOAD[p]) S.d[p] = await PAGE_LOAD[p]();
    if (S.chat) S.d.chatMsgs = (await api('chat?jadwal_id=' + S.chat)).items;
  } catch (e) { toast(e.message); }
}
async function reloadPage() { await loadForPage(S.appPage); render(); }
async function goApp(p) { S.appPage = p; S.drawer = false; S.edit = null; S.chat = null; S.payId = null; render(); window.scrollTo(0, 0); await loadForPage(p); render(); }
async function enterApp() {
  S.inApp = true; clearPub();
  const next = S.afterLogin; S.afterLogin = null;
  S.appPage = next && allowedPage(next) ? next : 'dashboard'; S.lspCtx = 'all';
  render(); window.scrollTo(0, 0); await loadForPage(S.appPage); render();
}

/* ===================== Formulir & aksi generik ===================== */
function collect(f) {
  const body = {};
  for (const el of f.elements) {
    if (!el.name || el.disabled || el.type === 'file') continue;
    let v;
    if (el.type === 'checkbox') v = el.checked;
    else if (el.type === 'radio') { if (!el.checked) continue; v = el.value; }
    else if (el.type === 'number' || el.dataset.num !== undefined) v = el.value === '' ? 0 : Number(el.value);
    else v = el.value;
    if (el.dataset.map) { (body[el.dataset.map] = body[el.dataset.map] || {})[el.dataset.key] = v; }
    else body[el.name] = v;
  }
  if (isPlatform() && S.lspFilter && body.lsp_id === undefined) body.lsp_id = S.lspFilter;
  return body;
}
document.addEventListener('submit', async e => {
  const f = e.target.closest('form[data-api]');
  if (!f) return;
  e.preventDefault();
  const err = f.querySelector('.ferr');
  const btns = [...f.querySelectorAll('button')];
  btns.forEach(b => b.disabled = true);
  try {
    const r = await api(withLsp(f.dataset.api), collect(f));
    toast(f.dataset.ok || 'Tersimpan.');
    S.edit = null;
    if (f.dataset.after && AFTER[f.dataset.after]) await AFTER[f.dataset.after](r); else await reloadPage();
  } catch (x) {
    if (err) { err.textContent = x.message; err.hidden = false; err.scrollIntoView({block:'nearest', behavior:'smooth'}); } else toast(x.message);
    btns.forEach(b => b.disabled = false);
  }
});
const AFTER = {
  alumniCari: async () => { await reloadPage(); },
  skemaSimpan: async r => { await reloadPage(); if (r && r.ditinjau_ulang) toast('Skema disimpan. Listing yang tayang ditarik sementara dan menunggu ditinjau ulang Admin Platform.'); },
  profil: async () => { await loadProfile(); if (ME && S.profile) ME.user.nama = S.profile.nama; await reloadPage(); },
};
document.addEventListener('click', async e => {
  const t = e.target.closest('[data-act],[data-tab],[data-sel],[data-edit],[data-close],[data-x]');
  if (!t || t.disabled) return;
  const d = t.dataset;
  try {
    if (d.tab !== undefined) { S.tab[d.tab] = d.v; S.sel[S.appPage] = null; render(); return; }
    if (d.sel !== undefined) { S.sel[S.appPage] = Number(d.sel); render(); const p = document.querySelector('#detail'); if (p && window.innerWidth < 900) p.scrollIntoView({behavior:'smooth'}); return; }
    if (d.edit !== undefined) { S.edit = d.edit; render(); const f = document.querySelector('#editor'); if (f) f.scrollIntoView({behavior:'smooth', block:'start'}); return; }
    if (d.close !== undefined) { S.edit = null; S.payId = null; render(); return; }
    if (d.x) { await X[d.x](t); return; }
    if (d.act) {
      if (d.confirm && !confirm(d.confirm)) return;
      const body = {};
      for (const k in d) {
        if (k.length > 1 && k[0] === 'p' && k[1] === k[1].toUpperCase() && /[A-Z]/.test(k[1])) {
          const key = (k[1].toLowerCase() + k.slice(2)).replace(/[A-Z]/g, c => '_' + c.toLowerCase());
          body[key] = /^\d+$/.test(d[k]) ? Number(d[k]) : d[k];
        }
      }
      if (d.with) d.with.split(',').forEach(s => { const el = document.querySelector(s); if (el) body[el.name] = el.type === 'checkbox' ? el.checked : el.value; });
      busy(true);
      try { await api(withLsp(d.act), body); toast(d.ok || 'Berhasil.'); await reloadPage(); }
      catch (x) { toast(x.message); render(); }
      finally { S.busy = false; }
    }
  } catch (x) { toast(x.message); }
});
document.addEventListener('change', e => {
  const t = e.target;
  if (!S.ap) return;
  if (t.name === 'ap-jadwal') S.ap.jadwal = Number(t.value);
  if (t.dataset.map === 'apl') S.ap.apl[t.dataset.key] = t.value;
  if (t.name === 'ap-tujuan') S.ap.tujuan = t.value;
  if (t.name === 'ap-consent') S.ap.consent = t.checked;
});
document.addEventListener('change', async e => {
  const t = e.target;
  if (t.matches('select[data-assign]')) {
    try { await api(withLsp('lsp/jadwal/asesor'), {jadwal_id: Number(t.dataset.assign), asesor_id: Number(t.value)}); toast(Number(t.value) ? 'Asesor ditugaskan dan diberi notifikasi.' : 'Penugasan dihapus.'); }
    catch (x) { toast(x.message); }
    await reloadPage();
  }
  if (t.matches('select[data-paket]')) {
    try { await api('platform/paket', {id: Number(t.dataset.paket), paket: t.value}); toast('Paket diperbarui.'); } catch (x) { toast(x.message); }
    await reloadPage();
  }
  if (t.matches('select#lapTahun')) { S.lapTahun = Number(t.value); await reloadPage(); }
});

/* Aksi khusus (data-x="nama") */
const X = {
  async apStart(t) {
    if (!ME) { S.afterLogin = 'skema'; S.ap = {id: Number(t.dataset.id), step: 0, jadwal: Number(t.dataset.jadwal || 0), apl: {}, tujuan: 'Sertifikasi', consent: false}; go('login', {loginErr: 'Masuk atau daftar sebagai asesi untuk melanjutkan pendaftaran.'}); return; }
    if (!ME.permissions.includes('application.own')) { toast('Pendaftaran skema hanya untuk akun asesi.'); return; }
    S.ap = {id: Number(t.dataset.id), step: 0, jadwal: Number(t.dataset.jadwal || 0), apl: {}, tujuan: 'Sertifikasi', consent: false};
    S.inApp = true; await goApp('skema');
  },
  apPrev() { S.ap.step = Math.max(0, S.ap.step - 1); render(); window.scrollTo(0, 0); },
  apNext() {
    const det = S.d.skema && S.d.skema.det;
    if (S.ap.step === 0 && !S.ap.jadwal) { toast('Pilih jadwal uji dulu.'); return; }
    if (S.ap.step === 1 && !ME.user.email_verified) { toast('Verifikasi email Anda dulu. Buka tautan di email, atau kirim ulang dari banner.'); return; }
    if (S.ap.step === 2 && det && det.skema.units.some(u => !S.ap.apl[u.id])) { toast('Isi asesmen mandiri untuk semua unit.'); return; }
    if (S.ap.step === 3) { const have = (S.d.skema.dok.items || []).map(x => x.jenis); if (S.d.skema.dok.wajib.some(w => !have.includes(w))) { toast('Lengkapi dokumen wajib dulu.'); return; } }
    S.ap.step++; render(); window.scrollTo(0, 0);
  },
  apAllK() { S.d.skema.det.skema.units.forEach(u => S.ap.apl[u.id] = 'K'); render(); },
  apCancel() { S.ap = null; reloadPage(); },
  async apSubmit(t) {
    if (!S.ap.consent) { toast('Centang persetujuan berbagi data ke LSP.'); return; }
    t.disabled = true;
    try {
      await api('asesi/apply', {jadwal_id: S.ap.jadwal, apl02: S.ap.apl, tujuan: S.ap.tujuan, consent: true});
      S.ap = null; clearPub(); await loadMe();
      toast('Pendaftaran terkirim. LSP akan memverifikasi berkas Anda.');
      await goApp('jadwal');
    } catch (x) { toast(x.message); t.disabled = false; }
  },
  async docUpload(t) {
    const jenis = t.dataset.jenis, input = document.getElementById('file-' + jenis);
    if (!input || !input.files[0]) { toast('Pilih berkas dulu.'); return; }
    if (input.files[0].size > 2097152) { toast('Ukuran berkas maksimal 2 MB.'); return; }
    const fd = new FormData(); fd.append('jenis', jenis); fd.append('file', input.files[0]);
    t.disabled = true;
    try {
      const res = await fetch('api.php?r=dokumen/upload', {method:'POST', credentials:'same-origin', headers:{'X-CSRF-Token': CSRF, Accept:'application/json'}, body: fd});
      const j = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(j.error || 'Gagal mengunggah.');
      toast('Dokumen diunggah.'); await reloadPage();
    } catch (x) { toast(x.message); t.disabled = false; }
  },
  async chatOpen(t) { S.chat = Number(t.dataset.id); S.d.chatMsgs = null; render(); S.d.chatMsgs = (await api('chat?jadwal_id=' + S.chat)).items; render(); const b = document.querySelector('.chat-body'); if (b) b.scrollTop = b.scrollHeight; },
  chatClose() { S.chat = null; render(); },
  async chatSend(t) {
    const el = document.getElementById('chat-isi'); const isi = el.value.trim(); if (!isi) return;
    t.disabled = true;
    try { await api('chat/kirim', {jadwal_id: S.chat, isi}); S.d.chatMsgs = (await api('chat?jadwal_id=' + S.chat)).items; render(); const b = document.querySelector('.chat-body'); if (b) b.scrollTop = b.scrollHeight; }
    catch (x) { toast(x.message); t.disabled = false; }
  },
  payOpen(t) { S.payId = Number(t.dataset.id); render(); },
  async payNow(t) {
    const m = document.querySelector('input[name="metode"]:checked');
    if (!m) { toast('Pilih metode pembayaran.'); return; }
    t.disabled = true;
    try { await api('asesi/bayar', {id: S.payId, metode: m.value}); S.payId = null; toast('Pembayaran berhasil. Permohonan masuk tahap pra-asesmen.'); await reloadPage(); }
    catch (x) { toast(x.message); t.disabled = false; }
  },
  async copyLink(t) { try { await navigator.clipboard.writeText(t.dataset.url); toast('Tautan verifikasi disalin.'); } catch (e) { prompt('Salin tautan ini:', t.dataset.url); } },
  cekPublik(t) { S.inApp = false; S.cekQ = t.dataset.kode; delete PUB.cek; go('verif'); },
  async kelasNext(t) { await api('asesi/kelas/progres', {id: Number(t.dataset.id), progres: Math.min(100, Number(t.dataset.p) + 20)}); toast('Progres disimpan.'); await reloadPage(); },
  async alumniCari() { S.alumniQ = (document.getElementById('alumni-q') || {}).value || ''; await reloadPage(); },
  async skkniUbah(t) {
    const judul = prompt('Judul unit ' + t.dataset.kode, t.dataset.judul);
    if (judul === null) return;
    const sektor = prompt('Sektor', t.dataset.sektor);
    if (sektor === null) return;
    busy(true);
    try { await api('skkni', {kode: t.dataset.kode, judul, sektor, ubah: true}); toast('Unit diperbarui.'); await reloadPage(); }
    catch (x) { toast(x.message); render(); }
    finally { S.busy = false; }
  },
  async skkniCari() { S.skkniQ = (document.getElementById('skkni-q') || {}).value || ''; await reloadPage(); },
  skkniPakai(t) { const ta = document.querySelector('textarea[name="units_text"]'); if (!ta) { toast('Buka form skema dulu.'); return; } ta.value = (ta.value.trim() ? ta.value.trim() + '\n' : '') + unesc(t.dataset.kode) + ' | ' + unesc(t.dataset.judul); toast('Unit ditambahkan ke form skema.'); },
  logbook() {
    // Sel diawali = + - @ diberi awalan ' agar tidak dijalankan sebagai rumus oleh aplikasi spreadsheet.
    const cell = v => { let t = unesc(String(v ?? '')); if (/^[=+\-@\t\r]/.test(t)) t = "'" + t; return '"' + t.replace(/"/g, '""') + '"'; };
    const rows = (S.d.riwayat.items || []).map(r => [r.diuji_at, r.lsp_nama, r.skema_nama, r.asesi_nama, r.rekomendasi, r.keputusan || '-'].map(cell).join(','));
    const blob = new Blob(['Tanggal uji,LSP,Skema,Asesi,Rekomendasi,Keputusan pleno\n' + rows.join('\n')], {type: 'text/csv'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'logbook-asesor.csv'; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
  },
  pubDetail(t) { go('detail', {skema: t.dataset.id}); },
};

/* ===================== Komponen chat ===================== */
function chatBox() {
  if (!S.chat) return '';
  const msgs = S.d.chatMsgs;
  return `<div class="card stack" id="chatbox" style="gap:.7rem"><div class="spread"><h3>${ic('chat')}Group chat jadwal</h3><button class="btn ghost sm" data-x="chatClose">Tutup</button></div>
    <div class="chat-body" style="max-height:340px;overflow-y:auto;display:flex;flex-direction:column;gap:.5rem;background:var(--bg);border-radius:14px;padding:.8rem">
      ${msgs === null || msgs === undefined ? '<p class="muted">Memuat…</p>' : msgs.length ? msgs.map(m => `<div class="ai-msg ${m.saya ? 'user' : 'assistant'}" style="max-width:85%"><b style="font-size:.75rem;display:block;opacity:.8">${m.nama} · ${fmtWaktu(m.created_at)}</b>${m.isi}</div>`).join('') : '<p class="muted">Belum ada pesan.</p>'}
    </div>
    <div class="row" style="flex-wrap:nowrap"><input id="chat-isi" maxlength="1000" placeholder="Tulis pesan…" style="flex:1"><button class="btn purple" data-x="chatSend">${ic('send')}Kirim</button></div></div>`;
}

/* ===================== ASESI ===================== */
function dAsesi() {
  const D = S.d.dashboard;
  if (!D || !D.p) return loading();
  const P = D.p.filter(lspOk).sort((a, b) => (AKTIF_ST.includes(b.status) ? 1 : 0) - (AKTIF_ST.includes(a.status) ? 1 : 0)), aktif = P.filter(p => AKTIF_ST.includes(p.status));
  if (!D.p.length) return dAsesiBaru();
  const belum = D.t.filter(t => t.status === 'belum');
  const next = aktif.filter(p => p.tanggal >= todayStr()).sort((a, b) => a.tanggal < b.tanggal ? -1 : 1)[0];
  const habis = D.s.filter(s => daysTo(s.berlaku) <= 180 && daysTo(s.berlaku) >= 0);
  const act = p => p.status === 'menunggu_bayar' ? `<button class="btn sm orange" data-go-app="bayar">${ic('wallet')}Bayar ${fmtRp(p.harga)}</button>`
    : p.status === 'perbaikan' ? `<button class="btn sm pink" data-go-app="jadwal">Perbaiki berkas</button>`
    : p.status === 'kompeten' ? `<button class="btn sm green" data-go-app="sertifikat">${ic('cert')}Lihat sertifikat</button>`
    : `<button class="btn sm" data-go-app="jadwal">Lihat detail</button>`;
  return `${phead(new Date().toLocaleDateString('id-ID', {weekday:'long', day:'numeric', month:'long', year:'numeric'}), 'Halo, ' + ME.user.nama.split(' ')[0], `<button class="btn purple" data-go-app="skema">${ic('search')}Daftar skema baru</button>`)}
  <div class="grid g4">${kpi('doc', 'blue', aktif.length, 'Permohonan aktif')}${kpi('cal', 'teal', next ? fmtHari(next.tanggal).replace(/ \d{4}$/, '') : '—', 'Jadwal uji terdekat', next ? next.skema_nama : '')}${kpi('wallet', 'orange', belum.length, 'Tagihan belum dibayar')}${kpi('cert', 'green', D.s.length, 'Sertifikat dimiliki')}</div>
  <div class="layout-2">
    <div class="stack"><div class="spread"><h3>Status permohonan</h3><span class="chip plain">Dari ${S.lspCtx === 'all' ? 'semua LSP' : S.lspCtx}</span></div>
      ${P.slice(0, 6).map(p => `<div class="card"><div class="spread"><div style="min-width:0"><h3>${p.skema_nama}</h3><p class="muted" style="font-size:.84rem">${p.lsp_nama} · ${fmtHari(p.tanggal)}${p.tuk_nama ? ' · ' + p.tuk_nama : ''}</p></div>${stChip(p)}</div>
        ${track(TRACK_N[p.status] ?? 0)}<div class="spread" style="margin-top:.9rem"><span class="muted" style="font-size:.84rem">${p.catatan_admin && p.status === 'perbaikan' ? 'Catatan LSP: ' + p.catatan_admin : p.asesor_nama && ['pra_asesmen', 'siap_uji'].includes(p.status) ? 'Asesor: ' + p.asesor_nama : ''}</span>${act(p)}</div></div>`).join('') || '<div class="card"><p class="muted">Tidak ada permohonan di LSP ini.</p></div>'}
    </div>
    <div class="stack"><h3>Dompet sertifikat</h3>
      ${D.s.slice(0, 2).map((s, i) => `<div class="cert-card" style="background:${i ? 'var(--g-blue)' : 'var(--g-teal)'};--s:${i ? 'var(--s-blue)' : 'var(--s-teal)'}"><p class="eyebrow" style="color:rgba(255,255,255,.85)">${s.lsp_nama}</p><h3 style="margin-top:.3rem">${s.skema_nama}</h3><p class="mono" style="opacity:.9;margin-top:.5rem">${s.nomor}</p><div class="spread" style="margin-top:.8rem"><span class="chip" style="background:rgba(255,255,255,.2);color:#fff">Berlaku s.d. ${fmtTgl(s.berlaku)}</span><button class="btn sm glass" data-go-app="sertifikat">Buka</button></div></div>`).join('') || '<div class="card"><p class="muted">Belum ada sertifikat.</p></div>'}
      ${habis.map(s => `<div class="alert warn">${ic('bell')}<span>Sertifikat ${s.skema_nama} habis dalam ${daysTo(s.berlaku)} hari. Daftar sertifikasi ulang agar tidak terputus.</span></div>`).join('')}
      ${belum.length ? `<div class="alert warn">${ic('wallet')}<span>${belum.length} tagihan menunggu pembayaran. <button class="btn sm orange" data-go-app="bayar" style="margin-top:.4rem">Bayar</button></span></div>` : ''}
    </div>
  </div>`;
}
function dAsesiBaru() {
  const p = S.profile || {};
  const steps = [['Akun dibuat', true], ['Email terverifikasi', !!(ME.user.email_verified)], ['Lengkapi dokumen pribadi', false, 'profil'], ['Daftar skema pertama', false, 'skema']];
  return `${phead('Selamat datang', 'Halo, ' + ME.user.nama.split(' ')[0], `<button class="btn purple" data-go-app="skema">${ic('search')}Cari skema</button>`)}
  <div class="layout-2">
    <div class="card stack"><h3>Langkah berikutnya</h3>
      ${steps.map((s, i) => `<div class="spread"><div class="row" style="gap:.7rem"><span class="step-n" style="margin:0;width:32px;height:32px;background:${s[1] ? 'var(--g-green)' : 'var(--surface-2)'};color:${s[1] ? '#fff' : 'var(--muted)'}">${s[1] ? ic('check') : i + 1}</span><b style="${s[1] ? '' : 'color:var(--muted)'}">${s[0]}</b></div>${!s[1] && s[2] ? `<button class="btn sm" data-go-app="${s[2]}">Mulai</button>` : ''}</div>`).join('')}
    </div>
    <div class="card stack"><h3>Belum ada permohonan</h3><p class="muted">Cari skema dari semua LSP, pilih jadwal dan TUK, isi asesmen mandiri, lalu ajukan. Data Anda baru dibagikan ke LSP yang Anda pilih.</p><button class="btn" data-go-app="skema">${ic('search')}Mulai cari skema</button></div>
  </div>`;
}
function pAsesiSkema() {
  const D = S.d.skema;
  if (!D) return loading();
  if (S.ap) return apWizard(D);
  const q = (S.q || '').toLowerCase(), b = S.tab.cariBidang || 'Semua';
  const list = D.cat.filter(c => c.tipe === 'skema' && c.skema_id && (b === 'Semua' || c.bidang === b) && (!q || c.judul.toLowerCase().includes(q)));
  return `${phead('Marketplace skema', 'Daftar skema baru')}
  <div class="card stack" style="gap:.7rem"><input id="cq" placeholder="Cari nama skema" value="${esc(S.q || '')}" aria-label="Cari skema">${tabs('cariBidang', ['Semua', 'TIK', 'Pariwisata', 'Konstruksi', 'Bisnis', 'Kesehatan'].map(x => [x, x]))}</div>
  <div class="grid g3">${list.map(c => `<div class="card stack" style="gap:.6rem"><div class="spread"><span class="badge" style="background:${GRAD[WARNA[c.id % WARNA.length]]}">${c.kode || initials(c.judul)}</span><span class="chip info">${c.bidang}</span></div>
    <div><h3>${c.judul}</h3><p class="muted" style="font-size:.84rem">${c.lsp_nama} · ${c.kota}</p></div>
    <div class="meta"><span class="chip plain">KKNI ${c.kkni || '—'}</span><span class="chip plain">${c.unit} unit</span><span class="chip ${c.format === 'SJJ' ? 'ok' : 'plain'}">${c.format}</span></div>
    <div class="spread" style="margin-top:auto"><div><span class="eyebrow">Biaya uji</span><div class="price">${fmtRp(c.harga)}</div></div>${c.jadwal_terdekat ? `<button class="btn sm green" data-x="apStart" data-id="${c.id}">Daftar</button>` : '<span class="chip plain">Belum ada jadwal</span>'}</div>
    <p class="muted" style="font-size:.78rem">${c.jadwal_terdekat ? 'Jadwal terdekat ' + fmtHari(c.jadwal_terdekat) : ''}</p></div>`).join('') || '<div class="card empty" style="grid-column:1/-1"><h3>Tidak ada skema yang cocok</h3></div>'}</div>`;
}
function apWizard(D) {
  const det = D.det;
  if (!det) return loading();
  const A = S.ap, sk = det.skema, L = det.listing;
  const steps = ['Pilih jadwal', 'Data diri', 'Asesmen mandiri (APL.02)', 'Dokumen', 'Konfirmasi'];
  const jad = det.jadwal.find(j => j.id === A.jadwal);
  let body = '';
  if (A.step === 0) {
    body = det.jadwal.length ? `<div class="stack" style="gap:.6rem">${det.jadwal.map(j => `<label class="sched-opt ${A.jadwal === j.id ? 'on' : ''}" ${j.sisa ? '' : 'style="opacity:.55"'}><input type="radio" name="ap-jadwal" value="${j.id}" ${A.jadwal === j.id ? 'checked' : ''} ${j.sisa ? '' : 'disabled'} style="width:auto">
      <div style="flex:1;min-width:0"><b>${fmtHari(j.tanggal)}</b> · <span class="muted">${j.jam} WIB</span><div class="muted" style="font-size:.85rem">${j.tuk_nama || 'TUK menyusul'} · ${j.metode}</div></div>
      ${j.sisa ? `<span class="chip ${j.sisa <= 3 ? 'warn' : 'ok'}">Sisa ${j.sisa}/${j.kuota}</span>` : '<span class="chip bad">Penuh</span>'}</label>`).join('')}</div>` : '<p class="muted">Belum ada jadwal terbuka untuk skema ini.</p>';
  } else if (A.step === 1) {
    const p = S.profile || {};
    body = `${!ME.user.email_verified ? `<div class="alert warn">${ic('bell')}<span>Email Anda belum diverifikasi. Buka tautan di email lalu muat ulang halaman ini. <button class="btn sm orange" data-resend="1" style="margin-top:.4rem">Kirim ulang email</button></span></div>` : ''}
      ${infoGrid([['Nama lengkap', ME.user.nama], ['NIK', p.nik ? `<span class="mono">${p.nik}</span>` : '—'], ['Tanggal lahir', p.tanggal_lahir ? fmtTgl(p.tanggal_lahir) : '—'], ['Email', ME.user.email + (ME.user.email_verified ? ' <span class="chip ok">Terverifikasi</span>' : ' <span class="chip warn">Belum diverifikasi</span>')], ['No. HP', p.no_hp || '—']])}
      <p class="muted" style="font-size:.84rem">Data diri dari profil Anda dipakai untuk FR.APL.01. Data ini baru dibagikan ke ${L.lsp_nama} setelah Anda mengajukan.</p>`;
  } else if (A.step === 2) {
    body = `<div class="spread"><p class="muted">Nilai diri Anda untuk setiap unit kompetensi: <b>K</b> (kompeten) atau <b>BK</b> (belum kompeten).</p><button class="btn sm ghost" data-x="apAllK">Saya kompeten di semua unit</button></div>${unitTable(sk.units, null, {input: 'apl', label: 'Penilaian diri', cur: A.apl})}`;
  } else if (A.step === 3) {
    const have = Object.fromEntries((D.dok.items || []).map(x => [x.jenis, x]));
    body = `<div class="stack" style="gap:.6rem">${Object.entries(D.dok.jenis).map(([k, l]) => docRow(k, l, have[k], D.dok.wajib.includes(k))).join('')}</div>
      <p class="muted" style="font-size:.82rem">Format PDF, JPG, atau PNG, maksimal 2 MB. Dokumen disimpan di profil dan bisa dipakai ulang untuk skema berikutnya.</p>`;
  } else {
    body = `${infoGrid([['Skema', sk.nama], ['LSP', L.lsp_nama], ['Jadwal', jad ? fmtHari(jad.tanggal) + ' · ' + jad.jam : '—'], ['TUK', jad ? (jad.tuk_nama || '—') + ' · ' + jad.metode : '—'], ['APL.02', Object.values(A.apl).filter(v => v === 'K').length + ' dari ' + sk.units.length + ' unit kompeten'], ['Biaya uji', fmtRp(sk.harga) + ' (dibayar setelah berkas diverifikasi)']])}
      <label class="f">Tujuan asesmen<select name="ap-tujuan">${['Sertifikasi', 'Sertifikasi ulang', 'Pengakuan Kompetensi Terkini (PKT)', 'Rekognisi Pembelajaran Lampau (RPL)', 'Lainnya'].map(o => `<option ${A.tujuan === o ? 'selected' : ''}>${o}</option>`).join('')}</select></label>
      <label class="toggle"><input type="checkbox" name="ap-consent" ${A.consent ? 'checked' : ''}><span>Saya menyetujui data diri, dokumen, dan asesmen mandiri saya dibagikan ke <b>${L.lsp_nama}</b> untuk proses sertifikasi ini, dan menyatakan data yang saya isi benar.</span></label>`;
  }
  return `<div class="spread"><div><p class="eyebrow">Pendaftaran uji kompetensi</p><h2>${sk.nama}</h2><p class="muted">${L.lsp_nama}</p></div><button class="btn ghost sm" data-x="apCancel">Batalkan</button></div>
  <div class="layout-2"><div class="card">
    <div class="stepper">${steps.map((s, i) => `<span class="${i < A.step ? 'done' : i === A.step ? 'now' : ''}">${i + 1}. ${s}</span>`).join('')}</div>
    <h3 style="margin-bottom:.9rem">${steps[A.step]}</h3>${body}
    <div class="spread" style="margin-top:1.2rem"><button class="btn ghost" data-x="apPrev" ${A.step === 0 ? 'hidden' : ''}>Kembali</button>
      ${A.step < 4 ? `<button class="btn ${['blue', 'purple', 'orange', 'teal'][A.step]}" data-x="apNext" style="margin-left:auto">Lanjut</button>` : `<button class="btn green lg" data-x="apSubmit" style="margin-left:auto">${ic('check')}Ajukan pendaftaran</button>`}</div>
  </div>
  <aside class="stack"><div class="card"><p class="eyebrow">Ringkasan</p><div class="stack" style="gap:.5rem;margin-top:.6rem;font-size:.9rem">
    <div class="spread"><span class="muted">Jadwal</span><b>${jad ? fmtHari(jad.tanggal) : '—'}</b></div><div class="spread"><span class="muted">Metode</span><b>${jad ? jad.metode : '—'}</b></div>
    <div class="spread"><span class="muted">Unit</span><b>${sk.units.length}</b></div><div class="spread"><b>Biaya uji</b><span class="price">${fmtRp(sk.harga)}</span></div></div></div>
    ${sk.persyaratan ? `<div class="card"><h3>Persyaratan</h3><ul style="margin:.5rem 0 0;padding-left:1.1rem;font-size:.88rem;display:grid;gap:.25rem">${sk.persyaratan.split('\n').map(x => `<li>${x}</li>`).join('')}</ul></div>` : ''}
  </aside></div>`;
}
function docRow(k, label, d, wajib) {
  return `<div class="card spread" style="padding:.8rem"><div style="min-width:0"><b>${label}</b> ${wajib ? '<span class="chip warn">Wajib</span>' : '<span class="chip plain">Opsional</span>'}
      <div class="muted" style="font-size:.8rem;margin-top:.2rem">${d ? `${d.nama_file} · ${fmtTgl(d.created_at)}` : 'Belum diunggah'}</div></div>
    <div class="row" style="gap:.4rem">${d ? `<a class="btn sm ghost" href="api.php?r=dokumen/file&id=${d.id}" target="_blank" rel="noopener">Lihat</a>` : ''}<input type="file" id="file-${k}" accept=".pdf,.jpg,.jpeg,.png" style="max-width:210px;font-size:.8rem"><button class="btn sm ${d ? 'ghost' : 'purple'}" data-x="docUpload" data-jenis="${k}">${d ? 'Ganti' : 'Unggah'}</button></div></div>`;
}
function pAsesiJadwal() {
  const D = S.d.jadwal;
  if (!D) return loading();
  const items = D.items.filter(lspOk);
  return `${phead('Permohonan & jadwal uji', 'Jadwal saya', `<button class="btn purple" data-go-app="skema">${ic('search')}Daftar skema baru</button>`)}
  ${chatBox()}
  ${items.map(p => `<div class="card stack" style="gap:.7rem"><div class="spread"><div style="min-width:0"><h3>${p.skema_nama}</h3><p class="muted" style="font-size:.85rem">${p.lsp_nama} · Permohonan #${p.id}</p></div>${stChip(p)}</div>
    ${track(TRACK_N[p.status] ?? 0)}
    ${infoGrid([['Jadwal uji', fmtHari(p.tanggal) + ' · ' + p.jam], ['TUK & metode', (p.tuk_nama || '—') + ' · ' + p.metode], ['Asesor', p.asesor_nama || 'Belum ditentukan'], ['Tagihan', p.tagihan ? `${fmtRp(p.tagihan.jumlah)} <span class="chip ${p.tagihan.status === 'lunas' ? 'ok' : p.tagihan.status === 'belum' ? 'warn' : 'plain'}">${p.tagihan.status}</span>` : '—']])}
    ${p.catatan_admin ? `<div class="alert ${p.status === 'perbaikan' || p.status === 'ditolak' ? 'bad' : 'info'}">${ic('doc')}<span><b>Catatan LSP:</b> ${p.catatan_admin}</span></div>` : ''}
    ${p.catatan_pra ? `<div class="alert ${p.rekom_pra === 'tidak' ? 'bad' : 'info'}">${ic('users')}<span><b>Pra-asesmen:</b> ${p.catatan_pra}</span></div>` : ''}
    ${p.keputusan ? `<div class="alert ${p.keputusan === 'K' ? 'info' : 'warn'}">${ic('shield')}<span><b>Hasil pleno: ${p.keputusan === 'K' ? 'Kompeten' : 'Belum kompeten'}.</b> ${p.catatan_pleno || ''}${p.keputusan === 'BK' ? ' Anda dapat mengajukan banding ke LSP dalam 14 hari.' : ''}</span></div>` : ''}
    <div class="row" style="justify-content:flex-end">
      ${p.status === 'perbaikan' ? `<button class="btn sm ghost" data-go-app="profil">Perbarui dokumen</button>${btnAct('asesi/resubmit', {id: p.id}, 'Kirim perbaikan', 'sm pink', 'data-ok="Perbaikan dikirim ke LSP."')}` : ''}
      ${p.status === 'menunggu_bayar' ? `<button class="btn sm orange" data-go-app="bayar">${ic('wallet')}Bayar</button>` : ''}
      ${['pra_asesmen', 'siap_uji', 'menunggu_pleno'].includes(p.status) ? `<button class="btn sm purple" data-x="chatOpen" data-id="${p.jadwal_id}">${ic('chat')}Group chat</button>` : ''}
      ${p.sertifikat ? `<button class="btn sm green" data-go-app="sertifikat">${ic('cert')}Sertifikat</button>` : ''}
      ${['diajukan', 'perbaikan', 'menunggu_bayar'].includes(p.status) ? btnAct('asesi/cancel', {id: p.id}, 'Batalkan', 'sm ghost', 'data-confirm="Batalkan permohonan ini?" data-ok="Permohonan dibatalkan."') : ''}
    </div></div>`).join('') || `<div class="card empty"><h3>Belum ada permohonan</h3><p class="muted">Daftar skema untuk mulai sertifikasi.</p><div style="margin-top:1rem"><button class="btn" data-go-app="skema">Cari skema</button></div></div>`}`;
}
function pAsesiBayar() {
  const D = S.d.bayar;
  if (!D) return loading();
  const t = D.items.filter(lspOk), cur = t.find(x => x.id === S.payId);
  const methods = [['Virtual Account BCA', 'blue'], ['Virtual Account BRI', 'teal'], ['Virtual Account Mandiri', 'orange'], ['QRIS', 'green'], ['E-wallet', 'purple']];
  return `${phead('Tagihan dari semua LSP', 'Pembayaran')}
  ${cur ? `<div class="card stack" id="editor" style="border:2px solid var(--brand-b)"><div class="spread"><div><p class="eyebrow">${cur.nomor}</p><h3>${cur.deskripsi}</h3><p class="muted">${cur.lsp_nama}</p></div><span class="price">${fmtRp(cur.jumlah)}</span></div>
    <div class="pay">${methods.map(([m, w]) => `<label><input type="radio" name="metode" value="${m}"><span class="dot" style="background:${GRAD[w]}"></span>${m}</label>`).join('')}</div>
    <div class="alert info">${ic('shield')}<span>Simulasi pembayaran: belum terhubung payment gateway, jadi tagihan langsung ditandai lunas.</span></div>
    <div class="row" style="justify-content:flex-end"><button class="btn ghost" data-close="1">Batal</button><button class="btn green" data-x="payNow">${ic('wallet')}Bayar ${fmtRp(cur.jumlah)}</button></div></div>` : ''}
  <div class="card">${table(['Nomor', 'Uraian', 'LSP', 'Jumlah', 'Jatuh tempo', 'Status', ''], t.map(x => tr([`<span class="mono">${x.nomor}</span>`, x.deskripsi, x.lsp_nama, `<span class="num">${fmtRp(x.jumlah)}</span>`, fmtTgl(x.jatuh_tempo),
    `<span class="chip ${x.status === 'lunas' ? 'ok' : x.status === 'belum' ? 'warn' : 'plain'}">${x.status === 'lunas' ? 'Lunas' : x.status === 'belum' ? 'Belum dibayar' : 'Batal'}</span>${x.dibayar_at ? `<div class="muted" style="font-size:.74rem">${x.metode} · ${fmtTgl(x.dibayar_at)}</div>` : ''}`,
    x.status === 'belum' ? `<button class="btn sm orange" data-x="payOpen" data-id="${x.id}">Bayar</button>` : '—'])), 'Belum ada tagihan.')}</div>`;
}
function pAsesiSertifikat() {
  const D = S.d.sertifikat;
  if (!D) return loading();
  const c = D.items.filter(lspOk);
  return `${phead('Dompet sertifikat', 'Sertifikat kompetensi saya')}
  <div class="grid g2">${c.map((s, i) => `<div class="cert-card" style="background:${GRAD[WARNA[i % WARNA.length]]};--s:var(--s-${WARNA[i % WARNA.length]})">
    <p class="eyebrow" style="color:rgba(255,255,255,.85)">${s.lsp_nama}</p><h3 style="margin-top:.3rem;font-size:1.25rem">${s.skema_nama}</h3><p class="mono" style="opacity:.92;margin-top:.5rem">${s.nomor}</p>
    <p style="opacity:.9;font-size:.85rem;margin-top:.3rem">Terbit ${fmtTgl(s.terbit)} · Berlaku s.d. ${fmtTgl(s.berlaku)}${daysTo(s.berlaku) <= 180 ? ` · <b>${daysTo(s.berlaku) < 0 ? 'Kedaluwarsa' : 'habis ' + daysTo(s.berlaku) + ' hari lagi'}</b>` : ''}</p>
    <div class="row" style="margin-top:.9rem;position:relative;z-index:1"><a class="btn sm white" href="api.php?r=sertifikat/cetak&id=${s.id}" target="_blank" rel="noopener">${ic('doc')}Cetak</a><button class="btn sm glass" data-x="copyLink" data-url="${s.url}">Salin tautan verifikasi</button><button class="btn sm glass" data-x="cekPublik" data-kode="${s.kode}">Cek keaslian</button></div></div>`).join('')
    || '<div class="card empty" style="grid-column:1/-1"><h3>Belum ada sertifikat</h3><p class="muted">Sertifikat terbit otomatis di sini setelah pleno menyatakan Anda kompeten.</p></div>'}</div>
  <div class="alert info">${ic('shield')}<span>Siapa pun bisa memeriksa keaslian sertifikat lewat tautan verifikasi atau nomor sertifikat di menu Verifikasi Sertifikat pada portal.</span></div>`;
}
function pAsesiKelas() {
  const D = S.d.kelas;
  if (!D) return loading();
  const mineIds = D.mine.map(k => k.listing_id);
  return `${phead('Pelatihan (LMS)', 'Kelas saya')}
  <div class="grid g3">${D.mine.map(k => `<div class="card stack" style="gap:.6rem"><div class="spread"><h3>${k.judul}</h3><span class="chip ${k.status === 'selesai' ? 'ok' : k.status === 'aktif' ? 'info' : 'warn'}">${k.status === 'menunggu_bayar' ? 'Menunggu bayar' : k.status === 'selesai' ? 'Selesai' : 'Aktif'}</span></div>
    <p class="muted" style="font-size:.84rem">${k.lsp_nama} · ${k.format}</p>
    <div style="height:10px;border-radius:99px;background:var(--surface-2);overflow:hidden"><div style="height:100%;width:${k.progres}%;background:var(--g-green)"></div></div><p class="muted" style="font-size:.8rem">${k.progres}% selesai</p>
    ${k.status === 'menunggu_bayar' ? '<button class="btn sm orange" data-go-app="bayar">Bayar untuk mulai</button>' : k.status === 'aktif' ? `<button class="btn sm green" data-x="kelasNext" data-id="${k.id}" data-p="${k.progres}">Tandai modul berikutnya selesai</button>` : '<span class="chip ok">Sertifikat pelatihan tersedia</span>'}</div>`).join('') || '<div class="card" style="grid-column:1/-1"><p class="muted">Anda belum mengikuti kelas.</p></div>'}</div>
  <h3>Kelas tersedia</h3>
  <div class="alert warn">${ic('shield')}<span>Pelatihan tidak wajib untuk ikut uji kompetensi. Instruktur kelas tidak boleh menjadi asesor peserta yang ia ajar.</span></div>
  <div class="grid g3">${D.cat.filter(c => !mineIds.includes(c.id)).map(c => `<div class="card stack" style="gap:.6rem"><h3>${c.judul}</h3><p class="muted" style="font-size:.84rem">${c.lsp_nama} · ${c.format} · ${c.kota}</p><p style="font-size:.86rem">${c.deskripsi}</p>
    <div class="spread" style="margin-top:auto"><span class="price">${c.harga ? fmtRp(c.harga) : 'Gratis'}</span>${btnAct('asesi/kelas/daftar', {listing_id: c.id}, 'Ikuti kelas', 'sm purple', 'data-ok="Terdaftar di kelas."')}</div></div>`).join('') || '<p class="muted">Tidak ada kelas lain.</p>'}</div>`;
}
function pProfil() {
  const p = S.profile, D = S.d.profil;
  if (!p || !D) return loading();
  const rows = [['Nama lengkap', p.nama], ['NIK', p.nik ? `<span class="mono">${p.nik}</span>` : '—'], ['Tanggal lahir', p.tanggal_lahir ? fmtTgl(p.tanggal_lahir) : '—'], ['Jenis kelamin', p.jenis_kelamin === 'L' ? 'Laki-laki' : p.jenis_kelamin === 'P' ? 'Perempuan' : '—'], ['Email', p.email + (p.email_verified ? ' <span class="chip ok">Terverifikasi</span>' : ' <span class="chip warn">Belum diverifikasi</span>')], ['No. HP', p.no_hp || '—'], ['Terdaftar di LSP', p.lsp_count + ' LSP']];
  const have = Object.fromEntries((D.items || []).map(x => [x.jenis, x]));
  return `${phead('Profil global', 'Profil & Dokumen')}
  <div class="alert info">${ic('shield')}<span>Profil dan dokumen ini dipakai ulang di semua LSP. NIK disimpan terenkripsi. LSP hanya bisa membuka dokumen Anda bila Anda mendaftar skema di LSP tersebut.</span></div>
  ${S.edit === 'profil' ? `<div class="card" id="editor" style="border:2px solid var(--brand-b)"><h3 style="margin-bottom:.4rem">Ubah profil</h3><p class="muted" style="font-size:.85rem;margin-bottom:.8rem">Nama, tanggal lahir, dan jenis kelamin hanya bisa diubah sebelum Anda punya pendaftaran yang berjalan atau sertifikat. NIK tidak bisa diubah sendiri.</p>${form('profile', `<div class="grid g2">
    ${fld('Nama lengkap (sesuai KTP)', inp('nama', p.nama, 'maxlength="120" autocomplete="name"'), true)}${p.nik ? '' : fld('NIK (16 digit, sesuai KTP)', inp('nik', '', 'inputmode="numeric" maxlength="16" class="mono"'), true)}${fld('Tanggal lahir', inp('tanggal_lahir', p.tanggal_lahir || '', 'type="date"'))}${fld('Jenis kelamin', sel('jenis_kelamin', [['L', 'Laki-laki'], ['P', 'Perempuan']], p.jenis_kelamin))}
    ${fld('Nomor HP', inp('no_hp', p.no_hp || '', 'inputmode="tel" maxlength="20" autocomplete="tel"'))}</div>
    <label class="row" style="gap:.5rem;font-size:.88rem;flex-wrap:nowrap"><input type="checkbox" name="consent_marketing" ${p.consent_marketing ? 'checked' : ''} style="width:auto">Saya bersedia menerima info skema dan pelatihan dari LSP.</label>${p.nik ? '' : `<label class="row" style="gap:.5rem;font-size:.88rem;flex-wrap:nowrap"><input type="checkbox" name="consent_privacy" style="width:auto">Saya menyetujui kebijakan privasi: NIK disimpan terenkripsi dan hanya dibagikan ke LSP tempat saya mendaftar.</label>`}`, {ok: 'Profil diperbarui.', after: 'profil', cancel: true})}</div>` : ''}
  <div class="card">${infoGrid(rows)}${S.edit === 'profil' ? '' : `<div class="row" style="justify-content:flex-end;margin-top:.8rem"><button class="btn sm ghost" data-edit="profil">${ic('users')}Ubah profil</button></div>`}</div>
  <div class="card stack"><h3>Dokumen pribadi</h3>${Object.entries(D.jenis).map(([k, l]) => docRow(k, l, have[k], D.wajib.includes(k))).join('')}<p class="muted" style="font-size:.82rem">PDF, JPG, atau PNG, maksimal 2 MB per berkas.</p></div>`;
}

/* ===================== ASESOR ===================== */
function dAsesor() {
  const D = S.d.dashboard;
  if (!D || !D.j) return loading();
  const up = D.j.filter(j => j.tanggal >= todayStr() && lspOk(j));
  const lspN = new Set(D.j.map(j => j.lsp_nama)).size;
  return `${phead(new Date().toLocaleDateString('id-ID', {weekday:'long', day:'numeric', month:'long', year:'numeric'}), 'Selamat datang, ' + ME.user.nama.split(',')[0])}
  <div class="grid g4">${kpi('cal', 'blue', up.filter(j => daysTo(j.tanggal) <= 30).length, 'Jadwal 30 hari ke depan')}${kpi('doc', 'orange', D.p.length, 'Pra-asesmen menunggu', D.p.length ? '<button class="btn sm orange" data-go-app="pra" style="margin-top:.3rem">Tinjau</button>' : '')}${kpi('check', 'teal', D.a.length, 'Siap diuji')}${kpi('shield', 'purple', D.pl.length, 'Berkas pleno', `<span class="muted">dari ${lspN} LSP</span>`)}</div>
  <div class="layout-2"><div class="card"><div class="spread" style="margin-bottom:.4rem"><h3>Jadwal mendatang</h3><button class="btn sm" data-go-app="kalender">Kalender gabungan</button></div>${calList(up.slice(0, 6))}</div>
    <div class="stack"><div class="card"><h3>Tinjau pra-asesmen</h3><div class="stack" style="gap:.6rem;margin-top:.8rem">${D.p.slice(0, 5).map(r => `<div class="spread" style="border-bottom:1px solid var(--line);padding-bottom:.6rem"><div><b style="font-size:.9rem">${r.asesi_nama}</b><p class="muted" style="font-size:.78rem">${r.skema_nama} · ${fmtHari(r.tanggal)}</p></div><button class="btn sm" data-go-app="pra">Tinjau</button></div>`).join('') || '<p class="muted">Tidak ada yang menunggu.</p>'}</div></div>
      <div class="alert info">${ic('shield')}<span>Anda hanya melihat asesi pada jadwal yang ditugaskan kepada Anda. Pleno untuk asesmen yang Anda uji sendiri tidak bisa Anda putuskan.</span></div></div></div>`;
}
const LSPC = ['#1d4ed8', '#0d9488', '#059669', '#7c3aed', '#db2777', '#f97316'];
function calList(list) {
  const names = [...new Set(list.map(j => j.lsp_nama))];
  const by = {};
  list.forEach(j => (by[j.tanggal] = by[j.tanggal] || []).push(j));
  return Object.entries(by).map(([d, js]) => `<div class="day"><b style="font-size:.85rem">${fmtHari(d).replace(/ \d{4}$/, '')}</b><div>${js.map(j => `<div class="ev" style="--c:${LSPC[names.indexOf(j.lsp_nama) % LSPC.length]}"><div class="spread"><b style="font-size:.9rem">Uji ${j.skema_nama} · ${j.peserta} asesi</b><span class="mono muted">${j.jam}</span></div><div class="spread"><span class="muted" style="font-size:.8rem">${j.lsp_nama} · ${j.tuk_nama || '—'} · ${j.metode}</span><button class="btn sm ghost" data-x="chatOpen" data-id="${j.id}">${ic('chat')}Chat</button></div></div>`).join('')}</div></div>`).join('') || '<p class="muted">Tidak ada jadwal.</p>';
}
function pKalender() {
  const D = S.d.kalender;
  if (!D) return loading();
  const items = D.items.filter(lspOk);
  return `${phead('Semua LSP tempat Anda bertugas', 'Kalender gabungan')}${chatBox()}
  <div class="card"><h3 style="margin-bottom:.5rem">Mendatang</h3>${calList(items.filter(j => j.tanggal >= todayStr()))}</div>
  <div class="card"><h3 style="margin-bottom:.5rem">Sudah lewat</h3>${calList(items.filter(j => j.tanggal < todayStr()).reverse())}</div>`;
}
function asesiPanel(p) {
  return `${infoGrid([['Asesi', p.asesi_nama], ['NIK', p.asesi_nik ? `<span class="mono">${p.asesi_nik}</span>` : '—'], ['Skema', p.skema_nama], ['Jadwal', fmtHari(p.tanggal) + ' · ' + (p.tuk_nama || '—')], ['LSP', p.lsp_nama], ['Tujuan', p.tujuan || '—']])}
    <div><p class="eyebrow" style="margin-bottom:.4rem">Dokumen</p><div class="row">${(p.dokumen || []).map(docLink).join('') || '<span class="muted">Tidak ada dokumen.</span>'}</div></div>`;
}
function listDetail(items, renderRow, renderDetail, empty) {
  const id = S.sel[S.appPage], cur = items.find(x => x.id === id) || items[0];
  return `<div class="ld"><div class="card stack" style="gap:.5rem">${items.map(x => `<button class="notif-item ${cur && x.id === cur.id ? 'unread' : ''}" data-sel="${x.id}"><span class="dot"></span><span style="flex:1;min-width:0">${renderRow(x)}</span></button>`).join('') || `<p class="muted">${empty}</p>`}</div>
    <div class="card stack" id="detail">${cur ? renderDetail(cur) : `<p class="muted">${empty}</p>`}</div></div>`;
}
function pPra() {
  const D = S.d.pra;
  if (!D) return loading();
  return `${phead('Hanya peserta yang ditugaskan kepada Anda', 'Tinjau pra-asesmen')}
  ${listDetail(D.items.filter(lspOk), x => `<b>${x.asesi_nama}</b><span class="muted" style="display:block;font-size:.8rem">${x.skema_nama} · ${fmtHari(x.tanggal)}</span>`, p => `<h3>APL.01 & APL.02</h3>${asesiPanel(p)}
    ${unitTable(p.units, p.apl02, {label: 'Penilaian diri asesi'})}
    ${form('asesor/pra/putus', `${hid('id', p.id)}<div class="row">${[['lanjut', 'Lanjut ke uji'], ['tidak', 'Belum dapat dilanjutkan']].map(([v, l]) => `<label class="row" style="gap:.35rem;font-weight:700"><input type="radio" name="rekom" value="${v}" style="width:auto" ${v === 'lanjut' ? 'checked' : ''}>${l}</label>`).join('')}</div>${fld('Catatan untuk asesi (wajib bila belum dapat dilanjutkan)', txa('catatan', '', 'rows="2" maxlength="500"'))}`, {ok: 'Rekomendasi pra-asesmen disimpan.', submit: 'Simpan rekomendasi'})}`, 'Tidak ada pra-asesmen yang menunggu.')}`;
}
function pAsesmen() {
  const D = S.d.asesmen;
  if (!D) return loading();
  return `${phead('Rekaman asesmen (FR.IA / FR.AK)', 'Asesmen')}
  ${listDetail(D.items.filter(lspOk), x => `<b>${x.asesi_nama}</b><span class="muted" style="display:block;font-size:.8rem">${x.skema_nama} · ${fmtHari(x.tanggal)}</span>`, p => `<h3>Hasil asesmen</h3>${asesiPanel(p)}
    ${p.tanggal > todayStr() ? `<div class="alert warn">${ic('cal')}<span>Uji dijadwalkan ${fmtHari(p.tanggal)}. Hasil baru bisa disimpan pada atau setelah tanggal uji.</span></div>` : ''}
    ${form('asesor/asesmen/simpan', `${hid('id', p.id)}<p class="muted" style="font-size:.85rem">Penilaian diri asesi: ${Object.values(p.apl02).filter(v => v === 'K').length}/${p.units.length} unit K.</p>${unitTable(p.units, null, {input: 'hasil', label: 'Keputusan asesor'})}${fld('Catatan asesor / umpan balik', txa('catatan', '', 'rows="3" maxlength="1000"'))}`, {ok: 'Hasil asesmen disimpan dan diteruskan ke pleno.', submit: 'Simpan & teruskan ke pleno'})}`, 'Tidak ada asesi yang siap diuji.')}`;
}
function plenoView(D) {
  return `${listDetail(D.items, x => `<b>${x.asesi_nama}</b><span class="muted" style="display:block;font-size:.8rem">${x.skema_nama} · rekomendasi ${x.rekomendasi} · ${x.lsp_nama}</span>`, p => `<h3>Berkas pleno</h3>${asesiPanel(p)}
    ${infoGrid([['Asesor penguji', p.penguji_nama || '—'], ['Rekomendasi asesor', `<span class="chip ${p.rekomendasi === 'K' ? 'ok' : 'bad'}">${p.rekomendasi === 'K' ? 'Kompeten' : 'Belum kompeten'}</span>`], ['Catatan asesor', p.catatan_asesor || '—']])}
    ${unitTable(p.units, p.hasil, {label: 'Hasil asesmen'})}
    ${form('pleno/putus', `${hid('id', p.id)}<div class="row">${[['K', 'Kompeten (terbitkan sertifikat)'], ['BK', 'Belum kompeten']].map(([v, l]) => `<label class="row" style="gap:.35rem;font-weight:700"><input type="radio" name="keputusan" value="${v}" style="width:auto" ${v === p.rekomendasi ? 'checked' : ''}>${l}</label>`).join('')}</div>${fld('Catatan pleno', txa('catatan', '', 'rows="2" maxlength="500"'))}`, {ok: 'Keputusan pleno disimpan.', submit: 'Putuskan'})}`, 'Tidak ada berkas pleno yang menunggu.')}
  ${D.diuji_sendiri && D.diuji_sendiri.length ? `<div class="card"><h3>Diuji oleh Anda (tidak bisa Anda plenokan)</h3>${table(['Asesi', 'Skema', 'LSP', 'Rekomendasi'], D.diuji_sendiri.map(x => tr([x.asesi_nama, x.skema_nama, x.lsp_nama, x.rekomendasi])))}<p class="muted" style="font-size:.8rem;margin-top:.5rem">Aturan independensi: asesor penguji tidak boleh menjadi pemutus pleno untuk asesi yang sama.</p></div>` : ''}`;
}
function pPleno() {
  const D = S.d.pleno;
  if (!D) return loading();
  return `${phead('Keputusan sertifikasi', 'Pleno')}${plenoView({...D, items: D.items.filter(lspOk)})}`;
}
function pRiwayat() {
  const D = S.d.riwayat;
  if (!D) return loading();
  const items = D.items.filter(lspOk);
  return `${phead('Rekap asesmen dari semua LSP', 'Riwayat & logbook', `<button class="btn teal" data-x="logbook">${ic('doc')}Unduh logbook (CSV)</button>`)}
  <div class="grid g4">${kpi('check', 'blue', items.length, 'Asesmen tercatat')}${kpi('cert', 'green', items.filter(x => x.keputusan === 'K').length, 'Kompeten')}${kpi('shield', 'orange', items.filter(x => x.keputusan === 'BK').length, 'Belum kompeten')}${kpi('cal', 'purple', items.filter(x => !x.keputusan).length, 'Menunggu pleno')}</div>
  <div class="card">${table(['Tanggal uji', 'LSP', 'Skema', 'Asesi', 'Rekomendasi', 'Pleno'], items.map(x => tr([fmtTgl(x.diuji_at), x.lsp_nama, x.skema_nama, x.asesi_nama, `<span class="chip ${x.rekomendasi === 'K' ? 'ok' : 'bad'}">${x.rekomendasi}</span>`, x.keputusan ? `<span class="chip ${x.keputusan === 'K' ? 'ok' : 'bad'}">${x.keputusan}</span>` : '<span class="chip info">Menunggu</span>'])))}</div>`;
}
function pHonor() {
  const D = S.d.honor;
  if (!D) return loading();
  const items = D.items.filter(lspOk);
  return `${phead('Terpisah per LSP', 'Honor asesor')}
  <div class="grid g3">${kpi('money', 'green', fmtRp(D.total), 'Total honor tercatat')}${kpi('check', 'blue', items.reduce((a, x) => a + x.asesi, 0), 'Asesi diuji')}${kpi('build', 'purple', new Set(items.map(x => x.lsp_nama)).size, 'LSP')}</div>
  <div class="card">${table(['Bulan', 'LSP', 'Asesi', 'Tarif', 'Total', 'Status'], items.map(x => tr([x.bulan, x.lsp_nama, x.asesi, fmtRp(x.tarif), `<b>${fmtRp(x.total)}</b>`, `<span class="chip ${x.status === 'dibayar' ? 'ok' : 'warn'}">${x.status === 'dibayar' ? 'Dibayar' : 'Diproses'}</span>`])), 'Belum ada honor.')}
  <p class="muted" style="font-size:.8rem;margin-top:.6rem">Tarif per asesi mengikuti pengaturan masing-masing LSP. Bukti potong PPh 21 diterbitkan bagian keuangan LSP.</p></div>`;
}

/* ===================== ADMIN LSP ===================== */
function dAdmin() {
  const D = S.d[S.appPage === 'dashLsp' ? 'dashLsp' : 'dashboard'];
  if (!D || !D.kpi) return loading();
  const k = D.kpi, maxF = Math.max(1, ...D.funnel.map(f => f[1]));
  const rev = S.listings.filter(l => l.status === 'revisi').length;
  return `${phead(ctxName() + ' · ' + ME.active.role_nama, 'Dashboard LSP', `${can('schedule.manage') ? `<button class="btn teal" data-go-app="jadwalA">${ic('cal')}Kelola jadwal</button>` : ''}${can('report.bnsp') ? `<button class="btn pink" data-go-app="laporan">${ic('chart')}Laporan BNSP</button>` : ''}`)}
  <div class="grid g4">${kpi('users', 'blue', k.pendaftar_bulan, 'Pendaftar bulan ini')}${kpi('doc', 'orange', k.perlu_verifikasi, 'Perlu verifikasi', k.perlu_verifikasi && can('registration.verify') ? '<button class="btn sm orange" data-go-app="daftar" style="margin-top:.3rem">Verifikasi</button>' : '')}${kpi('check', 'teal', k.berjalan, 'Asesmen berjalan')}${kpi('shield', 'purple', k.menunggu_pleno, 'Menunggu pleno')}</div>
  <div class="layout-2">
    <div class="card"><div class="spread" style="margin-bottom:.6rem"><h3>Pendaftaran perlu verifikasi</h3><span class="chip warn">${k.perlu_verifikasi} menunggu</span></div>
      ${table(['Pemohon', 'Skema', 'Jadwal', ''], D.verifikasi.map(p => tr([`<b>${p.asesi_nama}</b>`, p.skema_nama, fmtHari(p.tanggal), can('registration.verify') ? `<button class="btn sm" data-go-app="daftar">Proses</button>` : ''])), 'Tidak ada pendaftaran yang menunggu.')}</div>
    <div class="stack">
      <div class="card"><h3>Funnel bulan ini</h3><div class="funnel" style="margin-top:.8rem">${D.funnel.map(([l, v], i) => `<div class="row" style="gap:.6rem;flex-wrap:nowrap"><span class="muted" style="width:70px;font-size:.8rem">${l}</span><div class="bar" style="width:${Math.max(8, 100 * v / maxF)}%;background:${GRAD[['blue', 'orange', 'teal', 'green'][i]]}">${v}</div></div>`).join('')}</div></div>
      <div class="stack" style="gap:.6rem">
        ${k.jadwal_tanpa_asesor ? `<div class="alert bad">${ic('users')}<span><b>${k.jadwal_tanpa_asesor} jadwal</b> belum punya asesor.${can('schedule.manage') ? ' <button class="btn sm red" data-go-app="jadwalA" style="margin-top:.4rem">Tugaskan</button>' : ''}</span></div>` : ''}
        ${k.sertifikat_habis ? `<div class="alert warn">${ic('cert')}<span><b>${k.sertifikat_habis} sertifikat</b> habis ≤ 90 hari: peluang sertifikasi ulang.</span></div>` : ''}
        ${rev ? `<div class="alert bad">${ic('wallet')}<span><b>Etalase:</b> ${rev} listing perlu revisi dari Admin Platform. <button class="btn sm red" data-go-app="etalase" style="margin-top:.4rem">Buka etalase</button></span></div>` : ''}
        <div class="alert info">${ic('money')}<span>Pendapatan bulan ini: <b>${fmtRp(k.pendapatan_bulan)}</b></span></div>
      </div>
    </div>
  </div>
  <div class="card"><div class="spread" style="margin-bottom:.6rem"><h3>Jadwal 30 hari ke depan</h3>${can('schedule.manage') ? '<button class="btn sm purple" data-go-app="jadwalA">Kelola jadwal</button>' : ''}</div>
    ${table(['Tanggal', 'Skema', 'TUK', 'Asesor', 'Peserta', 'Status'], D.jadwal.map(j => tr([`<b>${fmtHari(j.tanggal)}</b>`, j.skema_nama, j.tuk_nama || '—', j.asesor_nama || '<span class="chip bad">Belum ada</span>', `${j.peserta}/${j.kuota}`, `<span class="chip ${j.status === 'dibuka' ? 'ok' : 'plain'}">${j.status}</span>`])), 'Tidak ada jadwal.')}</div>`;
}
function pPendaftaran() {
  const D = S.d.daftar;
  if (!D) return loading();
  const t = curTab('daftar', 'diajukan');
  const cnt = st => D.items.filter(p => p.status === st).length;
  const items = D.items.filter(p => t === 'semua' || p.status === t);
  return `${phead(ctxName(), 'Pendaftaran asesi')}
  ${tabs('daftar', [['diajukan', 'Perlu verifikasi', cnt('diajukan')], ['perbaikan', 'Menunggu perbaikan', cnt('perbaikan')], ['menunggu_bayar', 'Menunggu bayar', cnt('menunggu_bayar')], ['semua', 'Semua', D.items.length]])}
  ${listDetail(items, x => `<b>${x.asesi_nama}</b><span class="muted" style="display:block;font-size:.8rem">${x.skema_nama} · ${fmtHari(x.tanggal)}${isPlatform() ? ' · ' + x.lsp_nama : ''}</span>`, p => `<div class="spread"><h3>Permohonan #${p.id}</h3>${stChip(p)}</div>${asesiPanel(p)}
    ${infoGrid([['Email', p.asesi_email], ['No. HP', p.asesi_hp || '—'], ['Tanggal lahir', p.asesi_tgl_lahir ? fmtTgl(p.asesi_tgl_lahir) : '—'], ['Biaya uji', fmtRp(p.harga)]])}
    <details><summary style="cursor:pointer;font-weight:700">Asesmen mandiri (APL.02): ${Object.values(p.apl02).filter(v => v === 'K').length}/${p.units.length} unit K</summary>${unitTable(p.units, p.apl02, {label: 'Penilaian diri'})}</details>
    ${p.catatan_admin ? `<div class="alert info">${ic('doc')}<span>Catatan sebelumnya: ${p.catatan_admin}</span></div>` : ''}
    ${p.status === 'diajukan' ? `<label class="f">Catatan untuk asesi (wajib untuk perbaikan/tolak)<textarea id="pd-cat" name="catatan" rows="2" maxlength="500"></textarea></label>
      <div class="row" style="justify-content:flex-end">${btnAct('lsp/pendaftaran/putus', {id: p.id, aksi: 'tolak'}, 'Tolak', 'red', 'data-with="#pd-cat" data-confirm="Tolak pendaftaran ini?" data-ok="Pendaftaran ditolak."')}${btnAct('lsp/pendaftaran/putus', {id: p.id, aksi: 'perbaikan'}, 'Minta perbaikan', 'orange', 'data-with="#pd-cat" data-ok="Permintaan perbaikan dikirim."')}${btnAct('lsp/pendaftaran/putus', {id: p.id, aksi: 'terima'}, ic('check') + 'Berkas lengkap, terbitkan tagihan', 'green', 'data-with="#pd-cat" data-ok="Berkas diterima. Tagihan dikirim ke asesi."')}</div>` : ''}`, 'Tidak ada pendaftaran di tab ini.')}`;
}
function pJadwalA() {
  const D = S.d.jadwalA;
  if (!D) return loading();
  const o = D.o, e = S.edit && S.edit !== 'new' ? D.j.find(j => j.id === Number(S.edit)) : null;
  const lspId = e ? e.lsp_id : (S.lspFilter || ME.active.lsp_id);
  const skOpts = o.skema.filter(s => !lspId || s.lsp_id === lspId).map(s => [s.id, s.kode + ' · ' + s.nama]);
  const tkOpts = [['0', '— Tanpa TUK —']].concat(o.tuk.filter(t => !lspId || t.lsp_id === lspId).map(t => [t.id, t.nama]));
  const editor = S.edit ? (needLspPick() && !e ? pickLspNote('membuat jadwal') : `<div class="card" id="editor" style="border:2px solid var(--brand-b)"><h3 style="margin-bottom:.8rem">${e ? 'Ubah jadwal' : 'Jadwal baru'}</h3>${form('lsp/jadwal', `${e ? hid('id', e.id) : ''}<div class="grid g2">
    ${fld('Skema', sel('skema_id', skOpts, e && e.skema_id, 'data-num'), true)}${fld('TUK', sel('tuk_id', tkOpts, e ? (e.tuk_id || 0) : 0, 'data-num'))}${fld('Metode', sel('metode', ['Tatap muka', 'SJJ'], e && e.metode))}
    ${fld('Tanggal', inp('tanggal', e ? e.tanggal : '', `type="date" ${e ? '' : 'min="' + todayStr() + '"'}`))}${fld('Jam', inp('jam', e ? e.jam : '08.00–16.00', 'maxlength="20"'))}
    ${fld('Kuota peserta', inp('kuota', e ? e.kuota : 20, 'type="number" min="1" max="500"'))}${fld('Status', sel('status', [['dibuka', 'Dibuka'], ['ditutup', 'Ditutup'], ['selesai', 'Selesai'], ['batal', 'Batal']], e ? e.status : 'dibuka'))}
    ${fld('Catatan (mis. tautan SJJ)', inp('catatan', e ? (e.catatan || '') : '', 'maxlength="255"'), true)}</div>`, {ok: 'Jadwal disimpan.', cancel: true})}</div>`) : '';
  const asOpts = j => [['0', '— Pilih asesor —']].concat(o.asesor.filter(a => a.lsp_id === j.lsp_id).map(a => [a.id, a.nama]));
  const t = curTab('jadwalA', 'akan');
  const items = D.j.filter(j => t === 'semua' || (t === 'akan' ? j.tanggal >= todayStr() : j.tanggal < todayStr()));
  if (t === 'akan') items.sort((a, b) => a.tanggal < b.tanggal ? -1 : a.tanggal > b.tanggal ? 1 : 0);
  return `${phead(ctxName(), 'Jadwal & penugasan asesor', `<button class="btn" data-edit="new">${ic('cal')}Jadwal baru</button>`)}${editor}
  ${tabs('jadwalA', [['akan', 'Akan datang'], ['lalu', 'Sudah lewat'], ['semua', 'Semua']])}
  <div class="card">${table(['Tanggal', 'Skema', 'TUK / metode', 'Peserta', 'Asesor', 'Status', ''], items.map(j => tr([`<b>${fmtHari(j.tanggal)}</b><div class="muted" style="font-size:.75rem">${j.jam}</div>`, j.skema_nama + (isPlatform() ? `<div class="muted" style="font-size:.75rem">${j.lsp_nama}</div>` : ''), `${j.tuk_nama || '—'}<div class="muted" style="font-size:.75rem">${j.metode}</div>`,
    `<span class="chip ${j.sisa ? 'ok' : 'bad'}">${j.peserta}/${j.kuota}</span>`, sel('asesor', asOpts(j), j.asesor_id || 0, `data-assign="${j.id}" aria-label="Asesor" style="min-width:170px"`), `<span class="chip ${j.status === 'dibuka' ? 'ok' : j.status === 'batal' ? 'bad' : 'plain'}">${j.status}</span>`,
    `<div class="row" style="gap:.3rem;flex-wrap:nowrap"><button class="btn sm ghost" data-edit="${j.id}">Ubah</button>${j.peserta ? '' : btnAct('lsp/jadwal/hapus', {id: j.id}, 'Hapus', 'sm ghost', 'data-confirm="Hapus jadwal ini?" data-ok="Jadwal dihapus."')}</div>`])), 'Belum ada jadwal.')}
  <p class="muted" style="font-size:.8rem;margin-top:.6rem">Sistem menolak penugasan bila asesor sudah bertugas di jadwal lain pada tanggal yang sama. Asesor menerima notifikasi penugasan.</p></div>`;
}
function pAsesmenA() {
  const D = S.d.asesmenA;
  if (!D) return loading();
  const t = curTab('asesmenA', 'semua');
  const items = D.items.filter(p => t === 'semua' || p.status === t);
  const n = st => D.items.filter(p => p.status === st).length;
  return `${phead(ctxName(), 'Pemantauan asesmen')}
  ${tabs('asesmenA', [['semua', 'Semua', D.items.length], ['pra_asesmen', 'Pra-asesmen', n('pra_asesmen')], ['siap_uji', 'Siap uji', n('siap_uji')], ['menunggu_pleno', 'Menunggu pleno', n('menunggu_pleno')], ['tidak_lanjut', 'Tidak lanjut', n('tidak_lanjut')]])}
  <div class="card">${table(['Asesi', 'Skema', 'Jadwal', 'TUK', 'Asesor', 'Status'], items.map(p => tr([`<b>${p.asesi_nama}</b>`, p.skema_nama, fmtHari(p.tanggal), p.tuk_nama || '—', p.asesor_nama || '<span class="chip bad">Belum ada</span>', stChip(p) + (p.rekomendasi ? ` <span class="chip ${p.rekomendasi === 'K' ? 'ok' : 'bad'}">Rek. ${p.rekomendasi}</span>` : '')])), 'Tidak ada asesmen.')}</div>`;
}
function pPlenoA() {
  const D = S.d.plenoA;
  if (!D) return loading();
  const t = curTab('plenoA', 'pleno');
  return `${phead(ctxName(), 'Pleno & sertifikat')}${tabs('plenoA', [['pleno', 'Menunggu pleno', D.p.items.length], ['hasil', 'Keputusan', D.h.items.length], ['sert', 'Sertifikat terbit', D.h.sertifikat.length]])}
  ${t === 'pleno' ? plenoView(D.p) : t === 'hasil' ? `<div class="card">${table(['Asesi', 'Skema', 'Asesor', 'Pleno oleh', 'Keputusan', 'Tanggal'], D.h.items.map(p => tr([p.asesi_nama, p.skema_nama, p.penguji_nama || '—', p.pleno_nama || '—', stChip(p), fmtTgl(p.diputuskan_at)])), 'Belum ada keputusan.')}</div>`
    : `<div class="card">${table(['Nomor', 'Nama', 'Skema', 'Terbit', 'Berlaku', ''], D.h.sertifikat.map(s => tr([`<span class="mono">${s.nomor}</span>`, s.nama, s.skema_nama, fmtTgl(s.terbit), fmtTgl(s.berlaku), `<a class="btn sm ghost" href="api.php?r=sertifikat/cetak&id=${s.id}" target="_blank" rel="noopener">Cetak</a>`])), 'Belum ada sertifikat.')}</div>`}`;
}
function pMaster() {
  const D = S.d.master;
  if (!D) return loading();
  const t = curTab('master', 'skema');
  let body = '';
  if (t === 'skema') {
    const e = S.edit && S.edit !== 'new' ? D.s.find(x => x.id === Number(S.edit)) : null;
    const editor = S.edit ? (needLspPick() && !e ? pickLspNote('menambah skema') : `<div class="card" id="editor" style="border:2px solid var(--brand-b)"><h3 style="margin-bottom:.8rem">${e ? 'Ubah skema' : 'Skema baru'}</h3>${form('lsp/skema', `${e ? hid('id', e.id) : ''}<div class="grid g2">
      ${fld('Kode', inp('kode', e ? e.kode : '', 'maxlength="40" required'))}${fld('Nama skema', inp('nama', e ? e.nama : '', 'maxlength="190" required'))}
      ${fld('Bidang', sel('bidang', ['TIK', 'Pariwisata', 'Konstruksi', 'Bisnis', 'Kesehatan'], e && e.bidang))}${fld('Jenjang KKNI', inp('kkni', e ? e.kkni : 'Level 3', 'maxlength="20"'))}
      ${fld('Biaya uji (Rp)', inp('harga', e ? e.harga : 750000, 'type="number" min="0" step="1000"'))}${fld('Status', sel('status', [['aktif', 'Aktif'], ['nonaktif', 'Nonaktif']], e && e.status))}
      ${fld('Deskripsi', txa('deskripsi', e ? (e.deskripsi || '') : '', 'rows="2" maxlength="2000"'), true)}${fld('Persyaratan (satu per baris)', txa('persyaratan', e ? (e.persyaratan || '') : '', 'rows="3" maxlength="2000"'), true)}
      ${fld('Unit kompetensi (satu per baris: KODE | Judul unit)', txa('units_text', e ? e.units.map(u => u.kode + ' | ' + u.judul).join('\n') : '', 'rows="6" class="mono" style="font-size:.82rem"'), true)}</div>
      <p class="muted" style="font-size:.8rem">Cari unit di tab Pustaka SKKNI lalu klik "Pakai" untuk menambahkannya. Unit skema yang sudah punya permohonan tidak diubah agar rekaman asesmen tetap utuh.</p>`, {ok: 'Skema disimpan.', cancel: true, after: 'skemaSimpan'})}</div>`) : '';
    body = `<div class="row" style="justify-content:flex-end"><button class="btn" data-edit="new">${ic('cert')}Skema baru</button></div>${editor}<div class="card">${table(['Kode', 'Skema', 'KKNI', 'Unit', 'Biaya', 'Status', 'Portal', ''], D.s.map(s => tr([`<span class="mono">${s.kode}</span>`, s.nama + (isPlatform() ? `<div class="muted" style="font-size:.75rem">${s.lsp_nama}</div>` : ''), s.kkni, s.units.length, fmtRp(s.harga), `<span class="chip ${s.status === 'aktif' ? 'ok' : 'plain'}">${s.status}</span>`, s.tayang ? '<span class="chip ok">Tayang</span>' : `<button class="btn sm ghost" data-go-app="etalase">Ajukan ke etalase</button>`, `<div class="row" style="gap:.3rem;flex-wrap:nowrap"><button class="btn sm ghost" data-edit="${s.id}">Ubah</button>${btnAct('lsp/skema/hapus', {id: s.id}, 'Hapus', 'sm ghost', 'data-confirm="Hapus skema ini?" data-ok="Skema dihapus."')}</div>`])))}</div>`;
  } else if (t === 'tuk') {
    const e = S.edit && S.edit !== 'new' ? D.t.find(x => x.id === Number(S.edit)) : null;
    const editor = S.edit ? (needLspPick() && !e ? pickLspNote('menambah TUK') : `<div class="card" id="editor" style="border:2px solid var(--brand-b)"><h3 style="margin-bottom:.8rem">${e ? 'Ubah TUK' : 'TUK baru'}</h3>${form('lsp/tuk', `${e ? hid('id', e.id) : ''}<div class="grid g2">
      ${fld('Nama TUK', inp('nama', e ? e.nama : '', 'maxlength="190"'), true)}${fld('Jenis', sel('jenis', ['Sewaktu', 'Tempat Kerja', 'Mandiri'], e && e.jenis))}${fld('Kapasitas', inp('kapasitas', e ? e.kapasitas : 20, 'type="number" min="1"'))}
      ${fld('Alamat', inp('alamat', e ? (e.alamat || '') : '', 'maxlength="255"'), true)}${fld('Verifikasi berlaku s.d.', inp('verif_sampai', e ? (e.verif_sampai || '') : '', 'type="date"'))}${fld('Status', sel('status', [['aktif', 'Aktif'], ['nonaktif', 'Nonaktif']], e && e.status))}</div>`, {ok: 'TUK disimpan.', cancel: true})}</div>`) : '';
    body = `<div class="row" style="justify-content:flex-end"><button class="btn" data-edit="new">${ic('build')}TUK baru</button></div>${editor}<div class="card">${table(['TUK', 'Jenis', 'Kapasitas', 'Verifikasi', 'Jadwal mendatang', 'Status', ''], D.t.map(x => tr([`<b>${x.nama}</b><div class="muted" style="font-size:.75rem">${x.alamat || ''}${isPlatform() ? ' · ' + x.lsp_nama : ''}</div>`, x.jenis, x.kapasitas, x.verif_sampai ? `<span class="chip ${daysTo(x.verif_sampai) < 60 ? 'warn' : 'ok'}">s.d. ${fmtTgl(x.verif_sampai)}</span>` : '—', x.jadwal_mendatang, `<span class="chip ${x.status === 'aktif' ? 'ok' : 'plain'}">${x.status}</span>`, `<div class="row" style="gap:.3rem;flex-wrap:nowrap"><button class="btn sm ghost" data-edit="${x.id}">Ubah</button>${btnAct('lsp/tuk/hapus', {id: x.id}, 'Hapus', 'sm ghost', 'data-confirm="Hapus TUK ini?" data-ok="TUK dihapus."')}</div>`])))}</div>`;
  } else if (t === 'asesor') {
    const editor = S.edit ? (needLspPick() ? pickLspNote('menambah asesor') : `<div class="card" id="editor" style="border:2px solid var(--brand-b)"><h3 style="margin-bottom:.4rem">Tambah asesor</h3><p class="muted" style="font-size:.85rem;margin-bottom:.8rem">Bila email sudah terdaftar sebagai asesor di LSP lain, cukup isi email (asesor boleh aktif di banyak LSP). Bila belum, isi nama dan password awal.</p>${form('lsp/asesor', `<div class="grid g2">${fld('Email', inp('email', '', 'type="email" maxlength="190"'), true)}${fld('Nama lengkap (akun baru)', inp('nama', '', 'maxlength="120"'))}${fld('Password awal (akun baru)', inp('password', '', 'type="password" autocomplete="new-password"'))}</div>`, {ok: 'Asesor ditambahkan.', cancel: true})}</div>`) : '';
    body = `<div class="row" style="justify-content:flex-end"><button class="btn" data-edit="new">${ic('users')}Tambah asesor</button></div>${editor}<div class="card">${table(['Asesor', 'Email', 'Jadwal mendatang', 'Sudah menguji', 'Status', ''], D.a.map(a => tr([`<b>${a.nama}</b>${isPlatform() ? `<div class="muted" style="font-size:.75rem">${a.lsp_nama}</div>` : ''}`, a.email, a.jadwal_mendatang, a.diuji, `<span class="chip ${a.status === 'aktif' ? 'ok' : 'bad'}">${a.status}</span>`,
      can('user.manage') ? btnAct('users/status', {membership_id: a.membership_id, status: a.status === 'aktif' ? 'nonaktif' : 'aktif'}, a.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan', 'sm ghost', `data-confirm="${a.status === 'aktif' ? 'Nonaktifkan asesor ini di LSP ini? Jadwal yang sudah ditugaskan tetap perlu dialihkan.' : 'Aktifkan kembali asesor ini?'}" data-ok="Status asesor diperbarui."`) : ''])))}<p class="muted" style="font-size:.8rem;margin-top:.6rem">Asesor yang dinonaktifkan tidak bisa lagi melihat jadwal dan berkas LSP ini. Riwayat asesmennya tetap tersimpan.</p></div>`;
  } else {
    body = pustakaView();
  }
  return `${phead(ctxName(), 'Skema, asesor, TUK')}${tabs('master', [['skema', 'Skema', D.s.length], ['tuk', 'TUK', D.t.length], ['asesor', 'Asesor', D.a.length], ['skkni', 'Pustaka SKKNI']])}${body}`;
}
function pustakaView() {
  const D = S.d.pustaka || S.d.masterSkkni;
  if (S.appPage === 'master' && !S.d.masterSkkni) { S.d.masterSkkni = {items: []}; api('skkni' + (S.skkniQ ? '?q=' + encodeURIComponent(S.skkniQ) : '')).then(r => { S.d.masterSkkni = r; render(); }); }
  const items = (D || {items: []}).items;
  return `<div class="card stack"><div class="row" style="flex-wrap:nowrap"><input id="skkni-q" placeholder="Cari kode atau judul unit, mis. data, kopi, listrik" value="${esc(S.skkniQ || '')}" style="flex:1"><button class="btn" data-x="skkniCari">${ic('search')}Cari</button></div>
    ${table(['Kode unit', 'Judul unit', 'Sektor', ''], items.map(u => tr([`<span class="mono">${u.kode}</span>`, u.judul, u.sektor, S.appPage === 'master' ? `<button class="btn sm ghost" data-x="skkniPakai" data-kode="${u.kode}" data-judul="${u.judul}">Pakai</button>`
      : can('lsp.manage') ? `<div class="row" style="gap:.3rem;flex-wrap:nowrap"><button class="btn sm ghost" data-x="skkniUbah" data-kode="${u.kode}" data-judul="${u.judul}" data-sektor="${u.sektor}">Ubah</button>${btnAct('skkni/hapus', {kode: u.kode}, 'Hapus', 'sm ghost', 'data-confirm="Hapus unit ini dari pustaka? Skema yang sudah memakainya tidak berubah." data-ok="Unit dihapus."')}</div>` : ''])), 'Tidak ada unit yang cocok.')}</div>`;
}
function pAlumni() {
  const D = S.d.alumni;
  if (!D) return loading();
  const soon = D.items.filter(s => daysTo(s.berlaku) <= 90 && daysTo(s.berlaku) >= 0).length;
  return `${phead(ctxName(), 'Database alumni')}
  <div class="grid g3">${kpi('cert', 'blue', D.items.length, 'Pemegang sertifikat')}${kpi('bell', 'orange', soon, 'Habis ≤ 90 hari', '<span class="muted">peluang sertifikasi ulang</span>')}${kpi('check', 'green', D.items.filter(s => daysTo(s.berlaku) >= 0).length, 'Masih berlaku')}</div>
  <div class="card stack"><div class="row" style="flex-wrap:nowrap"><input id="alumni-q" placeholder="Cari nama, nomor sertifikat, atau skema" value="${esc(S.alumniQ || '')}" style="flex:1"><button class="btn" data-x="alumniCari">${ic('search')}Cari</button></div>
    ${table(['Nama', 'Skema', 'Nomor', 'Terbit', 'Berlaku', ''], D.items.map(s => tr([`<b>${s.nama}</b>`, s.skema_nama, `<span class="mono" style="font-size:.78rem">${s.nomor}</span>`, fmtTgl(s.terbit), `<span class="chip ${daysTo(s.berlaku) < 0 ? 'bad' : daysTo(s.berlaku) <= 90 ? 'warn' : 'ok'}">${fmtTgl(s.berlaku)}</span>`, `<a class="btn sm ghost" href="api.php?r=sertifikat/cetak&id=${s.id}" target="_blank" rel="noopener">Cetak</a>`])), 'Belum ada alumni.')}</div>`;
}
function pMutu() {
  const D = S.d.mutu;
  if (!D) return loading();
  const e = S.edit && S.edit !== 'new' ? D.items.find(x => x.id === Number(S.edit)) : null;
  const editor = S.edit ? (needLspPick() && !e ? pickLspNote('mencatat item mutu') : `<div class="card" id="editor" style="border:2px solid var(--brand-b)"><h3 style="margin-bottom:.8rem">${e ? 'Ubah item mutu' : 'Item mutu baru'}</h3>${form('lsp/mutu', `${e ? hid('id', e.id) : ''}<div class="grid g2">
    ${fld('Jenis', sel('jenis', D.jenis, e && e.jenis))}${fld('Status', sel('status', [['terbuka', 'Terbuka'], ['proses', 'Dalam proses'], ['selesai', 'Selesai']], e && e.status))}
    ${fld('Judul', inp('judul', e ? e.judul : '', 'maxlength="190"'), true)}${fld('Uraian / tindakan', txa('deskripsi', e ? (e.deskripsi || '') : '', 'rows="3" maxlength="2000"'), true)}
    ${fld('PIC', inp('pic', e ? (e.pic || '') : '', 'maxlength="120"'))}${fld('Tenggat', inp('tenggat', e ? (e.tenggat || '') : '', 'type="date"'))}</div>`, {ok: 'Item mutu disimpan.', cancel: true})}</div>`) : '';
  const n = st => D.items.filter(x => x.status === st).length;
  const late = D.items.filter(x => x.status !== 'selesai' && x.tenggat && daysTo(x.tenggat) < 0).length;
  return `${phead(ctxName() + ' · Pedoman BNSP 201', 'Sistem manajemen mutu', `<button class="btn" data-edit="new">${ic('shield')}Catat item</button>`)}
  <div class="grid g4">${kpi('bell', 'orange', n('terbuka'), 'Terbuka')}${kpi('cal', 'blue', n('proses'), 'Dalam proses')}${kpi('check', 'green', n('selesai'), 'Selesai')}${kpi('shield', 'red', late, 'Lewat tenggat')}</div>${editor}
  <div class="card">${table(['Jenis', 'Judul', 'PIC', 'Tenggat', 'Status', ''], D.items.map(x => tr([`<span class="chip plain">${x.jenis}</span>`, `<b>${x.judul}</b>${x.deskripsi ? `<div class="muted" style="font-size:.78rem">${x.deskripsi}</div>` : ''}`, x.pic || '—', x.tenggat ? `<span class="chip ${x.status !== 'selesai' && daysTo(x.tenggat) < 0 ? 'bad' : 'plain'}">${fmtTgl(x.tenggat)}</span>` : '—', `<span class="chip ${x.status === 'selesai' ? 'ok' : x.status === 'proses' ? 'info' : 'warn'}">${x.status}</span>`, `<div class="row" style="gap:.3rem;flex-wrap:nowrap"><button class="btn sm ghost" data-edit="${x.id}">Ubah</button>${x.status === 'selesai' ? '' : btnAct('lsp/mutu/hapus', {id: x.id}, 'Hapus', 'sm ghost', 'data-confirm="Hapus item mutu ini?" data-ok="Item mutu dihapus."')}</div>`])), 'Belum ada catatan mutu.')}</div>`;
}
function pKeuangan() {
  const D = S.d.keuangan;
  if (!D) return loading();
  const t = curTab('keu', 'belum');
  const items = D.items.filter(x => t === 'semua' || x.status === t);
  return `${phead(ctxName(), 'Keuangan')}
  <div class="grid g3">${kpi('money', 'green', fmtRp(D.ringkasan.lunas_bulan), 'Diterima bulan ini')}${kpi('wallet', 'orange', fmtRp(D.ringkasan.belum), 'Belum dibayar')}${kpi('chart', 'blue', fmtRp(D.ringkasan.lunas_total), 'Total diterima')}</div>
  ${tabs('keu', [['belum', 'Belum dibayar', D.items.filter(x => x.status === 'belum').length], ['lunas', 'Lunas'], ['semua', 'Semua']])}
  <div class="card">${table(['Nomor', 'Asesi', 'Uraian', 'Jumlah', 'Status', ''], items.map(x => tr([`<span class="mono" style="font-size:.78rem">${x.nomor}</span>${isPlatform() ? `<div class="muted" style="font-size:.74rem">${x.lsp_nama}</div>` : ''}`, x.nama, x.deskripsi, `<span class="num">${fmtRp(x.jumlah)}</span>`, `<span class="chip ${x.status === 'lunas' ? 'ok' : x.status === 'belum' ? 'warn' : 'plain'}">${x.status}</span>${x.dibayar_at ? `<div class="muted" style="font-size:.74rem">${x.metode}</div>` : ''}`, x.status === 'belum' ? btnAct('lsp/keuangan/lunas', {id: x.id}, 'Tandai lunas', 'sm green', 'data-confirm="Konfirmasi pembayaran transfer manual untuk tagihan ini?" data-ok="Tagihan ditandai lunas."') : '—'])), 'Tidak ada tagihan.')}</div>
  <div class="card"><h3 style="margin-bottom:.6rem">Honor asesor bulan ini</h3>${table(['Asesor', 'LSP', 'Asesi diuji', 'Honor'], D.honor.map(h => tr([h.nama, h.lsp_nama, h.asesi, `<b>${fmtRp(h.total)}</b>`])), 'Belum ada asesmen bulan ini.')}</div>`;
}
const TAHAP = {baru: ['Baru', 'blue'], dihubungi: ['Dihubungi', 'purple'], proposal: ['Proposal', 'orange'], menang: ['Menang', 'green'], kalah: ['Kalah', 'red']};
function pCrm() {
  const D = S.d.crm;
  if (!D) return loading();
  const e = S.edit && S.edit !== 'new' ? D.items.find(x => x.id === Number(S.edit)) : null;
  const editor = S.edit ? (needLspPick() && !e ? pickLspNote('menambah lead') : `<div class="card" id="editor" style="border:2px solid var(--brand-b)"><h3 style="margin-bottom:.8rem">${e ? 'Ubah lead' : 'Lead baru'}</h3>${form('lsp/crm', `${e ? hid('id', e.id) : ''}<div class="grid g2">
    ${fld('Nama kontak', inp('nama', e ? e.nama : '', 'maxlength="150"'))}${fld('Organisasi', inp('organisasi', e ? (e.organisasi || '') : '', 'maxlength="150"'))}
    ${fld('Kontak (email/HP)', inp('kontak', e ? (e.kontak || '') : '', 'maxlength="150"'))}${fld('Sumber', sel('sumber', ['Website', 'Referral', 'Pameran', 'Instagram', 'LinkedIn', 'WhatsApp', 'Lainnya'], e && e.sumber))}
    ${fld('Tahap', sel('tahap', D.tahap.map(x => [x, TAHAP[x][0]]), e && e.tahap))}${fld('Nilai potensi (Rp)', inp('nilai', e ? e.nilai : 0, 'type="number" min="0" step="100000"'))}
    ${fld('Follow-up', inp('followup', e ? (e.followup || '') : '', 'type="date"'))}${fld('Catatan', txa('catatan', e ? (e.catatan || '') : '', 'rows="2" maxlength="2000"'), true)}</div>`, {ok: 'Lead disimpan.', cancel: true})}</div>`) : '';
  const won = D.items.filter(x => x.tahap === 'menang').reduce((a, x) => a + x.nilai, 0);
  const pipe = D.items.filter(x => !['menang', 'kalah'].includes(x.tahap)).reduce((a, x) => a + x.nilai, 0);
  const due = D.items.filter(x => x.followup && daysTo(x.followup) <= 0 && !['menang', 'kalah'].includes(x.tahap)).length;
  return `${phead(ctxName(), 'CRM', `<button class="btn" data-edit="new">${ic('users')}Lead baru</button>`)}
  <div class="grid g3">${kpi('chart', 'blue', fmtRp(pipe), 'Nilai pipeline')}${kpi('check', 'green', fmtRp(won), 'Menang')}${kpi('bell', 'orange', due, 'Follow-up jatuh tempo')}</div>${editor}
  <div style="display:grid;grid-template-columns:repeat(5,minmax(200px,1fr));gap:.8rem;overflow-x:auto">${D.tahap.map((th, i) => `<div class="card stack" style="gap:.6rem;padding:.9rem;background:var(--surface-2)"><div class="spread"><b>${TAHAP[th][0]}</b><span class="chip plain">${D.items.filter(x => x.tahap === th).length}</span></div>
    ${D.items.filter(x => x.tahap === th).map(x => `<div class="card" style="padding:.75rem"><b style="font-size:.9rem">${x.organisasi || x.nama}</b><p class="muted" style="font-size:.78rem">${x.nama}${x.kontak ? ' · ' + x.kontak : ''}</p>${x.nilai ? `<p class="num" style="font-size:.85rem;font-weight:700;margin-top:.2rem">${fmtRp(x.nilai)}</p>` : ''}${x.followup ? `<p style="font-size:.75rem;margin-top:.2rem" class="${daysTo(x.followup) <= 0 ? '' : 'muted'}">${daysTo(x.followup) <= 0 ? '⚠ ' : ''}Follow-up ${fmtTgl(x.followup)}</p>` : ''}
      <div class="row" style="gap:.3rem;margin-top:.5rem">${i > 0 ? btnAct('lsp/crm/tahap', {id: x.id, tahap: D.tahap[i - 1]}, '←', 'sm ghost', 'aria-label="Mundur tahap" data-ok="Tahap diubah."') : ''}${i < D.tahap.length - 1 ? btnAct('lsp/crm/tahap', {id: x.id, tahap: D.tahap[i + 1]}, '→', 'sm ghost', 'aria-label="Maju tahap" data-ok="Tahap diubah."') : ''}<button class="btn sm ghost" data-edit="${x.id}">Ubah</button>${btnAct('lsp/crm/hapus', {id: x.id}, 'Hapus', 'sm ghost', 'data-confirm="Hapus lead ini?" data-ok="Lead dihapus."')}</div></div>`).join('')}</div>`).join('')}</div>`;
}
function pLaporan() {
  const D = S.d.laporan;
  if (!D) return loading();
  const y = new Date().getFullYear(), tot = k => D.items.reduce((a, x) => a + x[k], 0);
  const csv = `api.php?r=lsp/laporan&format=csv&tahun=${D.tahun}${isPlatform() && S.lspFilter ? '&lsp=' + S.lspFilter : ''}`;
  return `${phead(ctxName(), 'Laporan BNSP', `<label class="row" style="gap:.4rem;font-weight:600;font-size:.86rem">Tahun <select id="lapTahun" style="width:auto">${[y, y - 1, y - 2].map(v => `<option ${v === D.tahun ? 'selected' : ''}>${v}</option>`).join('')}</select></label><a class="btn green" href="${csv}">${ic('doc')}Unduh Excel (CSV)</a>`)}
  <div class="grid g4">${kpi('users', 'blue', tot('pendaftar'), 'Pendaftar')}${kpi('check', 'teal', tot('diuji'), 'Diuji')}${kpi('cert', 'green', tot('kompeten'), 'Kompeten')}${kpi('shield', 'orange', tot('belum_kompeten'), 'Belum kompeten')}</div>
  <div class="card">${table(['Kode', 'Skema', 'Pendaftar', 'Diuji', 'Kompeten', 'Belum kompeten', '% kompeten'], D.items.map(x => tr([`<span class="mono">${x.kode}</span>`, x.nama + (isPlatform() ? `<div class="muted" style="font-size:.75rem">${x.lsp_nama}</div>` : ''), x.pendaftar, x.diuji, x.kompeten, x.belum_kompeten, x.kompeten + x.belum_kompeten ? Math.round(100 * x.kompeten / (x.kompeten + x.belum_kompeten)) + '%' : '—'])))}
  <p class="muted" style="font-size:.8rem;margin-top:.6rem">Rekap ini menjadi dasar laporan berkala ke BNSP (jumlah uji, hasil, dan sertifikat terbit per skema).</p></div>`;
}
function pSetting() {
  if (needLspPick()) return `${phead('Platform', 'Profil LSP & pengaturan')}${pickLspNote('mengubah pengaturan LSP')}`;
  const D = S.d.setting;
  if (!D) return loading();
  const l = D.lsp;
  return `${phead(l.nama, 'Profil LSP & pengaturan')}
  <div class="card">${form('lsp/pengaturan', `<div class="grid g2">${fld('Nama LSP' + (isPlatform() ? '' : ' <span class="muted">(diubah oleh Admin Platform lewat tiket support)</span>'), inp('nama', l.nama, 'maxlength="190"' + (isPlatform() ? '' : ' disabled')), true)}${fld('Kota', inp('kota', l.kota, 'maxlength="100"'))}${fld('Telepon', inp('telepon', l.telepon || '', 'maxlength="30"'))}
    ${fld('Alamat', inp('alamat', l.alamat || '', 'maxlength="255"'), true)}${fld('Email', inp('email', l.email || '', 'type="email" maxlength="190"'))}${fld('Website', inp('website', l.website || '', 'maxlength="190" placeholder="https://"'))}
    ${fld('Deskripsi di halaman profil portal', txa('deskripsi', l.deskripsi || '', 'rows="3" maxlength="2000"'), true)}${fld('Lisensi BNSP berlaku s.d.' + (isPlatform() ? '' : ' <span class="muted">(diubah oleh Admin Platform)</span>'), inp('lisensi_sampai', l.lisensi_sampai || '', 'type="date"' + (isPlatform() ? '' : ' disabled')))}${fld('Honor asesor per asesi (Rp)', inp('honor_per_asesi', l.honor_per_asesi, 'type="number" min="0" step="5000"'))}</div>
    <p class="muted" style="font-size:.82rem">Jenis LSP: ${l.jenis} · Paket: ${l.paket} · Status: ${l.status}</p>`, {ok: 'Pengaturan disimpan.'})}</div>
  <div class="card spread"><div><h3>Butuh bantuan?</h3><p class="muted" style="font-size:.86rem">Kirim tiket ke tim PortalLSP.</p></div><button class="btn purple" data-go-app="support">${ic('chat')}Tiket support</button></div>`;
}
function pSupport() {
  const D = S.d.support;
  if (!D) return loading();
  const plat = isPlatform();
  return `${phead(plat ? 'Platform' : ctxName(), 'Tiket support', `<button class="btn" data-edit="new">${ic('chat')}Tiket baru</button>`)}
  ${S.edit === 'new' ? `<div class="card" id="editor" style="border:2px solid var(--brand-b)">${form('tiket', `${fld('Judul', inp('judul', '', 'maxlength="190"'))}${fld('Uraian masalah', txa('isi', '', 'rows="4" maxlength="4000"'))}${fld('Prioritas', sel('prioritas', [['normal', 'Normal'], ['rendah', 'Rendah'], ['tinggi', 'Tinggi']], 'normal'))}`, {ok: 'Tiket dikirim ke tim PortalLSP.', cancel: true, submit: 'Kirim tiket'})}</div>` : ''}
  ${D.items.map(t => `<div class="card stack" style="gap:.6rem"><div class="spread"><div><h3>${t.judul}</h3><p class="muted" style="font-size:.8rem">#${t.id} · ${t.nama}${t.lsp_nama ? ' · ' + t.lsp_nama : ''} · ${fmtWaktu(t.created_at)}</p></div><div class="row"><span class="chip ${t.prioritas === 'tinggi' ? 'bad' : 'plain'}">${t.prioritas}</span><span class="chip ${t.status === 'selesai' ? 'ok' : t.status === 'proses' ? 'info' : 'warn'}">${t.status}</span></div></div>
    <p style="font-size:.9rem">${t.isi}</p>${t.balasan.map(b => `<div class="alert info" style="font-size:.86rem"><span><b>${b.nama}</b> · ${fmtWaktu(b.created_at)}<br>${b.isi}</span></div>`).join('')}
    ${t.status !== 'selesai' || plat ? form('tiket/balas', `${hid('id', t.id)}<div class="row" style="flex-wrap:nowrap;align-items:flex-end"><label class="f" style="flex:1">Balasan${txa('isi', '', 'rows="1" maxlength="4000"')}</label>${plat ? sel('status', [['', 'Status tetap'], ['proses', 'Proses'], ['selesai', 'Selesai'], ['terbuka', 'Buka lagi']], '', 'style="width:auto"') : ''}</div>`, {ok: 'Balasan terkirim.', submit: 'Kirim', cls: 'purple'}) : ''}</div>`).join('') || '<div class="card"><p class="muted">Belum ada tiket.</p></div>'}`;
}

/* ===================== TUK ===================== */
function dTuk() {
  const D = S.d[S.appPage === 'dashTuk' ? 'dashTuk' : 'dashboard'];
  if (!D || !D.items) return loading();
  return `${phead(ctxName(), ME.active.tuk_nama || 'Semua TUK', can('tuk.schedule') ? `<button class="btn orange" data-go-app="jadwalT">${ic('cal')}Jadwal TUK</button>` : '')}
  <div class="grid g4">${kpi('users', 'blue', D.items.reduce((a, t) => a + t.pemohon, 0), 'Peserta aktif')}${kpi('cal', 'teal', D.items.reduce((a, t) => a + t.jadwal_bulan, 0), 'Jadwal bulan ini')}${kpi('build', 'purple', D.items.length, 'TUK')}${kpi('shield', 'orange', D.items.filter(t => t.verif_sampai && daysTo(t.verif_sampai) < 60).length, 'Verifikasi < 60 hari')}</div>
  <div class="grid g2">${D.items.map(t => `<div class="card stack" style="gap:.6rem"><div class="spread"><div><h3>${t.nama}</h3><p class="muted" style="font-size:.82rem">${t.jenis} · ${t.alamat || ''}</p></div><span class="chip ${t.status === 'aktif' ? 'ok' : 'plain'}">${t.status}</span></div>
    ${infoGrid([['Peserta aktif', t.pemohon], ['Jadwal bulan ini', t.jadwal_bulan], ['Sarana siap', t.sarpras_siap === null ? '—' : t.sarpras_siap + '%'], ['Verifikasi s.d.', t.verif_sampai ? `<span class="chip ${daysTo(t.verif_sampai) < 60 ? 'warn' : 'ok'}">${fmtTgl(t.verif_sampai)}</span>` : '—']])}</div>`).join('') || '<div class="card"><p class="muted">Belum ada TUK.</p></div>'}</div>
  <div class="alert info">${ic('shield')}<span>${ME.active.role === 'admin_tuk' ? 'Admin TUK hanya melihat data TUK ini. Data TUK lain tidak tampil.' : 'Menampilkan semua TUK di ' + ctxName() + '.'}</span></div>`;
}
function pPemohon() {
  const D = S.d.pemohon;
  if (!D) return loading();
  return `${phead(ME.active.tuk_nama || ctxName(), 'Pemohon di TUK')}<div class="card">${table(['Asesi', 'Skema', 'Jadwal', 'TUK', 'Asesor', 'Status'], D.items.map(p => tr([`<b>${p.asesi_nama}</b>`, p.skema_nama, fmtHari(p.tanggal), p.tuk_nama, p.asesor_nama || '—', stChip(p)])), 'Belum ada pemohon.')}</div>`;
}
function pJadwalT() {
  const D = S.d.jadwalT;
  if (!D) return loading();
  return `${phead(ME.active.tuk_nama || ctxName(), 'Jadwal TUK')}${chatBox()}<div class="card">${table(['Tanggal', 'Skema', 'TUK', 'Metode', 'Peserta', 'Asesor', ''], D.items.map(j => tr([`<b>${fmtHari(j.tanggal)}</b><div class="muted" style="font-size:.75rem">${j.jam}</div>`, j.skema_nama, j.tuk_nama, j.metode, `${j.peserta}/${j.kuota}`, j.asesor_nama || '—', `<button class="btn sm ghost" data-x="chatOpen" data-id="${j.id}">${ic('chat')}Chat</button>`])), 'Belum ada jadwal.')}
  <p class="muted" style="font-size:.8rem;margin-top:.6rem">Jadwal dibuat Admin LSP. Kirim usulan jadwal atau kebutuhan ruang lewat group chat jadwal.</p></div>`;
}
function pSarpras() {
  const D = S.d.sarpras;
  if (!D) return loading();
  const e = S.edit && S.edit !== 'new' ? D.s.find(x => x.id === Number(S.edit)) : null;
  const tukOpt = D.tuk.map(t => [t.id, t.nama]);
  const editor = S.edit ? `<div class="card" id="editor" style="border:2px solid var(--brand-b)"><h3 style="margin-bottom:.8rem">${e ? 'Ubah alat' : 'Tambah alat'}</h3>${form('tuk/sarpras', `${e ? hid('id', e.id) : ''}<div class="grid g2">
    ${!e && ME.active.role !== 'admin_tuk' ? fld('TUK', sel('tuk_id', tukOpt, '', 'data-num'), true) : ''}${fld('Nama alat / fasilitas', inp('nama', e ? e.nama : '', 'maxlength="150"'), true)}
    ${fld('Jumlah', inp('jumlah', e ? e.jumlah : 1, 'type="number" min="0"'))}${fld('Kondisi', sel('kondisi', [['baik', 'Baik'], ['perlu perbaikan', 'Perlu perbaikan'], ['rusak', 'Rusak']], e && e.kondisi))}${fld('Catatan', inp('catatan', e ? (e.catatan || '') : '', 'maxlength="255"'), true)}</div>`, {ok: 'Data sarana disimpan.', cancel: true})}</div>` : '';
  return `${phead(ME.active.tuk_nama || ctxName(), 'Sarana & prasarana', `<button class="btn" data-edit="new">${ic('build')}Tambah alat</button>`)}${editor}
  <div class="card">${table(['TUK', 'Alat / fasilitas', 'Jumlah', 'Kondisi', 'Catatan', 'Diperbarui', ''], D.s.map(x => tr([x.tuk_nama, `<b>${x.nama}</b>`, x.jumlah, `<span class="chip ${x.kondisi === 'baik' ? 'ok' : x.kondisi === 'rusak' ? 'bad' : 'warn'}">${x.kondisi}</span>`, x.catatan || '—', fmtTgl(x.updated_at), `<div class="row" style="gap:.3rem;flex-wrap:nowrap"><button class="btn sm ghost" data-edit="${x.id}">Ubah</button>${btnAct('tuk/sarpras/hapus', {id: x.id}, 'Hapus', 'sm ghost', 'data-confirm="Hapus alat ini?" data-ok="Alat dihapus."')}</div>`])), 'Belum ada data sarana.')}</div>`;
}
function pChat() {
  const D = S.d.chat;
  if (!D) return loading();
  return `${phead(ME.active.tuk_nama || ctxName(), 'Group chat asesmen')}
  <div class="layout-2"><div class="card stack" style="gap:.5rem">${D.items.map(j => `<button class="notif-item ${S.chat === j.id ? 'unread' : ''}" data-x="chatOpen" data-id="${j.id}"><span class="dot"></span><span style="flex:1"><b>${j.skema_nama}</b><span class="muted" style="display:block;font-size:.8rem">${fmtHari(j.tanggal)} · ${j.tuk_nama || '—'} · ${j.peserta} peserta</span></span></button>`).join('') || '<p class="muted">Belum ada ruang chat.</p>'}</div>
  <div>${S.chat ? chatBox() : '<div class="card"><p class="muted">Pilih jadwal untuk membuka chat dengan asesor, asesi, dan TUK.</p></div>'}</div></div>`;
}
function pAlumniT() {
  const D = S.d.alumniT;
  if (!D) return loading();
  return `${phead(ME.active.tuk_nama || ctxName(), 'Alumni TUK')}<div class="card">${table(['Nama', 'Skema', 'Nomor', 'Terbit', 'Berlaku'], D.items.map(s => tr([`<b>${s.nama}</b>`, s.skema_nama, `<span class="mono" style="font-size:.78rem">${s.nomor}</span>`, fmtTgl(s.terbit), fmtTgl(s.berlaku)])), 'Belum ada alumni dari TUK ini.')}</div>`;
}

/* ===================== PLATFORM ===================== */
function dSuper() {
  const D = S.d.dashboard;
  if (!D || !D.kpi) return loading();
  const k = D.kpi;
  return `${phead('Platform', 'Ringkasan platform', `<button class="btn green" data-go-app="lspList">${ic('build')}Kelola LSP klien</button>`)}
  <div class="grid g3">${kpi('check', 'orange', String(S.reviews.length), 'Listing menunggu persetujuan', S.reviews.length ? '<button class="btn sm orange" data-go-app="approval" style="margin-top:.3rem">Tinjau sekarang</button>' : '')}${kpi('build', 'blue', k.lsp_aktif, 'LSP aktif')}${kpi('chat', 'pink', k.tiket_terbuka, 'Tiket support terbuka', k.tiket_terbuka ? '<button class="btn sm pink" data-go-app="support" style="margin-top:.3rem">Buka</button>' : '')}</div>
  <div class="grid g3">${kpi('users', 'purple', k.asesi, 'Asesi aktif')}${kpi('cert', 'green', k.sertifikat_tahun, 'Sertifikat terbit tahun ini')}${kpi('money', 'teal', fmtRp(k.transaksi_bulan), 'Transaksi bulan ini')}</div>
  <div class="card"><div class="spread" style="margin-bottom:.6rem"><h3>LSP klien</h3><button class="btn sm" data-go-app="lspList">Lihat semua</button></div>${table(['LSP', 'Pengguna', 'Listing tayang', 'Status', ''], S.lsps.map(l => tr([`<b>${l.nama}</b>`, l.pengguna, l.tayang, `<span class="chip ${l.status === 'aktif' ? 'ok' : 'bad'}">${l.status}</span>`, `<button class="btn sm" data-openlsp="${l.id}">Buka data</button>`])))}</div>
  <div class="alert info">${ic('shield')}<span>Admin Platform punya akses penuh ke semua menu dan semua LSP. Pilih LSP di bagian atas untuk menyaring, atau "Semua LSP" untuk melihat gabungan. Setiap perubahan data tercatat di log audit.</span></div>`;
}
function pLspList() {
  const rows = S.lsps.map(l => tr([`<b>${l.nama}</b><div class="muted" style="font-size:.76rem">${l.jenis} · ${l.kota}</div>`, l.pengguna, l.asesor, l.asesi, l.tuk, `${l.tayang} / ${l.listing}${l.menunggu ? ` <span class="chip warn">${l.menunggu} menunggu</span>` : ''}`, `<span class="chip ${l.status === 'aktif' ? 'ok' : 'bad'}">${l.status === 'aktif' ? 'Aktif' : 'Nonaktif'}</span>`,
    `<div class="row" style="gap:.3rem"><button class="btn sm" data-openlsp="${l.id}">Buka data</button>${btnAct('platform/lsp/status', {id: l.id, status: l.status === 'aktif' ? 'nonaktif' : 'aktif'}, l.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan', 'sm ' + (l.status === 'aktif' ? 'ghost' : 'green'), `data-confirm="${l.status === 'aktif' ? 'Nonaktifkan LSP ini? Semua penggunanya kehilangan akses.' : 'Aktifkan kembali LSP ini?'}" data-ok="Status LSP diubah."`)}</div>`]));
  const editor = S.edit === 'new' ? `<div class="card" id="editor" style="border:2px solid var(--brand-b)"><h3 style="margin-bottom:.8rem">Onboarding LSP klien baru</h3>${form('platform/lsp', `<div class="grid g2">
    ${fld('Nama LSP', inp('nama', '', 'maxlength="190"'), true)}${fld('Kode singkat', inp('kode', '', 'maxlength="10" placeholder="mis. KNH"'))}${fld('Jenis', sel('jenis', ['P1', 'P2', 'P3'], 'P3'))}${fld('Kota', inp('kota', '', 'maxlength="100"'))}${fld('Paket', sel('paket', ['Basic', 'Pro', 'Enterprise'], 'Pro'))}
    ${fld('Nama Admin LSP', inp('admin_nama', '', 'maxlength="120"'))}${fld('Email Admin LSP', inp('admin_email', '', 'type="email" maxlength="190"'))}${fld('Password awal Admin LSP', inp('admin_password', '', 'type="password" autocomplete="new-password"'), true)}</div>
    <p class="muted" style="font-size:.82rem">Admin LSP wajib mengganti password awal saat pertama masuk. Serahkan password lewat kanal aman, bukan email.</p>`, {ok: 'LSP dibuat. Admin LSP bisa masuk dengan password awal.', cancel: true, submit: 'Buat LSP'})}</div>` : '';
  return `${phead('Platform', 'LSP klien', `<button class="btn green" data-edit="new">${ic('build')}Tambah LSP klien</button>`)}${editor}
  <div class="grid g4">${kpi('build', 'blue', S.lsps.length, 'LSP terdaftar')}${kpi('users', 'purple', S.lsps.reduce((a, l) => a + l.pengguna, 0), 'Keanggotaan aktif')}${kpi('wallet', 'green', S.lsps.reduce((a, l) => a + l.tayang, 0), 'Listing tayang')}${kpi('check', 'orange', S.lsps.reduce((a, l) => a + l.menunggu, 0), 'Menunggu persetujuan')}</div>
  <div class="card">${table(['LSP', 'Pengguna', 'Asesor', 'Asesi', 'TUK', 'Listing tayang', 'Status', ''], rows, 'Memuat…')}
  <p class="muted" style="font-size:.8rem;margin-top:.6rem">"Buka data" menampilkan dashboard, etalase, pengguna, dan log notifikasi khusus LSP itu.</p></div>`;
}
function pPaket() {
  const D = S.d.paket;
  if (!D) return loading();
  const mrr = D.items.filter(l => l.status === 'aktif').reduce((a, l) => a + l.biaya_bulan, 0);
  return `${phead('Platform', 'Paket & tagihan')}
  <div class="grid g3">${D.paket.map((p, i) => kpi(['users', 'chart', 'shield'][i], ['blue', 'purple', 'orange'][i], fmtRp(p.biaya), 'Paket ' + p.nama + ' / bulan', `<span class="muted">kuota ${p.kuota.toLocaleString('id-ID')} asesi/tahun</span>`)).join('')}</div>
  <div class="card"><div class="spread" style="margin-bottom:.6rem"><h3>Langganan LSP</h3><span class="chip ok">Pendapatan berulang: ${fmtRp(mrr)}/bulan</span></div>
  ${table(['LSP', 'Paket', 'Pemakaian kuota tahun ini', 'Biaya/bulan', 'Status'], D.items.map(l => tr([`<b>${l.nama}</b>`, sel('paket', D.paket.map(p => p.nama), l.paket, `data-paket="${l.id}" style="width:auto" aria-label="Paket ${l.nama}"`),
    `<div style="height:8px;border-radius:99px;background:var(--surface-2);overflow:hidden;width:160px"><div style="height:100%;width:${Math.min(100, 100 * l.terpakai / Math.max(1, l.kuota_asesi))}%;background:${l.terpakai / l.kuota_asesi > .9 ? 'var(--g-red)' : 'var(--g-green)'}"></div></div><span class="muted" style="font-size:.76rem">${l.terpakai} / ${l.kuota_asesi.toLocaleString('id-ID')}</span>`, fmtRp(l.biaya_bulan), `<span class="chip ${l.status === 'aktif' ? 'ok' : 'bad'}">${l.status}</span>`])))}</div>`;
}
function pPustaka() {
  return `${phead('Platform', 'Pustaka SKKNI')}<div class="alert info">${ic('book')}<span>Unit kompetensi global yang bisa dipakai semua LSP saat menyusun skema (menu Skema, Asesor, TUK → Pustaka SKKNI → Pakai).</span></div>${pustakaView()}
  <div class="card"><h3 style="margin-bottom:.8rem">Tambah unit SKKNI</h3>${form('skkni', `<div class="grid g2">${fld('Kode unit', inp('kode', '', 'maxlength="40" class="mono"'))}${fld('Sektor', inp('sektor', '', 'maxlength="60" placeholder="mis. TIK"'))}${fld('Judul unit', inp('judul', '', 'maxlength="255"'), true)}</div>`, {ok: 'Unit ditambahkan ke pustaka.'})}</div>`;
}
function pAudit() {
  const D = S.d.audit;
  if (!D) return loading();
  return `${phead(ctxName(), 'Log akses & audit')}<div class="alert info">${ic('shield')}<span>Semua login, akses ditolak, perubahan data, unduhan dokumen, dan keputusan tercatat. Menampilkan 300 catatan terakhir.</span></div>
  <div class="card">${table(['Waktu', 'Pengguna', 'LSP', 'Aksi', 'Target', 'IP'], D.items.map(a => tr([`<span class="num" style="white-space:nowrap">${fmtWaktu(a.created_at)}</span>`, a.email || '—', a.lsp_nama || '—', `<span class="chip ${/denied|failed|locked|ditolak/.test(a.action) ? 'bad' : 'plain'} mono" style="font-size:.72rem">${a.action}</span>`, `<span class="muted" style="font-size:.8rem">${a.target || '—'}</span>`, `<span class="mono" style="font-size:.76rem">${a.ip}</span>`])))}</div>`;
}

/* ===================== Etalase: tautan ke skema master ===================== */
const _pEtalase = pEtalase;
pEtalase = function () {
  let html = _pEtalase();
  const o = S.d.etalase;
  if (S.form === 'skema' && o && o.skema) {
    const lspId = S.lspFilter || ME.active.lsp_id;
    const opts = o.skema.filter(s => !lspId || s.lsp_id === lspId).map(s => `<option value="${s.id}">${s.kode} · ${s.nama}</option>`).join('');
    html = html.replace('<label class="f">Judul<input id="lf-judul"', `<label class="f" style="grid-column:1/-1">Skema master (agar asesi bisa langsung daftar jadwal)<select id="lf-skema"><option value="">— Tidak ditautkan —</option>${opts}</select></label><label class="f">Judul<input id="lf-judul"`);
  }
  return html;
};
const _saveListing = saveListing;
saveListing = async function (status) {
  const el = document.getElementById('lf-skema');
  S.pendingSkema = el && el.value ? Number(el.value) : 0;
  return _saveListing(status);
};
const _api = api;
api = function (path, data, retried) {
  if (path === 'listings' && data && data.tipe === 'skema' && S.pendingSkema) data = {...data, skema_id: S.pendingSkema};
  return _api(path, data, retried);
};

/* ===================== Portal publik ===================== */
function pubErr(x) { return x && x.error ? `<div class="card"><p class="alert bad">${esc(x.error)}</p></div>` : ''; }
function skemaCardPub(c) {
  return `<article class="card skema" data-x="pubDetail" data-id="${c.id}" tabindex="0">
    <div class="spread"><span class="badge" style="background:${GRAD[WARNA[c.id % WARNA.length]]}">${c.kode || initials(c.judul)}</span>${c.jadwal_terdekat ? `<span class="chip ok">Jadwal ${fmtTgl(c.jadwal_terdekat).replace(/ \d{4}$/, '')}</span>` : '<span class="chip plain">Jadwal menyusul</span>'}</div>
    <div><h3>${c.judul}</h3><p class="muted" style="font-size:.85rem;margin-top:.2rem">${c.lsp_nama}</p></div>
    <div class="meta"><span class="chip info">${c.bidang}</span>${c.kkni ? `<span class="chip plain">KKNI ${c.kkni}</span>` : ''}${c.unit ? `<span class="chip plain">${c.unit} unit</span>` : ''}<span class="chip ${c.format === 'SJJ' ? 'ok' : 'plain'}">${c.format}</span></div>
    <div class="spread" style="margin-top:auto"><div><span class="eyebrow">Biaya uji</span><div class="price">${fmtRp(c.harga)}</div></div><span class="muted row" style="gap:.25rem;font-size:.84rem">${ic('pin')}${c.kota}</span></div>
  </article>`;
}
function pBeranda() {
  const cat = lazy('catalog', 'pub/catalog'), jd = lazy('jadwal', 'pub/jadwal'), ls = lazy('lsp', 'pub/lsp');
  const sk = cat && cat.items ? cat.items.filter(c => c.tipe === 'skema') : [];
  const alumni = ls && ls.items ? ls.items.reduce((a, l) => a + l.alumni, 0) : 0;
  return `<section class="hero"><div class="wrap">
    <div><span class="chip" style="background:rgba(255,255,255,.18);color:#fff">Satu portal untuk semua LSP berlisensi BNSP</span>
      <h1 style="margin-top:1rem">Cari, daftar, dan raih sertifikat kompetensi BNSP dalam satu tempat</h1>
      <p class="lead">Bandingkan skema dari berbagai LSP, pilih jadwal dan TUK, unggah berkas sekali, bayar online, lalu pantau proses asesmen sampai sertifikat terbit.</p>
      <form class="search" id="heroSearch"><input id="hq" placeholder="Cari skema, mis. Web Developer, Barista" aria-label="Cari skema"><button class="btn" type="submit">${ic('search')}Cari Skema</button></form>
      <div class="quick"><button class="btn sm green" data-filter="TIK">Teknologi Informasi</button><button class="btn sm orange" data-filter="Pariwisata">Pariwisata</button><button class="btn sm teal" data-filter="Konstruksi">Konstruksi</button><button class="btn sm pink" data-filter="Bisnis">Bisnis</button></div></div>
    <div class="hero-card"><p class="eyebrow" style="color:rgba(255,255,255,.8)">Alur di PortalLSP</p>
      <div class="cert" style="margin-top:.7rem"><div class="spread"><b>Contoh: Junior Web Developer</b><span class="chip info">Pra-asesmen</span></div>
        <div class="track">${[1, 1, 1, 2, 0, 0, 0].map(v => `<div class="${v === 1 ? 'd' : v === 2 ? 'n' : ''}"></div>`).join('')}</div>${TRACK_L}</div>
      <div class="row" style="margin-top:.9rem;justify-content:space-between"><span style="font-size:.85rem;opacity:.9">Pantau status permohonan Anda kapan saja</span><button class="btn sm white" data-go="${ME ? 'beranda' : 'daftar'}" ${ME ? 'data-enter="1"' : ''}>${ME ? 'Dashboard' : 'Daftar gratis'}</button></div></div>
  </div></section>
  <div class="wrap"><div class="stats">${[[ls && ls.items ? ls.items.length : '…', 'LSP terdaftar'], [sk.length || '…', 'skema terbuka'], [jd && jd.items ? jd.items.length : '…', 'jadwal uji dibuka'], [alumni || '…', 'pemegang sertifikat']].map(([b, l]) => `<div class="stat"><b>${b}</b><span class="muted" style="font-size:.85rem">${l}</span></div>`).join('')}</div></div>
  <section class="block"><div class="wrap"><div class="sec-head"><div><p class="eyebrow">Skema terbuka</p><h2>Pilih skema & daftar online</h2></div><button class="btn sm purple" data-go="cari">Lihat semua skema</button></div>
    <div class="grid g4">${sk.slice(0, 4).map(skemaCardPub).join('') || (cat ? '<p class="muted">Belum ada skema.</p>' : '<p class="muted">Memuat…</p>')}</div></div></section>
  <section class="block" style="padding-top:0"><div class="wrap layout-2">
    <div class="card"><div class="sec-head" style="margin-bottom:.6rem"><h3>Jadwal uji terdekat</h3><button class="btn sm teal" data-go="jadwal">Semua jadwal</button></div>
      ${table(['Tanggal', 'Skema', 'TUK', 'Sisa kuota'], (jd && jd.items || []).slice(0, 5).map(j => tr([`<b class="num">${fmtTgl(j.tanggal).replace(/ \d{4}$/, '')}</b>`, j.skema_nama, `<span class="muted">${j.tuk_nama || '—'}</span>`, j.sisa ? `<span class="chip ${j.sisa <= 3 ? 'warn' : 'ok'}">${j.sisa}</span>` : '<span class="chip bad">Penuh</span>'])), jd ? 'Belum ada jadwal.' : 'Memuat…')}</div>
    <div class="card" style="background:var(--grad);color:#fff;border:0"><p class="eyebrow" style="color:rgba(255,255,255,.8)">Verifikasi sertifikat</p><h3 style="margin-top:.4rem;font-size:1.25rem">Pastikan sertifikat kompetensi asli</h3>
      <p style="opacity:.9;font-size:.9rem;margin-top:.4rem">Masukkan nomor sertifikat atau kode verifikasi.</p>
      <form class="search" id="cekHome" style="margin-top:1rem"><input id="cek-home" placeholder="Nomor atau kode, mis. TDN7K3P9QX" aria-label="Nomor sertifikat"><button class="btn green" type="submit">${ic('check')}Cek</button></form></div>
  </div></section>
  ${blogHome()}
  <section class="block" style="padding-top:0"><div class="wrap"><div class="sec-head"><div><p class="eyebrow">Alur sertifikasi</p><h2>Empat langkah sampai sertifikat terbit</h2></div></div>
    <div class="steps">${[['blue', 'Pilih skema & jadwal', 'Bandingkan skema dari semua LSP, lalu pilih TUK dan tanggal uji.'], ['purple', 'Isi APL & unggah berkas', 'Isi asesmen mandiri FR.APL.02. Berkas di profil dipakai ulang untuk skema berikutnya.'], ['orange', 'Bayar & ikuti asesmen', 'Bayar setelah berkas diverifikasi, ikuti pra-asesmen dan uji di TUK atau jarak jauh.'], ['green', 'Sertifikat terbit', 'Setelah pleno, sertifikat digital ber-tautan verifikasi masuk ke dompet Anda.']].map(([w, t, d], i) => `<div class="card"><span class="step-n" style="background:${GRAD[w]}">${i + 1}</span><h3>${t}</h3><p class="muted" style="font-size:.88rem;margin-top:.3rem">${d}</p></div>`).join('')}</div></div></section>
  <section class="block" style="padding-top:0"><div class="wrap"><div class="cta"><div style="max-width:60ch"><p class="eyebrow" style="color:rgba(255,255,255,.8)">Untuk Lembaga Sertifikasi Profesi</p><h2 style="margin-top:.35rem">Kelola asesmen, mutu, dan sertifikat LSP Anda tanpa tumpukan berkas</h2><p style="opacity:.9;margin-top:.5rem">Pendaftaran online, jadwal & penugasan asesor, pleno, sertifikat digital, laporan BNSP, CRM, dan modul mutu dalam satu sistem.</p></div>
    <div class="row"><button class="btn lg white" data-go="untuk">Lihat paket</button><button class="btn lg orange" data-go="untuk">Minta demo</button></div></div></div></section>`;
}
function pCari() {
  const cat = lazy('catalog', 'pub/catalog');
  if (!cat) return `<section class="block"><div class="wrap">${loading()}</div></section>`;
  const bidang = ['Semua', 'TIK', 'Pariwisata', 'Konstruksi', 'Bisnis', 'Kesehatan'];
  const list = (cat.items || []).filter(s => s.tipe === 'skema' && (S.filter === 'Semua' || s.bidang === S.filter) && (!S.q || s.judul.toLowerCase().includes(S.q.toLowerCase())));
  return `<section class="block"><div class="wrap stack">${pubErr(cat)}
    <div><p class="eyebrow">Marketplace skema</p><h2>Cari skema dari semua LSP</h2></div>
    <div class="card stack" style="gap:.8rem"><input id="cq" placeholder="Cari nama skema" value="${esc(S.q)}" aria-label="Cari nama skema"><div class="filters">${bidang.map(b => `<button class="fchip ${S.filter === b ? 'on' : ''}" data-filter="${b}">${b}</button>`).join('')}</div></div>
    <div class="spread"><p class="muted"><b class="num" style="color:var(--fg)">${list.length}</b> skema ditemukan</p><span class="chip plain">Hanya skema yang sudah dikurasi Admin Platform</span></div>
    <div class="grid g4">${list.map(skemaCardPub).join('') || '<div class="card empty" style="grid-column:1/-1"><h3>Belum ada skema yang cocok</h3><p class="muted">Coba kata kunci lain atau pilih bidang "Semua".</p></div>'}</div>
  </div></section>`;
}
function pDetail() {
  const d = lazy('detail-' + S.skema, 'pub/skema?id=' + S.skema);
  if (!d) return `<section class="block"><div class="wrap">${loading()}</div></section>`;
  if (d.error) return `<section class="block"><div class="wrap stack"><button class="btn ghost sm" data-go="cari" style="align-self:flex-start">← Kembali</button>${pubErr(d)}</div></section>`;
  const L = d.listing, sk = d.skema, pick = S.pubJadwal || (d.jadwal.find(j => j.sisa) || {}).id;
  return `<section class="block"><div class="wrap stack">
    <button class="btn ghost sm" data-go="cari" style="align-self:flex-start">← Kembali ke pencarian</button>
    <div class="layout-2"><div class="stack">
      <div class="card" style="background:var(--grad);color:#fff;border:0"><div class="row"><span class="chip" style="background:rgba(255,255,255,.2);color:#fff">${L.bidang}</span>${sk ? `<span class="chip" style="background:rgba(255,255,255,.2);color:#fff">KKNI ${sk.kkni}</span><span class="chip" style="background:rgba(255,255,255,.2);color:#fff">${sk.units.length} unit kompetensi</span>` : ''}</div>
        <h1 style="font-size:clamp(1.6rem,3.4vw,2.3rem);margin-top:.8rem">${L.judul}</h1><p style="opacity:.9;margin-top:.4rem">${L.lsp_nama}${L.lisensi_sampai ? ' · Lisensi BNSP berlaku s.d. ' + fmtTgl(L.lisensi_sampai) : ''}</p></div>
      <div class="card"><h3>Tentang skema</h3><p style="margin-top:.4rem">${sk && sk.deskripsi ? sk.deskripsi : L.deskripsi}</p></div>
      <div class="card"><h3 style="margin-bottom:.8rem">Pilih jadwal uji</h3>${d.jadwal.length ? `<div class="stack" style="gap:.6rem">${d.jadwal.map(j => `<label class="sched-opt ${pick === j.id ? 'on' : ''}" ${j.sisa ? '' : 'style="opacity:.55"'}><input type="radio" name="pubjd" value="${j.id}" ${pick === j.id ? 'checked' : ''} ${j.sisa ? '' : 'disabled'} style="width:auto">
        <div style="flex:1;min-width:0"><b>${fmtHari(j.tanggal)}</b> · <span class="muted">${j.jam} WIB</span><div class="muted" style="font-size:.85rem">${j.tuk_nama || 'TUK menyusul'}${j.tuk_alamat ? ' · ' + j.tuk_alamat : ''} · ${j.metode}</div></div>
        ${j.sisa ? `<span class="chip ${j.sisa <= 3 ? 'warn' : 'ok'}">Sisa ${j.sisa}/${j.kuota}</span>` : '<span class="chip bad">Penuh</span>'}</label>`).join('')}</div>
        <div class="row" style="justify-content:flex-end;margin-top:1rem"><button class="btn green lg" data-x="apStart" data-id="${L.id}" data-jadwal="${pick || 0}">${ic('check')}Daftar uji kompetensi</button></div>` : '<p class="muted">Belum ada jadwal terbuka. Hubungi LSP untuk info jadwal berikutnya.</p>'}</div>
      ${sk ? `<div class="card"><h3>Unit kompetensi</h3><p class="muted" style="font-size:.85rem">Mengacu SKKNI</p><div style="margin-top:.7rem">${table(['Kode unit', 'Judul unit'], sk.units.map(u => tr([`<span class="mono">${u.kode}</span>`, u.judul])))}</div></div>` : ''}
    </div>
    <aside class="stack" style="position:sticky;top:80px">
      <div class="card"><p class="eyebrow">Ringkasan</p><div class="stack" style="gap:.55rem;margin-top:.7rem;font-size:.9rem">
        <div class="spread"><span class="muted">Skema</span><b style="text-align:right">${L.judul}</b></div><div class="spread"><span class="muted">Metode</span><span class="chip ${L.format === 'SJJ' ? 'ok' : 'plain'}">${L.format}</span></div>
        <div class="spread"><span class="muted">Lokasi</span><b>${L.kota}</b></div><hr style="border:0;border-top:1px dashed var(--line);width:100%">
        <div class="spread"><b>Biaya uji</b><span class="price">${fmtRp(sk ? sk.harga : L.harga)}</span></div><p class="muted" style="font-size:.78rem">Dibayar setelah LSP memverifikasi berkas Anda.</p></div></div>
      ${sk && sk.persyaratan ? `<div class="card"><h3>Persyaratan dasar</h3><ul style="margin:.6rem 0 0;padding-left:1.1rem;display:grid;gap:.3rem;font-size:.9rem">${sk.persyaratan.split('\n').map(x => `<li>${x}</li>`).join('')}</ul></div>` : ''}
      <div class="card row" style="justify-content:space-between"><div><b>${L.lsp_nama}</b><p class="muted" style="font-size:.82rem">${L.lsp_kota || ''}</p></div><button class="btn sm purple" data-go="lsp">Profil LSP</button></div>
    </aside></div></div></section>`;
}
document.addEventListener('change', e => { if (e.target.name === 'pubjd') { S.pubJadwal = Number(e.target.value); render(); } });
function pJadwal() {
  const jd = lazy('jadwal', 'pub/jadwal');
  if (!jd) return `<section class="block"><div class="wrap">${loading()}</div></section>`;
  return `<section class="block"><div class="wrap stack">${pubErr(jd)}<div><p class="eyebrow">Kalender uji</p><h2>Jadwal uji kompetensi terbuka</h2></div>
  <div class="card">${table(['Tanggal', 'Skema', 'LSP', 'TUK', 'Metode', 'Kuota', ''], (jd.items || []).map(j => tr([`<b class="num">${fmtHari(j.tanggal)}</b>`, j.skema_nama, `<span class="muted">${j.lsp_nama}</span>`, `<span class="muted">${j.tuk_nama || '—'}</span>`, `<span class="chip ${j.metode === 'SJJ' ? 'ok' : 'plain'}">${j.metode}</span>`, j.sisa ? `<span class="chip ${j.sisa <= 3 ? 'warn' : 'ok'}">Sisa ${j.sisa}</span>` : '<span class="chip bad">Penuh</span>', j.sisa ? `<button class="btn sm" data-x="apStart" data-id="${j.listing_id}" data-jadwal="${j.id}">Daftar</button>` : ''])), 'Belum ada jadwal terbuka.')}</div></div></section>`;
}
function pLsp() {
  const ls = lazy('lsp', 'pub/lsp');
  if (!ls) return `<section class="block"><div class="wrap">${loading()}</div></section>`;
  const cat = lazy('catalog', 'pub/catalog');
  const open = S.lspOpen;
  return `<section class="block"><div class="wrap stack">${pubErr(ls)}<div><p class="eyebrow">Direktori</p><h2>Daftar LSP</h2><p class="muted" style="margin-top:.3rem">Hanya LSP aktif yang tampil. Setiap LSP punya halaman profil di portal ini.</p></div>
  <div class="grid g3">${(ls.items || []).map((l, i) => `<article class="card stack" style="gap:.8rem">
    <div class="row"><span class="logo-mark" style="width:48px;height:48px;background:${GRAD[WARNA[i % WARNA.length]]};color:#fff">${ic('build')}</span><div style="min-width:0"><h3>${l.nama}</h3><p class="muted" style="font-size:.84rem">LSP ${l.jenis} · ${l.kota}</p></div></div>
    ${l.lisensi_sampai ? `<div class="row"><span class="chip ok">${ic('check')}Lisensi s.d. ${fmtTgl(l.lisensi_sampai)}</span></div>` : ''}
    <div class="spread"><span class="muted" style="font-size:.88rem"><b class="num" style="color:var(--fg)">${l.skema}</b> skema · <b class="num" style="color:var(--fg)">${l.tuk}</b> TUK · <b class="num" style="color:var(--fg)">${l.alumni}</b> alumni</span><button class="btn sm" data-x="lspOpen" data-id="${l.id}">${open === l.id ? 'Tutup' : 'Lihat profil'}</button></div>
    ${open === l.id ? `<div class="stack" style="gap:.5rem;border-top:1px solid var(--line);padding-top:.8rem;font-size:.88rem">${l.deskripsi ? `<p>${l.deskripsi}</p>` : ''}${l.alamat ? `<p class="muted">${ic('pin')} ${l.alamat}</p>` : ''}${l.telepon ? `<p class="muted">Telp. ${l.telepon}</p>` : ''}${l.email ? `<p class="muted">${l.email}</p>` : ''}
      <p class="eyebrow">Skema</p><div class="row">${(cat && cat.items || []).filter(c => c.lsp_id === l.id && c.tipe === 'skema').map(c => `<button class="btn sm ghost" data-x="pubDetail" data-id="${c.id}">${c.judul}</button>`).join('') || '<span class="muted">—</span>'}</div></div>` : ''}
  </article>`).join('')}</div></div></section>`;
}
X.lspOpen = t => { S.lspOpen = S.lspOpen === Number(t.dataset.id) ? null : Number(t.dataset.id); render(); };
function pLms() {
  const cat = lazy('catalog', 'pub/catalog');
  if (!cat) return `<section class="block"><div class="wrap">${loading()}</div></section>`;
  const k = (cat.items || []).filter(c => c.tipe === 'pelatihan');
  return `<section class="block"><div class="wrap stack"><div><p class="eyebrow">Pelatihan (LMS)</p><h2>Kelas persiapan & bimtek</h2></div>
  <p class="alert warn">${ic('shield')}<span>Pelatihan tidak wajib untuk ikut uji kompetensi. Instruktur kelas tidak boleh menjadi asesor peserta yang ia ajar.</span></p>
  <div class="grid g3">${k.map(x => `<article class="card stack" style="gap:.7rem"><div style="height:120px;border-radius:16px;background:${GRAD[WARNA[x.id % WARNA.length]]};display:grid;place-items:center;color:#fff">${ic('book', 'ico" style="width:42px;height:42px')}</div>
    <h3>${x.judul}</h3><p class="muted" style="font-size:.85rem">${x.lsp_nama} · ${x.format} · ${x.kota}</p><p style="font-size:.86rem">${x.deskripsi}</p>
    <div class="spread" style="margin-top:auto"><span class="price">${x.harga ? fmtRp(x.harga) : 'Gratis'}</span><button class="btn sm purple" data-x="kelasIkut" data-id="${x.id}">Ikuti kelas</button></div></article>`).join('') || '<p class="muted">Belum ada kelas.</p>'}</div></div></section>`;
}
X.kelasIkut = async t => {
  if (!ME) { S.afterLogin = 'kelas'; go('login', {loginErr: 'Masuk atau daftar sebagai asesi untuk mengikuti kelas.'}); return; }
  if (!ME.permissions.includes('class.own')) { toast('Kelas hanya untuk akun asesi.'); return; }
  try { const r = await api('asesi/kelas/daftar', {listing_id: Number(t.dataset.id)}); toast(r.perlu_bayar ? 'Terdaftar. Selesaikan pembayaran untuk mulai.' : 'Terdaftar. Selamat belajar!'); S.inApp = true; await goApp(r.perlu_bayar ? 'bayar' : 'kelas'); }
  catch (x) { toast(x.message); }
};
function pVerif() {
  const q = S.cekQ;
  const r = q ? lazy('cek', 'pub/verify?q=' + encodeURIComponent(q)) : null;
  const ST = {berlaku: ['ok', 'Berlaku'], kedaluwarsa: ['warn', 'Kedaluwarsa'], dicabut: ['bad', 'Dicabut']};
  return `<section class="block"><div class="wrap stack" style="max-width:760px"><div><p class="eyebrow">Verifikasi publik</p><h2>Verifikasi sertifikat kompetensi</h2></div>
  <form class="card row" id="cekForm"><input id="vnum" class="mono" style="flex:1 1 260px" value="${esc(q || '')}" placeholder="Nomor sertifikat atau kode verifikasi" aria-label="Nomor sertifikat"><button class="btn green" type="submit">${ic('check')}Verifikasi</button></form>
  ${!q ? '<p class="muted">Contoh kode untuk dicoba: <span class="mono">TDN7K3P9QX</span></p>' : !r ? loading() : r.error ? `<div class="alert bad">${esc(r.error)}</div>` : r.found ? `<div class="card stack" style="gap:.8rem;border:2px solid var(--ok)"><div class="spread"><h3>${ic('check')} Sertifikat terdaftar</h3><span class="chip ${ST[r.status][0]}">${ST[r.status][1]}</span></div>
    ${infoGrid([['Nama', r.nama], ['Skema', r.skema], ['LSP penerbit', r.lsp], ['Nomor', `<span class="mono">${r.nomor}</span>`], ['Terbit', fmtTgl(r.terbit)], ['Berlaku sampai', fmtTgl(r.berlaku)]])}${r.nama_disamarkan ? '<p class="muted" style="font-size:.84rem">Nama disamarkan karena pencarian memakai nomor sertifikat. Masukkan <b>kode verifikasi</b> (10 karakter, tercetak di sertifikat) untuk melihat nama lengkap.</p>' : ''}</div>`
    : `<div class="card"><p class="alert bad">${ic('shield')}<span>Sertifikat dengan nomor/kode itu tidak ditemukan di PortalLSP. Pastikan penulisannya benar, atau hubungi LSP penerbit.</span></p></div>`}
  </div></section>`;
}
document.addEventListener('submit', e => {
  if (e.target.id === 'cekForm' || e.target.id === 'cekHome') {
    e.preventDefault();
    S.cekQ = (document.getElementById(e.target.id === 'cekForm' ? 'vnum' : 'cek-home').value || '').trim();
    delete PUB.cek; S.inApp = false; go('verif');
  }
});

/* ===================== Menu & halaman ===================== */
const PAGES = {
  skema: pAsesiSkema, jadwal: pAsesiJadwal, bayar: pAsesiBayar, sertifikat: pAsesiSertifikat, kelas: pAsesiKelas,
  kalender: pKalender, pra: pPra, asesmen: pAsesmen, pleno: pPleno, riwayat: pRiwayat, honor: pHonor,
  daftar: pPendaftaran, jadwalA: pJadwalA, asesmenA: pAsesmenA, plenoA: pPlenoA, master: pMaster, alumni: pAlumni, mutu: pMutu,
  keuangan: pKeuangan, crm: pCrm, laporan: pLaporan, setting: pSetting, support: pSupport,
  pemohon: pPemohon, jadwalT: pJadwalT, sarpras: pSarpras, chat: pChat, alumniT: pAlumniT,
  paket: pPaket, pustaka: pPustaka, audit: pAudit,
};
const _modulPlaceholder = modul;
modul = function (k) { return PAGES[k] ? PAGES[k]() : _modulPlaceholder(k); };
document.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('article[data-x]')) e.target.click(); });

/* ===================== CMS blog ===================== */
ICON.pen = '<path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25Zm17.7-10.2a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83Z"/>';
MENU.admin.splice(MENU.admin.findIndex(it => it[0] === 'etalase') + 1, 0, ['blog', 'Blog (CMS)', 'pen', 'blog.manage']);
MENU.super.splice(MENU.super.findIndex(it => it[0] === 'approval') + 1, 0, ['blog', 'Blog (CMS)', 'pen', 'blog.manage']);
PUB_NAV.splice(PUB_NAV.findIndex(it => it[0] === 'untuk'), 0, ['blog', 'Blog']);
const TITLE0 = document.title;
const BST = {draf: ['plain', 'Draf'], terbit: ['ok', 'Terbit'], terjadwal: ['info', 'Terjadwal'], diturunkan: ['bad', 'Diturunkan']};
const KAT_W = ['blue', 'green', 'purple', 'orange', 'pink', 'teal'];

/* Markdown sederhana. Masukan SUDAH di-escape (semua teks dari server di-escape oleh api(); pratinjau memakai esc()). */
function mdInline(s) {
  return s
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>')
    .replace(/(^|[\s(])\*([^*\s][^*]*)\*/g, '$1<i>$2</i>')
    .replace(/(^|[\s(])_([^_\s][^_]*)_/g, '$1<i>$2</i>')
    .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/(?!\/)[^\s)]*)\)/g, '<a href="$2" target="_blank" rel="noopener nofollow ugc">$1</a>');
}
function md(src) {
  const lines = String(src || '').replace(/\r/g, '').split('\n');
  let html = '', para = [], list = null;
  const flushP = () => { if (para.length) { html += `<p>${mdInline(para.join(' '))}</p>`; para = []; } };
  const flushL = () => { if (list) { html += `<${list.t}>${list.items.map(i => `<li>${mdInline(i)}</li>`).join('')}</${list.t}>`; list = null; } };
  for (const raw of lines) {
    const l = raw.trim();
    let m;
    if (!l) { flushP(); flushL(); continue; }
    if ((m = l.match(/^(#{1,3})\s+(.+)$/))) { flushP(); flushL(); const h = m[1].length === 3 ? 'h3' : 'h2'; html += `<${h}>${mdInline(m[2])}</${h}>`; continue; }
    if ((m = l.match(/^&gt;\s?(.*)$/))) { flushP(); flushL(); html += `<blockquote>${mdInline(m[1])}</blockquote>`; continue; }
    if (/^(-{3,}|\*{3,})$/.test(l)) { flushP(); flushL(); html += '<hr>'; continue; }
    if ((m = l.match(/^[-*]\s+(.+)$/))) { flushP(); if (!list || list.t !== 'ul') { flushL(); list = {t: 'ul', items: []}; } list.items.push(m[1]); continue; }
    if ((m = l.match(/^\d+[.)]\s+(.+)$/))) { flushP(); if (!list || list.t !== 'ol') { flushL(); list = {t: 'ol', items: []}; } list.items.push(m[1]); continue; }
    flushL(); para.push(l);
  }
  flushP(); flushL();
  return html;
}
const toLocalDT = s => s ? String(s).replace(' ', 'T').slice(0, 16) : '';

/* ---------- Halaman pengelola (Admin Platform, Admin LSP, Marketing) ---------- */
PAGE_LOAD.blog = () => g('blog');
function blogEditor(D, p) {
  const isNew = !p.id;
  const penerbit = isPlatform() && isNew
    ? fld('Penerbit', sel('lsp_id', [[0, 'PortalLSP (platform)']].concat(S.lsps.filter(l => l.status === 'aktif').map(l => [l.id, l.nama])), S.lspFilter || 0))
    : fld('Penerbit', `<input value="${isNew ? ctxName() : p.penerbit}" disabled>`);
  const tool = [['bold', '<b>B</b>', 'Tebal'], ['italic', '<i>I</i>', 'Miring'], ['h2', 'H2', 'Subjudul'], ['ul', '• Daftar', 'Daftar berpoin'], ['ol', '1. Daftar', 'Daftar bernomor'], ['quote', '❝ Kutipan', 'Kutipan'], ['link', 'Tautan', 'Tautan']];
  return `<div class="card stack" id="editor" style="border:2px solid var(--brand-b)">
    <div class="spread"><h3>${isNew ? 'Tulis artikel baru' : 'Ubah artikel'}</h3>${p.status_view ? `<span class="chip ${BST[p.status_view][0]}">${BST[p.status_view][1]}</span>` : ''}</div>
    ${p.status === 'diturunkan' ? `<p class="alert bad">${ic('shield')}<span>Diturunkan oleh Admin Platform: ${p.catatan_moderasi || '—'}. ${isPlatform() ? 'Pulihkan dari tabel di bawah bila sudah diperbaiki.' : 'Perbaiki isinya, lalu minta Admin Platform menayangkannya kembali lewat Tiket Support.'}</span></p>` : ''}
    ${form('blog', `${p.id ? hid('id', p.id) : ''}
      <div class="grid g2">
        ${fld('Judul', inp('judul', p.judul, 'required maxlength="150" placeholder="Mis. 5 Tips Lulus Uji Kompetensi"'), true)}
        ${penerbit}
        ${fld('Kategori', sel('kategori', D.kategori, p.kategori || D.kategori[0]))}
        ${fld('Tag <span class="muted">(pisahkan dengan koma, maks. 8)</span>', inp('tags', (p.tags || []).join(', '), 'maxlength="200" placeholder="sertifikasi, tips"'))}
        ${fld('Slug URL <span class="muted">(opsional, otomatis dari judul)</span>', inp('slug', p.slug || '', 'maxlength="90" placeholder="5-tips-lulus-uji-kompetensi"'))}
      </div>
      ${fld('Ringkasan <span class="muted">(tampil di daftar artikel, maks. 300 karakter)</span>', txa('ringkasan', p.ringkasan, 'rows="2" maxlength="300"'))}
      <div class="f"><span>Isi artikel</span>
        <div class="row" style="gap:.35rem;margin:.35rem 0">${tool.map(([k, l, t]) => `<button type="button" class="btn ghost sm" data-x="mdIns" data-m="${k}" title="${t}" aria-label="${t}">${l}</button>`).join('')}
          <button type="button" class="btn sm purple" data-x="blogPreview" style="margin-left:auto">${ic('search')}Pratinjau</button></div>
        ${txa('konten', p.konten, 'id="blog-konten" rows="16" maxlength="50000" required style="font-family:ui-monospace,Menlo,monospace;font-size:.88rem;line-height:1.55"')}
        <p class="muted" style="font-size:.78rem;margin-top:.3rem">Format: <span class="mono">## Subjudul</span>, <span class="mono">**tebal**</span>, <span class="mono">_miring_</span>, <span class="mono">- daftar</span>, <span class="mono">1. daftar</span>, <span class="mono">&gt; kutipan</span>, <span class="mono">[teks](https://alamat)</span>. Baris kosong memisahkan paragraf.</p>
        <div id="blog-preview" class="card prose" hidden style="margin-top:.6rem;background:var(--bg)"></div>
      </div>
      <div class="grid g2">
        ${fld('Status', sel('status', [['draf', 'Simpan sebagai draf'], ['terbit', 'Terbitkan']], p.status === 'draf' || !p.status ? 'draf' : 'terbit'))}
        ${fld('Waktu terbit <span class="muted">(kosongkan = sekarang; isi tanggal mendatang untuk menjadwalkan)</span>', `<input type="datetime-local" name="terbit_at" value="${toLocalDT(p.terbit_at)}">`)}
      </div>`, {after: 'blogSaved', cancel: true, submit: 'Simpan artikel', ok: 'Artikel disimpan.'})}
    ${p.id ? `<div class="stack" style="gap:.6rem;border-top:1px solid var(--line);padding-top:1rem"><h3>Gambar sampul</h3>
      ${p.cover ? `<img src="${p.cover}" alt="Gambar sampul" class="blog-cover" style="max-width:420px;border-radius:14px">` : '<p class="muted">Belum ada gambar sampul. Tanpa gambar, kartu artikel memakai warna kategori.</p>'}
      <div class="row"><input type="file" id="blog-cover-file" accept="image/png,image/jpeg" style="max-width:280px"><button class="btn sm" data-x="blogCover" data-id="${p.id}">${ic('send')}Unggah</button>${p.cover ? `<button class="btn sm ghost" data-x="blogCoverDel" data-id="${p.id}">Hapus gambar</button>` : ''}</div>
      <p class="muted" style="font-size:.78rem">JPG atau PNG, maks. 2 MB. Rasio 16:9 disarankan (mis. 1200×675 piksel).</p></div>`
    : '<p class="muted" style="font-size:.85rem">Simpan artikel dulu untuk menambahkan gambar sampul.</p>'}
  </div>`;
}
function pBlog() {
  const D = S.d.blog;
  const head = phead(isPlatform() ? 'Platform · ' + ctxName() : ctxName(), 'Blog (CMS)', `<button class="btn" data-edit="new">${ic('pen')}Tulis artikel</button>`);
  if (!D) return head + loading();
  const items = D.items, cnt = k => items.filter(p => p.status_view === k).length;
  const t = curTab('blog', 'semua');
  const list = t === 'semua' ? items : items.filter(p => p.status_view === t);
  const editing = S.edit === 'new' ? {} : (S.edit ? items.find(p => String(p.id) === S.edit) : null);
  return `${head}
  <div class="alert info">${ic('shield')}<span>${D.moderator ? 'Anda bisa menulis atas nama platform atau LSP mana pun, dan menurunkan artikel yang melanggar ketentuan.' : 'Artikel LSP Anda langsung tayang di halaman Blog publik. Admin Platform dapat menurunkan artikel yang melanggar ketentuan (mis. klaim "pasti lulus").'}</span></div>
  <div class="grid g4">${kpi('check', 'green', cnt('terbit'), 'Terbit')}${kpi('cal', 'blue', cnt('terjadwal'), 'Terjadwal')}${kpi('pen', 'orange', cnt('draf'), 'Draf')}${kpi('chart', 'purple', items.reduce((a, p) => a + p.dibaca, 0).toLocaleString('id-ID'), 'Total dibaca')}</div>
  ${editing ? blogEditor(D, editing) : ''}
  <div class="card">${tabs('blog', [['semua', 'Semua', items.length], ['terbit', 'Terbit', cnt('terbit')], ['terjadwal', 'Terjadwal', cnt('terjadwal')], ['draf', 'Draf', cnt('draf')], ['diturunkan', 'Diturunkan', cnt('diturunkan')]])}
    <div style="margin-top:.8rem">${table(['Artikel', ...(isPlatform() ? ['Penerbit'] : []), 'Status', 'Terbit', 'Dibaca', 'Aksi'], list.map(p => tr([
      `<b>${p.judul}</b><div class="muted" style="font-size:.76rem">${p.kategori} · /${p.slug}${p.penulis ? ' · ' + p.penulis : ''}</div>`,
      ...(isPlatform() ? [`<span class="chip ${p.lsp_id ? 'plain' : 'info'}">${p.penerbit}</span>`] : []),
      `<span class="chip ${BST[p.status_view][0]}">${BST[p.status_view][1]}</span>${p.status === 'diturunkan' && p.catatan_moderasi ? `<div class="muted" style="font-size:.74rem;max-width:220px;margin-top:.2rem">${p.catatan_moderasi}</div>` : ''}`,
      `<span class="num">${p.terbit_at ? fmtWaktu(p.terbit_at) : '—'}</span>`,
      `<span class="num">${p.dibaca.toLocaleString('id-ID')}</span>`,
      `<div class="row" style="gap:.35rem;flex-wrap:nowrap"><button class="btn sm" data-edit="${p.id}">Ubah</button>
        ${p.status_view === 'terbit' ? `<a class="btn sm ghost" href="./?artikel=${p.slug}" target="_blank" rel="noopener">Lihat</a>` : ''}
        ${D.moderator && p.status !== 'diturunkan' && p.status_view !== 'draf' ? `<button class="btn sm orange" data-x="blogTurunkan" data-id="${p.id}">Turunkan</button>` : ''}
        ${D.moderator && p.status === 'diturunkan' ? btnAct('blog/moderasi', {id: p.id, aksi: 'pulihkan'}, 'Pulihkan', 'sm green', 'data-ok="Artikel tayang kembali."') : ''}
        ${btnAct('blog/hapus', {id: p.id}, 'Hapus', 'sm red', 'data-confirm="Hapus artikel ini? Tindakan ini tidak bisa dibatalkan." data-ok="Artikel dihapus."')}</div>`,
    ])), t === 'semua' ? 'Belum ada artikel. Klik "Tulis artikel" untuk mulai.' : 'Tidak ada artikel dengan status ini.')}</div></div>`;
}
PAGES.blog = pBlog;
const clearBlogPub = () => { for (const k in PUB) if (k.startsWith('blog') || k.startsWith('artikel')) delete PUB[k]; };
AFTER.blogSaved = async r => {
  clearBlogPub();
  if (r.catatan) toast(r.catatan);
  await reloadPage();
  if (r.baru) { S.edit = String(r.id); render(); toast('Artikel disimpan. Tambahkan gambar sampul bila perlu.'); }
};
async function blogUpload(fd) {
  const res = await fetch('api.php?r=blog/cover', {method: 'POST', credentials: 'same-origin', headers: {'X-CSRF-Token': CSRF, Accept: 'application/json'}, body: fd});
  const j = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(j.error || 'Gagal memproses gambar.');
  clearBlogPub();
}
Object.assign(X, {
  mdIns(t) {
    const ta = document.getElementById('blog-konten'); if (!ta) return;
    const a = ta.selectionStart, b = ta.selectionEnd, v = ta.value, s = v.slice(a, b);
    const lineStart = v.lastIndexOf('\n', a - 1) + 1;
    const wrap = {bold: ['**', '**', 'teks tebal'], italic: ['_', '_', 'teks miring'], link: ['[', '](https://)', 'teks tautan']}[t.dataset.m];
    const prefix = {h2: '## ', ul: '- ', ol: '1. ', quote: '> '}[t.dataset.m];
    if (wrap) { const ins = wrap[0] + (s || wrap[2]) + wrap[1]; ta.setRangeText(ins, a, b, 'end'); }
    else if (prefix) ta.setRangeText(prefix, lineStart, lineStart, 'end');
    ta.focus();
  },
  blogPreview() {
    const ta = document.getElementById('blog-konten'), pv = document.getElementById('blog-preview');
    if (!ta || !pv) return;
    if (!pv.hidden) { pv.hidden = true; return; }
    pv.innerHTML = md(esc(ta.value)) || '<p class="muted">Belum ada isi.</p>'; pv.hidden = false;
  },
  async blogCover(t) {
    const input = document.getElementById('blog-cover-file');
    if (!input || !input.files[0]) { toast('Pilih gambar dulu.'); return; }
    if (input.files[0].size > 2097152) { toast('Ukuran gambar maksimal 2 MB.'); return; }
    const fd = new FormData(); fd.append('id', t.dataset.id); fd.append('file', input.files[0]);
    t.disabled = true;
    try { await blogUpload(fd); toast('Gambar sampul diunggah.'); await reloadPage(); }
    catch (x) { toast(x.message); t.disabled = false; }
  },
  async blogCoverDel(t) {
    if (!confirm('Hapus gambar sampul artikel ini?')) return;
    const fd = new FormData(); fd.append('id', t.dataset.id); fd.append('hapus', '1');
    try { await blogUpload(fd); toast('Gambar sampul dihapus.'); await reloadPage(); } catch (x) { toast(x.message); }
  },
  async blogTurunkan(t) {
    const c = prompt('Alasan menurunkan artikel (dikirim ke LSP penerbit):');
    if (c === null) return;
    try { await api('blog/moderasi', {id: Number(t.dataset.id), aksi: 'turunkan', catatan: c.trim()}); clearBlogPub(); toast('Artikel diturunkan dan LSP diberi tahu.'); await reloadPage(); }
    catch (x) { toast(x.message); }
  },
  /* Publik */
  blogOpen(t) { go('blog', {blogSlug: t.dataset.slug}); },
  blogBack() { go('blog', {blogSlug: null}); },
  blogKat(t) { S.blogKat = t.dataset.k || ''; S.blogPage = 1; render(); },
  blogPage(t) { S.blogPage = Number(t.dataset.p); render(); window.scrollTo(0, 0); },
  blogTag(t) { S.blogQ = unesc(t.dataset.tag); S.blogKat = ''; S.blogPage = 1; go('blog', {blogSlug: null}); },
  blogReset() { S.blogQ = ''; S.blogKat = ''; S.blogPage = 1; render(); },
  async blogShare(t) { const url = location.origin + location.pathname + '?artikel=' + t.dataset.slug; try { await navigator.clipboard.writeText(url); toast('Tautan artikel disalin.'); } catch (e) { prompt('Salin tautan ini:', url); } },
});

/* ---------- Halaman publik ---------- */
const katGrad = k => GRAD[KAT_W[Math.max(0, ['Berita', 'Tips Sertifikasi', 'Regulasi', 'Pengumuman', 'Kisah Alumni', 'Pelatihan'].indexOf(k)) % KAT_W.length]];
function blogCard(p) {
  return `<article class="card blog-card" data-x="blogOpen" data-slug="${p.slug}" tabindex="0" role="link" aria-label="${p.judul}">
    ${p.cover ? `<img src="${p.cover}" alt="" loading="lazy" class="blog-cover">` : `<div class="blog-cover ph" style="background:${katGrad(p.kategori)}">${ic('pen', 'ico big')}</div>`}
    <div class="bc"><span class="chip info">${p.kategori}</span><h3>${p.judul}</h3>${p.ringkasan ? `<p class="muted">${p.ringkasan}</p>` : ''}
      <div class="muted meta">${p.penerbit} · ${fmtTgl(p.terbit_at)} · ${p.menit_baca} menit baca</div></div></article>`;
}
function blogHome() {
  const b = lazy('blog-home', 'pub/blog');
  if (b && !b.error && !(b.items || []).length) return '';
  return `<section class="block" style="padding-top:0"><div class="wrap"><div class="sec-head"><div><p class="eyebrow">Blog</p><h2>Artikel & kabar terbaru</h2></div><button class="btn sm purple" data-go="blog">Semua artikel</button></div>
    <div class="grid g3">${b ? (b.items || []).slice(0, 3).map(blogCard).join('') : '<p class="muted">Memuat…</p>'}</div></div></section>`;
}
function pBlogPub() {
  if (S.blogSlug) return pArtikel();
  const qs = new URLSearchParams();
  if (S.blogQ) qs.set('q', S.blogQ);
  if (S.blogKat) qs.set('kategori', unesc(S.blogKat));
  if ((S.blogPage || 1) > 1) qs.set('page', S.blogPage);
  const key = 'blog?' + qs, b = lazy(key, 'pub/blog' + (String(qs) ? '?' + qs : ''));
  const items = b && b.items || [], pages = b && b.total ? Math.ceil(b.total / b.per_page) : 1, page = b ? b.page : 1;
  const filtered = S.blogQ || S.blogKat;
  const first = !filtered && page === 1 ? items[0] : null;
  const rest = first ? items.slice(1) : items;
  return `<section class="block"><div class="wrap stack" style="gap:1.4rem">
    <div><p class="eyebrow">Blog PortalLSP</p><h2>Wawasan sertifikasi kompetensi</h2><p class="muted" style="margin-top:.3rem">Tips persiapan uji, regulasi BNSP, pengumuman jadwal, dan kisah alumni dari LSP di PortalLSP.</p></div>
    <form class="search" id="blogSearch" role="search" style="max-width:560px"><input id="blog-q" value="${esc(S.blogQ || '')}" placeholder="Cari artikel, mis. tips uji, barista" aria-label="Cari artikel"><button class="btn" type="submit">${ic('search')}Cari</button></form>
    <div class="tabs"><button class="fchip ${!S.blogKat ? 'on' : ''}" data-x="blogKat" data-k="">Semua</button>${(b && b.kategori || []).filter(k => k.n).map(k => `<button class="fchip ${S.blogKat === k.nama ? 'on' : ''}" data-x="blogKat" data-k="${k.nama}">${k.nama} <span class="num">(${k.n})</span></button>`).join('')}</div>
    ${pubErr(b)}
    ${filtered && b ? `<p class="muted">${b.total} artikel${S.blogQ ? ` untuk "<b>${esc(S.blogQ)}</b>"` : ''}${S.blogKat ? ` di kategori <b>${S.blogKat}</b>` : ''}. <button class="btn sm ghost" data-x="blogReset">Hapus filter</button></p>` : ''}
    ${!b ? '<p class="muted">Memuat…</p>' : !items.length ? `<div class="card empty"><h3>Belum ada artikel</h3><p class="muted">${filtered ? 'Coba kata kunci atau kategori lain.' : 'Artikel akan tampil di sini setelah diterbitkan.'}</p></div>` : ''}
    ${first ? `<article class="card blog-card blog-feat" data-x="blogOpen" data-slug="${first.slug}" tabindex="0" role="link" aria-label="${first.judul}">
      ${first.cover ? `<img src="${first.cover}" alt="" class="blog-cover">` : `<div class="blog-cover ph" style="background:${katGrad(first.kategori)}">${ic('pen', 'ico big')}</div>`}
      <div class="bc"><span class="chip info">${first.kategori}</span><h2>${first.judul}</h2>${first.ringkasan ? `<p class="muted" style="font-size:1rem">${first.ringkasan}</p>` : ''}
        <div class="muted meta">${first.penerbit} · ${first.penulis} · ${fmtTgl(first.terbit_at)} · ${first.menit_baca} menit baca</div><span class="btn sm purple" style="align-self:flex-start;margin-top:.4rem">Baca artikel</span></div></article>` : ''}
    ${rest.length ? `<div class="grid g3">${rest.map(blogCard).join('')}</div>` : ''}
    ${pages > 1 ? `<div class="row" style="justify-content:center">${page > 1 ? `<button class="btn ghost sm" data-x="blogPage" data-p="${page - 1}">← Sebelumnya</button>` : ''}<span class="muted">Halaman ${page} dari ${pages}</span>${page < pages ? `<button class="btn ghost sm" data-x="blogPage" data-p="${page + 1}">Berikutnya →</button>` : ''}</div>` : ''}
  </div></section>`;
}
function pArtikel() {
  const d = lazy('artikel:' + S.blogSlug, 'pub/blog/post?slug=' + encodeURIComponent(S.blogSlug));
  const back = `<button class="btn ghost sm" data-x="blogBack">← Semua artikel</button>`;
  if (!d) return `<section class="block"><div class="wrap">${back}<p class="muted" style="margin-top:1rem">Memuat…</p></div></section>`;
  if (d.error) return `<section class="block"><div class="wrap stack">${back}<div class="card empty"><h3>Artikel tidak ditemukan</h3><p class="muted">${esc(d.error)}</p></div></div></section>`;
  const p = d.post;
  document.title = unesc(p.judul) + ' · ' + TITLE0;
  const shareText = encodeURIComponent(unesc(p.judul) + ' ' + location.origin + location.pathname + '?artikel=' + p.slug);
  return `<section class="block"><div class="wrap"><article class="blog-article stack" style="gap:1.1rem">
    <div>${back}</div>
    <div class="stack" style="gap:.6rem"><div class="row"><span class="chip info">${p.kategori}</span><span class="chip plain">${p.penerbit}</span></div>
      <h1 class="blog-title">${p.judul}</h1>
      ${p.ringkasan ? `<p class="lead muted">${p.ringkasan}</p>` : ''}
      <p class="muted" style="font-size:.88rem">Oleh <b>${p.penulis}</b> · ${fmtTgl(p.terbit_at)} · ${p.menit_baca} menit baca · ${p.dibaca.toLocaleString('id-ID')} kali dibaca</p></div>
    ${p.cover ? `<img src="${p.cover}" alt="" class="blog-cover" style="border-radius:var(--r-lg)">` : ''}
    <div class="prose">${md(p.konten)}</div>
    ${p.tags.length ? `<div class="row" style="gap:.4rem">${p.tags.map(tg => `<button class="fchip" data-x="blogTag" data-tag="${tg}">#${tg}</button>`).join('')}</div>` : ''}
    <div class="row" style="border-top:1px solid var(--line);padding-top:1rem"><span class="muted">Bagikan:</span><button class="btn sm ghost" data-x="blogShare" data-slug="${p.slug}">${ic('send')}Salin tautan</button><a class="btn sm green" href="https://wa.me/?text=${shareText}" target="_blank" rel="noopener">${ic('chat')}WhatsApp</a></div>
    <div class="cta" style="padding:1.4rem"><div><h3 style="color:#fff">Siap mengikuti uji kompetensi?</h3><p style="opacity:.9;margin-top:.3rem">Bandingkan skema dari berbagai LSP dan daftar online.</p></div><button class="btn white" data-go="cari">${ic('search')}Cari skema</button></div>
  </article>
  ${d.terkait.length ? `<div class="stack" style="gap:.8rem;margin-top:2rem"><h2>Artikel terkait</h2><div class="grid g3">${d.terkait.map(blogCard).join('')}</div></div>` : ''}
  </div></section>`;
}
document.addEventListener('submit', e => {
  if (e.target.id !== 'blogSearch') return;
  e.preventDefault();
  S.blogQ = (document.getElementById('blog-q') || {}).value.trim(); S.blogPage = 1; render();
});

/* Navigasi publik: tambahkan halaman Blog dan tautan langsung ?artikel=slug. */
const _go = go;
go = function (page, extra = {}) { if (page === 'blog' && !('blogSlug' in extra)) extra = Object.assign({}, extra, {blogSlug: null}); _go(page, extra); };
const PUB_PAGES = () => ({beranda: pBeranda, cari: pCari, detail: pDetail, jadwal: pJadwal, lsp: pLsp, lms: pLms, verif: pVerif, untuk: pUntuk, login: pLogin, daftar: pDaftar, blog: pBlogPub});
publik = function () {
  document.title = TITLE0;
  return topbar() + (PUB_PAGES()[S.page] || pBeranda)() + `<footer><div class="wrap spread"><span class="row">${ic('shield')}<b>PortalLSP</b><span class="muted">Purwarupa UI/UX · nama produk sementara</span></span><span class="row" style="gap:1rem"><button class="btn ghost sm" data-go="blog">Blog</button><span class="muted">Terdaftar PSE · Server di Indonesia</span></span></div></footer>`;
};
(function () {
  const a = new URLSearchParams(location.search).get('artikel');
  if (a) { S.page = 'blog'; S.blogSlug = a.slice(0, 190); S.stayPublic = true; history.replaceState(null, '', location.pathname); }
})();
const _enterApp = enterApp;
enterApp = async function () {
  // Pengunjung yang sudah login tetap di artikel bila membuka tautan ?artikel=… (sekali, saat halaman dimuat).
  if (S.stayPublic && S.page === 'blog') { S.stayPublic = false; render(); return; }
  S.stayPublic = false; document.title = TITLE0;
  return _enterApp();
};

/* Laci menu (HP/tablet): tutup dengan Escape. */
document.addEventListener('keydown', e => { if (e.key === 'Escape' && S.drawer) { S.drawer = false; render(); const b = document.getElementById('appMenuBtn'); if (b) b.focus(); } });
