<?php

use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Dispatch;
use App\Models\DispatchPlan;
use App\Models\DispatchPlanItem;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use App\Models\VarietyStock;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLoading;

function planningUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

function planningBooking(string $paymentStatus = 'completed'): Booking
{
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);

    return planningBookingFor($dealer, $farmer, $paymentStatus);
}

function planningBookingFor(Dealer $dealer, ?Farmer $farmer = null, string $paymentStatus = 'completed'): Booking
{
    $farmer ??= Farmer::factory()->create(['dealer_id' => $dealer->id]);

    return Booking::factory()->create([
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'approval_status' => 'approved',
        'payment_status' => $paymentStatus,
        'plant_qty' => 400,
        'plant_rate' => 12,
    ]);
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('dispatch planner can create a plan and add a vehicle to it', function () {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-01-TEST', 'vehicle_type' => 'Truck', 'capacity' => 500, 'status' => true]);

    $response = $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'route' => 'North Zone',
    ]);

    $plan = DispatchPlan::first();
    $response->assertRedirect(route('dispatch-plans.show', $plan));
    expect($plan->plan_no)->toStartWith('PLN-'.now()->year.'-');
    expect($plan->approval_status)->toBe('pending');

    // Vehicles cannot be added before the plan is approved — Dispatch
    // Planner can approve their own plan per the module's decision.
    $this->actingAs($planner)->post(route('vehicle-assignments.store', $plan), [
        'vehicle_id' => $vehicle->id,
        'driver_name' => 'Ramesh',
        'start_km' => 1000,
    ])->assertForbidden();

    $this->actingAs($planner)->post(route('dispatch-plans.approve', $plan))->assertRedirect();
    expect($plan->fresh()->approval_status)->toBe('approved');

    $this->actingAs($planner)->post(route('vehicle-assignments.store', $plan), [
        'vehicle_id' => $vehicle->id,
        'driver_name' => 'Ramesh',
        'start_km' => 1000,
    ])->assertRedirect();

    expect(VehicleAssignment::where('dispatch_plan_id', $plan->id)->where('vehicle_id', $vehicle->id)->exists())->toBeTrue();
});

test('a plan can be rejected with a reason, and stays locked from adding vehicles', function () {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-11-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000008', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);

    $this->actingAs($planner)->post(route('dispatch-plans.reject', $plan), [
        'rejection_reason' => 'Route no longer needed today',
    ])->assertRedirect();

    expect($plan->fresh()->approval_status)->toBe('rejected');
    expect($plan->fresh()->rejection_reason)->toBe('Route no longer needed today');

    $this->actingAs($planner)->post(route('vehicle-assignments.store', $plan), [
        'vehicle_id' => $vehicle->id,
        'start_km' => 1000,
    ])->assertForbidden();
});

test('an already-approved plan can still be rejected with a reason, and existing vehicles are left untouched', function () {
    $planner = planningUser('dispatch-planner');
    $existingVehicle = Vehicle::create(['vehicle_no' => 'GJ-12-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000009', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $existingVehicle->id, 'created_by' => $planner->id]);

    $this->actingAs($planner)->post(route('dispatch-plans.reject', $plan), [
        'rejection_reason' => 'Wrong dealer assigned — approved by mistake',
    ])->assertRedirect();

    expect($plan->fresh()->approval_status)->toBe('rejected');
    expect($plan->fresh()->rejection_reason)->toBe('Wrong dealer assigned — approved by mistake');
    expect(VehicleAssignment::find($assignment->id))->not->toBeNull();

    // No new vehicles can be added once it's rejected again.
    $anotherVehicle = Vehicle::create(['vehicle_no' => 'GJ-13-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $this->actingAs($planner)->post(route('vehicle-assignments.store', $plan), [
        'vehicle_id' => $anotherVehicle->id,
        'start_km' => 1000,
    ])->assertForbidden();
});

test('a rejected plan cannot be rejected again', function () {
    $planner = planningUser('dispatch-planner');
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000010', 'plan_date' => now()->toDateString(), 'approval_status' => 'rejected', 'rejection_reason' => 'Original reason', 'created_by' => $planner->id]);

    $this->actingAs($planner)->post(route('dispatch-plans.reject', $plan), [
        'rejection_reason' => 'Second reason',
    ])->assertForbidden();
});

test('marketing can view plans but cannot create one', function () {
    $marketing = planningUser('marketing');

    $this->actingAs($marketing)->get(route('dispatch-plans.index'))->assertOk();
    $this->actingAs($marketing)->post(route('dispatch-plans.store'), ['plan_date' => now()->toDateString()])->assertForbidden();
});

test('supervisor can access the planning board — Dispatch Lifecycle spec has them view/edit/approve plans', function () {
    $supervisor = planningUser('supervisor');

    $this->actingAs($supervisor)->get(route('dispatch-plans.index'))->assertOk();
});

