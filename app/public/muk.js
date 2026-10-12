'use strict';
/* PortalLSP: formulir asesmen paperless (Buku Kerja 333-25) — renderer generik dari definisi server,
   berkas asesmen per asesi, laporan per jadwal, perangkat asesmen (MUK), validasi, dan banding.
   Semua teks dari server sudah di-escape oleh api(); isian pengguna dikirim mentah dan divalidasi server. */

/* ===================== Gaya & cetak ===================== */
(function () {
  const st = document.createElement('style');
  st.textContent = `
  .muk-doc{display:grid;grid-template-columns:minmax(0,1fr);gap:1rem;min-width:0;max-width:100%}
  .muk-head{width:100%;border-collapse:collapse;font-size:.9rem;table-layout:fixed}
  .muk-head td{border:1px solid var(--line);padding:.45rem .6rem;vertical-align:top;overflow-wrap:anywhere}
  .muk-head td:first-child{width:34%;font-weight:700;background:var(--bg)}
  .muk-sec{border:1px solid var(--line);border-radius:14px;padding:1rem;display:grid;grid-template-columns:minmax(0,1fr);gap:.8rem;min-width:0}
  .muk-sec .f{min-width:0}
  .muk-sec>h3{font-size:1rem}
  .muk-info{background:var(--bg);border-radius:10px;padding:.7rem .9rem;font-size:.86rem;display:grid;gap:.3rem}
  .muk-opts{display:flex;flex-wrap:wrap;gap:.3rem .9rem}
  .muk-opts label{display:flex;gap:.35rem;align-items:flex-start;font-weight:600;font-size:.88rem}
  .muk-opts input{width:auto;margin-top:.15rem}
  .muk-grid td{vertical-align:top}
  .muk-grid .grp td{background:var(--bg);font-weight:800;font-size:.85rem}
  .muk-kuk{margin:.3rem 0 0;padding-left:1.1rem;font-size:.8rem;color:var(--muted)}
  .muk-ro{font-weight:700}
  .muk-ttd{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.8rem}
  .muk-ttd>div{border:1px dashed var(--line);border-radius:12px;padding:.7rem .8rem;font-size:.86rem}
  .muk-check{display:grid;gap:.5rem}
  .muk-row{display:flex;gap:.8rem;align-items:center;justify-content:space-between;border:1px solid var(--line);border-radius:12px;padding:.7rem .9rem;flex-wrap:wrap}
  @media print{
    body *{visibility:hidden}
    .muk-doc,.muk-doc *{visibility:visible}
    .muk-doc{position:absolute;left:0;top:0;width:100%}
    .muk-doc .no-print,.muk-doc button{display:none!important}
  }`;
  document.head.appendChild(st);
})();

/* ===================== Utilitas ===================== */
const MK_ST = {belum: ['plain', 'Belum diisi'], baru: ['plain', 'Belum diisi'], draf: ['warn', 'Menunggu isian / tanda tangan'], final: ['ok', 'Lengkap & ditandatangani'],
  valid: ['ok', 'Tervalidasi'], menunggu_validasi: ['warn', 'Menunggu validasi'], diajukan: ['warn', 'Diajukan'], diputus: ['ok', 'Diputus']};
const mkChip = s => `<span class="chip ${(MK_ST[s] || ['plain'])[0]}">${(MK_ST[s] || [0, s])[1]}</span>`;
const mkOpts = (t, o) => t === 'yt' ? [['Ya', 'Ya'], ['Tidak', 'Tidak']] : t === 'kbk' ? [['K', 'K'], ['BK', 'BK']]
  : !o ? [] : Array.isArray(o) ? o.map(v => [v, v]) : Object.entries(o);
