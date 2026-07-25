<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleStoreRequest;
use App\Http\Requests\VehicleUpdateRequest;
use App\Models\ActivityLog;
use App\Models\DispatchPlan;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Vehicle::class, 'vehicle');
    }

    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $trashed = $request->boolean('trashed');

        $vehicles = Vehicle::query()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('vehicle_no', 'like', "%{$search}%")
                ->orWhere('driver_name', 'like', "%{$search}%")
                ->orWhere('transport_company', 'like', "%{$search}%")))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('vehicles.index', compact('vehicles', 'trashed'));
    }

    /**
     * ?vehicle_no= and ?return_dispatch_plan_id= arrive from the Dispatch
     * page's "Add vehicle" flow when the typed vehicle number isn't
     * registered yet — prefills the number and carries the plan id through
     * as a hidden field so store() can auto-assign the new vehicle back to
     * that plan once created.
     */
    public function create(Request $request)
    {
        return view('vehicles.form', [
            'vehicle' => new Vehicle(['status' => true, 'vehicle_no' => $request->query('vehicle_no')]),
            'returnDispatchPlanId' => $request->query('return_dispatch_plan_id'),
        ]);
    }

    public function store(VehicleStoreRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $vehicle = Vehicle::create($data);

        ActivityLog::record('vehicles', $vehicle->id, 'create', [], $vehicle->toArray());

        $plan = $request->filled('return_dispatch_plan_id')
            ? DispatchPlan::find($request->input('return_dispatch_plan_id'))
            : null;

        if ($plan && $request->user()->can('create', [VehicleAssignment::class, $plan])) {
            $assignment = VehicleAssignment::create([
                'dispatch_plan_id' => $plan->id,
                'vehicle_id' => $vehicle->id,
                'created_by' => $request->user()->id,
            ]);

            ActivityLog::record('vehicle_assignments', $assignment->id, 'create', [], $assignment->toArray());

            return redirect()->route('dispatches.index')->with('success', "Vehicle {$vehicle->vehicle_no} registered and assigned to {$plan->plan_no}.");
        }

        return redirect()->route('vehicles.index')->with('success', 'Vehicle added successfully.');
    }

    public function edit(Vehicle $vehicle)
    {
        return view('vehicles.form', compact('vehicle'));
    }

    public function update(VehicleUpdateRequest $request, Vehicle $vehicle)
    {
        $original = $vehicle->toArray();

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $vehicle->update($data);

        ActivityLog::record('vehicles', $vehicle->id, 'update', $original, $vehicle->fresh()->toArray());

        return redirect()->route('vehicles.index')->with('success', 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle)
    {
        DB::transaction(function () use ($vehicle) {
            $vehicle->update(['deleted_by' => auth()->id()]);
            $vehicle->delete();
        });

        ActivityLog::record('vehicles', $vehicle->id, 'delete');

        return redirect()->route('vehicles.index')->with('success', 'Vehicle deleted successfully.');
    }

    public function restore(Vehicle $vehicle)
    {
        $this->authorize('restore', $vehicle);

        DB::transaction(function () use ($vehicle) {
            $vehicle->restore();
            $vehicle->update(['deleted_by' => null]);
        });

        ActivityLog::record('vehicles', $vehicle->id, 'update', [], [], 'Vehicle restored');

        return redirect()->route('vehicles.index')->with('success', 'Vehicle restored successfully.');
    }
}