test('the same vehicle cannot be added twice to the same plan', function () {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-02-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000001', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);

    $this->actingAs($planner)->post(route('vehicle-assignments.store', $plan), [
        'vehicle_id' => $vehicle->id,
        'start_km' => 1000,
    ])->assertSessionHasErrors(['vehicle_id']);
});

test('a booking committed to the plan can be assigned to a vehicle, and then cannot be assigned to a second vehicle', function () {
    $planner = planningUser('dispatch-planner');
    $vehicleA = Vehicle::create(['vehicle_no' => 'GJ-03-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $vehicleB = Vehicle::create(['vehicle_no' => 'GJ-04-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000002', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $assignmentA = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicleA->id, 'created_by' => $planner->id]);
    $assignmentB = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicleB->id, 'created_by' => $planner->id]);
    $booking = planningBooking();
    // Bookings must be committed to the plan itself (Dealer -> Booking ->
    // Dispatch Quantity, done via farmer_estimates) before they're
    // assignable to any vehicle in it.
    $plan->farmerEstimates()->create(['dealer_id' => $booking->dealer_id, 'farmer_id' => $booking->farmer_id, 'booking_id' => $booking->id, 'plant_quantity' => $booking->plant_qty, 'dispatch_type' => 'full']);

    $this->actingAs($planner)->post(route('dispatch-plan-items.store', $assignmentA), [
        'booking_id' => $booking->id,
    ])->assertRedirect();

    expect(DispatchPlanItem::where('booking_id', $booking->id)->where('vehicle_assignment_id', $assignmentA->id)->exists())->toBeTrue();

    // Already assigned — no longer unassigned, so it's rejected as an invalid selection.
    $this->actingAs($planner)->post(route('dispatch-plan-items.store', $assignmentB), [
        'booking_id' => $booking->id,
    ])->assertSessionHasErrors(['booking_id']);
});

test('a booking not committed to the plan cannot be assigned to a vehicle directly', function () {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-05-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000003', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);
    $booking = planningBooking('partial');

    // Never committed to this plan via a farmer_estimates row (payment/
    // approval gating for bookings now happens earlier, at plan creation —
    // see DispatchPaymentGateTest), so it isn't a valid selection here.
    $this->actingAs($planner)->post(route('dispatch-plan-items.store', $assignment), [
        'booking_id' => $booking->id,
    ])->assertSessionHasErrors(['booking_id']);
});

test('a booking can be reassigned between vehicles in the same plan', function () {
    $planner = planningUser('dispatch-planner');
    $vehicleA = Vehicle::create(['vehicle_no' => 'GJ-06-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $vehicleB = Vehicle::create(['vehicle_no' => 'GJ-07-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000004', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $assignmentA = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicleA->id, 'created_by' => $planner->id]);
    $assignmentB = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicleB->id, 'created_by' => $planner->id]);
    $booking = planningBooking();
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignmentA->id, 'booking_id' => $booking->id, 'dispatch_qty' => $booking->plant_qty, 'created_by' => $planner->id]);

    $this->actingAs($planner)->put(route('dispatch-plan-items.reassign', $item), [
        'vehicle_assignment_id' => $assignmentB->id,
    ])->assertRedirect();

    expect($item->fresh()->vehicle_assignment_id)->toBe($assignmentB->id);
});

test('removing a booking from a vehicle frees it up for planning again', function () {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-08-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000005', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);
    $booking = planningBooking();
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => $booking->plant_qty, 'created_by' => $planner->id]);

    $this->actingAs($planner)->delete(route('dispatch-plan-items.destroy', $item))->assertRedirect();

    expect(DispatchPlanItem::where('booking_id', $booking->id)->exists())->toBeFalse();
});

test('a vehicle assignment with bookings still on it cannot be deleted', function () {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-09-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000006', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);
    $booking = planningBooking();
    DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => $booking->plant_qty, 'created_by' => $planner->id]);

    $this->actingAs(planningUser('super-admin'))->delete(route('vehicle-assignments.destroy', $assignment))
        ->assertSessionHasErrors(['vehicle_assignment']);

    expect($assignment->fresh())->not->toBeNull();
});

test('a plan with vehicles still on it cannot be deleted', function () {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-10-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000007', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);

    $this->actingAs(planningUser('super-admin'))->delete(route('dispatch-plans.destroy', $plan))
        ->assertSessionHasErrors(['dispatch_plan']);

    expect($plan->fresh())->not->toBeNull();
});

