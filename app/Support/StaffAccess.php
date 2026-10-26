<?php

namespace App\Support;

use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Permission\HomeRouteResolver;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Hak akses staf yang berubah saat halamannya sedang terbuka (admin mencabut izin di "Roles & Hak Akses").
 *
 * - redirectForDenied(): staf yang membuka halaman yang tidak (lagi) diizinkan TIDAK melihat halaman 403, tapi dipindah
 *   ke halaman pendaratannya (HomeRouteResolver) dengan notifikasi. Tamu, customer, dan staf yang sama sekali tidak
 *   punya halaman tetap mendapat 403 seperti biasa.
 * - version(): sidik jari hak akses akun (role, izin, home route, status aktif) — dipantau browser
 *   (partials.access-watch) supaya menu sidebar ikut berubah tanpa menunggu pindah halaman.
 */
class StaffAccess
{
    public static function version(User $user): string
    {
        $user->loadMissing('roles');

        return sha1(json_encode([
            $user->getKey(),
            $user->is_active,
            $user->roles->pluck('name')->sort()->values()->all(),
            $user->roles->pluck('home_route')->all(),
            $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ]));
    }

    /** null = tetap tampilkan 403 (bukan staf panel, bukan navigasi halaman, atau halaman pendaratannya sendiri ditolak). */
    public static function redirectForDenied(Request $request, Throwable $e): ?RedirectResponse
    {
        if (! $request->isMethod('GET') || $request->is('api/*') || $request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return null;
        }

        // Hanya halaman yang dibuka dari menu (halaman / resource panel admin, layar POS & KDS). Unduhan / ekspor / invoice
        // tetap 403 — "dialihkan ke halaman lain" tidak masuk akal untuk tombol unduh.
        if (! self::isScreen($request)) {
            return null;
        }

        $user = $request->user();
        if (! $user instanceof User || $user->is_active === false) {
            return null;
        }

        try {
            if (! $user->canAccessPanel(Filament::getPanel('admin'))) {
                return null;
            }
            $landing = HomeRouteResolver::resolve($user);
        } catch (Throwable) {
            return null;
        }

        // Customer / tidak punya halaman staf sama sekali, atau halaman pendaratannya sendiri yang ditolak (cegah muter-muter).
        $landingPath = '/'.trim((string) parse_url($landing, PHP_URL_PATH), '/');
        if ($landing === HomeRouteResolver::CUSTOMER || $landingPath === '/'.trim($request->path(), '/')) {
            return null;
        }

        // Respons 302 tidak lewat pencatat 403 di bootstrap/app.php — jejak percobaannya tetap dicatat di sini (termasuk
        // pengecekan diam-diam di bawah; accessDenied() men-dedup 60 detik, jadi pengecekan + muat ulang tetap satu baris).
        $message = $e->getMessage();
        ActivityLogger::accessDenied(($message !== '' && $message !== 'This action is unauthorized.' ? $message : 'tidak memiliki izin untuk halaman ini').' (dialihkan ke '.$landingPath.')');

        // Pengecekan diam-diam dari partials.access-watch: cukup jawab "dialihkan" — notifikasinya muncul saat halaman
        // benar-benar dimuat ulang (kalau tidak, muncul dua kali).
        if ($request->hasHeader('X-Club61-Access-Probe')) {
            return redirect($landing);
        }

        Notification::make()
            ->title('Akses ditolak')
            ->body('Akun Anda tidak punya izin membuka halaman '.self::pageLabel($request).' (hak akses diatur admin). Anda dialihkan ke halaman yang masih diizinkan.')
            ->warning()
            ->persistent()
            ->send();

        return redirect($landing);
    }

    private static function isScreen(Request $request): bool
    {
        if ($request->is('pos') || $request->is('kitchen')) {
            return true;
        }

        try {
            $class = $request->route()?->getControllerClass();
        } catch (Throwable) {
            return false;
        }

        return is_string($class) && (is_subclass_of($class, \Filament\Pages\Page::class) || is_subclass_of($class, \Filament\Resources\Pages\Page::class));
    }

    /** Nama menu halaman yang ditolak, mis. "Tarif Setoran" — sebisanya, selain itu "tersebut". */
    private static function pageLabel(Request $request): string
    {
        try {
            $class = $request->route()?->getControllerClass();

            $label = match (true) {
                ! is_string($class) => null,
                is_subclass_of($class, \Filament\Resources\Pages\Page::class) => $class::getResource()::getNavigationLabel(),
                is_subclass_of($class, \Filament\Pages\Page::class) => $class::getNavigationLabel(),
                default => null,
            };
        } catch (Throwable) {
            $label = null;
        }

        if (! $label) {
            $label = match (true) {
                $request->is('pos') => 'Terminal POS',
                $request->is('kitchen') => 'Monitor Dapur',
                default => null,
            };
        }

        return $label ? '"'.$label.'"' : 'tersebut';
    }
}
