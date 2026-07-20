<?php

namespace App\Policies;

use App\Models\Farmer;
use App\Models\User;

class FarmerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'marketing', 'dealer', 'accounts'])
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

    public function create(User $user): bool
    {
        // Accounts is explicitly view + edit only — no create, no delete.
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('farmers.create');
    }

    public function update(User $user, Farmer $farmer): bool
    {
        if (! $user->hasPermission('farmers.edit')) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'admin', 'accounts'])) {
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
