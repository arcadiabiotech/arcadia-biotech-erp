<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleAssignmentStoreRequest;
use App\Http\Requests\VehicleAssignmentUpdateRequest;
use App\Models\ActivityLog;
use App\Models\DispatchPlan;
use App\Models\VehicleAssignment;
use App\Services\DispatchPlanningService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VehicleAssignmentController extends Controller
{
    public function __construct(
        private readonly DispatchPlanningService $planning,
    ) {}

    public function store(VehicleAssignmentStoreRequest $request, DispatchPlan $dispatchPlan)
    {
        $this->authorize('create', [VehicleAssignment::class, $dispatchPlan]);

        $data = $request->validated();
        $data['dispatch_plan_id'] = $dispatchPlan->id;
        $data['created_by'] = $request->user()->id;

        $assignment = VehicleAssignment::create($data);

        // Dispatch Lifecycle spec: no manual "assign booking to this
        // vehicle" step — every still-unassigned committed booking in the
        // plan attaches to it automatically right here.
        $this->planning->autoAssignAllBookings($assignment, $request->user());

        ActivityLog::record('vehicle_assignments', $assignment->id, 'create', [], $assignment->fresh(['items'])->toArray());

        return back()->with('success', 'Vehicle added to the plan.');
    }

    public function update(VehicleAssignmentUpdateRequest $request, VehicleAssignment $vehicleAssignment)
    {
        $this->authorize('update', $vehicleAssignment);

        $original = $vehicleAssignment->only(['vehicle_id', 'marketing_user_id', 'driver_name', 'driver_mobile', 'helper_name', 'estimated_departure_time', 'remarks']);
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;
        $vehicleAssignment->update($data);

        ActivityLog::record('vehicle_assignments', $vehicleAssignment->id, 'update', $original, $vehicleAssignment->fresh()->only(['vehicle_id', 'marketing_user_id', 'driver_name', 'driver_mobile', 'helper_name', 'estimated_departure_time', 'remarks']));

        return back()->with('success', 'Vehicle assignment updated.');
    }

    public function destroy(VehicleAssignment $vehicleAssignment)
    {
        $this->authorize('delete', $vehicleAssignment);

        if ($vehicleAssignment->items()->exists()) {
            throw ValidationException::withMessages(['vehicle_assignment' => 'Remove all bookings from this vehicle before deleting it.']);
        }

        $vehicleAssignment->delete();

        ActivityLog::record('vehicle_assignments', $vehicleAssignment->id, 'delete');

        return back()->with('success', 'Vehicle removed from the plan.');
    }

    /**
     * Supervisor's final sign-off once every booking on this vehicle is
     * loaded — marks loading complete (DispatchPlanningService::
     * approveLoading()) and drops straight into Dispatch creation for this
     * vehicle (skipping the separate "pick an eligible vehicle" picker
     * screen), so the same Supervisor can carry it through to challan +
     * dispatched without a role hand-off.
     */
    public function approveLoading(Request $request, VehicleAssignment $vehicleAssignment)
    {
        $this->authorize('approveLoading', $vehicleAssignment);

        $this->planning->approveLoading($vehicleAssignment, $request->user());

        return redirect()->route('dispatches.create', ['vehicle_assignment_id' => $vehicleAssignment->id])
            ->with('success', 'Loading approved — create the Dispatch for this vehicle below.');
    }
}
