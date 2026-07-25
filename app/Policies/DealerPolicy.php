<?php

namespace App\Policies;

use App\Models\Dealer;
use App\Models\User;

class DealerPolicy
{
    /**
     * The list itself is scoped per-role in DealerController@index. Anyone
     * with a legitimate reason to see any dealer at all may open it; roles
     * with no default reason (e.g. Staff) fall through to the permission
     * check, so granting them dealers.view via the Roles UI is all that's
     * needed to admit them — no code change required.
     *
     * Role-based access refactor: Accounts is deliberately excluded —
     * "Dealers" is not in Accounts' current menu — and the seeder no
     * longer grants Accounts the dealers.view permission, so the fallback
     * below can't silently readmit them either.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'marketing', 'dealer'])
            || $user->hasPermission('dealers.view');
    }

    public function view(User $user, Dealer $dealer): bool
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        if ($user->hasRole('marketing')) {
            return $dealer->assignment?->marketing_user_id === $user->id;
        }

        if ($user->hasRole('dealer')) {
            return $dealer->id === $user->dealer_id;
        }

        // Accounts (and any other role explicitly granted the permission)
        // can view any dealer.
        return $user->hasPermission('dealers.view');
    }

    /**
     * User hierarchy refactor: Marketing may now create Dealers directly
     * (DealerController::store() auto-links the new dealer to the creating
     * Marketing user via DealerAssignment — the same mechanism Admin uses
     * to manually assign a dealer, just triggered automatically instead of
     * requiring a separate admin step).
     * Dispatch Planner and Accounts also gained this: the dispatch plan
     * form's and booking form's "register new dealer" modals need them to
     * be able to hit dealers.store inline without leaving the page.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'marketing', 'dispatch-planner', 'accounts']) && $user->hasPermission('dealers.create');
    }

    public function update(User $user, Dealer $dealer): bool
    {
        if (! $user->hasPermission('dealers.edit')) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Marketing may edit only a dealer assigned to them, and only if
        // an Admin has explicitly granted dealers.edit to the Marketing role.
        if ($user->hasRole('marketing')) {
            return $dealer->assignment?->marketing_user_id === $user->id;
        }

        return false;
    }

    public function delete(User $user, Dealer $dealer): bool
    {
        // Accounts explicitly cannot delete; only Admin/Super Admin can.
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('dealers.delete');
    }

    public function restore(User $user, Dealer $dealer): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('dealers.delete');
    }
}
