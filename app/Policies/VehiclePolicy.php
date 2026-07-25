<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * Role-based access refactor: Dispatch is deliberately excluded — the
     * standalone Vehicles listing is not in Dispatch's current menu (the
     * vehicle picker embedded in the Dispatch create/edit form queries
     * Vehicle directly and is unaffected). The seeder no longer grants
     * Dispatch the vehicles.view permission, so the fallback can't
     * silently readmit them.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || $user->hasPermission('vehicles.view');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Dispatch Planner is included so they can register a not-yet-known
     * vehicle on the fly from the "Add vehicle" flow instead of blocking on
     * an admin — see VehicleController::store()'s return_dispatch_plan_id
     * handling.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'dispatch-planner']) && $user->hasPermission('vehicles.create');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('vehicles.edit');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('vehicles.delete');
    }

    public function restore(User $user, Vehicle $vehicle): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('vehicles.delete');
    }
}
