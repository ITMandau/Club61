<?php

namespace App\Support;

/**
 * Email buatan sistem untuk akun yang didaftarkan staf tanpa email asli (walk-in kasir, jual membership,
 * sponsor korporat). Alamat ini tidak punya kotak masuk — jangan pernah dikirimi email (reset sandi, dll).
 */
class PlaceholderEmail
{
    private const PATTERNS = [
        '/@walkin\.club61\.internal$/i',          // ManagesCheckoutAndPayments (walk-in padel)
        '/^mbr_\d+@club61\.id$/i',                 // JualMembership (walk-in membership)
        '/^corp_\d+@club61\.id$/i',                // SponsorOrganizationService (PIC korporat)
        '/\.(internal|local|invalid|test)$/i',
    ];

    public static function is(?string $email): bool
    {
        $email = trim((string) $email);

        if ($email === '') {
            return true;
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $email)) {
                return true;
            }
        }

        return false;
    }
}
