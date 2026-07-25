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
     * Booking Cancelled / Rejected -> Release Reservation, or (with $qty) a
     * farmer rejecting part of a delivered shipment -> the rejected portion
     * goes back to available stock. $status ('cancelled' or 'released')
     * records which of the two triggered a full release; a reservation that
     * has ever had anything converted stays 'converted' once fully
     * resolved, since some of the booking genuinely did ship.
     */
    public function release(Booking $booking, string $status, ?int $actorId = null, ?int $qty = null): void
    {
        DB::transaction(function () use ($booking, $status, $actorId, $qty) {
            $reservation = StockReservation::where('booking_id', $booking->id)->lockForUpdate()->first();

            if (! $reservation || $reservation->status !== 'reserved') {
                return;
            }

            $qty = min($qty ?? $reservation->reserved_qty, $reservation->reserved_qty);

            $original = $reservation->toArray();

            $reservation->reserved_qty -= $qty;
            $reservation->released_qty += $qty;

            if ($reservation->reserved_qty <= 0) {
                $reservation->status = $reservation->converted_qty > 0 ? 'converted' : $status;
            }

            $reservation->updated_by = $actorId;
            $reservation->save();

            ActivityLog::record('stock_reservations', $reservation->id, 'update', $original, $reservation->fresh()->toArray(), ucfirst($status)." {$qty} — ".$booking->booking_no);
        });
    }

    /**
     * Dispatch Completed -> Convert Reservation into an actual stock issue.
     * $qty defaults to the reservation's full remaining amount (the
     * original one-shot behavior); passing a smaller $qty converts only
     * that much, leaving the rest of the reservation active — the case
     * where a farmer rejects part of a delivered line and only the
     * accepted portion is converted to a real stock deduction.
     */
    public function convert(Booking $booking, ?int $qty = null, ?int $actorId = null): void
    {
        DB::transaction(function () use ($booking, $qty, $actorId) {
            $reservation = StockReservation::where('booking_id', $booking->id)->lockForUpdate()->first();

            if (! $reservation) {
                throw ValidationException::withMessages(['dispatch_status' => 'This booking has no stock reservation to convert.']);
            }

            if ($reservation->status !== 'reserved') {
                throw ValidationException::withMessages(['dispatch_status' => 'Only an active reservation can be converted.']);
            }

            $qty ??= $reservation->reserved_qty;

            if ($qty > $reservation->reserved_qty) {
                throw ValidationException::withMessages(['dispatch_status' => "Cannot convert {$qty} plants — only {$reservation->reserved_qty} still reserved for this booking."]);
            }

            $stock = $this->lockedStock($reservation->variety);
            $stock->update([
                'actual_qty' => max(0, $stock->actual_qty - $qty),
                'updated_by' => $actorId,
            ]);

            $original = $reservation->toArray();

            $reservation->reserved_qty -= $qty;
            $reservation->converted_qty += $qty;

            if ($reservation->reserved_qty <= 0) {
                $reservation->status = 'converted';
            }

            $reservation->updated_by = $actorId;
            $reservation->save();

            ActivityLog::record('stock_reservations', $reservation->id, 'update', $original, $reservation->fresh()->toArray(), "Converted {$qty} to stock issue — ".$booking->booking_no);
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
