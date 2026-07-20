<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'marketing', 'accounts', 'dealer'])
            || $user->hasPermission('bookings.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        if ($user->hasRole('marketing')) {
            return $booking->dealer?->assignment?->marketing_user_id === $user->id;
        }

        if ($user->hasRole('dealer')) {
            return $booking->dealer_id === $user->dealer_id;
        }

        return $user->hasPermission('bookings.view');
    }

    public function create(User $user): bool
    {
        if (! $user->hasPermission('bookings.create')) {
            return false;
        }

        // Dealer never creates bookings — view only.
        return $user->hasRole(['super-admin', 'admin', 'marketing', 'accounts']);
    }

    /**
     * General edit: Marketing/Accounts may only edit while the booking is
     * still a Draft; Admin has full access regardless of status (that's
     * what "Unlock Booking" exists for — to bring a locked booking back
     * within everyone else's reach).
     */
    public function update(User $user, Booking $booking): bool
    {
        if (! $user->hasPermission('bookings.edit')) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        if ($booking->approval_status !== 'draft') {
            return false;
        }

        if ($user->hasRole('marketing')) {
            return $booking->dealer?->assignment?->marketing_user_id === $user->id;
        }

        return $user->hasRole('accounts');
    }

    /**
     * plant_rate and discount are the two fields the spec singles out as
     * Admin-only changes, distinct from the broader Draft-scoped edit above
     * — Marketing/Accounts can edit a draft booking's other fields, but
     * never its pricing.
     */
    public function updatePricing(User $user, Booking $booking): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('bookings.edit');
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('bookings.delete');
    }

    public function restore(User $user, Booking $booking): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('bookings.delete');
    }

    /**
     * Move Draft -> Pending. Same authors as general update.
     */
    public function submit(User $user, Booking $booking): bool
    {
        return $this->update($user, $booking);
    }

    /**
     * Accounts Verification — the checkpoint between Pending and Admin's
     * final Approve. Admin can also verify (the recurring "Admin can do
     * anything" override used throughout this app), but Accounts is the
     * intended actor — and Accounts itself can never reach approve().
     */
    public function verify(User $user, Booking $booking): bool
    {
        if ($booking->approval_status !== 'pending') {
            return false;
        }

        return $user->hasRole(['super-admin', 'admin'])
            || ($user->hasRole('accounts') && $user->hasPermission('bookings.edit'));
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('bookings.approve');
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('bookings.reject');
    }

    public function hold(User $user, Booking $booking): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('bookings.hold');
    }

    public function unlock(User $user, Booking $booking): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('bookings.unlock');
    }

    /**
     * Reuses the existing payments.create permission (from the Accounts
     * permission catalog) rather than inventing a bookings-specific one.
     */
    public function receivePayment(User $user, Booking $booking): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            || ($user->hasRole('accounts') && $user->hasPermission('payments.create'));
    }

    /**
     * Stand-in for a future Dispatch module's own authorization — Admin
     * only for now, and only an Approved booking can ever be dispatched.
     */
    public function updateDispatchStatus(User $user, Booking $booking): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $booking->approval_status === 'approved';
    }
}
