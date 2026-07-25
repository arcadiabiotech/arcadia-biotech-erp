<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Dispatch;
use App\Models\DispatchLine;
use App\Models\User;
use App\Models\VehicleAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns dispatch_no/challan_no generation, creating a Dispatch out of an
 * already-loaded Dispatch Planning vehicle, and every status transition in
 * the Draft -> Pending -> Loading -> Dispatched -> Delivered -> Completed
 * lifecycle (plus Cancel/Unlock). Centralizing the state machine here keeps
 * DispatchController thin, the same way BookingService does for Booking.
 */
class DispatchService
{
    public function __construct(
        private readonly StockReservationService $reservations,
        private readonly ChallanService $challans,
        private readonly DispatchPlanningService $planning,
    ) {}

    /**
     * One-click "Vehicle Loaded" action for the Dispatches index page:
     * marks every booking on the vehicle loaded, approves loading (the
     * existing per-item review flow in Dispatch Planning stays available as
     * a manual alternative — this just collapses it into a single step),
     * creates the Dispatch for the vehicle's full remaining quantity, and
     * carries it straight through to "loading" so the challan is generated
     * immediately. Everything happens in one transaction so a mid-way
     * failure never leaves the vehicle half-loaded with no Dispatch, or a
     * Dispatch with no challan.
     */
    public function loadVehicle(VehicleAssignment $assignment, User $actor): Dispatch
    {
        return DB::transaction(function () use ($assignment, $actor) {
            $assignment = VehicleAssignment::with('items')->lockForUpdate()->findOrFail($assignment->id);

            if ($assignment->items->isEmpty()) {
                throw ValidationException::withMessages(['loading' => 'Add at least one booking to this vehicle before marking it loaded.']);
            }

            foreach ($assignment->items as $item) {
                if (! $item->isLoaded()) {
                    $this->planning->toggleLoaded($item, $actor, true);
                }
            }

            $assignment = $assignment->fresh(['items', 'loading']);

            if ($assignment->loading_status !== 'completed') {
                $this->planning->approveLoading($assignment, $actor);
            }

            $lineInputs = [];
            foreach ($assignment->items as $item) {
                if ($item->remaining_qty > 0) {
                    $lineInputs[$item->id] = ['qty' => $item->remaining_qty];
                }
            }

            $dispatch = $this->createFromAssignment($assignment, $lineInputs, $actor);

            $this->submit($dispatch);
            $this->startLoading($dispatch, $actor);

            return $dispatch->fresh(['lines']);
        });
    }

    /**
     * VehicleAssignments a Dispatch can be created from: loading completed
     * and at least one planned item still has quantity left to dispatch
     * (repeatable — the same assignment can be picked again for a later
     * partial Dispatch). Role-scoped the same way
     * DispatchPlanningService::eligibleBookings() scopes marketing users to
     * their own assigned dealers.
     */
    public function eligibleAssignments(User $user)
    {
        return VehicleAssignment::query()
            ->whereHas('loading', fn ($q) => $q->where('status', 'completed'))
            ->with(['vehicle', 'dispatchPlan', 'items.booking.dealer', 'items.booking.farmer', 'items.dispatchLines'])
            ->when($user->hasRole('marketing'), fn ($q) => $q->whereHas('items.booking.dealer.assignment', fn ($qq) => $qq->where('marketing_user_id', $user->id)))
            ->get()
            ->filter(fn (VehicleAssignment $assignment) => $assignment->items->contains(fn ($item) => $item->remaining_qty > 0))
            ->values();
    }

