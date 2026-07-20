<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\StockReservation;
use App\Models\VarietyStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Stock Reservation engine: reserves plants against a variety's actual
 * stock when a booking is created, keeps the reservation in sync when the
 * booking is edited, releases it if the booking is cancelled or rejected,
 * and converts it into a real stock deduction once dispatch completes.
 *
 * Every write here runs inside a transaction with the relevant row(s)
 * locked (SELECT ... FOR UPDATE), so two concurrent bookings against the
 * same variety can never both succeed past the same available-stock ceiling,
 * and a concurrent edit/release/convert on the same reservation can't race.
 */
class StockReservationService
{
    public function reserve(Booking $booking): StockReservation
    {
        return DB::transaction(function () use ($booking) {
            $stock = $this->lockedStock($booking->variety);

            $this->assertAvailable($stock, $booking->plant_qty, excludingReservedQty: 0);

            $reservation = StockReservation::create([
                'booking_id' => $booking->id,
                'dealer_id' => $booking->dealer_id,
                'farmer_id' => $booking->farmer_id,
                'variety' => $booking->variety,
                'reserved_qty' => $booking->plant_qty,
                'status' => 'reserved',
                'created_by' => $booking->created_by,
            ]);

            ActivityLog::record('stock_reservations', $reservation->id, 'create', [], $reservation->toArray(), 'Reserved for '.$booking->booking_no);

            return $reservation;
        });
    }

    /**
     * Booking Edited -> Update Reservation. Only touches a reservation
     * that's still active — one already released/converted is a closed
     * chapter an unrelated booking edit shouldn't reopen.
     */
    public function updateReservation(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $reservation = StockReservation::where('booking_id', $booking->id)->lockForUpdate()->first();

            if (! $reservation || $reservation->status !== 'reserved') {
                return;
            }

            $stock = $this->lockedStock($booking->variety);
            $excluding = $reservation->variety === $booking->variety ? $reservation->reserved_qty : 0;
            $this->assertAvailable($stock, $booking->plant_qty, $excluding);

            $original = $reservation->toArray();

            $reservation->update([
                'dealer_id' => $booking->dealer_id,
                'farmer_id' => $booking->farmer_id,
                'variety' => $booking->variety,
                'reserved_qty' => $booking->plant_qty,
                'updated_by' => $booking->updated_by,
            ]);

            ActivityLog::record('stock_reservations', $reservation->id, 'update', $original, $reservation->fresh()->toArray(), 'Updated for '.$booking->booking_no);
        });
    }

    /**
     * Booking Cancelled / Rejected -> Release Reservation. $status
     * ('cancelled' or 'released') records which of the two triggered it.
     */
    public function release(Booking $booking, string $status, ?int $actorId = null): void
    {
        DB::transaction(function () use ($booking, $status, $actorId) {
            $reservation = StockReservation::where('booking_id', $booking->id)->lockForUpdate()->first();

            if (! $reservation || $reservation->status !== 'reserved') {
                return;
            }

            $original = $reservation->toArray();

            $reservation->update([
                'released_qty' => $reservation->reserved_qty,
                'status' => $status,
                'updated_by' => $actorId,
            ]);

            ActivityLog::record('stock_reservations', $reservation->id, 'update', $original, $reservation->fresh()->toArray(), ucfirst($status).' — '.$booking->booking_no);
        });
    }

    /**
     * Dispatch Completed -> Convert Reservation into an actual stock issue.
     */
    public function convert(Booking $booking, ?int $actorId = null): void
    {
        DB::transaction(function () use ($booking, $actorId) {
            $reservation = StockReservation::where('booking_id', $booking->id)->lockForUpdate()->first();

            if (! $reservation) {
                throw ValidationException::withMessages(['dispatch_status' => 'This booking has no stock reservation to convert.']);
            }

            if ($reservation->status !== 'reserved') {
                throw ValidationException::withMessages(['dispatch_status' => 'Only an active reservation can be converted.']);
            }

            $stock = $this->lockedStock($reservation->variety);
            $stock->update([
                'actual_qty' => max(0, $stock->actual_qty - $reservation->reserved_qty),
                'updated_by' => $actorId,
            ]);

            $original = $reservation->toArray();
            $reservation->update(['status' => 'converted', 'updated_by' => $actorId]);

            ActivityLog::record('stock_reservations', $reservation->id, 'update', $original, $reservation->fresh()->toArray(), 'Converted to stock issue — '.$booking->booking_no);
        });
    }

    public function availableStock(string $variety): int
    {
        VarietyStock::firstOrCreate(['variety' => $variety], ['actual_qty' => 0]);

        return VarietyStock::where('variety', $variety)->first()->availableQty();
    }

    /**
     * Ensures the variety's stock row exists, then re-reads it with a
     * row lock inside the caller's transaction.
     */
    private function lockedStock(string $variety): VarietyStock
    {
        VarietyStock::firstOrCreate(['variety' => $variety], ['actual_qty' => 0]);

        return VarietyStock::where('variety', $variety)->lockForUpdate()->first();
    }

    private function assertAvailable(VarietyStock $stock, int $requestedQty, int $excludingReservedQty): void
    {
        $activeReserved = StockReservation::where('variety', $stock->variety)->where('status', 'reserved')->sum('reserved_qty');
        $available = $stock->actual_qty - ($activeReserved - $excludingReservedQty);

        if ($requestedQty > $available) {
            throw ValidationException::withMessages([
                'plant_qty' => "Insufficient stock for {$stock->variety}. Available: {$available}, requested: {$requestedQty}.",
            ]);
        }
    }
}
