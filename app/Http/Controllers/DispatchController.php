<?php

namespace App\Http\Controllers;

use App\Http\Requests\DispatchStoreRequest;
use App\Http\Requests\DispatchUpdateRequest;
use App\Mail\DeliveryChallanMail;
use App\Models\ActivityLog;
use App\Models\Challan;
use App\Models\Dealer;
use App\Models\Dispatch;
use App\Models\DispatchPlan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Services\DispatchService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class DispatchController extends Controller
{
    public function __construct(
        private readonly DispatchService $dispatches,
    ) {
        $this->authorizeResource(Dispatch::class, 'dispatch');
    }

    /**
     * Dispatch list. Search, advanced filters, pagination and role-based
     * scoping all happen here; eager loading avoids N+1 queries on the
     * lines/vehicle columns rendered per row.
     */
    public function index(Request $request)
    {
        $dispatches = $this->filtered($request)
            ->with(['lines.dealer', 'lines.farmer', 'lines.booking', 'vehicle'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dispatches.index', array_merge(
            [
                'dispatches' => $dispatches,
                'trashed' => $request->boolean('trashed'),
            ],
            $this->filterOptions($request->user()),
            $this->planningWidgets($request->user())
        ));
    }

    /**
     * Plan approval and vehicle-adding moved here from the Dispatch
     * Planning pages — Dispatch Planning itself is now create/edit-only,
     * everything past plan creation happens on this page. Only queried for
     * users who can actually see dispatch plans at all (DispatchPlanPolicy),
     * so roles like Dispatch/Accounts/Dealer never pay for these queries.
     */
    private function planningWidgets(User $user): array
    {
        if (! $user->can('viewAny', DispatchPlan::class)) {
            return ['pendingPlans' => collect(), 'approvedPlans' => collect(), 'planVehicles' => collect(), 'planMarketingUsers' => collect()];
        }

        return [
            'pendingPlans' => DispatchPlan::where('approval_status', 'pending')->with(['farmerEstimates.dealer', 'farmerEstimates.farmer', 'farmerEstimates.booking'])->latest('plan_date')->limit(20)->get(),
            'approvedPlans' => DispatchPlan::where('approval_status', 'approved')->with(['farmerEstimates.dealer', 'farmerEstimates.farmer', 'farmerEstimates.booking', 'vehicleAssignments.vehicle', 'vehicleAssignments.loading', 'vehicleAssignments.items', 'vehicleAssignments.dispatches'])->latest('plan_date')->limit(20)->get(),
            'planVehicles' => Vehicle::where('status', true)->orderBy('vehicle_no')->get(),
            'planMarketingUsers' => User::whereHas('role', fn ($q) => $q->where('name', 'marketing'))->where('status', true)->orderBy('name')->get(),
        ];
    }

    /**
     * No vehicle_assignment_id yet: shows the "Available for Dispatch"
     * picker. With one: shows the actual line-items form for that vehicle.
     */
    public function create(Request $request)
    {
        $eligible = $this->dispatches->eligibleAssignments($request->user());

        if (! $request->filled('vehicle_assignment_id')) {
            return view('dispatches.create-select', ['assignments' => $eligible]);
        }

        $assignment = $eligible->firstWhere('id', (int) $request->input('vehicle_assignment_id'));

        abort_if(! $assignment, 404);

        return view('dispatches.form', [
            'dispatch' => new Dispatch(['dispatch_date' => now()->toDateString()]),
            'assignment' => $assignment,
        ]);
    }

    public function store(DispatchStoreRequest $request)
    {
        $data = $request->validated();

        $assignment = VehicleAssignment::with('items.booking')->findOrFail($data['vehicle_assignment_id']);

        $lineInputs = [];
        foreach ((array) ($data['lines'] ?? []) as $itemId => $line) {
            $lineInputs[$itemId] = [
                'qty' => $line['qty'] ?? 0,
                'extra_qty' => $line['extra_qty'] ?? 0,
                'qty_per_crate' => $line['qty_per_crate'] ?? null,
                'batch_number' => $line['batch_number'] ?? null,
                'plant_age' => $line['plant_age'] ?? null,
                'remarks' => $line['remarks'] ?? null,
            ];
        }

        $dispatch = $this->dispatches->createFromAssignment($assignment, $lineInputs, $request->user());

        $headerUpdate = [
            'driver_name' => $data['driver_name'] ?? $dispatch->driver_name,
            'driver_mobile' => $data['driver_mobile'] ?? $dispatch->driver_mobile,
            'lr_number' => $data['lr_number'] ?? null,
            'dispatch_date' => $data['dispatch_date'],
            'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'gps_location' => $data['gps_location'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            $headerUpdate['photo'] = $request->file('photo')->store('dispatches/photos', 'public');
        }

        if ($request->hasFile('signature')) {
            $headerUpdate['signature'] = $request->file('signature')->store('dispatches/signatures', 'public');
        }

        $dispatch->update($headerUpdate);

        return redirect()->route('dispatches.show', $dispatch)->with('success', 'Dispatch created successfully.');
    }

    /**
     * Dispatch profile page: dealer-grouped line items, documents
     * (photo/signature/GPS), timeline and audit history.
     */
    public function show(Dispatch $dispatch)
    {
        $dispatch->load(['lines.booking', 'lines.dealer', 'lines.farmer', 'vehicle', 'vehicleAssignment.dispatchPlan', 'createdBy', 'updatedBy']);

        $activity = ActivityLog::where('module', 'dispatches')->where('record_id', $dispatch->id)->latest()->limit(30)->get();

        $dealerChallans = Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_DEALER)->with('dealer')->orderBy('challan_no')->get();
        $farmerChallans = Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_FARMER)->with(['dealer', 'farmer'])->orderBy('challan_no')->get();

        return view('dispatches.show', compact('dispatch', 'activity', 'dealerChallans', 'farmerChallans'));
    }

    public function edit(Dispatch $dispatch)
    {
        return view('dispatches.form', [
            'dispatch' => $dispatch,
            'assignment' => null,
            'vehicles' => Vehicle::where('status', true)->orderBy('vehicle_no')->get(),
        ]);
    }

    /**
     * Header-only edit — booking/quantity detail lives on dispatch_lines and
     * isn't editable after creation.
     */
    public function update(DispatchUpdateRequest $request, Dispatch $dispatch)
    {
        $original = $dispatch->toArray();

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            if ($dispatch->photo) {
                Storage::disk('public')->delete($dispatch->photo);
            }
            $data['photo'] = $request->file('photo')->store('dispatches/photos', 'public');
        }

        if ($request->hasFile('signature')) {
            if ($dispatch->signature) {
                Storage::disk('public')->delete($dispatch->signature);
            }
            $data['signature'] = $request->file('signature')->store('dispatches/signatures', 'public');
        }

        $dispatch->update($data);

        ActivityLog::record('dispatches', $dispatch->id, 'update', $original, $dispatch->fresh()->toArray());

        return redirect()->route('dispatches.show', $dispatch)->with('success', 'Dispatch updated successfully.');
    }

    public function destroy(Dispatch $dispatch)
    {
        DB::transaction(function () use ($dispatch) {
            $dispatch->update(['deleted_by' => auth()->id()]);
            $dispatch->delete();
        });

        ActivityLog::record('dispatches', $dispatch->id, 'delete');

        return redirect()->route('dispatches.index')->with('success', 'Dispatch deleted successfully.');
    }

    public function restore(Dispatch $dispatch)
    {
        $this->authorize('restore', $dispatch);

        DB::transaction(function () use ($dispatch) {
            $dispatch->restore();
            $dispatch->update(['deleted_by' => null]);
        });

        ActivityLog::record('dispatches', $dispatch->id, 'update', [], [], 'Dispatch restored');

        return redirect()->route('dispatches.index')->with('success', 'Dispatch restored successfully.');
    }

    public function submit(Dispatch $dispatch)
    {
        $this->authorize('submit', $dispatch);

        $this->dispatches->submit($dispatch);

        return back()->with('success', 'Dispatch submitted.');
    }

    public function startLoading(Request $request, Dispatch $dispatch)
    {
        $this->authorize('startLoading', $dispatch);

        $this->dispatches->startLoading($dispatch, $request->user());

        return back()->with('success', 'Loading started — challan(s) generated.');
    }

    public function vehicleOut(Request $request, Dispatch $dispatch)
    {
        $this->authorize('vehicleOut', $dispatch);

        $this->dispatches->vehicleOut($dispatch, $request->user());

        return back()->with('success', 'Vehicle dispatched.');
    }

    /**
     * One-click "Vehicle Loaded" from the Dispatches index page — replaces
     * the old flow of navigating into Dispatch Planning to mark items
     * loaded, approve loading, then separately create+submit+start-loading
     * a Dispatch. Stays on the same page; the challan is generated inline
     * by DispatchService::loadVehicle().
     */
    public function loadVehicle(Request $request, VehicleAssignment $vehicleAssignment)
    {
        $this->authorize('loadVehicle', $vehicleAssignment);

        $dispatch = $this->dispatches->loadVehicle($vehicleAssignment, $request->user());

        return back()->with('success', "Vehicle loaded — challan {$dispatch->challan_no} generated.");
    }

    public function markDelivered(Request $request, Dispatch $dispatch)
    {
        $this->authorize('markDelivered', $dispatch);

        $validated = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.accepted_qty' => ['nullable', 'integer', 'min:0'],
            'lines.*.rejected_qty' => ['nullable', 'integer', 'min:0'],
            'lines.*.rejection_reason' => ['nullable', 'string', 'max:500'],
            'delivery_location' => ['nullable', 'string', 'max:255'],
            'receiver_name' => ['nullable', 'string', 'max:150'],
            'receiver_mobile' => ['nullable', 'string', 'max:15'],
            'receiver_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->dispatches->markDelivered($dispatch, $validated['lines'] ?? [], $request->user()->id, [
            'delivery_location' => $validated['delivery_location'] ?? null,
            'receiver_name' => $validated['receiver_name'] ?? null,
            'receiver_mobile' => $validated['receiver_mobile'] ?? null,
            'receiver_remarks' => $validated['receiver_remarks'] ?? null,
        ]);

        return back()->with('success', 'Delivery completed.');
    }

    /**
     * Dispatch Lifecycle spec Step 6 — the lightweight "truck is physically
     * back" event, separate from the fuller Return Inspection below.
     */
    public function vehicleReturned(Request $request, Dispatch $dispatch)
    {
        $this->authorize('markVehicleReturned', $dispatch);

        $validated = $request->validate([
            'odometer_end' => ['required', 'integer', 'min:0'],
            'toll_charges' => ['nullable', 'numeric', 'min:0'],
            'other_expenses' => ['nullable', 'numeric', 'min:0'],
            'driver_allowance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->dispatches->markVehicleReturned(
            $dispatch,
            $request->user(),
            (int) $validated['odometer_end'],
            isset($validated['toll_charges']) ? (float) $validated['toll_charges'] : null,
            isset($validated['other_expenses']) ? (float) $validated['other_expenses'] : null,
            isset($validated['driver_allowance']) ? (float) $validated['driver_allowance'] : null,
        );

        return back()->with('success', 'Vehicle returned — transport cost recorded.');
    }

    /**
     * Complete Dispatch -> Reduce Actual Stock -> Reservation Converted ->
     * Booking Dispatch Status = Completed, all inside one transaction.
     */
    public function complete(Request $request, Dispatch $dispatch)
    {
        $this->authorize('complete', $dispatch);

        DB::transaction(function () use ($request, $dispatch) {
            $this->dispatches->complete($dispatch, $request->user()->id);
        });

        return back()->with('success', 'Dispatch completed — stock reservation converted to a stock issue.');
    }

    /**
     * "Return Inspection" per the Dispatch Lifecycle spec's Step 7 — only
     * reachable in the UI once Step 6's vehicleReturned() has been clicked,
     * though not hard-enforced here (DispatchPolicy::recordReturn() already
     * allows it across the same status range as markVehicleReturned()).
     */
    public function recordReturn(Request $request, Dispatch $dispatch)
    {
        $this->authorize('recordReturn', $dispatch);

        $validated = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.returned_qty' => ['required', 'numeric', 'min:0'],
            'lines.*.damage_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.missing_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.broken_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.dead_plant_qty' => ['nullable', 'integer', 'min:0'],
            'lines.*.extra_returned_qty' => ['nullable', 'integer', 'min:0'],
            'vehicle_returned_at' => ['required', 'date'],
            'return_remarks' => ['nullable', 'string', 'max:500'],
            'damage_remarks' => ['nullable', 'string', 'max:1000'],
            'driver_remarks' => ['nullable', 'string', 'max:1000'],
            'supervisor_remarks' => ['nullable', 'string', 'max:1000'],
            'return_photos' => ['nullable', 'array'],
            'return_photos.*' => ['image', 'max:4096'],
        ]);

        $photoPaths = collect($request->file('return_photos', []))
            ->map(fn ($file) => $file->store('dispatches/return-photos', 'public'))
            ->all();

        DB::transaction(function () use ($validated, $photoPaths, $dispatch, $request) {
            $this->dispatches->recordReturn(
                $dispatch,
                $validated['lines'] ?? [],
                $validated['vehicle_returned_at'],
                $validated['return_remarks'] ?? null,
                $request->user()->id,
                [
                    'damage_remarks' => $validated['damage_remarks'] ?? null,
                    'driver_remarks' => $validated['driver_remarks'] ?? null,
                    'supervisor_remarks' => $validated['supervisor_remarks'] ?? null,
                    'return_photos' => $photoPaths ?: null,
                ],
            );
        });

        return back()->with('success', 'Return inspection recorded.');
    }

    public function cancel(Request $request, Dispatch $dispatch)
    {
        $this->authorize('cancel', $dispatch);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($validated, $dispatch, $request) {
            $this->dispatches->cancel($dispatch, $validated['reason'], $request->user()->id);
        });

        return back()->with('success', 'Dispatch cancelled.');
    }

    public function unlock(Dispatch $dispatch)
    {
        $this->authorize('unlock', $dispatch);

        $this->dispatches->unlock($dispatch);

        return back()->with('success', 'Dispatch unlocked for editing.');
    }

    public function print(Dispatch $dispatch)
    {
        $this->authorize('view', $dispatch);

        $dispatch->load(['lines.booking', 'lines.dealer', 'lines.farmer.village', 'lines.farmer.taluka', 'lines.farmer.district', 'lines.farmer.state', 'vehicle', 'createdBy']);

        return view('dispatches.print', compact('dispatch'));
    }

    public function pdf(Dispatch $dispatch)
    {
        $this->authorize('view', $dispatch);

        $dispatch->load(['lines.booking', 'lines.dealer', 'lines.farmer.village', 'lines.farmer.taluka', 'lines.farmer.district', 'lines.farmer.state', 'vehicle', 'createdBy']);

        return Pdf::loadView('dispatches.print', compact('dispatch'))
            ->download("{$dispatch->dispatch_no}.pdf");
    }

    /**
     * "Email PDF" — sends the same challan document (rendered from the same
     * dispatches.print Blade as Print/Download) as a PDF attachment to a
     * one-off recipient email address, e.g. the dealer or farmer.
     */
    public function emailChallan(Request $request, Dispatch $dispatch)
    {
        $this->authorize('view', $dispatch);

        $data = $request->validate(['email' => ['required', 'email']]);

        $dispatch->load(['lines.booking', 'lines.dealer', 'lines.farmer.village', 'lines.farmer.taluka', 'lines.farmer.district', 'lines.farmer.state', 'vehicle', 'createdBy']);

        Mail::to($data['email'])->send(new DeliveryChallanMail($dispatch));

        return back()->with('success', "Challan emailed to {$data['email']}.");
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Dispatch::class);

        $dispatches = $this->filtered($request)->with(['lines.booking', 'lines.dealer', 'lines.farmer', 'vehicle'])->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="dispatches.csv"',
        ];

        return response()->streamDownload(function () use ($dispatches) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Dispatch No', 'Booking No', 'Dealer', 'Farmer', 'Vehicle', 'Driver', 'Dispatch Date', 'Expected Delivery', 'Actual Delivery', 'Dispatch Qty', 'Remaining Qty', 'Status', 'Challan No']);
            foreach ($dispatches as $dispatch) {
                foreach ($dispatch->lines as $line) {
                    fputcsv($out, [
                        $dispatch->dispatch_no,
                        $line->booking?->booking_no,
                        $line->dealer?->dealer_name,
                        $line->farmer?->farmer_name,
                        $dispatch->vehicle?->vehicle_no,
                        $dispatch->driver_name,
                        $dispatch->dispatch_date?->format('Y-m-d'),
                        $dispatch->expected_delivery_date?->format('Y-m-d'),
                        $dispatch->actual_delivery_date?->format('Y-m-d'),
                        $line->dispatch_qty,
                        $line->remaining_qty,
                        $dispatch->status,
                        $dispatch->challan_no,
                    ]);
                }
            }
            fclose($out);
        }, 'dispatches.csv', $headers);
    }

    /**
     * Shared search/filter/role-scope query builder, reused by index() and export().
     */
    private function filtered(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();

        return Dispatch::query()
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('dispatch_no', 'like', "%{$search}%")
                ->orWhere('challan_no', 'like', "%{$search}%")
                ->orWhere('driver_name', 'like', "%{$search}%")
                ->orWhereHas('lines.booking', fn ($b) => $b->where('booking_no', 'like', "%{$search}%"))
                ->orWhereHas('lines.dealer', fn ($d) => $d->where('dealer_name', 'like', "%{$search}%"))
                ->orWhereHas('lines.farmer', fn ($f) => $f->where('farmer_name', 'like', "%{$search}%"))))
            ->when($request->filled('dealer'), fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('dealer_id', $request->input('dealer'))))
            ->when($request->filled('vehicle'), fn ($q) => $q->where('vehicle_id', $request->input('vehicle')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('dispatch_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('dispatch_date', '<=', $request->input('date_to')))
            ->when($user->hasRole('marketing'), fn ($q) => $q->whereHas('lines.dealer.assignment', fn ($qq) => $qq->where('marketing_user_id', $user->id)))
            ->when($user->hasRole('dealer'), fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('dealer_id', $user->dealer_id)));
    }

    private function filterOptions($user): array
    {
        return [
            'dealers' => Dealer::orderBy('dealer_name')->get(['id', 'dealer_name']),
            'vehicles' => Vehicle::orderBy('vehicle_no')->get(['id', 'vehicle_no']),
            'statuses' => Dispatch::STATUSES,
        ];
    }
}
