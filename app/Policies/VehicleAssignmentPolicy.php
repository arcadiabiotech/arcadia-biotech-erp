<?php

namespace App\Policies;

use App\Models\DispatchPlan;
use App\Models\User;
use App\Models\VehicleAssignment;

class VehicleAssignmentPolicy
{
    /**
     * Unscoped for every role granted the view permission — same convention
     * already used for the Dispatch role in DispatchPolicy::view() (no
     * per-supervisor assignment exists in this schema; Supervisor's "Today's
     * Vehicles" dashboard shows every vehicle due out that day, not a
     * pre-assigned subset).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'dispatch-planner', 'marketing', 'supervisor'])
            && $user->hasPermission('dispatch-planning.view');
    }

    public function view(User $user, VehicleAssignment $assignment): bool
    {
        return $this->viewAny($user);
    }

    /**
     * A vehicle can only be assigned to a Plan that's already Approved —
     * "Booking cannot move to Dispatch until Planning is complete" now
     * extends one step earlier: Planning itself must be Approved first.
     * $plan is optional only so Laravel's authorizeResource-style calls
     * without a plan in context don't hard-fail; every real call site
     * passes it via `$this->authorize('create', [VehicleAssignment::class, $plan])`.
     *
     * Supervisor included per the Dispatch Lifecycle spec — "Add Vehicle" is
     * now their step, not just dispatch-planner's.
     */
    public function create(User $user, ?DispatchPlan $plan = null): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'dispatch-planner', 'supervisor'])
            && $user->hasPermission('dispatch-planning.create')
            && ($plan === null || $plan->approval_status === 'approved');
    }

    public function update(User $user, VehicleAssignment $assignment): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'dispatch-planner'])
            && $user->hasPermission('dispatch-planning.edit')
            && $assignment->loading_status !== 'completed';
    }

    public function delete(User $user, VehicleAssignment $assignment): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('dispatch-planning.delete')
            && $assignment->loading_status !== 'completed';
    }

    /**
     * Toggling a booking's "loaded" checkbox — Supervisor's own job. Locked
     * out once this vehicle's loading has already been approved.
     */
    public function markLoaded(User $user, VehicleAssignment $assignment): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'supervisor'])
            && $user->hasPermission('dispatch-planning.load')
            && $assignment->loading_status !== 'completed';
    }

    /**
     * Whether every item is actually loaded (the real precondition for
     * "Approve Loading") is a business rule enforced in
     * DispatchPlanningService::approveLoading(), not here — this policy
     * only gates who is allowed to attempt it at all, same separation of
     * concerns as DispatchPolicy::complete() vs DispatchService::complete().
     */
    public function approveLoading(User $user, VehicleAssignment $assignment): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'supervisor'])
            && $user->hasPermission('dispatch-planning.approve-loading')
            && $assignment->loading_status !== 'completed';
    }

    /**
     * The one-click "Vehicle Loaded" action (DispatchService::loadVehicle())
     * — needs both the loading-approval right (same as approveLoading()
     * above) and the Dispatch-create right, since it also creates the real
     * Dispatch record. Only super-admin/admin/supervisor ever hold both in
     * practice (see PermissionSeeder), same as the manual multi-step path.
     */
    public function loadVehicle(User $user, VehicleAssignment $assignment): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'supervisor'])
            && $user->hasPermission('dispatch-planning.approve-loading')
            && $user->hasPermission('dispatch.create')
            && $assignment->loading_status !== 'completed';
    }
}
