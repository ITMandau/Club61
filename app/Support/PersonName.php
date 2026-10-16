<?php

namespace App\Support;

use App\Rules\ValidPersonName;

/**
 * Aturan nama orang (customer / akun) — satu sumber untuk semua form: daftar web & app, edit profil, admin user,
 * walk-in kasir (booking & membership), anggota sponsor. Hanya huruf & spasi, 2–100 karakter.
 * Nama perusahaan / produk / F&B "atas nama" tidak memakai aturan ini.
 */
class PersonName
{
    public const MAX_LENGTH = 100;

    public const MIN_LETTERS = 2;

    public const MESSAGE = 'Nama hanya boleh berisi huruf dan spasi (minimal 2 huruf, maksimal 100 karakter).';

    /** Rapikan spasi (spasi ganda / tab / baris baru jadi satu spasi). Isi lain tidak diubah. */
    public static function clean(?string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $name));
    }

    public static function isValid(?string $name): bool
    {
        $clean = self::clean($name);

        return preg_match('/^[\p{L}\p{M}]+( [\p{L}\p{M}]+)*$/u', $clean) === 1
            && mb_strlen(str_replace(' ', '', $clean)) >= self::MIN_LETTERS
            && mb_strlen($clean) <= self::MAX_LENGTH;
    }

    /** Aturan validasi Laravel untuk field nama wajib. */
    public static function rules(): array
    {
        return ['required', 'string', new ValidPersonName];
    }
}