    /**
     * Creates one Dispatch header for the vehicle trip plus one DispatchLine
     * per selected planned item (skipping items with no/zero qty selected —
     * this is what allows a vehicle's plan to be fulfilled across more than
     * one Dispatch). $lineInputs is keyed by dispatch_plan_item_id:
     * ['qty' => int, 'extra_qty' => int, 'qty_per_crate' => ?int,
     * 'batch_number' => ?string, 'plant_age' => ?string, 'remarks' => ?string].
     */
    public function createFromAssignment(VehicleAssignment $assignment, array $lineInputs, User $actor): Dispatch
    {
        return DB::transaction(function () use ($assignment, $lineInputs, $actor) {
            $dispatch = Dispatch::create([
                'dispatch_no' => $this->nextDispatchNo(),
                'vehicle_assignment_id' => $assignment->id,
                'vehicle_id' => $assignment->vehicle_id,
                'driver_name' => $assignment->driver_name,
                'driver_mobile' => $assignment->driver_mobile,
                'dispatch_date' => now()->toDateString(),
                'status' => 'draft',
                'odometer_start' => $assignment->start_km,
                'cost_per_km' => $assignment->vehicle->cost_per_km,
                'driver_allowance' => $assignment->vehicle->driver_allowance,
                'created_by' => $actor->id,
            ]);

            $itemsById = $assignment->items->keyBy('id');

            foreach ($lineInputs as $itemId => $input) {
                $qty = (int) ($input['qty'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                $item = $itemsById->get((int) $itemId);

                if (! $item) {
                    continue;
                }

                if ($qty > $item->remaining_qty) {
                    throw ValidationException::withMessages([
                        'lines' => "Dispatch quantity for {$item->booking->booking_no} cannot exceed the remaining {$item->remaining_qty} plants.",
                    ]);
                }

                $booking = $item->booking;
                $extraQty = (int) ($input['extra_qty'] ?? 0);

                DispatchLine::create([
                    'dispatch_id' => $dispatch->id,
                    'dispatch_plan_item_id' => $item->id,
                    'booking_id' => $booking->id,
                    'dealer_id' => $booking->dealer_id,
                    'farmer_id' => $booking->farmer_id,
                    'dispatch_qty' => $qty,
                    'extra_qty' => $extraQty,
                    'qty_per_crate' => $input['qty_per_crate'] ?? null,
                    'batch_number' => $input['batch_number'] ?? null,
                    'plant_age' => $input['plant_age'] ?? null,
                    'remaining_qty' => max(0, $item->remaining_qty - $qty),
                    'remarks' => $input['remarks'] ?? null,
                    'created_by' => $actor->id,
                ]);
            }

            if ($dispatch->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => 'Select a quantity for at least one booking.']);
            }

            ActivityLog::record('dispatches', $dispatch->id, 'create', [], $dispatch->fresh('lines')->toArray(), "Created from Dispatch Plan {$assignment->dispatchPlan->plan_no}");

            return $dispatch;
        });
    }

