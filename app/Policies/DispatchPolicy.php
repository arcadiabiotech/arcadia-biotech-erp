<?php

namespace App\Policies;

use App\Models\Dispatch;
use App\Models\User;

class DispatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'marketing', 'accounts', 'dealer', 'dispatch'])
            || $user->hasPermission('dispatch.view');
    }

    public function view(User $user, Dispatch $dispatch): bool
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        if ($user->hasRole('marketing')) {
            return $dispatch->lines()->whereHas('dealer.assignment', fn ($q) => $q->where('marketing_user_id', $user->id))->exists();
        }

        if ($user->hasRole('dealer')) {
            return $dispatch->lines()->where('dealer_id', $user->dealer_id)->exists();
        }

        return $user->hasPermission('dispatch.view');
    }

    /**
     * Dispatch can only ever be created from an already-loaded Dispatch
     * Planning vehicle — that per-record check lives in
     * DispatchStoreRequest (which assignment is chosen isn't known at this
     * class-level ability check). Supervisor included so they can carry a
     * vehicle straight from Approve Loading into Dispatch creation without
     * handing off to a separate Dispatch-role user.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'dispatch', 'supervisor']) && $user->hasPermission('dispatch.create');
    }

    /**
     * Dispatch User: "Cannot Edit Completed Dispatch" — extended to also
     * exclude Dispatched/Delivered ("Dispatch Vehicle" is the last step that
     * allows any change — no further modifications past it, per the
     * workflow spec) and Cancelled, which is unlocked back to Draft
     * explicitly rather than edited directly. Admin has full access
     * regardless. Supervisor included for the same reason as create() above.
     */
    public function update(User $user, Dispatch $dispatch): bool
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        if (! $user->hasRole(['dispatch', 'supervisor']) || ! $user->hasPermission('dispatch.edit')) {
            return false;
        }

        return ! in_array($dispatch->status, ['dispatched', 'delivered', 'completed', 'cancelled'], true);
    }

    public function delete(User $user, Dispatch $dispatch): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('dispatch.delete');
    }

    public function restore(User $user, Dispatch $dispatch): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('dispatch.delete');
    }

    public function submit(User $user, Dispatch $dispatch): bool
    {
        return $this->update($user, $dispatch) && $dispatch->status === 'draft';
    }

    public function startLoading(User $user, Dispatch $dispatch): bool
    {
        return $this->canOperate($user, 'dispatch.loading') && $dispatch->status === 'pending';
    }

    public function vehicleOut(User $user, Dispatch $dispatch): bool
    {
        return $this->canOperate($user, 'dispatch.loading') && $dispatch->status === 'loading';
    }

    /**
     * Dispatch Lifecycle spec: Marketing Officer, Dealer, Company Employee
     * (the 'staff' role), Admin, Accounts, or Handling Supervisor may mark
     * a delivery complete — wider than canOperate()'s dispatch/supervisor
     * whitelist used by every other step, so this gets its own scoped
     * check (canDeliver()) rather than broadening canOperate() itself.
     */
    public function markDelivered(User $user, Dispatch $dispatch): bool
    {
        return $this->canDeliver($user, $dispatch) && $dispatch->status === 'dispatched';
    }

    /**
     * Handling Supervisor's "the truck is physically back at the nursery"
     * step — precedes the fuller Return Inspection (recordReturn()) that
     * captures the actual crate/plant breakdown. Same status range as
     * recordReturn(): the vehicle can return whether or not delivery/
     * completion has been marked yet. Also the Step 7A Odometer End /
     * transport-cost entry point — once odometer_end has actually been
     * recorded once, only Admin/Super Admin may come back and correct it
     * ("nothing editable after final save except by Admin"), same
     * admin-only-correction convention as cancel()/unlock() below.
     */
    public function markVehicleReturned(User $user, Dispatch $dispatch): bool
    {
        if (! $this->canOperate($user, 'dispatch.deliver')) {
            return false;
        }

        if (! in_array($dispatch->status, ['dispatched', 'delivered', 'completed'], true)) {
            return false;
        }

        if ($dispatch->odometer_end !== null && ! $user->hasRole(['super-admin', 'admin'])) {
            return false;
        }

        return true;
    }

    public function complete(User $user, Dispatch $dispatch): bool
    {
        return $this->canOperate($user, 'dispatch.complete') && $dispatch->status === 'delivered';
    }

    /**
     * Vehicle/crate return can be recorded (and re-recorded, for partial
     * returns) any time after the vehicle has actually left — i.e. from
     * "dispatched" onward, regardless of whether the dispatch has since
     * been marked delivered/completed.
     */
    public function recordReturn(User $user, Dispatch $dispatch): bool
    {
        return $this->canOperate($user, 'dispatch.deliver')
            && in_array($dispatch->status, ['dispatched', 'delivered', 'completed'], true);
    }

    /**
     * Admin: "Cancel Dispatch".
     */
    public function cancel(User $user, Dispatch $dispatch): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('dispatch.cancel')
            && ! in_array($dispatch->status, ['completed', 'cancelled'], true);
    }

    /**
     * Admin: "Unlock Dispatch".
     */
    public function unlock(User $user, Dispatch $dispatch): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('dispatch.unlock')
            && $dispatch->status === 'cancelled';
    }

    /**
     * Admin always qualifies; the Dispatch role additionally needs the
     * specific workflow permission for the step being performed. Supervisor
     * is included here too, and (per the Dispatch Lifecycle spec) is now
     * also granted dispatch.deliver — so this opens startLoading()/
     * vehicleOut() *and* markVehicleReturned()/recordReturn()/complete()
     * for them, in addition to Dispatch role.
     */
    private function canOperate(User $user, string $permission): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            || ($user->hasRole(['dispatch', 'supervisor']) && $user->hasPermission($permission));
    }

    /**
     * The wider role set for markDelivered() only: admin/dispatch/
     * supervisor/accounts/staff ("Company Employee") qualify outright once
     * permitted; marketing and dealer are additionally scoped to their own
     * dealer's/assigned dealers' dispatches, same convention as view().
     */
    private function canDeliver(User $user, Dispatch $dispatch): bool
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        if (! $user->hasPermission('dispatch.deliver')) {
            return false;
        }

        if ($user->hasRole(['dispatch', 'supervisor', 'accounts', 'staff'])) {
            return true;
        }

        if ($user->hasRole('marketing')) {
            return $dispatch->lines()->whereHas('dealer.assignment', fn ($q) => $q->where('marketing_user_id', $user->id))->exists();
        }

        if ($user->hasRole('dealer')) {
            return $dispatch->lines()->where('dealer_id', $user->dealer_id)->exists();
        }

        return false;
    }
}
