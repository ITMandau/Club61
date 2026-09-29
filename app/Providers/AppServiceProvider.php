<?php

namespace App\Providers;

use App\Models\Setting\CompanyProfileSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Payment\PaymentFulfillmentRegistry::class, function () {
            $registry = new \App\Services\Payment\PaymentFulfillmentRegistry();
            $registry->register('PADEL', \App\Services\Padel\Handlers\PadelFulfillmentHandler::class);
            $registry->register('MEMBERSHIP', \App\Services\Membership\MembershipFulfillmentHandler::class);

            return $registry;
        });

        // Filament tidak punya login page sendiri lagi (AdminPanelProvider), jadi begitu staf
        // logout dari /admin, arahkan langsung ke satu-satunya pintu login (/login) — bukan ke
        // dashboard panel /admin (yang defaultnya dituju Filament\Auth\Http\Responses\LogoutResponse
        // kalau tidak ada login page terdaftar), supaya tidak ada hop redirect tambahan.
        $this->app->bind(
            \Filament\Auth\Http\Responses\Contracts\LogoutResponse::class,
            fn () => new class implements \Filament\Auth\Http\Responses\Contracts\LogoutResponse
            {
                public function toResponse($request)
                {
                    return redirect()->route('login');
                }
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('super_admin') ? true : null;
        });

        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(\App\Models\Sponsor\SponsorOrganization::class, \App\Policies\Sponsor\SponsorOrganizationPolicy::class);

        // Satu sumber data company profile dipakai ulang di halaman depan publik (welcome)
        // DAN panel kiri halaman login — supaya fakta venue (jumlah lapangan, daftar
        // fasilitas, alamat/jam) tidak pernah drift antar 2 file lagi seperti insiden
        // "4 vs 3 lapangan" yang memicu Modul 14 (Company Profile Content).
        View::composer(['welcome', 'auth.login'], function ($view) {
            $view->with('companyProfile', CompanyProfileSetting::current());
        });

        // Facilities Showcase, "Kenapa Pilih Club61", Membership Teaser, & stats bar cuma
        // tampil di halaman depan publik (bukan panel login, yang cuma butuh kartu ringkas
        // dari $companyProfile di atas).
        View::composer('welcome', function ($view) {
            $view->with('companyFacilities', \App\Models\Setting\CompanyProfileFacility::active()->get());
            $view->with('companyValueProps', \App\Models\Setting\CompanyProfileValueProp::active()->get());

            // Semua paket individual yang aktif ditampilkan (bukan cuma 3 termurah) — supaya
            // staf yang menambah/menonaktifkan paket lewat resource Membership tidak perlu
            // sentuh halaman depan lagi, cukup 1 sumber data yang sama.
            $view->with(
                'membershipPlans',
                \App\Models\Membership\MembershipPlan::query()
                    ->where('is_active', true)
                    ->where('ownership_type', 'INDIVIDUAL')
                    ->with('benefits')
                    ->orderBy('price')
                    ->get()
            );

            // Angka nyata dari database, bukan dummy — biar hero page ga cuma dekorasi kosong.
            $view->with('venueStats', [
                'active_members' => \App\Models\Membership\UserMembership::where('status', 'ACTIVE')->distinct('user_id')->count('user_id'),
                'sponsor_partners' => \App\Models\Sponsor\SponsorOrganization::count(),
            ]);
        });

        if (! app()->environment('production') && (request()->header('x-forwarded-proto') === 'https' || str_contains(request()->header('host') ?? '', 'ngrok'))) {
            URL::forceScheme('https');
        }
        // 1. Rate Limiting Otentikasi - Anti Brute-Force
        // Dua limit independen: per-IP (10/menit) DAN per-akun (5/menit, dikunci ke
        // identifier login/email-nya sendiri, bukan IP). Tanpa limit per-akun, attacker
        // yang gonta-ganti IP (proxy/botnet) bisa nyoba password tanpa batas ke satu akun
        // yang sama karena limit per-IP tidak pernah kena untuk akun itu.
        $authThrottleResponse = function () {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan autentikasi. Silakan tunggu 1 menit.',
                'errors' => null,
            ], 429);
        };

        RateLimiter::for('auth-throttle', function (Request $request) use ($authThrottleResponse) {
            $identifier = strtolower(trim((string) ($request->input('login') ?? $request->input('email') ?? '')));

            return [
                Limit::perMinute(10)->by('ip:'.$request->ip())->response($authThrottleResponse),
                Limit::perMinute(5)->by('account:'.($identifier !== '' ? $identifier : $request->ip()))->response($authThrottleResponse),
            ];
        });

        // 2. Rate Limiting Booking Padel (5 hit/menit/User) - Anti Calo & Bot
        RateLimiter::for('booking-throttle', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak permintaan booking. Silakan tunggu 1 menit.',
                    'errors' => null,
                ], 429);
            });
        });
    }
}