test('supervisor can toggle a booking loaded, which flips the vehicle from pending to loading', function () {
    $planner = planningUser('dispatch-planner');
    $supervisor = planningUser('supervisor');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-12-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000009', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);
    $booking = planningBooking();
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => $booking->plant_qty, 'created_by' => $planner->id]);

    expect($assignment->fresh()->loading_status)->toBe('pending');

    $this->actingAs($supervisor)->post(route('dispatch-plan-items.toggle-loaded', $item))->assertRedirect();

    expect($item->fresh()->isLoaded())->toBeTrue();
    expect($assignment->fresh()->loading_status)->toBe('loading');

    // Toggling again unmarks it.
    $this->actingAs($supervisor)->post(route('dispatch-plan-items.toggle-loaded', $item))->assertRedirect();
    expect($item->fresh()->isLoaded())->toBeFalse();
});

test('approving loading is blocked until every booking on the vehicle is marked loaded', function () {
    $planner = planningUser('dispatch-planner');
    $supervisor = planningUser('supervisor');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-13-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000010', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);
    $bookingA = planningBooking();
    $bookingB = planningBooking();
    $itemA = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $bookingA->id, 'dispatch_qty' => $bookingA->plant_qty, 'created_by' => $planner->id]);
    DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $bookingB->id, 'dispatch_qty' => $bookingB->plant_qty, 'created_by' => $planner->id]);

    $this->actingAs($supervisor)->post(route('dispatch-plan-items.toggle-loaded', $itemA));

    // Only one of the two items is loaded — approval must fail.
    $this->actingAs($supervisor)->post(route('vehicle-assignments.approve-loading', $assignment))
        ->assertSessionHasErrors(['loading']);

    expect(Dispatch::count())->toBe(0);
});

test('approving loading does not create a Dispatch — it only marks the vehicle available for dispatch', function () {
    $planner = planningUser('dispatch-planner');
    $supervisor = planningUser('supervisor');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-14-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000011', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'driver_name' => 'Ramesh', 'driver_mobile' => '9988776655', 'created_by' => $planner->id]);
    $bookingA = planningBooking();
    $bookingB = planningBooking();
    $itemA = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $bookingA->id, 'dispatch_qty' => $bookingA->plant_qty, 'created_by' => $planner->id]);
    $itemB = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $bookingB->id, 'dispatch_qty' => $bookingB->plant_qty, 'created_by' => $planner->id]);

    $this->actingAs($supervisor)->post(route('dispatch-plan-items.toggle-loaded', $itemA));
    $this->actingAs($supervisor)->post(route('dispatch-plan-items.toggle-loaded', $itemB));

    expect($assignment->fresh()->dispatch_status)->toBeNull();

    $this->actingAs($supervisor)->post(route('vehicle-assignments.approve-loading', $assignment))->assertRedirect();

    expect(Dispatch::count())->toBe(0);
    expect($itemA->fresh()->remaining_qty)->toBe($bookingA->plant_qty);
    expect($itemB->fresh()->remaining_qty)->toBe($bookingB->plant_qty);

    expect($assignment->fresh()->loading_status)->toBe('completed');
    expect($assignment->fresh()->dispatch_status)->toBe('available');
    expect(VehicleLoading::where('vehicle_assignment_id', $assignment->id)->first()->supervisor_id)->toBe($supervisor->id);

    // Available for a Dispatch role user to pick up and create a real
    // Dispatch from — DispatchService::eligibleAssignments() scoping.
    $this->actingAs(planningUser('dispatch'))->get(route('dispatches.create'))->assertSee($vehicle->vehicle_no);
});

test('approving loading is forbidden once already completed', function () {
    $planner = planningUser('dispatch-planner');
    $supervisor = planningUser('supervisor');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-15-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000012', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);
    $booking = planningBooking();
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => $booking->plant_qty, 'created_by' => $planner->id]);

    $this->actingAs($supervisor)->post(route('dispatch-plan-items.toggle-loaded', $item));
    $this->actingAs($supervisor)->post(route('vehicle-assignments.approve-loading', $assignment));

    $this->actingAs($supervisor)->post(route('vehicle-assignments.approve-loading', $assignment))->assertForbidden();
    expect($assignment->fresh()->loading_status)->toBe('completed');
});

test('the dispatch-planner role cannot mark loaded or approve loading — that is supervisor-only', function () {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-16-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000013', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);
    $booking = planningBooking();
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => $booking->plant_qty, 'created_by' => $planner->id]);

    $this->actingAs($planner)->post(route('dispatch-plan-items.toggle-loaded', $item))->assertForbidden();
    $this->actingAs($planner)->post(route('vehicle-assignments.approve-loading', $assignment))->assertForbidden();
});

