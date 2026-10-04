<?php

namespace App\Http\Controllers;

use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CorporateDashboardController extends Controller
{
    /** Dashboard Sponsor Team milik PIC yang sedang login. */
    public function show(Request $request): View
    {
        $organization = $this->organizationQuery()
            ->where('sponsor_admin_user_id', auth()->id())
            ->first();

        return $this->render($request, $organization, previewMode: false);
    }

    /**
     * Pratinjau dashboard PIC untuk staf (read-only). Semua aksi PIC di halaman ini tetap
     * dijaga SponsorOrganizationPolicy (sponsor_admin_user_id === user login) di API-nya,
     * jadi staf hanya bisa melihat, tidak bisa merilis voucher / mengubah roster atas nama PIC.
     */
    public function preview(Request $request, string $organization): View
    {
        $user = auth()->user();
        abort_unless($user && ($user->can('View:SponsorDashboard') || $user->can('view_sponsor_organizations')), 403);

        return $this->render($request, $this->organizationQuery()->findOrFail($organization), previewMode: true);
    }

    private function organizationQuery()
    {
        return SponsorOrganization::with(['sponsorAdmin:id,name', 'userMembership.plan', 'userMembership.balances', 'accessSchedules' => function ($q) {
            $q->where('valid_until', '>=', now()->toDateString())->orderBy('valid_from');
        }]);
    }

    private function render(Request $request, ?SponsorOrganization $organization, bool $previewMode): View
    {
        $search = trim((string) $request->query('search', ''));

        // Roster table/cards are paginated + searchable so the page stays fast once a
        // sponsor's team grows large. Summary stats and the bulk-release modal below need
        // the FULL active roster regardless of the current search/page, so they're fetched
        // separately rather than derived from the paginated $members collection.
        $members = $organization
            ? SponsorOrganizationMember::where('sponsor_organization_id', $organization->id)
                ->with(['user:id,name,phone', 'vouchers'])
                ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($uq) => $uq
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')))
                ->latest('created_at')
                ->paginate(10)
                ->withQueryString()
            : new LengthAwarePaginator(collect(), 0, 10);

        $allActiveMembers = $organization
            ? SponsorOrganizationMember::where('sponsor_organization_id', $organization->id)
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

        return view('customer.corporate', [
            'organization' => $organization,
            'previewMode' => $previewMode,
            'members' => $members,
            'search' => $search,
            'activeMemberCount' => $allActiveMembers->count(),
            'totalHoursReleased' => $organization ? $organization->totalHoursReleased() : 0.0,
            'totalHoursUsed' => (float) $activeVouchers->sum(fn ($v) => (float) $v->hours_used),
            'totalQuota' => $organization ? $organization->totalQuota() : null,
            'quotaRemaining' => $organization ? $organization->remainingQuota() : null,
            'activeMembersForBulk' => $allActiveMembers,
        ]);
    }
}
