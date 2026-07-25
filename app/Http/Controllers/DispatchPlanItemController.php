<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\DispatchPlanItem;
use App\Models\VehicleAssignment;
use App\Services\DispatchPlanningService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DispatchPlanItemController extends Controller
{
    public function __construct(
        private readonly DispatchPlanningService $planning,
    ) {}

    /**
     * Assigns one of the plan's own already-committed bookings (Dealer ->
     * Booking -> Dispatch Quantity, set when the plan itself was
     * created/edited) to this vehicle. The dropdown/plain-select stand-in
     * for Phase 3's drag-and-drop.
     */
    public function store(Request $request, VehicleAssignment $vehicleAssignment)
    {
        $this->authorize('update', $vehicleAssignment);

        $data = $request->validate([
            'booking_id' => ['required', Rule::in($this->planning->unassignedPlanBookings($vehicleAssignment->dispatchPlan)->pluck('booking_id'))],
            'dispatch_qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $booking = Booking::findOrFail($data['booking_id']);
        $item = $this->planning->addBooking($vehicleAssignment, $booking, $data['dispatch_qty'] ?? null, $request->user());

        ActivityLog::record('vehicle_assignments', $vehicleAssignment->id, 'update', [], [], "Booking {$booking->booking_no} added");

        return back()->with('success', "Booking {$booking->booking_no} added to {$vehicleAssignment->vehicle->vehicle_no}.");
    }

    /**
     * Move a booking to a different vehicle within the same plan.
     */
    public function reassign(Request $request, DispatchPlanItem $dispatchPlanItem)
    {
        $this->authorize('update', $dispatchPlanItem->vehicleAssignment);

        $data = $request->validate([
            'vehicle_assignment_id' => [
                'required',
                Rule::exists('vehicle_assignments', 'id')->where('dispatch_plan_id', $dispatchPlanItem->vehicleAssignment->dispatch_plan_id),
            ],
        ]);

        $to = VehicleAssignment::findOrFail($data['vehicle_assignment_id']);
        $this->authorize('update', $to);

        $this->planning->reassignBooking($dispatchPlanItem, $to, $request->user());

        ActivityLog::record('vehicle_assignments', $to->id, 'update', [], [], "Booking {$dispatchPlanItem->booking->booking_no} moved from {$dispatchPlanItem->vehicleAssignment->vehicle->vehicle_no}");

        return back()->with('success', 'Booking moved.');
    }

    public function destroy(Request $request, DispatchPlanItem $dispatchPlanItem)
    {
        $this->authorize('update', $dispatchPlanItem->vehicleAssignment);

        $bookingNo = $dispatchPlanItem->booking->booking_no;
        $this->planning->removeBooking($dispatchPlanItem, $request->user());

        ActivityLog::record('vehicle_assignments', $dispatchPlanItem->vehicle_assignment_id, 'update', [], [], "Booking {$bookingNo} removed");

        return back()->with('success', "Booking {$bookingNo} removed from the vehicle.");
    }

    /**
     * Supervisor's "physically loaded onto the vehicle" checkbox — flips
     * based on the item's current state rather than taking an input, since
     * the UI is a single toggle button per booking.
     */
    public function toggleLoaded(Request $request, DispatchPlanItem $dispatchPlanItem)
    {
        $this->authorize('markLoaded', $dispatchPlanItem->vehicleAssignment);

        $this->planning->toggleLoaded($dispatchPlanItem, $request->user(), ! $dispatchPlanItem->isLoaded());

        return back()->with('success', $dispatchPlanItem->fresh()->isLoaded() ? 'Booking marked loaded.' : 'Booking marked not loaded.');
    }
}