test('the Dispatches page shows full dealer/farmer/booking/variety detail for a plan both before and after approval', function () {
    $planner = planningUser('dispatch-planner');
    $dealer = Dealer::factory()->create();
    $booking = planningBookingFor($dealer);

    $pendingPlan = DispatchPlan::create(['plan_no' => 'PLN-2026-000050', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $pendingPlan->farmerEstimates()->create(['dealer_id' => $dealer->id, 'farmer_id' => $booking->farmer_id, 'booking_id' => $booking->id, 'plant_quantity' => 400, 'dispatch_type' => 'full']);

    $approvedPlan = DispatchPlan::create(['plan_no' => 'PLN-2026-000051', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $planner->id]);
    $bookingB = planningBookingFor($dealer);
    $approvedPlan->farmerEstimates()->create(['dealer_id' => $dealer->id, 'farmer_id' => $bookingB->farmer_id, 'booking_id' => $bookingB->id, 'plant_quantity' => 400, 'dispatch_type' => 'full']);

    $response = $this->actingAs($planner)->get(route('dispatches.index'));

    $response->assertOk();
    $response->assertSee($dealer->dealer_name);
    $response->assertSee($booking->booking_no);
    $response->assertSee($booking->farmer->farmer_name);
    $response->assertSee($booking->variety);
    $response->assertSee($bookingB->booking_no);
    $response->assertSee($bookingB->farmer->farmer_name);
    $response->assertSee($bookingB->variety);
});

test('the create-booking modal marks a variety with no available stock as not available', function () {
    $planner = planningUser('dispatch-planner');
    VarietyStock::create(['variety' => 'G9', 'actual_qty' => 0]);
    VarietyStock::create(['variety' => 'Williams', 'actual_qty' => 500]);

    $response = $this->actingAs($planner)->get(route('dispatch-plans.create'));

    $response->assertOk();
    $response->assertSee('G9 — Not available', false);
    $response->assertSee('Williams — 500 available', false);
});

test('a plan can be created with multiple dealers, each with multiple bookings, and total dispatch quantity is summed automatically', function () {
    $planner = planningUser('dispatch-planner');
    $dealerA = Dealer::factory()->create();
    $dealerB = Dealer::factory()->create();
    $bookingA1 = planningBookingFor($dealerA);
    $bookingA2 = planningBookingFor($dealerA);
    $bookingB1 = planningBookingFor($dealerB);

    $response = $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $bookingA1->id, 'dispatch_qty' => 100],
            ['booking_id' => $bookingA2->id, 'dispatch_qty' => 150],
            ['booking_id' => $bookingB1->id, 'dispatch_qty' => 200],
        ],
    ]);

    $plan = DispatchPlan::first();
    $response->assertRedirect(route('dispatch-plans.show', $plan));

    expect($plan->farmerEstimates()->count())->toBe(3);
    expect($plan->farmerEstimates->sum('plant_quantity'))->toBe(450);
    expect($plan->farmerEstimates()->where('booking_id', $bookingA1->id)->first()->dealer_id)->toBe($dealerA->id);
    expect($plan->farmerEstimates()->where('booking_id', $bookingB1->id)->first()->dealer_id)->toBe($dealerB->id);
});

test('editing a plan prefills each row\'s dispatch quantity and the total from existing farmer estimates', function () {
    $planner = planningUser('dispatch-planner');
    $dealer = Dealer::factory()->create();
    $bookingA = planningBookingFor($dealer);
    $bookingB = planningBookingFor($dealer);

    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000099', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $plan->farmerEstimates()->create(['dealer_id' => $dealer->id, 'farmer_id' => $bookingA->farmer_id, 'booking_id' => $bookingA->id, 'plant_quantity' => 120, 'dispatch_type' => 'partial']);
    $plan->farmerEstimates()->create(['dealer_id' => $dealer->id, 'farmer_id' => $bookingB->farmer_id, 'booking_id' => $bookingB->id, 'plant_quantity' => 80, 'dispatch_type' => 'partial']);

    $response = $this->actingAs($planner)->get(route('dispatch-plans.edit', $plan));
    $response->assertOk();

    // Js::from() renders JSON.parse('...') with quotes escaped as ".
    $html = str_replace('\\u0022', '"', $response->getContent());

    expect($html)->toContain('"dispatchQty":"120"');
    expect($html)->toContain('"dispatchQty":"80"');
    expect($html)->not->toContain('"qty":');
});

test('duplicate bookings in the same plan submission are rejected', function () {
    $planner = planningUser('dispatch-planner');
    $booking = planningBooking();

    $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 100],
            ['booking_id' => $booking->id, 'dispatch_qty' => 50],
        ],
    ])->assertSessionHasErrors();

    expect(DispatchPlan::count())->toBe(0);
});

test('a nonexistent booking_id in the estimate breakdown is rejected', function () {
    $planner = planningUser('dispatch-planner');

    $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => 999999, 'dispatch_qty' => 100],
        ],
    ])->assertSessionHasErrors();

    expect(DispatchPlan::count())->toBe(0);
});

test('a dispatch quantity greater than the booking balance is rejected', function () {
    $planner = planningUser('dispatch-planner');
    $booking = planningBooking();

    $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => $booking->plant_qty + 1],
        ],
    ])->assertSessionHasErrors();

    expect(DispatchPlan::count())->toBe(0);
});

