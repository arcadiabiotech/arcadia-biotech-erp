<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Owns booking_no generation and every approval_status/payment_status
 * transition. Centralizing the status machine here — rather than spreading
 * "can this move from X to Y" checks across the controller — is what keeps
 * BookingController thin and makes the workflow rules auditable in one place.
 */
class BookingService
{
    /**
     * BK-YYYY-000001, sequence resets each calendar year. withTrashed()
     * avoids reissuing a number that belongs to a soft-deleted booking.
     */
    public function nextBookingNo(): string
    {
        $prefix = 'BK-'.now()->year.'-';

        $maxNumber = Booking::withTrashed()
            ->where('booking_no', 'like', "{$prefix}%")
            ->selectRaw('MAX(CAST(SUBSTRING(booking_no, ?) AS UNSIGNED)) as max_number', [strlen($prefix) + 1])
            ->value('max_number');

        return $prefix.str_pad(((int) $maxNumber) + 1, 6, '0', STR_PAD_LEFT);
    }

    public function submit(Booking $booking): void
    {
        $this->assertStatus($booking, 'draft', 'submitted for approval');

        $original = $booking->approval_status;
        $booking->approval_status = 'pending';
        $booking->save();

        ActivityLog::record('bookings', $booking->id, 'update', ['approval_status' => $original], ['approval_status' => 'pending'], 'Submitted for approval');
    }

    /**
     * Accounts Verification — the gate between Pending and Admin Approval.
     * Accounts signs off that a booking is correct before it can ever reach
     * Admin's final approval; Accounts itself can never approve.
     */
    public function verify(Booking $booking, User $actor, ?string $remarks = null): void
    {
        $this->assertStatus($booking, 'pending', 'verified');

        $booking->approval_status = 'verified';
        $booking->save();

        ActivityLog::record('bookings', $booking->id, 'update', ['approval_status' => 'pending'], ['approval_status' => 'verified'], $remarks ?: 'Verified by Accounts');
    }

    public function approve(Booking $booking, User $actor, ?string $remarks = null): void
    {
        $this->assertStatus($booking, 'verified', 'approved');

        $booking->approval_status = 'approved';
        $booking->approved_by = $actor->id;
        $booking->save();

        ActivityLog::record('bookings', $booking->id, 'approve', ['approval_status' => 'verified'], ['approval_status' => 'approved'], $remarks);
    }

    /**
     * Admin may reject at either checkpoint — before Accounts verifies, or
     * after, overriding a verification already given.
     */
    public function reject(Booking $booking, User $actor, ?string $remarks = null): void
    {
        if (! in_array($booking->approval_status, ['pending', 'verified'], true)) {
            throw ValidationException::withMessages(['approval_status' => 'Only a pending or verified booking can be rejected.']);
        }

        $original = $booking->approval_status;
        $booking->approval_status = 'rejected';
        $booking->approved_by = $actor->id;
        $booking->save();

        ActivityLog::record('bookings', $booking->id, 'reject', ['approval_status' => $original], ['approval_status' => 'rejected'], $remarks);
    }

    public function hold(Booking $booking, ?string $remarks = null): void
    {
        if (! in_array($booking->approval_status, ['pending', 'verified', 'approved'], true)) {
            throw ValidationException::withMessages(['approval_status' => 'Only a pending, verified or approved booking can be put on hold.']);
        }

        $original = $booking->approval_status;
        $booking->approval_status = 'hold';
        $booking->save();

        ActivityLog::record('bookings', $booking->id, 'hold', ['approval_status' => $original], ['approval_status' => 'hold'], $remarks);
    }

    /**
     * Brings a locked (approved/rejected/hold) booking back to Draft so
     * Marketing/Accounts can edit it again — this is the mechanism behind
     * the Admin-only "Unlock Booking" capability.
     */
    public function unlock(Booking $booking, ?string $remarks = null): void
    {
        if (! in_array($booking->approval_status, ['approved', 'rejected', 'hold'], true)) {
            throw ValidationException::withMessages(['approval_status' => 'Only an approved, rejected or held booking can be unlocked.']);
        }

        $original = $booking->approval_status;
        $booking->approval_status = 'draft';
        $booking->save();

        ActivityLog::record('bookings', $booking->id, 'unlock', ['approval_status' => $original], ['approval_status' => 'draft'], $remarks);
    }

    public function receivePayment(Booking $booking, User $actor, float $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }

        if ($amount > (float) $booking->balance_amount) {
            throw ValidationException::withMessages(['amount' => 'Payment amount cannot exceed the outstanding balance.']);
        }

        $original = ['advance_amount' => (string) $booking->advance_amount, 'payment_status' => $booking->payment_status];

        $booking->advance_amount = round((float) $booking->advance_amount + $amount, 2);
        $booking->accounts_user_id ??= $actor->id;
        $booking->payment_status = $booking->advance_amount >= $booking->booking_amount ? 'completed' : 'partial';
        $booking->save();

        ActivityLog::record(
            'bookings',
            $booking->id,
            'update',
            $original,
            ['advance_amount' => (string) $booking->advance_amount, 'payment_status' => $booking->payment_status],
            'Payment received: ₹'.number_format($amount, 2)
        );
    }

    private function assertStatus(Booking $booking, string $expected, string $actionDescription): void
    {
        if ($booking->approval_status !== $expected) {
            throw ValidationException::withMessages([
                'approval_status' => "Only a {$expected} booking can be {$actionDescription}.",
            ]);
        }
    }
}