const mkWaktu = s => s ? new Date(String(s).replace(' ', 'T')).toLocaleString('id-ID', {day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'}) : '—';
const MKC = {};
/** Ambil data sekali (cache per kunci) lalu render ulang saat tiba. */
function mkLazy(key, url) {
  if (!(key in MKC)) {
    MKC[key] = null;
    api(url).then(v => { MKC[key] = v; render(); }).catch(e => { MKC[key] = {error: e.message}; render(); });
  }
  return MKC[key];
}
const mkDrop = prefix => { for (const k in MKC) if (k.startsWith(prefix)) delete MKC[k]; };

/* ===================== Renderer formulir ===================== */
function mkAttrs(path) { return Object.entries(path).filter(([, v]) => v !== undefined).map(([k, v]) => `data-${k}="${v}"`).join(' '); }
function mkInput(c, val, edit, path, rowOpts) {
  const t = c.t, opts = mkOpts(t, c.o === '@row' ? rowOpts : c.o);
  const name = 'mk-' + Object.values(path).filter(v => v !== undefined).join('-');
  if (!edit) {
    if (val === null || val === undefined || val === '' || (Array.isArray(val) && !val.length)) return '<span class="muted">—</span>';
    if (t === 'multi') { const m = Object.fromEntries(opts); return `<span class="muk-ro">${val.map(v => m[v] ?? v).join('; ')}</span>`; }
    if (t === 'pilih' || t === 'yt' || t === 'kbk') { const m = Object.fromEntries(opts); const lbl = m[val] ?? val;
      return `<span class="chip ${val === 'Ya' || val === 'K' || val === 'MS' || val === 'dapat' || val === 'diterima' ? 'ok' : val === 'Tidak' || val === 'BK' || val === 'TMS' || val === 'TA' || val === 'tidak' ? 'bad' : 'info'}">${lbl}</span>`; }
    if (t === 'panjang') return `<div style="white-space:pre-wrap">${val}</div>`;
    return `<span>${t === 'tanggal' ? fmtHari(val) : val}</span>`;
  }
  const a = mkAttrs(path);
  if (t === 'teks') return `<input ${a} value="${val ?? ''}" maxlength="500" aria-label="${c.l || ''}">`;
  if (t === 'panjang') return `<textarea ${a} rows="3" maxlength="4000" aria-label="${c.l || ''}">${val ?? ''}</textarea>`;
  if (t === 'tanggal') return `<input type="date" ${a} value="${val ?? ''}" aria-label="${c.l || ''}">`;
  if (t === 'multi') return `<div class="muk-opts">${opts.map(([v, l]) => `<label><input type="checkbox" ${a} value="${v}" ${(val || []).includes(v) ? 'checked' : ''}>${l}</label>`).join('')}</div>`;
  return `<div class="muk-opts" role="radiogroup" aria-label="${c.l || ''}">${opts.map(([v, l]) => `<label><input type="radio" name="${name}" ${a} value="${v}" ${String(val) === String(v) ? 'checked' : ''}>${l}</label>`).join('')}</div>`;
}
const mkOwner = (c, f, def) => c.oleh || (f && f.oleh) || def.pengisi;
function mkRowHead(f) {
  return {unit: 'Unit Kompetensi', elemen: 'Elemen dan Kriteria Unjuk Kerja', kuk: 'Kriteria Unjuk Kerja', item: 'Butir', asesi: 'Nama Asesi', persyaratan: 'Bukti Persyaratan Dasar'}[f.baris] || (f.l || 'Komponen');
}
function mkGrid(f, F, edit) {
  const def = F.def, data = F.data || {}, cur = data[f.k];
  const cols = f.kol || [];
  if (f.baris === 'bebas') {
    const rows = Array.isArray(cur) ? cur.slice() : [];
    const can = cols.some(c => edit(mkOwner(c, f, def)));
    const extra = can ? Math.max(1, (S.mkExtra && S.mkExtra[f.k]) || (rows.length ? 0 : (f.min || 1))) : 0;
    for (let i = 0; i < extra; i++) rows.push({});
    return `${table(['No.'].concat(cols.map(c => c.l)), rows.map((r, i) => tr([String(i + 1)].concat(cols.map(c => mkInput(c, r[c.k], edit(mkOwner(c, f, def)), {f: f.k, i, c: c.k}))))), 'Belum ada baris.')}
      ${can ? `<button type="button" class="btn sm ghost no-print" data-x="mkAddRow" data-k="${f.k}">+ Tambah baris</button>` : ''}`;
  }
  const rows = f.rows || [];
  let lastGrp = '';
  const out = [];
  rows.forEach(r => {
    const grp = f.baris === 'kuk' ? r.unit + ' — Elemen ' + r.elemen : '';
    if (grp && grp !== lastGrp) { out.push(`<tr class="grp"><td colspan="${cols.length + 1}">${grp}</td></tr>`); lastGrp = grp; }
    let label = r.label;
    if (f.baris === 'elemen') label = `<b>${r.unit}</b><div>Elemen ${r.elemen}</div><ul class="muk-kuk">${(r.kuk || []).map(k => `<li>${k}</li>`).join('')}</ul>`;
    if (f.baris === 'item') label = `<div style="white-space:pre-wrap">${r.label}</div>${Object.entries(r.item || {}).filter(([k]) => !['pertanyaan', 'skenario', 'kriteria'].includes(k)).map(([k, x]) => `<div class="muted" style="font-size:.82rem;white-space:pre-wrap"><b>${x.l}</b> ${x.v}</div>`).join('')}`;
    const rv = (cur && cur[r.key]) || {};
    out.push(`<tr><td>${label}</td>${cols.map(c => `<td>${mkInput(c, rv[c.k], edit(mkOwner(c, f, def)), {f: f.k, r: r.key, c: c.k}, r.o)}</td>`).join('')}</tr>`);
  });
  return `<div class="table-wrap"><table class="muk-grid"><thead><tr><th>${mkRowHead(f)}</th>${cols.map(c => `<th>${c.l}</th>`).join('')}</tr></thead><tbody>${out.join('') || `<tr><td colspan="${cols.length + 1}" class="muted">Tidak ada baris.</td></tr>`}</tbody></table></div>`;
}
function mkHeader(h) {
  const rows = [['Skema Sertifikasi (KKNI/Okupasi/Klaster) — Judul', h.skema_judul], ['Skema Sertifikasi — Nomor', h.skema_nomor], h.lsp ? ['LSP', h.lsp] : null,
    h.tuk ? ['TUK (Sewaktu/Tempat Kerja/Mandiri)', h.tuk] : null, h.asesor ? ['Nama Asesor', h.asesor + (h.no_reg ? ` · No. Reg ${h.no_reg}` : '')] : null,
    h.asesi ? ['Nama Asesi', h.asesi] : null, h.tanggal ? ['Tanggal', fmtHari(h.tanggal) + (h.waktu ? ' · ' + h.waktu : '')] : null].filter(Boolean);
  return `<table class="muk-head no-stack"><tbody>${rows.map(([k, v]) => `<tr><td>${k}</td><td>${v || '—'}</td></tr>`).join('')}</tbody></table>`;
}
/** Render formulir. opts: {embed: tanpa tombol, readonly, actions: html tambahan, extraTtd} */
function mukRender(F, opts = {}) {
  const def = F.def, data = F.data || {};
  const pihak = opts.readonly ? [] : (F.pihak || []);
  const edit = owner => pihak.includes(owner);
  const pribadi = F.pribadi && F.pribadi.length ? `<table class="muk-head no-stack"><tbody>${F.pribadi.map(([k, v]) => `<tr><td>${k}</td><td>${v}</td></tr>`).join('')}</tbody></table><p class="muted" style="font-size:.78rem">Data pribadi diambil dari profil Anda; ubah di menu Profil bila ada yang keliru.</p>` : '';
  const secs = def.bagian.map((b, bi) => `<div class="muk-sec"><h3>${b.j}</h3>${bi === 0 ? pribadi : ''}${b.f.map(f => {
    if (f.t === 'info') return `<div class="muk-info">${f.l ? `<b>${f.l}</b>` : ''}${(f.v || []).map(x => `<div>${x}</div>`).join('')}</div>`;
    if (f.t === 'grid') return `<div class="stack" style="gap:.5rem">${f.l ? `<b>${f.l}</b>` : ''}${mkGrid(f, F, edit)}</div>`;
    return `<label class="f"><span>${f.l}${f.wajib && edit(mkOwner(f, null, def)) ? ' <span class="muted">*</span>' : ''}</span>${mkInput(f, data[f.k], edit(mkOwner(f, null, def)), {f: f.k})}</label>`;
  }).join('')}</div>`).join('');
  const ttd = Object.entries(def.ttd).map(([p, l]) => {
    const t = (F.ttd || {})[p];
    return `<div><p class="eyebrow">${l}</p>${t ? `<b>${t.nama}</b>${t.no_reg ? `<div class="muted">No. Reg ${t.no_reg}</div>` : ''}<div class="muted" style="font-size:.78rem">Ditandatangani elektronik ${mkWaktu(t.at)}${t.hash ? ` · sidik ${t.hash}` : ''}</div>${t.utuh === true ? '<span class="chip ok" style="margin-top:.25rem">Isi utuh sejak ditandatangani</span>' : t.utuh === false ? '<span class="chip bad" style="margin-top:.25rem">Isi berubah setelah ditandatangani</span>' : ''}${t.demo ? ' <span class="chip plain" style="margin-top:.25rem" title="Tanda tangan ini dibuat saat data demo disiapkan">Data demo</span>' : ''}` : '<span class="chip plain">Belum ditandatangani</span>'}</div>`;
  }).join('');
  const act = opts.embed ? '' : (opts.actions || (pihak.length && !F.catatan ? `<div class="card stack no-print" style="gap:.6rem;border:2px solid var(--brand-b)">
      <label class="toggle"><input type="checkbox" id="mk-setuju"><span>Saya menyatakan isian saya pada formulir ini benar dan menandatanganinya secara elektronik sebagai <b>${pihak.map(p => def.ttd[p]).join(', ')}</b>. Setelah ditandatangani, isian saya tidak dapat diubah.</span></label>
      <div class="row" style="justify-content:flex-end"><button class="btn ghost" data-x="mkSave">Simpan draf</button><button class="btn green" data-x="mkSign">${ic('check')}Tandatangani</button></div></div>` : ''));
  return `<div class="muk-doc" id="mkDoc">
    <div class="spread"><div><p class="eyebrow">${def.tahap || 'Perangkat asesmen'}</p><h2 style="font-size:1.25rem">${def.judul}</h2></div>
      <div class="row no-print">${F.status ? mkChip(F.status) : ''}${opts.embed ? '' : `<button class="btn sm ghost" data-x="mkPrint">${ic('doc')}Cetak / PDF</button>`}</div></div>
    ${F.header ? mkHeader(F.header) : ''}
    ${F.catatan ? `<div class="alert info no-print">${ic('bell')}<span>${F.catatan}</span></div>` : ''}
    ${secs}
    <div class="muk-sec"><h3>Tanda tangan elektronik</h3><div class="muk-ttd">${ttd}</div>${opts.extraTtd || ''}</div>
    ${act}</div>`;
}
/** Kumpulkan isian dari DOM menjadi objek data (hanya kolom yang dapat diedit yang punya input). */
function mukCollect(root) {
  const out = {}, multi = new Set();
  const set = (d, v) => {
    if (d.r !== undefined) { out[d.f] = out[d.f] || {}; out[d.f][d.r] = out[d.f][d.r] || {}; out[d.f][d.r][d.c] = v; }
    else if (d.i !== undefined) { out[d.f] = out[d.f] || []; out[d.f][d.i] = out[d.f][d.i] || {}; out[d.f][d.i][d.c] = v; }
    else out[d.f] = v;
  };
  const get = d => d.r !== undefined ? ((out[d.f] || {})[d.r] || {})[d.c] : d.i !== undefined ? ((out[d.f] || [])[d.i] || {})[d.c] : out[d.f];
  (root || document).querySelectorAll('[data-f]').forEach(el => {
    const d = el.dataset;
    if (el.type === 'checkbox') {
      const key = [d.f, d.r, d.i, d.c].join('|');
      if (!multi.has(key)) { multi.add(key); set(d, []); }
      if (el.checked) get(d).push(el.value);
    } else if (el.type === 'radio') {
      if (get(d) === undefined) set(d, null);
      if (el.checked) set(d, el.value);
    } else set(d, el.value.trim() === '' ? null : el.value);
  });
  for (const k in out) if (Array.isArray(out[k])) out[k] = out[k].filter(Boolean);
  return out;
}

/* ===================== Aksi formulir ===================== */
Object.assign(X, {
  mkPrint() { window.print(); },
  mkAddRow(t) { S.mkExtra = S.mkExtra || {}; const k = t.dataset.k; const cur = mukCollect($('#mkDoc')); if (S.mkF) S.mkF.data = Object.assign({}, S.mkF.data, cur); S.mkExtra[k] = 1; render(); },
  async mkSave(t) { await mkWrite(t, false); },
  async mkSign(t) {
    if (!$('#mk-setuju') || !$('#mk-setuju').checked) { toast('Centang pernyataan tanda tangan elektronik dulu.'); return; }
    await mkWrite(t, true);
  },
  async mkOpen(t) { S.berkas = Number(t.dataset.pid); S.mkF = null; S.mkExtra = {}; mkDrop('berkas:'); render(); window.scrollTo(0, 0); },
  async mkForm(t) { await mkLoadForm({permohonan_id: S.berkas}, t.dataset.kode); },
  mkBack() { S.mkF = null; S.mkExtra = {}; if (S.mkJ && S.mkJ.form) { S.mkJ.form = null; } mkDrop('berkas:'); mkDrop('jadwal:'); render(); window.scrollTo(0, 0); },
  mkClose() { S.berkas = null; S.mkF = null; S.mkJ = null; S.mkExtra = {}; reloadPage(); },
});
async function mkLoadForm(ctx, kode, id) {
  const qs = Object.entries(ctx).map(([k, v]) => k + '=' + v).join('&') + '&kode=' + encodeURIComponent(kode) + (id ? '&id=' + id : '');
  try { S.mkF = await api('muk/formulir?' + qs); S.mkF.ctx = ctx; S.mkExtra = {}; render(); window.scrollTo(0, 0); }
  catch (x) { toast(x.message); }
}
async function mkWrite(t, sign) {
  const F = S.mkF; if (!F) return;
  const body = Object.assign({}, F.ctx, {kode: F.kode, id: F.id || undefined, baru: F.baru && !F.id ? true : undefined, data: mukCollect($('#mkDoc'))});
  if (sign) { body.setuju = true; if (F.pihak && F.pihak.length) body.sebagai = F.pihak[0]; }
  t.disabled = true;
  try {
    const r = await api(sign ? 'muk/formulir/ttd' : 'muk/formulir/simpan', body);
    toast(sign ? 'Formulir ditandatangani.' : 'Draf disimpan.');
    await mkLoadForm(F.ctx, F.kode, r.id);
    mkDrop('berkas:'); mkDrop('jadwal:');
  } catch (x) { toast(x.message); t.disabled = false; }
}

/* ===================== Berkas asesmen per permohonan ===================== */
function pBerkas() {
  if (S.mkF) return `<div class="row no-print"><button class="btn ghost sm" data-x="mkBack">← Kembali ke berkas</button></div>${mukRender(S.mkF)}`;
  const B = mkLazy('berkas:' + S.berkas, 'muk/berkas?permohonan_id=' + S.berkas);
  if (!B) return loading();
  if (B.error) return `<div class="card"><p class="alert bad">${esc(B.error)}</p><button class="btn ghost" data-x="mkClose">Kembali</button></div>`;
  const p = B.permohonan;
  const pot = B.potensi ? `Potensi asesi ${B.potensi}` : 'Potensi asesi belum ditentukan (FR.AK.07)';
  return `${phead('Berkas asesmen paperless · Buku Kerja 333-25', p.asesi_nama + ' — ' + p.skema_nama, `<button class="btn ghost" data-x="mkClose">← Kembali</button>`)}
  <div class="card">${infoGrid([['Status', `<span class="chip info">${p.status_label}</span>`], ['Jadwal uji', fmtHari(p.tanggal)], ['Asesor', p.asesor_nama || '—'], ['Instrumen (FR.MAPA.02)', (B.instrumen.length ? B.instrumen.join(', ') : '—') + `<div class="muted" style="font-size:.8rem">${pot}</div>`]])}</div>
  <div class="card stack"><h3>Daftar periksa kelengkapan dokumen</h3><div class="muk-check">${B.items.map(it => `<div class="muk-row">
      <div style="min-width:0;flex:1"><b>${it.judul}</b><div class="muted" style="font-size:.8rem">${it.tahap} · ${it.ttd.map(x => `${x.label}: ${x.sudah ? '✔' : '—'}`).join(' · ')}</div>${it.catatan && it.pihak.length ? `<div class="muted" style="font-size:.78rem">${it.catatan}</div>` : ''}</div>
      <div class="row">${mkChip(it.status)}${it.pihak.length && !it.catatan ? '<span class="chip warn">Perlu tindakan Anda</span>' : ''}<button class="btn sm ${it.pihak.length && !it.catatan ? 'green' : 'ghost'}" data-x="mkForm" data-kode="${it.kode}">${it.pihak.length && !it.catatan ? 'Isi / tandatangani' : 'Lihat'}</button></div></div>`).join('')}</div></div>
  ${B.banding && B.banding.length ? `<div class="card"><h3>Banding</h3>${table(['Diajukan', 'Alasan', 'Status', 'Hasil'], B.banding.map(b => tr([fmtTgl(b.created_at), b.alasan, mkChip(b.status), b.hasil ? `<b>${b.hasil}</b><div class="muted" style="font-size:.8rem">${b.catatan || ''}</div>` : '—'])))}</div>` : ''}`;
}
/* Halaman yang membuka berkas asesi di tempat (tanpa pindah menu). */
for (const k of ['jadwal', 'pra', 'asesmen', 'pleno', 'daftar', 'asesmenA', 'plenoA', 'banding', 'pemohon']) {
  const orig = PAGES[k];
  PAGES[k] = () => S.berkas ? pBerkas() : orig ? orig() : '';
}
const _goAppMuk = goApp;
goApp = async function (p) { S.berkas = null; S.mkF = null; S.mkJ = null; S.mkP = null; S.mkExtra = {}; return _goAppMuk(p); };
const btnBerkas = (pid, label = 'Berkas FR', cls = 'sm purple') => `<button class="btn ${cls}" data-x="mkOpen" data-pid="${pid}">${ic('doc')}${label}</button>`;

/* ===================== Asesi: wizard FR.APL.01/02 ===================== */
function apCapture() {
  const el = document.getElementById('apMk');
  if (!el || !S.ap) return;
  const v = mukCollect(el);
  if (S.ap.step === 1) S.ap.apl01 = Object.assign({}, S.ap.apl01 || {}, v);
  if (S.ap.step === 2) S.ap.apl02 = Object.assign({}, S.ap.apl02 || {}, v);
}
/** Cek kelengkapan langkah wizard sebelum lanjut (server tetap memvalidasi ulang). */
function apCheck(step) {
  const mk = S.ap.mk;
  if (!mk || mk === 'loading') return 'Tunggu formulir dimuat.';
  if (mk.error) return mk.error;
  const fld = (kode, k) => mk[kode].def.bagian.flatMap(b => b.f).find(f => f.k === k);
  if (step === 1) {
    const a = S.ap.apl01 || {};
    if (!a.pendidikan) return 'Isi kualifikasi pendidikan (FR.APL.01).';
    if (!a.tujuan) return 'Pilih tujuan asesmen (FR.APL.01).';
    const rows = (fld('APL.01', 'dasar') || {}).rows || [];
    if (rows.some(r => !((a.dasar || {})[r.key] || {}).bukti)) return 'Tuliskan bukti untuk setiap persyaratan dasar (FR.APL.01 bagian 3).';
  }
  if (step === 2) {
    if (!mk.siap) return 'Skema ini belum memiliki elemen dan KUK.';
    const rows = (fld('APL.02', 'elemen') || {}).rows || [];
    const d = (S.ap.apl02 || {}).elemen || {};
    if (rows.some(r => !(d[r.key] || {}).kbk || !(d[r.key] || {}).bukti)) return 'Isi K/BK dan bukti yang relevan untuk setiap elemen (FR.APL.02).';
  }
  return '';
}

/* ===================== Admin LSP: verifikasi FR.APL.01 ===================== */
function aplVerif(p) {
  const F = mkLazy('apl01:' + p.id, 'muk/formulir?permohonan_id=' + p.id + '&kode=APL.01');
  const F2 = mkLazy('apl02:' + p.id, 'muk/formulir?permohonan_id=' + p.id + '&kode=APL.02');
  if (!F || !F2) return loading();
  if (F.error) return `<p class="alert bad">${esc(F.error)}</p>`;
  return `${p.status === 'diajukan' ? `<div class="alert info">${ic('doc')}<span>Verifikasi setiap bukti FR.APL.01 bagian 3 (Memenuhi Syarat / Tidak Memenuhi Syarat / Tidak Ada). Pemohon hanya dapat diterima bila semua bukti Memenuhi Syarat; tanda tangan Admin LSP dibubuhkan otomatis saat Anda memutuskan.</span></div>` : ''}
    <div id="pdMk">${mukRender(F, {embed: true, readonly: p.status !== 'diajukan'})}</div>
    <details><summary style="cursor:pointer;font-weight:700">FR.APL.02 Asesmen mandiri asesi</summary>${mukRender(F2, {embed: true, readonly: true})}</details>`;
}
X.pdPutus = async t => {
  const d = t.dataset;
  if (d.confirm && !confirm(d.confirm)) return;
  const v = mukCollect(document.getElementById('pdMk'));
  const body = {id: Number(d.id), aksi: d.aksi, catatan: (document.getElementById('pd-cat') || {}).value || '', verifikasi: {dasar: v.dasar || {}, admin_bukti: v.admin_bukti || {}}};
  busy(true);
  try { await api(withLsp('lsp/pendaftaran/putus'), body); toast(d.ok || 'Tersimpan.'); mkDrop('apl0'); await reloadPage(); }
  catch (x) { toast(x.message); render(); }
  finally { S.busy = false; }
};

/* ===================== Asesor: laporan per jadwal ===================== */
PAGE_LOAD.laporanA = () => api('asesor/laporan');
PAGES.laporanA = () => {
  if (S.mkF) return `<div class="row no-print"><button class="btn ghost sm" data-x="mkBack">← Kembali</button></div>${mukRender(S.mkF)}`;
  const D = S.d.laporanA;
  if (!D) return loading();
  return `${phead('Per jadwal uji', 'Laporan (FR.AK.05) & tinjauan (FR.AK.06)')}
  <div class="alert info">${ic('doc')}<span>Serahkan <b>FR.AK.05 Laporan Asesmen</b> setelah semua FR.AK.02 jadwal ditandatangani; Komite Teknis hanya dapat memutuskan berkas yang tercakup laporan. <b>FR.AK.06</b> merekam tinjauan proses asesmen.</span></div>
  <div class="card">${table(['Tanggal', 'Skema', 'LSP', 'Asesi diuji', 'FR.AK.05', 'FR.AK.06', ''], D.items.map(j => tr([`<b>${fmtHari(j.tanggal)}</b>`, j.skema_nama, j.lsp_nama,
    `${j.diuji}${j.belum ? ` <span class="chip warn">${j.belum} belum diuji</span>` : ''}${j.tercakup < j.diuji ? ` <span class="chip bad">${j.diuji - j.tercakup} belum dilaporkan</span>` : ''}`, mkChip(j.ak05), mkChip(j.ak06),
    `<div class="row" style="gap:.3rem;flex-wrap:nowrap"><button class="btn sm ${j.tercakup < j.diuji ? 'green' : 'ghost'}" data-x="mkJadwal" data-jid="${j.id}" data-kode="AK.05">AK.05</button><button class="btn sm ghost" data-x="mkJadwal" data-jid="${j.id}" data-kode="AK.06">AK.06</button></div>`])), 'Belum ada jadwal yang sudah diuji.')}</div>`;
};
X.mkJadwal = async t => {
  const jid = Number(t.dataset.jid), kode = t.dataset.kode;
  // Laporan baru dibuat bila laporan terakhir sudah final tetapi ada asesi yang belum tercakup (laporan susulan).
  const j = (S.d.laporanA && S.d.laporanA.items || []).find(x => x.id === jid);
  const baru = kode === 'AK.05' && j && j.ak05 === 'final' && j.tercakup < j.diuji;
  if (baru) { S.mkF = null; try { S.mkF = await api('muk/formulir?jadwal_id=' + jid + '&kode=AK.05&baru=1'); S.mkF.ctx = {jadwal_id: jid}; S.mkF.baru = true; render(); } catch (x) { toast(x.message); } return; }
  await mkLoadForm({jadwal_id: jid}, kode);
};

/* ===================== Perangkat asesmen (MUK) & validasi ===================== */
PAGE_LOAD.muk = () => api(withLsp('muk/perangkat'));
PAGE_LOAD.mukV = PAGE_LOAD.muk;
function pMuk() {
  if (S.mkF) return `<div class="row no-print"><button class="btn ghost sm" data-x="mkBack">← Kembali</button></div>${mukRender(S.mkF)}`;
  if (S.mkP && S.mkP.kode) return pMukEditor();
  if (S.mkP && S.mkP.sid) {
    const D = mkLazy('perangkat:' + S.mkP.sid, 'muk/perangkat?skema_id=' + S.mkP.sid);
    if (!D) return loading();
    if (D.error) return `<p class="alert bad">${esc(D.error)}</p>`;
    return `${phead('Perangkat asesmen (MUK) · ' + D.skema.kode, D.skema.nama, `<button class="btn ghost" data-x="mukList">← Semua skema</button>`)}
    <div class="alert info">${ic('shield')}<span>Setiap perangkat disusun penyusun lalu <b>divalidasi oleh asesor lain</b> (blok Penyusun dan Validator). Hanya versi tervalidasi yang dipakai pada asesmen; perubahan menjadi versi baru.</span></div>
    <div class="card">${table(['Perangkat', 'Versi dipakai', 'Versi terbaru', 'Penyusun', 'Validator', ''], D.items.map(it => tr([`<b>${it.judul}</b>`,
      it.valid ? `v${it.valid.versi} ${mkChip('valid')}` : '<span class="chip bad">Belum ada</span>', it.terbaru ? `v${it.terbaru.versi} ${mkChip(it.terbaru.status)}` : '—',
      it.terbaru && it.terbaru.penyusun ? `${it.terbaru.penyusun.nama}<div class="muted" style="font-size:.75rem">${it.terbaru.penyusun.no_met || ''}</div>` : '—',
      it.terbaru && it.terbaru.validator ? `${it.terbaru.validator.nama}<div class="muted" style="font-size:.75rem">${fmtTgl(it.terbaru.validator.at)}</div>` : '—',
      `<button class="btn sm ${it.terbaru && it.terbaru.status === 'menunggu_validasi' ? 'orange' : 'ghost'}" data-x="mukOpen" data-kode="${it.kode}">${D.boleh ? 'Buka' : 'Lihat'}</button>`])))}</div>
    <div class="card"><div class="spread"><h3>Elemen & KUK skema</h3><span class="chip info">${D.elemen.length} elemen</span></div>
      ${D.elemen.length ? `<details><summary style="cursor:pointer;font-weight:700">Lihat ${D.units.length} unit</summary>${table(['Unit', 'Kel.', 'Elemen', 'KUK'], D.elemen.map(e => tr([e.unit_kode, String((D.units.find(u => u.id === e.unit_id) || {}).kelompok || 1), e.no + '. ' + e.judul, e.kuk.map((k, i) => `${e.no}.${i + 1} ${k}`).join('<br>')])))}</details>` : '<p class="alert bad">Skema belum memiliki elemen & KUK. Lengkapi di menu Skema, Asesor, TUK.</p>'}</div>
    <div class="card"><div class="spread"><h3>Validasi asesmen (FR.VA)</h3>${D.boleh_va ? `<button class="btn sm purple" data-x="mukVa" data-id="0">+ Validasi baru</button>` : ''}</div>
      ${table(['Tanggal', 'Periode', 'Status', ''], D.validasi.map(v => tr([fmtHari(v.tanggal), v.periode || '—', mkChip(v.status), `<button class="btn sm ghost" data-x="mukVa" data-id="${v.id}">Buka</button>`])), 'Belum ada rekaman validasi.')}</div>`;
  }
  const D = S.d[S.appPage];
  if (!D) return loading();
  return `${phead('Buku Kerja 333-25', 'Perangkat asesmen & validasi')}
  <div class="card">${table(['Skema', 'LSP', 'Perangkat tervalidasi', ''], D.items.filter(lspOk).map(s => tr([`<b>${s.kode}</b> · ${s.nama}`, s.lsp_nama, `<span class="chip ${s.valid >= s.total ? 'ok' : 'warn'}">${s.valid}/${s.total}</span>`, `<button class="btn sm" data-x="mukSkema" data-sid="${s.id}">Buka</button>`])), 'Tidak ada skema.')}</div>`;
}
PAGES.muk = pMuk;
PAGES.mukV = pMuk;
function pMukEditor() {
  const P = mkLazy('pdetail:' + S.mkP.sid + ':' + S.mkP.kode, 'muk/perangkat/detail?skema_id=' + S.mkP.sid + '&kode=' + S.mkP.kode);
  if (!P) return loading();
  if (P.error) return `<p class="alert bad">${esc(P.error)}</p>`;
  const pr = P.perangkat;
  const F = {def: P.def, data: S.mkP.draft || P.data, header: P.header, status: pr ? pr.status : 'baru',
    ttd: {penyusun: pr && pr.penyusun && pr.penyusun.at ? {nama: pr.penyusun.nama, no_reg: pr.penyusun.no_met, at: pr.penyusun.at} : undefined,
      validator: pr && pr.validator ? {nama: pr.validator.nama, no_reg: pr.validator.no_met, at: pr.validator.at} : undefined},
    pihak: P.pihak.includes('penyusun') ? ['penyusun'] : []};
  let actions = '';
  if (P.pihak.includes('penyusun')) {
    actions = `<div class="card stack no-print" style="gap:.6rem;border:2px solid var(--brand-b)">${pr && pr.status === 'valid' ? `<p class="muted" style="font-size:.85rem">Versi ${pr.versi} sudah tervalidasi. Menyimpan perubahan membuat versi baru yang harus divalidasi ulang.</p>` : ''}
      ${pr && pr.catatan && pr.status === 'draf' ? `<p class="alert warn">Catatan validator: ${pr.catatan}</p>` : ''}
      <label class="toggle"><input type="checkbox" id="mk-setuju"><span>Saya sebagai <b>penyusun</b> menyatakan perangkat ini sesuai skema dan standar kompetensi, dan menandatanganinya secara elektronik untuk diajukan ke validator.</span></label>
      <div class="row" style="justify-content:flex-end"><button class="btn ghost" data-x="mukPSave">Simpan draf</button><button class="btn green" data-x="mukPSign">${ic('check')}Tandatangani & ajukan validasi</button></div></div>`;
  } else if (P.pihak.includes('validator')) {
    actions = `<div class="card stack no-print" style="gap:.6rem;border:2px solid var(--brand-b)"><h3>Validasi perangkat</h3>
      <label class="f">Catatan validator (wajib bila dikembalikan)<textarea id="mk-vcat" rows="2" maxlength="500"></textarea></label>
      <label class="toggle"><input type="checkbox" id="mk-setuju"><span>Saya sebagai <b>validator</b> telah meninjau perangkat ini terhadap aturan bukti dan prinsip asesmen dan menandatanganinya secara elektronik.</span></label>
      <div class="row" style="justify-content:flex-end"><button class="btn orange" data-x="mukPReturn">Kembalikan ke penyusun</button><button class="btn green" data-x="mukPValid">${ic('check')}Validasi & tandatangani</button></div></div>`;
  }
  return `<div class="row no-print"><button class="btn ghost sm" data-x="mukBackSkema">← Kembali ke perangkat skema</button>${pr ? `<span class="chip plain">Versi ${pr.versi}</span>` : ''}</div>${mukRender(F, {actions: actions || '<span></span>'})}`;
}
Object.assign(X, {
  mukList() { S.mkP = null; render(); },
  mukSkema(t) { S.mkP = {sid: Number(t.dataset.sid)}; render(); window.scrollTo(0, 0); },
  mukOpen(t) { S.mkP.kode = t.dataset.kode; S.mkP.draft = null; S.mkExtra = {}; render(); window.scrollTo(0, 0); },
  mukBackSkema() { mkDrop('perangkat:' + S.mkP.sid); mkDrop('pdetail:'); S.mkP = {sid: S.mkP.sid}; render(); },
  async mukVa(t) { const id = Number(t.dataset.id); await mkLoadForm({skema_id: S.mkP.sid}, 'VA', id || undefined); if (S.mkF && !id) { S.mkF.id = null; } },
  async mukPSave(t) { await mukPWrite(t, false); },
  async mukPSign(t) { if (!$('#mk-setuju').checked) { toast('Centang pernyataan tanda tangan elektronik dulu.'); return; } await mukPWrite(t, true); },
  async mukPValid(t) { if (!$('#mk-setuju').checked) { toast('Centang pernyataan tanda tangan elektronik dulu.'); return; } await mukPVal(t, 'valid'); },
  async mukPReturn(t) { await mukPVal(t, 'kembalikan'); },
});
async function mukPWrite(t, sign) {
  const data = mukCollect($('#mkDoc'));
  t.disabled = true;
  try {
    await api(sign ? 'muk/perangkat/ttd' : 'muk/perangkat/simpan', {skema_id: S.mkP.sid, kode: S.mkP.kode, data, setuju: sign || undefined, sebagai: sign ? 'penyusun' : undefined});
    toast(sign ? 'Perangkat ditandatangani dan diajukan ke validator.' : 'Draf perangkat disimpan.');
    mkDrop('pdetail:'); mkDrop('perangkat:'); S.mkP.draft = null; S.mkExtra = {}; render();
  } catch (x) { toast(x.message); S.mkP.draft = Object.assign({}, data); t.disabled = false; }
}
async function mukPVal(t, aksi) {
  t.disabled = true;
  try {
    await api('muk/perangkat/ttd', {skema_id: S.mkP.sid, kode: S.mkP.kode, sebagai: 'validator', aksi, catatan: ($('#mk-vcat') || {}).value || '', setuju: aksi === 'valid'});
    toast(aksi === 'valid' ? 'Perangkat tervalidasi.' : 'Perangkat dikembalikan ke penyusun.');
    mkDrop('pdetail:'); mkDrop('perangkat:'); render();
  } catch (x) { toast(x.message); t.disabled = false; }
}

/* ===================== Banding (Komite Teknis) ===================== */
PAGE_LOAD.banding = () => g('banding');
PAGES.banding = () => {
  if (S.berkas) return pBerkas();
  const D = S.d.banding;
  if (!D) return loading();
  const t = curTab('banding', 'diajukan');
  const items = D.items.filter(b => t === 'semua' || b.status === t);
  return `${phead(ctxName(), 'Banding asesmen (FR.AK.04)')}
  <div class="alert info">${ic('shield')}<span>Banding diputus personil yang <b>tidak terlibat</b> dalam asesmen maupun keputusan yang dibanding (PBNSP 201 §9.8.4). Sistem menolak bila Anda pemutus pleno, asesor, atau asesi yang bersangkutan.</span></div>
  ${tabs('banding', [['diajukan', 'Menunggu', D.items.filter(b => b.status === 'diajukan').length], ['diputus', 'Diputus'], ['semua', 'Semua']])}
  ${listDetail(items, b => `<b>${b.asesi_nama}</b><span class="muted" style="display:block;font-size:.8rem">${b.skema_nama} · ${fmtTgl(b.created_at)}</span>`, b => `<div class="spread"><h3>Banding #${b.id}</h3>${mkChip(b.status)}</div>
    ${infoGrid([['Asesi', b.asesi_nama], ['Skema', b.skema_nama], ['Tanggal uji', fmtHari(b.tanggal_uji)], ['Keputusan awal', b.keputusan_awal === 'K' ? 'Kompeten' : 'Belum kompeten']])}
    <div class="muk-info"><b>Alasan banding</b><div>${b.alasan}</div></div>
    <div class="row">${btnBerkas(b.permohonan_id, 'Periksa berkas FR', 'sm purple')}</div>
    ${b.status === 'diputus' ? `<div class="alert ${b.hasil === 'diterima' ? 'info' : 'warn'}"><span><b>Hasil: ${b.hasil}</b> oleh ${b.pemutus_nama || '—'} (${fmtTgl(b.diputus_at)}). ${b.catatan || ''}</span></div>`
      : b.boleh ? form('banding/putus', `${hid('id', b.id)}<div class="row">${[['ditolak', 'Ditolak (keputusan tetap)'], ['diterima', 'Diterima (asesmen ulang)']].map(([v, l]) => `<label class="row" style="gap:.35rem;font-weight:700"><input type="radio" name="hasil" value="${v}" style="width:auto">${l}</label>`).join('')}</div>${fld('Dasar keputusan banding', txa('catatan', '', 'rows="3" maxlength="1000"'))}`, {ok: 'Keputusan banding disimpan.', submit: 'Putuskan banding'})
      : '<p class="alert warn">Anda terlibat dalam asesmen atau keputusan ini, sehingga tidak dapat memutus banding.</p>'}`, 'Tidak ada banding.')}`;
};

/* ===================== Menu ===================== */
MENU.asesor.splice(MENU.asesor.findIndex(it => it[0] === 'pleno'), 0, ['laporanA', 'Laporan & Tinjauan', 'doc', 'assessment.conduct']);
MENU.asesor.splice(MENU.asesor.findIndex(it => it[0] === 'riwayat'), 0, ['muk', 'Perangkat Asesmen (MUK)', 'book', 'muk.manage']);
(function () {
  const i = MENU.admin.findIndex(it => it[0] === 'plenoA');
  MENU.admin.splice(i + 1, 0, ['banding', 'Banding Asesmen', 'shield', 'appeal.view'], ['muk', 'Perangkat Asesmen (MUK)', 'book', 'muk.manage'], ['mukV', 'Validasi Asesmen (FR.VA)', 'check', 'assessment.review']);
  const j = MENU.super.findIndex(it => it[0] === 'plenoA');
  if (j >= 0) MENU.super.splice(j + 1, 0, ['banding', 'Banding Asesmen', 'shield', 'appeal.view'], ['muk', 'Perangkat Asesmen (MUK)', 'book', 'muk.manage']);
})();