test('a vehicle assignment on an approved plan can have its vehicle changed', function () {
    $planner = planningUser('dispatch-planner');
    $vehicleA = Vehicle::create(['vehicle_no' => 'GJ-17-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $vehicleB = Vehicle::create(['vehicle_no' => 'GJ-18-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000015', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicleA->id, 'driver_name' => 'Original Driver', 'created_by' => $planner->id]);

    $this->actingAs($planner)->put(route('vehicle-assignments.update', $assignment), [
        'vehicle_id' => $vehicleB->id,
        'driver_name' => 'New Driver',
        'start_km' => 1000,
    ])->assertRedirect();

    expect($assignment->fresh()->vehicle_id)->toBe($vehicleB->id);
    expect($assignment->fresh()->driver_name)->toBe('New Driver');
});

test('a vehicle assignment with no bookings can be deleted from an approved plan', function () {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-19-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000016', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);

    $this->actingAs(planningUser('super-admin'))->delete(route('vehicle-assignments.destroy', $assignment))->assertRedirect();

    expect(VehicleAssignment::find($assignment->id))->toBeNull();
});

test('updating a plan replaces its booking estimates entirely', function () {
    $planner = planningUser('dispatch-planner');
    $bookingOld = planningBooking();
    $bookingNew = planningBooking();
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000014', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $plan->farmerEstimates()->create(['dealer_id' => $bookingOld->dealer_id, 'farmer_id' => $bookingOld->farmer_id, 'booking_id' => $bookingOld->id, 'plant_quantity' => 300, 'dispatch_type' => 'partial']);

    $this->actingAs($planner)->put(route('dispatch-plans.update', $plan), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $bookingNew->id, 'dispatch_qty' => 75],
        ],
    ])->assertRedirect(route('dispatch-plans.show', $plan));

    $plan->refresh();
    expect($plan->farmerEstimates()->count())->toBe(1);
    expect($plan->farmerEstimates->first()->booking_id)->toBe($bookingNew->id);
    expect($plan->farmerEstimates->sum('plant_quantity'))->toBe(75);
});

test('start_km is captured when adding a vehicle and can be changed later', function () {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-20-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000017', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $planner->id]);

    $this->actingAs($planner)->post(route('vehicle-assignments.store', $plan), [
        'vehicle_id' => $vehicle->id,
        'start_km' => 1000,
    ])->assertRedirect();

    $assignment = VehicleAssignment::where('dispatch_plan_id', $plan->id)->first();
    expect($assignment->start_km)->toBe(1000);

    $this->actingAs($planner)->put(route('vehicle-assignments.update', $assignment), [
        'vehicle_id' => $vehicle->id,
        'start_km' => 1050,
    ])->assertRedirect();

    expect($assignment->fresh()->start_km)->toBe(1050);
});

test('dispatch planner can register a not-yet-known vehicle and it is auto-assigned back to the plan', function () {
    $planner = planningUser('dispatch-planner');
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000018', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $planner->id]);

    $response = $this->actingAs($planner)->post(route('vehicles.store'), [
        'vehicle_no' => 'GJ-99-NEWREG',
        'status' => true,
        'cost_per_km' => 15,
        'return_dispatch_plan_id' => $plan->id,
    ]);

    $response->assertRedirect(route('dispatches.index'));

    $vehicle = Vehicle::where('vehicle_no', 'GJ-99-NEWREG')->first();
    expect($vehicle)->not->toBeNull();
    expect(VehicleAssignment::where('dispatch_plan_id', $plan->id)->where('vehicle_id', $vehicle->id)->exists())->toBeTrue();
});

test('a marketing role cannot register a new vehicle even with a return_dispatch_plan_id', function () {
    $marketing = planningUser('marketing');
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000019', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $marketing->id]);

    $this->actingAs($marketing)->post(route('vehicles.store'), [
        'vehicle_no' => 'GJ-99-BLOCKED',
        'status' => true,
        'return_dispatch_plan_id' => $plan->id,
    ])->assertForbidden();

    expect(Vehicle::where('vehicle_no', 'GJ-99-BLOCKED')->exists())->toBeFalse();
});

test('a vehicle number is always stored and matched in uppercase, regardless of the casing typed', function () {
    $planner = planningUser('dispatch-planner');
    $admin = planningUser('admin');

    $this->actingAs($planner)->post(route('vehicles.store'), [
        'vehicle_no' => 'gj23cg9999',
        'status' => true,
        'cost_per_km' => 15,
    ])->assertRedirect();

    $vehicle = Vehicle::where('vehicle_no', 'GJ23CG9999')->first();
    expect($vehicle)->not->toBeNull();
    expect(Vehicle::where('vehicle_no', 'gj23cg9999')->exists())->toBeFalse();

    $this->actingAs($admin)->put(route('vehicles.update', $vehicle), [
        'vehicle_no' => 'gj23cg8888',
        'status' => true,
        'cost_per_km' => 15,
    ])->assertRedirect();

    expect($vehicle->fresh()->vehicle_no)->toBe('GJ23CG8888');
});

