<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Dealer;
use App\Models\DispatchPlan;
use App\Models\DispatchPlanItem;
use App\Models\Farmer;
use App\Models\LoadingHistory;
use App\Models\User;
use App\Models\VehicleAssignment;
use App\Models\VehicleLoading;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns numbering and the booking <-> vehicle grouping rules for the
 * Dispatch Planning module — the pre-dispatch layer that sits before
 * DispatchService's own draft->...->completed lifecycle. Loading approval
 * (approveLoading()) only marks the vehicle "available for dispatch";
 * creating the actual Dispatch is a separate, explicit step a Dispatch-role
 * user takes afterwards via DispatchService::createFromAssignment().
 */
class DispatchPlanningService
{
    public function __construct(
        private readonly BookingService $bookings,
    ) {}

    /**
     * PLN-YYYY-000001, sequence resets each calendar year — same pattern as
     * DispatchService::nextDispatchNo().
     */
    public function nextPlanNo(): string
    {
        $prefix = 'PLN-'.now()->year.'-';

        $maxNumber = DispatchPlan::withTrashed()
            ->where('plan_no', 'like', "{$prefix}%")
            ->selectRaw('MAX(CAST(SUBSTRING(plan_no, ?) AS UNSIGNED)) as max_number', [strlen($prefix) + 1])
            ->value('max_number');

        return $prefix.str_pad(((int) $maxNumber) + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Payment statuses this user may plan/dispatch against: fully paid is
     * always allowed; dispatch.partial-payment additionally allows partial;
     * dispatch.no-payment is the special, more permissive grant that also
     * allows a fully unpaid (pending) booking through — a deliberate
     * override for cases (e.g. a trusted dealer, an urgent shipment) where
     * planning needs to proceed ahead of payment.
     */
    private function allowedPaymentStatuses(User $user): array
    {
        if ($user->hasPermission('dispatch.no-payment')) {
            return ['completed', 'partial', 'pending'];
        }

        if ($user->hasPermission('dispatch.partial-payment')) {
            return ['completed', 'partial'];
        }

        return ['completed'];
    }

    /**
     * Bookings that can enter the planning pipeline at all: Approved, not
     * already in a (non-removed) plan item, and payment-gated exactly like
     * direct Dispatch creation (DispatchStoreRequest::allowedBookingIds()) —
     * fully paid dispatches normally, partial payment needs
     * dispatch.partial-payment, unpaid needs the more permissive
     * dispatch.no-payment.
     */
    public function eligibleBookings(User $user)
    {
        $plannedBookingIds = DispatchPlanItem::pluck('booking_id');

        return Booking::query()
            ->where('approval_status', 'approved')
            ->whereNotIn('id', $plannedBookingIds)
            ->whereIn('payment_status', $this->allowedPaymentStatuses($user))
            ->when($user->hasRole('marketing'), fn ($q) => $q->whereHas('dealer.assignment', fn ($qq) => $qq->where('marketing_user_id', $user->id)))
            ->with(['dealer.village', 'dealer.taluka', 'dealer.district', 'dealer.state', 'farmer'])
            ->orderBy('booking_no')
            ->get();
    }

    /**
     * Bookings selectable when creating/editing a Dispatch Plan itself (the
     * Dealer -> Booking -> Dispatch Quantity screen) — this is now the
     * earliest point a booking is committed to a plan at all, so it carries
     * the same payment gate eligibleBookings() used to enforce at the later
     * "load onto a vehicle" step (fully paid normally, partial payment needs
     * dispatch.partial-payment, unpaid needs dispatch.no-payment), plus
     * excluding Rejected bookings and ones with nothing left to dispatch. A
     * booking already linked to $plan is always included regardless of any
     * of the above having since changed, so editing an existing plan still
     * renders that row's read-only fields correctly.
     */
    public function activeBookingsForPlanning(User $user, ?DispatchPlan $plan = null)
    {
        $linkedBookingIds = $plan?->exists
            ? $plan->farmerEstimates()->whereNotNull('booking_id')->pluck('booking_id')
            : collect();

        $allowedPaymentStatuses = $this->allowedPaymentStatuses($user);

        return Booking::query()
            ->when($user->hasRole('marketing'), fn ($q) => $q->whereHas('dealer.assignment', fn ($qq) => $qq->where('marketing_user_id', $user->id)))
            ->with(['farmer:id,farmer_name', 'dispatchLines:id,booking_id,dispatch_qty'])
            ->get()
            ->filter(fn (Booking $booking) => $linkedBookingIds->contains($booking->id) || (
                $booking->approval_status !== 'rejected'
                && in_array($booking->payment_status, $allowedPaymentStatuses, true)
                && $booking->balance_qty > 0
            ))
            ->values();
    }

    /**
     * Bookings still available to be placed on a vehicle for $plan — every
     * booking already committed to the plan (Dealer -> Booking -> Dispatch
     * Quantity step) that hasn't yet been assigned to any vehicle. No
     * further eligibility gate needed here: Approval/payment/balance were
     * already enforced once, when the booking was committed to the plan via
     * activeBookingsForPlanning() above.
     */
    public function unassignedPlanBookings(DispatchPlan $plan)
    {
        $assignedBookingIds = DispatchPlanItem::whereHas(
            'vehicleAssignment',
            fn ($q) => $q->where('dispatch_plan_id', $plan->id)
        )->pluck('booking_id');

        return $plan->farmerEstimates()
            ->whereNotNull('booking_id')
            ->whereNotIn('booking_id', $assignedBookingIds)
            ->with(['booking', 'dealer', 'farmer'])
            ->get();
    }

    /**
     * Adds a booking to a vehicle's plan. dispatch_qty defaults to — and can
     * never exceed — the quantity committed for this booking back when it
     * was added to the plan itself (DispatchPlanFarmerEstimate.plant_quantity,
     * which may be less than the booking's full plant_qty for a partial
     * dispatch), not the booking's raw total. Falls back to the booking's
     * full plant_qty only if no such commitment row exists (legacy data).
     */
    public function addBooking(VehicleAssignment $assignment, Booking $booking, ?int $qty, User $actor): DispatchPlanItem
    {
        if (DispatchPlanItem::where('booking_id', $booking->id)->exists()) {
            throw ValidationException::withMessages(['booking_id' => 'This booking is already assigned to a vehicle.']);
        }

        $committedQty = $assignment->dispatchPlan->farmerEstimates()->where('booking_id', $booking->id)->value('plant_quantity')
            ?? $booking->plant_qty;

        $qty = $qty ?? $committedQty;

        if ($qty > $committedQty) {
            throw ValidationException::withMessages(['dispatch_qty' => "Dispatch quantity cannot exceed the {$committedQty} plants committed for this booking in the plan."]);
        }

        return DispatchPlanItem::create([
            'vehicle_assignment_id' => $assignment->id,
            'booking_id' => $booking->id,
            'dispatch_qty' => $qty,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Dispatch Lifecycle spec: "Add Vehicle" no longer has a manual
     * "assign booking to this vehicle" step — every one of the plan's
     * still-unassigned committed bookings auto-attaches to whichever
     * vehicle is added, right away. Called once, right after
     * VehicleAssignmentController::store() creates the assignment. A
     * booking that individually fails (e.g. an edge-case qty mismatch) is
     * skipped rather than aborting the rest — the manual "+ Assign a
     * booking" / reassignBooking() path still exists underneath for anyone
     * who needs to fix up an individual booking afterwards.
     */
    public function autoAssignAllBookings(VehicleAssignment $assignment, User $actor): void
    {
        foreach ($this->unassignedPlanBookings($assignment->dispatchPlan) as $estimate) {
            try {
                $this->addBooking($assignment, $estimate->booking, null, $actor);
            } catch (ValidationException) {
                continue;
            }
        }
    }

    /**
     * Moves a booking from one vehicle to another (the fallback dropdown in
     * Phase 2, and the drag-and-drop endpoint from Phase 3 onward). Blocked
     * once the source vehicle's loading has already been approved.
     */
    public function reassignBooking(DispatchPlanItem $item, VehicleAssignment $to, User $actor): void
    {
        if ($item->vehicleAssignment->loading_status === 'completed') {
            throw ValidationException::withMessages(['vehicle_assignment_id' => 'This booking has already been loaded and approved — it cannot be moved.']);
        }

        $item->update(['vehicle_assignment_id' => $to->id, 'updated_by' => $actor->id]);
    }

    /**
     * Removes a booking from planning entirely, freeing it up to be
     * assigned to a different vehicle or re-planned later.
     */
    public function removeBooking(DispatchPlanItem $item, User $actor): void
    {
        if ($item->vehicleAssignment->loading_status === 'completed') {
            throw ValidationException::withMessages(['booking_id' => 'This booking has already been loaded and approved — it cannot be removed.']);
        }

        $item->update(['updated_by' => $actor->id]);
        $item->delete();
    }

    /**
     * Toggles one booking's loaded state. Flips the vehicle's loading
     * status pending -> loading on the very first item toggled, and logs an
     * append-only history row either way.
     */
    public function toggleLoaded(DispatchPlanItem $item, User $actor, bool $loaded): void
    {
        DB::transaction(function () use ($item, $actor, $loaded) {
            $assignment = $item->vehicleAssignment;
            // Explicit 'status' on create — Eloquent doesn't sync the
            // column's own DB default back onto the in-memory instance
            // after an insert, so without this the check below (=== 'pending')
            // would silently never match on a freshly created row.
            $loading = $assignment->loading ?? VehicleLoading::create(['vehicle_assignment_id' => $assignment->id, 'status' => 'pending']);

            $item->update([
                'loaded_at' => $loaded ? now() : null,
                'loaded_by' => $loaded ? $actor->id : null,
                'updated_by' => $actor->id,
            ]);

            if ($loaded && $loading->status === 'pending') {
                $loading->update(['status' => 'loading', 'loading_started_at' => now(), 'updated_by' => $actor->id]);
                LoadingHistory::record($assignment->id, null, 'loading_started', $actor->id);
            }

            LoadingHistory::record($assignment->id, $item->id, $loaded ? 'item_loaded' : 'item_unloaded', $actor->id);
        });
    }

    /**
     * Once every booking on this vehicle is physically loaded, Supervisor
     * approves it here — this only flips VehicleLoading to 'completed' and
     * logs the approval. It no longer creates any Dispatch: the vehicle
     * just becomes "available for dispatch" (VehicleAssignment::
     * dispatch_status), and a Dispatch-role user picks it up explicitly via
     * DispatchService::createFromAssignment() afterwards, possibly more
     * than once for partial quantities.
     */
    public function approveLoading(VehicleAssignment $assignment, User $actor): void
    {
        $items = $assignment->items;

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['loading' => 'Add at least one booking to this vehicle before approving loading.']);
        }

        if ($items->contains(fn (DispatchPlanItem $item) => ! $item->isLoaded())) {
            throw ValidationException::withMessages(['loading' => 'Every booking on this vehicle must be marked loaded before approving.']);
        }

        DB::transaction(function () use ($assignment, $actor) {
            $loading = $assignment->loading ?? VehicleLoading::create(['vehicle_assignment_id' => $assignment->id, 'status' => 'pending']);
            $loading->update([
                'status' => 'completed',
                'loading_finished_at' => now(),
                'supervisor_id' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            LoadingHistory::record($assignment->id, null, 'loading_approved', $actor->id);

            ActivityLog::record('vehicle_assignments', $assignment->id, 'update', [], [], 'Loading approved — available for dispatch');
        });
    }
}
