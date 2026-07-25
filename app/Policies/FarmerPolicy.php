<?php

namespace App\Policies;

use App\Models\Farmer;
use App\Models\User;

class FarmerPolicy
{
    /**
     * Role-based access refactor: Accounts is deliberately excluded —
     * "Farmers" is not in Accounts' current menu — and the seeder no
     * longer grants Accounts the farmers.view permission, so the fallback
     * below can't silently readmit them either.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'marketing', 'dealer'])
            || $user->hasPermission('farmers.view');
    }

    public function view(User $user, Farmer $farmer): bool
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        if ($user->hasRole('marketing')) {
            return $farmer->dealer?->assignment?->marketing_user_id === $user->id;
        }

        if ($user->hasRole('dealer')) {
            return $farmer->dealer_id === $user->dealer_id;
        }

        return $user->hasPermission('farmers.view');
    }

    /**
     * User hierarchy: Dealer and Marketing may both create Farmers directly
     * — a Marketing user registers a Farmer under one of their own assigned
     * Dealers. FarmerStoreRequest::allowedDealerIds() already restricts a
     * Marketing-role submitter to their assigned dealers (and a Dealer-role
     * submitter to their own dealer_id), so no controller change is needed
     * to keep this safe — the existing validation already defends it.
     * Dispatch Planner and Accounts also gained this: the dispatch plan
     * form's and booking form's "register new farmer" modals need them to
     * be able to hit farmers.store inline without leaving the page
     * (allowedDealerIds() lets them attribute the new farmer to any dealer,
     * matching admin, since neither role is tied to one dealer).
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'marketing', 'dealer', 'dispatch-planner', 'accounts']) && $user->hasPermission('farmers.create');
    }

    public function update(User $user, Farmer $farmer): bool
    {
        if (! $user->hasPermission('farmers.edit')) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        if ($user->hasRole('marketing')) {
            return $farmer->dealer?->assignment?->marketing_user_id === $user->id;
        }

        if ($user->hasRole('dealer')) {
            return $farmer->dealer_id === $user->dealer_id;
        }

        return false;
    }

    public function delete(User $user, Farmer $farmer): bool
    {
        // Only Admin/Super Admin — Accounts, Marketing and Dealer never delete.
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('farmers.delete');
    }

    public function restore(User $user, Farmer $farmer): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('farmers.delete');
    }
}