test('marking a vehicle loaded is blocked with a clear error until a booking is assigned to it', function () {
    $planner = planningUser('dispatch-planner');
    $supervisor = planningUser('supervisor');
    $booking = planningBooking();
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-01-NOBOOK', 'vehicle_type' => 'Truck', 'status' => true]);

    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000020', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $planner->id]);
    $plan->farmerEstimates()->create(['dealer_id' => $booking->dealer_id, 'farmer_id' => $booking->farmer_id, 'booking_id' => $booking->id, 'plant_quantity' => 100, 'dispatch_type' => 'partial']);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);

    // Committing a booking to the plan (Dealer -> Booking -> Dispatch Quantity)
    // doesn't put it on any particular vehicle — that's a separate, explicit
    // "assign a booking" step per vehicle. Loading before that step is done
    // must fail with a message pointing at what's missing, not a generic error.
    $this->actingAs($supervisor)->post(route('vehicle-assignments.load-vehicle', $assignment))
        ->assertSessionHasErrors(['loading' => 'Add at least one booking to this vehicle before marking it loaded.']);

    $this->actingAs($planner)->post(route('dispatch-plan-items.store', $assignment), [
        'booking_id' => $booking->id,
        'dispatch_qty' => 100,
    ])->assertSessionHasNoErrors();

    $this->actingAs($supervisor)->post(route('vehicle-assignments.load-vehicle', $assignment))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($assignment->fresh()->loading_status)->toBe('completed');
});

test('approving loading redirects straight into Dispatch creation for that vehicle', function () {
    $supervisor = planningUser('supervisor');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-21-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000020', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $supervisor->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $supervisor->id]);
    $booking = planningBooking();
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => $booking->plant_qty, 'created_by' => $supervisor->id]);
    $this->actingAs($supervisor)->post(route('dispatch-plan-items.toggle-loaded', $item));

    $this->actingAs($supervisor)->post(route('vehicle-assignments.approve-loading', $assignment))
        ->assertRedirect(route('dispatches.create', ['vehicle_assignment_id' => $assignment->id]));
});

test('supervisor can carry a vehicle from loading approval through to dispatched and delivered, but not complete', function () {
    $supervisor = planningUser('supervisor');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-22-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000021', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $supervisor->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $supervisor->id]);
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);
    $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'farmer_id' => $farmer->id, 'approval_status' => 'approved', 'payment_status' => 'completed', 'plant_qty' => 300, 'plant_rate' => 10]);
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => 300, 'created_by' => $supervisor->id]);
    $this->actingAs($supervisor)->post(route('dispatch-plan-items.toggle-loaded', $item));
    $this->actingAs($supervisor)->post(route('vehicle-assignments.approve-loading', $assignment));

    $this->actingAs($supervisor)->post(route('dispatches.store'), [
        'vehicle_assignment_id' => $assignment->id,
        'dispatch_date' => now()->toDateString(),
        'lines' => [$item->id => ['qty' => 300]],
    ])->assertRedirect();

    $dispatch = Dispatch::first();
    expect($dispatch->status)->toBe('draft');

    $this->actingAs($supervisor)->post(route('dispatches.submit', $dispatch))->assertRedirect();
    expect($dispatch->fresh()->status)->toBe('pending');

    $this->actingAs($supervisor)->post(route('dispatches.start-loading', $dispatch))->assertRedirect();
    expect($dispatch->fresh()->status)->toBe('loading');

    $this->actingAs($supervisor)->post(route('dispatches.vehicle-out', $dispatch))->assertRedirect();
    $dispatch->refresh();
    expect($dispatch->status)->toBe('dispatched');
    expect($dispatch->challan_no)->not->toBeNull();

    // Dispatch Lifecycle spec: Handling Supervisor owns the whole operation
    // through delivery, but "Complete dispatch" (the stock-conversion step)
    // stays Dispatch-role/Admin-only.
    $this->actingAs($supervisor)->post(route('dispatches.deliver', $dispatch))->assertRedirect();
    expect($dispatch->fresh()->status)->toBe('delivered');
    $this->actingAs($supervisor)->post(route('dispatches.complete', $dispatch))->assertForbidden();
});

// ==========================================================================
// Dispatch Lifecycle spec: Accounts creates plans, Supervisor reviews/
// approves, auto-assign replaces the manual "assign booking to vehicle"
// step, and delivery/return/inspection gain new roles and fields.
// ==========================================================================

