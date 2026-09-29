<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Layar Browser Laptop & Monitor Venue)
|--------------------------------------------------------------------------
*/

// 1. Layar Web Customer (Depan)
Route::get('/', function () {
    return view('welcome');
});

// Toggle bahasa ID/EN untuk halaman depan — disimpan di session (lihat SetLocale
// middleware), redirect balik ke halaman asal supaya posisi scroll/section tidak berubah.
Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(in_array($locale, \App\Http\Middleware\SetLocale::ALLOWED_LOCALES, true), 404);
    session(['site_locale' => $locale]);

    return redirect()->back();
})->name('lang.switch');

// 2. Layar POS Kasir Frontdesk & KDS Dapur (Wajib Auth & Otorisasi Staf)
Route::middleware(['auth'])->group(function () {
    Route::get('/pos', function () {
        if (! auth()->user()->isStaff()) {
            abort(403, 'Akses Ditolak: Hanya staf kasir atau admin yang dapat mengakses terminal POS.');
        }
        return view('pos.index');
    })->name('pos.index');

    Route::post('/pos/check-in', function (\Illuminate\Http\Request $request, \App\Services\Padel\PadelBookingService $service) {
        $user = auth()->user();
        if (! $user || ! $user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses Ditolak: Hanya staf kasir atau admin yang berhak melakukan check-in tiket.',
            ], 403);
        }

        $code = trim($request->input('code') ?? $request->input('qr_code_hash') ?? $request->input('booking_code') ?? '');
        if (empty($code)) {
            return response()->json(['success' => false, 'message' => 'Kode tiket atau QR wajib diisi.'], 422);
        }

        try {
            $result = $service->checkIn($code, $user);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    })->name('pos.checkin');

    // 3. Layar Monitor Dapur / KOT (Kitchen Display System)
    Route::get('/kitchen', function () {
        if (! (auth()->user()->hasRole('kitchen') || auth()->user()->isAdmin())) {
            abort(403, 'Akses Ditolak: Hanya staf dapur atau admin yang dapat mengakses KDS.');
        }
        return view('kitchen.kds');
    })->name('kitchen.kds');
});

// 4. Dashboard Member / Customer
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/booking', function () {
        return view('customer.booking');
    })->name('customer.booking');

    Route::get('/cart', function () {
        return view('customer.cart');
    })->name('customer.cart');

    Route::get('/checkout', function () {
        $clubFinanceSettings = \App\Models\Pos\ClubFinanceSetting::getSettings();
        return view('customer.checkout', [
            'clubFinanceSettings' => $clubFinanceSettings,
        ]);
    })->name('customer.checkout');

    Route::get('/my-club', function () {
        return view('customer.my-club');
    })->name('customer.my-club');

    Route::get('/membership', function (\Illuminate\Http\Request $request) {
        $allPlans = \App\Models\Membership\MembershipPlan::with('benefits')
            ->where('is_active', true)
            ->get();
        $selectedPlanId = $request->query('plan') ?? ($allPlans->firstWhere('code', 'MBR-SILVER')->id ?? $allPlans->first()?->id ?? null);

        return view('customer.membership', [
            'allPlans' => $allPlans,
            'selectedPlanId' => $selectedPlanId,
        ]);
    })->name('customer.membership');

    Route::get('/invoice', function () {
        return view('customer.invoice');
    })->name('customer.invoice');

    Route::get('/corporate/sample-csv', function () {
        $csv = "name,phone,hours\nBudi Santoso,081234567890,10\nSiti Rahma,081399887766,5\nAndi Wijaya,081700112233,8\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sponsor-roster-sample.csv"',
        ]);
    })->name('customer.corporate.sample-csv');

    Route::get('/corporate', function (\Illuminate\Http\Request $request) {
        $organization = \App\Models\Sponsor\SponsorOrganization::with(['userMembership.plan', 'userMembership.balances', 'accessSchedules' => function ($q) {
            $q->where('valid_until', '>=', now()->toDateString())->orderBy('valid_from');
        }])
            ->where('sponsor_admin_user_id', auth()->id())
            ->first();

        $search = trim((string) $request->query('search', ''));

        // Roster table/cards are paginated + searchable so the page stays fast once a
        // sponsor's team grows large. Summary stats and the bulk-release modal below need
        // the FULL active roster regardless of the current search/page, so they're fetched
        // separately rather than derived from the paginated $members collection.
        $members = $organization
            ? \App\Models\Sponsor\SponsorOrganizationMember::where('sponsor_organization_id', $organization->id)
                ->with(['user:id,name,phone', 'vouchers'])
                ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($uq) => $uq
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')))
                ->latest('created_at')
                ->paginate(10)
                ->withQueryString()
            : new \Illuminate\Pagination\LengthAwarePaginator(collect(), 0, 10);

        $allActiveMembers = $organization
            ? \App\Models\Sponsor\SponsorOrganizationMember::where('sponsor_organization_id', $organization->id)
                ->where('status', 'ACTIVE')
                ->with(['user:id,name,phone', 'vouchers'])
                ->get()
            : collect();

        // "Hours Used" is scoped to currently-active, non-expired vouchers (what employees still
        // have actually spent so far). "Hours Released" / "Quota Remaining" below are LIFETIME
        // figures on the organization itself (every voucher ever issued, even expired ones or
        // ones belonging to a since-revoked member) — once a voucher is released it permanently
        // consumes contract quota, whether or not it ends up being used before it expires.
        $activeVouchers = $allActiveMembers->flatMap->vouchers->filter(fn ($v) => ! $v->isExpired());
        $totalHoursUsed = (float) $activeVouchers->sum(fn ($v) => (float) $v->hours_used);
        $totalHoursReleased = $organization ? $organization->totalHoursReleased() : 0.0;
        $totalQuota = $organization ? $organization->totalQuota() : null;
        $quotaRemaining = $organization ? $organization->remainingQuota() : null;

        return view('customer.corporate', [
            'organization' => $organization,
            'members' => $members,
            'search' => $search,
            'activeMemberCount' => $allActiveMembers->count(),
            'totalHoursReleased' => $totalHoursReleased,
            'totalHoursUsed' => $totalHoursUsed,
            'totalQuota' => $totalQuota,
            'quotaRemaining' => $quotaRemaining,
            'activeMembersForBulk' => $allActiveMembers,
        ]);
    })->name('customer.corporate');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
