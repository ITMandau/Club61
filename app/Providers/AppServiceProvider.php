<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasAnyRole(['super_admin', 'admin']) ? true : null;
        });

        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);

        if (request()->header('x-forwarded-proto') === 'https' || str_contains(request()->header('host') ?? '', 'ngrok')) {
            URL::forceScheme('https');
        }
        // 1. Rate Limiting Otentikasi (10 hit/menit/IP) - Anti Brute-Force
        RateLimiter::for('auth-throttle', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan autentikasi. Silakan tunggu 1 menit.',
                    'errors' => null,
                ], 429);
            });
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