test('accounts can create a dispatch plan, added alongside dispatch-planner not replacing it', function () {
    $accounts = planningUser('accounts');
    $booking = planningBooking();

    $response = $this->actingAs($accounts)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'route' => 'North Zone',
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 400],
        ],
    ]);

    $plan = DispatchPlan::first();
    $response->assertRedirect(route('dispatch-plans.show', $plan));
    expect($plan->created_by)->toBe($accounts->id);
});

test('supervisor can view, edit and approve a plan created by accounts', function () {
    $accounts = planningUser('accounts');
    $supervisor = planningUser('supervisor');
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000030', 'plan_date' => now()->toDateString(), 'created_by' => $accounts->id]);

    $this->actingAs($supervisor)->get(route('dispatch-plans.show', $plan))->assertOk();

    $this->actingAs($supervisor)->put(route('dispatch-plans.update', $plan), [
        'plan_date' => now()->toDateString(),
        'route' => 'Updated by supervisor',
    ])->assertRedirect();
    expect($plan->fresh()->route)->toBe('Updated by supervisor');

    $this->actingAs($supervisor)->post(route('dispatch-plans.approve', $plan))->assertRedirect();
    expect($plan->fresh()->approval_status)->toBe('approved');
});

test('adding a vehicle auto-assigns every unassigned committed booking, with no manual assign step', function () {
    $supervisor = planningUser('supervisor');
    $dealer = Dealer::factory()->create();
    $bookingA = planningBookingFor($dealer);
    $bookingB = planningBookingFor($dealer);
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-30-AUTO', 'vehicle_type' => 'Truck', 'status' => true]);

    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000031', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $supervisor->id]);
    $plan->farmerEstimates()->create(['dealer_id' => $dealer->id, 'farmer_id' => $bookingA->farmer_id, 'booking_id' => $bookingA->id, 'plant_quantity' => 400, 'dispatch_type' => 'full']);
    $plan->farmerEstimates()->create(['dealer_id' => $dealer->id, 'farmer_id' => $bookingB->farmer_id, 'booking_id' => $bookingB->id, 'plant_quantity' => 400, 'dispatch_type' => 'full']);

    $this->actingAs($supervisor)->post(route('vehicle-assignments.store', $plan), [
        'vehicle_id' => $vehicle->id,
        'start_km' => 1000,
    ])->assertRedirect();

    $assignment = VehicleAssignment::where('dispatch_plan_id', $plan->id)->first();
    expect($assignment->items()->count())->toBe(2);
    expect($assignment->items()->pluck('booking_id')->sort()->values()->all())->toBe([$bookingA->id, $bookingB->id]);
});

test('a second vehicle on the same plan only picks up whatever booking is still unassigned', function () {
    $supervisor = planningUser('supervisor');
    $dealer = Dealer::factory()->create();
    $bookingA = planningBookingFor($dealer);
    $bookingB = planningBookingFor($dealer);
    $vehicleA = Vehicle::create(['vehicle_no' => 'GJ-31-AUTO', 'vehicle_type' => 'Truck', 'status' => true]);
    $vehicleB = Vehicle::create(['vehicle_no' => 'GJ-32-AUTO', 'vehicle_type' => 'Truck', 'status' => true]);

    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-000032', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $supervisor->id]);
    $plan->farmerEstimates()->create(['dealer_id' => $dealer->id, 'farmer_id' => $bookingA->farmer_id, 'booking_id' => $bookingA->id, 'plant_quantity' => 400, 'dispatch_type' => 'full']);
    $plan->farmerEstimates()->create(['dealer_id' => $dealer->id, 'farmer_id' => $bookingB->farmer_id, 'booking_id' => $bookingB->id, 'plant_quantity' => 400, 'dispatch_type' => 'full']);

    $this->actingAs($supervisor)->post(route('vehicle-assignments.store', $plan), ['vehicle_id' => $vehicleA->id, 'start_km' => 1000])->assertRedirect();
    $this->actingAs($supervisor)->post(route('vehicle-assignments.store', $plan), ['vehicle_id' => $vehicleB->id, 'start_km' => 1000])->assertRedirect();

    $assignmentA = VehicleAssignment::where('vehicle_id', $vehicleA->id)->first();
    $assignmentB = VehicleAssignment::where('vehicle_id', $vehicleB->id)->first();

    expect($assignmentA->items()->count())->toBe(2);
    expect($assignmentB->items()->count())->toBe(0);
});

