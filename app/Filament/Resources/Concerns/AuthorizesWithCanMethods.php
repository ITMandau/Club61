<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Di Filament 5, tombol aksi (Tambah, Edit, Hapus, Hapus Massal) TIDAK memanggil canCreate()/canEdit()/canDelete()
 * milik resource — aksi memakai getAuthorizationResponse() yang mencari Model Policy. Model tanpa policy (atau dengan
 * policy untuk portal customer) jatuh ke "boleh". Akibatnya staf yang hanya punya izin LIHAT paket membership / sponsor
 * bisa menghapus data lewat tombol hapus di tabel, walau canDelete() resource menolak.
 *
 * Trait ini mengarahkan SEMUA pengecekan aksi ke method can*() resource (yang memeriksa izin matriks Club61).
 * Resource pemakai wajib meng-override canViewAny/canCreate/canEdit/canDelete/canDeleteAny.
 */
trait AuthorizesWithCanMethods
{
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $ability = $action instanceof \BackedEnum ? (string) $action->value : ($action instanceof UnitEnum ? $action->name : $action);

        $allowed = match ($ability) {
            // 'view' sengaja memakai canViewAny(): canView() bawaan memanggil can('view') lagi → rekursi.
            'viewAny', 'view' => static::canViewAny(),
            'create', 'replicate' => static::canCreate(),
            'update' => $record instanceof Model && static::canEdit($record),
            'delete' => $record instanceof Model && static::canDelete($record),
            'deleteAny' => static::canDeleteAny(),
            // Tidak dipakai di panel ini (restore / hapus permanen / urut ulang) → tolak.
            default => false,
        };

        return $allowed ? Response::allow() : Response::deny('Akses ditolak: Anda tidak memiliki izin untuk aksi ini.');
    }
}
