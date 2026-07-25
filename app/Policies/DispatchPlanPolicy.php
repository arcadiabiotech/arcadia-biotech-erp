<?php

namespace App\Policies;

use App\Models\DispatchPlan;
use App\Models\User;

class DispatchPlanPolicy
{
    /**
     * Marketing is view-only per the Dispatch Planning spec — granted the
     * dispatch-planning.view permission but never create/edit/delete.
     *
     * Dispatch Lifecycle spec: Accounts creates plans (Dealer/Farmers/Qty/
     * Date/Route) and Handling Supervisor reviews/edits/approves them from
     * the Dispatch module — both added here alongside the existing
     * dispatch-planner, not replacing it.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'dispatch-planner', 'marketing', 'accounts', 'supervisor'])
            && $user->hasPermission('dispatch-planning.view');
    }

    public function view(User $user, DispatchPlan $plan): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'dispatch-planner', 'accounts'])
            && $user->hasPermission('dispatch-planning.create');
    }

    public function update(User $user, DispatchPlan $plan): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'dispatch-planner', 'supervisor'])
            && $user->hasPermission('dispatch-planning.edit');
    }

    public function delete(User $user, DispatchPlan $plan): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('dispatch-planning.delete');
    }

    /**
     * Admin, the Dispatch Planner role, or (Dispatch Lifecycle spec)
     * Handling Supervisor can approve/reject — not restricted to the plan's
     * own creator, per the spec's decision that this is a lightweight
     * single-step gate, not a full separate-approver workflow. Supervisor
     * only sees the button at all if actually granted
     * dispatch-planning.approve — "Approve (if permission exists)" per that
     * spec. Only a still-Pending plan can be approved.
     */
    public function approve(User $user, DispatchPlan $plan): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'dispatch-planner', 'supervisor'])
            && $user->hasPermission('dispatch-planning.approve')
            && $plan->approval_status === 'pending';
    }

    /**
     * Unlike approve(), reject() also allows reversing an already-Approved
     * plan (with a reason) — an admin can catch a mistaken approval even
     * after vehicles have been added to it; existing vehicle assignments
     * are left as-is (VehicleAssignmentPolicy::create() already blocks new
     * ones once the plan is no longer Approved). A Rejected plan can't be
     * rejected again.
     */
    public function reject(User $user, DispatchPlan $plan): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'dispatch-planner', 'supervisor'])
            && $user->hasPermission('dispatch-planning.approve')
            && in_array($plan->approval_status, ['pending', 'approved'], true);
    }
}
