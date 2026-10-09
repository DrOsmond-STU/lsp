<?php
declare(strict_types=1);

/**
 * Definisi peran. scope:
 *  platform = pengelola aplikasi (tanpa lsp_id)
 *  lsp      = staf internal satu LSP (hanya boleh satu keanggotaan)
 *  tuk      = admin satu TUK di dalam satu LSP
 *  personal = asesor / asesi (boleh aktif di banyak LSP)
 */
const ROLE_DEFS = [
    'platform_admin' => ['Admin Platform', 'platform', false],
    'admin_lsp'      => ['Admin LSP', 'lsp', false],
    'manajer_mutu'   => ['Manajer Mutu', 'lsp', false],
    'keuangan'       => ['Keuangan LSP', 'lsp', false],
    'marketing'      => ['Marketing LSP', 'lsp', false],
    'admin_tuk'      => ['Admin TUK', 'tuk', false],
    'asesor'         => ['Asesor', 'personal', true],
    'asesi'          => ['Asesi', 'personal', true],
];

const PERM_DEFS = [
    'platform.dashboard'    => 'Melihat ringkasan platform',
    'listing.review'        => 'Menyetujui / menolak listing e-commerce & LMS',
    'lsp.manage'            => 'Mengelola LSP klien, paket, dan support',
    'rbac.view'             => 'Melihat peran & hak akses',
    'lsp.dashboard'         => 'Melihat dashboard LSP',
    'registration.verify'   => 'Memverifikasi pendaftaran asesi',
    'schedule.manage'       => 'Mengelola jadwal & penugasan asesor',
    'assessment.monitor'    => 'Memantau asesmen',
    'decision.manage'       => 'Mengelola pleno & penerbitan sertifikat',
    'master.manage'         => 'Mengelola skema, asesor, TUK',
    'alumni.view'           => 'Melihat database alumni',
    'listing.manage'        => 'Mengelola etalase & pelatihan LSP',
    'quality.manage'        => 'Mengelola sistem manajemen mutu',
    'finance.manage'        => 'Mengelola keuangan LSP',
    'crm.manage'            => 'Mengelola CRM',
    'report.bnsp'           => 'Membuat laporan BNSP',
    'settings.manage'       => 'Mengubah profil & pengaturan LSP',
    'user.manage'           => 'Mengelola pengguna & peran di LSP',
    'tuk.dashboard'         => 'Melihat dashboard TUK',
    'tuk.applicants'        => 'Melihat pemohon di TUK',
    'tuk.schedule'          => 'Mengelola jadwal TUK',
    'tuk.facility'          => 'Mengelola sarana & prasarana TUK',
    'tuk.chat'              => 'Group chat asesmen TUK',
    'tuk.alumni'            => 'Melihat alumni TUK',
    'asesor.dashboard'      => 'Melihat dashboard asesor',
    'preassessment.review'  => 'Meninjau pra-asesmen',
    'assessment.conduct'    => 'Melaksanakan asesmen',
    'pleno.participate'     => 'Mengikuti pleno',
    'asesor.history'        => 'Melihat riwayat & logbook asesor',
    'asesor.honor'          => 'Melihat honor sendiri',
    'asesi.dashboard'       => 'Melihat dashboard asesi',
    'application.own'       => 'Mengelola permohonan sendiri',
    'payment.own'           => 'Membayar tagihan sendiri',
    'certificate.own'       => 'Melihat sertifikat sendiri',
    'class.own'             => 'Mengikuti kelas',
    'profile.own'           => 'Mengelola profil & dokumen sendiri',
    'notif.log'             => 'Melihat log pengiriman notifikasi',
    'ai.use'                => 'Memakai asisten AI',
];

/** Admin Platform tidak dicantumkan di sini: ia selalu memegang SEMUA hak akses (lihat role_perm_map). */
const ROLE_PERMS = [
    'admin_lsp'      => ['lsp.dashboard', 'registration.verify', 'schedule.manage', 'assessment.monitor', 'decision.manage',
                         'master.manage', 'alumni.view', 'listing.manage', 'quality.manage', 'finance.manage', 'crm.manage',
                         'report.bnsp', 'settings.manage', 'user.manage', 'rbac.view', 'notif.log', 'ai.use'],
    'manajer_mutu'   => ['lsp.dashboard', 'quality.manage', 'alumni.view', 'report.bnsp', 'decision.manage', 'ai.use'],
    'keuangan'       => ['lsp.dashboard', 'finance.manage', 'ai.use'],
    'marketing'      => ['lsp.dashboard', 'listing.manage', 'crm.manage', 'ai.use'],
    'admin_tuk'      => ['tuk.dashboard', 'tuk.applicants', 'tuk.schedule', 'tuk.facility', 'tuk.chat', 'tuk.alumni', 'ai.use'],
    'asesor'         => ['asesor.dashboard', 'preassessment.review', 'assessment.conduct', 'pleno.participate', 'asesor.history', 'asesor.honor', 'ai.use'],
    'asesi'          => ['asesi.dashboard', 'application.own', 'payment.own', 'certificate.own', 'class.own', 'profile.own', 'ai.use'],
];

/** Peta peran → hak akses, termasuk Admin Platform yang mendapat semua hak akses. */
function role_perm_map(): array
{
    return ['platform_admin' => array_keys(PERM_DEFS)] + ROLE_PERMS;
}

function is_platform(?array $m): bool
{
    return $m !== null && $m['role'] === 'platform_admin';
}

/** Peran yang boleh diberikan Admin LSP kepada pengguna di LSP-nya. */
const LSP_ASSIGNABLE_ROLES = ['admin_lsp', 'manajer_mutu', 'keuangan', 'marketing'];

function role_label(string $role): string
{
    return ROLE_DEFS[$role][0] ?? $role;
}

/** Hak akses dibaca dari tabel role_permissions (bukan dari klien). */
function permissions_for(?array $membership): array
{
    static $cache = [];
    if (!$membership) {
        return [];
    }
    $role = $membership['role'];
    if ($role === 'platform_admin') {
        return array_keys(PERM_DEFS); // akses penuh ke semua menu dan semua LSP
    }
    if (!isset($cache[$role])) {
        $cache[$role] = q('SELECT perm_code FROM role_permissions WHERE role_code = ? ORDER BY perm_code', [$role])
            ->fetchAll(PDO::FETCH_COLUMN);
    }
    return $cache[$role];
}

function can(string $perm): bool
{
    return in_array($perm, permissions_for(active_membership()), true);
}

function require_auth(bool $allowMustChange = false): array
{
    $u = current_user();
    if (!$u) {
        fail('Silakan masuk terlebih dahulu.', 401);
    }
    if (!$allowMustChange && (int)$u['must_change_password'] === 1) {
        fail('Anda wajib mengganti password terlebih dahulu.', 403);
    }
    if (!active_membership()) {
        fail('Akun Anda belum punya akses aktif ke LSP mana pun.', 403);
    }
    return $u;
}

/** Wajib login + punya hak akses; mengembalikan keanggotaan aktif (konteks LSP). */
function require_perm(string $perm): array
{
    require_auth();
    if (!can($perm)) {
        audit('access.denied', $perm);
        fail('Anda tidak punya hak akses untuk tindakan ini.', 403);
    }
    return active_membership();
}
