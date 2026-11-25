<?php

namespace App\Support;

use App\Models\User;

/**
 * Hak akses per nomor WhatsApp (BR03), dipakai halaman Kontak dan Prospek.
 * Administrator dan Supervisor melihat semua nomor; Agen/CS hanya nomor yang ditugaskan.
 */
class AksesNomor
{
    public const HAK_MEMBALAS = ['Balas chat', 'Kelola'];

    /** null = semua nomor; selain itu daftar id nomor yang boleh dilihat. */
    public static function dapatDilihat(User $pengguna): ?array
    {
        if (in_array($pengguna->role, ['Administrator', 'Supervisor'], true)) {
            return null;
        }

        return $pengguna->nomorWhatsapp()
            ->wherePivotIn('hak_akses', ['Lihat saja', ...self::HAK_MEMBALAS])
            ->pluck('nomor_whatsapp.id')->map(fn ($id) => (int) $id)->all();
    }

    /** null = semua nomor; selain itu daftar id nomor yang datanya boleh diubah. */
    public static function dapatDikelola(User $pengguna): ?array
    {
        if (in_array($pengguna->role, ['Administrator', 'Supervisor'], true)) {
            return null;
        }

        return $pengguna->nomorWhatsapp()->wherePivotIn('hak_akses', self::HAK_MEMBALAS)
            ->pluck('nomor_whatsapp.id')->map(fn ($id) => (int) $id)->all();
    }
}
