<?php

namespace App\Policies\Sponsor;

use App\Models\Sponsor\SponsorOrganization;
use App\Models\User;

/**
 * Corporate PIC (Person In Charge) authorization — scoped strictly to their own
 * organization. This governs the CUSTOMER PORTAL (/corporate) only, via the routes in
 * SponsorOrganizationController — it does NOT gate the staff Filament panel.
 *
 * App\Filament\Resources\Sponsor\SponsorOrganizationResource has its own explicit
 * canViewAny()/canCreate()/canEdit()/canDelete() overrides checking staff permission
 * slugs (view_sponsor_organizations / manage_sponsor_organizations) instead of this
 * policy, precisely so a staff account is never accidentally granted or denied access
 * based on this PIC-ownership check (sponsor_admin_user_id === user->id), which would
 * be meaningless for a staff member.
 */
class SponsorOrganizationPolicy
{
    public function view(User $user, SponsorOrganization $organization): bool
    {
        return $organization->sponsor_admin_user_id === $user->id;
    }

    public function update(User $user, SponsorOrganization $organization): bool
    {
        return $organization->sponsor_admin_user_id === $user->id;
    }

    public function manageMembers(User $user, SponsorOrganization $organization): bool
    {
        return $organization->sponsor_admin_user_id === $user->id;
    }
}
