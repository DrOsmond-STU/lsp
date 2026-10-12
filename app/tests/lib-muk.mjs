// Pembantu uji formulir paperless: isi kolom wajib milik satu pihak berdasarkan definisi dari server.
const today = () => new Date(Date.now() + 7 * 3600e3).toISOString().slice(0, 10);
const optKeys = o => !o ? [] : Array.isArray(o) ? o : Object.keys(o);
const unesc = v => typeof v === 'string' ? v.replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&') : v;

function nilai(c, rowOpts, over) {
  if (over && c.k in over) return over[c.k];
  switch (c.t) {
    case 'teks': return 'Isian uji ' + c.k;
    case 'panjang': return 'Uraian uji untuk ' + c.k;
    case 'tanggal': return today();
    case 'yt': return 'Ya';
    case 'kbk': return 'K';
    case 'pilih': return optKeys(c.o === '@row' ? rowOpts : c.o)[0];
    case 'multi': return optKeys(c.o === '@row' ? rowOpts : c.o).slice(0, 1);
  }
  return null;
}

/** Data untuk pihak `party`: semua kolom milik pihak itu diisi (bawaan atau dari `over`). */
export function fillFor(F, party, over = {}) {
  const def = F.def, data = {};
  for (const b of def.bagian) for (const f of b.f) {
    if (f.t === 'info') continue;
    const own = c => (c.oleh || f.oleh || def.pengisi) === party;
    if (f.t !== 'grid') { if ((f.oleh || def.pengisi) === party) data[f.k] = unesc(nilai(f, null, over)); continue; }
    const mine = (f.kol || []).filter(own);
    if (!mine.length) continue;
    if (f.baris === 'bebas') { data[f.k] = [Object.fromEntries(mine.map(c => [c.k, unesc(nilai(c, null, over[f.k + '.row'] || {}))]))]; continue; }
    data[f.k] = {};
    for (const r of f.rows) data[f.k][r.key] = Object.fromEntries(mine.map(c => [c.k, unesc(nilai(c, r.o, (over[f.k] || {})[r.key] || over[f.k + '.*']))]));
  }
  return data;
}

/** Ambil formulir, isi bagian pihak, lalu tandatangani. Mengembalikan {status, json}. */
export async function signForm(client, ctx, kode, party, over = {}, extra = {}) {
  const qs = Object.entries(ctx).map(([k, v]) => k + '=' + v).join('&');
  const F = (await client.req(`muk/formulir?${qs}&kode=${kode}`)).json;
  if (!F.def) return {status: 0, json: F};
  const data = fillFor(F, party, over);
  return client.req('muk/formulir/ttd', {...ctx, kode, id: F.id || undefined, sebagai: party, setuju: true, data, ...extra});
}

/** Isian FR.APL.01/02 asesi untuk mendaftar skema. */
export async function aplData(client, skemaId, over = {}) {
  const d = (await client.req('muk/def?skema_id=' + skemaId)).json;
  return {apl01: fillFor(d['APL.01'], 'asesi', {tujuan: 'Sertifikasi', pendidikan: 'SMK RPL', ...(over.apl01 || {})}),
    apl02: fillFor(d['APL.02'], 'asesi', {'elemen.*': {kbk: 'K', bukti: 'Sertifikat pelatihan dan portofolio proyek'}, ...(over.apl02 || {})})};
}

/** Verifikasi FR.APL.01 oleh admin: semua bukti diberi status yang sama. */
export async function verifikasi(admin, pid, status = 'MS') {
  const F = (await admin.req(`muk/formulir?permohonan_id=${pid}&kode=APL.01`)).json;
  const v = fillFor(F, 'admin', {'dasar.*': {status}, 'admin_bukti.*': {status}});
  return {dasar: v.dasar, admin_bukti: v.admin_bukti};
}

/** Konsultasi pra-asesmen lengkap: APL.02 ditinjau, AK.07 & AK.01 ditandatangani asesor lalu asesi. Mengembalikan daftar status HTTP. */
export async function praAsesmen(asesor, asesi, pid, potensi = '1') {
  const ctx = {permohonan_id: pid}, st = [];
  st.push((await signForm(asesor, ctx, 'APL.02', 'asesor', {rekom: 'dapat', catatan: null})).status);
  st.push((await signForm(asesor, ctx, 'AK.07', 'asesor', {potensi, sepakat: 'tidak', 'butir.*': {perlu: 'Tidak', ket: null}, instrumen: null, acuan: null, metode: null})).status);
  st.push((await signForm(asesor, ctx, 'AK.01', 'asesor', {coi: 'Ya', banding_dijelaskan: 'Ya'})).status);
  st.push((await signForm(asesi, ctx, 'AK.07', 'asesi')).status);
  st.push((await signForm(asesi, ctx, 'AK.01', 'asesi')).status);
  return st;
}

/** Isi semua instrumen aktif (asesi menjawab lembar jawaban lebih dulu), lalu FR.AK.02. */
export async function asesmen(asesor, asesi, pid, hasil = 'K') {
  const ctx = {permohonan_id: pid}, st = [];
  const ins = (await asesor.req('muk/berkas?permohonan_id=' + pid)).json.instrumen || [];
  for (const ia of ins) {
    if (ia === 'IA.05' || ia === 'IA.06') {
      st.push((await signForm(asesi, ctx, ia, 'asesi')).status);
      st.push((await signForm(asesor, ctx, ia, 'asesor')).status);
    } else {
      st.push((await signForm(asesor, ctx, ia, 'asesor', {rekomendasi: hasil, bk_pada: hasil === 'BK' ? 'Unit 1 elemen 1' : null})).status);
      if (ia !== 'IA.10') st.push((await signForm(asesi, ctx, ia, 'asesi')).status);
    }
  }
  st.push((await signForm(asesor, ctx, 'AK.02', 'asesor', {'unit.*': {bukti: ['obs'], hasil}, tindak_lanjut: hasil === 'BK' ? 'Latihan ulang unit terkait' : null})).status);
  return st;
}

/** Laporan asesmen FR.AK.05 untuk jadwal (laporan susulan bila sudah ada). */
export async function laporan(asesor, jadwalId) {
  let F = (await asesor.req(`muk/formulir?jadwal_id=${jadwalId}&kode=AK.05`)).json;
  const baru = F.status === 'final';
  if (baru) F = (await asesor.req(`muk/formulir?jadwal_id=${jadwalId}&kode=AK.05&baru=1`)).json;
  const data = fillFor(F, 'asesor', {penolakan: 'Tidak ada'});
  return asesor.req('muk/formulir/ttd', {jadwal_id: jadwalId, kode: 'AK.05', id: F.id || undefined, baru: baru || undefined, sebagai: 'asesor', setuju: true, data});
}