test('company employee (staff role), marketing, dealer and accounts can each mark a dispatch delivered with receiver details', function (string $roleName) {
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-40-DELIVER-'.$roleName, 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-DEL-'.$roleName, 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);

    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);
    $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'farmer_id' => $farmer->id, 'approval_status' => 'approved', 'payment_status' => 'completed', 'plant_qty' => 200, 'plant_rate' => 10]);
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => 200, 'created_by' => $planner->id]);

    $dispatch = app(\App\Services\DispatchService::class)->createFromAssignment($assignment->fresh(['items']), [$item->id => ['qty' => 200]], $planner);
    app(\App\Services\DispatchService::class)->submit($dispatch);
    app(\App\Services\DispatchService::class)->startLoading($dispatch, $planner);
    app(\App\Services\DispatchService::class)->vehicleOut($dispatch, $planner);

    $roleUser = match ($roleName) {
        'marketing' => tap(planningUser('marketing'), fn ($u) => \App\Models\DealerAssignment::create(['dealer_id' => $dealer->id, 'marketing_user_id' => $u->id, 'assigned_date' => now(), 'status' => true])),
        'dealer' => tap(planningUser('dealer'), fn ($u) => $u->update(['dealer_id' => $dealer->id])),
        default => planningUser($roleName),
    };

    $response = $this->actingAs($roleUser)->post(route('dispatches.deliver', $dispatch), [
        'delivery_location' => 'Farm gate, Anand',
        'receiver_name' => 'Ramesh Patel',
        'receiver_mobile' => '9876543210',
        'receiver_remarks' => 'Received in good condition',
    ]);

    $response->assertRedirect();

    $dispatch->refresh();
    expect($dispatch->status)->toBe('delivered');
    expect($dispatch->delivery_location)->toBe('Farm gate, Anand');
    expect($dispatch->receiver_name)->toBe('Ramesh Patel');
    expect($dispatch->receiver_mobile)->toBe('9876543210');
    expect($dispatch->delivered_by)->toBe($roleUser->id);
    expect($dispatch->delivered_at)->not->toBeNull();
})->with(['staff', 'accounts', 'marketing', 'dealer']);

test('vehicle returned then return inspection records the new missing/broken/dead/extra breakdown', function () {
    $supervisor = planningUser('supervisor');
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-41-RETURN', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-RETURN1', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);

    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);
    $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'farmer_id' => $farmer->id, 'approval_status' => 'approved', 'payment_status' => 'completed', 'plant_qty' => 400, 'plant_rate' => 10]);
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => 400, 'created_by' => $planner->id]);

    $service = app(\App\Services\DispatchService::class);
    $dispatch = $service->createFromAssignment($assignment->fresh(['items']), [$item->id => ['qty' => 400, 'qty_per_crate' => 40]], $planner);
    $service->submit($dispatch);
    $service->startLoading($dispatch, $planner);
    $service->vehicleOut($dispatch, $planner);

    $this->actingAs($supervisor)->post(route('dispatches.vehicle-returned', $dispatch), ['odometer_end' => 500])->assertRedirect();
    $dispatch->refresh();
    expect($dispatch->vehicle_returned_by)->toBe($supervisor->id);
    expect($dispatch->vehicle_status_label)->toBe('Available');

    $line = $dispatch->lines->first();
    $this->actingAs($supervisor)->post(route('dispatches.record-return', $dispatch), [
        'lines' => [
            $line->id => [
                'returned_qty' => 7,
                'damage_qty' => 1,
                'missing_qty' => 1,
                'broken_qty' => 1,
                'dead_plant_qty' => 5,
                'extra_returned_qty' => 2,
            ],
        ],
        'vehicle_returned_at' => now()->toDateString(),
        'return_remarks' => 'All crates accounted for',
        'damage_remarks' => 'One crate lid cracked',
        'driver_remarks' => 'Smooth trip',
        'supervisor_remarks' => 'Verified on arrival',
    ])->assertRedirect();

    $line->refresh();
    expect((float) $line->crates_returned)->toBe(7.0);
    expect((float) $line->missing_qty)->toBe(1.0);
    expect((float) $line->broken_qty)->toBe(1.0);
    expect($line->dead_plant_qty)->toBe(5);
    expect($line->extra_returned_qty)->toBe(2);

    $dispatch->refresh();
    expect($dispatch->damage_remarks)->toBe('One crate lid cracked');
    expect($dispatch->driver_remarks)->toBe('Smooth trip');
    expect($dispatch->supervisor_remarks)->toBe('Verified on arrival');
});

test('the granular loading checklist backend still works even though it is no longer linked from the plan page', function () {
    $supervisor = planningUser('supervisor');
    $planner = planningUser('dispatch-planner');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-42-CHECKLIST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-CHECK1', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved', 'created_by' => $planner->id]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'created_by' => $planner->id]);
    $booking = planningBooking();
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => 400, 'created_by' => $planner->id]);

    $this->actingAs($supervisor)->post(route('dispatch-plan-items.toggle-loaded', $item))->assertRedirect();
    expect($item->fresh()->isLoaded())->toBeTrue();

    $this->actingAs($supervisor)->post(route('vehicle-assignments.approve-loading', $assignment))->assertRedirect();
    expect($assignment->fresh()->loading_status)->toBe('completed');
});