    /**
     * DIS-YYYY-000001, sequence resets each calendar year. withTrashed()
     * avoids reissuing a number that belongs to a soft-deleted dispatch.
     */
    public function nextDispatchNo(): string
    {
        $prefix = 'DIS-'.now()->year.'-';

        $maxNumber = Dispatch::withTrashed()
            ->where('dispatch_no', 'like', "{$prefix}%")
            ->selectRaw('MAX(CAST(SUBSTRING(dispatch_no, ?) AS UNSIGNED)) as max_number', [strlen($prefix) + 1])
            ->value('max_number');

        return $prefix.str_pad(((int) $maxNumber) + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * CHN-YYYY-000001 — the dispatch's own challan number, issued by
     * startLoading() once loading begins, not at Dispatch creation.
     */
    public function nextChallanNo(): string
    {
        $prefix = 'CHN-'.now()->year.'-';

        $maxNumber = Dispatch::withTrashed()
            ->where('challan_no', 'like', "{$prefix}%")
            ->selectRaw('MAX(CAST(SUBSTRING(challan_no, ?) AS UNSIGNED)) as max_number', [strlen($prefix) + 1])
            ->value('max_number');

        return $prefix.str_pad(((int) $maxNumber) + 1, 6, '0', STR_PAD_LEFT);
    }

    public function submit(Dispatch $dispatch): void
    {
        $this->transition($dispatch, 'draft', 'pending', 'Submitted for loading');
    }

    /**
     * Dispatch User: "Can Update Loading Status" — step 1, goods are being
     * loaded onto the vehicle. Requires a vehicle to already be assigned.
     * This is also the moment the challan is issued — challan_no is only
     * ever generated here, once loading actually starts, not at Dispatch
     * creation and not held back for the vehicle to physically leave. Same
     * moment ChallanService splits any 2+-farmer dealer into a Master
     * Dealer Challan + one Farmer Challan each (see its own docblock).
     */
    public function startLoading(Dispatch $dispatch, User $actor): void
    {
        if (! $dispatch->vehicle_id) {
            throw ValidationException::withMessages(['vehicle_id' => 'Assign a vehicle before starting loading.']);
        }

        $this->transition($dispatch, 'pending', 'loading', 'Loading started');

        $original = $dispatch->only('challan_no', 'loaded_at', 'loaded_by');

        $dispatch->challan_no = $this->nextChallanNo();
        $dispatch->loaded_at = now();
        $dispatch->loaded_by = $actor->id;
        $dispatch->save();

        $this->challans->generateForDispatch($dispatch, $actor);

        ActivityLog::record('dispatches', $dispatch->id, 'update', $original, $dispatch->fresh()->only('challan_no', 'loaded_at', 'loaded_by'), 'Vehicle loaded — challan issued');
    }

    /**
     * Dispatch User: "Can Update Loading Status" — step 2 ("Dispatch
     * Vehicle"), the vehicle has physically left with the goods already
     * loaded and its challan already issued by startLoading() above. No
     * further modifications are allowed past this point — DispatchPolicy::
     * update() already excludes every status past "loading" other than
     * cancel/unlock.
     */
    public function vehicleOut(Dispatch $dispatch, User $actor): void
    {
        $this->transition($dispatch, 'loading', 'dispatched', 'Vehicle dispatched');

        $original = $dispatch->only('dispatched_at', 'dispatched_by');

        $dispatch->dispatched_at = now();
        $dispatch->dispatched_by = $actor->id;
        $dispatch->save();

        ActivityLog::record('dispatches', $dispatch->id, 'update', $original, $dispatch->fresh()->only('dispatched_at', 'dispatched_by'), 'Vehicle dispatched');
    }

    /**
     * Dispatch User: "Can Mark Delivered" — also open, per the Dispatch
     * Lifecycle spec, to Marketing/Dealer/Company Employee/Accounts/
     * Handling Supervisor (DispatchPolicy::canDeliver()). Records, per
     * line, how many of the shipped plants the farmer actually accepted vs.
     * rejected — a farmer rejecting some (or all) of a line never blocks
     * the Dispatch from being marked delivered. $lineDeliveries is keyed by
     * dispatch_line_id: ['accepted_qty' => int, 'rejected_qty' => int,
     * 'rejection_reason' => ?string]; a line missing from the array (the
     * common case — nothing rejected) defaults to fully accepted.
     * $deliveryDetails: ['delivery_location' => ?string, 'receiver_name' =>
     * ?string, 'receiver_mobile' => ?string, 'receiver_remarks' => ?string]
     * — who actually received the shipment and where, captured alongside
     * the existing date-only actual_delivery_date via a precise delivered_at.
     */
    public function markDelivered(Dispatch $dispatch, array $lineDeliveries, int $actorId, array $deliveryDetails = []): void
    {
        if ($dispatch->status !== 'dispatched') {
            throw ValidationException::withMessages(['status' => 'Only a dispatched shipment can be marked delivered.']);
        }

        DB::transaction(function () use ($dispatch, $lineDeliveries, $actorId, $deliveryDetails) {
            foreach ($dispatch->lines as $line) {
                $input = $lineDeliveries[$line->id] ?? [];
                $accepted = array_key_exists('accepted_qty', $input) ? (int) $input['accepted_qty'] : $line->total_qty;
                $rejected = (int) ($input['rejected_qty'] ?? 0);

                if ($accepted + $rejected !== $line->total_qty) {
                    throw ValidationException::withMessages([
                        "lines.{$line->id}.accepted_qty" => "Accepted + rejected must equal the {$line->total_qty} plants shipped for {$line->booking->booking_no}.",
                    ]);
                }

                $line->update([
                    'accepted_qty' => $accepted,
                    'rejected_qty' => $rejected,
                    'rejection_reason' => $rejected > 0 ? ($input['rejection_reason'] ?? null) : null,
                    'updated_by' => $actorId,
                ]);
            }

            $trackedFields = ['status', 'actual_delivery_date', 'delivery_location', 'receiver_name', 'receiver_mobile', 'receiver_remarks', 'delivered_at', 'delivered_by'];
            $original = $dispatch->only($trackedFields);

            $dispatch->status = 'delivered';
            $dispatch->actual_delivery_date = now()->toDateString();
            $dispatch->delivery_location = $deliveryDetails['delivery_location'] ?? null;
            $dispatch->receiver_name = $deliveryDetails['receiver_name'] ?? null;
            $dispatch->receiver_mobile = $deliveryDetails['receiver_mobile'] ?? null;
            $dispatch->receiver_remarks = $deliveryDetails['receiver_remarks'] ?? null;
            $dispatch->delivered_at = now();
            $dispatch->delivered_by = $actorId;
            $dispatch->updated_by = $actorId;
            $dispatch->save();

            ActivityLog::record('dispatches', $dispatch->id, 'update', $original, $dispatch->fresh()->only($trackedFields), 'Delivery completed');
        });
    }

    /**
     * Complete Dispatch -> Reduce Actual Stock -> Reservation Converted ->
     * Booking Dispatch Status recomputed, for every booking on this
     * shipment. The one place stock actually moves from Reserved to Actual
     * Stock Out — only for each line's accepted_qty; any rejected_qty is
     * released back to available stock instead, since those plants never
     * left the farmer's hands as a real sale.
     *
     * A booking can be split across more than one Dispatch (partial
     * dispatch), so its status is never unconditionally "completed" here —
     * re-queried fresh (with its full dispatchLines sum) after this line's
     * conversion: balance still > 0 means another Dispatch is still owed for
     * this booking ("partial"), balance 0 means every planned plant has now
     * actually shipped ("completed" — i.e. Fully Dispatched).
     */
    public function complete(Dispatch $dispatch, int $actorId): void
    {
        if ($dispatch->status !== 'delivered') {
            throw ValidationException::withMessages(['status' => 'Only a delivered shipment can be completed.']);
        }

        $original = $dispatch->only('status');

        foreach ($dispatch->lines as $line) {
            $accepted = $line->accepted_qty ?? $line->total_qty;
            $rejected = $line->rejected_qty ?? 0;

            if ($accepted > 0) {
                $this->reservations->convert($line->booking, $accepted, $actorId);
            }

            if ($rejected > 0) {
                $this->reservations->release($line->booking, 'released', $actorId, $rejected);
            }

            $booking = Booking::with('dispatchLines:id,booking_id,dispatch_qty')->find($line->booking_id);
            $booking?->update([
                'dispatch_status' => $booking->balance_qty > 0 ? 'partial' : 'completed',
                'updated_by' => $actorId,
            ]);
        }

        $dispatch->status = 'completed';
        $dispatch->updated_by = $actorId;
        $dispatch->save();

        ActivityLog::record('dispatches', $dispatch->id, 'update', $original, $dispatch->fresh()->only('status'), 'Dispatch completed — stock reservation converted');
    }

    /**
     * Dispatch Lifecycle spec Step 6 "Vehicle Returned" — the "the truck is
     * physically back" event, distinct from (and a precondition to, in the
     * UI) the fuller Return Inspection recorded by recordReturn() below.
     * Also the Step 7A Odometer End / transport-cost entry point: Total KM =
     * Odometer End - Odometer Start; Transport Cost = Total KM * Cost Per
     * KM (snapshot copied from the vehicle at Dispatch creation, see
     * createFromAssignment()); Total Transport Expense = Transport Cost +
     * Driver Allowance + Toll Charges + Other Expenses. odometer_start being
     * null (a legacy dispatch, or one whose assignment never captured
     * start_km) means the cost fields are left null rather than computed
     * off a missing baseline — vehicle_returned_by/odometer_end still save.
     * Sets vehicle_returned_at to today if it isn't already set — needed so
     * the Transport Cost Report/dashboard cards (which key off that column)
     * reflect the trip right away rather than waiting on the separate,
     * optional Return Inspection step; recordReturn() can still overwrite it
     * later with a corrected date. DispatchPolicy::markVehicleReturned()
     * locks this to Admin-only once odometer_end has already been recorded
     * once.
     */
    public function markVehicleReturned(
        Dispatch $dispatch,
        User $actor,
        int $odometerEnd,
        ?float $tollCharges = null,
        ?float $otherExpenses = null,
        ?float $driverAllowanceOverride = null,
    ): void {
        DB::transaction(function () use ($dispatch, $actor, $odometerEnd, $tollCharges, $otherExpenses, $driverAllowanceOverride) {
            if ($dispatch->odometer_start !== null && $odometerEnd < $dispatch->odometer_start) {
                throw ValidationException::withMessages([
                    'odometer_end' => "Odometer End ({$odometerEnd}) cannot be less than Odometer Start ({$dispatch->odometer_start}).",
                ]);
            }

            $trackedFields = ['vehicle_returned_by', 'vehicle_returned_at', 'odometer_end', 'total_km', 'cost_per_km', 'transport_cost', 'driver_allowance', 'toll_charges', 'other_expenses', 'total_transport_expense'];
            $original = $dispatch->only($trackedFields);

            $dispatch->vehicle_returned_by = $actor->id;
            $dispatch->vehicle_returned_at ??= now()->toDateString();
            $dispatch->odometer_end = $odometerEnd;

            if ($dispatch->odometer_start !== null) {
                $costPerKm = (float) ($dispatch->cost_per_km ?? 0);
                $driverAllowance = $driverAllowanceOverride ?? (float) ($dispatch->driver_allowance ?? 0);
                $toll = $tollCharges ?? 0.0;
                $other = $otherExpenses ?? 0.0;
                $totalKm = $odometerEnd - $dispatch->odometer_start;
                $transportCost = round($totalKm * $costPerKm, 2);

                $dispatch->total_km = $totalKm;
                $dispatch->transport_cost = $transportCost;
                $dispatch->driver_allowance = $driverAllowance;
                $dispatch->toll_charges = $toll;
                $dispatch->other_expenses = $other;
                $dispatch->total_transport_expense = round($transportCost + $driverAllowance + $toll + $other, 2);
            }

            $dispatch->updated_by = $actor->id;
            $dispatch->save();

            ActivityLog::record('dispatches', $dispatch->id, 'update', $original, $dispatch->fresh()->only($trackedFields), 'Vehicle returned — transport cost recorded');
        });
    }

    /**
     * Records the vehicle's return trip — the date the vehicle itself
     * returned lives on the Dispatch header (the truck returns once), while
     * crates/plants received back are recorded per line (cumulative, so a
     * partial return can be topped up later) since a multi-dealer Dispatch
     * reconciles crates per booking/dealer, not for the vehicle as a whole.
     * $lines is keyed by dispatch_line_id => ['returned_qty', 'damage_qty',
     * 'missing_qty', 'broken_qty', 'dead_plant_qty', 'extra_returned_qty'].
     * A damaged/missing/broken crate is accounted for but not "returned" —
     * it's written off, so returned + damaged + missing + broken together
     * can never exceed what was sent. $details: ['damage_remarks',
     * 'driver_remarks', 'supervisor_remarks', 'return_photos' => array of
     * stored file paths] — the Return Inspection form's header-level
     * fields, per the Dispatch Lifecycle spec's Step 7. Independent of the
     * dispatch's own status progression: the shipment can already be
     * "completed" while crates are still being returned in batches.
     */
    public function recordReturn(Dispatch $dispatch, array $lines, string $vehicleReturnedAt, ?string $remarks, int $actorId, array $details = []): void
    {
        DB::transaction(function () use ($dispatch, $lines, $vehicleReturnedAt, $remarks, $actorId, $details) {
            $linesById = $dispatch->lines->keyBy('id');

            foreach ($lines as $lineId => $entry) {
                $line = $linesById->get((int) $lineId);

                if (! $line) {
                    continue;
                }

                $returned = isset($entry['returned_qty']) && $entry['returned_qty'] !== ''
                    ? (float) $entry['returned_qty']
                    : (float) ($line->crates_returned ?? 0);

                $damage = isset($entry['damage_qty']) && $entry['damage_qty'] !== ''
                    ? (float) $entry['damage_qty']
                    : (float) ($line->damage_qty ?? 0);

                $missing = isset($entry['missing_qty']) && $entry['missing_qty'] !== ''
                    ? (float) $entry['missing_qty']
                    : (float) ($line->missing_qty ?? 0);

                $broken = isset($entry['broken_qty']) && $entry['broken_qty'] !== ''
                    ? (float) $entry['broken_qty']
                    : (float) ($line->broken_qty ?? 0);

                $deadPlants = isset($entry['dead_plant_qty']) && $entry['dead_plant_qty'] !== ''
                    ? (int) $entry['dead_plant_qty']
                    : (int) ($line->dead_plant_qty ?? 0);

                $extraReturned = isset($entry['extra_returned_qty']) && $entry['extra_returned_qty'] !== ''
                    ? (int) $entry['extra_returned_qty']
                    : (int) ($line->extra_returned_qty ?? 0);

                if ($line->crate_count !== null && ($returned + $damage + $missing + $broken) > $line->crate_count) {
                    throw ValidationException::withMessages([
                        'crates_returned' => "Returned ({$returned}) plus damaged ({$damage}), missing ({$missing}) and broken ({$broken}) crates cannot exceed the {$line->crate_count} crates sent for {$line->booking->booking_no}.",
                    ]);
                }

                $line->update([
                    'crates_returned' => $returned,
                    'damage_qty' => $damage,
                    'missing_qty' => $missing,
                    'broken_qty' => $broken,
                    'dead_plant_qty' => $deadPlants,
                    'extra_returned_qty' => $extraReturned,
                    'updated_by' => $actorId,
                ]);
            }

            $trackedFields = ['vehicle_returned_at', 'return_remarks', 'damage_remarks', 'driver_remarks', 'supervisor_remarks', 'return_photos'];
            $original = $dispatch->only($trackedFields);

            $dispatch->vehicle_returned_at = $vehicleReturnedAt;
            $dispatch->return_remarks = $remarks;
            $dispatch->damage_remarks = $details['damage_remarks'] ?? null;
            $dispatch->driver_remarks = $details['driver_remarks'] ?? null;
            $dispatch->supervisor_remarks = $details['supervisor_remarks'] ?? null;
            $dispatch->return_photos = $details['return_photos'] ?? null;
            $dispatch->updated_by = $actorId;
            $dispatch->save();

            ActivityLog::record('dispatches', $dispatch->id, 'update', $original, $dispatch->fresh()->only($trackedFields), 'Return inspection recorded');
        });
    }

    /**
     * Admin-only. Reason Required Before Cancel — stored in remarks since
     * there is no dedicated cancel_reason column.
     */
    public function cancel(Dispatch $dispatch, string $reason, int $actorId): void
    {
        if (in_array($dispatch->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'A completed or already-cancelled dispatch cannot be cancelled.']);
        }

        $original = $dispatch->only('status', 'remarks');

        $dispatch->status = 'cancelled';
        $dispatch->remarks = trim(($dispatch->remarks ? $dispatch->remarks."\n" : '')."Cancelled: {$reason}");
        $dispatch->updated_by = $actorId;
        $dispatch->save();

        ActivityLog::record('dispatches', $dispatch->id, 'update', $original, $dispatch->fresh()->only('status', 'remarks'), "Cancelled — {$reason}");
    }

    /**
     * Admin-only "Unlock Dispatch" — brings a cancelled dispatch back to
     * Draft so it can be corrected and resubmitted.
     */
    public function unlock(Dispatch $dispatch): void
    {
        $this->transition($dispatch, 'cancelled', 'draft', 'Unlocked for editing');
    }

    private function transition(Dispatch $dispatch, string $expected, string $next, string $remarks): void
    {
        if ($dispatch->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => "Only a {$expected} dispatch can move to {$next}.",
            ]);
        }

        $original = $dispatch->status;
        $dispatch->status = $next;
        $dispatch->save();

        ActivityLog::record('dispatches', $dispatch->id, 'update', ['status' => $original], ['status' => $next], $remarks);
    }
}
