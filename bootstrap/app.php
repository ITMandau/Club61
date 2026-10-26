<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // /broadcasting/auth untuk channel privat (notifikasi Filament). Layar jadwal memakai channel publik.
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware) {
        // Audit SEC-008: hanya proxy lokal (nginx di VPS yang sama) yang dipercaya. Dulu '*': header X-Forwarded-For
        // dari pengunjung ikut dipercaya → IP bisa dipalsukan untuk menghindari batas per-IP & mengotori Log Aktivitas.
        // nginx → PHP-FPM: IP diambil dari koneksi; nginx → proxy lokal: IP dari header yang ditulis nginx sendiri.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->statefulApi();
        // Batas umum semua route api/* (limiter 'api' di AppServiceProvider); webhook gateway dikecualikan per route.
        $middleware->throttleApi('api');
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // AuthenticateSession: sesi web di perangkat lain otomatis keluar setelah password diganti / direset lewat email
        // (dulu hanya panel admin yang begini — HP customer yang hilang tetap login walau password sudah direset).
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class, \Illuminate\Session\Middleware\AuthenticateSession::class, \App\Http\Middleware\EnsureUserIsActive::class]);
        $middleware->api(append: [\App\Http\Middleware\EnsureUserIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Enforce JSON responses for all /api/* requests
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // 1. Validation Exception (HTTP 422)
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi data gagal.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 2. Authentication Exception (HTTP 401)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Token tidak valid atau sesi telah berakhir.',
                    'errors' => null,
                ], 401);
            }
        });

        // 3. Model Not Found Exception (HTTP 404)
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data resource tidak ditemukan.',
                    'errors' => null,
                ], 404);
            }
        });

        // 4. General HTTP Exception (e.g. 409 Conflict, 403 Forbidden, 429 Throttle)
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Terjadi kesalahan pada request.',
                    'errors' => null,
                ], $e->getStatusCode());
            }
        });

        // 4b. Form web kedaluwarsa (419 / CSRF): bukan tampilkan "PAGE EXPIRED", tapi kembalikan user ke form-nya
        // dengan isian (tanpa kata sandi) + pesan, jadi tinggal kirim ulang. Contoh: halaman login yang lama terbuka
        // di tab HP, atau tombol Back setelah login. Logout yang kedaluwarsa tetap dituntaskan (user memang mau keluar).
        // Kalau browser tidak menyimpan cookie sesi sama sekali, redirect akan berulang tanpa hasil → tampilkan
        // halaman errors/419 yang menyarankan cek cookie. Request Livewire ditangani di browser (livewire-session-expired).
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->is('api/*') || $request->expectsJson() || $request->hasHeader('X-Livewire')) {
                return null;
            }
            if (! $request->hasCookie(config('session.cookie'))) {
                return null;
            }
            if ($request->routeIs('logout')) {
                \Illuminate\Support\Facades\Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/');
            }

            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password']))
                ->with('session_expired', 'Halaman ini terlalu lama terbuka sehingga sesinya habis. Data Anda belum terkirim — silakan periksa lalu kirim ulang.');
        });

        // 4c. Staf membuka halaman yang tidak (lagi) diizinkan — mis. izinnya baru dicabut admin saat halaman terbuka:
        // dipindah ke halaman pendaratannya + notifikasi, bukan halaman 403. Tamu / customer tetap 403 (App\Support\StaffAccess).
        $exceptions->render(function (HttpException $e, Request $request) {
            return $e->getStatusCode() === 403 ? \App\Support\StaffAccess::redirectForDenied($request, $e) : null;
        });

        // 5. Log Aktivitas: setiap percobaan membuka halaman / menjalankan aksi tanpa izin (403).
        // Dicatat di sini — SETELAH transaksi aksi yang ditolak di-rollback — supaya jejaknya tetap ada.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 403) {
                $message = $e->getMessage();
                \App\Services\Audit\ActivityLogger::accessDenied(
                    $message !== '' && $message !== 'This action is unauthorized.' ? $message : 'tidak memiliki izin untuk halaman / aksi ini'
                );
            }

            return $response;
        });
    })->create();
