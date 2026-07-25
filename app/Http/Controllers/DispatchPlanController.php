<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasLocationOptions;
use App\Http\Requests\DispatchPlanStoreRequest;
use App\Http\Requests\DispatchPlanUpdateRequest;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Dealer;
use App\Models\DispatchPlan;
use App\Models\Farmer;
use App\Models\VarietyStock;
use App\Services\DispatchPlanningService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DispatchPlanController extends Controller
{
    use HasLocationOptions;

    public function __construct(
        private readonly DispatchPlanningService $planning,
    ) {
        // Route::resource('dispatch-plans', ...) auto-generates the
        // {dispatch_plan} (snake_case) route parameter for this kebab-case,
        // multi-word resource name — the binding name here, and every route-
        // bound method parameter below, must match it exactly.
        $this->authorizeResource(DispatchPlan::class, 'dispatch_plan');
    }

    public function index(Request $request)
    {
        $plans = DispatchPlan::withCount('vehicleAssignments')
            ->withSum('farmerEstimates as total_plant_quantity', 'plant_quantity')
            ->with('farmerEstimates.dealer')
            ->when($request->filled('plan_date'), fn ($q) => $q->whereDate('plan_date', $request->input('plan_date')))
            ->latest('plan_date')
            ->paginate(15)
            ->withQueryString();

        return view('dispatch-plans.index', compact('plans'));
    }

    public function create(Request $request)
    {
        return view('dispatch-plans.form', array_merge(
            [
                'plan' => new DispatchPlan(['plan_date' => now()->toDateString()]),
                'dealers' => Dealer::orderBy('dealer_name')->get(),
                'bookings' => $this->planning->activeBookingsForPlanning($request->user()),
                'farmers' => Farmer::orderBy('farmer_name')->get(['id', 'farmer_name', 'dealer_id']),
                'initialDealerGroups' => $this->initialDealerGroups(null),
                'varietyStocks' => $this->varietyStocks(),
            ],
            // Feeds the "register new farmer" quick-add modal's location cascade.
            $this->locationOptions()
        ));
    }

    public function store(DispatchPlanStoreRequest $request)
    {
        $data = $request->validated();
        $bookingRows = $data['farmer_estimates'] ?? [];
        unset($data['farmer_estimates']);
        $data['plan_no'] = $this->planning->nextPlanNo();
        $data['created_by'] = $request->user()->id;

        $plan = DispatchPlan::create($data);
        $this->syncBookingEstimates($plan, $bookingRows);

        ActivityLog::record('dispatch_plans', $plan->id, 'create', [], $plan->toArray());

        return to_route('dispatch-plans.show', $plan)->with('success', 'Dispatch plan created — pending approval before vehicles can be added.');
    }

    public function show(DispatchPlan $dispatch_plan)
    {
        $dispatch_plan->load([
            'farmerEstimates.dealer',
            'farmerEstimates.farmer',
            'farmerEstimates.booking',
            'vehicleAssignments.vehicle',
            'vehicleAssignments.marketingOfficer',
            'vehicleAssignments.items.booking.dealer.village',
            'vehicleAssignments.items.booking.dealer.taluka',
            'vehicleAssignments.items.booking.dealer.district',
            'vehicleAssignments.items.booking.dealer.state',
            'vehicleAssignments.items.booking.farmer',
            'vehicleAssignments.items.dispatchLines.dispatch',
            'vehicleAssignments.loading',
        ]);

        $unassignedBookings = $this->planning->unassignedPlanBookings($dispatch_plan);
        $plan = $dispatch_plan;

        return view('dispatch-plans.show', compact('plan', 'unassignedBookings'));
    }

    public function edit(DispatchPlan $dispatch_plan, Request $request)
    {
        return view('dispatch-plans.form', array_merge(
            [
                'plan' => $dispatch_plan,
                'dealers' => Dealer::orderBy('dealer_name')->get(),
                'bookings' => $this->planning->activeBookingsForPlanning($request->user(), $dispatch_plan),
                'farmers' => Farmer::orderBy('farmer_name')->get(['id', 'farmer_name', 'dealer_id']),
                'initialDealerGroups' => $this->initialDealerGroups($dispatch_plan),
                'varietyStocks' => $this->varietyStocks(),
            ],
            $this->locationOptions()
        ));
    }

    public function update(DispatchPlanUpdateRequest $request, DispatchPlan $dispatch_plan)
    {
        $original = $dispatch_plan->only(['plan_date', 'route', 'remarks']);
        $data = $request->validated();
        $bookingRows = $data['farmer_estimates'] ?? [];
        unset($data['farmer_estimates']);
        $data['updated_by'] = $request->user()->id;
        $dispatch_plan->update($data);
        $this->syncBookingEstimates($dispatch_plan, $bookingRows);

        ActivityLog::record('dispatch_plans', $dispatch_plan->id, 'update', $original, $dispatch_plan->fresh()->only(['plan_date', 'route', 'remarks']));

        return to_route('dispatch-plans.show', $dispatch_plan)->with('success', 'Dispatch plan updated.');
    }

    /**
     * Dealer and farmer are always derived from the Booking itself rather
     * than trusted from the client — the form only ever sends
     * booking_id/dispatch_qty per row. dispatch_type is likewise computed
     * here, not accepted from the client: 'full' when the chosen quantity
     * uses up the booking's entire live balance, 'partial' otherwise. Full
     * delete + recreate is simplest since this is a lightweight
     * planning-time snapshot, not a workflow-tracked record — the real
     * quantity truth lives downstream in DispatchPlanItem/DispatchLine once
     * a vehicle is actually assigned.
     */
    private function syncBookingEstimates(DispatchPlan $plan, array $rows): void
    {
        $plan->farmerEstimates()->delete();

        if (empty($rows)) {
            return;
        }

        $bookingsById = Booking::with('dispatchLines:id,booking_id,dispatch_qty')
            ->whereIn('id', collect($rows)->pluck('booking_id'))
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $booking = $bookingsById->get($row['booking_id']);

            if (! $booking) {
                continue;
            }

            $qty = (int) $row['dispatch_qty'];

            $plan->farmerEstimates()->create([
                'dealer_id' => $booking->dealer_id,
                'farmer_id' => $booking->farmer_id,
                'booking_id' => $booking->id,
                'plant_quantity' => $qty,
                'dispatch_type' => $qty >= $booking->balance_qty ? 'full' : 'partial',
            ]);
        }
    }

    /**
     * Builds the Alpine.js form's initial dealer-group state: on a
     * validation failure, reconstruct groups from old('farmer_estimates')
     * (looking up each row's dealer via its booking); otherwise from the
     * plan's existing farmerEstimates on edit; empty for a brand new plan.
     */
    private function initialDealerGroups(?DispatchPlan $plan): array
    {
        if (old('farmer_estimates')) {
            $rows = collect(old('farmer_estimates'));
            $bookingsById = Booking::whereIn('id', $rows->pluck('booking_id'))->get()->keyBy('id');

            return $rows->groupBy(fn ($row) => $bookingsById->get($row['booking_id'])?->dealer_id ?? 0)
                ->map(fn ($groupRows, $dealerId) => [
                    'dealerId' => (string) $dealerId,
                    'rows' => $groupRows->map(fn ($r) => ['bookingId' => (string) $r['booking_id'], 'dispatchQty' => $r['dispatch_qty']])->values()->all(),
                ])->values()->all();
        }

        if ($plan?->exists) {
            return $plan->farmerEstimates->groupBy('dealer_id')
                ->map(fn ($groupRows, $dealerId) => [
                    'dealerId' => (string) $dealerId,
                    'rows' => $groupRows->map(fn ($r) => ['bookingId' => (string) $r->booking_id, 'dispatchQty' => (string) $r->plant_quantity])->values()->all(),
                ])->values()->all();
        }

        return [];
    }

    /**
     * Available stock (actual - reserved) per variety, for the "Create
     * booking" quick-add modal's Variety dropdown — lets it show "Not
     * available" up front instead of the user finding out only after
     * submitting and hitting StockReservationService's insufficient-stock
     * error.
     */
    private function varietyStocks(): array
    {
        foreach (Booking::VARIETIES as $variety) {
            VarietyStock::firstOrCreate(['variety' => $variety], ['actual_qty' => 0]);
        }

        return VarietyStock::whereIn('variety', Booking::VARIETIES)->get()
            ->mapWithKeys(fn (VarietyStock $stock) => [$stock->variety => $stock->availableQty()])
            ->all();
    }

    public function destroy(DispatchPlan $dispatch_plan)
    {
        if ($dispatch_plan->vehicleAssignments()->exists()) {
            throw ValidationException::withMessages(['dispatch_plan' => 'Remove all vehicles from this plan before deleting it.']);
        }

        $dispatch_plan->delete();

        ActivityLog::record('dispatch_plans', $dispatch_plan->id, 'delete');

        return to_route('dispatch-plans.index')->with('success', 'Dispatch plan deleted.');
    }

    /**
     * Custom (non-resource) route, so the {dispatchPlan} parameter is
     * whatever camelCase name I gave it in routes/web.php — no snake_case
     * mismatch concern here, unlike the resource-route methods above.
     */
    public function approve(DispatchPlan $dispatchPlan)
    {
        $this->authorize('approve', $dispatchPlan);

        $original = $dispatchPlan->only(['approval_status', 'approved_by', 'approved_at']);
        $dispatchPlan->update([
            'approval_status' => 'approved',
            'approved_by' => request()->user()->id,
            'approved_at' => now(),
        ]);

        ActivityLog::record('dispatch_plans', $dispatchPlan->id, 'approve', $original, $dispatchPlan->fresh()->only(['approval_status', 'approved_by', 'approved_at']));

        return back()->with('success', 'Dispatch plan approved — vehicles can now be added.');
    }

    public function reject(Request $request, DispatchPlan $dispatchPlan)
    {
        $this->authorize('reject', $dispatchPlan);

        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        $original = $dispatchPlan->only(['approval_status', 'rejection_reason']);
        $dispatchPlan->update([
            'approval_status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        ActivityLog::record('dispatch_plans', $dispatchPlan->id, 'reject', $original, $dispatchPlan->fresh()->only(['approval_status', 'rejection_reason']), $validated['rejection_reason']);

        return back()->with('success', 'Dispatch plan rejected.');
    }
}
