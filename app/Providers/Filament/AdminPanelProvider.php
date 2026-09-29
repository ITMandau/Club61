<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Sengaja TIDAK register login page Filament sendiri — supaya cuma ada SATU pintu
            // login untuk semua orang (staf maupun customer), yaitu /login (LoginRequest.php,
            // sudah mendukung email/No HP + auto-redirect ke /admin kalau stafnya admin/staff).
            // Efeknya: akses /admin tanpa login otomatis diarahkan ke /login (fallback bawaan
            // Laravel saat Filament::getLoginUrl() null), dan logout dari /admin juga diarahkan
            // ke /login lewat binding LogoutResponse custom di AppServiceProvider.
            ->darkMode(false)
            ->brandName('Club 61 Padel Court')
            ->brandLogo(asset('images/club61-logo.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/club61-logo.png'))
            ->maxContentWidth('full')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->spa()
            ->resources([
                \App\Filament\Resources\Users\UserResource::class,
                \App\Filament\Resources\Roles\RoleResource::class,
                \App\Filament\Resources\Membership\MembershipPlanResource::class,
                \App\Filament\Resources\Sponsor\SponsorOrganizationResource::class,
                \App\Filament\Resources\Sponsor\SponsorAccessScheduleResource::class,
            ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
            ])
            ->renderHook(
                'panels::head.end',
                fn () => view('filament.custom-styles')
            )
            ->pages([
                \App\Filament\Pages\Dashboard::class,
                \App\Filament\Pages\Analytics::class,
                \App\Filament\Pages\BookingSystem::class,
                \App\Filament\Pages\BookOfflineCourt::class,
                \App\Filament\Pages\JualMembership::class,
                \App\Filament\Pages\KelolaPemesanan::class,
                \App\Filament\Pages\Kustomer::class,
                \App\Filament\Pages\KelolaKaryawan::class,
                \App\Filament\Pages\KelolaTurnamen::class,
                \App\Filament\Pages\KelolaClub::class,
                \App\Filament\Pages\Marketing::class,
                \App\Filament\Pages\MasterData::class,
                \App\Filament\Pages\PengaturanBiayaPajak::class,
                \App\Filament\Pages\KelolaKontenWebsite::class,
                \App\Filament\Pages\KelolaMenuFnb::class,
                \App\Filament\Pages\SponsorDashboard::class,
            ])
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\RedirectToFirstAccessiblePanelPage::class,
            ]);
    }
}
